<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";
require_once "sql_functions.php";

$include_exits = false;
if (isset($_GET['include_exits'])) {
    $include_exits = true;
}
if (isset($_POST["include_exits"])) {
    $include_exits = true;
}

function prepare_table()
{
    global $link;
    $sql = "DROP TABLE report3";
    try {
        if ($stmt = mysqli_prepare($link, $sql)) {
            mysqli_stmt_execute($stmt);
        }
    } catch (Exception $e) {
        // Ignore errors dropping table
    }

    $sql = "
        CREATE TABLE report3 (
            client_id int(11) NOT NULL,
            report_date date NOT NULL,
            report_time time NOT NULL,
            program_name varchar(64) NOT NULL,
            first_name varchar(45) NOT NULL,
            last_name varchar(45) NOT NULL,
            image_url varchar(256) NOT NULL,
            dob date DEFAULT NULL,
            age int(2) DEFAULT NULL,
            gender varchar(16) NOT NULL,
            phone_number varchar(45) DEFAULT NULL,
            ethnicity_code varchar(1) NOT NULL,
            ethnicity_name varchar(16) NOT NULL,
            required_sessions int(11) NOT NULL,
            cause_number varchar(15) DEFAULT NULL,
            referral_type varchar(45) NOT NULL,
            case_manager_first_name varchar(45) NOT NULL,
            case_manager_last_name varchar(45) NOT NULL,
            case_manager_email varchar(45) DEFAULT NULL,
            case_manager_office varchar(45) DEFAULT NULL,
            group_name varchar(45) DEFAULT NULL,
            fee decimal(6,2) NOT NULL DEFAULT 0,
            balance decimal(6,2) NOT NULL DEFAULT 0,
            attended int(3) NOT NULL DEFAULT 0,
            absence_excused int(3) NOT NULL DEFAULT 0,
            absence_unexcused int(3) NOT NULL DEFAULT 0,
            client_stage varchar(128) NOT NULL,
            client_note varchar(2048) NOT NULL,
            speaks_significantly_in_group varchar(1) NOT NULL,
            respectful_to_group varchar(1) NOT NULL,
            takes_responsibility_for_past varchar(1) NOT NULL,
            disruptive_argumentitive varchar(1) NOT NULL,
            humor_inappropriate varchar(1) NOT NULL,
            blames_victim varchar(1) NOT NULL,
            appears_drug_alcohol varchar(1) NOT NULL,
            inappropriate_to_staff varchar(1) NOT NULL,
            other_concerns varchar(2048) DEFAULT NULL,
            orientation_date date DEFAULT NULL,
            exit_date date DEFAULT NULL,
            exit_reason varchar(45) DEFAULT NULL,
            exit_note varchar(2048) DEFAULT NULL,
            c1_header varchar(16) DEFAULT NULL,
            c1_11 varchar(8) DEFAULT NULL,
            c1_12 varchar(8) DEFAULT NULL,
            c1_13 varchar(8) DEFAULT NULL,
            c1_14 varchar(8) DEFAULT NULL,
            c1_15 varchar(8) DEFAULT NULL,
            c1_16 varchar(8) DEFAULT NULL,
            c1_17 varchar(8) DEFAULT NULL,
            c1_21 varchar(8) DEFAULT NULL,
            c1_22 varchar(8) DEFAULT NULL,
            c1_23 varchar(8) DEFAULT NULL,
            c1_24 varchar(8) DEFAULT NULL,
            c1_25 varchar(8) DEFAULT NULL,
            c1_26 varchar(8) DEFAULT NULL,
            c1_27 varchar(8) DEFAULT NULL,
            c1_31 varchar(8) DEFAULT NULL,
            c1_32 varchar(8) DEFAULT NULL,
            c1_33 varchar(8) DEFAULT NULL,
            c1_34 varchar(8) DEFAULT NULL,
            c1_35 varchar(8) DEFAULT NULL,
            c1_36 varchar(8) DEFAULT NULL,
            c1_37 varchar(8) DEFAULT NULL,
            c1_41 varchar(8) DEFAULT NULL,
            c1_42 varchar(8) DEFAULT NULL,
            c1_43 varchar(8) DEFAULT NULL,
            c1_44 varchar(8) DEFAULT NULL,
            c1_45 varchar(8) DEFAULT NULL,
            c1_46 varchar(8) DEFAULT NULL,
            c1_47 varchar(8) DEFAULT NULL,
            c1_51 varchar(8) DEFAULT NULL,
            c1_52 varchar(8) DEFAULT NULL,
            c1_53 varchar(8) DEFAULT NULL,
            c1_54 varchar(8) DEFAULT NULL,
            c1_55 varchar(8) DEFAULT NULL,
            c1_56 varchar(8) DEFAULT NULL,
            c1_57 varchar(8) DEFAULT NULL,
            c1_61 varchar(8) DEFAULT NULL,
            c1_62 varchar(8) DEFAULT NULL,
            c1_63 varchar(8) DEFAULT NULL,
            c1_64 varchar(8) DEFAULT NULL,
            c1_65 varchar(8) DEFAULT NULL,
            c1_66 varchar(8) DEFAULT NULL,
            c1_67 varchar(8) DEFAULT NULL,
            c2_header varchar(16) DEFAULT NULL,
            c2_11 varchar(8) DEFAULT NULL,
            c2_12 varchar(8) DEFAULT NULL,
            c2_13 varchar(8) DEFAULT NULL,
            c2_14 varchar(8) DEFAULT NULL,
            c2_15 varchar(8) DEFAULT NULL,
            c2_16 varchar(8) DEFAULT NULL,
            c2_17 varchar(8) DEFAULT NULL,
            c2_21 varchar(8) DEFAULT NULL,
            c2_22 varchar(8) DEFAULT NULL,
            c2_23 varchar(8) DEFAULT NULL,
            c2_24 varchar(8) DEFAULT NULL,
            c2_25 varchar(8) DEFAULT NULL,
            c2_26 varchar(8) DEFAULT NULL,
            c2_27 varchar(8) DEFAULT NULL,
            c2_31 varchar(8) DEFAULT NULL,
            c2_32 varchar(8) DEFAULT NULL,
            c2_33 varchar(8) DEFAULT NULL,
            c2_34 varchar(8) DEFAULT NULL,
            c2_35 varchar(8) DEFAULT NULL,
            c2_36 varchar(8) DEFAULT NULL,
            c2_37 varchar(8) DEFAULT NULL,
            c2_41 varchar(8) DEFAULT NULL,
            c2_42 varchar(8) DEFAULT NULL,
            c2_43 varchar(8) DEFAULT NULL,
            c2_44 varchar(8) DEFAULT NULL,
            c2_45 varchar(8) DEFAULT NULL,
            c2_46 varchar(8) DEFAULT NULL,
            c2_47 varchar(8) DEFAULT NULL,
            c2_51 varchar(8) DEFAULT NULL,
            c2_52 varchar(8) DEFAULT NULL,
            c2_53 varchar(8) DEFAULT NULL,
            c2_54 varchar(8) DEFAULT NULL,
            c2_55 varchar(8) DEFAULT NULL,
            c2_56 varchar(8) DEFAULT NULL,
            c2_57 varchar(8) DEFAULT NULL,
            c2_61 varchar(8) DEFAULT NULL,
            c2_62 varchar(8) DEFAULT NULL,
            c2_63 varchar(8) DEFAULT NULL,
            c2_64 varchar(8) DEFAULT NULL,
            c2_65 varchar(8) DEFAULT NULL,
            c2_66 varchar(8) DEFAULT NULL,
            c2_67 varchar(8) DEFAULT NULL,
            c3_header varchar(16) DEFAULT NULL,
            c3_11 varchar(8) DEFAULT NULL,
            c3_12 varchar(8) DEFAULT NULL,
            c3_13 varchar(8) DEFAULT NULL,
            c3_14 varchar(8) DEFAULT NULL,
            c3_15 varchar(8) DEFAULT NULL,
            c3_16 varchar(8) DEFAULT NULL,
            c3_17 varchar(8) DEFAULT NULL,
            c3_21 varchar(8) DEFAULT NULL,
            c3_22 varchar(8) DEFAULT NULL,
            c3_23 varchar(8) DEFAULT NULL,
            c3_24 varchar(8) DEFAULT NULL,
            c3_25 varchar(8) DEFAULT NULL,
            c3_26 varchar(8) DEFAULT NULL,
            c3_27 varchar(8) DEFAULT NULL,
            c3_31 varchar(8) DEFAULT NULL,
            c3_32 varchar(8) DEFAULT NULL,
            c3_33 varchar(8) DEFAULT NULL,
            c3_34 varchar(8) DEFAULT NULL,
            c3_35 varchar(8) DEFAULT NULL,
            c3_36 varchar(8) DEFAULT NULL,
            c3_37 varchar(8) DEFAULT NULL,
            c3_41 varchar(8) DEFAULT NULL,
            c3_42 varchar(8) DEFAULT NULL,
            c3_43 varchar(8) DEFAULT NULL,
            c3_44 varchar(8) DEFAULT NULL,
            c3_45 varchar(8) DEFAULT NULL,
            c3_46 varchar(8) DEFAULT NULL,
            c3_47 varchar(8) DEFAULT NULL,
            c3_51 varchar(8) DEFAULT NULL,
            c3_52 varchar(8) DEFAULT NULL,
            c3_53 varchar(8) DEFAULT NULL,
            c3_54 varchar(8) DEFAULT NULL,
            c3_55 varchar(8) DEFAULT NULL,
            c3_56 varchar(8) DEFAULT NULL,
            c3_57 varchar(8) DEFAULT NULL,
            c3_61 varchar(8) DEFAULT NULL,
            c3_62 varchar(8) DEFAULT NULL,
            c3_63 varchar(8) DEFAULT NULL,
            c3_64 varchar(8) DEFAULT NULL,
            c3_65 varchar(8) DEFAULT NULL,
            c3_66 varchar(8) DEFAULT NULL,
            c3_67 varchar(8) DEFAULT NULL,
            c4_header varchar(16) DEFAULT NULL,
            c4_11 varchar(8) DEFAULT NULL,
            c4_12 varchar(8) DEFAULT NULL,
            c4_13 varchar(8) DEFAULT NULL,
            c4_14 varchar(8) DEFAULT NULL,
            c4_15 varchar(8) DEFAULT NULL,
            c4_16 varchar(8) DEFAULT NULL,
            c4_17 varchar(8) DEFAULT NULL,
            c4_21 varchar(8) DEFAULT NULL,
            c4_22 varchar(8) DEFAULT NULL,
            c4_23 varchar(8) DEFAULT NULL,
            c4_24 varchar(8) DEFAULT NULL,
            c4_25 varchar(8) DEFAULT NULL,
            c4_26 varchar(8) DEFAULT NULL,
            c4_27 varchar(8) DEFAULT NULL,
            c4_31 varchar(8) DEFAULT NULL,
            c4_32 varchar(8) DEFAULT NULL,
            c4_33 varchar(8) DEFAULT NULL,
            c4_34 varchar(8) DEFAULT NULL,
            c4_35 varchar(8) DEFAULT NULL,
            c4_36 varchar(8) DEFAULT NULL,
            c4_37 varchar(8) DEFAULT NULL,
            c4_41 varchar(8) DEFAULT NULL,
            c4_42 varchar(8) DEFAULT NULL,
            c4_43 varchar(8) DEFAULT NULL,
            c4_44 varchar(8) DEFAULT NULL,
            c4_45 varchar(8) DEFAULT NULL,
            c4_46 varchar(8) DEFAULT NULL,
            c4_47 varchar(8) DEFAULT NULL,
            c4_51 varchar(8) DEFAULT NULL,
            c4_52 varchar(8) DEFAULT NULL,
            c4_53 varchar(8) DEFAULT NULL,
            c4_54 varchar(8) DEFAULT NULL,
            c4_55 varchar(8) DEFAULT NULL,
            c4_56 varchar(8) DEFAULT NULL,
            c4_57 varchar(8) DEFAULT NULL,
            c4_61 varchar(8) DEFAULT NULL,
            c4_62 varchar(8) DEFAULT NULL,
            c4_63 varchar(8) DEFAULT NULL,
            c4_64 varchar(8) DEFAULT NULL,
            c4_65 varchar(8) DEFAULT NULL,
            c4_66 varchar(8) DEFAULT NULL,
            c4_67 varchar(8) DEFAULT NULL,
            last_attended date DEFAULT NULL,
            last_absence date DEFAULT NULL
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;        
        ";
    if ($stmt = mysqli_prepare($link, $sql)) {
        if (mysqli_stmt_execute($stmt)) {
            // Success
        } else {
            echo "Oops! Something went wrong. Please try again.<br>" . $stmt->error;
        }
    }

    // Close statement
    mysqli_stmt_close($stmt);
    return;
}

function populate_report3($include_exits)
{
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        $url = "https://";
    else
        $url = "http://";
    $url .= $_SERVER['HTTP_HOST'];
    $url .= $_SERVER['REQUEST_URI'];
    $url_array = explode('/', $url);
    array_pop($url_array);
    $url = implode('/', $url_array);

    global $link;
    $resultarray = null;
    $sql = "
            INSERT INTO report3
        (
        client_id,
        report_date,
        report_time,
        program_name,
        first_name,
        last_name,
        image_url,
        dob,
        age,
        gender,
        phone_number,
        ethnicity_code,
        ethnicity_name,
        cause_number,
        referral_type,
        case_manager_first_name,
        case_manager_last_name,
        case_manager_email,
        case_manager_office,
        group_name,
        required_sessions,
        fee,
        balance,
        attended,
        absence_excused,
        absence_unexcused,
        client_stage,
        client_note,
        speaks_significantly_in_group,
        respectful_to_group,
        takes_responsibility_for_past,
        disruptive_argumentitive,
        humor_inappropriate,
        blames_victim,
        appears_drug_alcohol,
        inappropriate_to_staff,
        other_concerns,
        orientation_date,
        exit_date,
        exit_reason,
        exit_note,
        last_attended,
        last_absence
        )
        SELECT
                c.id client_id,
                now(),
                now(),
                p.name program_name,
                c.first_name first_name,
                c.last_name last_name,
                coalesce(concat('" . $url . "/getImageKey.php?id=', c.id, '&key=', i.hash),concat('" . $url . "', '/img/male-placeholder.jpg')),
                c.date_of_birth dob,
                DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(), c.date_of_birth)),'%Y') +0 age,
                g.gender gender,
                c.phone_number phone_number,
                e.code ethnicity_code,
                e.name ethnicity_name,
                c.cause_number cause_number,
                rt.referral_type referral_type,
                m.first_name po_first,
                m.last_name po_last,
                m.email po_email,
                m.office po_office,
                tg.name group_name,
                c.required_sessions required_sessions,
                c.fee fee,
                coalesce(client_ledger.balance, '0') balance,
                coalesce(client_attendance.sessions_attended,'0') sessions_attended,
                coalesce(absence_excused.count,'0') absence_excused,
                coalesce(absence_unexcused.count,'0') absence_unexcused,
                stage.stage client_stage,
                c.note client_note,
                case when speaksSignificantlyInGroup = 0 then 'N' else 'Y' end as speaks_significantly_in_group,
                case when respectfulTowardsGroup = 0 then 'N' else 'Y' end as respectful_to_group,
                case when takesResponsibilityForPastBehavior = 0 then 'N' else 'Y' end as takes_responsibility_for_past,
                case when disruptiveOrArgumentitive = 1 then 'Y' else 'N' end as disruptive_argumentitive,
                case when inappropriateHumor = 1 then 'Y' else 'N' end as humor_inappropriate,
                case when blamesVictim = 1 then 'Y' else 'N' end as blames_victim,
                case when drug_alcohol = 1 then 'Y' else 'N' end as appears_drug_alcohol,
                case when inappropriate_behavior_to_staff = 1 then 'Y' else 'N' end as inappropriate_to_staff,
                c.other_concerns,
                c.orientation_date orientation_date,
                c.exit_date exit_date,
                er.reason exit_reason,
                c.exit_note exit_note,
                client_last_attendance.last_seen last_attended,
                client_last_absence.last_absence last_absence
                FROM client
                    c
                LEFT JOIN program p ON
                    c.program_id = p.id
                LEFT OUTER JOIN ethnicity e ON
                    c.ethnicity_id = e.id
                LEFT OUTER JOIN image i ON
                    c.id = i.id    
                LEFT OUTER JOIN case_manager m ON
                    c.case_manager_id = m.id
                LEFT OUTER JOIN referral_type rt ON
                    c.referral_type_id = rt.id
                LEFT OUTER JOIN exit_reason er ON
                    c.exit_reason_id = er.id
                LEFT OUTER JOIN therapy_group tg ON
                    c.therapy_group_id = tg.id
                LEFT JOIN gender g ON
                    c.gender_id = g.id
                LEFT JOIN client_stage stage ON
                    c.client_stage_id = stage.id
                LEFT JOIN (select ar.client_id client_id, count(ar.client_id) sessions_attended from attendance_record ar group by ar.client_id) as client_attendance ON
                    c.id = client_attendance.client_id
                LEFT OUTER JOIN (select ar.client_id client_id, max(ts.date) last_seen from attendance_record ar left join therapy_session ts on ar.therapy_session_id = ts.id group by ar.client_id) as client_last_attendance ON 
                    c.id = client_last_attendance.client_id
                LEFT JOIN (select client_id, count(id) count from absence a where excused <> 1 group by a.client_id) as absence_unexcused ON
                    c.id = absence_unexcused.client_id
                LEFT JOIN (select client_id, max(date) last_absence from absence a where excused <> 1 group by a.client_id) as client_last_absence ON
                    c.id = client_last_absence.client_id
                LEFT JOIN (select client_id, count(id) count from absence a where excused = 1 group by a.client_id) as absence_excused ON
                    c.id = absence_excused.client_id
                LEFT JOIN (select l.client_id client_id, sum(l.amount) balance from ledger l group by l.client_id) as client_ledger ON
                    c.id = client_ledger.client_id";
                if($include_exits){
                    $sql = $sql . ";";
                }
                else {
                    $sql = $sql . " WHERE c.exit_date is null;";
                }

    if ($stmt = mysqli_prepare($link, $sql)) {
        if (mysqli_stmt_execute($stmt)) {
            // Success
        } else {
            echo "Oops! Something went wrong. Please try again.<br>" . $stmt->error;
        }
    }
    // Close statement
    mysqli_stmt_close($stmt);
    return;
}


