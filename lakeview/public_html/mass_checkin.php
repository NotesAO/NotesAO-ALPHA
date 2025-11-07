<?php
declare(strict_types=1);
include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';

date_default_timezone_set('America/Chicago');

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

/* ── curriculum grouping by program (Phase 1 rules) ─────────────
   1 DOEP: 2–2–2–1–1
   2 DWIE: 2–3–2
   3 DWII: 1 per module
   4 Parenting: 4–4
   6 LSAT: 4–4
*/
function load_curriculum(mysqli $db, int $program_id): array {
    $rows = [];
    $stmt = $db->prepare("SELECT id FROM curriculum WHERE program_id=? ORDER BY sort_order ASC, id ASC");
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && ($r=$res->fetch_assoc())) $rows[] = (int)$r['id'];
    return $rows;
}
function grouping_plan(int $program_id): array|int {
    return match ($program_id) {
        1 => [2,2,2,1,1], // DOEP
        2 => [2,3,2],     // DWIE
        3 => 1,           // DWII
        4 => [4,4],       // Parenting
        6 => [4,4],       // LSAT
        default => 4,
    };
}
function group_curriculum_ids(array $ids, array|int $plan): array {
    $out = []; $i=0; $day=1;
    if (is_int($plan)) {
        $sz = max(1,$plan);
        while ($i < count($ids)) {
            $out[$day++] = array_slice($ids, $i, $sz);
            $i += $sz;
        }
        return $out;
    }
    $plan = array_values(array_filter($plan, fn($n)=> (int)$n>0));
    if (!$plan) $plan=[4];
    foreach ($plan as $size) {
        if ($i >= count($ids)) break;
        $take = max(1,(int)$size);
        $out[$day++] = array_slice($ids, $i, $take);
        $i += $take;
    }
    $last = max(1,(int)end($plan));
    while ($i < count($ids)) {
        $out[$day++] = array_slice($ids, $i, $last);
        $i += $last;
    }
    return $out; // [day => [curriculum_id,...]]
}

/* ── attendance_curriculum progress ───────────────────────────── */
function day_completion(mysqli $db, int $client_id, int $program_id, array $day_ids): int {
    if (!$day_ids) return 0;
    $ph    = implode(',', array_fill(0, count($day_ids), '?'));
    $types = str_repeat('i', count($day_ids) + 2);
    $sql   = "SELECT COUNT(*) cnt
                FROM attendance_curriculum
               WHERE client_id=? AND program_id=? AND curriculum_id IN ($ph)";
    $st = $db->prepare($sql);
    $params = array_merge([$client_id, $program_id], $day_ids);
    $st->bind_param($types, ...$params);
    $st->execute();
    $r = $st->get_result();
    return (int)($r ? ($r->fetch_assoc()['cnt'] ?? 0) : 0);
}
function next_day_to_award(mysqli $db, int $client_id, int $program_id, array $groups): ?int {
    foreach ($groups as $day => $ids) {
        if (day_completion($db, $client_id, $program_id, $ids) < count($ids)) return (int)$day;
    }
    return null;
}


/* ── group resolution ─────────────────────────────────────────── */
function client_group_id(mysqli $db, int $client_id, int $program_id): int {
    $gid = 0;
    $q = $db->prepare("SELECT therapy_group_id FROM client WHERE id=? LIMIT 1");
    $q->bind_param('i',$client_id);
    $q->execute();
    $res = $q->get_result();
    if ($res && ($r=$res->fetch_assoc())) $gid = (int)($r['therapy_group_id'] ?? 0);

    if ($gid > 0) {
        $chk = $db->prepare("SELECT 1 FROM therapy_group WHERE id=? AND program_id=?");
        $chk->bind_param('ii',$gid,$program_id);
        $chk->execute();
        $cr = $chk->get_result();
        if ($cr && $cr->num_rows) return $gid;
    }

    $d = $db->prepare("SELECT id FROM therapy_group WHERE program_id=? ORDER BY id ASC LIMIT 1");
    $d->bind_param('i',$program_id);
    $d->execute();
    $rr = $d->get_result()->fetch_assoc();
    if ($rr) return (int)$rr['id'];
    throw new Exception("No therapy_group for program $program_id");
}

