<?php
$appname = 'CTC';

// The main file contains the database connection, session initializing, and functions, other PHP files will depend on this file.
// Include the configuration file
include_once dirname(__FILE__) . '/../config/config.php';

// We need to use sessions, so you should always start sessions using the below function
// Set session cookie parameters and start session
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '.notesao.com', // Allow across subdomains
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connect to the MySQL database using MySQLi
$con = mysqli_connect(db_host, db_user, db_pass, db_name);
// If there is an error with the MySQL connection, stop the script and output the error
if (mysqli_connect_errno()) {
    exit('Failed to connect to MySQL: ' . mysqli_connect_error());
}

// The below function will check if the user is logged-in and also check the remember me cookie
function check_loggedin($con, $redirect_file = 'index.php') {
    global $_SESSION, $appname, $default_program_id;

    // Update the "last seen" column if needed
    if (isset($_SESSION['loggedin'])) {
        $date = date('Y-m-d\TH:i:s');
        $stmt = $con->prepare('UPDATE accounts SET last_seen = ? WHERE id = ?');
        $stmt->bind_param('si', $date, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }

    // Check if the user is logged-in but to a different instance of the app
    if (isset($_SESSION['loggedin']) && (!isset($_SESSION['appname']) || $_SESSION['appname'] != $appname)) {
        session_destroy();
        header('Location: ' . $redirect_file);
        exit;
    }

    // If the 'remember me' cookie is present, authenticate the user
    if (isset($_COOKIE['rememberme']) && !empty($_COOKIE['rememberme']) && !isset($_SESSION['loggedin'])) {
        $stmt = $con->prepare('SELECT id, username, role FROM accounts WHERE rememberme = ?');
        $stmt->bind_param('s', $_COOKIE['rememberme']);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $username, $role);
            $stmt->fetch();
            $stmt->close();

            // Before calling session_regenerate_id(true);
            if (!headers_sent()) {
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['loggedin'] = true;
                $_SESSION['name'] = $username;
                $_SESSION['user_id'] = $id;
                $_SESSION['role'] = $role;
                $_SESSION['appname'] = $appname;

                // Set the default program if not already set
                if (empty($_SESSION['program_id'])) {
                    $_SESSION['program_id'] = $default_program_id;
                }

                // Fetch and set the program name
                $stmt = $con->prepare('SELECT name FROM program WHERE id = ?');
                $stmt->bind_param('i', $_SESSION['program_id']);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($program_name);
                    $stmt->fetch();
                    $_SESSION['program_name'] = $program_name;
                } else {
                    $_SESSION['program_name'] = "Unknown Program id = " . $_SESSION['program_id'];
                }
                $stmt->close();

                // Update the last seen date
                $date = date('Y-m-d\TH:i:s');
                $stmt = $con->prepare('UPDATE accounts SET last_seen = ? WHERE id = ?');
                $stmt->bind_param('si', $date, $id);
                $stmt->execute();
                $stmt->close();
            } else {
                // Log the error and handle appropriately
                error_log("Cannot regenerate session ID because headers have already been sent.");
                // For security, you might destroy the session and redirect
                session_destroy();
                exit('A session error occurred. Please try again.');
            }
        } else {
            header('Location: ' . $redirect_file);
            exit;
        }
    } elseif (!isset($_SESSION['loggedin'])) {
        header('Location: ' . $redirect_file);
        exit;
    }

    // Ensure program_id and program_name are set for logged-in users
    if (!empty($_SESSION['loggedin']) && empty($_SESSION['program_id'])) {
        $_SESSION['program_id'] = $default_program_id;
        $stmt = $con->prepare('SELECT name FROM program WHERE id = ?');
        $stmt->bind_param('i', $_SESSION['program_id']);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($program_name);
            $stmt->fetch();
            $_SESSION['program_name'] = $program_name;
        } else {
            $_SESSION['program_name'] = "Unknown Program id = " . $_SESSION['program_id'];
        }
        $stmt->close();
    }
}
?>
