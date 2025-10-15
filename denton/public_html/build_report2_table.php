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
        $sql = "DROP TABLE report2";
        try {
            if($stmt = mysqli_prepare($link, $sql)){
                mysqli_stmt_execute($stmt);
            }
        }
        catch (Exception $e) {
            // Ignore errors dropping table
        }


        $sql = "
        CREATE TABLE report2 (
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
            case_manager_phone varchar(45) DEFAULT NULL,
            case_manager_fax varchar(45) DEFAULT NULL,
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
            last_attended date DEFAULT NULL,
            last_absence date DEFAULT NULL,
            P1 date DEFAULT NULL,
            P1_cur varchar(64) DEFAULT NULL,
            P2 date DEFAULT NULL,
            P2_cur varchar(64) DEFAULT NULL,
            P3 date DEFAULT NULL,
            P3_cur varchar(64) DEFAULT NULL,
            P4 date DEFAULT NULL,
            P4_cur varchar(64) DEFAULT NULL,
            P5 date DEFAULT NULL,
            P5_cur varchar(64) DEFAULT NULL,
            P6 date DEFAULT NULL,
            P6_cur varchar(64) DEFAULT NULL,
            P7 date DEFAULT NULL,
            P7_cur varchar(64) DEFAULT NULL,
            P8 date DEFAULT NULL,
            P8_cur varchar(64) DEFAULT NULL,
            P9 date DEFAULT NULL,
            P9_cur varchar(64) DEFAULT NULL,
            P10 date DEFAULT NULL,
            P10_cur varchar(64) DEFAULT NULL,
            P11 date DEFAULT NULL,
            P11_cur varchar(64) DEFAULT NULL,
            P12 date DEFAULT NULL,
            P12_cur varchar(64) DEFAULT NULL,
            P13 date DEFAULT NULL,
            P13_cur varchar(64) DEFAULT NULL,
            P14 date DEFAULT NULL,
            P14_cur varchar(64) DEFAULT NULL,
            P15 date DEFAULT NULL,
            P15_cur varchar(64) DEFAULT NULL,
            P16 date DEFAULT NULL,
            P16_cur varchar(64) DEFAULT NULL,
            P17 date DEFAULT NULL,
            P17_cur varchar(64) DEFAULT NULL,
            P18 date DEFAULT NULL,
            P18_cur varchar(64) DEFAULT NULL,
            P19 date DEFAULT NULL,
            P19_cur varchar(64) DEFAULT NULL,
            P20 date DEFAULT NULL,
            P20_cur varchar(64) DEFAULT NULL,
            P21 date DEFAULT NULL,
            P21_cur varchar(64) DEFAULT NULL,
            P22 date DEFAULT NULL,
            P22_cur varchar(64) DEFAULT NULL,
            P23 date DEFAULT NULL,
            P23_cur varchar(64) DEFAULT NULL,
            P24 date DEFAULT NULL,
            P24_cur varchar(64) DEFAULT NULL,
            P25 date DEFAULT NULL,
            P25_cur varchar(64) DEFAULT NULL,
            P26 date DEFAULT NULL,
            P26_cur varchar(64) DEFAULT NULL,
            P27 date DEFAULT NULL,
            P27_cur varchar(64) DEFAULT NULL,
            P28 date DEFAULT NULL,
            P28_cur varchar(64) DEFAULT NULL,
            P29 date DEFAULT NULL,
            P29_cur varchar(64) DEFAULT NULL,
            P30 date DEFAULT NULL,
            P30_cur varchar(64) DEFAULT NULL,
            P31 date DEFAULT NULL,
            P31_cur varchar(64) DEFAULT NULL,
            P32 date DEFAULT NULL,
            P32_cur varchar(64) DEFAULT NULL,
            P33 date DEFAULT NULL,
            P33_cur varchar(64) DEFAULT NULL,
            P34 date DEFAULT NULL,
            P34_cur varchar(64) DEFAULT NULL,
            P35 date DEFAULT NULL,
            P35_cur varchar(64) DEFAULT NULL,
            A1 date DEFAULT NULL,
            A2 date DEFAULT NULL,
            A3 date DEFAULT NULL,
            A4 date DEFAULT NULL,
            A5 date DEFAULT NULL,
            A6 date DEFAULT NULL,
            A7 date DEFAULT NULL,
            A8 date DEFAULT NULL,
            A9 date DEFAULT NULL,
            A10 date DEFAULT NULL,
            A11 date DEFAULT NULL,
            A12 date DEFAULT NULL,
            A13 date DEFAULT NULL,
            A14 date DEFAULT NULL,
            A15 date DEFAULT NULL,
            A16 date DEFAULT NULL,
            A17 date DEFAULT NULL,
            A18 date DEFAULT NULL,
            A19 date DEFAULT NULL,
            A20 date DEFAULT NULL,
            A21 date DEFAULT NULL,
            A22 date DEFAULT NULL,
            A23 date DEFAULT NULL,
            A24 date DEFAULT NULL,
            A25 date DEFAULT NULL,
            A26 date DEFAULT NULL,
            A27 date DEFAULT NULL,
            A28 date DEFAULT NULL
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

    function populate_report2($include_exits) {
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')   
            $url = "https://";   
        else  
            $url = "http://";   
        $url.= $_SERVER['HTTP_HOST'];   
        $url.= $_SERVER['REQUEST_URI'];
        $url_array = explode('/', $url);
        array_pop($url_array);
        $url = implode('/', $url_array);         

        global $link;
        $resultarray = null;
        $sql = "
            INSERT INTO report2
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
        case_manager_phone,
        case_manager_fax,
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
                coalesce(concat('" . $url ."/getImageKey.php?id=', c.id, '&key=', i.hash),concat('" . $url ."', '/img/male-placeholder.jpg')),
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
                m.phone_number po_phone,
                m.fax po_fax,                                
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


    function populate_report2_client_attendance($client_id) {
        global $link;

        $present_dates = array();
        $present_cur = array();
        
        $attendance_sql = "
                SELECT
                ar.client_id client_id,
                ts.id session_id,
                ts.date session_date,
                cur.short_description curriculum
            FROM
                client c
            LEFT join attendance_record ar ON
                c.id = ar.client_id
            LEFT JOIN therapy_session ts ON
                ar.therapy_session_id = ts.id
            LEFT OUTER JOIN curriculum cur ON
                ts.curriculum_id = cur.id
            WHERE
                ar.client_id = ?
            ORDER BY session_date asc
        ";
        if($stmt = mysqli_prepare($link, $attendance_sql)){
            mysqli_stmt_bind_param($stmt, "i", $client_id);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    while($row = mysqli_fetch_array($result)){
                        $present_dates[] = $row["session_date"];
                        $present_cur[] = $row["curriculum"];
                    }
                } else{
                    echo "ERROR <br>".$stmt->error . "<br>";
                }
            } else{
                echo "ERROR <br>".$stmt->error . "<br>";
            }
        }
        mysqli_stmt_close($stmt);

        $parameter_array = array();

        $NUM_PRESENT = 35;
        // If client has more than 28 absences then take the most recent 28
        if(count($present_dates) > $NUM_PRESENT) {
            $start_index = count($present_dates) - $NUM_PRESENT;
            $present_dates = array_slice($present_dates, $start_index, $NUM_PRESENT);
            $present_cur = array_slice($present_cur, $start_index, $NUM_PRESENT);
        }

        for($count = 0; $count < $NUM_PRESENT; $count++){
            $parameter_array[] = (count($present_dates) > $count) ? $present_dates[$count] : NULL;
            $parameter_array[] = (count($present_cur) > $count) ? $present_cur[$count] : NULL;
        }
        
        $parameter_array[] = $client_id;

        $sql = "UPDATE report2 set P1=?, P1_cur=?, P2=?, P2_cur=?, P3=?, P3_cur=?, P4=?, P4_cur=?, P5=?, P5_cur=?, P6=?, P6_cur=?, P7=?, P7_cur=?, P8=?, P8_cur=?, P9=?, P9_cur=?, P10=?, P10_cur=?, P11=?, P11_cur=?, P12=?, P12_cur=?, P13=?, P13_cur=?, P14=?, P14_cur=?, P15=?, P15_cur=?, P16=?, P16_cur=?, P17=?, P17_cur=?, P18=?, P18_cur=?, P19=?, P19_cur=?, P20=?, P20_cur=?, P21=?, P21_cur=?, P22=?, P22_cur=?, P23=?, P23_cur=?, P24=?, P24_cur=?, P25=?, P25_cur=?, P26=?, P26_cur=?, P27=?, P27_cur=?, P28=?, P28_cur=?, P29=?, P29_cur=?, P30=?, P30_cur=?, P31=?, P31_cur=?, P32=?, P32_cur=?, P33=?, P33_cur=?, P34=?, P34_cur=?, P35=?, P35_cur=? WHERE client_id=?";
        $__vartype = "ssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssi";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, $__vartype, ...$parameter_array);
            if(mysqli_stmt_execute($stmt)){
            } else{
                echo "ERROR <br>".$stmt->error . "<br>";
            }
        } else{
            echo "ERROR <br>".$stmt->error . "<br>";
        }

        $attendance_sql = "SELECT date FROM absence where excused <> '1' and client_id = ? ORDER BY date asc";
        $absent_dates = array();
        if($stmt = mysqli_prepare($link, $attendance_sql)){
            mysqli_stmt_bind_param($stmt, "i", $client_id);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                if(mysqli_stmt_execute($stmt)){
                    $result = mysqli_stmt_get_result($stmt);
                    while($row = mysqli_fetch_array($result)){
                        $absent_dates[] = $row["date"];
                    }
                } else{
                    echo "ERROR <br>".$stmt->error . "<br>";
                }
            } else{
                echo "ERROR <br>".$stmt->error . "<br>";
            }
        }
        mysqli_stmt_close($stmt);

        $NUM_ABSENT = 28;
        // If client has more than 28 absences then take the most recent 28
        if(count($absent_dates) > $NUM_ABSENT) {
            $start_index = count($absent_dates) - $NUM_ABSENT;
            $absent_dates = array_slice($absent_dates, $start_index, $NUM_ABSENT);
        }
        
        $parameter_array = array();
        for($count = 0; $count < $NUM_ABSENT; $count++){
            // Null padd any blank columns
            $parameter_array[] = (count($absent_dates) > $count) ? $absent_dates[$count] : NULL;
        }
        $parameter_array[] = $client_id;

        $sql = "UPDATE report2 set A1=?, A2=?, A3=?, A4=?, A5=?, A6=?, A7=?, A8=?, A9=?, A10=?, A11=?, A12=?, A13=?, A14=?, A15=?, A16=?, A17=?, A18=?, A19=?, A20=?, A21=?, A22=?, A23=?, A24=?, A25=?, A26=?, A27=?, A28=? WHERE client_id=?";
        $__vartype = "ssssssssssssssssssssssssssssi";
        if($stmt = mysqli_prepare($link, $sql)){
            mysqli_stmt_bind_param($stmt, $__vartype, ...$parameter_array);
            if(mysqli_stmt_execute($stmt)){
            } else{
                echo "ERROR <br>".$stmt->error . "<br>";
            }
        } else{
            echo "ERROR <br>".$stmt->error . "<br>";
        }

        mysqli_stmt_close($stmt);
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
    populate_report2($include_exits);

    $client_count = 0;
    // Loop through and populate the attendance data for each clientID
    global $link;
    $sql = "SELECT client_id from report2";
    if($stmt = mysqli_prepare($link, $sql)){
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);
                while($row = mysqli_fetch_array($result)){
                    $client_count++;
                    populate_report2_client_attendance($row["client_id"]);
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

