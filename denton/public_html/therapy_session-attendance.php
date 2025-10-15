<?php
include_once 'auth.php';
check_loggedin($con);

    require_once "helpers.php";
    require_once "sql_functions.php";

    $therapy_session_id = "";
    if (isset($_GET['therapy_session_id'])) {
        $therapy_session_id = $_GET['therapy_session_id'];
    }
    if($_SERVER["REQUEST_METHOD"] == "POST"){
        $therapy_session_id = trim($_POST["therapy_session_id"]);
    }
    $therapy_session_id_err = "";

    $therapy_session = get_therapy_session_info(trim($therapy_session_id));
    if(!isset($therapy_session)){
        header("location: error.php");
        exit();
    }

    $therapy_group = get_therapy_group_info($therapy_session["therapy_group_id"]);
    if(!isset($therapy_group)){
        header("location: error.php");
        exit();
    }

    $attendance_results = get_session_attendance($therapy_session_id);
    if(!isset($attendance_results)){
        $attendance_results = array();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Therapy Session Attendance</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0-14/css/all.min.css" integrity="sha512-YVm6dLGBSj6KG3uUb1L5m25JXXYrd9yQ1P7RKDSstzYiPxI2vYLCCGyfrlXw3YcN/EM3UJ/IAqsCmfdc6pk/Tg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style type="text/css">
        .page-header h2{
            margin-top: 0;
        }
        table tr td:last-child a{
            margin-right: 5px;
        }
        body {
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php require_once('navbar.php'); ?>

    <section class="pt-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <div class="row">
                        <div class="col-3">
                            <h2>Session Attendance</h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row bg-light">
                <div class="col-7 pt-3 pb-3">
                    <div class="row">
                        <div class="col">
                            <small class="text-muted">Group Name</small>
                            <h5><?php echo htmlspecialchars($therapy_group["name"]); ?></h5>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-3">
                            <small class="text-muted">Sesion Date</small>
                            <h5><?php echo htmlspecialchars($therapy_session["date"]) . " (" . htmlspecialchars($therapy_session["weekday"]) . ")"; ?></h5>
                        </div>
                        <div class="col-1">
                            <small class="text-muted">Attendance</small>
                            <h5><?php echo htmlspecialchars($therapy_session["attendance"]); ?></h5>
                        </div>
                        <div class="col-2">
                            <small class="text-muted">Facilitator</small>
                            <h5><?php echo htmlspecialchars($therapy_session["facilitator"]); ?></h5>
                        </div>
                        <div class="col">
                            <small class="text-muted">Curriculum</small>
                            <h5><?php echo htmlspecialchars($therapy_session["curriculum_short"]); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <br>
                </div>
            </div>
            <div class="row">
                <div class="col-8">
                    <table class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th></th>
                            <th>Name</th>
                            <th>Referral_type</th>
                            <th>Phone</th>
                            <th>Attendance Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $num_rows = count($attendance_results);
                            for($i=0; $i<$num_rows; $i++){
                                $row = $attendance_results[$i];
                                echo "<tr>";
                                echo "<td>" . $i+1 . "</td>";
                                echo "<td><a href='client-review.php?client_id=". $row['client_id'] . "' title='View Client' data-toggle='tooltip'>" . htmlspecialchars($row['first_name']) . ' ' . htmlspecialchars($row['last_name']) . "</a></td>";
                                echo "<td>" . htmlspecialchars($row['referral_type']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['phone_number']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['attendance_note']) . "</td>";
                                echo "</tr>";
                            }
                        ?>
                    </tbody>
                    </table>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                    <br>
                </div>
            </div>
        </div>
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