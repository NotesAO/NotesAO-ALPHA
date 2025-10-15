<?php
/**
 * evaluations-vtc.php — Veteran's Treatment Court: Evaluation Packet (26 pages)
 * -----------------------------------------------------------------------------
 * - Dynamic, schema-driven renderer for the 360+ fields in `evaluations_vtc`
 * - Styles and UX akin to evaluations-pai.php (header, directions card, progress bar)
 * - CSRF protection, session-based paging, single prepared INSERT at final submit
 *
 * Dev helpers (keep for now; small footer links):
 *   ?schema=1               → JSON dump of live columns & comments
 *   ?debug=unassigned       → HTML list of columns that didn't match a page
 */

declare(strict_types=1);
ob_start();
session_start();

// ===================== THANK-YOU / RECEIPT PAGE (styled) =====================
if (isset($_GET['done']) && (int)$_GET['done'] === 1) {
  // Anti-cache headers
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: 0');

  $conf = htmlspecialchars($_GET['conf'] ?? '', ENT_QUOTES);
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Evaluation Submitted — Free for Life Group</title>
    <style>
      :root{
        --bg:#f3f4f6; --card:#fff; --ink:#111827; --sub:#6b7280; --line:#e5e7eb;
        --brand:#0b1220; --primary:#2563eb; --primary-700:#1d4ed8;
      }
      *{box-sizing:border-box}
      html,body{height:100%}
      body{margin:0;background:var(--bg);color:var(--ink);
           font:16px/1.55 ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial}
      /* Header */
      .hdr{position:sticky;top:0;z-index:10;background:var(--brand);color:#fff;
           box-shadow:0 1px 0 rgba(255,255,255,.06)}
      .container{max-width:1100px;margin:0 auto;padding:0 16px}
      .brand{display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:56px}
      .title{font-weight:700;font-size:18px;letter-spacing:.2px}
      .logo img{height:32px;display:block}
      /* Card */
      .card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px;margin:24px auto}
      .section-title{font-size:20px;font-weight:800;margin:0 0 8px}
      .text-muted{color:var(--sub)}
      .kv{display:flex;gap:16px;margin:8px 0}
      .kv .k{width:180px;color:var(--sub)}
      .kv .v{font-weight:600}
      .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
      .btn{border:1px solid var(--line);background:#fff;border-radius:10px;padding:10px 14px;
           cursor:pointer;font-weight:600}
      .btn:hover{background:#f9fafb}
      .btn-primary{background:var(--primary);border-color:var(--primary);color:#fff}
      .btn-primary:hover{background:var(--primary-700);border-color:var(--primary-700)}
      hr{border:none;border-top:1px solid var(--line);margin:16px 0}
      .footer-tip{color:var(--sub);font-size:14px}
    </style>
  </head>
  <body>
    <header class="hdr">
      <div class="container brand">
        <div class="title">Free for Life Group — Veteran’s Treatment Court Evaluation</div>
        <div class="logo">
          <!-- Optional: update to your real logo path -->
          <!-- <img src="/assets/ffl-logo.svg" alt="Free for Life Group"> -->
        </div>
      </div>
    </header>

    <main class="container">
      <section class="card" style="max-width:860px;">
        <h2 class="section-title">Thank you — your evaluation was submitted successfully.</h2>
        <p class="text-muted">We’ve received your responses. A counselor will review your evaluation and follow up as needed.</p>

        <div class="kv"><span class="k">Confirmation Code</span><span class="v"><?= $conf ?></span></div>
        <div class="kv"><span class="k">Submitted</span><span class="v"><?= date('F j, Y, g:i A') ?></span></div>

        <div class="actions">
          <button type="button" class="btn" onclick="window.print()">Print Confirmation</button>
          <a class="btn btn-primary" href="evaluations-vtc.php?step=1">Start a New Evaluation</a>
        </div>

        <hr>
        <p class="footer-tip">Tip: If you share this computer, consider closing this tab to protect your information.</p>
      </section>
    </main>

    <script>
    (function(){
      try {
        localStorage.clear();
        sessionStorage.clear();
        if ('caches' in window) { caches.keys().then(keys => keys.forEach(k => caches.delete(k))); }
      } catch(e) {}
      // Ensure refresh lands on a fresh step 1
      try { history.replaceState(null, '', 'evaluations-vtc.php?step=1'); } catch(e) {}
    })();
    </script>
  </body>
  </html>
  <?php
  exit; // stop normal step rendering
}
// =================== END THANK-YOU / RECEIPT PAGE ===================


require_once dirname(__DIR__) . '/config/config.php';
$mysqli = isset($link) ? $link : (isset($con) ? $con : null);
if (!$mysqli) { http_response_code(500); exit('DB connection not found.'); }
$mysqli->set_charset('utf8mb4');

/* ────────────────────────────────────────────────────────────────────────── */
/* CSRF helpers                                                              */
/* ────────────────────────────────────────────────────────────────────────── */
function csrf_boot(): void {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_check(): void {
  if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403); exit('Invalid CSRF token');
  }
}

/* ────────────────────────────────────────────────────────────────────────── */
/* Utilities                                                                  */
/* ────────────────────────────────────────────────────────────────────────── */
function s($v){ return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function norm($v){
  if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
  if (is_string($v)) return trim($v);
  return $v;
}
function rx_has($rx, string $hay): bool {
  try { return (bool)preg_match($rx, $hay); }
  catch (Throwable $e) { return false; }
}
function get_step(): int {
  $step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
  return max(1, min(26, $step));
}

/* ── Page-1 required names ─────────────────────────────────────────────── */
const VTC_P1_REQUIRED_TEXT   = ['first_name','last_name','email'];
const VTC_P1_REQUIRED_CHECKS = [
  'free_for_life_group_vtc_evaluation_statement_of_confidentiality_',
  'as_a_client_you_have_the_right_to_withhold_or_release_informatio',
  'a_licensee_shall_report_if_required_by_any_of_the_following_laws',
  'policy_keep_peers_confidential',
  'policy_facility_privacy_requirements',
  'policy_observers_recording',
  'policy_ethics_grievances_ack',
];

// Long policy text for Page 1 consents.
// Key by the actual field name if you know it; otherwise the numeric
// keys 0..5 will be used by position in VTC_P1_REQUIRED_CHECKS.
$VTC_P1_CONSENT_CONTENT = [
  // 0
  [
    'title' => 'Statement of Confidentiality & Consent for Treatment',
    'body'  => 'Free for Life Group - VTC Evaluation Statement of Confidentiality & Consent for Treatment. Confidentiality is keeping private the information you share with your evaluator. On occasion, other employees may access your record for agency teaching, supervision and administrative purposes; these staff members also respect the privacy of your records. In accordance with the Texas Department of Criminal Justice–Community Justice Assistance Division and Veterans Court: Clients are required to sign Consent for Release of Information permitting information to be released to Veterans Courts, correction agencies, and others per agency policy.'
  ],
  // 1
  [
    'title' => 'Your right to release or withhold information & exceptions',
    'body'  => 'You may withhold or release information to other individuals or agencies. A signed statement is required before information may be released to anyone outside Free for Life Group – VTC Evaluation. This right has exceptions: (A) a court subpoenas information; (B) reasonable concern that harm may come to you or others, including child abuse, elder abuse, or abuse of a disabled person. Per Texas LPC Code of Ethics, Chapter 681, Subchapter C, Rule 681.43, licensees must report to TDPRS when required by law (Family Code Ch.261; Human Resources Code Ch.48). When staff determines probability of imminent physical injury to self or others, staff will take safety initiatives and may notify medical or law enforcement and/or a potential victim (Texas Health & Safety Code §611.004(a)). (C) disclosure of sexual misconduct or sexual exploitation by a previous therapist or mental health professional must also be reported per Rule 681.43.'
  ],
  // 2
  [
    'title' => 'Mandatory reporting & media involvement',
    'body'  => 'A licensee shall report if required by: (1) Health & Safety Code Ch.161 Subch. K (Rule 161.131 et seq.) regarding abuse, neglect, and illegal, unprofessional, or unethical conduct in certain facilities; (2) Civil Practice & Remedies Code §81.006 (sexual exploitation by a mental health service provider). Personal data and additional information may be submitted to TDCJ-CJAD for program assessments and research. Media involvement: any media contact arranged by Free for Life Group shall include the presence of a Free for Life Group employee to protect your confidentiality.'
  ],
  // 3
  [
    'title' => 'Peer confidentiality',
    'body'  => 'We ask that you keep confidential any information you may learn about other clients receiving services from Free for Life – VTC Evaluation.'
  ],
  // 4
  [
    'title' => 'Remote evaluation privacy requirements',
    'body'  => 'Disable devices that could collect information from the environment (e.g., Google Home, Amazon Alexa, Apple Siri). Do not record or take screenshots. Participate from a private space (not parks, yards, or public areas). Others not in the evaluation should not hear or observe the session; avoid walking around public spaces. Parents must ensure children are safe and not interrupting or listening. Participants must not use the session to expel partners or children from the residence; relocate to a private room if needed.'
  ],
  // 5
  [
    'title' => 'Observers, recording, and grievances',
    'body'  => 'Observers (e.g., student interns, trainees, other professionals) may occasionally sit in and must sign confidentiality statements. This facility and Zoom sessions may be video recorded for security and quality assurance. Keep confidential any information learned about other clients. Ethics & Grievances: Services are delivered professionally and ethically, but specific results cannot be guaranteed. If you have concerns, inform your evaluator. If unresolved, contact the Executive Director, Van Martin (Free for Life – VTC Evaluation, 817-501-5102). If still unresolved, you may contact Veteran’s Court: 817-884-3754.'
  ],
  [
    'title' => 'Ethics & Grievances',
    'body' => 'All agency services will be delivered in as professional and ethical manner as possible. It is impossible to guarantee any specific results regarding your goals. However, if you have concerns regarding the professional performance of your evaluator, please inform your evaluator. If your evaluator is not able to resolve your concerns, please report them to your evaluators immediate supervisor, Executive Director, Van Martin, of Free for Life -VTC Evaluation.: 817-501-5102. If Mr. Martin is unable to resolve your concerns about professional performance, you may contact: Veterans Court: 817-884-3754.'
  ]
];

// If you know exact field names, you can also add named keys, e.g.:
// $VTC_P1_CONSENT_CONTENT['consent_confidentiality'] = [...];


// Returns a readable label for a field, even when the DB comment is empty.
function vtc_label(array $meta): string {
  global $LONG_LABELS;
  $name  = $meta['name'] ?? '';
  $comment = isset($meta['comment']) ? trim($meta['comment']) : '';
  $label   = isset($LONG_LABELS[$name]) ? $LONG_LABELS[$name]
           : ($comment !== '' ? $comment : ucwords(str_replace('_',' ', $name)));

  if (trim($label) === '') {
    // prettify from column name as last resort
    $label = ucwords(str_replace('_',' ', $name));
  }
  return $label;
}


function vtc_validate_page1(array $data): array {
  $err = [];

  // Required text fields
  if (empty(trim($data['first_name'] ?? ''))) $err['first_name'] = 'First name is required.';
  if (empty(trim($data['last_name'] ?? '')))  $err['last_name']  = 'Last name is required.';
  $email = trim($data['email'] ?? '');
  if ($email === '')            { $err['email'] = 'Email is required.'; }
  elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err['email'] = 'Enter a valid email address.';
  }

  // Required consents (must be "1")
  foreach (VTC_P1_REQUIRED_CHECKS as $name) {
    if (($data[$name] ?? '') !== '1') $err[$name] = 'Please check this box to continue.';
  }

  return $err;
}
function vtc_validate_page2(array $data): array {
  $err = [];

  if (empty(trim($data['dl_number'] ?? '')))     $err['dl_number'] = 'Driver/ID number is required.';
  if (empty(trim($data['address1'] ?? '')))      $err['address1'] = 'Home address is required.';
  if (empty(trim($data['phone_primary'] ?? ''))) $err['phone_primary'] = 'Primary phone is required.';

  if (empty(trim($data['dob'] ?? '')))           $err['dob'] = 'Date of birth is required.';
  if (($data['age'] ?? '') === '' || !is_numeric($data['age'])) $err['age'] = 'Age is required.';

  foreach (['race','gender','education_level','employed'] as $n) {
    if (empty(trim($data[$n] ?? ''))) $err[$n] = 'This field is required.';
  }
  if (($data['employed'] ?? '') === 'Yes') {
    if (empty(trim($data['employer'] ?? '')))   $err['employer']   = 'Employer is required when employed is Yes.';
    if (empty(trim($data['occupation'] ?? ''))) $err['occupation'] = 'Occupation is required when employed is Yes.';
  }
  if (empty(trim($data['military_service'] ?? '')))
    $err['military_service'] = 'Military service history is required.';
  // Emergency contact (from the UI's two fields; they aren't part of $data)
  $ef = trim($_POST['__emerg_first'] ?? '');
  $el = trim($_POST['__emerg_last']  ?? '');
  if ($ef === '') $err['__emerg_first'] = 'First name required.';
  if ($el === '') $err['__emerg_last']  = 'Last name required.';
  if (empty(trim($data['emergency_contact_phone'] ?? ''))) $err['emergency_contact_phone'] = 'Contact phone is required.';

  return $err;
}
function vtc_validate_page3(array $data): array {
  $err = [];
  if (empty(trim($data['vtc_officer_name'] ?? '')))
    $err['vtc_officer_name'] = 'Court officer’s name is required.';
  $em = trim($data['attorney_email'] ?? '');
  if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL))
    $err['attorney_email'] = 'Enter a valid email address.';
  return $err;
}
function vtc_validate_page4(array $data): array {
  $err = [];

  // Always required:
  foreach ([
    'marital_status','living_situation','has_children',
    'child_abused_physically','child_abused_sexually',
    'child_abused_emotionally','child_neglected'
  ] as $n) {
    if (empty(trim($data[$n] ?? ''))) $err[$n] = 'This field is required.';
  }

  // If has children, require 'children_live_with_you'
  if (($data['has_children'] ?? '') === 'Yes') {
    if (empty(trim($data['children_live_with_you'] ?? '')))
      $err['children_live_with_you'] = 'Please answer this question.';
  }

  // If any abuse/neglect is Yes, require both CPS questions
  $abuseAny = in_array('Yes', [
    $data['child_abused_physically']  ?? '',
    $data['child_abused_sexually']    ?? '',
    $data['child_abused_emotionally'] ?? '',
    $data['child_neglected']          ?? '',
  ], true);

  if ($abuseAny) {
    foreach (['cps_notified','cps_care_or_supervision'] as $n) {
      if (empty(trim($data[$n] ?? ''))) $err[$n] = 'This field is required.';
    }
  }

  return $err;
}

function vtc_validate_page5(array $data): array {
  $err = [];

  // Always require the four Yes/No selectors
  foreach (['alcohol_past_use','alcohol_current_use','drug_past_use','drug_current_use'] as $n) {
    if (empty(trim($data[$n] ?? ''))) $err[$n] = 'This field is required.';
  }

  // If any is Yes, its companion detail is required
  $pairs = [
    ['alcohol_past_use','alcohol_past_details'],
    ['alcohol_current_use','alcohol_current_details'],
    ['drug_past_use','drug_past_details'],
    ['drug_current_use','drug_current_details'],
  ];
  foreach ($pairs as [$yn,$detail]) {
    if (($data[$yn] ?? '') === 'Yes' && empty(trim($data[$detail] ?? ''))) {
      $err[$detail] = 'Please provide details.';
    }
  }

  return $err;
}
function vtc_validate_page6(array $data): array {
  $err = [];

  foreach ([
    'counseling_history','depressed_now','suicide_attempt_history',
    'psych_meds_current','sexual_abuse_history',
    'head_trauma_history','weapon_possession_history','childhood_abuse_history'
  ] as $n) {
    if (empty(trim($data[$n] ?? ''))) $err[$n] = 'This field is required.';
  }

  $rules = [
    ['counseling_history',      ['counseling_details']],
    ['depressed_now',           ['depressed_details']],
    ['suicide_attempt_history', ['suicide_last_attempt_when']],
    ['psych_meds_current',      ['psych_meds_list','psych_meds_physician']],
    ['head_trauma_history',     ['head_trauma_details']],
  ];
  foreach ($rules as [$yn,$details]) {
    if (($data[$yn] ?? '') === 'Yes') {
      foreach ($details as $d) {
        if (empty(trim($data[$d] ?? ''))) $err[$d] = 'Please provide details.';
      }
    }
  }

  return $err;
}
function vtc_validate_page7(array $data): array {
  $err = [];
  foreach ([
    'upbringing_where_grow_up',
    'upbringing_who_raised_you',
    'upbringing_raised_by_both_parents',
    'upbringing_parents_caretakers_names',
    'upbringing_divorce_explain',
    'upbringing_caretaker_addiction',
    'upbringing_caretaker_mental_health',
    'upbringing_finances_growing_up',
    'upbringing_traumatic_experiences',
    'upbringing_school_experience',
    'upbringing_caretakers_help_schoolwork',
    'upbringing_anything_else_for_court',
  ] as $n) {
    if (empty(trim($data[$n] ?? ''))) {
      $err[$n] = 'This field is required.';
    }
  }
  return $err;
}
function vtc_validate_page8(array $data): array {
  $err = [];
  foreach ([
    'legal_first_arrest_details',
    'legal_multiple_arrests_details',
    'legal_prevention_plan',
    'legal_hopes_from_vtc',
  ] as $n) {
    if (empty(trim($data[$n] ?? ''))) $err[$n] = 'This field is required.';
  }
  return $err;
}
function vtc_validate_page9(array $data): array {
  $err = [];
  foreach ([
    'military_join_details',
    'military_trauma_description',
    'military_impact_beliefs',
    'military_grief_counseling',
    'military_culture_mh_attitudes',
  ] as $n) {
    if (empty(trim($data[$n] ?? ''))) $err[$n] = 'This field is required.';
  }
  return $err;
}
function vtc_validate_page10(array $data): array {
  $err = [];
  foreach ([
    'medications_prescribed_history',
    'medications_first_prescribed_details',
    'medications_current_and_desired',
  ] as $n) {
    if (empty(trim($data[$n] ?? ''))) $err[$n] = 'This field is required.';
  }
  return $err;
}
function vtc_validate_page11(array $data): array {
  $err = [];
  foreach ([
    'addiction_impact_on_life',
    'addiction_overcome_attempts',
    'sobriety_future_impact',
    'hope_for_future_narrative',
  ] as $n) {
    if (empty(trim($data[$n] ?? ''))) $err[$n] = 'This field is required.';
  }
  return $err;
}
function vtc_validate_page12(array $data): array {
  $err = [];
  foreach ([
    'beliefs_impact_on_life',
    'beliefs_extraordinary_experiences',
    'beliefs_shape_future',
  ] as $n) {
    if (empty(trim($data[$n] ?? ''))) $err[$n] = 'This field is required.';
  }
  return $err;
}
function vtc_validate_page13(array $data): array {
  $names = [
    'roi_purpose_ack','roi_limited_info_ack','roi_confidentiality_ack','roi_voluntary_ack',
    'rule_dismissal_ack','rule_eval_components_ack','rule_complete_process_ack','rule_focus_webcam_ack',
    'rule_quiet_room_ack','rule_interruption_no_credit_ack','rule_substance_violation_ack','rule_restroom_5min_ack',
    'rule_payment_due_ack','rule_arrive_on_time_ack','rule_update_contact_ack','rule_call_if_absent_ack',
    'rule_no_intoxication_ack','rule_no_abusive_language_ack','rule_notify_emergencies_ack',
    'rule_program_goal_ack','rule_no_violence_ack',
    'free_for_life_group_requires_me_to_disable_any_devices_that_coul',
  ];
  $err = [];
  foreach ($names as $n) {
    if (($data[$n] ?? '') !== '1') $err[$n] = 'You must agree to proceed.';
  }
  return $err;
}
function vtc_validate_page14(array $data): array {
  $err = [];
  if (empty(trim($data['legal_offense_summary'] ?? ''))) {
    $err['legal_offense_summary'] = 'This field is required.';
  }
  return $err;
}
function vtc_validate_page15(array $data): array {
  $err = [];
  foreach ([
    'policy_received_ack',
    'rights_provider_obligations_ack',
    'rights_client_terminate_ack',
    'rights_provider_terminate_conditions',
    'rights_termination_policy_scope_ack',
    'rights_termination_circumstances_copy_ack',
    'final_attestation_ack',
  ] as $n) {
    if (($data[$n] ?? '') !== '1') $err[$n] = 'You must agree to proceed.';
  }
  if (empty(trim($data['legal_first_name'] ?? ''))) $err['legal_first_name'] = 'Required.';
  if (empty(trim($data['legal_last_name']  ?? ''))) $err['legal_last_name']  = 'Required.';
  return $err;
}
function vtc_validate_page16(array $data): array {
  $required = [
    'punishment_feelings','sadness','pessimism','self_dislike','past_failure',
    'self_criticalness','loss_of_pleasure','suicidal_thoughts_or_wishes',
    'guilty_feelings','crying','agitation','irritability','loss_of_interest',
    'loss_of_interest_in_sex','indecisiveness','concentration_difficulty',
    'worthlessness','tiredness_or_fatigue','loss_of_energy',
    'changes_in_appetite','changes_in_sleeping_pattern'
  ];
  $err = [];
  foreach ($required as $n) {
    if (($data[$n] ?? '') === '') $err[$n] = 'Required.';
  }
  return $err;
}
function vtc_validate_page17(array $post): array {
  $need = $GLOBALS['PAGES'][17]['exact'];
  $err  = [];
  foreach ($need as $name) {
    if (!isset($post[$name]) || trim((string)$post[$name]) === '') {
      $err[$name] = 'This field is required.';
    }
  }
  return $err;
}
function vtc_validate_page18(array $data): array {
      $err = [];
      foreach ($GLOBALS['PAGES'][18]['exact'] as $n) {
        if (($data[$n] ?? '') === '') $err[$n] = 'Required.';
      }
    return $err;
}
function vtc_validate_page19(array $data): array {
      $err = [];
      foreach ($GLOBALS['PAGES'][19]['exact'] as $n) {
        if (($data[$n] ?? '') === '') $err[$n] = 'Required.';
      }
      return $err;
}
function vtc_validate_page20(array $data): array {
  $err = [];
  foreach ($GLOBALS['PAGES'][20]['exact'] as $n) {
    if (($data[$n] ?? '') === '') $err[$n] = 'Required.';
  }
  return $err;
}
function vtc_validate_page21(array $data): array {
  $err = [];
  foreach ($GLOBALS['PAGES'][21]['exact'] as $n) {
    if (($data[$n] ?? '') === '') $err[$n] = 'Required.';
  }
  return $err;
}
function vtc_validate_page22(array $data): array {
  $err = [];
  foreach ($GLOBALS['PAGES'][22]['exact'] as $n) {
    if (($data[$n] ?? '') === '') $err[$n] = 'Required.';
  }
  return $err;
}
function vtc_validate_page23(array $data): array {
  $err = [];
  foreach ($GLOBALS['PAGES'][23]['exact'] as $n) {
    if (($data[$n] ?? '') === '') $err[$n] = 'Required.';
  }
  return $err;
}
function vtc_validate_page24(array $data): array {
  $err = [];
  foreach ($GLOBALS['PAGES'][24]['exact'] as $n) {
    if (($data[$n] ?? '') === '') $err[$n] = 'Required.';
  }
  return $err;
}
function vtc_validate_page25(array $data): array {
  $err = [];
  foreach ($GLOBALS['PAGES'][25]['exact'] as $n) {
    if (($data[$n] ?? '') === '') $err[$n] = 'Required.';
  }
  return $err;
}
function vtc_validate_page26(array $data): array {
  $err = [];
  if (trim($data['sign_first_name'] ?? '') === '') $err['sign_first_name'] = 'Required.';
  if (trim($data['sign_last_name']  ?? '') === '') $err['sign_last_name']  = 'Required.';
  if (trim($data['timestamp']       ?? '') === '') $err['timestamp']       = 'Required.';
  // signature_attestation_text is set automatically below
  return $err;
}


if (!function_exists('vtc_yesno')) {
  function vtc_yesno(array $meta): string {
    global $FORM_ERRORS;
    $name   = $meta['name'];
    $label  = vtc_label($meta);
    $val    = $_SESSION['vtc_form'][$name] ?? '';
    $hasErr = isset($FORM_ERRORS[$name]);
    $wrapErr  = $hasErr ? ' error'      : '';
    $inputErr = $hasErr ? ' is-invalid' : '';
    $msgHtml  = $hasErr ? "<div class='error-msg'>".htmlspecialchars($FORM_ERRORS[$name])."</div>" : "";
    $h  = "<label class='fld{$wrapErr}'>";
    $h .= "<span class='lbl'>".htmlspecialchars($label)."</span>";
    $h .= "<select class='input{$inputErr}' name='".htmlspecialchars($name)."'>";
    $h .= "<option value=''>— select —</option>";
    foreach (['Yes','No'] as $o) {
      $sel = ($val === $o) ? " selected" : "";
      $h .= "<option{$sel}>".htmlspecialchars($o)."</option>";
    }
    return $h."</select>{$msgHtml}</label>";
  }
}