function populate_report3_client_calendar($client_id, $calendar_values, $prefix = NULL)
{
    global $link;

    $parameter_array = array();
    $NUM_VALUES = 43;
    for ($count = 0; $count < $NUM_VALUES; $count++) {
        $parameter_array[] = (count($calendar_values) > $count) ? $calendar_values[$count] : NULL;
    }
    $parameter_array[] = $client_id;

    $sql = "UPDATE report3 set c1_header=?,
        c1_11=?, c1_12=?, c1_13=?, c1_14=?, c1_15=?, c1_16=?, c1_17=?,
        c1_21=?, c1_22=?, c1_23=?, c1_24=?, c1_25=?, c1_26=?, c1_27=?,
        c1_31=?, c1_32=?, c1_33=?, c1_34=?, c1_35=?, c1_36=?, c1_37=?,
        c1_41=?, c1_42=?, c1_43=?, c1_44=?, c1_45=?, c1_46=?, c1_47=?,
        c1_51=?, c1_52=?, c1_53=?, c1_54=?, c1_55=?, c1_56=?, c1_57=?,
        c1_61=?, c1_62=?, c1_63=?, c1_64=?, c1_65=?, c1_66=?, c1_67=?
           WHERE client_id=?";

    if ($prefix != NULL) {
        $sql = str_replace("c1", $prefix, $sql);
    }

    $__vartype = "sssssssssssssssssssssssssssssssssssssssssssi";
    if ($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, $__vartype, ...$parameter_array);
        if (mysqli_stmt_execute($stmt)) {
        } else {
            echo "ERROR <br>" . $stmt->error . "<br>";
        }
    } else {
        echo "ERROR <br>" . $stmt->error . "<br>";
    }

    mysqli_stmt_close($stmt);
}


