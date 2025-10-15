<?php
// update_clients.php

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include necessary files
include_once '/home/notesao/dwag/config/config.php'; // Adjust path if needed
include_once 'auth.php';
require_once 'helpers.php';


// -- 1. Define and sanitize CSV --

/**
 * Sanitize a CSV file by stripping disallowed characters from each cell.
 * Returns path to a new temporary file.
 */
function sanitize_csv_file($file_path) {
    $temp_file = tempnam(sys_get_temp_dir(), 'sanitized_csv_');
    $output_handle = fopen($temp_file, 'w');

    if (($handle = fopen($file_path, 'r')) !== false) {
        while (($row = fgetcsv($handle))) {
            $sanitized_row = array_map(function ($cell) {
                // Allow letters, numbers, whitespace, and some punctuation
                // Use '#' as the delimiter so '/' in the set won't conflict
                $cell = preg_replace('#[^A-Za-z0-9\s\.,!?;:\'\"()\-_/]#u', '', $cell);
                return $cell;
            }, $row);
            fputcsv($output_handle, $sanitized_row);
        }
        fclose($handle);
    }

    fclose($output_handle);
    return $temp_file;
}

// -- 3. Get form data (start/end date) --

$start_date = $_POST['start_date'] ?? '';
$end_date   = $_POST['end_date']   ?? '';

// -- 4. Paths and environment --

$clinic_folder   = 'dwag'; // Adjust or derive dynamically if needed
$python_script   = '/home/notesao/NotePro-Report-Generator/Update_Clients.py';
$venv_python     = '/home/notesao/NotePro-Report-Generator/venv311/bin/python';

$original_csv_path = "/home/notesao/NotePro-Report-Generator/csv/$clinic_folder/report5_dump_" . date('Ymd') . '.csv';

// 4a. First sanitize the original CSV
$sanitized_csv_path = sanitize_csv_file($original_csv_path);

// We will produce a second CSV containing only valid rows (i.e., “essential” columns present)
$valid_csv_path = tempnam(sys_get_temp_dir(), 'valid_csv_');

// -- 5. Read sanitized CSV, validate essential columns, skip invalid rows --

$skippedRowsBeforePython = [];  // to store info about skipped rows (missing columns, etc.)
$essentialCols = [
    'client_id',
    'program_name',
    'first_name',
    'gender',
    'attended',
    'required_sessions',
    // add more if you deem them essential:
    // 'exit_date', 'exit_reason', 'orientation_date', etc. 
];

$handle = fopen($sanitized_csv_path, 'r');
if (!$handle) {
    echo json_encode([
        'status' => 'error',
        'message' => "Could not open sanitized CSV at $sanitized_csv_path"
    ]);
    exit;
}

// We'll read in the CSV, figure out the headers, then write only valid rows to $valid_csv_path
$outputHandle = fopen($valid_csv_path, 'w');

$headers = [];
$isFirstRow = true;
$validCount = 0;
$totalCount = 0;

if ($handle !== false) {
    while (($row = fgetcsv($handle, 10000, ',')) !== false) {
        $totalCount++;
        if ($isFirstRow) {
            // store header row
            $headers = $row;
            fputcsv($outputHandle, $row); // write headers to valid CSV
            $isFirstRow = false;
            continue;
        }

        // Convert row to associative array for easy checks
        $rowAssoc = array_combine($headers, $row);

        // Check for missing essential columns
        $missing = [];
        foreach ($essentialCols as $col) {
            if (!isset($rowAssoc[$col]) || $rowAssoc[$col] === '') {
                $missing[] = $col;
            }
        }

        if (!empty($missing)) {
            // skip row, log reason
            $skippedRowsBeforePython[] = [
                'client_id' => $rowAssoc['client_id'] ?? '',
                'reason'    => 'Missing essential columns: ' . implode(', ', $missing),
                'row_data'  => $rowAssoc
            ];
            continue;
        }

        // If we made it here, the row is valid enough. Write to valid CSV.
        fputcsv($outputHandle, $row);
        $validCount++;
    }
    fclose($handle);
    fclose($outputHandle);
}

// -- 6. Build the command to run Python with the valid CSV --

$start_date_arg = escapeshellarg($start_date);
$end_date_arg   = escapeshellarg($end_date);

$output_csv_path = "/home/notesao/NotePro-Report-Generator/csv/$clinic_folder/updated_report5_dump_" . date('Ymd') . '.csv';

$templates_dir = "/home/notesao/NotePro-Report-Generator/templates/$clinic_folder";

// Command looks like: python Update_Clients.py --csv_file valid_csv_path ...
$command = "$venv_python $python_script --csv_file $valid_csv_path --start_date $start_date_arg --end_date $end_date_arg --output_csv_path $output_csv_path --templates_dir $templates_dir";

// -- 7. Execute Python script and capture output --
$output = shell_exec($command . ' 2>&1');

// If the Python script didn't produce an updated CSV, show error
if (!file_exists($output_csv_path)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error generating updated CSV file. Output: ' . $output
    ]);
    // we can optionally unlink($valid_csv_path);
    exit;
}

// -- 8. Parse the updated CSV and update DB as before --

