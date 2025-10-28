<?php
declare(strict_types=1);
include_once 'auth.php';
check_loggedin($con);
header('Content-Type: application/json');

/* -------- helpers -------- */
function sql_one(mysqli $con, string $q, array $p = []) {
  $st = $con->prepare($q);
  if ($p) $st->bind_param(str_repeat('s', count($p)), ...$p);
  $st->execute(); $r = $st->get_result();
  return $r ? $r->fetch_assoc() : null;
}
function sql_all(mysqli $con, string $q, array $p = []) : array {
  $st = $con->prepare($q);
  if ($p) $st->bind_param(str_repeat('s', count($p)), ...$p);
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

/* same grouping used in client_review_panel.php */
function plan_for_program(int $program_id) {
  // int = fixed chunk size; array = explicit day sizes
  return match($program_id) {
    4 => [4,4],   // Parenting Education
    6 => [4,4],   // Life Skills/Anti Theft
    1 => 5,       // DOEP (adjust if needed)
    default => 4  // fallback chunk of 4
  };
}
function day_groups(array $curriculum_ids, int|array $plan): array {
  $groups = [];
  $i = 0; $day = 1;
  if (is_int($plan)) {
    $sz = max(1,$plan);
    while ($i < count($curriculum_ids)) {
      $groups[$day++] = array_slice($curriculum_ids, $i, $sz);
      $i += $sz;
    }
  } else {
    $sizes = array_values(array_filter($plan, fn($n)=>intval($n)>0));
    if (!$sizes) $sizes = [4];
    $si = 0;
    while ($i < count($curriculum_ids)) {
      $take = intval($sizes[min($si, count($sizes)-1)]);
      $groups[$day++] = array_slice($curriculum_ids, $i, $take);
      $i += $take; $si++;
    }
  }
  return $groups;
}
/* -------- /helpers -------- */

$req_raw = file_get_contents('php://input');
$req = json_decode($req_raw, true) ?: [];

$client_id     = (int)($req['client_id'] ?? 0);
$curriculum_id = (int)($req['curriculum_id'] ?? 0);
$complete      = (int)($req['complete'] ?? 0);
$session_date  = trim((string)($req['session_date'] ?? '')); // YYYY-MM-DD

if ($client_id<=0 || $curriculum_id<=0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad input']); exit;
}

/* program + group */
$row = sql_one(
  $con,
  "SELECT c.therapy_group_id, c.program_id, p.uses_milestones
     FROM client c
     JOIN program p ON p.id=c.program_id
    WHERE c.id=?",
  [$client_id]
);
if (!$row || (int)$row['uses_milestones'] !== 1) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'program not milestone-enabled']); exit;
}
$therapy_group_id = (int)($row['therapy_group_id'] ?? 0);
$program_id       = (int)($row['program_id'] ?? 0);

/* date handling */
$dt = $session_date !== '' ? DateTime::createFromFormat('Y-m-d', $session_date) : new DateTime('today');
if (!$dt) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'invalid session_date']); exit; }
$stampDate = $dt->format('Y-m-d');
$stampDT   = $stampDate.' 12:00:00'; // neutral time
$user_id   = (int)($_SESSION['id'] ?? 0);

