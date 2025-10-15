<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    // Not logged in, redirect to login page
    header("Location: /login.php");
    exit();
}
// User is authenticated; continue loading the page
?>
