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
$facilitator_id = "";
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
$facilitator_id_err = "";
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

    // --- NEW: read & normalize the added demographic fields ---
    $external_id      = trim($_POST["external_id"] ?? "");

    $preferred_name   = trim($_POST["preferred_name"] ?? "");
    $middle_name      = trim($_POST["middle_name"] ?? "");

    $street           = trim($_POST["street"] ?? "");
    $city             = trim($_POST["city"] ?? "");

    // Uppercase and clamp state to 2 letters, removing non-letters
    $state_input      = $_POST["state"] ?? "";
    $state_clean      = preg_replace('/[^A-Za-z]/', '', strtoupper(trim($state_input)));
    $state            = substr($state_clean, 0, 2);

    $zip              = trim($_POST["zip"] ?? "");
    $county           = trim($_POST["county"] ?? "");

    // Normalize all phones consistently
    $phone_number_home = formatPhone(trim($_POST["phone_number_home"] ?? ""));
    $phone_number_work = formatPhone(trim($_POST["phone_number_work"] ?? ""));

    $marital_status   = trim($_POST["marital_status"] ?? "");
    $religion         = trim($_POST["religion"] ?? "");
    $employment       = trim($_POST["employment"] ?? "");
    $employer         = trim($_POST["employer"] ?? "");

    // Free text, keep as-is (you already escape on output)
    $diagnostic_codes = trim($_POST["diagnostic_codes"] ?? "");


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
    $facilitator_id = trim($_POST["facilitator_id"] ?? "");
    if ($facilitator_id === "") $facilitator_id = NULL;
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

    $stmt = $pdo->prepare("
        UPDATE client SET
            program_id=?, first_name=?, preferred_name=?, middle_name=?, last_name=?,
            date_of_birth=?, gender_id=?, email=?, phone_number=?, phone_number_home=?, phone_number_work=?,
            cause_number=?, referral_type_id=?, ethnicity_id=?,
            required_sessions=?, fee=?, case_manager_id=?, therapy_group_id=?, client_stage_id=?,
            note=?, emergency_contact=?,
            street=?, city=?, state=?, zip=?, county=?,
            marital_status=?, religion=?, employment=?, employer=?, diagnostic_codes=?,
            orientation_date=?, exit_date=?, exit_reason_id=?, exit_note=?,
            speaksSignificantlyInGroup=?, respectfulTowardsGroup=?, takesResponsibilityForPastBehavior=?,
            disruptiveOrArgumentitive=?, inappropriateHumor=?, blamesVictim=?, drug_alcohol=?, inappropriate_behavior_to_staff=?,
            other_concerns=?, weekly_attendance=?, attends_sunday=?, attends_sunday_t4c=?, attends_monday=?,
            attends_tuesday=?, attends_wednesday=?, attends_thursday=?, attends_friday=?, attends_saturday=?,
            behavior_contract_status=?, behavior_contract_signed_date=?, birth_place=?, intake_packet=?, facilitator_id=?,
            external_id=?
        WHERE id=?");
    if (!$stmt->execute([
        $program_id, $first_name, $preferred_name, $middle_name, $last_name,
        $date_of_birth, $gender_id, $email, $phone_number, $phone_number_home, $phone_number_work,
        $cause_number, $referral_type_id, $ethnicity_id,
        $required_sessions, $fee, $case_manager_id, $therapy_group_id, $client_stage_id,
        $note, $emergency_contact,
        $street, $city, $state, $zip, $county,
        $marital_status, $religion, $employment, $employer, $diagnostic_codes,
        $orientation_date, $exit_date, $exit_reason_id, $exit_note,
        $speaksSignificantlyInGroup, $respectfulTowardsGroup, $takesResponsibilityForPastBehavior,
        $disruptiveOrArgumentitive, $inappropriateHumor, $blamesVictim, $drugAlcohol, $inappropriateBehavior,
        $other_concerns, $weekly_attendance, $attends_sunday, $attends_sunday_t4c, $attends_monday,
        $attends_tuesday, $attends_wednesday, $attends_thursday, $attends_friday, $attends_saturday,
        $behavior_contract_status, $behavior_contract_signed_date, $birth_place, $intake_packet, $facilitator_id,
        $external_id,
        $id
        ])) {
        echo "Something went wrong. Please try again later.";
        header("location: error.php");
    } else {
      // ---------- EXIT CAUSES: write selections ----------
      try {
          $pdo->beginTransaction();

          // Current selections from form
          $selected_ids = array_map('intval', $_POST['exit_causes'] ?? []);
          $other_text   = trim($_POST['exit_cause_other'] ?? '');

          // Map id->code so we can attach text only to OTHER
          $mapStmt = $pdo->query("SELECT id, code FROM exit_cause");
          $codeById = [];
          foreach ($mapStmt as $r) $codeById[(int)$r['id']] = $r['code'];

          // Reset existing rows for this client
          $del = $pdo->prepare("DELETE FROM client_exit_cause WHERE client_id = ?");
          $del->execute([$id]);

          // Insert new rows
          $ins = $pdo->prepare("INSERT INTO client_exit_cause (client_id, exit_cause_id, other_text) VALUES (?, ?, ?)");
          foreach ($selected_ids as $cid) {
              $text = (isset($codeById[$cid]) && $codeById[$cid] === 'OTHER') ? ($other_text ?: null) : null;
              $ins->execute([$id, $cid, $text]);
          }

          $pdo->commit();
      } catch (Throwable $t) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          error_log("Exit cause save failed for client {$id}: ".$t->getMessage());
      }

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
                    $facilitator_id = htmlspecialchars($row["facilitator_id"] ?? "");
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

                    $external_id        = htmlspecialchars($row["external_id"] ?? "");
                    $preferred_name     = htmlspecialchars($row["preferred_name"] ?? "");
                    $middle_name        = htmlspecialchars($row["middle_name"] ?? "");
                    $street             = htmlspecialchars($row["street"] ?? "");
                    $city               = htmlspecialchars($row["city"] ?? "");
                    $state              = htmlspecialchars($row["state"] ?? "");
                    $zip                = htmlspecialchars($row["zip"] ?? "");
                    $county             = htmlspecialchars($row["county"] ?? "");
                    $phone_number_home  = htmlspecialchars($row["phone_number_home"] ?? "");
                    $phone_number_work  = htmlspecialchars($row["phone_number_work"] ?? "");
                    $marital_status     = htmlspecialchars($row["marital_status"] ?? "");
                    $religion           = htmlspecialchars($row["religion"] ?? "");
                    $employment         = htmlspecialchars($row["employment"] ?? "");
                    $employer           = htmlspecialchars($row["employer"] ?? "");
                    $diagnostic_codes   = htmlspecialchars($row["diagnostic_codes"] ?? "");
                    // ---- Exit causes currently set for this client ----
                    $selected_exit_causes = [];
                    $exit_cause_other     = "";
                    if ($stmt2 = mysqli_prepare($link, "SELECT cec.exit_cause_id, COALESCE(cec.other_text,'') AS other_text, ec.code
                                                        FROM client_exit_cause cec
                                                        JOIN exit_cause ec ON ec.id = cec.exit_cause_id
                                                        WHERE cec.client_id = ?")) {
                        mysqli_stmt_bind_param($stmt2, "i", $id);
                        if (mysqli_stmt_execute($stmt2)) {
                            $res2 = mysqli_stmt_get_result($stmt2);
                            while ($r = mysqli_fetch_assoc($res2)) {
                                $selected_exit_causes[] = (int)$r['exit_cause_id'];
                                if ($r['code'] === 'OTHER') $exit_cause_other = $r['other_text'];
                            }
                        }
                        mysqli_stmt_close($stmt2);
                    }


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
      .muted-label{ font-size:.85rem;color:#6c757d;text-transform:uppercase;letter-spacing:.04em }
      .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace }
    </style>
</head>

<body>
  <!-- Shared navbar -->
  <?php require_once('navbar.php'); ?>

  <section class="pt-3">
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-6">
          <div class="page-header">
            <h2>Update Client Record</h2>
          </div>
        </div>
      </div>

      <!-- Form -->
      <form autocomplete="off"
            action="<?php echo htmlspecialchars(basename($_SERVER['REQUEST_URI'])); ?>"
            method="post">

        <!-- =================== Identity =================== -->
        <div class="row">
          <div class="col-2">
            <div class="form-group">
              <label class="muted-label">External ID</label>
              <input type="text" name="external_id" maxlength="64" class="form-control mono" value="<?=$external_id?>">
            </div>
          </div>
          <div class="col-2">
            <div class="form-group">
              <label class="muted-label">First Name</label>
              <input type="text" name="first_name" maxlength="45" class="form-control" value="<?=$first_name?>">
              <span class="form-text"><?=$first_name_err?></span>
            </div>
          </div>
          <div class="col-2">
            <div class="form-group">
              <label class="muted-label">Preferred</label>
              <input type="text" name="preferred_name" maxlength="75" class="form-control" value="<?=$preferred_name?>">
            </div>
          </div>
          <div class="col-2">
            <div class="form-group">
              <label class="muted-label">Middle</label>
              <input type="text" name="middle_name" maxlength="75" class="form-control" value="<?=$middle_name?>">
            </div>
          </div>
          <div class="col-2">
            <div class="form-group">
              <label class="muted-label">Last Name</label>
              <input type="text" name="last_name" maxlength="45" class="form-control" value="<?=$last_name?>">
              <span class="form-text"><?=$last_name_err?></span>
            </div>
          </div>
        </div>

        <!-- =================== DOB / Gender / Email =================== -->
        <div class="row">
          <div class="col-3">
            <div class="form-group">
              <label class="muted-label">Date of Birth</label>
              <input type="date" name="date_of_birth" class="form-control" value="<?=$date_of_birth?>">
              <span class="form-text"><?=$date_of_birth_err?></span>
            </div>
          </div>
          <div class="col-2">
            <div class="form-group">
              <label class="muted-label">Gender</label>
              <select class="form-control" id="gender_id" name="gender_id">
                <?php foreach (get_genders() as $g): ?>
                  <option value="<?=$g['id']?>" <?=$g['id']==$gender_id?'selected':''?>><?=htmlspecialchars($g['gender'])?></option>
                <?php endforeach; ?>
              </select>
              <span class="form-text"><?=$gender_id_err?></span>
            </div>
          </div>
          <div class="col-4">
            <div class="form-group">
              <label class="muted-label">E-Mail</label>
              <input type="email" name="email" maxlength="64" class="form-control" autocomplete="off" value="<?=$email?>">
              <span class="form-text"><?=$email_err?></span>
            </div>
          </div>
          <div class="col-3">
            <div class="form-group">
              <label class="muted-label">Birth Place</label>
              <input type="text" name="birth_place" maxlength="128" class="form-control" value="<?=$birth_place?>">
              <span class="form-text"><?=$birth_place_err?></span>
            </div>
          </div>
        </div>

        <!-- =================== Address =================== -->
        <div class="row">
          <div class="col-5">
            <label class="muted-label">Street</label>
            <input type="text" name="street" maxlength="120" class="form-control" value="<?=$street?>">
          </div>
          <div class="col-4">
            <label class="muted-label">City / State / ZIP</label>
            <div class="d-flex">
              <input type="text" name="city"  maxlength="75" class="form-control mr-2" style="flex:2" value="<?=$city?>">
              <input type="text" name="state" maxlength="2"  class="form-control mr-2 text-uppercase" style="flex:1" value="<?=$state?>" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z]/g,'').slice(0,2);">
              <input type="text" name="zip"   maxlength="10" class="form-control" style="flex:2" value="<?=$zip?>">
            </div>
          </div>
          <div class="col-3">
            <label class="muted-label">County</label>
            <input type="text" name="county" maxlength="75" class="form-control" value="<?=$county?>">
          </div>
        </div>

        <!-- =================== Phones =================== -->
        <div class="row mt-3">
          <div class="col-4">
            <label class="muted-label">Mobile Phone</label>
            <input type="text" name="phone_number" maxlength="45" class="form-control" value="<?=$phone_number?>">
            <span class="form-text"><?=$phone_number_err?></span>
          </div>
          <div class="col-4">
            <label class="muted-label">Home Phone</label>
            <input type="text" name="phone_number_home" maxlength="30" class="form-control" value="<?=$phone_number_home?>">
          </div>
          <div class="col-4">
            <label class="muted-label">Work Phone</label>
            <input type="text" name="phone_number_work" maxlength="30" class="form-control" value="<?=$phone_number_work?>">
          </div>
        </div>

        <!-- =================== Program / Group / Orientation / Stage / Intake Packet =================== -->
        <div class="row mt-4">
          <div class="col-3">
            <label class="muted-label">Program</label>
            <select class="form-control" id="program_id" name="program_id">
              <?php foreach (get_programs() as $p): ?>
                <option value="<?=$p['id']?>" <?=$p['id']==$program_id?'selected':''?>><?=htmlspecialchars($p['name'])?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-text"><?=$program_id_err?></span>
          </div>
          <div class="col-4">
            <label class="muted-label">Therapy Group</label>
            <select class="form-control" id="therapy_group_id" name="therapy_group_id">
              <?php foreach (get_therapy_groups() as $group): ?>
                <option value="<?=$group['id']?>" <?=$group['id']==$therapy_group_id?'selected':''?>><?=htmlspecialchars($group["name"] . " - " . $group["address"]) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-text"><?=$therapy_group_id_err?></span>
          </div>
          <div class="col-2">
            <label class="muted-label">Orientation Date</label>
            <input type="date" name="orientation_date" class="form-control" value="<?=$orientation_date?>">
            <span class="form-text"><?=$orientation_date_err?></span>
          </div>
          <div class="col-2">
            <label class="muted-label">Progress Stage</label>
            <select class="form-control" id="client_stage_id" name="client_stage_id">
              <?php foreach (get_client_stages() as $stage): ?>
                <option value="<?=$stage['id']?>" <?=$stage['id']==$client_stage_id?'selected':''?>><?=htmlspecialchars($stage["stage"]) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-text"><?=$client_stage_id_err?></span>
          </div>
          <div class="col-1 d-flex align-items-end">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="intake_packet" name="intake_packet" <?= $intake_packet ? 'checked' : '' ?>>
              <label class="form-check-label" for="intake_packet">Intake</label>
            </div>
          </div>
        </div>

        <!-- =================== Referral / Cause / Case / Ethnicity =================== -->
        <div class="row mt-3">
          <div class="col-3">
            <label class="muted-label">Referral Type</label>
            <select class="form-control" id="referral_type_id" name="referral_type_id">
              <?php foreach (get_referral_types() as $ref): ?>
                <option value="<?=$ref['id']?>" <?=$ref['id']==$referral_type_id?'selected':''?>><?=htmlspecialchars($ref["referral_type"]) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-text"><?=$referral_type_id_err?></span>
          </div>
          <div class="col-3">
            <label class="muted-label">Cause Number</label>
            <input type="text" name="cause_number" maxlength="15" class="form-control" value="<?=$cause_number?>">
            <span class="form-text"><?=$cause_number_err?></span>
          </div>
          <div class="col-3">
            <label class="muted-label">Case Manager</label>
            <select class="form-control" id="case_manager_id" name="case_manager_id">
              <?php foreach (get_case_managers() as $m): ?>
                <option value="<?=$m['id']?>" <?=$m['id']==$case_manager_id?'selected':''?>><?=htmlspecialchars($m["last_name"] . ", " . $m["first_name"] . " - " . $m["office"]) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-text"><?=$case_manager_id_err?></span>
          </div>
          <div class="col-3">
            <label class="muted-label">Ethnicity</label>
            <select class="form-control" id="ethnicity_id" name="ethnicity_id">
              <?php foreach (get_ethnicities() as $e): ?>
                <option value="<?=$e['id']?>" <?=$e['id']==$ethnicity_id?'selected':''?>><?=htmlspecialchars($e['code'].' - '.$e['name'])?></option>
              <?php endforeach; ?>
            </select>
            <span class="form-text"><?=$ethnicity_id_err?></span>
          </div>
        </div>

        <!-- =================== Sessions / Fee =================== -->
        <div class="row mt-3">
          <div class="col-2">
            <label class="muted-label">Required Sessions</label>
            <input type="number" name="required_sessions" class="form-control" value="<?= ($required_sessions === "") ? "15" : $required_sessions; ?>">
            <span class="form-text"><?=$required_sessions_err?></span>
          </div>
          <div class="col-2">
            <label class="muted-label">Sessions / Week</label>
            <input type="number" name="weekly_attendance" step="1" class="form-control" value="<?= ($weekly_attendance === "") ? "1" : $weekly_attendance; ?>">
            <span class="form-text"><?=$weekly_attendance_err?></span>
          </div>
          <div class="col-2">
            <label class="muted-label">Fee / Session</label>
            <input type="number" name="fee" step="1.00" class="form-control" value="<?= ($fee === "") ? "30.00" : $fee; ?>">
            <span class="form-text"><?=$fee_err?></span>
          </div>
          <div class="col-3">
            <label class="muted-label">Facilitator</label>
            <select class="form-control" id="facilitator_id" name="facilitator_id">
              <option value="" <?= empty($facilitator_id) ? 'selected' : '' ?>>Not Specified</option>
              <?php
                $res = mysqli_query($link, "SELECT id, first_name, last_name, COALESCE(phone,'') AS phone FROM facilitator ORDER BY last_name, first_name");
                if ($res) {
                  while ($f = mysqli_fetch_assoc($res)) {
                    $idv = (int)$f['id'];
                    $label = htmlspecialchars($f['last_name'].", ".$f['first_name'].($f['phone']?" — ".$f['phone']:""));
                    $sel = ($idv == (int)$facilitator_id) ? "selected" : "";
                    echo "<option value=\"$idv\" $sel>$label</option>";
                  }
                }
              ?>
            </select>
            <span class="form-text"><?=$facilitator_id_err?></span>
          </div>
        </div>

        <!-- =================== Marital / Religion / Employment / Employer =================== -->
        <div class="row mt-4">
          <div class="col-3">
            <label class="muted-label">Marital Status</label>
            <input type="text" name="marital_status" maxlength="30" class="form-control" value="<?=$marital_status?>">
          </div>
          <div class="col-3">
            <label class="muted-label">Religion</label>
            <input type="text" name="religion" maxlength="50" class="form-control" value="<?=$religion?>">
          </div>
          <div class="col-3">
            <label class="muted-label">Employment</label>
            <input type="text" name="employment" maxlength="100" class="form-control" value="<?=$employment?>">
          </div>
          <div class="col-3">
            <label class="muted-label">Employer</label>
            <input type="text" name="employer" maxlength="120" class="form-control" value="<?=$employer?>">
          </div>
        </div>

        <!-- =================== Diagnostic / Emergency =================== -->
        <div class="row mt-4">
          <div class="col-6">
            <label class="muted-label">Diagnostic Codes</label>
            <textarea name="diagnostic_codes" class="form-control" rows="2"><?=$diagnostic_codes?></textarea>
          </div>
          <div class="col-6">
            <label class="muted-label">Emergency Contact</label>
            <textarea name="emergency_contact" class="form-control" rows="2"><?=$emergency_contact?></textarea>
            <small class="text-muted">Format: “Emergency Contact: Full Name (Emergency Contact: Relation) Emergency Contact: Phone Number”</small>
          </div>
        </div>

        <!-- =================== Attendance Days =================== -->
        <div class="row mt-4">
          <div class="col-6">
            <label class="muted-label">Attendance Day(s) &nbsp;<small class="text-muted">Select planned class days</small></label>
            <div>
              <!-- Sun -->
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="attends_sunday"    <?= $attends_sunday=="1" ? "checked":"" ?>>
                <label class="form-check-label">Sunday</label>
              </div>
              <!-- Mon -->
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="attends_monday"    <?= $attends_monday=="1" ? "checked":"" ?>>
                <label class="form-check-label">Monday</label>
              </div>
              <!-- Tue -->
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="attends_tuesday"   <?= $attends_tuesday=="1" ? "checked":"" ?>>
                <label class="form-check-label">Tuesday</label>
              </div>
              <!-- Wed -->
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="attends_wednesday" <?= $attends_wednesday=="1" ? "checked":"" ?>>
                <label class="form-check-label">Wednesday</label>
              </div>
              <!-- Thu -->
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="attends_thursday"  <?= $attends_thursday=="1" ? "checked":"" ?>>
                <label class="form-check-label">Thursday</label>
              </div>
              <!-- Fri -->
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="attends_friday"    <?= $attends_friday=="1" ? "checked":"" ?>>
                <label class="form-check-label">Friday</label>
              </div>
              <!-- Sat -->
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="attends_saturday"  <?= $attends_saturday=="1" ? "checked":"" ?>>
                <label class="form-check-label">Saturday</label>
              </div>
              <!-- extra Sun (T4C) -->
              <div id="sun2wrap" class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="attends_sunday_t4c" <?= $attends_sunday_t4c ? 'checked' : '' ?>>
                <label class="form-check-label">Sunday&nbsp;(2× T4C)</label>
              </div>
            </div>
          </div>
        </div>

        <!-- =================== Conduct Checkboxes =================== -->
        <div class="row mt-3">
          <div class="col">
            <label class="muted-label">Conduct</label><br>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="speaksSignificantlyInGroup"  <?= $speaksSignificantlyInGroup=="1" ? "checked":"" ?> >
              <label class="form-check-label">Excessive speaking</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="respectfulTowardsGroup"      <?= $respectfulTowardsGroup=="1" ? "checked":"" ?> >
              <label class="form-check-label">Respectful</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="takesResponsibilityForPastBehavior" <?= $takesResponsibilityForPastBehavior=="1" ? "checked":"" ?> >
              <label class="form-check-label">Takes responsibility</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="disruptiveOrArgumentitive"  <?= $disruptiveOrArgumentitive=="1" ? "checked":"" ?> >
              <label class="form-check-label">Disruptive</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="inappropriateHumor"         <?= $inappropriateHumor=="1" ? "checked":"" ?> >
              <label class="form-check-label">Inappropriate humor</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="blamesVictim"               <?= $blamesVictim=="1" ? "checked":"" ?> >
              <label class="form-check-label">Blames victim</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="drugAlcohol"                <?= $drugAlcohol=="1" ? "checked":"" ?> >
              <label class="form-check-label">Alcohol or drugs</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="inappropriateBehavior"      <?= $inappropriateBehavior=="1" ? "checked":"" ?> >
              <label class="form-check-label">Inappropriate behavior</label>
            </div>
          </div>
        </div>

        <!-- =================== Behavior Contract =================== -->
        <div class="row mt-3">
          <div class="col-3">
            <label class="muted-label" for="behavior_contract_status">Behavior Contract Status</label>
            <select name="behavior_contract_status" id="behavior_contract_status" class="form-control">
              <option value="Not Needed" <?= (($row['behavior_contract_status'] ?? '') === "Not Needed") ? "selected" : "" ?>>Not Needed</option>
              <option value="Needed"     <?= (($row['behavior_contract_status'] ?? '') === "Needed")     ? "selected" : "" ?>>Needed</option>
              <option value="Signed"     <?= (($row['behavior_contract_status'] ?? '') === "Signed")     ? "selected" : "" ?>>Signed</option>
            </select>
          </div>
          <div class="col-3">
            <label class="muted-label" for="behavior_contract_signed_date">Signed Date</label>
            <input type="date" name="behavior_contract_signed_date" id="behavior_contract_signed_date" class="form-control" value="<?= htmlspecialchars($row['behavior_contract_signed_date'] ?? '') ?>">
          </div>
        </div>

        <!-- =================== Notes =================== -->
        <div class="row mt-4">
          <div class="col-6">
            <div class="form-group">
              <label class="muted-label">Client Notes</label>
              <textarea name="note" maxlength="2048" class="form-control"><?=$note?></textarea>
              <span class="form-text"><?=$note_err?></span>
            </div>
          </div>
          <div class="col-6">
            <div class="form-group">
              <label class="muted-label">Other Concerns</label>
              <textarea name="other_concerns" maxlength="2048" class="form-control"><?=$other_concerns?></textarea>
              <span class="form-text"><?=$other_concerns_err?></span>
            </div>
          </div>
        </div>

        <!-- =================== Exit Info =================== -->
        <div class="row mt-4">
          <div class="col-2">
            <div class="form-group">
              <label class="muted-label">Exit Date</label>
              <input type="date" name="exit_date" class="form-control" value="<?=$exit_date?>">
              <span class="form-text"><?=$exit_date_err?></span>
            </div>
          </div>
          <div class="col-3">
            <div class="form-group">
              <label class="muted-label">Exit Reason</label>
              <select class="form-control" id="exit_reason_id" name="exit_reason_id">
                <?php foreach (get_exit_reasons() as $er): ?>
                  <option value="<?=$er['id']?>" <?=$er['id']==$exit_reason_id?'selected':''?>><?=htmlspecialchars($er["reason"]) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="form-text"><?=$exit_reason_id_err?></span>
            </div>
          </div>
          <div class="col-7">
            <div class="form-group">
              <label class="muted-label">Exit Notes</label>
              <textarea name="exit_note" maxlength="512" class="form-control"><?=$exit_note?></textarea>
              <span class="form-text"><?=$exit_note_err?></span>
            </div>
          </div>
        </div>

        <!-- =================== Exit Cause(s) =================== -->
        <div class="row mt-3">
          <div class="col-12">
            <label class="muted-label d-block">Exit Cause(s)</label>
            <div class="d-flex flex-wrap">
              <?php
                $causes = [];
                $q = mysqli_query($link, "SELECT id, code, label FROM exit_cause WHERE active=1 ORDER BY sort_order, id");
                while ($q && $c = mysqli_fetch_assoc($q)) $causes[] = $c;

                foreach ($causes as $c) {
                    $cid = (int)$c['id'];
                    $checked = in_array($cid, $selected_exit_causes ?? [], true) ? 'checked' : '';
                    $safeLbl = htmlspecialchars($c['label']);
                    $safeCode = htmlspecialchars($c['code']);
                    echo '<div class="form-check mr-4 mb-2">';
                    echo "  <input class=\"form-check-input exit-cause\" type=\"checkbox\" id=\"exit_cause_$cid\" name=\"exit_causes[]\" value=\"$cid\" data-code=\"$safeCode\" $checked>";
                    echo "  <label class=\"form-check-label\" for=\"exit_cause_$cid\">$safeLbl</label>";
                    echo '</div>';
                }
              ?>
            </div>

            <div id="exitCauseOtherWrap" class="mt-2" style="display:none;">
              <label class="muted-label">Other details</label>
              <input type="text" name="exit_cause_other" id="exit_cause_other" maxlength="512"
                    class="form-control" value="<?= htmlspecialchars($exit_cause_other ?? '') ?>">
              <small class="text-muted">Required if “Other” is selected.</small>
            </div>
          </div>
        </div>


        

        <!-- =================== Submit =================== -->
        <div class="row mt-4">
          <div class="col">
            <input type="hidden" name="id" value="<?=$id?>" />
            <input type="submit" class="btn btn-primary" value="Submit">
            <a href="client-review.php?client_id=<?=$id?>" class="btn btn-secondary">Cancel</a>
          </div>
        </div>
      </form>
    </div>
  </section>

  <script>
    document.addEventListener('DOMContentLoaded', ()=>{
      // Existing T4C toggle
      const prog   = document.getElementById('program_id');
      const sun2   = document.getElementById('sun2wrap');
      const toggle = ()=>{ if (sun2) sun2.style.display = (+prog.value === 1) ? 'inline-block' : 'none'; };
      if (prog) { toggle(); prog.addEventListener('change', toggle); }

      // Exit Cause OTHER toggle
      const otherWrap = document.getElementById('exitCauseOtherWrap');
      const otherInput= document.getElementById('exit_cause_other');
      const boxes = Array.from(document.querySelectorAll('.exit-cause'));
      function toggleOther() {
        const otherChecked = boxes.some(b => b.dataset.code === 'OTHER' && b.checked);
        if (otherWrap) otherWrap.style.display = otherChecked ? 'block' : 'none';
        if (!otherChecked && otherInput) otherInput.value = '';
      }
      boxes.forEach(b => b.addEventListener('change', toggleOther));
      toggleOther(); // initial
    });
  </script>


  <!-- JS deps -->
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"
          integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="
          crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
          integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
          crossorigin="anonymous"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
          integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYJZE3Ipu6Tp75j7Bh/kR0JKI"
          crossorigin="anonymous"></script>
</body>

</html>