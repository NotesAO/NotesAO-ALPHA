<?php
include_once 'auth.php';
check_loggedin($con);
    require_once "helpers.php";

    function stringClean($value) {
        $value = str_replace(' ', '', $value);  // Remove spaces
        $value = preg_replace('/[^A-Za-z0-9\-]/', '', $value); // Removes special chars.
        return $value;
    }
    
    $table_num = "";
    if (isset($_GET['table_num'])) {
        $table_num = $_GET['table_num'];
    }


    $filename = "filename";
    // environment_program_report_date.csv
    if(isset($appname)){
        $filename = stringClean($appname);
    }

    // if(isset($_SESSION['program_name'])){
    //     $filename = $filename . "_" . stringClean($_SESSION['program_name']);
    // }

    $filename = $filename . "_" . "report" . $table_num;  // report table name
//    $filename = $filename . "_" . date("Ymd_His");
    $filename = $filename . "_" . date("Ymd");
    $filename = $filename . ".csv";

    header( 'Content-Type: text/csv' );
    header( "Content-Disposition: attachment;filename=" . $filename );
    $out = fopen('php://output', 'w');

        // Prepare a select statement
        $sql = "SELECT * from report2 join report3 on report2.client_id = report3.client_id;";
        $firstRow = true;

        if($stmt = mysqli_prepare($link, $sql)){
            if(mysqli_stmt_execute($stmt)){
                $result = mysqli_stmt_get_result($stmt);

                while($resultrow = mysqli_fetch_array($result, MYSQLI_ASSOC)){
                    $resultrow = $resultrow;
                    if($firstRow) {
                        $firstRow = false;
                        $headers = array_keys($resultrow);
                        fputcsv($out, $headers);
                    }
                    fputcsv($out, $resultrow);
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.<br>".$stmt->error;
            }
        }
        // Close statement
        mysqli_stmt_close($stmt);
    
    fclose($out);
?>

