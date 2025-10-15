<?php
// client-contract-upload.php

require_once '../config/config.php';  // Adjust path if needed
require_once 'sql_functions.php';
include_once 'auth.php';
check_loggedin($con);

// Check if we have a client_id in GET
if (!isset($_GET['client_id'])) {
    die("No client ID specified.");
}

$client_id = $_GET['client_id'];
$uploadError = "";

// If form is submitted, process the file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check client_id in POST matches the GET value (basic security)
    if (!isset($_POST['client_id']) || $_POST['client_id'] !== $client_id) {
        die("Client ID mismatch.");
    }

    // Check if file was uploaded without errors
    if (isset($_FILES['contract_file']) && $_FILES['contract_file']['error'] === UPLOAD_ERR_OK) {
        // Basic info about the uploaded file
        $fileTmpPath = $_FILES['contract_file']['tmp_name'];
        $fileName    = $_FILES['contract_file']['name'];
        $fileSize    = $_FILES['contract_file']['size'];
        $fileType    = $_FILES['contract_file']['type'];

        // (Optional) validate file extension, size, etc. here

        // Folder path (ensure it is writable)
        $uploadFolder = '/home/clinicnotepro/uploads/sandbox/';

        // Build a new file name: "<clientID>_BH.<extension>"
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $newFileName = $client_id . '_BH.' . $ext;  // e.g. "4074_BH.pdf"

        // Full destination path
        $destPath = $uploadFolder . $newFileName;

        // Attempt to move from PHP tmp location to the uploads folder
        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            $uploadError = "Could not move uploaded file (permissions?)";
        } else {
            // Build INSERT statement using MySQLi + question-mark placeholders
            // NOTE: if 'id' is AUTO_INCREMENT, you do NOT include it here
            $sql = "
                INSERT INTO office_documents
                  (user_id, clinic_folder, file_name, file_path, file_type, status, reviewed_by, review_date, upload_date)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            // Prepare the statement
            if (!$stmt = $con->prepare($sql)) {
                $uploadError = "Prepare failed: " . $con->error;
            } else {
                // Extract info for each column
                // user_id -> from session
                $user_id       = $_SESSION['id'] ?? 0;
                // clinic_folder -> "sandbox"
                $clinic_folder = 'sandbox';
                // file_name -> our newly built $newFileName
                $finalFileName = $newFileName;
                // file_path -> $destPath
                $finalFilePath = $destPath;
                // file_type -> "Other" (or "Behavior Contract" if you like)
                $file_type     = 'Other';
                // status -> "Pending"
                $status        = 'Pending';
                // reviewed_by -> null
                $reviewed_by   = null;
                // review_date -> null
                $review_date   = null;
                // upload_date -> current date/time
                $upload_date   = date('Y-m-d H:i:s');

                // Bind parameters:
                //   i = integer, s = string, s = string, ...
                //   user_id is integer
                //   everything else is string or null
                // If a variable is null, MySQLi may need a little help. We can pass an empty string or do
                //   $stmt->bind_param(...) but we want param as 's' if we pass an empty string
                // For truly null columns, we can use "bind_param" then call "send_long_data()" or do
                //   "NULL" placeholders. Easiest is to pass empty strings for the null fields or do param "s" and pass null.
                $stmt->bind_param(
                    'issssssss',
                    $user_id,
                    $clinic_folder,
                    $finalFileName,
                    $finalFilePath,
                    $file_type,
                    $status,
                    $reviewed_by,
                    $review_date,
                    $upload_date
                );

                // Execute
                if (!$stmt->execute()) {
                    $uploadError = "Execute failed: " . $stmt->error;
                } else {
                    // Success: redirect back to client-review
                    $stmt->close();
                    header("Location: client-review.php?client_id=" . urlencode($client_id));
                    exit;
                }
            }
        }
    } else {
        $uploadError = "No file selected or an upload error occurred.";
    }
}

// If we got here, either GET request or there was an error
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Contract</title>
    <link rel="stylesheet" 
          href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
          integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk"
          crossorigin="anonymous">
</head>
<body>
<div class="container pt-4">
    <h1>Upload Contract for Client ID: <?= htmlspecialchars($client_id); ?></h1>

    <?php if (!empty($uploadError)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($uploadError); ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="form-group">
        <input type="hidden" name="client_id" value="<?= htmlspecialchars($client_id); ?>">

        <label for="contract_file">Select Contract PDF/DOC:</label><br>
        <input type="file" name="contract_file" id="contract_file" required>
        
        <button type="submit" class="btn btn-primary mt-3">Upload</button>
        <a href="client-review.php?client_id=<?= urlencode($client_id); ?>"
           class="btn btn-secondary mt-3">Cancel</a>
    </form>
</div>
</body>
</html>
