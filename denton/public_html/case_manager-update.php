<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";

// Define variables and initialize with empty values
$first_name = "";
$last_name = "";
$office = "";
$email = "";
$phone_number = "";
$fax = "";

$first_name_err = "";
$last_name_err = "";
$office_err = "";
$email_err = "";
$phone_number_err = "";
$fax_err = "";


// Processing form data when form is submitted
if(isset($_POST["id"]) && !empty($_POST["id"])){
    // Get hidden input value
    $id = $_POST["id"];

    $first_name = trim($_POST["first_name"]);
		$last_name = trim($_POST["last_name"]);
		$office = trim($_POST["office"]);
		$email = trim($_POST["email"]);
		$phone_number = formatPhone(trim($_POST["phone_number"]));
		$fax = trim($_POST["fax"]);
		

    // Prepare an update statement
    $dsn = "mysql:host=".db_host.";dbname=".db_name.";charset=utf8mb4";
    $options = [
        PDO::ATTR_EMULATE_PREPARES   => false, // turn off emulation mode for "real" prepared statements
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
    ];
    try {
        $pdo = new PDO($dsn, db_user, db_pass, $options);
    } catch (Exception $e) {
        error_log($e->getMessage());
        exit('Something weird happened');
    }

    $vars = parse_columns('case_manager', $_POST);
    $stmt = $pdo->prepare("UPDATE case_manager SET first_name=?,last_name=?,office=?,email=?,phone_number=?,fax=? WHERE id=?");

    if(!$stmt->execute([ $first_name,$last_name,$office,$email,$phone_number,$fax,$id  ])) {
        echo "Something went wrong. Please try again later.";
        header("location: error.php");
    } else {
        $stmt = null;
        header("location: case_manager-read.php?id=$id");
    }
} else {
    // Check existence of id parameter before processing further
	$_GET["id"] = trim($_GET["id"]);
    if(isset($_GET["id"]) && !empty($_GET["id"])){
        // Get URL parameter
        $id =  trim($_GET["id"]);

        // Prepare a select statement
        $sql = "SELECT * FROM case_manager WHERE id = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            // Set parameters
            $param_id = $id;

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

                    // Retrieve individual field value

                    $first_name = htmlspecialchars($row["first_name"]);
					$last_name = htmlspecialchars($row["last_name"]);
					$office = htmlspecialchars($row["office"]);
					$email = htmlspecialchars($row["email"]);
					$phone_number = htmlspecialchars($row["phone_number"]);
					$fax = htmlspecialchars($row["fax"]);
					

                } else{
                    // URL doesn't contain valid id. Redirect to error page
                    header("location: error.php");
                    exit();
                }

            } else{
                echo "Oops! Something went wrong. Please try again later.<br>".$stmt->error;
            }
        }

        // Close statement
        mysqli_stmt_close($stmt);

    }  else{
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
    <title>Update Record</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<?php require_once('navbar.php'); ?>
<body>
    <section class="pt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 mx-auto">
                    <div class="page-header">
                        <h2>Update Record</h2>
                    </div>
                    <p>Please edit the input values and submit to update the record.</p>
                    <form action="<?php echo htmlspecialchars(basename($_SERVER['REQUEST_URI'])); ?>" method="post">

                        <div class="form-group">
                                <label>first_name</label>
                                <input type="text" name="first_name" maxlength="45"class="form-control" value="<?php echo $first_name; ?>">
                                <span class="form-text"><?php echo $first_name_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>last_name</label>
                                <input type="text" name="last_name" maxlength="45"class="form-control" value="<?php echo $last_name; ?>">
                                <span class="form-text"><?php echo $last_name_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>office</label>
                                <input type="text" name="office" maxlength="45"class="form-control" value="<?php echo $office; ?>">
                                <span class="form-text"><?php echo $office_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>email</label>
                                <input type="text" name="email" maxlength="45"class="form-control" value="<?php echo $email; ?>">
                                <span class="form-text"><?php echo $email_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>phone_number</label>
                                <input type="text" name="phone_number" maxlength="45"class="form-control" value="<?php echo $phone_number; ?>">
                                <span class="form-text"><?php echo $phone_number_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>fax</label>
                                <input type="text" name="fax" maxlength="45"class="form-control" value="<?php echo $fax; ?>">
                                <span class="form-text"><?php echo $fax_err; ?></span>
                            </div>

                        <input type="hidden" name="id" value="<?php echo $id; ?>"/>
                        <input type="submit" class="btn btn-primary" value="Submit">
                        <a href="case_manager-index.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
