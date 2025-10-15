<?php
include_once 'auth.php';
check_loggedin($con);

$client_id = "";
if (isset($_GET['client_id'])) {
    $client_id = $_GET['client_id'];
}
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $client_id = trim($_POST["client_id"]);
}

// Process delete operation after confirmation
if(isset($_POST["id"]) && !empty($_POST["id"])){
    // Include config file
    require_once "../config/config.php";
    require_once "helpers.php";

    // Prepare a delete statement
    $sql = "DELETE FROM client_event WHERE id = ?";

    if($stmt = mysqli_prepare($link, $sql)){
        // Set parameters
        $param_id = trim($_POST["id"]);
        mysqli_stmt_bind_param($stmt, "i", $param_id);

        // Attempt to execute the prepared statement
        if(mysqli_stmt_execute($stmt)){
            // Records deleted successfully. Redirect to landing page
            header("location: client-event.php?client_id=$client_id");
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
    // Check existence of id parameter
	$_GET["id"] = trim($_GET["id"]);
    if(empty($_GET["id"])){
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
                        <input type="hidden" name="id" value="<?php echo trim($_GET["id"]); ?>"/>
                        <input type="hidden" name="client_id" value="<?php echo $client_id;?>"/>
                            <p>Are you sure you want to delete this record?</p><br>
                            <p>
                                <input type="submit" value="Yes" class="btn btn-danger">
                                <a href="client-event.php?client_id=<?php echo $client_id;?>" class="btn btn-secondary">No</a>
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
