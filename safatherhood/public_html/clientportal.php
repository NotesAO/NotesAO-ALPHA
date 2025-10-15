<?php
ob_start();
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}


// -------------------------
// LOGGING SETUP (optional)
// -------------------------
$log_file = '/home/notesao/logs/client.error.log';
ini_set('log_errors', 1);
ini_set('error_log', $log_file);

function log_event($message) {
    global $log_file;
    error_log("[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, 3, $log_file);
}

log_event("=== New page load: clientportal.php ===");

// -------------------------
// DB CONFIG
// -------------------------
define('db_host', '50.28.37.79');
define('db_name', 'clinicnotepro_safatherhood');
define('db_user', 'clinicnotepro_safatherhood_app');
define('db_pass', 'PF-m[T-+pF%g');

// -------------------------
// CONNECT TO CLIENT DB
// -------------------------
// Connect and force UTF-8
$con = new mysqli(db_host, db_user, db_pass, db_name);
if ($con->connect_error) {
    log_event("❌ DB connection failed: " . $con->connect_error);
    die("Database connection failed.");
}

// Ensure proper charset for curly quotes, en-dashes, etc.
if (!$con->set_charset('utf8mb4')) {
    log_event("⚠️ Failed to set charset: " . $con->error);
}
$con->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

log_event("✅ DB connected (charset=" . $con->character_set_name() . ")");


/* ───────────────────────────────────────────────────────────────────────────
   Attendance helpers for the modal (reuses existing helpers if present)
   ─────────────────────────────────────────────────────────────────────────── */



// Calendar renderer (copied from client_review_panel.php)
function buildCalendar($timestamp, $attendance, $excused, $unexcused){
    $today = new DateTime('today');
    $day   = date('d', $timestamp);
    $month = date('m', $timestamp);
    $year  = date('Y', $timestamp);

    // First day, title, and day-of-week math
    $first_day = mktime(0,0,0,$month,1,$year);
    $title     = date('F', $first_day);   // e.g., "August"
    $day_of_week = date('D', $first_day); // Sun/Mon/...

    // Map to blanks leading the month grid
    switch($day_of_week){
        case "Sun": $blank = 0; break; 
        case "Mon": $blank = 1; break; 
        case "Tue": $blank = 2; break; 
        case "Wed": $blank = 3; break; 
        case "Thu": $blank = 4; break; 
        case "Fri": $blank = 5; break; 
        case "Sat": $blank = 6; break; 
        default:    $blank = 0;
    }

    $days_in_month = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);

    // Calendar shell
    echo '<div class="calendar mb-3">';
    echo '<div class="calendar-header"><strong>' . htmlspecialchars($title . ' ' . $year) . '</strong></div>';
    echo '<table class="table table-sm table-bordered mb-0">';
    echo '<thead class="thead-light"><tr>';
    echo '<th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>';
    echo '</tr></thead><tbody><tr>';

    // Leading blanks
    for ($i = 0; $i < $blank; $i++) echo '<td class="bg-light"></td>';

    $day_count = $blank;
    for ($d = 1; $d <= $days_in_month; $d++){
        $date_str = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $classes = [];

        if (in_array($date_str, $attendance, true)) {
            $classes[] = 'attended';
        } elseif (in_array($date_str, $excused, true)) {
            $classes[] = 'excused';
        } elseif (in_array($date_str, $unexcused, true)) {
            $classes[] = 'unexcused';
        }

        // Today highlight
        if ($date_str === $today->format('Y-m-d')) $classes[] = 'today';

        echo '<td class="' . implode(' ', $classes) . '"><small>' . $d . '</small></td>';

        $day_count++;
        if ($day_count == 7){
            echo '</tr><tr>';
            $day_count = 0;
        }
    }

    // Trailing blanks
    while ($day_count > 0 && $day_count < 7){
        echo '<td class="bg-light"></td>';
        $day_count++;
    }

    echo '</tr></tbody></table></div>';
}

