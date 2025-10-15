<?php
include_once 'auth.php';
check_loggedin($con);

    require_once "helpers.php";
    require_once "sql_functions.php";

    $client_id = "";
    if (isset($_GET['client_id'])) {
        $client_id = $_GET['client_id'];
    }
    if($_SERVER["REQUEST_METHOD"] == "POST"){
        $client_id = trim($_POST["client_id"]);
    }
    $client_id_err = "";

    $client = get_client_info(trim($client_id));
    if(!isset($client)){
        header("location: error.php");
        exit();
    }

    $attendance_results = get_client_attendance_summary(trim($client_id));
    if(!isset($attendance_results)){
        header("location: error.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Therapy Session Info</title>
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
                            <h2>Client Attendance</h2>
                        </div>
                        <div class="col-3">
                            <h5><a class="nav-link" href="client-update.php?id=<?php echo htmlspecialchars($client_id); ?>">Update Client</a></h5>
                        </div>                    
                    </div>
                </div>
            </div>
            <div class="row bg-light">
                <div class="col-7 pt-3 pb-3"> <!-- Client demographic info -->
                    <div class="row">
                        <div class="col-2">
                            <small class="text-muted">First Name</small>
                            <h5><?php echo htmlspecialchars($client["first_name"]); ?></h5>
                        </div>                    
                        <div class="col-2">
                            <small class="text-muted">Last Name</small>
                            <h5><?php echo htmlspecialchars($client["last_name"]); ?></h5>
                        </div>
                        <div class="col-3">
                            <small class="text-muted">DOB</small>
                            <h5><?php echo htmlspecialchars($client["date_of_birth"]) . " (". htmlspecialchars($client["age"]) . ")"; ?></h5>
                        </div>
                        <div class="col-2">
                            <small class="text-muted">Phone</small>
                            <h5><?php echo htmlspecialchars($client["phone_number"]); ?></h5>
                        </div>
                        <div class="col-3">
                            <small class="text-muted">E-Mail</small>
                            <h5><?php echo htmlspecialchars($client["email"]); ?></h5>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-3">
                                <small class="text-muted">Regular Group</small>
                                <h5><?php echo htmlspecialchars( $client["group_name"] ); ?></h5>
                        </div>
                        <div class="col-2">
                            <small class="text-muted">Referral Type</small>
                            <h5><?php echo htmlspecialchars($client["referral_type"]); ?></h5>
                        </div>
                        <div class="col-3">
                            <small class="text-muted">Case Manager</small>
                            <h5><?php echo htmlspecialchars($client["case_manager"]); ?></h5>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Parole Office</small>
                            <h5><?php echo htmlspecialchars($client["po_office"]); ?></h5>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <small class="text-muted">Client Notes</small>
                            <h5><?php echo htmlspecialchars($client["client_note"]); ?></h5>
                        </div>
                    </div>
                    <div class="row">
                <div class="col-1">
                    <small class="text-muted">Required</small>
                    <h4><?php echo htmlspecialchars($client["sessions_required"]); ?></h4>
                </div>
                <div class="col-1">
                    <small class="text-muted">Present</small>
                    <h4><?php echo htmlspecialchars( $client["sessions_attended"] ); ?></h4>
                </div>
                <div class="col-1">
                    <small class="text-muted">Excused</small>
                    <h4><?php echo htmlspecialchars( $client["absence_excused"] ); ?></h4>
                </div>
                <div class="col-1">
                    <small class="text-muted">Unexcused</small>
                    <h4><?php echo htmlspecialchars( $client["absence_unexcused"] ); ?></h4>
                </div>
            </div>

                </div>
                <div class="col-1 pt-3 pb-3">  <!-- Image column -->
                    <div class="row">
                    <img src="getImage.php?id=<?=$client_id;?>" class="img-thumbnail" alt="client picture" onerror="this.onerror=null; this.src='img/male-placeholder.jpg'">
                    </div>
                    <div class="row justify-content-center">
                        <a class="nav-link" href="./client-image-upload.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Update Image</a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-8">
                    <table class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th></th>
                            <th>Status</th>
                            <th>Note</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $num_rows = count($attendance_results);
                            for($i=0; $i<$num_rows; $i++){
                                $row = $attendance_results[$i];
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['date']) . "</td>";
                                if('present' == $row['status']) {
                                    echo "<td>&#x2705</td>";
                                }
                                else if('excused' == $row['status']) {
                                    echo "<td>&#x2716</td>";
                                }
                                else if('unexcused' == $row['status']) {
                                    echo "<td>&#x274C</td>";
                                }
                                else {
                                    echo "<td></td>";  // ??????
                                }
                                echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['note']) . "</td>";
                                echo "<td>";
                                if('present' == $row['status']) {
                                    // buttons to update or delete attendance record
                                    echo "<a href='attendance_record-delete.php?therapy_session_id=". $row['record_id'] . "&client_id=" . $client_id . "' title='Delete' data-toggle='tooltip' class='btn btn-primary'>Delete</a>";
                                } else {
                                    echo "<a href='absence-update.php?id=". $row['record_id'] . "&client_id=" . $client_id . "' title='Update' data-toggle='tooltip' class='btn btn-primary'>Update</a>";
                                    echo "<a href='absence-delete.php?id=". $row['record_id'] . "&client_id=" . $client_id . "' title='Delete' data-toggle='tooltip' class='btn btn-primary'>Delete</a>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        ?>
                    </tbody>
                    </table>
                </div>
            </div>
            <div class="row">
                <div class="col-2">
                    <a href="absence-create.php?client_id=<?php echo $client_id; ?>" class="btn btn-primary">Create Absence Record</a>
                </div>
                <div class="col-1">
                    <a href="client-review.php?client_id=<?php echo $client_id; ?>"class="btn btn-secondary">Cancel</a>
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