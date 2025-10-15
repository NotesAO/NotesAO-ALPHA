<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";

function stringClean($value) {
    $value = str_replace(' ', '', $value);  // Remove spaces
    $value = preg_replace('/[^A-Za-z0-9\-]/', '', $value); // Removes special chars.
    return $value;
}

// Set up the filename and directory
$directory = "/home/notesao/NotePro-Report-Generator/csv/";
$filename = "filename";
if (isset($appname)) {
    $filename = stringClean($appname);
}

$table_num = isset($_GET['table_num']) ? $_GET['table_num'] : "";
$filename = $filename . "_" . "report" . $table_num . "_" . date("Ymd") . ".csv";
$file_path = $directory . $filename;

// Open the file for writing
$out = fopen($file_path, 'w');
if (!$out) {
    error_log("Failed to open file for writing: $file_path");
    exit("Could not write to file.");
}

// Prepare a select statement
$sql = "SELECT * FROM report2 JOIN report3 ON report2.client_id = report3.client_id;";
$firstRow = true;

if ($stmt = mysqli_prepare($link, $sql)) {
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);

        while ($resultrow = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
            if ($firstRow) {
                $firstRow = false;
                $headers = array_keys($resultrow);
                fputcsv($out, $headers);
            }
            fputcsv($out, $resultrow);
        }
    } else {
        error_log("Error executing statement: " . $stmt->error);
    }
} else {
    error_log("Error preparing statement: " . mysqli_error($link));
}

// Close resources
mysqli_stmt_close($stmt);
fclose($out);

error_log("CSV successfully created at $file_path.");
?>
