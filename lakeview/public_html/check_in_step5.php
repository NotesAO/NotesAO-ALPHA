<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";

// Define variables and initialize with empty values
$paid = "";

$client_id_err = "";
$therapy_session_id_err = "";
$paid_err = "";

$therapy_session_id = "";
if (isset($_GET['therapy_session_id'])) {
    $therapy_session_id = $_GET['therapy_session_id'];
}
if (isset($_POST['therapy_session_id'])) {
    $therapy_session_id = $_POST['therapy_session_id'];
}
$therapy_session_id_err = "";

$client_id = "";
if (isset($_GET['client_id'])) {
    $client_id = $_GET['client_id'];
}
if (isset($_POST['client_id'])) {
    $client_id = $_POST['client_id'];
}
if ($client_id === '' || $client_id === null) {
    header("location: error.php");
    exit();
}
$client_id_err = "";


// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
		$paid = trim($_POST["paid"]);
		$fee = trim($_POST["fee"]);
		$other_concerns = trim($_POST["other_concerns"]);
        $phone_number = formatPhone(trim($_POST["phone_number"]));
        $email = trim($_POST["email"]);
        $client_stage_id = trim($_POST["client_stage_id"]);
        $case_manager_id = trim($_POST["case_manager_id"]);
        $speaksSignificantlyInGroup = isset($_POST['speaksSignificantlyInGroup']) ? 1 : 0;
        $respectfulTowardsGroup = isset($_POST['respectfulTowardsGroup']) ? 1 : 0;
        $takesResponsibilityForPastBehavior = isset($_POST['takesResponsibilityForPastBehavior']) ? 1 : 0;
        $disruptiveOrArgumentitive = isset($_POST['disruptiveOrArgumentitive']) ? 1 : 0;
        $inappropriateHumor = isset($_POST['inappropriateHumor']) ? 1 : 0;
        $blamesVictim = isset($_POST['blamesVictim']) ? 1 : 0;
        $drugAlcohol = (isset($_POST['drug_alcohol']) || isset($_POST['drugAlcohol'])) ? 1 : 0;
        $inappropriateBehavior = (isset($_POST['inappropriate_behavior_to_staff']) || isset($_POST['inappropriateBehavior'])) ? 1 : 0;
        $attends_sunday = isset($_POST['attends_sunday']) ? 1 : 0;
        $attends_monday = isset($_POST['attends_monday']) ? 1 : 0;
        $attends_tuesday = isset($_POST['attends_tuesday']) ? 1 : 0;
        $attends_wednesday = isset($_POST['attends_wednesday']) ? 1 : 0;
        $attends_thursday = isset($_POST['attends_thursday']) ? 1 : 0;
        $attends_friday = isset($_POST['attends_friday']) ? 1 : 0;
        $attends_saturday = isset($_POST['attends_saturday']) ? 1 : 0;
        $therapy_group_id = trim($_POST["therapy_group_id"]);

        // new plan fields from step 4
        $paid_source = $_POST['paid_source'] ?? 'Weekly';
        $paid_intake = (($_POST['paid_intake'] ?? 'no') === 'yes');
        $is_weekly   = ($paid_source === 'Weekly');

        // normalize amounts
        $fee  = is_numeric($fee)  ? (float)$fee  : 0.0;
        $paid = is_numeric($paid) ? (float)$paid : 0.0;

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

        // Insert the attendance Record
        $vars = parse_columns('attendance_record', $_POST);
        // Safe insert if mass check-in already created a record
        $stmt = $pdo->prepare("INSERT IGNORE INTO attendance_record (client_id, therapy_session_id) VALUES (?, ?)");
        if ($stmt->execute([$client_id, $therapy_session_id])) { $stmt = null; } else { echo "Something went wrong. Please try again later."; }

        // Try to mark as attended if those columns exist (ignore error if not)
        try {
            $stmt = $pdo->prepare("UPDATE attendance_record SET attended=1, excused=0 WHERE client_id=? AND therapy_session_id=?");
            $stmt->execute([$client_id, $therapy_session_id]);
            $stmt = null;
        } catch (Throwable $e) {
            // table without attended/excused; ignore
        }

        // Update the client conduct
        $stmt = $pdo->prepare("UPDATE client set speaksSignificantlyInGroup = ?, respectfulTowardsGroup = ?, takesResponsibilityForPastBehavior = ?, disruptiveOrArgumentitive = ?, inappropriateHumor = ?, blamesVictim = ?, drug_alcohol = ?, inappropriate_behavior_to_staff = ? WHERE ID = ?");
        if($stmt->execute([ $speaksSignificantlyInGroup, $respectfulTowardsGroup, $takesResponsibilityForPastBehavior, $disruptiveOrArgumentitive, $inappropriateHumor, $blamesVictim, $drugAlcohol, $inappropriateBehavior, $client_id ])) {
            $stmt = null;
//                header("location: attendance_record-index.php");
        } else{
            echo "Something went wrong. Please try again later.";
        }

        // Update the client attends days
        $stmt = $pdo->prepare("UPDATE client set attends_sunday = ?, attends_monday = ?, attends_tuesday = ?, attends_wednesday = ?, attends_thursday = ?, attends_friday = ?, attends_saturday = ? WHERE ID = ?");
        if($stmt->execute([ $attends_sunday, $attends_monday, $attends_tuesday, $attends_wednesday, $attends_thursday, $attends_friday, $attends_saturday, $client_id ])) {
            $stmt = null;
//                header("location: attendance_record-index.php");
        } else{
            echo "Something went wrong. Please try again later.";
        }
        
        // Update the client contact info
        $stmt = $pdo->prepare("UPDATE client set email = ?, phone_number = ?, fee = ?, case_manager_id = ?, client_stage_id = ?, therapy_group_id = ?, other_concerns = ? WHERE ID = ?");
        if($stmt->execute([ $email, $phone_number, $fee, $case_manager_id, $client_stage_id, $therapy_group_id, $other_concerns, $client_id ])) {
            $stmt = null;
//                header("location: attendance_record-index.php");
        } else{
            echo "Something went wrong. Please try again later.";
        }

        // Insert the fee for attendance (weekly plans only) — idempotent by note
        if ($is_weekly && $fee > 0) {
            $ledger_note = "Attendance fee session_id " . $therapy_session_id;
            $stmt = $pdo->prepare("SELECT 1 FROM ledger WHERE client_id=? AND note=? LIMIT 1");
            $stmt->execute([$client_id, $ledger_note]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO ledger (client_id,amount,note) VALUES (?,?,?)");
                if (!$stmt->execute([$client_id, -1.0 * $fee, $ledger_note])) {
                    echo "Something went wrong. Please try again later.";
                }
            }
        }


        // Insert the amount paid at check-in (skip if one-time already paid at intake). Idempotent by note
        if ($paid > 0 && !(!$is_weekly && $paid_intake)) {
            $note_suffix = $is_weekly ? "" : (" - " . $paid_source);
            $ledger_note = "Paid at Check-In session_id " . $therapy_session_id . $note_suffix;

            $stmt = $pdo->prepare("SELECT 1 FROM ledger WHERE client_id=? AND note=? LIMIT 1");
            $stmt->execute([$client_id, $ledger_note]);
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO ledger (client_id,amount,note) VALUES (?,?,?)");
                if (!$stmt->execute([$client_id, $paid, $ledger_note])) {
                    echo "Something went wrong. Please try again later.";
                }
            }
        }


        // ---- Milestone completions from Step 4 ----
        $milestones = isset($_POST['milestone_complete']) && is_array($_POST['milestone_complete'])
            ? array_values(array_unique(array_map('intval', $_POST['milestone_complete'])))
            : [];

        if ($milestones) {
            // resolve client's program_id
            $pid = 0;
            $stmt = $pdo->prepare("SELECT program_id FROM client WHERE id=?");
            $stmt->execute([$client_id]);
            if ($row = $stmt->fetch()) $pid = (int)$row['program_id'];
            $stmt = null;

            // session date stamp (midday to avoid boundary issues)
            $sessDate = null;
            $stmt = $pdo->prepare("SELECT DATE(`date`) AS d FROM therapy_session WHERE id=?");
            $stmt->execute([$therapy_session_id]);
            if ($row = $stmt->fetch()) { $sessDate = $row['d']; }
            $stmt = null;
            $stampDT = ($sessDate ?: date('Y-m-d')).' 12:00:00';
            $user_id = (int)($_SESSION['id'] ?? 0);

            // upsert attendance_curriculum rows with session link
            $sql = "INSERT INTO attendance_curriculum
                        (client_id, program_id, curriculum_id, session_id, completed_at, staff_user_id, source)
                    VALUES (?, ?, ?, ?, ?, ?, 'checkin')
                    ON DUPLICATE KEY UPDATE
                        completed_at=VALUES(completed_at),
                        session_id=VALUES(session_id),
                        staff_user_id=VALUES(staff_user_id),
                        source=VALUES(source)";
            $ins = $pdo->prepare($sql);

            foreach ($milestones as $curId) {
                $ins->execute([
                    (int)$client_id,
                    (int)$pid,
                    (int)$curId,
                    (int)$therapy_session_id,
                    $stampDT,
                    $user_id
                ]);
            }
            $ins = null;

            // ensure attendance exists and marked attended=1, excused=0
            try {
                $stmt = $pdo->prepare("INSERT IGNORE INTO attendance_record (client_id, therapy_session_id) VALUES (?, ?)");
                $stmt->execute([$client_id, $therapy_session_id]);
                $stmt = null;

                $stmt = $pdo->prepare("UPDATE attendance_record SET attended=1, excused=0 WHERE client_id=? AND therapy_session_id=?");
                $stmt->execute([$client_id, $therapy_session_id]);
                $stmt = null;
            } catch (Throwable $e) {
                // ignore if schema lacks attended/excused
            }
        }

    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Check-In</title>
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
<body>
    <?php require_once('navbar.php'); ?>

    <section class="pt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col">
                    <p>Attendance Record Created</p>
                    <a href="check_in_step3.php?therapy_session_id=<?=$therapy_session_id;?>" class="btn btn-primary">Check In Next Client</a> 
                </div>
            </div>
        </div>
    </section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>
</html>