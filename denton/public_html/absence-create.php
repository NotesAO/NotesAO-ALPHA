<?php
include_once 'auth.php';
check_loggedin($con);

require_once "../config/config.php";
require_once "helpers.php";

$client_id = "";
if (isset($_GET['client_id'])) {
    $client_id = $_GET['client_id'];
}
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $client_id = trim($_POST["client_id"]);
}
$client_id_err = "";

$date = "";
$excused = "";
$note = "";

$client_id_err = "";
$date_err = "";
$excused_err = "";
$note_err = "";


// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
        $client_id = trim($_POST["client_id"]);
		$date = trim($_POST["date"]);
        $excused = isset($_POST['excused']) ? 1 : 0;

		$note = trim($_POST["note"]);
		
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

        $vars = parse_columns('absence', $_POST);
        $stmt = $pdo->prepare("INSERT INTO absence (client_id,date,excused,note) VALUES (?,?,?,?)");

        if($stmt->execute([ $client_id,$date,$excused,$note  ])) {
                $stmt = null;
                header("location: client-attendance.php?client_id=$client_id");
            } else{
                echo "Something went wrong. Please try again later.";
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
                                <label>client_id</label>
                                    <input type="hidden" id="client_id" name="client_id" value="<?php echo $client_id?>" />
                                    <select class="form-control" id="client_id" name="client_id" disabled="true">
                                    <?php
                                        $sql = "SELECT *,id FROM client";
                                        $result = mysqli_query($link, $sql);
                                        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                                            $duprow = $row;
                                            unset($duprow["id"]);
                                            $value = implode(" | ", $duprow);
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
                                <label>date</label>
                                <input type="date" name="date" class="form-control" value="<?php echo $date; ?>">
                                <span class="form-text"><?php echo $date_err; ?></span>
                            </div>
                        <div class="form-group">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" value="" name="excused" <?php if($excused == "1") echo "checked"; ?>>
                                <label class="form-check-label" for="excused">Excused Absence</label>                    
                            </div>
                            <span class="form-text"><?php echo $excused_err; ?></span>
                        </div>
						<div class="form-group">
                                <label>note</label>
                                <input type="text" name="note" maxlength="1024"class="form-control" value="<?php echo $note; ?>">
                                <span class="form-text"><?php echo $note_err; ?></span>
                            </div>

                        <input type="submit" class="btn btn-primary" value="Submit">
                        <a href="client-attendance.php?client_id=<?php echo $client_id; ?>" class="btn btn-secondary">Cancel</a>
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