<?php
// Enable error reporting and logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/home/notesao/NotePro-Report-Generator/fetch_data_errors.log');
error_log("fetch_data.php script started.");
set_exception_handler(function($e) {
    error_log("Uncaught exception: " . $e->getMessage());
});


// Get the clinic folder from the request parameter
// Hardcode the clinic folder to 'safatherhood' for this instance
$clinic_folder = 'safatherhood';
error_log("Hardcoded clinic folder: " . $clinic_folder);

// Define the config path using the provided clinic folder
$configPath = "/home/notesao/safatherhood/config/config.php";

// Load config.php and check for $con (database connection)
if (file_exists($configPath)) {
    include_once $configPath;
    
    // Check if $con is set; if not, assign $link to $con
    if (!isset($con)) {
        // In your config.php, the connection is stored in $link
        if (isset($link)) {
            $con = $link;
            error_log("Database connection assigned from \$link to \$con.");
        } else {
            error_log("Database connection not established. Ensure \$con is set in config.php.");
            echo json_encode(["status" => "error", "message" => "Database connection missing."]);
            exit;
        }
    }
    error_log("Loaded config from $configPath");
} else {
    error_log("Config file not found for clinic: $clinic_folder at $configPath");
    echo json_encode(["status" => "error", "message" => "Clinic config file missing."]);
    exit;
}


// Include any required helper files
require_once "helpers.php";
require_once "sql_functions.php";

// Set include_exits to true by default
$include_exits = true;

try {
    // Step 1: Build Report Table 2
    error_log("Starting Build Report Table 2");
    prepare_report2_table($con);
    populate_report2($include_exits);

    // Populate attendance data for each clientID in report2
    $client_ids = [];
    $sql = "SELECT client_id FROM report2";
    if ($result = mysqli_query($con, $sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $client_ids[] = $row['client_id'];
        }
        foreach ($client_ids as $client_id) {
            populate_report2_client_attendance($client_id);
        }
        error_log("Populated attendance data for " . count($client_ids) . " clients.");
    } else {
        error_log("Error fetching client IDs from report2: " . mysqli_error($con));
    }

    // Preload all attendance and absence data to reduce repetitive queries
    $attendance_data = [];
    $absence_data = ['excused' => [], 'unexcused' => []];

    // Preload attendance data
    $attendance_sql = "
    SELECT
        ar.client_id,
        ts.date AS session_date
    FROM
        attendance_record ar
    LEFT JOIN therapy_session ts ON ar.therapy_session_id = ts.id
    ";
    if ($result = mysqli_query($con, $attendance_sql)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $attendance_data[$row['client_id']][] = $row['session_date'];
    }
    error_log("Attendance data preloaded for " . count($attendance_data) . " clients.");
    } else {
    error_log("Error preloading attendance data: " . mysqli_error($con));
    }

    // Preload absence data
    $absence_sql = "SELECT client_id, date, excused FROM absence";
    if ($result = mysqli_query($con, $absence_sql)) {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['excused'] == 1) {
                $absence_data['excused'][$row['client_id']][] = $row['date'];
            } else {
                $absence_data['unexcused'][$row['client_id']][] = $row['date'];
            }
        }
        error_log("Absence data preloaded for excused and unexcused absences.");
    } else {
        error_log("Error preloading absence data: " . mysqli_error($con));
    }

    // Step 2: Build Report Table 3
    error_log("Starting Build Report Table 3");
    prepare_report3_table($con);
    populate_report3($include_exits);

    // Fetch all clients in chunks
    $chunk_size = 50; // Adjust the chunk size as needed
    $sql = "SELECT client_id, orientation_date, exit_date FROM report3";
    if ($result = mysqli_query($con, $sql)) {
        $clients = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $clients[] = $row;
        }

        $chunks = array_chunk($clients, $chunk_size);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $row) {
                $client_id = $row["client_id"];
                error_log("Processing client ID: $client_id");
                
                // Add try-catch for orientation_date
                try {
                    $orientation_date = $row["orientation_date"] ? new DateTime($row["orientation_date"]) : new DateTime('today');
                } catch (Exception $e) {
                    error_log("Error parsing orientation_date for client_id: $client_id. Exception: " . $e->getMessage());
                    continue; // Skip this client and move to the next one
                }

                // Add try-catch for exit_date
                try {
                    $exit_date = $row["exit_date"] ? new DateTime($row["exit_date"]) : new DateTime('today');
                } catch (Exception $e) {
                    error_log("Error parsing exit_date for client_id: $client_id. Exception: " . $e->getMessage());
                    continue; // Skip this client and move to the next one
                }

                // Process the client calendar (existing logic here)
                $date = clone $orientation_date;
                $interval = $exit_date->diff($date);
                $months = $interval->m + ($interval->y * 12);
                while ($months > 3) {
                    $date->modify('first day of next month');
                    $interval = $exit_date->diff($date);
                    $months = $interval->m + ($interval->y * 12);
                }

                $attendance = $attendance_data[$client_id] ?? [];
                $excused = $absence_data['excused'][$client_id] ?? [];
                $unexcused = $absence_data['unexcused'][$client_id] ?? [];
                
                // Build calendars
                $prefixes = ['c1', 'c2', 'c3', 'c4'];
                foreach ($prefixes as $prefix) {
                    if ($date <= $exit_date) {
                        error_log("Building calendar for client_id: $client_id, month: " . $date->format('Y-m'));
                        buildCalendar($client_id, $date->getTimestamp(), $attendance, $excused, $unexcused, $prefix);
                        $date->modify('first day of next month');
                    } else {
                        break;
                    }
                }
            }
            error_log("Processed a chunk of $chunk_size clients.");
        }
    } else {
        error_log("Error fetching clients for calendar processing: " . mysqli_error($con));
    }

    // Step 3: Dump Report 5 CSV
    error_log("Starting Dump Report 5 CSV");
    dump_report5_csv($con);

    // Return a success message
    header('Content-Type: application/json');
    echo json_encode(["status" => "success", "message" => "Data fetching completed successfully."]);

} catch (Exception $e) {
    error_log("Error during Report Table preparation: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Error during report preparation."]);
}
exit;

