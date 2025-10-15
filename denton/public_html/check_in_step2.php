<?php
include_once 'auth.php';
check_loggedin($con);
    require_once "helpers.php";
    require_once "sql_functions.php";


    // Define variables and initialize with empty values
    // Processing form data when form is submitted
    $therapy_group_id = "";
    if (isset($_GET['therapy_group_id'])) {
        $therapy_group_id = $_GET['therapy_group_id'];
    }
    if($_SERVER["REQUEST_METHOD"] == "POST"){
        $therapy_group_id = trim($_POST["therapy_group_id"]);
    }
    $therapy_group_id_err = "";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TherapyTrack - Client Check In</title>
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
            <!--  Headder Row -->
            <div class="row">
                <div class="col page-header clearfix">
                        <h2 class="float-left">Client Check In</h2>
                    </div>
                <div class="col">
                    <a href="check_in_step1.php" class="btn btn-primary float-right">Return to Step 1</a>
                </div>
            </div>


            <div class="row">
            <div class="col">
                <?php
                    $resultArray = get_therapy_group_info($therapy_group_id);
                    if(isset($resultArray)) {
                            unset($resultArray["id"]);
                            unset($resultArray["city"]);
                            unset($resultArray["state"]);
                            unset($resultArray["zip"]);
                            $value = implode(" - ", $resultArray);
                            echo '<p class="h4">Group: ' . htmlspecialchars($value) . '</p>';
                    }
                    else {
                        echo '<div class="alert alert-warning" role="alert"> Group not found
                        <a href="check_in_step1.php" class="btn btn-primary float-right">Return to Step 1</a>
                        </div>';
                    }
                ?>
            </div>
            </div>


            <form action="check_in_step3.php" method="get">
                <input type="hidden" id="therapy_group_id" name="therapy_group_id" value="<?php echo $therapy_group_id?>" />

                <!--  Session Selection -->
                <div class="row">
                    <div class="col-auto">
                        <label><h3 class="float-left">Select your session</h3></label>
                    </div>
                    <div class="col-auto">
                        <select class="form-control" id="therapy_session_id" name="therapy_session_id">
                        <?php
                            $sql = "SELECT
                            ts.date session_date,
                            ts.duration_minutes session_duration,
                            cur.short_description curriculum_description,
                            ts.id therapy_session_id
                        FROM
                            therapy_group tg
                        LEFT JOIN therapy_session ts ON
                            ts.therapy_group_id = tg.id
                        LEFT outer JOIN curriculum cur ON
                            ts.curriculum_id = cur.id
                        WHERE
                            tg.id = ?";

                            if($stmt = mysqli_prepare($link, $sql)){
                                $__vartype = "i";
                                mysqli_stmt_bind_param($stmt, $__vartype, $therapy_group_id);
                        
                                if(mysqli_stmt_execute($stmt)){
                                    $result = mysqli_stmt_get_result($stmt);
                    
                                    while($resultrow = mysqli_fetch_array($result, MYSQLI_ASSOC)){

                                        $duprow = $resultrow;
                                        unset($duprow["therapy_session_id"]);
                                        unset($duprow["session_duration"]);
                                        

                                        $value = implode(" - ", $duprow);
                                        if ($resultrow["therapy_session_id"] == $therapy_session_id){
                                            echo '<option value="' . "$resultrow[therapy_session_id]" . '"selected="selected">' . htmlspecialchars($value) . '</option>';
                                        } else {
                                            echo '<option value="' . "$resultrow[therapy_session_id]" . '">' . htmlspecialchars($value) . '</option>';
                                        }
                                    }
                                } else{
                                    echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
                                }
                            }
                            // Close statement
                            mysqli_stmt_close($stmt);
                        ?>
                        </select>
                    </div>
                    <div class="col">
                        <input type="submit" class="btn btn-info" value="Next">
                    </div>
                </div>
            </form>
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