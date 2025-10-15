<?php
include_once 'auth.php';
check_loggedin($con);

require_once "helpers.php";

// Define variables and initialize with empty values
$first_name = "";
$last_name = "";
$email = "";
$phone = "";
$licensure = "";

$first_name_err = "";
$last_name_err = "";
$email_err = "";
$phone_err = "";
$licensure_err = "";


// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
        $first_name = trim($_POST["first_name"]);
		$last_name = trim($_POST["last_name"]);
		$email = trim($_POST["email"]);
		$phone = trim($_POST["phone"]);
		$licensure = trim($_POST["licensure"]);
		

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
          exit('Something weird happened'); //something a user can understand
        }

        $vars = parse_columns('facilitator', $_POST);
        $stmt = $pdo->prepare("INSERT INTO facilitator (first_name,last_name,email,phone,licensure) VALUES (?,?,?,?,?)");

        if($stmt->execute([ $first_name,$last_name,$email,$phone,$licensure  ])) {
                $stmt = null;
                header("location: facilitator-index.php");
            } else{
                echo "Something went wrong. Please try again later.";
            }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Facilitator Create</title>
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
    <!-- Bootstrap 4.5 and Font Awesome (Ensure these match home.php) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

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
                                <label>email</label>
                                <input type="text" name="email" maxlength="45"class="form-control" value="<?php echo $email; ?>">
                                <span class="form-text"><?php echo $email_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>phone</label>
                                <input type="text" name="phone" maxlength="45"class="form-control" value="<?php echo $phone; ?>">
                                <span class="form-text"><?php echo $phone_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>licensure</label>
                                <input type="text" name="licensure" maxlength="45"class="form-control" value="<?php echo $licensure; ?>">
                                <span class="form-text"><?php echo $licensure_err; ?></span>
                            </div>

                        <input type="submit" class="btn btn-primary" value="Submit">
                        <a href="facilitator-index.php" class="btn btn-secondary">Cancel</a>
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