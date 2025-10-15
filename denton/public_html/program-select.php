<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";
require_once "sql_functions.php";

// Default to the program ID in the session
$program_id = $_SESSION['program_id'];
$program_name = $_SESSION['program_name'];

// If a new Program ID was posted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $posted_program_id = trim($_POST["program_id"]);

    $sql = "SELECT name FROM program where id = " . $posted_program_id;
    $result = mysqli_query($link, $sql);
    if ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
        // Program was found in DB; Update the Session
        $program_name = $row["name"];
        $program_id = $posted_program_id;

        $_SESSION['program_id'] = $program_id;
        $_SESSION['program_name'] = $program_name;
        header('Location: home.php');
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Select Program</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<?php require_once('navbar.php'); ?>

<body>
    <section class="pt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 mx-auto">
                    <div class="page-header">
                        <h2>Current Program is <?php echo htmlspecialchars($program_name); ?></h2>
                    </div>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="form-group">
                            <label>Select Program</label>
                            <select class="form-control" id="program_id" name="program_id">
                                <?php
                                $programs = get_programs();
                                foreach ($programs as $program) {
                                    $value = htmlspecialchars($program["name"]);
                                    if ($program["id"] == $program_id) {
                                        echo '<option value="' . "$program[id]" . '"selected="selected">' . "$value" . '</option>';
                                    } else {
                                        echo '<option value="' . "$program[id]" . '">' . "$value" . '</option>';
                                    }
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
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>

</html>