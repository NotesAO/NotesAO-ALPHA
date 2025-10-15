<?php
/**
 * sandbox – BIPP Virtual Intake Packet
 * ------------------------------------
 * • GET  : render multi‑step form  (HTML follows this PHP section)
 * • POST : CSRF check → validate → INSERT into clinicnotepro_sandbox.intake_packet
 *          → e‑mail staff → confirmation screen
 *
 * Uses the **shared** /config/config.php instead of hard‑coded creds.
 */

declare(strict_types=1);
ob_start();
session_start();

/* ------------------------------------------------------------------ */
/* 0.  CONFIG + DB CONNECTION                                          */
/* ------------------------------------------------------------------ */
const ADMIN_ALERT_EMAIL = 'admin@notesao.com';

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
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf'], $_POST['csrf_token'])) {
        http_response_code(403); exit('Invalid CSRF token');
    }
}
/* trim() all scalars, return null if key missing ---------------------------------------------- */
function postv(string $k): ?string { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : null; }
/* check‑box / radio → tinyint(1) ---------------------------------------------------------------- */
function postb(string $k): int     { return (isset($_POST[$k]) && $_POST[$k]) ? 1 : 0; }

/* ------------------------------------------------------------------ */
/* 2.  POST  – SAVE INTAKE                                            */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    /* ---- Bare‑minimum fields that must never be empty --------------- */
    foreach (['first_name','last_name','date_of_birth','email','digital_signature'] as $req) {
        if (postv($req) === '') exit("<h3>Missing required field: $req</h3>");
    }

    // -- if “Other” was selected, an explanation is mandatory -----------------
    if (in_array('Other', $_POST['reasons'] ?? [], true) && postv('other_reason_text') === '') {
        exit('<h3>Please explain the “Other” reason.</h3>');
    }


    $gender = postv('gender_id');       // 1 = Not specified, 2 = Male, 3 = Female
    // 2 is the default/“male” program; 3 is the “female” program
    $program_id = ($gender === '3') ? 3 : 2;   // anything except ‘3’ falls back to 2

    /* ---- normalize signature_date so it can never be after intake_date ---- */
    $intake_date_raw    = postv('intake_date')    ?: date('Y-m-d');
    $signature_date_raw = postv('signature_date') ?: date('Y-m-d');

    $intake_ts    = $intake_date_raw    ? strtotime($intake_date_raw)    : null;
    $signature_ts = $signature_date_raw ? strtotime($signature_date_raw) : null;

    if ($intake_ts && $signature_ts && $signature_ts > $intake_ts) {
        // Force signature_date to intake_date
        $signature_date_raw = date('Y-m-d', $intake_ts);
        // if you store time, use: date('Y-m-d H:i:s', $intake_ts);
    }

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
        'referral_type_id'         => postv('referral_type_id'),
        'referring_officer_name'   => postv('referring_officer_name'),
        'referring_officer_email'  => postv('referring_officer_email'),
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

        /* Substance use ------------------------------------------------ */
        'alcohol_past'          => postb('alcohol_past'),
        'alcohol_frequency'  => postv('alcohol_frequency'),
        'alcohol_current'       => postb('alcohol_current'),
        'alcohol_current_details'=> postv('alcohol_current_details'),
        'drug_past'             => postb('drug_past'),
        'drug_past_details'     => postv('drug_past_details'),
        'drug_current'          => postb('drug_current'),
        'drug_current_details'  => postv('drug_current_details'),
        'alcohol_during_abuse'  => postb('alcohol_during_abuse'),
        'drug_during_abuse'     => postb('drug_during_abuse'),

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
        'abuse_trauma_history'  => postb('abuse_trauma_history'),
        'violent_incident_desc' => postv('violent_incident_desc'),

        /* Victim ------------------------------------------------------- */
        'victim_contact_provided' => postb('victim_knowledge'),
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

        /* Consents ----------------------------------------------------- */
        'consent_confidentiality'   => postb('agree_confidentiality'),
        'consent_disclosure'        => postb('agree_disclosure'),
        'consent_program_agreement' => postb('agree_program'),
        'consent_responsibility'    => postb('agree_responsibility'),
        'consent_policy_termination'=> postb('agree_termination'),

        /* BIPP goals / notes ------------------------------------------ */
        'reasons'               => implode(', ', $_POST['reasons'] ?? []),
        'other_reason_text'     => postv('other_reason_text'),
        'offense_description'   => postv('describe_reason'),
        'personal_goal'         => postv('personal_goal_bipp'),
        'counselor_name'        => postv('counselor'),
        'chosen_group_time'     => postv('group_time'),

        /* Signature & meta -------------------------------------------- */
        'intake_date'           => $intake_date_raw,
        'digital_signature'     => postv('digital_signature'),
        'signature_date'        => $signature_date_raw,

        'packet_complete'       => 1
    ];

    /* ---- Build & execute INSERT ------------------------------------ */
    $cols  = array_keys($fields);
    $place = array_fill(0, count($cols), '?');
    $sql   = 'INSERT INTO intake_packet ('.implode(',', $cols).') VALUES ('.implode(',', $place).')';

    /* Optional developer sanity‑check */
    if (substr_count($sql,'?') !== count($fields)) {
        exit('Developer error: placeholder / param count mismatch');
    }

    $stmt = $db->prepare($sql) or exit('Server error.');
    $types = str_repeat('s', count($fields));       // all params → strings; DB will cast
    $stmt->bind_param($types, ...array_values($fields));
    $stmt->execute();
    if ($stmt->error) { error_log($stmt->error); exit('Could not save packet.'); }

    /* ① mark this packet “done” for THIS browser session */
    $_SESSION['show_thank_you_once'] = true;

    /* ① Post‑Redirect‑Get prevents accidental re‑submits */
    header('Location: ' . $_SERVER['PHP_SELF'], true, 303);   // you can use 303 as status if you like

    /* ---- Notify staff ---------------------------------------------- */
    // ✱ Sanitise (strip CR/LF) to prevent header‑injection
    $fname = preg_replace('/[\r\n]+/', ' ', postv('first_name'));
    $lname = preg_replace('/[\r\n]+/', ' ', postv('last_name'));

    $headers  = "From: reporting@ffl.notesao.com\r\n";
    $headers .= "Reply-To: " . postv('email') . "\r\n";   // nice but optional
    $headers .= "X-Mailer: PHP/" . PHP_VERSION;

    @mail(
        ADMIN_ALERT_EMAIL,
        "Sandbox has received a new Intake Packet for $fname $lname",
        "A new online intake packet was submitted.\n\nView pending & submitted packets:\nhttps://{$_SERVER['HTTP_HOST']}/intake-index.php",
        $headers,
        '-freporting@ffl.notesao.com'            // ← some MTAs (esp. cPanel) require this
    );

    exit;

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

    <div class="jumbotron bg-white text-center shadow-sm py-4 mb-4">
      <a href="https://notesao.com" target="_blank" rel="noopener">
        <img src="notesao.png" alt="NotesAO"
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
        <a href="https://notesao.com" class="btn btn-primary btn-lg">
          NotesAO Homepage
        </a>
      </div>
    </div>

    <script>localStorage.clear();</script>
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
<title>Sandbox | Intake Packet</title>
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



