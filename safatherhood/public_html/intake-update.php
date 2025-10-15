<?php
// intake-update.php — flexible editor for intake_packet
// - No required fields
// - Auto-fill empty dates with signature_date (except DOB/victim_dob/created_at)
// - Default consent booleans to Yes if empty
// - Highlight missing fields (yellow)
// - Render every column from DESCRIBE intake_packet (unmapped go to "Other")

include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';

$db = isset($link) ? $link : (isset($con) ? $con : null);
if (!$db) { die('Database connection not found.'); }

// ---------- Helpers ----------
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

function mysql_type_to_ui($name, $type) {
  $t = strtolower($type);
  // explicit by type
  if (strpos($t,'enum(') === 0) return 'enum';
  if (strpos($t,'tinyint(1)') === 0) return 'yesno';
  if (strpos($t,'int(') === 0 || strpos($t,'bigint(') === 0 || strpos($t,'smallint(') === 0) return 'int';
  if (strpos($t,'decimal') === 0 || strpos($t,'float') === 0 || strpos($t,'double') === 0) return 'number';
  if (strpos($t,'datetime') === 0 || strpos($t,'timestamp') === 0) return 'datetime';
  if (strpos($t,'date') === 0) return 'date';
  if (strpos($t,'text') !== false || preg_match('/(_desc|_details|_history|_reason|_notes?)$/i', $name)) return 'textarea';
  return 'text';
}

function mysql_type_is_numeric($type) {
  $t = strtolower($type);
  return (strpos($t,'int') !== false) || (strpos($t,'decimal') === 0) || (strpos($t,'float') === 0) || (strpos($t,'double') === 0);
}

// ---------- Inputs ----------
$intake_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($intake_id <= 0) die('Missing or invalid intake id.');

// ---------- DB metadata ----------
$DB_COLS = [];  // field => mysql type
$DB_ENUM = [];  // field => [enum options]
if ($r = $db->query("DESCRIBE intake_packet")) {
  while ($d = $r->fetch_assoc()) {
    $DB_COLS[$d['Field']] = strtolower($d['Type']);
    if (strpos($DB_COLS[$d['Field']], "enum(") === 0) {
      $opts = substr($DB_COLS[$d['Field']], 5, -1);
      $DB_ENUM[$d['Field']] = array_map(static function($s){
        return trim($s, " '\"");
      }, explode(',', $opts));
    }
  }
  $r->free();
}
if (!$DB_COLS) die('Could not describe intake_packet.');

// Which dates should NOT auto-fill from signature_date
$DATE_EXCLUDE = ['date_of_birth','victim_dob','created_at'];

