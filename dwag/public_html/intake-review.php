<?php
// intake-review.php — read-only intake viewer with Verify + Suggested Import + Find Client modal

include_once 'auth.php';
include_once '../config/config.php';           // provides $link
if (session_status()===PHP_SESSION_NONE) session_start();
$db = isset($link) ? $link : (isset($con) ? $con : null);
if (!$db) { die('Database connection not found.'); }
$db->set_charset('utf8mb4');

check_loggedin($db);
require_once 'helpers.php';

// CSRF shims (safe if real functions already exist)
if (!function_exists('csrf_check')) { function csrf_check(){} }
if (!function_exists('csrf_field')) { function csrf_field(){} }

// --- Column helpers (local; mirror of client-create-import.php patterns) ---
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

function _db_current_schema(mysqli $con): string {
  $res = $con->query("SELECT DATABASE() AS db");
  $row = $res ? $res->fetch_assoc() : null;
  return $row && !empty($row['db']) ? $row['db'] : '';
}

function table_has_col(mysqli $con, string $table, string $col): bool {
  $db = _db_current_schema($con);
  if (!$db) return false;
  $sql = "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1";
  $st = $con->prepare($sql);
  $st->bind_param('sss', $db, $table, $col);
  $st->execute(); $st->store_result();
  $ok = $st->num_rows > 0; $st->close();
  return $ok;
}

// ------- GET param -------
$intake_id = isset($_GET['id']) ? (int)$_GET['id']
           : (isset($_POST['intake_id']) ? (int)$_POST['intake_id'] : 0);
if ($intake_id <= 0) { die('Missing or invalid intake id.'); }


