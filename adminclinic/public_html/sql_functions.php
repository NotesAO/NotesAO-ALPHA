<?php
/**********************************************************************
 *  SQL helper-library  (admin-clinic + tenant clinics)
 *  Uses a single PDO connection but can be called from mysqli pages
 *********************************************************************/
require_once __DIR__ . '/../config/config.php';   // db_host, db_name, db_user, db_pass

/* ─────────── tiny PDO convenience ─────────── */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $pdo = new PDO(
        'mysql:host=' . db_host . ';dbname=' . db_name . ';charset=utf8mb4',
        db_user,
        db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}

/**
 * run( $sql , [$param, …] )
 *  • SELECT  → returns rows
 *  • INSERT / UPDATE / DELETE → returns true
 */
function run(string $sql, array $a = []) {
    $st = db()->prepare($sql);
    $st->execute($a);
    return str_starts_with(ltrim($sql), 'SELECT') ? $st->fetchAll() : true;
}

/* ------------------------------------------------------------------ */
/*  LOOK-UP lists used by <select> dropdowns                          */
/* ------------------------------------------------------------------ */
function get_programs()       { return run("SELECT id,name FROM program ORDER BY name"); }
function get_genders()        { return run("SELECT id,gender FROM gender ORDER BY id"); }
function get_ethnicities()    { return run("SELECT id,code,name FROM ethnicity ORDER BY code"); }
function get_referral_types() { return run("SELECT id,referral_type FROM referral_type ORDER BY id"); }
function get_exit_reasons()   { return run("SELECT id,reason FROM exit_reason ORDER BY id"); }
function get_case_managers()  { return run("SELECT id,first_name,last_name,office FROM case_manager ORDER BY last_name,first_name"); }
function get_client_stages()  { return run("SELECT id,stage FROM client_stage ORDER BY id"); }

