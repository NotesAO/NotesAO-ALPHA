<?php
include_once 'auth.php';
global $appname;

// Redirect logged-in users to the home page
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: home.php');
    exit;
}

// Check if they are "remembered"
if (isset($_COOKIE['rememberme']) && !empty($_COOKIE['rememberme'])) {
    $stmt = $con->prepare('SELECT id, username, role FROM accounts WHERE rememberme = ?');
    $stmt->bind_param('s', $_COOKIE['rememberme']);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $username, $role);
        $stmt->fetch();
        $stmt->close();

        session_regenerate_id(true);  // Security: regenerate session ID
        $_SESSION['loggedin'] = true;
        $_SESSION['name'] = $username;
        $_SESSION['id'] = $id;
        $_SESSION['role'] = $role;
        $_SESSION['appname'] = $appname;

        // Update last seen date
        $date = date('Y-m-d\TH:i:s');
        $stmt = $con->prepare('UPDATE accounts SET last_seen = ? WHERE id = ?');
        $stmt->bind_param('si', $date, $id);
        $stmt->execute();
        $stmt->close();

        // Redirect to home page
        header('Location: home.php');
        exit;
    }
    $stmt->close();
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: https://notesao.com/login.php');
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1">
    <title>NotesAO - Login</title>
    <!-- FAVICON LINKS (from index.html) -->
    <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">

    <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">

    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="apple-touch-icon" sizes="167x167" href="/favicons/apple-touch-icon-ipad-pro.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-touch-icon-ipad.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-touch-icon-120x120.png">

    <link rel="manifest" href="/favicons/site.webmanifest">
    <meta name="apple-mobile-web-app-title" content="NotesAO">
    <link href="style.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css" integrity="sha512-xh6O/CkQoPOWDdYTDqeRdPCVd1SpvCA9XXcUnZS2FmJNp1coAFzvtCN9BmamE+4aHK8yyUHUSCcJHgXloTyT2A==" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body>
    <div class="login">
        <h1><?php echo htmlspecialchars($appname);?></h1>
        <div class="links">
            <a href="index.php" class="active">Login</a>
        </div>

        <form action="authenticate.php" method="post">
            <label for="username">
                <i class="fas fa-user"></i>
            </label>
            <input type="text" name="username" placeholder="Username" id="username" required>

            <label for="password">
                <i class="fas fa-lock"></i>
            </label>
            <input type="password" name="password" placeholder="Password" id="password" required>

            <label id="rememberme">
                <input type="checkbox" name="rememberme"> Remember me
            </label>
            
            <div class="msg"></div>
            <input type="submit" value="Login">
        </form>
    </div>

    <script>
    // AJAX login form submission
    let loginForm = document.querySelector('.login form');
    loginForm.onsubmit = event => {
        event.preventDefault();
        fetch(loginForm.action, { method: 'POST', body: new FormData(loginForm) }).then(response => response.text()).then(result => {
            if (result.toLowerCase().includes('success')) {
                window.location.href = 'home.php';
            } else {
                document.querySelector('.msg').innerHTML = result;
            }
        });
    };
    </script>
</body>
</html>
