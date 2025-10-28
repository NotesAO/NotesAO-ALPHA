<?php
// ------------------------------------------------------------
// client-reminders.php  (FFLTest)
// Index: list groups (today-first), quick-send, broadcast composer.
// Group view: roster + per-group editor + send selected.
// In-house WYSIWYG (no external deps), unsubscribe headers/links,
// client-specific {{group_link}} via clientportal_lib.php.
// ------------------------------------------------------------
declare(strict_types=1);
date_default_timezone_set('America/Chicago');

require_once '../config/config.php';
require_once 'auth.php';
check_loggedin($con, '../index.php');
// Clinic-aware base URL
function base_url(): string {
  $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $base   = defined('APP_BASE_URL') ? APP_BASE_URL : ("$scheme://$host");
  return rtrim($base, '/');
}


require_once 'clientportal_lib.php'; // exposes notesao_regular_group_link(...) + $groupData in your copy

/* -----------------------------------------------
   CSRF
-------------------------------------------------*/
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}


/* -----------------------------------------------
   DB helpers
-------------------------------------------------*/
function qall(mysqli $con, string $sql, array $params = [], string $types = ''): array {
    $stmt = $con->prepare($sql);
    if (!$stmt) throw new RuntimeException("Prepare failed: {$con->error}");
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}
function qone(mysqli $con, string $sql, array $params = [], string $types = ''): ?array {
    $r = qall($con, $sql, $params, $types);
    return $r[0] ?? null;
}
// Session lookups for a client’s regular group
function last_regular_session(mysqli $con, int $clientId, int $groupId): ?array {
  $sql = "SELECT ts.id, ts.date
          FROM therapy_session ts
          WHERE ts.therapy_group_id=? AND ts.date < NOW()
          ORDER BY ts.date DESC LIMIT 1";
  return qone($con,$sql,[$groupId],'i');
}
function next_regular_session(mysqli $con, int $clientId, int $groupId): ?array {
  $sql = "SELECT ts.id, ts.date
          FROM therapy_session ts
          WHERE ts.therapy_group_id=? AND ts.date > NOW()
          ORDER BY ts.date ASC LIMIT 1";
  return qone($con,$sql,[$groupId],'i');
}

// Did client attend the given session?
function attended_session(mysqli $con, int $clientId, int $sessionId): bool {
  $r = qone($con,"SELECT 1 FROM attendance_record WHERE client_id=? AND therapy_session_id=? LIMIT 1",
            [$clientId,$sessionId],'ii');
  return (bool)$r;
}

// Has client attended ANY session between (last regular, next regular)?
function attended_makeup_in_window(mysqli $con, int $clientId, string $startDt, string $endDt): bool {
  $sql="SELECT 1
        FROM attendance_record ar
        JOIN therapy_session ts ON ts.id=ar.therapy_session_id
        WHERE ar.client_id=? AND ts.date > ? AND ts.date < ?
        LIMIT 1";
  return (bool)qone($con,$sql,[$clientId,$startDt,$endDt],'iss');
}

// Optional: flag unexcused absence on the regular day (use if you record absence rows)
function unexcused_absence_on(mysqli $con, int $clientId, string $dayYmd): bool {
  $r=qone($con,"SELECT 1 FROM absence WHERE client_id=? AND date=? AND (excused=0 OR excused IS NULL) LIMIT 1",
          [$clientId,$dayYmd],'is');
  return (bool)$r;
}

// Upcoming sessions for same program, between now and next regular
function upcoming_makeups(mysqli $con, int $programId, string $endDt): array {
  $sql="SELECT ts.id AS session_id, ts.date, tg.id AS group_id, tg.name, tg.program_id,
               CONCAT_WS(' ', tg.address, tg.city, tg.state, tg.zip) AS address
        FROM therapy_session ts
        JOIN therapy_group tg ON tg.id=ts.therapy_group_id
        WHERE tg.program_id=? AND ts.date >= NOW() AND ts.date < ?
        ORDER BY ts.date ASC, tg.name ASC";
  return qall($con,$sql,[$programId,$endDt],'is');
}

function get_group_address(mysqli $con, int $groupId): string {
  static $cache=[];
  if (isset($cache[$groupId])) return $cache[$groupId];
  $r = qone($con,"SELECT CONCAT_WS(' ', address, city, state, zip) AS addr FROM therapy_group WHERE id=?",[$groupId],'i');
  return $cache[$groupId] = trim($r['addr'] ?? '');
}

/* -----------------------------------------------
   Group helpers
-------------------------------------------------*/
function parse_weekday_time(string $groupName): array {
    // Return [weekdayIdx (0..6 or null), 'Monday', '7:30 PM']
    $wdMap = ['SUNDAY'=>0,'MONDAY'=>1,'TUESDAY'=>2,'WEDNESDAY'=>3,'THURSDAY'=>4,'FRIDAY'=>5,'SATURDAY'=>6];
    $weekday = null; $weekdayIdx = null; $timeDisp = null;
    foreach ($wdMap as $word=>$idx) {
        if (stripos($groupName, $word)!==false) { $weekdayIdx=$idx; $weekday=ucfirst(strtolower($word)); break; }
    }
    if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(AM|PM)\b/i', $groupName, $m)) {
        $hh=(int)$m[1]; $mm=$m[2]??'00'; $ampm=strtoupper($m[3]);
        $timeDisp = sprintf('%d:%s %s',$hh,str_pad($mm,2,'0',STR_PAD_LEFT),$ampm);
    }
    return [$weekdayIdx,$weekday,$timeDisp];
}
function day_ordering(): array {
    $todayIdx=(int)date('w'); $seq=[];
    for ($i=0;$i<7;$i++) $seq[] = ($todayIdx+$i)%7;
    return $seq;
}
function day_name_from_idx(int $i): string {
    return ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][$i];
}

function get_all_groups(mysqli $con): array {
    // therapy_group: id, program_id, name, address, city, state, zip
    $sql="SELECT id, program_id, name, address, city, state, zip
          FROM therapy_group
          ORDER BY name";
    return qall($con,$sql);
}
function get_clients_for_group(mysqli $con, int $groupId): array {
    $sql="SELECT id, first_name, last_name, email, program_id, therapy_group_id, gender_id, case_manager_id

          FROM client
          WHERE therapy_group_id=?
            AND exit_date IS NULL
            AND email IS NOT NULL AND email <> ''
          ORDER BY last_name, first_name";
    return qall($con,$sql,[$groupId],'i');
}
function get_all_active_clients(mysqli $con): array {
    $sql="SELECT
             id, first_name, last_name, email,
             program_id, therapy_group_id, gender_id, case_manager_id,
             attends_sunday, attends_monday, attends_tuesday,
             attends_wednesday, attends_thursday, attends_friday, attends_saturday
          FROM client
          WHERE exit_date IS NULL
            AND email IS NOT NULL AND email <> ''
          ORDER BY last_name, first_name";
    return qall($con,$sql);
}


