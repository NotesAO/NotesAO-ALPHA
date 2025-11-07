<?php
declare(strict_types=1);
include_once 'auth.php';
check_loggedin($con);
header('Content-Type: application/json');
date_default_timezone_set('America/Chicago');

/* ============ small helpers ============ */
function sql_one(mysqli $con, string $q, array $p = [], string $types = '') {
  $st = $con->prepare($q);
  if (!$st) return null;
  if ($p) {
    if ($types === '') $types = str_repeat('s', count($p));
    $st->bind_param($types, ...$p);
  }
  $st->execute(); $r = $st->get_result();
  return $r ? $r->fetch_assoc() : null;
}
function sql_all(mysqli $con, string $q, array $p = [], string $types = ''): array {
  $st = $con->prepare($q);
  if (!$st) return [];
  if ($p) {
    if ($types === '') $types = str_repeat('s', count($p));
    $st->bind_param($types, ...$p);
  }
  $st->execute(); $r = $st->get_result();
  return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
function column_exists(mysqli $con, string $table, string $col): bool {
  if (!preg_match('/^[a-zA-Z0-9_]+$/',$table)) return false;
  if (!preg_match('/^[a-zA-Z0-9_]+$/',$col)) return false;
  $col_esc = $con->real_escape_string($col);
  $res = $con->query("SHOW COLUMNS FROM `$table` LIKE '$col_esc'");
  return ($res && $res->num_rows>0);
}

/* ============ request ============ */
$req = json_decode(file_get_contents('php://input'), true) ?: [];
$client_id     = (int)($req['client_id'] ?? 0);
$curriculum_id = (int)($req['curriculum_id'] ?? 0);
$complete      = (int)($req['complete'] ?? 0);    // 1=checked, 0=unchecked
$session_date  = trim((string)($req['session_date'] ?? '')); // YYYY-MM-DD optional

if ($client_id<=0 || $curriculum_id<=0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad input']); exit;
}

/* ============ program + group flags ============ */
$cg = sql_one(
  $con,
  "SELECT c.program_id, c.therapy_group_id, p.uses_milestones
     FROM client c
     JOIN program p ON p.id=c.program_id
    WHERE c.id=?",
  [$client_id],'i'
);
if (!$cg || (int)$cg['uses_milestones'] !== 1) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'program not milestone-enabled']); exit;
}
$program_id       = (int)$cg['program_id'];
$therapy_group_id = (int)($cg['therapy_group_id'] ?? 0);
$has_ts_note      = column_exists($con,'therapy_session','note');

/* ============ curriculum order + grouping ============ */
$rows = sql_all(
  $con,
  "SELECT id FROM curriculum WHERE program_id=? ORDER BY sort_order ASC, id ASC",
  [$program_id],'i'
);
$cur_ids = array_map(fn($r)=>(int)$r['id'], $rows);

function plan_for_program(int $pid) {
  return match($pid) {
    1 => [2,2,2,1,1], // DOEP
    2 => [2,3,2],     // DWIE
    3 => 1,           // DWII
    4 => [4,4],       // Parenting
    6 => [4,4],       // LSAT
    default => 4
  };
}
function day_groups(array $ids, int|array $plan): array {
  $out=[]; $i=0; $day=1;
  if (is_int($plan)) {
    $sz=max(1,$plan);
    while ($i<count($ids)) { $out[$day++]=array_slice($ids,$i,$sz); $i+=$sz; }
    return $out;
  }
  $plan=array_values(array_filter($plan,fn($n)=>(int)$n>0)); if(!$plan) $plan=[4];
  foreach($plan as $sz){ if($i>=count($ids)) break; $take=max(1,(int)$sz); $out[$day++]=array_slice($ids,$i,$take); $i+=$take; }
  $last=max(1,(int)end($plan));
  while($i<count($ids)){ $out[$day++]=array_slice($ids,$i,$last); $i+=$last; }
  return $out;
}
$groups = day_groups($cur_ids, plan_for_program($program_id));

