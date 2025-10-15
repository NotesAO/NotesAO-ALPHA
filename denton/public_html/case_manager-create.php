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
    if($_SERVER["REQUEST_METHOD"] == "POST"){
        $first_name = trim($_POST["first_name"]);
		$last_name = trim($_POST["last_name"]);
		$office = trim($_POST["office"]);
		$email = trim($_POST["email"]);
		$phone_number = formatPhone(trim($_POST["phone_number"]));
		$fax = trim($_POST["fax"]);
		
        $dsn = "mysql:host=".db_host.";dbname=".db_name.";charset=utf8mb4";

        $options = [
          PDO::ATTR_EMULATE_PREPARES   => false, // turn off emulation mode for "real" prepared statements
          PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
        ];
        try {
          $pdo = new PDO($dsn, db_user, db_pass, $options);
          $vars = parse_columns('case_manager', $_POST);
          $stmt = $pdo->prepare("INSERT INTO case_manager (first_name,last_name,office,email,phone_number,fax) VALUES (?,?,?,?,?,?)");
          $stmt->execute([$first_name,$last_name,$office,$email,$phone_number,$fax]);
          $stmt = null;
          header("location: case_manager-index.php");
        } catch (Exception $e) {
          error_log($e->getMessage());
          exit('Something weird happened'); //something a user can understand
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Record</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<?php require_once('navbar.php'); ?>
<body>
    <section class="pt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 mx-auto">
                    <div class="page-header">
                        <h2>Create Record</h2>
                    </div>
                    <p>Please fill this form and submit to add a record to the database.</p>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

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

                        <input type="submit" class="btn btn-primary" value="Submit">
                        <a href="case_manager-index.php" class="btn btn-secondary">Cancel</a>
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