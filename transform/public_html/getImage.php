<?php
    include 'auth.php';
    check_loggedin($con);

    global $link;
    
    $id = $_GET['id'];
    // do some validation here to ensure id is safe
  
    $sql = "SELECT image_data FROM image WHERE id = ?";
    if($stmt = mysqli_prepare($link, $sql)) {
        $param_id = trim($id);
        mysqli_stmt_bind_param($stmt, "i", $param_id);

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
