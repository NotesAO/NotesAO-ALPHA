<?php
include_once 'auth.php';
check_loggedin($con);

// Include config file
require_once "../config/config.php";
require_once "helpers.php";
require_once "sql_functions.php";

$start_date = date("Y-m-d", mktime(0, 0, 0, date("m") - 1, 1));
if (isset($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}
if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
    $start_date = trim($_POST["start_date"]);
}
$end_date = date("Y-m-d", mktime(0, 0, 0, date("m"), 0));
if (isset($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}
if (isset($_POST['end_date']) && !empty($_POST['end_date'])) {
    $end_date = trim($_POST["end_date"]);
}
if (isset($_POST['program_id']) && !empty($_POST['program_id'])) {
    $program_id = trim($_POST["program_id"]);
}

function buildMARRow($displayText, $sql, $date1, $date2 = null)
{
    global $link;
    $probation = 0;
    $parole = 0;
    $pretrial = 0;
    $other = 0;

    if ($stmt = mysqli_prepare($link, $sql)) {
        if (isset($date2)) {
            mysqli_stmt_bind_param($stmt, "ss", $date1, $date2);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $date1);
        }
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $ref_type = strtolower($row["referral_type"]);
                $count = $row["count"];
                switch ($ref_type) {
                    case 'probation';
                        $probation += $count;
                        break;
                    case 'parole';
                        $parole += $count;
                        break;
                    case 'pretrial';
                        $pretrial += $count;
                        break;
                    default;
                        $other += $count;
                        break;
                }
            }
        } else {
            echo "Error in SQL: " . $stmt->error;
        }
    }
    mysqli_stmt_close($stmt);

    echo "<tr>";
    echo "<td>" . $displayText . "</td>";
    echo "<td>" . $probation . "</td>";
    echo "<td>" . $parole . "</td>";
    echo "<td>" . $pretrial . "</td>";
    echo "<td>" . $other . "</td>";
    echo "<td>" . $probation + $parole + $pretrial + $other . "</td>";
    echo "</tr>";
}

function runCountSql($sql, $date1, $date2 = null)
{
    global $link;
    $count = 0;

    if ($stmt = mysqli_prepare($link, $sql)) {
        if (isset($date2)) {
            mysqli_stmt_bind_param($stmt, "ss", $date1, $date2);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $date1);
        }
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $count = $row["count"];
            }
        } else {
            echo "Error in SQL: " . $stmt->error;
        }
    }
    mysqli_stmt_close($stmt);
    return $count;
}

if (isset($_POST['action']) && $_POST['action'] == 'Generate') {
    global $link;

    $begin_probation = 0;
    $begin_parole = 0;
    $begin_pretrial = 0;
    $begin_other = 0;

    $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE orientation_date is not null and (exit_date is null or exit_date < ?) group by rt.referral_type;";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $start_date);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $ref_type = strtolower($row["referral_type"]);
                $count = $row["count"];
                switch ($ref_type) {
                    case 'probation';
                        $begin_probation += $count;
                        break;
                    case 'parole';
                        $begin_parole += $count;
                        break;
                    case 'pretrial';
                        $begin_pretrial += $count;
                        break;
                    default;
                        $begin_other += $count;
                        break;
                }
            }
        } else {
            echo "Error in SQL: " . $stmt->error;
        }
    }
    mysqli_stmt_close($stmt);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Monthly Activity Report</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<?php require_once('navbar.php'); ?>