$day_num = null; $day_ids=[];
foreach ($groups as $d=>$ids) {
  if (in_array($curriculum_id, $ids, true)) { $day_num=(int)$d; $day_ids=$ids; break; }
}
if ($day_num===null) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'curriculum not mapped to a day']); exit;
}

/* ============ day completeness + session helpers ============ */
function day_completed(mysqli $con, int $client_id, int $program_id, array $day_ids): bool {
  if (!$day_ids) return false;
  $ph = implode(',', array_fill(0,count($day_ids),'?'));
  $types = 'ii'.str_repeat('i', count($day_ids));
  $params = array_merge([$client_id,$program_id], $day_ids);
  $row = sql_one(
    $con,
    "SELECT COUNT(*) AS n
       FROM attendance_curriculum
      WHERE client_id=? AND program_id=? AND curriculum_id IN ($ph)",
    $params, $types
  );
  return (int)($row['n'] ?? 0) >= count($day_ids);
}

function ensure_session(mysqli $con, int $therapy_group_id, string $dateYmd, int $day_num, bool $has_note): int {
  $dateOnly = $con->real_escape_string($dateYmd);
  // normalize created tag to ASCII hyphen; search will use prefix only
  $tag = "Milestone Day $day_num - Full Day";
  $prefixLike = "Milestone Day $day_num%";


  // find existing
  if ($has_note) {
    $sel = $con->prepare(
      "SELECT id FROM therapy_session
        WHERE therapy_group_id ".($therapy_group_id>0 ? "=?":"IS NULL")."
          AND DATE(`date`)=?
          AND `note` LIKE ?
        LIMIT 1"
    );
    if ($therapy_group_id>0) $sel->bind_param('iss',$therapy_group_id,$dateOnly,$prefixLike); // or ->bind_param('ss', $dateOnly, $prefixLike)
    else                      $sel->bind_param('ss',$dateOnly,$prefixLike);
  } else {
    $sel = $con->prepare(
      "SELECT id FROM therapy_session
        WHERE therapy_group_id ".($therapy_group_id>0 ? "=?":"IS NULL")."
          AND DATE(`date`)=?
        LIMIT 1"
    );
    if ($therapy_group_id>0) $sel->bind_param('is',$therapy_group_id,$dateOnly);
    else                      $sel->bind_param('s',$dateOnly);
  }
  $sel->execute();
  $row = $sel->get_result()->fetch_assoc();
  if ($row) return (int)$row['id'];

  // create
  $dt = $dateYmd.' 09:00:00';
  if ($has_note) {
    if ($therapy_group_id>0) {
      $st = $con->prepare("INSERT INTO therapy_session (therapy_group_id, `date`, `note`) VALUES (?,?,?)");
      $st->bind_param('iss', $therapy_group_id, $dt, $tag);
    } else {
      $st = $con->prepare("INSERT INTO therapy_session (`date`, `note`) VALUES (?,?)");
      $st->bind_param('ss', $dt, $tag);
    }
  } else {
    if ($therapy_group_id>0) {
      $st = $con->prepare("INSERT INTO therapy_session (therapy_group_id, `date`) VALUES (?,?)");
      $st->bind_param('is', $therapy_group_id, $dt);
    } else {
      $st = $con->prepare("INSERT INTO therapy_session (`date`) VALUES (?)");
      $st->bind_param('s', $dt);
    }
  }
  if (!$st->execute()) throw new Exception('create session failed');
  return (int)$con->insert_id;
}

