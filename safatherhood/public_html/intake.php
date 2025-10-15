<?php


/**
 * SAFATHERHOOD – BIPP Virtual Intake Packet
 * ------------------------------------
 * • GET  : render multi‑step form  (HTML follows this PHP section)
 * • POST : CSRF check → validate → INSERT into clinicnotepro_safatherhood.intake_packet
 *          → e‑mail staff → confirmation screen
 *
 * Uses the **shared** /config/config.php instead of hard‑coded creds.
 */

declare(strict_types=1);
ob_start();
session_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

/* ------------------------------------------------------------------ */
/* 0.  CONFIG + DB CONNECTION                                          */
/* ------------------------------------------------------------------ */
const ADMIN_ALERT_EMAILS = [
  'admin@notesao.com',
  'amandag@aitscm.org',
  'nathaliaa@aitscm.org',
  'isaiahr@aitscm.org',
  'albertc@aitscm.org',
];

// (optional) back-compat if other code still uses the old constant
if (!defined('ADMIN_ALERT_EMAIL') && !empty(ADMIN_ALERT_EMAILS)) {
  define('ADMIN_ALERT_EMAIL', ADMIN_ALERT_EMAILS[0]);
}

require_once dirname(__DIR__) . '/config/config.php';   // provides $link + $default_program_id
/** @var mysqli $link */
$db = $link;
$db->set_charset('utf8mb4');