// Function to prepare report2 table
function prepare_report2_table($con) {
    // Drop existing report2 table if it exists
    $drop_sql = "DROP TABLE IF EXISTS report2";
    if ($stmt = mysqli_prepare($con, $drop_sql)) {
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        error_log("Report2 table dropped successfully.");
    } else {
        error_log("Error dropping Report2 table: " . mysqli_error($con));
    }

    // Define table structure for report2
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
            behavior_contract_status varchar(45) DEFAULT NULL,
            behavior_contract_signed_date DATE DEFAULT NULL,
            birth_place varchar(128) DEFAULT NULL,
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

    // Execute the table creation SQL
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        error_log("Report2 table created successfully.");
    } else {
        error_log("Error creating Report2 table: " . mysqli_error($con));
    }
}

// Function to populate report2 table
function populate_report2($include_exits) {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')   
        $url = "https://";   
    else  
        $url = "https://";   
    $url.= $_SERVER['HTTP_HOST'];   
    $url.= $_SERVER['REQUEST_URI'];
    $url_array = explode('/', $url);
    array_pop($url_array);
    $url = implode('/', $url_array);         

    global $con;
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
            required_sessions,
            cause_number,
            referral_type,
            case_manager_first_name,
            case_manager_last_name,
            case_manager_email,
            case_manager_phone,
            case_manager_fax,
            case_manager_office,
            group_name,
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
            last_absence,
            behavior_contract_status,
            behavior_contract_signed_date,
            birth_place
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
            c.required_sessions required_sessions,
            c.cause_number cause_number,
            rt.referral_type referral_type,
            m.first_name case_manager_first_name,
            m.last_name case_manager_last_name,
            m.email case_manager_email,
            m.phone_number case_manager_phone,
            m.fax case_manager_fax,
            m.office case_manager_office,
            tg.name group_name,
            c.fee fee,
            coalesce(client_ledger.balance, '0') balance,
            coalesce(client_attendance.sessions_attended,'0') attended,
            coalesce(absence_excused.count,'0') absence_excused,
            coalesce(absence_unexcused.count,'0') absence_unexcused,
            stage.stage client_stage,
            c.note client_note,
            CASE WHEN speaksSignificantlyInGroup = 0 THEN 'N' ELSE 'Y' END AS speaks_significantly_in_group,
            CASE WHEN respectfulTowardsGroup = 0 THEN 'N' ELSE 'Y' END AS respectful_to_group,
            CASE WHEN takesResponsibilityForPastBehavior = 0 THEN 'N' ELSE 'Y' END AS takes_responsibility_for_past,
            CASE WHEN disruptiveOrArgumentitive = 1 THEN 'Y' ELSE 'N' END AS disruptive_argumentitive,
            CASE WHEN inappropriateHumor = 1 THEN 'Y' ELSE 'N' END AS humor_inappropriate,
            CASE WHEN blamesVictim = 1 THEN 'Y' ELSE 'N' END AS blames_victim,
            CASE WHEN drug_alcohol = 1 THEN 'Y' ELSE 'N' END AS appears_drug_alcohol,
            CASE WHEN inappropriate_behavior_to_staff = 1 THEN 'Y' ELSE 'N' END AS inappropriate_to_staff,
            c.other_concerns,
            c.orientation_date orientation_date,
            c.exit_date exit_date,
            er.reason exit_reason,
            c.exit_note exit_note,
            client_last_attendance.last_seen last_attended,
            client_last_absence.last_absence last_absence,
            c.behavior_contract_status behavior_contract_status,
            c.behavior_contract_signed_date behavior_contract_signed_date,
            c.birth_place birth_place
        FROM client c
        LEFT JOIN program p ON c.program_id = p.id
        LEFT OUTER JOIN ethnicity e ON c.ethnicity_id = e.id
        LEFT OUTER JOIN image i ON c.id = i.id    
        LEFT OUTER JOIN case_manager m ON c.case_manager_id = m.id
        LEFT OUTER JOIN referral_type rt ON c.referral_type_id = rt.id
        LEFT OUTER JOIN exit_reason er ON c.exit_reason_id = er.id
        LEFT OUTER JOIN therapy_group tg ON c.therapy_group_id = tg.id
        LEFT JOIN gender g ON c.gender_id = g.id
        LEFT JOIN client_stage stage ON c.client_stage_id = stage.id
        LEFT JOIN (SELECT ar.client_id client_id, COUNT(ar.client_id) sessions_attended FROM attendance_record ar GROUP BY ar.client_id) AS client_attendance ON c.id = client_attendance.client_id
        LEFT OUTER JOIN (SELECT ar.client_id client_id, MAX(ts.date) last_seen FROM attendance_record ar LEFT JOIN therapy_session ts ON ar.therapy_session_id = ts.id GROUP BY ar.client_id) AS client_last_attendance ON c.id = client_last_attendance.client_id
        LEFT OUTER JOIN (SELECT client_id client_id, MAX(date) last_absence FROM absence GROUP BY client_id) AS client_last_absence ON c.id = client_last_absence.client_id
        LEFT JOIN (SELECT client_id, COUNT(id) count FROM absence a WHERE excused <> 1 GROUP BY a.client_id) AS absence_unexcused ON c.id = absence_unexcused.client_id
        LEFT JOIN (SELECT client_id, COUNT(id) count FROM absence a WHERE excused = 1 GROUP BY a.client_id) AS absence_excused ON c.id = absence_excused.client_id
        LEFT JOIN (SELECT l.client_id client_id, SUM(l.amount) balance FROM ledger l GROUP BY l.client_id) AS client_ledger ON c.id = client_ledger.client_id
    ";
    // Since include_exits is set to true by default, we include all clients
    $sql .= ";";

    if ($stmt = mysqli_prepare($con, $sql)) {
        if (mysqli_stmt_execute($stmt)) {
            // Success
            error_log("Report2 data populated successfully.");
        } else {
            error_log("Error populating Report2: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Error preparing statement for Report2 population: " . mysqli_error($con));
    }
}

// Function to populate attendance data for a client in report2
function populate_report2_client_attendance($client_id) {
    global $con;

    $present_dates = array();
    $present_cur = array();

    $attendance_sql = "
        SELECT
            ar.client_id client_id,
            ts.id session_id,
            ts.date session_date,
            cur.short_description curriculum
        FROM client c
        LEFT JOIN attendance_record ar ON c.id = ar.client_id
        LEFT JOIN therapy_session ts ON ar.therapy_session_id = ts.id
        LEFT OUTER JOIN curriculum cur ON ts.curriculum_id = cur.id
        WHERE ar.client_id = ?
        ORDER BY session_date ASC
    ";
    if($stmt = mysqli_prepare($con, $attendance_sql)){
        mysqli_stmt_bind_param($stmt, "i", $client_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            while($row = mysqli_fetch_array($result)){
                $present_dates[] = $row["session_date"];
                $present_cur[] = $row["curriculum"];
            }
        } else{
            error_log("Error executing attendance SQL: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Error preparing attendance SQL: " . mysqli_error($con));
    }

    $parameter_array = array();

    $NUM_PRESENT = 35;
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

    $sql = "UPDATE report2 SET P1=?, P1_cur=?, P2=?, P2_cur=?, P3=?, P3_cur=?, P4=?, P4_cur=?, P5=?, P5_cur=?, P6=?, P6_cur=?, P7=?, P7_cur=?, P8=?, P8_cur=?, P9=?, P9_cur=?, P10=?, P10_cur=?, P11=?, P11_cur=?, P12=?, P12_cur=?, P13=?, P13_cur=?, P14=?, P14_cur=?, P15=?, P15_cur=?, P16=?, P16_cur=?, P17=?, P17_cur=?, P18=?, P18_cur=?, P19=?, P19_cur=?, P20=?, P20_cur=?, P21=?, P21_cur=?, P22=?, P22_cur=?, P23=?, P23_cur=?, P24=?, P24_cur=?, P25=?, P25_cur=?, P26=?, P26_cur=?, P27=?, P27_cur=?, P28=?, P28_cur=?, P29=?, P29_cur=?, P30=?, P30_cur=?, P31=?, P31_cur=?, P32=?, P32_cur=?, P33=?, P33_cur=?, P34=?, P34_cur=?, P35=?, P35_cur=? WHERE client_id=?";
    $__vartype = str_repeat('s', 70) . 'i';
    if($stmt = mysqli_prepare($con, $sql)){
        mysqli_stmt_bind_param($stmt, $__vartype, ...$parameter_array);
        if(mysqli_stmt_execute($stmt)){
            // Success
        } else{
            error_log("Error updating attendance data for client $client_id: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Error preparing attendance update SQL for client $client_id: " . mysqli_error($con));
    }

    // Update absence data
    $absent_dates = array();
    $absence_sql = "SELECT date FROM absence WHERE excused <> '1' AND client_id = ? ORDER BY date ASC";
    if($stmt = mysqli_prepare($con, $absence_sql)){
        mysqli_stmt_bind_param($stmt, "i", $client_id);
        if(mysqli_stmt_execute($stmt)){
            $result = mysqli_stmt_get_result($stmt);
            while($row = mysqli_fetch_array($result)){
                $absent_dates[] = $row["date"];
            }
        } else{
            error_log("Error executing absence SQL: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Error preparing absence SQL: " . mysqli_error($con));
    }

    $NUM_ABSENT = 28;
    if(count($absent_dates) > $NUM_ABSENT) {
        $start_index = count($absent_dates) - $NUM_ABSENT;
        $absent_dates = array_slice($absent_dates, $start_index, $NUM_ABSENT);
    }

    $parameter_array = array();
    for($count = 0; $count < $NUM_ABSENT; $count++){
        $parameter_array[] = (count($absent_dates) > $count) ? $absent_dates[$count] : NULL;
    }
    $parameter_array[] = $client_id;

    $sql = "UPDATE report2 SET A1=?, A2=?, A3=?, A4=?, A5=?, A6=?, A7=?, A8=?, A9=?, A10=?, A11=?, A12=?, A13=?, A14=?, A15=?, A16=?, A17=?, A18=?, A19=?, A20=?, A21=?, A22=?, A23=?, A24=?, A25=?, A26=?, A27=?, A28=? WHERE client_id=?";
    $__vartype = str_repeat('s', 28) . 'i';
    if($stmt = mysqli_prepare($con, $sql)){
        mysqli_stmt_bind_param($stmt, $__vartype, ...$parameter_array);
        if(mysqli_stmt_execute($stmt)){
            // Success
        } else{
            error_log("Error updating absence data for client $client_id: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Error preparing absence update SQL for client $client_id: " . mysqli_error($con));
    }
}

// Function to prepare report3 table
function prepare_report3_table($con) {
    // Drop existing report3 table if it exists
    $drop_sql = "DROP TABLE IF EXISTS report3";
    if ($stmt = mysqli_prepare($con, $drop_sql)) {
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        error_log("Report3 table dropped successfully.");
    } else {
        error_log("Error dropping Report3 table: " . mysqli_error($con));
    }

    // Define table structure for report3
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

    // Execute the table creation SQL
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        error_log("Report3 table created successfully.");
    } else {
        error_log("Error creating Report3 table: " . mysqli_error($con));
    }
}

// Function to populate report3 table
function populate_report3($include_exits) {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        $url = "https://";
    else
        $url = "http://";
    $url .= $_SERVER['HTTP_HOST'];
    $url .= $_SERVER['REQUEST_URI'];
    $url_array = explode('/', $url);
    array_pop($url_array);
    $url = implode('/', $url_array);

    global $con;
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
            required_sessions,
            cause_number,
            referral_type,
            case_manager_first_name,
            case_manager_last_name,
            case_manager_email,
            case_manager_office,
            group_name,
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
            c.required_sessions required_sessions,
            c.cause_number cause_number,
            rt.referral_type referral_type,
            m.first_name case_manager_first_name,
            m.last_name case_manager_last_name,
            m.email case_manager_email,
            m.office case_manager_office,
            tg.name group_name,
            c.fee fee,
            coalesce(client_ledger.balance, '0') balance,
            coalesce(client_attendance.sessions_attended,'0') attended,
            coalesce(absence_excused.count,'0') absence_excused,
            coalesce(absence_unexcused.count,'0') absence_unexcused,
            stage.stage client_stage,
            c.note client_note,
            CASE WHEN speaksSignificantlyInGroup = 0 THEN 'N' ELSE 'Y' END AS speaks_significantly_in_group,
            CASE WHEN respectfulTowardsGroup = 0 THEN 'N' ELSE 'Y' END AS respectful_to_group,
            CASE WHEN takesResponsibilityForPastBehavior = 0 THEN 'N' ELSE 'Y' END AS takes_responsibility_for_past,
            CASE WHEN disruptiveOrArgumentitive = 1 THEN 'Y' ELSE 'N' END AS disruptive_argumentitive,
            CASE WHEN inappropriateHumor = 1 THEN 'Y' ELSE 'N' END AS humor_inappropriate,
            CASE WHEN blamesVictim = 1 THEN 'Y' ELSE 'N' END AS blames_victim,
            CASE WHEN drug_alcohol = 1 THEN 'Y' ELSE 'N' END AS appears_drug_alcohol,
            CASE WHEN inappropriate_behavior_to_staff = 1 THEN 'Y' ELSE 'N' END AS inappropriate_to_staff,
            c.other_concerns,
            c.orientation_date orientation_date,
            c.exit_date exit_date,
            er.reason exit_reason,
            c.exit_note exit_note,
            client_last_attendance.last_seen last_attended,
            client_last_absence.last_absence last_absence
        FROM client c
        LEFT JOIN program p ON c.program_id = p.id
        LEFT OUTER JOIN ethnicity e ON c.ethnicity_id = e.id
        LEFT OUTER JOIN image i ON c.id = i.id    
        LEFT OUTER JOIN case_manager m ON c.case_manager_id = m.id
        LEFT OUTER JOIN referral_type rt ON c.referral_type_id = rt.id
        LEFT OUTER JOIN exit_reason er ON c.exit_reason_id = er.id
        LEFT OUTER JOIN therapy_group tg ON c.therapy_group_id = tg.id
        LEFT JOIN gender g ON c.gender_id = g.id
        LEFT JOIN client_stage stage ON c.client_stage_id = stage.id
        LEFT JOIN (SELECT ar.client_id client_id, COUNT(ar.client_id) sessions_attended FROM attendance_record ar GROUP BY ar.client_id) AS client_attendance ON c.id = client_attendance.client_id
        LEFT OUTER JOIN (SELECT ar.client_id client_id, MAX(ts.date) last_seen FROM attendance_record ar LEFT JOIN therapy_session ts ON ar.therapy_session_id = ts.id GROUP BY ar.client_id) AS client_last_attendance ON c.id = client_last_attendance.client_id
        LEFT JOIN (SELECT client_id, COUNT(id) count FROM absence a WHERE excused <> 1 GROUP BY a.client_id) AS absence_unexcused ON c.id = absence_unexcused.client_id
        LEFT JOIN (SELECT client_id, MAX(date) last_absence FROM absence a WHERE excused <> 1 GROUP BY a.client_id) AS client_last_absence ON c.id = client_last_absence.client_id
        LEFT JOIN (SELECT client_id, COUNT(id) count FROM absence a WHERE excused = 1 GROUP BY a.client_id) AS absence_excused ON c.id = absence_excused.client_id
        LEFT JOIN (SELECT l.client_id client_id, SUM(l.amount) balance FROM ledger l GROUP BY l.client_id) AS client_ledger ON c.id = client_ledger.client_id
    ";
    // Since include_exits is set to true by default, we include all clients
    $sql .= ";";

    if ($stmt = mysqli_prepare($con, $sql)) {
        if (mysqli_stmt_execute($stmt)) {
            // Success
            error_log("Report3 data populated successfully.");
        } else {
            error_log("Error populating Report3: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Error preparing statement for Report3 population: " . mysqli_error($con));
    }
}

// Function to build calendar for a client
function buildCalendar($client_id, $date, $attendance, $excused, $unexcused, $prefix) {
    error_log("Started building calendar for client_id: $client_id, date: " . date('Y-m-d', $date));

    $calendar_values = array();

    $today = new DateTime('today');
    $day = date('d', $date);
    $month = date('m', $date);
    $year = date('Y', $date);

    // Generate the first day of the month 
    $first_day = mktime(0, 0, 0, $month, 1, $year);
    $curDay = new DateTime();
    $curDay->setTimestamp($first_day);

    // Get the month name 
    $title = date('F', $first_day);

    // Find out what day of the week the first day of the month falls on 
    $day_of_week = date('D', $first_day);

    // Determine how many blank days occur before it
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

    // Determine how many days are in the current month
    $days_in_month = cal_days_in_month(0, $month, $year);

    $calendar_values[] = $title . $year;

    // Add blank days
    while ($blank > 0) {
        $calendar_values[] = NULL;
        $blank = $blank - 1;
    }

    $day_num = 1;

    // Count up the days, until we've done all of them in the month
    while ($day_num <= $days_in_month) {
        $cell_string = $day_num;

        $count = arrayCount($curDay->format("Y-m-d"), $attendance);
        if ($count >= 2) {
            $cell_string .= " ✅✅"; // Ensure two checkmarks if two attendances exist
        } else if ($count == 1) {
            $cell_string .= " ✅";
        }

        $count = arrayCount($curDay->format("Y-m-d"), $excused);
        for ($i = 0; $i < $count; $i++) {
            $cell_string .= "✖"; // Excused absence
        }

        $count = arrayCount($curDay->format("Y-m-d"), $unexcused);
        for ($i = 0; $i < $count; $i++) {
            $cell_string .= "❌"; // Unexcused absence
        }

        error_log("Original cell_string for client_id=$client_id, day_num=$day_num: $cell_string");

        $cell_string = str_replace(["✅", "âœ…"], "P", $cell_string);
        $cell_string = str_replace(["❌", "âŒ"], "X", $cell_string);
        $cell_string = str_replace(["✖", "âœ–"], "E", $cell_string);

        error_log("Sanitized cell_string for client_id=$client_id, day_num=$day_num: $cell_string");

        $calendar_values[] = $cell_string;
        $day_num++;
        $curDay->modify('+1 day');
    }

    populate_report3_client_calendar($client_id, $calendar_values, $prefix);
    error_log("Finished building calendar for client_id: $client_id, prefix: $prefix");
}

// Function to populate calendar data for a client in report3
function populate_report3_client_calendar($client_id, $calendar_values, $prefix = NULL) {
    global $con;

    $parameter_array = array();
    $NUM_VALUES = 43;
    for ($count = 0; $count < $NUM_VALUES; $count++) {
        $parameter_array[] = (count($calendar_values) > $count) ? $calendar_values[$count] : NULL;
    }
    $parameter_array[] = $client_id;

    $sql = "UPDATE report3 SET c1_header=?,
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

    $__vartype = str_repeat('s', 43) . 'i';
    if ($stmt = mysqli_prepare($con, $sql)) {
        mysqli_stmt_bind_param($stmt, $__vartype, ...$parameter_array);
        if (mysqli_stmt_execute($stmt)) {
            // Success
        } else {
            error_log("Error updating calendar data for client $client_id: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("Error preparing calendar update SQL for client $client_id: " . mysqli_error($con));
    }
}

// Function to dump report5 CSV
function dump_report5_csv($con) {
    $directory = "/home/notesao/NotePro-Report-Generator/csv/safatherhood/";
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0775, true)) {
            error_log("Failed to create directory: $directory");
            return;
        }
    }

    if (!is_writable($directory)) {
        error_log("Directory is not writable: $directory");
        return;
    }

    $filename = $directory . "report5_dump_" . date("Ymd") . ".csv";
    $sql = "SELECT * FROM report2 JOIN report3 ON report2.client_id = report3.client_id;";
    $firstRow = true;

    $file = fopen($filename, 'wb');
    if (!$file) {
        error_log("Failed to open file for writing: $filename");
        return;
    }

    // Write BOM for UTF-8
    fputs($file, "\xEF\xBB\xBF");

    if ($stmt = mysqli_prepare($con, $sql)) {
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $firstRow = true;

            while ($row = mysqli_fetch_assoc($result)) {
                if ($firstRow) {
                    fputcsv($file, array_keys($row));
                    $firstRow = false;
                }

                foreach ($row as $key => $value) {
                    if (is_string($value)) {
                        $value = str_replace(
                            ['âœ…', 'âŒ', 'âœ–', 'â–'],
                            ['✓', '✗', '✖', '✕'],
                            $value
                        );
                        $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                    }
                    $row[$key] = $value;
                }

                fputcsv($file, $row);
            }

            error_log("CSV written successfully to $filename");
        } else {
            error_log("SQL Execution Error: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        error_log("SQL Preparation Error: " . mysqli_error($con));
    }

    fclose($file);

}

?>