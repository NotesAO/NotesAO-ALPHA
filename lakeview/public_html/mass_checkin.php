<?php
declare(strict_types=1);
include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';

$link = isset($link) ? $link : $con;

/* ── schema helpers ───────────────────────────────────────────── */
function table_exists(mysqli $db, string $table): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/',$table)) return false;
    $t = $db->real_escape_string($table);
    $q = $db->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' LIMIT 1");
    return $q && $q->num_rows > 0;
}
function column_exists(mysqli $db, string $table, string $col): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/',$table)) return false;
    if (!preg_match('/^[a-zA-Z0-9_]+$/',$col)) return false;
    $c = $db->real_escape_string($col);
    $res = $db->query("SHOW COLUMNS FROM `$table` LIKE '$c'");
    return $res && $res->num_rows > 0;
}

/* ── program flags ────────────────────────────────────────────── */
function is_milestone_program(mysqli $db, int $program_id): bool {
    if (!table_exists($db,'program') || !column_exists($db,'program','uses_milestones')) return false;
    $res = $db->query("SELECT uses_milestones FROM program WHERE id=$program_id");
    if ($res && ($r=$res->fetch_assoc())) return (int)$r['uses_milestones'] === 1;
    return false;
}

/* ── curriculum grouping (by program) ───────────────────────────
   Map program_id → chunks per day. Parenting=4+4, LifeSkills=4+4, DOEP=5.
*/
function load_curriculum(mysqli $db, int $program_id): array {
    $rows = [];
    if (!$db->real_query("SELECT id FROM curriculum WHERE program_id=$program_id ORDER BY id ASC")) return $rows;
    $res = $db->store_result();
    while ($res && ($r=$res->fetch_assoc())) $rows[] = (int)$r['id'];
    return $rows;
}
function grouping_plan(int $program_id): array {
    if ($program_id === 4) return [4,4];             // Parenting Education
    if ($program_id === 6) return [4,4];             // Life Skills/Anti Theft
    if ($program_id === 1) return [5];               // DOEP
    return [4,4];                                    // sensible default
}
function group_curriculum_ids(array $ids, array $plan): array {
    $out = []; $i=0; $day=1;
    if (!$plan) $plan=[4];
    foreach ($plan as $size) {
        $take = max(1,(int)$size);
        if ($i >= count($ids)) break;
        $out[$day] = array_slice($ids, $i, $take);
        $i += $take; $day++;
    }
    // if curriculum longer than plan, continue chunking with last size
    $last = max(1,(int)end($plan));
    while ($i < count($ids)) {
        $out[$day] = array_slice($ids, $i, $last);
        $i += $last; $day++;
    }
    return $out; // [day => [curriculum_id,...]]
}

/* ── client progress using client_milestone ───────────────────── */
function day_completion(mysqli $db, int $client_id, array $day_ids): int {
    if (!$day_ids) return 0;
    $idlist = implode(',', array_map('intval',$day_ids));
    $sql = "SELECT COUNT(*) cnt
              FROM client_milestone
             WHERE client_id=$client_id
               AND curriculum_id IN ($idlist)";
    $cnt = 0;
    if ($q = $db->query($sql)) { $cnt = (int)$q->fetch_assoc()['cnt']; }
    return $cnt;
}
function next_day_to_award(mysqli $db, int $client_id, array $groups): ?int {
    foreach ($groups as $day => $ids) {
        if (day_completion($db, $client_id, $ids) < count($ids)) return (int)$day;
    }
    return null; // all complete
}

/* ── group resolution ─────────────────────────────────────────── */
function client_group_id(mysqli $db, int $client_id, int $program_id): int {
    $gid = 0;
    if ($q = $db->query("SELECT therapy_group_id FROM client WHERE id=$client_id LIMIT 1")) {
        $r = $q->fetch_assoc(); $gid = (int)($r['therapy_group_id'] ?? 0);
        if ($gid > 0) {
            $chk = $db->query("SELECT 1 FROM therapy_group WHERE id=$gid AND program_id=$program_id");
            if ($chk && $chk->num_rows) return $gid;
        }
    }
    $d = $db->query("SELECT id FROM therapy_group WHERE program_id=$program_id ORDER BY id ASC LIMIT 1");
    if ($d && ($rr=$d->fetch_assoc())) return (int)$rr['id'];
    throw new Exception("No therapy_group for program $program_id");
}

