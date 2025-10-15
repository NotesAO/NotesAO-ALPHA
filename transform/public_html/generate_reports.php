<?php
include '/home/notesao/transform/public_html/auth.php';
check_loggedin($con, 'https://notesao.com/login.php');

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// -------------------------------------------------------------------
// 1) Define Variables
// -------------------------------------------------------------------
$clinic_folder = 'transform'; 
$base_dir = '/home/notesao/NotePro-Report-Generator';

// For "transform" clinic:
$generated_documents_dir        = "$base_dir/GeneratedDocuments/$clinic_folder";
$public_generated_documents_dir = "/home/notesao/$clinic_folder/public_html/GeneratedDocuments";
$csv_dir                        = "$base_dir/csv/$clinic_folder";
$tasks_dir                      = "$base_dir/tasks";
$python_interpreter             = "$base_dir/venv311/bin/python3";
$templates_dir                  = "$base_dir/templates/$clinic_folder"; 
// e.g. /home/notesao/NotePro-Report-Generator/templates/transform

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
// 3) Helper: Convert CSV to UTF-8
// -------------------------------------------------------------------
function convert_csv_to_utf8($file_path) {
    $temp_file = tempnam(sys_get_temp_dir(), 'csv_utf8_');
    $output_handle = fopen($temp_file, 'w');

    if (($handle = fopen($file_path, 'r')) !== false) {
        while (($row = fgetcsv($handle))) {
            $converted_row = array_map(function ($cell) {
                $detected = mb_detect_encoding(
                    $cell,
                    ['UTF-8', 'ISO-8859-1', 'WINDOWS-1252'],
                    true
                );
                if ($detected) {
                    $cell = mb_convert_encoding($cell, 'UTF-8', $detected);
                } else {
                    // Remove strange chars if no encoding detected
                    $cell = preg_replace('#[^A-Za-z0-9\s\.,!?;:\'\"()\-_/]#u', '', $cell);
                }
                return $cell;
            }, $row);

            fputcsv($output_handle, $converted_row);
        }
        fclose($handle);
    }

    fclose($output_handle);
    return $temp_file;
}

// -------------------------------------------------------------------
// 4) Helper: Filter CSV by client_id (for single-client scenario)
// -------------------------------------------------------------------
function filter_csv_by_client_id($csv_path, $client_id) {
    $temp_file = tempnam(sys_get_temp_dir(), 'filtered_');
    $in  = fopen($csv_path, 'r');
    $out = fopen($temp_file, 'w');

    if (!$in || !$out) {
        // If something fails, just return original CSV
        return $csv_path;
    }

    // Read the header row
    $header = fgetcsv($in);
    if (!$header) {
        fclose($in);
        fclose($out);
        return $csv_path;
    }
    fputcsv($out, $header);

    // Find which column is "client_id"
    $clientIdIndex = array_search('client_id', $header);
    if ($clientIdIndex === false) {
        fclose($in);
        fclose($out);
        return $csv_path; // No client_id column => can't filter
    }

    // Keep only rows matching that client_id
    while (($row = fgetcsv($in)) !== false) {
        if (isset($row[$clientIdIndex]) && $row[$clientIdIndex] == $client_id) {
            fputcsv($out, $row);
        }
    }

    fclose($in);
    fclose($out);

    return $temp_file;
}

// -------------------------------------------------------------------
// 5) Only POST is allowed for generating or "download_zip"
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Please use POST.']);
    exit;
}

// -------------------------------------------------------------------
// 5A) Handle "download_zip" action
// -------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'download_zip') {
    if (!isset($_POST['date'])) {
        echo json_encode(['status' => 'error', 'message' => 'Date parameter missing for download_zip action.']);
        exit;
    }
    $today_date_formatted = $_POST['date'];
    $clinic_specific_dir = "$public_generated_documents_dir/$today_date_formatted";

    if (!file_exists($clinic_specific_dir) || !is_dir($clinic_specific_dir)) {
        echo json_encode([
            'status' => 'error',
            'message' => "No documents found for date ($today_date_formatted)."
        ]);
        exit;
    }

    $zip_filename = "Generated_Reports_$today_date_formatted.zip";
    $zip_path = "$public_generated_documents_dir/$zip_filename";

    if (!is_dir($public_generated_documents_dir)) {
        if (!mkdir($public_generated_documents_dir, 0755, true)) {
            echo json_encode([
                'status' => 'error',
                'message' => "Failed to create directory: $public_generated_documents_dir"
            ]);
            exit;
        }
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo json_encode([
            'status' => 'error',
            'message' => "Failed to create ZIP file at $zip_path"
        ]);
        exit;
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($clinic_specific_dir));
    foreach ($files as $file) {
        if (!$file->isDir()) {
            $relativePath = substr($file->getRealPath(), strlen($clinic_specific_dir) + 1);
            if (file_exists($file->getRealPath())) {
                $zip->addFile($file->getRealPath(), $relativePath);
            }
        }
    }

    if (!$zip->close()) {
        echo json_encode([
            'status' => 'error',
            'message' => "Failed to close ZIP file at $zip_path"
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'ZIP file created successfully.',
        'download_url' => "/GeneratedDocuments/$zip_filename"
    ]);
    exit;
}

