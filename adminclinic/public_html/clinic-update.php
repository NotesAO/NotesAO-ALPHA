<?php
/* ─── DEBUG (comment-out in production) ─────────────────────────────── */
ini_set('display_errors',1);            error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
/* ───────────────────────────────────────────────────────────────────── */

include_once 'auth.php';      check_loggedin($con);
require_once 'sql_functions.php';       // db(), run()
require_once 'helpers.php';            // the h() escaper

/* tiny PDO helper (only if not provided elsewhere) */
if (!function_exists('run')) {
    function run(string $q,array $p=[]){
        $st=db()->prepare($q);$st->execute($p);
        return preg_match('/^\s*SELECT/i',$q) ? $st->fetchAll(PDO::FETCH_ASSOC) : true;
    }
}

/* ───────── Resolve clinic id ───────── */
$id=(int)($_GET['id']??$_POST['id']??0) ?: die('missing id');
$clean=fn(?string $v)=>($v===null||trim($v)==='')?null:trim($v);

/* ───────── SAVE ───────── */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    db()->beginTransaction();

    /* ────────────────── Onboarding task seeding helpers ────────────────── */
    /* resolves an onboarding_task.id by its task_name (phase='Onboarding') */
    $getTaskId = function(string $key) {
        $row = run("SELECT id FROM onboarding_task WHERE phase='Onboarding' AND task_name=?", [$key]);
        return $row[0]['id'] ?? null;
    };

    /* upsert a scoped clinic_onboarding_task row (program/template/data aware) */
    $upsertOnTask = db()->prepare("
        INSERT INTO clinic_onboarding_task
            (clinic_id, task_id, status, checked_by_user_id, checked_at, notes, program_id, template_type, data_field)
        VALUES (?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            notes = VALUES(notes)
    ");


    /* ── clinic core ── */
    $core=['name','subdomain','status','go_live_date',
           'primary_contact_name','primary_contact_email','primary_contact_phone'];
    $set=implode(', ',array_map(fn($c)=>"$c=?", $core));
    run("UPDATE clinic SET $set WHERE id=?",
        [...array_map(fn($c)=>$clean($_POST[$c]??null),$core),$id]);

    /* ── sales profile ── */
    $cols=['first_meeting_date','estimated_client_count',
           'admin_account_count','facilitator_account_count',
           'pricepoint','regular_onboarding_fee',
           'contact_name','contact_email','contact_phone'];
    $vals=[];
    foreach($cols as $c){
        $vals[]=$c==='regular_onboarding_fee'
                 ? (isset($_POST['regular_onboarding_fee'])?1:0)
                 : $clean($_POST[$c]??null);
    }

    run("INSERT INTO clinic_sales_profile (clinic_id,".implode(',',$cols).")
         VALUES (?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE
            first_meeting_date         = VALUES(first_meeting_date),
            estimated_client_count     = VALUES(estimated_client_count),
            admin_account_count        = VALUES(admin_account_count),
            facilitator_account_count  = VALUES(facilitator_account_count),
            pricepoint                 = VALUES(pricepoint),
            regular_onboarding_fee     = VALUES(regular_onboarding_fee),
            contact_name               = VALUES(contact_name),
            contact_email              = VALUES(contact_email),
            contact_phone              = VALUES(contact_phone)",
        [$id,...$vals]);

    /* ── payment profile ── */
    run("DELETE FROM clinic_payment_profile WHERE clinic_id=?",[$id]);
    run("INSERT INTO clinic_payment_profile
            (clinic_id,method,accepts_partial,used_by_facilitators,
             notesao_processor_opt_in,additional_details)
          VALUES (?,?,?,?,?,?)",
        [$id,
         $_POST['method']             ?? 'Cash',
         isset($_POST['accepts_partial'])?1:0,
         isset($_POST['used_by_facilitators'])?1:0,
         isset($_POST['notesao_processor_opt_in'])?1:0,
         $clean($_POST['additional_details']??null)]);

    
    /* ── programs ──  (wipe & replace) */
    run("DELETE FROM program WHERE clinic_id=?",[$id]);

    $progStmt=db()->prepare(
        "INSERT INTO program
            (clinic_id, prog_code, name,
            offers_in_person, offers_virtual, is_virtual,
            in_person_location, virtual_platform, weekly_times)
        VALUES (?,?,?,?,?,?,?,?,?)");


    $labels=['bipp_m'=>'BIPP (male)','bipp_f'=>'BIPP (female)',
             'anger'=>'Anger Control','t4c'=>'Thinking for a Change',
             'tips'=>'TIPS','mrt'=>'MRT','iop'=>'IOP','sop'=>'SOP'];
    $meta = $_POST['prog_meta'] ?? [];   // ← NEW


    foreach ($_POST['programs'] ?? [] as $code) {
        $loc  = $clean($meta[$code]['loc']  ?? null);
        $virt = $clean($meta[$code]['virt'] ?? null);
        $offers_ip = $loc ? 1 : 0;
        $offers_v  = $virt ? 1 : 0;
        $progStmt->execute([
            $id, $code, $labels[$code] ?? ucfirst($code),
            $offers_ip, $offers_v, $offers_v,   // is_virtual: treat “has virtual” as virtual program
            $loc, $virt,
            null                                 // weekly_times unused
        ]);

    }
    /* “Other” */
    if(!empty($_POST['program_other_enabled'])
       && ($pname=$clean($_POST['program_other_name']??''))){
        $loc  = $clean($meta['other']['loc']  ?? null);
        $virt = $clean($meta['other']['virt'] ?? null);
        $offers_ip = $loc ? 1 : 0;
        $offers_v  = $virt ? 1 : 0;
        $progStmt->execute([$id,'other',$pname,$offers_ip,$offers_v,$offers_v,$loc,$virt,null]);

    }

    /* ────────────────── Seed per‑program Template tasks ────────────────── */
    $progRowsFull = run(
        "SELECT id, prog_code,
                offers_in_person AS has_ip,
                offers_virtual   AS has_v
          FROM program
          WHERE clinic_id = ?", [$id]);


    /* resolve catalog ids once */
    $T = [
      'entr' => $getTaskId('Template: Entrance Notification'),
      'abs'  => $getTaskId('Template: Absence'),
      'pri'  => $getTaskId('Template: Progress Report (In-Person)'),
      'prv'  => $getTaskId('Template: Progress Report (Virtual)'),
      'cc'   => $getTaskId('Template: Completion Certificate'),
      'cl'   => $getTaskId('Template: Completion Letter'),
      'cpi'  => $getTaskId('Template: Completion Progress (In-Person)'),
      'cpv'  => $getTaskId('Template: Completion Progress (Virtual)'),
      'xl'   => $getTaskId('Template: Exit Letter'),
      'xpi'  => $getTaskId('Template: Exit Progress (In-Person)'),
      'xpv'  => $getTaskId('Template: Exit Progress (Virtual)'),
      'v_e'  => $getTaskId('Template: Victim Entrance'),
      'v_c'  => $getTaskId('Template: Victim Completion'),
      'v_x'  => $getTaskId('Template: Victim Exit'),
      'bc'   => $getTaskId('Template: Behavior Contract'),
      'wb'   => $getTaskId('Template: Workbook'),
    ];

    foreach ($progRowsFull as $p) {
        $pid   = (int)$p['id'];
        $hasIP = (int)$p['has_ip'] === 1;
        $hasV  = (int)$p['has_v']  === 1;

        // always required (not modality-specific)
        $req = [
          ['tid'=>$T['entr'], 'tt'=>'Entrance_Notification'],
          ['tid'=>$T['abs'],  'tt'=>'Absence'],
          ['tid'=>$T['cc'],   'tt'=>'Completion_Certificate'],
          ['tid'=>$T['cl'],   'tt'=>'Completion_Letter'],
          ['tid'=>$T['xl'],   'tt'=>'Exit_Letter'],
          ['tid'=>$T['v_e'],  'tt'=>'Victim_Letter_Entrance'],
          ['tid'=>$T['v_c'],  'tt'=>'Victim_Letter_Completion'],
          ['tid'=>$T['v_x'],  'tt'=>'Victim_Letter_Exit'],
          ['tid'=>$T['bc'],   'tt'=>'Behavior_Contract'],
          ['tid'=>$T['wb'],   'tt'=>'Workbook'],
        ];

        // modality-specific
        if ($hasIP) {
            $req[] = ['tid'=>$T['pri'], 'tt'=>'Progress_Report_InPerson'];
            $req[] = ['tid'=>$T['cpi'], 'tt'=>'Completion_Progress_Report_InPerson'];
            $req[] = ['tid'=>$T['xpi'], 'tt'=>'Exit_Progress_Report_InPerson'];
        }
        if ($hasV) {
            $req[] = ['tid'=>$T['prv'], 'tt'=>'Progress_Report_Virtual'];
            $req[] = ['tid'=>$T['cpv'], 'tt'=>'Completion_Progress_Report_Virtual'];
            $req[] = ['tid'=>$T['xpv'], 'tt'=>'Exit_Progress_Report_Virtual'];
        }

        foreach ($req as $r) {
            if (!$r['tid']) continue; // catalog row missing → skip
            $upsertOnTask->execute([
                $id, $r['tid'], 'Pending',
                $_SESSION['admin_id'] ?? null, null, null,
                $pid, $r['tt'], null
            ]);
        }
    }

    /* ────────────────── Seed data-field coverage tasks ────────────────── */
    $dataTaskId = $getTaskId('Data: Client Fields Coverage');
    if ($dataTaskId) {
        $clinicFields = [
            'first_name','last_name','dob','birth_place','gender','ethnicity',
            'program','intake_packet_submitted','phone','email',
            'emergency_contact_name','emergency_contact_relation','emergency_contact_phone',
            'referral_type','required_sessions','sessions_per_week',
            'group_fee','assigned_group','orientation_date',
            'cause_number','current_balance',
            /* NOTE: REMOVE 'case_manager' here */
            'victim_name','victim_relationship','victim_gender','victim_age',
            'victim_children_under_18','victim_lives_with_client',
            'victim_address','victim_phone','victim_email',
            'attendance_dates'
        ];

        /* Clean up bad historical rows + orphans for this clinic before seeding */
        $place = implode(',', array_fill(0, count($clinicFields), '?'));

        /* 1) Per-program copies of generic fields (should be global): remove them */
        run("
            DELETE FROM clinic_onboarding_task
            WHERE clinic_id = ?
              AND task_id   = ?
              AND template_type IS NULL
              AND program_id IS NOT NULL
              AND data_field IN ($place)
        ", array_merge([$id, $dataTaskId], $clinicFields));

        /* 2) Orphans: program_id that no longer exists (ids change when we re-create programs) */
        run("
            DELETE c
              FROM clinic_onboarding_task c
        LEFT JOIN program p ON p.id = c.program_id
            WHERE c.clinic_id = ?
              AND c.program_id IS NOT NULL
              AND p.id IS NULL
        ", [$id]);

        /* 3) Deduplicate exact duplicates at this clinic (keep the first) */
        run("
            DELETE c1
              FROM clinic_onboarding_task c1
              JOIN clinic_onboarding_task c2
                ON c1.clinic_id=c2.clinic_id AND c1.task_id=c2.task_id
              AND COALESCE(c1.program_id,0)=COALESCE(c2.program_id,0)
              AND COALESCE(c1.template_type,'')=COALESCE(c2.template_type,'')
              AND COALESCE(c1.data_field,'')=COALESCE(c2.data_field,'')
              AND c1.id > c2.id
            WHERE c1.clinic_id=?
        ", [$id]);


        foreach ($clinicFields as $fn) {
          run("
              INSERT INTO clinic_onboarding_task
                  (clinic_id, task_id, status, checked_by_user_id, checked_at, notes,
                  program_id, template_type, data_field)
              SELECT ?, ?, ?, ?, NULL, NULL, NULL, NULL, ?
              FROM DUAL
              WHERE NOT EXISTS (
                  SELECT 1
                    FROM clinic_onboarding_task
                  WHERE clinic_id = ?
                    AND task_id   = ?
                    AND data_field = ?
                    AND program_id IS NULL
                    AND template_type IS NULL
              )
          ", [
              $id,                         // clinic_id
              $dataTaskId,                 // task_id  (← was missing)
              'Pending',                   // status
              $_SESSION['admin_id'] ?? null, // checked_by_user_id
              $fn,                         // data_field (for the INSERT)
              $id, $dataTaskId, $fn        // NOT EXISTS bind params
          ]);
      }


        /* Per-program: exactly one 'case_manager' per program (NULL-safe) */
        foreach ($progRowsFull as $p) {
            run("
                INSERT INTO clinic_onboarding_task
                    (clinic_id, task_id, status, checked_by_user_id, checked_at, notes,
                    program_id, template_type, data_field)
                SELECT ?, ?, 'Pending', ?, NULL, NULL, ?, NULL, 'case_manager'
                FROM DUAL
                WHERE NOT EXISTS (
                    SELECT 1 FROM clinic_onboarding_task
                    WHERE clinic_id=? AND task_id=? AND program_id=? 
                      AND data_field='case_manager' AND template_type IS NULL
                )
            ", [
                $id, $dataTaskId, $_SESSION['admin_id'] ?? null, (int)$p['id'],
                $id, $dataTaskId, (int)$p['id']
            ]);
        }


    }



    /* ────────────────── Seed user-account creation tasks (optional) ────────────────── */
    $acctTaskId = $getTaskId('Accounts: Create User');
    if ($acctTaskId) {
        $reqs = run("SELECT username, email FROM user_account_request WHERE clinic_id=? AND status='Pending'", [$id]);
        foreach ($reqs as $r) {
            $upsertOnTask->execute([
                $id, $acctTaskId, 'Pending',
                $_SESSION['admin_id'] ?? null, null,
                sprintf('username=%s; email=%s', $r['username'], $r['email']),
                null, null, 'user_account'
            ]);
        }
    }

    /* ── onboarding checklist (scoped rows) ── */
    if (isset($_POST['sc'])) {
        $now = date('Y-m-d H:i:s');
        $ins = db()->prepare("
            INSERT INTO clinic_onboarding_task
              (clinic_id, task_id, status, checked_by_user_id, checked_at, notes, program_id, template_type, data_field)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              status=VALUES(status),
              checked_by_user_id=VALUES(checked_by_user_id),
              checked_at=VALUES(checked_at),
              notes=VALUES(notes)
        ");

        // current program id by code (after we just re-inserted programs)
        $progIdByCode = [];
        foreach (run("SELECT id, prog_code FROM program WHERE clinic_id=?", [$id]) as $pr) {
            $progIdByCode[$pr['prog_code']] = (int)$pr['id'];
        }


        foreach ($_POST['sc'] as $row) {
          $st = $row['status'] ?? 'Pending';

          // prefer stable prog_code over posted program_id (ids were regenerated)
          $code = $row['prog_code'] ?? '';
          if ($code !== '' && !isset($progIdByCode[$code])) {
              // program was removed in this save → skip this row silently
              continue;
          }
          $pid = ($code === '') ? null : $progIdByCode[$code];

          $tt  = ($row['template_type'] ?? '') === '' ? null : $row['template_type'];
          $df  = ($row['data_field'] ?? '') === '' ? null : $row['data_field'];

          $ins->execute([
              $id, (int)$row['task_id'], $st,
              $_SESSION['admin_id'] ?? null,
              $st==='Complete' ? $now : null,
              $clean($row['notes'] ?? null),
              $pid, $tt, $df
          ]);
        }

    }


    /* ── onboarding checklist (non-Sales only) ── */
    if(isset($_POST['task'])){
        $now=date('Y-m-d H:i:s');
        $chk=db()->prepare(
            "INSERT INTO clinic_onboarding_task
                 (clinic_id,task_id,status,checked_by_user_id,checked_at,notes)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                 status            = VALUES(status),
                 checked_by_user_id= VALUES(checked_by_user_id),
                 checked_at        = VALUES(checked_at),
                 notes             = VALUES(notes)");
        foreach($_POST['task'] as $tid=>$row){
            $st=$row['status']??'Pending';
            $chk->execute([$id,(int)$tid,$st,
                           $_SESSION['admin_id']??null,
                           $st==='Complete'?$now:null,
                           $clean($row['notes']??null)]);
        }
    }

    /* ---------- WEEKLY SCHEDULE (table rows) --------------------------- */
    run("DELETE FROM clinic_weekly_schedule WHERE clinic_id=?", [$id]);

    if (!empty($_POST['sched'])) {
        $insSched = db()->prepare(
          "INSERT INTO clinic_weekly_schedule
            (clinic_id, prog_code, day_of_week, start_time, end_time, location, perm_link)
          VALUES (?,?,?,?,?,?,?)");

        foreach ($_POST['sched'] as $row) {
            $pg = $clean($row['prog'] ?? '');
            $dow = $clean($row['day']  ?? '');
            $st  = $clean($row['start']??'');
            $et  = $clean($row['end']  ?? '');
            $lnk = $clean($row['link'] ?? '');

            if (!$dow || !$st || !$et || !$pg) continue;          // skip incomplete lines

            /* ─── FK validation (“die” gives a friendly message instead of PDO fatal) ─── */
            $chk = db()->prepare('SELECT 1 FROM program WHERE clinic_id = ? AND prog_code = ?');
            $chk->execute([$id, $pg]);
            if (!$chk->fetchColumn()) {
                die("Invalid program code ($pg) for this clinic");
            }

            $loc = $clean($meta[$pg]['loc'] ?? null);   // location that matches the program
            $insSched->execute([
                $id, $pg, $dow,
                $st . ':00', $et . ':00',
                $loc,
                $lnk
            ]);

            /* Note: start_time and end_time are stored as HH:MM:SS */
        }
    }



    error_log('SCHEDULE POST = '.print_r($_POST['sched'] ?? [], true));

    db()->commit();
    header("Location: clinic-review.php?id=$id");exit;
}

/* ───────── LOAD (for form) ───────── */
$clinic = run("SELECT * FROM clinic WHERE id=?",[$id])[0] ?? die('bad id');
$sales  = run("SELECT * FROM clinic_sales_profile WHERE clinic_id=?",[$id])[0]??[];
$pay    = run("SELECT * FROM clinic_payment_profile WHERE clinic_id=?",[$id])[0]??[];

$progRows = run(
    "SELECT id, prog_code,
            name,
            in_person_location,
            virtual_platform
     FROM program
     WHERE clinic_id = ?", [$id]);


$progMap  = [];           // for check-boxes (“Programs” section)
$progOpts = [];           // for the schedule <select>
foreach ($progRows as $pr) {
    $progMap[$pr['prog_code']] = $pr;        // existing behaviour
    $progOpts[$pr['prog_code']] = $pr['name'];   // NEW – dynamic dropdown
}


/* ─── fallback for clinics that have zero programs yet ─── */
if (!$progOpts) {
    $progOpts = [
      'bipp_m'=>'BIPP (male)','bipp_f'=>'BIPP (female)',
      'anger' =>'Anger Control','t4c'=>'Thinking for a Change'
    ];
}

/* Onboarding (scoped) rows + computed display category + subcategory */
$scoped = run("
  SELECT c.task_id, t.task_name, t.task_description, t.weight,
         c.program_id, c.template_type, c.data_field,
         COALESCE(c.status,'Pending') AS status,
         COALESCE(c.notes,'') AS notes,
         p.name      AS program_name,
         p.prog_code AS program_code,

         /* ---- display subcategory (section title) ---- */
         CASE
           /* DOCS */
           WHEN t.category='Docs' AND t.task_name='Template: Entrance Notification' THEN 'Entrance Notifications'
           WHEN t.category='Docs' AND t.task_name='Template: Absence' THEN 'Absence Notices'
           WHEN t.category='Docs' AND t.task_name IN ('Template: Progress Report (In-Person)','Template: Progress Report (Virtual)') THEN 'Progress Reports'
           WHEN t.category='Docs' AND t.task_name IN ('Template: Completion Certificate','Template: Completion Letter','Template: Completion Progress (In-Person)','Template: Completion Progress (Virtual)') THEN 'Completion Documents'
           WHEN t.category='Docs' AND t.task_name IN ('Template: Exit Letter','Template: Exit Progress (In-Person)','Template: Exit Progress (Virtual)') THEN 'Exit Notices'
           WHEN t.category='Docs' AND t.task_name IN ('Template: Victim Entrance','Template: Victim Completion','Template: Victim Exit') THEN 'Victim Letters'
           WHEN t.category='Docs' AND t.task_name='Template: Behavior Contract' THEN 'Behavior'
           WHEN t.category='Docs' AND t.task_name='Template: Workbook' THEN 'Workbook'

           /* DATA buckets (names remain as subsections) */
           WHEN t.category='Data' AND c.data_field IN ('first_name','last_name','dob','birth_place','gender','ethnicity') THEN 'Client Identity'
           WHEN t.category='Data' AND c.data_field IN ('phone','email','emergency_contact_name','emergency_contact_relation','emergency_contact_phone') THEN 'Emergency Contact'
           WHEN t.category='Data' AND c.data_field IN ('program','assigned_group','sessions_per_week','required_sessions','orientation_date','attendance_dates') THEN 'Program & Attendance'
           WHEN t.category='Data' AND c.data_field IN ('group_fee','current_balance') THEN 'Financial'
           WHEN t.category='Data' AND c.data_field IN ('referral_type','cause_number') THEN 'Legal'
           WHEN t.category='Data' AND c.data_field IN ('case_manager') THEN 'Case Management'
           WHEN t.category='Data' AND c.data_field IN ('intake_packet_submitted') THEN 'Intake Packet'
           WHEN t.category='Data' AND (c.data_field LIKE 'victim_%' OR c.data_field IN ('victim_name','victim_relationship','victim_gender','victim_age','victim_children_under_18','victim_lives_with_client','victim_address','victim_phone','victim_email'))
                THEN 'Victim Information'
           ELSE 'Other'
         END AS subcat,

         /* ---- display TOP-LEVEL category badge ---- */
         CASE
           WHEN t.category='Docs' AND t.task_name='Template: Workbook' THEN 'Curriculum'
           WHEN t.category='Data' AND (c.data_field LIKE 'victim_%' OR c.data_field IN ('victim_name','victim_relationship','victim_gender','victim_age','victim_children_under_18','victim_lives_with_client','victim_address','victim_phone','victim_email'))
                THEN 'Victim'
           WHEN t.category='Data' AND c.data_field IN ('phone','email','emergency_contact_name','emergency_contact_relation','emergency_contact_phone')
                THEN 'Emergency Contact'
           WHEN t.category='Data' THEN 'Client'
           ELSE t.category
         END AS group_cat

    FROM clinic_onboarding_task c
    JOIN onboarding_task t ON t.id = c.task_id AND t.phase='Onboarding' AND t.is_active=1
    LEFT JOIN program p     ON p.id = c.program_id
   WHERE c.clinic_id = ?
ORDER BY group_cat, subcat, program_name, c.template_type, c.data_field
", [$id]);



$pctSales = sales_pct_complete($id);
$onb      = onboarding_progress_parts($id);
$pctOn    = $onb['pct_complete'];   // if you still need a single number anywhere


/* HTML escaper shortcut */
$h=fn($arr,$k=null)=>htmlspecialchars(is_array($arr)?($arr[$k]??''):$arr,ENT_QUOTES,'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<title>Edit Clinic</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<style>
textarea{white-space:pre}
body{padding-top:56px;font-size:16px}
fieldset{border:1px solid #dee2e6;padding:1rem;margin-bottom:1.5rem}
legend{font-size:1rem;width:auto;padding:0 6px;font-weight:600}
.table-sm td{vertical-align:middle}
</style>
</head>
<?php require 'navbar.php'; ?>
<body>
<section class="pt-4"><div class="container-fluid">

<h2 class="mb-0">Edit Clinic – <?=$h($clinic,'name')?></h2>

<!-- dual progress bars -->
<div class="row my-3">
  <div class="col-md-3"><strong>Sales</strong></div>
  <div class="col-md-9">
    <div class="progress"><div class="progress-bar bg-info" style="width:<?=$pctSales?>%"><?=$pctSales?>%</div></div>
  </div>
</div>
<div class="row mb-4">
  <div class="col-md-3"><strong>On-boarding</strong></div>
  <div class="col-md-9">
    <div class="progress">
      <div class="progress-bar bg-success"
           style="width:<?=$onb['pct_complete']?>%"
           title="Complete: <?=$onb['pct_complete']?>%">
        <?=$onb['pct_complete']?>%
      </div>
      <?php if ($onb['pct_inprog'] > 0): ?>
      <div class="progress-bar bg-warning"
           style="width:<?=$onb['pct_inprog']?>%"
           title="In Progress: <?=$onb['pct_inprog']?>%"></div>
      <?php endif; ?>
    </div>
    <small class="text-muted">
      <?=$onb['pct_complete']?>% complete
      <?php if ($onb['pct_inprog'] > 0): ?> · <?=$onb['pct_inprog']?>% in progress<?php endif; ?>
    </small>
  </div>
</div>


<form method="post">
<input type="hidden" name="id" value="<?=$id?>">

<!-- ===== CLINIC CORE ================================================== -->
<fieldset><legend>Clinic Info</legend>
<div class="form-row">
  <div class="form-group col-md-4"><label>Name *</label>
    <input name="name" class="form-control" value="<?=$h($clinic,'name')?>"></div>
  <div class="form-group col-md-3"><label>Sub-domain</label>
    <input name="subdomain" class="form-control" value="<?=$h($clinic,'subdomain')?>"></div>
  <div class="form-group col-md-2"><label>Status</label>
    <select name="status" class="form-control">
      <?php foreach(['Prospect','Onboarding','Live','Paused'] as $s): ?>
        <option <?=$s===$clinic['status']?'selected':''?>><?=$s?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="form-group col-md-3"><label>Go-Live</label>
    <input type="date" name="go_live_date" class="form-control"
           value="<?=$h($clinic,'go_live_date')?>"></div>
</div>

<h6 class="mt-2">Primary Contact</h6>
<div class="form-row">
  <div class="form-group col-md-4"><label>Name</label>
    <input name="primary_contact_name" class="form-control" value="<?=$h($clinic,'primary_contact_name')?>"></div>
  <div class="form-group col-md-4"><label>Email</label>
    <input name="primary_contact_email" class="form-control" value="<?=$h($clinic,'primary_contact_email')?>"></div>
  <div class="form-group col-md-4"><label>Phone</label>
    <input name="primary_contact_phone" class="form-control" value="<?=$h($clinic,'primary_contact_phone')?>"></div>
</div>
</fieldset>

<div class="row">
  <!-- === SALES PROFILE =============================================== -->
  <div class="col-md-6">
    <fieldset><legend>Sales Profile</legend>
      <?php
        $numberField=function($label,$name)use($sales,$h){
            echo '<div class="form-group"><label>'.$label.'</label>
                   <input type="number" name="'.$name.'" class="form-control"
                          value="'.$h($sales,$name).'"></div>';
        };
      ?>
      <div class="form-group"><label>First Meeting</label>
        <input type="date" name="first_meeting_date" class="form-control"
               value="<?=$h($sales,'first_meeting_date')?>"></div>
      <?=$numberField('Estimated Client Count','estimated_client_count');?>
      <div class="form-row">
        <div class="form-group col-sm-6">
          <label>Admin accounts</label>
          <input type="number" name="admin_account_count"
                class="form-control"
                value="<?=$h($sales,'admin_account_count')?>">
        </div>

        <div class="form-group col-sm-6">
          <label>Facilitator accounts</label>
          <input type="number" name="facilitator_account_count"
                class="form-control"
                value="<?=$h($sales,'facilitator_account_count')?>">
        </div>
      </div>

      <div class="form-group"><label>Price-point</label>
        <select name="pricepoint" class="form-control">
          <?php foreach(['$100','$120','$140','$150','$200'] as $p): ?>
            <option <?=$p===$sales['pricepoint']?'selected':''?>><?=$p?></option>
          <?php endforeach; ?>
        </select></div>

      <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" name="regular_onboarding_fee"
               <?=$sales['regular_onboarding_fee']?'checked':''?>>
        <label class="form-check-label">Regular onboarding fee</label>
      </div>

      <h6>Sales Contact</h6>
      <div class="form-group"><label>Name</label>
        <input name="contact_name" class="form-control" value="<?=$h($sales,'contact_name')?>"></div>
      <div class="form-group"><label>Email</label>
        <input name="contact_email" class="form-control" value="<?=$h($sales,'contact_email')?>"></div>
      <div class="form-group"><label>Phone</label>
        <input name="contact_phone" class="form-control" value="<?=$h($sales,'contact_phone')?>"></div>
    </fieldset>

    <fieldset><legend>Clinic Logo</legend>
      <?php $logo = get_clinic_logo($id); ?>
      <div id="logoDrop" class="border rounded text-center p-4"
          style="cursor:pointer;background:#fafafa">
          <?php if ($logo): ?>
              <img src="<?=$h($logo,'file_path')?>" class="img-fluid mb-2"
                  style="max-height:120px">
              
              <p class="text-primary">Click or drag to replace</p>
          <?php else: ?>
              <i class="fas fa-upload fa-2x text-muted mb-2"></i>
              <p>Click or drag a logo here</p>
          <?php endif; ?>
      </div>
      <input type="file" id="logoInput"
              accept="image/png,image/jpeg,image/gif,image/svg+xml"
              style="display:none">

    </fieldset>

  </div>
  


  <!-- === PAYMENT PROFILE ============================================= -->
  <div class="col-md-6">
    <fieldset><legend>Payment Profile</legend>
      <div class="form-group"><label>Method</label>
        <select name="method" class="form-control">
          <?php foreach(['Stripe','Square','WooCommerce','Cash','Check','Other'] as $m): ?>
            <option <?=$m===($pay['method']??'Cash')?'selected':''?>><?=$m?></option>
          <?php endforeach; ?>
        </select></div>

      <?php
        $cb=function($label,$name)use($pay){
            echo '<div class="form-check">
                    <input type="checkbox" class="form-check-input" name="'.$name.'"'
                    .(($pay[$name]??0)?' checked':'').'>
                    <label class="form-check-label">'.$label.'</label></div>';
        };
        $cb('Accepts partial','accepts_partial');
        $cb('Used by facilitators','used_by_facilitators');
        $cb('Opt-in NotesAO Processor','notesao_processor_opt_in');
      ?>

      <div class="form-group mt-2"><label>Additional Details</label>
        <textarea name="additional_details" rows="3" class="form-control"><?=$h($pay,'additional_details')?></textarea></div>
    </fieldset>

    <!-- === PROGRAMS =================================================== -->
    <fieldset><legend>Programs</legend>

    <?php  /* we’re IN PHP here (file is .php), keep a label map for later */ 
           $labels = $progOpts;  ?>

    <!-- leave PHP ⇒ start pure HTML -->
    <table class="table table-sm mb-2">
      <thead class="thead-light">
        <tr>
          <th style="width:20%">Enable</th>
          <th>Name</th>
          <th style="width:30%">Location</th>
          <th style="width:30%">Virtual provider</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($progOpts as $code => $label):
              $row = $progMap[$code] ?? []; ?>
        <tr>
          <td class="text-center">
            <input type="checkbox"
                   name="programs[]" value="<?= $code ?>"
                   <?= isset($progMap[$code]) ? 'checked' : '' ?>>
          </td>
          <td><?= htmlspecialchars($label) ?></td>
          <td>
            <input name="prog_meta[<?= $code ?>][loc]"
                   class="form-control form-control-sm"
                   value="<?= htmlspecialchars($row['in_person_location'] ?? '') ?>">
          </td>
          <td>
            <input name="prog_meta[<?= $code ?>][virt]"
                   class="form-control form-control-sm"
                   value="<?= htmlspecialchars($row['virtual_platform'] ?? '') ?>">
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php  /* ← PHP resumes here for “Other” block */ ?>
        <?php $other = $progMap['other'] ?? []; ?>
        <div class="form-check mt-2">
          <input type="checkbox" class="form-check-input"
                 name="program_other_enabled" id="p_other"
                 <?= $other ? 'checked' : '' ?>>
          <label class="form-check-label font-weight-bold" for="p_other">Other</label>
          <input name="program_other_name"
                 class="form-control form-control-sm d-inline-block ml-2"
                 style="width:60%"
                 value="<?= $h($other,'name') ?>"
                 placeholder="Program name(s)">
        </div>
    </fieldset>




    <!-- ========== GROUP SCHEDULE (per-row editor) ======================= -->
    <fieldset><legend>Group Schedule</legend>

    <table class="table table-sm" id="schedTbl">
      <thead class="thead-light">
        <tr>
          <th style="width:15%">Day</th>
          <th style="width:15%">Start</th>
          <th style="width:15%">End</th>
          <th style="width:26%">Program</th>   <!-- NEW -->
          <th style="width:15%">Link (opt.)</th>
          <th style="width:9%"></th>
        </tr>
      </thead>

      <tbody>
      <?php
        /* existing rows from DB */
        $schedRows = run("SELECT * FROM clinic_weekly_schedule
                          WHERE clinic_id=? ORDER BY FIELD(day_of_week,
                          'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), start_time", [$id]);

        $idx = 0;
        /* programs label map ------------------------------------------ */
        $labels = $progOpts;              // dynamic - uses the DB list you built earlier



        /* ───── schedule-row printer ───── */
        $printRow = function(array $r, $i) use ($h, $labels)
        {
            $dow = $r['day_of_week']??'';
            $st  = substr($r['start_time']??'',0,5);
            $et  = substr($r['end_time']??'',0,5);
            $lnk = $r['perm_link']??'';
            $pg  = $r['prog_code']??'';

            echo '<tr>
              <td>
                <select name="sched['.$i.'][day]" class="form-control form-control-sm">
                  <option value="">—</option>';
                  foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)
                      echo '<option '.($d===$dow?'selected':'').'>'.$d.'</option>';
            echo ' </select>
              </td>

              <td><input type="time" name="sched['.$i.'][start]" value="'.$st.'" class="form-control form-control-sm"></td>
              <td><input type="time" name="sched['.$i.'][end]"   value="'.$et.'" class="form-control form-control-sm"></td>

              <td>
                <select name="sched['.$i.'][prog]" class="form-control form-control-sm">
                  <option value="">—</option>';
                  foreach ($labels as $code=>$label)
                      echo '<option value="'.$code.'"'.($code===$pg?' selected':'').'>'.$label.'</option>';
            echo ' </select>
              </td>

              <td><input type="text" name="sched['.$i.'][link]" value="'.$h($lnk).'" class="form-control form-control-sm"></td>

              <td class="text-center">
                <button type="button" class="btn btn-sm btn-danger delRow">&times;</button>
              </td>
            </tr>';
        };

        /* ---- JS template for new rows (capture into a string) ---- */
        ob_start();
        $printRow([], 'IDX');      // “IDX” will be replaced client-side
        $rowTmpl = trim(ob_get_clean());
        foreach ($schedRows as $r)  $printRow($r,$idx++);
        /* one empty row if table is blank */
        if (!$idx) $printRow([],0);
      ?>
      </tbody>
    </table>

    <button type="button" class="btn btn-sm btn-outline-secondary" id="addRow">Add&nbsp;Row</button>
    </fieldset>

    <script>
    /* quick & dirty vanilla-JS row adder / deleter */
    document.getElementById('addRow').onclick = () => {
      const tbl = document.querySelector('#schedTbl tbody');
      const i   = tbl.rows.length;
      const tmpl = `<?=str_replace("\n",'', addslashes($rowTmpl));?>`.replace(/IDX/g,i);
      tbl.insertAdjacentHTML('beforeend', tmpl);
    };

    document.addEventListener('click', e => {
      if (e.target.classList.contains('delRow')) {
          e.target.closest('tr').remove();
      }
    });
    </script>

  </div>
</div><!-- /row -->

<!-- === ON-BOARDING CHECKLIST (grouped) ================================ -->
<h4 class="mt-4">On-Boarding Checklist</h4>

<?php
/* group by TopCategory → Subcategory */
$groups = [];  // key = "TopCat|Subcat"
foreach ($scoped as $row) {
    $key = $row['group_cat'].'|'.$row['subcat'];   // ← was $row['category']
    $groups[$key][] = $row;
}
ksort($groups);


$makeId = function($category, $subcat) {
    return preg_replace('/[^a-z0-9]+/i','-', strtolower($category.'-'.$subcat));
};
?>

<?php $i = 0; ?>
<div id="onb-accordion">
<?php foreach ($groups as $key => $rows):
    list($cat, $sub) = explode('|', $key, 2);
    $gid = $makeId($cat,$sub);
    $count = count($rows);
?>
  <div class="card mb-2">
    <div class="card-header p-2" id="h-<?=$gid?>">
      <h6 class="mb-0">
        <button class="btn btn-link p-0" type="button" data-toggle="collapse" data-target="#c-<?=$gid?>" aria-expanded="false" aria-controls="c-<?=$gid?>">
          <span class="badge badge-secondary mr-2"><?=$cat?></span> <?=$sub?> <span class="text-muted">(<?=$count?>)</span>
        </button>
      </h6>
    </div>

    <div id="c-<?=$gid?>" class="collapse" aria-labelledby="h-<?=$gid?>" data-parent="#onb-accordion">
      <div class="card-body p-2">
        <table class="table table-sm table-bordered mb-0">
          <thead class="thead-light">
            <tr>
              <th style="width:26%">Task</th>
              <th style="width:18%">Program</th>
              <th style="width:18%">Scope</th>
              <th style="width:16%">Status</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
          <?php
                $scopeLabel=function($r){
                    if (!empty($r['template_type'])) return $r['template_type'];
                    if (!empty($r['data_field']))    return 'Field: '.$r['data_field'];
                    return '—';
                };
                foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['task_description']) ?></td>
              <td><?= htmlspecialchars($r['program_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($scopeLabel($r)) ?></td>
              <td>
                <input type="hidden" name="sc[<?=$i?>][task_id]"       value="<?= (int)$r['task_id'] ?>">
                <input type="hidden" name="sc[<?=$i?>][program_id]"    value="<?= $r['program_id'] === null ? '' : (int)$r['program_id'] ?>">
                <input type="hidden" name="sc[<?=$i?>][template_type]" value="<?= htmlspecialchars($r['template_type'] ?? '') ?>">
                <input type="hidden" name="sc[<?=$i?>][data_field]"    value="<?= htmlspecialchars($r['data_field'] ?? '') ?>">
                <input type="hidden" name="sc[<?=$i?>][prog_code]"     value="<?= htmlspecialchars($r['program_code'] ?? '') ?>">


                <select name="sc[<?=$i?>][status]" class="form-control form-control-sm">
                  <?php foreach (['Pending','In Progress','Complete','N/A'] as $s): ?>
                    <option <?= $s === $r['status'] ? 'selected' : '' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input name="sc[<?=$i?>][notes]" value="<?=$h($r,'notes')?>" class="form-control form-control-sm"></td>
            </tr>
          <?php $i++; endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>



<button class="btn btn-primary">Save Changes</button>
<a href="clinic-review.php?id=<?=$id?>" class="btn btn-secondary ml-2">Cancel</a>

</form>
</div></section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

<script>
(() => {
    const drop   = document.getElementById('logoDrop');
    const input  = document.getElementById('logoInput');
    const clinic = <?= (int)$id ?>;                  // from PHP

    /* click = open file dialog */
    drop.addEventListener('click', () => input.click());

    /* drag-over styling */
    ['dragenter','dragover'].forEach(evt =>
        drop.addEventListener(evt, e => {
            e.preventDefault(); e.dataTransfer.dropEffect = 'copy';
            drop.classList.add('bg-light');
        }));
    ['dragleave','drop'].forEach(evt =>
        drop.addEventListener(evt, () => drop.classList.remove('bg-light')));

    /* drop handler */
    drop.addEventListener('drop', e => {
        e.preventDefault();
        if (e.dataTransfer.files[0]) uploadLogo(e.dataTransfer.files[0]);
    });

    /* file-input handler */
    input.addEventListener('change', () => {
        if (input.files[0]) uploadLogo(input.files[0]);
    });

    function uploadLogo(file) {
        if (!file.type.match(/^image\//)) { alert('Please choose an image'); return; }

        const fd = new FormData();
        fd.append('logo',      file);
        fd.append('clinic_id', clinic);

        fetch('upload_logo.php', {method:'POST', body:fd})
          .then(r => r.text())
          .then(txt => {
              let res;
              try     { res = JSON.parse(txt); }
              catch(e){ throw 'Server returned invalid JSON:\n' + txt; }
              if (!res.ok) throw res.error || 'Upload failed';
              // success → show thumbnail etc.
          })
          .catch(alert);

    }
})();
</script>

</body>
</html>
