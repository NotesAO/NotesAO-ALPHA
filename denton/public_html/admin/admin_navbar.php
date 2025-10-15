<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand nav-link disabled" href="#">
        <?php echo $appname . " - Administrative Tools" ?>
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
                    <a class="dropdown-item" href="activity-reporting.php">Activity Reporting</a>
                    <a class="dropdown-item" href="client_file_update.php">Client Update Util</a>
                    <a class="dropdown-item" href="accounts.php">User Accounts</a>
                    <hr class="dropdown-divider">
                    <a class="dropdown-item" href="..">Return to App</a>
                    <hr class="dropdown-divider">
                    <a class="dropdown-item" href="../logout.php">Logout</a>
                </div>
            </li>
        </ul>
    </div>
    <h5><?php echo $_SESSION['name']; ?></h5>
    <a class="nav-link" href="logout.php">Logout</a>
</nav>