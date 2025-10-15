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
    <title>Messaging (CSV)</title>
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
