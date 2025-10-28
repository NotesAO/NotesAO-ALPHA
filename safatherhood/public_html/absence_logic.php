<?php
/**
 * SAF absence_logic.php
 * ---------------------
 * Generates absence rows for active clients based on attends_{day} flags and recorded attendance.
 * Behavior:
 *  - Inserts an absence only if 6 full days have passed after the missed scheduled day.
 *  - Only considers clients with exit_reason_id = 1 (Active) and not exited (exit_date is NULL) by default.
 *  - Ignores weekly_attendance field entirely.
 *  - T4C (program_id = 1): allow "double attendance" within the ISO week to cover one missing scheduled day,
 *    then a rolling 21-day window for any remaining surplus attendance.
 *  - Never duplicates an absence if one already exists for the client on that date (excused or not).
 *  - Never creates absences for days the client is not scheduled to attend (attends_{weekday} = 0).
 *  - Lookback window defaults to the last 8 weeks; configurable via ?lookback_weeks=N.
 *  - Dry-run support via ?dry=1. Restrict to a single client via ?client_id=ID.
 *
 * Tables referenced (expected):
 *  - client(id, program_id, exit_reason_id, exit_date, orientation_date,
 *           attends_sunday ... attends_saturday, required_sessions, therapy_group_id, first_name, last_name)
 *  - attendance_record(id, client_id, therapy_session_id)
 *  - therapy_session(id, date)
 *  - absence(id, client_id, date, note)
 */
declare(strict_types=1);
date_default_timezone_set('America/Chicago');

require_once __DIR__ . '/../config/config.php'; // provides $link (mysqli) and DB constants
require_once __DIR__ . '/helpers.php';           // optional h() helper if present

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// Inputs
$dry            = isset($_GET['dry']) ? (int)$_GET['dry'] : 0;
$onlyClientId   = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$includeExited  = isset($_GET['include_exited']) ? (int)$_GET['include_exited'] : 0;
$lookbackWeeks  = isset($_GET['lookback_weeks']) ? max(1, (int)$_GET['lookback_weeks']) : 8;

// message from the creator: hi and good luck! try finding my other easter eggs (34td)

// Bounds
$today        = (new DateTimeImmutable('today'));
$cutoffDate   = $today->modify('-6 days'); // wait 6 days before inserting an absence
$lookbackFrom = $today->modify("-{$lookbackWeeks} weeks");

// Fetch candidate clients
$clients = fetch_clients($onlyClientId, $includeExited);
printf("<p>Mode: %s | Clients: %d | Cutoff: %s | Lookback start: %s</p>",
  $dry ? 'DRY' : 'LIVE',
  count($clients),
  $cutoffDate->format('Y-m-d'),
  $lookbackFrom->format('Y-m-d')
);

foreach ($clients as $row) {
  process_client($row, $cutoffDate, $lookbackFrom, (bool)$dry);
}

echo "<hr><p>Done.</p>";

/* ====================================================================== */
/* Logic                                                                  */
/* ====================================================================== */

function fetch_clients(int $onlyClientId, int $includeExited): array {
  /** @var mysqli $link */
  global $link;

  $where = [];
  $params = [];
  $types = '';

  // Only clients who are scheduled at least one day
  $where[] = "(COALESCE(attends_sunday,0)+COALESCE(attends_monday,0)+COALESCE(attends_tuesday,0)+COALESCE(attends_wednesday,0)+COALESCE(attends_thursday,0)+COALESCE(attends_friday,0)+COALESCE(attends_saturday,0)) > 0";

  if (!$includeExited) {
    $where[] = "(exit_date IS NULL AND (exit_reason_id IS NULL OR exit_reason_id = 1))";
  }

  // Must have an orientation date or at least one attendance historically
  $having_start = " (orientation_date IS NOT NULL OR first_attendance IS NOT NULL) ";

  if ($onlyClientId > 0) {
    $where[] = "c.id = ?";
    $types  .= 'i';
    $params[] = $onlyClientId;
  }

  $sql = "
    SELECT
      c.id,
      c.first_name, c.last_name,
      c.program_id,
      c.orientation_date,
      c.required_sessions,
      c.attends_sunday, c.attends_monday, c.attends_tuesday, c.attends_wednesday, c.attends_thursday, c.attends_friday, c.attends_saturday
    , (
        SELECT MIN(ts.date)
        FROM attendance_record ar
        JOIN therapy_session ts ON ts.id = ar.therapy_session_id
        WHERE ar.client_id = c.id
      ) AS first_attendance
    FROM client c
    WHERE " . implode(' AND ', $where) . "
    HAVING {$having_start}
    ORDER BY c.id ASC
  ";

  $stmt = $link->prepare($sql);
  if (!$stmt) { echo "<pre>Prepare failed: " . h($link->error) . "</pre>"; return []; }
  if ($types !== '') { $stmt->bind_param($types, ...$params); }
  if (!$stmt->execute()) { echo "<pre>Execute failed: " . h($stmt->error) . "</pre>"; return []; }
  $res = $stmt->get_result();
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();
  return $rows;
}

