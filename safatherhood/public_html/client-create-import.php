<?php
// client-create-import.php — create a client from an intake packet (prefilled form + insert)
// Keeps client-create.php unchanged

include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// --------- Helpers ---------
function table_has_col(mysqli $con, string $table, string $col): bool {
  $db = defined('db_name') ? db_name : $con->query("SELECT DATABASE() AS db")->fetch_assoc()['db'];
  $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
  $st = $con->prepare($sql); $st->bind_param('sss', $db, $table, $col); $st->execute(); $st->store_result();
  $exists = $st->num_rows > 0; $st->close(); return $exists;
}
function get_rows(mysqli $con, string $sql): array {
  $out = []; if ($res = $con->query($sql)) { while ($r = $res->fetch_assoc()) $out[] = $r; $res->free(); }
  return $out;
}
function postv($k,$d=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function intv($k,$d=null){ return isset($_POST[$k]) && $_POST[$k] !== '' ? (int)$_POST[$k] : $d; }

function table_exists(mysqli $con, string $table): bool {
  $db = defined('db_name') ? db_name : ($con->query("SELECT DATABASE() AS db")->fetch_assoc()['db'] ?? '');
  $st = $con->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1");
  $st->bind_param('ss', $db, $table);
  $st->execute(); $st->store_result();
  $ok = $st->num_rows > 0; $st->close(); return $ok;
}

function first_existing_col(mysqli $con, string $table, array $candidates): ?string {
  $db = defined('db_name') ? db_name : ($con->query("SELECT DATABASE() AS db")->fetch_assoc()['db'] ?? '');
  if (!$db) return null;
  // build a safe FIELD() order clause
  $placeholders = implode(',', array_fill(0, count($candidates), '?'));
  $sql = "SELECT COLUMN_NAME
          FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME IN ($placeholders)
          ORDER BY FIELD(COLUMN_NAME, $placeholders) LIMIT 1";
  $st = $con->prepare($sql);
  // bind params twice (for IN list and FIELD list)
  $types = str_repeat('s', 2 + 2*count($candidates));
  $params = array_merge([$db, $table], $candidates, $candidates);
  $st->bind_param($types, ...$params);
  $st->execute(); $st->bind_result($col);
  $found = $st->fetch() ? $col : null;
  $st->close();
  return $found;
}


// --------- Prefill from intake ---------
$prefill = [];
$prefill_intake_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prefill_intake_id'])) {
  $prefill_intake_id = (int)$_POST['prefill_intake_id'];
} elseif (isset($_GET['intake_id'])) {
  $prefill_intake_id = (int)$_GET['intake_id'];
}

if ($prefill_intake_id > 0) {
  $sql = "SELECT
            intake_id,
            first_name,last_name,date_of_birth,gender_id,race_id,
            phone_cell,email,
            emergency_name,emergency_relation,emergency_phone,
            referral_type_id,
            id_number,            -- -> cause_number
            program_id,           -- may be null
            signature_date, intake_date
          FROM intake_packet WHERE intake_id = ?";
  $st = $con->prepare($sql);
  $st->bind_param('i', $prefill_intake_id);
  $st->execute();
  $rs = $st->get_result();
  if ($row = $rs->fetch_assoc()) {
    $prefill = [
      'intake_id'         => (int)$row['intake_id'],
      'first_name'        => $row['first_name'] ?? '',
      'last_name'         => $row['last_name'] ?? '',
      'date_of_birth'     => $row['date_of_birth'] ?? '',
      'gender_id'         => $row['gender_id'] ?? '',
      'race_id'           => $row['race_id'] ?? '',
      'phone_cell'        => $row['phone_cell'] ?? '',
      'email'             => $row['email'] ?? '',
      'emergency_name'    => $row['emergency_name'] ?? '',
      'emergency_relation'=> $row['emergency_relation'] ?? '',
      'emergency_phone'   => $row['emergency_phone'] ?? '',
      'referral_type_id'  => $row['referral_type_id'] ?? '',
      'cause_number'      => $row['id_number'] ?? '',
      'program_id'        => $row['program_id'] ?? '',
      'signature_date'    => $row['signature_date'] ?? '',
      'intake_date'       => $row['intake_date'] ?? '',
    ];
    // If program_id empty, derive from gender (your rule)
    if ($prefill['program_id'] === '' || $prefill['program_id'] === null) {
      if ((string)$prefill['gender_id'] === '2') $prefill['program_id'] = 2; // Men's BIPP
      if ((string)$prefill['gender_id'] === '3') $prefill['program_id'] = 3; // Women's BIPP
    }
  }
  $st->close();
}

