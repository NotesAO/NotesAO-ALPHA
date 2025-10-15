<?php
// Include the root "../config/config.php" and "auth.php" files
include_once '../../config/config.php';
include_once '../auth.php';
require_once "../helpers.php";

// Check if the user is logged-in
check_loggedin($con, '../index.php');
$stmt = $con->prepare('SELECT password, email, role, username FROM accounts WHERE id = ?');
$stmt->bind_param('i', $_SESSION['id']);
$stmt->execute();
$stmt->bind_result($password, $email, $role, $username);
$stmt->fetch();
$stmt->close();
if ($role != 'Admin') {
    exit('You do not have permission to access this page!');
}

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
    // Convert Directional Quotes
    $item = convert_smart_quotes($item);
    $item = mb_convert_encoding($item, 'ASCII');
    // $item = iconv('UTF-8', 'ASCII//TRANSLIT', $item);
  
    if(str_contains($item, "?")) {
        echo "<b>Confirm Output:</b> " . $item . "<br>";
    }
    
    // if($item === FALSE){
    //     echo "Invalid character in String:" . $before . "<br>";
    //     echo "Converting to :" . $item . "<br>";
    // }
}

function convert_smart_quotes($string)
{
    $quotes = array(
        "\xC2\xAB"   => '"', // « (U+00AB) in UTF-8
        "\xC2\xBB"   => '"', // » (U+00BB) in UTF-8
        "\xE2\x80\x98" => "'", // ‘ (U+2018) in UTF-8
        "\xE2\x80\x99" => "'", // ’ (U+2019) in UTF-8
        "\xE2\x80\x9A" => "'", // ‚ (U+201A) in UTF-8
        "\xE2\x80\x9B" => "'", // ‛ (U+201B) in UTF-8
        "\xE2\x80\x9C" => '"', // “ (U+201C) in UTF-8
        "\xE2\x80\x9D" => '"', // ” (U+201D) in UTF-8
        "\xE2\x80\x9E" => '"', // „ (U+201E) in UTF-8
        "\xE2\x80\x9F" => '"', // ‟ (U+201F) in UTF-8
        "\xE2\x80\xB9" => "'", // ‹ (U+2039) in UTF-8
        "\xE2\x80\xBA" => "'", // › (U+203A) in UTF-8
    );
    $string = strtr($string, $quotes);

    // Version 2
    $search = array(
        chr(145),
        chr(146),
        chr(147),
        chr(148),
        chr(151)
    );
    $replace = array("'","'",'"','"',' - ');
    $string = str_replace($search, $replace, $string);

    // Version 3
    $string = str_replace(
        array('&#8216;','&#8217;','&#8220;','&#8221;'),
        array("'", "'", '"', '"'),
        $string
    );

    // Version 4
    $search = array(
        '&lsquo;', 
        '&rsquo;', 
        '&ldquo;', 
        '&rdquo;', 
        '&mdash;',
        '&ndash;',
    );
    $replace = array("'","'",'"','"',' - ', '-');
    $string = str_replace($search, $replace, $string);

    return $string;
}

$csvArray = [];
if (isset($_FILES['datafile']) && $_FILES['datafile']['tmp_name']) {
    // File was sent - use it for recepient data
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
} else if (isset($_POST['csvJson'])) {
    // File was NOT sent - use serialized recepient data
    $csvArray = json_decode($_POST['csvJson']);
}

$csvKeys = [];
$csvData = [];
if (count($csvArray) > 0) {
    $csvTemp = $csvArray;
    // Get the headder row
    $csvKeys = array_shift($csvTemp);

    // Get the data rows
    foreach ($csvTemp as $row) {
        $csvData[] = array_combine($csvKeys, $row);
    }
}


$needComma = false;
$paramKeys = [];
$update_sql = "update client set ";
if (in_array('client_stage', $csvKeys)) {
    $paramKeys[] = 'client_stage';
    $update_sql = $update_sql . "client_stage_id = (select id from client_stage where stage like ?)";
    $needComma = true;
}
if (in_array('group_name', $csvKeys)) {
    $paramKeys[] = 'group_name';
    if ($needComma) {
        $update_sql = $update_sql . ", ";
    }
    $update_sql = $update_sql . "therapy_group_id = (select id from therapy_group where name like ?)";
    $needComma = true;
}

if (in_array('first_name', $csvKeys)) {
    $paramKeys[] = 'first_name';
    if ($needComma) {
        $update_sql = $update_sql . ", ";
    }
    $update_sql = $update_sql . "first_name = ?";
    $needComma = true;
}

if (in_array('last_name', $csvKeys)) {
    $paramKeys[] = 'last_name';
    if ($needComma) {
        $update_sql = $update_sql . ", ";
    }
    $update_sql = $update_sql . "last_name = ?";
    $needComma = true;
}

if (in_array('phone_number', $csvKeys)) {
    $paramKeys[] = 'phone_number';
    if ($needComma) {
        $update_sql = $update_sql . ", ";
    }
    $update_sql = $update_sql . "phone_number = ?";
    $needComma = true;
}

if (in_array('orientation_date', $csvKeys)) {
    $paramKeys[] = 'orientation_date';
    if ($needComma) {
        $update_sql = $update_sql . ", ";
    }
    $update_sql = $update_sql . "orientation_date = NULLIF(STR_TO_DATE(?, '%m/%e/%Y'),'0000-00-00')";
    $needComma = true;
}