// -------------------------------------------------------------------
// 6) Handle actual "report generation" action
// -------------------------------------------------------------------
$program     = isset($_POST['program'])     ? trim($_POST['program'])     : '';
$reports     = isset($_POST['reports'])     ? (array)$_POST['reports']    : [];
$start_date  = isset($_POST['start_date'])  ? trim($_POST['start_date'])  : null;
$end_date    = isset($_POST['end_date'])    ? trim($_POST['end_date'])    : null;

// NEW: Optional client name
$client_name = isset($_POST['client_name']) ? trim($_POST['client_name']) : '';
$client_id   = null; // Will look up if present

// For normal mode, these checks remain:
if (empty($program)) {
    echo json_encode(['status' => 'error', 'message' => 'Program selection is required.']);
    exit;
}
if (empty($reports)) {
    echo json_encode(['status' => 'error', 'message' => 'At least one report type must be selected.']);
    exit;
}

$valid_programs = ["BIPP", "Anger Control", "Thinking for a Change"];
if (!in_array($program, $valid_programs)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid program selected.']);
    exit;
}

$valid_reports = [
    "Completion Documents",
    "Entrance Notifications",
    "Exit Notices",
    "Unexcused Absences",
    "Progress Reports (Stage of Change)",
    "Progress Reports (Curriculum)",
    "Behavior Contracts"
];
foreach ($reports as $report) {
    if (!in_array($report, $valid_reports)) {
        echo json_encode(['status' => 'error', 'message' => "Invalid report type selected: $report"]);
        exit;
    }
}

// -------------------------------------------------------------------
// 7) Check CSV existence & convert to UTF-8
// -------------------------------------------------------------------
$today_date = date('Ymd');
$expected_csv_filename = "report5_dump_$today_date.csv";
$csv_file_path = "$csv_dir/$expected_csv_filename";

if (!file_exists($csv_file_path)) {
    echo json_encode([
        'status' => 'error',
        'message' => "CSV file not found: $expected_csv_filename"
    ]);
    exit;
}

$converted_csv_path = convert_csv_to_utf8($csv_file_path);

// -------------------------------------------------------------------
// 8) If client_name is provided, find matching client_id and filter CSV
// -------------------------------------------------------------------
if (!empty($client_name)) {
    // Must parse e.g. "John Doe" -> first/last
    $parts = explode(' ', $client_name, 2);
    if (count($parts) < 2) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please enter both first and last name, e.g. "Jane Doe".'
        ]);
        unlink($converted_csv_path);
        exit;
    }
    $first_name = $parts[0];
    $last_name  = $parts[1];

    // Lookup in your "client" table. Adjust if needed for your schema:
    $sql = "SELECT id AS cid
            FROM client
            WHERE first_name = ?
              AND last_name  = ?
            LIMIT 1";
    if ($stmt = $con->prepare($sql)) {
        $stmt->bind_param('ss', $first_name, $last_name);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $client_id = $row['cid'];
        }
        $stmt->close();
    }

    if (!$client_id) {
        echo json_encode([
            'status' => 'error',
            'message' => "No matching client found for '$client_name'."
        ]);
        unlink($converted_csv_path);
        exit;
    }
}

// If single client scenario, we don't want date filtering to block them.
// If your Python scripts require start_date/end_date, let's pass a wide range:
$use_date_filter = true;
if ($client_id) {
    $use_date_filter = false;  // or set dummy date range
}

// -------------------------------------------------------------------
// 9) Possibly filter CSV to only that client
// -------------------------------------------------------------------
$filtered_csv_path = $converted_csv_path;
if ($client_id) {
    $filtered_csv_path = filter_csv_by_client_id($converted_csv_path, $client_id);
}

// -------------------------------------------------------------------
// 10) Create output folder
// -------------------------------------------------------------------
$today_date_formatted = date('m.d.y');
$clinic_specific_dir   = "$public_generated_documents_dir/$today_date_formatted";

if (!file_exists($clinic_specific_dir)) {
    if (!mkdir($clinic_specific_dir, 0755, true)) {
        echo json_encode([
            'status' => 'error',
            'message' => "Failed to create folder: $clinic_specific_dir"
        ]);
        unlink($converted_csv_path);
        if ($filtered_csv_path !== $converted_csv_path) {
            @unlink($filtered_csv_path);
        }
        exit;
    }
}