// --------- Options for selects (column-aware) ---------
$programs = $stages = $groups = [];

// programs
if (table_exists($con, 'program')) {
  $progLabel = first_existing_col($con, 'program', ['name','program','program_name','title','label']);
  $labelSql  = $progLabel ? "`$progLabel`" : "CAST(id AS CHAR)";
  $orderSql  = $progLabel ? "`$progLabel`" : "id";
  $programs  = get_rows($con, "SELECT id, $labelSql AS name FROM program ORDER BY $orderSql");
}

// stages
if (table_exists($con, 'client_stage')) {
  $stageLabel = first_existing_col($con, 'client_stage', ['name','stage','stage_name','title','label']);
  $labelSql   = $stageLabel ? "`$stageLabel`" : "CONCAT('Stage ', id)";
  $orderSql   = $stageLabel ? "`$stageLabel`" : "id";
  $stages     = get_rows($con, "SELECT id, $labelSql AS name FROM client_stage ORDER BY $orderSql");
}

// therapy groups
if (table_exists($con, 'therapy_group')) {
  $groupLabel = first_existing_col($con, 'therapy_group', ['name','group_name','title','label']);
  $labelSql   = $groupLabel ? "`$groupLabel`" : "CONCAT('Group ', id)";
  $orderSql   = $groupLabel ? "`$groupLabel`" : "id";
  $groups     = get_rows($con, "SELECT id, $labelSql AS name FROM therapy_group ORDER BY $orderSql");
}

// case managers (from case_manager table)
$caseManagers = [];
if (table_exists($con, 'case_manager')) {
  // id, first_name, last_name, office, email, phone_number, fax
  $caseManagers = get_rows(
    $con,
    "SELECT id,
            TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) AS full_name,
            COALESCE(office,'') AS office,
            COALESCE(email,'')  AS email
     FROM case_manager
     ORDER BY last_name IS NULL, last_name='', last_name, first_name"
  );
}


// default Precontemplation stage id, if present
$precontemplation_id = null;
foreach ($stages as $s) {
  if (strcasecmp($s['name'], 'Precontemplation') === 0) { $precontemplation_id = (int)$s['id']; break; }
}


// Defaults
$DEFAULT_FEE = 25.00;
$DEFAULT_SESSIONS_PER_WEEK = 1;
$REQUIRED_SESSIONS_DEFAULTS = [
  2 => 20, // BIPP (male)
  3 => 20, // BIPP (female)
  1 => 8, // Anger/other placeholder; adjust per clinic
];

// Preselect Precontemplation stage if exists
$precontemplation_id = null;
foreach ($stages as $s) {
  if (strcasecmp($s['name'], 'Precontemplation') === 0) { $precontemplation_id = (int)$s['id']; break; }
}

