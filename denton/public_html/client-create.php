<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";
require_once "sql_functions.php";

// Define variables and initialize with empty values
$first_name = "";
$last_name = "";
$program_id = $_SESSION['program_id'];
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
$exit_date = "";
$exit_reason_id = "";
$exit_note = "";
//$documents_url = "";
$weekly_attendance = "";
$attends_sunday = false;
$attends_monday = false;
$attends_tuesday = false;
$attends_wednesday = false;
$attends_thursday = false;
$attends_friday = false;
$attends_saturday = false;

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
//$documents_url_err = "";
$weekly_attendance_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
    $emergency_contact = trim($_POST["emergency_contact"]);
    $orientation_date = trim($_POST["orientation_date"]);
    $exit_date = trim($_POST["exit_date"]);
    $exit_reason_id = trim($_POST["exit_reason_id"]);
    $exit_note = trim($_POST["exit_note"]);
    //		$documents_url = trim($_POST["documents_url"]);
    $weekly_attendance = trim($_POST["weekly_attendance"]);
    $attends_sunday = isset($_POST['attends_sunday']) ? 1 : 0;
    $attends_monday = isset($_POST['attends_monday']) ? 1 : 0;
    $attends_tuesday = isset($_POST['attends_tuesday']) ? 1 : 0;
    $attends_wednesday = isset($_POST['attends_wednesday']) ? 1 : 0;
    $attends_thursday = isset($_POST['attends_thursday']) ? 1 : 0;
    $attends_friday = isset($_POST['attends_friday']) ? 1 : 0;
    $attends_saturday = isset($_POST['attends_saturday']) ? 1 : 0;

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

    $vars = parse_columns('client', $_POST);
    if (empty($orientation_date)) $orientation_date = NULL;
    if (empty($exit_date)) $exit_date = NULL;
    $stmt = $pdo->prepare("INSERT INTO client (program_id,first_name,last_name,date_of_birth,gender_id,email,phone_number,cause_number,referral_type_id,ethnicity_id,required_sessions,fee,case_manager_id,therapy_group_id,client_stage_id,note,emergency_contact,orientation_date,exit_date,exit_reason_id,exit_note,weekly_attendance,attends_sunday,attends_monday,attends_tuesday,attends_wednesday,attends_thursday,attends_friday,attends_saturday) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    if ($stmt->execute([$program_id, $first_name, $last_name, $date_of_birth, $gender_id, $email, $phone_number, $cause_number, $referral_type_id, $ethnicity_id, $required_sessions, $fee, $case_manager_id, $therapy_group_id, $client_stage_id,  $note, $emergency_contact, $orientation_date, $exit_date, $exit_reason_id, $exit_note, $weekly_attendance, $attends_sunday, $attends_monday, $attends_tuesday, $attends_wednesday, $attends_thursday, $attends_friday, $attends_saturday])) {
        $stmt = null;
        header("location: client-index.php");
    } else {
        echo "Something went wrong. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>New Client Enrollment</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<?php require_once('navbar.php'); ?>

<body>
    <section class="pt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <div class="page-header">
                        <h2>New Client Enrollment</h2>
                    </div>
                </div>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="row">
                    <div class="col-2">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" maxlength="45" class="form-control" value="<?php echo $first_name; ?>">
                            <span class="form-text"><?php echo $first_name_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" maxlength="45" class="form-control" value="<?php echo $last_name; ?>">
                            <span class="form-text"><?php echo $last_name_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?php echo $date_of_birth; ?>">
                            <span class="form-text"><?php echo $date_of_birth_err; ?></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-2">
                        <div class="form-group">
                            <label>Gender</label>
                            <select class="form-control" id="gender_id" name="gender_id">
                                <?php
                                    $genders = get_genders();
                                    foreach ($genders as $gender) {
                                        $value = htmlspecialchars($gender["gender"]);
                                        if ($gender["id"] == $gender_id) {
                                            echo '<option value="' . "$gender[id]" . '"selected="selected">' . "$value" . '</option>';
                                        } else {
                                            echo '<option value="' . "$gender[id]" . '">' . "$value" . '</option>';
                                        }
                                    }
                                ?>
                            </select>
                            <span class="form-text"><?php echo $gender_id_err; ?></span>
                        </div>
                    </div>

                    <div class="col-2">
                        <div class="form-group">
                            <label>Ethnicity</label>
                            <select class="form-control" id="ethnicity_id" name="ethnicity_id">
                                <?php
                                    $ethnicities = get_ethnicities();
                                    foreach ($ethnicities as $ethnicity) {
                                        $value = htmlspecialchars($ethnicity["code"] . " - " . $ethnicity["name"]);
                                        if ($ethnicity["id"] == $ethnicity_id) {
                                            echo '<option value="' . "$ethnicity[id]" . '"selected="selected">' . "$value" . '</option>';
                                        } else {
                                            echo '<option value="' . "$ethnicity[id]" . '">' . "$value" . '</option>';
                                        }
                                    }
                                ?>
                            </select>
                            <span class="form-text"><?php echo $ethnicity_id_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Program</label>
                            <select class="form-control" id="program_id" name="program_id">
                                <?php
                                    $programs = get_programs();
                                    foreach ($programs as $program) {
                                        $value = htmlspecialchars($program["name"]);
                                        if ($program["id"] == $program_id) {
                                            echo '<option value="' . "$program[id]" . '"selected="selected">' . "$value" . '</option>';
                                        } else {
                                            echo '<option value="' . "$program[id]" . '">' . "$value" . '</option>';
                                        }
                                    }
                                ?>
                            </select>
                            <span class="form-text"><?php echo $program_id_err; ?></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-2">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone_number" maxlength="45" class="form-control" value="<?php echo $phone_number; ?>">
                            <span class="form-text"><?php echo $phone_number_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>E-Mail</label>
                            <input type="text" name="email" maxlength="64" class="form-control" value="<?php echo $email; ?>">
                            <span class="form-text"><?php echo $email_err; ?></span>
                        </div>
                    </div>
                    <div class="col-7">
                        <div class="form-group">
                            <label>Emergency Contact</label>
                            <input type="text" name="emergency_contact" maxlength="512" class="form-control" value="<?php echo $emergency_contact; ?>">
                            <span class="form-text"><?php echo $emergency_contact_err; ?></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-1">
                        <div class="form-group">
                            <label>Referral Type</label>
                            <select class="form-control" id="referral_type_id" name="referral_type_id">
                                <?php
                                    $referral_types = get_referral_types();
                                    foreach ($referral_types as $referral_type) {
                                        $value = htmlspecialchars($referral_type["referral_type"]);
                                        if ($referral_type["id"] == $referral_type_id) {
                                            echo '<option value="' . "$referral_type[id]" . '"selected="selected">' . "$value" . '</option>';
                                        } else {
                                            echo '<option value="' . "$referral_type[id]" . '">' . "$value" . '</option>';
                                        }
                                    }
                                ?>
                            </select>
                            <span class="form-text"><?php echo $referral_type_id_err; ?></span>
                        </div>
                    </div>
                    <div class="col-1">
                        <div class="form-group">
                            <label>Required Sessions</label>
                            <input type="number" name="required_sessions" class="form-control" value="<?php echo ($required_sessions == "") ? "15" : $required_sessions; ?>">
                            <span class="form-text"><?php echo $required_sessions_err; ?></span>
                        </div>
                    </div>
                    <div class="col-1">
                        <div class="form-group">
                            <label>Session per Week</label>
                            <input type="number" name="weekly_attendance" step="1" class="form-control" value="<?php echo ($weekly_attendance == "") ? "1" : $weekly_attendance; ?>">
                            <span class="form-text"><?php echo $weekly_attendance_err; ?></span>
                        </div>
                    </div>
                    <div class="col-1">
                        <div class="form-group">
                            <label>Fee per Session</label>
                            <input type="number" name="fee" step="1.00" class="form-control" value="<?php echo ($fee == "") ? "30.00" : $fee; ?>">
                            <span class="form-text"><?php echo $fee_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Case Manager</label>
                            <select class="form-control" id="case_manager_id" name="case_manager_id">
                                <?php
                                    $managers = get_case_managers();
                                    foreach ($managers as $manager) {
                                        $value = htmlspecialchars($manager["last_name"] . ", " . $manager["first_name"] . " - " . $manager["office"]);
                                        if ($manager["id"] == $case_manager_id) {
                                            echo '<option value="' . "$manager[id]" . '"selected="selected">' . "$value" . '</option>';
                                        } else {
                                            echo '<option value="' . "$manager[id]" . '">' . "$value" . '</option>';
                                        }
                                    }
                                ?>
                            </select>
                            <span class="form-text"><?php echo $case_manager_id_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>cause_number</label>
                            <input type="text" name="cause_number" maxlength="15" class="form-control" value="<?php echo $cause_number; ?>">
                            <span class="form-text"><?php echo $cause_number_err; ?></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-4">
                        <div class="form-group">
                            <label>Therapy Group</label>
                            <select class="form-control" id="therapy_group_id" name="therapy_group_id">
                            <?php
                            $groups = get_therapy_groups($_SESSION['program_id']);
                            foreach ($groups as $group) {
                                $value = htmlspecialchars($group["name"] . " - " . $group["address"]);
                                if ($group["id"] == $therapy_group_id) {
                                    echo '<option value="' . "$group[id]" . '"selected="selected">' . "$value" . '</option>';
                                } else {
                                    echo '<option value="' . "$group[id]" . '">' . "$value" . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <span class="form-text"><?php echo $therapy_group_id_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Orientation Date</label>
                            <input type="date" name="orientation_date" class="form-control" value="<?php echo $orientation_date; ?>">
                            <span class="form-text"><?php echo $orientation_date_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                    <div class="form-group">
                            <label>Progress Stage</label>
                            <select class="form-control" id="client_stage_id" name="client_stage_id">
                            <?php
                            $stages = get_client_stages();
                            foreach ($stages as $stage) {
                                $value = htmlspecialchars($stage["stage"]);
                                if ($stage["id"] == $client_stage_id) {
                                    echo '<option value="' . "$stage[id]" . '"selected="selected">' . "$value" . '</option>';
                                } else {
                                    echo '<option value="' . "$stage[id]" . '">' . "$value" . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <span class="form-text"><?php echo $client_stage_id_err; ?></span>
                        </div>
                    </div>
                </div>
                <div class="row"> <!-- Attendance Fields -->
                    <div class="col-6">
                        <label>Attendance Day(s) - Select the days of the week the client plans to attend class</label>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="<?php echo $attends_sunday; ?>" name="attends_sunday">
                            <label class="form-check-label" for="attends_sunday">Sunday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="<?php echo $attends_monday; ?>" name="attends_monday">
                            <label class="form-check-label" for="attends_monday">Monday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="<?php echo $attends_tuesday; ?>" name="attends_tuesday">
                            <label class="form-check-label" for="attends_tuesday">Tuesday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="<?php echo $attends_wednesday; ?>" name="attends_wednesday">
                            <label class="form-check-label" for="attends_wednesday">Wednesday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="<?php echo $attends_thursday; ?>" name="attends_thursday">
                            <label class="form-check-label" for="attends_thursday">Thursday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="<?php echo $attends_friday; ?>" name="attends_friday">
                            <label class="form-check-label" for="attends_friday">Friday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="<?php echo $attends_saturday; ?>" name="attends_saturday">
                            <label class="form-check-label" for="attends_saturday">Saturday</label>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label>Client Notes</label>
                            <textarea type="text" name="note" maxlength="2048" class="form-control"><?php echo $note; ?></textarea>
                            <span class="form-text"><?php echo $note_err; ?></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-2">
                        <div class="form-group">
                            <label>Exit Date</label>
                            <input type="date" name="exit_date" class="form-control" value="<?php echo $exit_date; ?>">
                            <span class="form-text"><?php echo $exit_date_err; ?></span>
                        </div>
                    </div>
                    <div class="col-2">
                        <div class="form-group">
                            <label>Exit Reason</label>
                            <select class="form-control" id="exit_reason_id" name="exit_reason_id">
                                <?php
                                    $exit_reasons = get_exit_reasons();
                                    foreach ($exit_reasons as $exit_reason) {
                                        $value = htmlspecialchars($exit_reason["reason"]);
                                        if ($exit_reason["id"] == $exit_reason_id) {
                                            echo '<option value="' . "$exit_reason[id]" . '"selected="selected">' . "$value" . '</option>';
                                        } else {
                                            echo '<option value="' . "$exit_reason[id]" . '">' . "$value" . '</option>';
                                        }
                                    }
                                ?>
                            </select>
                            <span class="form-text"><?php echo $exit_reason_id_err; ?></span>
                        </div>
                    </div>
                    <div class="col-5">
                        <div class="form-group">
                            <label>Exit Notes</label>
                            <input type="text" name="exit_note" maxlength="512" class="form-control" value="<?php echo $exit_note; ?>">
                            <span class="form-text"><?php echo $exit_note_err; ?></span>
                        </div>
                    </div>
                </div>
                <!--
						<div class="form-group">
                                <label>documents_url</label>
                                <input type="text" name="documents_url" maxlength="128"class="form-control" value="<?php echo $documents_url; ?>">
                                <span class="form-text"><?php echo $documents_url_err; ?></span>
                            </div>
    						<div class="form-group">
                                <label>speaksSignificantlyInGroup</label>
                                <input type="number" name="speaksSignificantlyInGroup" class="form-control" value="<?php echo $speaksSignificantlyInGroup; ?>">
                                <span class="form-text"><?php echo $speaksSignificantlyInGroup_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>respectfulTowardsGroup</label>
                                <input type="number" name="respectfulTowardsGroup" class="form-control" value="<?php echo $respectfulTowardsGroup; ?>">
                                <span class="form-text"><?php echo $respectfulTowardsGroup_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>takesResponsibilityForPastBehavior</label>
                                <input type="number" name="takesResponsibilityForPastBehavior" class="form-control" value="<?php echo $takesResponsibilityForPastBehavior; ?>">
                                <span class="form-text"><?php echo $takesResponsibilityForPastBehavior_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>disruptiveOrArgumentitive</label>
                                <input type="number" name="disruptiveOrArgumentitive" class="form-control" value="<?php echo $disruptiveOrArgumentitive; ?>">
                                <span class="form-text"><?php echo $disruptiveOrArgumentitive_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>inappropriateHumor</label>
                                <input type="number" name="inappropriateHumor" class="form-control" value="<?php echo $inappropriateHumor; ?>">
                                <span class="form-text"><?php echo $inappropriateHumor_err; ?></span>
                            </div>
						<div class="form-group">
                                <label>blamesVictim</label>
                                <input type="number" name="blamesVictim" class="form-control" value="<?php echo $blamesVictim; ?>">
                                <span class="form-text"><?php echo $blamesVictim_err; ?></span>
                            </div>
-->
                <input type="submit" class="btn btn-primary" value="Submit">
                <a href="client-index.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </section>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>

</html>