if (!function_exists('vtc_radio_group')) {
  /**
   * Render a labeled radio group with value => text choices.
   * Stores the raw value (e.g., "0","1","2","3","1a","1b"...).
   */
  /**
 * Render a Likert block (BDI) where each radio option is a full-width "button".
 * $opts may be an indexed array [0=>'text',1=>'text',2=>'text',3=>'text'] or
 * an associative array ['0'=>'text', '1'=>'text', ...].
 */
  function vtc_radio_group(array $meta, array $opts, string $title = null): string {
    global $FORM_ERRORS;

    $name   = $meta['name'];
    $label  = $title ?: vtc_label($meta);
    $value  = $_SESSION['vtc_form'][$name] ?? '';
    $hasErr = isset($FORM_ERRORS[$name]);

    $wrapErr  = $hasErr ? ' error'      : '';
    $msgHtml  = $hasErr ? "<div class='error-msg'>".s($FORM_ERRORS[$name])."</div>" : "";

    // Normalize options to numbered pairs: [ ['val'=>'0','text'=>'...'], ... ]
    $norm = [];
    $i = 0;
    foreach ($opts as $k => $v) {
      $val  = is_int($k) ? (string)$k : (string)$k;
      $text = is_array($v) ? ($v['text'] ?? (string)$v) : (string)$v;
      if ($val === '' || !ctype_digit($val)) $val = (string)$i; // fall back to index if needed
      $norm[] = ['val'=>$val, 'text'=>$text];
      $i++;
    }

    // Fieldset + legend for semantics
    $h  = "<fieldset class='likert{$wrapErr}'>";
    $h .=   "<legend>".s($label)." <span class='req'>*</span></legend>";

    foreach ($norm as $opt) {
      $val = $opt['val'];
      $txt = $opt['text'];
      $id  = $name.'_'.$val;
      $sel = ((string)$value === (string)$val) ? ' checked' : '';
      $h  .= "<label class='bdi-opt' for='".s($id)."'>
                <input type='radio' id='".s($id)."' name='".s($name)."' value='".s($val)."'{$sel}>
                <div class='bdi-btn'>
                  <b class='bdi-num'>".s($val)."</b>
                  <span class='bdi-text'>".s($txt)."</span>
                </div>
              </label>";
    }

    $h .=   $msgHtml;
    $h .= "</fieldset>";
    return $h;
  }


}
/** Beck Anxiety Inventory: 4-point select */
function vtc_bai_select(array $meta): string {
  global $FORM_ERRORS;
  $name   = $meta['name'];
  $label  = vtc_label($meta);
  $val    = $_SESSION['vtc_form'][$name] ?? '';
  $hasErr = isset($FORM_ERRORS[$name]);
  $wrapErr  = $hasErr ? ' error' : '';
  $inputErr = $hasErr ? ' is-invalid' : '';
  $msgHtml  = $hasErr ? "<div class='error-msg'>".s($FORM_ERRORS[$name])."</div>" : "";

  $opts = [
    'Not At All',
    'Mildly — It did not bother me much.',
    'Moderately — It was very unpleasant but I could stand it.',
    'Severely — I could barely stand it.',
  ];

  $h  = "<label class='fld{$wrapErr}'>";
  $h .=   "<span class='lbl'>".s($label)." <strong>*</strong></span>";
  $h .=   "<select class='input{$inputErr}' name='".s($name)."'>";
  $h .=     "<option value=''>— Not selected —</option>";
  foreach ($opts as $o) $h .= "<option".($val===$o?' selected':'').">".s($o)."</option>";
  $h .=   "</select>{$msgHtml}";
  $h .= "</label>";
  return $h;
}



if (!function_exists('vtc_select')) {
  function vtc_select(array $meta, array $opts): string {
    global $FORM_ERRORS;
    $name   = $meta['name'];
    $label  = vtc_label($meta);
    $val    = $_SESSION['vtc_form'][$name] ?? '';
    $hasErr = isset($FORM_ERRORS[$name]);
    $wrapErr  = $hasErr ? ' error'      : '';
    $inputErr = $hasErr ? ' is-invalid' : '';
    $msgHtml  = $hasErr ? "<div class='error-msg'>".htmlspecialchars($FORM_ERRORS[$name])."</div>" : "";
    $h  = "<label class='fld{$wrapErr}'>";
    $h .= "<span class='lbl'>".htmlspecialchars($label)."</span>";
    $h .= "<select class='input{$inputErr}' name='".htmlspecialchars($name)."'>";
    $h .= "<option value=''>— select —</option>";
    foreach ($opts as $o) {
      $sel = ($val === $o) ? " selected" : "";
      $h  .= "<option{$sel}>".htmlspecialchars($o)."</option>";
    }
    return $h."</select>{$msgHtml}</label>";
  }
}

// -------------------------------------------------------



/* ────────────────────────────────────────────────────────────────────────── */
/* Live schema loader                                                         */
/* ────────────────────────────────────────────────────────────────────────── */
function vtc_schema(mysqli $db): array {
  $rows = [];
  $res = $db->query("
    SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_COMMENT, ORDINAL_POSITION
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'evaluations_vtc'
    ORDER BY ORDINAL_POSITION
  ");
  if (!$res) return $rows;
  while ($r = $res->fetch_assoc()) {
    $name = $r['COLUMN_NAME'];
    $rows[$name] = [
      'name'    => $name,
      'type'    => strtolower($r['DATA_TYPE'] ?? ''),
      'ctype'   => strtolower($r['COLUMN_TYPE'] ?? ''),
      'null'    => $r['IS_NULLABLE'] === 'YES',
      'comment' => $r['COLUMN_COMMENT'] ?: '',
      'ord'     => (int)$r['ORDINAL_POSITION'],
    ];
  }
  $res->free();
  return $rows;
}
$SCHEMA = vtc_schema($mysqli);

$LABEL_OVERRIDES = [
  'dl_number' => 'Driver/ID Number',
  'cid_number' => 'CID Number',
  'address1' => 'Address line 1',
  'address2' => 'Address line 2',
  'city' => 'City',
  'state' => 'State',
  'zip' => 'Zip',
  'phone_primary' => 'Primary Phone (no spaces)',
  'dob' => 'Date of Birth',
  'education_level' => 'Highest Education Level',
  'military_service' => 'Military Service (branch & dates)',
  'emergency_contact_name' => 'Emergency Contact — Full Name',
  'emergency_contact_phone' => 'Emergency Contact — Phone',
  'race' => 'Ethnicity',
  'gender' => 'Gender',
  'employed' => 'Are you currently employed?',
  'employer' => 'Employer Name',
  'occupation' => 'Occupation/Job Title',
  'referral_source' => 'Referral Source',
  'vtc_officer_name' => 'VTC Officer Name',
  'attorney_name' => 'Attorney Name',
  'attorney_email' => 'Attorney Email',
  'marital_status'               => 'Marital Status *',
  'living_situation'             => 'Living Situation *',
  'has_children'                 => 'Do you have any children? *',
  'children_live_with_you'       => 'If YES, do your children live with you?',
  'names_and_ages_of_your_children_if_applicable'          => 'Names & Ages of your children (If applicable):',
  'children_abused_physically'   => 'Have any of your children EVER been abused physically? *',
  'children_abused_sexually'     => 'Have any of your children EVER been abused sexually? *',
  'children_abused_emotionally'  => 'Have any of your children EVER been abused emotionally? *',
  'children_neglected'           => 'Have any of your children EVER been neglected? *',
  'cps_notified'                 => 'If YES, has Child Protective Services ever been notified?',
  'cps_care_or_supervision'              => 'If YES, have any of your children ever been under CPS care or supervision?',
  'discipline_description'       => 'Describe how you discipline your children. Provide examples (if applicable):',
];
foreach ($LABEL_OVERRIDES as $k=>$v) if (isset($SCHEMA[$k])) $SCHEMA[$k]['comment'] = $v;

// Beck Anxiety Inventory (BAI) answer set
$BAI_CHOICES = [
  'Not At All',
  'Mildly: It did not bother me much.',
  'Moderately: It was very unpleasant but I could stand it.',
  'Severely: I could barely stand it.',
];


// Force full Page-1 consent language in PHP (not the DB)
$LONG_LABELS = [
  'policy_facility_privacy_requirements' => <<<TXT
Free for Life Group requires facilitators and participants to disable any devices that could collect information from the environment, such as Google Home Assistant, Amazon Alexa, or Apple Siri and sign an agreement that they will not record nor take screenshots of Evaluation. Free for Life Group requires each participant: is in a private space and not in any public area such as a park, yard, open area; other people not in the evaluation should not be exposed to the content nor hear or observe the evaluation. This includes changing locations, walking around the house, neighborhood, or any public space. • Responsibility of having a private area on the participant. • The participant cannot use virtual evaluation session to expel his/her partner or children from the residence. • The participant must relocate to another location or private room in the residence. • For participants that are parents, Free for Life Group shall: discuss with participants how the participant will ensure children are safe and taken care of, but are not interrupting the session or listening to evaluation discussions.
TXT,
  'as_a_client_you_have_the_right_to_withhold_or_release_informatio' => <<<TXT
As a client, you have the right to withhold or release information to other individuals or agencies. A statement signed by you is required before any information may be released to anyone outside Free for Life Group -VTC Evaluation. This right applies with the following exceptions: A. When a court of law subpoenas information shared by you with your Evaluator. B. When there is reasonable concern that harm may come to you or others, as in child abuse, elder abuse and abuse of a disabled person. In accordance to the Code of Ethics of the Texas State Boards of Licensed Professional Counselors, Chapter 681, Subchapter C, Rule 681.43:A licensee shall report to the Texas Department of Protective and Regulatory Services (TDPRS) if required by any of the following laws: 1) the Family Code, Chapter 261, concerning abuse or neglect of minors; 2) the Human Resources Code, Chapter 48, concerning abuse, neglect or exploitation of elderly or disabled persons. Also, when staff determines that there is probability of imminent physical injury to self or others, staff will take safety initiatives and may if appropriate, notify medical or law enforcement personnel and/or the victim/partner (Section 611.004 (a) of the Texas Health and Safety Code). C. When there is disclosure of sexual misconduct or sexual exploitation by a previous therapist or mental health professional. In accordance to the Code of Ethics of the Texas State Board of Licensed Professional Counselors, Chapter 681, Subchapter C, Rule 681.43
TXT,
];

// Inject these long labels into the in-memory schema before we build pages
foreach ($LONG_LABELS as $col => $txt) {
  if (isset($SCHEMA[$col])) {
    $SCHEMA[$col]['comment'] = $txt;
  }
}


