<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";
require_once "sql_functions.php";

// Define variables and initialize with empty values
$first_name = "";
$last_name = "";
$program_id = "";
$date_of_birth = "";
$email = "";
$phone_number = "";
$cause_number = "";
$referral_type_id = "";
$ethnicity_id = "";
$gender_id = "";
$required_sessions = "";
$fee = "";
$case_manager_id = "";
$therapy_group_id = "";
$client_stage_id = "";
$note = "";
$emergency_contact = "";
$orientation_date = "";
$exit_date = NULL;
$exit_reason_id = NULL;
$exit_note = "";
$weekly_attendance = "";
$attends_sunday = false;
$attends_sunday_t4c = false;
$attends_monday = false;
$attends_tuesday = false;
$attends_wednesday = false;
$attends_thursday = false;
$attends_friday = false;
$attends_saturday = false;

$speaksSignificantlyInGroup = "";
$respectfulTowardsGroup = "";
$takesResponsibilityForPastBehavior = "";
$disruptiveOrArgumentitive = "";
$inappropriateHumor = "";
$blamesVictim = "";
$drugAlcohol = "";
$inappropriateBehavior = "";
$other_concerns = "";
$behavior_contract_status = "";
$behavior_contract_signed_date = "";
$birth_place = "";
$intake_packet =0;

$first_name_err = "";
$last_name_err = "";
$program_id_err = "";
$date_of_birth_err = "";
$email_err = "";
$phone_number_err = "";
$cause_number_err = "";
$referral_type_id_err = "";
$ethnicity_id_err = "";
$gender_id_err = "";
$required_sessions_err = "";
$fee_err = "";
$case_manager_id_err = "";
$therapy_group_id_err = "";
$client_stage_id_err = "";
$note_err = "";
$emergency_contact_err = "";
$orientation_date_err = "";
$exit_date_err = "";
$exit_reason_id_err = "";
$exit_note_err = "";
$speaksSignificantlyInGroup_err = "";
$respectfulTowardsGroup_err = "";
$takesResponsibilityForPastBehavior_err = "";
$disruptiveOrArgumentitive_err = "";
$inappropriateHumor_err = "";
$blamesVictim_err = "";
$drugAlcohol_err = "";
$inappropriateBehavior_err = "";
$other_concerns_err = "";
$weekly_attendance_err = "";
$behavior_contract_status_err = "";
$behavior_contract_signed_date_err = "";
$birth_place_err = "";
$intake_packet_err = "";

