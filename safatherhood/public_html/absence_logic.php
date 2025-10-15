<?php
require_once "../config/config.php";
require_once "helpers.php";
require_once "sql_functions.php";

function buildAbsenceRecords()
{
    global $link;

    // For clients - Not exited, with orientation date
    // Maybe require at least 1 attendance record ?
    // exclude clients who have attendance count >= required sessions
    $sql = "SELECT id, orientation_date, first_attendance.date first_attendance, weekly_attendance, attends_sunday, attends_monday, attends_tuesday, attends_wednesday, attends_thursday, attends_friday, attends_saturday from client c 
        LEFT JOIN (SELECT ar.client_id client_id, MIN(ts.date) date FROM attendance_record ar LEFT JOIN therapy_session ts ON ar.therapy_session_id = ts.id GROUP BY ar.client_id) as first_attendance ON c.id = first_attendance.client_id
        LEFT JOIN (SELECT ar.client_id client_id, count(ar.client_id) count FROM attendance_record ar GROUP BY ar.client_id) as sessions_attended ON c.id = sessions_attended.client_id
        where c.exit_date is null and c.orientation_date is not null and c.weekly_attendance > 0 and c.required_sessions > sessions_attended.count";
    if ($stmt = mysqli_prepare($link, $sql)) {
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($result)) {
                    buildAbsenceRecordsForClient($row["id"], $row["first_attendance"], $row["weekly_attendance"], $row);
                }
            } else {
                echo "ERROR <br>" . $stmt->error . "<br>";
            }
        } else {
            echo "ERROR <br>" . $stmt->error . "<br>";
        }
    }
}

// Starting at orientation date? First Attendance?  4 weeks ago?
// Build list of all days that should have attendance based on day of week and attends_x
// If attendance record with +-6 days then No Absence otherwise absence
// Remove expected day and attendance day and repeat until all expected days are removed

