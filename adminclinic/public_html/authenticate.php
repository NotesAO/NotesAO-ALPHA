<?php
include 'auth.php';

error_log("Authenticate script accessed."); // Log access to authenticate

// Check if login data is received
if (!isset($_POST['username'], $_POST['password'])) {
    error_log("Login data incomplete."); // Log missing data
    exit('Please fill both the username and password fields!');
}

// Prepare SQL to prevent SQL injection
$stmt = $con->prepare('SELECT id, password, rememberme, activation_code, role, username FROM accounts WHERE username = ?');
$stmt->bind_param('s', $_POST['username']);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Bind results
    $stmt->bind_result($id, $password, $rememberme, $activation_code, $role, $username);
    $stmt->fetch();
    $stmt->close();

    // Verify password
    if (password_verify($_POST['password'], $password)) {
        if (account_activation && $activation_code != 'activated') {
            echo 'Please activate your account to login! Click <a href="resend-activation.php">here</a> to resend the activation email.';
        } else {
            session_regenerate_id(true);
            $_SESSION['loggedin'] = true;
            $_SESSION['name'] = $username;
            $_SESSION['id'] = $id;
            $_SESSION['role'] = $role;
            $_SESSION['appname'] = $appname ?? 'Default App';
            $_SESSION['program_id'] = $default_program_id ?? 1;

            error_log("Login successful for user ID: $id");

            // Fetch and set program name
            $stmt = $con->prepare('SELECT name FROM program WHERE id = ?');
            $stmt->bind_param('i', $_SESSION['program_id']);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($program_name);
                $stmt->fetch();
                $_SESSION['program_name'] = $program_name;
            } else {
                $_SESSION['program_name'] = "Unknown Program id=" . $_SESSION['program_id'];
            }
            $stmt->close();

            // Set "remember me" cookie
            if (isset($_POST['rememberme'])) {
                $cookiehash = $rememberme ?: password_hash($id . $username . 'yoursecretkey', PASSWORD_DEFAULT);
                setcookie('rememberme', $cookiehash, time() + 86400 * 30, '/', '.notesao.com', false, true);
                $stmt = $con->prepare('UPDATE accounts SET rememberme = ? WHERE id = ?');
                $stmt->bind_param('si', $cookiehash, $id);
                $stmt->execute();
                $stmt->close();
            }

            // Update last seen
            $date = date('Y-m-d\TH:i:s');
            $stmt = $con->prepare('UPDATE accounts SET last_seen = ? WHERE id = ?');
            $stmt->bind_param('si', $date, $id);
            $stmt->execute();
            $stmt->close();

            echo 'Success'; // Output for AJAX
        }
    } else {
        error_log("Incorrect password for username: {$_POST['username']}"); // Log incorrect password
        echo 'Incorrect email and/or password!';
    }
} else {
    error_log("No account found for username: {$_POST['username']}"); // Log incorrect username
    echo 'Incorrect email and/or password!';
}
?>