/* ────────────────────────────────────────────────────────────────────────── */
/* Dev endpoints (optional)                                                   */
/* ────────────────────────────────────────────────────────────────────────── */
if (isset($_GET['schema'])) {
  header('content-type: application/json');
  echo json_encode(array_values($SCHEMA), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
  exit;
}

/* ────────────────────────────────────────────────────────────────────────── */
/* Page plan (26 pages)                                                       */
/*  - Uses comment/label text to place fields; "exact" overrides for key IDs  */
/* ────────────────────────────────────────────────────────────────────────── */
$PAGES[1]['exact'] = ['first_name','last_name','email']; // plus consents you already render

$PAGES[2] = [
  'title' => 'Intake Information & Personal History',
  'exact' => [
    'dl_number','cid_number',
    'address1','address2','city','state','zip','phone_primary',
    'dob','age','race','gender','education_level','employed','employer','occupation',
    'military_service',
    'emergency_contact_name','emergency_contact_phone',
  ],
];

$PAGES[3] = [
  'title' => 'Referral Information',
  'exact' => ['referral_source','vtc_officer_name','attorney_name','attorney_email'],
];

$PAGES[4] = [
  'title' => 'Marital & Family Information',
  'exact' => [
    'marital_status',
    'living_situation',
    'has_children',
    'children_live_with_you',
    'names_and_ages_of_your_children_if_applicable',
    'child_abused_physically',
    'child_abused_sexually',
    'child_abused_emotionally',
    'child_neglected',
    'cps_notified',
    'cps_care_or_supervision',
    'describe_how_you_discipline_your_children_please_provide_example',
  ],
];

$PAGES[5] = [
  'title' => 'Drug & Alcohol Information',
  'exact' => [
    'alcohol_past_use','alcohol_past_details',
    'alcohol_current_use','alcohol_current_details',
    'drug_past_use','drug_past_details',
    'drug_current_use','drug_current_details',
  ],
];

$PAGES[6] = [
  'title' => 'Counseling History',
  'exact' => [
    'counseling_history','counseling_details',
    'depressed_now','depressed_details',
    'suicide_attempt_history','suicide_last_attempt_when',
    'psych_meds_current','psych_meds_list','psych_meds_physician',
    'sexual_abuse_history',
    'head_trauma_history','head_trauma_details',
    'weapon_possession_history',
    'childhood_abuse_history',
  ],
];

$PAGES[7] = [
  'title' => 'Childhood to Adult',
  'exact' => [
    'upbringing_where_grow_up',
    'upbringing_who_raised_you',
    'upbringing_raised_by_both_parents',
    'upbringing_parents_caretakers_names',
    'upbringing_divorce_explain',
    'upbringing_caretaker_addiction',
    'upbringing_caretaker_mental_health',
    'upbringing_finances_growing_up',
    'upbringing_traumatic_experiences',
    'upbringing_school_experience',
    'upbringing_caretakers_help_schoolwork',
    'upbringing_anything_else_for_court',
  ],
];

$PAGES[8] = [
  'title' => 'Legal History',
  'exact' => [
    'legal_first_arrest_details',
    'legal_multiple_arrests_details',
    'legal_prevention_plan',
    'legal_hopes_from_vtc',
  ],
];

$PAGES[9] = [
  'title' => 'Military Experience',
  'exact' => [
    'military_join_details',
    'military_trauma_description',
    'military_impact_beliefs',
    'military_grief_counseling',
    'military_culture_mh_attitudes',
  ],
];

$PAGES[10] = [
  'title' => 'Medications',
  'exact' => [
    'medications_prescribed_history',
    'medications_first_prescribed_details',
    'medications_current_and_desired',
  ],
];
$PAGES[11] = [
  'title' => 'Addiction & Hope',
  'exact' => [
    'addiction_impact_on_life',
    'addiction_overcome_attempts',
    'sobriety_future_impact',
    'hope_for_future_narrative',
  ],
];
$PAGES[12] = [
  'title' => 'Beliefs & Experiences',
  'exact' => [
    'beliefs_impact_on_life',
    'beliefs_extraordinary_experiences',
    'beliefs_shape_future',
  ],
];
// PAGE 13 — Consent for Disclosure & Program Agreement

// Page 13 full consent language overrides
$DEVICE_ACK = 'free_for_life_group_requires_me_to_disable_any_devices_that_coul';

$LONG_LABELS = array_merge($LONG_LABELS ?? [], [

  // --- Consent for Disclosure of Information (ROI) ---
  'roi_purpose_ack' => <<<TXT
I understand that such disclosure will be made for the purposes of progress reports, referrals, and facilitating Court Evaluation and Treatment Recommendations. *
TXT,

  'roi_limited_info_ack' => <<<TXT
Disclosure is limited to information regarding attendance, participation, information exchange and referrals for services. I understand that I may revoke this consent at any time and that my request for revocation must be in writing. If not earlier revoked, this consent for disclosure of information shall expire 1 year after my completion of or termination from Free for Life – VTC Evaluation. *
TXT,

  'roi_confidentiality_ack' => <<<TXT
I understand my right to confidentiality. I further understand that this consent form gives Free for Life – VTC Evaluation permission to share confidential information about me in the way described above. I understand that The Court will be contacted by Free for Life Group. Court shall be provided enrollment, completion or termination information from Free for Life – VTC Evaluation. *
TXT,

  'roi_voluntary_ack' => <<<TXT
Release of information is voluntary; I understand I have a right to refuse Free for Life – VTC Evaluation request for this disclosure. *
TXT,

  // --- Free for Life Program Agreement ---
  'rule_dismissal_ack' => <<<TXT
Free for Life – VTC Evaluation reserves the right to dismiss any client who refuses to meet the provisions of The Texas Department of Criminal Justice- Veteran's Court guidelines. Information you disclose during an assessment / evaluation is confidential and shall be shared only with Veteran's Court. *
TXT,

  'rule_eval_components_ack' => <<<TXT
VTC Evaluation consists of Psychosocial History, Assessments, and Interpretive Evaluation Report. This process may take as long as 3 hours. *
TXT,

  'rule_complete_process_ack' => <<<TXT
You must complete the entire evaluation process or you will be discharged and referred back to the court- in the absence of legitimate extenuating circumstances. *
TXT,

  'rule_focus_webcam_ack' => <<<TXT
You must be focused and facing your webcam during the entire Evaluation; NO watching TV or working on your computer; You will only be given one warning, second warning will result in no credit for the session and/or receive a discharge violation from Free for Life VTC Evaluation. *
TXT,

  'rule_quiet_room_ack' => <<<TXT
You must be in a quiet room; not driving, in your car, or doing any other activity, otherwise you will receive no credit for that VTC Evaluation session and/or receive a discharge violation from Free for Life VTC Evaluation. *
TXT,

  'rule_interruption_no_credit_ack' => <<<TXT
If there is any interruption during your Free for Life Group by a child or adult, you will lose credit for your class and/or receive a discharge violation from Free for Life Program. *
TXT,

  'rule_substance_violation_ack' => <<<TXT
If there is any appearance of alcohol, or vaping during Free for Life Group, it will result in a request for a drug test, if found dirty, you must sign a behavioral contract or unsuccessfully discharge from Free for Life VTC Evaluation. *
TXT,

  'rule_restroom_5min_ack' => <<<TXT
If after a restroom break you do not return, or you take more than 5 minutes, you shall receive no credit for your Free for Life VTC Evaluation session. *
TXT,

  'rule_payment_due_ack' => <<<TXT
Payment for services is due at the time service is rendered. You will not be credited for Evaluation unless payment is received. *
TXT,

  'rule_arrive_on_time_ack' => <<<TXT
I hereby agree to arrive to all of my sessions on time. If you are 5 minutes late after the designated start time - you will not receive credit for attending Evaluation.. *
TXT,

  'rule_update_contact_ack' => <<<TXT
I will notify Free for Life VTC Evaluation of any change of address or phone number. *
TXT,

  'rule_call_if_absent_ack' => <<<TXT
I hereby agree to contact VTC Evaluation by phone at 817-501-5102 when I am unable to attend a scheduled session. Failure to contact Free for Life is an automatic discharge from Free for Life Program. *
TXT,

  'rule_no_intoxication_ack' => <<<TXT
I agree not to attend Evaluation under the influence of alcohol or drugs; refusal of a Drug Screening is an automatic discharge. It will be my responsibility to arrange transportation or any safety measures so I'm not a danger to myself or others for driving under the influence. Veteran's Court will be notified of the incident. *
TXT,

  'rule_no_abusive_language_ack' => <<<TXT
I hereby agree not to be abusive towards any staff person. I understand that I may not use sexist or racist language. *
TXT,

  'rule_notify_emergencies_ack' => <<<TXT
I hereby agree to notify a staff person of any and all emergencies that I am either part of or witness to. *
TXT,

  'rule_program_goal_ack' => <<<TXT
I understand that Free for Life VTC Evaluation is committed to helping me gain a better understanding of my problems and how to find productive solutions- the main goal of my Evaluation. *
TXT,

  'rule_no_violence_ack' => <<<TXT
Participants agree to not use any form of violence, abusive, threatening and controlling behaviors including stalking during the Evaluation and VTC Court Process. A participant who uses violence may be terminated from the program. This action will be reported to participant’s referral agencies. Participants who are terminated for this reason and wish to re-enter the program will re-start their Evaluation Process. *
TXT,

  // --- Taking Responsibility: device/privacy requirement (truncated column name) ---
  $DEVICE_ACK => <<<TXT
Free for Life Group requires me to disable any devices that could collect information from the environment during my Evaluation, such as Google Home Assistant, Amazon Alexa, or Apple Siri and I agree that I will not record nor take screenshots of my evaluation session. Free for Life Group requires me to be: in a private space and not in any public area such as a park, yard, open area; other people not in my evaluation should not be exposed to the content nor hear or observe my evaluation. This includes changing locations, walking around the house, neighborhood, or any public space. The responsibility of having a private area is mine. I cannot use a virtual session to expel my partner or children from the residence. I must relocate to another location or private room in the residence. If I am a parent, I agree to ensure my children are safe and taken care of, but are not interrupting the session or listening to session discussions. *
TXT,
]);


$PAGES[13] = [
  'title' => 'Consent for Disclosure & Program Agreement',
  'exact' => [
    // Consent for Disclosure of Information (ROI)
    'roi_purpose_ack',
    'roi_limited_info_ack',
    'roi_confidentiality_ack',
    'roi_voluntary_ack',

    // Free for Life Program Agreement
    'rule_dismissal_ack',
    'rule_eval_components_ack',
    'rule_complete_process_ack',
    'rule_focus_webcam_ack',
    'rule_quiet_room_ack',
    'rule_interruption_no_credit_ack',
    'rule_substance_violation_ack',
    'rule_restroom_5min_ack',
    'rule_payment_due_ack',
    'rule_arrive_on_time_ack',
    'rule_update_contact_ack',
    'rule_call_if_absent_ack',
    'rule_no_intoxication_ack',
    'rule_no_abusive_language_ack',
    'rule_notify_emergencies_ack',
    'rule_program_goal_ack',
    'rule_no_violence_ack',
    $DEVICE_ACK,  // Taking Responsibility (device/privacy requirement)
  ],
];
$PAGES[14] = [
  'title' => 'Individualized Plan (Legal Offense/Charge)',
  'exact' => ['legal_offense_summary'],
];
$PAGES[15] = [
  'title' => 'Termination Policy & Client Rights (I Agree) + Signatures',
  'exact' => [
    'policy_received_ack',
    'rights_provider_obligations_ack',
    'rights_client_terminate_ack',
    'rights_provider_terminate_conditions',
    'rights_termination_policy_scope_ack',
    'rights_termination_circumstances_copy_ack',
    'final_attestation_ack',
    'legal_first_name','legal_last_name',
  ],
];
$PAGES[16] = [
  'title' => 'Inventories: BDI (Beck Depression Inventory)',
  'exact' => [
    'punishment_feelings',
    'sadness',
    'pessimism',
    'self_dislike',
    'past_failure',
    'self_criticalness',
    'loss_of_pleasure',
    'suicidal_thoughts_or_wishes',
    'guilty_feelings',
    'crying',
    'agitation',
    'irritability',
    'loss_of_interest',
    'loss_of_interest_in_sex',
    'indecisiveness',
    'concentration_difficulty',
    'worthlessness',
    'tiredness_or_fatigue',
    'loss_of_energy',
    'changes_in_appetite',
    'changes_in_sleeping_pattern',
  ],
];

$PAGES[17] = [
  'title' => 'Inventories: BAI (Beck Anxiety Inventory)',
  'exact' => [
    'feeling_hot',
    'wobbliness_in_legs',
    'numbness_or_tingling',
    'unable_to_relax',
    'fear_of_worst_happening',
    'dizzy_or_lightheaded',
    'heart_pounding_racing',
    'unsteady',
    'terrified_or_afraid',
    'nervous',
    'feeling_of_choking',
    'hands_trembling',
    'shaky_unsteady',
    'fear_of_losing_control',
    'difficulty_breathing',
    'fear_of_dying',
    'scared',
    'face_flushed',
    'indigestion_or_discomfort_in_abdomen',
    'faint_lightheaded',
    'sweating_not_due_to_heat',
  ],
];

$PAGES[18] = [
  'title' => 'Inventories: BHS (Beck Hopelessness Scale)',
  'exact' => [
    '1_i_look_forward_to_the_future_with_hope_and_enthusiasm',
    '2_i_might_as_well_give_up_because_i_cant_make_things_better_for_',
    '3_when_things_are_going_badly_i_am_helped_by_knowing_they_cant_s',
    '4_i_cant_imagine_what_my_life_would_be_like_in_10_years',
    '5_i_have_enough_time_to_accomplish_the_things_i_most_want_to_do',
    '6_in_the_future_i_expect_to_succeed_in_what_concerns_me_most',
    '7_my_future_seems_dark_to_me',
    '8_i_expect_to_get_more_good_things_in_life_than_the_average_pers',
    '9_i_just_dont_get_the_breaks_and_theres_no_reason_to_believe_i_w',
    '10_my_past_experiences_have_prepared_me_well_for_the_future',
    '11_all_i_can_see_ahead_of_me_is_unpleasantness_rather_than_pleas',
    '12_i_dont_expect_to_get_what_i_really_want',
    '13_when_i_look_ahead_to_the_future_i_expect_i_will_be_happier_th',
    '14_things_just_wont_work_out_the_way_i_want_them_to',
    '15_i_have_great_faith_in_the_future',
    '16_i_never_get_what_i_want_so_its_foolish_to_want_anything',
    '17_it_is_very_unlikely_that_i_will_get_any_real_satisfaction_in_',
    '18_the_future_seems_vague_and_uncertain_to_me',
    '19_i_can_look_forward_to_more_good_times_than_bad_times',
    '20_theres_no_use_in_really_trying_to_get_something_i_want_becaus',
  ],
];

$PAGES[19] = [
  'title' => 'Inventories: HAM-D',
  'exact' => [
    'insomnia_initial_difficulty_in_falling_asleep',
    'agitation_restlessness_associated_with_anxiety',
    'insomnia_middle_complains_of_being_restless_and_disturbed_during',
    'insomnia_delayed_waking_in_early_hours_of_the_morning_and_unable',
    'insight_insight_must_be_interpreted_in_terms_of_patients_underst',
    'genital_symptoms_loss_of_libido_menstrual_disturbances',
    'somatic_symptoms_gastrointestinal_loss_of_appetite_heavy_feeling',
    'somatic_symptoms_general',
    'diurnal_variation_symptoms_worse_in_morning_or_evening_note_whic',
    'weight_loss',
    'obsessional_symptoms_obsessive_thoughts_and_compulsions_against_',
    'suicide',
    'retardation_slowness_of_thought_speech_and_activity_apathy_stupo',
    'feelings_of_guilt',
    'work_and_interests',
    'anxiety',
    'anxiety_somatic_gastrointestinal_indigestion_cardiovascular_palp',
    'depersonalization_and_derealization_feelings_of_unreality_nihili',
    'depressed_mood_gloomy_attitude_pessimism_about_the_future_feelin',
    'hypochondriasis',
    'paranoid_symptoms_not_with_a_depressive_quality',
  ],
];

$PAGES[20] = [
  'title' => 'Inventories: HAM-A',
  'exact' => [
    'tension_feelings_of_tension_fatigability_startle_response_moved_',
    'anxious_worries_anticipation_of_the_worst_fearful_anticipation_i',
    'fears_of_dark_of_strangers_of_being_left_alone_of_animals_of_tra',
    'insomnia_difficulty_in_falling_asleep_broken_sleep_unsatisfying_',
    'intellectual_cognitive_difficulty_in_concentration_poor_memory',
    'depressed_mood_loss_of_interest_lack_of_pleasure_in_hobbies_depr',
    'somatic_muscular_pains_and_aches_twitching_stiffness_myoclonic_j',
    'somatic_sensory_tinnitus_blurring_of_vision_hot_and_cold_flushes',
    'cardiovascular_symptoms_tachycardia_palpitations_pain_in_chest_t',
    'respiratory_symptoms_pressure_or_constriction_in_chest_choking_f',
    'gastrointestinal_symptoms_difficulty_in_swallowing_wind_abdomina',
    'genitourinary_symptoms_frequency_of_micturition_urgency_of_mictu',
    'autonomic_symptoms_dry_mouth_flushing_pallor_tendency_to_sweat_g',
    'behavior_fidgeting_restlessness_or_pacing_tremor_of_hands_furrow',
  ],
];

$PAGES[21] = [
  'title' => 'PTSD CheckList – Military Version (PCL-M)',
  'exact' => [
    '1_repeated_disturbing_memories_thoughts_or_images_of_a_stressful',
    '2_repeated_disturbing_dreams_of_a_stressful_military_experience',
    '3_suddenly_acting_or_feeling_as_if_a_stressful_military_experien',
    '4_feeling_very_upset_when_something_reminded_you_of_a_stressful_',
    '5_having_physical_reactions_e_g_heart_pounding_trouble_breathing',
    '6_avoid_thinking_about_or_talking_about_a_stressful_military_exp',
    '7_avoid_activities_or_talking_about_a_stressful_military_experie',
    '8_trouble_remembering_important_parts_of_a_stressful_military_ex',
    '9_loss_of_interest_in_things_that_you_used_to_enjoy',
    '10_feeling_distant_or_cut_off_from_other_people',
    '11_feeling_emotionally_numb_or_being_unable_to_have_loving_feeli',
    '12_feeling_as_if_your_future_will_somehow_be_cut_short',
    '13_trouble_falling_or_staying_asleep',
    '14_feeling_irritable_or_having_angry_outbursts',
    '15_having_difficulty_concentrating',
    '16_being_super_alert_or_watchful_on_guard',
    '17_feeling_jumpy_or_easily_startled',
  ],
];

$PAGES[22] = [
  'title' => 'TBI Checklist',
  'exact' => [
    '1_feeling_dizzy',
    '2_loss_of_balance',
    '3_poor_coordination_clumsy',
    '4_headaches',
    '5_nausea',
    '6_vision_problems_blurring_trouble_seeing',
    '7_sensitivity_to_light',
    '8_hearing_difficulty',
    '9_sensitivity_to_noise',
    '10_numbness_to_tingling_on_parts_of_body',
    '11_change_in_taste_and_or_smell',
    '12_loss_or_increase_of_appetite',
    '13_poor_concentration_or_easily_distracted',
    '14_forgetfulness_cant_remember_things',
    '15_difficulty_making_decisions',
    '16_slowed_thinking_cant_finish_things',
    '17_fatigue_loss_of_energy_easily_tired',
    '18_difficulty_falling_or_staying_asleep',
    '19_feeling_anxious_or_tense',
    '20_feeling_depressed_or_sad',
    '21_irritability_easily_annoyed',
    '22_poor_frustration_tolerance_overwhelmed',
  ],
];

$PAGES[23] = [
  'title' => 'SASSI (True/False)',
  'exact' => [
    '1_people_know_they_can_count_on_me_for_solutions',
    '2_most_people_make_some_mistakes_in_their_lives',
    '3_i_usually_go_along_and_do_what_others_are_doing',
    '4_i_have_never_been_in_trouble_with_the_police',
    '5_i_was_always_well_behaved_in_school',
    '6_i_like_doing_things_on_the_spur_of_the_moment',
    '7_i_have_not_lived_the_way_i_should',
    '8_i_can_be_friendly_with_people_who_do_many_wrong_things',
    '9_i_do_not_like_to_sit_and_daydream',
    '10_no_one_has_ever_criticized_or_punished_me',
    '11_sometimes_i_have_a_hard_time_sitting_still',
    '12_people_would_be_better_off_if_they_took_my_advice',
    '13_at_times_i_feel_worn_out_for_no_special_reason',
    '14_i_am_a_restless_person',
    '15_it_is_better_not_to_talk_about_personal_problems',
    '16_i_have_had_days_weeks_or_months_when_i_couldnt_get_much_done_',
    '17_i_am_very_respectful_of_authority',
    '18_i_come_up_with_good_strategies',
    '19_i_have_been_tempted_to_leave_home',
    '20_i_often_feel_that_strangers_look_at_me_with_disapproval',
    '21_other_people_would_fall_apart_if_they_had_to_deal_with_what_i',
    '22_i_have_avoided_people_i_did_not_want_to_speak_to',
    '23_some_crooks_are_so_clever_that_i_hope_they_get_away_with_what',
    '24_my_school_teachers_had_some_problems_with_me',
    '25_i_have_never_done_anything_dangerous_just_for_fun',
    '26_i_need_to_have_something_to_do_so_i_dont_get_bored',
    '27_i_have_sometimes_drunk_too_much',
    '28_much_of_my_life_is_uninteresting',
    '29_sometimes_i_wish_i_could_control_myself_better',
    '30_i_believe_that_people_sometimes_get_confused',
    '31_sometimes_i_am_no_good_for_anything_at_all',
    '32_i_break_more_laws_than_many_people',
    '33_if_some_friends_and_i_were_in_trouble_together_i_would_rather',
    '34_crying_does_not_help',
    '35_i_think_there_is_something_wrong_with_my_memory',
    '36_i_have_sometimes_been_tempted_to_hit_people',
    '37_most_people_would_lie_to_get_what_they_want',
    '38_i_always_feel_sure_of_myself',
    '39_i_have_never_broken_a_major_law',
    '40_there_have_been_times_when_i_have_done_things_i_couldnt_remem',
    '41_i_think_carefully_about_all_my_actions',
    '42_i_have_used_too_much_alcohol_or_pot_or_used_too_often',
    '43_nearly_everyone_enjoys_being_picked_on_and_made_fun_of',
    '44_i_like_to_obey_the_law',
    '45_i_frequently_make_lists_of_things_to_do',
    '46_i_think_i_know_some_pretty_undesirable_types',
    '47_most_people_will_laugh_at_a_joke_now_and_then',
    '48_i_have_rarely_been_punished',
    '49_i_use_tobacco_regularly',
    '50_at_times_i_have_been_so_full_of_energy_that_i_felt_i_didnt_ne',
    '51_i_have_sometimes_sat_around_when_i_should_have_been_working',
    '52_i_am_often_resentful',
    '53_i_take_all_my_responsibilities_seriously',
    '54_i_do_most_of_my_drinking_or_drug_use_away_from_home',
    '55_i_have_had_a_drink_first_thing_in_the_morning_to_steady_my_ne',
    '56_while_i_was_a_teenager_i_began_drinking_or_using_other_drugs_',
    '57_one_of_my_parents_was_is_a_heavy_drinker_or_drug_user',
    '58_when_i_drink_or_use_drugs_i_tend_to_get_into_trouble',
    '59_my_drinking_or_other_drug_use_causes_problems_between_me_and_',
    '60_new_activities_can_be_a_strain_if_i_cant_drink_or_use_when_i_',
    '61_i_frequently_use_non_prescription_antacids_or_digestion_medic',
    '62_i_have_never_felt_sad_over_anything',
    '63_i_have_neglected_obligations_to_family_or_work_because_of_my_',
    '64_i_am_usually_happy',
    '65_im_good_at_figuring_out_the_plot_in_a_spy_drama_or_murder_mys',
    '66_i_have_wished_i_could_cut_down_my_drinking_or_drug_use',
    '67_i_am_a_binge_drinker_drug_user',
    '68_i_often_use_energy_drinks_or_other_over_the_counter_products_',
    '69_im_reluctant_to_tell_my_doctors_about_all_the_medications_im_',
    '70_my_doctors_have_not_prescribed_me_enough_medication_to_get_th',
    '71_i_know_that_my_drinking_using_is_making_my_problems_worse',
    '72_i_have_built_up_a_tolerance_to_the_alcohol_drugs_or_medicatio',
    '73_over_time_i_have_noticed_i_drink_or_use_more_than_i_used_to',
    '74_i_have_worried_about_my_parent_s_drinking_or_drug_use',
  ],
];

$PAGES[24] = [
  'title' => 'Alcohol Use',
  'exact' => [
    '1_had_drinks_beer_wine_liquor_with_lunch',
    '2_taken_a_drink_or_drinks_to_help_you_talk_about_your_feelings_o',
    '3_taken_a_drink_or_drinks_to_relieve_a_tired_feeling_or_give_you',
    '4_had_more_to_drink_than_you_intended_to',
    '5_experienced_physical_problems_after_drinking_e_g_nausea_seeing',
    '6_gotten_into_trouble_on_the_job_in_school_or_with_the_law_becau',
    '7_became_depressed_after_having_sobered_up',
    '8_argued_with_your_family_or_friends_because_of_your_drinking',
    '9_had_the_effects_of_drinking_recur_after_not_drinking_for_a_whi',
    '10_had_problems_in_relationships_because_of_your_drinking_e_g_lo',
    '11_became_nervous_or_had_the_shakes_after_having_sobered_up',
    '12_tried_to_commit_suicide_while_drunk',
    '13_found_myself_craving_a_drink_or_a_particular_drug',
  ],
];

$PAGES[25] = [
  'title' => 'Drug Use',
  'exact' => [
    '1_misused_medications_or_took_drugs_to_improve_your_thinking_and',
    '2_misused_medications_or_took_drugs_to_help_you_feel_better_abou',
    '3_misused_medications_or_took_drugs_to_become_more_aware_of_your',
    '4_misused_medications_or_took_drugs_to_improve_your_enjoyment_of',
    '5_misused_medications_or_took_drugs_to_help_forget_that_you_feel',
    '6_misused_medications_or_took_drugs_to_forget_school_work_or_fam',
    '7_gotten_into_trouble_at_home_work_or_with_the_police_because_of',
    '8_gotten_really_stoned_or_wiped_out_on_drugs_more_than_just_high',
    '9_tried_to_get_a_hold_of_some_prescription_drug_e_g_tranquilizer',
    '10_spent_your_spare_time_in_drug_related_activities_e_g_talking_',
    '11_used_drugs_or_medications_and_alcohol_at_the_same_time',
    '12_kept_taking_medications_or_drugs_in_order_to_avoid_pain_or_wi',
    '13_felt_your_misuse_of_medications_alcohol_or_drugs_has_kept_you',
    '14_took_a_higher_dose_or_different_medications_than_your_doctor_',
    '15_used_prescription_drugs_that_were_not_prescribed_for_you',
    '16_your_doctor_denied_your_request_for_medications_you_needed',
    '17_been_accepted_into_a_treatment_program_because_of_misuse_of_m',
    '18_engaged_in_activity_that_could_have_been_physically_dangerous',
  ],
];

$PAGES[26] = [
  'title' => 'Evaluation Signature and Submission',
  'exact' => ['sign_first_name','sign_last_name','timestamp','signature_attestation_text'],
];



$LONG_LABELS = ($LONG_LABELS ?? []) + [
  // page 2
  'phone_primary' => 'Primary Phone (no spaces)',
  'education_level' => 'Highest Education Level',
  'race' => 'Ethnicity',
  'military_service' => 'Military Service (branch & dates) *', // required per your earlier request
  'emergency_contact_name' => 'Emergency Contact — Full Name',
  'emergency_contact_phone' => 'Emergency Contact — Phone',
  // page 3
  'referral_source'  => 'Referral Source',
  'vtc_officer_name' => "VTC Court Officer’s Name *",
  'attorney_name'    => "Attorney’s Name",
  'attorney_email'   => "Attorney’s Email",
  // page 4
  'marital_status'              => 'Marital Status *',
  'living_situation'            => 'Living Situation *',
  'has_children'                => 'Do you have any children? *',
  'children_live_with_you'      => 'If YES, do your children live with you?',
  'names_and_ages_of_your_children_if_applicable'         => 'Names & Ages of your children (If applicable):',
  'child_abused_physically'  => 'Have any of your children EVER been abused physically? *',
  'child_abused_sexually'    => 'Have any of your children EVER been abused sexually? *',
  'child_abused_emotionally' => 'Have any of your children EVER been abused emotionally? *',
  'child_neglected'          => 'Have any of your children EVER been neglected? *',
  'cps_notified'                => 'If YES, has Child Protective Services ever been notified?',
  'cps_care_or_supervision'             => 'If YES, have any of your children ever been under CPS care or supervision?',
  'describe_how_you_discipline_your_children_please_provide_example'      => 'Describe how you discipline your children. Provide examples (if applicable):',

  'alcohol_past_use'        => 'Use of alcohol in the past? *',
  'alcohol_past_details'    => 'If YES, how often? and how much?',
  'alcohol_current_use'     => 'Use of alcohol currently? *',
  'alcohol_current_details' => 'If YES, how often? and how much?',
  'drug_past_use'           => 'Use of drugs in the past? *',
  'drug_past_details'       => 'If YES, how often? and what drug?',
  'drug_current_use'        => 'Use of drugs currently? *',
  'drug_current_details'    => 'If YES, how often? and what drug?',

  'counseling_history'            => 'Have you ever been in counseling? *',
  'counseling_details'            => 'If YES, please explain:',
  'depressed_now'                 => 'Are you currently depressed? *',
  'depressed_details'             => 'If YES, please explain:',
  'suicide_attempt_history'       => 'Have you ever attempted suicide? *',
  'suicide_last_attempt_when'     => 'If YES, when was the last attempt?',
  'psych_meds_current'            => 'Are you taking any medications for any mental health conditions? *',
  'psych_meds_list'               => 'If YES, what medications?',
  'psych_meds_physician'          => 'If YES, providing physician?',
  'sexual_abuse_history'          => 'Do you have any history of being sexual abused or assaulted by others? *',
  'head_trauma_history'           => 'Do you have any history of head trauma injuries or episodes of blackouts? (including overdoses, coma, stroke, accidents, any form of blunt force) *',
  'head_trauma_details'           => 'If "Yes" to head trauma, please describe.',
  'weapon_possession_history'     => 'Do you have history of possessing a weapon? *',
  'childhood_abuse_history'       => 'Do you have any history of abuse and/or trauma as a child? *',

  'upbringing_where_grow_up'                 => 'Where did you grow up? *',
  'upbringing_who_raised_you'                => 'Who raised you? *',
  'upbringing_raised_by_both_parents'        => 'Were you raised by both parents? *',
  'upbringing_parents_caretakers_names'      => 'What are your parents / caretakers names? *',
  'upbringing_divorce_explain'               => 'Was there a divorce? If so, please explain. *',
  'upbringing_caretaker_addiction'           => 'Did they struggle with any form of addiction? Please describe. *',
  'upbringing_caretaker_mental_health'       => 'Did they struggle with any sort of mental health issue? Please Describe. *',
  'upbringing_finances_growing_up'           => 'What were finances like when you were growing up? Please elaborate. *',
  'upbringing_traumatic_experiences'         => 'Were there any major incidents, accidents, natural disasters, or other forms of traumatic experiences growing up? Please Share in Detail. *',
  'upbringing_school_experience'             => 'What was school like for you? Please describe in detail — from academic study to interacting with your peers, fellow students and teachers. *',
  'upbringing_caretakers_help_schoolwork'    => 'Did your caretakers help you study or make sure your school work was completed? Please Describe. *',
  'upbringing_anything_else_for_court'       => 'Please share anything else that will help Veteran’s Court understand your life, challenges, and legal circumstances. *',

  'legal_first_arrest_details'     => 'When was the first time you were arrested? Describe the details of what happened; including age, location, charge/circumstance, consequence, punishment, sentence, and impact on your life. *',
  'legal_multiple_arrests_details' => 'Were you arrested more than once? If so, please elaborate each arrest in detail including age, location, charge/circumstance, consequence, punishment, sentence, and impact on your life. *',
  'legal_prevention_plan'          => 'How do you hope to keep yourself from getting into more legal trouble and consequences going forward in your life? Please share all your thoughts and ideas. *',
  'legal_hopes_from_vtc'           => 'How do you hope to be impacted by your Veteran’s Court experience? Please elaborate. *',

  'military_join_details'         => 'When did you join the Military? Please include age, branch, duties, time span, locations, wartime, combat, etc. *',
  'military_trauma_description'   => 'Please describe any traumatic events you experienced while in the Military; however big or small; significant, ordinary, or extraordinary. *',
  'military_impact_beliefs'       => 'What impact do you believe these experiences had / have on your life? *',
  'military_grief_counseling'     => 'Have you had any counseling or therapeutic interventions to help you resolve your grief? If so, please describe, if not please elaborate. *',
  'military_culture_mh_attitudes' => 'How did the Military and/or your colleagues view anyone seeking Mental Health Counseling or Treatment? Please Elaborate. *',

  'medications_prescribed_history'       => 'Were you ever prescribed Mental Health, Psychiatric, or Medical Medications? *',
  'medications_first_prescribed_details' => 'When were you first prescribed Medical, Mental Health, or Psychiatric Medications, age, circumstance, symptoms, diagnoses, names of medications, benefits and issues. *',
  'medications_current_and_desired'      => 'What, if any, medications do you take currently? Are there any you would like to take but have not, or cannot? Please elaborate. *',

  'addiction_impact_on_life'    => 'If you struggle or have ever struggled with Addiction to any mood altering substance, what impact did / does it have on your life — including benefits, enjoyment, risks, problems, consequences on your body, mind, and spirit. Please go into detail. *',
  'addiction_overcome_attempts' => 'How have you overcome or tried to overcome any of your addictions or substance use / abuse? Please openly share. *',
  'sobriety_future_impact'      => 'What impact might sobriety have on your life going forward? Please elaborate. *',
  'hope_for_future_narrative'   => 'Share what it is that gives you hope for the future. What is it that fuels your being to move forward in a positive healthy direction in your life? Please share all the ideas that come to mind or surface. *',

  'beliefs_impact_on_life'            => 'How have your beliefs impacted your life? Please elaborate and feel free to provide detail. *',
  'beliefs_extraordinary_experiences' => 'Please share about any extraordinary or supernatural experiences you\'ve had and how they impacted you; including premonitions, intuitions, out of body experiences, near death experiences, after death visitations, moments of clarity, near death like experiences, powerful dreams. Feel free to share in as much detail as you\'d like. *',
  'beliefs_shape_future'              => 'How would you like your beliefs and experiences to shape your life going forward? *',

  'legal_offense_summary' => 'Please state what legal offence/s lead to your arrest, and what you wound up being charged with. *',

  'feeling_hot' => 'Feeling hot',
  'wobbliness_in_legs' => 'Wobbliness in legs',
  'numbness_or_tingling' => 'Numbness or tingling',
  'unable_to_relax' => 'Unable to relax',
  'fear_of_worst_happening' => 'Fear of worst happening',
  'dizzy_or_lightheaded' => 'Dizzy or lightheaded',
  'heart_pounding_racing' => 'Heart pounding/racing', 
  'unsteady' => 'Unsteady',
  'terrified_or_afraid' => 'Terrified or afraid', 
  'nervous' => 'Nervous',
  'feeling_of_choking' => 'Feeling of choking',
  'hands_trembling' => 'Hands trembling',
  'shaky_unsteady' => 'Shaky/Unsteady',
  'fear_of_losing_control' => 'Fear of losing control',
  'difficulty_breathing' => 'Difficulty breathing',
  'fear_of_dying' => 'Fear of dying',
  'scared' => 'Scared',
  'face_flushed' => 'Face flushed',
  'indigestion_or_discomfort_in_abdomen' => 'Indigestion or discomfort in abdomen',
  'faint_lightheaded' => 'Faint, lightheaded',
  'sweating_not_due_to_heat' => 'Sweating (not due to heat)',
];
$LONG_LABELS[$DEVICE_ACK] =
  'Free for Life Group requires me to disable any devices that could collect information from the environment during my Evaluation, such as Google Home Assistant, Amazon Alexa, or Apple Siri, and I agree that I will not record nor take screenshots of the Evaluation. I must be in a private space and not in any public area (park, yard, open area); other people not in the evaluation should not be exposed to the content nor hear or observe the evaluation. This includes changing locations, walking around the house, neighborhood, or any public space. If I am a parent, I agree to ensure my children are safe and taken care of, but are not interrupting the session or listening to session discussions.';
$LONG_LABELS += [
  // VTC Evaluation Policy for Clients & Termination Policy
  'policy_received_ack' => <<<TXT
I have received a copy of the "Policy for Clients" for Free for Life – VTC Evaluation. I understand my rights and responsibilities and I agree to enter Free for Life – VTC Evaluation. *
TXT,
  'rights_provider_obligations_ack' => <<<TXT
I understand that in accordance with Guideline 31 of the Texas Department of Criminal Justice-Community Justice Assistance Division and Texas Council VTC Court guidelines, I am being provided a written agreement that clearly delineates the obligation of the Free for Life -VTC Evaluation to the client. I understand that the Free for Life -VTC Evaluation shall: 1. Provide services in a manner that I can understand. 2. Provide copies of all written agreements. 3. Notify me of changes in evaluation time and schedule. 4. Comply with anti-discrimination laws. 5. Report courts of law, and/or other referral agencies regarding my progress or lack of progress during my evaluation. 6. Provide reports daily. and / or weekly about my VTC Evaluation progress to my referral source: Probation, Parole, Child Protective Services, Courts, Attorney. 7. Report to me regarding my status and participation. 8. Provide fair and humane treatment. *
TXT,
  'rights_client_terminate_ack' => <<<TXT
As a client of Free for Life -VTC Evaluation you have the right to terminate services with our agency at any moment. The risk of terminating services will be explained to you by an evaluator. You have the right to choose other agencies for your services and Free for Life -VTC Evaluation will provide you with a list of known community agencies that may provide the services you need, except for clients referred by Probation; clients will be referred back to their Supervision Officer. *
TXT,
  'rights_provider_terminate_conditions' => <<<TXT
Free for Life -VTC Evaluation also has the right to terminate services with clients if : A. Continued abuse, particularly physical violence. B. Client has accumulated any consecutive absences, C. Client has failed to pay for services, E. Client is believed to be violent/aggressive towards others or staff, F. Client is involved in illegal activities on the premises or during a Zoom session, G. Client's need for treatment is incompatible with types of services Free for Life Group provides, H. Free for Life -VTC Evaluation Client violates any of the VTC Evaluation or VTC Court rules, I. A report will be made within 5 working days to your referral source of any known law violations, incidents or physical violence, and /or termination from VTC Evaluation. Clients have the right to seek other resources outside of Free for Life -VTC Evaluation and when possible Free for Life -VTC Evaluation staff will provide or make a referral. *
TXT,
  'rights_termination_policy_scope_ack' => <<<TXT
The above Termination Policy applies to clients who are attending services on a Voluntary basis or Court-ordered to receive services or who are mandated to receive services by other entities; however, clients are responsible to check with those entities who mandate them to receive Free for Life Group services regarding the alternatives for receiving services in another agency or consequences for choosing to stop services before making this final decision. *
TXT,
  'rights_termination_circumstances_copy_ack' => <<<TXT
Free for Life -VTC Evaluation will provide clients at the time of evaluation with a copy of the circumstances under which they can be terminated before completion. *
TXT,
  'final_attestation_ack' => <<<TXT
Entering my name constitutes a digital signature. I hereby confirm the above information to the best of my knowledge is correct and true, with no misleading or false content in accordance with Texas Perjury Statute, Sec. 37.02 (a) (2) Chapter 32, Civil Practice and Remedies Code.
I have read and understand the above statements and voluntarily enter into Veteran's Court evaluation services from the staff of Free for Life Group -VTC Evaluation- by entering my name below: I hereby confirm the above information to the best of my knowledge is correct and true, with no misleading or false content in accordance with Texas Perjury Statute, Sec. 37.02 (a) (2) Chapter 32, Civil Practice and Remedies Code. *
TXT,

  // Field captions
  'legal_first_name' => 'Legal First Name *',
  'legal_last_name'  => 'Legal Last Name *',
];



/* ────────────────────────────────────────────────────────────────────────── */
/* Build page→fields mapping from schema (with safe matcher)                  */
/* ────────────────────────────────────────────────────────────────────────── */
function build_page_fields(array $SCHEMA, array $PAGES): array {
  $assigned = [];
  $map = [];

  foreach ($PAGES as $i => $page) {
    $set = [];

    // 1) Exact column names (in schema order)
    if (!empty($page['exact'])) {
      foreach ($SCHEMA as $col => $meta) {
        if (in_array($col, $page['exact'], true) && empty($assigned[$col])) {
          $set[$col] = $meta; $assigned[$col] = true;
        }
      }
    }

    // 2) Name regex (match against COLUMN_NAME only)
    if (!empty($page['name_rx'])) {
      foreach ($SCHEMA as $col => $meta) {
        if (!empty($assigned[$col])) continue;
        foreach ($page['name_rx'] as $rx) {
          if (@preg_match($rx, $col)) { $set[$col] = $meta; $assigned[$col] = true; break; }
        }
      }
    }

    // 3) (Optional) Comment regex — used rarely now
    if (!empty($page['comment_rx'])) {
      foreach ($SCHEMA as $col => $meta) {
        if (!empty($assigned[$col])) continue;
        $c = (string)($meta['comment'] ?? '');
        foreach ($page['comment_rx'] as $rx) {
          if ($c !== '' && @preg_match($rx, $c)) { $set[$col] = $meta; $assigned[$col] = true; break; }
        }
      }
    }

    // Keep table order
    uasort($set, fn($a,$b)=>($a['ord']<=>$b['ord']));
    $map[$i] = array_values($set);
  }

  return $map;
}

$PAGE_FIELDS = build_page_fields($SCHEMA, $PAGES);

// Capture any schema fields we never placed (debug helper)
$UNASSIGNED = [];
if (isset($_GET['debug']) && $_GET['debug'] === 'unassigned') {
  $placed = [];
  foreach ($PAGE_FIELDS as $pp) foreach ($pp as $m) $placed[$m['name']] = true;
  foreach ($SCHEMA as $col => $meta) if (!isset($placed[$col]) && !in_array($col,['id','created_at','updated_at'],true)) $UNASSIGNED[] = $meta;
  header('content-type:text/html; charset=utf-8');
  echo "<h2>Unassigned columns</h2><ul>";
  foreach ($UNASSIGNED as $meta) echo "<li><code>".s($meta['name'])."</code> — ".s($meta['comment'])."</li>";
  echo "</ul>"; exit;
}

/* ────────────────────────────────────────────────────────────────────────── */
/* Input renderers                                                            */
/* ────────────────────────────────────────────────────────────────────────── */
function select_box(string $label, string $name, array $options, $val, string $hint=''): string {
  $html = "<label class='fld'><span class='lbl'>".s($label);
  if ($hint) $html .= " <span class='hint'>(".s($hint).")</span>";
  $html .= "</span><select class='input' name='".s($name)."'><option value=''>— select —</option>";
  foreach ($options as $o) {
    $sel = ((string)$val === (string)$o) ? " selected" : "";
    $html .= "<option$sel>".s($o)."</option>";
  }
  $html .= "</select></label>";
  return $html;
}

function input_for(array $meta, $value=null): string {
  global $FORM_ERRORS, $LONG_LABELS;

  // --- basics --------------------------------------------------------------
  $name  = $meta['name'];
  $label = vtc_label($meta);                    // safe prettified label
  $type  = $meta['type'] ?? '';
  $val   = $value ?? ($_SESSION['vtc_form'][$name] ?? '');
  if (is_array($val)) $val = json_encode($val, JSON_UNESCAPED_UNICODE);
  $val   = (string)$val;

  // error plumbing
  $hasErr   = isset($FORM_ERRORS[$name]);
  $wrapErr  = $hasErr ? ' error'      : '';
  $inputErr = $hasErr ? ' is-invalid' : '';
  $msgHtml  = $hasErr ? "<div class='error-msg'>".s($FORM_ERRORS[$name])."</div>" : "";

  // --- HARD OVERRIDES: run before any heuristics --------------------------
  // Force LONG textareas for these columns
  if (in_array($name, [
    // Page 2
    'military_service',
    // Page 4
    'names_and_ages_of_your_children_if_applicable',
    'describe_how_you_discipline_your_children_please_provide_example',
    'discipline_description', // if you're using this shorter key
    // Page 6
    'head_trauma_description',
    'head_trauma_details',
  ], true)) {
    return "<label class='fld fld-long{$wrapErr}'>"
         .   "<span class='lbl'>".s($label)."</span>"
         .   "<textarea class='textarea{$inputErr}' rows='4' name='".s($name)."'>".s($val)."</textarea>"
         .   $msgHtml
         . "</label>";
  }

  // Force single-line <input type="text"> for these columns (even if DB says TEXT)
  if (in_array($name, [
    // Page 2
    'dl_number','cid_number','address1','address2','city','state','zip',
    'employer','occupation',
    // Page 3
    'vtc_officer_name','attorney_name',
    // Page 5 details
    'alcohol_past_details','alcohol_current_details','drug_past_details','drug_current_details',
    // Page 6 details
    'counseling_details','depressed_details','suicide_last_attempt_when','psych_meds_list','psych_meds_physician',
  ], true)) {
    return "<label class='fld{$wrapErr}'>"
         .   "<span class='lbl'>".s($label)."</span>"
         .   "<input type='text' class='input{$inputErr}' name='".s($name)."' value='".s($val)."'>"
         .   $msgHtml
         . "</label>";
  }

  // --- Specific enums/selects ---------------------------------------------
  // Referral Source dropdown (Page 3)
  if ($name === 'referral_source') {
    $opts = ['Probation','Parole','Veterans Treatment Court','Self-Referral','Attorney','Judge/Court','Other'];
    $h = "<label class='fld{$wrapErr}'>"
       .   "<span class='lbl'>".s($label)."</span>"
       .   "<select class='input{$inputErr}' name='".s($name)."'>"
       .     "<option value=''>— select —</option>";
    foreach ($opts as $o) $h .= "<option".($val===$o?' selected':'').">".s($o)."</option>";
    $h .=   "</select>{$msgHtml}</label>";
    return $h;
  }

  // Page-2 enums (race/gender/education/employed)
  $lower = strtolower($label.' '.$name);
  if ($name === 'race' || preg_match('/\brace\b/i', $lower)) {
    $opts = ['American Indian/Alaska Native','Asian','Black/African American','Hispanic/Latino','Native Hawaiian/Pacific Islander','White','Multiracial','Other','Prefer not to say'];
    $h = "<label class='fld{$wrapErr}'><span class='lbl'>".s($label)."</span><select class='input{$inputErr}' name='".s($name)."'><option value=''>— select —</option>";
    foreach ($opts as $o) $h .= "<option".($val===$o?' selected':'').">".s($o)."</option>";
    return $h."</select>{$msgHtml}</label>";
  }
  if ($name === 'gender' || preg_match('/\b(sex|gender)\b/i', $lower)) {
    $opts = ['Male','Female','Other','Prefer not to say'];
    $h = "<label class='fld{$wrapErr}'><span class='lbl'>".s($label)."</span><select class='input{$inputErr}' name='".s($name)."'><option value=''>— select —</option>";
    foreach ($opts as $o) $h .= "<option".($val===$o?' selected':'').">".s($o)."</option>";
    return $h."</select>{$msgHtml}</label>";
  }
  if ($name === 'education_level' || preg_match('/\beducation\b/i', $lower)) {
    $opts = ['Less than HS','HS Diploma/GED','Some College','Associates','Bachelors','Masters','Doctorates','Other'];
    $h = "<label class='fld{$wrapErr}'><span class='lbl'>".s($label)."</span><select class='input{$inputErr}' name='".s($name)."'><option value=''>— select —</option>";
    foreach ($opts as $o) $h .= "<option".($val===$o?' selected':'').">".s($o)."</option>";
    return $h."</select>{$msgHtml}</label>";
  }
  if ($name === 'employed' || preg_match('/\bemployed\b/i', $lower)) {
    $opts = ['Yes','No'];
    $h = "<label class='fld{$wrapErr}'><span class='lbl'>".s($label)."</span><select class='input{$inputErr}' name='".s($name)."'><option value=''>— select —</option>";
    foreach ($opts as $o) $h .= "<option".($val===$o?' selected':'').">".s($o)."</option>";
    return $h."</select>{$msgHtml}</label>";
  }

  // --- Generic control heuristics -----------------------------------------
  // NOTE: these run AFTER overrides, so they won't affect forced fields.
  $isEmail = strpos($lower, 'email') !== false;
  $isPhone = strpos($lower, 'phone') !== false;
  $isDOB   = strpos($lower, 'date of birth') !== false;
  $isDate  = $isDOB || (strpos($lower,'date') !== false
            && strpos($lower,'created')===false
            && strpos($lower,'updated')===false
            && strpos($lower,'timestamp')===false);

  // age/number heuristic (but NEVER for the children names/ages textarea)
  $nameIsAgey = ($name === 'age' || preg_match('/(^|_)(age|years?)($|_)/i', $name));
  if ($name === 'names_and_ages_of_your_children_if_applicable') $nameIsAgey = false;
  $isInt = ($type === 'int' || $nameIsAgey);

  // Long free text if DB says (and not already forced)
  $isLong = (strlen($label) > 120) || in_array($type, ['text','mediumtext','longtext'], true);

  // Consent/agree checkboxes heuristic
  if (preg_match('/\bi agree\b/i', $label)) {
    return "<label class='agree{$wrapErr}'>"
         .   "<input type='checkbox' name='".s($name)."' value='1' ".($val==='1'?'checked':'').">"
         .   "<span>".s($label)."</span>{$msgHtml}"
         . "</label>";
  }

  if ($isDate)  return "<label class='fld{$wrapErr}'><span class='lbl'>".s($label)."</span><input type='date' class='input{$inputErr}' name='".s($name)."' value='".s($val)."'>{$msgHtml}</label>";
  if ($isEmail) return "<label class='fld{$wrapErr}'><span class='lbl'>".s($label)."</span><input type='email' class='input{$inputErr}' name='".s($name)."' value='".s($val)."'>{$msgHtml}</label>";
  if ($isPhone) return "<label class='fld{$wrapErr}'><span class='lbl'>".s($label)."</span><input type='tel' class='input{$inputErr}' name='".s($name)."' value='".s($val)."'>{$msgHtml}</label>";
  if ($isInt)   return "<label class='fld{$wrapErr}'><span class='lbl'>".s($label)."</span><input type='number' class='input{$inputErr}' name='".s($name)."' value='".s($val)."'>{$msgHtml}</label>";

  if ($isLong) {
    return "<label class='fld fld-long{$wrapErr}'>"
         .   "<span class='lbl'>".s($label)."</span>"
         .   "<textarea class='textarea{$inputErr}' rows='6' name='".s($name)."'>".s($val)."</textarea>"
         .   $msgHtml
         . "</label>";
  }

  // --- default: single-line text input ------------------------------------
  return "<label class='fld{$wrapErr}'>"
       .   "<span class='lbl'>".s($label)."</span>"
       .   "<input type='text' class='input{$inputErr}' name='".s($name)."' value='".s($val)."'>"
       .   $msgHtml
       . "</label>";
}




/* ────────────────────────────────────────────────────────────────────────── */
/* Legend (mini-key bubble) — per-page, only when scaled items exist          */
/* ────────────────────────────────────────────────────────────────────────── */
function legend_for_fields(array $fields): array {
  $flags = [
    'bdi' => false, 'bai' => false, 'hamd' => false, 'hama' => false,
    'pcl' => false, 'pcsi' => false, 'sassi' => false, 'fva' => false, 'fvod' => false
  ];
  foreach ($fields as $m) {
    $lab = strtolower(($m['comment'] ?: $m['name']) . ' ' . $m['name']);
    if (preg_match('/\b(bdi|beck depression|punishment feelings|sadness|pessimism|worth|fatigue|sleep|appetite)\b/i', $lab)) $flags['bdi'] = true;
    if (preg_match('/\b(bai|anxiety inventory|feeling hot|wobbliness|tingling|fear|hands trembling|sweating)\b/i', $lab)) $flags['bai'] = true;
    if (preg_match('/\b(ham[\- ]?d|depression rating|insomnia|agitation|somatic|suicide|retardation|work and interests)\b/i', $lab)) $flags['hamd'] = true;
    if (preg_match('/\b(ham[\- ]?a|ham a|tension|anxious|genitourinary|autonomic|cardiovascular|respiratory)\b/i', $lab)) $flags['hama'] = true;
    if (preg_match('/\b(pcl|ptsd checklist|military version)\b/i', $lab)) $flags['pcl'] = true;
    if (preg_match('/\b(tbi checklist|post\-concussive|dizzy|balance|coordination|sensitivity|fatigue|tired|irritability)\b/i', $lab)) $flags['pcsi'] = true;
    if (preg_match('/\b(sassi|true\/false)\b/i', $lab)) $flags['sassi'] = true;
    if (preg_match('/\b(fva|alcohol use|drinks)\b/i', $lab)) $flags['fva'] = true;
    if (preg_match('/\b(fvod|drug use|medications)\b/i', $lab)) $flags['fvod'] = true;
  }
  $out = [];
  if ($flags['bdi'])  $out[] = "<span class='legend-badge'>BDI</span> 0–3";
  if ($flags['bai'])  $out[] = "<span class='legend-badge'>BAI</span> Not at all → Severely";
  if ($flags['hamd']) $out[] = "<span class='legend-badge'>HAM-D</span> 0–4";
  if ($flags['hama']) $out[] = "<span class='legend-badge'>HAM-A</span> 0–4";
  if ($flags['pcl'])  $out[] = "<span class='legend-badge'>PCL</span> 1–5";
  if ($flags['pcsi']) $out[] = "<span class='legend-badge'>TBI</span> 0–4";
  if ($flags['fva'])  $out[] = "<span class='legend-badge'>Alcohol</span> Never → Very Often";
  if ($flags['fvod']) $out[] = "<span class='legend-badge'>Drugs</span> Never → Very Often";
  if ($flags['sassi'])$out[] = "<span class='legend-badge'>SASSI</span> True/False";
  return $out;
}

/* ────────────────────────────────────────────────────────────────────────── */
/* Insert row (final submit)                                                  */
/* ────────────────────────────────────────────────────────────────────────── */
function insert_row(mysqli $db, array $payload): array {
  $cols = [];
  $res = $db->query("DESCRIBE `evaluations_vtc`");
  while ($r = $res->fetch_assoc()) {
    $f = $r['Field'];
    if (in_array($f, ['id','created_at','updated_at'], true)) continue;
    $cols[$f] = true;
  }
  $data = [];
  foreach ($payload as $k => $v) if (isset($cols[$k])) $data[$k] = norm($v);
  if (!$data) return [false, 'No form fields matched table columns.'];

  $names = array_keys($data);
  $qs = implode(',', array_fill(0, count($names), '?'));
  $sql = "INSERT INTO `evaluations_vtc` (`created_at`,`updated_at`,`".implode('`,`',$names)."`) VALUES (NOW(),NOW(),$qs)";
  $stmt = $db->prepare($sql);
  if (!$stmt) return [false, "Prepare failed: ".$db->error];
  $types = str_repeat('s', count($names));
  $vals = array_values($data);
  $bind = [$types];
  foreach ($vals as $i => $v) $bind[] = &$vals[$i];
  call_user_func_array([$stmt,'bind_param'],$bind);
  if (!$stmt->execute()) { $e=$stmt->error; $stmt->close(); return [false,$e]; }
  $id = $stmt->insert_id; $stmt->close();
  return [true, $id];
}

/* ────────────────────────────────────────────────────────────────────────── */
/* Directions / guidelines text (as shown in your screenshots)                */
/* ────────────────────────────────────────────────────────────────────────── */
$GUIDELINES_HTML = '
  <p><strong>Thank you for taking the time to complete our online form.</strong> The first <strong>35%</strong> of your form are personal information and open-ended questions about your experiences. The remainder of the form is primarily multiple-choice questions. So breathe deep, and know that most of the form is multiple choice&mdash;and nothing like the first 35%.</p>

  <p>To ensure a smooth and efficient process, please adhere to the following rules:</p>
  <ul>
    <li><strong>Use the Save Draft Feature:</strong> If you need a break or wish to finish the form later, please utilize the <em>Save Draft</em> button located at the bottom of the page. This will allow you to return and complete your submission without losing your progress. When you return to pick up where you left off, click <em>Next</em> at the bottom of each page, and you will see the information in each page you previously completed.</li>
    <li><strong>Review Before Submitting:</strong> Before finalizing your submission, take a moment to review your entries for any mistakes or incomplete sections.</li>
    <li><strong>Accuracy is Essential:</strong> Please provide truthful and accurate information in all fields. This helps us serve you better and prevents potential issues with your submission. Your responses are <em>confidential and HIPAA protected</em>. In order to receive the best evaluation that allows Mental Health Treatment Court to help you succeed, respond to all questions and prompts honestly.</li>
    <li><strong>Complete All Fields:</strong> Ensure that all required fields are filled out. Incomplete submissions may delay processing and result in follow-up requests for additional information.</li>
    <li><strong>Take Your Time:</strong> We encourage you to read each question carefully and provide thoughtful responses. Rushing through the form may lead to errors or omissions.</li>
  </ul>

  <p>By following these guidelines, you contribute to a more efficient and effective submission process. Thank you for your cooperation!</p>

';

/* ────────────────────────────────────────────────────────────────────────── */
/* Controller: save / draft / next / submit                                   */
/* ────────────────────────────────────────────────────────────────────────── */
csrf_boot();
$step = get_step();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // What step are we on (trust hidden _step first, fall back to existing $step)?
  $curStep = (int)($_POST['_step'] ?? $step ?? 1);
  $action  = $_POST['_action'] ?? 'next';

  // Build a clean copy of what was posted
  $posted = $_POST;
  unset($posted['csrf_token'], $posted['_step'], $posted['_action']);

  // Step 2 glue: combine emergency first/last → single DB column
  if ($curStep === 2) {
    $ef = trim($_POST['__emerg_first'] ?? '');
    $el = trim($_POST['__emerg_last']  ?? '');
    if ($ef || $el) {
      $posted['emergency_contact_name'] = trim($ef.' '.$el);
    }
  }

  // Normalize checkboxes ("on" → "1")
  foreach ($posted as $k => $v) {
    if ($v === 'on') $posted[$k] = '1';
  }

  // Merge into session draft
  $_SESSION['vtc_form'] = array_merge($_SESSION['vtc_form'] ?? [], $posted);

  // -------- Save Draft: stay on the same page, no validation --------
  if ($action === 'draft') {
    header('Location: evaluations-vtc.php?step=' . $curStep);
    exit;
  }

  // -------- Validate current step before advancing/submitting --------
  $errs = [];
  switch ($curStep) {
    case 1:  $errs = vtc_validate_page1($_SESSION['vtc_form'] ?? []); break;
    case 2:  $errs = vtc_validate_page2($_SESSION['vtc_form'] ?? []); break;
    case 3:  $errs = vtc_validate_page3($_SESSION['vtc_form'] ?? []); break;
    case 4:  $errs = vtc_validate_page4($_SESSION['vtc_form'] ?? []); break;
    case 5:  $errs = vtc_validate_page5($_SESSION['vtc_form'] ?? []); break;
    case 6:  $errs = vtc_validate_page6($_SESSION['vtc_form'] ?? []); break;
    case 7:  $errs = vtc_validate_page7($_SESSION['vtc_form'] ?? []); break;
    case 8:  $errs = vtc_validate_page8($_SESSION['vtc_form'] ?? []); break;
    case 9:  $errs = vtc_validate_page9($_SESSION['vtc_form'] ?? []); break;
    case 10: $errs = vtc_validate_page10($_SESSION['vtc_form'] ?? []); break;
    case 11: $errs = vtc_validate_page11($_SESSION['vtc_form'] ?? []); break;
    case 12: $errs = vtc_validate_page12($_SESSION['vtc_form'] ?? []); break;
    case 13: $errs = vtc_validate_page13($_SESSION['vtc_form'] ?? []); break;
    case 14: $errs = vtc_validate_page14($_SESSION['vtc_form'] ?? []); break;
    case 15: $errs = vtc_validate_page15($_SESSION['vtc_form'] ?? []); break;
    case 16: $errs = vtc_validate_page16($_SESSION['vtc_form'] ?? []); break;
    case 17: $errs = vtc_validate_page17($_SESSION['vtc_form'] ?? []); break;
    case 18: $errs = vtc_validate_page18($_SESSION['vtc_form'] ?? []); break;
    case 19: $errs = vtc_validate_page19($_SESSION['vtc_form'] ?? []); break;
    case 20: $errs = vtc_validate_page20($_SESSION['vtc_form'] ?? []); break;
    case 21: $errs = vtc_validate_page21($_SESSION['vtc_form'] ?? []); break;
    case 22: $errs = vtc_validate_page22($_SESSION['vtc_form'] ?? []); break;
    case 23: $errs = vtc_validate_page23($_SESSION['vtc_form'] ?? []); break;
    case 24: $errs = vtc_validate_page24($_SESSION['vtc_form'] ?? []); break;
    case 25: $errs = vtc_validate_page25($_SESSION['vtc_form'] ?? []); break;
    case 26:
      // We'll add attestation/timestamps right before final submit below.
      $errs = vtc_validate_page26($_SESSION['vtc_form'] ?? []);
      break;
  }

  if (!empty($errs)) {
    $_SESSION['vtc_errors'] = $errs;
    header('Location: evaluations-vtc.php?step=' . $curStep . '#errors');
    exit;
  }

  // -------- Final submit (step 26 only) --------
  if ($curStep === 26 && in_array($action, ['submit','next'], true)) {
    // Attestation + default timestamp
    $attest = "Entering your name constitutes your Electronic Signature; "
            . "you verify the information in your evaluation is accurate and true "
            . "to the best of your knowledge. By clicking SUBMIT, you confirm the "
            . "above information is correct and true, with no misrepresentation or "
            . "false content in accordance with applicable law.";
    $_SESSION['vtc_form']['signature_attestation_text'] = $attest;

    // If empty, set today's date (use 'Y-m-d H:i:s' if your column is DATETIME)
    if (empty($_SESSION['vtc_form']['timestamp'])) {
      $_SESSION['vtc_form']['timestamp'] = date('Y-m-d');
    }

    // Meta fields
    if (!empty($_SESSION['user_id'])) {
      $_SESSION['vtc_form']['user_id']    = (string)$_SESSION['user_id'];
      $_SESSION['vtc_form']['created_by'] = $_SESSION['vtc_form']['created_by'] ?? (string)$_SESSION['user_id'];
      $_SESSION['vtc_form']['updated_by'] = (string)$_SESSION['user_id'];
    }
    $_SESSION['vtc_form']['last_updated'] = date('Y-m-d H:i:s');
    $_SESSION['vtc_form']['entry_status'] = 'submitted';

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($ip, ',') !== false) { $ip = trim(explode(',', $ip)[0]); }
    $_SESSION['vtc_form']['ip'] = $ip;

    if (empty($_SESSION['vtc_form']['key'])) {
      $_SESSION['vtc_form']['key'] = bin2hex(random_bytes(16));
    }

    // Final DB save
    [$ok, $payload] = insert_row($mysqli, $_SESSION['vtc_form'] ?? []);
    if (!$ok) {
      http_response_code(422);
      echo "<!doctype html><meta charset='utf-8'><pre>Save failed:\n".s($payload)."</pre>";
      exit;
    }

    // Prepare confirmation code, clear session state, and redirect to thank-you
    $conf = $_SESSION['vtc_form']['key'] ?? bin2hex(random_bytes(8));
    unset($_SESSION['vtc_form'], $_SESSION['vtc_errors']);
    if (function_exists('session_regenerate_id')) session_regenerate_id(true);

    header('Location: evaluations-vtc.php?done=1&conf=' . urlencode($conf));
    exit;
  }

  // -------- Otherwise, advance to the next step --------
  $next = min(26, $curStep + 1);
  header('Location: evaluations-vtc.php?step=' . $next);
  exit;
}


