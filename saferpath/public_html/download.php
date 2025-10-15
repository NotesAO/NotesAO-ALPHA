<?php
// Hardcoded clinic-specific details
$clinic = 'saferpath'; // Clinic name
$base_directory = '/home/notesao/NotePro-Report-Generator/absence_reports';
$file_name = 'AbsenceReport_' . date('Ymd') . '.pdf'; // File name for today's report
$file_path = "$base_directory/$clinic/$file_name"; // Full path to the file

// Check if the file exists and is readable
if (file_exists($file_path) && is_readable($file_path)) {
    // Set headers to force file download
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));

    // Output the file
    readfile($file_path);
    exit;
} else {
    // Handle errors (e.g., file not found or permission issues)
    http_response_code(404); // Send 404 response code
    echo "Error: The requested file could not be found or accessed.";
    exit;
}
