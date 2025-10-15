<?php
require_once "../config/config.php";
require_once "helpers.php";
require_once "sql_functions.php";

function markCompletedClients()
{
    global $link;

    // For clients - Not exited with attended sessions >= required sessions.
    
    $sql = "SELECT id, last_attendance.date last_attendance from client c 
        LEFT JOIN (SELECT ar.client_id client_id, MAX(ts.date) date FROM attendance_record ar LEFT JOIN therapy_session ts ON ar.therapy_session_id = ts.id GROUP BY ar.client_id) as last_attendance ON c.id = last_attendance.client_id
        LEFT JOIN (SELECT ar.client_id client_id, count(ar.client_id) count FROM attendance_record ar GROUP BY ar.client_id) as sessions_attended ON c.id = sessions_attended.client_id
        where c.exit_date is null and c.exit_reason_id = (select id from exit_reason where reason = 'Not Exited') and c.required_sessions <= sessions_attended.count";
    if ($stmt = mysqli_prepare($link, $sql)) {
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    exitClient($row["id"], $row["last_attendance"]);
                }
            } else {
                echo "ERROR <br>" . $stmt->error . "<br>";
            }
        } else {
            echo "ERROR <br>" . $stmt->error . "<br>";
        }
    }
}

function exitClient($client_id, $date)
{
    $dsn = "mysql:host=" . db_host . ";dbname=" . db_name . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_EMULATE_PREPARES   => false, // turn off emulation mode for "real" prepared statements
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
    ];
    try {
        $pdo = new PDO($dsn, db_user, db_pass, $options);
    } catch (Exception $e) {
        error_log($e->getMessage());
        exit('Error connecting to DB'); //something a user can understand
    }

    $vars = parse_columns('attendance_record', $_POST);
    $stmt = $pdo->prepare("UPDATE client set exit_date = ?, exit_reason_id = (select id from exit_reason where reason = 'Completion of Program' ) where id = ?");
echo "Marking client " . $client_id . " exited (complete) on " .  $date . "<br>";

    if ($stmt->execute([$date, $client_id])) {
        insertClientEvent($pdo, $client_id);
         $stmt = null;
    } else {
         echo "Error marking client exited";
    }
}

function insertClientEvent($pdo, $client_id)
{
    $stmt = $pdo->prepare("INSERT INTO client_event (client_id, client_event_type_id, date, note) select ?, (SELECT id FROM client_event_type where event_type = 'Other'), now(), 'Marked Exited by automatic completion logic'");
    if ($stmt->execute([$client_id])) {
        $stmt = null;
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>NotesAO - Completion Clients</title>
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
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>

<body>
    <?php markCompletedClients(); ?>
</body>