$FORM_ERRORS = $_SESSION['vtc_errors'] ?? [];
unset($_SESSION['vtc_errors']);


/* ────────────────────────────────────────────────────────────────────────── */
/* Render                                                                     */
/* ────────────────────────────────────────────────────────────────────────── */
$pageTitle = $PAGES[$step]['title'] ?? 'Page';
$fields    = $PAGE_FIELDS[$step] ?? [];
$percent   = (int)round(($step / 26) * 100);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>VTC Evaluation — NotesAO</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
  :root{
    --bg:#0f172a; --bg2:#111827;
    --brand:#2563eb; --brand2:#1d4ed8;
    --card:#ffffff; --ink:#0b1220; --muted:#5b667a; --line:#e5e7eb;
  }

  body{
    margin:0;
    background:#f6f7fb;
    color:var(--ink);
    font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial;
    font-size:16px;            /* PAI base size */
    line-height:1.5;
    -webkit-font-smoothing:antialiased;
    -moz-osx-font-smoothing:grayscale;
  }
  /* header + layout */
  .hdr{background:linear-gradient(180deg,var(--bg),var(--bg2));color:#fff;padding:18px 0 20px}
  .wrap{max-width:1060px;margin:0 auto;padding:0 16px}
  .hdr-row{display:flex;align-items:center;justify-content:space-between;gap:16px}
  .brand{               /* lets the left block take available space */
    display:flex;
    flex-direction:column;
    flex:1 1 auto;
    }
  .brand h1{
    margin:2px 0;
    font-weight:700;
    font-size:22px;            /* PAI scale */
    font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial;
  }
  .brand small{opacity:.8}

  /* logo chip: makes the white background intentional */
  .np-logo-chip{
    display:inline-flex;
    align-items:center;
    padding:.35rem .5rem;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:.5rem;
    box-shadow:0 3px 10px rgba(0,0,0,.08);
    margin-left:auto;
  }
  .np-logo-chip img{
    display:block;
    max-height:36px;
    width:auto;
    height:auto;
  }
  @media (max-width:576px){
    .np-logo-chip{padding:.25rem .4rem}
    .np-logo-chip img{max-height:30px}
  }

  /* Mini answer-key bubble: fixed under header, stays visible while scrolling */
  /* Mini answer-key bubble: starts under the instructions, then sticks */
  .mini-key{
    position: static;                 /* in the normal flow by default */
    margin: 10px auto 0;
    display: none;                    /* shown when .active */
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 999px;
    padding: .35rem .75rem;
    box-shadow: 0 8px 24px rgba(0,0,0,.10);
    font-size: .9rem;
    white-space: nowrap;
  }
  .mini-key.active{ display:flex; }
  .mini-key .bubble{ display:flex; gap:.5rem; align-items:center; }
  .mini-key .legend-badge{
    border-radius: .35rem; padding: .05rem .45rem; font-size: .8rem;
    border: 1px solid #d1d5db;
  }
  .mini-key .hint{ color:#6b7280; margin-left:.5rem; }

  /* when we scroll past the anchor, JS adds .is-fixed */
  .mini-key.is-fixed{
    position: fixed;
    left: 50%;
    transform: translateX(-50%);
    top: var(--miniKeyTop, 72px);     /* JS sets this to sit just below header */
    z-index: 1040;
    margin: 0;                        /* remove flow spacing while fixed */
  }

  @media (max-width:576px){
    .mini-key{ font-size:.85rem; padding:.30rem .6rem; }
    .mini-key .legend-badge{ font-size:.75rem; }
  }


  .legend-badge{font-size:.8rem;border:1px solid #d1d5db;border-radius:.5rem;padding:.1rem .5rem}

  /* cards / content */
  .card{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 6px 16px rgba(0,0,0,.05);padding:16px;margin:14px 0}
  .section-title{
    margin:0 0 10px 0;
    font-size:20px;            /* PAI section size */
    font-weight:700;
    font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial;
  }
  .progress{height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden}
  .progress i{display:block;height:100%;background:linear-gradient(90deg,#60a5fa,#2563eb)}
  .bar{display:flex;align-items:center;gap:12px}
  .bar .pct{min-width:44px;text-align:right;color:#334155;font-weight:700}
  .meta{color:#64748b;font-size:13.5px}

  /* --- Make our CSS grid columns ignore Bootstrap's .col-* flex widths --- */
    .grid{ display:grid; grid-template-columns:repeat(12,minmax(0,1fr)); gap:14px; }

    /* Neutralize Bootstrap flex/width when .col-* are used inside .grid */
    .grid > .col-12,
    .grid > .col-9,
    .grid > .col-8,
    .grid > .col-6,
    .grid > .col-5,
    .grid > .col-4,
    .grid > .col-3,
    .grid > .col-2{
    width:auto !important;
    flex:initial !important;
    min-width:0;               /* allow content to fill the grid cell */
    }

    /* Explicit grid spans (won’t be overridden by Bootstrap) */
    .grid > .col-12{ grid-column:span 12 !important; }
    .grid > .col-6 { grid-column:span 6  !important; }
    .grid > .col-4 { grid-column:span 4  !important; }  /* 1/3 row */

    /* Inputs fill their cells */
    .grid .fld, .names-row .fld{ width:100%; }
    .grid .input, .grid select, .grid textarea,
    .names-row .input{ width:100%; }

    /* Keep stacking on small screens (unchanged behavior) */
    @media (max-width:900px){
    .grid > .col-6,
    .grid > .col-4{ grid-column:span 12 !important; }
    }

  .col-12{grid-column:span 12} .col-6{grid-column:span 6} .col-4{grid-column:span 4}
  .fld{display:flex;flex-direction:column;gap:6px;padding:10px;border:1px solid var(--line);border-radius:10px;background:#fff}
  .lbl{
    font-weight:600;
    color:#1f2937;
    font-size:14.5px;          /* PAI label size */
  }
  .hint{font-weight:400;color:var(--muted)}
  .input,.textarea,select{
    width:100%;
    border:1px solid var(--line);
    border-radius:8px;
    padding:10px 12px;
    background:#fff;
    font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial;
    font-size:15px;            /* PAI field text */
  }
  .textarea{min-height:140px;resize:vertical}
  .agree{display:flex;gap:10px;align-items:flex-start;padding:14px;border:1px solid var(--line);border-radius:10px;background:#fafbff}
  .agree input{margin-top:3px;transform:scale(1.05)}
  .agree span { white-space: pre-wrap; }
  .sticky{position:sticky;bottom:0;z-index:8;background:#fff;border-top:1px solid var(--line);padding:14px 0;box-shadow:0 -6px 18px rgba(0,0,0,.04)}
  /* Sticky footer controls: centered, comfy size, no overflow */
    .sticky{
    position: sticky;
    bottom: 0;
    z-index: 8;
    background: #fff;
    border-top: 1px solid var(--line);
    padding: 14px 0;
    box-shadow: 0 -6px 18px rgba(0,0,0,.04);
    }

    .sticky .row{
    display: flex;
    justify-content: center;   /* center horizontally */
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;           /* wrap on small screens */
    width: 100%;
    }

    .sticky .btn{
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;            /* don’t grow */
    width: auto;               /* size to content */
    min-width: 180px;          /* comfy width */
    padding: 12px 20px;        /* taller button */
    border-radius: 10px;
    }

    .sticky .meta{
    text-align: center;        /* keep the helper text centered too */
    margin-top: 6px;
    }

    /* Optional: on very small screens, let buttons expand a bit */
    @media (max-width: 480px){
    .sticky .btn{ min-width: 140px; padding: 10px 16px; }
    }


  .btn{
    appearance:none;
    border:1px solid var(--brand2);
    background:#1d4ed8;color:#fff;
    border-radius:8px;
    padding:10px 14px;
    font-weight:700;
    font-size:15px;            /* PAI button text */
    cursor:pointer;
  }
  .btn.secondary{background:#fff;color:#1d4ed8}
  .btn[disabled]{opacity:.6;cursor:not-allowed}
  /* Page-1: first/last name side-by-side taking full width */
  .names-row{
    display:grid;
    grid-template-columns: 1fr 1fr; /* two equal halves */
    gap:14px;
  }
  @media (max-width:900px){
    .names-row{ grid-template-columns: 1fr; } /* stack on small screens, like PAI */
  }

  @media (max-width:900px){ .col-6,.col-4{grid-column:span 12} }

  /* Invalid field highlighting */
  .fld.error, .agree.error { border-color:#ef4444; background:#fff7f7; }
  .input.is-invalid, .textarea.is-invalid, select.is-invalid { border-color:#ef4444; }
  .error-msg { color:#b91c1c; font-size:12px; margin-top:4px; font-weight:600; }

  /* PAI-style subheading under H1 */
  .brand .np-sub{ font-size:13.5px; opacity:.85; }

  /* Consent paragraph sizing to match PAI */
  .agree span{ white-space:pre-wrap; font-size:14.5px; }

  /* extra column sizes for 12-col grid */
  .col-2{grid-column:span 2}
  .col-3{grid-column:span 3}
  .col-5{grid-column:span 5}
  .col-8{grid-column:span 8}
  .col-9{grid-column:span 9}

  /* hide/show for conditional employer/occupation */
  .hidden{display:none}

  .row-title{
    margin: 14px 0 8px;
    font-weight: 700;
    font-size: 14.5px;
    color: #475569;
  }
  /* ===== Inventory: radio buttons behave like buttons ===== */
  .likert { border: 1px solid var(--line); border-radius: 12px; padding: 10px; background: #fff; }
  .likert legend { font-weight: 700; font-size: 14px; margin: 0 0 8px 0; }

  /* One option (label wraps input so the whole row is clickable) */
  .bdi-opt { display: block; margin: 8px 0 0; cursor: pointer; }
  .bdi-opt:first-of-type { margin-top: 0; }

  .bdi-opt input {
    position: absolute; /* keep it focusable for a11y but not visible */
    opacity: 0;
    width: 1px; height: 1px;
    pointer-events: none;
  }

  .bdi-btn {
    display: flex; gap: 10px; align-items: flex-start;
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 10px 12px;
    background: #fff;
    transition: border-color .15s, box-shadow .15s, background .15s;
  }

  .bdi-num {
    flex: 0 0 28px;
    width: 28px; height: 28px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 8px;
    background: #f3f4f6;
    font-weight: 700;
  }

  .bdi-text { line-height: 1.35; }

  .bdi-opt:hover .bdi-btn { border-color: #94a3b8; box-shadow: 0 2px 10px rgba(0,0,0,.06); }
  .bdi-opt input:focus + .bdi-btn { outline: 2px solid var(--brand); outline-offset: 2px; }

  .bdi-opt input:checked + .bdi-btn {
    border-color: var(--brand);
    background: #eff6ff;
    box-shadow: 0 0 0 2px rgba(37,99,235,.20) inset;
  }
  .bdi-opt input:checked + .bdi-btn .bdi-num { background: var(--brand); color: #fff; }

  /* keep two-column grid you already use on BDI */

  </style>
</head>
<body>

<?php if (file_exists(__DIR__ . '/includes/favicon-switch.php')) { include __DIR__ . '/includes/favicon-switch.php'; } ?>

<?php
  // Allow override, else use standard asset
  $logo_url = $logo_url ?? "ffllogo.png";
?>

<header class="hdr">
  <div class="wrap hdr-row">
    <div class="brand">
      <h1>Free for Life Group — Veteran’s Treatment Court Evaluation</h1>
      <small class="np-sub">NotesAO Form</small>
    </div>
    <div class="np-logo-chip" aria-hidden="true">
      <img src="<?php echo s($logo_url); ?>" alt="NotesAO">
    </div>
  </div>
</header>


<main class="wrap">
  <!-- progress -->
  <div class="card">
    <div class="bar">
      <div class="progress" style="flex:1"><i style="width:<?= $percent ?>%"></i></div>
      <div class="pct"><?= $percent ?>%</div>
      <div class="meta">Page <?= $step ?>/26</div>
    </div>
  </div>

  <!-- directions -->
  <section class="card directions">
    <h2 class="section-title"><?= s($pageTitle) ?></h2>
    <div class="meta"><?= $GUIDELINES_HTML ?></div>

    <?php
    // Anchor used to decide when the chip should stick
    if (in_array((int)$step, [16,17,18,19,20,21,22,23,24,25], true)) {
      echo '<div id="miniKeyAnchor"></div>';
    }

    // === Mini key: BDI custom on 16, default on other scale pages ===
    $scaleSteps = [16,17,18,19,20,21,22,23,24,25];
    $cur = (int)$step;

    if ($cur === 16) {
      ?>
      <div class="mini-key active">
        <div class="bubble">
          <strong class="me-1">Answer Key (BDI):</strong>
          0 — Not at all <span class="sep">&nbsp;&middot;&nbsp;</span>
          1 — Sometimes <span class="sep">&nbsp;&middot;&nbsp;</span>
          2 — All the time <span class="sep">&nbsp;&middot;&nbsp;</span>
          3 — Yes, to the extreme
          
        </div>
      </div>
    <?php
    } 
    if ($cur === 17) { ?>
      <div class="mini-key active">
        <div class="bubble">
          <strong class="me-1">Answer Key (BAI):</strong>
          Not At All
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          Mildly
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          Moderately
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          Severely

        </div>
      </div>
    <?php
    }
    if ($cur === 19) { ?>
      <div class="mini-key active">
        <div class="bubble">
          <strong class="me-1">Answer Key (HAM-D):</strong>
          0 — Absent
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          1 — Occasional
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          2 — Frequent
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          3 — Constant
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          4 — Always

        </div>
      </div>
    <?php

    }
    if ($cur === 20) { ?>
      <div class="mini-key active">
        <div class="bubble">
          <strong class="me-1">Answer Key (HAM-A):</strong>
          0 — None
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          1 — Mild
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          2 — Moderate
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          3 — Severe
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          4 — Severe, Grossly Disabling
        </div>
      </div>
    <?php

    }
    if ($cur === 21) { ?>
      <div class="mini-key active">
        <div class="bubble">
          <strong class="me-1">Answer Key (PCL-M):</strong>
          1 — Not at all
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          2 — A little bit
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          3 — Moderately
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          4 — Quite a bit
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          5 — Extremely
        </div>
      </div>
    <?php

    }
    if ($cur === 22) { ?>
      <div class="mini-key active">
        <div class="bubble">
          <strong class="me-1">Answer Key (TBI):</strong>
          0 — None
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          1 — Mild
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          2 — Moderate
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          3 — Severe
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          4 — Very Severe
        </div>
      </div>
    <?php

    }
    if ($cur === 24) { ?>
      <div class="mini-key active">
        <div class="bubble">
          <strong class="me-1">Answer Key (Alcohol Use):</strong>
          Never
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          Once or Twice
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          Several Times
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          Repeatedly
        </div>
      </div>
    <?php

    }
    if ($cur === 25) { ?>
      <div class="mini-key active">
        <div class="bubble">
          <strong class="me-1">Answer Key (Drug Use):</strong>
          Never
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          Once or Twice
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          Several Times
          <span class="sep">&nbsp;&middot;&nbsp;</span>
          Repeatedly
        </div>
      </div>
    <?php

    }
    ?>
  </section>



  <!-- form -->
  <form method="post" action="?step=<?php echo $step; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo s($_SESSION['csrf']); ?>">
    <input type="hidden" name="_step" value="<?php echo $step; ?>">

    <?php
      if (empty($fields)) {
        echo "<div class='card'><p><em>No fields matched for this page. If this is unexpected, verify the live schema. Use <code>?debug=unassigned</code>.</em></p></div>";
      } else {
        $isPage1 = ((int)$step === 1);

        if ($isPage1) {
          // Index current page fields by name for quick lookup
          $byName = [];
          foreach ($fields as $m) { $byName[$m['name']] = $m; }

          // Page 1: First & Last (row 1), Email (row 2)
          $first = $last = $email = null;
          foreach ($fields as $m) {
            $lab = strtolower($m['comment'] ?: $m['name']);
            if (!$first && strpos($lab,'first name') !== false) { $first = $m; continue; }
            if (!$last  && strpos($lab,'last name')  !== false) { $last  = $m; continue; }
            if (!$email && strpos($lab,'email')      !== false) { $email = $m; continue; }
          }

          echo "<section class='card'><h3 class='section-title'>Introduction</h3>";

          // First + Last on one line
          echo "<div class='names-row'>";
          if ($first) echo "<div>".input_for($first)."</div>";
          if ($last)  echo "<div>".input_for($last)."</div>";
          echo "</div>";

          // Email full width
          if ($email) {
            echo "<div class='grid' style='margin-top:14px'><div class='col-12'>".input_for($email)."</div></div>";
          }

          echo "</section>";

          // ----- Consents (required) -----
          $consentNames = defined('VTC_P1_REQUIRED_CHECKS') ? VTC_P1_REQUIRED_CHECKS : [];

          if (!function_exists('vtc_agree_panel')) {
            function vtc_agree_panel(array $meta, string $title, string $body): string {
              global $FORM_ERRORS;
              $name    = $meta['name'];
              $val     = $_SESSION['vtc_form'][$name] ?? '';
              $hasErr  = isset($FORM_ERRORS[$name]);
              $wrapErr = $hasErr ? ' error' : '';
              $msgHtml = $hasErr ? "<div class='error-msg'>".s($FORM_ERRORS[$name])."</div>" : "";

              // You can swap <details> for a plain <div> if you don’t want collapsible blocks.
              return "
              <article class='consent{$wrapErr}' style='padding:12px 14px;border:1px solid var(--line,#e5e7eb);border-radius:10px;background:#fff'>
                <details open>
                  <summary style='font-weight:600;cursor:pointer;margin-bottom:8px'>".s($title)."</summary>
                  <div class='consent-body' style='color:#4b5563;line-height:1.5;margin-bottom:10px'>".nl2br(s($body))."</div>
                </details>
                <label class='agree' style='display:flex;gap:.6rem;align-items:center;margin-top:6px'>
                  <input type='checkbox' name='".s($name)."' value='1' ".($val==='1'?'checked':'').">
                  <span>I Agree <strong>*</strong></span>
                </label>
                {$msgHtml}
              </article>";
            }
          }

          if (!empty($consentNames)) {
            echo "<section class='card'><h3 class='section-title'>Consents</h3><div class='stack' style='display:grid;gap:10px'>";

            // Bring the array of bodies into scope
            global $VTC_P1_CONSENT_CONTENT;

            // When we iterate, we’ll first try to match by name; if not found, use position.
            foreach ($consentNames as $i => $n) {
              $meta = $byName[$n] ?? ['name' => $n]; // $byName built earlier from $fields
              // By-name match or numeric fallback (0..5)
              $content = $VTC_P1_CONSENT_CONTENT[$n] ?? ($VTC_P1_CONSENT_CONTENT[$i] ?? null);
              $title = $content['title'] ?? ($meta['comment'] ?? 'Consent');
              $body  = $content['body']  ?? '';
              echo vtc_agree_panel($meta, $title, $body);
            }

            echo "</div></section>";
          }

        } else {
            /* ==================== PAGE 2 ==================== */
            if ($step === 2) {
            // helper
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            echo "<section class='card'><h3 class='section-title'>Intake Information & Personal History</h3>";

            /* Identification */
            echo "<div class='row-title'>Identification</div>";
            echo "<div class='names-row'>"; // two equal halves
            if ($m = $by('dl_number'))  echo "<div>".input_for($m)."</div>";
            if ($m = $by('cid_number')) echo "<div>".input_for($m)."</div>";
            echo "</div>";

            /* Address */
            echo "<div class='row-title'>Home Address</div>";
            echo "<div class='grid'>";
            if ($m = $by('address1')) echo "<div class='col-12'>".input_for($m)."</div>";
            if ($m = $by('address2')) echo "<div class='col-12'>".input_for($m)."</div>";
            // City / State / Zip — equal thirds
            if ($m = $by('city'))  echo "<div class='col-4'>".input_for($m)."</div>";
            if ($m = $by('state')) echo "<div class='col-4'>".input_for($m)."</div>";
            if ($m = $by('zip'))   echo "<div class='col-4'>".input_for($m)."</div>";
            echo "</div>";

            /* Phone */
            echo "<div class='grid'>";
            if ($m = $by('phone_primary')) echo "<div class='col-12'>".input_for($m)."</div>";
            echo "</div>";

            /* Demographics */
            echo "<div class='row-title'>Demographics</div>";
            echo "<div class='grid'>";
            // Date of Birth / Age / Ethnicity — equal thirds
            if ($m = $by('dob'))  echo "<div class='col-4'>".input_for($m)."</div>";
            if ($m = $by('age'))  echo "<div class='col-4'>".input_for($m)."</div>";
            if ($m = $by('race')) echo "<div class='col-4'>".input_for($m)."</div>";
            echo "</div>";

            echo "<div class='grid'>";
            // Gender / Education / Employed — equal thirds
            if ($m = $by('gender'))          echo "<div class='col-4'>".input_for($m)."</div>";
            if ($m = $by('education_level')) echo "<div class='col-4'>".input_for($m)."</div>";
            if ($m = $by('employed'))        echo "<div class='col-4'>".input_for($m)."</div>";
            echo "</div>";

            /* Employment details (only if employed = Yes) */
            $empVal = $_SESSION['vtc_form']['employed'] ?? '';
            $empHidden = ($empVal === 'Yes') ? '' : ' hidden';
            echo "<div class='row-title' id='empTitle' ".($empHidden?'style="display:none"':'').">Employment Details</div>";
            echo "<div id='empWrap' class='names-row{$empHidden}'>"; // 50/50 like DL/CID and First/Last
            if ($m = $by('employer'))   echo "<div>".input_for($m)."</div>";
            if ($m = $by('occupation')) echo "<div>".input_for($m)."</div>";
            echo "</div>";

            /* Military */
            echo "<div class='row-title'>Military Service</div>";
            echo "<div class='grid'>";
            if ($m = $by('military_service')) echo "<div class='col-12'>".input_for($m)."</div>";
            echo "</div>";

            /* Emergency Contact */
            echo "<div class='row-title'>Emergency Contact</div>";
            // Split First/Last UI -> stores into emergency_contact_name (hidden) as before
            $ecFull  = $_SESSION['vtc_form']['emergency_contact_name'] ?? '';
            $ecParts = preg_split('/\s+/', trim($ecFull), 2);
            $ecFirst = $ecParts[0] ?? '';
            $ecLast  = $ecParts[1] ?? '';
            $efErr = isset($FORM_ERRORS['__emerg_first']) ? ' error' : '';
            $elErr = isset($FORM_ERRORS['__emerg_last'])  ? ' error' : '';
            $efMsg = isset($FORM_ERRORS['__emerg_first']) ? "<div class='error-msg'>".s($FORM_ERRORS['__emerg_first'])."</div>" : "";
            $elMsg = isset($FORM_ERRORS['__emerg_last'])  ? "<div class='error-msg'>".s($FORM_ERRORS['__emerg_last'])."</div>" : "";

            echo "<div class='names-row'>";
            echo "<div><label class='fld{$efErr}'><span class='lbl'>Emergency Contact — First Name *</span><input type='text' class='input' name='__emerg_first' value='".s($ecFirst)."'>".$efMsg."</label></div>";
            echo "<div><label class='fld{$elErr}'><span class='lbl'>Emergency Contact — Last Name *</span><input type='text' class='input' name='__emerg_last' value='".s($ecLast)."'>".$elMsg."</label></div>";
            echo "</div>";
            echo "<input type='hidden' name='emergency_contact_name' value='".s($ecFull)."'>";

            echo "<div class='grid'>";
            if ($m = $by('emergency_contact_phone')) echo "<div class='col-12'>".input_for($m)."</div>";
            echo "</div>";

            echo "</section>";

            }

            /* ==================== PAGE 3 ==================== */
            if ($step === 3) {
                $by = function(string $name) use ($fields) {
                    foreach ($fields as $m) if ($m['name'] === $name) return $m;
                    return null;
                };

                echo "<section class='card'><h3 class='section-title'>Referral Information</h3>";

                // Referral Source (full width)
                echo "<div class='grid'>";
                if ($m = $by('referral_source')) echo "<div class='col-12'>".input_for($m)."</div>";
                echo "</div>";

                // VTC Court Officer (required)
                echo "<div class='grid'>";
                if ($m = $by('vtc_officer_name')) echo "<div class='col-12'>".input_for($m)."</div>";
                echo "</div>";

                // Attorney (Name + Email) — 50/50 row
                echo "<div class='names-row'>";
                if ($m = $by('attorney_name'))  echo "<div>".input_for($m)."</div>";
                if ($m = $by('attorney_email')) echo "<div>".input_for($m)."</div>";
                echo "</div>";

                echo "</section>";
            }

            /* ==================== PAGE 4 ==================== */
            if ($step === 4) {
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            echo "<section class='card'><h3 class='section-title'>Marital & Family Information</h3>";

            // Marital & Living
            if ($m = $by('marital_status')) {
                echo "<div class='grid'><div class='col-12'>".
                vtc_select($m, ['Single','Married','Separated','Divorced','Widowed','Domestic Partnership','Other'])
                ."</div></div>";
            }
            if ($m = $by('living_situation')) {
                echo "<div class='grid'><div class='col-12'>".
                vtc_select($m, [
                    'Alone','With Partner/Spouse','With Parents','With Relatives',
                    'With Friends/Roommates','Transitional Housing','Shelter','Homeless','Other'
                ])."</div></div>";
            }

            // Do you have children?
            echo "<div class='grid'>";
            if ($m = $by('has_children')) echo "<div class='col-12'>".vtc_yesno($m)."</div>";
            echo "</div>";

            $hasKids = $_SESSION['vtc_form']['has_children'] ?? '';
            $kidsHidden = ($hasKids === 'Yes') ? '' : ' hidden';
            echo "<div id='kidsBlock' class='{$kidsHidden}'>";

            // If YES…
            echo "<div class='grid'>";
            if ($m = $by('children_live_with_you')) echo "<div class='col-12'>".vtc_yesno($m)."</div>";
            echo "</div>";

            // Names & Ages (textarea)
            echo "<div class='grid'>";
            if ($m = $by('names_and_ages_of_your_children_if_applicable')) {
                echo "<div class='col-12'>".input_for($m)."</div>";
            }
            echo "</div>";


            echo "</div>"; // kidsBlock

            // Abuse/Neglect (always shown)
            echo "<div class='grid'>";
            foreach ([
                'child_abused_physically',
                'child_abused_sexually',
                'child_abused_emotionally',
                'child_neglected'
            ] as $n) { if ($m = $by($n)) echo "<div class='col-6'>".vtc_yesno($m)."</div>"; }
            echo "</div>";

            $abuseKeys = [
                'child_abused_physically',
                'child_abused_sexually',
                'child_abused_emotionally',
                'child_neglected'
            ];
            $abuseAny = false;
            foreach ($abuseKeys as $k) {
                if (($_SESSION['vtc_form'][$k] ?? '') === 'Yes') { $abuseAny = true; break; }
            }
            $cpsHidden = $abuseAny ? '' : ' hidden';
            echo "<div id='cpsBlock' class='{$cpsHidden}'>";
            // CPS followups (always shown)
            echo   "<div class='grid'>";
            if ($m = $by('cps_notified'))    echo "<div class='col-12'>".vtc_yesno($m)."</div>";
            if ($m = $by('cps_care_or_supervision')) echo "<div class='col-12'>".vtc_yesno($m)."</div>";
            echo   "</div>";
            echo "</div>";

            // Discipline description
            echo "<div class='grid'>";
            if ($m = $by('describe_how_you_discipline_your_children_please_provide_example')) echo "<div class='col-12'>".input_for($m)."</div>";
            echo "</div>";

            echo "</section>";
            }

            /* ==================== PAGE 5 ==================== */
            if ($step === 5) {
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            echo "<section class='card'><h3 class='section-title'>Drug & Alcohol Information</h3>";

            // Helper for each Yes/No + conditional detail pair
            $pairs = [
                ['alcohol_past_use',    'alcohol_past_details'],
                ['alcohol_current_use', 'alcohol_current_details'],
                ['drug_past_use',       'drug_past_details'],
                ['drug_current_use',    'drug_current_details'],
            ];

            foreach ($pairs as [$yn, $detail]) {
                // Yes/No row
                if ($m = $by($yn)) echo "<div class='grid'><div class='col-12'>".vtc_yesno($m)."</div></div>";

                // Detail row — hidden unless user chose Yes
                $show = (($_SESSION['vtc_form'][$yn] ?? '') === 'Yes');
                $cls  = $show ? '' : ' hidden';
                echo "<div id='{$detail}_wrap' class='{$cls}'><div class='grid'>";
                if ($m = $by($detail)) echo "<div class='col-12'>".input_for($m)."</div>";
                echo "</div></div>";
            }

            echo "</section>";
            }

            /* ==================== PAGE 6 ==================== */
            if ($step === 6) {
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            echo "<section class='card'><h3 class='section-title'>Counseling History</h3>";

            // Yes/No + dependent detail fields
            $rules = [
                ['counseling_history',        ['counseling_details']],
                ['depressed_now',             ['depressed_details']],
                ['suicide_attempt_history',   ['suicide_last_attempt_when']],
                ['psych_meds_current',        ['psych_meds_list','psych_meds_physician']],
                ['head_trauma_history',       ['head_trauma_details']],
            ];

            foreach ($rules as [$yn, $details]) {
                if ($m = $by($yn)) echo "<div class='grid'><div class='col-12'>".vtc_yesno($m)."</div></div>";
                $show = (($_SESSION['vtc_form'][$yn] ?? '') === 'Yes');
                $cls  = $show ? '' : ' hidden';
                echo "<div id='{$yn}_details' class='{$cls}'><div class='grid'>";
                foreach ($details as $d) if ($m = $by($d)) echo "<div class='col-12'>".input_for($m)."</div>";
                echo "</div></div>";
            }

            // Standalone yes/no items
            echo "<div class='grid'>";
            foreach (['weapon_possession_history','sexual_abuse_history','childhood_abuse_history'] as $single) {
                if ($m = $by($single)) echo "<div class='col-12'>".vtc_yesno($m)."</div>";
            }
            echo "</div>";

            echo "</section>";
            }

            /* ==================== PAGE 7 ==================== */
            if ($step === 7) {
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            echo "<section class='card'><h3 class='section-title'>Childhood to Adult</h3>";

            $names = [
                'upbringing_where_grow_up',
                'upbringing_who_raised_you',
                'upbringing_raised_by_both_parents',
                'upbringing_parents_caretakers_names',
                'upbringing_divorce_explain',
                'upbringing_caretaker_addiction',
                'upbringing_caretaker_mental_health',
                'upbringing_finances_growing_up',
                'upbringing_traumatic_experiences',
                'upbringing_school_experience',
                'upbringing_caretakers_help_schoolwork',
                'upbringing_anything_else_for_court',
            ];

            echo "<div class='grid'>";
            foreach ($names as $n) {
                if ($m = $by($n)) {
                // TEXT columns will already render as <textarea> via input_for() heuristics
                echo "<div class='col-12'>".input_for($m)."</div>";
                }
            }
            echo "</div>";

            echo "</section>";
            }
            /* ==================== PAGE 8 ==================== */
            if ($step === 8) {
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            echo "<section class='card'><h3 class='section-title'>Legal History</h3>";

            $names = [
                'legal_first_arrest_details',
                'legal_multiple_arrests_details',
                'legal_prevention_plan',
                'legal_hopes_from_vtc',
            ];

            echo "<div class='grid'>";
            foreach ($names as $n) if ($m = $by($n)) echo "<div class='col-12'>".input_for($m)."</div>";
            echo "</div>";

            echo "</section>";
            }
            /* ==================== PAGE 9 ==================== */
            if ($step === 9) {
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            echo "<section class='card'><h3 class='section-title'>Military Experience</h3>";

            $names = [
                'military_join_details',
                'military_trauma_description',
                'military_impact_beliefs',
                'military_grief_counseling',
                'military_culture_mh_attitudes',
            ];

            echo "<div class='grid'>";
            foreach ($names as $n) if ($m = $by($n)) echo "<div class='col-12'>".input_for($m)."</div>";
            echo "</div>";

            echo "</section>";
            }

            /* ==================== PAGE 10 ==================== */
            if ($step === 10) {
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            echo "<section class='card'><h3 class='section-title'>Medications</h3>";

            foreach ([
                'medications_prescribed_history',
                'medications_first_prescribed_details',
                'medications_current_and_desired',
            ] as $n) {
                if ($m = $by($n)) echo "<div class='grid'><div class='col-12'>".input_for($m)."</div></div>";
            }

            echo "</section>";
            }
            /* ==================== PAGE 11 ==================== */
            if ($step === 11) {
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            echo "<section class='card'><h3 class='section-title'>Addiction & Hope</h3>";

            foreach ([
                'addiction_impact_on_life',
                'addiction_overcome_attempts',
                'sobriety_future_impact',
                'hope_for_future_narrative',
            ] as $n) {
                if ($m = $by($n)) echo "<div class='grid'><div class='col-12'>".input_for($m)."</div></div>";
            }

            echo "</section>";
            }
            /* ==================== PAGE 12 ==================== */
            if ($step === 12) {
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            echo "<section class='card'><h3 class='section-title'>Beliefs & Experiences</h3>";

            foreach ([
                'beliefs_impact_on_life',
                'beliefs_extraordinary_experiences',
                'beliefs_shape_future',
            ] as $n) {
                if ($m = $by($n)) echo "<div class='grid'><div class='col-12'>".input_for($m)."</div></div>";
            }

            echo "</section>";
            }
            /* ==================== PAGE 13 ==================== */
            if ($step === 13) {
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            // Fallback checkbox renderer if not already defined
            if (!function_exists('vtc_agree_box')) {
                function vtc_agree_box(array $meta): string {
                global $FORM_ERRORS;
                $name  = $meta['name'];
                $label = vtc_label($meta);
                $val   = $_SESSION['vtc_form'][$name] ?? '';
                $hasErr = isset($FORM_ERRORS[$name]);
                $wrapErr = $hasErr ? ' error' : '';
                $msgHtml = $hasErr ? "<div class='error-msg'>".s($FORM_ERRORS[$name])."</div>" : "";
                return "<label class='agree{$wrapErr}'><input type='checkbox' name='".s($name)."' value='1' ".($val==='1'?'checked':'').">
                        <span>".s($label)." <strong>*</strong></span>{$msgHtml}</label>";
                }
            }

            // Groupings (purely for headings in the UI)
            $roi = [
                'roi_purpose_ack','roi_limited_info_ack','roi_confidentiality_ack','roi_voluntary_ack'
            ];
            $program = [
                'rule_dismissal_ack','rule_eval_components_ack','rule_complete_process_ack','rule_focus_webcam_ack',
                'rule_quiet_room_ack','rule_interruption_no_credit_ack','rule_substance_violation_ack','rule_restroom_5min_ack',
                'rule_payment_due_ack','rule_arrive_on_time_ack','rule_update_contact_ack','rule_call_if_absent_ack',
                'rule_no_intoxication_ack','rule_no_abusive_language_ack','rule_notify_emergencies_ack',
                'rule_program_goal_ack','rule_no_violence_ack'
            ];
            $takingResp = [
                'free_for_life_group_requires_me_to_disable_any_devices_that_coul'
            ];

            echo "<section class='card'><h3 class='section-title'>Consent for Disclosure of Information</h3><div class='grid'>";
            foreach ($roi as $n) if ($m = $by($n)) echo "<div class='col-12'>".vtc_agree_box($m)."</div>";
            echo "</div></section>";

            echo "<section class='card'><h3 class='section-title'>Free for Life Program Agreement</h3><div class='grid'>";
            foreach ($program as $n) if ($m = $by($n)) echo "<div class='col-12'>".vtc_agree_box($m)."</div>";
            echo "</div></section>";

            echo "<section class='card'><h3 class='section-title'>Taking Responsibility</h3><div class='grid'>";
            foreach ($takingResp as $n) if ($m = $by($n)) echo "<div class='col-12'>".vtc_agree_box($m)."</div>";
            echo "</div></section>";
            }
            /* ==================== PAGE 14 ==================== */
            if ($step === 14) {
            $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
            };

            echo "<section class='card'><h3 class='section-title'>Individualized Plan</h3>";

            if ($m = $by('legal_offense_summary')) {
                // input_for() will render a textarea for TEXT columns
                echo "<div class='grid'><div class='col-12'>".input_for($m)."</div></div>";
            }

            echo "</section>";
            }
            /* ==================== PAGE 15 ==================== */
            if ($step === 15) {
              $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
              };

              // Safe helper if not defined already
              if (!function_exists('vtc_agree_box')) {
                function vtc_agree_box(array $meta): string {
                  global $FORM_ERRORS;
                  $name  = $meta['name'];
                  $label = vtc_label($meta);
                  $val   = $_SESSION['vtc_form'][$name] ?? '';
                  $hasErr = isset($FORM_ERRORS[$name]);
                  $wrapErr = $hasErr ? ' error' : '';
                  $msgHtml = $hasErr ? "<div class='error-msg'>".s($FORM_ERRORS[$name])."</div>" : "";
                  return "<label class='agree{$wrapErr}'><input type='checkbox' name='".s($name)."' value='1' ".($val==='1'?'checked':'').">
                          <span>".s($label)." <strong>*</strong></span>{$msgHtml}</label>";
                }
              }

              echo "<section class='card'><h3 class='section-title'>VTC Evaluation Policy for Clients & Termination Policy</h3><div class='grid'>";
              foreach ([
                'policy_received_ack',
                'rights_provider_obligations_ack',
                'rights_client_terminate_ack',
                'rights_provider_terminate_conditions',
                'rights_termination_policy_scope_ack',
                'rights_termination_circumstances_copy_ack',
                'final_attestation_ack',
              ] as $n) if ($m = $by($n)) echo "<div class='col-12'>".vtc_agree_box($m)."</div>";
              echo "</div></section>";

              // Legal name (side-by-side, reusing .names-row from page 1 CSS)
              echo "<section class='card'><h3 class='section-title'>Legal Name</h3>";
              echo "<div class='names-row'>";
              if ($m = $by('legal_first_name')) echo "<div>".input_for($m)."</div>";
              if ($m = $by('legal_last_name'))  echo "<div>".input_for($m)."</div>";
              echo "</div></section>";
            }
            /* ==================== PAGE 16 — Inventories: BDI ==================== */
            if ($step === 16) {

              // [field => [Title, choices[value] = text]]
              $BDI = [

                'punishment_feelings' => ['Punishment Feelings', [
                  '0' => "I don't feel I am being punished.",
                  '1' => "I feel I may be punished.",
                  '2' => "I expect to be punished.",
                  '3' => "I feel I am being punished.",
                ]],

                'sadness' => ['Sadness', [
                  '0' => "I do not feel sad.",
                  '1' => "I feel sad much of the time.",
                  '2' => "I am sad all the time.",
                  '3' => "I am so sad or unhappy that I can't stand it.",
                ]],

                'pessimism' => ['Pessimism', [
                  '0' => "I am not discouraged about my future.",
                  '1' => "I feel more discouraged about my future than I used to be.",
                  '2' => "I do not expect things to work out for me.",
                  '3' => "I feel my future is hopeless and will only get worse.",
                ]],

                'self_dislike' => ['Self-Dislike', [
                  '0' => "I feel the same about myself as ever.",
                  '1' => "I have lost confidence in myself.",
                  '2' => "I am disappointed in myself.",
                  '3' => "I dislike myself.",
                ]],

                'past_failure' => ['Past Failure', [
                  '0' => "I do not feel like a failure.",
                  '1' => "I have failed more than I should have.",
                  '2' => "As I look back, I see a lot of failures.",
                  '3' => "I feel I am a total failure as a person.",
                ]],

                'self_criticalness' => ['Self-Criticalness', [
                  '0' => "I don't criticize or blame myself more than usual.",
                  '1' => "I am more critical of myself than I used to be.",
                  '2' => "I criticize myself for all of my faults.",
                  '3' => "I blame myself for everything bad that happens.",
                ]],

                'loss_of_pleasure' => ['Loss of Pleasure', [
                  '0' => "I get as much pleasure as I ever did from the things I enjoy.",
                  '1' => "I don't enjoy things as much as I used to.",
                  '2' => "I get very little pleasure from the things I used to enjoy.",
                  '3' => "I can't get any pleasure from the things I used to enjoy.",
                ]],

                'suicidal_thoughts_or_wishes' => ['Suicidal Thoughts or Wishes', [
                  '0' => "I don't have any thoughts of killing myself.",
                  '1' => "I have thoughts of killing myself, but I would not carry them out.",
                  '2' => "I would like to kill myself.",
                  '3' => "I would kill myself if I had the chance.",
                ]],

                'guilty_feelings' => ['Guilty Feelings', [
                  '0' => "I don't feel particularly guilty.",
                  '1' => "I feel guilty over many things I have done or should have done.",
                  '2' => "I feel quite guilty most of the time.",
                  '3' => "I feel guilty all of the time.",
                ]],

                'crying' => ['Crying', [
                  '0' => "I don't cry any more than I used to.",
                  '1' => "I cry more than I used to.",
                  '2' => "I cry over every little thing.",
                  '3' => "I feel like crying, but I can't.",
                ]],

                'agitation' => ['Agitation', [
                  '0' => "I am no more restless or wound up than usual.",
                  '1' => "I am more restless or wound up than usual.",
                  '2' => "I am so restless or agitated that it's hard to stay still.",
                  '3' => "I am so restless or agitated that I have to keep moving or doing something.",
                ]],

                'irritability' => ['Irritability', [
                  '0' => "I am no more irritable than usual.",
                  '1' => "I am more irritable than usual.",
                  '2' => "I am much more irritable than usual.",
                  '3' => "I am irritable all the time.",
                ]],

                'loss_of_interest' => ['Loss of Interest', [
                  '0' => "I have not lost interest in other people or activities.",
                  '1' => "I am less interested in other people or things than before.",
                  '2' => "I have lost most of my interest in other people or things.",
                  '3' => "It's hard to get interested in anything.",
                ]],

                'loss_of_interest_in_sex' => ['Loss of Interest in Sex', [
                  '0' => "I have not noticed any recent change in my interest in sex.",
                  '1' => "I am less interested in sex than I used to be.",
                  '2' => "I am much less interested in sex now.",
                  '3' => "I have lost interest in sex completely.",
                ]],

                'indecisiveness' => ['Indecisiveness', [
                  '0' => "I make decisions about as well as ever.",
                  '1' => "I find it more difficult to make decisions than usual.",
                  '2' => "I have much greater difficulty in making decisions than I used to.",
                  '3' => "I have trouble making any decisions.",
                ]],

                'concentration_difficulty' => ['Concentration Difficulty', [
                  '0' => "I can concentrate as well as ever.",
                  '1' => "I can't concentrate as well as usual.",
                  '2' => "It's hard to keep my mind on anything for very long.",
                  '3' => "I find I can't concentrate on anything.",
                ]],

                'worthlessness' => ['Worthlessness', [
                  '0' => "I do not feel I am worthless.",
                  '1' => "I don't consider myself as worthwhile and useful as I used to.",
                  '2' => "I feel more worthless as compared to other people.",
                  '3' => "I feel utterly worthless.",
                ]],

                'tiredness_or_fatigue' => ['Tiredness or Fatigue', [
                  '0' => "I am no more tired or fatigued than usual.",
                  '1' => "I get more tired or fatigued more easily than usual.",
                  '2' => "I am too tired or fatigued to do a lot of the things I used to do.",
                  '3' => "I am too tired or fatigued to do most of the things I used to do.",
                ]],

                'loss_of_energy' => ['Loss of Energy', [
                  '0' => "I have as much energy as ever.",
                  '1' => "I have less energy than I used to have.",
                  '2' => "I don't have enough energy to do very much.",
                  '3' => "I don't have enough energy to do anything.",
                ]],

                'changes_in_appetite' => ['Changes in Appetite', [
                  '0'  => "I have not experienced any change in my appetite.",
                  '1a' => "My appetite is somewhat less than usual.",
                  '1b' => "My appetite is somewhat greater than usual.",
                  '2a' => "My appetite is much less than before.",
                  '2b' => "My appetite is much greater than usual.",
                  '3a' => "I have no appetite at all.",
                  '3b' => "I crave food all the time.",
                ]],

                'changes_in_sleeping_pattern' => ['Changes in Sleeping Pattern', [
                  '0'  => "I have not experienced any change in my sleeping pattern.",
                  '1a' => "I sleep somewhat more than usual.",
                  '1b' => "I sleep somewhat less than usual.",
                  '2a' => "I sleep a lot more than usual.",
                  '2b' => "I sleep a lot less than usual.",
                  '3a' => "I sleep most of the day.",
                  '3b' => "I wake up 1–2 hours early and can't get back to sleep.",
                ]],
              ];

              echo "<section class='card'><h3 class='section-title'>Inventories: BDI (Beck Depression Inventory)</h3>";
              echo "<div class='grid'>";

              foreach ($BDI as $field => [$title, $choices]) {
                // find this column meta (already in $fields from your loader)
                $meta = null; foreach ($fields as $m) { if ($m['name'] === $field) { $meta = $m; break; } }
                if (!$meta) continue;

                // each group in a half-width card
                echo "<div class='col-6'>".vtc_radio_group($meta, $choices, $title)."</div>";
              }

              echo "</div></section>";
            }
            /* ==================== PAGE 17 — BAI ==================== */
            if ($step === 17) {
              $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
              };

                // Build the BAI items map so the foreach has data
                // (keeps labels in sync with your existing $LONG_LABELS)
                $BAI_ITEMS = [];
                foreach (($PAGES[17]['exact'] ?? []) as $n) {
                  $BAI_ITEMS[$n] = $LONG_LABELS[$n] ?? ucwords(str_replace('_',' ', $n));
                }


              echo "<section class='card'><h3 class='section-title'>Inventories: BAI — Select answers true for you regarding the past week including today.</h3>";
              echo "<div class='grid'>";

              // 3-column layout
              $i = 0;
              foreach ($BAI_ITEMS as $name => $label) {
                $meta = $by($name);
                if (!$meta) continue;
                // keep DB comment if present, else use our label
                if (empty($meta['comment'])) $meta['comment'] = $label;

                echo "<div class='col-4'>".vtc_bai_select($meta)."</div>";
                $i++;
              }

              echo "</div></section>";
            }
            /* ==================== PAGE 18 — Inventories: BHS ==================== */
            if ($step === 18) {
              // helper to get field meta from DESCRIBE
              $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
              };

              // choices
              $TF = ['True' => 'True', 'False' => 'False'];

              // Use DB comments as labels; if a comment is missing, fall back to these strings.
              $BHS_LABEL_FALLBACK = [
                '1_i_look_forward_to_the_future_with_hope_and_enthusiasm' => 'I look forward to the future with hope and enthusiasm.',
                '2_i_might_as_well_give_up_because_i_cant_make_things_better_for_' => 'I might as well give up because I can’t make things better for myself.',
                '3_when_things_are_going_badly_i_am_helped_by_knowing_they_cant_s' => 'When things are going badly, I am helped by knowing they can’t stay that way forever.',
                '4_i_cant_imagine_what_my_life_would_be_like_in_10_years' => 'I can’t imagine what my life would be like in 10 years.',
                '5_i_have_enough_time_to_accomplish_the_things_i_most_want_to_do' => 'I have enough time to accomplish the things I most want to do.',
                '6_in_the_future_i_expect_to_succeed_in_what_concerns_me_most' => 'In the future, I expect to succeed in what concerns me most.',
                '7_my_future_seems_dark_to_me' => 'My future seems dark to me.',
                '8_i_expect_to_get_more_good_things_in_life_than_the_average_pers' => 'I expect to get more good things in life than the average person.',
                '9_i_just_dont_get_the_breaks_and_theres_no_reason_to_believe_i_w' => 'I just don’t get the breaks, and there’s no reason to believe I will in the future.',
                '10_my_past_experiences_have_prepared_me_well_for_the_future' => 'My past experiences have prepared me well for the future.',
                '11_all_i_can_see_ahead_of_me_is_unpleasantness_rather_than_pleas' => 'All I can see ahead of me is unpleasantness rather than pleasantness.',
                '12_i_dont_expect_to_get_what_i_really_want' => 'I don’t expect to get what I really want.',
                '13_when_i_look_ahead_to_the_future_i_expect_i_will_be_happier_th' => 'When I look ahead to the future, I expect I will be happier than I am now.',
                '14_things_just_wont_work_out_the_way_i_want_them_to' => 'Things just won’t work out the way I want them to.',
                '15_i_have_great_faith_in_the_future' => 'I have great faith in the future.',
                '16_i_never_get_what_i_want_so_its_foolish_to_want_anything' => 'I never get what I want so it’s foolish to want anything.',
                '17_it_is_very_unlikely_that_i_will_get_any_real_satisfaction_in_' => 'It is very unlikely that I will get any real satisfaction in the future.',
                '18_the_future_seems_vague_and_uncertain_to_me' => 'The future seems vague and uncertain to me.',
                '19_i_can_look_forward_to_more_good_times_than_bad_times' => 'I can look forward to more good times than bad times.',
                '20_theres_no_use_in_really_trying_to_get_something_i_want_becaus' => 'There’s no use in really trying to get something I want because I probably won’t get it.',
              ];

              echo "<section class='card'><h3 class='section-title'>Inventories: BHS (True/False)</h3>";
              echo "<div class='grid'>";

              foreach ($PAGES[18]['exact'] as $col) {
                $meta = $by($col);
                if (!$meta) continue;
                // Prefer DB comment if present; otherwise fallback string
                if (empty($meta['comment'])) {
                  $meta['comment'] = $BHS_LABEL_FALLBACK[$col] ?? ucwords(str_replace('_',' ', preg_replace('/^\d+_/', '', $col)));
                }
                echo "<div class='col-6'>".vtc_radio_group($meta, $TF, $meta['comment'])."</div>";
              }

              echo "</div></section>";
            }
            /* ==================== PAGE 19 — Inventories: HAM-D ==================== */
            if ($step === 19) {
              // schema lookup
              $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
              };

              // Choices
              $C02 = [
                '0' => '0 – Absent',
                '1' => '1 – Occasional',
                '2' => '2 – Frequent',
              ];
              $INSIGHT = [
                '0' => '0 – No loss',
                '1' => '1 – Partial or doubtful loss',
                '2' => '2 – Loss of insight',
              ];
              $MILD_SEVERE = [
                '0' => '0 – Absent',
                '1' => '1 – Mild',
                '2' => '2 – Severe',
              ];
              $DIURNAL = [
                '0' => '0 – No variation',
                '1' => '1 – Mild variation',
                '2' => '2 – Severe variation',
              ];
              $WEIGHT = [
                '0' => '0 – No weight loss',
                '1' => '1 – Slight',
                '2' => '2 – Obvious or severe',
              ];
              $C04_SUICIDE = [
                '0' => '0 – Absent',
                '1' => '1 – Feels life is not worth living',
                '2' => '2 – Wishes he/she were dead',
                '3' => '3 – Suicidal ideas or gestures',
                '4' => '4 – Attempts at suicide',
              ];
              $C04_RETARD = [
                '0' => '0 – Absent',
                '1' => '1 – Slight retardation at interview',
                '2' => '2 – Obvious retardation at interview',
                '3' => '3 – Interview difficult',
                '4' => '4 – Complete stupor',
              ];
              $C04_GUILT = [
                '0' => '0 – Absent',
                '1' => '1 – Self-reproach; let people down',
                '2' => '2 – Ideas of guilt',
                '3' => '3 – Illness is punishment; delusions of guilt',
                '4' => '4 – Hallucinations of guilt',
              ];
              $C04_WORK = [
                '0' => '0 – No difficulty',
                '1' => '1 – Incapacity/listless/indecisive',
                '2' => '2 – Loss of interest; ↓ social',
                '3' => '3 – Productivity decreased',
                '4' => '4 – Unable to work (present illness only)',
              ];
              $C04_ANX = [
                '0' => '0 – No difficulty',
                '1' => '1 – Tension/irritability',
                '2' => '2 – Worrying about minor matters',
                '3' => '3 – Apprehensive attitude',
                '4' => '4 – Fears',
              ];
              $C04_ANX_SOM = [
                '0' => '0 – Absent',
                '1' => '1 – Mild',
                '2' => '2 – Moderate',
                '3' => '3 – Severe',
                '4' => '4 – Incapacitating',
              ];
              $C04_DPD = [
                '0' => '0 – Absent',
                '1' => '1 – Mild',
                '2' => '2 – Moderate',
                '3' => '3 – Severe',
                '4' => '4 – Incapacitating',
              ];
              $C04_DEPRESSED = [
                '0' => '0 – Absent',
                '1' => '1 – Sadness, etc.',
                '2' => '2 – Occasional weeping',
                '3' => '3 – Frequent weeping',
                '4' => '4 – Extreme symptoms',
              ];
              $C04_HYPO = [
                '0' => '0 – Not present',
                '1' => '1 – Self-absorption (bodily)',
                '2' => '2 – Preoccupation with health',
                '3' => '3 – Querulous attitude',
                '4' => '4 – Hypochondriacal delusions',
              ];
              $C04_PARANOID = [
                '0' => '0 – None',
                '1' => '1 – Suspicious',
                '2' => '2 – Ideas of reference',
                '3' => '3 – Delusions of reference & persecution',
                '4' => '4 – Hallucinations, persecutory',
              ];

              // Field → choices mapping
              $CHOICES = [
                'insomnia_initial_difficulty_in_falling_asleep' => $C02,
                'agitation_restlessness_associated_with_anxiety' => $C02,
                'insomnia_middle_complains_of_being_restless_and_disturbed_during' => $C02,
                'insomnia_delayed_waking_in_early_hours_of_the_morning_and_unable' => $C02,
                'insight_insight_must_be_interpreted_in_terms_of_patients_underst' => $INSIGHT,
                'genital_symptoms_loss_of_libido_menstrual_disturbances' => $MILD_SEVERE,
                'somatic_symptoms_gastrointestinal_loss_of_appetite_heavy_feeling' => $MILD_SEVERE,
                'somatic_symptoms_general' => $MILD_SEVERE,
                'diurnal_variation_symptoms_worse_in_morning_or_evening_note_whic' => $DIURNAL,
                'weight_loss' => $WEIGHT,
                'obsessional_symptoms_obsessive_thoughts_and_compulsions_against_' => $MILD_SEVERE,
                'suicide' => $C04_SUICIDE,
                'retardation_slowness_of_thought_speech_and_activity_apathy_stupo' => $C04_RETARD,
                'feelings_of_guilt' => $C04_GUILT,
                'work_and_interests' => $C04_WORK,
                'anxiety' => $C04_ANX,
                'anxiety_somatic_gastrointestinal_indigestion_cardiovascular_palp' => $C04_ANX_SOM,
                'depersonalization_and_derealization_feelings_of_unreality_nihili' => $C04_DPD,
                'depressed_mood_gloomy_attitude_pessimism_about_the_future_feelin' => $C04_DEPRESSED,
                'hypochondriasis' => $C04_HYPO,
                'paranoid_symptoms_not_with_a_depressive_quality' => $C04_PARANOID,
              ];

              // Label fallbacks (used only if column comments are empty)
              $LABELS = [
                'insomnia_initial_difficulty_in_falling_asleep' => 'INSOMNIA – Initial (Difficulty in falling asleep)',
                'agitation_restlessness_associated_with_anxiety' => 'AGITATION (Restlessness associated with anxiety.)',
                'insomnia_middle_complains_of_being_restless_and_disturbed_during' => 'INSOMNIA – Middle (Restless/disturbed; waking during the night)',
                'insomnia_delayed_waking_in_early_hours_of_the_morning_and_unable' => 'INSOMNIA – Delayed (Early morning waking; unable to return to sleep)',
                'insight_insight_must_be_interpreted_in_terms_of_patients_underst' => 'INSIGHT (Interpret in terms of understanding/background)',
                'genital_symptoms_loss_of_libido_menstrual_disturbances' => 'GENITAL SYMPTOMS (Loss of libido, menstrual disturbances)',
                'somatic_symptoms_gastrointestinal_loss_of_appetite_heavy_feeling' => 'SOMATIC SYMPTOMS – Gastrointestinal',
                'somatic_symptoms_general' => 'SOMATIC SYMPTOMS - GENERAL (Heaviness in limbs, back or head; diffuse backache; loss of energy and fatiguability)',
                'diurnal_variation_symptoms_worse_in_morning_or_evening_note_whic' => 'DIURNAL VARIATION (Note whether morning/evening)',
                'weight_loss' => 'WEIGHT LOSS',
                'obsessional_symptoms_obsessive_thoughts_and_compulsions_against_' => 'OBSESSIONAL SYMPTOMS',
                'suicide' => 'SUICIDE',
                'retardation_slowness_of_thought_speech_and_activity_apathy_stupo' => 'RETARDATION (Thought/speech/activity; apathy; stupor)',
                'feelings_of_guilt' => 'FEELINGS OF GUILT',
                'work_and_interests' => 'WORK AND INTERESTS',
                'anxiety' => 'ANXIETY',
                'anxiety_somatic_gastrointestinal_indigestion_cardiovascular_palp' => 'ANXIETY – SOMATIC',
                'depersonalization_and_derealization_feelings_of_unreality_nihili' => 'DEPERSONALIZATION & DEREALIZATION',
                'depressed_mood_gloomy_attitude_pessimism_about_the_future_feelin' => 'DEPRESSED MOOD',
                'hypochondriasis' => 'HYPOCHONDRIASIS',
                'paranoid_symptoms_not_with_a_depressive_quality' => 'PARANOID SYMPTOMS (Not depressive quality)',
              ];

              echo "<section class='card'><h3 class='section-title'>Inventories: HAM-D</h3>";
              echo "<div class='grid'>";

              foreach ($PAGES[19]['exact'] as $col) {
                $meta = $by($col);
                if (!$meta) continue;

                if (empty($meta['comment'])) $meta['comment'] = $LABELS[$col] ?? $col;

                $choices = $CHOICES[$col] ?? $C02;
                echo "<div class='col-6'>".vtc_radio_group($meta, $choices, $meta['comment'])."</div>";
              }

              echo "</div></section>";
            }
            /* ==================== PAGE 20 — Inventories: HAM-A ==================== */
            if ($step === 20) {
              // Find schema meta by name
              $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
              };

              // 0–4 scale
              $HAMA = [
                '1' => '1 – Not at all',
                '2' => '2 – A little bit',
                '3' => '3 – Moderately',
                '4' => '4 – Quite a bit',
                '5' => '5 – Extremely',
              ];

              // Fallback labels if DB comments are empty
              $LABELS = [
                'tension_feelings_of_tension_fatigability_startle_response_moved_' => 'Tension: feelings of tension, fatigability, startle response, moved to tears, trembling, restlessness, inability to relax',
                'anxious_worries_anticipation_of_the_worst_fearful_anticipation_i' => 'Anxious: worries, anticipation of the worst, fearful anticipation, irritability',
                'fears_of_dark_of_strangers_of_being_left_alone_of_animals_of_tra' => 'Fears: dark, strangers, being alone, animals, traffic, crowds',
                'insomnia_difficulty_in_falling_asleep_broken_sleep_unsatisfying_' => 'Insomnia: difficulty falling asleep, broken/unsatisfying sleep, fatigue on waking, nightmares',
                'intellectual_cognitive_difficulty_in_concentration_poor_memory'   => 'Intellectual (cognitive): difficulty in concentration, poor memory',
                'depressed_mood_loss_of_interest_lack_of_pleasure_in_hobbies_depr' => 'Depressed mood: loss of interest/pleasure, early waking, diurnal swing',
                'somatic_muscular_pains_and_aches_twitching_stiffness_myoclonic_j' => 'Somatic (muscular): pains/aches, twitching, stiffness, grinding teeth, unsteady voice',
                'somatic_sensory_tinnitus_blurring_of_vision_hot_and_cold_flushes' => 'Somatic (sensory): tinnitus, blurred vision, hot/cold flushes, weakness, pricking',
                'cardiovascular_symptoms_tachycardia_palpitations_pain_in_chest_t' => 'Cardiovascular: tachycardia, palpitations, chest pain, throbbing vessels, fainting',
                'respiratory_symptoms_pressure_or_constriction_in_chest_choking_f' => 'Respiratory: chest pressure/constriction, choking feelings, sighing, dyspnea',
                'gastrointestinal_symptoms_difficulty_in_swallowing_wind_abdomina' => 'Gastrointestinal: dysphagia, wind, abdominal pain/burning, nausea, vomiting, constipation',
                'genitourinary_symptoms_frequency_of_micturition_urgency_of_mictu' => 'Genitourinary: frequency/urgency of micturition, libido loss, impotence',
                'autonomic_symptoms_dry_mouth_flushing_pallor_tendency_to_sweat_g' => 'Autonomic: dry mouth, flushing, pallor, sweating, giddiness, tension headache',
                'behavior_fidgeting_restlessness_or_pacing_tremor_of_hands_furrow' => 'Behavior: fidgeting, pacing, tremor, furrowed brow, rapid respiration, brisk tendon jerks',
              ];

              echo "<section class='card'><h3 class='section-title'>Ham-A</h3>";
              echo "<div class='grid'>";

              foreach ($PAGES[20]['exact'] as $col) {
                $meta = $by($col);
                if (!$meta) continue;

                if (empty($meta['comment'])) $meta['comment'] = $LABELS[$col] ?? $col;

                // If no saved value yet, prepend a blank placeholder so the user must choose.
                $cur = $_SESSION['vtc_form'][$col] ?? '';

                // If there's no saved value, prepend a blank placeholder so the user must choose.
                // (array_merge preserves string keys and keeps the placeholder first)
                $choices = ($cur === '')
                  ? array_merge(['' => '— select —'], $HAMA)
                  : $HAMA;

                if (function_exists('vtc_select')) {
                  echo "<div class='col-6'>".vtc_select($meta, $choices, $meta['comment'])."</div>";
                } else {
                  echo "<div class='col-6'>".vtc_radio_group($meta, $choices, $meta['comment'])."</div>";
                }


              }

              echo "</div></section>";
            }
            /* ==================== PAGE 21 — PCL-M ==================== */
            if ($step === 21) {
              $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
              };

              // Standard PCL-M scale
              $PCLM = [
                '1' => '1 – Not at all',
                '2' => '2 – A little bit',
                '3' => '3 – Moderately',
                '4' => '4 – Quite a bit',
                '5' => '5 – Extremely',
              ];

              // Fallback labels (used only if the DB column comment is empty)
              $LBL = [
                '1_repeated_disturbing_memories_thoughts_or_images_of_a_stressful' => '1. Repeated, disturbing memories, thoughts, or images of a stressful military experience?',
                '2_repeated_disturbing_dreams_of_a_stressful_military_experience'  => '2. Repeated, disturbing dreams of a stressful military experience?',
                '3_suddenly_acting_or_feeling_as_if_a_stressful_military_experien' => '3. Suddenly acting or feeling as if a stressful military experience were happening again?',
                '4_feeling_very_upset_when_something_reminded_you_of_a_stressful_' => '4. Feeling very upset when something reminded you of a stressful military experience?',
                '5_having_physical_reactions_e_g_heart_pounding_trouble_breathing' => '5. Physical reactions (e.g., heart pounding, trouble breathing, sweating) when reminded?',
                '6_avoid_thinking_about_or_talking_about_a_stressful_military_exp' => '6. Avoid thinking/talking about a stressful military experience or avoid related feelings?',
                '7_avoid_activities_or_talking_about_a_stressful_military_experie' => '7. Avoid activities or situations related to a stressful military experience?',
                '8_trouble_remembering_important_parts_of_a_stressful_military_ex' => '8. Trouble remembering important parts of a stressful military experience?',
                '9_loss_of_interest_in_things_that_you_used_to_enjoy'              => '9. Loss of interest in things you used to enjoy?',
                '10_feeling_distant_or_cut_off_from_other_people'                  => '10. Feeling distant or cut off from other people?',
                '11_feeling_emotionally_numb_or_being_unable_to_have_loving_feeli' => '11. Feeling emotionally numb or unable to have loving feelings?',
                '12_feeling_as_if_your_future_will_somehow_be_cut_short'           => '12. Feeling as if your future will somehow be cut short?',
                '13_trouble_falling_or_staying_asleep'                             => '13. Trouble falling or staying asleep?',
                '14_feeling_irritable_or_having_angry_outbursts'                   => '14. Feeling irritable or having angry outbursts?',
                '15_having_difficulty_concentrating'                               => '15. Having difficulty concentrating?',
                '16_being_super_alert_or_watchful_on_guard'                        => '16. Being “super alert” or watchful on guard?',
                '17_feeling_jumpy_or_easily_startled'                              => '17. Feeling jumpy or easily startled?',
              ];

              echo "<section class='card'><h3 class='section-title'>PTSD CheckList – Military Version (PCL-M)</h3>";
              echo "<p class='text-muted'>Below is a list of problems and complaints that veterans sometimes have in response to stressful military experiences. Please read each one carefully and select how much you have been bothered in the last month.</p>";
              echo "<div class='grid'>";

              foreach ($PAGES[21]['exact'] as $col) {
                $meta = $by($col);
                if (!$meta) continue;
                if (empty($meta['comment'])) $meta['comment'] = $LBL[$col] ?? $col;

                if (function_exists('vtc_select')) {
                  echo "<div class='col-6'>".vtc_select($meta, $PCLM, $meta['comment'])."</div>";
                } else {
                  echo "<div class='col-6'>".vtc_radio_group($meta, $PCLM, $meta['comment'])."</div>";
                }
              }

              echo "</div></section>";
            }
            /* ==================== PAGE 22 — TBI Checklist ==================== */
            if ($step === 22) {
              // schema lookup helper
              $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
              };

              // 0–4 scale
              $TBI = [
                '0' => '0 – None',
                '1' => '1 – Mild',
                '2' => '2 – Moderate',
                '3' => '3 – Severe',
                '4' => '4 – Very Severe',
              ];

              // Fallback labels (used only if column comments are empty)
              $LBL = [
                '1_feeling_dizzy'                                   => '1. Feeling Dizzy',
                '2_loss_of_balance'                                 => '2. Loss of balance',
                '3_poor_coordination_clumsy'                        => '3. Poor coordination, clumsy',
                '4_headaches'                                       => '4. Headaches',
                '5_nausea'                                          => '5. Nausea',
                '6_vision_problems_blurring_trouble_seeing'         => '6. Vision problems, blurring, trouble seeing',
                '7_sensitivity_to_light'                            => '7. Sensitivity to light',
                '8_hearing_difficulty'                              => '8. Hearing difficulty',
                '9_sensitivity_to_noise'                            => '9. Sensitivity to noise',
                '10_numbness_to_tingling_on_parts_of_body'          => '10. Numbness or tingling on parts of body',
                '11_change_in_taste_and_or_smell'                   => '11. Change in taste and/or smell',
                '12_loss_or_increase_of_appetite'                   => '12. Loss or increase of appetite',
                '13_poor_concentration_or_easily_distracted'        => '13. Poor concentration or easily distracted',
                '14_forgetfulness_cant_remember_things'             => '14. Forgetfulness, can’t remember things',
                '15_difficulty_making_decisions'                    => '15. Difficulty making decisions',
                '16_slowed_thinking_cant_finish_things'             => '16. Slowed thinking, can’t finish things',
                '17_fatigue_loss_of_energy_easily_tired'            => '17. Fatigue, loss of energy, easily tired',
                '18_difficulty_falling_or_staying_asleep'           => '18. Difficulty falling or staying asleep',
                '19_feeling_anxious_or_tense'                       => '19. Feeling anxious or tense',
                '20_feeling_depressed_or_sad'                       => '20. Feeling depressed or sad',
                '21_irritability_easily_annoyed'                    => '21. Irritability, easily annoyed',
                '22_poor_frustration_tolerance_overwhelmed'         => '22. Poor frustration tolerance, overwhelmed',
              ];

              echo "<section class='card'><h3 class='section-title'>TBI Checklist</h3>";
              echo "<p class='text-muted'>Please rate how much the following symptoms have disturbed you since your injury.</p>";
              echo "<div class='grid'>";

              foreach ($PAGES[22]['exact'] as $col) {
                $meta = $by($col);
                if (!$meta) continue;

                if (empty($meta['comment'])) $meta['comment'] = $LBL[$col] ?? $col;

                // Add a blank placeholder if there is no saved value yet so the user must choose
                $cur     = $_SESSION['vtc_form'][$col] ?? '';
                $choices = ($cur === '') ? array_merge(['' => '— select —'], $TBI) : $TBI;

                if (function_exists('vtc_select')) {
                  echo "<div class='col-6'>".vtc_select($meta, $choices, $meta['comment'])."</div>";
                } else {
                  echo "<div class='col-6'>".vtc_radio_group($meta, $choices, $meta['comment'])."</div>";
                }
              }

              echo "</div></section>";
            }
            /* ==================== PAGE 23 — SASSI (True/False) ==================== */
            if ($step === 23) {
              $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
              };

              $TF = ['True' => 'True', 'False' => 'False'];

              echo "<section class='card'><h3 class='section-title'>SASSI</h3>";
              echo "<p class='text-muted'>Please choose True or False for each statement.</p>";
              echo "<div class='grid'>";

              foreach ($PAGES[23]['exact'] as $col) {
                $meta = $by($col);
                if (!$meta) continue;

                // Prefer DB comment text; otherwise make a readable fallback
                if (empty($meta['comment'])) {
                  $fallback = preg_replace('/^\d+_/', '', $col);
                  $fallback = ucwords(str_replace('_', ' ', $fallback));
                  $meta['comment'] = $fallback;
                }

                echo "<div class='col-6'>".vtc_radio_group($meta, $TF, $meta['comment'])."</div>";
              }

              echo "</div></section>";
            }
            /* ==================== PAGE 24 — Alcohol Use ==================== */
            if ($step === 24) {
              $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
              };

              // Frequency scale (adjust labels if you prefer different wording)
              $FREQ = [
                'Never'       => 'Never',
                'Once or Twice'      => 'Once or Twice',
                'Several Times'   => 'Several Times',
                'Repeatedly'       => 'Repeadedly',
              ];

              // Fallback labels if column comments are empty
              $LBL = [
                '1_had_drinks_beer_wine_liquor_with_lunch'                   => '1. Had drinks (beer, wine, liquor) with lunch?',
                '2_taken_a_drink_or_drinks_to_help_you_talk_about_your_feelings_o' => '2. Taken a drink to help you talk about your feelings or ideas?',
                '3_taken_a_drink_or_drinks_to_relieve_a_tired_feeling_or_give_you' => '3. Taken a drink to relieve a tired feeling or give you energy to keep going?',
                '4_had_more_to_drink_than_you_intended_to'                   => '4. Had more to drink than you intended to?',
                '5_experienced_physical_problems_after_drinking_e_g_nausea_seeing' => '5. Experienced physical problems after drinking (nausea, vision/hearing issues, dizziness, etc.)?',
                '6_gotten_into_trouble_on_the_job_in_school_or_with_the_law_becau' => '6. Gotten into trouble on the job, in school, or with the law because of your drinking?',
                '7_became_depressed_after_having_sobered_up'                 => '7. Became depressed after having sobered up?',
                '8_argued_with_your_family_or_friends_because_of_your_drinking'    => '8. Argued with family or friends because of your drinking?',
                '9_had_the_effects_of_drinking_recur_after_not_drinking_for_a_whi' => '9. Effects of drinking recurred after a while without drinking (e.g., flashbacks, hallucinations)?',
                '10_had_problems_in_relationships_because_of_your_drinking_e_g_lo' => '10. Relationship problems because of your drinking (loss of friends, separation, divorce, etc.)?',
                '11_became_nervous_or_had_the_shakes_after_having_sobered_up'      => '11. Became nervous or had the shakes after sobering up?',
                '12_tried_to_commit_suicide_while_drunk'                     => '12. Tried to commit suicide while drunk?',
                '13_found_myself_craving_a_drink_or_a_particular_drug'       => '13. Found yourself craving a drink or a particular drug?',
              ];

              echo "<section class='card'><h3 class='section-title'>Alcohol Use</h3>";
              echo "<p class='text-muted' style='margin-top:-6px'>For each item, choose how often this happened <strong>in the 6 months before your arrest</strong>. “Drinks” includes beer, wine, and liquor.</p>";
              echo "<div class='grid'>";

              foreach ($PAGES[24]['exact'] as $col) {
                $meta = $by($col); if (!$meta) continue;
                if (empty($meta['comment'])) $meta['comment'] = $LBL[$col] ?? $col;

                // Force an explicit choice by prepending a placeholder when no value saved yet
                $cur     = $_SESSION['vtc_form'][$col] ?? '';
                $choices = ($cur === '') ? array_merge(['' => '— select —'], $FREQ) : $FREQ;

                if (function_exists('vtc_select')) {
                  echo "<div class='col-6'>".vtc_select($meta, $choices, $meta['comment'])."</div>";
                } else {
                  echo "<div class='col-6'>".vtc_radio_group($meta, $choices, $meta['comment'])."</div>";
                }
              }

              echo "</div></section>";
            }
            /* ==================== PAGE 25 — Drug Use ==================== */
            if ($step === 25) {
              $by = function(string $name) use ($fields) {
                foreach ($fields as $m) if ($m['name'] === $name) return $m;
                return null;
              };

              // Frequency scale
              $FREQ = [
                'Never'      => 'Never',
                'Rarely'     => 'Rarely',
                'Sometimes'  => 'Sometimes',
                'Often'      => 'Often',
                'Very often' => 'Very often',
              ];

              // Fallback labels if the DB column comments are empty
              $LBL = [
                '1_misused_medications_or_took_drugs_to_improve_your_thinking_and' => '1. Misused medications or took drugs to improve your thinking and feelings?',
                '2_misused_medications_or_took_drugs_to_help_you_feel_better_abou' => '2. Misused medications or took drugs to help you feel better about a problem?',
                '3_misused_medications_or_took_drugs_to_become_more_aware_of_your' => '3. Misused medications or took drugs to become more aware of your senses (e.g., sight, hearing, touch)?',
                '4_misused_medications_or_took_drugs_to_improve_your_enjoyment_of' => '4. Misused medications or took drugs to improve your enjoyment of sex?',
                '5_misused_medications_or_took_drugs_to_help_forget_that_you_feel' => '5. Misused medications or took drugs to help forget that you feel helpless or unworthy?',
                '6_misused_medications_or_took_drugs_to_forget_school_work_or_fam' => '6. Misused medications or took drugs to forget school, work, or family pressures?',
                '7_gotten_into_trouble_at_home_work_or_with_the_police_because_of' => '7. Gotten into trouble at home, work, or with the police because of medications or drug-related activities?',
                '8_gotten_really_stoned_or_wiped_out_on_drugs_more_than_just_high' => '8. Gotten really stoned or wiped out on drugs (more than just high)?',
                '9_tried_to_get_a_hold_of_some_prescription_drug_e_g_tranquilizer' => '9. Tried to get a hold of some prescription drug (tranquilizers, pain killers, sleep aids, etc.)?',
                '10_spent_your_spare_time_in_drug_related_activities_e_g_talking_' => '10. Spent spare time in drug-related activities (talking, buying, selling, taking, etc.)?',
                '11_used_drugs_or_medications_and_alcohol_at_the_same_time' => '11. Used drugs/medications and alcohol at the same time?',
                '12_kept_taking_medications_or_drugs_in_order_to_avoid_pain_or_wi' => '12. Kept taking medications or drugs to avoid pain or withdrawal?',
                '13_felt_your_misuse_of_medications_alcohol_or_drugs_has_kept_you' => '13. Felt your misuse kept you from getting what you want out of life?',
                '14_took_a_higher_dose_or_different_medications_than_your_doctor_' => '14. Took a higher dose or different meds than prescribed to get the relief you need?',
                '15_used_prescription_drugs_that_were_not_prescribed_for_you' => '15. Used prescription drugs not prescribed for you?',
                '16_your_doctor_denied_your_request_for_medications_you_needed' => '16. Your doctor denied your request for medications you needed?',
                '17_been_accepted_into_a_treatment_program_because_of_misuse_of_m' => '17. Been accepted into a treatment program because of misuse of meds, alcohol, or drugs?',
                '18_engaged_in_activity_that_could_have_been_physically_dangerous' => '18. Engaged in activity that could be physically dangerous after/while using?',
              ];

              echo "<section class='card'><h3 class='section-title'>Drug Use</h3>";
              echo "<p class='text-muted' style='margin-top:-6px'>For each item, choose how often this happened <strong>in the 6 months before your arrest</strong>. “Misuse” = more/longer than prescribed or not prescribed. “Drugs” include pot, cocaine, meth, heroin, etc.</p>";
              echo "<div class='grid'>";

              foreach ($PAGES[25]['exact'] as $col) {
                $meta = $by($col); if (!$meta) continue;
                if (empty($meta['comment'])) $meta['comment'] = $LBL[$col] ?? $col;

                // Add a placeholder when no saved value so user must choose
                $cur     = $_SESSION['vtc_form'][$col] ?? '';
                $choices = ($cur === '') ? array_merge(['' => '— select —'], $FREQ) : $FREQ;

                if (function_exists('vtc_select')) {
                  echo "<div class='col-6'>".vtc_select($meta, $choices, $meta['comment'])."</div>";
                } else {
                  echo "<div class='col-6'>".vtc_radio_group($meta, $choices, $meta['comment'])."</div>";
                }
              }

              echo "</div></section>";
            }
            
          /* ==================== PAGE 26 — Signature & Submit ==================== */
          if ($step === 26) {
            $by = function(string $name) use ($fields) {
              foreach ($fields as $m) if ($m['name'] === $name) return $m;
              return null;
            };

            $first = $by('sign_first_name');
            $last  = $by('sign_last_name');
            $date  = $by('timestamp');

            // Prefill today's date if user has no value yet (DATE; switch to Y-m-d H:i:s for DATETIME)
            $today = date('Y-m-d');
            if (empty($_SESSION['vtc_form']['timestamp'])) {
              $_SESSION['vtc_form']['timestamp'] = $today;
            }

            $attest_text = "Entering your name constitutes your Electronic Signature; you verify the information "
                        . "in your evaluation is accurate and true to the best of your knowledge. By clicking "
                        . "SUBMIT, you confirm the above information is correct and true, with no misrepresentation "
                        . "or false content in accordance with applicable law.";

            echo "<section class='card'><h3 class='section-title'>Evaluation Signature and Submission</h3>";
            echo "<div class='grid'>";

            // First / Last
            echo "<div class='col-6'>";
            echo "<label class='lbl'>Name <span class='req'>*</span></label>";
            echo "<div class='row gap-8'>";
            echo "  <div class='col'><input type='text' name='sign_first_name' value='".htmlspecialchars($_SESSION['vtc_form']['sign_first_name'] ?? '', ENT_QUOTES)."' placeholder='First' class='input' required></div>";
            echo "  <div class='col'><input type='text' name='sign_last_name'  value='".htmlspecialchars($_SESSION['vtc_form']['sign_last_name']  ?? '', ENT_QUOTES)."' placeholder='Last'  class='input' required></div>";
            echo "</div></div>";

            // Date (timestamp)
            echo "<div class='col-6'>";
            echo "<label class='lbl'>Today's Date <span class='req'>*</span></label>";
            echo "<input type='date' name='timestamp' value='".htmlspecialchars($_SESSION['vtc_form']['timestamp'] ?? $today, ENT_QUOTES)."' class='input' required>";
            echo "</div>";

            echo "</div>"; // grid

            // Attestation (stored on submit via POST hook; include hidden for draft)
            echo "<p class='text-muted small' style='margin-top:12px;'>$attest_text</p>";
            echo "<input type='hidden' name='signature_attestation_text' value=\"".htmlspecialchars($attest_text, ENT_QUOTES)."\">";

            // (Optional) If you have a signature canvas widget, render it here.
            // echo vtc_signature_pad('signature_data'); // not persisted in this table per your schema

            echo "</section>";
          }







        }
      }
    ?>

    <div class="sticky">
      <div class="row">
        <?php if ($step > 1): ?>
          <a class="btn secondary" href="?step=<?php echo $step-1; ?>">Previous</a>
        <?php endif; ?>
        <?php if ($step < 26): ?>
          <button class="btn" type="submit" name="_action" value="next">Next</button>
        <?php else: ?>
          <button class="btn" type="submit" name="_action" value="submit">Submit Evaluation</button>
        <?php endif; ?>
      </div>
    </div>
  </form>

</main>

<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  function validEmail(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

  // ====== PAGE 1 ======
  if (step === 1) {
    var req1 = <?php echo json_encode(array_merge(VTC_P1_REQUIRED_TEXT, VTC_P1_REQUIRED_CHECKS)); ?>;
    var nextBtn1 = document.querySelector('button[name="_action"][value="next"]');

    function check1(){
      var ok=true;
      req1.forEach(function(name){
        var el=form.elements[name]; if(!el) return;
        if (el.type==='checkbox'){ if(!el.checked) ok=false; }
        else {
          var v=(el.value||'').trim();
          if(v==='') ok=false;
          if(name==='email' && !validEmail(v)) ok=false;
        }
      });
      nextBtn1.disabled = !ok;
    }
    form.addEventListener('input',check1);
    form.addEventListener('change',check1);
    check1();
    return;
  }

  // ====== PAGE 2 ======
  if (step === 2) {
    var nextBtn2 = document.querySelector('button[name="_action"][value="next"]');
    var empSel   = form.elements['employed'];
    var empWrap  = document.getElementById('empWrap');
    var ef = form.elements['__emerg_first'];
    var el = form.elements['__emerg_last'];
    var ecHidden = form.elements['emergency_contact_name'];

    function combineEC(){ if(ecHidden) ecHidden.value = ( (ef.value||'').trim() + ' ' + (el.value||'').trim() ).trim(); }
    function needsWork(){
      // required base
      var need = ['dl_number','address1','phone_primary','dob','age','race','gender','education_level','employed','__emerg_first','__emerg_last','emergency_contact_phone','military_service'];
      var ok = true;
      need.forEach(function(name){
        var elx = form.elements[name]; if(!elx) return;
        var v   = (elx.type==='checkbox') ? (elx.checked ? '1' : '') : (elx.value||'').trim();
        if(v==='') ok=false;
      });
      // if employed Yes, employer & occupation required
      var emp = (form.elements['employed'] && form.elements['employed'].value) || '';
      if (emp === 'Yes') {
        ['employer','occupation'].forEach(function(n){
          var elx=form.elements[n]; if(!elx) return;
          if((elx.value||'').trim()==='') ok=false;
        });
      }
      return ok;
    }

    function toggleEmp(){
        if (!empSel) return;
        var show = empSel.value === 'Yes';
        if (empWrap)  empWrap.classList.toggle('hidden', !show);
        var t = document.getElementById('empTitle');
        if (t) t.style.display = show ? '' : 'none';
    }


    function check2(){
      combineEC();
      nextBtn2.disabled = !needsWork();
    }

    form.addEventListener('input',check2);
    form.addEventListener('change',function(e){
      if (e.target && e.target.name === 'employed') toggleEmp();
      check2();
    });
    form.addEventListener('submit', combineEC);
    toggleEmp();
    check2();
  }
  // ====== PAGE 3 ======
  if (step === 3) {
    var form = document.querySelector('form');
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');
    function ok(){
        var officer = (form.elements['vtc_officer_name']?.value || '').trim();
        return officer !== '';
    }
    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }
    form.addEventListener('input', check);
    form.addEventListener('change', check);
    check();
  }

})();
</script>
<script>
(function () {
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 4) {
    var nextBtn   = document.querySelector('button[name="_action"][value="next"]');
    var hasKids   = form.elements['has_children'];
    var kidsBlock = document.getElementById('kidsBlock');

    // CPS toggle bits
    var cpsBlock   = document.getElementById('cpsBlock');
    var abuseNames = [
      'child_abused_physically',
      'child_abused_sexually',
      'child_abused_emotionally',
      'child_neglected'
    ];
    var abuseEls = abuseNames.map(function (n) { return form.elements[n]; }).filter(Boolean);

    function toggleKids() {
      var show = (hasKids && hasKids.value === 'Yes');
      if (kidsBlock) kidsBlock.classList.toggle('hidden', !show);
    }

    function abuseAny() {
      return abuseEls.some(function (e) { return e && e.value === 'Yes'; });
    }

    function toggleCps() {
      if (cpsBlock) cpsBlock.classList.toggle('hidden', !abuseAny());
    }

    function ok() {
      // Always required on page 4
      var required = [
        'marital_status', 'living_situation', 'has_children',
        'child_abused_physically', 'child_abused_sexually',
        'child_abused_emotionally', 'child_neglected'
      ];

      // If has children, require the follow-up
      if (hasKids && hasKids.value === 'Yes') {
        required.push('children_live_with_you');
      }

      // If any abuse/neglect is Yes, require CPS questions
      if (abuseAny()) {
        required.push('cps_notified', 'cps_care_or_supervision');
      }

      for (var i = 0; i < required.length; i++) {
        var el = form.elements[required[i]];
        if (!el || (el.value || '').trim() === '') return false;
      }
      return true;
    }

    function check() {
      if (nextBtn) nextBtn.disabled = !ok();
    }

    // Events
    form.addEventListener('change', function (e) {
      if (e.target === hasKids) toggleKids();
      if (abuseEls.indexOf(e.target) !== -1) toggleCps();
      check();
    });
    form.addEventListener('input', check);

    // Initial state
    toggleKids();
    toggleCps();
    check();
  }
})();
</script>
<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 5) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');

    // Pairs: if YES on left, show right
    var pairs = [
      ['alcohol_past_use',    'alcohol_past_details'],
      ['alcohol_current_use', 'alcohol_current_details'],
      ['drug_past_use',       'drug_past_details'],
      ['drug_current_use',    'drug_current_details'],
    ];

    function wrapId(detail){ return detail + '_wrap'; }

    function toggleOne(yn, detail){
      var ynEl = form.elements[yn];
      var wrap = document.getElementById(wrapId(detail));
      if (!ynEl || !wrap) return;
      var show = (ynEl.value === 'Yes');
      wrap.classList.toggle('hidden', !show);
    }

    function toggleAll(){
      pairs.forEach(function(p){ toggleOne(p[0], p[1]); });
    }

    function ok(){
      // The four Yes/No selectors are always required
      var required = ['alcohol_past_use','alcohol_current_use','drug_past_use','drug_current_use'];

      // If any is Yes, require its detail field as well
      pairs.forEach(function(p){
        var yn = form.elements[p[0]], dt = form.elements[p[1]];
        if (yn && yn.value === 'Yes' && dt) required.push(p[1]);
      });

      for (var i=0;i<required.length;i++){
        var el = form.elements[required[i]];
        if (!el || (el.value||'').trim() === '') return false;
      }
      return true;
    }

    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }

    // Events
    form.addEventListener('change', function(e){
      if (!e.target.name) return;
      // If a Yes/No changed, re-toggle details
      if (['alcohol_past_use','alcohol_current_use','drug_past_use','drug_current_use'].indexOf(e.target.name) >= 0) {
        toggleAll();
      }
      check();
    });
    form.addEventListener('input', check);

    // Initial state
    toggleAll();
    check();
  }
})();
</script>

