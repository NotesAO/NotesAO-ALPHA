<?php
include_once 'auth.php'; // Ensure session and database connection

// Check if the user is logged in
check_loggedin($con);

// Hardcoded clinic folder
$clinic_folder = 'transform';
$upload_dir = "/home/clinicnotepro/uploads/$clinic_folder/";

// Validate the file request
if (!isset($_GET['file']) || empty($_GET['file'])) {
    die("Error: No file specified.");
}

$file_name = basename($_GET['file']); // Extract filename safely
$file_path = $upload_dir . $file_name;

// Check if file exists
if (!file_exists($file_path)) {
    die("Error: File not found.");
}

// Determine MIME type based on file extension
$mime_types = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'jpg'  => 'image/jpeg',
    'png'  => 'image/png',
    'txt'  => 'text/plain',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
];

$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$content_type = $mime_types[$file_ext] ?? 'application/octet-stream';

// Set appropriate headers for file download
header("Content-Type: $content_type");
header("Content-Disposition: attachment; filename=\"$file_name\"");
header("Content-Length: " . filesize($file_path));
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Read and output the file
readfile($file_path);
exit;
