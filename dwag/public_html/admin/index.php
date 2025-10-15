<?php
include_once '../auth.php';
include_once '../../config/config.php';
check_loggedin($con, '../index.php');

// Verify session role directly
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    error_log("Access Denied: User with ID {$_SESSION['id']} attempted to access admin page with role: " . ($_SESSION['role'] ?? 'not set'));
    exit('You do not have permission to access this page!');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>NotesAO - Admin Panel</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

    <style>
        body {
            padding-top: 70px;
            background-color: #f8f9fa;
        }
        .page-header h2 {
            margin-top: 0;
        }
        .admin-btn {
            margin: 10px 5px;
        }
    </style>
</head>

<body>
<?php require_once('admin_navbar.php'); ?>

<div class="container">
    <div class="page-header my-4">
        <h2>Admin Dashboard</h2>
    </div>

    <!-- Admin Buttons for Each Feature -->
    <div class="d-flex flex-wrap">
        <a href="activity-reporting.php" class="btn btn-info admin-btn">
            <i class="fas fa-chart-line"></i> Activity Reporting (Under Construction)
        </a>

        <a href="client_file_update.php" class="btn btn-warning admin-btn">
            <i class="fas fa-user-edit"></i> Client Update Util
        </a>

        <a href="accounts.php" class="btn btn-primary admin-btn">
            <i class="fas fa-users-cog"></i> User Accounts
        </a>

        <a href=".." class="btn btn-secondary admin-btn">
            <i class="fas fa-arrow-left"></i> Return to App
        </a>
    </div>
</div>

</body>
</html>