<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 6) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');

    var rules = [
      ['counseling_history',      ['counseling_details']],
      ['depressed_now',           ['depressed_details']],
      ['suicide_attempt_history', ['suicide_last_attempt_when']],
      ['psych_meds_current',      ['psych_meds_list','psych_meds_physician']],
      ['head_trauma_history',     ['head_trauma_details']],
    ];

    var allYN = [
      'counseling_history','depressed_now','suicide_attempt_history',
      'psych_meds_current','sexual_abuse_history',
      'head_trauma_history','weapon_possession_history',
      'childhood_abuse_history'
    ];

    function toggleOne(yn){
      var el = form.elements[yn], wrap = document.getElementById(yn + '_details');
      if (!wrap) return;
      wrap.classList.toggle('hidden', !(el && el.value === 'Yes'));
    }
    function toggleAll(){ rules.forEach(function(r){ toggleOne(r[0]); }); }

    function ok(){
      // require all yes/no items
      var required = allYN.slice();

      // require dependent details when the parent is Yes
      rules.forEach(function(r){
        var yn = form.elements[r[0]];
        if (yn && yn.value === 'Yes') r[1].forEach(function(n){ if (form.elements[n]) required.push(n); });
      });

      for (var i=0;i<required.length;i++){
        var el = form.elements[required[i]];
        if (!el || (el.value||'').trim()==='') return false;
      }
      return true;
    }

    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }

    form.addEventListener('change', function(e){
      if (allYN.indexOf(e.target.name) >= 0) toggleAll();
      check();
    });
    form.addEventListener('input', check);

    toggleAll();
    check();
  }
})();
</script>

