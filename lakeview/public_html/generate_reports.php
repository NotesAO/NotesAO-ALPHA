<?php
include '/home/notesao/lakeview/public_html/auth.php';
check_loggedin($con, 'https://notesao.com/login.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// -------------------------------------------------------------------
// 1) Define Variables
// -------------------------------------------------------------------
$clinic_folder = 'lakeview';
$base_dir = '/home/notesao/NotePro-Report-Generator';

// I/O roots
$generated_documents_dir        = "$base_dir/GeneratedDocuments/$clinic_folder";
$public_generated_documents_dir = "/home/notesao/$clinic_folder/public_html/GeneratedDocuments";
$csv_dir                        = "$base_dir/csv/$clinic_folder";
$tasks_dir                      = "$base_dir/tasks";
$python_interpreter             = "$base_dir/venv311/bin/python3";
$templates_dir                  = "$base_dir/templates/$clinic_folder";

// -------------------------------------------------------------------
// 2) Ensure directories exist
// -------------------------------------------------------------------
$directories = [
    $csv_dir,
    $generated_documents_dir,
    $tasks_dir,
    $public_generated_documents_dir
];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            echo json_encode(['status' => 'error', 'message' => "Failed to create directory: $dir"]);
            exit;
        }
    }
}

// -------------------------------------------------------------------
// 3) Helpers
// -------------------------------------------------------------------
function convert_csv_to_utf8($file_path) {
    $temp_file = tempnam(sys_get_temp_dir(), 'csv_utf8_');
    $out = fopen($temp_file, 'w');
    if (($in = fopen($file_path, 'r')) !== false) {
        while (($row = fgetcsv($in)) !== false) {
            $converted = array_map(function ($cell) {
                $enc = mb_detect_encoding($cell, ['UTF-8','ISO-8859-1','WINDOWS-1252'], true);
                if ($enc) return mb_convert_encoding($cell, 'UTF-8', $enc);
                return preg_replace('#[^A-Za-z0-9\s\.,!?;:\'\"()\-_/]#u', '', $cell);
            }, $row);
            fputcsv($out, $converted);
        }
        fclose($in);
    }
    fclose($out);
    return $temp_file;
}

function filter_csv_by_client_id($csv_path, $client_id) {
    $tmp = tempnam(sys_get_temp_dir(), 'filtered_');
    $in  = fopen($csv_path, 'r');
    $out = fopen($tmp, 'w');
    if (!$in || !$out) return $csv_path;

    $header = fgetcsv($in);
    if (!$header) { fclose($in); fclose($out); return $csv_path; }
    fputcsv($out, $header);

    $idx = array_search('client_id', $header);
    if ($idx === false) { fclose($in); fclose($out); return $csv_path; }

    while (($row = fgetcsv($in)) !== false) {
        if (isset($row[$idx]) && $row[$idx] == $client_id) fputcsv($out, $row);
    }
    fclose($in); fclose($out);
    return $tmp;
}

/**
 * Find today's CSV for Report 5.
 * Supports:
 *  - {ClinicCap}_report5_{YYYYMMDD}.csv   e.g. Lakeview_report5_20251104.csv
 *  - {clinic}_report5_{YYYYMMDD}.csv      e.g. lakeview_report5_20251104.csv
 *  - report5_dump_{YYYYMMDD}.csv          legacy
 */
function find_today_csv($csv_dir, $clinic_folder, $today_ymd) {
    $cCap = ucfirst($clinic_folder);
    $cLow = strtolower($clinic_folder);

    $candidates = [
        "$csv_dir/{$cCap}_report5_{$today_ymd}.csv",
        "$csv_dir/{$cLow}_report5_{$today_ymd}.csv",
        "$csv_dir/report5_dump_{$today_ymd}.csv",
    ];
    foreach ($candidates as $p) {
        if (file_exists($p)) return $p;
    }
    // As a last resort, pick the most recent report5 CSV
    $glob = glob("$csv_dir/*report5*.csv");
    if ($glob) {
        rsort($glob);
        return $glob[0];
    }
    return null;
}

// Map program name → short code prefix used in filenames
function program_prefix($program_name) {
    static $map = [
        'BIPP (male)'             => 'BIPP',
        'BIPP (female)'           => 'BIPP',
        'Thinking for a Change'   => 'T4C',
        'Anger Management'        => 'AC',      // existing scripts use AC_*
        'Life Skills/Anti Theft'  => 'LSAT',    // LSAT_*
        'Parenting Education'     => 'PARENTING',
        'DOEP'                    => 'DOEP',
        'DWIE'                    => 'DWIE',
        'DWII'                    => 'DWII',
        'Marijuana Education'     => 'MJED',    // adjust if your scripts differ
        'Marijuana Intervention'  => 'MJINT',   // adjust if your scripts differ
        'SAE'                     => 'SAE',
    ];
    return $map[$program_name] ?? null;
}