/* -----------------------------------------------
   T4C rosters (program_id = 1)
   - In-person via attends_<day> booleans
   - Virtual as therapy_group_id = 116
-------------------------------------------------*/
function get_t4c_day_rosters(mysqli $con): array {
    $days=['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
    $rosters=[];
    foreach ($days as $d) {
        $col="attends_{$d}";
        $sql="SELECT id, first_name, last_name, email, program_id, therapy_group_id, gender_id, case_manager_id

              FROM client
              WHERE program_id=1
                AND exit_date IS NULL
                AND $col=1
                AND email IS NOT NULL AND email <> ''
              ORDER BY last_name, first_name";
        $rosters[$d]=qall($con,$sql);
    }
    $virtual=qall($con,"SELECT id, first_name, last_name, email, program_id, therapy_group_id, gender_id, case_manager_id

                        FROM client
                        WHERE program_id=1
                          AND exit_date IS NULL
                          AND therapy_group_id=116
                          AND email IS NOT NULL AND email <> ''
                        ORDER BY last_name, first_name");
    return [$rosters,$virtual];
}

function program_name_from_id(int $pid): string {
  return [1=>'Thinking for a Change', 2=>"Men's BIPP", 3=>"Women's BIPP", 4=>'Anger Control'][$pid] ?? 'Program';
}

/**
 * Returns:
 * [
 *   <program_id> => [
 *     'program_name' => 'Thinking for a Change',
 *     'groups' => [
 *        <group_id> => ['group_name' => 'Sunday Men’s BIPP 5PM', 'clients' => [ ...client rows... ]],
 *        ...
 *     ]
 *   ],
 *   ...
 * ]
 */

// --- T4C location groups -> weekdays mapping (Sunday=0 .. Saturday=6)
function t4c_special_days_for_group(int $therapyGroupId): array {
  // tg_id 3: Mon 10am, Mon 7pm, Wed 7pm, Fri 10am  => Mon(1), Wed(3), Fri(5)
  // tg_id 6: Thu 7pm, Sun 2:30pm, Sun 5pm          => Thu(4), Sun(0)
  switch ($therapyGroupId) {
    case 3: return [1,3,5];
    case 6: return [0,4];
    default: return [];
  }
}

function t4c_group_matches_day(int $therapyGroupId, int $targetDayIdx): bool {
  $days = t4c_special_days_for_group($therapyGroupId);
  return in_array($targetDayIdx, $days, true);
}

function last_occurrence_start_of_day(int $dow, string $tz='America/Chicago'): string {
  $now = new DateTime('now', new DateTimeZone($tz));
  $todayIdx = (int)$now->format('w');
  $daysBack = ($todayIdx - $dow + 7) % 7;
  if ($daysBack === 0) $daysBack = 7; // <- key: use *previous* same weekday, not today
  $dt = (clone $now)->modify("-{$daysBack} days")->setTime(0,0,0);
  return $dt->format('Y-m-d H:i:s');
}


function attended_in_program_since(mysqli $con, int $clientId, int $programId, string $sinceIso): bool {
  $sql = "SELECT 1
          FROM attendance_record ar
          JOIN therapy_session ts ON ts.id = ar.therapy_session_id
          JOIN therapy_group tg ON tg.id = ts.therapy_group_id
          WHERE ar.client_id = ? AND tg.program_id = ? AND ts.date >= ?
          LIMIT 1";
  return (bool)qone($con, $sql, [$clientId, $programId, $sinceIso], 'iis');
}

function program_has_day(int $pid, int $dow): bool {
  // 0=Sun..6=Sat
  static $days = [
    1 => [0,1,3,4,5],    // T4C: Sun, Mon, Wed, Thu, Fri
    2 => [0,1,2,3,6],    // BIPP (Men): Sun, Mon, Tue, Wed, Sat
    3 => [0,1,2,3,6],    // BIPP (Women): same
    4 => [0,6],          // Anger Control: Sun, Sat
  ];
  return in_array($dow, $days[$pid] ?? [], true);
}

function count_attended_in_program_since(mysqli $con, int $clientId, int $programId, string $sinceIso): int {
  $row = qone($con, "SELECT COUNT(*) c
                     FROM attendance_record ar
                     JOIN therapy_session ts ON ts.id=ar.therapy_session_id
                     JOIN therapy_group tg ON tg.id=ts.therapy_group_id
                     WHERE ar.client_id=? AND tg.program_id=? AND ts.date>=?",
                     [$clientId,$programId,$sinceIso],'iis');
  return (int)($row['c'] ?? 0);
}

/**
 * True if client still owes a session inside the current window.
 * - T4C: needs 2 per week since Sunday.
 * - Weekly programs (BIPP/Anger): must have MISSED the last regular and not made up since.
 */


function week_start_sunday(string $tz='America/Chicago'): string {
    $tzObj = new DateTimeZone($tz);
    $now   = new DateTime('now', $tzObj);
    $idx   = (int)$now->format('w'); // 0=Sun

    $start = clone $now;
    if ($idx > 0) {
        $start->modify('-'.$idx.' days');
    }
    $start->setTime(0, 0, 0);

    return $start->format('Y-m-d H:i:s');
}


/**
 * True if client still owes a session for the current week window.
 * - BIPP/Anger Control: since the last occurrence of their regular weekday.
 * - T4C: since week start (any T4C day satisfies).
 */
function pending_this_week(mysqli $con, array $client): bool {
  $pid  = (int)($client['program_id'] ?? 0);
  $rgid = (int)($client['therapy_group_id'] ?? 0);
  if (!$pid || !$rgid) return false;

  if ($pid === 1) { // T4C needs 2 per week
    $since = week_start_sunday();
    return count_attended_in_program_since($con, (int)$client['id'], 1, $since) < 2;
  }

  // Weekly programs: client must have MISSED last regular and not made up since.
    // Weekly programs: determine window
  $tg = qone($con, "SELECT name FROM therapy_group WHERE id=?", [$rgid], 'i');
  if (!$tg) return false;

  $last = last_regular_session($con,(int)$client['id'],$rgid);
  $next = next_regular_session($con,(int)$client['id'],$rgid);

  // Fallback if DB has no sessions scheduled
  if (!$last || !$next) {
    [$gIdx] = parse_weekday_time($tg['name'] ?? '');
    if ($gIdx === null) return false;
    // Since the last occurrence of the client’s regular weekday
    $since = last_occurrence_start_of_day($gIdx);
    // Owe a session if none attended in program since that day
    return count_attended_in_program_since($con, (int)$client['id'], $pid, $since) < 1;
  }

  // If they already made up between last and next, not a candidate
  if (attended_makeup_in_window($con,(int)$client['id'],$last['date'],$next['date'])) return false;

  // Must have missed the last regular session
  return !attended_session($con,(int)$client['id'],(int)$last['id']);

}




// Next occurrence helper (Sunday=0..Saturday=6)
function _next_occurrence_after_now(int $dow, int $hh, int $mm, string $tz='America/Chicago'): DateTime {
  $now = new DateTime('now', new DateTimeZone($tz));
  $todayIdx = (int)$now->format('w');
  $daysAhead = ($dow - $todayIdx + 7) % 7;
  $candidate = (clone $now)->setTime($hh,$mm,0);
  if ($daysAhead > 0) $candidate->modify("+{$daysAhead} days");
  // if same day but time passed, push a week
  if ($daysAhead === 0 && $candidate <= $now) $candidate->modify('+7 days');
  return $candidate;
}

// If DB has no "next" session, synthesize one from the schedule
function _fallback_next_regular_dt(int $therapyGroupId, string $groupName): ?string {
  $sched = [];
  // T4C location groups with fixed weekly patterns
  if ($therapyGroupId === 3) { // FTW: Mon 10a, Mon 7p, Wed 7p, Fri 10a
    $sched = [[1,10,0],[1,19,0],[3,19,0],[5,10,0]];
  } elseif ($therapyGroupId === 6) { // Arlington: Thu 7p, Sun 2:30p, Sun 5p
    $sched = [[4,19,0],[0,14,30],[0,17,0]];
  } else {
    // Try to parse weekday+time from name (e.g., "Tuesday 7:30 PM")
    [$idx,$wd,$tm] = parse_weekday_time($groupName);
    if ($idx !== null && $tm && preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(AM|PM)$/i',$tm,$m)) {
      $hh=(int)$m[1]; $mm=(int)($m[2] ?? 0); $ap=strtoupper($m[3]);
      if ($ap==='PM' && $hh<12) $hh+=12; if ($ap==='AM' && $hh===12) $hh=0;
      $sched = [[$idx,$hh,$mm]];
    }
  }
  if (!$sched) return null;

  $best = null;
  foreach ($sched as [$dow,$hh,$mm]) {
    $cand = _next_occurrence_after_now($dow,$hh,$mm);
    if ($best === null || $cand < $best) $best = $cand;
  }
  return $best ? $best->format('Y-m-d H:i:s') : null;
}


function find_makeup_candidates(mysqli $con, int $targetDayIdx = null): array {
  $clients = get_all_active_clients($con);
  $out = [];

  foreach ($clients as $c) {
    $pid = (int)($c['program_id'] ?? 0);
    $rgid = (int)($c['therapy_group_id'] ?? 0);
    if (!$pid || !$rgid) continue;

    // If viewing a specific day, include only programs that actually meet that day
    if ($targetDayIdx !== null && !program_has_day($pid, $targetDayIdx)) continue;

    $tg = qone($con,"SELECT id, name FROM therapy_group WHERE id=?",[$rgid],'i');
    if (!$tg) continue;
    [$gIdx] = parse_weekday_time($tg['name']);

    // Weekly programs: exclude clients whose REGULAR weekday == the selected day
    // They are not make-up candidates on their own regular day.
    if ($targetDayIdx !== null && $pid !== 1 && $gIdx !== null && $gIdx === $targetDayIdx) continue;

    if (!pending_this_week($con, $c)) continue;

    // Ensure there is at least one option between now and their next regular
    // (avoids listing people when there is nothing to send for this window)
    list($todayHtml, $moreHtml, $nextDt) = render_makeup_blocks($con, $c);
    if ($pid !== 1 && $nextDt !== null && $todayHtml === '' && $moreHtml === '') continue;
    if (!isset($out[$pid])) $out[$pid] = ['program_name' => program_name_from_id($pid), 'groups' => []];
    if (!isset($out[$pid]['groups'][$rgid])) $out[$pid]['groups'][$rgid] = ['group_name' => $tg['name'], 'clients' => []];
    $out[$pid]['groups'][$rgid]['clients'][] = $c;
  }

  foreach ($out as $pid => $_) {
    uasort($out[$pid]['groups'], fn($a,$b)=>strcasecmp($a['group_name']??'',$b['group_name']??''));
  }
  uasort($out, fn($a,$b)=>strcasecmp($a['program_name']??'',$b['program_name']??''));
  return $out;
}




/* -----------------------------------------------
   Subject/Body helpers for placeholders
-------------------------------------------------*/
function program_short(int $pid): string {
    switch ($pid) {
        case 1: return 'T4C';
        case 2: return 'BIPP';
        case 3: return 'BIPP';
        case 4: return 'Anger Control';
        default: return 'Program';
    }
}
function gender_possessive_label(int $genderId): string {
    // 2 = Male, 3 = Female (your schema uses gender_id)
    if ($genderId === 2) return "Men's";
    if ($genderId === 3) return "Women's";
    return '';
}

function ordinal_suffix(int $n): string {
    $n = abs($n) % 100;
    if ($n >= 11 && $n <= 13) return 'th';
    switch ($n % 10) { case 1: return 'st'; case 2: return 'nd'; case 3: return 'rd'; default: return 'th'; }
}
function next_group_date_str(array $gmeta, string $tz = 'America/Chicago'): string {
    $wdName = $gmeta['weekday'] ?? '';
    if ($wdName === '') {
        $dt = new DateTime('now', new DateTimeZone($tz));
        return $dt->format('F j') . ordinal_suffix((int)$dt->format('j'));
    }
    $wdIndex = ['Sunday'=>0,'Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6][$wdName] ?? null;
    $now = new DateTime('now', new DateTimeZone($tz));
    $target = clone $now;

    // Default time 12:00 PM if missing
    $time = trim((string)($gmeta['time'] ?? '12:00 PM'));
    if (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(AM|PM)$/i', $time, $m)) {
        $hh = (int)$m[1]; $mm = (int)($m[2] ?? 0); $ampm = strtoupper($m[3]);
        if ($ampm === 'PM' && $hh < 12) $hh += 12;
        if ($ampm === 'AM' && $hh === 12) $hh = 0;
    } else { $hh = 12; $mm = 0; }

    if ($wdIndex !== null) {
        $todayIdx = (int)$now->format('w');
        $daysAhead = ($wdIndex - $todayIdx + 7) % 7;
        $candidate = (clone $now)->setTime($hh,$mm,0);
        if ($daysAhead === 0 && $candidate <= $now) $daysAhead = 7;
        if ($daysAhead > 0) $target->modify("+{$daysAhead} days");
    }
    $target->setTime($hh,$mm,0);
    return $target->format('F j') . ordinal_suffix((int)$target->format('j'));
}

function fetch_case_manager(mysqli $con, ?int $cmId): ?array {
    if (!$cmId) return null;
    $row = qone($con, "SELECT id, first_name, last_name, office, email, phone_number, referral_source FROM case_manager WHERE id=?", [$cmId], 'i');
    return $row ?: null;
}


/* -----------------------------------------------
   Client-specific group link (hook to portal logic)
-------------------------------------------------*/
function resolve_group_link(mysqli $con, array $client): string {
    if (function_exists('notesao_regular_group_link')) {
        try { return (string)notesao_regular_group_link($con, (int)$client['id']); }
        catch (Throwable $e) { /* fall through */ }
    }
    // Safe fallback: portal landing (adjust if needed)
    return (defined('APP_BASE_URL') ? APP_BASE_URL : 'https://ffltest.notesao.com') . '/client.php';
}

// Try to resolve a join link for ANY group for this client; fall back to portal.
function resolve_group_link_for_makeup(mysqli $con, array $client, int $therapyGroupId, ?string $isoDatetime=null): string {
  if (function_exists('notesao_makeup_group_link')) {
    try { return (string)notesao_makeup_group_link($con, (int)$client['id'], $therapyGroupId, $isoDatetime); }
    catch (Throwable $e) { /* fall through */ }
  }
  return (defined('APP_BASE_URL') ? APP_BASE_URL : 'https://ffltest.notesao.com') . '/clientportal.php';
}



// Cached case manager lookup
function get_case_manager(mysqli $con, ?int $cmId): array {
    static $cache = [];
    if (!$cmId) return ['name'=>'','office'=>''];
    if (isset($cache[$cmId])) return $cache[$cmId];
    $row = qone($con, "SELECT first_name, last_name, office FROM case_manager WHERE id=?", [$cmId], 'i');
    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $cache[$cmId] = ['name'=>$name, 'office'=>$row['office'] ?? ''];
    return $cache[$cmId];
}

/* -----------------------------------------------
   Placeholder builders for subject/body (Step 3)
-------------------------------------------------*/
function subject_prefix_for(array $client): string {
    $pid = (int)($client['program_id'] ?? 0);
    $gid = (int)($client['gender_id'] ?? 0); // 1=unspecified, 2=male, 3=female

    if ($pid === 2 || $pid === 3) { // BIPP (men/women programs both map to BIPP in text)
        if ($gid === 3) return "Women's BIPP";
        if ($gid === 2) return "Men's BIPP";
        return "BIPP"; // fallback when unspecified
    }
    if ($pid === 1) return "Thinking for a Change";
    if ($pid === 4) return "Anger Control";
    return "Program";
}

function pseudo_sessions_for_program_between(mysqli $con, int $programId, string $startIso, string $endIso, string $tz='America/Chicago'): array {
  $start = new DateTime($startIso, new DateTimeZone($tz));
  $now   = new DateTime('now', new DateTimeZone($tz));
  if ($start < $now) $start = $now;

  $end = new DateTime($endIso, new DateTimeZone($tz));
  if ($end <= $start) return [];

  $groups = qall($con, "SELECT id, name FROM therapy_group WHERE program_id=?", [$programId], 'i');

  $sessions = [];
  foreach ($groups as $g) {
    [$idx, $_wd, $timeDisp] = parse_weekday_time($g['name']);
    if ($idx === null || !$timeDisp) continue;

    if (!preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(AM|PM)\b/i', $timeDisp, $m)) continue;
    $hh=(int)$m[1]; $mm= isset($m[2]) ? (int)$m[2] : 0; $ap=strtoupper($m[3]);
    if ($hh === 12) $hh = 0; if ($ap === 'PM') $hh += 12;

    // first occurrence on/after $start
    $d = (clone $start)->setTime(0,0,0);
    $curW = (int)$d->format('w');
    $delta = ($idx - $curW + 7) % 7;
    if ($delta > 0) $d->modify("+{$delta} days");
    $d->setTime($hh,$mm,0);
    if ($d < $start) $d->modify('+7 days');

    while ($d < $end) {
      // inside the while ($d < $end) loop
      $sessions[] = [
        'session_id' => 0,
        'date'       => $d->format('Y-m-d H:i:s'),
        'group_id'   => (int)$g['id'],
        'name'       => $g['name'],
        'program_id' => $programId,
        'address'    => get_group_address($con, (int)$g['id']),
      ];

      $d->modify('+7 days');
    }
  }

  // de-dupe & sort
  $seen = []; $out = [];
  foreach ($sessions as $s) {
    $k = $s['group_id'].'@'.$s['date'];
    if (isset($seen[$k])) continue;
    $seen[$k]=true; $out[]=$s;
  }
  usort($out, fn($a,$b)=> strcmp($a['date'],$b['date']) ?: strcasecmp($a['name'],$b['name']));
  return $out;
}

// --- helpers for in-person BIPP makeups ---
function group_address_for_id(mysqli $con, int $gid): string {
  $g = qone($con,"SELECT address,city,state,zip,name FROM therapy_group WHERE id=?",[$gid],'i');
  $addr = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
    $g['address']??'', $g['city']??'', $g['state']??'', $g['zip']??''
  ]))));
  return $addr;
}
function is_virtual_group_name(string $name): bool {
  $n = strtolower($name);
  return (strpos($n,'virtual')!==false || strpos($n,'zoom')!==false || strpos($n,'online')!==false);
}
function default_bipp_inperson_address(): string {
  return '1100 East Lancaster Ave., Fort Worth, TX 76102';
}
function _next_occurrence_after_or_on(DateTime $start,int $dow,int $hh,int $mm,DateTimeZone $tz): DateTime {
  $d = clone $start;
  $todayIdx=(int)$d->format('w');
  $delta = ($dow - $todayIdx + 7) % 7;
  $d->modify("+{$delta} days")->setTime($hh,$mm,0);
  if ($d < $start) $d->modify('+7 days');
  return $d;
}
/**
 * Pseudo in-person schedule for BIPP (Men/Women share times, location FW)
 * Sat 9:00 AM, Sun 5:00 PM, Tue 7:00 PM
 */