// ---------- Handle CREATE ----------
$errors = [];
$created_client_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_client'])) {
  if (function_exists('csrf_check')) csrf_check();

  // Required (your list)
  $first_name = postv('first_name');
  $last_name  = postv('last_name');
  $dob        = postv('date_of_birth');
  $gender_id  = intv('gender_id');
  $race_id    = postv('race_id'); // may be mapped to ethnicity_id
  $phone      = postv('phone_cell');
  $email      = postv('email');
  $em_name    = postv('emergency_name');
  $em_phone   = postv('emergency_phone');
  $em_rel   = postv('emergency_relation');
  $emergency_contact_combined = trim(
    $em_name
    . ($em_rel !== ''   ? ' ('.$em_rel.')' : '')
    . ($em_phone !== '' ? ' '.$em_phone    : '')
  );

  $ref_type   = intv('referral_type_id');

  // Optional/derived (but you’re treating them as required)
  $program_id    = intv('program_id');
  $req_sessions  = intv('required_sessions', ($program_id && isset($REQUIRED_SESSIONS_DEFAULTS[$program_id])) ? $REQUIRED_SESSIONS_DEFAULTS[$program_id] : null);
  $sessions_wk   = intv('sessions_per_week', $DEFAULT_SESSIONS_PER_WEEK);
  $fee           = postv('fee', (string)$DEFAULT_FEE);
  $case_mgr_id   = intv('case_manager_id');
  $therapy_gid   = intv('therapy_group_id');
  $orientation   = postv('orientation_date');
  $stage_id      = intv('stage_id', $precontemplation_id);
  $cause_number  = postv('cause_number');
  $other_concerns= postv('other_concerns');
  $imported_from = intv('imported_intake_id', $prefill_intake_id ?: null);

  // Attendance days (checkboxes)
  $attends_days = [
    'attends_sunday'    => isset($_POST['attends_sunday'])    ? 1 : 0,
    'attends_monday'    => isset($_POST['attends_monday'])    ? 1 : 0,
    'attends_tuesday'   => isset($_POST['attends_tuesday'])   ? 1 : 0,
    'attends_wednesday' => isset($_POST['attends_wednesday']) ? 1 : 0,
    'attends_thursday'  => isset($_POST['attends_thursday'])  ? 1 : 0,
    'attends_friday'    => isset($_POST['attends_friday'])    ? 1 : 0,
    'attends_saturday'  => isset($_POST['attends_saturday'])  ? 1 : 0,
  ];

  // Server-side validation (no blanks)
  foreach ([
    'First name'         => $first_name,
    'Last name'          => $last_name,
    'Date of birth'      => $dob,
    'Gender'             => $gender_id,
    'Ethnicity/Race'     => $race_id,
    'Phone'              => $phone,
    'Email'              => $email,
    'Referral type'      => $ref_type,
    'Program'            => $program_id,
    'Required sessions'  => $req_sessions,
    'Sessions per week'  => $sessions_wk,
    'Group fee'          => $fee,
    'Case manager'       => $case_mgr_id,
    'Therapy group'      => $therapy_gid,
    'Orientation date'   => $orientation,
    'Stage'              => $stage_id,
    'Cause number'       => $cause_number,

  ] as $label => $val) {
    if ($val === '' || $val === null) $errors[] = "$label is required.";
  }
  if (array_sum($attends_days) === 0) {
    $errors[] = "Please select at least one Attendance Day.";
  }

  if (!$errors) {
    // Build dynamic INSERT with only columns that actually exist in client table
    $cols = [];
    $vals = [];
    $types= '';

    $candidates = [
        // identity
        'first_name'        => $first_name,
        'last_name'         => $last_name,
        'date_of_birth'     => $dob,
        'gender_id'         => $gender_id,

        // ethnicity/race (prefer ethnicity_id if present)
        (table_has_col($con,'client','ethnicity_id')
            ? 'ethnicity_id'
            : (table_has_col($con,'client','race_id') ? 'race_id' : null)) => $race_id,

        // contact
        'email'             => $email,
        (table_has_col($con,'client','phone_number')
            ? 'phone_number'
            : (table_has_col($con,'client','phone_cell')
                ? 'phone_cell'
                : (table_has_col($con,'client','phone') ? 'phone' : null))) => $phone,

        // merged emergency contact "Name (Relation) Phone"
        (table_has_col($con,'client','emergency_contact') ? 'emergency_contact' : null)
            => $emergency_contact_combined,

        // program & scheduling
        'referral_type_id'  => $ref_type,
        'program_id'        => $program_id,
        'required_sessions' => $req_sessions,
        (table_has_col($con,'client','weekly_attendance')
            ? 'weekly_attendance'
            : (table_has_col($con,'client','sessions_per_week') ? 'sessions_per_week' : null)) => $sessions_wk,
        'fee'               => $fee,

        // relationships
        (table_has_col($con,'client','case_manager_id') ? 'case_manager_id' : null) => $case_mgr_id,
        (table_has_col($con,'client','therapy_group_id') ? 'therapy_group_id' : null) => $therapy_gid,

        // status & misc
        (table_has_col($con,'client','orientation_date') ? 'orientation_date' : null) => $orientation,
        (table_has_col($con,'client','client_stage_id') ? 'client_stage_id' : (table_has_col($con,'client','stage_id') ? 'stage_id' : null)) => $stage_id,
        (table_has_col($con,'client','cause_number') ? 'cause_number' : null) => $cause_number,
        (table_has_col($con,'client','other_concerns') ? 'other_concerns' : null) => $other_concerns,
        (table_has_col($con,'client','imported_intake_id') ? 'imported_intake_id' : null) => $imported_from,

        // attendance flags
        (table_has_col($con,'client','attends_sunday')    ? 'attends_sunday'    : null) => $attends_days['attends_sunday'],
        (table_has_col($con,'client','attends_monday')    ? 'attends_monday'    : null) => $attends_days['attends_monday'],
        (table_has_col($con,'client','attends_tuesday')   ? 'attends_tuesday'   : null) => $attends_days['attends_tuesday'],
        (table_has_col($con,'client','attends_wednesday') ? 'attends_wednesday' : null) => $attends_days['attends_wednesday'],
        (table_has_col($con,'client','attends_thursday')  ? 'attends_thursday'  : null) => $attends_days['attends_thursday'],
        (table_has_col($con,'client','attends_friday')    ? 'attends_friday'    : null) => $attends_days['attends_friday'],
        (table_has_col($con,'client','attends_saturday')  ? 'attends_saturday'  : null) => $attends_days['attends_saturday'],
        table_has_col($con,'client','intake_packet') ? 'intake_packet' : null => 1,
        table_has_col($con,'client','imported_intake_id') ? 'imported_intake_id' : null =>(int)$prefill_intake_id,
    ];


    foreach ($candidates as $col => $val) {
      if ($col === null) continue;
      $cols[] = $col;
      $vals[] = $val;
      // rudimentary typing
      if (is_int($val)) $types .= 'i';
      elseif (is_float($val) || preg_match('/^\d+\.\d+$/', (string)$val)) $types .= 'd';
      else $types .= 's';
    }

    if ($cols) {
        // 1) strip any null/empty column names — and keep vals/types aligned
        $cleanCols = [];
        $cleanVals = [];
        $cleanTypes = '';
        for ($i = 0, $n = count($cols); $i < $n; $i++) {
            $c = $cols[$i];
            if ($c === null || $c === '') continue;
            $cleanCols[]  = $c;
            $cleanVals[]  = $vals[$i];
            $cleanTypes  .= $types[$i];  // $types was built 1 char per value
        }
        $cols  = $cleanCols;
        $vals  = $cleanVals;
        $types = $cleanTypes;

        // 2) backtick-quote column names to avoid reserved word collisions
        $colList = implode(',', array_map(function($c){
            return '`' . str_replace('`','``',$c) . '`';
        }, $cols));

        $placeholders = implode(',', array_fill(0, count($cols), '?'));

        // 3) final SQL (quote the table too for consistency)
        $sql = "INSERT INTO `client` ($colList) VALUES ($placeholders)";

        // Optional: quick debug while testing
        // error_log("CLIENT INSERT SQL: $sql");
        // error_log("CLIENT INSERT TYPES: $types");
        // error_log("CLIENT INSERT COLS: ".implode(',', $cols));

        $st = $con->prepare($sql);
        $st->bind_param($types, ...$vals);
        if ($st->execute()) {
            $created_client_id = $st->insert_id;

            // === Initialize ledger with Orientation charge and payment ===
            // Schema observed: ledger(id, client_id, amount, create_date, note)

            // === Auto-import Victim from intake (Page 7) ===
            if ($created_client_id && $imported_from && table_exists($con, 'victim')) {
                // Pull victim fields from the intake
                if ($sv = $con->prepare(
                    "SELECT victim_contact_provided,
                            victim_first_name, victim_last_name,
                            victim_relationship, victim_relationship_other,
                            victim_gender, victim_age, victim_dob,
                            live_with_victim, children_with_victim,
                            victim_address, victim_city, victim_state, victim_zip,
                            victim_phone, victim_email
                    FROM intake_packet
                    WHERE intake_id = ? LIMIT 1"
                )) {
                    $sv->bind_param('i', $imported_from);
                    $sv->execute();
                    $r = $sv->get_result()->fetch_assoc();
                    $sv->close();

                    if ($r && (int)($r['victim_contact_provided'] ?? 0) === 1) {
                        $vf = trim((string)($r['victim_first_name'] ?? ''));
                        $vl = trim((string)($r['victim_last_name'] ?? ''));
                        $vname = trim($vf . ' ' . $vl);

                        if ($vname !== '') {
                            // Skip if this victim already exists for this client
                            $exists = false;
                            if ($se = $con->prepare("SELECT 1 FROM victim WHERE client_id=? AND name=? LIMIT 1")) {
                                $se->bind_param('is', $created_client_id, $vname);
                                $se->execute(); $se->store_result();
                                $exists = $se->num_rows > 0;
                                $se->close();
                            }

                            if (!$exists) {
                                // Relationship (fallback to *_other)
                                $relationship = trim((string)($r['victim_relationship'] ?? ''));
                                if ($relationship === '' && !empty($r['victim_relationship_other'])) {
                                    $relationship = trim((string)$r['victim_relationship_other']);
                                }

                                // Gender & Age
                                $gender = trim((string)($r['victim_gender'] ?? '')) ?: null;

                                $age = null;
                                $age_str = trim((string)($r['victim_age'] ?? ''));
                                if ($age_str !== '' && ctype_digit($age_str)) {
                                    $age = (int)$age_str;
                                } elseif (!empty($r['victim_dob']) && $r['victim_dob'] !== '0000-00-00') {
                                    $dob = DateTime::createFromFormat('Y-m-d', $r['victim_dob']);
                                    if ($dob) { $age = (int)$dob->diff(new DateTime('today'))->y; }
                                }

                                // Living with client / children < 18
                                $living_with_client = (int)($r['live_with_victim'] ?? 0);
                                // intake_packet.children_with_victim is already numeric — copy directly
                                $children_under_18 = (isset($r['children_with_victim']) && $r['children_with_victim'] !== '')
                                    ? (int)$r['children_with_victim']
                                    : null;

                                // Contact & address
                                $address_line1 = trim((string)($r['victim_address'] ?? '')) ?: null;
                                $address_line2 = null;
                                $city          = trim((string)($r['victim_city'] ?? '')) ?: null;
                                $state         = trim((string)($r['victim_state'] ?? '')) ?: null;
                                $zip           = trim((string)($r['victim_zip'] ?? '')) ?: null;
                                $phone         = trim((string)($r['victim_phone'] ?? '')) ?: null;
                                $email         = trim((string)($r['victim_email'] ?? '')) ?: null;

                                // Insert victim
                                if ($si = $con->prepare(
                                    "INSERT INTO victim
                                    (client_id, name, relationship, gender, age,
                                      living_with_client, children_under_18,
                                      address_line1, address_line2, city, state, zip, phone, email)
                                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
                                )) {
                                    $si->bind_param(
                                        'isssiiisssssss',
                                        $created_client_id,
                                        $vname,
                                        $relationship,
                                        $gender,
                                        $age,
                                        $living_with_client,
                                        $children_under_18,
                                        $address_line1,
                                        $address_line2,
                                        $city,
                                        $state,
                                        $zip,
                                        $phone,
                                        $email
                                    );
                                    $si->execute();
                                    $si->close();
                                }
                            }
                        }
                    }
                }
            }
            // === end Auto-import Victim ===


            if ($lg = $con->prepare(
                "INSERT INTO ledger (client_id, amount, create_date, note)
                VALUES (?, ?, NOW(), ?)"
            )) {
                // 1) Charge: -25.00  Note: "orientation group fee"
                $amt1  = -25.00;
                $note1 = 'orientation group fee';
                $lg->bind_param('ids', $created_client_id, $amt1, $note1);
                $lg->execute();

                // 2) Payment: +25.00  Note: "paid orientation"
                $amt2  = 25.00;
                $note2 = 'paid orientation';
                $lg->bind_param('ids', $created_client_id, $amt2, $note2);
                $lg->execute();

                $lg->close();
            } else {
                // optional: don't block client creation, just log the error
                $errors[] = "Ledger init prepare failed: " . $con->error;
            }
        } else {
            $errors[] = "Insert failed: " . $st->error;
        }
        $st->close();


    } else {
    $errors[] = "No matching columns to insert.";
    }

  }

  // If inserted, mark intake as imported (columns vary by clinic)
  if (!$errors && $created_client_id && $imported_from) {
    $now = date('Y-m-d H:i:s');
    $set = [];
    $typesU = '';
    $valsU  = [];

    if (table_has_col($con,'intake_packet','imported_to_client')) { $set[]='imported_to_client=?'; $typesU.='i'; $valsU[] = 1; }
    if (table_has_col($con,'intake_packet','imported_client_id')) { $set[]='imported_client_id=?'; $typesU.='i'; $valsU[] = $created_client_id; }
    if (table_has_col($con,'intake_packet','imported_at'))        { $set[]='imported_at=?';        $typesU.='s'; $valsU[] = $now; }

    if ($set) {
      $sqlU = 'UPDATE intake_packet SET '.implode(', ',$set).' WHERE intake_id=?';
      $typesU .= 'i'; $valsU[] = $imported_from;
      $stU = $con->prepare($sqlU);
      $stU->bind_param($typesU, ...$valsU);
      $stU->execute();
      $stU->close();
    }
  }

  if (!$errors && $created_client_id) {
    header('Location: client-review.php?client_id='.(int)$created_client_id);
    exit;
  }
}

