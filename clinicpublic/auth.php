<?php
$appname = 'Notepro';
// Include the configuration file for database and other settings
include_once dirname(__FILE__) . '/config.php';

// Connect to the MySQL database
$con = mysqli_connect(db_host, db_user, db_pass, db_name);
if (mysqli_connect_errno()) {
    exit('Failed to connect to MySQL: ' . mysqli_connect_error());
}

// Function to check if the user is logged in
function check_loggedin($con, $redirect_file = '/login.php') {
    // Check if session is set
    if (isset($_SESSION['loggedin']) && $_SESSION['loggedin']) {
        global $appname;
        // Verify if the session matches the app instance
        if (!isset($_SESSION['appname']) || $_SESSION['appname'] != $appname) {
            session_destroy();
            header('Location: ' . $redirect_file);
            exit;
        }
    } else {
        // Handle "remember me" cookie if session is not set
        if (isset($_COOKIE['rememberme']) && !empty($_COOKIE['rememberme']) && !isset($_SESSION['loggedin'])) {
            $stmt = $con->prepare('SELECT id, username, role FROM accounts WHERE rememberme = ?');
            $stmt->bind_param('s', $_COOKIE['rememberme']);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $username, $role);
                $stmt->fetch();
                session_regenerate_id();
                $_SESSION['loggedin'] = true;
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION['appname'] = $appname;

                // Update last seen date
                $date = date('Y-m-d\TH:i:s');
                $stmt_lastseen = $con->prepare('UPDATE accounts SET last_seen = ? WHERE id = ?');
                $stmt_lastseen->bind_param('si', $date, $id);
                $stmt_lastseen->execute();
                $stmt_lastseen->close();
            } else {
                $stmt->close();
                header('Location: ' . $redirect_file);
                exit;
            }
            $stmt->close();
        } else {
            header('Location: ' . $redirect_file);
            exit;
        }
    }
}
?>