function render_makeup_blocks(mysqli $con, array $client): array {
  $groupId = (int)$client['therapy_group_id'];
  if (!$groupId) return ['','',null];

  $tg = qone($con, "SELECT id,name,program_id FROM therapy_group WHERE id=?", [$groupId], 'i');

  // Last/next (fallback for "next")
  $last = last_regular_session($con,(int)$client['id'],$groupId);
  $next = next_regular_session($con,(int)$client['id'],$groupId);
  if (!$next) {
    $fallback = _fallback_next_regular_dt($groupId, $tg['name'] ?? '')
             ?: (new DateTime('+7 days', new DateTimeZone('America/Chicago')))->format('Y-m-d H:i:s');
    $next = ['id'=>0,'date'=>$fallback];
  }

  $lastDt = $last['date'] ?? (new DateTime('-7 days', new DateTimeZone('America/Chicago')))->format('Y-m-d H:i:s');

  // Already satisfied strictly between last and next?
  if ($last && attended_makeup_in_window($con,(int)$client['id'],$lastDt,$next['date'])) {
    return ['','',$next['date']];
  }

  $programId = (int)($client['program_id'] ?? 0);
  $tz = new DateTimeZone('America/Chicago');
  $startIso = (new DateTime('now',$tz))->format('Y-m-d 00:00:00');

  // DB sessions within window
  $dbRows = upcoming_makeups($con,$programId,$next['date']); // ts.date >= NOW()

  // Add addresses to DB rows
  foreach ($dbRows as &$r) {
    $r['group_name'] = $r['name'];
    $r['address'] = group_address_for_id($con,(int)$r['group_id']);
  } unset($r);

  // For BIPP: synthesize known in-person slots (Sat 9a, Sun 5p, Tue 7p)
  $synthRows = in_array($programId, [1,2,3,4], true)
    ? pseudo_sessions_for_program_between($con,$programId,$startIso,$next['date'])
    : [];

  
  foreach ($synthRows as &$r) { $r['group_name'] = $r['name'] ?? ''; }
  unset($r);


  // Build a set of existing minute-precision datetimes from DB
  $existingKey = [];
  foreach ($dbRows as $r) {
    $existingKey[(new DateTime($r['date'],$tz))->format('Y-m-d H:i')] = true;
  }
  // Keep only pseudo rows not already present in DB at same minute
  $synthRows = array_values(array_filter($synthRows, function($r) use ($tz,$existingKey){
    $k=(new DateTime($r['date'],$tz))->format('Y-m-d H:i');
    return empty($existingKey[$k]);
  }));

  // Merge DB + pseudo
  $rows = array_merge($dbRows,$synthRows);

  // ---- FILTERS ----
  if ($programId===2 || $programId===3) {
    // BIPP: in-person only (has address) and not "Virtual"
    $rows = array_values(array_filter($rows, function($r){
      $addr = trim($r['address'] ?? '');
      $name = (string)($r['group_name'] ?? $r['name'] ?? '');
      return $addr !== '' && !is_virtual_group_name($name);
    }));
  } elseif ($programId===1) {
    // T4C is fine (links handled below)
  }

  if (!$rows) return ['','',$next['date']];

  // Sort by datetime
  usort($rows, fn($a,$b)=>strcmp($a['date'],$b['date']));

  // Partition today vs later
  $todayYmd = (new DateTime('now',$tz))->format('Y-m-d');
  $fmt = function(string $iso) use ($tz){ $d=new DateTime($iso,$tz); return $d->format('l g:i A'); };

  $today = array_filter($rows, fn($s)=> (new DateTime($s['date'],$tz))->format('Y-m-d') === $todayYmd);
  $later = array_filter($rows, fn($s)=> (new DateTime($s['date'],$tz))->format('Y-m-d') >  $todayYmd);

  // Optional one-time location header for BIPP
  $inPersonHeader = ($programId===2 || $programId===3)
    ? '<p><strong>Location (in person):</strong> '.htmlspecialchars(default_bipp_inperson_address()).'</p>'
    : '';

  // Render blocks
  $tonightHtml = '';
  if ($today) {
    $tonightHtml = $inPersonHeader . '<p><strong>Today:</strong></p><ul>';
    foreach ($today as $s) {
      if ($programId===1) {
        $link = resolve_group_link_for_makeup($con,$client,(int)($s['group_id']??0),$s['date']);
        $tonightHtml .= '<li>'.$fmt($s['date']).' — <a href="'.htmlspecialchars($link).'">'.htmlspecialchars($s['group_name']).'</a></li>';
      } else {
        $tonightHtml .= '<li>'.$fmt($s['date']).' — '.htmlspecialchars($s['group_name']).'</li>';
      }
    }
    $tonightHtml .= '</ul>';
    // header only once
    $inPersonHeader = '';
  }

  $byDay = [];
  foreach ($later as $s) {
    $day = (new DateTime($s['date'],$tz))->format('l');
    $byDay[$day][] = $s;
  }
  $moreHtml = '';
  if ($byDay) $moreHtml .= $inPersonHeader; // if not used above, put it here
  foreach ($byDay as $day=>$rowsByDay) {
    $moreHtml .= '<p><strong>'.htmlspecialchars($day).':</strong></p><ul>';
    foreach ($rowsByDay as $s) {
      if ($programId===1) {
        $link = resolve_group_link_for_makeup($con,$client,(int)($s['group_id']??0),$s['date']);
        $moreHtml .= '<li>'.$fmt($s['date']).' — <a href="'.htmlspecialchars($link).'">'.htmlspecialchars($s['group_name']).'</a></li>';
      } else {
        $moreHtml .= '<li>'.$fmt($s['date']).' — '.htmlspecialchars($s['group_name']).'</li>';
      }
    }
    $moreHtml .= '</ul>';
  }

  return [$tonightHtml,$moreHtml,$next['date']];
}



