<?php
include_once 'auth.php';
check_loggedin($con);

require_once "helpers.php";
require_once "sql_functions.php";

if (isset($_POST['action']) && $_POST['action'] == 'Add New Event') {
    // Add New Client Event
    $client_id = trim($_POST["client_id"]);
    $client_event_type_id = trim($_POST["client_event_type_id"]);
    $date = trim($_POST["date"]);
    $note = trim($_POST["note"]);

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
        exit('Something weird happened'); //something a user can understand
    }

    $vars = parse_columns('client_event', $_POST);
    $stmt = $pdo->prepare("INSERT INTO client_event (client_id,client_event_type_id, date, note) VALUES (?,?,?,?)");

    if ($stmt->execute([$client_id, $client_event_type_id, $date, $note])) {
        $stmt = null;
    } else {
        echo "Something went wrong. Please try again later.";
    }
}

$client_id = getParam("client_id");
$client_id_err = "";

$client = get_client_info(trim($client_id));
if (!isset($client)) {
    header("location: error.php");
    exit();
}

$client_events = get_client_events(trim($client_id));
if (!isset($client_events)) {
    header("location: error.php");
    exit();
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Client Event Info</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0-14/css/all.min.css" integrity="sha512-YVm6dLGBSj6KG3uUb1L5m25JXXYrd9yQ1P7RKDSstzYiPxI2vYLCCGyfrlXw3YcN/EM3UJ/IAqsCmfdc6pk/Tg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style type="text/css">
        table tr td:last-child a {
            margin-right: 5px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <?php require_once('navbar.php'); ?>

    <section>
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <div class="row">
                        <div class="col-3">
                            <h3>Client Info</h3>
                        </div>
                        <div class="col-3">
                            <h5><a class="nav-link" href="client-update.php?id=<?php echo htmlspecialchars($client_id); ?>">Update Client</a></h5>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row bg-light">
                <div class="col-7 pb-3"> <!-- Client demographic info -->
                    <div class="row">
                        <div class="col-2">
                            <small class="text-muted">First Name</small>
                            <h5><?php echo htmlspecialchars($client["first_name"]); ?></h5>
                        </div>
                        <div class="col-2">
                            <small class="text-muted">Last Name</small>
                            <h5><?php echo htmlspecialchars($client["last_name"]); ?></h5>
                        </div>
                        <div class="col-3">
                            <small class="text-muted">DOB</small>
                            <h5><?php echo htmlspecialchars($client["date_of_birth"]) . " (" . htmlspecialchars($client["age"]) . ")"; ?></h5>
                        </div>
                        <div class="col-2">
                            <small class="text-muted">Phone</small>
                            <h5><?php echo htmlspecialchars($client["phone_number"]); ?></h5>
                        </div>
                        <div class="col-3">
                            <small class="text-muted">E-Mail</small>
                            <h5><?php echo htmlspecialchars($client["email"]); ?></h5>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-3">
                            <small class="text-muted">Regular Group</small>
                            <h5><?php echo htmlspecialchars($client["group_name"]); ?></h5>
                        </div>
                        <div class="col-2">
                            <small class="text-muted">Referral Type</small>
                            <h5><?php echo htmlspecialchars($client["referral_type"]); ?></h5>
                        </div>
                        <div class="col-3">
                            <small class="text-muted">Case Manager</small>
                            <h5><?php echo htmlspecialchars($client["case_manager"]); ?></h5>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Parole Office</small>
                            <h5><?php echo htmlspecialchars($client["po_office"]); ?></h5>
                        </div>
                    </div>
                    <!-- <div class="row">
                        <div class="col">
                            <small class="text-muted">Client Notes</small>
                            <h5><?php echo htmlspecialchars($client["client_note"]); ?></h5>
                        </div>
                    </div> -->
                    <div class="row">
                        <div class="col">
                            <small class="text-muted">Other Concerns</small>
                            <h5><?php echo htmlspecialchars($client["other_concerns"]); ?></h5>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-2">
                            <small class="text-muted">Sessions Attended</small>
                            <h4><?php echo htmlspecialchars($client["sessions_attended"] . " of " . $client["sessions_required"]); ?></h4>
                        </div>
                        <div class="col-2">
                            <small class="text-muted">Stage of Change</small>
                            <h4><?php echo htmlspecialchars($client["client_stage"]); ?></h4>
                        </div>
                        <div class="col-2">
                            <small class="text-muted">Orientation Date</small>
                            <h4><?php echo htmlspecialchars($client["orientation_date"]); ?></h4>
                        </div>
                        <div class="col-2">
                            <small class="text-muted">Exit Date</small>
                            <h4><?php echo htmlspecialchars($client["exit_date"]); ?></h4>
                        </div>
                    </div>

                </div>
                <div class="col-1 pt-3 pb-3"> <!-- Image column -->
                    <div class="row">
                        <img src="getImage.php?id=<?= $client_id; ?>" class="img-thumbnail" alt="client picture" onerror="this.onerror=null; this.src='img/male-placeholder.jpg'">
                    </div>
                    <div class="row justify-content-center">
                        <a class="nav-link" target="_blank" href="./client-image-upload.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Update Image</a>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-2">
                    <h3 class="float-left">Client Event History</h3>
                </div>
            </div>

            <div class="row bg-light pt-3">
                <div class="col-8">
                    <table class='table table-bordered table-striped'>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Note</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($client_events as $event) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($event['date']) . "</td>";
                                echo "<td>" . htmlspecialchars($event['event_type']) . "</td>";
                                echo "<td>" . htmlspecialchars($event['note']) . "</td>";
                                echo "<td>";
                                echo "<a href='client-event-update.php?id=" . $event['event_id'] . "&client_id=" . $client_id . "' title='Update client event record' data-toggle='tooltip' class='btn btn-primary'>Update</a>";
                                echo "<a href='client-event-delete.php?id=" . $event['event_id'] . "&client_id=" . $client_id . "' title='Delete client event record' data-toggle='tooltip' class='btn btn-primary'>Delete</a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row">
                <div class="col">
                    <h3>Add New Event</h3>
                </div>
            </div>

            <div class="row bg-light pb-3">
                <div class="col">
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>" />
                        <div class="row">
                            <div class="col-2">
                                <label>Event Type</label>
                                <select class="form-control" id="client_event_type_id" name="client_event_type_id">
                                    <?php
                                    $event_types = get_client_event_types();
                                    foreach ($event_types as $event_type) {
                                        $value = htmlspecialchars($event_type["event_type"]);
                                        echo '<option value="' . "$event_type[id]" . '">' . "$value" . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-2">
                                <label>Event Date</label>
                                <input type="datetime-local" name="date" class="form-control" value="<?php echo date('Y-m-d\TH:i');?>">
                            </div>
                            <div class="col-4">
                                <label>Note</label>
                                <textarea type="text" rows="1" name="note" maxlength="2048" class="form-control"></textarea>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-1">
                                <input type="submit" class="btn btn-success" name="action" value="Add New Event">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="row pt-3 pb-3">
                <div class="col-1">
                    <a href="client-review.php?client_id=<?php echo $client_id; ?>" title='Return to client review' data-toggle='tooltip' class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </div>

    </section>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>

</html>