<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 7) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');

    var required = [
      'upbringing_where_grow_up',
      'upbringing_who_raised_you',
      'upbringing_raised_by_both_parents',
      'upbringing_parents_caretakers_names',
      'upbringing_divorce_explain',
      'upbringing_caretaker_addiction',
      'upbringing_caretaker_mental_health',
      'upbringing_finances_growing_up',
      'upbringing_traumatic_experiences',
      'upbringing_school_experience',
      'upbringing_caretakers_help_schoolwork',
      'upbringing_anything_else_for_court'
    ];

    function ok(){
      for (var i=0;i<required.length;i++){
        var el = form.elements[required[i]];
        if (!el || (el.value||'').trim()==='') return false;
      }
      return true;
    }
    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }

    form.addEventListener('input', check);
    form.addEventListener('change', check);
    check();
  }
})();
</script>

<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 8) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');
    var required = [
      'legal_first_arrest_details',
      'legal_multiple_arrests_details',
      'legal_prevention_plan',
      'legal_hopes_from_vtc'
    ];

    function ok(){
      for (var i=0;i<required.length;i++){
        var el = form.elements[required[i]];
        if (!el || (el.value||'').trim()==='') return false;
      }
      return true;
    }
    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }

    form.addEventListener('input', check);
    form.addEventListener('change', check);
    check();
  }
})();
</script>

