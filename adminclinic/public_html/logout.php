<?php
session_start();
// Destroy the session associated with the user
session_destroy();

// If the user is remembered, delete the cookie with the correct parameters
if (isset($_COOKIE['rememberme'])) {
    unset($_COOKIE['rememberme']);
    setcookie(
        'rememberme',
        '', 
        time() - 3600, 
        '/',         // Path
        '.notesao.com', // Domain (adjust if needed)
        false,        // Secure, set to true if using HTTPS
        true          // HttpOnly
    );
}

// Redirect to the login page:
header('Location: https://notesao.com/login.php');
exit;
?>
