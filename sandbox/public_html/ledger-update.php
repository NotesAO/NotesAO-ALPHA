<?php
include_once 'auth.php';
check_loggedin($con);

// Include config file
require_once "../config/config.php";
require_once "helpers.php";

// Define variables and initialize with empty values
$client_id = "";
if (isset($_GET['client_id'])) {
    $client_id = $_GET['client_id'];
}
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $client_id = trim($_POST["client_id"]);
}
$client_id_err = "";

$amount = "";
$create_date = "";
$note = "";

$amount_err = "";
$create_date_err = "";
$note_err = "";


// Processing form data when form is submitted
if(isset($_POST["id"]) && !empty($_POST["id"])){
    // Get hidden input value
    $id = $_POST["id"];

    $amount = trim($_POST["amount"]);
    $create_date = trim($_POST["create_date"]);
    $note = trim($_POST["note"]);
		
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

    $vars = parse_columns('ledger', $_POST);
    $stmt = $pdo->prepare("UPDATE ledger SET client_id=?,amount=?,create_date=?,note=? WHERE id=?");

    if(!$stmt->execute([ $client_id,$amount,$create_date,$note,$id  ])) {
        echo "Something went wrong. Please try again later.";
        header("location: error.php");
    } else {
        $stmt = null;
        header("location: client-ledger.php?client_id=$client_id");
    }
} else {
    // Check existence of id parameter before processing further
	$_GET["id"] = trim($_GET["id"]);
    if(isset($_GET["id"]) && !empty($_GET["id"])){
        // Get URL parameter
        $id =  trim($_GET["id"]);

        // Prepare a select statement
        $sql = "SELECT * FROM ledger WHERE id = ?";
        if($stmt = mysqli_prepare($link, $sql)){
            $param_id = $id;
			mysqli_stmt_bind_param($stmt, "i", $param_id);

            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);

                if(mysqli_num_rows($result) == 1){
                    /* Fetch result row as an associative array. Since the result set
                    contains only one row, we don't need to use while loop */
                    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);

                    // Retrieve individual field value
                    $client_id = htmlspecialchars($row["client_id"]);
					$amount = htmlspecialchars($row["amount"]);
					$create_date = htmlspecialchars($row["create_date"]);
					$note = htmlspecialchars($row["note"]);
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
    <title>NotesAO - Ledger Update</title>
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
                                <label>client_id</label>
                                    <select class="form-control" id="client_id" name="client_id">
                                    <?php
                                        $sql = "SELECT concat(first_name, ' ', last_name), date_of_birth, phone_number, id FROM client";
                                        $result = mysqli_query($link, $sql);
                                        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                                            $duprow = $row;
                                            unset($duprow["id"]);
                                            $value = implode(" - ", $duprow);
                                            if ($row["id"] == $client_id){
                                            echo '<option value="' . "$row[id]" . '"selected="selected">' . "$value" . '</option>';
                                            } else {
                                                echo '<option value="' . "$row[id]" . '">' . "$value" . '</option>';
                                        }
                                        }
                                    ?>
                                    </select>
                                <span class="form-text"><?php echo $client_id_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>amount</label>
                                <input type="number" name="amount" class="form-control" value="<?php echo $amount; ?>" step="any">
                                <span class="form-text"><?php echo $amount_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>create_date</label>
                                <input type="datetime-local" name="create_date" class="form-control" value="<?php echo date("Y-m-d\TH:i:s", strtotime($create_date)); ?>">
                                <span class="form-text"><?php echo $create_date_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>note</label>
                                <input type="text" name="note" maxlength="45"class="form-control" value="<?php echo $note; ?>">
                                <span class="form-text"><?php echo $note_err; ?></span>
                            </div>

                        <input type="hidden" name="id" value="<?php echo $id; ?>"/>
                        <input type="submit" class="btn btn-primary" value="Submit">
                        <a href="client-ledger.php?client_id=<?php echo $client_id;?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
