<?php
include_once '../auth.php';
include_once '../../config/config.php';
check_loggedin($con, '../index.php');
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <a class="navbar-brand" href="#">
        <i class="fas fa-tools"></i> Admin Panel - Lakeview
    </a>

    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarAdmin" aria-controls="navbarAdmin" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarAdmin">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item">
                <a class="nav-link" href="activity-reporting.php">
                    <i class="fas fa-chart-line"></i> Activity Reporting
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="client_file_update.php">
                    <i class="fas fa-user-edit"></i> Client Update Util
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="accounts.php">
                    <i class="fas fa-users-cog"></i> User Accounts
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="..">
                    <i class="fas fa-arrow-left"></i> Return to App
                </a>
            </li>
        </ul>

        <span class="navbar-text mr-3">
            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['name']); ?>
        </span>

        <a class="btn btn-outline-danger my-2 my-sm-0" href="../logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>
