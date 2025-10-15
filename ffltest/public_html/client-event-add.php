<?php
// Include the root "../config/config.php" and "auth.php" files
include_once '../config/config.php';
include_once 'auth.php';
require_once "helpers.php";
require_once "sql_functions.php";

// Check if the user is logged-in
check_loggedin($con, 'index.php');

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

function encodeSpecialChars(&$item)
{
    $item = mb_convert_encoding($item, 'Windows-1252', 'UTF-8');
}


$client_event_type_id = getParam("client_event_type_id");
$date = getParam("date", date('Y-m-d\TH:i'));
$note = getParam("note");
$client_id_list = getParam("client_id_list");


$csvArray = [];
if (isset($_FILES['datafile']) && $_FILES['datafile']['tmp_name']) {
    $client_id_list = "";

    // File was sent - use it to populate client_ids
    $tmpName = $_FILES['datafile']['tmp_name'];
    $csvArray = array_map('str_getcsv', file($tmpName));

    // WARNING - The file may or may not contain a byte order mark "magic number"
    // If it does this strips off the trash from the first item
    $str = $csvArray[0][0];
    if (mb_detect_encoding($str) === 'UTF-8') {
        $csvArray[0][0] = preg_replace('/\x{EF}\x{BB}\x{BF}/', '', $str);
    }

    // UTF encode everything to handle special chars
    array_walk_recursive($csvArray, 'encodeSpecialChars');

    $csvKeys = [];
    $csvData = [];
    if (count($csvArray) > 0) {
        $csvTemp = $csvArray;
        // Get the headder row
        $csvKeys = array_shift($csvTemp);
        foreach ($csvTemp as $row) {
            $csvData[] = array_combine($csvKeys, $row);
        }

        $first = true;
        foreach ($csvData as $row) {
            if($first) {
                $client_id_list = $row["client_id"];
                $first = false;
            }
            else {
                $client_id_list = $client_id_list . ", " . $row["client_id"];
            }
        }
    }
}

$event_count = 0;
$action = getParam("action");
if ($action == "Add Events") {
    $stmt = $pdo->prepare("INSERT INTO client_event (client_id, client_event_type_id, date, note) values (?, ?, ?, ?)");

    $array = explode(', ', $client_id_list); //split string into array seperated by ', '

    foreach ($array as $client_id) //loop over values
    {
        try {
            $stmt->execute([$client_id, $client_event_type_id, $date, $note]);
        } catch (Exception $e) {
            $msgError = $e->getMessage();
        }
        $event_count = $event_count + $stmt->rowCount();
    }
    $stmt = null;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>NotesAO - Client Event Add</title>
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
    <section class="pt-2">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <div class="page-header">
                        <h2>Bulk Add Client Event</h2>
                    </div>
                </div>
            </div>
            <?php if ($action == "Add Events") { ?>
                <section class="pt-2">
                    <div class="container-fluid">
                        <div class="row">
                            Added <?php echo $event_count; ?> client events
                        </div>
                        <div class="row">
                            <a href='' class='btn btn-dark'>Return to Index</a>
                        </div>
                    </div>
                </section>
            <?php } else { ?>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-2">
                            <label>Event Type</label>
                            <select class="form-control" id="client_event_type_id" name="client_event_type_id">
                                <?php
                                $event_types = get_client_event_types();
                                foreach ($event_types as $event_type) {
                                    $value = htmlspecialchars($event_type["event_type"]);
                                    if ($event_type["id"] == $client_event_type_id) {
                                        echo '<option selected="selected" value="' . "$event_type[id]" . '">' . "$value" . '</option>';
                                    } else {
                                        echo '<option value="' . "$event_type[id]" . '">' . "$value" . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-2">
                            <label>Event Date</label>
                            <input type="datetime-local" name="date" class="form-control" value="<?php echo $date; ?>">
                        </div>
                        <div class="col-4">
                            <label>Note</label>
                            <textarea type="text" rows="1" name="note" maxlength="2048" class="form-control"><?php echo htmlspecialchars($note); ?></textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-8">
                            <label>Client IDs (comma separated list)</label>
                            <textarea type="text" rows="1" name="client_id_list" maxlength="2048" class="form-control"><?php echo htmlspecialchars($client_id_list); ?></textarea>
                        </div>
                    </div>

                    <div class="row pb-3">
                        <div class="col-3">
                            <label>Load Client IDs from File</label>
                            <input class="form-control" type="file" id="datafile" name="datafile">
                        </div>
                        <div class="col-1">
                            <label>&nbsp;</label>
                            <input type="submit" class="btn btn-success" name="action" value="Load Client IDs">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-1">
                            <input type="submit" class="btn btn-danger" name="action" value="Add Events">
                        </div>
                        <div class="col-1">
                            <a href="index.php" class="btn btn-dark">Cancel</a>
                        </div>
                    </div>
                </form>
            <?php } ?>
        </div>
    </section>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>

</html>