function buildAbsenceRecordsForClient($client_id, $start_date, $weekly_attendance, $row)
{
    echo "<b>Checking attendance for client " . $client_id . "</b><br>";
    echo "orientation date: " . $row['orientation_date'] . "<br>";

    $start_date = (new DateTime($start_date))->setTime(0, 0, 0, 0);

    // Don't generate absences more than 4 weeks ago
    // Limiting the lookback is tricky because because of the +- 6days window.
    // If you limit the generation of required days then old attendance can satisify
    // more than 1 attendnace day.  Going to try blocking the creation of OLD absences
    // As an alternative
    $maxOld = (new DateTime("-2 weeks"))->setTime(0, 0, 0, 0);

    $stop_date = (new DateTime())->setTime(0, 0, 0, 0);  // midnight
    echo "start_date: " . $start_date->format('Y-m-d H:i:s') . " stop_date: " . $stop_date->format('Y-m-d H:i:s') . "<br>";

    $attended_days = get_client_attendance_days(trim($client_id));

    $temp = get_client_absence_days(trim($client_id));
    $excused_absence_days = $temp[0];
    $unexcused_absence_days = $temp[1];

    $required_days = [];
    $date_idx = clone($start_date);
    while ($date_idx < $stop_date) {
        if(isRequiredDOW($date_idx, $row)) {
            $required_days[] = clone($date_idx);
        }
        $date_idx->modify('+ 1 day');
    }

    // Remove days where requierd day == attended
    // This helps for multiple day/week programs where 2nd attendnace was being counted as make
    // up for previous absence.  While technically correct it was confusing for the user
    $absence_candidates = [];
    while(count($required_days) > 0){
        $requiredDay = array_shift($required_days);
        $key = array_search($requiredDay->format("Y-m-d"), $attended_days);
        if($key !== false) {
echo "using attendance on " . $requiredDay->format("Y-m-d") . " to satisify " .  $requiredDay->format("Y-m-d"). "<br>";
            unset($attended_days[$key]); 
        }
        else {
            $absence_candidates[] = $requiredDay;
        }
    }

    // reset required days to the days that didn't have attendance
    $required_days = $absence_candidates;
    while(count($required_days) > 0){
        $requiredDay = array_shift($required_days);
        $found = false;

        // Check existing absences FIRST. Helps with multiple attendance in week.
        $key = array_search($requiredDay->format("Y-m-d"), $excused_absence_days);
        if($key !== false) {
echo "using excused absence " . $requiredDay->format("Y-m-d") . " to satisify " .  $requiredDay->format("Y-m-d"). "<br>";
            // Already an excused absence on that day
            $found = true;
            // Remove the absence so it can't be used to satisify another absence
            unset($excused_absence_days[$key]); 
        }

        // Check for unexcused absence
        if(!$found) {
            $key = array_search($requiredDay->format("Y-m-d"), $unexcused_absence_days);
            if($key !== false) {
echo "using un-excused absence " . $requiredDay->format("Y-m-d") . " to satisify " .  $requiredDay->format("Y-m-d"). "<br>";
                // Already an un-excused absence on that day
                $found = true;
                // Remove the absence so it can't be used to satisify another absence
                unset($unexcused_absence_days[$key]); 
            }
        }
                
        if(!$found) {
            // Look from -6 to +6 from $required_day for makeup attendance
            $date_idx = clone($requiredDay);
            $date_idx-> modify('-6 days');

            $i = 0;
            while($i < 13 && !$found) {
                $key = array_search($date_idx->format("Y-m-d"), $attended_days);
                if($key !== false) {
echo "using makeup attendance " . $date_idx->format("Y-m-d") . " to satisify " .  $requiredDay->format("Y-m-d"). "<br>";
                    $found = true;
                    // Remove the attendance day so it can't be used to satisify another required attendance
                    unset($attended_days[$key]); 
                }
                $i++;
                $date_idx->modify('+1 day');
            }
        }

        // No absences until after 6 day grace period
        $grace_date = (new DateTime("-6 days"))->setTime(0, 0, 0, 0);  // 6 days ago

        if(!$found) {
            if($requiredDay < $maxOld) {
                echo "not creating absence " . $requiredDay->format("Y-m-d") . " because it's too old.<br>";
            }
            else if ($requiredDay > $grace_date) {
                echo "not creating absence " . $requiredDay->format("Y-m-d") . " because it's too recent.<br>";
            }
            else {
                insertAbsenceRecord($client_id, $requiredDay->format("Y-m-d"));
            }
        }
    }
}

function isRequiredDOW($date, $row)
{
    $dayOfWeek = $date->format("w");
    if ($dayOfWeek == "0") {
        return $row['attends_sunday'] == 1;
    } elseif ($dayOfWeek == "1") {
        return $row['attends_monday'] == 1;
    } elseif ($dayOfWeek == "2") {
        return $row['attends_tuesday'] == 1;
    } elseif ($dayOfWeek == "3") {
        return $row['attends_wednesday'] == 1;
    } elseif ($dayOfWeek == "4") {
        return $row['attends_thursday'] == 1;
    } elseif ($dayOfWeek == "5") {
        return $row['attends_friday'] == 1;
    } elseif ($dayOfWeek == "6") {
        return $row['attends_saturday'] == 1;
    }
    
    return $false;
}

function insertAbsenceRecord($client_id, $date)
{
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
        exit('Error creating absence record'); //something a user can understand
    }

    $vars = parse_columns('attendance_record', $_POST);
    $stmt = $pdo->prepare("INSERT INTO absence (client_id, date, note) VALUES (?,?,?)");

    $today = new DateTime();
    $note = "Auto generated " . $today->format("Y-m-d");

    echo "insertAbsenceRecord client: " . $client_id . " date: " . $date . " note: " . $note . "<br>";
    if ($stmt->execute([$client_id, $date, $note])) {
         $stmt = null;
    } else {
         echo "Error creating absence record";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>NotesAO - Absence Report</title>
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

<body>
    <?php buildAbsenceRecords(); ?>
</body>