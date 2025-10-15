<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";

// Define variables and initialize with empty values
$name = "";
$address = "";
$city = "";
$state = "";
$zip = "";

$name_err = "";
$address_err = "";
$city_err = "";
$state_err = "";
$zip_err = "";


// Processing form data when form is submitted
if(isset($_POST["id"]) && !empty($_POST["id"])){
    // Get hidden input value
    $id = $_POST["id"];

    $name = trim($_POST["name"]);
		$address = trim($_POST["address"]);
		$city = trim($_POST["city"]);
		$state = trim($_POST["state"]);
		$zip = trim($_POST["zip"]);
		

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

    $vars = parse_columns('location', $_POST);
    $stmt = $pdo->prepare("UPDATE location SET name=?,address=?,city=?,state=?,zip=? WHERE id=?");

    if(!$stmt->execute([ $name,$address,$city,$state,$zip,$id  ])) {
        echo "Something went wrong. Please try again later.";
        header("location: error.php");
    } else {
        $stmt = null;
        header("location: location-read.php?id=$id");
    }
} else {
    // Check existence of id parameter before processing further
	$_GET["id"] = trim($_GET["id"]);
    if(isset($_GET["id"]) && !empty($_GET["id"])){
        // Get URL parameter
        $id =  trim($_GET["id"]);

        // Prepare a select statement
        $sql = "SELECT * FROM location WHERE id = ?";
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

                    $name = htmlspecialchars($row["name"]);
					$address = htmlspecialchars($row["address"]);
					$city = htmlspecialchars($row["city"]);
					$state = htmlspecialchars($row["state"]);
					$zip = htmlspecialchars($row["zip"]);
					

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
    <title>NotesAO - Location Update</title>
    <!-- FAVICON LINKS (from index.html) -->
    <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">

    <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">

    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="apple-touch-icon" sizes="167x167" href="/favicons/apple-touch-icon-ipad-pro.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-touch-icon-ipad.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-touch-icon-120x120.png">

    <link rel="manifest" href="/favicons/site.webmanifest">
    <meta name="apple-mobile-web-app-title" content="NotesAO">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

</head>

<body>
<?php require_once('navbar.php'); ?>
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
                                <label>name</label>
                                <input type="text" name="name" maxlength="45"class="form-control" value="<?php echo $name; ?>">
                                <span class="form-text"><?php echo $name_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>address</label>
                                <input type="text" name="address" maxlength="45"class="form-control" value="<?php echo $address; ?>">
                                <span class="form-text"><?php echo $address_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>city</label>
                                <input type="text" name="city" maxlength="45"class="form-control" value="<?php echo $city; ?>">
                                <span class="form-text"><?php echo $city_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>state</label>
                                <input type="text" name="state" maxlength="45"class="form-control" value="<?php echo $state; ?>">
                                <span class="form-text"><?php echo $state_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>zip</label>
                                <input type="text" name="zip" maxlength="45"class="form-control" value="<?php echo $zip; ?>">
                                <span class="form-text"><?php echo $zip_err; ?></span>
                            </div>

                        <input type="hidden" name="id" value="<?php echo $id; ?>"/>
                        <input type="submit" class="btn btn-primary" value="Submit">
                        <a href="location-index.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" crossorigin="anonymous"></script>

</body>
</html>