function referral_type_label(?int $refId): string {
    // TODO: adjust to your actual referral_type_id values
    $map = [
        1 => 'Court',
        2 => 'Parole',
        3 => 'Probation',
        4 => 'CPS',
        5 => 'Self-Pay',
    ];
    return $map[$refId ?? 0] ?? '';
}

function build_officer_sentence(array $off): string {
    $name = trim($off['name'] ?? '');
    $office = trim($off['office'] ?? '');
    if ($name && $office) return $office . " and " . $name . " expect your presence.";
    if ($name) return $name . " expects your presence.";
    if ($office) return $office . " expects your presence.";
    return "";
}

function build_referral_sentence(array $client, array $off, string $programShort): string {
    $label = referral_type_label(isset($client['referral_type_id']) ? (int)$client['referral_type_id'] : null);
    $office = trim($off['office'] ?? '');
    if ($office !== '') return "Your " . $office . " mandated " . $programShort . " group is scheduled.";
    if ($label !== '') return "Your " . $label . " mandated " . $programShort . " group is scheduled.";
    return "Your " . $programShort . " group is scheduled.";
}

/**
 * Where line:
 *  - In-person: "Where: 6850 Manhattan Blvd., Fort Worth, TX 76120"
 *  - Virtual:   "Where: Virtual — use your group link: Tuesday 7:30 PM Group Link" (clickable)
 */
function build_meeting_where(array $gmeta, string $groupLink, ?string $weekday, ?string $timeDisp): string {
    $addr = trim($gmeta['address'] ?? '');
    if ($addr !== '') {
        return 'Where: ' . htmlspecialchars($addr, ENT_QUOTES, 'UTF-8');
    }
    $label = trim(trim((string)$weekday) . ' ' . trim((string)$timeDisp));
    $label = $label !== '' ? ($label . ' Group Link') : 'Group Link';
    $alink = '<a href="' . htmlspecialchars($groupLink, ENT_QUOTES, 'UTF-8') . '">'
           . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
           . '</a>';
    return 'Where: Virtual (Use your group link):<br>' . $alink;
}





