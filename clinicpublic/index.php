<?php
header("Location: /login.php");
exit();
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "https://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
    <meta charset="us-ascii">
    <title>www.Notepro.co</title>
</head>
<body>
    <h1>www.notepro.co</h1>
    <h2>A data management and reporting solution to allow you to easily meet TDCJ-CJAD reporting requirements enabling you to focus on empowering your clients to make healthy decisions.</h2>

    <?php
    // Database connection details
    $db_host = '50.28.37.79'; // Your database host
    $db_name = 'clinicnotepro_globalclinics'; // This should point to the global clinics database
    $db_user = 'clinicnotepro_global'; // Your global database user
    $db_pass = 'N0tePr0*!'; // Your global database password
    
    $con = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    
    if (!$con) {
        die('Could not connect to the global clinics database: ' . mysqli_error($con));
    }

    // Query to fetch clinics from the global clinics database
    $sql = "SELECT name, domain FROM clinics";
    $result = mysqli_query($con, $sql);

    if (mysqli_num_rows($result) > 0) {
        echo '<ul>';
        // Output each clinic as a link
        while ($row = mysqli_fetch_assoc($result)) {
            echo '<li><a href="https://' . $row['domain'] . '">' . $row['name'] . '</a></li>';
        }
        echo '</ul>';
    } else {
        echo "No clinics available.";
    }

    // Close the database connection
    mysqli_close($con);
    ?>

</body>
</html>

