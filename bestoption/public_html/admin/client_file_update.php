<?php
// Include the root "../config/config.php" and "auth.php" files
include_once '../../config/config.php';
include_once '../auth.php';
require_once "../helpers.php";

// Check if the user is logged-in
check_loggedin($con, '../index.php');
// Ensure the role is set in the session
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    error_log("Access Denied: Role is " . ($_SESSION['role'] ?? 'not set'));
    exit('You do not have permission to access this page!');
}

$dsn = "mysql:host=" . db_host . ";dbname=" . db_name . ";charset=utf8mb4";
$options = [
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    $pdo = new PDO($dsn, db_user, db_pass, $options);
} catch (Exception $e) {
    error_log($e->getMessage());
    exit('Something weird happened'); // Something a user can understand
}

function encodeSpecialChars(&$item)
{
    // Convert Directional Quotes
    $item = convert_smart_quotes($item);
    $item = mb_convert_encoding($item, 'ASCII');

    if (str_contains($item, "?")) {
        echo "<b>Confirm Output:</b> " . $item . "<br>";
    }
}

function convert_smart_quotes($string)
{
    $quotes = [
        "\xC2\xAB"   => '"',  // « (U+00AB) in UTF-8
        "\xC2\xBB"   => '"',  // » (U+00BB) in UTF-8
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
    ];
    $string = strtr($string, $quotes);

    // Version 2
    $search = [chr(145), chr(146), chr(147), chr(148), chr(151)];
    $replace = ["'", "'", '"', '"', ' - '];
    $string = str_replace($search, $replace, $string);

    // Version 3
    $string = str_replace(
        ['&#8216;','&#8217;','&#8220;','&#8221;'],
        ["'", "'", '"', '"'],
        $string
    );

    // Version 4
    $search = ['&lsquo;', '&rsquo;', '&ldquo;', '&rdquo;', '&mdash;', '&ndash;'];
    $replace = ["'", "'", '"', '"', ' - ', '-'];
    $string = str_replace($search, $replace, $string);

    return $string;
}

$csvArray = [];
if (isset($_FILES['datafile']) && $_FILES['datafile']['tmp_name']) {
    // File was sent
    $tmpName = $_FILES['datafile']['tmp_name'];
    $csvArray = array_map('str_getcsv', file($tmpName));

    // Strip possible BOM
    $str = $csvArray[0][0];
    if (mb_detect_encoding($str) === 'UTF-8') {
        $csvArray[0][0] = preg_replace('/\x{EF}\x{BB}\x{BF}/', '', $str);
    }

    // Convert to ASCII and handle special chars
    array_walk_recursive($csvArray, 'encodeSpecialChars');
} else if (isset($_POST['csvJson'])) {
    // Use serialized recipient data
    $csvArray = json_decode($_POST['csvJson']);
}

$csvKeys = [];
$csvData = [];
if (count($csvArray) > 0) {
    $csvTemp = $csvArray;
    // Header row
    $csvKeys = array_shift($csvTemp);
    // Data rows
    foreach ($csvTemp as $row) {
        $csvData[] = array_combine($csvKeys, $row);
    }
}

$needComma = false;
$paramKeys = [];
$update_sql = "UPDATE client SET ";

if (in_array('client_stage', $csvKeys)) {
    $paramKeys[] = 'client_stage';
    $update_sql .= "client_stage_id = (SELECT id FROM client_stage WHERE stage LIKE ?)";
    $needComma = true;
}
if (in_array('group_name', $csvKeys)) {
    $paramKeys[] = 'group_name';
    if ($needComma) {
        $update_sql .= ", ";
    }
    $update_sql .= "therapy_group_id = (SELECT id FROM therapy_group WHERE name LIKE ?)";
    $needComma = true;
}
if (in_array('first_name', $csvKeys)) {
    $paramKeys[] = 'first_name';
    if ($needComma) {
        $update_sql .= ", ";
    }
    $update_sql .= "first_name = ?";
    $needComma = true;
}
if (in_array('last_name', $csvKeys)) {
    $paramKeys[] = 'last_name';
    if ($needComma) {
        $update_sql .= ", ";
    }
    $update_sql .= "last_name = ?";
    $needComma = true;
}
if (in_array('phone_number', $csvKeys)) {
    $paramKeys[] = 'phone_number';
    if ($needComma) {
        $update_sql .= ", ";
    }
    $update_sql .= "phone_number = ?";
    $needComma = true;
}
if (in_array('orientation_date', $csvKeys)) {
    $paramKeys[] = 'orientation_date';
    if ($needComma) {
        $update_sql .= ", ";
    }
    $update_sql .= "orientation_date = NULLIF(STR_TO_DATE(?, '%m/%e/%Y'),'0000-00-00')";
    $needComma = true;
}
if (in_array('exit_date', $csvKeys)) {
    $paramKeys[] = 'exit_date';
    if ($needComma) {
        $update_sql .= ", ";
    }
    $update_sql .= "exit_date = NULLIF(STR_TO_DATE(?, '%m/%e/%Y'),'0000-00-00')";
    $needComma = true;
}
if (in_array('exit_reason', $csvKeys)) {
    $paramKeys[] = 'exit_reason';
    if ($needComma) {
        $update_sql .= ", ";
    }
    $update_sql .= "exit_reason_id = (SELECT id FROM exit_reason WHERE reason LIKE ?)";
    $needComma = true;
}
if (in_array('client_note', $csvKeys)) {
    $paramKeys[] = 'client_note';
    if ($needComma) {
        $update_sql .= ", ";
    }
    $update_sql .= "note = ?";
    $needComma = true;
}