/* -----------------------------------------------
   Placeholder expansion
-------------------------------------------------*/
function expand_placeholders(array $client, array $gmeta, string $subject, string $html, mysqli $con): array {
    // --- program/gender ---
    $pid = (int)($client['program_id'] ?? 0);
    $gid = (int)($client['gender_id'] ?? 0); // 1=unspecified, 2=male, 3=female

    $programFull  = [1=>"Thinking for a Change", 2=>"Men's BIPP", 3=>"Women's BIPP", 4=>"Anger Control"][$pid] ?? 'Program';
    $programShort = ($pid === 1) ? 'T4C' : (($pid === 2 || $pid === 3) ? 'BIPP' : ($pid === 4 ? 'Anger Control' : 'Program'));
    $genderPossessive = ($gid === 2) ? "Men's" : (($gid === 3) ? "Women's" : "Client's");

    // --- next occurrence + lockout (10 min after start) ---
    $weekday = $gmeta['weekday'] ?? null;
    $timeDisp = $gmeta['time'] ?? null;
    $next_group_date = '';
    $lockout_time = '';
    if ($weekday && $timeDisp && preg_match('/^\s*(\d{1,2})(?::(\d{2}))?\s*(AM|PM)\s*$/i', $timeDisp, $m)) {
        $wdMap = ['sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
        $idx = $wdMap[strtolower($weekday)] ?? null;
        if ($idx !== null) {
            $hh=(int)$m[1]; $mm=(int)($m[2] ?? 0); $ampm=strtoupper($m[3]);
            if ($hh === 12) $hh = 0;
            if ($ampm === 'PM') $hh += 12;
            $tz = new DateTimeZone('America/Chicago');
            $now = new DateTime('now', $tz);
            $target = (clone $now)->setTime($hh, $mm, 0);
            $todayIdx = (int)$now->format('w');
            $daysAhead = ($idx - $todayIdx + 7) % 7;
            if ($daysAhead === 0 && $target <= $now) $daysAhead = 7;
            if ($daysAhead > 0) $target->modify("+{$daysAhead} days");

            $next_group_date = $target->format('l, F j');
            $lockout_time = (clone $target)->modify('+10 minutes')->format('g:i A');
        }
    }

    // --- per-client link & officer/case manager ---
    $groupLink = resolve_group_link($con, $client);
    $off = get_case_manager($con, isset($client['case_manager_id']) ? (int)$client['case_manager_id'] : null);

    // --- custom sentences ---
    $subject_prefix     = subject_prefix_for($client);
    $referral_sentence  = build_referral_sentence($client, $off, $programShort);
    $officer_sentence   = build_officer_sentence($off);

    // --- meeting text (with labeled link for virtual) ---
    $meeting_where = build_meeting_where($gmeta, $groupLink, $weekday, $timeDisp);

    // --- replacements (provide both compact and spaced variants) ---
    $rep = [
        '{{first_name}}'           => $client['first_name'] ?? '',
        '{{ last_name }}'          => $client['last_name'] ?? '',       '{{last_name}}' => $client['last_name'] ?? '',

        '{{gender_possessive}}'    => $genderPossessive,                '{{ gender_possessive }}' => $genderPossessive,
        '{{program_short}}'        => $programShort,                    '{{ program_short }}'     => $programShort,
        '{{program_name}}'         => $programFull,                     '{{ program_name }}'      => $programFull,

        '{{group_name}}'           => $gmeta['name'] ?? '',             '{{ group_name }}'        => $gmeta['name'] ?? '',
        '{{group_day}}'            => $weekday ?? '',                   '{{ group_day }}'         => $weekday ?? '',
        '{{group_time}}'           => $timeDisp ?? '',                  '{{ group_time }}'        => $timeDisp ?? '',
        '{{meeting_location}}'     => $gmeta['address'] ?? '',          '{{ meeting_location }}'  => $gmeta['address'] ?? '',
        '{{group_link}}'           => $groupLink,                       '{{ group_link }}'        => $groupLink,

        '{{subject_prefix}}'       => $subject_prefix,                  '{{ subject_prefix }}'    => $subject_prefix,
        '{{referral_sentence}}'    => $referral_sentence,               '{{ referral_sentence }}' => $referral_sentence,
        '{{officer_sentence}}'     => $officer_sentence,                '{{ officer_sentence }}'  => $officer_sentence,
        '{{meeting_where}}'        => $meeting_where,                   '{{ meeting_where }}'     => $meeting_where,

        '{{next_group_date}}'      => $next_group_date,                 '{{ next_group_date }}'   => $next_group_date,
        '{{lockout_time}}'         => $lockout_time,                    '{{ lockout_time }}'      => $lockout_time,
    ];

    $subject = strtr($subject, $rep);
    $html    = strtr($html, $rep);
    return [$subject, $html];
}


// Map T4C day -> default time + location label
function t4c_meta_for_day(string $day): array {
    $day = strtolower($day);
    switch ($day) {
        // FORT WORTH T4C
        case 'monday':    return ['time' => '10:00 AM or 7:00 PM', 'address' => 'In person — Fort Worth Office: 1100 East Lancaster Ave., Fort Worth, TX 76102'];
        case 'wednesday': return ['time' => '7:00 PM',               'address' => 'In person — Fort Worth Office: 1100 East Lancaster Ave., Fort Worth, TX 76102'];
        case 'friday':    return ['time' => '10:00 AM',              'address' => 'In person — Fort Worth Office: 1100 East Lancaster Ave., Fort Worth, TX 76102'];

        // ARLINGTON T4C
        case 'sunday':    return ['time' => '2:30 PM or 5:00 PM',    'address' => 'In person — Arlington Office: 6850 Manhattan Blvd., Fort Worth, TX 76120'];
        case 'thursday':  return ['time' => '7:00 PM',               'address' => 'In person — Arlington Office: 6850 Manhattan Blvd., Fort Worth, TX 76120'];

        // Not currently scheduled (kept safe/clear)
        default:          return ['time' => 'TBD',                   'address' => 'In person — see the address above'];
    }
}




/* -----------------------------------------------
   HTML sanitization (allow-list)
-------------------------------------------------*/
function sanitize_email_html(string $html): string {
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><a>'
             . '<h1><h2><h3><h4><h5><h6><table><thead><tbody><tr><th><td>';
    $html = strip_tags($html, $allowed);

    // Strip inline event handlers and style attributes
    $html = preg_replace('/\son\w+\s*=\s*("|\').*?\1/si', '', $html);
    $html = preg_replace('/\sstyle\s*=\s*("|\').*?\1/si', '', $html);

    // Normalize <a> tags: allow only http(s) or mailto
    $html = preg_replace_callback('/<a\b[^>]*>/i', function ($m) {
        $tag = $m[0];
        if (!preg_match('/href\s*=\s*("|\')([^"\']+)\1/i', $tag, $h)) return '<a>';
        $href = $h[2];
        if (!preg_match('#^(https?://|mailto:)#i', $href)) $href = '#';
        return '<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">';
    }, $html);

    return $html;
}

/* -----------------------------------------------
   Unsubscribe helpers
-------------------------------------------------*/
function unsubscribe_token(string $email): string {
    $email = strtolower(trim($email));
    $salt  = defined('EMAIL_UNSUB_SALT') ? EMAIL_UNSUB_SALT : (__FILE__ . php_uname());
    return hash('sha256', $email . $salt);
}
function unsubscribe_url(string $email): string {
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : 'https://ffltest.notesao.com';
    return rtrim($base, '/') . '/unsubscribe.php?e='
        . urlencode($email) . '&t=' . unsubscribe_token($email);
}
function is_unsubscribed(mysqli $con, string $email): bool {
    $row = qone($con, "SELECT 1 FROM email_unsubscribed WHERE email=? LIMIT 1",
                [strtolower(trim($email))], 's');
    return (bool)$row;
}
function wrap_email_html_with_footer(string $html, string $email): string {
    $u = unsubscribe_url($email);
    $footer = '<hr style="border-top:1px solid #ddd;margin:24px 0;">'
            . '<p style="font:12px/1.4 Arial,Helvetica,sans-serif;color:#666">'
            . 'This reminder was sent by Free for Life Group. If you no longer wish to receive emails at this address, '
            . '<a href="'.htmlspecialchars($u, ENT_QUOTES, 'UTF-8').'">unsubscribe here</a>.'
            . '</p>';
    return $html . $footer;
}

/* -----------------------------------------------
   Mail
-------------------------------------------------*/
function send_html_mail(string $to, string $subject, string $htmlBody, string $from = null): bool {
    $from    = $from ?: (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@ffl.notesao.com');
    $replyTo = defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : 'admin@notesao.com';

    $headers = [
        "From: Free for Life Group <{$from}>",
        "Reply-To: {$replyTo}",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        'List-Unsubscribe: <' . unsubscribe_url($to) . '>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        'Precedence: bulk',
        'X-Mailer: NotesAO-Reminders/1.0'
    ];
    $envelope = "-f {$from}";

    // --- prepend header logo ---
    $base    = defined('APP_BASE_URL') ? APP_BASE_URL : 'https://ffltest.notesao.com';
    $logoUrl = rtrim($base, '/') . '/ffllogo.png';
    $header  = '<div style="text-align:center;margin:0 0 12px 0">'
             . '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Free for Life Group" style="max-width:100%;height:auto;display:inline-block;">'
             . '</div>';

    // --- append footer with unsubscribe ---
    $htmlBody = $header . $htmlBody;
    $htmlBody = wrap_email_html_with_footer($htmlBody, $to);

    // Finalize headers string
    $rawHeaders = implode("\r\n", $headers);

    // Send
    $sent = @mail($to, $subject, $htmlBody, $rawHeaders, $envelope);

    // ---- LOG TO FILE (daily rotation) ----
    // Path is outside web root
    $logDir = '/home/notesao/mail-logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0700, true);
    }
    $logFile = $logDir . '/reminders-' . date('Y-m-d') . '.log';

    // One entry per attempt
    $status = $sent ? 'SENT' : 'FAILED';
    $record = "-----\n"
            . date('c') . " | STATUS:$status | TO:$to | SUBJ:$subject\n"
            . "ENVELOPE: $envelope\n"
            . "HEADERS:\n$rawHeaders\n\n"
            . "BODY:\n$htmlBody\n\n";

    @file_put_contents($logFile, $record, FILE_APPEND | LOCK_EX);
    // ---- END LOG ----

    return $sent;
}



/* -----------------------------------------------
   Default templates
   Subject example: "Men's BIPP Group Reminder: Tuesday at 7:30 PM"
-------------------------------------------------*/
$DEF_SUBJECT = "{{subject_prefix}} Group Reminder: {{group_day}} at {{group_time}}";

$DEF_BODY = <<<HTML
<p>Hi {{first_name}},</p>

<p><strong>Your {{program_name}} group is {{group_day}} at {{group_time}} ({{next_group_date}}).</strong></p>

<p>
  {{referral_sentence}} {{officer_sentence}}
  Please arrive early. <em>Lockout is {{lockout_time}}</em>. Your presence is a mandatory stipulation.
  Incarceration occurs for non-attendance. Enjoy life fearlessly by remaining in good standing with CSCD &amp; TDCJ.
  You've got this!
</p>

<p>{{meeting_where}}</p>

<p>
  Client Portal (for Make-Up Group Options):<br>
  <a href="https://ffl.notesao.com/clientportal.php">FFL – Client Portal</a>
</p>

<p>
  Questions? Reply to this email with any attendance or payment concerns OR call during office hours (Mon–Fri, 8am–4pm).
</p>

<p>Blessings,<br>Free for Life Group</p>
HTML;

$DEF_SUBJECT_ALL = "Free for Life Group — Reminder";
$DEF_BODY_ALL = <<<HTML
<p>Hi {{first_name}},</p>
<p>This is a reminder from Free for Life Group. If you have a session coming up, please arrive a few minutes early.</p>
<p>Check your personal link and details in your portal:</p>
<p><a href="https://ffl.notesao.com/clientportal.php">FFL – Client Portal</a></p>
<p>— Free for Life Group</p>
HTML;

$DEF_SUBJECT_MAKEUP = "Make-Up Options — {{subject_prefix}} — {{makeup_day}}";
$DEF_BODY_MAKEUP = <<<HTML
<p>Hi {{first_name}},</p>
<p>You missed your regular {{program_name}} group. <strong>{{makeup_day}}</strong> is your next make-up opportunity.</p>

{{tonight_block}}

<p><strong>More make-ups before your next regular group (by day):</strong></p>
{{more_makeups_block}}

<p>Need details or a different option? Use your portal:<br>
<a href="https://ffl.notesao.com/clientportal.php">FFL – Client Portal</a></p>
<p>— Free for Life Group</p>
HTML;



/* -----------------------------------------------
   Group meta cache
-------------------------------------------------*/
function group_meta_cache(mysqli $con): array {
    $cache = [];
    foreach (get_all_groups($con) as $g) {
        [$idx,$wd,$tm] = parse_weekday_time($g['name']);
        $addrParts = array_filter([$g['address'] ?? '', $g['city'] ?? '', $g['state'] ?? '', $g['zip'] ?? '']);
        $addr = trim(preg_replace('/\s+/', ' ', implode(' ', $addrParts)));
        $cache[(int)$g['id']] = [
            'name'       => $g['name'],
            'weekday'    => $wd ?: '',
            'time'       => $tm ?: '',
            'address'    => $addr,
            'program_id' => (int)$g['program_id'],
        ];
    }
    return $cache;
}
$GROUP_META = group_meta_cache($con);

/* -----------------------------------------------
   POST actions
-------------------------------------------------*/
$flash = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'quick') {
        // Quick send default to a single group (gid numeric or t4c:<day>)
        $gid = $_POST['gid'] ?? '';
        [$sent,$failed,$errs] = [0,0,[]];

        if (preg_match('/^\d+$/',$gid)) {
            $gid = (int)$gid;
            $clients = get_clients_for_group($con,$gid);
            $gmeta = $GROUP_META[$gid] ?? ['name'=>'','weekday'=>'','time'=>'','address'=>''];

            foreach ($clients as $c) {
                if (empty($c['email'])) { $failed++; $errs[]="Missing email for #{$c['id']}"; continue; }
                if (is_unsubscribed($con, $c['email'])) { $failed++; $errs[]="Unsubscribed: {$c['email']}"; continue; }

                // Build → then sanitize → then send
                [$subj,$html] = expand_placeholders($c,$gmeta,$DEF_SUBJECT,$DEF_BODY,$con);
                $html = sanitize_email_html($html);
                $to = $c['email'];

                if (send_html_mail($to,$subj,$html)) {
                    $sent++;
                    @qall($con,"INSERT INTO client_event (client_id, client_event_type_id, note) VALUES (?,1,?)",
                          [(int)$c['id'],'Automated reminder email sent (...)'],'is');


                } else { $failed++; $errs[]="Failed: {$c['email']}"; }
            }

        } elseif (preg_match('/^t4c:(sunday|monday|tuesday|wednesday|thursday|friday|saturday)$/', $gid, $mm)) {
            $day = $mm[1];
            [$t4cByDay, $t4cVirtual] = get_t4c_day_rosters($con);
            $clients = $t4cByDay[$day] ?? [];

            // NEW: day-aware meta (gives you time + in-person rendering)
            $meta = t4c_meta_for_day($day);
            $gmeta = [
                'name'    => "Thinking for a Change — " . ucfirst($day),
                'weekday' => ucfirst($day),
                'time'    => $meta['time'],      // e.g., "10:00 AM or 7:00 PM"
                'address' => $meta['address'],   // non-empty => in-person "Where:" line
            ];

            foreach ($clients as $c) {
                if (empty($c['email'])) { $failed++; $errs[]="Missing email for #{$c['id']}"; continue; }
                if (is_unsubscribed($con, $c['email'])) { $failed++; $errs[]="Unsubscribed: {$c['email']}"; continue; }

                [$subj, $html] = expand_placeholders($c, $gmeta, $DEF_SUBJECT, $DEF_BODY, $con);
                $html = sanitize_email_html($html);
                $to = $c['email'];

                if (send_html_mail($to, $subj, $html)) {
                    $sent++;
                    @qall($con,"INSERT INTO client_event (client_id, client_event_type_id, note) VALUES (?,1,?)",
                          [(int)$c['id'],'Automated reminder email sent (...)'],'is');

                } else {
                    $failed++; $errs[]="Failed: {$c['email']}";
                }
            }
        


        } else {
            $flash = ['type'=>'danger','msg'=>'Unknown group id.'];
        }

        if (!$flash) {
            $type = $failed ? 'warning' : 'success';
            $msg  = "Quick send completed. Sent: <b>$sent</b> • Failed: <b>$failed</b>";
            if ($failed && $errs) $msg .= '<br>'.htmlspecialchars(implode('; ',$errs));
            $flash = ['type'=>$type,'msg'=>$msg];
        }
    }

    if ($action === 'send_group') {
        $gid = $_POST['gid'] ?? '';
        $subjectTpl = trim($_POST['email_subject'] ?? '');
        $bodyTpl = trim($_POST['email_body'] ?? '');   // NOT sanitize here

        $ids        = $_POST['client_ids'] ?? [];
        if ($subjectTpl==='' || $bodyTpl==='' || empty($ids)) {
            $flash = ['type'=>'danger','msg'=>'Subject, body, and at least one recipient are required.'];
        } else {
            [$sent,$failed,$errs] = [0,0,[]];
            if (preg_match('/^\d+$/',$gid)) {
                $gid = (int)$gid;
                $gmeta = $GROUP_META[$gid] ?? ['name'=>'','weekday'=>'','time'=>'','address'=>''];
            } elseif (preg_match('/^t4c:(.+)$/',$gid,$mm)) {
                $d = $mm[1]; $gmeta = ['name'=>"Thinking for a Change — ".ucfirst($d),'weekday'=>ucfirst($d),'time'=>'','address'=>''];
            } else { $gmeta = ['name'=>'','weekday'=>'','time'=>'','address'=>'']; }

            foreach ($ids as $cid) {
                $c = qone($con,"SELECT id, first_name, last_name, email, program_id, therapy_group_id, gender_id, case_manager_id
                                FROM client WHERE id=?",[(int)$cid],'i');
                if (!$c || empty($c['email'])) { $failed++; $errs[] = "Missing email for #$cid"; continue; }
                if (is_unsubscribed($con, $c['email'])) { $failed++; $errs[] = "Unsubscribed: {$c['email']}"; continue; }

                [$subj,$html] = expand_placeholders($c,$gmeta,$subjectTpl,$bodyTpl,$con);
                $html = sanitize_email_html($html);
                $to = $c['email'];

                if (send_html_mail($to,$subj,$html)) {
                    $sent++;
                    @qall($con,"INSERT INTO client_event (client_id, client_event_type_id, note) VALUES (?,1,?)",
                          [(int)$c['id'],'Automated reminder email sent (...)'],'is');


                } else { $failed++; $errs[]="Failed: {$c['email']}"; }
            }

            $type = $failed ? 'warning' : 'success';
            $msg  = "Send completed. Sent: <b>$sent</b> • Failed: <b>$failed</b>";
            if ($failed && $errs) $msg .= '<br>'.htmlspecialchars(implode('; ',$errs));
            $flash = ['type'=>$type,'msg'=>$msg];
        }
    }

    if ($action === 'send_all') {
        $subjectTpl = trim($_POST['email_subject'] ?? '');
        $bodyTpl    = sanitize_email_html(trim($_POST['email_body'] ?? ''));
        if ($subjectTpl==='' || $bodyTpl==='') {
            $flash = ['type'=>'danger','msg'=>'Subject and body are required.'];
        } else {
            [$sent,$failed,$errs] = [0,0,[]];
            $clients = get_all_active_clients($con);
            $gmeta = ['name'=>'','weekday'=>'','time'=>'','address'=>'']; // generic

            foreach ($clients as $c) {
                if (empty($c['email'])) { $failed++; $errs[] = "Missing email for #{$c['id']}"; continue; }
                if (is_unsubscribed($con, $c['email'])) { $failed++; $errs[] = "Unsubscribed: {$c['email']}"; continue; }

                [$subj,$html] = expand_placeholders($c,$gmeta,$subjectTpl,$bodyTpl,$con);
                $html = sanitize_email_html($html);
                $to = $c['email'];

                if (send_html_mail($to,$subj,$html)) {
                    $sent++;
                    @qall($con,"INSERT INTO client_event (client_id, client_event_type_id, note) VALUES (?,1,?)",
                          [(int)$c['id'],'Automated reminder email sent (...)'],'is');


                } else { $failed++; $errs[]="Failed: {$c['email']}"; }
            }

            $type = $failed ? 'warning' : 'success';
            $msg  = "Broadcast completed. Sent: <b>$sent</b> • Failed: <b>$failed</b>";
            if ($failed && $errs) $msg .= '<br>'.htmlspecialchars(implode('; ',$errs));
            $flash = ['type'=>$type,'msg'=>$msg];
        }
    }
    if ($action === 'send_makeup') {
        $subjectTpl = trim($_POST['email_subject'] ?? '');
        $bodyTpl    = trim($_POST['email_body'] ?? '');
        $makeupDay  = trim($_POST['makeup_day'] ?? '');
        $ids        = $_POST['client_ids'] ?? [];

        if ($subjectTpl==='' || $bodyTpl==='' || $makeupDay==='' || empty($ids)) {
            $flash = ['type'=>'danger','msg'=>'Subject, body, day, and at least one recipient are required.'];
        } else {
            [$sent,$failed,$errs] = [0,0,[]];
            $gmeta = ['name'=>"Make-Up for $makeupDay",'weekday'=>$makeupDay,'time'=>'','address'=>''];

            foreach ($ids as $cid) {
                $c = qone($con,"SELECT id, first_name, last_name, email, program_id, therapy_group_id, gender_id, case_manager_id FROM client WHERE id=?",[(int)$cid],'i');
                if (!$c || empty($c['email'])) { $failed++; $errs[]="Missing email for #$cid"; continue; }
                if (is_unsubscribed($con, $c['email'])) { $failed++; $errs[]="Unsubscribed: {$c['email']}"; continue; }

                list($tonightHtml,$moreHtml,$nextDt) = render_makeup_blocks($con,$c);
                // If already attended a make-up in window, skip
                if ($nextDt!==null && $tonightHtml==='' && $moreHtml==='') continue;

                $tplSubj  = strtr($subjectTpl, ['{{makeup_day}}'=>$makeupDay,'{{ makeup_day }}'=>$makeupDay]);
                $tplBody  = strtr($bodyTpl,   ['{{makeup_day}}'=>$makeupDay,'{{ makeup_day }}'=>$makeupDay,
                                              '{{tonight_block}}'=>$tonightHtml, '{{ tonight_block }}'=>$tonightHtml,
                                              '{{more_makeups_block}}'=>$moreHtml, '{{ more_makeups_block }}'=>$moreHtml]);

                [$subj,$html] = expand_placeholders($c,$gmeta,$tplSubj,$tplBody,$con);
                $html = sanitize_email_html($html);
                if (send_html_mail($c['email'],$subj,$html)) {
                    $sent++;
                    @qall($con,"INSERT INTO client_event (client_id, client_event_type_id, note) VALUES (?,1,?)",
                          [(int)$c['id'],'Automated make-up email sent (...)'],'is');
                } else { $failed++; $errs[]="Failed: {$c['email']}"; }
            }
            $type = $failed ? 'warning' : 'success';
            $msg  = "Make-up send completed. Sent: <b>$sent</b> • Failed: <b>$failed</b>";
            if ($failed && $errs) $msg .= '<br>'.htmlspecialchars(implode('; ',$errs));
            $flash = ['type'=>$type,'msg'=>$msg];
        }
    }

}