function upsert_attendance_present(mysqli $con, int $ts_id, int $client_id): void {
  $has_attended = column_exists($con,'attendance_record','attended');
  $has_excused  = column_exists($con,'attendance_record','excused');
  if ($has_attended && $has_excused) {
    $st = $con->prepare(
      "INSERT INTO attendance_record (therapy_session_id, client_id, attended, excused)
       VALUES (?,?,1,0)
       ON DUPLICATE KEY UPDATE attended=1, excused=0"
    );
    $st->bind_param('ii', $ts_id, $client_id);
  } elseif ($has_attended) {
    $st = $con->prepare(
      "INSERT INTO attendance_record (therapy_session_id, client_id, attended)
       VALUES (?,?,1)
       ON DUPLICATE KEY UPDATE attended=1"
    );
    $st->bind_param('ii', $ts_id, $client_id);
  } else {
    $st = $con->prepare("INSERT IGNORE INTO attendance_record (therapy_session_id, client_id) VALUES (?,?)");
    $st->bind_param('ii', $ts_id, $client_id);
  }
  if (!$st->execute()) throw new Exception('attendance upsert failed');
}

/**
 * Find the session to retract. Priority:
 *  1) session_id already linked on any remaining items of this day.
 *  2) if note column exists, use note LIKE "Milestone Day X — Full Day%".
 *  3) else fall back to any same-day session from the client’s attendance (by date of latest linked item).
 */
function find_day_session_for_retract(mysqli $con, int $client_id, int $program_id, array $day_ids, int $day_num, bool $has_note): ?int {
  if (!$day_ids) return null;

  // (1) session_id linked on remaining items
  $ph = implode(',', array_fill(0,count($day_ids),'?'));
  $types = 'ii'.str_repeat('i', count($day_ids));
  $params = array_merge([$client_id,$program_id], $day_ids);
  $row = sql_one(
    $con,
    "SELECT session_id
       FROM attendance_curriculum
      WHERE client_id=? AND program_id=? AND curriculum_id IN ($ph) AND session_id IS NOT NULL
      ORDER BY completed_at DESC
      LIMIT 1",
    $params, $types
  );
  if ($row && (int)$row['session_id']>0) return (int)$row['session_id'];

  if ($has_note) {
    // (2) look up by note
    $like = "Milestone Day $day_num%";

    $st = $con->prepare(
      "SELECT ts.id
         FROM attendance_record ar
         JOIN therapy_session ts ON ts.id=ar.therapy_session_id
        WHERE ar.client_id=? AND ts.`note` LIKE ?
        ORDER BY ts.`date` DESC LIMIT 1"
    );
    $st->bind_param('is', $client_id, $like);
    $st->execute();
    $r = $st->get_result()->fetch_assoc();
    if ($r) return (int)$r['id'];
  }

  // (3) fallback by date of latest completed item in the day
  $row2 = sql_one(
    $con,
    "SELECT DATE(completed_at) d
       FROM attendance_curriculum
      WHERE client_id=? AND program_id=? AND curriculum_id IN ($ph)
      ORDER BY completed_at DESC LIMIT 1",
    $params, $types
  );
  if (!$row2 || empty($row2['d'])) return null;

  $dateOnly = $row2['d'];
  $st = $con->prepare(
    "SELECT ts.id
       FROM attendance_record ar
       JOIN therapy_session ts ON ts.id=ar.therapy_session_id
      WHERE ar.client_id=? AND DATE(ts.`date`)=?
      ORDER BY ts.`date` DESC LIMIT 1"
  );
  $st->bind_param('is', $client_id, $dateOnly);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  return $r ? (int)$r['id'] : null;
}

function unlink_day_from_session(mysqli $con, int $ts_id, int $client_id, int $program_id, array $day_ids): void {
  if ($ts_id<=0 || !$day_ids) return;
  $ph = implode(',', array_fill(0,count($day_ids),'?'));
  $types = 'iii'.str_repeat('i', count($day_ids));
  $params = array_merge([$ts_id,$client_id,$program_id], $day_ids);
  $upd = $con->prepare(
    "UPDATE attendance_curriculum
        SET session_id=NULL
      WHERE session_id=? AND client_id=? AND program_id=? AND curriculum_id IN ($ph)"
  );
  $upd->bind_param($types, ...$params);
  $upd->execute();
}