// Fallback loader using the same tables as admin code (no auth dependency).
function portal_fetch_attendance_arrays(mysqli $con, int $clientId): array {
    $attendance = [];
    $excused = [];
    $unexcused = [];

    // 1) Present/attended days (attendance_record -> therapy_session.date)
    $q1 = "SELECT DATE(ts.date) AS d
           FROM attendance_record ar
           JOIN therapy_session ts ON ts.id = ar.therapy_session_id
           WHERE ar.client_id = ?
           ORDER BY ts.date ASC";
    if ($stmt = $con->prepare($q1)) {
        $stmt->bind_param('i', $clientId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                if (!empty($row['d'])) $attendance[] = $row['d'];
            }
        }
        $stmt->close();
    }

    // 2) Absences with excused flag
    $q2 = "SELECT DATE(`date`) AS d, COALESCE(excused,0) AS excused
           FROM absence
           WHERE client_id = ?";
    if ($stmt = $con->prepare($q2)) {
        $stmt->bind_param('i', $clientId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                if (empty($row['d'])) continue;
                if ((int)$row['excused'] === 1) $excused[] = $row['d'];
                else $unexcused[] = $row['d'];
            }
        }
        $stmt->close();
    }

    // De-dupe just in case
    $attendance = array_values(array_unique($attendance));
    $excused    = array_values(array_unique($excused));
    $unexcused  = array_values(array_unique($unexcused));

    return [$attendance, $excused, $unexcused];
}

// Cache a few settings to avoid multiple queries
function get_settings_map(mysqli $con, array $keys): array {
    $out = array_fill_keys($keys, null);
    if (empty($keys)) return $out;
    $esc = array_map(function($k) use ($con){ return "'" . mysqli_real_escape_string($con,$k) . "'"; }, $keys);
    $sql = "SELECT setting_key, setting_value FROM portal_setting WHERE setting_key IN (" . implode(",", $esc) . ")";
    if ($rs = mysqli_query($con, $sql)) {
        while ($row = mysqli_fetch_assoc($rs)) {
            $k = $row['setting_key'];
            $v = trim((string)$row['setting_value']);
            $out[$k] = ($v !== '') ? $v : null;
        }
        mysqli_free_result($rs);
    }
    return $out;
}

/**
 * Decide which payment link to use based on gender + fee.
 * gender_id: 2=Male, 3=Female
 * fee: matches integer 25 -> $25 links; 15/10 -> Reduced links; else -> fallback
 */
function group_join_url(array $g): string {
    $u = $g['join_url'] ?? ($g['link'] ?? '');
    return is_string($u) ? trim($u) : '';
}


function portal_payment_link_for_group(array $map, int $therapy_group_id): string {
    $key = 'paylink.tg.' . $therapy_group_id;
    $link = trim($map[$key] ?? '');
    if ($link === '') $link = trim($map['payment_link_url'] ?? '');
    return $link;
}


// We'll compute $payment_link after $foundClient is resolved from POST.
$payment_link = null;





// -------------------------
// HELPER: Convert IDs to text
// -------------------------
function getProgramName($program_id) {
    switch($program_id) {
        case 1: return "Anger Management";
        case 2: return "Men's BIPP";
        case 3: return "Women's BIPP";
        case 4: return "Theft Intervention";
        default: return "Other/Unknown Program";
    }
}


function getReferralName($referral_id) {
    switch($referral_id) {
        case 1: return "Probation";
        case 2: return "Parole";
        case 3: return "Pretrial";
        case 4: return "CPS";
        case 5: return "Attorney";
        case 6: return "VTC";
        default: return "Other/Unknown Referral";
    }
}

// -----------------------------------------------------------------------------
// FULL GROUP DATA
// Each entry includes 'day_time' for short label in make-up list
// -----------------------------------------------------------------------------
require_once 'clientportal_lib.php';
$group_data = cpl_group_data_for_view($con);  // same keys per link as before

$groupData = $group_data;  // align name used below

// Ensure a row for every therapy_group_id with a placeholder if missing.
$have = [];
foreach ($groupData as $g) { $have[(int)$g['therapy_group_id']] = true; }

// pull all therapy_group ids
$tg = [];
$res = $con->query("SELECT id, name FROM therapy_group");
if ($res) { while ($r = $res->fetch_assoc()) $tg[] = $r; $res->free(); }

// placeholder URL shown when no real link is configured
$placeholder = 'https://example.com/coming-soon';

foreach ($tg as $row) {
    $id = (int)$row['id'];
    if (!isset($have[$id])) {
        $groupData[] = [
            'program_id'        => 0,
            'referral_type_id'  => 0,
            'gender_id'         => 0,
            'required_sessions' => 0,
            'fee'               => 0,
            'therapy_group_id'  => $id,
            'label'             => $row['name'] ?: ("Group #".$id),
            'day_time'          => '',
            'join_url'              => $placeholder,
        ];
    }
}