// ---------- Fetch current row ----------
$stmt = mysqli_prepare($db, "SELECT * FROM intake_packet WHERE intake_id = ?");
mysqli_stmt_bind_param($stmt, "i", $intake_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$orig = $res ? $res->fetch_assoc() : null;
mysqli_free_result($res);
mysqli_stmt_close($stmt);
if (!$orig) die('Intake not found.');

// ---------- Mappings ----------
$PROGRAM_MAP = [1=>'Anger Management', 2=>'BIPP (male)', 3=>'BIPP (female)', 4=>'Theft Intervention'];
$REFERRAL_MAP = [0=>'Other',1=>'Probation',2=>'Parole',3=>'Pretrial',4=>'CPS',5=>'Attorney'];


// Map of race_id => label (matches intake.php)
$RACE_MAP = [
    0 => 'Hispanic',
    1 => 'African American',
    2 => 'Asian',
    3 => 'Middle Easterner',
    4 => 'Caucasian',
    5 => 'Other',
];

// Map of gender_id => label (Lakeview)
$GENDER_MAP = [
    1 => 'Other',
    2 => 'Male',
    3 => 'Female',
];


// Render <option> tags for a <select>
if (!function_exists('render_options_from_map')) {
    function render_options_from_map(array $map, $selected): void {
        foreach ($map as $val => $label) {
            $isSel = ((string)$val === (string)$selected) ? ' selected' : '';
            echo '<option value="'.h($val).'"'.$isSel.'>'.h($label).'</option>';
        }
    }
}


// VTA item labels (1..28)
$VTA_ITEMS = [
  "Called her a name and/or criticized her.",
  "Tried to keep her from doing something she wanted to do (e.g., going out with friends, going to meetings).",
  "Gave her angry stares or looks.",
  "Prevented her from having money for her own use.",
  "Ended a discussion with her and made the decision yourself.",
  "Threatened to hit or throw something at her.",
  "Pushed, grabbed or shoved her.",
  "Put down her family and friends.",
  "Accused her of paying too much attention to someone or something else.",
  "Put her on an allowance.",
  "Used the children to threaten her (e.g., said she would lose custody or you would leave town with the children).",
  "Became upset because dinner, housework or laundry was not ready when you wanted it or done the way you thought it should be.",
  "Said things to scare her (e.g., said something ‘bad’ would happen or threatened suicide).",
  "Slapped, hit or punched her.",
  "Made her do something humiliating or degrading (e.g., made her beg for forgiveness or ask permission).",
  "Checked up on her (e.g., listened to phone calls, checked car mileage, called repeatedly at work).",
  "Drove recklessly when she was in the car.",
  "Pressured her to have sex in a way she didn’t like or want.",
  "Refused to do housework or child care.",
  "Threatened her with a knife, gun or other weapon.",
  "Told her she was a bad parent.",
  "Stopped her or tried to stop her from going to work or school.",
  "Threw, hit, kicked or smashed something.",
  "Kicked her.",
  "Physically forced her to have sex.",
  "Threw her around.",
  "Physically attacked the sexual parts of her body.",
  "Choked or strangled her."
];

// VTA response labels (value => label)
$VTA_LABELS = [
  'N' => 'Never',
  'R' => 'Rarely',
  'O' => 'Occasionally',
  'F' => 'Frequently',
  'V' => 'Very Frequently',
];


// Consent field heuristic (tinyint(1) and name starts with consent_ or agree_)
function is_consent_bool($name, $type) {
  return (strpos(strtolower($type), 'tinyint(1)') === 0) && preg_match('/^(consent|agree)_/i', $name);
}

// ---------- Handle POST (Save) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (function_exists('csrf_check')) { csrf_check(); }

  $post = $_POST;

  // Auto-fill missing dates with signature_date (except DOBs/created_at)
  $sig = trim((string)($post['signature_date'] ?? ''));
  if ($sig !== '') {
    foreach ($DB_COLS as $c => $t) {
      if (in_array($c, $DATE_EXCLUDE, true)) continue;
      if (strpos($t,'date') !== false || strpos($t,'datetime') === 0 || strpos($t,'timestamp') === 0) {
        if (!array_key_exists($c,$post) || trim((string)$post[$c]) === '') {
          $post[$c] = $sig;
        }
      }
    }
  }

  // Default consents to Yes when empty
  foreach ($DB_COLS as $c => $t) {
    if (is_consent_bool($c, $t)) {
      if (!array_key_exists($c,$post) || $post[$c] === '') {
        $post[$c] = '1';
      }
    }
  }

  // Build dynamic UPDATE
  $sets = [];
  $vals = [];
  $types = '';

  foreach ($DB_COLS as $c => $t) {
    if ($c === 'intake_id') continue; // PK
    if (!array_key_exists($c, $post)) continue; // keep as-is if not present (shouldn't happen)
    $v = $post[$c];

    // Normalize empties: for date/datetime/numeric -> NULL; for strings -> ''
    $isDateLike = (strpos($t,'date') !== false) || (strpos($t,'datetime') === 0) || (strpos($t,'timestamp') === 0);
    if ($v === '' || $v === null) {
      if ($isDateLike || mysql_type_is_numeric($t)) {
        $v = null;
      } else {
        $v = ''; // varchar/text stay empty string
      }
    }

    $sets[] = "$c = ?";
    if (strpos($t,'tinyint(1)') === 0 || strpos($t,'int(') === 0 || strpos($t,'bigint(') === 0 || strpos($t,'smallint(') === 0) {
      $types .= is_null($v) ? 's' : 'i'; // mysqli doesn't have NULL type; passing null with 's' is okay
      $vals[] = is_null($v) ? null : (int)$v;
    } elseif (strpos($t,'decimal') === 0 || strpos($t,'float') === 0 || strpos($t,'double') === 0) {
      $types .= is_null($v) ? 's' : 'd';
      $vals[] = is_null($v) ? null : (float)$v;
    } else {
      $types .= 's';
      $vals[] = $v;
    }
  }

  $types .= 'i';
  $vals[] = $intake_id;

  if ($sets) {
    $sql = "UPDATE intake_packet SET ".implode(", ", $sets)." WHERE intake_id = ?";
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) { die('Prepare failed: '.mysqli_error($db)); }

    // bind_param requires references
    $bindArgs = [];
    $bindArgs[] = & $types;
    for ($i=0; $i<count($vals); $i++) { $bindArgs[] = & $vals[$i]; }
    call_user_func_array([$stmt, 'bind_param'], $bindArgs);

    if (!mysqli_stmt_execute($stmt)) {
      die('Update failed: '.mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    // Redirect
    if (isset($_POST['save_view'])) {
      header("Location: intake-review.php?id=".$intake_id."&ok=updated");
    } else {
      header("Location: intake-update.php?id=".$intake_id."&ok=updated");
    }
    exit;
  }
}

// Reload latest after POST or initial view
$stmt = mysqli_prepare($db, "SELECT * FROM intake_packet WHERE intake_id = ?");
mysqli_stmt_bind_param($stmt, "i", $intake_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = $res ? $res->fetch_assoc() : null;
mysqli_free_result($res);
mysqli_stmt_close($stmt);
if (!$row) $row = $orig;

// ---------- Section field lists (known, human-friendly) ----------
$SECTIONS = [
  // Page 1: Identity / quick facts
  'Intake Summary' => [
    'first_name','last_name','date_of_birth','gender_id','race_id',
    'program_id','id_number','email','phone_cell',
    'intake_date','signature_date','created_at'
  ],

  'Identity & Contact' => [
    'address_street','address_city','address_state','address_zip',
    'address_country','birth_city'
  ],

  // Basic socioeconomics used on Page 4 in your intake
  'Employment & Education' => [
    'education_level','occupation','employed','employer'
  ],

  'Military & Emergency' => [
    'military_branch','military_date',
    'emergency_name','emergency_phone','emergency_relation'
  ],

  // Referral + reason text blocks
  'Referral' => [
    'referral_type_id','referring_officer_name','referring_officer_email','referring_officer_phone','referring_cause_number',
    'reasons','other_reason_text'
  ],

  // Page 4 – household/children/CPS
  'Children & CPS' => [
    'living_situation','marital_status',
    'has_children','children_live_with_you','children_names_ages',
    'child_abuse_physical','child_abuse_sexual','child_abuse_emotional','child_abuse_neglect',
    'discipline_desc',
    'cps_notified','cps_care','cps_case_year_status','cps_caseworker_contact'
  ],

  // Page 5 – substance use
  'Substance Use' => [
    'alcohol_past','alcohol_current','alcohol_frequency','alcohol_current_details','alcohol_during_abuse',
    'drug_past','drug_current','drug_current_details','drug_during_abuse','drug_past_details',
    'last_substance_use'
  ],

  // Page 6 – mental health
  'Mental & Medical' => [
    'counseling_history','counseling_reason',
    'depressed_currently','depression_reason','attempted_suicide','suicide_last_attempt',
    'mental_health_meds','mental_meds_list','mental_doctor_name',
    'sexual_abuse_history','head_trauma_history','head_trauma_desc'
  ],

  // Violence & weapons
  'Violence & Weapons' => [
    'weapon_possession_history','weapon_possession_details',
    'abuse_trauma_history','violent_incident_desc'
  ],

  // Page 7 – victim info blocks
  'Victim Information (Page 7)' => [
    'focus_on_actions','long_term_assault_thoughts',
    'victim_contact_provided','live_with_victim','children_with_victim',
    'children_live_with_you_p7','children_live_with_you_p7_other',
    'victim_relationship','victim_relationship_other',
    'victim_first_name','victim_last_name','victim_gender','victim_age',
    'victim_phone','victim_email',
    'victim_address','victim_city','victim_state','victim_zip','victim_dob'
  ],

  // Page 7 – single-name release + sworn statement
  'Page 7 Release' => [
    'consent_release_sig_name','consent_release_signed_date',
    'sworn_sig_name','sworn_signed_date'
  ],

  // Pages 1 and 8a–8e – all consent toggles/dates/signatures (including 8c start fields and 8d initials)
  'Consents (Pages 1 & 8a–8e)' => [
    // Page 1
    'confidentiality_sig_p1','confidentiality_date_p1',
    'consent_confidentiality','consent_disclosure','consent_partner_info',
    'consent_program_agreement','consent_responsibility','consent_virtual_rules','consent_policy_termination',

    // 8a — Release to agencies
    'consent8a_referral_type','consent8a_signature','consent8a_date','consent8a_agree',

    // 8b — Disclosure partner field + sig/date
    'victim_relationship_8b','disclosure_signature_8b','disclosure_date_8b',

    // 8c — Program agreement (start date, DOW, time + two sign/date lines)
    'start_date_8c','start_dow_8c','start_time_8c',
    'program_signature_8ca','program_date_8ca',
    'program_signature_8cb','program_date_8cb',

    // 8d — Virtual group rules (initial each + sign/date)
    'vgr_initial_1','vgr_initial_2','vgr_initial_3','vgr_initial_4','vgr_initial_5',
    'vgr_initial_6','vgr_initial_7','vgr_initial_8','vgr_initial_9','vgr_initial_10',
    'vgr_initial_11','vgr_initial_12','vgr_initial_13','vgr_initial_14','vgr_initial_15',
    'vgr_initial_16','vgr_initial_17','vgr_initial_18','vgr_initial_19',
    'vgr_signature_8d','vgr_date_8d',

    // 8e — Policy & termination
    'termination_signature_8e','termination_date_8e',
  ],

  // Page 7b — VTA
  'VTA (Victim Treatment Assessment)' => array_merge(
    ['vta_partner_name','vta_date','vta_signature'],
    array_map(fn($i) => sprintf('vta_b%02d',$i), range(1,28))
  ),

  'Offense & Program' => [
    'offense_reason','offense_description','personal_goal','counselor_name','chosen_group_time'
  ],

  'Workflow & Metadata' => [
    'digital_signature','additional_charge_dates','additional_charge_details',
    'packet_complete','staff_verified','verified_by','verified_at',
    'imported_to_client','imported_client_id'
  ],
];


// Collect known fields
$KNOWN = [];
foreach ($SECTIONS as $list) { foreach ($list as $c) { $KNOWN[$c] = true; } }
// VTA b01..b28 are “known” too
for ($i=1; $i<=28; $i++) { $KNOWN[sprintf('vta_b%02d',$i)] = true; }

// Auto-append any remaining columns (except PK)
$UNMAPPED = [];
foreach ($DB_COLS as $c => $t) {
  if ($c === 'intake_id') continue;
  if (!isset($KNOWN[$c])) $UNMAPPED[] = $c;
}
if ($UNMAPPED) {
  $SECTIONS['Other (Unmapped from DB)'] = $UNMAPPED;
}

// ---------- UI helpers ----------
function labelize($name) {
  $name = str_replace('_', ' ', $name);
  $name = preg_replace('/\bid\b/i', 'ID', $name);
  return ucwords($name);
}

function render_input($name, $value, $type, $dbType, $enumOpts, $params=[]) {
  global $PROGRAM_MAP, $REFERRAL_MAP, $DATE_EXCLUDE, $row, $DB_COLS, $RACE_MAP, $GENDER_MAP;

  $isMissing = ($value === '' || $value === null);
  $missingClass = $isMissing ? ' missing' : '';
  $attr = '';
  // don't mark created_at as missing
  if ($name === 'created_at') $missingClass = '';

  // Default consent booleans to Yes in UI
  if ($type === 'yesno' && ($value === '' || $value === null) && is_consent_bool($name, $dbType)) {
    $value = '1';
    $isMissing = false;
    $missingClass = '';
  }

  // Pre-populate date fields with signature_date (UI) except exclusions
  if (($type === 'date' || $type === 'datetime') && ($value === '' || $value === null) && !in_array($name, $DATE_EXCLUDE, true)) {
    if (!empty($row['signature_date'])) {
      $value = $row['signature_date'];
      $isMissing = false;
      $missingClass = '';
    }
  }

  // Custom mappings (program/referral dropdowns)
  if ($name === 'program_id') {
    $options = $PROGRAM_MAP;
    $html = '<select name="program_id" class="form-control'.$missingClass.'">';
    $html .= '<option value=""></option>';
    foreach ($options as $k=>$lab) {
      $sel = ((string)$value === (string)$k) ? 'selected' : '';
      $html .= '<option value="'.h($k).'" '.$sel.'>'.h($lab).'</option>';
    }
    $html .= '</select>';
    return $html;
  }
  if ($name === 'referral_type_id') {
    $options = $REFERRAL_MAP;
    $html = '<select name="referral_type_id" class="form-control'.$missingClass.'">';
    $html .= '<option value=""></option>';
    foreach ($options as $k=>$lab) {
      $sel = ((string)$value === (string)$k) ? 'selected' : '';
      $html .= '<option value="'.h($k).'" '.$sel.'>'.h($lab).'</option>';
    }
    $html .= '</select>';
    return $html;
  }
  if ($name === 'race_id') {
    // Render Race/Ethnicity as a dropdown with numeric IDs and text labels
    $options = $RACE_MAP; // [0=>'Hispanic', 1=>'African American', ...]
    $html = '<select name="race_id" class="form-control'.$missingClass.'">';
    $html .= '<option value=""></option>';
    foreach ($options as $k => $lab) {
      $sel = ((string)$value === (string)$k) ? 'selected' : '';
      $html .= '<option value="'.h($k).'" '.$sel.'>'.h($lab).'</option>';
    }
    $html .= '</select>';
    return $html;
  }
  if ($name === 'gender_id') {
    // Render Gender as a dropdown with numeric IDs and text labels
    $options = $GENDER_MAP; // [1=>'Other', 2=>'Male', 3=>'Female']
    $html = '<select name="gender_id" class="form-control'.$missingClass.'">';
    $html .= '<option value=""></option>';
    foreach ($options as $k => $lab) {
        $sel = ((string)$value === (string)$k) ? 'selected' : '';
        $html .= '<option value="'.h($k).'" '.$sel.'>'.h($lab).'</option>';
    }
    $html .= '</select>';
    return $html;
}



  // Type-specific rendering
  switch ($type) {
    case 'yesno':
      $html  = '<select name="'.h($name).'" class="form-control yesno-select'.$missingClass.'">';
      $html .= '<option value=""></option>';
      $html .= '<option value="1" '.($value==='1'?'selected':'').'>Yes</option>';
      $html .= '<option value="0" '.($value==='0'?'selected':'').'>No</option>';
      $html .= '</select>';
      return $html;

    case 'enum':
        $opts = $enumOpts ?: [];
        $html  = '<select name="'.h($name).'" class="form-control'.$missingClass.'">';
        $html .= '<option value=""></option>';
        foreach ($opts as $opt) {
            // case-insensitive compare so stored 'n' also selects 'N'
            $sel = (strcasecmp((string)$value, (string)$opt) === 0) ? 'selected' : '';
            // pretty labels for VTA items
            if (strpos($name, 'vta_b') === 0 && isset($GLOBALS['VTA_LABELS'][strtoupper($opt)])) {
            $label = $GLOBALS['VTA_LABELS'][strtoupper($opt)];
            } else {
            $label = $opt;
            }
            $html .= '<option value="'.h($opt).'" '.$sel.'>'.h($label).'</option>';
        }
        $html .= '</select>';
        return $html;


    case 'int':
      return '<input type="number" step="1" class="form-control'.$missingClass.'" name="'.h($name).'" value="'.h($value).'">';

    case 'number':
      return '<input type="number" class="form-control'.$missingClass.'" name="'.h($name).'" value="'.h($value).'">';

    case 'datetime':
      // format to HTML5 datetime-local (YYYY-MM-DDTHH:MM)
      $val = '';
      if (!empty($value) && $value !== '0000-00-00 00:00:00') {
        $ts = strtotime($value);
        if ($ts) $val = date('Y-m-d\TH:i', $ts);
      }
      return '<input type="datetime-local" class="form-control'.$missingClass.'" name="'.h($name).'" value="'.h($val).'">';

    case 'date':
      $val = '';
      if (!empty($value) && $value !== '0000-00-00') {
        $ts = strtotime($value);
        $val = $ts ? date('Y-m-d', $ts) : $value;
      }
      return '<input type="date" class="form-control'.$missingClass.'" name="'.h($name).'" value="'.h($val).'">';

    case 'textarea':
      return '<textarea class="form-control'.$missingClass.'" name="'.h($name).'" rows="2">'.h($value).'</textarea>';

    case 'text':
    default:
      return '<input type="text" class="form-control'.$missingClass.'" name="'.h($name).'" value="'.h($value).'">';
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NotesAO - Intake Update</title>

  <!-- Favicons -->
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

  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
        integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk"
        crossorigin="anonymous">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0-14/css/all.min.css"
        integrity="sha512-YVm6dLGBSj6KG3uUb1L5m25JXXYrd9yQ1P7RKDSstzYiPxI2vYLCCGyfrlXw3YcN/EM3UJ/IAqsCmfdc6pk/Tg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

  <style>
    /* Layout & spacing (match review styling) */
    .section-card + .section-card { margin-top: 1rem; }
    .kv { display:flex; align-items:center; margin-bottom:.25rem; }
    .kv .k { flex:0 0 180px; max-width:180px; color:#6c757d; }
    .kv .v { flex:1 1 auto; min-width:0; overflow-wrap:anywhere; word-break:break-word; }
    .table-sm td, .table-sm th { padding:.35rem .5rem; }
    .actions-toolbar { margin-bottom:1rem; }
    .missing { background-color:#fff3cd !important; }

    @media (max-width: 767px) {
      .kv { display:block; }
      .kv .k { max-width:none; margin-bottom:.15rem; }
    }

    @media print {
      nav, .no-print, .modal { display: none !important; }
      body { padding: 0; }
      .card { border: 0; box-shadow: none; page-break-inside: avoid; }
      .card-header { background: #fff !important; border-bottom: 1px solid #000 !important; }
      .card-body { padding-top: .5rem; }
      .table { border-color: #000; }
      .table th, .table td { border-color: #000 !important; }
      @page { margin: 0.5in; }
    }
  </style>
</head>
<?php require_once('navbar.php'); ?>
<body>
<section class="pt-5">
  <div class="container-fluid">

    <?php if (!empty($_GET['ok']) && $_GET['ok']==='updated'): ?>
      <div class="alert alert-success no-print">Saved.</div>
    <?php endif; ?>

    <!-- Toolbar -->
    <div class="d-flex justify-content-between align-items-center actions-toolbar no-print">
      <div>
        <a href="intake-index.php" class="btn btn-light"><i class="far fa-list-alt"></i> Back to Index</a>
        <a href="intake-review.php?id=<?php echo (int)$intake_id; ?>" class="btn btn-secondary"><i class="far fa-eye"></i> Review</a>
      </div>
      <div>
        <button form="updateForm" type="submit" name="save" class="btn btn-primary">
          <i class="far fa-save"></i> Save
        </button>
        <button form="updateForm" type="submit" name="save_view" value="1" class="btn btn-success">
          <i class="fas fa-check"></i> Save & View
        </button>
      </div>
    </div>

    <form id="updateForm" method="post">
      <?php if (function_exists('csrf_field')) csrf_field(); ?>

      <!-- Note -->
      <div class="alert alert-info no-print">
        <strong>Heads up:</strong>
        Empty date fields will auto-fill with <em>Signature Date</em> on save (except Date of Birth / Victim DOB / Created At).
        Consent toggles default to <em>Yes</em> if left empty. Missing fields are highlighted in yellow.
      </div>

      <!-- Intake ID (read-only display) -->
      <div class="card section-card">
        <div class="card-header"><strong>Record</strong></div>
        <div class="card-body">
          <div class="kv">
            <div class="k">Intake ID</div>
            <div class="v">
              <input type="text" class="form-control" value="<?php echo (int)$row['intake_id']; ?>" readonly>
              <input type="hidden" name="intake_id" value="<?php echo (int)$row['intake_id']; ?>">
            </div>
          </div>
        </div>
      </div>

      <?php
      // Render standard sections
      foreach ($SECTIONS as $section => $cols) {
        // VTA custom block
        if ($section === 'VTA (Victim Treatment Assessment)') {
          ?>
          <div class="card section-card">
            <div class="card-header"><strong><?php echo h($section); ?></strong></div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-4">
                  <div class="kv"><div class="k"><?php echo h(labelize('vta_partner_name')); ?></div>
                    <div class="v"><?php
                      echo render_input('vta_partner_name', $row['vta_partner_name'] ?? '', mysql_type_to_ui('vta_partner_name', $DB_COLS['vta_partner_name'] ?? 'varchar(255)'), $DB_COLS['vta_partner_name'] ?? 'varchar(255)', $DB_ENUM['vta_partner_name'] ?? []);
                    ?></div></div>
                </div>
                <div class="col-md-4">
                  <div class="kv"><div class="k"><?php echo h(labelize('vta_date')); ?></div>
                    <div class="v"><?php
                      echo render_input('vta_date', $row['vta_date'] ?? '', mysql_type_to_ui('vta_date', $DB_COLS['vta_date'] ?? 'date'), $DB_COLS['vta_date'] ?? 'date', $DB_ENUM['vta_date'] ?? []);
                    ?></div></div>
                </div>
                <div class="col-md-4">
                  <div class="kv"><div class="k"><?php echo h(labelize('vta_signature')); ?></div>
                    <div class="v"><?php
                      echo render_input('vta_signature', $row['vta_signature'] ?? '', mysql_type_to_ui('vta_signature', $DB_COLS['vta_signature'] ?? 'varchar(255)'), $DB_COLS['vta_signature'] ?? 'varchar(255)', $DB_ENUM['vta_signature'] ?? []);
                    ?></div></div>
                </div>
              </div>

              <div class="table-responsive mt-2">
                <table class="table table-sm table-bordered mb-0">
                  <thead><tr><th style="width:60px;">#</th><th>Item</th><th style="width:200px;">Response</th></tr></thead>
                  <tbody>
                  <?php
                  // Determine VTA options
                  // Determine VTA options: use DB ENUM when present; otherwise N/R/O/F/V
                  $vtaEnum = (isset($DB_ENUM['vta_b01']) && $DB_ENUM['vta_b01'])
                          ? $DB_ENUM['vta_b01']
                          : ['N','R','O','F','V'];


                  for ($i=1; $i<=28; $i++):
                    $col = sprintf('vta_b%02d',$i);
                    $val = $row[$col] ?? '';
                    $ui  = mysql_type_to_ui($col, $DB_COLS[$col] ?? 'enum');
                    ?>
                    <tr>
                      <td><?php echo $i; ?></td>
                      <td><?php echo h($VTA_ITEMS[$i-1] ?? ('Item '.$i)); ?></td>
                      <td>
                        <?php
                        if ($ui === 'enum') {
                          echo render_input($col, $val, 'enum', $DB_COLS[$col] ?? 'enum', $DB_ENUM[$col] ?? $vtaEnum);
                        } else {
                          // Fallback as select with N/R/O/F/V
                          echo render_input($col, $val, 'enum', $DB_COLS[$col] ?? 'enum', $vtaEnum);
                        }
                        ?>
                      </td>
                    </tr>
                  <?php endfor; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <?php
          continue;
        }

        // Normal non-VTA sections
        ?>
        <div class="card section-card">
          <div class="card-header"><strong><?php echo h($section); ?></strong></div>
          <div class="card-body">
            <div class="row">
              <?php
              $colCount = 0;
              foreach ($cols as $c) {
                // some columns might not exist in DB (safe-guard)
                if (!array_key_exists($c, $DB_COLS)) continue;

                $val = $row[$c] ?? '';
                $ui  = mysql_type_to_ui($c, $DB_COLS[$c]);
                $opts = ($ui==='enum') ? ($DB_ENUM[$c] ?? []) : [];

                // created_at display read-only
                $readonly = ($c === 'created_at');

                echo '<div class="col-md-4">';
                echo '<div class="kv"><div class="k">'.h(labelize($c)).'</div><div class="v">';
                if ($readonly) {
                  echo '<input type="text" class="form-control" value="'.h($val).'" readonly>';
                } else {
                  echo render_input($c, $val, $ui, $DB_COLS[$c], $opts);
                }
                echo '</div></div>';
                echo '</div>';

                $colCount++;
              }
              ?>
            </div>
          </div>
        </div>
        <?php
      }

      // Render unmapped fields, if any (in a compact table)
      
      ?>

    </form>
  </div>
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"
        integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="
        crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5nzZ3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>

<script>
(function($){
  // Live mark missing inputs
  function markMissing($root){
    $root.find('[name]').each(function(){
      var v = $(this).val();
      if (v == null) v = '';
      if (typeof v === 'string') v = v.trim();
      // never highlight created_at
      if (this.name === 'created_at') { $(this).removeClass('missing'); return; }
      $(this).toggleClass('missing', v === '');
    });
  }
  $(function(){
    var $form = $('#updateForm');
    markMissing($form);
    $form.on('input change', '[name]', function(){ markMissing($form); });
  });
})(jQuery);
</script>

</body>
</html>
