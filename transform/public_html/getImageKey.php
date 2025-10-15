<?php
    include_once dirname(__FILE__) . '/../config/config.php';
    $con = mysqli_connect(db_host, db_user, db_pass, db_name);
   
    // If there is an error with the MySQL connection, stop the script and output the error
    if (mysqli_connect_errno()) {
        exit('Failed to connect to MySQL: ' . mysqli_connect_error());
    }

    global $link;
    
    $id = $_GET['id'];
    $id = htmlspecialchars(trim($id));

    $key = $_GET['key'];
    $key = htmlspecialchars(trim($key));

    $sql = "SELECT image_data FROM image WHERE id = ? and hash = ?";
    if($stmt = mysqli_prepare($link, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $id, $key);

        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if(mysqli_num_rows($result) == 1) {
                $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
                header("Content-type: image/jpeg");
                echo $row['image_data'];
            }
        }
    }
    mysqli_stmt_close($stmt);
?>