// For each program, enumerate allowed report labels
function allowed_reports_for($program_name) {
    // Canonical labels
    $labels = [
        'Completion Documents',
        'Enrollment Letters',
        'Entrance Notifications',
        'Exit Notices',
        'Unexcused Absences',
        'Progress Reports (Stage of Change)',
        'Progress Reports (Curriculum)',
        'Behavior Contracts',
        'Victim Letters',
    ];

    switch ($program_name) {
        case 'BIPP (male)':
        case 'BIPP (female)':
            return [
                $labels[0], // Completion Documents
                $labels[1], // Enrollment Letters
                $labels[2], // Entrance Notifications
                $labels[3], // Exit Notices
                $labels[4], // Unexcused Absences
                $labels[5], // SOC progress
                $labels[6], // CUR progress
                $labels[7], // Behavior Contracts
                $labels[8], // Victim Letters
            ];
        case 'Thinking for a Change':
            return [
                $labels[0], $labels[2], $labels[3], $labels[4],
                $labels[5], $labels[6], // both use same T4C script
            ];
        case 'Anger Management':
            return [
                $labels[0], $labels[2], $labels[3], $labels[4],
                $labels[5], $labels[6], // both use same AC script
            ];
        // For the remaining programs, start with the core four. Expand later as scripts exist.
        case 'DOEP':
        case 'DWIE':
        case 'DWII':
        case 'Parenting Education':
        case 'Life Skills/Anti Theft':
        case 'Marijuana Education':
        case 'Marijuana Intervention':
        case 'SAE':
            return [
                $labels[0], // Completion Documents
                $labels[2], // Entrance Notifications
                $labels[3], // Exit Notices
                $labels[4], // Unexcused Absences
            ];
        default:
            return [];
    }
}

// Resolve script path per program + report
function resolve_script($base_dir, $program_name, $report_type) {
    $prefix = program_prefix($program_name);
    if (!$prefix) return null;

    // Per-program overrides
    $overrides = [
        'BIPP (male)' => [
            'Progress Reports (Stage of Change)' => 'BIPP_SOC_Progress_Reports_Script.py',
            'Progress Reports (Curriculum)'      => 'BIPP_CUR_Progress_Reports_Script.py',
            'Behavior Contracts'                 => 'BIPP_Behavior_Contracts_Script.py',
            'Victim Letters'                     => 'BIPP_Victim_Letters_Script.py',
            'Enrollment Letters'                 => 'BIPP_Enrollment_Letters_Script.py',
        ],
        'BIPP (female)' => [
            'Progress Reports (Stage of Change)' => 'BIPP_SOC_Progress_Reports_Script.py',
            'Progress Reports (Curriculum)'      => 'BIPP_CUR_Progress_Reports_Script.py',
            'Behavior Contracts'                 => 'BIPP_Behavior_Contracts_Script.py',
            'Victim Letters'                     => 'BIPP_Victim_Letters_Script.py',
            'Enrollment Letters'                 => 'BIPP_Enrollment_Letters_Script.py',
        ],
        'Thinking for a Change' => [
            'Progress Reports (Stage of Change)' => 'T4C_Progress_Reports_Script.py',
            'Progress Reports (Curriculum)'      => 'T4C_Progress_Reports_Script.py',
        ],
        'Anger Management' => [
            'Progress Reports (Stage of Change)' => 'AC_Progress_Reports_Script.py',
            'Progress Reports (Curriculum)'      => 'AC_Progress_Reports_Script.py',
        ],
    ];
    if (isset($overrides[$program_name][$report_type])) {
        return "$base_dir/" . $overrides[$program_name][$report_type];
    }

    // Generic suffixes
    $suffix_map = [
        'Completion Documents'                => 'Completion_Documents_Script.py',
        'Enrollment Letters'                  => 'Enrollment_Letters_Script.py',
        'Entrance Notifications'              => 'Entrance_Notifications_Script.py',
        'Exit Notices'                        => 'Exit_Notices_Script.py',
        'Unexcused Absences'                  => 'Unexcused_Absences_Script.py',
        'Progress Reports (Stage of Change)'  => 'Progress_Reports_Script.py',     // only used if not overridden
        'Progress Reports (Curriculum)'       => 'Progress_Reports_Script.py',     // only used if not overridden
        'Behavior Contracts'                  => 'Behavior_Contracts_Script.py',
        'Victim Letters'                      => 'Victim_Letters_Script.py',
    ];

    if (!isset($suffix_map[$report_type])) return null;
    return "$base_dir/{$prefix}_{$suffix_map[$report_type]}";
}