/**
 * Build absences for one client.
 */
function process_client(array $c, DateTimeImmutable $cutoffDate, DateTimeImmutable $lookbackFrom, bool $dry): void {
  /** @var mysqli $link */
  global $link;

  $cid = (int)$c['id'];
  $progId = (int)($c['program_id'] ?? 0);

  $startDate = resolve_start_date($c, $lookbackFrom);
  if (!$startDate) {
    printf("<p>Client %d: no start date. Skipped.</p>", $cid);
    return;
  }

  printf("<h3>Client %d — %s %s (program_id=%d)</h3>", $cid, h($c['first_name']??''), h($c['last_name']??''), $progId);
  printf("<p>Start=%s | Cutoff=%s</p>", $startDate->format('Y-m-d'), $cutoffDate->format('Y-m-d'));

  // Attendance map: date => count
  $attendCounts = fetch_attendance_counts($cid, $startDate, $cutoffDate);

  // Existing absences set
  $existingAbsences = fetch_existing_absences($cid, $startDate, $cutoffDate);

  // Build required scheduled days list
  $requiredDays = enumerate_required_days($c, $startDate, $cutoffDate);

   // Decide per scheduled day
    foreach ($requiredDays as $d) {
    $dateStr = $d->format('Y-m-d');

    if ($d > $cutoffDate) { printf("<div>Skip: %s in 6-day wait</div>", $dateStr); continue; }
    if (isset($existingAbsences[$dateStr])) { printf("<div>Skip: absence exists %s</div>", $dateStr); continue; }
    if (!empty($attendCounts[$dateStr]))   { printf("<div>Skip: attended %s</div>", $dateStr); continue; }

    // Makeup after within +1..+6 days → insert EXCUSED absence
    if (consume_attendance_after($attendCounts, $d, 6)) {
        if ($dry) { printf("<div>[DRY] Would INSERT EXCUSED %s</div>", $dateStr); }
        else {
        $ok = insert_absence($cid, $dateStr, true);
        printf("<div>%s EXCUSED %s</div>", $ok ? 'INSERTED' : 'ERROR', $dateStr);
        }
        continue;
    }

    // Attendance within -1..-6 days before → covered, no absence
    if (consume_attendance_before($attendCounts, $d, 6)) {
        printf("<div>Skip: covered by earlier attendance near %s</div>", $dateStr);
        continue;
    }

    // True absence
    if ($dry) { printf("<div>[DRY] Would INSERT %s</div>", $dateStr); }
    else {
        $ok = insert_absence($cid, $dateStr, false);
        printf("<div>%s %s</div>", $ok ? 'INSERTED' : 'ERROR', $dateStr);
    }
    }

}

/** Start date preference: orientation_date else first_attendance, capped by lookback window. */
function resolve_start_date(array $c, DateTimeImmutable $lookbackFrom): ?DateTimeImmutable {
  $od = !empty($c['orientation_date']) ? (new DateTimeImmutable($c['orientation_date']))->setTime(0,0,0) : null;
  $fa = !empty($c['first_attendance']) ? (new DateTimeImmutable($c['first_attendance']))->setTime(0,0,0) : null;

  $chosen = $od ?: $fa;
  if (!$chosen) return null;

  if ($chosen < $lookbackFrom) $chosen = $lookbackFrom;
  return $chosen;
}

/** Build required days list based on attends_{weekday} flags from start..cutoff inclusive. */
function enumerate_required_days(array $c, DateTimeImmutable $from, DateTimeImmutable $to): array {
  $flags = [
    0 => (int)($c['attends_sunday'] ?? 0),
    1 => (int)($c['attends_monday'] ?? 0),
    2 => (int)($c['attends_tuesday'] ?? 0),
    3 => (int)($c['attends_wednesday'] ?? 0),
    4 => (int)($c['attends_thursday'] ?? 0),
    5 => (int)($c['attends_friday'] ?? 0),
    6 => (int)($c['attends_saturday'] ?? 0),
  ];
  $days = [];
  for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
    $dow = (int)$d->format('w'); // 0=Sun..6=Sat
    if (!empty($flags[$dow])) {
      $days[] = $d;
    }
  }
  return $days;
}