/* ── ensure a SINGLE session for the day (one attendance record) ─ */
function ensure_day_session(mysqli $db, int $therapy_group_id, string $date, int $day, string $user_note): int {
    $dateOnly = $db->real_escape_string($date);
    $tag = "Milestone Day $day — Full Day";
    $tagEsc = $db->real_escape_string($tag);

    $sel = $db->query(
        "SELECT id FROM therapy_session
          WHERE therapy_group_id=$therapy_group_id
            AND DATE(`date`)='$dateOnly'
            AND `note` LIKE '$tagEsc%' LIMIT 1"
    );
    if ($sel && $sel->num_rows) return (int)$sel->fetch_assoc()['id'];

    $dt = $date.' 09:00:00';
    $noteSuffix = trim($user_note) !== '' ? (' — '.$db->real_escape_string($user_note)) : '';
    $noteVal = $tag.$noteSuffix;

    $cols = ['therapy_group_id','`date`'];
    $vals = [$therapy_group_id, "'$dt'"];

    if (column_exists($db,'therapy_session','duration_minutes')) { $cols[]='duration_minutes'; $vals[]='240'; }
    if (column_exists($db,'therapy_session','curriculum_id'))   { $cols[]='curriculum_id';   $vals[]='NULL'; }
    if (column_exists($db,'therapy_session','facilitator_id'))  { $cols[]='facilitator_id';  $vals[]='NULL'; }
    if (column_exists($db,'therapy_session','note'))            { $cols[]='`note`';          $vals[]="'$noteVal'"; }

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

/* ── write attendance record (present) ────────────────────────── */
function upsert_attendance_present(mysqli $db, int $therapy_session_id, int $client_id): void {
    $has_attended = column_exists($db,'attendance_record','attended');
    $has_excused  = column_exists($db,'attendance_record','excused');

    if ($has_attended && $has_excused) {
        $st = $db->prepare(
          "INSERT INTO attendance_record (therapy_session_id, client_id, attended, excused)
           VALUES (?,?,1,0)
           ON DUPLICATE KEY UPDATE attended=1, excused=0"
        );
        $st->bind_param('ii', $therapy_session_id, $client_id);
        if (!$st->execute()) throw new Exception('attendance upsert failed');
        return;
    }
    if ($has_attended) {
        $st = $db->prepare(
          "INSERT INTO attendance_record (therapy_session_id, client_id, attended)
           VALUES (?,?,1)
           ON DUPLICATE KEY UPDATE attended=1"
        );
        $st->bind_param('ii', $therapy_session_id, $client_id);
        if (!$st->execute()) throw new Exception('attendance upsert failed');
        return;
    }
    $st = $db->prepare(
      "INSERT IGNORE INTO attendance_record (therapy_session_id, client_id) VALUES (?,?)"
    );
    $st->bind_param('ii', $therapy_session_id, $client_id);
    if (!$st->execute()) throw new Exception('attendance insert failed');
}

/* ── link completions to session_id for that day ──────────────── */
function link_day_items_to_session(mysqli $db, int $client_id, int $program_id, array $day_ids, int $therapy_session_id, string $date): void {
    if (!$day_ids) return;
    $ph    = implode(',', array_fill(0, count($day_ids), '?'));
    $types = 'iiis' . str_repeat('i', count($day_ids));
    $sql   = "UPDATE attendance_curriculum
                 SET session_id=?
               WHERE client_id=? AND program_id=? AND DATE(completed_at)=?
                 AND curriculum_id IN ($ph)";
    $stmt = $db->prepare($sql);
    $params = array_merge([$therapy_session_id, $client_id, $program_id, $date], $day_ids);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
}

/* ── auto-exit when sessions reached ──────────────────────────── */
function maybe_auto_exit(mysqli $db, int $client_id, string $date_for_exit): void {
    // required = COALESCE(client.required_sessions, program.expected_sessions)
    $req = null;
    $q = $db->prepare(
        "SELECT COALESCE(c.required_sessions, p.expected_sessions) AS req
           FROM client c JOIN program p ON p.id=c.program_id
          WHERE c.id=?"
    );
    $q->bind_param('i', $client_id);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    if (!$r || $r['req'] === null) return;
    $req = (int)$r['req'];

    // attended count
    $has_attended = column_exists($db,'attendance_record','attended');
    if ($has_attended) {
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS n
               FROM attendance_record ar
               WHERE ar.client_id=? AND ar.attended=1"
        );
    } else {
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS n
               FROM attendance_record ar
               WHERE ar.client_id=?"
        );
    }
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $n = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);

    if ($n < $req) return;

    // already exited?
    $chk = $db->prepare("SELECT exit_date FROM client WHERE id=?");
    $chk->bind_param('i',$client_id);
    $chk->execute();
    $er = $chk->get_result()->fetch_assoc();
    if ($er && !empty($er['exit_date']) && $er['exit_date'] !== '0000-00-00') return;

    // use last session date for this client
    $stmt2 = $db->prepare(
        "SELECT DATE(MAX(ts.`date`)) AS last_dt
           FROM attendance_record ar
           JOIN therapy_session ts ON ts.id=ar.therapy_session_id
          WHERE ar.client_id=?"
    );
    $stmt2->bind_param('i',$client_id);
    $stmt2->execute();
    $last_dt = $stmt2->get_result()->fetch_assoc()['last_dt'] ?? $date_for_exit;

    // set exit_date and exit_reason_id=3
    $ex = $db->prepare("UPDATE client SET exit_date=?, exit_reason_id=3 WHERE id=?");
    $ex->bind_param('si', $last_dt, $client_id);
    $ex->execute();
}

