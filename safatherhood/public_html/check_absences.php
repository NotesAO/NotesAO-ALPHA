<?php
// check_absences.php

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include necessary files
include_once '/home/notesao/safatherhood/config/config.php'; // Adjust the path if needed
include_once 'auth.php';
require_once 'helpers.php';

check_loggedin($link);

function sanitize_csv_file($file_path) {
    $temp_file = tempnam(sys_get_temp_dir(), 'abs_sanitized_');
    $output_handle = fopen($temp_file, 'w');

    if (($handle = fopen($file_path, 'r')) !== false) {
        while (($row = fgetcsv($handle))) {
            $sanitized_row = array_map(function ($cell) {
                // Whitelist letters, digits, whitespace, punctuation, etc.
                // Everything else (like 0x96) is removed.
                return preg_replace('#[^A-Za-z0-9\s\.,!?;:\'\"()\-_/]#u', '', $cell);
            }, $row);
            fputcsv($output_handle, $sanitized_row);
        }
        fclose($handle);
    }

    fclose($output_handle);
    return $temp_file;
}

// Define paths
$clinic_folder = 'safatherhoojd'; // Hardcoded clinic folder
$base_dir = '/home/notesao/NotePro-Report-Generator';
$python_script = "$base_dir/check_absences.py";
$venv_python = "$base_dir/venv311/bin/python";
$csv_directory = "$base_dir/csv/$clinic_folder/";
$output_directory = "$base_dir/absence_reports/$clinic_folder/";

// Ensure directories exist
if (!file_exists($csv_directory)) {
    mkdir($csv_directory, 0777, true);
}
if (!file_exists($output_directory)) {
    mkdir($output_directory, 0777, true);
}

// Step 1: Use the generated CSV file for today's date
$date_str = date('Ymd');
$original_csv_file = $csv_directory . "report5_dump_$date_str.csv";
$absence_csv_file = $csv_directory . "absence_report5_dump_$date_str.csv";

if (!file_exists($original_csv_file)) {
    echo json_encode(['status' => 'error', 'message' => "CSV file not found: $original_csv_file"]);
    exit;
}

// Step 2: Copy the original CSV file to create the absence version
if (file_exists($absence_csv_file)) {
    unlink($absence_csv_file);  // Remove any existing absence file
}

if (!copy($original_csv_file, $absence_csv_file)) {
    echo json_encode(['status' => 'error', 'message' => 'Error copying CSV file.']);
    exit;
}

// --------------------------------------------
// Now sanitize the newly created $absence_csv_file
$sanitized_absence_csv = sanitize_csv_file($absence_csv_file);
// --------------------------------------------

// Step 2: Sanitize inputs and run absence processing
$report_date = $_POST['report_date'] ?? date('m/d/Y');
$report_date_arg = escapeshellarg($report_date);
$csv_file_arg = escapeshellarg($sanitized_absence_csv); // <-- pass the sanitized path
$output_directory_arg = escapeshellarg($output_directory);

$command = "$venv_python $python_script --csv_file $csv_file_arg --report_date $report_date_arg --output_directory $output_directory_arg";

// Execute the command and capture output
$check_absences_output = shell_exec($command . ' 2>&1');

// Check for errors in script execution
if (strpos($check_absences_output, 'Error') !== false) {
    echo json_encode(['status' => 'error', 'message' => 'Error generating absence report. Output: ' . $check_absences_output]);
    exit;
}

// Step 3: Check and serve the generated PDF
$today = date('Ymd');
$output_pdf = $output_directory . "AbsenceReport_{$today}.pdf";

// Verify if the PDF exists
if (!file_exists($output_pdf)) {
    error_log("Error: PDF file not found at path: $output_pdf");
    http_response_code(404); // Send a 404 HTTP response
    echo json_encode([
        'status' => 'error',
        'message' => 'Absence report not found. Please ensure the report generation process was successful.'
    ]);
    exit;
}

// Step 5: Copy the generated PDF to the public directory for download
$public_directory = "/home/notesao/safatherhood/public_html/documents/absence_reports/";
$new_location = $public_directory . "AbsenceReport_{$today}.pdf";

// Ensure the public directory exists
if (!file_exists($public_directory)) {
    if (!mkdir($public_directory, 0777, true)) {
        error_log("Error: Failed to create public directory at $public_directory");
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to create the public documents folder.'
        ]);
        exit;
    }
}

// Copy the PDF to the public directory
if (!copy($output_pdf, $new_location)) {
    error_log("Error: Failed to copy PDF to $new_location");
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to copy the report to the public documents folder.'
    ]);
    exit;
}

// Set appropriate permissions for the copied PDF
chmod($new_location, 0644);

// Construct the download link
$download_link = "/documents/absence_reports/AbsenceReport_{$today}.pdf";

// Log the generated download link for debugging
error_log("PDF file copied successfully to: $new_location");

// Step 6: Return success response with the download link
echo json_encode([
    'status' => 'success',
    'message' => 'Absence report generated successfully!',
    'download_link' => $download_link
]);
exit;
unlink($sanitized_absence_csv);

?>