function maybe_delete_empty_session(mysqli $con, int $ts_id): void {
  if ($ts_id<=0) return;
  $chk = $con->prepare("SELECT 1 FROM attendance_record WHERE therapy_session_id=? LIMIT 1");
  $chk->bind_param('i', $ts_id);
  $chk->execute();
  $has_any = $chk->get_result()->num_rows > 0;
  if (!$has_any) {
    $del = $con->prepare("DELETE FROM therapy_session WHERE id=?");
    $del->bind_param('i', $ts_id);
    $del->execute();
  }
}

/* ============ timestamps ============ */
$dt = $session_date !== '' ? DateTime::createFromFormat('Y-m-d', $session_date) : new DateTime('today');
if (!$dt) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid session_date']); exit; }
$stampDate = $dt->format('Y-m-d');
$stampDT   = $stampDate.' 12:00:00';
$user_id   = (int)($_SESSION['id'] ?? 0);

/* ============ main ============ */
$con->begin_transaction();
try {
  if ($complete === 1) {
    // mark this item complete
    $ins = $con->prepare(
      "INSERT INTO attendance_curriculum (client_id, program_id, curriculum_id, session_id, completed_at, staff_user_id, source)
       VALUES (?,?,?,NULL,?,?, 'manual')
       ON DUPLICATE KEY UPDATE completed_at=VALUES(completed_at), staff_user_id=VALUES(staff_user_id), source=VALUES(source)"
    );
    $ins->bind_param('iiisi', $client_id, $program_id, $curriculum_id, $stampDT, $user_id);
    if (!$ins->execute()) throw new Exception('attendance_curriculum upsert failed');

    // if day now complete → create/reuse session and link
    $now_complete = day_completed($con, $client_id, $program_id, $day_ids);
    if ($now_complete) {
      $ts_id = ensure_session($con, $therapy_group_id, $stampDate, $day_num, $has_ts_note);
      upsert_attendance_present($con, $ts_id, $client_id);

      $ph = implode(',', array_fill(0,count($day_ids),'?'));
      $types = 'iii'.str_repeat('i', count($day_ids));
      $params = array_merge([$ts_id,$client_id,$program_id], $day_ids);
      $upd = $con->prepare(
        "UPDATE attendance_curriculum
            SET session_id=?
          WHERE client_id=? AND program_id=? AND curriculum_id IN ($ph)"
      );
      $upd->bind_param($types, ...$params);
      $upd->execute();
    }

    $con->commit();
    echo json_encode(['ok'=>true,'completed_at'=>date('Y-m-d H:i', strtotime($stampDT)), 'day_completed'=>$now_complete]); exit;

  } else {
    // uncheck: delete this item’s completion
    $del = $con->prepare("DELETE FROM attendance_curriculum WHERE client_id=? AND program_id=? AND curriculum_id=?");
    $del->bind_param('iii', $client_id, $program_id, $curriculum_id);
    if (!$del->execute()) throw new Exception('attendance_curriculum delete failed');

    // if day is no longer complete → retract attendance and unlink session
    $still_complete = day_completed($con, $client_id, $program_id, $day_ids);
    $attendance_retracted = false;
    if (!$still_complete) {
      $ts_id = find_day_session_for_retract($con, $client_id, $program_id, $day_ids, $day_num, $has_ts_note);
      if ($ts_id) {
        // delete this client’s attendance for that session
        $da = $con->prepare("DELETE FROM attendance_record WHERE therapy_session_id=? AND client_id=?");
        $da->bind_param('ii', $ts_id, $client_id);
        $da->execute();
        // unlink session_id from remaining items of that day
        unlink_day_from_session($con, $ts_id, $client_id, $program_id, $day_ids);
        // drop the session if now empty
        maybe_delete_empty_session($con, $ts_id);
        $attendance_retracted = true;
      }
    }

    $con->commit();
    echo json_encode(['ok'=>true,'completed_at'=>'', 'day_completed'=>false, 'attendance_retracted'=>$attendance_retracted]); exit;
  }

} catch (Throwable $e) {
  $con->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server error']); exit;
}