$con->begin_transaction();
try {
  if ($complete === 1) {
    // upsert milestone with provided timestamp
    $stmt = $con->prepare(
      "INSERT INTO client_milestone (client_id, curriculum_id, completed_at, set_by)
       VALUES (?,?,?,?)
       ON DUPLICATE KEY UPDATE completed_at=VALUES(completed_at), set_by=VALUES(set_by)"
    );
    if ($stmt === false) throw new Exception('prepare failed');
    $stmt->bind_param('iisi', $client_id, $curriculum_id, $stampDT, $user_id);
    if (!$stmt->execute()) throw new Exception('milestone upsert failed');
  } else {
    // remove milestone only
    $stmt = $con->prepare("DELETE FROM client_milestone WHERE client_id=? AND curriculum_id=?");
    $stmt->bind_param('ii', $client_id, $curriculum_id);
    if (!$stmt->execute()) throw new Exception('milestone delete failed');
  }

  /* compute the current "day" set for this curriculum_id */
  $rows = sql_all(
    $con,
    "SELECT id FROM curriculum WHERE program_id=? ORDER BY id ASC",
    [$program_id]
  );
  $ids = array_map(fn($r)=>(int)$r['id'], $rows);

  // find the group that contains $curriculum_id
  $groups = day_groups($ids, plan_for_program($program_id));
  $day_ids = [];
  foreach ($groups as $g) {
    if (in_array($curriculum_id, $g, true)) { $day_ids = $g; break; }
  }

  $completed_at = ($complete === 1) ? date('Y-m-d H:i', strtotime($stampDT)) : '';

  // Only create attendance when ALL items in the day are completed.
  if ($complete === 1 && $day_ids) {
    // how many from this day are completed for this client?
    $ph = implode(',', array_fill(0, count($day_ids), '?'));
    $params = array_merge([$client_id], array_map('strval', $day_ids));
    $rowc = sql_one(
      $con,
      "SELECT COUNT(*) AS n
         FROM client_milestone
        WHERE client_id=?
          AND curriculum_id IN ($ph)",
      $params
    );
    $day_done = ((int)($rowc['n'] ?? 0) >= count($day_ids));

    if ($day_done) {
      // ensure a therapy_session exists for this client's group on $stampDate
      if ($therapy_group_id > 0) {
        $sess = sql_one(
          $con,
          "SELECT id FROM therapy_session WHERE therapy_group_id=? AND DATE(`date`)=? LIMIT 1",
          [$therapy_group_id, $stampDate]
        );
      } else {
        $sess = sql_one(
          $con,
          "SELECT id FROM therapy_session WHERE therapy_group_id IS NULL AND DATE(`date`)=? LIMIT 1",
          [$stampDate]
        );
      }

      if (!$sess) {
        if ($therapy_group_id > 0) {
          if (column_exists($con,'therapy_session','created_by')) {
            $st = $con->prepare("INSERT INTO therapy_session (therapy_group_id, `date`, created_by) VALUES (?,?,?)");
            $st->bind_param('isi', $therapy_group_id, $stampDT, $user_id);
          } else {
            $st = $con->prepare("INSERT INTO therapy_session (therapy_group_id, `date`) VALUES (?,?)");
            $st->bind_param('is', $therapy_group_id, $stampDT);
          }
        } else {
          if (column_exists($con,'therapy_session','created_by')) {
            $st = $con->prepare("INSERT INTO therapy_session (`date`, created_by) VALUES (?,?)");
            $st->bind_param('si', $stampDT, $user_id);
          } else {
            $st = $con->prepare("INSERT INTO therapy_session (`date`) VALUES (?)");
            $st->bind_param('s', $stampDT);
          }
        }
        if (!$st->execute()) throw new Exception('create session failed');
        $therapy_session_id = $st->insert_id;
      } else {
        $therapy_session_id = (int)$sess['id'];
      }

      // upsert attendance as present
      $has_attended = column_exists($con,'attendance_record','attended');
      $has_excused  = column_exists($con,'attendance_record','excused');

      if ($has_attended && $has_excused) {
        $st = $con->prepare(
          "INSERT INTO attendance_record (therapy_session_id, client_id, attended, excused)
           VALUES (?,?,1,0)
           ON DUPLICATE KEY UPDATE attended=1, excused=0"
        );
        $st->bind_param('ii', $therapy_session_id, $client_id);
      } elseif ($has_attended) {
        $st = $con->prepare(
          "INSERT INTO attendance_record (therapy_session_id, client_id, attended)
           VALUES (?,?,1)
           ON DUPLICATE KEY UPDATE attended=1"
        );
        $st->bind_param('ii', $therapy_session_id, $client_id);
      } else {
        $st = $con->prepare(
          "INSERT IGNORE INTO attendance_record (therapy_session_id, client_id)
           VALUES (?,?)"
        );
        $st->bind_param('ii', $therapy_session_id, $client_id);
      }
      if (!$st->execute()) throw new Exception('attendance upsert failed');
    }
  }

  $con->commit();
  echo json_encode(['ok'=>true,'completed_at'=>$completed_at]); exit;

} catch (Throwable $e) {
  $con->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server error']); exit;
}
