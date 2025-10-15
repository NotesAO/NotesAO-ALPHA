<?php
// get_task_status.php

// Enable error logging to a file (optional for debugging)
ini_set('log_errors', '1');
ini_set('error_log', '/home/notesao/saferpath/public_html/get_task_status_errors.log');

// Disable displaying errors on the screen
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Set error reporting level to log all errors
error_reporting(E_ALL);

// Set the error log file path
$log_file = '/home/notesao/saferpath/public_html/get_task_status_errors.log';
ini_set('error_log', $log_file);

// Set response header to JSON
header('Content-Type: application/json');

// Check if 'task_id' is provided
if (!isset($_GET['task_id']) || empty(trim($_GET['task_id']))) {
    echo json_encode(['status' => 'error', 'message' => 'No task_id provided.']);
    exit;
}

$task_id = trim($_GET['task_id']);

// Define the path to the status file
$tasks_dir = '/home/notesao/NotePro-Report-Generator/tasks';
$status_file = "$tasks_dir/status_$task_id.txt";

// Check if the status file exists
if (!file_exists($status_file)) {
    echo json_encode(['status' => 'error', 'message' => 'Task not found.']);
    exit;
}

// Read the status file
$status_content = file_get_contents($status_file);
$lines = explode("\n", $status_content);
$status = '';
$message = '';

foreach ($lines as $line) {
    if (strpos($line, 'status:') === 0) {
        $status = trim(substr($line, strlen('status:')));
    }
    if (strpos($line, 'message:') === 0) {
        $message = trim(substr($line, strlen('message:')));
    }
}

if (empty($status)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status file format.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'task_status' => $status, // 'running', 'completed', 'failed'
    'message' => $message
]);
exit;
?>