/* ── final correct implementation (concise, no signature errors) ─ */
try {
    // Re-parse inputs safely
    $program_id = isset($_POST['program_id']) ? (int)$_POST['program_id'] : (int)($_SESSION['program_id'] ?? 0);
    $date_raw   = trim($_POST['checkin_date'] ?? '');
    $note       = trim($_POST['note'] ?? '');
    $client_ids = isset($_POST['client_ids']) && is_array($_POST['client_ids']) ? array_values(array_unique(array_map('intval', $_POST['client_ids']))):[];

    if ($program_id <= 0) throw new Exception('Missing program');
    if (!is_milestone_program($link,$program_id)) throw new Exception('Program not milestone-enabled');
    if ($date_raw === '') throw new Exception('Missing date');
    if (empty($client_ids)) throw new Exception('No clients selected');

    $d = date_create($date_raw);
    if (!$d) throw new Exception('Bad date');
    $date = $d->format('Y-m-d');
    $stampDT = $date.' 12:00:00';
    $user_id = (int)($_SESSION['id'] ?? 0);

    $cur_ids = load_curriculum($link, $program_id);
    if (!$cur_ids) throw new Exception('No curriculum for program');
    $groups = group_curriculum_ids($cur_ids, grouping_plan($program_id));

    // pre-build prepared statements
    $ins_ac = $link->prepare(
        "INSERT INTO attendance_curriculum (client_id, program_id, curriculum_id, session_id, completed_at, staff_user_id, source)
        VALUES (?,?,?,NULL,?,?, 'bulk')
        ON DUPLICATE KEY UPDATE completed_at=VALUES(completed_at), staff_user_id=VALUES(staff_user_id), source=VALUES(source)"
    );
    if (!$ins_ac) { throw new Exception('prepare attendance_curriculum failed: '.$link->error); }
    // bind types: i i i s i
    foreach ($client_ids as $cid) {
        $link->begin_transaction();
        try {
            $gid = client_group_id($link, $cid, $program_id);

            // find next day
            $day = null;
            foreach ($groups as $dix => $ids) {
                if (day_completion($link, $cid, $program_id, $ids) < count($ids)) { $day = (int)$dix; break; }
            }
            if ($day === null) { $link->commit(); continue; }
            $day_ids = $groups[$day] ?? [];
            if (!$day_ids) { $link->commit(); continue; }

            // upsert AC rows
            foreach ($day_ids as $curId) {
                $cid_i = (int)$cid;
                $pid_i = (int)$program_id;
                $cur_i = (int)$curId;
                $ins_ac->bind_param('iiisi', $cid_i, $pid_i, $cur_i, $stampDT, $user_id);
                if (!$ins_ac->execute()) throw new Exception('attendance_curriculum upsert failed');
            }

            // ensure session + attendance
            $ts_id = ensure_day_session($link, $gid, $date, $day, $note);
            upsert_attendance_present($link, $ts_id, $cid);

            // link curriculum rows to session_id
            link_day_items_to_session($link, $cid, $program_id, $day_ids, $ts_id, $date);

            // auto-exit if reached required
            maybe_auto_exit($link, $cid, $date);

            $link->commit();
        } catch (Throwable $inner) {
            $link->rollback();
            throw $inner;
        }
    }
    if ($ins_ac instanceof mysqli_stmt) { $ins_ac->close(); }

    header("Location: client-index.php?mc_ok=1");
    exit;

} catch (Throwable $e) {
    header("Location: client-index.php?mc_err=".urlencode($e->getMessage()));
    exit;
}
