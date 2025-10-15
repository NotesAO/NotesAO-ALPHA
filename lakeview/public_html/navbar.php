<?php

include_once '../config/config.php';
include_once 'auth.php';
check_loggedin($con, '../index.php');

// Set fallback for program_name if it's not set in the session
$program_name = $_SESSION['program_name'];
$username     = $_SESSION['name'] ?? 'User';
?>

<!-- Font Awesome CSS link (example using CDN) -->
<link rel="stylesheet" 
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
      integrity="sha512-dLcz9Da+7Ry9p07Cq9pNFIK8vCPN5BqkCXnRxqcyHjOMhJKAocT+h7b8ySBxsp6pc1p6ZC4gnM3G5Ts6zGx0ZQ=="
      crossorigin="anonymous" />

<!-- Example: darker navbar background, light text -->

<style>
  body {
    padding-top: 50px; /* Adjust if navbar is taller or shorter */
  }
  .dropdown-menu {
    max-height: 80vh;
    overflow-y: auto;
    overflow-x: hidden;
  }
</style>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <a class="navbar-brand nav-link disabled" href="#">
        <?php echo htmlspecialchars("Lakeview - Current Program: " . $program_name); ?>
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse"
            data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
            aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Collapsible section -->
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav mr-auto">
            <!-- Dropdown Menu -->
            <li class="nav-item dropdown">
                <!-- More descriptive dropdown toggle label -->
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown"
                   role="button" data-toggle="dropdown" aria-haspopup="true"
                   aria-expanded="false">
                    <i class="fas fa-bars"></i> Navigate
                </a>

                <!-- Dropdown items: add padding class p-3 to give it more space -->
                <div class="dropdown-menu p-3" aria-labelledby="navbarDropdown">
                    <h6 class="dropdown-header">Main</h6>
                    <a class="dropdown-item" href="home.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                    <a class="dropdown-item" href="program-select.php">
                        <i class="fas fa-chalkboard-teacher"></i> Program Selection
                    </a>
                    <div class="dropdown-divider"></div>

                    <h6 class="dropdown-header">Client & Sessions</h6>
                    <a class="dropdown-item" href="check_in_step1.php">
                        <i class="fas fa-user-check"></i> Check In
                    </a>
                    <a class="dropdown-item" href="client-index.php">
                        <i class="fas fa-users"></i> Clients
                    </a>
                    <!-- ✱ NEW: Intake packets quick‑view -->
                    <a class="dropdown-item" href="intake-index.php">
                        <i class="fas fa-file-signature"></i> Intake Packets
                    </a>
                    <a class="dropdown-item" href="therapy_session-index.php">
                        <i class="fas fa-clipboard-list"></i> Sessions
                    </a>
                    <a class="dropdown-item" href="case_manager-index.php">
                        <i class="fas fa-user-tie"></i> Case Managers
                    </a>
                    <a class="dropdown-item" href="curriculum-index.php">
                        <i class="fas fa-book"></i> Curriculum
                    </a>

                    <div class="dropdown-divider"></div>
                    <h6 class="dropdown-header">Reporting</h6>

                    <!-- Report Generator above Export CSV -->
                    <a class="dropdown-item" href="reportgen.php">
                        <i class="fas fa-print"></i> Report Generator
                    </a>

                    <!-- Renamed "Reporting" to "Export CSV" -->
                    <a class="dropdown-item" href="reporting.php">
                        <i class="fas fa-file-export"></i> Export CSV
                    </a>


                    <?php
                    if ($_SESSION['role'] === 'Admin') {
                        echo '<div class="dropdown-divider"></div>';
                        echo '<h6 class="dropdown-header">Admin Only</h6>';
                        echo '<a class="dropdown-item" href="client-reminders.php">'
                           . '<i class="fas fa-chalkboard"></i> Reminders</a>';
                        echo '<a class="dropdown-item" href="client-event-add.php">'
                           . '<i class="fas fa-bullhorn"></i> Client Events (bulk add)</a>';
                        echo '<a class="dropdown-item" href="client-victim-index.php">'
                            . '<i class="fas fa-user-injured"></i> Victims</a>';

                        echo '<a class="dropdown-item" href="ethnicity-index.php">'
                           . '<i class="fas fa-globe"></i> Ethnicity</a>';
                        echo '<a class="dropdown-item" href="facilitator-index.php">'
                           . '<i class="fas fa-user-graduate"></i> Facilitators</a>';
                        echo '<a class="dropdown-item" href="therapy_group-index.php">'
                           . '<i class="fas fa-users-cog"></i> Groups</a>';
                        echo '<a class="dropdown-item" href="referral_type-index.php">'
                           . '<i class="fas fa-handshake"></i> Referral Types</a>';
                        echo '<a class="dropdown-item" href="./admin">'
                           . '<i class="fas fa-tools"></i> Tools</a>';
                    }
                    ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </li>
        </ul>
    </div>

    <!-- Right side: user info and quick links -->
    <div class="navbar-text mr-3">
        <i class="fas fa-user"></i> <?php echo htmlspecialchars($username); ?>
    </div>
    <a class="nav-link text-light" href="home.php">
        <i class="fas fa-home"></i> Home
    </a>
    <span class="text-light"> | </span>
    <a class="nav-link text-light" href="logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</nav>
