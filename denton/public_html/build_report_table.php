<?php
    include_once 'auth.php';
    check_loggedin($con);
    require_once "helpers.php";
    require_once "sql_functions.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>buildcsv</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<body>
<section class="pt-5">

<?php
    $start_time = microtime(true);

    truncate_table("report");
    populate_report();

    $client_count = 0;
    // Loop through and populate the attendance data for each clientID
    global $link;
    $sql = "SELECT client_id from report";
    if($stmt = mysqli_prepare($link, $sql)){
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_array($result)){
                    $client_count++;
                    populate_report_client_attendance($row["client_id"]);
                }
            } else{
                echo "ERROR <br>".$stmt->error . "<br>";
            }
        } else{
            echo "ERROR <br>".$stmt->error . "<br>";
        }
    }
    // Close statement
    mysqli_stmt_close($stmt);

    $end_time = microtime(true);
    echo "Populated data for " . $client_count . " clients in " . ($end_time - $start_time) . " sec";    
?>

<a href="reporting.php" class="btn btn-primary">Return to Reporting</a>

</section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
    <script type="text/javascript">
        $(document).ready(function(){
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>

