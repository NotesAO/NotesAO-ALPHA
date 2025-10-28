<?php
require_once "../config/config.php";
require_once "helpers.php";
require_once "sql_functions.php";

// Fallback HTML escaper
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}


/**
 * DWAG absence logic
 * - Same structure as FFLTest
 * - Inserts an absence the day AFTER a required session with no attendance
 * - No ±6 day makeup search
 */

function buildAbsenceRecords(): void
{
    global $link;

    $sql = "SELECT
              c.id,
              c.orientation_date,
              first_attendance.date AS first_attendance,
              c.weekly_attendance,
              c.attends_sunday, c.attends_monday, c.attends_tuesday,
              c.attends_wednesday, c.attends_thursday, c.attends_friday, c.attends_saturday
            FROM client c
            LEFT JOIN (
              SELECT ar.client_id, MIN(ts.date) AS date
              FROM attendance_record ar
              LEFT JOIN therapy_session ts ON ar.therapy_session_id = ts.id
              GROUP BY ar.client_id
            ) AS first_attendance ON c.id = first_attendance.client_id
            LEFT JOIN (
              SELECT ar.client_id, COUNT(*) AS cnt
              FROM attendance_record ar
              GROUP BY ar.client_id
            ) AS sessions_attended ON c.id = sessions_attended.client_id
            WHERE c.exit_date IS NULL
              AND c.orientation_date IS NOT NULL
              AND c.weekly_attendance >= 0
              AND (c.required_sessions > IFNULL(sessions_attended.cnt,0))";

    if ($stmt = mysqli_prepare($link, $sql)) {
        if (mysqli_stmt_execute($stmt)) {
            $rs = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($rs)) {
                buildAbsenceRecordsForClient(
                    (int)$row["id"],
                    $row["orientation_date"],

                    (int)$row["weekly_attendance"],
                    $row
                );
            }
        } else {
            echo "ERROR<br>" . $stmt->error . "<br>";
        }
    }
}

function nextBusinessDay(DateTime $d): DateTime {
    $nb = (clone $d)->modify('+1 day')->setTime(0,0,0,0);
    while (in_array($nb->format('N'), ['6','7'], true)) { $nb->modify('+1 day'); }
    return $nb;
}

