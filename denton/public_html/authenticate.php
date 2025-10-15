<?php
include 'auth.php';
// Now we check if the data from the login form was submitted, isset() will check if the data exists
if (!isset($_POST['username'], $_POST['password'])) {
	// Could not retrieve the captured data, output error
	exit('Please fill both the username and password fields!');
}
// Prepare our SQL query and find the account associated with the login details
// Preparing the SQL statement will prevent SQL injection
$stmt = $con->prepare('SELECT id, password, rememberme, activation_code, role, username FROM accounts WHERE username = ?');
// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string and therefore we specify "s"
$stmt->bind_param('s', $_POST['username']);
$stmt->execute();
$stmt->store_result();
// Check if the account exists:
if ($stmt->num_rows > 0) {
	// Bind results
	$stmt->bind_result($id, $password, $rememberme, $activation_code, $role, $username);
	$stmt->fetch();
	$stmt->close();
	// Account exists... Verify the form password
	if (password_verify($_POST['password'], $password)) {
		// Check if the account is activated
		if (account_activation && $activation_code != 'activated') {
			// User has not activated their account, output the message
			echo 'Please activate your account to login! Click <a href="resend-activation.php">here</a> to resend the activation email.';
		} else {
			// Verification success! User has loggedin!
			// Declare the session variables, which will basically act like cookies, but will store the data on the server as opposed to the client
			session_regenerate_id();
			$_SESSION['loggedin'] = TRUE;
			$_SESSION['name'] = $username;
			$_SESSION['id'] = $id;
			$_SESSION['role'] = $role;
            global $appname;
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

			// IF the "remember me" checkbox is checked...
			if (isset($_POST['rememberme'])) {
				// Generate a hash that will be stored as a cookie and in the database. It will be used to identify the user.
				$cookiehash = !empty($rememberme) ? $rememberme : password_hash($id . $username . 'yoursecretkey', PASSWORD_DEFAULT);
				// The number of days the user will be remembered
				$days = 30;
				// Create the cookie
				setcookie('rememberme', $cookiehash, (int)(time()+60*60*24*$days));
				// Update the "rememberme" field in the accounts table with the new hash
				$stmt = $con->prepare('UPDATE accounts SET rememberme = ? WHERE id = ?');
				$stmt->bind_param('si', $cookiehash, $id);
				$stmt->execute();
				$stmt->close();
			}
			// Update last seen date
			$date = date('Y-m-d\TH:i:s');
			$stmt = $con->prepare('UPDATE accounts SET last_seen = ? WHERE id = ?');
			$stmt->bind_param('si', $date, $id);
			$stmt->execute();
			$stmt->close();
			// Output msg; do not change this line as the AJAX code depends on it
			echo 'Success'; 
		}
	} else {
		// Incorrect password
		echo 'Incorrect email and/or password!';
	}
} else {
	// Incorrect email
	echo 'Incorrect email and/or password!';
}
?>
