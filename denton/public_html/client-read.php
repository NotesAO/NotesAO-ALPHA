<?php
include_once 'auth.php';
check_loggedin($con);

// Check existence of id parameter before processing further
$_GET["id"] = trim($_GET["id"]);
if(isset($_GET["id"]) && !empty($_GET["id"])){
    require_once "helpers.php";

    // Prepare a select statement
    $sql = "SELECT * FROM client WHERE id = ?";

    if($stmt = mysqli_prepare($link, $sql)){
        // Set parameters
        $param_id = trim($_GET["id"]);

        // Bind variables to the prepared statement as parameters
		if (is_int($param_id)) $__vartype = "i";
		elseif (is_string($param_id)) $__vartype = "s";
		elseif (is_numeric($param_id)) $__vartype = "d";
		else $__vartype = "b"; // blob
        mysqli_stmt_bind_param($stmt, $__vartype, $param_id);

        // Attempt to execute the prepared statement
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);

            if(mysqli_num_rows($result) == 1){
                /* Fetch result row as an associative array. Since the result set
                contains only one row, we don't need to use while loop */
                $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
            } else{
                // URL doesn't contain valid id parameter. Redirect to error page
                header("location: error.php");
                exit();
            }

        } else{
            echo "Oops! Something went wrong. Please try again later.<br>".$stmt->error;
        }
    }

    // Close statement
    mysqli_stmt_close($stmt);

    // Close connection
    mysqli_close($link);
} else{
    // URL doesn't contain id parameter. Redirect to error page
    header("location: error.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Record</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<?php require_once('navbar.php'); ?>
<body>
    <section class="pt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="page-header">
                        <h1>View Record</h1>
                    </div>

                     <div class="form-group">
                            <h4>first_name</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["first_name"]); ?></p>
                        </div><div class="form-group">
                            <h4>last_name</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["last_name"]); ?></p>
                        </div><div class="form-group">
                            <h4>date_of_birth</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["date_of_birth"]); ?></p>
                        </div><div class="form-group">
                            <h4>gender_id</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["gender_id"]); ?></p>
                        </div><div class="form-group">
                            <h4>email</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["email"]); ?></p>
                        </div><div class="form-group">
                            <h4>phone_number</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["phone_number"]); ?></p>
                        </div><div class="form-group">
                            <h4>cause_number</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["cause_number"]); ?></p>
                        </div><div class="form-group">
                            <h4>ethnicity_id</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["ethnicity_id"]); ?></p>
                        </div><div class="form-group">
                            <h4>required_sessions</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["required_sessions"]); ?></p>
                        </div><div class="form-group">
                            <h4>fee</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["fee"]); ?></p>
                        </div><div class="form-group">
                            <h4>case_manager_id</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["case_manager_id"]); ?></p>
                        </div><div class="form-group">
                            <h4>note</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["note"]); ?></p>
                        </div><div class="form-group">
                           <h4>Emergency Contact</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["emergency_contact"]); ?></p>
                        </div><div class="form-group">
                            <h4>other concerns</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["other_concerns"]); ?></p>
                        </div><div class="form-group">
                            <h4>orientation_date</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["orientation_date"]); ?></p>
                        </div><div class="form-group">
                            <h4>exit_date</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["exit_date"]); ?></p>
                        </div><div class="form-group">
                            <h4>exit_reason_id</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["exit_reason_id"]); ?></p>
                        </div><div class="form-group">
                            <h4>exit_note</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["exit_note"]); ?></p>
                        </div><div class="form-group">
                            <h4>speaksSignificantlyInGroup</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["speaksSignificantlyInGroup"]); ?></p>
                        </div><div class="form-group">
                            <h4>respectfulTowardsGroup</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["respectfulTowardsGroup"]); ?></p>
                        </div><div class="form-group">
                            <h4>takesResponsibilityForPastBehavior</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["takesResponsibilityForPastBehavior"]); ?></p>
                        </div><div class="form-group">
                            <h4>disruptiveOrArgumentitive</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["disruptiveOrArgumentitive"]); ?></p>
                        </div><div class="form-group">
                            <h4>inappropriateHumor</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["inappropriateHumor"]); ?></p>
                        </div><div class="form-group">
                            <h4>blamesVictim</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["blamesVictim"]); ?></p>
                        </div><div class="form-group">
                            <h4>drugAlch</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["drug_alcohol"]); ?></p>
                        </div><div class="form-group">
                            <h4>inappropriate behavior</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["inappropriate_behavior_to_staff"]); ?></p>
                        </div>
                    <p><a href="client-index.php" class="btn btn-primary">Back</a></p>
                </div>
            </div>
        </div>
    </section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>
</html>