// -------------------------------------------------------------------
// 11) Scripts for each program/report type
// -------------------------------------------------------------------
$script_paths = [
    "BIPP" => [
        "Completion Documents"   => "$base_dir/BIPP_Completion_Documents_Script.py",
        "Entrance Notifications" => "$base_dir/BIPP_Entrance_Notifications_Script.py",
        "Exit Notices"           => "$base_dir/BIPP_Exit_Notices_Script.py",
        "Unexcused Absences"     => "$base_dir/BIPP_Unexcused_Absences_Script.py",
        "Progress Reports (Stage of Change)"=> "$base_dir/BIPP_SOC_Progress_Reports_Script.py",
        "Progress Reports (Curriculum)" => "$base_dir/BIPP_CUR_Progress_Reports_Script.py",
        "Behavior Contracts" => "$base_dir/BIPP_Behavior_Contracts_Script.py"
    ],
    "Anger Control" => [
        "Completion Documents"   => "$base_dir/AC_Completion_Documents_Script.py",
        "Entrance Notifications" => "$base_dir/AC_Entrance_Notifications_Script.py",
        "Exit Notices"           => "$base_dir/AC_Exit_Notices_Script.py",
        "Unexcused Absences"     => "$base_dir/AC_Unexcused_Absences_Script.py",
        "Progress Reports (Stage of Change)"=> "$base_dir/AC_Progress_Reports_Script.py",
        "Progress Reports (Curriculum)"=> "$base_dir/AC_Progress_Reports_Script.py"
    ],
    "Thinking for a Change" => [
        "Completion Documents"   => "$base_dir/T4C_Completion_Documents_Script.py",
        "Entrance Notifications" => "$base_dir/T4C_Entrance_Notifications_Script.py",
        "Exit Notices"           => "$base_dir/T4C_Exit_Notices_Script.py",
        "Unexcused Absences"     => "$base_dir/T4C_Unexcused_Absences_Script.py",
        "Progress Reports (Stage of Change)"=> "$base_dir/T4C_Progress_Reports_Script.py",
        "Progress Reports (Curriculum)"=> "$base_dir/T4C_Progress_Reports_Script.py"
    ]
];

// -------------------------------------------------------------------
// 12) Generate each selected report
// -------------------------------------------------------------------
foreach ($reports as $report_type) {
    if (!isset($script_paths[$program][$report_type])) {
        echo json_encode(['status' => 'error', 'message' => "No script defined for $report_type in $program"]);
        // Cleanup
        unlink($converted_csv_path);
        if ($filtered_csv_path !== $converted_csv_path) {
            @unlink($filtered_csv_path);
        }
        exit;
    }

    $script = $script_paths[$program][$report_type];
    if (!file_exists($script)) {
        echo json_encode(['status' => 'error', 'message' => "Script not found: $script"]);
        unlink($converted_csv_path);
        if ($filtered_csv_path !== $converted_csv_path) {
            @unlink($filtered_csv_path);
        }
        exit;
    }

    // Build shell command (use *filtered* CSV path)
    $cmd = escapeshellcmd("$python_interpreter $script")
         . ' --csv_file '        . escapeshellarg($filtered_csv_path)
         . ' --clinic_folder '    . escapeshellarg($clinic_folder)
         . ' --templates_dir '    . escapeshellarg($templates_dir)
         . ' --output_dir '       . escapeshellarg($clinic_specific_dir);

         if ($report_type === "Progress Reports (Curriculum)") {
            // Only needed for the new curriculum script
            $cmd .= ' --db_host ' . escapeshellarg('50.28.37.79')
                  . ' --db_user ' . escapeshellarg('clinicnotepro_transform_app')
                  . ' --db_pass ' . escapeshellarg('PF-m[T-+pF%g')
                  . ' --db_name ' . escapeshellarg('clinicnotepro_transform');
        }
        
    // If we are using date filters:
    if ($use_date_filter && !empty($start_date) && !empty($end_date)) {
        $cmd .= ' --start_date ' . escapeshellarg($start_date)
              . ' --end_date '   . escapeshellarg($end_date);
    } elseif (!$use_date_filter) {
        // Single client scenario => pass a wide date range so the script doesn't exclude them
        $cmd .= ' --start_date 1970-01-01 --end_date 2100-01-01';
    }

    // Debug if you like:
    // error_log("Executing command: $cmd");

    exec("$cmd 2>&1", $output, $return_var);
    if ($return_var !== 0) {
        echo json_encode([
            'status'  => 'error',
            'message' => "Error executing $report_type: " . implode("\n", $output)
        ]);
        unlink($converted_csv_path);
        if ($filtered_csv_path !== $converted_csv_path) {
            @unlink($filtered_csv_path);
        }
        exit;
    }
}

// -------------------------------------------------------------------
// 13) Cleanup & Return Success
// -------------------------------------------------------------------
unlink($converted_csv_path);
if ($filtered_csv_path !== $converted_csv_path && file_exists($filtered_csv_path)) {
    unlink($filtered_csv_path);
}

echo json_encode([
    'status'              => 'success',
    'message'             => 'All selected reports generated successfully.',
    'show_download_button'=> true,
    'date'                => $today_date_formatted
]);
exit;
