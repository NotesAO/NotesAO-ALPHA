<?php
include_once 'auth.php';
check_loggedin($con);

    // Include config file
    require_once "../config/config.php";
    require_once "helpers.php";

    $threshold = 7;
    if (isset($_GET['threshold'])) {
        $threshold = $_GET['threshold'];
    }
    if(isset($_POST['threshold']) && !empty($_POST['threshold'])){
        $threshold = trim($_POST["threshold"]);
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Truant Client Report</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<?php require_once('navbar.php'); ?>
<body>
    <section class="pt-2">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <div class="page-header">
                        <h2>Truant Client Report</h2>
                    </div>
                </div>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
            <div class="form-group">
            <div class="row">
                <div class="col">
                    <h6>This report lists clients with no exit date and who have not attended any sesions in (threshold) days.</h6>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                    <small class="text-muted">Threshold (days)</small>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                    <input type="number" name="threshold" class="form-control" value="<?php echo $threshold; ?>">
                </div>
                <div class="col-1">
                    <input type="submit" class="btn btn-success" name="action" value="Generate">
                </div>
                <div class="col-1">
                    <a href="index.php" class="btn btn-dark">Cancel</a>
                </div>
            </div>
            </div>
            <br>
            </form>
            <?php if (isset($_POST['action']) && $_POST['action'] == 'Generate'): ?>
                <div class="row">
                <div class="col">
                <table class='table table-bordered table-striped'>
                <thead>
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Phone Number</th>
                        <th>Orientation Date</th>
                        <th>Program</th>
                        <th>Group</th>                        
                        <th>Attended / Required</th>
                        <th>Last Seen</th>
                        <th>Days Elapsed</th>
                        <th>Review</th>
                    </tr>
                </thead>

                <?php
                    // Orientation occured before start of month and not exited or exited after start of month
                    $sql = "SELECT c.id, first_name, last_name, phone_number, p.name program, tg.name group_name, orientation_date, sessions_attended, c.required_sessions, last_seen, datediff(now(), last_seen) elapsed
                    from client c 
                    JOIN program p ON c.program_id = p.id
                    LEFT OUTER JOIN therapy_group tg ON c.therapy_group_id = tg.id
                    LEFT OUTER JOIN (select ar.client_id client_id, count(ar.client_id) sessions_attended from attendance_record ar group by ar.client_id) as client_total_attendance ON 
                    c.id = client_total_attendance.client_id
                    LEFT OUTER JOIN (select ar.client_id client_id, max(ts.date) last_seen from attendance_record ar left join therapy_session ts on ar.therapy_session_id = ts.id group by ar.client_id) as client_last_attendance ON 
                    c.id = client_last_attendance.client_id
                    WHERE 
                    c.exit_date is null
                    HAVING last_seen < (NOW() - INTERVAL ? DAY) 
                    order by last_name";

                    global $link;
                    if($stmt = mysqli_prepare($link, $sql)) {
                        mysqli_stmt_bind_param($stmt, "i", $threshold);
                        if(mysqli_stmt_execute($stmt)) {
                            $result = mysqli_stmt_get_result($stmt);
                            while($row = mysqli_fetch_assoc($result)) {
                                echo "<tr>";
                                echo "<td>" . $row["first_name"] . "</td>";
                                echo "<td>" . $row["last_name"] . "</td>";
                                echo "<td>" . $row["phone_number"] . "</td>";
                                echo "<td>" . $row["orientation_date"] . "</td>";
                                echo "<td>" . $row["program"] . "</td>";
                                echo "<td>" . $row["group_name"] . "</td>";
                                echo "<td>" . $row["sessions_attended"] . " / " . $row["required_sessions"] . "</td>";
                                echo "<td>" . $row["last_seen"] . "</td>";
                                echo "<td>" . $row["elapsed"] . "</td>";
                                echo "<td><a href='client-review.php?client_id=". $row["id"] . "' >Client Review</a></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "Error in SQL: ".$stmt->error;
                        }
                    }
                    mysqli_stmt_close($stmt);                    
                ?>

                </table>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>
</html>