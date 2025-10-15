<?php
include_once 'auth.php';
include_once '../config/config.php';
check_loggedin($con, '../index.php');
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand nav-link disabled" href="#">
        <?php echo $appname . " - " . $_SESSION['program_name']; ?>
    </a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav mr-auto">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Select Page
                </a>
                <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                    <a class="dropdown-item" href="program-select.php">Program Selection</a>
                    <hr class="dropdown-divider">
                    <a class="dropdown-item" href="check_in_step1.php">Check In</a>
                    <a class="dropdown-item" href="client-index.php">Clients</a>
                    <a class="dropdown-item" href="therapy_session-index.php">Sessions</a>
                    <a class="dropdown-item" href="case_manager-index.php">Case Managers</a>
                    <a class="dropdown-item" href="curriculum-index.php">Curriculum</a>
                    <hr class="dropdown-divider">
                    <a class="dropdown-item" href="message_csv.php">Messaging (CSV)</a>
                    <a class="dropdown-item" href="reporting.php">Reporting</a>

                    <?php
                        if ($_SESSION['role'] == 'Admin') {
                            echo '<hr class="dropdown-divider">';
                            echo '<h6 class="dropdown-header">Administrator Only</h6>';
                            echo '<a class="dropdown-item" href="client-event-add.php">Client Events (bulk add)</a>';
                            echo '<a class="dropdown-item" href="ethnicity-index.php">Ethnicity</a>';
                            echo '<a class="dropdown-item" href="facilitator-index.php">Facilitators</a>';
                            echo '<a class="dropdown-item" href="therapy_group-index.php">Groups</a>';
                            echo '<a class="dropdown-item" href="referral_type-index.php">Referral Types</a>';
                            echo '<a class="dropdown-item" href="./admin">Tools</a>';
                        }
                    ?>
                    <!--          <a class="dropdown-item" href="image-index.php">image</a>  -->
                    <hr class="dropdown-divider">
                    <a class="dropdown-item" href="logout.php">Logout</a>

                </div>
            </li>
        </ul>
    </div>
    <h5><?php echo $_SESSION['name']; ?></h5>
    <a class="nav-link" href="logout.php">Logout</a>
</nav>