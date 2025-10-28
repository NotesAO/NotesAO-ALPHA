<?php
declare(strict_types=1);
include_once 'auth.php';
check_loggedin($con);
header('Content-Type: application/json');

/* --- helpers --- */
function sql_one(mysqli $con, string $q, array $p = []) {
  $st = $con->prepare($q);
  if ($p) { $st->bind_param(str_repeat('s', count($p)), ...$p); }
  $st->execute();
  $r = $st->get_result();
  return $r ? $r->fetch_assoc() : null;
}
function sql_all(mysqli $con, string $q, array $p = []) : array {
  $st = $con->prepare($q);
  if ($p) { $st->bind_param(str_repeat('s', count($p)), ...$p); }
  $st->execute();
  $r = $st->get_result();
  return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
function dows_to_flags(array $dows): array {
  $f=['sunday'=>0,'monday'=>0,'tuesday'=>0,'wednesday'=>0,'thursday'=>0,'friday'=>0,'saturday'=>0];
  foreach ($dows as $n) {
    $name = strtolower((new DateTime("Sunday +$n day"))->format('l'));
    $f[$name] = 1;
  }
  return $f;
}
function table_exists(mysqli $con, string $name): bool {
  if (!preg_match('/^[a-zA-Z0-9_]+$/',$name)) return false;
  $name = $con->real_escape_string($name);
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$name' LIMIT 1";
  $res = $con->query($sql);
  return $res && $res->num_rows > 0;
}

/* --- /helpers --- */

$client_id = (int)($_GET['client_id'] ?? 0);
if ($client_id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad input']);
  exit;
}

/* client + flags */
$client = sql_one(
  $con,
  "SELECT orientation_date,
          attends_sunday, attends_monday, attends_tuesday,
          attends_wednesday, attends_thursday, attends_friday, attends_saturday,
          program_id
     FROM client WHERE id=?",
  [$client_id]
);

$lp = sql_one(
  $con,
  "SELECT CAST(ts.`date` AS DATE) d
     FROM attendance_record ar
     JOIN therapy_session ts ON ts.id=ar.therapy_session_id
    WHERE ar.client_id=?
    ORDER BY ts.`date` DESC
    LIMIT 1",
  [$client_id]
);
$last = $lp['d'] ?? null;

/* keep in sync with client review panel */
$EXPECTED_DOWS = [
  4 => [6,0],   // Parenting: Sat+Sun
  6 => [6,0],   // Life Skills/Anti Theft: Sat+Sun
  1 => [5],     // DOEP example: Sat only
];

$flags = [
  'sunday'    => (int)($client['attends_sunday']??0),
  'monday'    => (int)($client['attends_monday']??0),
  'tuesday'   => (int)($client['attends_tuesday']??0),
  'wednesday' => (int)($client['attends_wednesday']??0),
  'thursday'  => (int)($client['attends_thursday']??0),
  'friday'    => (int)($client['attends_friday']??0),
  'saturday'  => (int)($client['attends_saturday']??0),
];
if (array_sum($flags) === 0) {
  $flags = dows_to_flags($EXPECTED_DOWS[(int)($client['program_id'] ?? 0)] ?? []);
}

/* window: from orientation_date to yesterday */
$start = new DateTime($client['orientation_date'] ?: 'today');
$yday  = new DateTime('yesterday');


$gaps = [];
if ($start <= $yday) {
  $from = $start->format('Y-m-d');
  $to   = $yday->format('Y-m-d');

  $absTable = table_exists($con,'absence_record') ? 'absence_record'
          : (table_exists($con,'absence') ? 'absence' : null);

  // prefetch attendance dates in range
  $att_rows = sql_all(
    $con,
    "SELECT CAST(ts.`date` AS DATE) d
       FROM attendance_record ar
       JOIN therapy_session ts ON ts.id=ar.therapy_session_id
      WHERE ar.client_id=? AND CAST(ts.`date` AS DATE) BETWEEN ? AND ?",
    [$client_id, $from, $to]
  );
  $att_set = [];
  foreach ($att_rows as $r) { $att_set[$r['d']] = true; }

  $abs_set = [];
  if ($absTable) {
    $abs_rows = sql_all(
      $con,
      "SELECT CAST(`date` AS DATE) d
        FROM `$absTable`
        WHERE client_id=? AND CAST(`date` AS DATE) BETWEEN ? AND ?",
      [$client_id, $from, $to]
    );
    foreach ($abs_rows as $r) { $abs_set[$r['d']] = true; }
  }

  for ($d = new DateTime($from); $d <= $yday; $d->modify('+1 day')) {
    $dow = strtolower($d->format('l'));
    if (empty($flags[$dow])) continue;

    $ds = $d->format('Y-m-d');

    // skip days with attendance
    if (!empty($att_set[$ds])) continue;

    // include all missed expected dates; annotate if absence already exists
    $gaps[] = [
      'date' => $ds,
      'has_absence' => isset($abs_set[$ds]) ? 1 : 0
    ];
  }
}

/* next expected within 14 days */
$next = null;
$today = new DateTime('today');
for ($i = 0; $i < 14; $i++) {
  $d = (clone $today)->modify("+$i day");
  $dow = strtolower($d->format('l'));
  if (!empty($flags[$dow])) { $next = $d->format('Y-m-d'); break; }
}

echo json_encode([
  'ok' => true,
  'last_attended' => $last ?: '—',
  'next_expected' => $next ?: '—',
  'gaps' => $gaps
]);