/** Attendance counts by date between from..to. */
function fetch_attendance_counts(int $cid, DateTimeImmutable $from, DateTimeImmutable $to): array {
  global $link;
  $sql = "
    SELECT DATE(ts.date) AS d, COUNT(*) AS cnt
    FROM attendance_record ar
    JOIN therapy_session ts ON ts.id = ar.therapy_session_id
    WHERE ar.client_id = ?
      AND ts.date BETWEEN ? AND ?
    GROUP BY DATE(ts.date)
  ";
  $stmt = $link->prepare($sql);
  $fromS = $from->format('Y-m-d');
  $toS   = $to->format('Y-m-d');
  $stmt->bind_param('iss', $cid, $fromS, $toS);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  while ($row = $res->fetch_assoc()) {
    $out[$row['d']] = (int)$row['cnt'];   // keys like 'YYYY-MM-DD'
  }
  $stmt->close();
  return $out;
}

/** Existing absences between from..to as a set. */
function fetch_existing_absences(int $cid, DateTimeImmutable $from, DateTimeImmutable $to): array {
  /** @var mysqli $link */
  global $link;

  $sql = "SELECT date FROM absence WHERE client_id = ? AND date BETWEEN ? AND ?";
  $stmt = $link->prepare($sql);
  $fromS = $from->format('Y-m-d');
  $toS   = $to->format('Y-m-d');
  $stmt->bind_param('iss', $cid, $fromS, $toS);
  $stmt->execute();
  $res = $stmt->get_result();
  $out = [];
  while ($row = $res->fetch_assoc()) {
    $out[$row['date']] = true;
  }
  $stmt->close();
  return $out;
}

function consume_attendance_after(array &$attendCounts, DateTimeImmutable $d, int $days=6): bool {
  for ($i = 1; $i <= $days; $i++) {
    $k = $d->modify("+{$i} days")->format('Y-m-d');
    if (!empty($attendCounts[$k])) {
      $attendCounts[$k]--;
      if ($attendCounts[$k] <= 0) unset($attendCounts[$k]);
      return true;
    }
  }
  return false;
}
function consume_attendance_before(array &$attendCounts, DateTimeImmutable $d, int $days=6): bool {
  for ($i = 1; $i <= $days; $i++) {
    $k = $d->modify("-{$i} days")->format('Y-m-d');
    if (!empty($attendCounts[$k])) {
      $attendCounts[$k]--;
      if ($attendCounts[$k] <= 0) unset($attendCounts[$k]);
      return true;
    }
  }
  return false;
}


function insert_absence(int $cid, string $date, bool $excused=false): bool {
  $dsn = "mysql:host=" . db_host . ";dbname=" . db_name . ";charset=utf8mb4";
  $opts = [
    PDO::ATTR_EMULATE_PREPARES=>false,
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  ];
  try { $pdo = new PDO($dsn, db_user, db_pass, $opts); } catch (Throwable $e) { error_log($e->getMessage()); return false; }

  $note = "Auto generated " . date('Y-m-d');
  $hasExcusedCol = false;
  try {
    $chk = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='absence' AND COLUMN_NAME='excused' LIMIT 1");
    $chk->execute(); $hasExcusedCol = (bool)$chk->fetchColumn();
  } catch (Throwable $e) { /* ignore */ }

  try {
    if ($hasExcusedCol) {
      $st = $pdo->prepare("INSERT INTO absence (client_id, date, note, excused) VALUES (?, ?, ?, ?)");
      return $st->execute([$cid, $date, $note, $excused ? 1 : 0]);
    } else {
      // no column: encode state in the note
      if ($excused) $note .= " [EXCUSED]";
      $st = $pdo->prepare("INSERT INTO absence (client_id, date, note) VALUES (?, ?, ?)");
      return $st->execute([$cid, $date, $note]);
    }
  } catch (Throwable $e) {
    error_log("Insert absence failed cid={$cid} date={$date}: ".$e->getMessage());
    return false;
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>SAF Absence Logic</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="icon" href="/favicons/favicon.ico">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    body{padding:16px}
    .muted{color:#6c757d}
    h3{margin-top:24px}
  </style>
</head>
<body>
  <h2>SAF Absence Logic</h2>
  <p class="muted">Adds absences 6 days after a missed scheduled day. T4C allows weekly make-up by surplus attendance.</p>
</body>
</html>
