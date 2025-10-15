<?php
ob_start(); // Start output buffering

// Enable error reporting and logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/home/clinicnotepro/public_html/login.error.log');

/**
 * Map a short clinic name (like "sandbox" or "ffltest")
 * to the clinic's folder AND domain name.
 */
function parse_clinic_folder($clinic_short) {
    // Only short keys allowed now:
    $clinic_map = [
        'sandbox' => [
            'folder' => 'sandbox',
            'domain' => 'sandbox.clinic.notepro.co'
        ],
        'ffl' => [
            'folder' => 'ffltest',
            'domain' => 'ffltest.clinic.notepro.co'
        ],
        'dwag' => [
            'folder' => 'dwag',
            'domain' => 'dwag.clinic.notepro.co'
        ],
        'transform' => [
            'folder' => 'transform',
            'domain' => 'transform.clinic.notepro.co'
        ],
        'saf' => [
            'folder' => 'safatherhood',
            'domain' => 'safatherhood.clinic.notepro.co'
        ],
        'ctc' => [
            'folder' => 'ctc',
            'domain' => 'ctc.clinic.notepro.co'
        ]
    ];

    // Return the array (folder + domain) if found, otherwise null
    return $clinic_map[$clinic_short] ?? null;
}

// Handle 'remember me' cookie if present, but only after determining the clinic
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_COOKIE['rememberme'])) {
    $clinic_folder = '';
    $clinic_domain = '';

    // Determine the clinic folder/domain based on POST or session
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Capture form input values
        $username       = $_POST['username'] ?? '';
        $password       = $_POST['password'] ?? '';
        $entered_clinic = $_POST['clinic']   ?? '';

        if (!$entered_clinic) {
            exit('Please enter a clinic.');
        }

        // Dynamically determine the clinic folder and domain
        $clinic_info = parse_clinic_folder($entered_clinic);

        if (!$clinic_info) {
            error_log("Invalid clinic entered: $entered_clinic");
            exit('Invalid clinic. Please enter a valid short name (e.g. "sandbox").');
        }

        // We got back something like: ['folder' => 'sandbox', 'domain' => 'sandbox.clinic.notepro.co']
        $clinic_folder = $clinic_info['folder'];
        $clinic_domain = $clinic_info['domain'];

        // Store in session so the code can reuse
        $_SESSION['clinic_folder'] = $clinic_folder;
        $_SESSION['clinic_domain'] = $clinic_domain;
        
    } elseif (isset($_COOKIE['rememberme']) && isset($_SESSION['clinic_folder'], $_SESSION['clinic_domain'])) {
        // 'Remember me' scenario
        error_log("Remember me cookie detected.");

        $clinic_folder = $_SESSION['clinic_folder'];
        $clinic_domain = $_SESSION['clinic_domain'];
    }

    // Include the correct auth.php file for the selected clinic folder
    if ($clinic_folder) {
        $auth_file_path = "/home/clinicnotepro/$clinic_folder/public_html/auth.php";
        if (file_exists($auth_file_path) && is_readable($auth_file_path)) {
            include_once $auth_file_path;
            error_log("Auth file included successfully from: $auth_file_path");
        } else {
            error_log("Configuration for this clinic is not available at path: $auth_file_path");
            exit("Configuration for this clinic is not available.");
        }

        // At this point, auth.php has started the session and set up $con

        // Authenticate user based on POST request
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $stmt = $con->prepare('SELECT id, password FROM accounts WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $hashed_password);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    // Successful login
                    session_regenerate_id(true);
                    $_SESSION['loggedin']  = true;
                    $_SESSION['user_id']   = $id;
                    $_SESSION['username']  = $username;

                    error_log("Login successful. Session set with user ID: " . $_SESSION['user_id']);

                    // Set "remember me" cookie if checked
                    if (isset($_POST['rememberme'])) {
                        $cookiehash = password_hash($id . $username . 'your_secret_key', PASSWORD_DEFAULT);
                        setcookie('rememberme', $cookiehash, time() + 86400 * 30, '/', '.notepro.co', false, true);
                        $stmt = $con->prepare('UPDATE accounts SET rememberme = ? WHERE id = ?');
                        $stmt->bind_param('si', $cookiehash, $id);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        $stmt->close();
                    }

                    // Redirect to the clinic's domain/home.php
                    header("Location: https://$clinic_domain/home.php");
                    ob_end_flush();
                    exit();
                } else {
                    error_log('Incorrect username or password.');
                    echo 'Incorrect username or password.<br>';
                }
            } else {
                error_log("No such user found.");
                echo 'Incorrect username or password.<br>';
            }
            $stmt->close();
            // Not closing $con in case it's needed
        }

        // Authenticate user based on 'rememberme' cookie
        if (isset($_COOKIE['rememberme']) && $clinic_folder) {
            $stmt = $con->prepare('SELECT id, username, role FROM accounts WHERE rememberme = ?');
            $stmt->bind_param('s', $_COOKIE['rememberme']);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id, $username, $role);
                $stmt->fetch();
                session_regenerate_id(true);
                $_SESSION['loggedin']  = true;
                $_SESSION['user_id']   = $id;
                $_SESSION['username']  = $username;
                $_SESSION['role']      = $role;

                error_log("Remembered user logged in. Session ID: " . $_SESSION['user_id']);
                header("Location: https://$clinic_domain/home.php");
                exit();
            }
            $stmt->close();
        }
    } else {
        error_log("Clinic folder is missing. Please enter a valid short clinic name.");
    }
}

// ====================
// HTML Login Form
// ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NotePro Login</title>
    <link rel="icon" href="NoteProLogoFinal.ico" type="image/x-icon">
    <style type="text/css">
        body { 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            font-family: Arial, Helvetica, sans-serif; 
            background: linear-gradient(to bottom right, #f0f4f8, #d9e4ea); 
            text-align: center; 
        }
        .container { 
            background: #ffffff; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2); 
        }
        img { 
            max-width: 150px; 
            margin-bottom: 20px; 
            cursor: pointer; 
        }
        button { 
            padding: 15px 25px; 
            font-size: 18px; 
            font-weight: bold; 
            color: #ffffff; 
            background-color: #007BFF; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            transition: background-color 0.3s, transform 0.2s; 
        }
        button:hover { 
            background-color: #0056b3; 
            transform: scale(1.05); 
        }
    </style>
</head>
<body>
<div class="container">
    <a href="https://notepro.co">
        <img alt="NotePro Logo" src="logo.png" />
    </a>
    <h2>Login</h2>
    
    <form action="" method="post">
        <label for="username">Username:</label><br>
        <input type="text" name="username" id="username" required><br><br>

        <label for="password">Password:</label><br>
        <input type="password" name="password" id="password" required><br><br>

        <!-- Only short name allowed: "sandbox" or "ffltest" -->
        <label for="clinic">Clinic:</label><br>
        <input 
            type="text" 
            name="clinic" 
            id="clinic" 
            placeholder="e.g. sandbox" 
            required
        ><br><br>

        <!-- Hidden "remember me" always set to 1 -->
        <input type="hidden" name="rememberme" id="rememberme" value="1">

        <button type="submit">Login</button>
    </form>

</div>
</body>
</html>