// -------------------------------------------------------------------
// 4) POST only
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Use POST.']);
    exit;
}

// -------------------------------------------------------------------
// 4A) download_zip action
// -------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'download_zip') {
    if (!isset($_POST['date'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing date for download_zip.']);
        exit;
    }
    $today_date_formatted = $_POST['date'];
    $clinic_specific_dir = "$public_generated_documents_dir/$today_date_formatted";

    if (!is_dir($clinic_specific_dir)) {
        echo json_encode(['status' => 'error', 'message' => "No documents found for date ($today_date_formatted)."]);
        exit;
    }

    $zip_filename = "Generated_Reports_$today_date_formatted.zip";
    $zip_path = "$public_generated_documents_dir/$zip_filename";

    if (!is_dir($public_generated_documents_dir) && !mkdir($public_generated_documents_dir, 0755, true)) {
        echo json_encode(['status' => 'error', 'message' => "Failed to create directory: $public_generated_documents_dir"]);
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo json_encode(['status' => 'error', 'message' => "Failed to create ZIP at $zip_path"]);
        exit;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($clinic_specific_dir));
    foreach ($files as $file) {
        if ($file->isDir()) continue;
        $rel = substr($file->getRealPath(), strlen($clinic_specific_dir) + 1);
        if (file_exists($file->getRealPath())) $zip->addFile($file->getRealPath(), $rel);
    }
    if (!$zip->close()) {
        echo json_encode(['status' => 'error', 'message' => "Failed to finalize ZIP at $zip_path"]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'ZIP ready.',
        'download_url' => "/GeneratedDocuments/$zip_filename"
    ]);
    exit;
}

// -------------------------------------------------------------------
// 5) Handle generation request
// -------------------------------------------------------------------
$program     = isset($_POST['program'])     ? trim($_POST['program'])     : '';
$reports     = isset($_POST['reports'])     ? (array)$_POST['reports']    : [];
$start_date  = isset($_POST['start_date'])  ? trim($_POST['start_date'])  : null;
$end_date    = isset($_POST['end_date'])    ? trim($_POST['end_date'])    : null;
$client_name = isset($_POST['client_name']) ? trim($_POST['client_name']) : '';
$client_id   = null;

// DEBUG: log exactly what was posted (remove after testing)
error_log('generate_reports POST=' . json_encode($_POST, JSON_UNESCAPED_UNICODE));

// Resolve program to canonical name
$program_id_posted   = trim($_POST['program'] ?? '');
$program_name_posted = trim($_POST['program_name'] ?? '');

if ($program_name_posted !== '') {
    // Frontend already sent the name
    $program = $program_name_posted;
} elseif ($program_id_posted !== '' && ctype_digit($program_id_posted)) {
    // Map numeric id → name
    $pid  = (int)$program_id_posted;
    $stmt = $con->prepare('SELECT name FROM program WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $program = $row['name']; // use canonical name downstream
    } else {
        echo json_encode(['status'=>'error','message'=>'Invalid or missing program.']);
        exit;
    }
    $stmt->close();
}


// Validate program against DB so new programs auto-apply
$valid_programs = [];
if ($res = $con->query("SELECT name FROM program")) {
    while ($row = $res->fetch_assoc()) $valid_programs[] = $row['name'];
    $res->free();
}

if ($program === '' || !in_array($program, $valid_programs, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing program.']);
    exit;
}

// Validate report selections per program
$allowed = allowed_reports_for($program);
if (empty($reports)) {
    echo json_encode(['status' => 'error', 'message' => 'Select at least one report.']);
    exit;
}
foreach ($reports as $r) {
    if (!in_array($r, $allowed, true)) {
        echo json_encode(['status' => 'error', 'message' => "Report not allowed for $program: $r"]);
        exit;
    }
}

// -------------------------------------------------------------------
// 6) Locate CSV
// -------------------------------------------------------------------
$today_ymd = date('Ymd');
$csv_file_path = find_today_csv($csv_dir, $clinic_folder, $today_ymd);
if (!$csv_file_path || !file_exists($csv_file_path)) {
    echo json_encode([
        'status' => 'error',
        'message' => "CSV file not found for today ($today_ymd). Expected one of: "
            . "Lakeview_report5_$today_ymd.csv, lakeview_report5_$today_ymd.csv, report5_dump_$today_ymd.csv."
    ]);
    exit;
}

$converted_csv_path = convert_csv_to_utf8($csv_file_path);

// -------------------------------------------------------------------
// 7) Optional: resolve client_id and filter CSV
// -------------------------------------------------------------------
if ($client_name !== '') {
    $parts = preg_split('/\s+/', $client_name, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) < 2) {
        echo json_encode(['status' => 'error', 'message' => 'Enter both first and last name, e.g., "Jane Doe".']);
        unlink($converted_csv_path);
        exit;
    }
    $last_name  = array_pop($parts);
    $first_name = implode(' ', $parts);

    $sql = "SELECT id AS cid FROM client WHERE first_name = ? AND last_name = ? LIMIT 1";
    if ($stmt = $con->prepare($sql)) {
        $stmt->bind_param('ss', $first_name, $last_name);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) $client_id = $row['cid'];
        $stmt->close();
    }
    if (!$client_id) {
        echo json_encode(['status' => 'error', 'message' => "No matching client found for \"$client_name\"."]);
        unlink($converted_csv_path);
        exit;
    }
}