/* Active therapy-groups → id + label “Name – Address” (address optional) */
function get_active_therapy_groups() {
    return run("
        SELECT id,
               CASE WHEN COALESCE(address,'')='' THEN name
                    ELSE CONCAT(name,' – ',address) END AS label
          FROM therapy_group
      ORDER  BY name");
}

/* get_therapy_groups([ $program_id ]) – used by create/update pages */
function get_therapy_groups(int $program_id = null) {
    if ($program_id === null) {
        return run("
            SELECT id,
                   CASE WHEN COALESCE(address,'')='' THEN name
                        ELSE CONCAT(name,' – ',address) END AS label
              FROM therapy_group
          ORDER  BY name");
    }
    return run("
        SELECT id,
               CASE WHEN COALESCE(address,'')='' THEN name
                    ELSE CONCAT(name,' – ',address) END AS label
          FROM therapy_group
         WHERE program_id = ?
      ORDER  BY name", [$program_id]);
}

/* ------------------------------------------------------------------ */
/*  Onboarding helpers (admin-clinic)                                 */
/* ------------------------------------------------------------------ */
function get_onboarding_tasks(int $clinic_id) {
    return run("
        SELECT t.id, t.phase, t.category, t.task_description, t.weight,
               COALESCE(c.status,'Pending') AS status,
               c.notes
          FROM onboarding_task t
     LEFT JOIN clinic_onboarding_task c
            ON c.clinic_id = ? AND c.task_id = t.id
         WHERE t.is_active = 1
      ORDER  BY FIELD(t.phase,'Sales','Client Data','Templates'),
               t.phase, t.category, t.id", [$clinic_id]);
}

function get_onboarding_progress(int $clinic_id) {
    $rows  = get_onboarding_tasks($clinic_id);
    $done  = 0;
    $total = 0;
    foreach ($rows as $r) {
        $total += $r['weight'];
        if ($r['status'] === 'Complete') $done += $r['weight'];
    }
    $pct = $total ? round($done / $total * 100) : 0;
    return ['done' => $done, 'total' => $total, 'percent' => $pct];
}

/* ------------------------------------------------------------------ */
/*  Clinic-level helpers (admin-clinic)                               */
/* ------------------------------------------------------------------ */
function get_clinic_info(int $clinic_id) {
    $rows = run("
        SELECT id, code, name, subdomain, status,
               created_at, go_live_date,
               primary_contact_name, primary_contact_email, primary_contact_phone
          FROM clinic
         WHERE id = ?", [$clinic_id]);
    return $rows[0] ?? null;
}

function get_clinic_programs(int $clinic_id) {
    return run("
        SELECT id, name, is_virtual, in_person_location
          FROM program
         WHERE clinic_id = ?
      ORDER  BY name", [$clinic_id]);
}

function get_clinic_schedule(int $clinic_id) {
    return run("
        SELECT p.name AS program,
               cgs.day_of_week,
               TIME_FORMAT(cgs.start_time,'%h:%i %p') AS start_time,
               TIME_FORMAT(cgs.end_time  ,'%h:%i %p') AS end_time,
               cgs.location,
               cgs.perm_link
          FROM clinic_group_schedule cgs
          JOIN program p ON p.id = cgs.program_id
         WHERE p.clinic_id = ?
      ORDER  BY p.name,
               FIELD(cgs.day_of_week,
                     'Sunday','Monday','Tuesday','Wednesday',
                     'Thursday','Friday','Saturday'),
               cgs.start_time", [$clinic_id]);
}

/* --------------------------------------------------
 *  Clinic logo helper – returns NULL if none exists
 * --------------------------------------------------*/
function get_clinic_logo(int $clinic_id): ?array
{
    $row = run("
        SELECT file_name,
               CONCAT('/uploads/clinic_logos/',clinic_id,'/',file_name) AS file_path
          FROM clinic_logo
         WHERE clinic_id = ?
         LIMIT 1", [$clinic_id]);

    return $row[0] ?? null;     // null = no logo yet
}



function save_clinic_logo(int $clinic_id,
                          string $fileName,
                          string $filePath,
                          string $mimeType,
                          int $fileSize,
                          ?int $uploadedBy = null): void
{
    run("INSERT INTO clinic_logo
            (clinic_id, file_name, file_path, mime_type, file_size, uploaded_by)
          VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            file_name   = VALUES(file_name),
            file_path   = VALUES(file_path),
            mime_type   = VALUES(mime_type),
            file_size   = VALUES(file_size),
            uploaded_at = NOW(),
            uploaded_by = VALUES(uploaded_by)",
        [$clinic_id, $fileName, $filePath, $mimeType, $fileSize, $uploadedBy]);
}


/**
 * Calculate how “complete” the Sales portion of onboarding is
 * for a given clinic. 0 = nothing, 100 = fully complete.
 * Update the weights / rules below any time the schema changes.
 */
function sales_pct_complete(int $clinicId): int
{
    // Convenient helper
    $firstRow = function (string $sql, array $p = []) {
        return run($sql, $p)[0] ?? [];
    };

    /* ---------- clinic core ---------- */
    $coreFields = [
        'name','subdomain','status','go_live_date',
        'primary_contact_name','primary_contact_email','primary_contact_phone'
    ];
    $core = $firstRow(
        "SELECT " . implode(',', $coreFields) . " FROM clinic WHERE id=?",
        [$clinicId]
    );

    /* ---------- sales profile ---------- */
    $salesFields = [
        'first_meeting_date','estimated_client_count',
        'admin_account_count','facilitator_account_count',
        'pricepoint','regular_onboarding_fee',
        'contact_name','contact_email','contact_phone'
    ];
    $sales = $firstRow(
        "SELECT " . implode(',', $salesFields) .
        " FROM clinic_sales_profile WHERE clinic_id=?",
        [$clinicId]
    );

    /* ---------- payment profile ---------- */
    // “additional_details” is intentionally **NOT** listed here (optional)
    $payFields = [
        'method','accepts_partial',
        'used_by_facilitators','notesao_processor_opt_in'
    ];
    $pay = $firstRow(
        "SELECT " . implode(',', $payFields) .
        " FROM clinic_payment_profile WHERE clinic_id=?",
        [$clinicId]
    );

    /* ---------- programmes ---------- */
    $programs = run(
        "SELECT prog_code, name,
                in_person_location, virtual_platform
         FROM program WHERE clinic_id=?", [$clinicId]
    );

    /* ---------- schedule (any row?) ---------- */
    $hasSchedule = run(
        "SELECT 1 FROM clinic_weekly_schedule
          WHERE clinic_id=? LIMIT 1", [$clinicId]
    );

    /* ---------- tally everything ---------- */
    $possible = count($coreFields) + count($salesFields) + count($payFields);
    $filled   = 0;

    // helper → always return *some* string, never null
    $s = static fn($v) => $v === null ? '' : (string)$v;

    // core tables
    foreach ($coreFields as $f)  if (trim($core [$f] ?? '') !== '') $filled++;
    foreach ($salesFields as $f) if (trim($sales[$f] ?? '') !== '') $filled++;
    foreach ($payFields  as $f)  if (trim($pay  [$f] ?? '') !== '') $filled++;

    // programme checks – 2 pts each
    foreach ($programs as $p) {
        if (trim($s($p['name'])) !== '')                      $filled++; // name
        if (trim($s($p['in_person_location'])) !== '' ||
            trim($s($p['virtual_platform']))  !== '')         $filled++; // location OR virtual
        $possible += 2;
    }

    // schedule (single point)
    $possible += 1;
    if ($hasSchedule) $filled++;

    /* ---------- percentage ---------- */            // safety – should never happen
    return $possible ? (int)floor($filled / $possible * 100) : 0;
}

/* Onboarding % complete (Complete=100%, In Progress=50%, Pending=0, N/A excluded) */
function onboarding_pct_complete(int $clinic_id): int {
    $row = run("
        SELECT
          SUM(CASE WHEN c.status <> 'N/A' THEN t.weight ELSE 0 END) AS denom,
          SUM(CASE
                WHEN c.status = 'Complete'    THEN t.weight
                WHEN c.status = 'In Progress' THEN t.weight * 0.5
                ELSE 0
              END) AS num
          FROM clinic_onboarding_task c
          JOIN onboarding_task t
            ON t.id = c.task_id
           AND t.phase = 'Onboarding'
           AND t.is_active = 1
         WHERE c.clinic_id = ?
    ", [$clinic_id])[0] ?? ['denom'=>0,'num'=>0];

    $den = (float)($row['denom'] ?? 0);
    $num = (float)($row['num']  ?? 0);
    return $den > 0 ? (int)round($num / $den * 100) : 0;
}

/* Onboarding breakdown (weights): complete + in-progress, N/A excluded) */
function onboarding_progress_parts(int $clinic_id): array {
    $row = run("
        SELECT
          SUM(CASE WHEN c.status <> 'N/A'          THEN COALESCE(t.weight,1) ELSE 0 END) AS denom,
          SUM(CASE WHEN c.status  = 'Complete'     THEN COALESCE(t.weight,1) ELSE 0 END) AS done,
          SUM(CASE WHEN c.status  = 'In Progress'  THEN COALESCE(t.weight,1) ELSE 0 END) AS doing
        FROM clinic_onboarding_task c
        JOIN onboarding_task t ON t.id = c.task_id
       WHERE t.phase='Onboarding'
         AND t.is_active = 1
         AND c.clinic_id = ?
    ", [$clinic_id])[0] ?? ['denom'=>0,'done'=>0,'doing'=>0];

    $den   = (float)($row['denom'] ?? 0);
    $done  = (float)($row['done']  ?? 0);
    $doing = (float)($row['doing'] ?? 0);

    $pctDone  = $den > 0 ? ($done  / $den * 100.0) : 0.0;
    $pctDoing = $den > 0 ? ($doing / $den * 100.0) : 0.0;

    // round to 1 decimal; cap stacked bars at 100
    $pctDoneR  = round($pctDone,  1);
    $pctDoingR = round($pctDoing, 1);
    if ($pctDoneR + $pctDoingR > 100) {
        $pctDoingR = max(0, 100 - $pctDoneR);
    }

    return [
        'den'           => $den,
        'done'          => $done,
        'doing'         => $doing,
        'pct_complete'  => $pctDoneR,                // green piece
        'pct_inprog'    => $pctDoingR,               // yellow piece
        'pct_remaining' => max(0, round(100 - $pctDoneR - $pctDoingR, 1)),
    ];
}



?>