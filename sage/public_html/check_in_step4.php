<?php
include_once 'auth.php';
check_loggedin($con);
include "helpers.php";

require_once "../config/config.php";
require_once "sql_functions.php";

$therapy_session_id = "";
if (isset($_GET['therapy_session_id'])) {
    $therapy_session_id = $_GET['therapy_session_id'];
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $therapy_session_id = trim($_POST["therapy_session_id"]);
}
$therapy_session_id_err = "";

$client_id = "";
if (isset($_GET['client_id'])) {
    $client_id = $_GET['client_id'];
}
if (isset($_POST['client_id'])) {
    $client_id = $_POST['client_id'];
}
$client = get_client_info(trim($client_id));
if (!isset($client)) {
    // URL doesn't contain valid id parameter. Redirect to error page
    header("location: error.php");
    exit();
}

$client_stage_id = $client['client_stage_id'];
$therapy_group_id  = $client['therapy_group_id'];        // client’s current group
$therapy_groups    = get_active_therapy_groups();        // list for dropdown
$attends_sunday = $client['attends_sunday'];
$attends_monday = $client['attends_monday'];
$attends_tuesday = $client['attends_tuesday'];
$attends_wednesday = $client['attends_wednesday'];
$attends_thursday = $client['attends_thursday'];
$attends_friday = $client['attends_friday'];
$attends_saturday = $client['attends_saturday'];
$intake_packet = $client['intake_packet'];

$attendance = get_client_attendance_days(trim($client_id));
$temp = get_client_absence_days(trim($client_id));
$excused = $temp[0];
$unexcused = $temp[1];

$stages = get_client_stages();

// I originaly was using a simple percentage of completion to calculate the
// suggested stage of change ID.  That has a few issues.  Relapse is the final stage
// dependant on stage of change ID etc.  I decided it would be better to hardcode
// based on percent complete.  This handles different required sessions without 
// needing new settings or depending on ID values.
// For now the value is Display Only
$num_stages = count($stages);
$percent_complete = $client["sessions_attended"] / $client["sessions_required"];
if($percent_complete < 0.20) {
    $calculated_stage = "Precontemplation";
}
else if($percent_complete < 0.40) {
    $calculated_stage = "Contemplation";
}
else if($percent_complete < 0.60) {
    $calculated_stage = "Preparation";
}
else if($percent_complete < 0.80) {
    $calculated_stage = "Action";
}
else {
    $calculated_stage = "Maintenance";
}
foreach ($stages as $stage) {
    if ($stage["id"] == $client_stage_id) {
        $client_stage = $stage["stage"];
    }
}