if (in_array('exit_date', $csvKeys)) {
    $paramKeys[] = 'exit_date';
    if ($needComma) {
        $update_sql = $update_sql . ", ";
    }
    $update_sql = $update_sql . "exit_date = NULLIF(STR_TO_DATE(?, '%m/%e/%Y'),'0000-00-00')";
    $needComma = true;
}

if (in_array('exit_reason', $csvKeys)) {
    $paramKeys[] = 'exit_reason';
    if ($needComma) {
        $update_sql = $update_sql . ", ";
    }
    $update_sql = $update_sql . "exit_reason_id = (select id from exit_reason where reason like ?)";
    $needComma = true;
}

if (in_array('client_note', $csvKeys)) {
    $paramKeys[] = 'client_note';
    if ($needComma) {
        $update_sql = $update_sql . ", ";
    }
    $update_sql = $update_sql . "note = ?";
    $needComma = true;
}

$client_id_found = true;
if (!in_array('client_id', $csvKeys)) {
    // Error client_id is required
    $client_id_found = false;
    $update_sql = "CSV must include client_id column";
}
else {
    $paramKeys[] = 'client_id';
    $update_sql = $update_sql . " where client.id = ?";
}
$mesage_results = [];

if ($client_id_found && isset($_POST['action']) && $_POST['action'] == 'Execute' ) {
    $stmt = $pdo->prepare($update_sql);

    if (isset($csvData) && count($csvData) > 0) {
        foreach ($csvData as $row) {
            $row_count = 0;
            $error = "";

            $paramArray = [];
            foreach($paramKeys as $key){
                $paramArray[] = $row[$key];
            }

            if ($stmt->execute($paramArray)) {
                $row_count = $stmt->rowCount();
            } else {
                $error = $stmt->errorInfo()[2];
            }

            $status = "updated " . $row_count . " rows " . $error;
            $mesage_results[] = array($row['client_id'], $status, implode(", ", $paramArray));
            if ($row_count == 1) {
                insertClientEvent($pdo, $row['client_id'], $row);
            }
        }
    }
    $stmt_w_exit = null;
    $stmt_wo_exit = null;
}

function insertClientEvent($pdo, $client_id, $values)
{
    $stmt = $pdo->prepare("INSERT INTO client_event (client_id, client_event_type_id, date, note) select ?, (SELECT id FROM client_event_type where event_type = 'Other'), now(), ?");
    $note = "Modified by Client Update Util: " . http_build_query($values, '', ',');
    $note = truncate($note, 2048);
    if ($stmt->execute([$client_id, $note])) {
        $stmt = null;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Client Update Util</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<?php require_once('admin_navbar.php'); ?>

<body>
    <section class="pt-2">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <div class="page-header">
                        <h2>Client Update Util</h2>
                    </div>
                </div>
            </div>
            <?php
            if ($client_id_found && isset($_POST['action']) && $_POST['action'] == 'Execute') {
                echo "<table class='table table-bordered table-striped'>";
                echo "<thead>";
                // Headder Row
                echo "<tr>";
                echo "<th>client_id</th>";
                echo "<th>status</th>";
                echo "<th>values</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";
                foreach ($mesage_results as $result) {
                    echo "<tr>";
                    foreach ($result as $value) {
                        echo "<td>" . $value . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</tbody>";
                echo "</table>";
                echo "<a href='..' class='btn btn-dark'>Return to Index</a>";
            } else {
            ?>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csvJson" value="<?php echo htmlentities(json_encode($csvArray)); ?>">
                    <div class="row">
                        <div class="col-6">
                            <label>This utility can be used to update the client table using a csv spreadsheet.  The spreadsheet must contain a column called <b>client_id</b> that specifies which client to update.
                                The following columns will be updated if included: <b>first_name, last_name, phone_number, client_stage, group_name, orientation_date, exit_date, exit_reason, client_note</b>  The order of the columns in the csv does not matter.
                            </label><br>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <label>SQL Preview</label><br>
                            <?php
                            echo "<textarea class='form-control' id='sql_preview' name='sql_preview' rows='3' readonly='true'>" . $update_sql . "</textarea>";
                            ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <label>CSV Data File</label>
                            <input class="form-control" type="file" id="datafile" name="datafile">
                        </div>
                    </div>
                    <div class="row pt-3 pb-3">
                        <div class="col-1">
                            <input type="submit" class="btn btn-success" name="action" value="Preview">
                        </div>
                        <div class="col-1">
                            <input type="submit" class="btn btn-danger" name="action" value="Execute">
                        </div>
                        <div class="col-1">
                            <a href="index.php" class="btn btn-dark">Cancel</a>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <label>Data File Contents</label>
                            <?php
                            echo "<table class='table table-bordered table-striped'>";
                            echo "<thead>";
                            $numRows = count($csvKeys);
                            // Headder Row
                            echo "<tr>";
                            foreach ($csvKeys as $value) {
                                echo "<th>$value</th>";
                            }
                            echo "</tr>";
                            echo "</thead>";

                            echo "<tbody>";
                            foreach ($csvData as $row) {
                                echo "<tr>";
                                foreach ($row as $value) {
                                    echo "<td>" . htmlspecialchars($value) . "</td>";
                                }
                                echo "</tr>";
                            }
                            echo "</tbody>";
                            echo "</table>";
                            ?>
                        </div>
                    </div>
                </form>
            <?php
            }
            ?>
        </div>
    </section>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>

</html>