// ------- Action handlers (Verify / Import) -------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('csrf_check')) { csrf_check(); } // optional

    if (isset($_POST['action']) && $_POST['action'] === 'verify') {
        $verified_by = $_SESSION['name'] ?? ($_SESSION['username'] ?? 'System');
        $pid = (int)($_SESSION['program_id'] ?? 0);
        $sql = "UPDATE intake_packet
                SET staff_verified = 1, verified_by = ?, verified_at = NOW()
                WHERE intake_id = ?".($pid ? " AND program_id = {$pid}" : "");
        $stmt = mysqli_prepare($db, $sql);

        mysqli_stmt_bind_param($stmt, "si", $verified_by, $intake_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header("Location: intake-review.php?id=".$intake_id."&ok=verified");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'import') {
      $client_id = isset($_POST['imported_client_id']) ? (int)$_POST['imported_client_id'] : 0;
      if ($client_id > 0) {
          $st = $db->prepare("SELECT 1 FROM client WHERE id=? LIMIT 1");
          $st->bind_param('i', $client_id);
          $st->execute(); $st->store_result();
          $exists = $st->num_rows > 0; 
          $st->close();
          if (!$exists) {
              header("Location: intake-review.php?id=".$intake_id."&err=client_missing");
              exit;
          }
          // 1) Mark intake as imported to this client
          $pid = (int)($_SESSION['program_id'] ?? 0);
          $stmt = mysqli_prepare($db,
              "UPDATE intake_packet
              SET imported_to_client = 1, imported_client_id = ?
              WHERE intake_id = ?".($pid ? " AND program_id = {$pid}" : ""));

          mysqli_stmt_bind_param($stmt, "ii", $client_id, $intake_id);
          mysqli_stmt_execute($stmt);
          mysqli_stmt_close($stmt);

          // 2) Initialize ledger with the orientation charge/payment (only if not already present)
          //    Schema: ledger(id, client_id, amount, create_date, note)
          $check = mysqli_prepare($db,
              "SELECT COUNT(*) 
                FROM ledger 
                WHERE client_id = ? 
                  AND note IN ('orientation group fee','paid orientation')");
          mysqli_stmt_bind_param($check, "i", $client_id);
          mysqli_stmt_execute($check);
          mysqli_stmt_bind_result($check, $cnt);
          mysqli_stmt_fetch($check);
          mysqli_stmt_close($check);

          if ((int)$cnt === 0) {
              // reuse one prepared statement; bind-by-reference lets us change values between executes
              $lg = mysqli_prepare($db,
                  "INSERT INTO ledger (client_id, amount, create_date, note)
                  VALUES (?, ?, NOW(), ?)");
              if ($lg) {
                  $amt = -25.00; $note = 'orientation group fee';
                  mysqli_stmt_bind_param($lg, "ids", $client_id, $amt, $note);
                  mysqli_stmt_execute($lg);

                  $amt = 25.00;  $note = 'paid orientation';
                  // re-execute with new values
                  mysqli_stmt_execute($lg);

                  mysqli_stmt_close($lg);
              }
              // (optional) else: swallow/Log error; don't block the import redirect
          }

          header("Location: intake-review.php?id=".$intake_id."&ok=imported");
          exit;
      }
      header("Location: intake-review.php?id=".$intake_id."&err=client");
      exit;
  }

      // --- NEW: Begin a review/merge session (render UI) ---
    if (isset($_POST['action']) && $_POST['action'] === 'begin_merge') {
        $merge_client_id = isset($_POST['merge_client_id']) ? (int)$_POST['merge_client_id'] : 0;
        if ($merge_client_id <= 0) {
            header("Location: intake-review.php?id=".$intake_id."&err=client");
            exit;
        }

        // Load intake row
        $intake_row = null;
        $st = mysqli_prepare($db, "SELECT * FROM intake_packet WHERE intake_id = ?");
        mysqli_stmt_bind_param($st, "i", $intake_id);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        $intake_row = $rs ? $rs->fetch_assoc() : null;
        mysqli_free_result($rs);
        mysqli_stmt_close($st);

        // Load client row
        $client_row = null;
        $st = mysqli_prepare($db, "SELECT * FROM client WHERE id = ?");
        mysqli_stmt_bind_param($st, "i", $merge_client_id);
        mysqli_stmt_execute($st);
        $rs = mysqli_stmt_get_result($st);
        $client_row = $rs ? $rs->fetch_assoc() : null;
        mysqli_free_result($rs);
        mysqli_stmt_close($st);

        // Define mergeable fields: client_col => ['label'=>..., 'intake_col'=>...]
        $MERGE_FIELDS = [
          'first_name'        => ['label'=>'First Name',          'intake_col'=>'first_name'],
          'last_name'         => ['label'=>'Last Name',           'intake_col'=>'last_name'],
          'date_of_birth'     => ['label'=>'Date of Birth',       'intake_col'=>'date_of_birth'],
          'gender_id'         => ['label'=>'Gender',              'intake_col'=>'gender_id'],
          // keep if DWAG client has race_id; otherwise it will auto-skip later
          'race_id'           => ['label'=>'Race/Ethnicity',      'intake_col'=>'race_id'],
          // DWAG client uses phone_number
          'phone_number'      => ['label'=>'Phone',               'intake_col'=>'phone_cell'],
          'email'             => ['label'=>'Email',               'intake_col'=>'email'],
          // DWAG client column name is emergency_contact
          'emergency_contact' => ['label'=>'Emergency Name',      'intake_col'=>'emergency_name'],
          'emergency_phone'   => ['label'=>'Emergency Phone',     'intake_col'=>'emergency_phone'],
          'referral_type_id'  => ['label'=>'Referral Type',       'intake_col'=>'referral_type_id'],
          // DWAG client uses cause_number; intake uses id_number
          'cause_number'      => ['label'=>'Cause/Case Number',   'intake_col'=>'id_number'],
          'program_id'        => ['label'=>'Program',             'intake_col'=>'program_id'],
          'signature_date'    => ['label'=>'Signature Date',      'intake_col'=>'signature_date'],
          // convenience: DWAG may have orientation_date, we map from intake_date
          'orientation_date'  => ['label'=>'Orientation Date',    'intake_col'=>'intake_date'],
        ];


        // Stash in globals for template branch
        $GLOBALS['_MERGE_MODE']   = true;
        $GLOBALS['_MERGE_CLIENT'] = $client_row;
        $GLOBALS['_MERGE_INTAKE'] = $intake_row;
        $GLOBALS['_MERGE_MAP']    = $MERGE_FIELDS;
        $GLOBALS['_MERGE_CLIENT_ID'] = $merge_client_id;
    }

    // --- NEW: Apply the merge (update client) ---
    if (isset($_POST['action']) && $_POST['action'] === 'apply_merge') {
        $client_id = isset($_POST['merge_client_id']) ? (int)$_POST['merge_client_id'] : 0;
        if ($client_id <= 0) {
            header("Location: intake-review.php?id=".$intake_id."&err=client");
            exit;
        }

        // Build whitelist aligned with begin_merge MERGE_FIELDS
        $MERGE_FIELDS = [
          'first_name','last_name','date_of_birth','gender_id','race_id',
          'phone_number','email',
          'emergency_contact','emergency_phone',
          'referral_type_id','cause_number','program_id','signature_date','orientation_date',
        ];


        // Prepare dynamic UPDATE (only existing columns)
        $set = [];
        $vals = [];
        $types = '';

        foreach ($MERGE_FIELDS as $col) {
            if (!isset($_POST['client'][$col])) continue;
            $value = $_POST['client'][$col];

            if (!table_has_col($db, 'client', $col)) continue; // skip non-existent

            // infer type (simple): *_id, fee, therapy_group_id → int; dates/strings → string
            if (preg_match('/_id$|^fee$|^therapy_group_id$/', $col)) {
                $types .= 'i';
                $vals[] = (int)$value;
                $set[]  = "$col = ?";
            } else {
                $types .= 's';
                $vals[] = $value;
                $set[]  = "$col = ?";
            }
        }

        if (!empty($set)) {
            $types .= 'i';
            $vals[] = $client_id;

            $sql = "UPDATE client SET ".implode(', ', $set)." WHERE id = ?";
            $st = mysqli_prepare($db, $sql);
            $params = array_merge([$types], $vals);
            $refs = [];
            foreach ($params as $k => $v) { $refs[$k] = &$params[$k]; }
            call_user_func_array([$st, 'bind_param'], $refs);

            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
        }

        // Link the intake to this client (mark imported_to_client)
        $pid = (int)($_SESSION['program_id'] ?? 0);
        $st = mysqli_prepare($db,
            "UPDATE intake_packet SET imported_to_client=1, imported_client_id=? WHERE intake_id=?".($pid ? " AND program_id = {$pid}" : ""));

        mysqli_stmt_bind_param($st, "ii", $client_id, $intake_id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        // (Optional) one-time orientation fee/paid entries — reuse your existing logic if desired.

        header("Location: intake-review.php?id=".$intake_id."&ok=merged");
        exit;
    }


}

if (!function_exists('race_text')) {
    function race_text($id): string {
        static $MAP = [
            0 => 'Hispanic',
            1 => 'African American',
            2 => 'Asian',
            3 => 'Middle Easterner',
            4 => 'Caucasian',
            5 => 'Other',
        ];
        if ($id === null || $id === '') return '';
        $k = is_numeric($id) ? (int)$id : null;   // handles '0' correctly
        return ($k !== null && array_key_exists($k, $MAP)) ? $MAP[$k] : '';
    }
}

if (!function_exists('fetch_ethnicity_label')) {
    function fetch_ethnicity_label(mysqli $db, int $id): string {
        if ($id <= 0) return '';
        // Try common label columns in order
        foreach (['name', 'ethnicity', 'label', 'title'] as $col) {
            $sql = "SELECT {$col} AS lbl FROM ethnicity WHERE id = ? LIMIT 1";
            $stmt = $db->prepare($sql);
            if ($stmt === false) continue;                    // Column may not exist; try next
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($res && ($r = $res->fetch_assoc()) && $r['lbl'] !== null && $r['lbl'] !== '') {
                    $stmt->close();
                    return (string)$r['lbl'];
                }
            }
            $stmt->close();
        }
        return '';
    }
}

// ------- Fetch intake row -------
$stmt = mysqli_prepare($db, "SELECT * FROM intake_packet WHERE intake_id = ?");
mysqli_stmt_bind_param($stmt, "i", $intake_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;
mysqli_free_result($res);
mysqli_stmt_close($stmt);
if (!$row) { die('Intake not found.'); }

$linked_client_id = (int)($row['imported_client_id'] ?? 0);
$linked_client_exists = false;
if ($linked_client_id > 0) {
  $st = $db->prepare("SELECT 1 FROM client WHERE id=? LIMIT 1");
  $st->bind_param('i', $linked_client_id);
  $st->execute(); $st->store_result();
  $linked_client_exists = $st->num_rows > 0;
  $st->close();
}


// === After fetching $row from intake_packet ===
$can_import_victim = (
  (int)($row['victim_contact_provided'] ?? 0) === 1 &&
  (int)($row['imported_to_client'] ?? 0) === 1 &&
  $linked_client_exists
);

$victim_already_imported = false;

if ($can_import_victim) {
  $vf = trim((string)($row['victim_first_name'] ?? ''));
  $vl = trim((string)($row['victim_last_name'] ?? ''));
  $vname = trim($vf . ' ' . $vl);

  if ($vname !== '') {
    $st = mysqli_prepare($db, "SELECT 1 FROM victim WHERE client_id = ? AND name = ? LIMIT 1");
    mysqli_stmt_bind_param($st, "is", $linked_client_id, $vname);
    mysqli_stmt_execute($st);
    mysqli_stmt_store_result($st);
    $victim_already_imported = mysqli_stmt_num_rows($st) > 0;
    mysqli_stmt_close($st);
  }
}



// ------- helpers -------
function fmtDate($d, $fmt = 'm/d/Y') {
  if (!$d || $d === '0000-00-00' || $d === '0000-00-00 00:00:00') return '—';
  $t = strtotime($d); return $t ? date($fmt, $t) : h($d);
}
function yesno($v) {
  if ($v === null || $v === '') return '—';
  if ($v === 1 || $v === '1') return 'Yes';
  if ($v === 0 || $v === '0') return 'No';
  return h($v);
}
function badgeYN($v, $yes='Yes', $no='No', $yesClass='success', $noClass='secondary') {
  $isYes = ($v === 1 || $v === '1'); $label = $isYes ? $yes : $no; $cls = $isYes ? $yesClass : $noClass;
  return "<span class=\"badge badge-$cls\">".h($label)."</span>";
}

function lookup_one(mysqli $db, string $table, string $idcol, string $labelcol, int $id): string {
  if ($id <= 0) return '';
  $sql = "SELECT {$labelcol} AS lbl FROM {$table} WHERE {$idcol}=? LIMIT 1";
  $st = $db->prepare($sql); if (!$st) return '';
  $st->bind_param('i',$id); $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $st->close();
  return $r['lbl'] ?? '';
}


// Labels (adjust if clinic uses different mappings)
$programLabel  = lookup_one($db,'program','id','name',(int)($row['program_id'] ?? 0));
$referralLabel = lookup_one($db,'referral_type','id','referral_type',(int)($row['referral_type_id'] ?? 0));

$VTA_MAP = ['N'=>'Never','R'=>'Rarely','O'=>'Occasionally','F'=>'Frequently','V'=>'Very Frequently'];
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


// Load consent text (external file you maintain)
$CONSENT_TEXT = [];
$consent_texts_path = __DIR__ . '/consent_texts.php';
if (file_exists($consent_texts_path)) { include $consent_texts_path; }

// Consent wiring: title + date
$CONSENTS = [
  ['key'=>'p1_confidentiality', 'title'=>'Confidentiality (Page 1)',                'date'=>$row['confidentiality_date_p1'] ?? null],
  ['key'=>'p8a_disclosure',     'title'=>'Disclosure of Information (8a)',          'date'=>($row['disclosure_date_8a'] ?? ($row['consent_disclosure_date'] ?? null))],
  ['key'=>'p8b_partners',       'title'=>'Partner/Victim Information (8b)',         'date'=>$row['disclosure_date_8b'] ?? null],
  ['key'=>'p8c_program',        'title'=>'Program Agreement (8c)',                  'date'=>$row['program_date_8ca'] ?? null],
  ['key'=>'p8c_responsibility', 'title'=>'Taking Responsibility (8c)',              'date'=>$row['program_date_8cb'] ?? null],
  ['key'=>'p8d_virtual',        'title'=>'Virtual Group Rules (8d)',                'date'=>$row['vgr_date_8d'] ?? null],
  ['key'=>'p8e_termination',    'title'=>'Termination Policy (8e)',                 'date'=>$row['termination_date_8e'] ?? null],
];

// ------- Suggested Import: try exact (FN+LN+DOB), then looser (DOB + FN or LN) -------
$suggested = [];
$fn = trim((string)($row['first_name'] ?? ''));
$ln = trim((string)($row['last_name'] ?? ''));
$dob = trim((string)($row['date_of_birth'] ?? ''));

// Exact match
if ($fn !== '' && $ln !== '' && $dob !== '' && $dob !== '0000-00-00') {
  $sql1 = "SELECT id, first_name, last_name, date_of_birth, phone_number
           FROM client
           WHERE UPPER(TRIM(first_name)) = UPPER(TRIM(?))
             AND UPPER(TRIM(last_name))  = UPPER(TRIM(?))
             AND date_of_birth = ?
           LIMIT 10";
  $stmt = mysqli_prepare($db, $sql1);
  mysqli_stmt_bind_param($stmt, "sss", $fn, $ln, $dob);
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);
  while ($c = $rs->fetch_assoc()) { $suggested[(int)$c['id']] = $c; }
  mysqli_free_result($rs);
  mysqli_stmt_close($stmt);

  // If none, looser: DOB + (FN or LN)
  if (empty($suggested)) {
    $sql2 = "SELECT id, first_name, last_name, date_of_birth, phone_number
             FROM client
             WHERE date_of_birth = ?
               AND (UPPER(TRIM(first_name)) = UPPER(TRIM(?))
                    OR UPPER(TRIM(last_name))  = UPPER(TRIM(?)))
             LIMIT 10";
    $stmt = mysqli_prepare($db, $sql2);
    mysqli_stmt_bind_param($stmt, "sss", $dob, $fn, $ln);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    while ($c = $rs->fetch_assoc()) { $suggested[(int)$c['id']] = $c; }
    mysqli_free_result($rs);
    mysqli_stmt_close($stmt);
  }
}

// ------- Find Client (modal search via GET ?find=) -------
$find = isset($_GET['find']) ? trim((string)$_GET['find']) : '';
$find_results = [];
if ($find !== '') {
  $like = '%'.$find.'%';
  $maybe_id = ctype_digit($find) ? (int)$find : null;
  $sql = "SELECT id, first_name, last_name, date_of_birth, phone_number
          FROM client
          WHERE (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name,' ',last_name) LIKE ? OR phone_number LIKE ?)";
  if ($maybe_id) { $sql .= " OR id = ?"; }

  $stmt = mysqli_prepare($db, $sql);
  if ($maybe_id) {
    mysqli_stmt_bind_param($stmt, "ssssi", $like, $like, $like, $like, $maybe_id);
  } else {
    mysqli_stmt_bind_param($stmt, "ssss", $like, $like, $like, $like);
  }
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);
  while ($c = $rs->fetch_assoc()) { $find_results[] = $c; }
  mysqli_free_result($rs);
  mysqli_stmt_close($stmt);
}

// ===== Victim Import action (from Intake Page 7) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_victim') {
    if (function_exists('csrf_check')) csrf_check();
    $intake_id_post = isset($_POST['intake_id']) ? (int)$_POST['intake_id'] : 0;
    // trust server-side context if mismatch
    if ($intake_id_post !== (int)$intake_id) { $intake_id_post = (int)$intake_id; }

    // Re-fetch intake row to avoid trusting client-side fields
    $st = mysqli_prepare($db, "SELECT * FROM intake_packet WHERE intake_id = ?");
    mysqli_stmt_bind_param($st, "i", $intake_id_post);
    mysqli_stmt_execute($st);
    $rs = mysqli_stmt_get_result($st);
    $I = $rs ? $rs->fetch_assoc() : null;
    mysqli_free_result($rs);
    mysqli_stmt_close($st);

    if (!$I) {
        header("Location: intake-review.php?id=$intake_id&err=no_intake");
        exit;
    }

    // Safety checks: must have victim contact provided, must be linked to a client
    $ok_contact = (int)($I['victim_contact_provided'] ?? 0) === 1;
    $ok_linked  = (int)($I['imported_to_client'] ?? 0) === 1 && !empty($I['imported_client_id']);
    if (!($ok_contact && $ok_linked)) {
        header("Location: intake-review.php?id=$intake_id&err=not_ready_for_victim_import");
        exit;
    }
    $client_id = (int)$I['imported_client_id'];
    // Ensure the linked client actually exists; do not create orphans
    $st = mysqli_prepare($db, "SELECT 1 FROM client WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($st, "i", $client_id);
    mysqli_stmt_execute($st);
    mysqli_stmt_store_result($st);
    $client_exists = mysqli_stmt_num_rows($st) > 0;
    mysqli_stmt_close($st);

    if (!$client_exists) {
        header("Location: intake-review.php?id=$intake_id&err=linked_client_missing");
        exit;
    }


    // Build victim name (required)
    $victim_first = trim((string)($I['victim_first_name'] ?? ''));
    $victim_last  = trim((string)($I['victim_last_name'] ?? ''));
    $victim_name  = trim($victim_first . ' ' . $victim_last);
    if ($victim_name === '') {
        header("Location: intake-review.php?id=$intake_id&err=missing_victim_name");
        exit;
    }

    // Relationship (fallback to *_other if main is blank)
    $relationship = trim((string)($I['victim_relationship'] ?? ''));
    if ($relationship === '' && !empty($I['victim_relationship_other'])) {
        $relationship = trim((string)$I['victim_relationship_other']);
    }

    // Gender (string in intake)
    $gender = trim((string)($I['victim_gender'] ?? ''));
    if ($gender === '') $gender = null;

    // Age: take explicit age if numeric; else compute from DOB if present
    $age = null;
    $age_str = trim((string)($I['victim_age'] ?? ''));
    if ($age_str !== '' && ctype_digit($age_str)) {
        $age = (int)$age_str;
    } elseif (!empty($I['victim_dob']) && $I['victim_dob'] !== '0000-00-00') {
        $dob = DateTime::createFromFormat('Y-m-d', $I['victim_dob']);
        if ($dob) {
            $today = new DateTime('today');
            $age = (int)$dob->diff($today)->y;
        }
    }

    // Living with client / children under 18
    // Living with client / children under 18
    $living_with_client = (int)($I['live_with_victim'] ?? 0);

    // intake_packet.children_with_victim is already numeric — copy it directly
    $children_under_18 = (isset($I['children_with_victim']) && $I['children_with_victim'] !== '')
        ? (int)$I['children_with_victim']
        : null; // keep NULL if intake left it blank


    // Contact & address
    $address_line1 = trim((string)($I['victim_address'] ?? '')) ?: null;
    $address_line2 = null;
    $city          = trim((string)($I['victim_city'] ?? '')) ?: null;
    $state         = trim((string)($I['victim_state'] ?? '')) ?: null;
    $zip           = trim((string)($I['victim_zip'] ?? '')) ?: null;
    $phone         = trim((string)($I['victim_phone'] ?? '')) ?: null;
    $email         = trim((string)($I['victim_email'] ?? '')) ?: null;

    // Idempotency: if a victim with same name already exists for this client, bail gracefully
    if ($st = mysqli_prepare($db, "SELECT id FROM victim WHERE client_id=? AND name=? LIMIT 1")) {
        mysqli_stmt_bind_param($st, "is", $client_id, $victim_name);
        mysqli_stmt_execute($st);
        mysqli_stmt_store_result($st);
        $exists = mysqli_stmt_num_rows($st) > 0;
        mysqli_stmt_close($st);
        if ($exists) {
            header("Location: client-victim.php?client_id=$client_id&ok=victim_already_exists");
            exit;
        }
    }

    // Insert (same shape & types as client-victim-create.php)
    $sql = "INSERT INTO victim
            (client_id, name, relationship, gender, age,
             living_with_client, children_under_18,
             address_line1, address_line2, city, state, zip, phone, email)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    if ($st = mysqli_prepare($db, $sql)) {
        // types: i s s s i i i s s s s s s s  (14 params)
        mysqli_stmt_bind_param(
          $st, 'isssiiisssssss',
          $client_id,          // i
          $victim_name,        // s
          $relationship,       // s
          $gender,             // s
          $age,                // i
          $living_with_client, // s
          $children_under_18,  // i
          $address_line1,      // s
          $address_line2,      // s
          $city,               // s
          $state,              // s
          $zip,                // s
          $phone,              // s
          $email               // s
        );


        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);

        header("Location: client-victim.php?client_id=$client_id&ok=victim_imported");
        exit;
    } else {
        header("Location: intake-review.php?id=$intake_id&err=db_prepare_failed");
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NotesAO - Intake Review</title>

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
    /* Layout & spacing */
    .section-card + .section-card { margin-top: 1rem; }
    .kv { display:flex; align-items:center; margin-bottom:.25rem; }
    .kv .k { flex:0 0 160px; max-width:160px; color:#6c757d; } /* was 220px */
    .kv .v { flex:1 1 auto; min-width:0; overflow-wrap:anywhere; word-break:break-word; }
    .badge { font-weight:500; }
    .consent-link { cursor:pointer; text-decoration:underline; }
    .table-sm td, .table-sm th { padding:.30rem .4rem; }
    .actions-toolbar { margin-bottom:1rem; } /* not sticky */
    .table td, .table th { vertical-align:top; }

    /* Hidden on screen; shown only in print for full consent text */
    .print-only { display: none; }

    @media print {
      nav, .no-print, .modal { display: none !important; }
      body { padding: 0; }
      /* Allow cards to break across pages to avoid large gaps */
      .card { border: 0; box-shadow: none; page-break-inside: auto; break-inside: auto; }
      .card-header { background: #fff !important; border-bottom: 1px solid #000 !important; }
      .card-body { padding-top: .5rem; }
      .table { border-color: #000; }
      .table th, .table td { border-color: #000 !important; }
      .page-break { page-break-before: always; }
      @page { margin: 0.5in; }

      /* Hide the clickable consents summary table in print */
      .consents-summary { display: none !important; }

      /* Full consent text section */
      .print-only { display: block; }
      .consents-print-section {
        /* let it flow; don't force a new page */
        page-break-before: auto;
        page-break-inside: auto;
        break-inside: auto;
        margin-top: 0;
      }
      /* keep each consent block intact */
      .consent-print .consent-item {
        page-break-inside: avoid;
        break-inside: avoid;
        margin-bottom: 1rem;
      }
      .consent-print h5 { font-size: 1rem; margin: .25rem 0 .5rem; }
      .consent-print small { color: #000 !important; }
      .consent-print ul, .consent-print ol { margin-left: 1.2em; }
    }
  </style>

</head>
<body>
<?php require_once('navbar.php'); ?>

<section class="pt-5">
  <div class="container-fluid">

    <?php if (!empty($_GET['ok'])): ?>
      <div class="alert alert-success no-print">
        <?php
          if ($_GET['ok']==='verified')  echo 'Intake verified.';
          elseif ($_GET['ok']==='imported') echo 'Intake imported to client.';
          elseif ($_GET['ok']==='merged')   echo 'Client updated and intake linked.';
        ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($_GET['err'])): ?>
      <?php if ($_GET['err']==='client'): ?>
        <div class="alert alert-warning no-print">Please select a valid existing Client to import.</div>
      <?php elseif ($_GET['err']==='client_missing'): ?>
        <div class="alert alert-danger no-print">The selected Client no longer exists. Please choose another existing client.</div>
      <?php elseif ($_GET['err']==='linked_client_missing'): ?>
        <div class="alert alert-danger no-print">This intake is linked to a Client that no longer exists. Use “Change Client” to fix the link.</div>
      <?php elseif ($_GET['err']==='not_ready_for_victim_import'): ?>
        <div class="alert alert-warning no-print">Victim import requires Page 7 contact information and a valid linked Client.</div>
      <?php elseif ($_GET['err']==='missing_intake_id' || $_GET['err']==='no_intake'): ?>
        <div class="alert alert-danger no-print">Invalid intake request. Please reload and try again.</div>
      <?php elseif ($_GET['err']==='db_prepare_failed'): ?>
        <div class="alert alert-danger no-print">A database error occurred while importing victim information.</div>
      <?php endif; ?>
    <?php endif; ?>


    <!-- Actions toolbar -->
    <div class="d-flex justify-content-between align-items-center actions-toolbar no-print">
      <div>
        <a href="intake-index.php" class="btn btn-light"><i class="far fa-list-alt"></i> Back to Index</a>
        <a href="intake-update.php?id=<?php echo (int)$intake_id; ?>" class="btn btn-info"><i class="far fa-edit"></i> Edit</a>
      </div>

      <div class="d-flex align-items-center flex-wrap">

        <!-- Verify -->
        <form method="post" action="intake-review.php?id=<?= (int)$intake_id ?>" class="px-3 py-2 border-bottom">

          <?php if (function_exists('csrf_field')) csrf_field(); ?>
          <input type="hidden" name="action" value="verify">
          <button type="submit" class="btn btn-success"
                  <?php echo ((int)($row['staff_verified'] ?? 0) === 1) ? 'disabled' : ''; ?>>
            <i class="fas fa-check"></i> Verify
          </button>
        </form>

        <!-- Suggested Import controls -->
        <?php $alreadyImported = ((int)($row['imported_to_client'] ?? 0) === 1); ?>
        <?php if (!$alreadyImported): ?>

          <?php if (count($suggested) === 1): $only = reset($suggested); ?>
            <form method="post" action="intake-review.php?id=<?= (int)$intake_id ?>" class="px-3 py-2 border-bottom">
              <?php if (function_exists('csrf_field')) csrf_field(); ?>
              <input type="hidden" name="action" value="begin_merge">
              <input type="hidden" name="merge_client_id" value="<?php echo (int)$only['id']; ?>">


              <button type="submit" class="btn btn-primary">
                <i class="fas fa-columns"></i>
                Review &amp; Merge into: <?php echo h($only['first_name'].' '.$only['last_name']); ?>
                (<?php echo fmtDate($only['date_of_birth']); ?>)
              </button>
            </form>


          <?php elseif (count($suggested) > 1): ?>
            <div class="btn-group mr-2 mb-2 mb-sm-0">
              <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown">
                <i class="fas fa-file-import"></i> Import… (<?php echo count($suggested); ?>)
              </button>
              <div class="dropdown-menu dropdown-menu-right p-0">
                <?php foreach ($suggested as $cand): ?>
                  <form method="post" action="intake-review.php?id=<?= (int)$intake_id ?>" class="px-3 py-2 border-bottom">
                    <?php if (function_exists('csrf_field')) csrf_field(); ?>
                    <input type="hidden" name="action" value="begin_merge">
                    <input type="hidden" name="merge_client_id" value="<?php echo (int)$cand['id']; ?>">


                    <div class="d-flex align-items-center">
                      <div class="mr-2">
                        <strong><?php echo h($cand['first_name'].' '.$cand['last_name']); ?></strong>
                        <div class="small text-muted">
                          DOB: <?php echo fmtDate($cand['date_of_birth']); ?>
                          <?php if (!empty($cand['phone_number'])): ?>
                            &nbsp;|&nbsp;<?php echo h($cand['phone_number']); ?>
                          <?php endif; ?>
                        </div>
                      </div>
                      <button type="submit" class="btn btn-sm btn-outline-primary ml-auto">Review &amp; Merge</button>
                    </div>
                  </form>

                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if (!empty($GLOBALS['_MERGE_MODE'])): 
            $INT = $GLOBALS['_MERGE_INTAKE'] ?? [];
            $CLI = $GLOBALS['_MERGE_CLIENT'] ?? [];
            $MAP = $GLOBALS['_MERGE_MAP'] ?? [];
            $MERGE_CLIENT_ID = (int)($GLOBALS['_MERGE_CLIENT_ID'] ?? 0);
          ?>
          <div class="card border-0 shadow-sm my-3">
            <div class="card-header">
              <strong>Review &amp; Merge</strong>
              <div class="small text-muted">Choose which values to apply to the client record. Left = Intake, Right = Current Client (editable).</div>
            </div>
            <div class="card-body">
              <form method="post" action="intake-review.php?id=<?= (int)$intake_id ?>" id="applyMergeForm">

                <?php if (function_exists('csrf_field')) csrf_field(); ?>
                <input type="hidden" name="action" value="apply_merge">
                <input type="hidden" name="merge_client_id" value="<?php echo (int)$MERGE_CLIENT_ID; ?>">




                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead class="thead-light">
                      <tr>
                        <th style="width:35%">Field</th>
                        <th style="width:32%">Intake Value</th>
                        <th style="width:33%">Client Value (will be saved)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($MAP as $client_col => $meta): 
                        $label = $meta['label'];
                        $intake_col = $meta['intake_col'];
                        $int_val = $INT[$intake_col] ?? '';
                        $cli_val = $CLI[$client_col] ?? '';
                      ?>
                      <tr>
                        <td><strong><?php echo h($label); ?></strong><div class="text-muted small"><?php echo h($client_col); ?></div></td>
                        <td>
                          <div class="d-flex">
                            <input class="form-control form-control-sm" value="<?php echo h($int_val); ?>" readonly>
                            <button type="button" class="btn btn-sm btn-outline-secondary ml-2"
                                    onclick="np_useIntake('<?= $client_col ?>')"
                                    title="Use intake value → client">
                              Use
                            </button>
                          </div>
                          <input type="hidden" id="intake_<?= $client_col ?>" value="<?php echo h($int_val); ?>">
                        </td>
                        <td>
                          <input class="form-control form-control-sm"
                                name="client[<?php echo h($client_col); ?>]"
                                id="client_<?php echo h($client_col); ?>"
                                value="<?php echo h($cli_val); ?>">
                        </td>
                      </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <div class="d-flex justify-content-between mt-3">
                  <a href="intake-review.php?id=<?php echo (int)$intake_id; ?>" class="btn btn-light">
                    Cancel
                  </a>
                  <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Apply Merge &amp; Link Intake
                  </button>
                </div>
              </form>
            </div>
          </div>

          <script>
          function np_useIntake(col){
            var iv = document.getElementById('intake_'+col);
            var cv = document.getElementById('client_'+col);
            if (iv && cv) { cv.value = iv.value; }
          }
          </script>
          <?php endif; ?>


          <?php if (count($suggested) === 0): ?>
            <!-- Hidden form for Create Client (avoid nesting forms) -->
            <form id="createFromIntakeForm" action="client-create-import.php" method="post" class="d-inline">
              <?php if (function_exists('csrf_field')) csrf_field(); ?>
              <input type="hidden" name="prefill_intake_id" value="<?php echo (int)$intake_id; ?>">
            </form>

            <!-- Create Client (inline) -->
            <button type="submit" form="createFromIntakeForm" class="btn btn-primary mr-2 mb-2 mb-sm-0">
              <i class="fas fa-user-plus"></i> Create Client
            </button>
          <?php endif; ?>


        <?php else: ?>
          <div class="btn-group mr-2 mb-2 mb-sm-0">
            <button class="btn btn-secondary" disabled>
              <i class="fas fa-file-import"></i> Imported
            </button>
            <button type="button"
                    class="btn btn-outline-primary"
                    onclick="$('#findClientModal').modal('show');">
              Change Client
            </button>
          </div>
        <?php endif; ?>


        <!-- Print -->
        <button class="btn btn-secondary" onclick="window.print()">
          <i class="fas fa-print"></i> Print PDF
        </button>
      </div>
    </div>


    <!-- Header -->
    <div class="card section-card">
      <div class="card-header">
        <h4 class="mb-0">Intake Summary</h4>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4">
            <div class="kv"><div class="k">First Name</div><div class="v"><?php echo h($row['first_name'] ?? ''); ?></div></div>
            <div class="kv"><div class="k">Last Name</div><div class="v"><?php echo h($row['last_name'] ?? ''); ?></div></div>

            <div class="kv"><div class="k">Date of Birth</div><div class="v"><?php echo fmtDate($row['date_of_birth'] ?? null); ?></div></div>
            <?php
              // Map gender_id to label
              $gender_label = lookup_one($db,'gender','id','gender',(int)($row['gender_id'] ?? 0));

            ?>
            <div class="kv"><div class="k">Gender</div><div class="v"><?php echo h($gender_label); ?></div></div>
          </div>
          <div class="col-md-4">
            <div class="kv"><div class="k">Program</div><div class="v"><?php echo h($programLabel); ?></div></div>
            <div class="kv"><div class="k">Referral Type</div><div class="v"><?php echo h($referralLabel); ?></div></div>
            <div class="kv"><div class="k">Submitted</div><div class="v"><?php echo fmtDate($row['created_at'] ?? null, 'm/d/Y h:i A'); ?></div></div>
          </div>
          <div class="col-md-4">
            <div class="kv"><div class="k">Packet Complete</div><div class="v"><?php echo badgeYN($row['packet_complete'] ?? 0, 'Complete','Incomplete'); ?></div></div>
            <div class="kv"><div class="k">Verified</div><div class="v">
              <?php echo badgeYN($row['staff_verified'] ?? 0, 'Verified','Unverified','primary','light'); ?>
              <?php if (!empty($row['verified_by']) || !empty($row['verified_at'])): ?>
                <small class="text-muted d-block"><?php echo h(trim(($row['verified_by'] ?? '').' '.fmtDate($row['verified_at'] ?? null, 'm/d/Y h:i A'))); ?></small>
              <?php endif; ?>
            </div></div>
            <div class="kv"><div class="k">Imported</div><div class="v">
              <?php if ((int)($row['imported_to_client'] ?? 0) === 1): ?>
                <?php if ($linked_client_exists): ?>
                  <span class="badge badge-info">Imported</span>
                  <small class="text-muted d-block">Client ID <?= (int)$linked_client_id ?></small>
                <?php else: ?>
                  <span class="badge badge-danger">Linked client missing</span>
                  <button type="button"
                          class="btn btn-sm btn-outline-primary ml-2"
                          onclick="$('#findClientModal').modal('show');">
                    Change Client
                  </button>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge-light">Not Imported</span>
              <?php endif; ?>
            </div></div>


          </div>
        </div>
      </div>
    </div>

    <!-- Identity & Contact -->
    <div class="card section-card">
      <div class="card-header"><strong>Identity & Contact</strong></div>
      <div class="card-body">

        <?php
        $race_ethnicity = lookup_one($db,'ethnicity','id','name',(int)($row['ethnicity_id'] ?? ($row['race_id'] ?? 0)));

        ?>

        <div class="row">
          <div class="col-md-4">
            <div class="kv"><div class="k">Email</div><div class="v"><?php echo h($row['email'] ?? ''); ?></div></div>
            <div class="kv"><div class="k">Phone (Cell)</div><div class="v"><?php echo h($row['phone_cell'] ?? ''); ?></div></div>
            <div class="kv"><div class="k">ID Number</div><div class="v"><?php echo h($row['id_number'] ?? ''); ?></div></div>
          </div>
          <div class="col-md-8">
            <div class="kv"><div class="k">Address</div>
              <div class="v">
                <?php
                  $addr = trim(($row['address_street'] ?? '').' '.($row['address_city'] ?? '').', '.($row['address_state'] ?? '').' '.($row['address_zip'] ?? '').' '.($row['address_country'] ?? ''));
                  echo h(preg_replace('/\s+/', ' ', $addr));
                ?>
              </div>
            </div>
            <div class="kv"><div class="k">Birth City</div><div class="v"><?php echo h($row['birth_city'] ?? ''); ?></div></div>
            <!-- ✅ New field directly under Birth City -->
            <div class="kv"><div class="k">Race/Ethnicity</div>
              <div class="v"><?php echo h($race_ethnicity); ?></div>
            </div>

            <!-- end new field -->
          </div>
        </div>
      </div>
    </div>

    <!-- Referral -->
    <div class="card section-card">
      <div class="card-header"><strong>Referral</strong></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4">
            <div class="kv"><div class="k">Referring Officer</div><div class="v"><?php echo h($row['referring_officer_name'] ?? ''); ?></div></div>
            <div class="kv"><div class="k">Officer Email</div><div class="v"><?php echo h($row['referring_officer_email'] ?? ''); ?></div></div>
            <div class="kv"><div class="k">Officer Phone</div><div class="v"><?php echo h($row['referring_officer_phone'] ?? ''); ?></div></div>
          </div>
          <div class="col-md-4">
            <div class="kv"><div class="k">Cause Number</div><div class="v"><?php echo h($row['referring_cause_number'] ?? ''); ?></div></div>
            <div class="kv"><div class="k">Emergency Contact</div><div class="v"><?php echo h($row['emergency_name'] ?? ''); ?></div></div>
            <div class="kv"><div class="k">Emergency Phone</div><div class="v"><?php echo h($row['emergency_phone'] ?? ''); ?></div></div>
            <div class="kv"><div class="k">Relation</div><div class="v"><?php echo h($row['emergency_relation'] ?? ''); ?></div></div>
          </div>
          

        </div>
      </div>
    </div>

    <!-- Children / CPS -->
    <div class="card section-card">
      <div class="card-header"><strong>Children & CPS</strong></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <table class="table table-sm table-bordered mb-0">
              <tbody>
              <tr><th class="w-50">Has Children</th><td><?php echo yesno($row['has_children'] ?? null); ?></td></tr>
              <tr><th>Children live with you?</th><td><?php echo yesno($row['children_live_with_you'] ?? null); ?></td></tr>
              <tr><th>Names & Ages</th><td><?php echo h($row['children_names_ages'] ?? ''); ?></td></tr>
              </tbody>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-sm table-bordered mb-0">
              <tbody>
              <tr><th class="w-50">CPS Notified</th><td><?php echo yesno($row['cps_notified'] ?? null); ?></td></tr>
              <tr><th>In CPS Care</th><td><?php echo yesno($row['cps_care'] ?? null); ?></td></tr>
              <tr><th>Case Yr/Status</th><td><?php echo h($row['cps_case_year_status'] ?? ''); ?></td></tr>
              <tr><th>Caseworker Contact</th><td><?php echo h($row['cps_caseworker_contact'] ?? ''); ?></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Substance Use -->
    <div class="card section-card">
      <div class="card-header"><strong>Substance Use</strong></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <table class="table table-sm table-bordered mb-0">
              <tbody>
              <tr><th class="w-50">Alcohol (past)</th><td><?php echo yesno($row['alcohol_past'] ?? null); ?></td></tr>
              <tr><th>Alcohol (current)</th><td><?php echo yesno($row['alcohol_current'] ?? null); ?></td></tr>
              <tr><th>Alcohol frequency</th><td><?php echo h($row['alcohol_frequency'] ?? ''); ?></td></tr>
              <tr><th>During abuse?</th><td><?php echo yesno($row['alcohol_during_abuse'] ?? null); ?></td></tr>
              <tr><th>Last substance use</th><td><?php echo h($row['last_substance_use'] ?? ''); ?></td></tr>
              </tbody>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-sm table-bordered mb-0">
              <tbody>
              <tr><th class="w-50">Drug (past)</th><td><?php echo yesno($row['drug_past'] ?? null); ?></td></tr>
              <tr><th>Drug (current)</th><td><?php echo yesno($row['drug_current'] ?? null); ?></td></tr>
              <tr><th>Alcohol details</th><td><?php echo h($row['alcohol_current_details'] ?? ''); ?></td></tr>
              <tr><th>Drug details</th><td><?php echo h($row['drug_current_details'] ?? ''); ?></td></tr>
              <tr><th>Past drug details</th><td><?php echo h($row['drug_past_details'] ?? ''); ?></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Mental / Medical -->
    <div class="card section-card">
      <div class="card-header"><strong>Mental & Medical</strong></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <table class="table table-sm table-bordered mb-0">
              <tbody>
              <tr><th class="w-50">Counseling History</th><td><?php echo yesno($row['counseling_history'] ?? null); ?></td></tr>
              <tr><th>Counseling Reason</th><td><?php echo h($row['counseling_reason'] ?? ''); ?></td></tr>
              <tr><th>Currently Depressed</th><td><?php echo yesno($row['depressed_currently'] ?? null); ?></td></tr>
              <tr><th>Depression Reason</th><td><?php echo h($row['depression_reason'] ?? ''); ?></td></tr>
              </tbody>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-sm table-bordered mb-0">
              <tbody>
              <tr><th class="w-50">Mental Health Meds</th><td><?php echo yesno($row['mental_health_meds'] ?? null); ?></td></tr>
              <tr><th>Med List</th><td><?php echo h($row['mental_meds_list'] ?? ''); ?></td></tr>
              <tr><th>Doctor Name</th><td><?php echo h($row['mental_doctor_name'] ?? ''); ?></td></tr>
              <tr><th>Sexual Abuse History</th><td><?php echo yesno($row['sexual_abuse_history'] ?? null); ?></td></tr>
              <tr><th>Head Trauma History</th><td><?php echo yesno($row['head_trauma_history'] ?? null); ?></td></tr>
              <tr><th>Head Trauma Desc</th><td><?php echo h($row['head_trauma_desc'] ?? ''); ?></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Violence / Weapons -->
    <div class="card section-card">
      <div class="card-header"><strong>Violence & Weapons</strong></div>
      <div class="card-body">
        <table class="table table-sm table-bordered mb-0">
          <tbody>
          <tr><th class="w-25">Weapon Possession History</th><td><?php echo yesno($row['weapon_possession_history'] ?? null); ?></td></tr>
          <tr><th>Weapon Details</th><td><?php echo h($row['weapon_possession_details'] ?? ''); ?></td></tr>
          <tr><th>Abuse / Trauma History</th><td><?php echo yesno($row['abuse_trauma_history'] ?? null); ?></td></tr>
          <tr><th>Violent Incident Description</th><td><?php echo h($row['violent_incident_desc'] ?? ''); ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Victim Info (Page 7) -->
    <div class="card section-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Victim Information (Page 7)</strong>
        
        <?php if ($can_import_victim && !$victim_already_imported): ?>
          <form method="post" action="intake-review.php?id=<?= (int)$intake_id ?>" class="m-0 p-0">
            <?php if (function_exists('csrf_field')) csrf_field(); ?>
            <input type="hidden" name="action" value="import_victim">
            <input type="hidden" name="intake_id" value="<?= (int)($intake_id ?? ($row['intake_id'] ?? 0)) ?>">
            <button type="submit" class="btn btn-sm btn-primary">
              <i class="fas fa-user-plus"></i> Import Victim from Intake
            </button>
          </form>

        <?php elseif ($can_import_victim && $victim_already_imported): ?>
          <button class="btn btn-sm btn-secondary" disabled>
            <i class="fas fa-check"></i> Already imported
          </button>
        <?php endif; ?>

      </div>

      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <table class="table table-sm table-bordered mb-0">
              <tbody>
              <tr><th class="w-50">Focus on actions</th><td><?= yesno($row['focus_on_actions'] ?? null) ?></td></tr>
              <tr><th>Long-term assault thoughts</th><td><?= yesno($row['long_term_assault_thoughts'] ?? null) ?></td></tr>
              <tr><th>Victim contact provided</th><td><?= yesno($row['victim_contact_provided'] ?? null) ?></td></tr>
              <tr><th>Live with victim?</th><td><?= yesno($row['live_with_victim'] ?? null) ?></td></tr>
              <tr><th>Children with victim?</th><td><?= yesno($row['children_with_victim'] ?? null) ?></td></tr>
              </tbody>
            </table>
          </div>

          <div class="col-md-6">
            <table class="table table-sm table-bordered mb-0">
              <tbody>
              <tr><th class="w-50">Relationship to victim</th><td><?= h($row['victim_relationship'] ?? '') ?></td></tr>
              <tr><th>Victim First/Last</th><td><?= h(trim(($row['victim_first_name'] ?? '') . ' ' . ($row['victim_last_name'] ?? ''))) ?></td></tr>
              <tr><th>Victim Gender/Age</th><td><?= h(trim(($row['victim_gender'] ?? '') . ' ' . ($row['victim_age'] ?? ''))) ?></td></tr>
              <tr><th>Victim Contact</th><td><?= h(trim(($row['victim_phone'] ?? '') . ' ' . ($row['victim_email'] ?? ''))) ?></td></tr>
              <tr><th>Victim Address</th><td><?= h(trim(($row['victim_address'] ?? '') . ' ' . ($row['victim_city'] ?? '') . ', ' . ($row['victim_state'] ?? '') . ' ' . ($row['victim_zip'] ?? ''))) ?></td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="row mt-2">
          <div class="col-md-6">
            <div class="kv">
              <div class="k">Page 7 Release — Signed on</div>
              <div class="v"><?= fmtDate($row['consent_release_signed_date'] ?? null) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>


    <!-- Consents -->
    <div class="card section-card consents-summary">
      <div class="card-header"><strong>Consents</strong></div>
      <div class="card-body">
        <table class="table table-sm table-bordered mb-0">
          <thead><tr><th>Consent</th><th>Signed on</th></tr></thead>
          <tbody>
          <?php foreach ($CONSENTS as $c): ?>
            <tr>
              <td>
                <a class="consent-link" data-toggle="modal" data-target="#modal_<?php echo h($c['key']); ?>">
                  <?php echo h($c['title']); ?>
                </a>
                &nbsp;—&nbsp;<small class="text-muted">click to view</small>
              </td>
              <td><?php echo fmtDate($c['date'] ?? null); ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Consents — FULL TEXT (print only) -->
    <div class="card section-card print-only consents-print-section">
      <div class="card-header"><strong>Consents — Full Text</strong></div>
      <div class="card-body consent-print">
        <?php foreach ($CONSENTS as $c): $k = $c['key']; ?>
          <div class="consent-item">
            <h5><?php echo h($c['title']); ?></h5>
            <div class="mt-2">
              <?php echo $CONSENT_TEXT[$k] ?? '<p>No consent text available.</p>'; ?>
            </div>
            <div class="mt-2">
              <small>Signed on: <?php echo fmtDate($c['date'] ?? null); ?></small>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>


    <?php
    $hasVTA = array_key_exists('vta_partner_name',$row) || array_key_exists('vta_b01',$row);
    if ($hasVTA):
    ?>


    <!-- VTA -->
    <div class="card section-card">
      <div class="card-header"><strong>Victim Treatment Assessment (VTA)</strong></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6"><div class="kv"><div class="k">Partner Name</div><div class="v"><?php echo h($row['vta_partner_name'] ?? ''); ?></div></div></div>
          <div class="col-md-3"><div class="kv"><div class="k">Date</div><div class="v"><?php echo fmtDate($row['vta_date'] ?? null); ?></div></div></div>
          <div class="col-md-3"><div class="kv"><div class="k">Signature</div><div class="v"><?php echo h($row['vta_signature'] ?? ''); ?></div></div></div>
        </div>
        <div class="mt-2">
          <small class="text-muted">Legend:
            <?php foreach ($VTA_MAP as $k=>$v): ?>
              <span class="mr-2"><strong><?php echo h($k); ?></strong> = <?php echo h($v); ?></span>
            <?php endforeach; ?>
          </small>
        </div>
        <div class="table-responsive mt-2">
          <table class="table table-sm table-bordered">
            <thead><tr><th>#</th><th>Item</th><th>Response</th></tr></thead>
            <tbody>
            <?php for ($i=1; $i<=28; $i++):
              $col = sprintf('vta_b%02d', $i);
              $val = strtoupper(trim((string)($row[$col] ?? '')));
              $resp = $VTA_MAP[$val] ?? ($val ?: '—');
              $itemText = $VTA_ITEMS[$i-1] ?? ("Item ".$i);
            ?>
              <tr>
                <td style="width:60px;"><?php echo $i; ?></td>
                <td><?php echo h($itemText); ?></td>
                <td style="width:160px;"><?php echo h($resp); ?></td>
              </tr>
            <?php endfor; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Offense / Program -->
    <div class="card section-card">
      <div class="card-header"><strong>Offense & Program</strong></div>
      <div class="card-body">
        <table class="table table-sm table-bordered mb-0">
          <tbody>
          <tr><th class="w-25">Offense Reason</th><td><?php echo h($row['offense_reason'] ?? ''); ?></td></tr>
          <tr><th>Offense Description</th><td><?php echo h($row['offense_description'] ?? ''); ?></td></tr>
          <tr><th>Personal Goal</th><td><?php echo h($row['personal_goal'] ?? ''); ?></td></tr>
          <tr><th>Counselor Name</th><td><?php echo h($row['counselor_name'] ?? ''); ?></td></tr>
          <tr><th>Chosen Group Time</th><td><?php echo h($row['chosen_group_time'] ?? ''); ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Workflow / Meta -->
    <div class="card section-card mb-5">
      <div class="card-header"><strong>Workflow & Metadata</strong></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4">
            <div class="kv"><div class="k">Digital Signature</div><div class="v"><?php echo h($row['digital_signature'] ?? ''); ?></div></div>
            <div class="kv"><div class="k">Signature Date</div><div class="v"><?php echo fmtDate($row['signature_date'] ?? null); ?></div></div>
            <div class="kv"><div class="k">Intake Date</div><div class="v"><?php echo fmtDate($row['intake_date'] ?? null); ?></div></div>
          </div>
          <div class="col-md-4">
            <div class="kv"><div class="k">Additional Charge Dates</div><div class="v"><?php echo h($row['additional_charge_dates'] ?? ''); ?></div></div>
            <div class="kv"><div class="k">Additional Charge Details</div><div class="v"><?php echo h($row['additional_charge_details'] ?? ''); ?></div></div>
          </div>
          <div class="col-md-4">
            <div class="kv"><div class="k">Record Created</div><div class="v"><?php echo fmtDate($row['created_at'] ?? null, 'm/d/Y h:i A'); ?></div></div>
            <div class="kv"><div class="k">Intake ID</div><div class="v"><?php echo (int)$row['intake_id']; ?></div></div>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- Consent Modals -->
<?php foreach ($CONSENTS as $c): $k = $c['key']; ?>
<div class="modal fade" id="modal_<?php echo h($k); ?>" tabindex="-1" role="dialog" aria-labelledby="label_<?php echo h($k); ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="label_<?php echo h($k); ?>"><?php echo h($c['title']); ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <?php echo $CONSENT_TEXT[$k] ?? '<p>No consent text available.</p>'; ?>
      </div>
      <div class="modal-footer">
        <span class="text-muted mr-auto">Signed on: <?php echo fmtDate($c['date'] ?? null); ?></span>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>


<!-- Find Client Modal -->
<div class="modal fade" id="findClientModal" tabindex="-1" role="dialog" aria-labelledby="findClientLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="findClientLabel">Find Existing Client</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
      </div>

      <div class="modal-body">
        <!-- Search form stands alone -->
        <form method="get" id="findClientSearch">
          <input type="hidden" name="id" value="<?php echo (int)$intake_id; ?>">
          <div class="form-group">
            <label>Search by name, full name, phone, or ID</label>
            <input type="text" class="form-control" name="find" value="<?php echo h($find); ?>" placeholder="e.g., Jane Doe, 210..., or 123">
          </div>
        </form>

        <?php if ($find !== ''): ?>
          <div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead><tr><th>ID</th><th>Name</th><th>DOB</th><th>Phone</th><th>Action</th></tr></thead>
              <tbody>
              <?php if (empty($find_results)): ?>
                <tr><td colspan="5" class="text-center text-muted">No results.</td></tr>
              <?php else: foreach ($find_results as $cand): ?>
                <tr>
                  <td><?php echo (int)$cand['id']; ?></td>
                  <td><?php echo h($cand['first_name'].' '.$cand['last_name']); ?></td>
                  <td><?php echo fmtDate($cand['date_of_birth']); ?></td>
                  <td><?php echo h($cand['phone_number'] ?? ''); ?></td>
                  <td>
                    <!-- Separate POST form per row is fine -->
                    <form method="post" action="intake-review.php?id=<?= (int)$intake_id ?>" class="m-0 p-0">
                      <?php if (function_exists('csrf_field')) csrf_field(); ?>
                      <input type="hidden" name="action" value="import">
                      <input type="hidden" name="imported_client_id" value="<?php echo (int)$cand['id']; ?>">
                      <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-file-import"></i> Import
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-outline-primary" form="findClientSearch">Search</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<script src="https://code.jquery.com/jquery-3.5.1.min.js"
        integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="
        crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
<?php if ($find !== ''): ?>
<script>
// Auto-open Find Client modal after search
$(function(){ $('#findClientModal').modal('show'); });
</script>
<?php endif; ?>
</body>
</html>
