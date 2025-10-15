<?php
include_once 'auth.php';
check_loggedin($con);

require_once "helpers.php";

// Define variables and initialize with empty values
$date = "";
$therapy_group_id = "";
$duration_minutes = "";
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
if(isset($_POST["id"]) && !empty($_POST["id"])){
    // Get hidden input value
    $id = $_POST["id"];

    $date = trim($_POST["date"]);
		$therapy_group_id = trim($_POST["therapy_group_id"]);
		$duration_minutes = trim($_POST["duration_minutes"]);
		$curriculum_id = trim($_POST["curriculum_id"]);
        if($curriculum_id == '') $curriculum_id = NULL;
		$facilitator_id = trim($_POST["facilitator_id"]);
        if($facilitator_id == '') $facilitator_id = NULL;
        $co_facilitator_id = isset($_POST["co_facilitator_id"]) ? trim($_POST["co_facilitator_id"]) : '';
        if ($co_facilitator_id === '') $co_facilitator_id = NULL;
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

    $vars = parse_columns('therapy_session', $_POST);
    $stmt = $pdo->prepare("UPDATE therapy_session
        SET therapy_group_id=?, date=?, duration_minutes=?, curriculum_id=?, facilitator_id=?, co_facilitator_id=?, note=?
        WHERE id=?");


    if(!$stmt->execute([ $therapy_group_id,$date,$duration_minutes,$curriculum_id,$facilitator_id,$co_facilitator_id,$note,$id ])) {

        echo "Something went wrong. Please try again later.";
        header("location: error.php");
    } else {
        $stmt = null;
        header("location: therapy_session-read.php?id=$id");
    }
} else {
    // Check existence of id parameter before processing further
	$_GET["id"] = trim($_GET["id"]);
    if(isset($_GET["id"]) && !empty($_GET["id"])){
        // Get URL parameter
        $id =  trim($_GET["id"]);

        // Prepare a select statement
        $sql = "SELECT * FROM therapy_session WHERE id = ?";
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

                    $date = htmlspecialchars($row["date"]);
					$therapy_group_id = htmlspecialchars($row["therapy_group_id"]);
					$duration_minutes = htmlspecialchars($row["duration_minutes"]);
					$curriculum_id = htmlspecialchars($row["curriculum_id"]);
					$facilitator_id = htmlspecialchars($row["facilitator_id"]);
                    $co_facilitator_id = htmlspecialchars($row["co_facilitator_id"]);
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
    <title>NotesAO - Therapy Session Update</title>
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
                        <h2>Update Record</h2>
                    </div>
                    <p>Please edit the input values and submit to update the record.</p>
                    <form action="<?php echo htmlspecialchars(basename($_SERVER['REQUEST_URI'])); ?>" method="post">

                        <div class="form-group">
                                <label>date</label>
                                <input type="datetime-local" name="date" class="form-control" value="<?php echo date("Y-m-d\TH:i:s", strtotime($date)); ?>">
                                <span class="form-text"><?php echo $date_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>therapy_group_id</label>
                                    <select class="form-control" id="therapy_group_id" name="therapy_group_id">
                                    <?php
                                        $sql = "SELECT name, address, id FROM therapy_group";
                                        $result = mysqli_query($link, $sql);
                                        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                                            $duprow = $row;
                                            unset($duprow["id"]);
                                            $value = implode(" | ", $duprow);
                                            if ($row["id"] == $therapy_group_id){
                                            echo '<option value="' . "$row[id]" . '"selected="selected">' . "$value" . '</option>';
                                            } else {
                                                echo '<option value="' . "$row[id]" . '">' . "$value" . '</option>';
                                        }
                                        }
                                    ?>
                                    </select>
                                <span class="form-text"><?php echo $therapy_group_id_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>duration_minutes</label>
                                <input type="number" name="duration_minutes" class="form-control" value="<?php echo $duration_minutes; ?>">
                                <span class="form-text"><?php echo $duration_minutes_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>curriculum</label>
                                    <select class="form-control" id="curriculum_id" name="curriculum_id">
                                    <option value="">Not Specified</option>
                                    <?php
                                        $sql = "SELECT short_description, id FROM curriculum WHERE is_hidden = 0";
                                        $result = mysqli_query($link, $sql);
                                        while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                                            $duprow = $row;
                                            unset($duprow["id"]);
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
                                <label>facilitator</label>
                                    <select class="form-control" id="facilitator_id" name="facilitator_id">
                                    <option value="">Not Specified</option>
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
                            <label>co-facilitator</label>
                            <select class="form-control" id="co_facilitator_id" name="co_facilitator_id">
                                <option value="">Not Specified</option>
                                <?php
                                $sql = "SELECT first_name, last_name, phone, id FROM facilitator";
                                $result = mysqli_query($link, $sql);
                                while($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                                    $duprow = $row;
                                    unset($duprow["id"]);
                                    $value = implode(" ", $duprow);
                                    if (isset($co_facilitator_id) && $row["id"] == $co_facilitator_id){
                                        echo '<option value="'.$row['id'].'" selected="selected">'.$value.'</option>';
                                    } else {
                                        echo '<option value="'.$row['id'].'">'.$value.'</option>';
                                    }
                                }
                                ?>
                            </select>
                            <span class="form-text"><?php /* no error text yet */ ?></span>
                            </div>

						<div class="form-group">
                                <label>note</label>
                                <input type="text" name="note" maxlength="512"class="form-control" value="<?php echo $note; ?>">
                                <span class="form-text"><?php echo $note_err; ?></span>
                            </div>

                        <input type="hidden" name="id" value="<?php echo $id; ?>"/>
                        <input type="submit" class="btn btn-primary" value="Submit">
                        <a href="therapy_session-index.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </section>
</body>
</html>
