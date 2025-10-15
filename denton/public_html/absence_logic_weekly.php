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

function buildAbsenceRecordsForClient($client_id, $start_date, $weekly_attendance, $row)
{
    echo "<b>Checking attendance for client " . $client_id . "</b><br>";;

    // Moving week (sunday-sat) at a time
    // Starting the Sunday after orientation (or first attendance)
    // Looks back 4 weeks max
    // If attendance record count + absence record count < expected_weekly attendance
    // Insert absence records - default to attends_days if specified

    $attendance_list = get_client_attendance_days(trim($client_id));
    $temp = get_client_absence_days(trim($client_id));
    $excused = $temp[0];
    $unexcused = $temp[1];

    // If today is sunday use it; otherwise last sunday
    $sunday = new DateTime();
    if ($sunday->format("w") == "0") {
        $stop_date = ($sunday)->setTime(0, 0, 0, 0);
    } else {
        $stop_date = (new DateTime("last sunday"))->setTime(0, 0, 0, 0);
    }

    $weeks_ago = (clone $stop_date)->modify("-4 weeks");
    $date_idx = (new DateTime($start_date))->modify("next sunday")->setTime(0, 0, 0, 0);

    // Only look back 4 weeks max
    if ($date_idx < $weeks_ago) {
        //            echo "start_date override before:" . $date_idx->format('Y-m-d') . " after " . $weeks_ago->format('Y-m-d') . "<br>";
        $date_idx = $weeks_ago;
    }

    while ($date_idx < $stop_date) {
        $week_start = clone $date_idx;
        $record_count = 0;
        for ($i = 0; $i < 7; $i++) {  // Week at a time
            if (in_array($date_idx->format("Y-m-d"), $attendance_list)) {
                $record_count = $record_count + arrayCount($date_idx->format("Y-m-d"), $attendance_list);
            }
            if (in_array($date_idx->format("Y-m-d"), $excused)) {
                $record_count = $record_count + arrayCount($date_idx->format("Y-m-d"), $excused);
            }
            if (in_array($date_idx->format("Y-m-d"), $unexcused)) {
                $record_count = $record_count + arrayCount($date_idx->format("Y-m-d"), $unexcused);
            }
            $date_idx->modify('+ 1 day');
        }

        echo "Analyze week starting " . $week_start->format("Y-m-d H:i:s") . " attendance " . $record_count . " required " . $weekly_attendance . "<br>";
        // Shouldn't happen but Don't create absences in the future
        if ($date_idx <= $stop_date) {
            while ($record_count < $weekly_attendance) {
                $absence_day = calculateAbsenceDay($week_start, $attendance_list, array_merge($excused, $unexcused), $row);
                insertAbsenceRecord($client_id, $absence_day->format("Y-m-d"));
                $unexcused[] = $absence_day->format("Y-m-d");
                $record_count++;
            }
        }
    }
}

function calculateAbsenceDay($start_day, $attendance_list, $absence_list, $row)
{
    // Start on the first day of the week.  Find a day where the client
    // is scheduled to attend but doesn't have attendance or absence record
    // If a day isn't found just geneate the absence for saturday
    $attends_sunday = $row['attends_sunday'] == 1;
    $attends_monday = $row['attends_monday'] == 1;
    $attends_tuesday = $row['attends_tuesday'] == 1;
    $attends_wednesday = $row['attends_wednesday'] == 1;
    $attends_thursday = $row['attends_thursday'] == 1;
    $attends_friday = $row['attends_friday'] == 1;
    $attends_saturday = $row['attends_saturday'] == 1;

    $dayFound = false;
    $absence_day = clone ($start_day);
    $count = 0;
    while (!$dayFound && $count < 7) {
        $count++;

        if (
            in_array($absence_day->format("Y-m-d"), $attendance_list) ||
            in_array($absence_day->format("Y-m-d"), $absence_list)
        ) {
            // Client already marked present or absent that day
            $absence_day->modify("+1 day");
        } else {
            $dayOfWeek = $absence_day->format("w");
            if ($dayOfWeek == "0" && $attends_sunday) {
                $dayFound = true;
            } elseif ($dayOfWeek == "1" && $attends_monday) {
                $dayFound = true;
            } elseif ($dayOfWeek == "2" && $attends_tuesday) {
                $dayFound = true;
            } elseif ($dayOfWeek == "3" && $attends_wednesday) {
                $dayFound = true;
            } elseif ($dayOfWeek == "4" && $attends_thursday) {
                $dayFound = true;
            } elseif ($dayOfWeek == "5" && $attends_friday) {
                $dayFound = true;
            } elseif ($dayOfWeek == "6" && $attends_saturday) {
                $dayFound = true;
            } else {
                $absence_day->modify("+1 day");
            }
        }
    }

    if (!$dayFound) {  // no matter what we will create the absence
        $absence_day = $start_day;
        echo "default absence day";
    }

    return $absence_day;
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
    <title>Build buildAbsenceRecords</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>

<body>
    <?php buildAbsenceRecords(); ?>
</body>