/* ── ensure a SINGLE session for the day (one attendance record) ─ */
function ensure_day_session(mysqli $db, int $therapy_group_id, string $date, int $day, string $user_note): int {
    $dateOnly = $db->real_escape_string($date);
    $tag = "Milestone Day $day — Full Day";
    $tagEsc = $db->real_escape_string($tag);
    // find existing on same calendar day for this group
    $sel = $db->query(
        "SELECT id FROM therapy_session
          WHERE therapy_group_id=$therapy_group_id
            AND DATE(`date`)= '$dateOnly'
            AND `note` LIKE '$tagEsc%' LIMIT 1"
    );
    if ($sel && $sel->num_rows) return (int)$sel->fetch_assoc()['id'];

    $dt = $date.' 09:00:00';
    $noteSuffix = trim($user_note) !== '' ? (' — '.$db->real_escape_string($user_note)) : '';
    $note = $tag.$noteSuffix;

    $cols = ['therapy_group_id','`date`'];
    $vals = [$therapy_group_id, "'$dt'"];

    if (column_exists($db,'therapy_session','duration_minutes')) { $cols[]='duration_minutes'; $vals[]='240'; }
    if (column_exists($db,'therapy_session','curriculum_id'))   { $cols[]='curriculum_id';   $vals[]='NULL'; }
    if (column_exists($db,'therapy_session','facilitator_id'))  { $cols[]='facilitator_id';  $vals[]='NULL'; }
    if (column_exists($db,'therapy_session','note'))            { $cols[]='`note`';          $vals[]="'$note'"; }

    $sql = "INSERT INTO therapy_session (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
    if (!$db->query($sql)) throw new Exception("Create session failed: ".$db->error);
    return (int)$db->insert_id;
}

function has_day_attendance(mysqli $db, int $client_id, string $date): bool {
    $dateEsc = $db->real_escape_string($date);
    $sql = "SELECT 1
              FROM attendance_record ar
              JOIN therapy_session ts ON ts.id=ar.therapy_session_id
             WHERE ar.client_id=$client_id AND DATE(ts.`date`)='$dateEsc' LIMIT 1";
    $q = $db->query($sql);
    return $q && $q->num_rows > 0;
}

/* ── mark all milestones for a day as complete ────────────────── */
function mark_day_milestones(mysqli $db, int $client_id, array $ids, string $date): void {
    if (!$ids) return;
    $dateEsc = $db->real_escape_string($date.' 09:00:00');
    foreach ($ids as $cid) {
        $cid = (int)$cid;
        // upsert style
        $exists = $db->query("SELECT 1 FROM client_milestone WHERE client_id=$client_id AND curriculum_id=$cid LIMIT 1");
        if ($exists && $exists->num_rows) {
            if (column_exists($db,'client_milestone','completed_at')) {
                $db->query("UPDATE client_milestone SET completed_at='$dateEsc'
                             WHERE client_id=$client_id AND curriculum_id=$cid");
            }
        } else {
            $cols = ['client_id','curriculum_id'];
            $vals = ["$client_id","$cid"];
            if (column_exists($db,'client_milestone','completed_at')) { $cols[]='completed_at'; $vals[]="'$dateEsc'"; }
            $db->query("INSERT INTO client_milestone (".implode(',',$cols).") VALUES (".implode(',',$vals).")");
        }
    }
}

/* ── main ─────────────────────────────────────────────────────── */
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Invalid method');

    $program_id = isset($_POST['program_id']) ? (int)$_POST['program_id'] : (int)($_SESSION['program_id'] ?? 0);
    $date_raw   = trim($_POST['checkin_date'] ?? '');
    $note       = trim($_POST['note'] ?? '');
    $client_ids = isset($_POST['client_ids']) && is_array($_POST['client_ids']) ? array_map('intval', $_POST['client_ids']) : [];

    if ($program_id <= 0) throw new Exception('Missing program');
    if (!is_milestone_program($link,$program_id)) throw new Exception('Program not milestone-enabled');
    if ($date_raw === '') throw new Exception('Missing date');
    if (empty($client_ids)) throw new Exception('No clients selected');

    $d = date_create($date_raw);
    if (!$d) throw new Exception('Bad date');
    $date = $d->format('Y-m-d');

    // curriculum → groups by day
    $cur_ids = load_curriculum($link, $program_id);
    if (!$cur_ids) throw new Exception('No curriculum for program');
    $groups = group_curriculum_ids($cur_ids, grouping_plan($program_id));

    $awarded = 0; $skipped = 0;
    foreach ($client_ids as $cid) {
        $gid = client_group_id($link, $cid, $program_id);

        // choose next incomplete day
        $day = next_day_to_award($link, $cid, $groups);
        if ($day === null) { $skipped++; continue; }

        // mark all milestones for that day
        $day_ids = $groups[$day] ?? [];
        mark_day_milestones($link, $cid, $day_ids, $date);

        // ensure a single day session and one attendance
        $ts_id = ensure_day_session($link, $gid, $date, $day, $note);
        if (!has_day_attendance($link, $cid, $date)) {
            if (!$link->query("INSERT INTO attendance_record (client_id, therapy_session_id) VALUES ($cid, $ts_id)")) {
                throw new Exception("Attendance insert failed for client $cid: ".$link->error);
            }
        }
        $awarded++;
    }

    header("Location: client-index.php?mc_ok=1");
    exit;

} catch (Throwable $e) {
    header("Location: client-index.php?mc_err=".urlencode($e->getMessage()));
    exit;
}