// ----------------------------------------------------
// PROCESS LOGIN / LOOKUP -----------------------------
// ----------------------------------------------------
$foundClient   = null;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $dob_year  = trim($_POST['dob_year'] ?? '');
    $dob_month = trim($_POST['dob_month'] ?? '');
    $dob_day   = trim($_POST['dob_day'] ?? '');
    $birth_place = trim($_POST['birth_place'] ?? '');
    $dob = null;

   
    // Basic validation: ensure all are non-empty
    if ($first_name && $last_name && $dob_year && $dob_month && $dob_day) {

        // reassemble into YYYY-MM-DD
        // zero-pad month/day to 2 digits if needed
        $dob = sprintf('%04d-%02d-%02d', $dob_year, $dob_month, $dob_day);

        log_event("📨 POST received | fn={$first_name}, ln={$last_name}, dob={$dob}, bp={$birth_place}");

        // ... proceed with your existing logic ...
    } else {
        $error_message = "First name, last name, and full DOB are required.";
        log_event("⚠️ Required fields missing (fn, ln, or dob).");
    }

    log_event("📨 POST received | fn={$first_name}, ln={$last_name}, dob={$dob}, bp={$birth_place}");

    if ($first_name !== '' && $last_name !== '' && is_string($dob) && $dob !== '') {


        // Pull from client table, birth_place optional
        $baseSql = "
            SELECT 
                c.id AS client_id,
                c.first_name, c.last_name, c.date_of_birth, c.birth_place,
                c.gender_id, c.referral_type_id, c.required_sessions, c.fee,
                c.therapy_group_id, c.program_id,
                c.weekly_attendance,
                c.attends_sunday, c.attends_monday, c.attends_tuesday,
                c.attends_wednesday, c.attends_thursday, c.attends_friday,
                c.attends_saturday,
                COALESCE(client_ledger.balance, 0) AS balance,
                c.orientation_date
            FROM client c
            LEFT JOIN (
                SELECT l.client_id, SUM(l.amount) AS balance
                FROM ledger l
                GROUP BY l.client_id
            ) AS client_ledger ON c.id = client_ledger.client_id
            WHERE LOWER(c.first_name) = LOWER(?)
            AND LOWER(c.last_name)  = LOWER(?)
            AND c.date_of_birth     = ?
        ";

        $params = [$first_name, $last_name, $dob];
        $types  = "sss";

        if ($birth_place !== '') {
            $baseSql .= " AND LOWER(c.birth_place) = LOWER(?) ";
            $params[] = $birth_place;
            $types   .= "s";
        }

        $baseSql .= " LIMIT 1";

        $stmt = $con->prepare($baseSql);
        if (!$stmt) {
            $error_message = "Server error (SQL).";
            log_event("❌ SQL prepare failed: " . $con->error . " | SQL=" . $baseSql);
        } else {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($client = $result->fetch_assoc()) {
                $_SESSION['client_verified'] = true;
                $foundClient = $client;
                log_event("✅ Match found: " . json_encode($client));

                // Payment link by therapy_group
                $needed_keys = [
                    'payment_link_url',
                    'paylink.tg.' . (int)$foundClient['therapy_group_id'],
                ];
                
                $payment_link = portal_payment_link_for_group(
                    get_settings_map($con, ['paylink.tg.'.(int)$foundClient['therapy_group_id'], 'payment_link_url']),
                    (int)$foundClient['therapy_group_id']
                );


                // Attendance arrays for the modal
                $attendanceDays = $excusedDays = $unexcusedDays = [];
                if (function_exists('get_client_attendance_days') && function_exists('get_client_absence_days')) {
                    $attendanceDays = get_client_attendance_days((int)$client['client_id']);
                    $abs = get_client_absence_days((int)$client['client_id']);
                    $excusedDays = $abs[0] ?? [];
                    $unexcusedDays = $abs[1] ?? [];
                } else {
                    list($attendanceDays, $excusedDays, $unexcusedDays) = portal_fetch_attendance_arrays($con, (int)$client['client_id']);
                }

            } else {
                $error_message = "No matching client record found.";
                log_event("❌ No match found for that name/DOB/birth_place.");
            }
            $stmt->close();
        }

    } else {
        $error_message = "First name, last name, and DOB are required.";
        log_event("⚠️ Required fields missing.");
    }

}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Client Portal - NotesAO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

    <style>
        body {
            background: #eef2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            max-width: 700px;
            width: 100%;
        }
        input, button {
            border-radius: 8px;
        }
        .error-message {
            background: #d9534f;
            color: white;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .info-table td { padding: 4px 8px; }
        .calendar .attended  { background:#d4edda; }    /* green-ish */
        .calendar .excused   { background:#fff3cd; }    /* yellow-ish */
        .calendar .unexcused { background:#f8d7da; }    /* red-ish */
        .calendar .today     { outline:2px solid #343a40; }
        .calendar .calendar-header { padding:6px 8px; background:#f1f3f5; border:1px solid #dee2e6; border-bottom:none; border-radius:4px 4px 0 0; }
        .calendar table { border-radius:0 0 4px 4px; overflow:hidden; }

    </style>
</head>
<body>

<div class="container">
    <a href="https://safatherhood.com/">
        <img alt="SA Fatherhood" src="safatherhoodlogo.png" class="img-fluid mb-3">
    </a>
    <h2 class="text-center">Client Portal</h2>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$foundClient && $error_message === ''): ?>
        <div class="error-message">No matching client record found.</div>
    <?php endif; ?>


    <!-- Simple lookup form -->
    <form method="post">
        <label>First Name:</label>
        <input type="text" name="first_name" class="form-control" required>

        <label>Last Name:</label>
        <input type="text" name="last_name" class="form-control" required>

        <label>Date of Birth:</label>
        <div class="form-row">
        <div class="col">
            <select name="dob_month" class="form-control" required>
            <option value="">Month</option>
            <?php
            for ($m = 1; $m <= 12; $m++) {
                $monthName = date("F", mktime(0, 0, 0, $m, 1));
                echo "<option value='$m'>$monthName</option>";
            }
            ?>
            </select>
        </div>
        <div class="col">
            <select name="dob_day" class="form-control" required>
            <option value="">Day</option>
            <?php
            for ($d = 1; $d <= 31; $d++) {
                echo "<option value='$d'>$d</option>";
            }
            ?>
            </select>
        </div>
        <div class="col">
            <select name="dob_year" class="form-control" required>
            <option value="">Year</option>
            <?php
            for ($y = 1930; $y <= (int)date('Y'); $y++) {
                echo "<option value='$y'>$y</option>";
            }
            ?>
            </select>
        </div>
        </div>


        <label>Birth Place (optional):</label>
        <input type="text" name="birth_place" class="form-control">

        <button type="submit" class="btn btn-primary btn-block mt-3">Submit</button>
    </form>
</div>

<?php if ($foundClient): ?>
<!-- Modal showing group info -->
<div class="modal fade" id="clientModal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content p-3">
      <div class="modal-header">
        <h5 class="modal-title">Welcome, <?= htmlspecialchars($foundClient['first_name']) ?>!</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
      <?php
        // ------------------------------------------------------
        // 1) Extract client data
        // ------------------------------------------------------
        $clientProgramId   = (int)$foundClient['program_id'];
        $clientReferralId  = (int)$foundClient['referral_type_id'];
        $clientGenderId    = (int)$foundClient['gender_id'];
        $clientSessions    = (int)$foundClient['required_sessions'];
        $clientFee         = (float)$foundClient['fee'];
        $clientGroupId     = (int)$foundClient['therapy_group_id'];

        $progName     = getProgramName($clientProgramId);
        $referralName = getReferralName($clientReferralId);
        $makeups = [];

        // ------------------------------------------------------
        // 2) Always display Program/Referral/RequiredSessions/Fee
        // ------------------------------------------------------
        echo '<table class="info-table">';
        echo '<tr><td><strong>Program:</strong></td><td>'   . htmlspecialchars($progName)     . '</td></tr>';
        echo '<tr><td><strong>Referral:</strong></td><td>'  . htmlspecialchars($referralName) . '</td></tr>';
        echo '<tr><td><strong>Required Sessions:</strong></td><td>' 
            . htmlspecialchars($clientSessions) . '</td></tr>';
        
        echo '</table>';
        echo '<hr>';
        ?>
        <?php
        // -------- Build $makeups + regular button text before Billing card --------
        if (!isset($makeups) || !is_array($makeups)) $makeups = [];

        // therapy_group metadata
        $tgMeta = [];
        if ($rs = $con->query("SELECT id,name,address FROM therapy_group")) {
            while ($r = $rs->fetch_assoc()) $tgMeta[(int)$r['id']] = $r;
            $rs->free();
        }

        $assignedTgId     = (int)$foundClient['therapy_group_id'];
        $assignedAddr     = $tgMeta[$assignedTgId]['address'] ?? '';
        $assignedIsVirtual= (strcasecmp($assignedAddr, 'Virtual') === 0);

        // Text for the REGULAR button: prefer day_time only
        // BEFORE the Billing card, after $assignedTgId / $tgMeta are set
        $regularBtnText = 'Regular session';
        foreach ($groupData as $g) {
            if ((int)($g['therapy_group_id'] ?? 0) === $assignedTgId) {
                $regularBtnText = trim((string)($g['day_time'] ?? ''))
                    ?: (trim((string)($g['label'] ?? '')) ?: (trim((string)($tgMeta[$assignedTgId]['name'] ?? 'Regular session'))));
                break;
            }
        }



        // Build make-up options if empty
        if (empty($makeups)) {
            foreach ($groupData as $g) {
                $tgid = (int)($g['therapy_group_id'] ?? 0);
                if ($tgid === 0 || $tgid === $assignedTgId) continue;

                // same program (0 = wildcard)
                $gProg = (int)($g['program_id'] ?? 0);
                if ($gProg !== 0 && $gProg !== (int)$foundClient['program_id']) continue;

                // modality rule: BIPP => virtual only; others => match assigned modality
                $addr = $g['address'] ?? ($tgMeta[$tgid]['address'] ?? '');
                $isV  = (strcasecmp($addr, 'Virtual') === 0);
                if ((int)$foundClient['program_id'] === 2 || (int)$foundClient['program_id'] === 3) {
                    if (!$isV) continue;
                } else {
                    if ($isV !== $assignedIsVirtual) continue;
                }

                // skip orientations
                $nm = ($g['label'] ?? ($tgMeta[$tgid]['name'] ?? ''));
                if (stripos($nm, 'Orientation') !== false || stripos($nm, 'ORE') !== false) continue;

                // virtual must have a link
                $link = group_join_url($g);
                if ($isV && $link === '') continue;

                // label for buttons: prefer day_time, else label, else tg name
                $text = trim((string)($g['day_time'] ?? ''));
                if ($text === '') $text = trim((string)($g['label'] ?? ''));
                if ($text === '') $text = trim((string)($tgMeta[$tgid]['name'] ?? 'Session'));
                if ($text === '') continue;

                $makeups[] = ['tgid'=>$tgid, 'label'=>$text, 'link'=>$link, 'isVirtual'=>$isV];
            }
        }

        // Pre-resolve the regular payment link
        $map = get_settings_map($con, ['paylink.tg.'.$assignedTgId,'payment_link_url']);
        $regularPayLink = portal_payment_link_for_group($map, $assignedTgId);

        // default link = regular; if a make-up was submitted previously, resolve it
        $resolved_link = $regularPayLink;
        $pay_mode = $_POST['pay_mode'] ?? '';
        $pay_tg   = (isset($_POST['pay_tg']) && ctype_digit($_POST['pay_tg'])) ? (int)$_POST['pay_tg'] : 0;
        if ($pay_mode==='makeup' && $pay_tg>0){
            $map2 = get_settings_map($con, ['paylink.tg.'.$pay_tg,'payment_link_url']);
            $resolved_link = portal_payment_link_for_group($map2, $pay_tg);
        }
        
        
        ?>





        <?php

        ?>
        <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title mb-2">Attendance</h5>
            <p class="mb-2"><small class="text-muted">
            Green = Attended, Yellow = Excused Absence, Red = Unexcused
            </small></p>
            <?php
            // Start month: orientation month if present, else two months ago
            $start = !empty($foundClient['orientation_date'])
                ? (new DateTime($foundClient['orientation_date']))->modify('first day of this month')
                : (new DateTime('first day of -2 months'));

            $end = (new DateTime('first day of this month'));
            $cursor = clone $start;
            $rendered = 0;

            while ($cursor <= $end && $rendered < 3) {
                buildCalendar($cursor->getTimestamp(), $attendanceDays, $excusedDays, $unexcusedDays);
                $cursor->modify('first day of next month');
                $rendered++;
            }
            ?>
        </div>
        </div>
        <?php



        // We'll use a simple flag to skip the $finalGroup logic if T4C
        $skipGroupLogic = false;

        // ------------------------------------------------------
        // 3) If T4C (program_id=1), show T4C block & skip $finalGroup
        // ------------------------------------------------------
        if ($clientProgramId === 1) {
            $skipGroupLogic = true;

            echo "<p><strong>Your Assigned Group(s):</strong></p>";

            // Convert 'attends_...' columns to booleans for T4C days
            $attends = [
                'sunday'    => (int)$foundClient['attends_sunday'],
                'monday'    => (int)$foundClient['attends_monday'],
                'wednesday' => (int)$foundClient['attends_wednesday'],
                'thursday'  => (int)$foundClient['attends_thursday'],
                'friday'    => (int)$foundClient['attends_friday']
            ];

            // Check if therapy_group_id indicates Virtual T4C (116)
            if ($clientGroupId === 116) {
                // ---------- Virtual T4C ----------
                $groupDisplay = [];



                if (!empty($groupDisplay)) {
                    echo "<ul>";
                    foreach ($groupDisplay as $item) {
                        echo "<li>$item</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p class='text-danger'>
                            ⚠️ No valid attendance days marked for T4C virtual group.
                          </p>";
                }

            } else {
                // ---------- In-Person T4C ----------
                $groupDisplay = [];

                
                if (!empty($groupDisplay)) {
                    echo "<ul>";
                    foreach ($groupDisplay as $line) {
                        echo "<li>" . htmlspecialchars($line) . "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p class='text-danger'>
                            ⚠️ No attendance days marked for T4C in-person groups.
                          </p>";
                }
            }

            // Done with T4C. We won't do finalGroup logic below.

        } // end if T4C

        // ------------------------------------------------------
        // 4) If not T4C, do pass-1 / pass-2 finalGroup logic
        // ------------------------------------------------------
        if (!$skipGroupLogic) {
            // Build therapy_group meta for Virtual vs In-Person checks
            $tgMeta = [];
            if ($res = $con->query("SELECT id, name, address FROM therapy_group")) {
                while ($r = $res->fetch_assoc()) { $tgMeta[(int)$r['id']] = $r; }
                $res->free();
            }

            // ──────────────────────────────────────────────────────────
            // PASS 1: prefer most-specific match (all fields may wildcard)
            // ──────────────────────────────────────────────────────────
            $exactMatch = null;
            $bestScore  = -1;

            foreach ($groupData as $g) {
                // Require program + therapy_group to match
                $gProg = (int)($g['program_id'] ?? 0);
                if ($gProg !== 0 && $gProg !== (int)$clientProgramId) continue;  // 0 = wildcard

                if ((int)($g['therapy_group_id'] ?? 0) !== (int)$clientGroupId)   continue;

                // Wildcard-friendly checks (NULL/0 means "any")
                $refOK = ($clientProgramId === 1) /* skip referral check for Anger/T4C if you want */
                    ? true
                    : (!isset($g['referral_type_id']) || (int)$g['referral_type_id'] === 0
                    || (int)$g['referral_type_id'] === (int)$clientReferralId);

                $genderOK = (!isset($g['gender_id']) || (int)$g['gender_id'] === 0
                            || (int)$g['gender_id'] === (int)$clientGenderId);

                $sessionsOK = (!isset($g['required_sessions']) || (int)$g['required_sessions'] === 0
                            || (int)$g['required_sessions'] === (int)$clientSessions);

                $feeOK = (!isset($g['fee']) || (float)$g['fee'] == 0.0
                        || (float)$g['fee'] == (float)$clientFee);

                if (!($refOK && $genderOK && $sessionsOK && $feeOK)) continue;

                // Specificity score: higher = more specific row (break ties by display_order if present)
                $score = 0;
                if (!empty($g['referral_type_id']))  $score++;
                if (!empty($g['gender_id']))         $score++;
                if (!empty($g['required_sessions'])) $score++;
                if (!empty($g['fee']) && (float)$g['fee'] > 0) $score++;

                // Prefer higher score; if equal, prefer lower display_order then first seen
                $g_display_order = isset($g['display_order']) ? (int)$g['display_order'] : PHP_INT_MAX;

                if ($score > $bestScore) {
                    $bestScore  = $score;
                    $exactMatch = $g;
                    $bestOrder  = $g_display_order;
                } elseif ($score === $bestScore) {
                    // Optional tie-breaker on display_order
                    if ($g_display_order < $bestOrder) {
                        $exactMatch = $g;
                        $bestOrder  = $g_display_order;
                    }
                }
            }

            $finalGroup = $exactMatch;
            if ($finalGroup && (int)($finalGroup['program_id'] ?? 0) === 0) {
                $finalGroup['program_id'] = (int)$clientProgramId;
            }


            // ──────────────────────────────────────────────────────────
            // PASS 2: fallback ignoring fee entirely (still wildcard others)
            // ──────────────────────────────────────────────────────────
            if (!$finalGroup) {
                log_event("⚠️ No PASS 1 match; trying PASS 2 (ignore fee).");

                $fallback     = null;
                $bestScore2   = -1;

                foreach ($groupData as $g) {
                    $gProg = (int)($g['program_id'] ?? 0);
                    if ($gProg !== 0 && $gProg !== (int)$clientProgramId) continue;  // 0 = wildcard

                    if ((int)($g['therapy_group_id'] ?? 0) !== (int)$clientGroupId)   continue;

                    $refOK = ($clientProgramId === 1)
                        ? true
                        : (!isset($g['referral_type_id']) || (int)$g['referral_type_id'] === 0
                        || (int)$g['referral_type_id'] === (int)$clientReferralId);

                    $genderOK = (!isset($g['gender_id']) || (int)$g['gender_id'] === 0
                                || (int)$g['gender_id'] === (int)$clientGenderId);

                    $sessionsOK = (!isset($g['required_sessions']) || (int)$g['required_sessions'] === 0
                                || (int)$g['required_sessions'] === (int)$clientSessions);

                    if (!($refOK && $genderOK && $sessionsOK)) continue;

                    // Specificity score (ignore fee in this pass)
                    $score = 0;
                    if (!empty($g['referral_type_id']))  $score++;
                    if (!empty($g['gender_id']))         $score++;
                    if (!empty($g['required_sessions'])) $score++;

                    $g_display_order = isset($g['display_order']) ? (int)$g['display_order'] : PHP_INT_MAX;

                    if ($score > $bestScore2) {
                        $bestScore2 = $score;
                        $fallback   = $g;
                        $bestOrder2 = $g_display_order;
                    } elseif ($score === $bestScore2) {
                        if ($g_display_order < $bestOrder2) {
                            $fallback   = $g;
                            $bestOrder2 = $g_display_order;
                        }
                    }
                }

                $finalGroup = $fallback;
            }

            // If we have a finalGroup, show BIPP or Anger info
            if ($finalGroup) {
                // If fallback, show note
                if (!$exactMatch) {
                    echo "<div class='alert alert-warning' role='alert'>
                            <strong>Note:</strong> The fee on your record ($"
                          . htmlspecialchars($clientFee)
                          . ") did not match exactly. We matched on your other info.
                          </div>";
                }

                // Distinguish BIPP in-person vs virtual by therapy_group.address
                $finalTgId      = (int)$finalGroup['therapy_group_id'];
                $finalAddr      = $tgMeta[$finalTgId]['address'] ?? '';
                $isVirtualBIPP  = (strcasecmp($finalAddr, 'Virtual') === 0);
                $isInPersonBIPP = !$isVirtualBIPP;


                if ($finalGroup) {
                    // If fallback, show note
                    if (!$exactMatch) {
                        echo "<div class='alert alert-warning' role='alert'>
                                <strong>Note:</strong> The fee on your record ($" . h($clientFee) . ") did not match exactly. We matched on your other info.
                            </div>";
                    }

                    // Helper: pick join_url, falling back to link
                    if (!function_exists('group_join_url')) {
                        function group_join_url(array $g): string {
                            $u = trim($g['join_url'] ?? '');
                            if ($u === '') $u = trim($g['link'] ?? '');
                            return $u;
                        }
                    }

                    echo "<p><strong>Your Assigned Group:</strong></p>";

                    // Determine modality by therapy_group.address
                    $finalTgId = (int)($finalGroup['therapy_group_id'] ?? 0);
                    $finalAddr = $tgMeta[$finalTgId]['address'] ?? ($finalGroup['address'] ?? '');
                    $isVirtual = (strcasecmp($finalAddr, 'Virtual') === 0);

                    // Assigned group details (text prefers day_time → label → therapy_group.name)
                    $assignedLabel = trim((string)($finalGroup['label'] ?? ''));
                    if ($assignedLabel === '') $assignedLabel = trim((string)($tgMeta[$finalTgId]['name'] ?? 'Assigned Session'));

                    $assignedText = trim((string)($finalGroup['day_time'] ?? ''));
                    if ($assignedText === '') $assignedText = $assignedLabel;

                    $assignedJoin  = group_join_url($finalGroup);

                    if ($isVirtual) {
                        echo "<p>" . h($assignedLabel) . "<br>";
                        if ($assignedJoin !== '') {
                            echo "<a href='" . h($assignedJoin) . "' target='_blank' rel='noopener'>" . h($assignedText) . "</a>";
                        } else {
                            echo "<span class='text-muted'>No Zoom link.</span>";
                        }
                        echo "</p>";
                    } else {
                        echo "<p>" . h($assignedLabel) . "</p>";
                        echo "<p>Location: 3014 Rivas Street, San Antonio, TX 78228</p>";
                        if (!empty($finalGroup['day_time'])) {
                            echo "<p>Time: " . h($finalGroup['day_time']) . "</p>";
                        }
                    }

                    /* ---------- Make-Up Groups ----------

                    BIPP (program_id 2 or 3): list all OTHER virtual groups in the same program.
                    Others: same-program + same modality as assigned.
                    Link text prefers day_time → label → therapy_group.name.
                    */
                    

                    /* ---------- Make-Up Groups (footer rendering only) ---------- */
                    $footerMakeups = []; // separate from the Billing `$makeups`

                    if ((int)$clientProgramId === 2 || (int)$clientProgramId === 3) {
                        // BIPP: all other virtual classes in same program
                        foreach ($groupData as $g) {
                            $tgid = (int)($g['therapy_group_id'] ?? 0);
                            $gProg = (int)($g['program_id'] ?? 0);
                            if ($gProg !== 0 && $gProg !== (int)$clientProgramId) continue;
                            if ($tgid === (int)$clientGroupId) continue;

                            $addr = $g['address'] ?? ($tgMeta[$tgid]['address'] ?? '');
                            if (strcasecmp($addr, 'Virtual') !== 0) continue;

                            $nm = ($g['label'] ?? ($tgMeta[$tgid]['name'] ?? ''));
                            if (stripos($nm, 'Orientation') !== false || stripos($nm, 'ORE') !== false) continue;

                            $link = group_join_url($g);
                            if ($link === '') continue;

                            $text = trim((string)($g['day_time'] ?? ''));
                            if ($text === '') $text = trim((string)($g['label'] ?? ''));
                            if ($text === '') $text = trim((string)($tgMeta[$tgid]['name'] ?? 'Session'));
                            if ($text === '') continue;

                            // key by tgid to avoid duplicates
                            $footerMakeups[$tgid] = ['tgid'=>$tgid, 'label'=>$text, 'link'=>$link, 'isVirtual'=>true];
                        }
                    } else {
                        // Non-BIPP: same program + same modality as assigned
                        foreach ($groupData as $g) {
                            if ($g === $finalGroup) continue;
                            $gProg = (int)($g['program_id'] ?? 0);
                            if ($gProg !== 0 && $gProg !== (int)$clientProgramId) continue;

                            $tgid = (int)($g['therapy_group_id'] ?? 0);
                            if ($tgid === (int)$clientGroupId) continue;

                            $nm = ($g['label'] ?? ($tgMeta[$tgid]['name'] ?? ''));
                            if (stripos($nm, 'Orientation') !== false || stripos($nm, 'ORE') !== false) continue;

                            $addr = $g['address'] ?? ($tgMeta[$tgid]['address'] ?? '');
                            $isV  = (strcasecmp($addr, 'Virtual') === 0);
                            if ($isV !== $isVirtual) continue;

                            $link = group_join_url($g);
                            if ($isVirtual && $link === '') continue;

                            $text = trim((string)($g['day_time'] ?? ''));
                            if ($text === '') $text = trim((string)($g['label'] ?? ''));
                            if ($text === '') $text = trim((string)($tgMeta[$tgid]['name'] ?? 'Session'));
                            if ($text === '') continue;

                            $footerMakeups[$tgid] = ['tgid'=>$tgid, 'label'=>$text, 'link'=>$link, 'isVirtual'=>$isVirtual];
                        }
                    }

                    // Render footer make-ups
                    if (!empty($footerMakeups)) {
                        echo "<hr><p><strong>Make-Up Groups (" . ($isVirtual ? "Virtual" : "In-Person") . "):</strong></p><ul>";
                        foreach ($footerMakeups as $mu) {
                            $text = h($mu['label']);
                            if (!empty($mu['isVirtual'])) {
                                echo "<li><a href='" . h($mu['link']) . "' target='_blank' rel='noopener'>{$text}</a></li>";
                            } else {
                                echo "<li><strong>{$text}</strong><br>Location: 3014 Rivas Street, San Antonio, TX 78228</li>";
                            }
                        }
                        echo "</ul>";
                    } else {
                        echo "<hr><p><em>No make-up groups available for your assignment.</em></p>";
                    }




                } else {
                    // No finalGroup
                    echo "<div class='alert alert-danger' role='alert'>
                            <strong>No matching link found</strong> for your group.
                            Please verify your data or contact the administrator.
                        </div>";
                }


            } else {
                // No finalGroup
                echo "<div class='alert alert-danger' role='alert'>
                        <strong>No matching link found</strong> for your group.
                        Please verify your data or contact the administrator.
                      </div>";
            }

        } // end if !$skipGroupLogic
      ?>
      <hr>

      <?php if ($clientProgramId === 2 || $clientProgramId === 3): ?>
        <p>
          <strong>Additional Admin/Reference:</strong><br>
          Virtual BIPP Intake Packet:
          <a href="https://safatherhood.notesao.com/intake.php" target="_blank">
            Click Here
          </a>
        </p>
      <?php endif; ?>
      
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('#clientModal').modal('show');
});
</script>
<?php endif; ?>

</body>
</html>