$client_id_found = true;
if (!in_array('client_id', $csvKeys)) {
    $client_id_found = false;
    $update_sql = "CSV must include 'client_id' column";
} else {
    $paramKeys[] = 'client_id';
    $update_sql .= " WHERE client.id = ?";
}

$mesage_results = [];

if ($client_id_found && isset($_POST['action']) && $_POST['action'] === 'Execute') {
    $stmt = $pdo->prepare($update_sql);

    if (!empty($csvData)) {
        foreach ($csvData as $row) {
            $row_count = 0;
            $error = "";

            $paramArray = [];
            foreach ($paramKeys as $key) {
                $paramArray[] = $row[$key];
            }

            if ($stmt->execute($paramArray)) {
                $row_count = $stmt->rowCount();
            } else {
                $error = $stmt->errorInfo()[2];
            }

            $status = "updated " . $row_count . " rows " . $error;
            $mesage_results[] = [$row['client_id'], $status, implode(", ", $paramArray)];
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
    $stmt = $pdo->prepare("INSERT INTO client_event (client_id, client_event_type_id, date, note)
                           SELECT ?, (SELECT id FROM client_event_type WHERE event_type = 'Other'), NOW(), ?");
    $note = "Modified by Client Update Util: " . http_build_query($values, '', ',');
    // ensure note is not too long
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
    <title>NotesAO - Client Updates</title>
    <!-- Match index.php: Bootstrap 4.5 + Font Awesome -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
    >

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script
      src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js">
    </script>
    <script
      src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js">
    </script>

    <style>
        /* Match index.php styling */
        body {
            padding-top: 70px; /* same offset for fixed-top navbar */
            background-color: #f8f9fa;
        }
        .page-header h2 {
            margin-top: 0;
        }
        .admin-btn {
            margin: 10px 5px;
        }
    </style>
</head>

<body>

<?php require_once('admin_navbar.php'); ?>

<div class="container">
    <div class="page-header my-3">
        <h2>Client Update Utility</h2>
    </div>

    <?php if ($client_id_found && isset($_POST['action']) && $_POST['action'] === 'Execute'): ?>
        <div class="alert alert-success">
            Client updates executed successfully.
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                <tr>
                    <th>Client ID</th>
                    <th>Status</th>
                    <th>Values</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($mesage_results as $result): ?>
                    <tr>
                        <?php foreach ($result as $value): ?>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a href="index.php" class="btn btn-dark mt-3">
            <i class="fas fa-arrow-left"></i> Back to Admin Panel
        </a>
    <?php else: ?>
        <form
          action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
          method="post"
          enctype="multipart/form-data"
          class="mb-5"
        >
            <input
              type="hidden"
              name="csvJson"
              value="<?php echo htmlentities(json_encode($csvArray)); ?>"
            >

            <!-- Instructions -->
            <div class="alert alert-info">
                This utility allows you to update the client table using a CSV file.
                The CSV must include a <strong>client_id</strong> column for identification.
                Supported columns: <strong>first_name, last_name, phone_number, client_stage, group_name, orientation_date, exit_date, exit_reason, client_note</strong>.
            </div>

            <!-- SQL Preview -->
            <div class="form-group">
                <label for="sql_preview" class="font-weight-bold">SQL Preview</label>
                <textarea
                  class="form-control"
                  id="sql_preview"
                  name="sql_preview"
                  rows="3"
                  readonly
                ><?php echo $update_sql; ?></textarea>
            </div>

            <!-- CSV Upload -->
            <div class="form-group">
                <label for="datafile" class="font-weight-bold">Upload CSV File</label>
                <input
                  type="file"
                  class="form-control-file"
                  id="datafile"
                  name="datafile"
                  required
                >
            </div>

            <!-- Action Buttons -->
            <div class="form-group d-flex">
                <button
                  type="submit"
                  class="btn btn-success mr-2"
                  name="action"
                  value="Preview"
                >
                    <i class="fas fa-eye"></i> Preview
                </button>
                <button
                  type="submit"
                  class="btn btn-danger mr-2"
                  name="action"
                  value="Execute"
                >
                    <i class="fas fa-play"></i> Execute
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Admin Panel
                </a>
            </div>

            <!-- Data File Contents -->
            <?php if (!empty($csvData)): ?>
                <h4 class="mt-4">Data File Contents</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                        <tr>
                            <?php foreach ($csvKeys as $key): ?>
                                <th><?php echo htmlspecialchars($key); ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($csvData as $row): ?>
                            <tr>
                                <?php foreach ($row as $value): ?>
                                    <td><?php echo htmlspecialchars($value); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
