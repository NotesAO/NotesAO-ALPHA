<?php
include_once '../auth.php';
check_loggedin($con);

// Get the account info using the logged-in session ID
$stmt = $con->prepare('SELECT password, email, role, username FROM accounts WHERE id = ?');
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($password, $email, $role, $username);
$stmt->fetch();
$stmt->close();
if ($role != 'Admin') {
    exit('You do not have permission to access this page!');
}

// Include config file
require_once "../../config/config.php";
require_once "../helpers.php";
require_once "../sql_functions.php";

// mktime(hour, minute, second, month, day, year, is_dst)
$start_date = getParam('start_date', date("Y-m-d", mktime(0, 0, 0, date("m"), 1, date("Y"))));
$end_date = getParam('end_date', date("Y-m-d", mktime(0, 0, 0, date("m") + 1, 0)));

$attendanceByProgram = "
select program, DATE_FORMAT(min(subq.session_date),'%M %d %Y') week, sum(ifnull(client_count, 0)) count   
from (
   select program.name program, therapy_group.name therapy_group, therapy_session.date session_date, clients_per_session.client_count
    from 
        therapy_session
        left join therapy_group on therapy_session.therapy_group_id = therapy_group.id
        left join program on therapy_group.program_id = program.id
        join (select therapy_session_id, count(*) client_count from attendance_record group by therapy_session_id ) clients_per_session on clients_per_session.therapy_session_id = therapy_session.id
    WHERE
    therapy_session.date between ? and ? 
    order by therapy_session.date DESC, program, therapy_group
) as subq
group by program, yearweek(subq.session_date)
	";
    $resultarray = [];
    if ($stmt = mysqli_prepare($link, $attendanceByProgram)) {
        $st = $start_date . " 00:00:00";
        $ed = $end_date . " 23:59:59";
        mysqli_stmt_bind_param($stmt, "ss", $st , $ed);
    }
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $resultarray = $result->fetch_all(MYSQLI_ASSOC);
    }
    mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Activity Report</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<?php require_once('admin_navbar.php'); ?>

<body>
    <section class="pt-2">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <div class="page-header">
                        <h2>Activity Reporting</h2>
                    </div>
                </div>
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <hr>
                    <div class="row bg-light">
                        <div class="col-6">
                            <h4>Data Extract</h4>
                        </div>
                    </div>
                    <div class="row bg-light">
                        <div class="col-2">
                            <small class="text-muted">Start Date</small>
                            <input type="date" name="start_date" class="form-control" value="<?php echo date("Y-m-d", strtotime($start_date)); ?>">
                        </div>
                        <div class="col-2">
                            <small class="text-muted">End Date</small>
                            <input type="date" name="end_date" class="form-control" value="<?php echo date("Y-m-d", strtotime($end_date)); ?>">
                        </div>
                        <div class="col-auto align-self-end">
                            <input type="submit" class="btn btn-success" name="action" value="Set Values">
                        </div>
                    </div>
                    <div class="row bg-light">
                        <div class="col-2">
                            <small class="text-muted">Start Date</small>
                            <h5><?php echo $start_date; ?></h5>
                        </div>
                        <div class="col-2">
                            <small class="text-muted">End Date</small>
                            <h5><?php echo $end_date; ?></h5>
                        </div>
                    </div>
                    <div class="row bg-light pb-3">
                        <div class="col-auto align-self-end">
                            <?php echo '<a class="btn btn-primary" href="activity-reporting-dumpcsv.php?file=attendance&start_date=' . $start_date . '&end_date=' . $end_date . '">Attendance</a>' ?>
                        </div>
                        <div class="col-auto align-self-end">
                            <?php echo '<a class="btn btn-primary" href="activity-reporting-dumpcsv.php?file=clients&start_date=' . $start_date . '&end_date=' . $end_date . '">Clients</a>' ?>
                        </div>
                        <div class="col-auto align-self-end">
                            <?php echo '<a class="btn btn-primary" href="activity-reporting-dumpcsv.php?file=revenue&start_date=' . $start_date . '&end_date=' . $end_date . '">Revenue</a>' ?>
                        </div>
                    </div>
                </div>
            </form>
            <hr>

            <div class="row">
                <div class="col-6">
                    <h4>Attendance by Program</h4>
                </div>
            </div>
            <div class="row">
                <table class='table table-bordered table-striped'>
                        <?php
                        echo '<thead><tr>';
                        $program = "";
                        $first = true;
                        foreach ($resultarray as $row) {
                            if($first){
                                $first = false;
                                $program = $row['program'];
                                echo '<th>Program</th>';
                            }
                            if($program == $row['program']){
                                echo '<th>' . $row['week'] . '</th>'; 
                            }
                        }
                        echo '</tr></thead>';


                            $program = "";
                            $first = true;
                            foreach ($resultarray as $row) {
                                if($program <> $row['program']){
                                    if($first){
                                        $first = false;
                                    }
                                    else {
                                        echo '</tr>'; 
                                    }
                                    $program = $row['program'];
                                    echo '<tr>';
                                    echo '<td>' . $program . '</td>'; 
                                }
                                echo '<td>' . $row['count'] . '</td>'; 
                            }
                            echo '</tr>'; 
                        ?>

                    <tr>
                        <td><b>Totals</b></td>
                    </tr>
                </table>
            </div>

            <div class="row">
                <div class="col-6">
                    <h4>Clients added by Week (last 8 weeks)</h4>
                </div>
            </div>

            <div class="row">
                <div class="col-6">
                    <h4>Revenue by Week (last 8 weeks)</h4>
                </div>
            </div>

            <div class="col-auto align-self-end">
                <a href="index.php" class="btn btn-dark">Cancel</a>
            </div>

        </div>
    </section>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>

</html>