// Processing form data when form is submitted
if (isset($_POST["id"]) && !empty($_POST["id"])) {
    // Get hidden input value
    $id = $_POST["id"];

    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $program_id = trim($_POST["program_id"]);
    $date_of_birth = trim($_POST["date_of_birth"]);
    $email = trim($_POST["email"]);
    $phone_number = formatPhone(trim($_POST["phone_number"]));
    $cause_number = trim($_POST["cause_number"]);
    $referral_type_id = trim($_POST["referral_type_id"]);
    $ethnicity_id = trim($_POST["ethnicity_id"]);
    $gender_id = trim($_POST["gender_id"]);
    $required_sessions = trim($_POST["required_sessions"]);
    $fee = trim($_POST["fee"]);
    $case_manager_id = trim($_POST["case_manager_id"]);
    $therapy_group_id = trim($_POST["therapy_group_id"]);
    $client_stage_id = trim($_POST["client_stage_id"]);
    $note = trim($_POST["note"]);
    $other_concerns = trim($_POST["other_concerns"]);
    $emergency_contact = trim($_POST["emergency_contact"]);
    $orientation_date = trim($_POST["orientation_date"]);
    $exit_date = trim($_POST["exit_date"]);
    $exit_reason_id = trim($_POST["exit_reason_id"]);
    $exit_note = trim($_POST["exit_note"]);
    $weekly_attendance = trim($_POST["weekly_attendance"]);
    $attends_sunday = isset($_POST['attends_sunday']) ? 1 : 0;
    $attends_sunday_t4c = isset($_POST['attends_sunday_t4c']) ? 1 : 0;
    $attends_monday = isset($_POST['attends_monday']) ? 1 : 0;
    $attends_tuesday = isset($_POST['attends_tuesday']) ? 1 : 0;
    $attends_wednesday = isset($_POST['attends_wednesday']) ? 1 : 0;
    $attends_thursday = isset($_POST['attends_thursday']) ? 1 : 0;
    $attends_friday = isset($_POST['attends_friday']) ? 1 : 0;
    $attends_saturday = isset($_POST['attends_saturday']) ? 1 : 0;
    $behavior_contract_status = trim($_POST["behavior_contract_status"] ?? "Not Needed");
    $behavior_contract_signed_date = trim($_POST["behavior_contract_signed_date"] ?? "");
    $birth_place = trim($_POST["birth_place"]);
    $intake_packet = isset($_POST['intake_packet']) ? 1 : 0;

    $speaksSignificantlyInGroup = isset($_POST['speaksSignificantlyInGroup']) ? 1 : 0;
    $respectfulTowardsGroup = isset($_POST['respectfulTowardsGroup']) ? 1 : 0;
    $takesResponsibilityForPastBehavior = isset($_POST['takesResponsibilityForPastBehavior']) ? 1 : 0;
    $disruptiveOrArgumentitive = isset($_POST['disruptiveOrArgumentitive']) ? 1 : 0;
    $inappropriateHumor = isset($_POST['inappropriateHumor']) ? 1 : 0;
    $blamesVictim = isset($_POST['blamesVictim']) ? 1 : 0;
    $drugAlcohol = isset($_POST['drugAlcohol']) ? 1 : 0;
    $inappropriateBehavior = isset($_POST['inappropriateBehavior']) ? 1 : 0;

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



    if (empty($orientation_date)) $orientation_date = NULL;
    if (empty($exit_date)) $exit_date = NULL;

    $stmt = $pdo->prepare("UPDATE client SET program_id=?,first_name=?,last_name=?,date_of_birth=?,gender_id=?,email=?,phone_number=?,cause_number=?,referral_type_id=?,ethnicity_id=?,required_sessions=?,fee=?,case_manager_id=?,therapy_group_id=?,client_stage_id=?,note=?,emergency_contact=?,orientation_date=?,exit_date=?,exit_reason_id=?,exit_note=?,speaksSignificantlyInGroup=?,respectfulTowardsGroup=?,takesResponsibilityForPastBehavior=?,disruptiveOrArgumentitive=?,inappropriateHumor=?,blamesVictim=?,drug_alcohol=?,inappropriate_behavior_to_staff=?,other_concerns=?,weekly_attendance=?,attends_sunday=?,attends_sunday_t4c=?,attends_monday=?,attends_tuesday=?,attends_wednesday=?,attends_thursday=?,attends_friday=?,attends_saturday=?,behavior_contract_status=?,behavior_contract_signed_date=?,birth_place=?,intake_packet=? WHERE id=?");
    if (!$stmt->execute([$program_id, $first_name, $last_name, $date_of_birth, $gender_id, $email, $phone_number, $cause_number, $referral_type_id, $ethnicity_id, $required_sessions, $fee, $case_manager_id, $therapy_group_id, $client_stage_id, $note, $emergency_contact, $orientation_date, $exit_date, $exit_reason_id, $exit_note, $speaksSignificantlyInGroup, $respectfulTowardsGroup, $takesResponsibilityForPastBehavior, $disruptiveOrArgumentitive, $inappropriateHumor, $blamesVictim, $drugAlcohol, $inappropriateBehavior, $other_concerns, $weekly_attendance, $attends_sunday, $attends_sunday_t4c, $attends_monday, $attends_tuesday, $attends_wednesday, $attends_thursday, $attends_friday, $attends_saturday, $behavior_contract_status, $behavior_contract_signed_date, $birth_place, $intake_packet, $id])) {
        echo "Something went wrong. Please try again later.";
        header("location: error.php");
    } else {
        $stmt = null;
        header("location: client-review.php?client_id=$id");
    }
} else {
    // Check existence of id parameter before processing further
    $_GET["id"] = trim($_GET["id"]);
    if (isset($_GET["id"]) && !empty($_GET["id"])) {
        // Get URL parameter
        $id =  trim($_GET["id"]);

        // Prepare a select statement
        $sql = "SELECT * FROM client WHERE id = ?";
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $id);

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);

                if (mysqli_num_rows($result) == 1) {
                    /* Fetch result row as an associative array. Since the result set
                    contains only one row, we don't need to use while loop */
                    $row = mysqli_fetch_array($result, MYSQLI_ASSOC);

                    // Retrieve individual field value
                    $program_id = htmlspecialchars($row["program_id"]);
                    $first_name = htmlspecialchars($row["first_name"]);
                    $last_name = htmlspecialchars($row["last_name"]);
                    $date_of_birth = htmlspecialchars($row["date_of_birth"]);
                    $email = htmlspecialchars($row["email"]);
                    $phone_number = htmlspecialchars($row["phone_number"]);
                    $cause_number = htmlspecialchars($row["cause_number"]);
                    $referral_type_id = htmlspecialchars($row["referral_type_id"]);
                    $ethnicity_id = htmlspecialchars($row["ethnicity_id"]);
                    $gender_id = htmlspecialchars($row["gender_id"]);
                    $required_sessions = htmlspecialchars($row["required_sessions"]);
                    $fee = htmlspecialchars($row["fee"]);
                    $case_manager_id = htmlspecialchars($row["case_manager_id"]);
                    $therapy_group_id = htmlspecialchars($row["therapy_group_id"]);
                    $client_stage_id = htmlspecialchars($row["client_stage_id"]);
                    $note = htmlspecialchars($row["note"]);
                    $emergency_contact = htmlspecialchars($row["emergency_contact"]);
                    $orientation_date = htmlspecialchars($row["orientation_date"]);
                    $exit_date = htmlspecialchars($row["exit_date"]);
                    $exit_reason_id = htmlspecialchars($row["exit_reason_id"]);
                    $exit_note = htmlspecialchars($row["exit_note"]);
                    $speaksSignificantlyInGroup = $row["speaksSignificantlyInGroup"];
                    $respectfulTowardsGroup = $row["respectfulTowardsGroup"];
                    $takesResponsibilityForPastBehavior = $row["takesResponsibilityForPastBehavior"];
                    $disruptiveOrArgumentitive = $row["disruptiveOrArgumentitive"];
                    $inappropriateHumor = $row["inappropriateHumor"];
                    $blamesVictim = $row["blamesVictim"];
                    $drugAlcohol = $row["drug_alcohol"];
                    $inappropriateBehavior = $row["inappropriate_behavior_to_staff"];
                    $other_concerns = htmlspecialchars($row["other_concerns"]);
                    $weekly_attendance = htmlspecialchars($row["weekly_attendance"]);
                    $attends_sunday = $row["attends_sunday"];
                    $attends_sunday_t4c = $row["attends_sunday_t4c"];
                    $attends_monday = $row["attends_monday"];
                    $attends_tuesday = $row["attends_tuesday"];
                    $attends_wednesday = $row["attends_wednesday"];
                    $attends_thursday = $row["attends_thursday"];
                    $attends_friday = $row["attends_friday"];
                    $attends_saturday = $row["attends_saturday"];
                    $behavior_contract_status = $row["behavior_contract_status"];
                    $behavior_contract_signed_date = $row["behavior_contract_signed_date"];
                    $birth_place = $row["birth_place"];
                    $intake_packet = $row["intake_packet"];
                } else {
                    // URL doesn't contain valid id. Redirect to error page
                    header("location: error.php");
                    exit();
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.<br>" . $stmt->error;
            }
        }

        // Close statement
        mysqli_stmt_close($stmt);
    } else {
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
    <title>NotesAO - Client Update</title>
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

    <!-- Bootstrap 4.5 CSS (No Integrity) -->
    <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
        crossorigin="anonymous">



    <!-- Font Awesome CSS -->
    <!-- Font Awesome (No Integrity) -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        crossorigin="anonymous" />


    <style>
      /* Match the styling/spacing of your other pages */
      body {
        padding-top: 56px; /* Offsets the fixed-top navbar (adjust if navbar is different height) */
        font-size: 16px;   /* Ensures consistent base font size */
      }
    </style>
</head>

<body>
    <!-- Include your shared navbar -->
    <?php require_once('navbar.php'); ?>

    <!-- Add top padding to the main section to match other pages -->
    <section class="pt-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <div class="page-header">
                        <h2>Update Client Record</h2>
                    </div>
                </div>
            </div>
            
            <!-- The Form -->
            <form autocomplete="off"
                  action="<?php echo htmlspecialchars(basename($_SERVER['REQUEST_URI'])); ?>"
                  method="post">
                
                <!-- Row 1 -->
                <div class="row">
                    <div class="col-2">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text"
                                   name="first_name"
                                   maxlength="45"
                                   class="form-control"
                                   value="<?php echo $first_name; ?>">
                            <span class="form-text"><?php echo $first_name_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text"
                                   name="last_name"
                                   maxlength="45"
                                   class="form-control"
                                   value="<?php echo $last_name; ?>">
                            <span class="form-text"><?php echo $last_name_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date"
                                   name="date_of_birth"
                                   class="form-control"
                                   value="<?php echo $date_of_birth; ?>">
                            <span class="form-text"><?php echo $date_of_birth_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Birth Place</label>
                            <input type="text"
                                name="birth_place"
                                maxlength="128"
                                class="form-control"
                                value="<?php echo $birth_place; ?>">
                            <span class="form-text"><?php echo $birth_place_err; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Row 2  ─────────────────────────────────────────────────────────────── -->
                <div class="row">
                <!-- Gender dropdown -->
                <div class="col-2">
                    <div class="form-group">
                    <label>Gender</label>
                    <select class="form-control" id="gender_id" name="gender_id">
                        <?php foreach (get_genders() as $g): ?>
                        <option value="<?=$g['id']?>" <?=$g['id']==$gender_id?'selected':''?>>
                            <?=htmlspecialchars($g['gender'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-text"><?=$gender_id_err?></span>
                    </div>
                </div>

                <!-- Ethnicity dropdown -->
                <div class="col-2">
                    <div class="form-group">
                    <label>Ethnicity</label>
                    <select class="form-control" id="ethnicity_id" name="ethnicity_id">
                        <?php foreach (get_ethnicities() as $e): ?>
                        <option value="<?=$e['id']?>" <?=$e['id']==$ethnicity_id?'selected':''?>>
                            <?=htmlspecialchars($e['code'].' - '.$e['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-text"><?=$ethnicity_id_err?></span>
                    </div>
                </div>

                <!-- Program dropdown -->
                <div class="col-2">
                    <div class="form-group">
                    <label>Program</label>
                    <select class="form-control" id="program_id" name="program_id">
                        <?php foreach (get_programs() as $p): ?>
                        <option value="<?=$p['id']?>" <?=$p['id']==$program_id?'selected':''?>>
                            <?=htmlspecialchars($p['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-text"><?=$program_id_err?></span>
                    </div>
                </div>

                <!-- ✔ Intake-packet checkbox (new column) -->
                <div class="col-2 d-flex align-items-end">
                    <div class="form-check mb-3">
                    <input class="form-check-input"
                            type="checkbox"
                            id="intake_packet"
                            name="intake_packet"
                            <?= $intake_packet ? 'checked' : '' ?>>
                    <label class="form-check-label" for="intake_packet">
                        Received&nbsp;Intake&nbsp;Packet
                    </label>
                    </div>
                </div>
                </div>


                <!-- Row 3 -->
                <div class="row">
                    <div class="col-2">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text"
                                   name="phone_number"
                                   maxlength="45"
                                   class="form-control"
                                   value="<?php echo $phone_number; ?>">
                            <span class="form-text"><?php echo $phone_number_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>E-Mail</label>
                            <input type="text"
                                   name="email"
                                   maxlength="64"
                                   class="form-control"
                                   autocomplete="off"
                                   value="<?php echo $email; ?>">
                            <span class="form-text"><?php echo $email_err; ?></span>
                        </div>
                    </div>
                    <div class="col-7">
                        <div class="form-group">
                            <label>Emergency Contact</label>
                            <input type="text"
                                   name="emergency_contact"
                                   maxlength="512"
                                   class="form-control"
                                   value="<?php echo $emergency_contact; ?>">
                            <span class="form-text"><?php echo $emergency_contact_err; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Row 4 -->
                <div class="row">
                    <div class="col-1">
                        <div class="form-group">
                            <label>Referral Type</label>
                            <select class="form-control"
                                    id="referral_type_id"
                                    name="referral_type_id">
                                <?php
                                    $referral_types = get_referral_types();
                                    foreach ($referral_types as $referral_type) {
                                        $value = htmlspecialchars($referral_type["referral_type"]);
                                        $selected = ($referral_type["id"] == $referral_type_id) ? 'selected' : '';
                                        echo "<option value='{$referral_type['id']}' {$selected}>$value</option>";
                                    }
                                ?>
                            </select>
                            <span class="form-text"><?php echo $referral_type_id_err; ?></span>
                        </div>
                    </div>
                    <div class="col-1">
                        <div class="form-group">
                            <label>Required Sessions</label>
                            <input type="number"
                                   name="required_sessions"
                                   class="form-control"
                                   value="<?php echo ($required_sessions == "") ? "15" : $required_sessions; ?>">
                            <span class="form-text"><?php echo $required_sessions_err; ?></span>
                        </div>
                    </div>
                    <div class="col-1">
                        <div class="form-group">
                            <label>Session per Week</label>
                            <input type="number"
                                   name="weekly_attendance"
                                   step="1"
                                   class="form-control"
                                   value="<?php echo ($weekly_attendance == "") ? "1" : $weekly_attendance; ?>">
                            <span class="form-text"><?php echo $weekly_attendance_err; ?></span>
                        </div>
                    </div>
                    <div class="col-1">
                        <div class="form-group">
                            <label>Fee per Session</label>
                            <input type="number"
                                   name="fee"
                                   step="1.00"
                                   class="form-control"
                                   value="<?php echo ($fee == "") ? "30.00" : $fee; ?>">
                            <span class="form-text"><?php echo $fee_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Case Manager</label>
                            <select class="form-control"
                                    id="case_manager_id"
                                    name="case_manager_id">
                                <?php
                                    $managers = get_case_managers();
                                    foreach ($managers as $manager) {
                                        $value = htmlspecialchars($manager["last_name"] . ", " . $manager["first_name"] . " - " . $manager["office"]);
                                        $selected = ($manager["id"] == $case_manager_id) ? 'selected' : '';
                                        echo "<option value='{$manager['id']}' {$selected}>$value</option>";
                                    }
                                ?>
                            </select>
                            <span class="form-text"><?php echo $case_manager_id_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>cause_number</label>
                            <input type="text"
                                   name="cause_number"
                                   maxlength="15"
                                   class="form-control"
                                   value="<?php echo $cause_number; ?>">
                            <span class="form-text"><?php echo $cause_number_err; ?></span>
                        </div>
                    </div>
                    
                </div>

                <!-- Row 5 -->
                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label>Therapy Group</label>
                            <select class="form-control"
                                    id="therapy_group_id"
                                    name="therapy_group_id">
                                <?php
                                    $groups = get_therapy_groups();
                                    foreach ($groups as $group) {
                                        $value = htmlspecialchars($group["name"] . " - " . $group["address"]);
                                        $selected = ($group["id"] == $therapy_group_id) ? 'selected' : '';
                                        echo "<option value='{$group['id']}' {$selected}>$value</option>";
                                    }
                                ?>
                            </select>
                            <span class="form-text"><?php echo $therapy_group_id_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Orientation Date</label>
                            <input type="date"
                                   name="orientation_date"
                                   class="form-control"
                                   value="<?php echo $orientation_date; ?>">
                            <span class="form-text"><?php echo $orientation_date_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Progress Stage</label>
                            <select class="form-control"
                                    id="client_stage_id"
                                    name="client_stage_id">
                                <?php
                                    $stages = get_client_stages();
                                    foreach ($stages as $stage) {
                                        $value = htmlspecialchars($stage["stage"]);
                                        $selected = ($stage["id"] == $client_stage_id) ? 'selected' : '';
                                        echo "<option value='{$stage['id']}' {$selected}>$value</option>";
                                    }
                                ?>
                            </select>
                            <span class="form-text"><?php echo $client_stage_id_err; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Attendance Fields -->
                <div class="row">
                    <div class="col-6">
                        <label>Attendance Day(s) - Select the days of the week the client plans to attend class</label>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <!-- Sunday -->
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="attends_sunday"
                                   <?php if ($attends_sunday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_sunday">Sunday</label>
                        </div>
                        <!-- Monday -->
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="attends_monday"
                                   <?php if ($attends_monday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_monday">Monday</label>
                        </div>
                        <!-- Tuesday -->
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="attends_tuesday"
                                   <?php if ($attends_tuesday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_tuesday">Tuesday</label>
                        </div>
                        <!-- Wednesday -->
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="attends_wednesday"
                                   <?php if ($attends_wednesday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_wednesday">Wednesday</label>
                        </div>
                        <!-- Thursday -->
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="attends_thursday"
                                   <?php if ($attends_thursday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_thursday">Thursday</label>
                        </div>
                        <!-- Friday -->
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="attends_friday"
                                   <?php if ($attends_friday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_friday">Friday</label>
                        </div>
                        <!-- Saturday -->
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="attends_saturday"
                                   <?php if ($attends_saturday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_saturday">Saturday</label>
                        </div>
                        <!-- extra Sunday (2× T4C) - only visible for Thinking for a Change -->
                        <div id="sun2wrap" class="form-check form-check-inline">
                            <input class="form-check-input"
                                    type="checkbox"
                                    name="attends_sunday_t4c"
                                    <?= $attends_sunday_t4c ? 'checked' : '' ?>>
                            <label class="form-check-label">Sunday&nbsp;(2× T4C)</label>
                        </div>

                    </div>
                </div>

                <!-- Client Notes & Other Concerns -->
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label>Client Notes</label>
                            <textarea name="note"
                                      maxlength="2048"
                                      class="form-control"><?php echo $note; ?></textarea>
                            <span class="form-text"><?php echo $note_err; ?></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label>Absence & Behavioral Risks</label>
                            <textarea name="other_concerns"
                                      maxlength="2048"
                                      class="form-control"><?php echo $other_concerns; ?></textarea>
                            <span class="form-text"><?php echo $other_concerns_err; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Exit Info -->
                <div class="row">
                    <div class="col-2">
                        <div class="form-group">
                            <label>Exit Date</label>
                            <input type="date"
                                   name="exit_date"
                                   class="form-control"
                                   value="<?php echo $exit_date; ?>">
                            <span class="form-text"><?php echo $exit_date_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Exit Reason</label>
                            <select class="form-control"
                                    id="exit_reason_id"
                                    name="exit_reason_id">
                                <?php
                                    $exit_reasons = get_exit_reasons();
                                    foreach ($exit_reasons as $exit_reason) {
                                        $value = htmlspecialchars($exit_reason["reason"]);
                                        $selected = ($exit_reason["id"] == $exit_reason_id) ? 'selected' : '';
                                        echo "<option value='{$exit_reason['id']}' {$selected}>$value</option>";
                                    }
                                ?>
                            </select>
                            <span class="form-text"><?php echo $exit_reason_id_err; ?></span>
                        </div>
                    </div>
                    <div class="col-5">
                        <div class="form-group">
                            <label>Exit Notes</label>
                            <textarea name="exit_note"
                                      maxlength="512"
                                      class="form-control"><?php echo $exit_note; ?></textarea>
                            <span class="form-text"><?php echo $exit_note_err; ?></span>
                        </div>
                    </div>

                </div>

                <!-- Behavior Checkboxes -->
                <div class="row">
                
                    <div class="col">
                        <label>Conduct</label>
                        <br>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="speaksSignificantlyInGroup"
                                   <?php if ($speaksSignificantlyInGroup == "1") echo "checked"; ?>>
                            <label class="form-check-label"
                                   for="speaksSignificantlyInGroup">Excessive speaking</label>
                        </div>
                        
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="respectfulTowardsGroup"
                                   <?php if ($respectfulTowardsGroup == "1") echo "checked"; ?>>
                            <label class="form-check-label"
                                   for="respectfulTowardsGroup">Respectful</label>
                        </div>
                        
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="takesResponsibilityForPastBehavior"
                                   <?php if ($takesResponsibilityForPastBehavior == "1") echo "checked"; ?>>
                            <label class="form-check-label"
                                   for="takesResponsibilityForPastBehavior">Takes responsibility</label>
                        </div>
                        
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="disruptiveOrArgumentitive"
                                   <?php if ($disruptiveOrArgumentitive == "1") echo "checked"; ?>>
                            <label class="form-check-label"
                                   for="disruptiveOrArgumentitive">Disruptive</label>
                        </div>
                        
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="inappropriateHumor"
                                   <?php if ($inappropriateHumor == "1") echo "checked"; ?>>
                            <label class="form-check-label"
                                   for="inappropriateHumor">Inappropriate humor</label>
                        </div>
                        
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="blamesVictim"
                                   <?php if ($blamesVictim == "1") echo "checked"; ?>>
                            <label class="form-check-label"
                                   for="blamesVictim">Blames victim</label>
                        </div>
                        
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="drugAlcohol"
                                   <?php if ($drugAlcohol == "1") echo "checked"; ?>>
                            <label class="form-check-label"
                                   for="drugAlcohol">Alcohol or drugs</label>
                        </div>
                        
                        <div class="form-check form-check-inline">
                            <input class="form-check-input"
                                   type="checkbox"
                                   value=""
                                   name="inappropriateBehavior"
                                   <?php if ($inappropriateBehavior == "1") echo "checked"; ?>>
                            <label class="form-check-label"
                                   for="inappropriateBehavior">Inappropriate behavior</label>
                        </div>
                    </div>
                </div>

                <!-- =======================
                    Behavior Contract Fields
                    ======================= -->
                <br>    
                <div class="form-group">
                    <label for="behavior_contract_status">Behavior Contract Status</label>
                    <select name="behavior_contract_status" id="behavior_contract_status" class="form-control">
                        <option value="Not Needed" <?= ($row['behavior_contract_status'] === "Not Needed") ? "selected" : "" ?>>Not Needed</option>
                        <option value="Needed"     <?= ($row['behavior_contract_status'] === "Needed") ? "selected" : "" ?>>Needed</option>
                        <option value="Signed"     <?= ($row['behavior_contract_status'] === "Signed") ? "selected" : "" ?>>Signed</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="behavior_contract_signed_date">Behavior Contract Signed Date</label>
                    <input type="date" 
                        name="behavior_contract_signed_date" 
                        id="behavior_contract_signed_date" 
                        class="form-control"
                        value="<?= htmlspecialchars($row['behavior_contract_signed_date'] ?? '') ?>">
                </div>



                <!-- Submission -->
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <!-- Hidden input to keep track of client id -->
                            <input type="hidden" name="id" value="<?php echo $id; ?>" />
                            <input type="submit" class="btn btn-primary" value="Submit">
                            <a href="client-review.php?client_id=<?php echo $id; ?>"
                               class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </form>
        </div> <!-- /.container-fluid -->
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', ()=>{
    const prog   = document.getElementById('program_id');
    const sun2   = document.getElementById('sun2wrap');
    const toggle = ()=>{ sun2.style.display = (+prog.value === 1) ? 'inline-block' : 'none'; };
    toggle();                 // initial
    prog.addEventListener('change', toggle);
    });
    </script>


    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" 
            integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" 
            crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" 
            integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" 
            crossorigin="anonymous"></script>

    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" 
            integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" 
            crossorigin="anonymous"></script>


</body>
</html>