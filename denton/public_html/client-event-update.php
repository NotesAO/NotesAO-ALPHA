<?php
include_once 'auth.php';
check_loggedin($con);

// Include config file
require_once "../config/config.php";
require_once "helpers.php";
require_once "sql_functions.php";

// Define variables and initialize with empty values
$client_id = "";
if (isset($_GET['client_id'])) {
    $client_id = $_GET['client_id'];
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $client_id = trim($_POST["client_id"]);
}
$client_id_err = "";

$event_type_id = "";
$event_type_id_err = "";

$date = "";
$date_err = "";

$note = "";
$note_err = "";


// Processing form data when form is submitted
if (isset($_POST["id"]) && !empty($_POST["id"])) {
    // Get hidden input value
    $id = $_POST["id"];

    $event_type_id = trim($_POST["event_type_id"]);
    $date = trim($_POST["date"]);
    $note = trim($_POST["note"]);

    // Prepare an update statement
    $dsn = "mysql:host=" . db_host . ";dbname=" . db_name . ";charset=utf8mb4";
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

    $vars = parse_columns('client_event', $_POST);
    $stmt = $pdo->prepare("UPDATE client_event SET client_event_type_id=?,date=?,note=? WHERE id=?");

    if (!$stmt->execute([$event_type_id, $date, $note, $id])) {
        echo "Something went wrong. Please try again later.";
        header("location: error.php");
    } else {
        $stmt = null;
        header("location: client-event.php?client_id=$client_id");
    }
} else {
    $_GET["id"] = trim($_GET["id"]);
    if (isset($_GET["id"]) && !empty($_GET["id"])) {
        // Get URL parameter
        $id =  trim($_GET["id"]);
        $event_info = get_client_event_info($id);
        if (!isset($event_info)) {
            echo "Oops! Something went wrong. Please try again later.<br>" . $stmt->error;
        }
    } else {
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
                            <label>Client</label>
                            <h3><?php echo htmlspecialchars($event_info["client_name"]); ?></h3>
                        </div>

                        <div class="form-group">
                            <label>Event Type</label>
                                <select class="form-control" id="event_type_id" name="event_type_id">
                                    <?php
                                        $event_types = get_client_event_types();
                                        foreach ($event_types as $event_type) {
                                            $value = htmlspecialchars($event_type["event_type"]);
                                            if ($event_type["id"] == $event_info['event_type_id']) {
                                                echo '<option value="' . "$event_type[id]" . '"selected="selected">' . "$value" . '</option>';
                                            } else {
                                                echo '<option value="' . "$event_type[id]" . '">' . "$value" . '</option>';
                                            }
                                        }
                                    ?>
                                </select>
                                <span class="form-text"><?php echo $event_type_id_err; ?></span>
                        </div>

                        <div class="form-group">
                            <label>Event Date</label>
                            <input type="datetime-local" name="date" class="form-control" value="<?php echo date("Y-m-d\TH:i:s", strtotime($event_info['event_date'])); ?>">
                            <span class="form-text"><?php echo $date_err; ?></span>
                        </div>
                        <div class="form-group">
                            <label>Note</label>
                            <textarea type="text" name="note" maxlength="2048" class="form-control"><?php echo $event_info['event_note']; ?></textarea>
                            <span class="form-text"><?php echo $note_err; ?></span>
                        </div>

                        <input type="hidden" name="id" value="<?php echo $id; ?>" />
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>" />

                        <input type="submit" class="btn btn-primary" value="Submit">
                        <a href="client-event.php?client_id=<?php echo $client_id; ?>" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </section>
</body>

</html>