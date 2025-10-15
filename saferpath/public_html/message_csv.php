<?php
include_once 'auth.php';
check_loggedin($con);

    require(__DIR__.'/../vendor/autoload.php');
    use Twilio\Rest\Client;

    // Include config file
    require_once "../config/config.php";
    require_once "helpers.php";

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

    $message_text = "";
    if(isset($_POST['message_text']) && !empty($_POST['message_text'])){
        $message_text = trim($_POST["message_text"]);
    }
    $messageKeys =  parseMessageForSubstituteVars($message_text);

    $csvArray = [];
    if(isset($_FILES['recepient_list']) && $_FILES['recepient_list']['tmp_name']) {
        // File was sent - use it for recepient data
        $tmpName = $_FILES['recepient_list']['tmp_name'];
        $csvArray = array_map('str_getcsv', file($tmpName));

        // WARNING - The file may or may not contain a byte order mark "magic number"
        // If it does this strips off the trash from the first item
        $str = $csvArray[0][0];
        if (mb_detect_encoding($str) === 'UTF-8') {
            $csvArray[0][0] = preg_replace('/\x{EF}\x{BB}\x{BF}/', '', $str);
        }
    }
    else if (isset($_POST['csvJson'])){
        // File was NOT sent - use serialized recepient data
        $csvArray = json_decode($_POST['csvJson']);
    }

    $csvKeys = [];
    $csvKeysWithDelim = [];
    $csvData = [];
    if(count($csvArray) > 0) {
        $csvTemp = $csvArray;
        // Get the headder row
        $csvKeys = array_shift($csvTemp);
        foreach($csvKeys as $key){
            $csvKeysWithDelim[] = "[" . $key . "]";
        }
        // Get the data rows
        foreach($csvTemp as $row) {
            $csvData[] = array_combine($csvKeys, $row);
        }
    }

    function parseMessageForSubstituteVars($message_text) {
        $keys = [];

        $indexStart = strpos($message_text, "[");
        while($indexStart !== false){
            $indexStop = strpos($message_text, "]", $indexStart);
            if($indexStop !== false) {
                $keys[] = substr($message_text, $indexStart+1,($indexStop-$indexStart)-1);
                $indexStart = strpos($message_text, "[", $indexStop);
            }
            else {
                // No close brace - we are done
                $indexStart = false;
            }
        }
        return $keys;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'Send') {
        // send messages
        $client = new Client(twilio_sid, twilio_token);
        $mesage_results = [];
        if(isset($csvData) && count($csvData) > 0){
            foreach($csvData as $row){

                $phoneNumber = "+1".$row['phone'];
                $badPhoneChars = array("(", ")", "-", " ");
                $phoneNumber = str_replace($badPhoneChars, "", $phoneNumber);
                $messageBody = str_replace($csvKeysWithDelim, $row, $message_text);
                $msgId = "";
                $msgError = "";
                $msgStatus = "";

                if($sendSMS) {
                    try {
                        $twilMsg = $client->messages->create(
                            $phoneNumber,
                            [
                                'from' => twilio_number,
                                'body' => $messageBody
                            ]
                        );
                        $msgId = $twilMsg->sid;
                        $msgStatus = $twilMsg->status;
                        $msgError = $twilMsg->errorMessage;
                    }
                    catch (Exception $e) {
                        $msgError = $e->getMessage();
                    }
                    insertClientEvent($pdo, $phoneNumber, $msgId, $msgStatus, $msgError, $messageBody);
                }
                $mesage_results[] = array($phoneNumber, $msgId, $msgStatus, $msgError);
            }
        }
    }

    function insertClientEvent($pdo, $phoneNumber, $msgId, $msgStatus, $msgError, $messageBody){
//        $insertSql = "INSERT INTO client_event (client_id, client_event_type_id, date, note) SELECT id, (SELECT id FROM client_event_type where event_type = 'Text Message'), now(), ? FROM client where REGEXP_REPLACE(phone_number, '[^0-9]', '') = ?";
        $insertSql = "INSERT INTO client_event (client_id, client_event_type_id, date, note) SELECT id, (SELECT id FROM client_event_type where event_type = 'Text Message'), now(), ? FROM client where replace(replace(replace(phone_number,'-',''),'(',''),')','') = ?";
                
        $stmt = $pdo->prepare($insertSql);
        $note = "Text msg sent to " . $phoneNumber . " msgId: " . $msgId . " " . $msgStatus . " " . $msgError . " " . $messageBody;
        $note = truncate($note, 2048);
        $phoneNumber = substr($phoneNumber, 2);  // Remove +1 from front of phone
        $stmt->execute([$note, $phoneNumber]);
        $stmt = null;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Messaging (CSV)</title>
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
    <section class="pt-2">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <div class="page-header">
                        <h2>Messaging</h2>
                    </div>
                </div>
            </div>
            <?php 
            if (isset($_POST['action']) && $_POST['action'] == 'Send') {
                echo "<table class='table table-bordered table-striped'>";
                echo "<thead>";
                // Headder Row
                echo "<tr>";
                echo "<th>number</th>";
                echo "<th>message id</th>";
                echo "<th>message status</th>";
                echo "<th>error</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";
                foreach($mesage_results as $result){
                    echo "<tr>";
                    foreach($result as $value){
                        echo "<td>".$value."</td>";
                    }
                    echo "</tr>";
                }
                echo "</tbody>";
                echo "</table>";
                echo "<a href='index.php' class='btn btn-dark'>Return to Index</a>";
            }
            else {
                include_once("message_csv_buildform.php");
            }
            ?>
        </div>
    </section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>
</html>