<?php
include_once 'auth.php';
check_loggedin($con);

// Validate id early
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: error.php");
    exit();
}

require_once 'helpers.php';

// Prepare a select statement — USE $con, not $link
$sql = "SELECT * FROM case_manager WHERE id = ?";
if ($stmt = mysqli_prepare($con, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1) {
            $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        } else {
            // No such record
            header("Location: error.php");
            exit();
        }
    } else {
        echo "Oops! Something went wrong. Please try again later.<br>" . $stmt->error;
    }
    mysqli_stmt_close($stmt);
} else {
    echo "Oops! Failed to prepare statement.<br>" . ($con->error ?? '');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Case Manager Record</title>
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
    <!-- Bootstrap 4.5 and Font Awesome (Ensure these match home.php) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

</head>
<?php require_once('navbar.php'); ?>
<body>
    <section class="pt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="page-header">
                        <h1>View Record</h1>
                    </div>

                     <div class="form-group">
                            <h4>first_name</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["first_name"]); ?></p>
                        </div><div class="form-group">
                            <h4>last_name</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["last_name"]); ?></p>
                        </div><div class="form-group">
                            <h4>office</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["office"]); ?></p>
                        </div><div class="form-group">
                            <h4>email</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["email"]); ?></p>
                        </div><div class="form-group">
                            <h4>phone_number</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["phone_number"]); ?></p>
                        </div><div class="form-group">
                            <h4>fax</h4>
                            <p class="form-control-static"><?php echo htmlspecialchars($row["fax"]); ?></p>
                        </div>

                    <p><a href="case_manager-index.php" class="btn btn-primary">Back</a></p>
                </div>
            </div>
        </div>
    </section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>
</html>