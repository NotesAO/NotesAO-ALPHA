<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";
require_once "sql_functions.php";

// Default to the program ID in the session
$program_id = $_SESSION['program_id'] ?? null;
$program_name = $_SESSION['program_name'] ?? 'BIPP (male)';

// Debug log current program information
error_log("Current Program in Session: ID=$program_id, Name=$program_name");

// If a new Program ID was posted
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["program_id"])) {
    $posted_program_id = intval(trim($_POST["program_id"]));

    // Hardcode the valid IDs and names for DWAG
    $hardcoded_programs = [
        1 => "MRT",
        2 => "BIPP (male)",
        3 => "BIPP (female)"
    ];

    if (isset($hardcoded_programs[$posted_program_id])) {
        // Update session with the selected program
        $_SESSION['program_id'] = $posted_program_id;
        $_SESSION['program_name'] = $hardcoded_programs[$posted_program_id];

        // Debug log
        error_log("Program updated successfully: ID=$posted_program_id, Name=" . $hardcoded_programs[$posted_program_id]);

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Redirect to the home page to refresh session data
        header('Location: home.php');
        exit;
    } else {
        // Log error if the posted ID isn't in our hardcoded array
        error_log("Invalid Program ID posted: $posted_program_id");
    }
} else {
    error_log("No Program ID posted or invalid request.");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>NotesAO - Program Selection</title>
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
    <!-- Bootstrap CSS -->
    <link rel="stylesheet"
          href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
          integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk"
          crossorigin="anonymous">

    <!-- Font Awesome for icons -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<?php require_once('navbar.php'); ?>

<body>
    <section class="pt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 mx-auto">
                    <div class="page-header">
                        <h2>Current Program: <?php echo htmlspecialchars($program_name); ?></h2>
                    </div>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="form-group">
                            <label>Select Program</label>
                            <select class="form-control" id="program_id" name="program_id">
                                <?php
                                $programs = get_programs();
                                foreach ($programs as $program) {
                                    $value = htmlspecialchars($program["name"]);
                                    $selected = ($program["id"] == $program_id) ? 'selected="selected"' : '';
                                    echo "<option value='{$program['id']}' $selected>$value</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <input type="submit" class="btn btn-primary" value="Select Program">
                        <a href="home.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </section>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2nJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>

</html>