<body>
    <section class="pt-2">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <div class="page-header">
                        <h2>Monthly Activity Report</h2>
                    </div>
                </div>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <div class="row">
                        <div class="col-auto">
                            <small class="text-muted">Start Date</small>
                            <input type="date" name="start_date" class="form-control" value="<?php echo date("Y-m-d", strtotime($start_date)); ?>">
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">End Date</small>
                            <input type="date" name="end_date" class="form-control" value="<?php echo date("Y-m-d", strtotime($end_date)); ?>">
                        </div>
                        <div class="col-auto">
                            <small class="text-muted">Program</small>
                            <select class="form-control" id="program_id" name="program_id">
                                <?php
                                $programs = get_programs();
                                foreach ($programs as $program) {
                                    $value = htmlspecialchars($program["name"]);
                                    if ($program["id"] == $program_id) {
                                        echo '<option value="' . "$program[id]" . '"selected="selected">' . "$value" . '</option>';
                                    } else {
                                        echo '<option value="' . "$program[id]" . '">' . "$value" . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-auto align-self-end">
                            <input type="submit" class="btn btn-success" name="action" value="Generate">
                        </div>
                        <div class="col-auto align-self-end">
                            <a href="index.php" class="btn btn-dark">Cancel</a>
                        </div>
                    </div>
                </div>
                <br>
            </form>
            <?php if (isset($_POST['action']) && $_POST['action'] == 'Generate') : ?>
                <div class="row">
                    <div class="col-6">
                        <table class='table table-bordered table-striped'>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Probation</th>
                                    <th>Parole</th>
                                    <th>Pretrial</th>
                                    <th>Other</th>
                                    <th>Total</th>
                                </tr>
                            </thead>

                            <?php
                            // Orientation occured before start of month and not exited or exited after start of month
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and (orientation_date < ?) and (exit_date is null or exit_date > ?) group by rt.referral_type;";
                            buildMARRow("Begining of Month Counts", $sql, $start_date, $start_date);
                            ?>

                            <tr>
                                <td>Total Referrals</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>

                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and orientation_date between ? and ? group by rt.referral_type;";
                            buildMARRow("New Placements", $sql, $start_date, $end_date);
                            ?>

                            <tr>
                                <td>Total Served</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>

                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join exit_reason er on c.exit_reason_id = er.id left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and exit_date between ? and ? and er.reason = 'Completion of Program' group by rt.referral_type;";
                            buildMARRow("Completion of Program", $sql, $start_date, $end_date);
                            ?>

                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join exit_reason er on c.exit_reason_id = er.id left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and exit_date between ? and ? and er.reason = 'Violation of Requirements' group by rt.referral_type;";
                            buildMARRow("Violation of Requirements", $sql, $start_date, $end_date);
                            ?>

                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join exit_reason er on c.exit_reason_id = er.id left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and exit_date between ? and ? and er.reason = 'Unable to Participate' group by rt.referral_type;";
                            buildMARRow("Unable to Participate", $sql, $start_date, $end_date);
                            ?>

                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join exit_reason er on c.exit_reason_id = er.id left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and exit_date between ? and ? and er.reason = 'Death' group by rt.referral_type;";
                            buildMARRow("Death", $sql, $start_date, $end_date);
                            ?>

                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join exit_reason er on c.exit_reason_id = er.id left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and exit_date between ? and ? and er.reason = 'Moved' group by rt.referral_type;";
                            buildMARRow("Moved", $sql, $start_date, $end_date);
                            ?>

                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join exit_reason er on c.exit_reason_id = er.id left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and exit_date between ? and ? and er.reason not in ('Completion of Program', 'Violation of Requirements', 'Unable to Participate', 'Moved', 'Death') group by rt.referral_type;";
                            buildMARRow("Other", $sql, $start_date, $end_date);
                            ?>

                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and exit_date between ? and ? group by rt.referral_type;";
                            buildMARRow("Total Exits", $sql, $start_date, $end_date);
                            ?>

                            <?php
                            // Orientation occured before END of month and not exited at END of Month
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and (orientation_date < ?) and (exit_date is null or exit_date > ?) group by rt.referral_type;";
                            buildMARRow("End of Month Counts", $sql, $end_date, $end_date);
                            ?>
                        </table>

                        <h3>New Placement Demographics</h3>
                        <h4>Age</h4>
                        <table class='table table-bordered table-striped'>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Probation</th>
                                    <th>Parole</th>
                                    <th>Pretrial</th>
                                    <th>Other</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <?php
                            // Orientation occured this month calculating age at orientation date
                            $sql = "SELECT referral_type referral_type, count(id) count from (SELECT c.id, DATE_FORMAT(FROM_DAYS(DATEDIFF(c.orientation_date, c.date_of_birth)),'%Y') +0 age, rt.referral_type from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and (c.orientation_date between ? and ?)) as temp where temp.age <= 21 group by temp.referral_type;";
                            buildMARRow("<= 21", $sql, $start_date, $end_date);
                            ?>
                            <?php
                            // Orientation occured this month calculating age at orientation date
                            $sql = "SELECT referral_type referral_type, count(id) count from (SELECT c.id, DATE_FORMAT(FROM_DAYS(DATEDIFF(c.orientation_date, c.date_of_birth)),'%Y') +0 age, rt.referral_type from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and (c.orientation_date between ? and ?)) as temp where age >= 22 and age <=25 group by referral_type;";
                            buildMARRow("22-25", $sql, $start_date, $end_date);
                            ?>
                            <?php
                            // Orientation occured this month calculating age at orientation date
                            $sql = "SELECT referral_type referral_type, count(id) count from (SELECT c.id, DATE_FORMAT(FROM_DAYS(DATEDIFF(c.orientation_date, c.date_of_birth)),'%Y') +0 age, rt.referral_type from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and (c.orientation_date between ? and ?)) as temp where age >= 26 and age <=29 group by referral_type;";
                            buildMARRow("26-29", $sql, $start_date, $end_date);
                            ?>
                            <?php
                            // Orientation occured this month calculating age at orientation date
                            $sql = "SELECT referral_type referral_type, count(id) count from (SELECT c.id, DATE_FORMAT(FROM_DAYS(DATEDIFF(c.orientation_date, c.date_of_birth)),'%Y') +0 age, rt.referral_type from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and (c.orientation_date between ? and ?)) as temp where age >= 30 and age <=39 group by referral_type;";
                            buildMARRow("30-39", $sql, $start_date, $end_date);
                            ?>
                            <?php
                            // Orientation occured this month calculating age at orientation date
                            $sql = "SELECT referral_type referral_type, count(id) count from (SELECT c.id, DATE_FORMAT(FROM_DAYS(DATEDIFF(c.orientation_date, c.date_of_birth)),'%Y') +0 age, rt.referral_type from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and (c.orientation_date between ? and ?)) as temp where age >= 40 and age <=49 group by referral_type;";
                            buildMARRow("40-49", $sql, $start_date, $end_date);
                            ?>
                            <?php
                            // Orientation occured this month calculating age at orientation date
                            $sql = "SELECT referral_type referral_type, count(id) count from (SELECT c.id, DATE_FORMAT(FROM_DAYS(DATEDIFF(c.orientation_date, c.date_of_birth)),'%Y') +0 age, rt.referral_type from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and (c.orientation_date between ? and ?)) as temp where age >= 50 group by referral_type;";
                            buildMARRow("50 +", $sql, $start_date, $end_date);
                            ?>
                            <?php
                            // Orientation occured this month
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and (orientation_date between ? and ?) group by rt.referral_type;";
                            buildMARRow("Total", $sql, $start_date, $end_date);
                            ?>

                        </table>
                        <h4>Race / Ethnicity</h4>
                        <table class='table table-bordered table-striped'>
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Probation</th>
                                    <th>Parole</th>
                                    <th>Pretrial</th>
                                    <th>Other</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join referral_type rt on c.referral_type_id = rt.id left outer join ethnicity e on c.ethnicity_id = e.id WHERE c.program_id = $program_id and (orientation_date between ? and ?) and e.code = 'B' group by rt.referral_type;";
                            buildMARRow("African American", $sql, $start_date, $end_date);
                            ?>
                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join referral_type rt on c.referral_type_id = rt.id left outer join ethnicity e on c.ethnicity_id = e.id WHERE c.program_id = $program_id and (orientation_date between ? and ?) and e.code = 'W' group by rt.referral_type;";
                            buildMARRow("Caucasian", $sql, $start_date, $end_date);
                            ?>
                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join referral_type rt on c.referral_type_id = rt.id left outer join ethnicity e on c.ethnicity_id = e.id WHERE c.program_id = $program_id and (orientation_date between ? and ?) and e.code = 'H' group by rt.referral_type;";
                            buildMARRow("Hispanic", $sql, $start_date, $end_date);
                            ?>
                            <?php
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join referral_type rt on c.referral_type_id = rt.id left outer join ethnicity e on c.ethnicity_id = e.id WHERE c.program_id = $program_id and (orientation_date between ? and ?) and e.code not in ('B','W','H') group by rt.referral_type;";
                            buildMARRow("Other", $sql, $start_date, $end_date);
                            ?>
                            <?php
                            // Orientation occured this month
                            $sql = "SELECT rt.referral_type, count(c.id) count from client c left outer join referral_type rt on c.referral_type_id = rt.id WHERE c.program_id = $program_id and (orientation_date between ? and ?) group by rt.referral_type;";
                            buildMARRow("Total", $sql, $start_date, $end_date);
                            ?>
                        </table>

                        <h3>Contacts and Training</h3>
                        <table class='table table-bordered table-striped'>
                            <thead>
                                <tr>
                                    <th>Intervention Sessions</th>
                                    <th>Totals</th>
                                </tr>
                            </thead>
                            <tr>
                                <td>Participant hours in intake sessions<br>(count of clients with orientation date within range)</td>
                                <?php
                                $sql = "SELECT count(c.id) count from client c WHERE c.program_id = $program_id and (orientation_date between ? and ?);";
                                $orientation_count = runCountSql($sql, $start_date, $end_date);
                                echo "<td>" . $orientation_count . "</td>";
                                ?>
                            </tr>
                            <tr>
                                <td>Participant hours in orientation sessions<br>(count of clients with orientation date within range)</td>
                                <?php
                                echo "<td>" . $orientation_count . "</td>";
                                ?>
                            </tr>
                            <tr>
                                <td>Participant hours in group sessions</td>
                                <?php
                                $sql = "SELECT sum(duration_minutes)/60 count from (SELECT ts.id session_id, ar.client_id, duration_minutes from therapy_session ts join therapy_group tg on ts.therapy_group_id = tg.id join attendance_record ar on ts.id = ar.therapy_session_id WHERE tg.program_id = $program_id and (ts.date between ? and ?)) as attendance;";

                                $total_hours = runCountSql($sql, $start_date, $end_date);
                                echo "<td>" . $total_hours . "</td>";
                                ?>
                            </tr>
                            <tr>
                                <td>Participant hours in individual sessions</td>
                                <td></td>
                            </tr>
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