function buildCalendar($client_id, $date, $attendance, $excused, $unexcused, $prefix)
{
    $calendar_values = array();

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

    $calendar_values[] = $title . $year;

    //first we take care of those blank days
    while ($blank > 0) {
        $calendar_values[] = NULL;
        $blank = $blank - 1;
    }

    $day_num = 1;

    //count up the days, until we've done all of them in the month
    while ($day_num <= $days_in_month) {
        $cell_string = $day_num;

        if ($day_num < 10) {
            echo "&nbsp;";
        }
        echo "&nbsp;";

        $count = arrayCount($curDay->format("Y-m-d"), $attendance);
        for ($i = 0; $i < $count; $i++) {
            $cell_string = $cell_string . " " . "✅"; //"&#x2705";  // Green Check for present
        }

        $count = arrayCount($curDay->format("Y-m-d"), $excused);
        for ($i = 0; $i < $count; $i++) {
            $cell_string = $cell_string . " " . "✖"; // "&#x2716";  // Grey x for excused absence
        }

        $count = arrayCount($curDay->format("Y-m-d"), $unexcused);
        for ($i = 0; $i < $count; $i++) {
            $cell_string = $cell_string . " " . "❌"; //"&#x274C";  // Red X for unexcused
        }

        $day_num++;
        $curDay->modify('+ 1 day');
        $calendar_values[] = $cell_string;
    }

    populate_report3_client_calendar($client_id, $calendar_values, $prefix);
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>buildcsv</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>

<body>
    <section class="pt-5">

        <?php
        $start_time = microtime(true);

        prepare_table();
        populate_report3($include_exits);

        $client_count = 0;
        // Loop through and populate the attendance data for each clientID
        global $link;
        $sql = "SELECT client_id, orientation_date, exit_date from report3";
        if ($stmt = mysqli_prepare($link, $sql)) {
            if (mysqli_stmt_execute($stmt)) {
                $result = mysqli_stmt_get_result($stmt);
                if (mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    while ($row = mysqli_fetch_array($result)) {
                        $client_count++;
                        $client_id = $row["client_id"];
                        $exit = new DateTime($row["exit_date"]);  // will default to day if not exited
                        $date = new DateTime($row["orientation_date"]);

                        // The calendar only has room for 4 months.  If the client
                        // has been participating for more than 4 months only show
                        // the most recent 4 months
                        $interval = $exit->diff($date);
                        $month_string = $interval->format("%m");
                        while($month_string > "3") {
                            $date->modify('first day of next month');
                            $interval = $exit->diff($date);
                            $month_string = $interval->format("%m");
                        }

                        $attendance = get_client_attendance_days(trim($client_id));
                        $temp = get_client_absence_days(trim($client_id));
                        $excused = $temp[0];
                        $unexcused = $temp[1];

                        buildCalendar($client_id, $date->getTimestamp(), $attendance, $excused, $unexcused, "c1");

                        $date->modify('first day of next month');
                        if ($date <= $exit) {
                            buildCalendar($client_id, $date->getTimestamp(), $attendance, $excused, $unexcused, "c2");
                        }
                        $date->modify('first day of next month');
                        if ($date <= $exit) {
                            buildCalendar($client_id, $date->getTimestamp(), $attendance, $excused, $unexcused, "c3");
                        }
                        $date->modify('first day of next month');
                        if ($date <= $exit) {
                            buildCalendar($client_id, $date->getTimestamp(), $attendance, $excused, $unexcused, "c4");
                        }
                    }
                } else {
                    echo "ERROR <br>" . $stmt->error . "<br>";
                }
            } else {
                echo "ERROR <br>" . $stmt->error . "<br>";
            }
        }
        // Close statement
        mysqli_stmt_close($stmt);

        $end_time = microtime(true);
        echo "Populated data for " . $client_count . " clients in " . ($end_time - $start_time) . " sec";
        ?>

        <a href="reporting.php" class="btn btn-primary">Return to Reporting</a>

    </section>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>

</html>