/* -----------------------------------------------
   View router & data
-------------------------------------------------*/
$gid = $_GET['gid'] ?? '';
$mode = $_GET['mode'] ?? 'reminders'; // reminders | makeup
$dayQ = $_GET['day']  ?? '';          // e.g. Monday

$allGroups = get_all_groups($con);
[$t4cByDay,$t4cVirtual] = get_t4c_day_rosters($con);
$order = day_ordering();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Reminders</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap + Icons -->
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"
        crossorigin="anonymous">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        crossorigin="anonymous" />

  <style>
    .sticky-actions { position: sticky; top: 64px; z-index: 100; }
    .muted { color:#6c757d; }
    .card + .card { margin-top: 1rem; }
    .table-sm td, .table-sm th { padding: .35rem .5rem; }
    .select-all { cursor:pointer; }

    /* --- In-house WYSIWYG --- */
    .wysiwrap { border:1px solid #dee2e6; border-radius:.25rem; }
    .wysi-toolbar { display:flex; flex-wrap:wrap; gap:.25rem; padding:.35rem; border-bottom:1px solid #dee2e6; background:#f8f9fa; }
    .wysi-toolbar button { border:1px solid #ced4da; background:#fff; padding:.25rem .5rem; border-radius:.25rem; font-size:.875rem; }
    .wysi-editor { min-height:220px; padding:.5rem; outline:none; }
    .wysi-source { display:none; width:100%; min-height:220px; padding:.5rem; font-family:monospace; font-size:.9rem; }
    .wysiwrap.source .wysi-editor { display:none; }
    .wysiwrap.source .wysi-source { display:block; }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container-fluid mt-3">
  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
      <?= $flash['msg'] ?>
    </div>
  <?php endif; ?>

  <?php if ($mode==='makeup' && $dayQ===''): ?>
    <?php
      // Days list + highlight today
      $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
      $todayIdx = (int)date('w');
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0"><i class="fas fa-undo"></i> Make-Up Reminders</h3>
      <div class="btn-group">
        <a href="client-reminders.php" class="btn btn-light"><i class="fas fa-bell"></i> Regular Mode</a>
        <a href="client-index.php" class="btn btn-light"><i class="fas fa-home"></i> Home</a>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><strong>Select a day to send make-up links</strong></div>
      <div class="list-group list-group-flush">
        <?php foreach ($days as $i => $d): ?>
          <a
            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?= $i === $todayIdx ? 'active' : '' ?>"
            href="client-reminders.php?mode=makeup&day=<?= urlencode($d) ?>"
          >
            <span>
              <i class="fas fa-calendar-day mr-2"></i><?= htmlspecialchars($d) ?>
              <?php if ($i === $todayIdx): ?>
                <span class="badge badge-light ml-2">Today</span>
              <?php endif; ?>
            </span>
            <i class="fas fa-chevron-right <?= $i === $todayIdx ? 'text-white-50' : 'text-muted' ?>"></i>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

  <?php elseif ($mode==='makeup' && $dayQ!==''): /* ---- MAKE-UP DAY VIEW ---- */ ?>

    <?php
      // Normalize and map day string to index
      $dayMap = ['sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
      $dayKey = strtolower(trim($dayQ));
      $dayIdx = $dayMap[$dayKey] ?? null;

      if ($dayIdx===null) {
        header('Location: client-reminders.php?mode=makeup');
        exit;
      }

      // Get candidates grouped by their regular group
      $candidates = find_makeup_candidates($con, $dayIdx);
    ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0"><i class="fas fa-undo"></i> Make-Up — <?= htmlspecialchars(ucfirst($dayKey)) ?></h3>
      </div>
      <div class="btn-group">
        <a href="client-reminders.php?mode=makeup" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="client-reminders.php" class="btn btn-light"><i class="fas fa-bell"></i> Regular Mode</a>
      </div>
    </div>

    <div class="row">
      <!-- Right: make-up composer -->
      <div class="col-lg-4 order-lg-2">
        <div class="card sticky-actions">
          <div class="card-header"><strong><i class="fas fa-envelope"></i> Compose Make-Up (<?= htmlspecialchars(ucfirst($dayKey)) ?>)</strong></div>
          <div class="card-body">
            <form method="post" id="sendMakeupForm">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="send_makeup">
              <input type="hidden" name="makeup_day" value="<?= htmlspecialchars(ucfirst($dayKey)) ?>">

              <div class="form-group">
                <label>From</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars(defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@ffl.notesao.com') ?>" readonly>
              </div>

              <div class="form-group">
                <label>Subject</label>
                <input type="text" class="form-control" name="email_subject" value="<?= htmlspecialchars($DEF_SUBJECT_MAKEUP) ?>">
              </div>

              <div class="form-group">
                <label>Body</label>
                <div class="wysiwrap" data-for="email_body_makeup">
                  <div class="wysi-toolbar">
                    <button type="button" data-cmd="bold"><i class="fas fa-bold"></i></button>
                    <button type="button" data-cmd="italic"><i class="fas fa-italic"></i></button>
                    <button type="button" data-cmd="underline"><i class="fas fa-underline"></i></button>
                    <button type="button" data-cmd="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
                    <button type="button" data-cmd="insertOrderedList"><i class="fas fa-list-ol"></i></button>
                    <button type="button" data-cmd="formatBlock" data-value="p">P</button>
                    <button type="button" data-cmd="formatBlock" data-value="h3">H3</button>
                    <button type="button" data-cmd="createLink">Link</button>
                    <button type="button" data-cmd="removeFormat">Clear</button>
                    <button type="button" data-toggle-source>Source</button>
                  </div>
                  <div class="wysi-editor" contenteditable="true"><?= $DEF_BODY_MAKEUP ?></div>
                  <textarea class="wysi-source form-control"></textarea>
                </div>
                <textarea name="email_body" id="email_body_makeup" class="d-none"></textarea>
                <small class="muted">Placeholders: {{first_name}}, {{last_name}}, {{program_name}}, {{subject_prefix}}, {{makeup_day}}, {{tonight_block}}, {{more_makeups_block}}, {{group_link}}</small>
              </div>

              <button type="submit" class="btn btn-warning btn-block">
                <i class="fas fa-paper-plane"></i> Send Make-Up to Selected
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Left: make-up candidates (grouped by Program → Regular Group) -->
      <div class="col-lg-8 order-lg-1">
        <?php if (empty($candidates)): ?>
          <div class="alert alert-info">No make-up candidates found.</div>
        <?php else: ?>
          <?php foreach ($candidates as $pid => $prog): ?>
            <div class="card mb-3">
              <div class="card-header">
                <strong><?= htmlspecialchars($prog['program_name']) ?></strong>
              </div>
              <div class="card-body p-0">
                <?php foreach ($prog['groups'] as $rgid => $bundle): ?>
                  <div class="border-top">
                    <div class="px-3 py-2 d-flex justify-content-between align-items-center">
                      <div><strong>Regular Group:</strong> <?= htmlspecialchars($bundle['group_name']) ?></div>
                      <div class="select-all text-primary" data-target="mkgrp-<?= (int)$pid ?>-<?= (int)$rgid ?>">
                        <i class="far fa-check-square"></i> Select all
                      </div>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light">
                          <tr><th style="width:36px;"></th><th>Client</th><th>Email</th></tr>
                        </thead>
                        <tbody id="mkgrp-<?= (int)$pid ?>-<?= (int)$rgid ?>">
                        <?php foreach ($bundle['clients'] as $c): ?>
                          <tr>
                            <td><input type="checkbox" class="client-check" name="client_ids[]" form="sendMakeupForm" value="<?= (int)$c['id'] ?>"></td>
                            <td><?= htmlspecialchars(($c['last_name'] ?? '').', '.($c['first_name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                          </tr>
                        <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>

  <?php elseif (preg_match('/^\d+$/', (string)$gid)): /* ---- GROUP VIEW (NUMERIC GID) ---- */ ?>
    <?php
      $gidNum  = (int)$gid;
      $gmeta   = $GROUP_META[$gidNum] ?? ['name'=>'','weekday'=>'','time'=>'','address'=>''];
      $clients = get_clients_for_group($con, $gidNum);
    ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0">
        <i class="fas fa-users"></i>
        <?= htmlspecialchars($gmeta['name'] ?: "Group #$gidNum") ?>
        <?php if (!empty($gmeta['time'])): ?>
          <span class="muted">(<?= htmlspecialchars($gmeta['time']) ?>)</span>
        <?php endif; ?>
      </h3>
      <div class="btn-group">
        <a href="client-reminders.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> All Groups</a>
        <form method="post" class="mb-0">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="quick">
          <input type="hidden" name="gid" value="<?= (int)$gidNum ?>">
          <button class="btn btn-success"><i class="fas fa-bolt"></i> Quick Send</button>
        </form>
      </div>
    </div>

    <div class="row">
      <!-- Right: composer -->
      <div class="col-lg-4 order-lg-2">
        <div class="card sticky-actions">
          <div class="card-header"><strong><i class="fas fa-envelope"></i> Compose to Selected</strong></div>
          <div class="card-body">
            <form method="post" id="sendGroupForm">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="send_group">
              <input type="hidden" name="gid" value="<?= (int)$gidNum ?>">

              <div class="form-group">
                <label>From</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars(defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@ffl.notesao.com') ?>" readonly>
              </div>
              <div class="form-group">
                <label>Subject</label>
                <input type="text" class="form-control" name="email_subject" value="<?= htmlspecialchars($DEF_SUBJECT) ?>">
              </div>
              <div class="form-group">
                <label>Body</label>
                <div class="wysiwrap" data-for="email_body_group">
                  <div class="wysi-toolbar">
                    <button type="button" data-cmd="bold"><i class="fas fa-bold"></i></button>
                    <button type="button" data-cmd="italic"><i class="fas fa-italic"></i></button>
                    <button type="button" data-cmd="underline"><i class="fas fa-underline"></i></button>
                    <button type="button" data-cmd="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
                    <button type="button" data-cmd="insertOrderedList"><i class="fas fa-list-ol"></i></button>
                    <button type="button" data-cmd="formatBlock" data-value="p">P</button>
                    <button type="button" data-cmd="formatBlock" data-value="h3">H3</button>
                    <button type="button" data-cmd="createLink">Link</button>
                    <button type="button" data-cmd="removeFormat">Clear</button>
                    <button type="button" data-toggle-source>Source</button>
                  </div>
                  <div class="wysi-editor" contenteditable="true"><?= $DEF_BODY ?></div>
                  <textarea class="wysi-source form-control"></textarea>
                </div>
                <textarea name="email_body" id="email_body_group" class="d-none"></textarea>
                <small class="muted">Placeholders: {{first_name}}, {{last_name}}, {{program_name}}, {{group_day}}, {{group_time}}, {{group_link}}</small>
              </div>

              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-paper-plane"></i> Send to Selected
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Left: roster -->
      <div class="col-lg-8 order-lg-1">
        <?php if (empty($clients)): ?>
          <div class="alert alert-info">No active clients with email in this group.</div>
        <?php else: ?>
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <strong>Roster</strong>
              <div class="select-all text-primary" data-target="roster-<?= (int)$gidNum ?>">
                <i class="far fa-check-square"></i> Select all
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                  <tr><th style="width:36px;"></th><th>Client</th><th>Email</th></tr>
                </thead>
                <tbody id="roster-<?= (int)$gidNum ?>">
                  <?php foreach ($clients as $c): ?>
                    <tr>
                      <td><input type="checkbox" class="client-check" name="client_ids[]" form="sendGroupForm" value="<?= (int)$c['id'] ?>"></td>
                      <td><?= htmlspecialchars(($c['last_name'] ?? '').', '.($c['first_name'] ?? '')) ?></td>
                      <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

  <?php elseif (preg_match('/^t4c:(sunday|monday|tuesday|wednesday|thursday|friday|saturday)$/i', (string)$gid, $mm)): /* ---- T4C PSEUDO OPEN ---- */ ?>
    <?php
      $day = strtolower($mm[1]);
      [$t4cByDay, $t4cVirtual] = get_t4c_day_rosters($con);
      $clients = $t4cByDay[$day] ?? [];
      $meta = t4c_meta_for_day($day);
      $gmeta = ['name'=>"Thinking for a Change — ".ucfirst($day),'weekday'=>ucfirst($day),'time'=>$meta['time'],'address'=>$meta['address']];
    ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0"><i class="fas fa-users"></i> T4C — <?= htmlspecialchars(ucfirst($day)) ?> (In-Person)</h3>
      <div class="btn-group">
        <a href="client-reminders.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> All Groups</a>
        <form method="post" class="mb-0">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="action" value="quick">
          <input type="hidden" name="gid" value="<?= htmlspecialchars('t4c:'.$day) ?>">
          <button class="btn btn-success"><i class="fas fa-bolt"></i> Quick Send</button>
        </form>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-4 order-lg-2">
        <div class="card sticky-actions">
          <div class="card-header"><strong><i class="fas fa-envelope"></i> Compose to Selected</strong></div>
          <div class="card-body">
            <form method="post" id="sendGroupForm">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="send_group">
              <input type="hidden" name="gid" value="<?= htmlspecialchars('t4c:'.$day) ?>">

              <div class="form-group">
                <label>From</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars(defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@ffl.notesao.com') ?>" readonly>
              </div>
              <div class="form-group">
                <label>Subject</label>
                <input type="text" class="form-control" name="email_subject" value="<?= htmlspecialchars($DEF_SUBJECT) ?>">
              </div>
              <div class="form-group">
                <label>Body</label>
                <div class="wysiwrap" data-for="email_body_group">
                  <div class="wysi-toolbar">
                    <button type="button" data-cmd="bold"><i class="fas fa-bold"></i></button>
                    <button type="button" data-cmd="italic"><i class="fas fa-italic"></i></button>
                    <button type="button" data-cmd="underline"><i class="fas fa-underline"></i></button>
                    <button type="button" data-cmd="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
                    <button type="button" data-cmd="insertOrderedList"><i class="fas fa-list-ol"></i></button>
                    <button type="button" data-cmd="formatBlock" data-value="p">P</button>
                    <button type="button" data-cmd="formatBlock" data-value="h3">H3</button>
                    <button type="button" data-cmd="createLink">Link</button>
                    <button type="button" data-cmd="removeFormat">Clear</button>
                    <button type="button" data-toggle-source>Source</button>
                  </div>
                  <div class="wysi-editor" contenteditable="true"><?= $DEF_BODY ?></div>
                  <textarea class="wysi-source form-control"></textarea>
                </div>
                <textarea name="email_body" id="email_body_group" class="d-none"></textarea>
              </div>

              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-paper-plane"></i> Send to Selected
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-8 order-lg-1">
        <?php if (empty($clients)): ?>
          <div class="alert alert-info">No active clients with email for this T4C day.</div>
        <?php else: ?>
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <strong>Roster</strong>
              <div class="select-all text-primary" data-target="roster-t4c-<?= htmlspecialchars($day) ?>">
                <i class="far fa-check-square"></i> Select all
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                  <tr><th style="width:36px;"></th><th>Client</th><th>Email</th></tr>
                </thead>
                <tbody id="roster-t4c-<?= htmlspecialchars($day) ?>">
                  <?php foreach ($clients as $c): ?>
                    <tr>
                      <td><input type="checkbox" class="client-check" name="client_ids[]" form="sendGroupForm" value="<?= (int)$c['id'] ?>"></td>
                      <td><?= htmlspecialchars(($c['last_name'] ?? '').', '.($c['first_name'] ?? '')) ?></td>
                      <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

  <?php elseif ($gid===''): ?>


    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0"><i class="fas fa-bell"></i> Group Reminders</h3>
      <div class="btn-group">
        <a href="client-index.php" class="btn btn-light"><i class="fas fa-home"></i> Home</a>
        <a href="client-reminders.php?mode=makeup" class="btn btn-warning"><i class="fas fa-undo"></i> Make-Up Mode</a>
      </div>

    </div>

    <div class="row">
      <!-- Right: Broadcast compose -->
      <div class="col-lg-4 order-lg-2">
        <div class="card sticky-actions">
          <div class="card-header">
            <strong><i class="fas fa-bullhorn"></i> Compose to All Clients</strong>
          </div>
          <div class="card-body">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="send_all">
              <div class="form-group">
                <label>From</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars(defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@ffl.notesao.com') ?>" readonly>
              </div>
              <div class="form-group">
                <label>Subject</label>
                <input type="text" class="form-control" name="email_subject"
                       value="<?= htmlspecialchars($DEF_SUBJECT_ALL) ?>">
              </div>

              <div class="form-group">
                <label>Body</label>
                <div class="wysiwrap" data-for="email_body_all">
                  <div class="wysi-toolbar">
                    <button type="button" data-cmd="bold"><i class="fas fa-bold"></i></button>
                    <button type="button" data-cmd="italic"><i class="fas fa-italic"></i></button>
                    <button type="button" data-cmd="underline"><i class="fas fa-underline"></i></button>
                    <button type="button" data-cmd="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
                    <button type="button" data-cmd="insertOrderedList"><i class="fas fa-list-ol"></i></button>
                    <button type="button" data-cmd="formatBlock" data-value="p">P</button>
                    <button type="button" data-cmd="formatBlock" data-value="h3">H3</button>
                    <button type="button" data-cmd="createLink">Link</button>
                    <button type="button" data-cmd="removeFormat">Clear</button>
                    <button type="button" data-toggle-source>Source</button>
                  </div>
                  <div class="wysi-editor" contenteditable="true"><?= $DEF_BODY_ALL ?></div>
                  <textarea class="wysi-source form-control"></textarea>
                </div>
                <!-- Hidden field actually submitted -->
                <textarea name="email_body" id="email_body_all" class="d-none"></textarea>

                <small class="muted">Tip: this blast is general. Group-specific placeholders (like {{group_day}}) may be empty.</small>
              </div>

              <button class="btn btn-primary btn-block"><i class="fas fa-paper-plane"></i> Send Broadcast</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Left: groups list (today-first) -->
      <div class="col-lg-8 order-lg-1">
        <?php
          // Organize DB groups by weekday + render
          $byDay=[0=>[],1=>[],2=>[],3=>[],4=>[],5=>[],6=>[]];
          foreach ($allGroups as $g) {
              if ((int)$g['program_id']===1 && (int)$g['id']===116) continue; // T4C virtual shown later
              [$idx,$wd,$tm] = parse_weekday_time($g['name']);
              if ($idx!==null) $byDay[$idx][] = $g;
          }

          foreach ($order as $didx) {
              $groupsForDay = $byDay[$didx] ?? [];
              $t4cKey = strtolower(day_name_from_idx($didx));
              $hasT4C = !empty($t4cByDay[$t4cKey]);

              if (empty($groupsForDay) && !$hasT4C) continue;

              echo '<div class="card"><div class="card-header d-flex justify-content-between align-items-center">';
              echo '<div><strong>'.htmlspecialchars(day_name_from_idx($didx)).'</strong></div>';
              echo '</div><div class="card-body p-0">';

              echo '<div class="list-group list-group-flush">';

              // T4C in-person pseudo group
              if ($hasT4C) {
                  $label = 'Thinking for a Change — '.ucfirst($t4cKey).' (In-Person)';
                  $gidPseudo = 't4c:'.$t4cKey;
                  echo '<div class="list-group-item d-flex justify-content-between align-items-center">';
                  echo '<div><i class="fas fa-users mr-2"></i>'.htmlspecialchars($label).'</div>';
                  echo '<div class="btn-group">';
                  echo '<a class="btn btn-sm btn-outline-primary" href="client-reminders.php?gid='.urlencode($gidPseudo).'"><i class="fas fa-folder-open"></i> Open</a>';
                  echo '<form method="post" class="ml-2 mb-0">';
                  echo '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token()).'">';
                  echo '<input type="hidden" name="action" value="quick">';
                  echo '<input type="hidden" name="gid" value="'.htmlspecialchars($gidPseudo).'">';
                  echo '<button class="btn btn-sm btn-outline-success"><i class="fas fa-bolt"></i> Quick Send</button>';
                  echo '</form>';
                  echo '</div></div>';
              }

              // Regular (non-T4C) groups
              foreach ($groupsForDay as $g) {
                  [$ix,$wd,$tm] = parse_weekday_time($g['name']);
                  $addr = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([$g['address']??'',$g['city']??'',$g['state']??'',$g['zip']??'']))));
                  echo '<div class="list-group-item d-flex justify-content-between align-items-center">';
                  echo '<div>';
                  echo '<div><i class="fas fa-users mr-2"></i><strong>'.htmlspecialchars($g['name']).'</strong>';
                  if ($tm) echo ' <span class="muted">('.htmlspecialchars($tm).')</span>';
                  echo '</div>';
                  if ($addr) echo '<div class="muted small"><i class="fas fa-map-marker-alt"></i> '.htmlspecialchars($addr).'</div>';
                  echo '</div>';
                  echo '<div class="btn-group">';
                  echo '<a class="btn btn-sm btn-outline-primary" href="client-reminders.php?gid='.(int)$g['id'].'"><i class="fas fa-folder-open"></i> Open</a>';
                  echo '<form method="post" class="ml-2 mb-0">';
                  echo '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token()).'">';
                  echo '<input type="hidden" name="action" value="quick">';
                  echo '<input type="hidden" name="gid" value="'.(int)$g['id'].'">';
                  echo '<button class="btn btn-sm btn-outline-success"><i class="fas fa-bolt"></i> Quick Send</button>';
                  echo '</form>';
                  echo '</div>';
                  echo '</div>';
              }

              echo '</div>'; // list-group
              echo '</div></div>'; // card-body, card
          }

          // T4C virtual (regular group id = 116)
          foreach ($allGroups as $g) {
              if ((int)$g['id'] !== 116) continue;
              [$ix,$wd,$tm] = parse_weekday_time($g['name']);
              $addr = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([$g['address']??'',$g['city']??'',$g['state']??'',$g['zip']??'']))));
              echo '<div class="card mt-3"><div class="card-header"><strong>T4C — Virtual</strong></div><div class="card-body p-0">';
              echo '<div class="list-group list-group-flush">';
              echo '<div class="list-group-item d-flex justify-content-between align-items-center">';
              echo '<div><i class="fas fa-video mr-2"></i><strong>'.htmlspecialchars($g['name']).'</strong>';
              if ($tm) echo ' <span class="muted">('.htmlspecialchars($tm).')</span>';
              if ($addr) echo '<div class="muted small"><i class="fas fa-map-marker-alt"></i> '.htmlspecialchars($addr).'</div>';
              echo '</div>';
              echo '<div class="btn-group">';
              echo '<a class="btn btn-sm btn-outline-primary" href="client-reminders.php?gid='.(int)$g['id'].'"><i class="fas fa-folder-open"></i> Open</a>';
              echo '<form method="post" class="ml-2 mb-0">';
              echo '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token()).'">';
              echo '<input type="hidden" name="action" value="quick">';
              echo '<input type="hidden" name="gid" value="116">';
              echo '<button class="btn btn-sm btn-outline-success"><i class="fas fa-bolt"></i> Quick Send</button>';
              echo '</form>';
              echo '</div></div>';
              echo '</div></div></div>';
          }
        ?>
      </div>
    </div>

  <?php endif; ?>

</div>


<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

<script>
// In-house WYSIWYG bootstrap
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.wysiwrap').forEach(function (wrap) {
    const editor = wrap.querySelector('.wysi-editor');
    const source = wrap.querySelector('.wysi-source');
    const toolbar = wrap.querySelector('.wysi-toolbar');
    const hiddenName = wrap.getAttribute('data-for');
    const hidden = document.getElementById(hiddenName);

    function toSource() { source.value = editor.innerHTML; }
    function toEditor() { editor.innerHTML = source.value; }

    toolbar.addEventListener('click', function (e) {
      const btn = e.target.closest('button');
      if (!btn) return;

      if (btn.hasAttribute('data-toggle-source')) {
        if (wrap.classList.contains('source')) { // back to WYSIWYG
          toEditor();
          wrap.classList.remove('source');
        } else {
          toSource();
          wrap.classList.add('source');
        }
        return;
      }

      const cmd = btn.getAttribute('data-cmd');
      if (!cmd) return;

      if (cmd === 'createLink') {
        const url = prompt('Enter URL (https:// or mailto:):','https://');
        if (url) document.execCommand('createLink', false, url);
        return;
      }
      if (cmd === 'formatBlock') {
        const val = btn.getAttribute('data-value') || 'p';
        document.execCommand('formatBlock', false, val);
        return;
      }
      document.execCommand(cmd, false, null);
    });

    // On form submit, copy editor HTML into hidden textarea actually posted
    const form = wrap.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        if (wrap.classList.contains('source')) toEditor(); // ensure editor reflects source
        if (hidden) hidden.value = editor.innerHTML;
      });
    }
  });

  // Select-all for roster checkboxes (group view)
  document.querySelectorAll('.select-all').forEach(function (el) {
    el.addEventListener('click', function () {
      const targetId = el.getAttribute('data-target');
      const box = document.getElementById(targetId);
      if (!box) return;
      const anyUnchecked = Array.from(box.querySelectorAll('input.client-check')).some(i => !i.checked);
      box.querySelectorAll('input.client-check').forEach(i => { i.checked = anyUnchecked; });
    });
  });
});
</script>
</body>
</html>
