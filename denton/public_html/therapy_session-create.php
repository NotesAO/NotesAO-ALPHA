<?php
include_once 'auth.php';
check_loggedin($con);

require_once "helpers.php";
require_once "sql_functions.php";

// Define variables and initialize with empty values
$therapy_group_id = "";
if (isset($_GET['therapy_group_id'])) {
    $therapy_group_id = $_GET['therapy_group_id'];
}

$date = "now";
$duration_minutes = "120";
$curriculum_id = "";
$facilitator_id = "";
$note = "";

$date_err = "";
$therapy_group_id_err = "";
$duration_minutes_err = "";
$curriculum_id_err = "";
$facilitator_id_err = "";
$note_err = "";


// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
        $date = trim($_POST["date"]);
		$therapy_group_id = trim($_POST["therapy_group_id"]);
		$duration_minutes = trim($_POST["duration_minutes"]);
		$curriculum_id = trim($_POST["curriculum_id"]);
        if($curriculum_id == '') $curriculum_id = NULL;
		$facilitator_id = trim($_POST["facilitator_id"]);
        if($facilitator_id == '') $facilitator_id = NULL;
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

        $vars = parse_columns('therapy_session', $_POST);
        $stmt = $pdo->prepare("INSERT INTO therapy_session (therapy_group_id,date,duration_minutes,curriculum_id,facilitator_id,note) VALUES (?,?,?,?,?,?)");

        if($stmt->execute([ $therapy_group_id,$date,$duration_minutes,$curriculum_id,$facilitator_id,$note  ])) {
                $stmt = null;
                header("location: therapy_session-index.php");
            } else{
                echo "Something went wrong. Please try again later.";
            }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Therapy Session</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<?php require_once('navbar.php'); ?>
<body>
    <section class="pt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 mx-auto">
                    <div class="page-header">
                        <h2>Create New Therapy Session</h2>
                    </div>
                    <p>Please fill this form and submit to add a new therapy session to the database.</p>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">

						<div class="form-group">
                                <label>Therapy Group</label>
                                    <select class="form-control" id="therapy_group_id" name="therapy_group_id">
                                    <?php
                                        $groups = get_therapy_groups($_SESSION['program_id']);
                                        foreach($groups as $group) {
                                            $value = htmlspecialchars($group["name"] . " - " . $group["address"]);
                                            if ($group["id"] == $therapy_group_id){
                                                echo '<option value="' . "$group[id]" . '"selected="selected">' . "$value" . '</option>';
                                            } else {
                                                echo '<option value="' . "$group[id]" . '">' . "$value" . '</option>';
                                            }
                                        }
                                    ?>
                                    </select>
                                <span class="form-text"><?php echo $therapy_group_id_err; ?></span>
                        </div>

                            <div class="form-group">
                                <label>Date</label>
                                <input type="datetime-local" name="date" class="form-control" value="<?php echo date("Y-m-d\TH:i", strtotime($date)); ?>">
                                <span class="form-text"><?php echo $date_err; ?></span>
                            </div>

						<div class="form-group">
                                <label>Duration (minutes)</label>
                                <input type="number" name="duration_minutes" class="form-control" step="15" min="0" max="240" value="<?php echo $duration_minutes; ?>">
                                <span class="form-text"><?php echo $duration_minutes_err; ?></span>
                            </div>

                            <div class="form-group">
                                <label>Curriculum</label>
                                    <select class="form-control" id="curriculum_id" name="curriculum_id">
                                    <option value="" selected="selected">Not Specified</option>
                                    <?php
                                        $sql = "SELECT *,id FROM curriculum";
                                        $result = mysqli_query($link, $sql);
                                        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                                            $duprow = $row;
                                            unset($duprow["id"]);
                                            unset($duprow["long_description"]);
                                            $value = implode(" | ", $duprow);
                                            if ($row["id"] == $curriculum_id){
                                            echo '<option value="' . "$row[id]" . '"selected="selected">' . "$value" . '</option>';
                                            } else {
                                                echo '<option value="' . "$row[id]" . '">' . "$value" . '</option>';
                                        }
                                        }
                                    ?>
                                    </select>
                                <span class="form-text"><?php echo $curriculum_id_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>Facilitator</label>
                                    <select class="form-control" id="facilitator_id" name="facilitator_id">
                                    <option value="" selected="selected">Not Specified</option>
                                    <?php
                                        $sql = "SELECT first_name, last_name, phone, id FROM facilitator";
                                        $result = mysqli_query($link, $sql);
                                        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                                            $duprow = $row;
                                            unset($duprow["id"]);
                                            $value = implode(" ", $duprow);
                                            if ($row["id"] == $facilitator_id){
                                            echo '<option value="' . "$row[id]" . '"selected="selected">' . "$value" . '</option>';
                                            } else {
                                                echo '<option value="' . "$row[id]" . '">' . "$value" . '</option>';
                                        }
                                        }
                                    ?>
                                    </select>
                                <span class="form-text"><?php echo $facilitator_id_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>Note</label>
                                <input type="text" name="note" maxlength="512"class="form-control" value="<?php echo $note; ?>">
                                <span class="form-text"><?php echo $note_err; ?></span>
                            </div>

                        <input type="submit" class="btn btn-primary" value="Submit">
                        <a href="therapy_session-index.php" class="btn btn-secondary">Cancel</a>
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