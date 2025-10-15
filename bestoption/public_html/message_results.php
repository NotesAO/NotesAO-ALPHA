<?php
    include_once 'auth.php';
    check_loggedin($con);

    require_once "../config/config.php";
    require_once "helpers.php";
    
    $messageData = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Messaging</title>
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
<?php require_once('navbar.php'); ?>
<body>
    <section class="pt-2">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <div class="page-header">
                        <h2>Messaging Results</h2>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <?php
                            echo "<table class='table table-bordered table-striped'>";
                            echo "<thead>";
                            // Headder Row
                            echo "<tr>";
                            echo "<th>number</th>";
                            echo "<th>name</th>";
                            echo "<th>message_id</th>";
                            echo "</tr>";
                            echo "</thead>";
                            echo "<tbody>";
                            foreach($messageData as $row) {
                                echo "<tr>";
                                foreach($row as $value){
                                    echo "<td>$value</td>";
                                }
                                echo "</tr>";
                            }
                            echo "</tbody>";
                            echo "</table>";
                        ?>
                </div>
            </div>
            <div class="row">
                <div class="col-1">
                    <a href="index.php" class="btn btn-dark">Done</a>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