</style>
</head>
<body>



<!-- Jumbotron / Header Section -->
    <div class="jumbotron bg-white text-center shadow-sm py-4">
      <a href="https://notesao.com" target="_blank">
        <!-- Responsive Image -->
          <img 
              src="notesao.png" 
              alt="NotesAO" 
              class="img-fluid mb-1"
              style="max-width: 60%; height: auto;"
          >
      </a>
    </div>




<h1>BIPP Intake Packet</h1>
<form method="post">
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

    <p>If you have questions while filling out the form or encounter any technical difficulties, please contact our support team at: <strong>(phone_number)</strong>.</p>

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
      <div class="col"><label>DL (Identification) Number</label><input name="id_number"></div>
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
        <select name="race_id">
            <option value="">-- Select --</option><option value="1">African American</option><option value="0">Hispanic</option><option value="2">Asian</option><option value="3">Middle Easterner</option><option value="4">Caucasian</option><option value="5">Other</option>
        </select>
      </div>
      <div class="col"><label class="required">Highest Education</label>
        <select name="education_level">
            <option value="">-- Select --</option><option value="1">High School</option><option value="0">GED</option><option value="2">Some College</option><option value="3">Associates</option><option value="4">Bachelors</option><option value="5">Masters</option><option value="6">Doctorates</option><option value="7">None of the Above</option>
        </select>
      </div>
    </div>

    <!-- Add vertical space between rows -->
    <div style="height: 1.5rem;"></div>

    <div class="row">
      <div class="col">
        <label class="required">Currently Employed?</label>
        <select name="employed">
          <option value="">-- Select --</option><option value="1">Yes</option><option value="0">No</option>
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

    <div class="policy">

    <p>Confidentiality is defined as keeping private the information shared by you, the client, with your
    counselor. On occasion, other employees may need access to your record for agency teaching,
    supervision, and administrative purposes. These staff members will also respect the privacy of your
    records. In accordance with the Texas Department of Criminal Justice – Community Justice Assistance Division
    and Texas Council on Family Violence Battering Intervention &amp; Prevention Program guidelines, clients are
    required to sign Consent for Release of Information, which permits information to be released to the
    victim/partner and/or her designated representative, law enforcement, the courts, correction
    agencies, and any others in accordance with agency policy.</p>

    <p><strong>As a client, you have the right to withhold or release information to other individuals or
    agencies.</strong> A statement signed by you is required before any information may be released to anyone
    outside (placeholder_clinicname). This right applies with the following exceptions:</p>

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
        <li><strong>Media involvement:</strong> Any media contact arranged by the (placeholder_clinicname) program
            will include the presence of a (placeholder_clinicname) employee to protect victim confidentiality.</li>
    </ul>

    <p><strong>We ask that you keep confidential information you may learn about other clients who are
    receiving services from (placeholder_clinicname).</strong></p>

    <p><strong>(placeholder_clinicname) requires facilitators and participants to:</strong></p>
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
            (program_director), at (phone_number).</li>
        <li>If further resolution is needed, contact the Texas Council on Family Violence at 800-525-1978.</li>
    </ul>

    <p><em>By clicking “I Agree” below, I confirm that I have read, understood, and agree to abide by the
    terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and
    I accept these terms as a condition of participation in the (placeholder_clinicname).</em></p>
    </div>

    <label class="required" style="display:block;margin-top:.75rem">
    <input type="checkbox" name="agree_confidentiality" required>
    I&nbsp;Agree&nbsp;– I have read, understood and accept the terms above
    </label>
    <!-- ────────────────────────────────────────────── -->

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
    <legend>3&nbsp;&nbsp;Referral Information</legend>
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
        <label class="required">Officer / Case Manager Name</label>
        <input name="referring_officer_name" required>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label>Officer E-mail</label>
        <input type="email" name="referring_officer_email">
      </div>
    </div>

    <!-- Add vertical space between rows -->
    <div style="height: 1.5rem;"></div>

    <div class="row">
      <div class="col">
        <label>Additional Charge or Arrest Dates (if applicable)</label>
        <input name="additional_charge_dates" placeholder="e.g. January 4, 2023, August 6, 2024">
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label>Additional Charge Details</label>
        <input name="additional_charge_details">
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
    </div><!-- /#children-details -->

    <!-- ───────── Discipline narrative ───────── -->
    <div class="mt-4">
      <label class="form-label" for="discipline_desc">
        Describe how you discipline your children. Please provide examples: (if applicable)
      </label>
      <textarea id="discipline_desc" name="discipline_desc" rows="3" class="form-control"
                placeholder="Type your answer here&hellip;"></textarea>
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
          If YES, why are you depressed: <span class="text-danger req-star d-none">*</span>
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
          History of sexual abuse? <span class="text-danger">*</span>
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
  <!--  7. VICTIM INFORMATION                                             -->
  <!-- ================================================================== -->
  <fieldset class="step" id="step-victim">
    <legend>7&nbsp;&nbsp;Victim Information</legend>

    <!-- Victim knowledge selection -->
    <div class="row no-gap mb-3">
      <div class="col-md-12">
        <p class="form-label fw-bold">Please select one:</p>
        <div id="victim-knowledge-buttons">
          <div class="victim-knowledge-option" data-value="0">
            I do <strong>not</strong> have knowledge of the victim's contact information
          </div>
          <div class="victim-knowledge-option" data-value="1">
            I do have knowledge of the victim's contact information (must provide below)
          </div>
        </div>
        <input type="hidden" name="victim_knowledge" id="victim_knowledge" required>
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
          <label for="children_under_18">If YES, how many children under the age of 18 live in the home?</label>
          <input name="children_under_18" type="text" class="form-control">
        </div>
      </div>
    </div>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <!-- ================================================================== -->
  <!--  8. CLIENT CONSENTS                                               -->
  <!-- ================================================================== -->
  <fieldset class="step" id="step-client-consents">
    <legend>8&nbsp;&nbsp;Client Consents</legend>

    <!-- Section: Consent for Disclosure of Information -->
    <div class="mb-4">
      <label class="form-label fw-bold">Consent for Disclosure of Information</label>
      <div class="border p-3 mb-2 rounded overflow-auto" style="max-height: 240px; background-color: #f8f9fa;">
        <p>I understand that such disclosure will be made for the purposes of progress reports, referrals, and facilitating victim safety.</p>
        <p>Disclosure is limited to information regarding attendance, participation, information exchange, and referrals for services.</p>
        <p>I understand that I may revoke this consent at any time and that my request for revocation must be in writing. If not earlier revoked, this consent for disclosure of information shall expire 1 year after my completion of or termination from (placeholder_clinicname).</p>
        <p>I understand my right to confidentiality. I further understand that this consent form gives (placeholder_clinicname) permission to share confidential information about me in the way described above.</p>
        <p>I understand that Victim will be contacted by the Victim Advocate and offered counseling services. She/He will be provided enrollment, completion, or termination information from (placeholder_clinicname).</p>
        <p>Release of information is voluntary; I understand I have a right to refuse (placeholder_clinicname)'s request for this disclosure.</p>
        <p>(placeholder_clinicname) reserves the right to dismiss any client who refuses to meet the provisions of The Texas Department of Criminal Justice-Community Justice Assistance Division and Texas Council on Family Violence Battering Intervention & Prevention Project guidelines.</p>
        <p>Information disclosed by batterers during an assessment (intake), group sessions, and exit is confidential and shall not be shared with victims.</p>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="agree_disclosure" name="agree_disclosure" required>
        <label class="form-check-label" for="agree_disclosure">
          By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the (placeholder_clinicname).
        </label>
      </div>
    </div>

    <!-- Section: NotesAO Program Agreement -->
    <div class="mb-4">
      <label class="form-label fw-bold">(placeholder_clinicname) Program Agreement</label>
      <div class="border p-3 mb-2 rounded overflow-auto" style="max-height: 240px; background-color: #f8f9fa;">
        <p>Fee per session is: Parole Intake Orientation $x, $x per group ($x total), $x Completion; Probation 18 Week – Intake Orientation $x, $x per group, $x Completion ($x total); Probation 27 Week – Intake Orientation $x, $x per group, $x Completion ($x total). This fee is only one type of demonstration of your accountability and restitution for violent behavior. Breaks, Assessment (intake), Orientation, or Exit Session are not to be included towards the 36 hours (18 weeks) or 54 hours (27 weeks).</p>
        <p>Battering Intervention and Prevention Program consists of Assessment (Intake) and Orientation and at least 36 hours of group sessions in a minimum of 24 weekly sessions. Not to exceed one session per week. If dismissed, the client must apply to re-enter into (placeholder_clinicname). Re-entry is considered on a case-by-case basis. I understand that I cannot re-enter the program until I have paid off my previous balance.</p>
        <p>Clients who miss (3) consecutive sessions (group or individual) or a total of (5 sessions), you will be discharged from the program. Your referral sources will determine what happens with your case as a result of your absences. <em>There are no excused absences. Incarceration is an inexcusable absence.</em> Clients have the option to attend “Attendance Review” if the client is facing discharge.</p>
        <p>You must be focused and facing your webcam during the entire group; <strong>NO</strong> watching TV or working on your computer. You will only be given one warning. Second warning will result in no credit for the session and/or a discharge violation from (placeholder_clinicname).</p>
        <p>You must be in a quiet room; not driving, in your car, or doing any other activity. Otherwise, you will receive no credit for that BIPP session and/or receive a discharge violation from (placeholder_clinicname).</p>
        <p>If there is any interruption during your (placeholder_clinicname) by a child or adult, you will lose credit for your class and/or receive a discharge violation from (placeholder_clinicname).</p>
        <p>If there is any appearance of alcohol or vaping during (placeholder_clinicname), it will result in a request for a drug test. If found dirty, you must sign a behavioral contract or be unsuccessfully discharged from (placeholder_clinicname).</p>
        <p>During your (placeholder_clinicname), if there is any woman present even for a moment, especially the victim, you shall receive no credit for the class and/or be unsuccessfully discharged from (placeholder_clinicname).</p>
        <p>If after a restroom break you do not return, or you take more than 5 minutes, you shall receive no credit for your (placeholder_clinicname) session.</p>
        <p>Payment for services is due at the time service is rendered. You will not be credited for attending groups or individual sessions unless payment is received. The client is required to maintain no more than a $x balance. I will continue to attend until I have a zero balance. Attendance may exceed 24 weeks if payment is not completed.</p>
        <p>I hereby agree to arrive to all of my sessions on time. If you are 5 minutes late after the designated start time, you will not receive credit for attending group.</p>
        <p>I understand that I must register into the group upon online login with the group facilitator. I will not be counted present for the session unless I register in with the group facilitator.</p>
        <p>I will notify (placeholder_clinicname) of any change of address or phone number.</p>
        <p>I hereby agree to contact BIPP by phone at (phone_number) when I am unable to attend a scheduled session. Failure to contact (placeholder_clinicname) within 2 consecutive absences is an automatic discharge from (placeholder_clinicname).</p>
        <p>I agree not to attend group under the influence of alcohol or drugs; refusal of a Drug Screening is an automatic discharge. It will be my responsibility to arrange transportation home or any safety measures so I'm not a danger to myself or others for driving under the influence. The referring agency will be notified of the incident.</p>
        <p>I hereby agree not to be abusive towards any staff person or other group member. I understand that I may not use sexist or racist language.</p>
        <p>I hereby agree to respect the confidentiality rights of my fellow client/group members. I further understand that a violation of this rule shall result in immediate termination from the program and shall be reported to the proper authorities.</p>
        <p>I hereby agree to notify a staff person of any and all emergencies that I am either part of or witness to.</p>
        <p>I understand that (placeholder_clinicname) is committed to helping me gain a better understanding of my problems and how to find productive solutions and that it is the main goal of my psychoeducational classes.</p>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="agree_program" name="agree_program" required>
        <label class="form-check-label" for="agree_program">
          By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the (placeholder_clinicname).
        </label>
      </div>
    </div>

    <!-- Section: Taking Responsibility -->
    <div class="mb-4">
      <label class="form-label fw-bold">Taking Responsibility</label>
      <div class="border p-3 mb-2 rounded overflow-auto" style="max-height: 240px; background-color: #f8f9fa;">
        <p>During group discussions, participants may not blame anyone else for their own behaviors.</p>
        <p>Participants agree to not use any form of violence, abusive, threatening, and controlling behaviors including stalking during the weeks they are in the program. A participant who uses violence may be terminated from the program. This action will be reported to the participant’s referral agencies. Participants will cease violent, abusive, threatening, and controlling behaviors, including stalking and violation of a protective order. Participants who are terminated for this reason and wish to re-enter the program will re-start from the 1st week.</p>
        <p>Participants will develop and adhere to a non-violence plan as outlined in the program curriculum.</p>
        <p>(placeholder_clinicname) requires me to disable any devices that could collect information from the environment, such as Google Home Assistant, Amazon Alexa, or Apple Siri, and I agree that I will not record nor take screenshots of the group. (placeholder_clinicname) requires me to be in a private space and not in any public area such as a park, yard, or open area; other people not in the group should not be exposed to the content nor hear or observe the group.</p>
        <p>This includes changing locations, walking around the house, neighborhood, or any public space. The responsibility of having a private area is mine. I cannot use the virtual group session to expel my partner or children from the residence. I must relocate to another location or private room in the residence. If I am a parent, I agree to ensure my children are safe and taken care of but are not interrupting the session or listening to group discussions.</p>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="agree_responsibility" name="agree_responsibility" required>
        <label class="form-check-label" for="agree_responsibility">
          By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the (placeholder_clinicname).
        </label>
      </div>
    </div>

    <!-- Section: Termination Policy -->
    <div class="mb-4">
      <label class="form-label fw-bold">BIPP Client Policy & Termination Policy</label>
      <div class="border p-3 mb-2 rounded overflow-auto" style="max-height: 240px; background-color: #f8f9fa;">
        <p>I have received a copy of the "Policy for Clients" for (placeholder_clinicname). I understand my rights and responsibilities, and I agree to enter (placeholder_clinicname).</p>
        <p>I understand that in accordance with Guideline 31 of the Texas Department of Criminal Justice-Community Justice Assistance Division and Texas Council on Family Violence Battering Intervention Prevention Program guidelines, I am being provided a written agreement that clearly delineates the obligation of the (placeholder_clinicname) to the client. I understand that the (placeholder_clinicname) shall:</p>
        <ul>
          <li>Provide services in a manner that I can understand.</li>
          <li>Provide copies of all written agreements.</li>
          <li>Notify me of changes in group time and schedules.</li>
          <li>Comply with anti-discrimination laws.</li>
          <li>Report quarterly to probation, courts of law, and/or other referral agencies regarding my progress or lack of progress during group.</li>
          <li>Provide reports weekly and/or monthly about my BIPP progress to my referral source: Probation, Parole, Child Protective Services, Courts, Attorney.</li>
          <li>Report to me regarding my status and participation.</li>
          <li>Provide fair and humane treatment.</li>
        </ul>
        <p>As a client of (placeholder_clinicname), you have the right to terminate services with our agency at any moment. The risk of terminating services will be explained to you by a counselor/instructor. You have the right to choose other agencies for your services, and (placeholder_clinicname) will provide you with a list of known community agencies that may provide the services you need, except for clients referred by Probation; clients will be referred back to their Supervision Officer.</p>
        <p>(placeholder_clinicname) also has the right to terminate services with clients if:</p>
        <ul>
          <li>Continued abuse, particularly physical violence.</li>
          <li>Client has accumulated (3) consecutive absences or a total of (5) sessions.</li>
          <li>Client has failed to pay for services over $x.</li>
          <li>Client is believed to be violent/aggressive towards others or staff.</li>
          <li>Client is involved in illegal activities on the premises.</li>
          <li>Client need for treatment is incompatible with types of services.</li>
          <li>(placeholder_clinicname) client violates any of the BIPP rules.</li>
        </ul>
        <p>A report will be made within 5 working days to your referral source of any known law violations, incidents or physical violence, and/or termination from BIPP.</p>
        <p>Clients have the right to seek other resources outside of (placeholder_clinicname), and when possible, (placeholder_clinicname) staff will provide or make a referral.</p>
        <p>The above Termination Policy applies to clients who are attending services on a voluntary basis or court-ordered to receive services or who are mandated to receive services by other entities; however, clients are responsible to check with those entities who mandate them to come regarding the alternatives for receiving services in another agency or consequences for choosing to stop services before making this final decision.</p>
        <p>(placeholder_clinicname) will provide batterers at the time of assessment (intake) with a copy of the circumstances under which they can be terminated before completion.</p>
        <p>I have read and understand the above statements and voluntarily enter into counseling services from the staff of (placeholder_clinicname) - by entering my name below. (By clicking SUBMIT, I hereby confirm the above information to the best of my knowledge is correct and true, with no misleading or false content in accordance with Texas Perjury Statute, Sec. 37.02 (a) (2) Chapter 32, Civil Practice and Remedies Code.)</p>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="agree_termination" name="agree_termination" required>
        <label class="form-check-label" for="agree_termination">
          By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the (placeholder_clinicname).
        </label>
      </div>
    </div>

    <!-- Navigation Buttons -->
    <div class="nav-buttons mt-4">
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

    <div class="mt-2 d-none" id="other_reason_text_container">
      <label for="other_reason_text">If other was chosen, please explain: <span class="text-danger">*</span></label>
      <input type="text" id="other_reason_text" name="other_reason_text" class="form-control">
    </div>

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
        <strong>EXAMPLE:</strong> Objective: Client will increase his knowledge regarding the issue of abuse, domestic violence and skills that can help him change behaviors and eliminate abuse and violence from his relationships. Strategies: Client will attend the BIPP group weekly for 90 minutes and will participate actively and display receptiveness to the information presented. Client will make consistent application of skills presented by thinking about the new information presented, reviewing the handouts, talking about what he’s learning with others, asking questions, making application of skills, completing assigned homework, giving examples in group of the progress he is making and by only focusing on him and his relationship with his partner. Client will practice POSITIVE SELF-TALK by stating I DON’T ARGUE, I DON’T FIGHT AND IF NEEDED I TAKE A TIME-OUT SO THAT I KEEP ME AND MY FAMILY MEMBERS SAFE FROM ABUSE AND VIOLENCE.
      </div>
    </div>

    <!-- 9B: Case Notes -->
    <h5 class="mt-4">9B. Case Notes</h5>

    <div class="mb-3">
      <label for="counselor">Counselor</label>
      <input type="text" id="counselor" name="counselor" class="form-control" value="Facilitator Name">
    </div>

    <!-- Group Time -->
    <div class="mb-3">
      <label for="group_time">Chosen Group Time <span class="text-danger">*</span></label>
      <select id="group_time" name="group_time" class="form-select" required>
        <option value="" disabled selected>Select your group time…</option>
        <option>Saturday 9am-11am (Lancaster)</option>
        <option>Sunday 5pm-7pm (Lancaster)</option>
        <option>Tuesday 7pm-9pm (Lancaster)</option>
        <option>VIRTUAL Saturday 9am-11am</option>
        <option>VIRTUAL Saturday 10am-12pm</option>
        <option>VIRTUAL Sunday 2pm-4pm</option>
        <option>VIRTUAL Sunday 2:30pm-4:30pm</option>
        <option>VIRTUAL Sunday 5pm-7pm</option>
        <option>VIRTUAL Monday 7:30pm-9:30pm</option>
        <option>VIRTUAL Monday 8:00pm-10:00pm</option>
        <option>VIRTUAL Tuesday 7:30pm-9:30pm</option>
        <option>VIRTUAL Tuesday 8:00pm-10:00pm</option>
        <option>VIRTUAL Wednesday 7:30pm-9:30pm</option>
        <option>VIRTUAL Wednesday 8pm-10pm</option>
        <option>VIRTUAL Women's Sunday 2pm-4pm</option>
        <option>VIRTUAL Women's Wednesday 7:30pm-9:30pm</option>
      </select>
    </div>

    <div class="mb-3">
      <label for="intake_date">Intake Date <span class="text-danger">*</span></label>
      <input type="date" id="intake_date" name="intake_date" class="form-control" required placeholder="yyyy-mm-dd">
      <small class="form-text text-muted">
        The date you will start BIPP & attend your first group. <br>
        <strong>Example:</strong> If today is Tuesday and you selected “Saturday 9AM” as your group time, enter this coming Saturday’s date.
      </small>
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
    if (saved !== null) {
      if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = saved === '1';
      } else {
        el.value = saved;
      }
    }
    const evt = (el.tagName === 'SELECT' || el.type === 'checkbox' || el.type === 'radio') ? 'change' : 'input';
    el.addEventListener(evt, () => {
      const val = (el.type === 'checkbox' || el.type === 'radio') ? (el.checked ? '1' : '0') : el.value;
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

  function toggleCPSBlock() {
    const show = (neglectedSel.value === '1');
    cpsBlock.classList.toggle('d-none', !show);
    cpsBlock.querySelectorAll('select').forEach(el => el.required = show);
    if (!show) cpsBlock.querySelectorAll('select').forEach(el => el.value = '');
  }

  function toggleChildBlock() {
    const show = (hasChildrenSel.value === '1');
    childDetailsBox.classList.toggle('d-none', !show);
    childDetailsBox.querySelectorAll('input, select').forEach(el => el.required = show);
    if (!show) {
      childDetailsBox.querySelectorAll('input, select').forEach(el => el.value = '');
      toggleCPSBlock();
    }
  }

  hasChildrenSel.addEventListener('change', toggleChildBlock);
  neglectedSel.addEventListener('change', toggleCPSBlock);
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
    { sel: 'head_trauma_history', grp: 'head_trauma_details_grp' }
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
  const victimInfoBlock   = document.getElementById('victim-info-block');
  const victimContactBlock = document.getElementById('victim-contact-block'); // ← use existing div

  const buttons = document.querySelectorAll('.victim-knowledge-option');
  const hiddenInput = document.getElementById('victim_knowledge');

  buttons.forEach((btn) => {
    btn.addEventListener('click', () => {
      // Set button active styles
      buttons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const value = btn.getAttribute('data-value');
      hiddenInput.value = value;

      // Always show relationship/living section
      victimInfoBlock.classList.remove('d-none');

      // Conditionally show/hide contact fields
      const showContact = value === '1';
      victimContactBlock.classList.toggle('d-none', !showContact);

      // Set required on contact fields only
      victimContactBlock.querySelectorAll('input, select').forEach(el => {
        el.required = showContact;
        if (!showContact) el.value = '';
      });
    });
  });

  const liveWithSelect = document.getElementById('live_with_victim');
  const childBlock = document.getElementById('children_under18_block');
  const childInput = document.querySelector('input[name="children_under_18"]');

  function toggleVictimChildBlock() {
    const show = liveWithSelect.value === '1';
    childBlock.classList.toggle('d-none', !show);
    childInput.required = show;
    if (!show) childInput.value = '';
  }

  liveWithSelect.addEventListener('change', toggleVictimChildBlock);
  toggleVictimChildBlock();

  // Page 9A – If "Other" reason selected, show explanation input
  const reasonCheckboxes       = document.querySelectorAll('input[name="reasons[]"]');
  const otherCheckbox          = document.getElementById('other_reason');
  const otherExplanationGroup  = document.getElementById('other_reason_text_container');
  const otherExplanationInput  = document.getElementById('other_reason_text');

  function toggleOtherExplanation() {
    const show = otherCheckbox?.checked;
    if (!otherExplanationGroup || !otherExplanationInput) return;
    otherExplanationGroup.classList.toggle('d-none', !show);
    otherExplanationInput.required = show;
    if (!show) otherExplanationInput.value = '';
  }

  reasonCheckboxes.forEach(cb => cb.addEventListener('change', toggleOtherExplanation));
  toggleOtherExplanation(); // initial state on load

  const today = new Date().toISOString().split('T')[0];
    const dateField = document.getElementById('signature_date');
    if (dateField) {
      dateField.value = today;
    }
});

</script>




</body>



</html>