$csvArray = [];
if (($handle = fopen($output_csv_path, 'r')) !== false) {
    while (($data = fgetcsv($handle, 10000, ',')) !== false) {
        $data = array_map('trim', $data);
        // skip empty lines
        if (!empty(array_filter($data))) {
            $csvArray[] = $data;
        }
    }
    fclose($handle);
}

$allowedFields = [
    'client_id',
    'client_stage',
    'client_note',
    'orientation_date',
    'exit_date',
    'exit_reason',
    'referral_type'
];

if (count($csvArray) === 0) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'CSV file is empty (after Python update).'
    ]);
    exit;
}

// First row = headers
$csvKeys = array_map('trim', array_shift($csvArray));

// Map header columns to the allowed fields
$allowedIndices = [];
foreach ($csvKeys as $index => $key) {
    if (in_array($key, $allowedFields)) {
        $allowedIndices[$index] = $key;
    }
}

if (!in_array('client_id', $allowedIndices)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Updated CSV must include client_id column.'
    ]);
    exit;
}

// Prepare final data for DB updates
$csvData = [];
foreach ($csvArray as $row) {
    $rowData = [];
    foreach ($allowedIndices as $index => $key) {
        $rowData[$key] = $row[$index] ?? null;
    }
    // skip if client_id is missing
    if (empty($rowData['client_id'])) {
        error_log("Skipping row with missing client_id: " . json_encode($rowData));
        continue;
    }
    $csvData[] = $rowData;
}

// -- 9. Build the SQL update statement as before --

$update_sql = "UPDATE client SET ";
$paramKeys  = [];
$needComma  = false;

if (in_array('client_stage', $allowedFields)) {
    $paramKeys[] = 'client_stage';
    if ($needComma) $update_sql .= ", ";
    $update_sql .= "client_stage_id = (SELECT id FROM client_stage WHERE stage LIKE ? LIMIT 1)";
    $needComma = true;
}
if (in_array('client_note', $allowedFields)) {
    $paramKeys[] = 'client_note';
    if ($needComma) $update_sql .= ", ";
    $update_sql .= "note = ?";
    $needComma = true;
}
if (in_array('orientation_date', $allowedFields)) {
    $paramKeys[] = 'orientation_date';
    if ($needComma) $update_sql .= ", ";
    $update_sql .= "orientation_date = NULLIF(STR_TO_DATE(?, '%Y-%m-%d'),'0000-00-00')";
    $needComma = true;
}
if (in_array('exit_date', $allowedFields)) {
    $paramKeys[] = 'exit_date';
    if ($needComma) $update_sql .= ", ";
    $update_sql .= "exit_date = NULLIF(STR_TO_DATE(?, '%Y-%m-%d'),'0000-00-00')";
    $needComma = true;
}
if (in_array('exit_reason', $allowedFields)) {
    $paramKeys[] = 'exit_reason';
    if ($needComma) $update_sql .= ", ";
    $update_sql .= "exit_reason_id = (SELECT id FROM exit_reason WHERE reason LIKE ? LIMIT 1)";
    $needComma = true;
}
$paramKeys[] = 'client_id';
$update_sql .= " WHERE client.id = ?";

// Prepare statement
$stmt = $link->prepare($update_sql);
if (!$stmt) {
    error_log("Prepare failed: (" . $link->errno . ") " . $link->error);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database prepare error: ' . $link->error
    ]);
    exit;
}

// Initialize counters
$processed_count = 0;
$skipped_count   = 0;
$updated_count   = 0;

$fieldTypes = [
    'client_stage'      => 's',
    'client_note'       => 's',
    'orientation_date'  => 's',
    'exit_date'         => 's',
    'exit_reason'       => 's',
    'client_id'         => 'i'
];

foreach ($csvData as $row) {
    $paramArray = [];
    $types      = '';

    foreach ($paramKeys as $key) {
        $param = $row[$key] ?? null;
        if ($key === 'client_id') {
            $param = (int)$param;
        }
        $paramArray[] = $param;
        $types       .= $fieldTypes[$key] ?? 's';
    }

    $stmt->bind_param($types, ...$paramArray);

    try {
        $stmt->execute();
        if ($stmt->errno) {
            error_log("Error executing statement: " . $stmt->error);
            $skipped_count++;
            continue;
        }
        $row_count = $stmt->affected_rows;
        if ($row_count > 0) {
            $updated_count++;
        }
        $processed_count++;
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        $skipped_count++;
        continue;
    }
}

$stmt->close();

// -- 10. Cleanup and Final Output --

unlink($sanitized_csv_path);
unlink($valid_csv_path); // remove the valid-only file
// You may also choose to unlink($output_csv_path) if you don't need the updated file

// Summarize the final results
$result = [
    'status'  => 'success',
    'message' => 'Clients updated successfully.',
    'summary' => [
        'processed_after_python' => $processed_count,
        'updated_after_python'   => $updated_count,
        'skipped_after_python'   => $skipped_count
    ],
    'pre_python_skips' => [
        'total_rows'  => $totalCount,
        'valid_rows'  => $validCount,
        'skipped_rows'=> []
    ]
];

// We can also include details of which rows were skipped before Python
foreach ($skippedRowsBeforePython as $skip) {
    $result['pre_python_skips']['skipped_rows'][] = [
        'client_id' => $skip['client_id'],
        'reason'    => $skip['reason']
        // 'row_data' => $skip['row_data'], // you can include raw data if you want
    ];
}

echo json_encode($result);
?>