<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 9) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');
    var required = [
      'military_join_details',
      'military_trauma_description',
      'military_impact_beliefs',
      'military_grief_counseling',
      'military_culture_mh_attitudes'
    ];

    function ok(){
      for (var i=0;i<required.length;i++){
        var el = form.elements[required[i]];
        if (!el || (el.value||'').trim()==='') return false;
      }
      return true;
    }
    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }

    form.addEventListener('input', check);
    form.addEventListener('change', check);
    check();
  }
})();
</script>
<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 10) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');
    var required = [
      'medications_prescribed_history',
      'medications_first_prescribed_details',
      'medications_current_and_desired'
    ];

    function ok(){
      for (var i=0;i<required.length;i++){
        var el = form.elements[required[i]];
        if (!el || (el.value||'').trim()==='') return false;
      }
      return true;
    }
    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }

    form.addEventListener('input', check);
    form.addEventListener('change', check);
    check();
  }
})();
</script>
<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 11) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');
    var required = [
      'addiction_impact_on_life',
      'addiction_overcome_attempts',
      'sobriety_future_impact',
      'hope_for_future_narrative'
    ];

    function ok(){
      for (var i=0;i<required.length;i++){
        var el = form.elements[required[i]];
        if (!el || (el.value||'').trim()==='') return false;
      }
      return true;
    }
    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }

    form.addEventListener('input', check);
    form.addEventListener('change', check);
    check();
  }
})();
</script>
<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 12) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');
    var required = [
      'beliefs_impact_on_life',
      'beliefs_extraordinary_experiences',
      'beliefs_shape_future'
    ];

    function ok(){
      for (var i=0;i<required.length;i++){
        var el = form.elements[required[i]];
        if (!el || (el.value||'').trim()==='') return false;
      }
      return true;
    }
    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }

    form.addEventListener('input', check);
    form.addEventListener('change', check);
    check();
  }
})();
</script>
<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 13) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');
    var required = [
      'roi_purpose_ack','roi_limited_info_ack','roi_confidentiality_ack','roi_voluntary_ack',
      'rule_dismissal_ack','rule_eval_components_ack','rule_complete_process_ack','rule_focus_webcam_ack',
      'rule_quiet_room_ack','rule_interruption_no_credit_ack','rule_substance_violation_ack','rule_restroom_5min_ack',
      'rule_payment_due_ack','rule_arrive_on_time_ack','rule_update_contact_ack','rule_call_if_absent_ack',
      'rule_no_intoxication_ack','rule_no_abusive_language_ack','rule_notify_emergencies_ack',
      'rule_program_goal_ack','rule_no_violence_ack',
      'free_for_life_group_requires_me_to_disable_any_devices_that_coul'
    ];

    function ok(){
      for (var i=0;i<required.length;i++){
        var el = form.elements[required[i]];
        if (!el || !el.checked) return false;
      }
      return true;
    }
    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }

    form.addEventListener('change', check);
    check();
  }
})();
</script>
<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 14) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');
    function ok(){
      var el = form.elements['legal_offense_summary'];
      return !!(el && (el.value || '').trim() !== '');
    }
    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }
    form.addEventListener('input', check);
    form.addEventListener('change', check);
    check();
  }
})();
</script>
<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 15) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');

    var checks = [
      'policy_received_ack',
      'rights_provider_obligations_ack',
      'rights_client_terminate_ack',
      'rights_provider_terminate_conditions',
      'rights_termination_policy_scope_ack',
      'rights_termination_circumstances_copy_ack',
      'final_attestation_ack'
    ];
    var names = ['legal_first_name','legal_last_name'];

    function ok(){
      // all consents checked
      for (var i=0;i<checks.length;i++){
        var el = form.elements[checks[i]];
        if (!el || !el.checked) return false;
      }
      // names present
      for (var j=0;j<names.length;j++){
        var t = form.elements[names[j]];
        if (!t || (t.value||'').trim()==='') return false;
      }
      return true;
    }
    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }

    form.addEventListener('change', check);
    form.addEventListener('input',  check);
    check();
  }
})();
</script>
<script>
(function(){
  var step = <?php echo (int)$step; ?>;
  var form = document.querySelector('form');
  if (!form) return;

  if (step === 16) {
    var nextBtn = document.querySelector('button[name="_action"][value="next"]');
    var names = [
      'punishment_feelings','sadness','pessimism','self_dislike','past_failure',
      'self_criticalness','loss_of_pleasure','suicidal_thoughts_or_wishes',
      'guilty_feelings','crying','agitation','irritability','loss_of_interest',
      'loss_of_interest_in_sex','indecisiveness','concentration_difficulty',
      'worthlessness','tiredness_or_fatigue','loss_of_energy',
      'changes_in_appetite','changes_in_sleeping_pattern'
    ];

    function ok(){
      for (var i=0;i<names.length;i++){
        if (!form.querySelector('input[name="'+names[i]+'"]:checked')) return false;
      }
      return true;
    }
    function check(){ if (nextBtn) nextBtn.disabled = !ok(); }
    form.addEventListener('change', check, true);
    form.addEventListener('input',  check, true);
    check();
  }
})();
</script>
<script>
(function(){
  var mk = document.querySelector('.mini-key');
  var anchor = document.getElementById('miniKeyAnchor');
  if (!mk || !anchor) return;

  // Spacer prevents layout jump when the chip becomes fixed
  var spacer = document.createElement('div');
  spacer.id = 'miniKeySpacer';
  spacer.style.height = '0px';
  mk.parentNode.insertBefore(spacer, mk.nextSibling);

  function headerHeight(){
    var hdr = document.querySelector('.hdr');
    return (hdr ? hdr.getBoundingClientRect().height : 64) + 8; // small gap
  }
  function place(){
    mk.style.setProperty('--miniKeyTop', headerHeight() + 'px');
  }
  function onScroll(){
    var threshold = anchor.getBoundingClientRect().bottom;
    var shouldFix = threshold <= headerHeight();
    if (shouldFix){
      if (!mk.classList.contains('is-fixed')){
        mk.classList.add('is-fixed');
        spacer.style.height = mk.getBoundingClientRect().height + 'px';
      }
    }else{
      if (mk.classList.contains('is-fixed')){
        mk.classList.remove('is-fixed');
        spacer.style.height = '0px';
      }
    }
  }

  place(); onScroll();
  window.addEventListener('resize', function(){ place(); onScroll(); });
  window.addEventListener('scroll', onScroll, {passive:true});
})();
</script>
<?php if ((int)$step === 17): ?>
<script>
(function(){
  var form    = document.querySelector('form');
  var nextBtn = document.querySelector('button[name="_action"][value="next"]');
  if (!form || !nextBtn) return;

  // Use the canonical list from PHP so names can't drift
  var required = <?php echo json_encode($PAGES[17]['exact']); ?>;

  function ok(){
    for (var i = 0; i < required.length; i++) {
      var n  = required[i];
      var el = form.elements[n];
      if (!el) { console.warn('BAI: missing DOM element for', n); return false; }
      var v = (el.value || '').trim();
      if (v === '') return false;
    }
    return true;
  }

  function check(){ nextBtn.disabled = !ok(); }
  form.addEventListener('change', check, true);
  form.addEventListener('input',  check, true);
  check();
})();
</script>
<?php endif; ?>
<?php if ((int)$step === 18): ?>
<script>
(function(){
  var form    = document.querySelector('form');
  var nextBtn = document.querySelector('button[name="_action"][value="next"]');
  if (!form || !nextBtn) return;

  // Use the canonical list from PHP so names can't drift
  var required = <?php echo json_encode($PAGES[18]['exact']); ?>;

  function ok(){
    for (var i = 0; i < required.length; i++) {
      var name = required[i];
      // true/false radios: require one checked per group
      if (!form.querySelector('input[name="'+name+'"]:checked')) return false;
    }
    return true;
  }

  function check(){ nextBtn.disabled = !ok(); }
  form.addEventListener('change', check, true);
  form.addEventListener('input',  check, true);

  // Initial state (respects pre-filled values)
  check();
})();
</script>
<?php endif; ?>
<?php if ((int)$step === 19): ?>
<script>
(function(){
  var form    = document.querySelector('form');
  var nextBtn = document.querySelector('button[name="_action"][value="next"]');
  if (!form || !nextBtn) return;

  var required = <?php echo json_encode($PAGES[19]['exact']); ?>;

  function ok(){
    for (var i = 0; i < required.length; i++) {
      var name = required[i];
      if (!form.querySelector('input[name="'+name+'"]:checked')) return false;
    }
    return true;
  }

  function check(){ nextBtn.disabled = !ok(); }
  form.addEventListener('change', check, true);
  form.addEventListener('input',  check, true);
  check();
})();
</script>
<?php endif; ?>
<?php if ((int)$step === 20): ?>
<script>
(function(){
  var form    = document.querySelector('form');
  var nextBtn = document.querySelector('button[name="_action"][value="next"]');
  if (!form || !nextBtn) return;

  var required = <?php echo json_encode($PAGES[20]['exact']); ?>;

  function ok(){
    for (var i = 0; i < required.length; i++) {
      var name = required[i];
      var el = form.elements[name];
      if (!el) return false;

      // support both <select> and radio groups
      if (el.tagName && el.tagName.toLowerCase() === 'select') {
        if ((el.value || '') === '') return false;
      } else {
        if (!form.querySelector('input[name="'+name+'"]:checked')) return false;
      }
    }
    return true;
  }

  function check(){ nextBtn.disabled = !ok(); }
  form.addEventListener('change', check, true);
  form.addEventListener('input',  check, true);
  check();
})();
</script>
<?php endif; ?>
<?php if ((int)$step === 21): ?>
<script>
(function(){
  var form    = document.querySelector('form');
  var nextBtn = document.querySelector('button[name="_action"][value="next"]');
  if (!form || !nextBtn) return;

  // Canonical field list from PHP so names can't drift
  var required = <?php echo json_encode($PAGES[21]['exact']); ?>;

  // Make selects required (native a11y), radios handled by group
  required.forEach(function(name){
    var el = form.elements[name];
    if (!el) return;
    if (el.tagName && el.tagName.toLowerCase() === 'select') {
      el.required = true;
    } else {
      var radios = form.querySelectorAll('input[type="radio"][name="'+name+'"]');
      if (radios.length) radios[0].required = true;
    }
  });

  function ok(){
    for (var i = 0; i < required.length; i++) {
      var name = required[i];
      var el   = form.elements[name];
      if (!el) return false;

      // Support both <select> and radio groups
      if (el.tagName && el.tagName.toLowerCase() === 'select') {
        if ((el.value || '') === '') return false;                // must not be the placeholder
      } else {
        if (!form.querySelector('input[type="radio"][name="'+name+'"]:checked')) return false;
      }
    }
    return true;
  }

  function check(){
    var valid = ok();
    nextBtn.disabled = !valid;
    nextBtn.setAttribute('aria-disabled', String(!valid));
    nextBtn.classList.toggle('btn-disabled', !valid);
  }

  form.addEventListener('change', check, true);
  form.addEventListener('input',  check, true);
  // Initial state (respects pre-filled data/placeholders)
  if (document.readyState === 'complete') { check(); }
  else { window.addEventListener('load', check, { once:true }); }
})();
</script>
<?php endif; ?>
<?php if ((int)$step === 22): ?>
<script>
(function(){
  var form    = document.querySelector('form');
  var nextBtn = document.querySelector('button[name="_action"][value="next"]');
  if (!form || !nextBtn) return;

  var required = <?php echo json_encode($PAGES[22]['exact']); ?>;

  // Make selects required (and one radio per group if using radios)
  required.forEach(function(name){
    var el = form.elements[name];
    if (!el) return;
    if (el.tagName && el.tagName.toLowerCase() === 'select') {
      el.required = true;
    } else {
      var r = form.querySelectorAll('input[type="radio"][name="'+name+'"]');
      if (r.length) r[0].required = true;
    }
  });

  function ok(){
    for (var i=0;i<required.length;i++){
      var name = required[i];
      var el   = form.elements[name];
      if (!el) return false;

      if (el.tagName && el.tagName.toLowerCase() === 'select') {
        if ((el.value||'') === '') return false; // still on placeholder
      } else {
        if (!form.querySelector('input[type="radio"][name="'+name+'"]:checked')) return false;
      }
    }
    return true;
  }

  function check(){
    var valid = ok();
    nextBtn.disabled = !valid;
    nextBtn.setAttribute('aria-disabled', String(!valid));
    nextBtn.classList.toggle('btn-disabled', !valid);
  }

  form.addEventListener('change', check, true);
  form.addEventListener('input',  check, true);
  if (document.readyState === 'complete') { check(); }
  else { window.addEventListener('load', check, {once:true}); }
})();
</script>
<?php endif; ?>
<?php if ((int)$step === 23): ?>
<script>
(function(){
  var form    = document.querySelector('form');
  var nextBtn = document.querySelector('button[name="_action"][value="next"]');
  if (!form || !nextBtn) return;

  var required = <?php echo json_encode($PAGES[23]['exact']); ?>;

  // Mark each radio group as required for native a11y
  required.forEach(function(name){
    var first = form.querySelector('input[type="radio"][name="'+name+'"]');
    if (first) first.required = true;
  });

  function ok(){
    for (var i=0;i<required.length;i++){
      var name = required[i];
      if (!form.querySelector('input[type="radio"][name="'+name+'"]:checked')) return false;
    }
    return true;
  }

  function check(){
    var valid = ok();
    nextBtn.disabled = !valid;
    nextBtn.setAttribute('aria-disabled', String(!valid));
    nextBtn.classList.toggle('btn-disabled', !valid);
  }

  form.addEventListener('change', check, true);
  form.addEventListener('input',  check, true);
  if (document.readyState === 'complete') { check(); }
  else { window.addEventListener('load', check, {once:true}); }
})();
</script>
<?php endif; ?>
<?php if ((int)$step === 24): ?>
<script>
(function(){
  var form    = document.querySelector('form');
  var nextBtn = document.querySelector('button[name="_action"][value="next"]');
  if (!form || !nextBtn) return;

  var required = <?php echo json_encode($PAGES[24]['exact']); ?>;

  // Make selects required (and radios if you switch UI)
  required.forEach(function(name){
    var el = form.elements[name];
    if (!el) return;
    if (el.tagName && el.tagName.toLowerCase() === 'select') {
      el.required = true;
    } else {
      var r = form.querySelectorAll('input[type="radio"][name="'+name+'"]');
      if (r.length) r[0].required = true;
    }
  });

  function ok(){
    for (var i=0;i<required.length;i++){
      var name = required[i];
      var el   = form.elements[name];
      if (!el) return false;

      if (el.tagName && el.tagName.toLowerCase() === 'select') {
        if ((el.value||'') === '') return false; // still on placeholder
      } else if (!form.querySelector('input[type="radio"][name="'+name+'"]:checked')) {
        return false;
      }
    }
    return true;
  }

  function check(){
    var valid = ok();
    nextBtn.disabled = !valid;
    nextBtn.setAttribute('aria-disabled', String(!valid));
    nextBtn.classList.toggle('btn-disabled', !valid);
  }

  form.addEventListener('change', check, true);
  form.addEventListener('input',  check, true);
  if (document.readyState === 'complete') { check(); }
  else { window.addEventListener('load', check, {once:true}); }
})();
</script>
<?php endif; ?>
<?php if ((int)$step === 25): ?>
<script>
(function(){
  var form    = document.querySelector('form');
  var nextBtn = document.querySelector('button[name="_action"][value="next"]');
  if (!form || !nextBtn) return;

  var required = <?php echo json_encode($PAGES[25]['exact']); ?>;

  // Make selects required (and radios if UI changes)
  required.forEach(function(name){
    var el = form.elements[name];
    if (!el) return;
    if (el.tagName && el.tagName.toLowerCase() === 'select') {
      el.required = true;
    } else {
      var r = form.querySelectorAll('input[type="radio"][name="'+name+'"]');
      if (r.length) r[0].required = true;
    }
  });

  function ok(){
    for (var i=0;i<required.length;i++){
      var name = required[i];
      var el   = form.elements[name];
      if (!el) return false;

      if (el.tagName && el.tagName.toLowerCase() === 'select') {
        if ((el.value||'') === '') return false; // still on placeholder
      } else if (!form.querySelector('input[type="radio"][name="'+name+'"]:checked')) {
        return false;
      }
    }
    return true;
  }

  function check(){
    var valid = ok();
    nextBtn.disabled = !valid;
    nextBtn.setAttribute('aria-disabled', String(!valid));
    nextBtn.classList.toggle('btn-disabled', !valid);
  }

  form.addEventListener('change', check, true);
  form.addEventListener('input',  check, true);
  if (document.readyState === 'complete') { check(); }
  else { window.addEventListener('load', check, {once:true}); }
})();
</script>
<?php endif; ?>
<?php if ((int)$step === 26): ?>
<script>
(function(){
  var form     = document.querySelector('form');
  var submitBtn= document.querySelector('button[type="submit"], button[name="_action"][value="submit"]')
                || document.querySelector('button[name="_action"][value="next"]'); // if your button is named "SUBMIT"
  if (!form || !submitBtn) return;

  function ok(){
    var f = (form.elements['sign_first_name'] || {}).value || '';
    var l = (form.elements['sign_last_name']  || {}).value || '';
    var d = (form.elements['timestamp']       || {}).value || '';
    return f.trim() !== '' && l.trim() !== '' && d.trim() !== '';
  }

  function check(){
    var valid = ok();
    submitBtn.disabled = !valid;
    submitBtn.setAttribute('aria-disabled', String(!valid));
    submitBtn.classList.toggle('btn-disabled', !valid);
  }

  form.addEventListener('input',  check, true);
  form.addEventListener('change', check, true);
  if (document.readyState === 'complete') { check(); }
  else { window.addEventListener('load', check, {once:true}); }
})();
</script>
<?php endif; ?>


</body>
</html>
