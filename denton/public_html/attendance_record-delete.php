<?php
include_once 'auth.php';
check_loggedin($con);
require_once "../config/config.php";
require_once "helpers.php";

// Process delete operation after confirmation
if(isset($_POST["client_id"]) && !empty($_POST["client_id"]) &&
isset($_POST["therapy_session_id"]) && !empty($_POST["therapy_session_id"])){

    // Prepare a delete statement
    $sql = "DELETE FROM attendance_record WHERE client_id = ? and therapy_session_id = ?";

    if($stmt = mysqli_prepare($link, $sql)){
        // Set parameters
        $client_id = trim($_POST["client_id"]);
        $therapy_session_id = trim($_POST["therapy_session_id"]);
        mysqli_stmt_bind_param($stmt, "ii", $client_id, $therapy_session_id);

        // Attempt to execute the prepared statement
        if(mysqli_stmt_execute($stmt)){
            // Records deleted successfully. Redirect to landing page
            header("location: client-attendance.php?client_id=$client_id'");
            exit();
        } else{
            echo "Oops! Something went wrong. Please try again later.<br>".$stmt->error;
        }
    }

    // Close statement
    mysqli_stmt_close($stmt);

    // Close connection
    mysqli_close($link);
} else{
    $client_id = trim($_GET["client_id"]);
    $therapy_session_id = trim($_GET["therapy_session_id"]);
    if(empty($client_id) || empty($therapy_session_id)){
        // URL doesn't contain id parameter. Redirect to error page
        header("location: error.php");
        exit();
    }
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
                <div class="col-md-6 mx-auto">
                    <div class="page-header">
                        <h1>Delete Record</h1>
                    </div>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                        <div class="alert alert-danger fade-in">
                            <input type="hidden" name="client_id" value="<?php echo trim($_GET["client_id"]); ?>"/>
                            <input type="hidden" name="therapy_session_id" value="<?php echo trim($_GET["therapy_session_id"]); ?>"/>
                            <p>NOTE: This function removes the client attendance record but it does NOT change any associated debits or credits to the client payment ledger.</p><br>
                            <p>Are you sure you want to delete this record?</p>
                            <p>
                                <input type="submit" value="Yes" class="btn btn-danger">
                                <?php echo "<a href='client-attendance.php?client_id=$client_id' class='btn btn-secondary'>No</a>"; ?>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>
</html>