$use_date_filter = $client_id ? false : true;
$filtered_csv_path = $client_id ? filter_csv_by_client_id($converted_csv_path, $client_id) : $converted_csv_path;

// -------------------------------------------------------------------
// 8) Create dated output folder
// -------------------------------------------------------------------
$today_date_formatted = date('m.d.y');
$clinic_specific_dir   = "$public_generated_documents_dir/$today_date_formatted";
if (!file_exists($clinic_specific_dir) && !mkdir($clinic_specific_dir, 0755, true)) {
    echo json_encode(['status' => 'error', 'message' => "Failed to create folder: $clinic_specific_dir"]);
    unlink($converted_csv_path);
    if ($filtered_csv_path !== $converted_csv_path) @unlink($filtered_csv_path);
    exit;
}

// -------------------------------------------------------------------
// 9) Generate selected reports
// -------------------------------------------------------------------
foreach ($reports as $report_type) {
    $script = resolve_script($base_dir, $program, $report_type);
    if (!$script || !file_exists($script)) {
        // Provide a precise error with expected path
        echo json_encode(['status' => 'error', 'message' => "Script not found for $program → $report_type: " . ($script ?: 'undefined')]);
        unlink($converted_csv_path);
        if ($filtered_csv_path !== $converted_csv_path) @unlink($filtered_csv_path);
        exit;
    }

    $cmd = escapeshellcmd("$python_interpreter $script")
         . ' --csv_file '      . escapeshellarg($filtered_csv_path)
         . ' --clinic_folder '  . escapeshellarg($clinic_folder)
         . ' --templates_dir '  . escapeshellarg($templates_dir)
         . ' --output_dir '     . escapeshellarg($clinic_specific_dir);

    // DB creds only for scripts that need live queries. Keep prior behavior for CUR progress.
    $needs_db = (
        $report_type === 'Progress Reports (Curriculum)'
        && in_array($program, ['BIPP (male)','BIPP (female)','Thinking for a Change','Anger Management'], true)
    );
    if ($needs_db) {
        $cmd .= ' --db_host ' . escapeshellarg('50.28.37.79')
              . ' --db_user ' . escapeshellarg('clinicnotepro_lakeview_app')
              . ' --db_pass ' . escapeshellarg('PF-m[T-+pF%g')
              . ' --db_name ' . escapeshellarg('clinicnotepro_lakeview');
    }

    if ($use_date_filter && !empty($start_date) && !empty($end_date)) {
        $cmd .= ' --start_date ' . escapeshellarg($start_date)
              . ' --end_date '   . escapeshellarg($end_date);
    } elseif (!$use_date_filter) {
        $cmd .= ' --start_date 1970-01-01 --end_date 2100-01-01';
    }

    exec("$cmd 2>&1", $output, $ret);
    if ($ret !== 0) {
        echo json_encode([
            'status'  => 'error',
            'message' => "Error executing $report_type for $program:\n" . implode("\n", $output)
        ]);
        unlink($converted_csv_path);
        if ($filtered_csv_path !== $converted_csv_path) @unlink($filtered_csv_path);
        exit;
    }
}

// -------------------------------------------------------------------
// 10) Cleanup & Success
// -------------------------------------------------------------------
@unlink($converted_csv_path);
if ($filtered_csv_path !== $converted_csv_path && file_exists($filtered_csv_path)) @unlink($filtered_csv_path);

echo json_encode([
    'status'               => 'success',
    'message'              => 'All selected reports generated.',
    'show_download_button' => true,
    'date'                 => $today_date_formatted
]);
exit;