function buildCalendar($date, $attendance, $excused, $unexcused)
{
    $today = new DateTime('today');
    $day = date('d', $date);
    $month = date('m', $date);
    $year = date('Y', $date);

    // Here we generate the first day of the month 
    $first_day = mktime(0, 0, 0, $month, 1, $year);
    $curDay = new DateTime();
    $curDay->setTimestamp($first_day);

    // This gets us the month name 
    $title = date('F', $first_day);

    //Here we find out what day of the week the first day of the month falls on 
    $day_of_week = date('D', $first_day);

    /*Once we know what day of the week it falls on, we know how many
    blank days occure before it. If the first day of the week is a 
    Sunday then it would be zero*/

    switch ($day_of_week) {
        case "Sun":
            $blank = 0;
            break;
        case "Mon":
            $blank = 1;
            break;
        case "Tue":
            $blank = 2;
            break;
        case "Wed":
            $blank = 3;
            break;
        case "Thu":
            $blank = 4;
            break;
        case "Fri":
            $blank = 5;
            break;
        case "Sat":
            $blank = 6;
            break;
    }

    //We then determine how many days are in the current month
    $days_in_month = cal_days_in_month(0, $month, $year);

    /*Here we take a closer look at the days of the month and prepare to make 
    our calendar table  . The first thing we do is determine what day of the week 
    the first of the month falls. Once we know that, we use the switch () function 
    to determine how many blank days we need in our calendar before the first day.*/
    //Here we start building the table heads 
    echo "<table border=1 width=392>";
    echo "<tr><th colspan=7> $title $year </th></tr>";
    echo "<tr><td width=56>S</td><td width=56>M</td><td 
        width=56>T</td><td width=56>W</td><td width=56>R</td><td 
        width=56>F</td><td width=56>S</td></tr>";

    //This counts the days in the week, up to 7
    $day_count = 1;

    echo "<tr>";
    //first we take care of those blank days
    while ($blank > 0) {
        echo "<td></td>";
        $blank = $blank - 1;
        $day_count++;
    }

    /*The first part of this code very simply echos the table tags, the month name, 
    and the headings for the days of the week. Then we start a while loop. 
    What we are doing is echoing empty table details, one for each blank day 
    we count down. Once the blank days are done it stops. At the same time, 
    our $day_count is going up by 1 each time through the loop. This is to keep 
    count so that we do not try to put more than seven days in a week.*/

    /*To finish our calendar we use one last while loop. This one fills in the 
    rest of our calendar with blank table details if needed. 
    Then we close our table and our script is done.*/

    //sets the first day of the month to 1 
    $day_num = 1;

    //count up the days, until we've done all of them in the month
    while ($day_num <= $days_in_month) {
        echo "<td>";
        if ($curDay == $today) {
            echo "<b>" . $day_num . "</b>";  // Bold today
        } else {
            echo $day_num;
        }
        if ($day_num < 10) {
            echo "&nbsp;";
        }
        echo "&nbsp;";

        $count = arrayCount($curDay->format("Y-m-d"), $attendance);
        for ($i = 0; $i < $count; $i++) {
            echo "&#x2705";  // Green Check for present
        }

        $count = arrayCount($curDay->format("Y-m-d"), $excused);
        for ($i = 0; $i < $count; $i++) {
            echo "&#x2716";  // Grey x for excused absence
        }

        $count = arrayCount($curDay->format("Y-m-d"), $unexcused);
        for ($i = 0; $i < $count; $i++) {
            echo "&#x274C";  // Red X for unexcused
        }

        echo "</td>";

        $day_num++;
        $day_count++;
        $curDay->modify('+ 1 day');

        //Make sure we start a new row every week
        if ($day_count > 7) {
            echo "</tr>";
            $day_count = 1;
        }
    }

    /*Now we need to fill in the days of the month. We do this with another while loop, 
    but this time we are counting up to the last day of the month. Each cycle echos 
    a table detail with the day of the month, and it repeats until we reach the last 
    day of the month. Our loop also contains a conditional statement. This checks 
    if the days of the week have reached 7, the end of the week. If it has, 
    it starts a new row, and resets the counter back to 1 (the first day of the week).*/

    //Finaly we finish out the table with some blank details if needed
    while ($day_count > 1 && $day_count <= 7) {
        echo "<td> </td>";
        $day_count++;
    }

    echo "</tr></table>";
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

    <section class="pt-3">
        <div class="container-fluid">
            <div class="row">
                <div class="col-2">
                    <h4>Session Check In</h4>
                </div>
            </div>
            <div class="row bg-light">
                <div class="col">
                    <?php
                    $sessionInfo = get_therapy_session_info($therapy_session_id);
                    if (isset($sessionInfo)) {
                        $value = $sessionInfo['group_name'] . " - " . $sessionInfo['group_address'];
                        echo '<p class="h4">' . htmlspecialchars($value) . '</p>';
                        $value = $sessionInfo['weekday'] . " " . $sessionInfo['date'] . " - " . $sessionInfo['facilitator'];
                        echo '<p class="h4">' . htmlspecialchars($value) . '</p>';
                    } else {
                        echo '<div class="alert alert-warning" role="alert"> Session not found
                        <a href="check_in_step1.php" class="btn btn-primary float-right">Return to Step 1</a>
                        </div>';
                        exit();
                    }
                    ?>
                </div>
            </div>
            <div class="row">
                <div class="col-6">
                </div>
                <div class="col">
                    <h5><a class="nav-link" target="_blank" title='Opens in New Tab' data-toggle='tooltip' href="./client-review.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Client Review</a></h5>
                </div>
                <div class="col">
                    <h5><a class="nav-link" target="_blank" title='Opens in New Tab' data-toggle='tooltip' href="./client-update.php?id=<?php echo htmlspecialchars($client_id); ?>">Edit Client</a></h5>
                </div>
                <div class="col">
                    <h5><a class="nav-link" target="_blank" title='Opens in New Tab' data-toggle='tooltip' href="./client-attendance.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Attendance</a></h5>
                </div>
                <div class="col">
                    <h5><a class="nav-link" target="_blank" title='Opens in New Tab' data-toggle='tooltip' href="./client-ledger.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Payments</a></h5>
                </div>
            </div>
        </div>
        <div class="container-fluid">
            <form action="check_in_step5.php" method="post">
                <input type="hidden" id="therapy_session_id" name="therapy_session_id" value="<?php echo $therapy_session_id ?>" />
                <input type="hidden" id="client_id" name="client_id" value="<?php echo $client_id ?>" />

                <div class="row bg-light">
                    <div class="col-8">
                        <div class="row">
                            <div class="col-2">
                                <small class="text-muted">First Name</small>
                                <h5><?php echo htmlspecialchars($client["first_name"]); ?></h5>
                            </div>
                            <div class="col-2">
                                <small class="text-muted">Last Name</small>
                                <h5><?php echo htmlspecialchars($client["last_name"]); ?></h5>
                            </div>
                            <div class="col-2">
                                <small class="text-muted">DOB</small>
                                <h5><?php echo htmlspecialchars($client["date_of_birth"]) . " (" . htmlspecialchars($client["age"]) . ")"; ?></h5>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-2">
                                <small class="text-muted">Ref Type</small>
                                <h5><?php echo htmlspecialchars($client["referral_type"]); ?></h5>
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">Case Manager - Office</small>
                                <select class="form-control" id="case_manager_id" name="case_manager_id">
                                    <?php
                                    $managers = get_case_managers();
                                    foreach ($managers as $manager) {
                                        $value = htmlspecialchars($manager["last_name"] . ", " . $manager["first_name"]. " - " . $manager["office"]);
                                        if ($manager["id"] == $client["case_manager_id"]) {
                                            echo '<option value="' . "$manager[id]" . '"selected="selected">' . "$value" . '</option>';
                                        } else {
                                            echo '<option value="' . "$manager[id]" . '">' . "$value" . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <!-- Therapy Group selector -->
                            <div class="col-auto">
                                <small class="text-muted">Therapy Group</small>
                                <select class="form-control" id="therapy_group_id"
                                        name="therapy_group_id" required>
                                    <?php foreach ($therapy_groups as $tg): ?>
                                        <option value="<?= $tg['id']; ?>"
                                                <?= $tg['id'] == $therapy_group_id ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($tg['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>


                        </div>
                        <div class="row">
                            <div class="col-2">
                                <small class="text-muted">Phone</small>
                                <input type="text" name="phone_number" maxlength="45" class="form-control" value="<?php echo htmlspecialchars($client["phone_number"]); ?>">
                            </div>
                            <div class="col-3">
                                <small class="text-muted">E-Mail</small>
                                <input type="text" name="email" maxlength="64" class="form-control" value="<?php echo htmlspecialchars($client["email"]); ?>">
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Emergency Contact</small>
                                <h5><?php echo htmlspecialchars($client["emergency_contact"]); ?></h5>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-1">
                                <small class="text-muted">Balance</small>
                                <?php
                                if ($client["balance"] < 0) {
                                    echo "<h5 class='text-danger'>";
                                } else {
                                    echo "<h5 class='text-success'>";
                                }
                                echo htmlspecialchars("$" . $client["balance"]) . "</h5>";
                                ?>
                            </div>
                            <div class="col-1">
                                <small class="text-muted">Pays</small>
                                <input name="fee" value="<?php echo htmlspecialchars($client["fee"]); ?>" type="number" placeholder="0.00" step="0.01" min="0" max="999" class="form-control" />
                            </div>
                            <div class="col-auto">
                                <small class="text-muted">Amount Collected @ Check In</small>
                                <input name="paid" value="<?php echo htmlspecialchars($client["fee"]); ?>" type="number" placeholder="0.00" step="0.01" min="0" max="999" class="form-control" />
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-1">
                                <small class="text-muted">Attended</small>
                                <h5><?php echo htmlspecialchars($client["sessions_attended"] . " of " . $client["sessions_required"]); ?></h5>
                            </div>
                            <div class="col-1">
                                <small class="text-muted">Absences</small>
                                <h5><?php echo htmlspecialchars($client["absence_unexcused"]); ?></h5>
                            </div>

                            <div class="col-2">
                                    <small class="text-muted">Stage of Change</small>
                                    <select class="form-control" id="client_stage_id" name="client_stage_id">
                                        <?php
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
                            </div>
                            <div class="col-2">
                                    <small class="text-muted">Suggested Stage of Change</small>
                                    <?php 
                                        if(strcmp($client_stage,$calculated_stage)== 0) {
                                            echo "<h5 class='text-success'>";
                                        }
                                        else {
                                            echo "<h5 class='text-danger'>";
                                        }
                                        echo htmlspecialchars($calculated_stage);
                                        echo "</h5>";
                                    ?>                                        
                            </div>
                            <!-- Intake-packet checkbox -->
                            <div class="col-3">
                                <small class="text-muted">Received Intake Packet</small>
                                <?php
                                    // 1 = yes, 0 = no (NULL also treated as “no” for safety)
                                    $gotPacket = intval($client['intake_packet'] ?? 0);

                                    if ($gotPacket) {
                                        echo '<h5 class="text-success">Yes</h5>';
                                    } else {
                                        echo '<h5 class="text-danger">No</h5>';
                                    }
                                ?>
                            </div>

                        </div>
                <div class="row"> <!-- Attendance Fields -->
                    <div class="col">
                        <small class="text-muted">Attendance Day(s) - Select the days of the week the client plans to attend class</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="" name="attends_sunday" <?php if ($attends_sunday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_sunday">Sunday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="" name="attends_monday" <?php if ($attends_monday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_monday">Monday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="" name="attends_tuesday" <?php if ($attends_tuesday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_tuesday">Tuesday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="" name="attends_wednesday" <?php if ($attends_wednesday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_wednesday">Wednesday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="" name="attends_thursday" <?php if ($attends_thursday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_thursday">Thursday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="" name="attends_friday" <?php if ($attends_friday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_friday">Friday</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" value="" name="attends_saturday" <?php if ($attends_saturday == "1") echo "checked"; ?>>
                            <label class="form-check-label" for="attends_saturday">Saturday</label>
                        </div>
                    </div>
                </div>                        
                        <div class="row">
                            <div class="col">
                                <small class="text-muted">Conduct</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="" name="speaksSignificantlyInGroup" <?php if ($client["speaksSignificantlyInGroup"] == "true") echo "checked"; ?>>
                                    <label class="form-check-label" for="speaksSignificantlyInGroup">Excessive speaking</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="" name="respectfulTowardsGroup" <?php if ($client["respectfulTowardsGroup"] == "true") echo "checked"; ?>>
                                    <label class="form-check-label" for="respectfulTowardsGroup">Respectful</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="" name="takesResponsibilityForPastBehavior" <?php if ($client["takesResponsibilityForPastBehavior"] == "true") echo "checked"; ?>>
                                    <label class="form-check-label" for="takesResponsibilityForPastBehavior">Takes responsibility</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="" name="disruptiveOrArgumentitive" <?php if ($client["disruptiveOrArgumentitive"] == "true") echo "checked"; ?>>
                                    <label class="form-check-label" for="disruptiveOrArgumentitive">Disruptive</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="" name="inappropriateHumor" <?php if ($client["inappropriateHumor"] == "true") echo "checked"; ?>>
                                    <label class="form-check-label" for="inappropriateHumor">Inappropriate humor</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="" name="blamesVictim" <?php if ($client["blamesVictim"] == "true") echo "checked"; ?>>
                                    <label class="form-check-label" for="blamesVictim">Blames victim</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="" name="drugAlcohol" <?php if ($client["drug_alcohol"] == "true") echo "checked"; ?>>
                                    <label class="form-check-label" for="drugAlcohol">Alcohol or drugs</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" value="" name="inappropriateBehavior" <?php if ($client["inappropriate_behavior_to_staff"] == "true") echo "checked"; ?>>
                                    <label class="form-check-label" for="inappropriateBehavior">Inappropriate behavior</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <small class="text-muted">Client Notes</small>
                                <h5><?php echo htmlspecialchars($client["client_note"]); ?></h5>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-7">
                                <small class="text-muted">Other Concerns</small>
                                <textarea type="text" name="other_concerns" maxlength="2048" class="form-control"><?php echo htmlspecialchars($client["other_concerns"]); ?></textarea>
                            </div>
                        </div>
                    </div> <!-- End first Column -->
                    <div class="col-2"> <!-- Calendar Column -->
                        <div class="row bg-light">
                            <small class="text-muted">Attendance History (most recent 2 months)</small>
                            <?php
                            $date = new DateTime(); // today
                            $date->modify('first day of last month');
                            buildCalendar($date->getTimestamp(), $attendance, $excused, $unexcused);

                            $date->modify('first day of next month');
                            buildCalendar($date->getTimestamp(), $attendance, $excused, $unexcused);
                            ?>
                        </div>
                    </div>
                    <div class="col-2 pr-4 pl-4"> <!-- Image column -->
                        <small class="text-muted">Client Image</small>
                        <div class="row">
                            <img src="getImage.php?id=<?= $client_id; ?>" class="img-thumbnail" alt="client picture" onerror="this.onerror=null; this.src='img/male-placeholder.jpg'">
                        </div>
                        <div class="row justify-content-center">
                            <a class="nav-link" target="_blank" href="./client-image-upload.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Update Image</a>
                        </div>
                    </div>
                </div>
                <div class="row bg-light pt-3 pb-3">
                    <div class="col-auto">
                        <input type="submit" class="btn btn-primary" value="Check In">
                    </div>
                    <div class="col-auto">
                        <a href="check_in_step3.php?&therapy_session_id=<?= $therapy_session_id; ?>" class="btn btn-primary">Back</a>
                    </div>
                </div>
            </form>
        </div>
    </section>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>

</html>