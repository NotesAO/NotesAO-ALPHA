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
    
    function prepare_table() {
        global $link;
        $sql = "DROP TABLE report4";
        try {
            if($stmt = mysqli_prepare($link, $sql)){
                mysqli_stmt_execute($stmt);
            }
        }
        catch (Exception $e) {
            // Ignore errors dropping table
        }


        $sql = "
        CREATE TABLE report4 (
            client_id int(11) NOT NULL,
            report_date date NOT NULL,
            report_time time NOT NULL,
            program_name varchar(64) NOT NULL,
            first_name varchar(45) NOT NULL,
            last_name varchar(45) NOT NULL,
            image_url varchar(256) NOT NULL,
            client_url varchar(256) NOT NULL,
            dob date DEFAULT NULL,
            age int(2) DEFAULT NULL,
            gender varchar(16) NOT NULL,
            phone_number varchar(45) DEFAULT NULL,
            email varchar(64) DEFAULT NULL,
            group_name varchar(45) DEFAULT NULL,
            referral_type varchar(45) NOT NULL,
            required_sessions int(11) NOT NULL,
            attended int(3) NOT NULL DEFAULT 0,
            last_attended date DEFAULT NULL,
            days_since_attended int(3) DEFAULT NULL,
            last_absence date DEFAULT NULL,
            absence_excused int(3) NOT NULL DEFAULT 0,
            absence_unexcused int(3) NOT NULL DEFAULT 0,
            attends_sunday  tinyint(1) NOT NULL DEFAULT 0,
            attends_monday  tinyint(1) NOT NULL DEFAULT 0,
            attends_tuesday  tinyint(1) NOT NULL DEFAULT 0,
            attends_wednesday  tinyint(1) NOT NULL DEFAULT 0,
            attends_thursday  tinyint(1) NOT NULL DEFAULT 0,
            attends_friday  tinyint(1) NOT NULL DEFAULT 0,
            attends_saturday  tinyint(1) NOT NULL DEFAULT 0,
            weekly_attendance int(1) NOT NULL DEFAULT 0,
            case_manager_first_name varchar(45) NOT NULL,
            case_manager_last_name varchar(45) NOT NULL,
            case_manager_email varchar(45) DEFAULT NULL,
            case_manager_phone varchar(45) DEFAULT NULL,
            case_manager_fax varchar(45) DEFAULT NULL,
            case_manager_office varchar(45) DEFAULT NULL,
            fee decimal(6,2) NOT NULL DEFAULT 0,
            balance decimal(6,2) NOT NULL DEFAULT 0,
            client_stage varchar(128) NOT NULL,
            client_note varchar(2048) NOT NULL,
            other_concerns varchar(2048) DEFAULT NULL,
            orientation_date date DEFAULT NULL,
            exit_date date DEFAULT NULL,
            exit_reason varchar(45) DEFAULT NULL,
            exit_note varchar(2048) DEFAULT NULL
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;        
        ";
        if($stmt = mysqli_prepare($link, $sql)){
            if(mysqli_stmt_execute($stmt)){
                // Success
            } else{
                echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
            }
        }

        // Close statement
        mysqli_stmt_close($stmt);
        return;
    }    

    function populate_report4($include_exits) {
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')   
            $url = "https://";   
        else  
            $url = "https://";   
        $url.= $_SERVER['HTTP_HOST'];   
        $url.= $_SERVER['REQUEST_URI'];
        $url_array = explode('/', $url);
        array_pop($url_array);
        $url = implode('/', $url_array);         

        global $link;
        $resultarray = null;
        $sql = "
            INSERT INTO report4
        (
        client_id,
        report_date,
        report_time,
        program_name,
        first_name,
        last_name,
        image_url,
        client_url,
        dob,
        age,
        gender,
        phone_number,
        email,
        group_name,
        referral_type,
        required_sessions,
        attended,
        last_attended,
        days_since_attended,
        last_absence,
        absence_excused,
        absence_unexcused,
        attends_sunday,
        attends_monday,
        attends_tuesday,
        attends_wednesday,
        attends_thursday,
        attends_friday,
        attends_saturday,
        weekly_attendance,
        case_manager_first_name,
        case_manager_last_name,
        case_manager_email,
        case_manager_phone,
        case_manager_fax,
        case_manager_office,
        fee,
        balance,
        client_stage,
        client_note,
        other_concerns,
        orientation_date,
        exit_date,
        exit_reason,
        exit_note
        )
        SELECT
                c.id client_id,
                now(),
                now(),
                p.name program_name,
                c.first_name first_name,
                c.last_name last_name,
                coalesce(concat('" . $url ."/getImageKey.php?id=', c.id, '&key=', i.hash),concat('" . $url ."', '/img/male-placeholder.jpg')),
                concat('" . $url . "/client-review.php?client_id=', c.id),                
                c.date_of_birth dob,
                DATE_FORMAT(FROM_DAYS(DATEDIFF(NOW(), c.date_of_birth)),'%Y') +0 age,
                g.gender gender,
                c.phone_number phone_number,
                c.email email,
                tg.name group_name,
                rt.referral_type referral_type,
                c.required_sessions required_sessions,
                coalesce(client_attendance.sessions_attended,'0') sessions_attended,
                client_last_attendance.last_seen last_attended,
                DATEDIFF(NOW(), client_last_attendance.last_seen) + 0 days_since,
                client_last_absence.last_absence last_absence,
                coalesce(absence_excused.count,'0') absence_excused,
                coalesce(absence_unexcused.count,'0') absence_unexcused,
                attends_sunday,
                attends_monday,
                attends_tuesday,
                attends_wednesday,
                attends_thursday,
                attends_friday,
                attends_saturday,
                weekly_attendance,                
                m.first_name po_first,
                m.last_name po_last,
                m.email po_email,
                m.phone_number po_phone,
                m.fax po_fax,                                
                m.office po_office,
                c.fee fee,
                coalesce(client_ledger.balance, '0') balance,
                stage.stage client_stage,
                c.note client_note,
                c.other_concerns,
                c.orientation_date orientation_date,
                c.exit_date exit_date,
                er.reason exit_reason,
                c.exit_note exit_note
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
                LEFT OUTER JOIN (select client_id client_id, max(date) last_absence from absence group by client_id) as client_last_absence ON 
                    c.id = client_last_absence.client_id
                LEFT JOIN (select client_id, count(id) count from absence a where excused <> 1 group by a.client_id) as absence_unexcused ON
                    c.id = absence_unexcused.client_id
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
    

            if($stmt = mysqli_prepare($link, $sql)){
                if(mysqli_stmt_execute($stmt)){
                    // Success
                } else{
                    echo "Oops! Something went wrong. Please try again.<br>".$stmt->error;
                }
            }
            // Close statement
            mysqli_stmt_close($stmt);
            return;
        }


    // function populate_report2_client_attendance($client_id) {
    //     global $link;

    //     $present_dates = array();
    //     $present_cur = array();
        
    //     $attendance_sql = "
    //             SELECT
    //             ar.client_id client_id,
    //             ts.id session_id,
    //             ts.date session_date,
    //             cur.short_description curriculum
    //         FROM
    //             client c
    //         LEFT join attendance_record ar ON
    //             c.id = ar.client_id
    //         LEFT JOIN therapy_session ts ON
    //             ar.therapy_session_id = ts.id
    //         LEFT OUTER JOIN curriculum cur ON
    //             ts.curriculum_id = cur.id
    //         WHERE
    //             ar.client_id = ?
    //         ORDER BY session_date asc
    //     ";
    //     if($stmt = mysqli_prepare($link, $attendance_sql)){
    //         mysqli_stmt_bind_param($stmt, "i", $client_id);
    //         if(mysqli_stmt_execute($stmt)){
    //             $result = mysqli_stmt_get_result($stmt);
    //             if(mysqli_stmt_execute($stmt)){
    //                 $result = mysqli_stmt_get_result($stmt);
    //                 while($row = mysqli_fetch_array($result)){
    //                     $present_dates[] = $row["session_date"];
    //                     $present_cur[] = $row["curriculum"];
    //                 }
    //             } else{
    //                 echo "ERROR <br>".$stmt->error . "<br>";
    //             }
    //         } else{
    //             echo "ERROR <br>".$stmt->error . "<br>";
    //         }
    //     }
    //     mysqli_stmt_close($stmt);

    //     $parameter_array = array();

    //     $NUM_PRESENT = 35;
    //     // If client has more than 28 absences then take the most recent 28
    //     if(count($present_dates) > $NUM_PRESENT) {
    //         $start_index = count($present_dates) - $NUM_PRESENT;
    //         $present_dates = array_slice($present_dates, $start_index, $NUM_PRESENT);
    //         $present_cur = array_slice($present_cur, $start_index, $NUM_PRESENT);
    //     }

    //     for($count = 0; $count < $NUM_PRESENT; $count++){
    //         $parameter_array[] = (count($present_dates) > $count) ? $present_dates[$count] : NULL;
    //         $parameter_array[] = (count($present_cur) > $count) ? $present_cur[$count] : NULL;
    //     }
        
    //     $parameter_array[] = $client_id;

    //     $sql = "UPDATE report2 set P1=?, P1_cur=?, P2=?, P2_cur=?, P3=?, P3_cur=?, P4=?, P4_cur=?, P5=?, P5_cur=?, P6=?, P6_cur=?, P7=?, P7_cur=?, P8=?, P8_cur=?, P9=?, P9_cur=?, P10=?, P10_cur=?, P11=?, P11_cur=?, P12=?, P12_cur=?, P13=?, P13_cur=?, P14=?, P14_cur=?, P15=?, P15_cur=?, P16=?, P16_cur=?, P17=?, P17_cur=?, P18=?, P18_cur=?, P19=?, P19_cur=?, P20=?, P20_cur=?, P21=?, P21_cur=?, P22=?, P22_cur=?, P23=?, P23_cur=?, P24=?, P24_cur=?, P25=?, P25_cur=?, P26=?, P26_cur=?, P27=?, P27_cur=?, P28=?, P28_cur=?, P29=?, P29_cur=?, P30=?, P30_cur=?, P31=?, P31_cur=?, P32=?, P32_cur=?, P33=?, P33_cur=?, P34=?, P34_cur=?, P35=?, P35_cur=? WHERE client_id=?";
    //     $__vartype = "ssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssi";
    //     if($stmt = mysqli_prepare($link, $sql)){
    //         mysqli_stmt_bind_param($stmt, $__vartype, ...$parameter_array);
    //         if(mysqli_stmt_execute($stmt)){
    //         } else{
    //             echo "ERROR <br>".$stmt->error . "<br>";
    //         }
    //     } else{
    //         echo "ERROR <br>".$stmt->error . "<br>";
    //     }

    //     $attendance_sql = "SELECT date FROM absence where excused <> '1' and client_id = ? ORDER BY date asc";
    //     $absent_dates = array();
    //     if($stmt = mysqli_prepare($link, $attendance_sql)){
    //         mysqli_stmt_bind_param($stmt, "i", $client_id);
    //         if(mysqli_stmt_execute($stmt)){
    //             $result = mysqli_stmt_get_result($stmt);
    //             if(mysqli_stmt_execute($stmt)){
    //                 $result = mysqli_stmt_get_result($stmt);
    //                 while($row = mysqli_fetch_array($result)){
    //                     $absent_dates[] = $row["date"];
    //                 }
    //             } else{
    //                 echo "ERROR <br>".$stmt->error . "<br>";
    //             }
    //         } else{
    //             echo "ERROR <br>".$stmt->error . "<br>";
    //         }
    //     }
    //     mysqli_stmt_close($stmt);

    //     $NUM_ABSENT = 28;
    //     // If client has more than 28 absences then take the most recent 28
    //     if(count($absent_dates) > $NUM_ABSENT) {
    //         $start_index = count($absent_dates) - $NUM_ABSENT;
    //         $absent_dates = array_slice($absent_dates, $start_index, $NUM_ABSENT);
    //     }
        
    //     $parameter_array = array();
    //     for($count = 0; $count < $NUM_ABSENT; $count++){
    //         // Null padd any blank columns
    //         $parameter_array[] = (count($absent_dates) > $count) ? $absent_dates[$count] : NULL;
    //     }
    //     $parameter_array[] = $client_id;

    //     $sql = "UPDATE report2 set A1=?, A2=?, A3=?, A4=?, A5=?, A6=?, A7=?, A8=?, A9=?, A10=?, A11=?, A12=?, A13=?, A14=?, A15=?, A16=?, A17=?, A18=?, A19=?, A20=?, A21=?, A22=?, A23=?, A24=?, A25=?, A26=?, A27=?, A28=? WHERE client_id=?";
    //     $__vartype = "ssssssssssssssssssssssssssssi";
    //     if($stmt = mysqli_prepare($link, $sql)){
    //         mysqli_stmt_bind_param($stmt, $__vartype, ...$parameter_array);
    //         if(mysqli_stmt_execute($stmt)){
    //         } else{
    //             echo "ERROR <br>".$stmt->error . "<br>";
    //         }
    //     } else{
    //         echo "ERROR <br>".$stmt->error . "<br>";
    //     }

    //     mysqli_stmt_close($stmt);
    // }


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Build Report 4</title>
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
<section class="pt-5">

<?php
    $start_time = microtime(true);

    prepare_table();
    populate_report4($include_exits);

    $client_count = 0;
    // Loop through and populate the attendance data for each clientID
    global $link;
    $sql = "SELECT client_id from report4";
    if($stmt = mysqli_prepare($link, $sql)){
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_array($result)){
                    $client_count++;
//                    populate_report2_client_attendance($row["client_id"]);
                }
            } else{
                echo "ERROR <br>".$stmt->error . "<br>";
            }
        } else{
            echo "ERROR <br>".$stmt->error . "<br>";
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
        $(document).ready(function(){
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>