function buildAbsenceRecordsForClient(int $client_id, string $start_date, int $weekly_attendance, array $row): void
{

    global $link;

    echo "<b>Checking attendance for client {$client_id}</b><br>";
    echo "orientation date: " . h($row['orientation_date']) . "<br>";

    $start_date = (new DateTime($start_date))->setTime(0,0,0,0);
    $stop_date  = (new DateTime())->setTime(0,0,0,0); // today 00:00
    // Avoid mass backfill
    $maxOld     = (new DateTime("-8 weeks"))->setTime(0,0,0,0);

    echo "start_date: " . $start_date->format('Y-m-d H:i:s') .
         " stop_date: " . $stop_date->format('Y-m-d H:i:s') . "<br>";

    $attended_days = get_client_attendance_days((string)$client_id);  // ["YYYY-mm-dd", ...]
    $temp = get_client_absence_days((string)$client_id);
    $excused_absence_days   = is_array($temp) && isset($temp[0]) ? $temp[0] : [];
    $unexcused_absence_days = is_array($temp) && isset($temp[1]) ? $temp[1] : [];

        // If there are multiple attendance rows on the same calendar day,
    // excuse one prior unexcused absence per extra attendance.
    $attended_days_full = $attended_days;                    // keep original list
    $counts = array_count_values($attended_days_full);       // 'YYYY-mm-dd' => n
    foreach ($counts as $dateStr => $cnt) {
        if ($cnt > 1) {
            $extra = $cnt - 1; // how many absences to excuse
            for ($i = 0; $i < $extra; $i++) {
                $stmt = $link->prepare(
                    "SELECT id FROM absence
                       WHERE client_id = ? AND date < ? AND excused = 0
                       ORDER BY date DESC LIMIT 1"
                );
                $stmt->bind_param('is', $client_id, $dateStr);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($rowA = $res->fetch_assoc()) {
                    $aid = (int)$rowA['id'];
                    $upd = $link->prepare(
                        "UPDATE absence
                            SET excused = 1,
                                note = CONCAT(COALESCE(note,''),' (Excused via double attendance on ', ?, ')')
                          WHERE id = ?"
                    );
                    $upd->bind_param('si', $dateStr, $aid);
                    $upd->execute();
                    echo "excused prior absence id {$aid} before {$dateStr} due to double attendance.<br>";
                } else {
                    echo "no prior unexcused absence to excuse for double attendance on {$dateStr}.<br>";
                    break; // nothing older to excuse
                }
            }
        }
    }


    // Build required day list (one entry per required session)
    $required_days = [];
    $d = clone $start_date;
    while ($d < $stop_date) {
        $need = sessionsRequiredThatDay($d, $row); // 0 or 1 for DWAG
        for ($i=0; $i<$need; $i++) { $required_days[] = clone $d; }
        $d->modify('+1 day');
    }

    // First pass: match exact same-day attendance
    $absence_candidates = [];
    while (!empty($required_days)) {
        $req = array_shift($required_days);
        $key = array_search($req->format("Y-m-d"), $attended_days, true);
        if ($key !== false) {
            echo "using attendance on " . $req->format("Y-m-d") . " to satisfy " . $req->format("Y-m-d") . "<br>";
            unset($attended_days[$key]);
        } else {
            $absence_candidates[] = $req;
        }
    }
    $required_days = $absence_candidates;

    // Second pass: honor existing absences (excused or unexcused)
    $clean = [];
    foreach ($required_days as $req) {
        $dateStr = $req->format("Y-m-d");
        $used = false;

        $k = array_search($dateStr, $excused_absence_days, true);
        if ($k !== false) {
            echo "using excused absence {$dateStr} to satisfy {$dateStr}<br>";
            unset($excused_absence_days[$k]);
            $used = true;
        }
        if (!$used) {
            $k = array_search($dateStr, $unexcused_absence_days, true);
            if ($k !== false) {
                echo "using un-excused absence {$dateStr} to satisfy {$dateStr}<br>";
                unset($unexcused_absence_days[$k]);
                $used = true;
            }
        }

        if (!$used) { $clean[] = $req; }
    }
    $required_days = $clean;

    // Final pass: next-business-day rule (no makeup window)
    $today = (new DateTime())->setTime(0,0,0,0);
    foreach ($required_days as $req) {
        $nbd = nextBusinessDay($req);
        if ($today < $nbd) {
            echo "not creating absence ".$req->format("Y-m-d")." awaiting ".$nbd->format("Y-m-d").".<br>";
            continue;
        }
        insertAbsenceRecord($client_id, $req->format("Y-m-d"));
    }

}
/**
 * DWAG: one possible session per day based on attends_* flags.
 * Returns 0 or 1.
 */
function sessionsRequiredThatDay(DateTime $d, array $row): int
{
    switch ($d->format('w')) { // 0=Sun … 6=Sat
        case '0': return !empty($row['attends_sunday'])     ? 1 : 0;
        case '1': return !empty($row['attends_monday'])     ? 1 : 0;
        case '2': return !empty($row['attends_tuesday'])    ? 1 : 0;
        case '3': return !empty($row['attends_wednesday'])  ? 1 : 0;
        case '4': return !empty($row['attends_thursday'])   ? 1 : 0;
        case '5': return !empty($row['attends_friday'])     ? 1 : 0;
        case '6': return !empty($row['attends_saturday'])   ? 1 : 0;
    }
    return 0;
}

function insertAbsenceRecord(int $client_id, string $date): void
{
    $dsn = "mysql:host=" . db_host . ";dbname=" . db_name . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    try {
        $pdo = new PDO($dsn, db_user, db_pass, $options);
    } catch (Exception $e) {
        error_log($e->getMessage());
        exit('Error creating absence record');
    }

    $stmt = $pdo->prepare("INSERT INTO absence (client_id, date, note) VALUES (?,?,?)");
    $note = "Auto generated " . (new DateTime())->format("Y-m-d");

    echo "insertAbsenceRecord client: {$client_id} date: {$date} note: {$note}<br>";
    if (!$stmt->execute([$client_id, $date, $note])) {
        echo "Error creating absence record";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NotesAO - Absence Report</title>
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
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
        integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk"
        crossorigin="anonymous">
</head>
<body>
<?php buildAbsenceRecords(); ?>
</body>
</html>
