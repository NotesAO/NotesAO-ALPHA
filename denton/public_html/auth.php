<?php
// The main file contains the database connection, session initializing, and functions, other PHP files will depend on this file.
// Include the configuration file
include_once dirname(__FILE__) . '/../config/config.php';

// We need to use sessions, so you should always start sessions using the below function
session_start();
// Connect to the MySQL database using MySQLi
$con = mysqli_connect(db_host, db_user, db_pass, db_name);
// If there is an error with the MySQL connection, stop the script and output the error
if (mysqli_connect_errno()) {
	exit('Failed to connect to MySQL: ' . mysqli_connect_error());
}

// The below function will check if the user is logged-in and also check the remember me cookie
function check_loggedin($con, $redirect_file = 'index.php') {
	// If you want to update the "last seen" column on every page load, you can uncomment the below code
	/*
	if (isset($_SESSION['loggedin'])) {
		$date = date('Y-m-d\TH:i:s');
		$stmt = $con->prepare('UPDATE accounts SET last_seen = ? WHERE id = ?');
		$stmt->bind_param('si', $date, $id);
		$stmt->execute();
		$stmt->close();
	}
	*/

    if(isset($_SESSION['loggedin'])) {
        global $appname;
        if(!isset($_SESSION['appname']) || $_SESSION['appname'] != $appname) {
            // The user is logged-in but to a different instance of the app!
            session_destroy();
            header('Location: ' . $redirect_file);
            exit;
        }
    }

	// Check for remember me cookie variable and loggedin session variable
    if (isset($_COOKIE['rememberme']) && !empty($_COOKIE['rememberme']) && !isset($_SESSION['loggedin'])) {
    	// If the remember me cookie matches one in the database then we can update the session variables.
    	$stmt = $con->prepare('SELECT id, username, role FROM accounts WHERE rememberme = ?');
		$stmt->bind_param('s', $_COOKIE['rememberme']);
		$stmt->execute();
		$stmt->store_result();
		// If there are results
		if ($stmt->num_rows > 0) {
			// Found a match, update the session variables and keep the user logged-in
			$stmt->bind_result($id, $username, $role);
			$stmt->fetch();
            $stmt->close();
			// Regenerate session ID
			session_regenerate_id();
			// Declare session variables; authenticate the user
			$_SESSION['loggedin'] = TRUE;
			$_SESSION['name'] = $username;
			$_SESSION['id'] = $id;
			$_SESSION['role'] = $role;
			$_SESSION['appname'] = $appname;
            global $default_program_id;
            $_SESSION['program_id'] = $default_program_id;
			$stmt = $con->prepare('SELECT name from program where id = ?');
			$stmt->bind_param('i', $default_program_id);
			$stmt->execute();
            $stmt->store_result();            
            if ($stmt->num_rows > 0) {
                $program_name = "";
                $stmt->bind_result($program_name);
                $stmt->fetch();
                $_SESSION['program_name'] = $program_name;
            }
            else {
                $_SESSION['program_name'] = "Unknown Program id =" + $default_program_id;
            }
            $stmt->close();

			// Update last seen date
			$date = date('Y-m-d\TH:i:s');
			$stmt = $con->prepare('UPDATE accounts SET last_seen = ? WHERE id = ?');
			$stmt->bind_param('si', $date, $id);
			$stmt->execute();
			$stmt->close();
		} else {
			// If the user is not remembered, redirect to the login page.
            $stmt->close();
			header('Location: ' . $redirect_file);
			exit;
		}
    } else if (!isset($_SESSION['loggedin'])) {
    	// If the user is not logged-in, redirect to the login page.
    	header('Location: ' . $redirect_file);
    	exit;
    }
}
?>