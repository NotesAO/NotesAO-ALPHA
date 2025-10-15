<?php
    include_once '../auth.php';
    check_loggedin($con);
    include_once '../helpers.php';
    
    // Get the account info using the logged-in session ID
    $stmt = $con->prepare('SELECT password, email, role, username FROM accounts WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['id']);
    $stmt->execute();
    $stmt->bind_result($password, $email, $role, $username);
    $stmt->fetch();
    $stmt->close();
    if ($role != 'Admin') {
        exit('You do not have permission to access this page!');
    }
    function stringClean($value) {
        $value = str_replace(' ', '', $value);  // Remove spaces
        $value = preg_replace('/[^A-Za-z0-9\-]/', '', $value); // Removes special chars.
        return $value;
    }
    
    $file = getParam('file', 'attendance');
    $start_date = getParam('start_date', date("Y-m-d", mktime(0, 0, 0, date("m"), 1, date("Y"))));
    $end_date = getParam('end_date', date("Y-m-d", mktime(0, 0, 0, date("m") + 1, 0)));

    $filename = "filename";
    if(isset($appname)){
        $filename = stringClean($appname);  // appname = clinic
    }
    $filename = $filename . "_" . $file;
    $filename = $filename . "_" . date("Ymd");
    $filename = $filename . ".csv";


    $attendance_query =
    "select program.name program, therapy_group.name therapy_group, therapy_session.date session_date, 
        DATE_FORMAT(therapy_session.date, '%Y') year, 
        DATE_FORMAT(therapy_session.date, '%m %b') month_of_year,
        YEARWEEK(therapy_session.date) yearweek,
        DATE_FORMAT(therapy_session.date, '%w %a') day_of_week, 
        attendance_counts.present present
    from 
        therapy_session
        left join therapy_group on therapy_session.therapy_group_id = therapy_group.id
        left join program on therapy_group.program_id = program.id
        JOIN (select therapy_session_id, count(*) present from attendance_record group by therapy_session_id) attendance_counts on therapy_session.id = attendance_counts.therapy_session_id
    WHERE
    therapy_session.date between ? and ? order by therapy_session.date DESC, program, therapy_group";

    $clients_query = "";
    $revenue_query = "";

    function runDataQuery($start_date, $end_date, $data_query)
    {
        global $link;

        if ($stmt = mysqli_prepare($link, $data_query)) {
            $st = $start_date . " 00:00:00";
            $ed = $end_date . " 23:59:59";
            mysqli_stmt_bind_param($stmt, "ss", $st , $ed);
        }
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $resultarray = $result->fetch_all(MYSQLI_ASSOC);
        }
        mysqli_stmt_close($stmt);
        return $resultarray;
    }

    $query = $attendance_query;
    if("clients" == $file) {
        $query = $clients_query;
    }
    else if ("revenue" == $file) {
        $query = $revenue_query;
    }


    header( 'Content-Type: text/csv' );
    header( "Content-Disposition: attachment;filename=" . $filename );
    $out = fopen('php://output', 'w');

    $firstRow = true;
    $resultArray = runDataQuery($start_date, $end_date, $query);
    foreach ($resultArray as $resultrow) {
        if($firstRow) {
            $firstRow = false;
            $headers = array_keys($resultrow);
            fputcsv($out, $headers);
        }
        fputcsv($out, $resultrow);
    }
    fclose($out);
?>