function fv($name, $default='') {
  return $_POST[$name] ?? ($GLOBALS['prefill'][$name] ?? $default);
}

// Attendance default unchecked
$dayFields = ['attends_sunday','attends_monday','attends_tuesday','attends_wednesday','attends_thursday','attends_friday','attends_saturday'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Client from Intake</title>
  <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
        crossorigin="anonymous">
  <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

</head>

<body>
<?php require_once 'navbar.php'; ?>
<div class="container-fluid pt-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Create Client from Intake<?php if(!empty($prefill['intake_id'])) echo ' #'.(int)$prefill['intake_id']; ?></h3>
    <div>
      <a href="intake-review.php?id=<?php echo isset($prefill['intake_id'])?(int)$prefill['intake_id']:0; ?>" class="btn btn-light">Back to Intake</a>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0"><?php foreach ($errors as $e) echo '<li>'.h($e).'</li>'; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post">
    <?php if (function_exists('csrf_field')) csrf_field(); ?>
    <input type="hidden" name="prefill_intake_id" value="<?php echo (int)($prefill['intake_id'] ?? 0); ?>">

    <div class="card mb-3">
      <div class="card-header"><strong>Identity</strong></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>First Name *</label>
            <input name="first_name" class="form-control" required value="<?php echo h(fv('first_name')); ?>">
          </div>
          <div class="form-group col-md-4">
            <label>Last Name *</label>
            <input name="last_name" class="form-control" required value="<?php echo h(fv('last_name')); ?>">
          </div>
          <div class="form-group col-md-4">
            <label>Date of Birth *</label>
            <input type="date" name="date_of_birth" class="form-control" required value="<?php echo h(fv('date_of_birth')); ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Gender *</label>
            <?php $g=(string)fv('gender_id'); ?>
            <select name="gender_id" class="form-control" required>
              <option value=""></option>
              <option value="1" <?php echo $g==='1'?'selected':''; ?>>Other</option>
              <option value="2" <?php echo $g==='2'?'selected':''; ?>>Male</option>
              <option value="3" <?php echo $g==='3'?'selected':''; ?>>Female</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Ethnicity/Race *</label>
            <?php $r=(string)fv('race_id'); ?>
            <select name="race_id" class="form-control" required>
              <option value=""></option>
              <option value="0" <?php echo $r==='0'?'selected':''; ?>>Hispanic</option>
              <option value="1" <?php echo $r==='1'?'selected':''; ?>>African American</option>
              <option value="2" <?php echo $r==='2'?'selected':''; ?>>Asian</option>
              <option value="3" <?php echo $r==='3'?'selected':''; ?>>Middle Easterner</option>
              <option value="4" <?php echo $r==='4'?'selected':''; ?>>Caucasian</option>
              <option value="5" <?php echo $r==='5'?'selected':''; ?>>Other</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Cause Number</label>
            <input name="cause_number" class="form-control" required value="<?php echo h(fv('cause_number')); ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><strong>Contact & Referral</strong></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Phone *</label>
            <input name="phone_cell" class="form-control" required value="<?php echo h(fv('phone_cell')); ?>">
          </div>
          <div class="form-group col-md-4">
            <label>Email *</label>
            <input type="email" name="email" class="form-control" required value="<?php echo h(fv('email')); ?>">
          </div>
          <div class="form-group col-md-4">
            <label>Referral Type *</label>
            <?php $rt=(string)fv('referral_type_id'); ?>
            <select name="referral_type_id" class="form-control" required>
              <option value=""></option>
              <option value="1" <?php echo $rt==='1'?'selected':''; ?>>Probation</option>
              <option value="2" <?php echo $rt==='2'?'selected':''; ?>>Parole</option>
              <option value="3" <?php echo $rt==='3'?'selected':''; ?>>Pretrial</option>
              <option value="4" <?php echo $rt==='4'?'selected':''; ?>>CPS</option>
              <option value="5" <?php echo $rt==='5'?'selected':''; ?>>Attorney</option>
              <option value="6" <?php echo $rt==='6'?'selected':''; ?>>VTC</option>
              <option value="0" <?php echo $rt==='0'?'selected':''; ?>>Other</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Emergency Contact *</label>
            <input name="emergency_name" class="form-control" required value="<?php echo h(fv('emergency_name')); ?>">
          </div>
          <div class="form-group col-md-4">
            <label>Emergency Relation (optional)</label>
            <input name="emergency_relation" class="form-control" value="<?php echo h(fv('emergency_relation')); ?>">
          </div>
          <div class="form-group col-md-4">
            <label>Emergency Phone *</label>
            <input name="emergency_phone" class="form-control" required value="<?php echo h(fv('emergency_phone')); ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><strong>Program & Scheduling</strong></div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Program</label>
            <?php $pid=(string)fv('program_id'); ?>
            <select name="program_id" id="program_id" class="form-control" required>
              <option value=""></option>
              <?php foreach ($programs as $p): ?>
                <option value="<?php echo (int)$p['id']; ?>" <?php echo ((string)$p['id']===$pid)?'selected':''; ?>>
                  <?php echo h($p['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group col-md-4">
            <label>Required Sessions</label>
            <input type="number" min="1" name="required_sessions" id="required_sessions" class="form-control" required
                   value="<?php
                     $v = fv('required_sessions',
                             (isset($prefill['program_id'],$REQUIRED_SESSIONS_DEFAULTS[$prefill['program_id']]))
                               ? $REQUIRED_SESSIONS_DEFAULTS[$prefill['program_id']] : '');
                     echo h($v);
                   ?>">
          </div>

          <div class="form-group col-md-4">
            <label>Sessions per Week</label>
            <input type="number" min="1" name="sessions_per_week" class="form-control" required
                   value="<?php echo h(fv('sessions_per_week', $DEFAULT_SESSIONS_PER_WEEK)); ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Group Fee</label>
            <?php
              // Use previously posted value if available; otherwise DEFAULT_FEE.
              // Coerce to int-string and clamp to allowed options.
              $fee_prior = fv('fee', number_format($DEFAULT_FEE, 2, '.', '')); // e.g. "25.00"
              $fee_opt   = (string)(int)$fee_prior;                             // -> "25"
              if (!in_array($fee_opt, ['10','15','25'], true)) {
                $fee_opt = '25';
              }
            ?>
            <select name="fee" id="fee" class="form-control" required>
              <option value="10" <?= $fee_opt === '10' ? 'selected' : '' ?>>$10</option>
              <option value="15" <?= $fee_opt === '15' ? 'selected' : '' ?>>$15</option>
              <option value="25" <?= $fee_opt === '25' ? 'selected' : '' ?>>$25</option>
            </select>
          </div>


          <div class="form-group col-md-4">
            <label>Case Manager</label>
            <?php $cm = (string)fv('case_manager_id'); ?>
            <select name="case_manager_id" class="form-control" required>
                <option value=""></option>
                <?php foreach ($caseManagers as $m): ?>
                    <?php
                    $label = $m['full_name'] !== '' ? $m['full_name'] : '(Unnamed)';
                    if (!empty($m['office'])) $label .= ' — ' . $m['office'];
                    ?>
                    <option value="<?php echo (int)$m['id']; ?>" <?php echo ((string)$m['id'] === $cm) ? 'selected' : ''; ?>>
                    <?php echo h($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>


          </div>

          <div class="form-group col-md-4">
            <label>Therapy Group</label>
            <?php $tg=(string)fv('therapy_group_id'); ?>
            <select name="therapy_group_id" class="form-control" required>
              <option value=""></option>
              <?php foreach ($groups as $g): ?>
                <option value="<?php echo (int)$g['id']; ?>" <?php echo ((string)$g['id']===$tg)?'selected':''; ?>>
                  <?php echo h($g['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Orientation Date</label>
            <input type="date" name="orientation_date" class="form-control" required value="<?php echo h(fv('orientation_date')); ?>">
          </div>
          <div class="form-group col-md-4">
            <label>Stage</label>
            <?php $sid=(string)fv('stage_id', $precontemplation_id ?? ''); ?>
            <select name="stage_id" class="form-control" required>
              <option value=""></option>
              <?php foreach ($stages as $s): ?>
                <option value="<?php echo (int)$s['id']; ?>" <?php echo ((string)$s['id']===$sid)?'selected':''; ?>>
                  <?php echo h($s['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Imported Intake ID</label>
            <input type="text" name="imported_intake_id" class="form-control" value="<?php echo h(fv('imported_intake_id', $prefill_intake_id)); ?>" readonly>
          </div>
        </div>

        <div class="form-row">
          <div class="col-md-12">
            <label>Attendance Days</label><br>
            <?php foreach ($dayFields as $df): ?>
              <?php $checked = isset($_POST[$df]) ? 'checked' : ''; ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="<?php echo $df; ?>" id="<?php echo $df; ?>" <?php echo $checked; ?>>
                <label class="form-check-label" for="<?php echo $df; ?>">
                  <?php echo ucwords(str_replace('attends_','',$df)); ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <input type="text" id="attendance_valid"
            style="position:absolute; left:-9999px; width:1px; height:1px;"
            required aria-hidden="true">
        <small id="attendance_help" class="form-text text-muted"></small>


        <div class="form-group mt-3">
          <label>Other Concerns</label>
          <textarea name="other_concerns" class="form-control" rows="2"><?php echo h(fv('other_concerns')); ?></textarea>
        </div>
      </div>
    </div>

    <div class="text-right">
      <button type="submit" name="create_client" class="btn btn-success">
        <i class="fas fa-check"></i> Create Client
      </button>
      <a href="intake-review.php?id=<?php echo (int)($prefill['intake_id'] ?? 0); ?>" class="btn btn-secondary">Cancel</a>
    </div>

  </form>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script>
(function($){
  // Defaults mapping for required_sessions
  var defaults = <?php echo json_encode($REQUIRED_SESSIONS_DEFAULTS, JSON_UNESCAPED_UNICODE); ?>;

  function attendanceValid(){
    var any = false;
    $('input[type=checkbox][name^=attends_]').each(function(){
      if (this.checked) { any = true; return false; }
    });
    var att = document.getElementById('attendance_valid');
    var help = document.getElementById('attendance_help');
    if (att) {
      att.value = any ? 'ok' : '';
        att.setCustomValidity(any ? '' : 'Please select at least one attendance day.');
    }
    if (help) help.textContent = any ? '': 'Please select at least one attendance day.';
    return any;
  }

  function updateCreateButtonState(){
    var form = document.querySelector('form[method="post"]');
    if (!form) return;
    attendanceValid(); // keep custom validity in sync
    var ok = form.checkValidity();
    var btn = document.querySelector('button[name="create_client"]');
    if (btn) btn.disabled = !ok;
  }

  // Suggest required_sessions when program changes (only if empty)
  $('#program_id').on('change', function(){
    var pid = $(this).val();
    if (pid && defaults[pid]) {
      var $rs = $('#required_sessions');
      if (!$rs.val()) { $rs.val(defaults[pid]); }
    }
    updateCreateButtonState();
  });

  // Live validation on input/change
  $(document).on('input change', 'form[method="post"] :input', updateCreateButtonState);
  $(document).on('change', 'input[type=checkbox][name^=attends_]', function(){
    attendanceValid();
    updateCreateButtonState();
  });

  // First run
  $(function(){
    attendanceValid();
    updateCreateButtonState();
  });
})(jQuery);
</script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" crossorigin="anonymous"></script>

</body>
</html>