/* ------------------------------------------------------------------ */
/* 1.  HELPERS                                                         */
/* ------------------------------------------------------------------ */
function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_check(): void
{
    $sent = (string)($_POST['csrf_token'] ?? '');
    $sess = (string)($_SESSION['csrf'] ?? '');
    if ($sent === '' || $sess === '' || !hash_equals($sess, $sent)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

/* trim() all scalars, return null if key missing ---------------------------------------------- */
function postv(string $k): ?string { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : null; }
/* check‑box / radio → tinyint(1) ---------------------------------------------------------------- */
function postb(string $k): int     { return (isset($_POST[$k]) && $_POST[$k]) ? 1 : 0; }

/* send a proper HTTP code + message and stop */
function fail(string $msg, int $code = 422): void {
    http_response_code($code);
    exit("<h3>{$msg}</h3>");
}

/* whitelist enumerations */
function post_enum(string $k, array $allowed): ?string {
    $v = postv($k);
    return $v !== null && in_array($v, $allowed, true) ? $v : null;
}

/* email validation (nullable) */
function post_email(string $k): ?string {
    $v = postv($k);
    return $v && filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : null;
}


/* ------------------------------------------------------------------ */
/* 2.  POST  – SAVE INTAKE                                            */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // ---- Bare-minimum fields that must never be empty
    foreach (['first_name','last_name','date_of_birth','email','phone_cell','digital_signature',
              'referral_type_id','additional_charge_details','discipline_desc','last_substance_use'] as $req) {
        $v = postv($req);
        if ($v === null || $v === '') fail("Missing required field: $req");
    }



    // Required client consents (defend against scripted POSTs)
    foreach ([
      'agree_confidentiality',       // Page 1
      'agree_disclosure',            // 8a
      'agree_disclosure_partners',   // 8b  (correct key)
      'agree_program_8c',            // 8c  (program agreement)
      'agree_responsibility_8c',     // 8c  (taking responsibility)
      'agree_termination_8e',        // 8e
    ] as $ck) {
      if (postb($ck) !== 1) fail('You must accept all required agreements.');
    }
    // DO NOT require "agree_virtual" here – it’s derived, not a checkbox.
    // Page 1 signature + date (required with confidentiality consent)
    $consent_p1_signature = trim((string) postv('consent_p1_signature'));
    $consent_p1_date_raw  = postv('consent_p1_date');

    if ($consent_p1_signature === '' || !$consent_p1_date_raw) {
        fail('Please sign and date the Page 1 Statement of Confidentiality and Consent for Treatment.');
    }

    $consent_p1_date_ts = strtotime($consent_p1_date_raw);
    if ($consent_p1_date_ts === false) {
        fail('Invalid date on Page 1 Statement of Confidentiality and Consent for Treatment.');
    }
    $consent_p1_date = date('Y-m-d', $consent_p1_date_ts);


    // Server-side required fields for address + emergency contact
    foreach (['address_street','address_city','address_state','address_zip',
              'emergency_name','emergency_phone','emergency_relation'] as $req) {
      $v = postv($req);
      if ($v === null || $v === '') fail("Missing required field: $req");
    }



    // Validate core enums
    $gender = post_enum('gender_id', ['1','2','3']);
    if ($gender === null) fail('Bad or missing gender.');

    $referral_type_id = post_enum('referral_type_id', ['0','1','2','3','4','5','6']);
    if ($referral_type_id === null) fail('Bad or missing referral type.');

    // Email validation (primary required, officer optional)
    if (!post_email('email')) fail('Please provide a valid email address.');
    $officerEmail = post_email('referring_officer_email'); // nullable


    /* ------------------------------------------------------------------ */
    /* Page 7 – Victim info, Sworn Statement, assessment items (POST)     */
    /* ------------------------------------------------------------------ */

    /** victim_knowledge: '0' = NO knowledge, '1' = HAS knowledge
     *  Default to '0' if not selected (matches your working example)
     */
    $vk = post_enum('victim_knowledge', ['0','1']); // '0' | '1' | null
    if ($vk !== '0' && $vk !== '1') {
        $vk = '0';
    }

    /* -------------------- Sworn Statement (server-side) -----------------
    * Required ONLY when victim_knowledge === '0' (no knowledge).
    * Otherwise, store NULLs.
    */
    $sworn_sig_name = trim((string) postv('sworn_sig_name'));     // <input name="sworn_sig_name">
    $sworn_date_raw = postv('sworn_signed_date');                 // <input name="sworn_signed_date">
    $needSworn      = ($vk === '0');

    if ($needSworn) {
        if ($sworn_sig_name === '' || !$sworn_date_raw) {
            fail('Please sign and date the Sworn Statement.');
        }
        $ts = strtotime($sworn_date_raw);
        if ($ts === false) {
            fail('Invalid Sworn Statement date.');
        }
        $sworn_date = date('Y-m-d', $ts);
    } else {
        // Has knowledge OR not selected → sworn is optional and not stored
        $sworn_sig_name = null;
        $sworn_date     = null;
    }

    /* -------------------------- Victim contact -------------------------- */
    /** Relationship is optional (capture but don’t fail if blank) */
    $rel = trim((string) postv('victim_relationship'));

    /** Field lists */
    $victimContactAll = [
      'victim_first_name','victim_last_name','victim_gender','victim_dob','victim_age',
      'victim_phone','victim_email','victim_address','victim_city','victim_state','victim_zip'
    ];

    /** Helper: detect if any contact field is present */
    $anyProvided = false;
    foreach ($victimContactAll as $k) {
        $v = postv($k);
        if ($v !== null && $v !== '') { $anyProvided = true; break; }
    }

    if ($vk === '1') {
        // HAS knowledge → require core fields
        foreach (['victim_first_name','victim_last_name','victim_gender','victim_phone'] as $k) {
            $v = postv($k);
            if ($v === null || $v === '') {
                fail("Missing required field (Page 7): $k");
            }
        }
        $vdob = postv('victim_dob');
        $vage = postv('victim_age');
        if (($vdob === null || $vdob === '') && ($vage === null || $vage === '')) {
            fail('Please provide the victim’s DOB or an estimated age.');
        }
    } else {
        // NO knowledge → do NOT require contact info.
        // If user typed any contact anyway, enforce a minimal consistent set.
        if ($anyProvided) {
            foreach (['victim_first_name','victim_last_name','victim_gender'] as $k) {
                $v = postv($k);
                if ($v === null || $v === '') {
                    fail("Missing required field (Page 7): $k");
                }
            }
            $vdob = postv('victim_dob');
            $vage = postv('victim_age');
            if (($vdob === null || $vdob === '') && ($vage === null || $vage === '')) {
                fail('Please provide the victim’s DOB or an estimated age.');
            }
        }
    }

    /* ----------------- Relationship assessment scale items -------------- */
    $freqScale = ['Never','Sometimes','Often','Frequently','Very Frequently'];

    $focus = post_enum('focus_on_actions', $freqScale);
    if ($focus === null) {
        fail('Please answer: How often do/did you focus on their actions, whereabouts, and friends?');
    }

    $threats = post_enum('long_term_assault_thoughts', $freqScale);
    if ($threats === null) {
        fail('Please answer: Do you have/had any long-term thoughts of assaulting or threatening them?');
    }

    /* ----------------- “Other” reason free-text requirement ------------- */
    $otherText = postv('other_reason_text');
    if (in_array('Other', $_POST['reasons'] ?? [], true) && ($otherText === null || $otherText === '')) {
        fail('Please explain the “Other” reason.');
    }

    /*  Notes:
    *  - Ensure your $fields array includes:
    *      'sworn_sig_name'    => $sworn_sig_name,
    *      'sworn_signed_date' => $sworn_date,
    *    (You said you already added these—great.)
    *
    *  - Release of Information validation/mapping stays where you already have it.
    */


    // $gender already validated above
    $program_id = ($gender === '3') ? 3 : 2;

    // --- derive 8d Virtual Group Rules consent ---
    $agree_virtual = 1;
    for ($i = 1; $i <= 19; $i++) {
        if (postv("vgr_initial_$i") === null || postv("vgr_initial_$i") === '') {
            $agree_virtual = 0; break;
        }
    }
    if ($agree_virtual === 1) {
        if (!postv('vgr_signature_8d') || !postv('vgr_date_8d')) {
            $agree_virtual = 0;
        }
    }

    // ---- helpers (put near other helpers, or inline here) ----
    /** Return Y-m-d if $v parses, else null */
    function norm_ymd(?string $v): ?string {
        if (!$v) return null;
        $ts = strtotime($v);
        return $ts === false ? null : date('Y-m-d', $ts);
    }

    /** Clamp $sig to valid range vs $intake and (optionally) today */
    function enforce_signature_date(?string $sig_ymd, string $intake_ymd): string {
        $sig_ts    = strtotime($sig_ymd);
        $intake_ts = strtotime($intake_ymd);
        if ($sig_ts === false) return $intake_ymd;                // fallback to intake date

        // If signature is after intake, clamp to intake
        if ($sig_ts > $intake_ts) return $intake_ymd;

        // Optional: prevent future signature dates relative to "today"
        // $today = strtotime(date('Y-m-d'));
        // if ($sig_ts > $today) return date('Y-m-d', $today);

        return date('Y-m-d', $sig_ts);
    }

    // ---- normalize & validate intake/signature dates (server-side) ----
    $intake_date_raw_in    = postv('intake_date');
    $signature_date_raw_in = postv('signature_date');

    // ---- enforce server-side dates ----
    $intake_date_ymd    = postv('intake_date') ?: date('Y-m-d'); // user can pick, fallback today
    $signature_date_ymd = date('Y-m-d');                        // always today (server decides)


    // Enforce legal ordering: signature ≤ intake

    // --- Page 7 – Consent for Release of Information (required) ---
    $release_sig_name = trim(postv('consent_release_sig_name'));
    $release_date_raw = postv('consent_release_signed_date');

    if (!$release_sig_name || !$release_date_raw) {
        fail('Please sign and date the Consent for Release of Information.');
    }
    $ts = strtotime($release_date_raw);
    if ($ts === false) {
        fail('Invalid date for Consent for Release of Information.');
    }
    $release_date = date('Y-m-d', $ts);




    /* ---- Map every form control → DB column ------------------------- */
    $fields = [
        /* Identity ---------------------------------------------------- */
        'first_name'            => postv('first_name'),
        'last_name'             => postv('last_name'),
        'email'                 => postv('email'),
        'phone_cell'            => postv('phone_cell'),
        'date_of_birth'         => postv('date_of_birth'),
        'gender_id'             => $gender,
        'program_id'            => $program_id,
        'id_number'             => postv('id_number'),

        /* Address ------------------------------------------------------ */
        'address_street'        => postv('address_street'),
        'address_city'          => postv('address_city'),
        'address_state'         => postv('address_state'),
        'address_zip'           => postv('address_zip'),
        'birth_city'            => postv('birth_city'),
        'race_id'                  => postv('race_id'),
        'education_level'       => postv('education_level'),

        /* Employment --------------------------------------------------- */
        'employed'              => postb('employed'),
        'employer'              => postv('employer'),
        'occupation'            => postv('occupation'),

        /* Emergency / military ---------------------------------------- */
        'emergency_name'        => postv('emergency_name'),
        'emergency_phone'       => postv('emergency_phone'),
        'emergency_relation'    => postv('emergency_relation'),
        'military_branch'       => postv('military_branch'),
        'military_date'         => postv('military_date'),

        /* Referral ----------------------------------------------------- */
        'referral_type_id'         => $referral_type_id,
        'referring_officer_name'   => postv('referring_officer_name'),
        'referring_officer_email'  => $officerEmail,
        'referring_officer_phone'  => postv('referring_officer_phone'),
        'referring_cause_number'    => postv('referring_cause_number'),
        'additional_charge_dates'  => postv('additional_charge_dates'),
        'additional_charge_details'=> postv('additional_charge_details'),

        /* Household / children ----------------------------------------- */
        'living_situation'      => postv('living_situation'),
        'marital_status'        => postv('marital_status'),
        'has_children'          => postb('has_children'),
        'children_live_with_you'=> postb('children_live_with_you'),
        'children_names_ages'   => postv('children_names_ages'),
        'child_abuse_physical'  => postb('abused_physically'),
        'child_abuse_sexual'    => postb('abused_sexually'),
        'child_abuse_emotional' => postb('abused_emotionally'),
        'child_abuse_neglect'   => postb('children_neglected'),
        'cps_notified'          => postb('cps_notified'),
        'cps_care'              => postb('cps_care'),
        'discipline_desc'       => postv('discipline_desc'),
        'cps_case_year_status'  => postv('cps_case_year_status'),
        'cps_caseworker_contact'=> postv('cps_caseworker_contact'),


        /* Substance use ------------------------------------------------ */
        'last_substance_use'     => postv('last_substance_use'),
        'alcohol_past'          => postb('alcohol_past'),
        'alcohol_frequency'  => postv('alcohol_frequency'),
        'alcohol_current'       => postb('alcohol_current'),
        'alcohol_current_details'=> postv('alcohol_current_details'),
        'drug_past'             => postb('drug_past'),
        'drug_past_details'     => postv('drug_past_details'),
        'drug_current'          => postb('drug_current'),
        'drug_current_details'  => postv('drug_current_details'),
        'alcohol_during_abuse'  => postv('alcohol_during_abuse'),
        'drug_during_abuse'     => postv('drug_during_abuse'),

        /* Mental‑health ----------------------------------------------- */
        'counseling_history'    => postb('counseling_history'),
        'counseling_reason'     => postv('counseling_reason'),
        'depressed_currently'   => postb('depressed_currently'),
        'depression_reason'     => postv('depression_reason'),
        'attempted_suicide'     => postb('attempted_suicide'),
        'suicide_last_attempt'  => postv('suicide_last_attempt'),
        'mental_health_meds'    => postb('mental_health_meds'),
        'mental_meds_list'      => postv('mental_meds_list'),
        'mental_doctor_name'    => postv('mental_doctor_name'),
        'sexual_abuse_history'  => postb('sexual_abuse_history'),
        'head_trauma_history'   => postb('head_trauma_history'),
        'head_trauma_desc'      => postv('head_trauma_desc'),
        'weapon_possession_history' => postb('weapon_possession_history'),
        'abuse_trauma_history'      => postb('abuse_trauma_history'),
        'violent_incident_desc'     => postv('violent_incident_desc'),
        'weapon_possession_details' => postv('weapon_possession_details'),



        /* Victim ------------------------------------------------------- */
        'focus_on_actions'          => postv('focus_on_actions'),
        'long_term_assault_thoughts'=> postv('long_term_assault_thoughts'),
        'victim_contact_provided' => ($vk === '1') ? 1 : 0,
        'sworn_sig_name'          => $sworn_sig_name,
        'sworn_signed_date'       => $sworn_date,
        'victim_relationship'     => postv('victim_relationship'),
        'victim_first_name'       => postv('victim_first_name'),
        'victim_last_name'        => postv('victim_last_name'),
        'victim_age'              => postv('victim_age'),
        'victim_gender'           => postv('victim_gender'),
        'victim_phone'            => postv('victim_phone'),
        'victim_email'            => postv('victim_email'),
        'victim_address'          => postv('victim_address'),
        'victim_city'             => postv('victim_city'),
        'victim_state'            => postv('victim_state'),
        'victim_zip'              => postv('victim_zip'),
        'live_with_victim'        => postb('live_with_victim'),
        'children_with_victim'    => postv('children_under_18'),
        'victim_dob'                => postv('victim_dob'),
        'victim_relationship_other' => postv('victim_relationship_other'),
        'children_live_with_you_p7' => postv('children_live_with_you_p7'),         // 0/1/2
        'children_live_with_you_p7_other' => postv('children_live_with_you_p7_other'),
        /* Page 7 – Consents (new) */
        'consent_release_sig_name'    => $release_sig_name,
        'consent_release_signed_date' => $release_date,

        'sworn_sig_name'          => $sworn_sig_name,
        'sworn_signed_date'       => $sworn_date,

        /* Consents ----------------------------------------------------- */
        'consent_confidentiality'    => postb('agree_confidentiality'),     // existing
        'consent_disclosure'         => postb('agree_disclosure'),          // 8a
        'consent8a_agree'            => postb('agree_disclosure'),          // 8a (duplicate for clarity)
        'consent8a_signature' => postv('consent8a_signature'),
        'consent8a_date'      => postv('consent8a_date'),   // normalize with Y-m-d if you prefer

        'consent_partner_info'       => postb('agree_disclosure_partners'), // 8b  (updated)
        'consent_program_agreement'  => postb('agree_program_8c'),          // 8c  (updated)
        'consent_responsibility'     => postb('agree_responsibility_8c'),   // 8c  (updated)
        'consent_policy_termination' => postb('agree_termination_8e'),      // 8e  (updated)

        /* If you want a boolean for Virtual Group Rules (8d) without a checkbox, do this:
          - compute $agree_virtual (see section C below) and then map: */
        'consent_virtual_rules'      => $agree_virtual,                     // 8d (derived)

        'confidentiality_sig_p1'  => postv('consent_p1_signature'),
        'confidentiality_date_p1' => postv('consent_p1_date'),

        /* Page 8b – Consent for Disclosure of Information for Partners */
        'victim_relationship_8b'  => postv('victim_relationship_8b'),
        'disclosure_signature_8b' => postv('disclosure_signature_8b'),
        'disclosure_date_8b'      => postv('disclosure_date_8b'),

        /* Page 8c – SAFC Program Agreement & Taking Responsibility */
        'start_date_8c'           => postv('start_date_8c'),
        'start_dow_8c'            => postv('start_dow_8c'),
        'start_time_8c'           => postv('start_time_8c'),
        // Program Agreement sign/date
        'program_signature_8ca'   => postv('program_signature_8ca'),
        'program_date_8ca'        => postv('program_date_8ca'),
        // Taking Responsibility sign/date
        'program_signature_8cb'   => postv('program_signature_8cb'),
        'program_date_8cb'        => postv('program_date_8cb'),

        /* Page 8d – Virtual Group Rules (initials + sign/date) */
        'vgr_initial_1'  => postv('vgr_initial_1'),
        'vgr_initial_2'  => postv('vgr_initial_2'),
        'vgr_initial_3'  => postv('vgr_initial_3'),
        'vgr_initial_4'  => postv('vgr_initial_4'),
        'vgr_initial_5'  => postv('vgr_initial_5'),
        'vgr_initial_6'  => postv('vgr_initial_6'),
        'vgr_initial_7'  => postv('vgr_initial_7'),
        'vgr_initial_8'  => postv('vgr_initial_8'),
        'vgr_initial_9'  => postv('vgr_initial_9'),
        'vgr_initial_10' => postv('vgr_initial_10'),
        'vgr_initial_11' => postv('vgr_initial_11'),
        'vgr_initial_12' => postv('vgr_initial_12'),
        'vgr_initial_13' => postv('vgr_initial_13'),
        'vgr_initial_14' => postv('vgr_initial_14'),
        'vgr_initial_15' => postv('vgr_initial_15'),
        'vgr_initial_16' => postv('vgr_initial_16'),
        'vgr_initial_17' => postv('vgr_initial_17'),
        'vgr_initial_18' => postv('vgr_initial_18'),
        'vgr_initial_19' => postv('vgr_initial_19'),
        'vgr_signature_8d' => postv('vgr_signature_8d'),
        'vgr_date_8d'      => postv('vgr_date_8d'),

        /* Page 8e – Policy For Clients and Termination Policy */
        'termination_signature_8e' => postv('termination_signature_8e'),
        'termination_date_8e'      => postv('termination_date_8e'),



        /* BIPP goals / notes ------------------------------------------ */
        'reasons'               => implode(', ', $_POST['reasons'] ?? []),
        'other_reason_text'     => postv('other_reason_text'),
        'offense_description'   => postv('describe_reason'),
        'personal_goal'         => postv('personal_goal_bipp'),
        'counselor_name'        => postv('counselor'),
        'chosen_group_time'     => postv('group_time'),

        /* Signature & meta -------------------------------------------- */
        'intake_date'           => $intake_date_ymd,
        'digital_signature'     => postv('digital_signature'),
        'signature_date'        => $signature_date_ymd,

        'packet_complete'       => 1
    ];

    // --- VTA parse/validate (do this BEFORE touching $fields) ---
    $vta_partner_name = trim($_POST['vta_partner_name'] ?? '');
    $vta_date_raw     = $_POST['vta_date'] ?? '';
    $vta_date         = $vta_date_raw ? date('Y-m-d', strtotime($vta_date_raw)) : null;
    $vta_signature    = trim($_POST['vta_signature'] ?? '');
    $vta_behavior_in  = $_POST['vta_behavior'] ?? [];   // array[0..27] N/R/O/F/V

    $allowedScale = ['N','R','O','F','V'];
    $vtaErrors = $vtaErrors ?? [];

    // minimal requireds (expand if needed)
    if ($vta_signature === '')    { $vtaErrors['vta_signature']    = 'required'; }
    if ($vta_partner_name === '') { $vtaErrors['vta_partner_name'] = 'required'; }

    // map to vta_b01..vta_b28 (validate scale)
    $vtaCols = [];
    for ($i = 0; $i < 28; $i++) {
        $key = sprintf('vta_b%02d', $i + 1);
        $val = strtoupper($vta_behavior_in[$i] ?? '');
        if (!in_array($val, $allowedScale, true)) {
            $vtaErrors[$key] = 'required';
            $val = null;
        }
        $vtaCols[$key] = $val;
    }
    if (!empty($vtaErrors)) {
        fail('Please complete the Victim Treatment Assessment.');
    }


    // if you hard‑fail on VTA errors, do it here (else, fold into $errors workflow)

    // --- Add VTA fields into the main $fields payload (single source of truth) ---
    $fields['vta_partner_name'] = $vta_partner_name;
    $fields['vta_date']         = $vta_date;       // YYYY‑MM‑DD
    $fields['vta_signature']    = $vta_signature;
    foreach ($vtaCols as $k => $v) {
        $fields[$k] = $v; // vta_b01..vta_b28
    }

    // --- Single INSERT built from $fields — only once ---
    $cols  = array_keys($fields);
    $place = array_fill(0, count($cols), '?');
    $sql   = 'INSERT INTO intake_packet ('.implode(',', $cols).') VALUES ('.implode(',', $place).')';

    if (substr_count($sql, '?') !== count($fields)) {
        exit('Developer error: placeholder / param count mismatch');
    }

    $stmt  = $db->prepare($sql) or exit('Server error.');
    $types = str_repeat('s', count($fields));  // let MySQL cast
    $stmt->bind_param($types, ...array_values($fields));
    $stmt->execute();
    if ($stmt->error) { error_log($stmt->error); exit('Could not save packet.'); }

    // mark thank‑you (read on GET)
    $_SESSION['show_thank_you_once'] = true;

    // ---- Notify staff (do BEFORE redirect) ----
    $fname = preg_replace('/[\r\n]+/', ' ', postv('first_name'));
    $lname = preg_replace('/[\r\n]+/', ' ', postv('last_name'));

    $replyTo = postv('email');
    if ($replyTo && preg_match('/[\r\n]/', $replyTo)) { $replyTo = null; }
    if ($replyTo && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) { $replyTo = null; }

    $baseHost = 'safatherhood.notesao.com'; // per‑clinic is fine
    $viewUrl  = "https://{$baseHost}/intake-index.php";

    $subject = "San Antonio Fatherhood Campaign has received a new Intake Packet for $fname $lname";
    $body    = "A new online intake packet was submitted.\n\nView pending & submitted packets:\n{$viewUrl}";

    // Build recipient list (validated)
    $rcpts = array_values(array_filter(
        ADMIN_ALERT_EMAILS,
        fn($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL)
    ));
    if ($rcpts) {
        $to  = array_shift($rcpts);

        $headers  = "From: reporting@safatherhood.notesao.com\r\n";
        if ($replyTo) $headers .= "Reply-To: $replyTo\r\n";
        if ($rcpts)  $headers .= "Bcc: " . implode(', ', $rcpts) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . PHP_VERSION;

        $mailOk = @mail(
            $to,
            $subject,
            $body,
            $headers,
            '-freporting@safatherhood.notesao.com' // envelope sender
        );
        if (!$mailOk) {
            error_log('Mail() returned false while notifying staff about intake packet.');
        }
    }

    // mark thank-you (read on GET)
    $_SESSION['show_thank_you_once'] = true;

    header('Location: ' . $_SERVER['PHP_SELF'], true, 303);
    exit; // done, redirect to GET handler
  }
/* ------------------------------------------------------------------ */
/* 3. GET  –  Display form                                            */
/* ------------------------------------------------------------------ */

/* ① Show the “Thank you” card once, then fall back to blank form */
if (!empty($_SESSION['show_thank_you_once'])) {
    unset($_SESSION['show_thank_you_once']);   // next refresh => blank form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title>Thank you – BIPP Intake</title>

      <!-- re‑use your existing favicon / Bootstrap links -->
      <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
      <link rel="stylesheet"
            href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <style>
        body{font-family:system-ui,Arial;background:#f5f6fa;padding:2rem}
        .card{max-width:720px;margin:0 auto;border:0;border-radius:8px;
              box-shadow:0 2px 8px rgba(0,0,0,.08)}
      </style>
    </head>
    <body>

    <script>
    try {
      if (sessionStorage.getItem('intake_just_submitted') === '1') {
        localStorage.clear();                        // nuke any saved steps/values
        sessionStorage.removeItem('intake_just_submitted'); // one-shot
      }
    } catch (e) {}
    </script>
    <!-- (everything else: your instructions, form markup, wizard JS, etc.) -->


    <div class="jumbotron bg-white text-center shadow-sm py-4 mb-4">
      <a href="https://aitscm.org" target="_blank" rel="noopener">
        <img src="safatherhoodlogo.png" alt="AITSCM Logo"
             class="img-fluid mb-1" style="max-width:60%;height:auto">
      </a>
    </div>

    <div class="card shadow-sm">
      <div class="card-body text-center p-5">
        <h2 class="mb-3">Thank you!</h2>
        <p class="lead mb-2">Your Intake Packet was submitted successfully.</p>
        <p class="mb-2">
          We have marked it <em>received</em> in your chart.
          We look forward to seeing you in group!
        </p>
        <p class="mb-4">
          <strong>Remember:</strong> use the <em>first link</em> in your e‑mail
          to join your group session.
        </p>
        <div class="d-flex gap-2 justify-content-center">
          <a href="/intake.php" class="btn btn-primary btn-lg">Close</a>
          <a href="https://aitscm.org/" class="btn btn-outline-secondary btn-lg">AITSCM</a>
        </div>

      </div>
    </div>

    <script>
    try {
      // mark that we just submitted, then wipe any saved progress
      sessionStorage.setItem('intake_just_submitted', '1');
      localStorage.clear();
    } catch (e) {}
    </script>

    </body>
    </html>
    <?php
    ob_end_flush();
    exit;
}


?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>SAF | BIPP Intake</title>
<!-- FAVICON LINKS (from index.html) -->
<link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
<link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">

<link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">

<link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="167x167" href="/favicons/apple-touch-icon-ipad-pro.png">
<link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-touch-icon-ipad.png">
<link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-touch-icon-120x120.png">

<link rel="manifest" href="/favicons/site.webmanifest">
<meta name="apple-mobile-web-app-title" content="NotesAO">
<!-- Bootstrap CSS/JS -->
<link rel="stylesheet" 
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

<!-- Font Awesome (optional for icons) -->
<link rel="stylesheet" 
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<!-- Chart.js for pie charts, bar charts, line charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
 body{font-family:system-ui,Arial,sans-serif;margin:0;background:#f5f6fa;padding:0 1rem}
 h1{text-align:center;font-size:2.0rem;margin:1rem 0}
 form{background:#fff;border-radius:8px;max-width:900px;margin:1rem auto;padding:2rem;box-shadow:0 2px 6px rgba(0,0,0,.08)}
 fieldset{border:0;margin:0 0 1.75rem;padding:0}
 legend{font-weight:700;margin-bottom:.5rem}
 .row{display:flex;gap:1rem;flex-wrap:wrap}
 .col{flex:1;min-width:220px}
 label{font-weight:600;display:block;margin-bottom:.25rem}
 input,select,textarea{width:100%;padding:.5rem;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;font-size:.95rem}
 textarea{min-height:90px}
 input[type=checkbox]{width:auto;margin-right:.35rem}
 .required:after{content:"*";color:#b00;margin-left:.25rem}
 button{display:block;margin:2rem auto 0;padding:.75rem 2rem;border:0;border-radius:4px;font-size:1rem;background:#1076d5;color:#fff;cursor:pointer}
 small{color:#555;display:block;margin-top:.25rem}
 .policy{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:1rem;margin-bottom:.75rem;max-height:180px;overflow-y:auto;font-size:.9rem}
 .invalid-field{border:2px solid #d93025 !important;background:#ffecec !important;}
  /* ---------- Intake-form intro banner (softer) ---------- */
  .intro{
    border:1px solid #c7c7c7;          /* light grey frame   */
    border-left:4px solid #0077cc;     /* thin red accent    */
    background:#e9f4ff;                /* faint blush fill   */
    padding:1.5rem;
    border-radius:6px;
    margin-bottom:2rem;
    font-size:.95rem;
    line-height:1.45;
  }

  .intro h2{
    margin:.25rem 0 .5rem;
    font-size:1.15rem;
    color:#333;                        /* neutral header     */
  }

  .intro ul{margin:.25rem 0 .75rem 1.25rem;padding-left:1.25rem}
  .intro li{margin-bottom:.25rem}

  /* keep the red call-out for the final disclaimer */
  .intro .note{color:#d2302c;font-weight:600;margin-top:1rem}

  /* Wizard */
  fieldset.step { display:none; }
  fieldset.step.active { display:block; }

  #progressBar {
    height:6px; background:#c7c7c7; border-radius:3px; overflow:hidden; margin-bottom:1rem;
  }
  #progressBar span {
    display:block; height:100%; width:0; background:#0077cc;
    transition: width .3s ease;
  }
  .nav-buttons { margin-top:1rem; display:flex; justify-content:space-between; }
  .no-gap{gap:0;}
  .radio-option {
    display: flex;
    align-items: flex-start;
    margin-bottom: 0.5rem;
    max-width: 100%;
  }

  .radio-option input[type="radio"] {
    margin-right: 0.5rem;
    margin-top: 2px; /* vertically align with first line of label */
    flex-shrink: 0;
  }

  .radio-option label {
    flex-grow: 1;
    margin-bottom: 0;
    text-align: left;
  }
  .form-check {
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    margin-bottom: 0.5rem;
    padding-left: 0 !important;
  }

  .form-check-label {
    text-align: left;
  }
  /* Force radio buttons left-aligned */
  .form-check-input[type="radio"] {
    display: inline-block !important;
    margin-left: 0 !important;
    margin-right: 0.5rem;
    position: relative;
    left: 0;
  }
  .victim-knowledge-option {
    width: 100%;
    margin-bottom: 10px;
    padding: 12px;
    border: 2px solid #007bff;
    border-radius: 5px;
    text-align: center;
    cursor: pointer;
    background-color: #fff;
    color: #007bff;
    font-weight: 500;
    transition: background-color 0.2s, color 0.2s;
  }

  .victim-knowledge-option:hover {
    background-color: #e9f3ff;
  }

  .victim-knowledge-option.active {
    background-color: #007bff;
    color: #fff;
  }
  /* For all "Initial" fields before rules */
  input.initials-box {
    display: inline-block;
    width: 50px;          /* narrow box for 2–4 initials */
    padding: 2px 6px;     /* smaller padding */
    font-size: 0.9rem;    /* optional: slightly smaller font */
    margin-right: 8px;    /* spacing before rule text */
    vertical-align: middle;
  }
  .rule-hanging {
    position: relative;
    padding-left: 80px;   /* same as initials width */
  }

  .rule-hanging .initials-box {
    position: absolute;
    left: 0;
    top: 0;
    width: 50px;
    padding: 2px 6px;
    font-size: 0.9rem;
  }

  /* Virtual Group Rules: hanging indent for initials */
  .vgr-list li.rule-hanging {
    position: relative;
    padding-left: 60px;      /* = gutter width; adjust to taste */
    margin-bottom: .5rem;
    list-style: decimal;     /* keep the numbered bullets */
  }

  /* The small initials field pinned in the left gutter */
  .vgr-list li.rule-hanging .initials-box {
    position: absolute;
    left: 0;
    top: 0;                  /* aligns with first line of text */
    width: 50px;             /* small, fixed width for initials */
    padding: 2px 6px;
    font-size: 0.9rem;
    line-height: 1.5;        /* match your body line-height */
    margin: 0;
  }

     



</style>
</head>
<body>



<!-- Jumbotron / Header Section -->
    <div class="jumbotron bg-white text-center shadow-sm py-4">
      <a href="https://aitscm.org/" target="_blank">
        <!-- Responsive Image -->
          <img 
              src="safatherhoodlogo.png" 
              alt="San Antonio Fatherhood Campaign" 
              class="img-fluid mb-1"
              style="max-width: 60%; height: auto;"
          >
      </a>
    </div>




<h1>BIPP Intake Packet</h1>
<form method="post" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES) ?>">

    <!-- ===== Introduction / Instructions ===== -->
    <div class="intro">
    <h2>Why This Form Is Important</h2>
    <p>This form collects essential information we need to:</p>
    <ul>
        <li>Evaluate your situation.</li>
        <li>Provide tailored support.</li>
        <li>Ensure you meet program requirements.</li>
    </ul>

    <h2>Key Instructions</h2>
    <ol style="margin-left:1.25rem">
        <li><strong>Honesty is Essential:</strong> be truthful and accurate when answering every question. Providing false or incomplete information can delay or even prevent your enrollment in the program.</li>
        <li><strong>Complete All Required Fields:</strong> most fields are marked as <span style="color:#d2302c;font-weight:700">required</span> and must be filled out. You will not be able to submit the form, move forward, or begin the BIPP program until the form is fully completed.</li>
        <li><strong>Review Before Submitting:</strong> double-check your answers to ensure they are correct. Once submitted, changes may not be possible without contacting our team.</li>
    </ol>

    <h2>Sections of the Form</h2>
    <p>The form includes the following sections:</p>
    <ul>
        <li><strong>Personal Information</strong></li>
        <ul>
        <li>Full Legal Name&nbsp;&nbsp;·&nbsp;&nbsp;Date of Birth&nbsp;&nbsp;·&nbsp;&nbsp;Contact Information (Phone, Email, Address)</li>
        <li>Emergency Contact Details</li>
        </ul>
        <li><strong>Legal Information</strong></li>
        <ul>
        <li>Case Number (if applicable)</li>
        <li>Court Details (if referred by court)</li>
        <li>Probation Officer Information (if applicable)</li>
        </ul>
        <li><strong>Program-Related Information</strong></li>
        <ul>
        <li>Referral Source</li>
        <li>Reasons for Enrollment</li>
        <li>History of Participation in Similar Programs</li>
        </ul>
    </ul>

    <h2>Tips for Filling Out the Form</h2>
    <ul>
        <li><strong>Take Your Time:</strong> ensure all information is accurate. The form is designed to save your progress in case you need to return later (if applicable).</li>
        <li><strong>Use Clear Language:</strong> avoid abbreviations or vague descriptions.</li>
        <li><strong>Double-Check Required Fields:</strong> look for any fields marked with an asterisk (*) and make sure they are complete.</li>
    </ul>

    <h2>What Happens After Submission?</h2>
    <ul>
        <li>Our team will review your responses to confirm form completion.</li>
        <li>The form will be added to your BIPP chart.</li>
    </ul>

    <!-- SA Fatherhood – Rules & Regulations callout -->
    <!-- SA Fatherhood – Rules & Regulations -->
    <p class="mt-3 mb-1">
      <a href="https://drive.google.com/file/d/1ZzaN8RuyhmcfWDWjz782ySoXyE9sDU5L/view" target="_blank" rel="noopener">
        <strong>San Antonio Fatherhood Rules and Regulations Agreement</strong>
      </a>
    </p>
    <p class="mb-3">
      Click to read current rules &amp; regulations @
      <a href="https://rules.safatherhood.com" target="_blank" rel="noopener">www.rules.safatherhood.com</a>.
    </p>



    <p>If you have questions while filling out the form or encounter any technical difficulties, please contact our support team at: <strong>(210) 664-0102</strong>.</p>

    <p class="note">Note: Incomplete or inaccurate forms will not be accepted. Thank you for your cooperation!</p>
    </div>
    <!-- ========================================= -->

    <!-- wizard progress bar -->
    <div id="progressBar"><span></span></div>
    <div id="stepAlert" class="alert alert-danger" style="display:none"></div>


  <!-- ================================================================== -->
  <!--  1. CONTACT & DEMOGRAPHICS                                         -->
  <!-- ================================================================== -->
  <fieldset class="step">
    <legend>1&nbsp;&nbsp;Contact Information</legend>
    <div class="row">
      <div class="col"><label class="required">First Name</label><input name="first_name" required></div>
      <div class="col"><label class="required">Last Name</label><input name="last_name" required></div>
    </div>
    <div class="row">
      <div class="col"><label class="required">Email</label><input type="email" name="email" required></div>
      <div class="col"><label class="required">Cell Phone</label><input name="phone_cell" required></div>
    </div>
    <!-- Add vertical space between rows -->
    <div style="height: 1.5rem;"></div>
    <div class="row">
      <div class="col"><label class="required">Date of Birth</label><input type="date" name="date_of_birth" required></div>
      <div class="col"><label class="required">Gender</label>
        <select name="gender_id" required>
          <option value="">-- Select --</option><option value="2">Male</option><option value="3">Female</option><option value="1">Not Specified</option>
        </select>
      </div>
      <div class="col"><label class="required">DL Identification Number</label><input name="id_number" required></div>
    </div>
    <!-- Add vertical space between rows -->
    <div style="height: 1.5rem;"></div>
    <label class="required">Street Address</label><input name="address_street" required>
    <div class="row">
      <div class="col"><label class="required">City</label><input name="address_city" required></div>
      <div class="col"><label class="required">State</label><input name="address_state" maxlength="2" required></div>
      <div class="col"><label class="required">Zip</label><input name="address_zip" maxlength="10" required></div>
    </div>
    <!-- Add vertical space between rows -->
    <div style="height: 1.5rem;"></div>
    <div class="row">
      <div class="col"><label class="required">City of Birth</label><input name="birth_city" required></div>
      <div class="col"><label class="required">Race</label>
        <select name="race_id" required>
            <option value="">-- Select --</option><option value="1">African American</option><option value="0">Hispanic</option><option value="2">Asian</option><option value="3">Middle Easterner</option><option value="4">Caucasian</option><option value="5">Other</option>
        </select>
      </div>
      <div class="col"><label class="required">Highest Education</label>
        <select name="education_level" required>
            <option value="">-- Select --</option><option value="1">High School</option><option value="0">GED</option><option value="2">Some College</option><option value="3">Associates</option><option value="4">Bachelors</option><option value="5">Masters</option><option value="6">Doctorates</option><option value="7">None of the Above</option>
        </select>
      </div>
    </div>


    <!-- Add vertical space between rows -->
    <div style="height: 1.5rem;"></div>

    <div class="row">
      <div class="col">
      <label class="required">Currently Employed?</label>
      <select name="employed" required>
        <option value="">-- Select --</option>
        <option value="1">Yes</option>
        <option value="0">No</option>
      </select>
      </div>
      <div class="col employer-fields" style="display:none;">
      <label>Employer</label>
      <input name="employer">
      </div>
      <div class="col employer-fields" style="display:none;">
      <label>Occupation</label>
      <input name="occupation">
      </div>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          const employedSelect = document.querySelector('select[name="employed"]');
          const employerFields = document.querySelectorAll('.employer-fields');
          function toggleEmployerFields() {
        if (employedSelect.value === "1") {
          employerFields.forEach(el => el.style.display = "");
        } else {
          employerFields.forEach(el => el.style.display = "none");
        }
          }
          employedSelect.addEventListener('change', toggleEmployerFields);
          toggleEmployerFields();
        });
      </script>
    </div>
    <!-- ───────── Confidentiality & Consent ───────── -->
    <h3 style="margin-top:2rem">Statement of Confidentiality&nbsp;&amp; Consent for Treatment</h3>

    <div class="border p-3 mb-3 rounded bg-light">

    <p>Confidentiality is defined as keeping private the information shared by you, the client, with your
    counselor. On occasion, other employees may need access to your record for agency teaching,
    supervision, and administrative purposes. These staff members will also respect the privacy of your
    records. In accordance with the Texas Department of Criminal Justice – Community Justice Assistance Division
    and Texas Council on Family Violence Battering Intervention &amp; Prevention Program guidelines, clients are
    required to sign Consent for Release of Information, which permits information to be released to the
    victim/partner and/or their designated representative, law enforcement, the courts, correction
    agencies, and any others in accordance with agency policy.</p>

    <p><strong>As a client, you have the right to withhold or release information to other individuals or
    agencies.</strong> A statement signed by you is required before any information may be released to anyone
    outside San Antonio Fatherhood Campaign – BIPP. This right applies with the following exceptions:</p>

    <ul>
        <li>When a court of law subpoenas information shared by you with your counselor.</li>
        <li>When there is reasonable concern that harm may come to you or others, as in child abuse, elder
            abuse, and abuse of a disabled person. Staff will notify appropriate agencies, including TDPRS
            (Texas Department of Protective and Regulatory Services), in accordance with applicable laws.</li>
        <li>When staff determines there is a probability of imminent physical injury to self or others.
            Staff may notify medical or law-enforcement personnel and/or the victim/partner
            (Section 611.004(a) of the Texas Health and Safety Code).</li>
        <li>When there is disclosure of sexual misconduct or sexual exploitation by a previous therapist or
            mental-health professional.</li>
    </ul>

    <p><strong>A licensee shall report if required by any of the following laws:</strong></p>
    <ul>
        <li><em>Health and Safety Code, Chapter 161, Subchapter K</em>, concerning abuse, neglect, or
            illegal, unprofessional, or unethical conduct in facilities providing mental-health services.</li>
        <li><em>Civil Practice and Remedies Code, §81.006</em>, concerning sexual exploitation by a
            mental-health service provider.</li>
        <li>All personal data and possibly additional information will be submitted to TDCJ-CJAD for program
            assessments and research.</li>
        <li><strong>Media involvement:</strong> Any media contact arranged by the San Antonio Fatherhood Campaign program
            will include the presence of a San Antonio Fatherhood Campaign employee to protect victim confidentiality.</li>
    </ul>

    <p><strong>We ask that you keep confidential information you may learn about other clients who are
    receiving services from San Antonio Fatherhood Campaign – BIPP.</strong></p>

    <p><strong>San Antonio Fatherhood Campaign requires facilitators and participants to:</strong></p>
    <ul>
        <li>Disable any devices that could collect information from the environment, such as Google Home
            Assistant, Amazon Alexa, or Apple Siri.</li>
        <li>Not record or take screenshots of group discussions.</li>
        <li>Ensure they are in a private space and not in any public area such as a park, yard, or open
            area. Other people not in the group should not hear or observe the group.</li>
        <li>Not use the virtual group session to expel their partner or children from the residence.
            Participants must relocate to another location or private room in the residence.</li>
        <li>Ensure that children are safe and cared for, but not interrupting the session or listening to
            group discussions.</li>
    </ul>

    <p><strong>Observers may occasionally sit in on a group.</strong> Observers must sign a confidentiality
    statement. Observers may include student interns, trainees, other professionals, or community
    members. This facility is video-recorded for security purposes, and treatment sessions may be
    video/audio recorded for quality assurance.</p>

    <p><strong>Ethics &amp; Grievances:</strong> All agency services will be delivered in as professional and
    ethical a manner as possible. While specific results cannot be guaranteed, if you have concerns
    about the professional performance of your counselor:</p>
    <ul>
        <li>Inform your counselor directly.</li>
        <li>If unresolved, report concerns to your counselor's immediate supervisor, Executive Director
            Van Martin, at 817-501-5102.</li>
        <li>If further resolution is needed, contact the Texas Council on Family Violence at 800-525-1978.</li>
    </ul>

    <!-- Page 1 – Additional consent language -->
    <p><strong>Important:</strong> Any no-shows, late arrivals, dismissals, missing classes, appointments, drops, or cancellations for any services may result in termination from this project. We reserve the right to modify this Agreement at any time without providing prior notice. If you have any concerns, please inform the staff.</p>

    <p>By signing this form, you agree to complete the assigned task that meets the needs prescribed to you by your referring agency (which includes yourself) and our staff. Additionally, you understand that failure to adhere to the project’s efforts will not be tolerated and may result in dismissal and notification to the referral agency.</p>

    <p><strong>My Signature below authorizes</strong> my American Indians in Texas team members to send SMS messages to the phone number provided during the enrollment/signup process for service-related needs. I understand that I may opt out of receiving SMS messages by replying to text messages with <strong>STOP</strong>, and that I may request help at any time by texting <strong>HELP</strong>. Furthermore, I understand that the frequency of SMS messages varies, and message and data rates from my mobile phone carrier may apply.</p>


    </div>

    <label class="required" style="display:block;margin-top:.75rem">
    <input type="checkbox" name="agree_confidentiality" required>
    I&nbsp;Agree&nbsp;– I have read, understood and accept the terms above
    </label>
    <!-- ────────────────────────────────────────────── -->

    <!-- Page 1 – Signature & Date (required) -->
    <div class="row g-2 mt-2">
      <div class="col-md-7">
        <label for="consent_p1_signature" class="form-label fw-semibold">Signature (type your full name) <span class="text-danger">*</span></label>
        <input type="text" id="consent_p1_signature" name="consent_p1_signature" class="form-control" required>
      </div>
      <div class="col-md-5">
        <label for="consent_p1_date" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
        <input type="date" id="consent_p1_date" name="consent_p1_date" class="form-control" required>

      </div>
    </div>


    <div class="nav-buttons">
        <button type="button" class="btn btn-primary next">Next</button>
    </div>

  </fieldset>

  <!-- ================================================================== -->
  <!--  2. EMERGENCY CONTACT                                              -->
  <!-- ================================================================== -->
  <fieldset class="step">
    <legend>2a&nbsp;&nbsp;Emergency Contact</legend>
    <div class="row">
      <div class="col"><label class="required">Name</label><input name="emergency_name" required></div>
    </div>
    <div class="row">
      <div class="col"><label class="required">Phone</label><input name="emergency_phone" required></div>
    </div>
    <div class="row">
      <div class="col"><label class="required">Relationship</label><input name="emergency_relation" required></div>
    </div>
    
    <!-- Add vertical space between rows -->
    <div style="height: 1.5rem;"></div>

    <legend>2b&nbsp;&nbsp;Military Service</legend>
    <div class="row">
      <div class="col"><label>Branch</label><input name="military_branch"></div>
    </div>
    <div class="row">
      <div class="col"><label>Date of Service</label><input name="military_date"></div>
    </div>

    <div class="nav-buttons">
        <button type="button" class="btn btn-secondary prev">Previous</button>
        <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <!-- ================================================================== -->
  <!--  3. REFERRAL / OFFICER                                            -->
  <!-- ================================================================== -->
  <fieldset class="step">
    <legend>3&nbsp;&nbsp;Referral Source</legend>
    <div class="row">
      <div class="col">
        <label class="required">Referral Type</label>
        <select name="referral_type_id" required>
          <option value="">-- Select --</option>
          <option value="1">Probation</option><option value="2">Parole</option>
          <option value="3">Pre-trial</option><option value="4">CPS</option>
          <option value="5">Attorney</option><option value="6">Self</option>
          <option value="0">Other / Unknown</option>
        </select>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label class="required">Probation/Parole/CPS Officer’s Name</label>
        <input name="referring_officer_name" required>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label class="required">Probation/Parole/CPS Officer’s Email</label>
        <input type="email" name="referring_officer_email" placeholder="officer@agency.gov" required>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label class="required">Probation/Parole/CPS Officer’s Phone</label>
        <input type="tel" name="referring_officer_phone" placeholder="201-555-0122" required>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label>Probation/Parole/CPS Cause / ML Number</label>
        <input type="text" name="referring_cause_number" maxlength="30"
              placeholder="e.g., ML-123456 or court cause #">
      </div>
    </div>


    <!-- Add vertical space between rows -->
    <div style="height: 1.5rem;"></div>

    <div class="row">
      <div class="col">
        <label class="required">Charge & Arrest Dates</label>
        <input name="additional_charge_dates" required placeholder="e.g. January 4, 2023, August 6, 2024">
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label class="required">Charge Details</label>
        <input name="additional_charge_details" required>
      </div>
    </div>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <!-- ================================================================== -->
  <!--  4. HOUSEHOLD & CHILDREN                                          -->
  <!-- ================================================================== -->
  <fieldset class="step" id="step-household">
    <legend>4&nbsp;&nbsp;Marital &amp; Family Information</legend>

    <!-- ───────── Primary household info ───────── -->
    <div class="row no-gap">
      <!-- Living situation ------------------------------------------------>
      <div class="col-md-4">
        <label class="form-label" for="living_situation">
          Living Situation <span class="text-danger">*</span>
        </label>
        <select id="living_situation" name="living_situation" class="form-select" required>
          <option value="" disabled selected>Choose&hellip;</option>
          <option>Alone</option>
          <option>With Partner</option>
          <option>With Relatives</option>
          <option>With Friends</option>
          <option>With Children</option>
          <option>Homeless</option>
        </select>
      </div>

      <!-- Marital status --------------------------------------------------->
      <div class="col-md-4">
        <label class="form-label" for="marital_status">
          Marital Status <span class="text-danger">*</span>
        </label>
        <select id="marital_status" name="marital_status" class="form-select" required>
          <option value="" disabled selected>Choose&hellip;</option>
          <option>Married</option>
          <option>Separated</option>
          <option>Divorced</option>
          <option>Single</option>
          <option>Dating</option>
        </select>
      </div>

      <!-- Have children? --------------------------------------------------->
      <div class="col-md-4">
        <label class="form-label" for="has_children">
          Do you have any children? <span class="text-danger">*</span>
        </label>
        <select id="has_children" name="has_children" class="form-select" required>
          <option value="" disabled selected>--</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>
    </div><!-- /.row -->

    <!-- ───────── Child-specific questions (hidden until needed) ───────── -->
    <div id="children-details" class="mt-4 d-none">
      <div class="row no-gap">
        <!-- Children live with you? --------------------------------------->
        <div class="col-md-4">
          <label class="form-label" for="children_live_with_you">
            Do your children live with you? <span class="text-danger">*</span>
          </label>
          <select id="children_live_with_you" name="children_live_with_you" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>

        <!-- Names & ages --------------------------------------------------->
        <div class="col-md-8">
          <label class="form-label" for="children_names_ages">
            Names &amp; Ages of Your Children <span class="text-danger">*</span>
          </label>
          <input id="children_names_ages" name="children_names_ages"
                class="form-control" placeholder="e.g. Alice 7, Ben 5, Carla 3">
        </div>
      </div><!-- /.row -->

      <!-- Abuse / neglect grid ------------------------------------------->
      <div class="row no-gap mt-3">
        <div class="col-md-4">
          <label class="form-label" for="abused_physically">
            Have any of your children ever been abused&nbsp;PHYSICALLY? <span class="text-danger">*</span>
          </label>
          <select id="abused_physically" name="abused_physically" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="abused_sexually">
            Have any of your children ever been abused&nbsp;SEXUALLY? <span class="text-danger">*</span>
          </label>
          <select id="abused_sexually" name="abused_sexually" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="abused_emotionally">
            Have any of your children ever been abused&nbsp;EMOTIONALLY? <span class="text-danger">*</span>
          </label>
          <select id="abused_emotionally" name="abused_emotionally" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-md-12">
          <label class="form-label" for="children_neglected">
            Have any of your children ever been&nbsp;neglected? <span class="text-danger">*</span>
          </label>
          <select id="children_neglected" name="children_neglected" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
      </div><!-- /.row -->

      <!-- CPS follow-ups (only if neglected = Yes) ------------------------>
      <div id="cps-block" class="row no-gap mt-3 d-none">
        <div class="col-md-6">
          <label class="form-label" for="cps_notified">
            Has&nbsp;Child Protective Services ever been notified? <span class="text-danger">*</span>
          </label>
          <select id="cps_notified" name="cps_notified" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="cps_care">
            Have any children been under CPS care? <span class="text-danger">*</span>
          </label>
          <select id="cps_care" name="cps_care" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
      </div><!-- /#cps-block -->
      <!-- Hidden field that actually gets saved to DB -->
      <input type="hidden" id="cps_case_year_status" name="cps_case_year_status">

      <!-- Case details (shown if cps_notified=Yes OR cps_care=Yes) -->
      <div id="cps-case-details" class="row no-gap mt-3 d-none">
        <div class="col-md-6">
          <label class="form-label" for="cps_case_year">
            If yes to either, what year was the case opened? <span class="text-danger">*</span>
          </label>
          <input id="cps_case_year" name="cps_case_year" type="number" min="1900" max="2100" class="form-control" placeholder="e.g. 2022">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="cps_case_open_closed">
            Is the case currently open or closed? <span class="text-danger">*</span>
          </label>
          <select id="cps_case_open_closed" name="cps_case_open_closed" class="form-select">
            <option value="" disabled selected>--</option>
            <option>Open</option>
            <option>Closed</option>
          </select>
        </div>
      </div>

      <!-- Caseworker contact (shown only if status is Open) -->
      <div id="cps-caseworker-block" class="mt-3 d-none">
        <label class="form-label" for="cps_caseworker_contact">
          If open, please provide CPS caseworker contact information. <span class="text-danger">*</span>
        </label>
        <textarea id="cps_caseworker_contact" name="cps_caseworker_contact" class="form-control" rows="3" placeholder="Name, phone, email"></textarea>
      </div>

    </div><!-- /#children-details -->

    <!-- ───────── Discipline narrative ───────── -->
    <div class="mt-4">
      <label class="form-label required" for="discipline_desc">
      Describe how you discipline your children. Please provide examples:
      </label>
      <textarea id="discipline_desc" name="discipline_desc" rows="3" class="form-control"
          placeholder="Type your answer here&hellip;" required></textarea>
    </div>

    <!-- ───────── Navigation buttons ───────── -->
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>


  <!-- ================================================================== -->
  <!--  5. SUBSTANCE USE HISTORY                                          -->
  <!-- ================================================================== -->
  <fieldset class="step" id="step-substance">
    <legend>5&nbsp;&nbsp;Substance Use History</legend>

    <!-- Top question: last use -->
    <div class="mb-3">
      <label class="form-label required" for="last_substance_use">
        When was the last time you used alcohol and/or drugs?
      </label>
      <input id="last_substance_use" name="last_substance_use"
            class="form-control" required
            placeholder="e.g. Yesterday, 2 weeks ago, Never">
    </div>


    <!-- ───────── Alcohol ───────── -->
    <div class="row no-gap mb-3">
      <!-- Past use ------------------------------------------------------->
      <div class="col-md-4">
        <label class="form-label" for="alcohol_past">
          Use of alcohol in the past? <span class="text-danger">*</span>
        </label>
        <select id="alcohol_past" name="alcohol_past" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>

      <!-- Past‑use details (hidden until “Yes”) ------------------------->
      <div class="col-md-8 d-none" id="alcohol_frequency_grp">
        <label class="form-label" for="alcohol_frequency">
          If <em>Yes</em>, how often&nbsp;&amp;&nbsp;how much?
          <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="alcohol_frequency" name="alcohol_frequency"
              class="form-control" placeholder="e.g. 2 drinks, 3× per week">
      </div>
    </div>

    <div class="row no-gap mb-3">
      <!-- Current use ---------------------------------------------------->
      <div class="col-md-4">
        <label class="form-label" for="alcohol_current">
          Use of alcohol currently? <span class="text-danger">*</span>
        </label>
        <select id="alcohol_current" name="alcohol_current" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>

      <!-- Current‑use details ------------------------------------------->
      <div class="col-md-8 d-none" id="alcohol_current_details_grp">
        <label class="form-label" for="alcohol_current_details">
          If <em>Yes</em>, how often&nbsp;&amp;&nbsp;how much?
          <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="alcohol_current_details" name="alcohol_current_details"
              class="form-control" placeholder="e.g. Nightly, 1–2 beers">
      </div>
    </div>

    <!-- ───────── Drugs ───────── -->
    <div class="row no-gap mb-3">
      <!-- Past use ------------------------------------------------------->
      <div class="col-md-4">
        <label class="form-label" for="drug_past">
          Use of drugs in the past? <span class="text-danger">*</span>
        </label>
        <select id="drug_past" name="drug_past" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>

      <!-- Past‑use details --------------------------------------------->
      <div class="col-md-8 d-none" id="drug_past_details_grp">
        <label class="form-label" for="drug_past_details">
          If <em>Yes</em>, how often&nbsp;&amp;&nbsp;what drug?
          <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="drug_past_details" name="drug_past_details"
              class="form-control" placeholder="e.g. Marijuana daily">
      </div>
    </div>

    <div class="row no-gap mb-3">
      <!-- Current use ---------------------------------------------------->
      <div class="col-md-4">
        <label class="form-label" for="drug_current">
          Use of drugs currently? <span class="text-danger">*</span>
        </label>
        <select id="drug_current" name="drug_current" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>

      <!-- Current‑use details ------------------------------------------->
      <div class="col-md-8 d-none" id="drug_current_details_grp">
        <label class="form-label" for="drug_current_details">
          If <em>Yes</em>, how often&nbsp;&amp;&nbsp;what drug?
          <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="drug_current_details" name="drug_current_details"
              class="form-control" placeholder="e.g. Prescription opioids weekly">
      </div>
    </div>

    <!-- ───────── During abusive incident ───────── -->
    <div class="row no-gap mb-4">
      <div class="col-md-6">
        <label class="form-label" for="alcohol_during_abuse">
          Were you using alcohol when you were abusive? <span class="text-danger">*</span>
        </label>
        <select id="alcohol_during_abuse" name="alcohol_during_abuse" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
          <option value="2">Sometimes</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="drug_during_abuse">
          Were you using drugs when you were abusive? <span class="text-danger">*</span>
        </label>
        <select id="drug_during_abuse" name="drug_during_abuse" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
          <option value="2">Sometimes</option>
        </select>
      </div>
    </div>

    <!-- Nav buttons -->
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <!-- ================================================================== -->
  <!--  6. COUNSELING / MENTAL‑HEALTH BACKGROUND                           -->
  <!-- ================================================================== -->
  <fieldset class="step" id="step-mental">
    <legend>6&nbsp;&nbsp;Counseling History&nbsp;&amp; Mental‑Health Background</legend>

    <!-- Ever in counseling? --------------------------------------------->
    <div class="row no-gap mb-3">
      <div class="col-md-6">
        <label class="form-label" for="counseling_history">
          Have you ever been in counseling? <span class="text-danger">*</span>
        </label>
        <select id="counseling_history" name="counseling_history" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>

      <!-- detail ---------------------------------------------------------->
      <div class="col-md-6 d-none" id="counseling_history_details_grp">
        <label class="form-label" for="counseling_reason">
          If YES, for what reason: <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="counseling_reason" name="counseling_reason" class="form-control"
              placeholder="e.g. PTSD, depression">
      </div>
    </div>

    <!-- Currently depressed? ---------------------------------------------->
    <div class="row no-gap mb-3">
      <div class="col-md-6">
        <label class="form-label" for="depressed_currently">
          Are you currently depressed? <span class="text-danger">*</span>
        </label>
        <select id="depressed_currently" name="depressed_currently" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>

      <div class="col-md-6 d-none" id="depressed_currently_details_grp">
        <label class="form-label" for="depression_reason">
          Please explain: <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="depression_reason" name="depression_reason" class="form-control">
      </div>
    </div>

    <!-- Attempted suicide? ------------------------------------------------>
    <div class="row no-gap mb-3">
      <div class="col-md-6">
        <label class="form-label" for="attempted_suicide">
          Have you ever attempted suicide? <span class="text-danger">*</span>
        </label>
        <select id="attempted_suicide" name="attempted_suicide" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>

      <div class="col-md-6 d-none" id="attempted_suicide_details_grp">
        <label class="form-label" for="suicide_last_attempt">
          If YES, when was the last attempt: <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="suicide_last_attempt" name="suicide_last_attempt" class="form-control"
              placeholder="e.g. May 2023">
      </div>
    </div>

    <!-- Mental‑health meds?  (full row question, two half‑row details) ---->
    <div class="row no-gap mb-3">
      <div class="col-md-12">
        <label class="form-label" for="mental_health_meds">
          Are you taking any medications for a mental‑health condition? <span class="text-danger">*</span>
        </label>
        <select id="mental_health_meds" name="mental_health_meds" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>
    </div>

    <div class="row no-gap mb-3 d-none" id="mental_health_meds_details_grp">
      <div class="col-md-6">
        <label class="form-label" for="mental_meds_list">
          If YES, what medications? <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="mental_meds_list" name="mental_meds_list" class="form-control">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="mental_doctor_name">
          If YES, what is your doctor’s name? <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="mental_doctor_name" name="mental_doctor_name" class="form-control">
      </div>
    </div>

    <!-- Remaining simple yes/no questions -------------------------------->
    <!-- Row 1 – sexual abuse & head‑trauma selectors (50 / 50) -->
    <!-- Row: Sexual Abuse (full-width now) -->
    <div class="row no-gap mb-3">
      <div class="col-md-12">
        <label class="form-label" for="sexual_abuse_history">
          Have you ever been accused of sexual abuse or assault? <span class="text-danger">*</span>
        </label>
        <select id="sexual_abuse_history" name="sexual_abuse_history"
                class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>
    </div>

    <!-- Row: Head Trauma & Follow-up side-by-side -->
    <div class="row no-gap mb-3" id="head_trauma_row">
      <!-- Question -->
      <div class="col-md-6">
        <label class="form-label" for="head_trauma_history">
          Do you have any history of head trauma, brain injury, stroke,
          including overdose blackouts? <span class="text-danger">*</span>
        </label>
        <select id="head_trauma_history" name="head_trauma_history"
                class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>

      <!-- Conditional Follow-up -->
      <div class="col-md-6 d-none" id="head_trauma_details_grp">
        <label class="form-label" for="head_trauma_desc">
          If YES, please describe:
          <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="head_trauma_desc" name="head_trauma_desc"
              class="form-control">
      </div>
    </div>


    <!-- Row 3 – weapon possession (full width) -->
    <div class="row no-gap mb-3">
      <div class="col-md-12">
        <label class="form-label" for="weapon_possession_history">
          History of weapon possession? <span class="text-danger">*</span>
        </label>
        <select id="weapon_possession_history" name="weapon_possession_history"
                class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>
    </div>

    <div class="row no-gap mb-3 d-none" id="weapon_possession_details_grp">
      <div class="col-md-12">
        <label class="form-label" for="weapon_possession_details">
          If YES, please explain: <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="weapon_possession_details" name="weapon_possession_details" class="form-control">
      </div>
    </div>


    <!-- Row 4 – abuse/trauma as child (full width) -->
    <div class="row no-gap mb-3">
      <div class="col-md-12">
        <label class="form-label" for="abuse_trauma_history">
          History of abuse / trauma as a child? <span class="text-danger">*</span>
        </label>
        <select id="abuse_trauma_history" name="abuse_trauma_history"
                class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>
    </div>


    <!-- narrative -------------------------------------------------------->
    <div class="mt-3">
      <label class="form-label" for="violent_incident_desc">
        Describe the most recent violent incident / offense / etc:
      </label>
      <textarea id="violent_incident_desc" name="violent_incident_desc"
                class="form-control" rows="3"></textarea>
    </div>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>



  <!-- ================================================================== -->
  <!--  7a. VICTIM INFORMATION                                             -->
  <!-- ================================================================== -->
  <fieldset class="step" id="step-victim">
    <legend>7a&nbsp;&nbsp;Victim Information</legend>

    <!-- Relationship Assessment (Page 7) -->
    <div class="mt-4 border-top pt-3">
      <h5>Relationship Assessment</h5>

      <div class="row no-gap mt-2">
        <div class="col-md-12">
          <label class="required" for="focus_on_actions">
            How often do/did you focus on their actions, whereabouts, and friends?
          </label>
          <select id="focus_on_actions" name="focus_on_actions" class="form-select" required>
            <option value="" disabled selected>--</option>
            <option>Never</option>
            <option>Sometimes</option>
            <option>Often</option>
            <option>Frequently</option>
            <option>Very Frequently</option>
          </select>
        </div>
      </div>

      <div class="row no-gap mt-3">
        <div class="col-md-12">
          <label class="required" for="long_term_assault_thoughts">
            Do you have/had any long-term thoughts of assaulting or threatening them?
          </label>
          <select id="long_term_assault_thoughts" name="long_term_assault_thoughts" class="form-select" required>
            <option value="" disabled selected>--</option>
            <option>Never</option>
            <option>Sometimes</option>
            <option>Often</option>
            <option>Frequently</option>
            <option>Very Frequently</option>
          </select>
        </div>
      </div>
    </div>


    <!-- Victim knowledge selection -->
    <div class="row no-gap mb-3">
      <div class="col-md-12">
        <p class="form-label fw-bold">Please select one:</p>

        <label class="victim-knowledge-option">
          <input type="radio" name="victim_knowledge" value="0">
          I do <strong>not</strong> have knowledge of the victim's contact information
        </label>

        <label class="victim-knowledge-option">
          <input type="radio" name="victim_knowledge" value="1">
          I do have knowledge of the victim's contact information (must provide below)
        </label>

      </div>
    </div>


    <!-- Conditional victim info section -->
    <div id="victim-info-block" class="d-none mt-3">
      <!-- Always shown once selection is made -->
      <div class="row">
        <div class="col-md-12">
          <label class="required">Relationship to victim</label>
          <select name="victim_relationship" class="form-select" required>
            <option value="">Select…</option>
            <option>Current Partner</option>
            <option>Ex-Partner</option>
            <option>Other</option>
          </select>
        </div>
      </div>

      <div class="row no-gap mt-2 d-none" id="victim_relationship_other_grp">
        <div class="col-md-12">
          <label for="victim_relationship_other">Please specify their relationship to you:</label>
          <input type="text" id="victim_relationship_other" name="victim_relationship_other" class="form-control">
        </div>
      </div>


      <!-- Contact fields (conditionally shown) -->
      <div id="victim-contact-block">
        <div class="row no-gap mt-3">
          <div class="col-md-6">
            <label>Victim's First Name</label>
            <input name="victim_first_name" class="form-control">
          </div>
          <div class="col-md-6">
            <label>Victim's Last Name</label>
            <input name="victim_last_name" class="form-control">
          </div>
        </div>

        <div class="row no-gap mt-3">
          <div class="col-md-6">
            <label>Victim's Date of Birth</label>
            <input name="victim_dob" type="date" class="form-control">
            <small class="text-muted">DOB or estimated age is required if you selected “I do not have knowledge…”.</small>
          </div>
          
        </div>


        <div class="row no-gap mt-3">
          <div class="col-md-6">
            <label>Victim's Age</label>
            <input name="victim_age" type="number" min="0" class="form-control">
          </div>
          <div class="col-md-6">
            <label>Victim's Gender</label>
            <select name="victim_gender" class="form-select">
              <option value="">Select…</option>
              <option>Male</option>
              <option>Female</option>
              <option>Not Specified</option>
            </select>
          </div>
        </div>

        <div class="row no-gap mt-3">
          <div class="col-md-6">
            <label>Victim's Phone</label>
            <input name="victim_phone" class="form-control">
          </div>
          <div class="col-md-6">
            <label>Victim's Email</label>
            <input name="victim_email" type="email" class="form-control">
          </div>
        </div>

        <div class="mt-3">
          <label>Victim's Address</label>
          <input name="victim_address" class="form-control">
        </div>

        <div class="row no-gap mt-3">
          <div class="col-md-4">
            <label>City</label>
            <input name="victim_city" class="form-control">
          </div>
          <div class="col-md-4">
            <label>State</label>
            <input name="victim_state" class="form-control">
          </div>
          <div class="col-md-4">
            <label>Zip Code</label>
            <input name="victim_zip" class="form-control">
          </div>
        </div>
      </div>

      <!-- Living with victim + children under 18 -->
      <div class="row no-gap mt-3">
        <div class="col-md-6">
          <label class="required" for="live_with_victim">Will you be living with the victim while attending BIPP?</label>
          <select id="live_with_victim" name="live_with_victim" class="form-select" required>
            <option value="">--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-md-6 d-none" id="children_under18_block">
          <label id="children_under18_label" for="children_under_18">
            How many children under the age of 18 are living with the victim?
          </label>
          <input name="children_under_18" id="children_under_18" type="text" class="form-control">
        </div>

      </div>
      <div class="row no-gap mt-3">
        <div class="col-md-6">
          <label class="required" for="children_live_with_you_p7">Do your child(ren) live with you?</label>
          <select id="children_live_with_you_p7" name="children_live_with_you_p7" class="form-select" required>
            <option value="">--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
            <option value="2">Other</option>
          </select>
        </div>
        <div class="col-md-6 d-none" id="children_live_with_you_p7_other_grp">
          <label for="children_live_with_you_p7_other">If Other, please specify</label>
          <input type="text" id="children_live_with_you_p7_other" name="children_live_with_you_p7_other" class="form-control">
        </div>
      </div>

    </div>

    <!-- ===== Page 7 – Additional Consents ===== -->
    <div class="mt-4 border-top pt-3">

      <!-- Consent for Release of Information and Limits to Confidentiality -->
      <h5 class="mt-3">Consent For Release of Information and Limits to Confidentiality</h5>
      <p class="mb-2">
        I understand that throughout the duration of the program the staff of Fatherhood Campaign
        may contact the person with whom I have been violent for descriptions of abusive and
        controlling behaviors I utilize.
      </p>

      <div class="row no-gap">
        <div class="col-md-8">
          <label class="form-label required" for="consent_release_sig_name">Participant Name (signature)</label>
          <input
            type="text"
            id="consent_release_sig_name"
            name="consent_release_sig_name"
            class="form-control"
            maxlength="120"
            value="<?= htmlspecialchars($_POST['consent_release_sig_name'] ?? '') ?>"
            placeholder="Type your full legal name"
            required
          >
        </div>
        <div class="col-md-4">
          <label class="form-label required" for="consent_release_signed_date">Date</label>
          <input
            type="date"
            id="consent_release_signed_date"
            name="consent_release_signed_date"
            class="form-control"
            value="<?= htmlspecialchars($_POST['consent_release_signed_date'] ?? '') ?>"
            required
          >
        </div>
      </div>

      <!-- Sworn Statement (shown only when victim_knowledge = "0") -->
      <div id="sworn-consent-block" class="mt-4 d-none">
        <h5>Sworn Statement</h5>
        <p class="mb-2">
          In the following statement, I am acknowledging that I do not know the contact information of the victim/survivor for which I was referred to this program for services.
          <br>
          I, <strong><span id="sworn_participant_name_preview"></span></strong> attest that I do not know the contact information of
          <strong><span id="sworn_victim_name_preview"></span></strong>.
          <br>
          (I have no knowledge of the victim’s address, email, phone number, or any contact information; I hereby sign a sworn statement.)
        </p>

        <div class="row no-gap">
          <div class="col-md-8">
            <label class="form-label required" for="sworn_sig_name">Participant Name (signature)</label>
            <input
              type="text"
              id="sworn_sig_name"
              name="sworn_sig_name"
              class="form-control"
              maxlength="120"
              value="<?= htmlspecialchars($_POST['sworn_sig_name'] ?? '') ?>"
              placeholder="Type your full legal name"
              <?= (($_POST['victim_knowledge'] ?? '') === '0') ? 'required' : '' ?>
            >
          </div>
          <div class="col-md-4">
          <label class="form-label required" for="sworn_signed_date">Date</label>
          <input
            type="date"
            id="sworn_signed_date"
            name="sworn_signed_date"
            class="form-control"
            value="<?= htmlspecialchars($_POST['sworn_signed_date'] ?? '') ?>"
            <?= (($_POST['victim_knowledge'] ?? '') === '0') ? 'required' : '' ?>
          >

        </div>
        </div>
      </div>
    </div>
    <!-- ===== /Page 7 – Additional Consents ===== -->



    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <!-- ================================================================== -->
  <!-- 7b. VICTIM TREATMENT ASSESSMENT (new page)                         -->
  <!-- ================================================================== -->
  <fieldset data-step="7b" id="step-7b" class="step">
    <h4 class="mb-2">7b. Victim Treatment Assessment</h4>
    <p class="text-muted mb-3">
      Estimate how often these behaviors occurred during the <strong>6 months before you began this program</strong>.
      Select one option per row.
    </p>

    <style>
      /* Scope to 7b only */
      #step-7b .vta-table { table-layout: auto; width: 100%; }
      #step-7b .vta-col-behavior { width: 64% !important; }
      #step-7b .vta-col-scale   { width: 7.2% !important; } /* 5 columns ≈ 36% total */

      #step-7b th, #step-7b td { padding: .30rem .35rem; }
      #step-7b thead th { font-weight: 600; font-size: .9rem; }
      #step-7b td:first-child {
        white-space: normal; word-break: break-word;
        font-size: 1rem !important; line-height: 1.35;
      }
      #step-7b td.text-center, #step-7b th.text-center { vertical-align: middle; }
      #step-7b .form-check-input { margin: 0; transform: scale(0.95); }
      #step-7b tbody tr:nth-child(odd) { background: #fafafa; } /* optional striping */
    </style>



    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label for="vta_partner_name" class="form-label">What is your (relation)'s name?</label>
        <input type="text" class="form-control" id="vta_partner_name" name="vta_partner_name" autocomplete="off" required>
      </div>
    </div>

    <div class="border rounded p-2 mb-2">
      <small class="text-muted d-block">Scale</small>
      <div class="d-flex flex-wrap gap-3">
        <span><strong>N</strong> = Never</span>
        <span><strong>R</strong> = Rarely</span>
        <span><strong>O</strong> = Occasionally</span>
        <span><strong>F</strong> = Frequently</span>
        <span><strong>V</strong> = Very Frequently</span>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-sm align-middle vta-table">
        <colgroup>
          <col class="vta-col-behavior">
          <col class="vta-col-scale">
          <col class="vta-col-scale">
          <col class="vta-col-scale">
          <col class="vta-col-scale">
          <col class="vta-col-scale">
        </colgroup>
        <thead class="table-light">
          
          <tr>
            <th>Behavior</th>
            <th class="text-center">N</th>
            <th class="text-center">R</th>
            <th class="text-center">O</th>
            <th class="text-center">F</th>
            <th class="text-center">V</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $vta_items = [
            "Called them a name and/or criticized them.",
            "Tried to keep them from doing something they wanted to do (e.g., going out with friends, going to meetings).",
            "Gave them angry stares or looks.",
            "Prevented them from having money for their own use.",
            "Ended a discussion with them and made the decision yourself.",
            "Threatened to hit or throw something at them.",
            "Pushed, grabbed or shoved them.",
            "Put down their family and friends.",
            "Accused them of paying too much attention to someone or something else.",
            "Put them on an allowance.",
            "Used the children to threaten them (e.g., said they would lose custody or you would leave town with the children).",
            "Became upset because dinner, housework or laundry was not ready when you wanted it or done the way you thought it should be.",
            "Said things to scare them (e.g., said something ‘bad’ would happen or threatened suicide).",
            "Slapped, hit or punched them.",
            "Made them do something humiliating or degrading (e.g., made them beg for forgiveness or ask permission).",
            "Checked up on them (e.g., listened to phone calls, checked car mileage, called repeatedly at work).",
            "Drove recklessly when they were in the car.",
            "Pressured them to have sex in a way they didn’t like or want.",
            "Refused to do housework or child care.",
            "Threatened them with a knife, gun or other weapon.",
            "Told them they were a bad parent.",
            "Stopped them or tried to stop them from going to work or school.",
            "Threw, hit, kicked or smashed something.",
            "Kicked them.",
            "Physically forced them to have sex.",
            "Threw them around.",
            "Physically attacked the sexual parts of their body.",
            "Choked or strangled them."
          ];
          $letters = ['N','R','O','F','V'];

          foreach ($vta_items as $i => $label):
            $name = "vta_behavior[$i]";
          ?>
          <tr>
            <td><?= htmlspecialchars($label) ?></td>
            <?php foreach ($letters as $j => $L): ?>
              <td class="text-center">
                <input
                  type="radio"
                  class="form-check-input"
                  name="<?= $name ?>"
                  value="<?= $L ?>"
                  <?php if ($j === 0): ?>required<?php endif; ?>
                  aria-label="<?= $L ?>"
                >
              </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label for="vta_signature" class="form-label fw-semibold">Participant Signature <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="vta_signature" name="vta_signature" required>
      </div>
      <div class="col-md-6">
        <label for="vta_date" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="vta_date" name="vta_date" required>
      </div>
    </div>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>



  <!-- ================================================================== -->
  <!-- 8a. REFERRAL – CONSENT FOR DISCLOSURE OF INFORMATION               -->
  <!-- ================================================================== -->
  <fieldset class="step" id="step-consent-disclosure">
    <legend>8a&nbsp;&nbsp;Referral – Consent for Disclosure of Information</legend>

    <!-- Referral Type (required) -->
    <div class="mb-3">
      <label for="referral_type" class="form-label fw-semibold">Referral Type <span class="text-danger">*</span></label>
      <select id="referral_type" name="referral_type" class="form-select" required>
        <option value="">— Select one —</option>
        <option value="Probation Officer">Probation Officer</option>
        <option value="Courts of Law">Courts of Law</option>
        <option value="Parole or Child Protective Services">Parole or Child Protective Services</option>
      </select>
      <div class="form-text">Required by the program.</div>
    </div>

    <div class="border p-3 mb-3 rounded bg-light">
      <p>I understand that such disclosure will be made for the purposes of information exchange, progress reports, coordination of services, other investigative departments and referrals and facilitating victim safety. Disclosure is limited to information regarding attendance, participation, information exchange, coordination of services and referrals & facilitating victim safety.</p>
      <p>I understand that I may revoke this consent at any time and that may request for revocation must be in writing. If not earlier revoked, this consent for disclosure of information shall expire 1 year after completion of or termination from Fatherhood Campaign - BIPP. I understand the right to confidentiality. I further understand that this consent form gives Fatherhood Campaign - BIPP permission to share confidential information in the way described above.</p>
      <p>Release of information is voluntary, I understand I have a right to refuse Fatherhood Campaign - BIPF request for this disclosure. Fatherhood Campaign - BIPP reserves the right to dismiss any client who refuses to meet the provisions of The Texas Department of Criminal Justice-Community Justice Assistance Division and Texas Council on Family Violence Battering Intervention & Prevention Project guidelines.</p>
      <p>I also understand that it will be necessary for Fatherhood Campaign staff to contact other individuals regarding my abusive and controlling behaviors and issues affecting my participation in the program. This could include law enforcement, the courts, community supervision and corrections officers and others, according to agency policy. Fatherhood Campaign has my permission to release and obtain information concerning my behavior and program participation to and from the above organizations/persons:</p>
      <p>That when it is determined that there is probability of imminent physical injury to oneself or others, stall will take safety initiatives and may, if appropriate, notify medical or law enforcement personnel and/or the victim and referral source; and If the assessment (intake) or subsequent contact reveals the possibility of incidents of child abuse or neglect, or abuse of the elderly or disabled, it must be reported to the Texas Department of Family and Protective Services (TDFPS).</p>
      <p>That personal data and possibly additional information will be submitted to TDCL-CJAD by the program or provided for the purposes of performing program assessments and other research.</p>
      <p>Case records are subject to subpoena; and Information disclosed by batterers during an assessment (intake), group sessions, and exit is confidential and shall not be shared with victims.</p>
    </div>

    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="agree_disclosure" name="agree_disclosure" required>
      <label class="form-check-label" for="agree_disclosure">
        By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the San Antonio Fatherhood Campaign - Batterers Intervention & Prevention Program.
      </label>
    </div>

    <!-- Participant signature + date (both required) -->
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label for="participant_signature" class="form-label fw-semibold">Participant Signature <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="participant_signature" name="participant_signature" required>
      </div>
      <div class="col-md-6">
        <label for="participant_signature_date" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="participant_signature_date" name="participant_signature_date" required>
      </div>
    </div>



    <div class="nav-buttons mt-3">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>


  <!-- ================================================================== -->
  <!-- 8b. CONSENT FOR DISCLOSURE OF INFORMATION FOR PARTNERS             -->
  <!-- ================================================================== -->
  <fieldset class="step" id="step-consent-disclosure-partners">
    <legend>8b&nbsp;&nbsp;Consent for Disclosure of Information for Partners</legend>

    <!-- Relationship flag only (mirror of 8a style) -->
    <div class="row g-3 mb-3">
      <div class="col-12">
        <label for="victim_relationship_8b" class="form-label fw-semibold">Relationship to victim <span class="text-danger">*</span></label>
        <select id="victim_relationship_8b" name="victim_relationship_8b" class="form-select" required>
          <option value="">— Select one —</option>
          <option value="current_partner">Current Partner</option>
          <option value="ex_partner">Ex‑Partner</option>
          <option value="other">Other</option>
        </select>
      </div>
    </div>

    <div class="border p-3 mb-3 rounded bg-light">
      <p>I understand that such disclosure will be made for the purposes of progress reports, referrals and facilitating victim safety.</p>
      <p>Disclosure is limited to information regarding attendance, participation, information exchange and referrals for services. I understand that I may revoke this consent at any time and that my request for revocation must be in writing. If not earlier revoked, this consent for disclosure of information shall expire 1 year after my completion of or termination from Fatherhood Campaign - Batterers Intervention &amp; Prevention Program.</p>
      <p>I understand my right to confidentiality. I further understand that this consent form gives Fatherhood Campaign - BIPP permission to share confidential information about me in the way described above. I understand that Victim will be contacted by the Victim Advocate and offered counseling services. They will be provided enrollment, completion or termination information from Fatherhood Campaign - BIPP</p>
      <p>Release of information is voluntary; I understand I have a right to refuse Fatherhood Campaign - BIPP request for this disclosure.</p>
      <p>Fatherhood Campaign - BIPP reserves the right to dismiss any client who refuses to meet the provisions of The Texas Department of Criminal Justice-Community Justice Assistance Division and Texas Council on Family Violence Battering Intervention &amp; Prevention Project guidelines. Information disclosed by batterers during an assessment (intake), group sessions, and exit is confidential and shall not be shared with victims.</p>
    </div>

    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="agree_disclosure_partners" name="agree_disclosure_partners" required>
      <label class="form-check-label" for="agree_disclosure_partners">
        By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the San Antonio Fatherhood Campaign - Batterers Intervention & Prevention Program.
      </label>
    </div>

    

    <!-- Participant signature + date (both required) -->
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label for="disclosure_signature_8b" class="form-label fw-semibold">Participant Signature <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="disclosure_signature_8b" name="disclosure_signature_8b" required>
      </div>
      <div class="col-md-6">
        <label for="disclosure_date_8b" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="disclosure_date_8b" name="disclosure_date_8b" required>
      </div>
    </div>

    <div class="nav-buttons mt-3">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>




  <!-- ================================================================== -->
  <!-- 8c. SAFC PROGRAM AGREEMENT + TAKING RESPONSIBILITY (merged page)   -->
  <!-- ================================================================== -->
  <fieldset class="step" id="step-consent-responsibility">
    <legend>8c&nbsp;&nbsp;SAFC Program Agreement &amp; Taking Responsibility</legend>

    <div class="border p-3 mb-3 rounded bg-light">
      <h6 class="mb-2">Program Agreement</h6>
      <p>Fee per session is $25.00; this fee is only one type of demonstration of your accountability and restitution for violent behavior. Breaks, assessment (intake), and orientation are not to be included towards the 36 hours.</p>

      <p>
        I agree to start 
        <input type="date" class="form-control d-inline-block w-auto ms-2 me-2" 
              id="start_date_8c" name="start_date_8c">
        on 
        <select class="form-select d-inline-block w-auto ms-2 me-2" 
                id="start_dow_8c" name="start_dow_8c">
          <option value="">— Select —</option>
          <option>Sunday</option><option>Monday</option><option>Tuesday</option>
          <option>Wednesday</option><option>Thursday</option><option>Friday</option><option>Saturday</option>
        </select>
        from 
        <input type="time" class="form-control d-inline-block w-auto ms-2 me-2" 
              id="start_time_8c" name="start_time_8c">.
      </p>

      <p>Battering Intervention and Prevention Program consist of Assessment (Intake) and Orientation and at least 36 hours of group sessions in a minimum of (20) weekly sessions, not to exceed one session per week,</p>
      <p>Exit Session. If dismissed, the client must apply to re-enter into the Fatherhood Campaign - BIPP. Re-entry is considered on a case by case basis. I understand that I can not re-enter the program until I have paid off my previous balance.</p>
      <p>Clients who miss (3) consecutive sessions (group or individual) or a (total of 5 sessions), you will be discharged from the program. Your referral sources will determine what happens with your case as a result of your absences.</p>
      <p>There are no excused absences. Incarceration is an inexcusable absence. Any new offense related to domestic violence is an automatic dismissal.</p>
      <p>If you have a cell phone it must be turned off or on silent and placed out of sight; text messaging is not allowed.</p>
      <p>No Food allowed in group room or virtual meeting.</p>
      <p>If you destroy or damage property, you will be liable for the damages.</p>
      <p>Restroom breaks should take no longer than 5 minutes unless you have prior approval from BIPP staff.</p>
      <p>Payment for services is due at the time service is rendered. You will not be credited for attending groups or individual sessions unless payment is received. Client is required to maintain no more than a $25 balance. I will continue to attend until I have a zero balance. Attendance may exceed 24wks if payment is not completed.</p>
      <p>This building is designated as a Non-Smoking facility or virtual meeting.</p>
      <p>I hereby agree to arrive to all of my sessions on time. If you are (5) MINUTES AFTER THE DESIGNATED START TIME-you will not receive credit for attending the group.</p>
      <p>I understand that I MUST sign the group attendance roster in the group room or check in during the virtual meeting. I WILL NOT be counted present for the session unless I sign the roster or check in during the virtual meeting.</p>
      <p>I will notify the Fatherhood Campaign - BIPP of any change of address or phone numbers.</p>
      <p>All homework assignments must be completed. Clients will not receive credit for incomplete assignments.</p>
      <p>I hereby agree to contact BIPP by phone at 210-664-0102 when I am unable to attend a scheduled session. Failure to contact Fatherhood Campaign - BIPP within (2) consecutive absences is an automatic dismissal.</p>
      <p>I agree not to attend a group or during a virtual meeting under the influence of alcohol or drugs; refusal of a UA is an automatic discharge. It will be my responsibility to arrange transportation home so I’m not a danger to myself or others for driving under the influences. The referring agency will be notified of this incident. I will be required to pay $35 to take a UA and will not return to the group until the results are reported to the Fatherhood Campaign - BIPP.</p>
      <p>I hereby agree not to be abusive towards any staff person or other group members. I understand that I may not use sexist or racist language.</p>
      <p>I hereby agree not to be in possession of a weapon of any kind. I also agree to follow federal firearm restriction laws related to domestic violence offenses.</p>
      <p>I hereby agree to respect the confidentiality rights of my fellow client/group members. I further understand that a violation of this rule shall result in immediate termination from the program and shall be reported to the proper authorities.</p>
      <p>I hereby agree to notify a staff person of any and all emergencies that I am either a part of or a witness to.</p>
      <p>I understand that Fatherhood Campaign - BIPP is committed to helping me gain a better understanding of my problems and how to find productive solutions and that it is the main goal of my psycho educational classes.</p>
    </div>

    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="agree_program_8c" name="agree_program_8c" required>
      <label class="form-check-label" for="agree_program_8c">
        By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the San Antonio Fatherhood Campaign - Batterers Intervention & Prevention Program.
      </label>
    </div>


    <!-- Participant signature + date (both required) -->
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label for="program_signature_8ca" class="form-label fw-semibold">Participant Signature <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="program_signature_8ca" name="program_signature_8ca" required>
      </div>
      <div class="col-md-6">
        <label for="program_date_8ca" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="program_date_8ca" name="program_date_8ca" required>
      </div>
    </div>

    <div class="border p-3 mb-3 rounded bg-light">
      <h6 class="mb-2">Taking Responsibility</h6>
      <p>During group discussions, participants may not blame anyone else for their own behaviors.</p>
      <p>Participants agree to not use any form of violence, abusive, threatening and controlling behaviors including stalking during the weeks they are in the program. A participant who uses violence may be terminated from the program. This action will be reported to participants' referral agencies. Participants will cease violent, abusive, threatening, and controlling behaviors, including stalking and violation of a protective order. Participants who are terminated for this reason and wish to re-enter the program will re-start from 1st week.</p>
      <p>Participants will develop and adhere to a non-violence plan as outlined in the program curriculum.</p>
    </div>

    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="agree_responsibility_8c" name="agree_responsibility_8c" required>
      <label class="form-check-label" for="agree_responsibility_8c">
        By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the San Antonio Fatherhood Campaign - Batterers Intervention & Prevention Program.
      </label>
    </div>

    
    <!-- Participant signature + date (both required) -->
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label for="program_signature_8cb" class="form-label fw-semibold">Participant Signature <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="program_signature_8cb" name="program_signature_8cb" required>
      </div>
      <div class="col-md-6">
        <label for="program_date_8cb" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="program_date_8cb" name="program_date_8cb" required>
      </div>
    </div>

    <div class="nav-buttons mt-3">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <!-- ================================================================== -->
  <!-- 8d. VIRTUAL GROUP RULES                                            -->
  <!-- ================================================================== -->
  <fieldset class="step" id="step-virtual-group-rules">
    <legend>8d&nbsp;&nbsp;Virtual Group Rules</legend>

    <div class="border p-3 mb-3 rounded bg-light">
      <p class="mb-2">Please Review and Adhere to the Group rules for a successful group experience:</p>

      <ol class="vgr-list mb-0 ps-3">
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_1" id="vgr_initial_1" maxlength="4" placeholder="Initial" required>
          Payment on-line does not guarantee credit for attending a group session.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_2" id="vgr_initial_2" maxlength="4" placeholder="Initial" required>
          Attendance is taken during the group-that will determine credit for attending.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_3" id="vgr_initial_3" maxlength="4" placeholder="Initial" required>
          Participant’s faces/eyes must always be visible – always keep a close-up of your face on webcam unless you’re on break.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_4" id="vgr_initial_4" maxlength="4" placeholder="Initial" required>
          No drowsiness or sleeping during group.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_5" id="vgr_initial_5" maxlength="4" placeholder="Initial" required>
          Mic’s stay off unless/until you are speaking.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_6" id="vgr_initial_6" maxlength="4" placeholder="Initial" required>
          Participants must be alone.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_7" id="vgr_initial_7" maxlength="4" placeholder="Initial" required>
          Cameras stay on unless you are taking a break; while on break, you must stay connected to Zoom.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_8" id="vgr_initial_8" maxlength="4" placeholder="Initial" required>
          No other devices can be in use during group time: TV, cell phone, stereo, etc.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_9" id="vgr_initial_9" maxlength="4" placeholder="Initial" required>
          While in class, set your phone on “Don’t disturb” mode.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_10" id="vgr_initial_10" maxlength="4" placeholder="Initial" required>
          No children, relatives, friends, or pets can be present.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_11" id="vgr_initial_11" maxlength="4" placeholder="Initial" required>
          No sunglasses, hats, hoodies, or filters that can disguise or camouflage your physical self or background.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_12" id="vgr_initial_12" maxlength="4" placeholder="Initial" required>
          No eating or smoking/vaping. Non-alcoholic beverages only.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_13" id="vgr_initial_13" maxlength="4" placeholder="Initial" required>
          No multi-tasking, reading, texting, walking around, driving, self-grooming, working, etc…
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_14" id="vgr_initial_14" maxlength="4" placeholder="Initial" required>
          Please use the Zoom feature to raise your hand so that the instructor sees that you’re requesting to participate.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_15" id="vgr_initial_15" maxlength="4" placeholder="Initial" required>
          Please remember to lower your hand when finished.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_16" id="vgr_initial_16" maxlength="4" placeholder="Initial" required>
          Please arrive prepared, bring pen, paper, charged device, charger, and make sure your internet and Wi‑Fi connection is strong and that you’re still.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_17" id="vgr_initial_17" maxlength="4" placeholder="Initial" required>
          To get credit for class, you’re required to stay attentive and actively participate throughout class.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_18" id="vgr_initial_18" maxlength="4" placeholder="Initial" required>
          If removed from the group for violation of rules, clients will not receive credit for attending and forfeit their $30 payment.
        </li>
        <li class="rule-hanging">
          <input type="text" class="initials-box" name="vgr_initial_19" id="vgr_initial_19" maxlength="4" placeholder="Initial" required>
          Please respond promptly to all chat messages and let your instructor/moderator know you understand what is being requested and that you will make the appropriate adjustments to adhere to the rules.
        </li>
      </ol>
    </div>

    <!-- Participant signature + date (both required) -->
    <div class="row g-3 mb-3 mt-2">
      <div class="col-md-6">
        <label for="vgr_signature_8d" class="form-label fw-semibold">Participant Signature <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="vgr_signature_8d" name="vgr_signature_8d" required>
      </div>
      <div class="col-md-6">
        <label for="vgr_date_8d" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="vgr_date_8d" name="vgr_date_8d" required>
      </div>
    </div>

    <div class="nav-buttons mt-3">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>




  <!-- ================================================================== -->
  <!-- 8e. POLICY FOR CLIENTS & TERMINATION POLICY                        -->
  <!-- ================================================================== -->
  <fieldset class="step" id="step-consent-termination">
    <legend>8e&nbsp;&nbsp;Policy For Clients and Termination Policy</legend>

    <div class="border p-3 mb-3 rounded bg-light">
      <h6 class="mb-2">Policy For Clients and Termination Policy</h6>
      <p>I have received a copy of the “Policy for Clients” for Fatherhood Campaign - BIPP. I understand my rights and responsibilities and I agree to enter the Fatherhood Campaign - BIPP.</p>
      <p>I understand that in accordance with Guideline 31 of the Texas Department of Criminal Justice-Community Justice Assistance Division and Texas Council on Family Violence Battering Intervention Prevention Program guidelines, I am being provided a written agreement that clearly delineates the obligation of the Fatherhood Campaign - BIPP to the client. I understand that the Fatherhood Campaign - BIPP shall:</p>
      <ol class="mb-3 ps-3">
        <li>Provide services in a manner that I can understand.</li>
        <li>Provide copies of all written agreements.</li>
        <li>Notify me of changes in group time and schedules.</li>
        <li>Comply with anti-discrimination laws.</li>
        <li>Report quarterly to probation, courts of law, and/or other referral agencies regarding my progress or lack of progress during the group.</li>
        <li>Report to me regarding my status and participation.</li>
        <li>Provide fair and humane treatment.</li>
      </ol>

      <h6 class="mb-2">TERMINATION POLICY</h6>
      <p>As a client of Fatherhood Campaign - BIPP you have the right to terminate services with our agency at any moment.</p>
      <p>The risk of terminating services will be explained to you by a counselor/instructor. You have the right to choose other agencies for your services and Fatherhood Campaign - BIPP will provide you with a list of known community agencies that may provide the services you need, except for clients referred by Probation; clients will be referred back to their Supervision Officer.</p>
      <p>Fatherhood Campaign - BIPP also has the right to terminate services with clients if :</p>
      <ol type="A" class="mb-3 ps-3">
        <li>Continued abuse, particularly physical violence.</li>
        <li>Client has accumulated (3) absences total. More than 2 discharged within a 1 year period.</li>
        <li>Client has failed to pay for services over $100 dollars</li>
        <li>The client is believed to be violent/aggressive towards others or staff.</li>
        <li>Client is involved in illegal activities on the premises. Any new domestic violence offense while enrolled in the program. Incarceration is an inexcusable absence.</li>
        <li>Client need for treatment is incompatible with types of services Fatherhood Campaign - BIPP Client violates any of the BIPP rules.</li>
        <li>Clients have the right to seek other resources outside of Fatherhood Campaign - BIPP and when possible, Fatherhood Campaign - BIPP staff will provide or make a referral.</li>
      </ol>
      <p>The above Termination Policy applies to clients who are attending services on a voluntary basis or Court-ordered to receive services or who are mandated to receive services by other entities; however, clients are responsible to check with those entities who mandate them to come regarding the alternatives for receiving services in another agency or consequences for choosing to stop services before making this final decision.</p>
      <p>Fatherhood Campaign - BIPP will provide batterers at the time of assessment (intake) with a copy of the circumstances under which they can be terminated before completion.</p>
    </div>

    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="agree_termination_8e" name="agree_termination_8e" required>
      <label class="form-check-label" for="agree_termination_8e">
        By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the San Antonio Fatherhood Campaign - Batterers Intervention & Prevention Program.
      </label>
    </div>

    <!-- Participant signature + date (both required) -->
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label for="termination_signature_8e" class="form-label fw-semibold">Participant Signature <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="termination_signature_8e" name="termination_signature_8e" required>
      </div>
      <div class="col-md-6">
        <label for="termination_date_8e" class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="termination_date_8e" name="termination_date_8e" required>
      </div>
    </div>

    <div class="nav-buttons mt-3">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>



  <!-- ================================================================== -->
  <!--  9. INDIVIDUALIZED PLAN & CASE NOTES                               -->
  <!-- ================================================================== -->
  <fieldset class="step">
    <legend>9&nbsp;&nbsp;Individualized Plan & Case Notes</legend>

    <!-- 9A: Individualized Plan -->
    <h5 class="mt-3">9A. Individualized Plan</h5>
    <label>Choose your reason(s) for attending the Battering Intervention & Prevention Program (BIPP): <span class="text-danger">*</span></label>

    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="protective_order" name="reasons[]" value="Protective Order">
      <label class="form-check-label" for="protective_order">Protective Order</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="aggravated_assault" name="reasons[]" value="Aggravated Assault">
      <label class="form-check-label" for="aggravated_assault">Aggravated Assault</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="violation_po" name="reasons[]" value="Violation of Protective Order">
      <label class="form-check-label" for="violation_po">Violation of Protective Order</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="choking" name="reasons[]" value="Choking/Strangulation Charge">
      <label class="form-check-label" for="choking">Choking / Strangulation Charge</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="interfering_911" name="reasons[]" value="Interfering with a 911 Call">
      <label class="form-check-label" for="interfering_911">Interfering with a 911 Call</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="terroristic_threat" name="reasons[]" value="Terroristic Threat">
      <label class="form-check-label" for="terroristic_threat">Terroristic Threat</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="injury_child" name="reasons[]" value="Injury to a Child">
      <label class="form-check-label" for="injury_child">Injury to a Child</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="other_reason" name="reasons[]" value="Other">
      <label class="form-check-label" for="other_reason">Other</label>
    </div>

    <div class="mt-2" id="other_reason_text_container" style="display:none">
      <label for="other_reason_text">Please explain “Other”</label>
      <input type="text" id="other_reason_text" name="other_reason_text" class="form-control">
    </div>

    <script>
    (function () {
      const otherBox = document.getElementById('other_reason_text_container');
      const otherInp = document.getElementById('other_reason_text');
      const boxes = document.querySelectorAll('input[name="reasons[]"]');

      function syncOther() {
        const otherChecked = Array.from(boxes).some(el => el.value === 'Other' && el.checked);
        if (otherChecked) {
          otherBox.style.display = '';
          otherInp.required = true;
          otherInp.disabled = false;
        } else {
          otherBox.style.display = 'none';
          otherInp.required = false;
          otherInp.disabled = true; // so it doesn't participate in native validation
          otherInp.value = '';
        }
      }

      boxes.forEach(el => el.addEventListener('change', syncOther));
      syncOther();
    })();
    </script>



    <!-- Describe Reason -->
    <div class="mt-3">
      <label for="describe_reason">Describe the reason you are here (offense) <span class="text-danger">*</span></label>
      <textarea name="describe_reason" id="describe_reason" class="form-control" required></textarea>
    </div>

    <!-- Personal Goal -->
    <div class="mt-3">
      <label for="personal_goal_bipp">Your personal goal for attending BIPP <span class="text-danger">*</span></label>
      <textarea name="personal_goal_bipp" id="personal_goal_bipp" class="form-control" required></textarea>
      <div class="alert alert-info mt-2">
        <strong>EXAMPLE:</strong> Objective: Client will increase their knowledge regarding the issue of abuse, domestic violence and skills that can help them change behaviors and eliminate abuse and violence from their relationships. Strategies: Client will attend the BIPP group weekly for 90 minutes and will participate actively and display receptiveness to the information presented. Client will make consistent application of skills presented by thinking about the new information presented, reviewing the handouts, talking about what they're learning with others, asking questions, making application of skills, completing assigned homework, giving examples in group of the progress they are making and by only focusing on them and their relationship with their partner. Client will practice POSITIVE SELF-TALK by stating I DON’T ARGUE, I DON’T FIGHT AND IF NEEDED I TAKE A TIME-OUT SO THAT I KEEP ME AND MY FAMILY MEMBERS SAFE FROM ABUSE AND VIOLENCE.
      </div>
    </div>


    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <!-- ================================================================== -->
  <!-- 10. DIGITAL SIGNATURE                                             -->
  <!-- ================================================================== -->
  <fieldset class="step">
    <legend>10&nbsp;&nbsp;Digital Signature</legend>

    <p><strong>Entering your name constitutes a digital signature.</strong> By clicking <strong>SUBMIT</strong>, I hereby confirm the above information to the best of my knowledge is correct and true, with no misleading or false content in accordance with Texas Perjury Statute, Sec. 37.02 (a) (2) Chapter 32, Civil Practice and Remedies Code.</p>

    <!-- Signature input -->
    <label class="required">Signature – type your full legal name</label>
    <input type="text" name="digital_signature" class="form-control" required>

    <!-- Optional: Signature pad (commented out, can be enabled with JS library)
    <label>Draw your signature (optional)</label>
    <canvas id="signature-pad" style="border: 1px solid #ccc; width: 100%; height: 150px;"></canvas>
    <input type="hidden" name="signature_image" id="signature_image">
    -->

    <!-- Date signed -->
    <div class="mt-3">
      <label for="signature_date">Date Signed</label>
      <input type="date" id="signature_date" name="signature_date" class="form-control" readonly>
    </div>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="submit" class="btn btn-success">Submit Intake Packet</button>
    </div>
  </fieldset>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form       = document.querySelector('form');
  const steps      = Array.from(form.querySelectorAll('fieldset.step'));
  const barFill    = document.querySelector('#progressBar span');
  const alertBox   = document.getElementById('stepAlert');
  let   current    = 0;

  // Restore last page
  const savedStep = +localStorage.getItem('intake_step') || 0;
  if (savedStep && savedStep < steps.length) current = savedStep;

  // LocalStorage restore + save
  document.querySelectorAll('input,select,textarea').forEach(el => {
    const key = 'intake_' + el.name;
    const saved = localStorage.getItem(key);
    const evt = (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'radio') ? 'change' : 'input';

    if (el.type === 'radio') {
      if (saved !== null) el.checked = (el.value === saved);
      el.addEventListener('change', () => {
        const checked = document.querySelector(`input[name="${el.name}"]:checked`);
        localStorage.setItem(key, checked ? checked.value : '');
      });
      return;
    }
    if (saved !== null) {
      if (el.type === 'checkbox') el.checked = (saved === '1'); else el.value = saved;
    }
    el.addEventListener(evt, () => {
      const val = (el.type === 'checkbox') ? (el.checked ? '1' : '0') : el.value;
      localStorage.setItem(key, val);
    });
  });


  function showStep(i) {
    steps[current].classList.remove('active');
    current = i;
    steps[current].classList.add('active');
    barFill.style.width = ((current + 1) / steps.length * 100) + '%';
    localStorage.setItem('intake_step', current);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function clearHighlights() {
    alertBox.style.display = 'none';
    alertBox.textContent = '';
    steps[current].querySelectorAll('.invalid-field').forEach(el => el.classList.remove('invalid-field'));
  }

  steps.forEach((fs, i) => fs.classList.toggle('active', i === current));
  barFill.style.width = ((current + 1) / steps.length * 100) + '%';
  localStorage.setItem('intake_step', current);

  form.addEventListener('click', e => {
    if (e.target.classList.contains('next')) {
      clearHighlights();
      const invalid = Array.from(steps[current].querySelectorAll(':invalid'));
      if (invalid.length) {
        invalid.forEach(el => el.classList.add('invalid-field'));
        const names = invalid.map(el => {
          const byFor = steps[current].querySelector(`label[for="${el.id}"]`);
          const near = el.closest('.col')?.querySelector('label');
          return ((byFor ?? near)?.textContent || el.name || 'field').trim();
        });
        alertBox.textContent = 'Please complete or correct: ' + names.join(', ');
        alertBox.style.display = 'block';
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
        invalid[0].focus({ preventScroll: true });
        return;
      }
      if (!validateVictimExtras()) return;
      if (current < steps.length - 1) showStep(current + 1);
    }

    if (e.target.classList.contains('prev')) {
      clearHighlights();
      if (current > 0) showStep(current - 1);
    }
  });

  form.addEventListener('input', e => {
    if (e.target.classList.contains('invalid-field') && e.target.checkValidity()) {
      e.target.classList.remove('invalid-field');
      if (!steps[current].querySelector(':invalid')) {
        alertBox.style.display = 'none';
      }
    }
  });

  // Page 4 – Household / Children logic
  const hasChildrenSel = document.getElementById('has_children');
  const childDetailsBox = document.getElementById('children-details');
  const neglectedSel = document.getElementById('children_neglected');
  const cpsBlock = document.getElementById('cps-block');
  
  const abusedPhysicalSel  = document.getElementById('abused_physically');
  const abusedSexualSel    = document.getElementById('abused_sexually');
  const abusedEmotionalSel = document.getElementById('abused_emotionally');


  function toggleCPSBlock() {
    // Show CPS if ANY of the 4 is "Yes"
    const show = [abusedPhysicalSel, abusedSexualSel, abusedEmotionalSel, neglectedSel]
      .some(el => el && el.value === '1');

    cpsBlock.classList.toggle('d-none', !show);
    cpsBlock.querySelectorAll('select').forEach(el => el.required = show);

    if (show) {
      // keep year/status sub-questions in sync
      toggleCPSCaseDetails();
    } else {
      // hard reset if CPS block is hidden
      cpsBlock.querySelectorAll('select').forEach(el => el.value = '');

      const yearEl   = document.getElementById('cps_case_year');
      const statusEl = document.getElementById('cps_case_open_closed');
      const workerEl = document.getElementById('cps_caseworker_contact');

      if (yearEl)   { yearEl.value = '';   yearEl.required = false; }
      if (statusEl) { statusEl.value = ''; statusEl.required = false; }
      if (workerEl) { workerEl.value = ''; workerEl.required = false; }

      document.getElementById('cps-case-details').classList.add('d-none');
      document.getElementById('cps-caseworker-block').classList.add('d-none');
    }
  }

  // Show case details if cps_notified=Yes OR cps_care=Yes
  function toggleCPSCaseDetails() {
    const notified = document.getElementById('cps_notified').value === '1';
    const care = document.getElementById('cps_care').value === '1';
    const show = notified || care;

    const details = document.getElementById('cps-case-details');
    const year = document.getElementById('cps_case_year');
    const status = document.getElementById('cps_case_open_closed');

    details.classList.toggle('d-none', !show);
    year.required = show;
    status.required = show;

    if (!show) {
      year.value = '';
      status.value = '';
      toggleCPSWorkerBlock(); // will hide & clear worker block
    }
  }

  function toggleCPSWorkerBlock() {
    const status = document.getElementById('cps_case_open_closed').value;
    const workerBlock = document.getElementById('cps-caseworker-block');
    const workerField = document.getElementById('cps_caseworker_contact');
    const show = status === 'Open';

    workerBlock.classList.toggle('d-none', !show);
    workerField.required = show;
    if (!show) workerField.value = '';
  }

  // hook up listeners
  document.getElementById('cps_notified').addEventListener('change', toggleCPSCaseDetails);
  document.getElementById('cps_care').addEventListener('change', toggleCPSCaseDetails);
  document.getElementById('cps_case_open_closed').addEventListener('change', toggleCPSWorkerBlock);

  // also call from your existing toggle so it stays in sync with Neglect gating
  const _origToggleCPSBlock = toggleCPSBlock;
  toggleCPSBlock = function() {
    _origToggleCPSBlock();

    if (!document.getElementById('cps-block').classList.contains('d-none')) {
      toggleCPSCaseDetails();
    } else {
      // hard reset if the CPS block is hidden
      const yearEl   = document.getElementById('cps_case_year');
      const statusEl = document.getElementById('cps_case_open_closed');
      const workerEl = document.getElementById('cps_caseworker_contact');

      yearEl.value = '';
      statusEl.value = '';

      // make sure hidden fields aren't required
      yearEl.required = false;
      statusEl.required = false;
      workerEl.required = false;

      toggleCPSWorkerBlock(); // also clears worker field via its own logic
      document.getElementById('cps-case-details').classList.add('d-none');
      document.getElementById('cps-caseworker-block').classList.add('d-none');
    }
  };



  function toggleChildBlock() {
    const show = (hasChildrenSel.value === '1');
    childDetailsBox.classList.toggle('d-none', !show);

    // Only make baseline fields required when children = Yes
    [
      '#children_live_with_you',
      '#children_names_ages',
      '#abused_physically',
      '#abused_sexually',
      '#abused_emotionally',
      '#children_neglected'
    ].forEach(sel => {
      const el = document.querySelector(sel);
      if (el) el.required = show;
    });

    if (!show) {
      childDetailsBox.querySelectorAll('input, select').forEach(el => el.value = '');
    }

    // keep CPS pieces in sync with Neglect gating
    toggleCPSBlock();
  }


  hasChildrenSel.addEventListener('change', toggleChildBlock);
  [abusedPhysicalSel, abusedSexualSel, abusedEmotionalSel, neglectedSel]
    .forEach(el => el.addEventListener('change', toggleCPSBlock));

  toggleChildBlock();
  toggleCPSBlock();

  // Page 5 – Substance Use
  [
    { sel: 'alcohol_past', grp: 'alcohol_frequency_grp' },
    { sel: 'alcohol_current', grp: 'alcohol_current_details_grp' },
    { sel: 'drug_past', grp: 'drug_past_details_grp' },
    { sel: 'drug_current', grp: 'drug_current_details_grp' }
  ].forEach(({ sel, grp }) => {
    const selectEl = document.getElementById(sel);
    const detailGrp = document.getElementById(grp);
    const inputEl = detailGrp.querySelector('input');
    const stars = detailGrp.querySelectorAll('.req-star');

    function toggle() {
      const show = (selectEl.value === '1');
      detailGrp.classList.toggle('d-none', !show);
      inputEl.required = show;
      if (!show) inputEl.value = '';
      stars.forEach(s => s.classList.toggle('d-none', !show));
    }

    toggle();
    selectEl.addEventListener('change', toggle);
  });

  // Page 6 – Mental Health
  [
    { sel: 'counseling_history', grp: 'counseling_history_details_grp' },
    { sel: 'depressed_currently', grp: 'depressed_currently_details_grp' },
    { sel: 'attempted_suicide', grp: 'attempted_suicide_details_grp' },
    { sel: 'mental_health_meds', grp: 'mental_health_meds_details_grp' },
    { sel: 'head_trauma_history', grp: 'head_trauma_details_grp' },
    { sel: 'weapon_possession_history', grp: 'weapon_possession_details_grp' } // ← add this
  ].forEach(({ sel, grp }) => {
    const control = document.getElementById(sel);
    const groupBox = document.getElementById(grp);
    if (!control || !groupBox) return;
    const inputs = groupBox.querySelectorAll('input, textarea');
    const stars = groupBox.querySelectorAll('.req-star');

    function toggle() {
      const show = (control.value === '1');
      groupBox.classList.toggle('d-none', !show);
      inputs.forEach(i => {
        i.required = show;
        if (!show) i.value = '';
      });
      stars.forEach(s => s.classList.toggle('d-none', !show));
    }

    toggle();
    control.addEventListener('change', toggle);
  });

  // Victim info section behavior
  // Victim info section behavior (radio-based)
  const victimInfoBlock     = document.getElementById('victim-info-block');
  const victimContactBlock  = document.getElementById('victim-contact-block');

  const vkRadios = Array.from(document.querySelectorAll('input[name="victim_knowledge"]'));
  const vkLabels = Array.from(document.querySelectorAll('.victim-knowledge-option'));

  // Relationship "Other" toggle (unchanged)
  const relSel       = document.querySelector('select[name="victim_relationship"]');
  const relOtherGrp  = document.getElementById('victim_relationship_other_grp');
  const relOther     = document.getElementById('victim_relationship_other');

  // Contact fields we may toggle required on (unchanged)
  const victimFirst  = document.querySelector('input[name="victim_first_name"]');
  const victimLast   = document.querySelector('input[name="victim_last_name"]');
  const victimGender = document.querySelector('select[name="victim_gender"]');
  const victimPhone  = document.querySelector('input[name="victim_phone"]');
  const victimAge    = document.querySelector('input[name="victim_age"]');
  const victimDOB    = document.querySelector('input[name="victim_dob"]');

  function getVK() {
    return vkRadios.find(r => r.checked)?.value ?? '';
  }
  function updateVKUI() {
    vkLabels.forEach(lbl => {
      const r = lbl.querySelector('input[name="victim_knowledge"]');
      lbl.classList.toggle('active', r && r.checked);
    });
  }
  function applyVictimRequirements() {
    const mode = getVK(); // '0' (no knowledge) or '1' (have knowledge)
    if (!mode) return;    // nothing chosen yet

    victimInfoBlock.classList.remove('d-none');
    victimContactBlock.classList.remove('d-none');

    [victimFirst, victimLast, victimGender, victimPhone, victimDOB, victimAge].forEach(el => el && (el.required = false));

    if (mode === '1') {
      [victimFirst, victimLast, victimGender, victimPhone].forEach(el => el.required = true);
    } else if (mode === '0') {
      [victimFirst, victimLast, victimGender].forEach(el => el.required = true);
    }
  }

  // ----- Page 7 – Consent logic (single-name version) -----
  const swornBlock = document.getElementById('sworn-consent-block');
  const releaseName = document.getElementById('consent_release_sig_name');
  const releaseDate = document.getElementById('consent_release_signed_date');
  const swornName   = document.getElementById('sworn_sig_name');
  const swornDate   = document.getElementById('sworn_signed_date');
  const swornPartPrev = document.getElementById('sworn_participant_name_preview');
  const swornVictPrev = document.getElementById('sworn_victim_name_preview');

  function todayIfBlank(el){ if(el && !el.value) el.valueAsDate = new Date(); }

  function setConsentVisibility() {
    const mode = getVK(); // '0' (no knowledge), '1' (has knowledge), or ''
    const needSworn = (mode === '0');     // only show when "I do NOT have knowledge…" is selected
    swornBlock.classList.toggle('d-none', !needSworn);

    if (releaseName) releaseName.required = true;
    if (releaseDate) releaseDate.required = true;

    if (swornName)  swornName.required  = needSworn;
    if (swornDate)  swornDate.required  = needSworn;

    if (!needSworn && swornName)  swornName.value = '';
    if (!needSworn && swornDate)  swornDate.value = '';
  }

  function updateNamePreviewsAndPrefill() {
    const pFirst = (form.elements.first_name?.value || '').trim();
    const pLast  = (form.elements.last_name?.value || '').trim();
    const participant = [pFirst, pLast].filter(Boolean).join(' ');
    const vFirst = (form.elements.victim_first_name?.value || '').trim();
    const vLast  = (form.elements.victim_last_name?.value || '').trim();
    const victim = [vFirst, vLast].filter(Boolean).join(' ');

    if (swornPartPrev) swornPartPrev.textContent = participant || 'Participant';
    if (swornVictPrev) swornVictPrev.textContent = victim || 'Victim/Survivor';
  }

  // integrate with your existing applyVictimRequirements()
  const __origApplyVK = applyVictimRequirements;
  applyVictimRequirements = function(){
    __origApplyVK();
    setConsentVisibility();
    updateNamePreviewsAndPrefill();
  };

  // keep previews in sync as names change
  ['first_name','last_name','victim_first_name','victim_last_name'].forEach(n=>{
    const el=form.elements[n]; if(el) el.addEventListener('input', updateNamePreviewsAndPrefill);
  });

  // init on load

  setConsentVisibility();
  updateNamePreviewsAndPrefill();


  // Relationship "Other" handler (kept)
  relSel.addEventListener('change', () => {
    const isOther = relSel.value === 'Other';
    relOtherGrp.classList.toggle('d-none', !isOther);
    relOther.required = isOther;
    if (!isOther) relOther.value = '';
  });

  // phone optional for "no knowledge" case by default
  victimPhone.required = false;

  // wire up radios
  vkRadios.forEach(r => r.addEventListener('change', () => {
    updateVKUI();
    applyVictimRequirements();
  }));

  // init UI
  updateVKUI();
  if (getVK()) applyVictimRequirements();

  // Live-with-victim → show count when NO
  const liveWithSelect = document.getElementById('live_with_victim');
  const childBlock     = document.getElementById('children_under18_block');
  const childInput     = document.getElementById('children_under_18');
  function toggleVictimChildBlock() {
    const show = liveWithSelect.value === '0'; // show when NO
    childBlock.classList.toggle('d-none', !show);
    childInput.required = show;
    if (!show) childInput.value = '';
  }
  liveWithSelect.addEventListener('change', toggleVictimChildBlock);
  toggleVictimChildBlock();

  // "Do your child(ren) live with you?" → Other text box toggle
  const p7LiveSel   = document.getElementById('children_live_with_you_p7');
  const p7OtherGrp  = document.getElementById('children_live_with_you_p7_other_grp');
  const p7Other     = document.getElementById('children_live_with_you_p7_other');
  function toggleP7Other() {
    const isOther = p7LiveSel.value === '2';
    p7OtherGrp.classList.toggle('d-none', !isOther);
    p7Other.required = isOther;
    if (!isOther) p7Other.value = '';
  }
  p7LiveSel.addEventListener('change', toggleP7Other);
  toggleP7Other();

  // Extra validation for DOB-or-Age when "no knowledge"
  function validateVictimExtras() {
    if (steps[current].id !== 'step-victim') return true;
    const mode = getVK() || '0';

    const anyProvided = ['victim_first_name','victim_last_name','victim_gender','victim_phone','victim_email','victim_address','victim_city','victim_state','victim_zip','victim_dob','victim_age']
      .some(n => (form.elements[n]?.value || '').trim() !== '');

    const needDOBorAge = (mode === '1') || (mode === '0' && anyProvided);
    if (needDOBorAge && !form.elements.victim_dob.value && !form.elements.victim_age.value) {
      [form.elements.victim_dob, form.elements.victim_age].forEach(el => el.classList.add('invalid-field'));
      alertBox.textContent = 'Please provide the victim’s DOB or an estimated age.';
      alertBox.style.display = 'block';
      alertBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
      form.elements.victim_dob.focus({ preventScroll: true });
      return false;
    }
    return true;
  }

  /* ---------- Page 7b: Victim Treatment Assessment ---------- */
  $allowedScale = ['N','R','O','F','V'];
  $vtaErrors = [];

  

  // If you already have a global $errors array, merge:

  /* Example with mysqli prepared statement (adjust to your code):
  $placeholders = rtrim(str_repeat('?,', count($insertCols)), ',');
  $sql = "INSERT INTO intake_packet (" . implode(',', $insertCols) . ") VALUES ($placeholders)";
  $stmt = $con->prepare($sql);
  $types = str_repeat('s', count($insertParams));   // all strings/DATE as string is fine
  $stmt->bind_param($types, ...$insertParams);
  $stmt->execute();
  */



  // Hook our extra validator into the "Next" button flow
  // (Add this inside your existing form click handler, right before advancing)



  const today = new Date().toISOString().split('T')[0];
    const dateField = document.getElementById('signature_date');
    if (dateField) {
      dateField.value = today;
    }

  form.addEventListener('submit', () => {
    // Default to "no knowledge" if untouched
    

    // Pack CPS year/status into the hidden field (unchanged)
    const year = (document.getElementById('cps_case_year')?.value || '').trim();
    const status = (document.getElementById('cps_case_open_closed')?.value || '').trim();
    const hidden = document.getElementById('cps_case_year_status');
    hidden.value = (year || status) ? `${year}${year && status ? ' — ' : ''}${status}` : '';
  });


});
</script>
<script>
document.addEventListener('submit', function (e) {
  // Disable native validation for hidden steps
  document.querySelectorAll('fieldset.step').forEach(fs => {
    const visible = getComputedStyle(fs).display !== 'none';
    if (!visible) {
      fs.querySelectorAll('[required]').forEach(el => { el.required = false; });
      fs.querySelectorAll('input,select,textarea,button').forEach(el => { el.disabled = false; });
      // ^ keep disabled=false for data posts; the “Other” text is separately managed
    }
  });
}, { capture: true });

document.addEventListener('submit', function (e) {
  const btn = e.target.querySelector('button[type="submit"], .submit');
  if (btn && !btn.disabled) {
    btn.disabled = true;
    btn.dataset.origText = btn.dataset.origText || btn.textContent;
    btn.textContent = 'Submitting…';
    setTimeout(() => { btn.disabled = false; btn.textContent = btn.dataset.origText; }, 12000);
  }
}, { capture: true });
</script>
<script>
(function () {
  function hasJustSubmitted() {
    try {
      if (document.cookie.indexOf('intake_submitted=1') !== -1) return true;
      if (sessionStorage.getItem('intake_just_submitted') === '1') return true;
    } catch (e) {}
    return false;
  }

  function deepClear(form) {
    // reset() to defaults first
    if (typeof form.reset === 'function') form.reset();

    // then force-clear everything to beat autofill
    const els = form.querySelectorAll('input, select, textarea');
    els.forEach(el => {
      // don't wipe CSRF or hidden meta inputs
      if (el.type === 'hidden') return;

      el.autocomplete = 'off';

      switch (el.type) {
        case 'checkbox':
        case 'radio':
          el.checked = false;
          break;
        case 'file':
          el.value = '';
          break;
        default:
          if (el.tagName === 'SELECT') {
            // go to first option (assumes it's a placeholder)
            el.selectedIndex = 0;
          } else {
            el.value = '';
          }
      }
    });

    // show step 1
    const steps = document.querySelectorAll('fieldset.step');
    steps.forEach((fs, idx) => { fs.style.display = (idx === 0) ? '' : 'none'; });
  }

  function clearMarkers() {
    // clear cookie + storage marker so future visits don't keep clearing
    document.cookie = 'intake_submitted=; Max-Age=0; path=/intake.php; SameSite=Lax';
    try { sessionStorage.removeItem('intake_just_submitted'); } catch(e) {}
  }

  // If we navigated back from thank-you and the page came from bfcache,
  // force a network reload once to discard filled values entirely.
  window.addEventListener('pageshow', function (e) {
    if (!hasJustSubmitted()) return;
    if (e.persisted) {
      clearMarkers();
      location.replace(location.pathname);
    }
  });

  // On a normal GET right after submit, perform a deep clear.
  function run() {
    if (!hasJustSubmitted()) return;
    const form = document.querySelector('form');
    if (form) deepClear(form);
    clearMarkers();
  }

  // Run after DOM ready, then again after load + a tick to beat autofill repaint.
  document.addEventListener('DOMContentLoaded', run);
  window.addEventListener('load', function () { setTimeout(run, 60); });
})();
</script>

<script>
(function () {
  var KEY = 'intake_just_submitted';
  var just = false;
  try { just = sessionStorage.getItem(KEY) === '1'; } catch(e) {}

  if (just) {
    // wipe any saved progress/values
    try { localStorage.clear(); } catch(e) {}

    // reset all fields to defaults
    var form = document.querySelector('form');
    if (form && typeof form.reset === 'function') form.reset();

    // make sure Step 1 is visible before the stepper runs
    var steps = document.querySelectorAll('fieldset.step');
    for (var i = 0; i < steps.length; i++) {
      steps[i].style.display = (i === 0 ? '' : 'none');
    }

    // remove any step/index keys your stepper uses
    try {
      localStorage.removeItem('intake_step');
      localStorage.removeItem('current_step');
      localStorage.removeItem('ip_step');
      localStorage.removeItem('intake_values');
      localStorage.removeItem('intake_progress');
    } catch(e) {}

    // one-time flag
    try { sessionStorage.removeItem(KEY); } catch(e) {}
  }

  // handle BFCache restores (Safari/Firefox)
  window.addEventListener('pageshow', function (ev) {
    if (ev.persisted) {
      try {
        if (sessionStorage.getItem(KEY) === '1') {
          localStorage.clear();
          if (form && typeof form.reset === 'function') form.reset();
          for (var i = 0; i < steps.length; i++) {
            steps[i].style.display = (i === 0 ? '' : 'none');
          }
          sessionStorage.removeItem(KEY);
        }
      } catch(e) {}
    }
  });
})();
</script>




</body>
</html>