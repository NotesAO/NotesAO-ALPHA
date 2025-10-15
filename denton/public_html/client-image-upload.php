<?php
include_once 'auth.php';
check_loggedin($con);

require_once "sql_functions.php";
require_once "helpers.php";

// Define variables and initialize with empty values
$client_id = "";
if (isset($_GET['client_id'])) {
    $client_id = $_GET['client_id'];
}
if (isset($_POST['client_id'])) {
    $client_id = $_POST['client_id'];
}

$client_err = "";
$file_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $target_dir = "uploads/";
    $target_file = $target_dir . basename($_FILES["clientImage"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    if ("" == $_FILES["clientImage"]["tmp_name"]) {
        $uploadOk = 0;
    }

    // Check if image file is a actual image or fake image
    if ($uploadOk == 1) {
        $check = getimagesize($_FILES["clientImage"]["tmp_name"]);
        if ($check !== false) {
            //        echo "File is an image - " . $check["mime"] . ".";
            $uploadOk = 1;
        } else {
            $file_err = "File is not an image.";
            $uploadOk = 0;
        }
    }
    // Check if file already exists
    if ($uploadOk == 1) {
        if (file_exists($target_file)) {
            $file_err = "File already exists.";
            $uploadOk = 0;
        }

        // Allow certain file formats
        if ($uploadOk == 1) {
            if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                $uploadOk = 0;
            }
        }
        // Check file size
        if ($uploadOk == 1) {
            if ($_FILES["clientImage"]["size"] > 500000) {
                $file_err = "Your file is too large. Max size 500000";
                $uploadOk = 0;
            }
        }

        // Check if $uploadOk is set to 0 by an error
        if ($uploadOk == 1) {
            // For storing on filesystem
            // if (move_uploaded_file($_FILES["clientImage"]["tmp_name"], $target_file)) {
            //   $file_err = "The file ". htmlspecialchars( basename( $_FILES["clientImage"]["name"])). " has been uploaded.";
            // } else {
            //   $file_err = "Sorry, there was an error uploading your file.";
            // }

            // For storing in DB
            $image = $_FILES['clientImage']['tmp_name'];
            $imgContent = file_get_contents($image);

            $dsn = "mysql:host=" . db_host . ";dbname=" . db_name . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_EMULATE_PREPARES   => false, // turn off emulation mode for "real" prepared statements
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
            ];
            try {
                $pdo = new PDO($dsn, db_user, db_pass, $options);
            } catch (Exception $e) {
                error_log($e->getMessage());
                exit('Error connecting to database'); //something a user can understand
            }

            $stmt = $pdo->prepare("INSERT into image (id, hash, image_data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE hash = VALUES(hash), image_data = VALUES(image_data)");
            $hash = hash("sha256", $imgContent);

            if ($stmt->execute([$client_id, $hash, $imgContent])) {
                $stmt = null;
                //      header("location: therapy_session-index.php");
            } else {
                echo "Something went wrong. Please try again later.";
            }
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Client Image Upload</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
</head>
<?php require_once('navbar.php'); ?>

<body>
    <section class="pt-5">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 mx-auto">
                    <div class="page-header">
                        <h2>Client Image Upload</h2>
                        <span class="form-text"><?php echo $file_err; ?></span>
                    </div>
                    <?php
                    $row = get_client_info(trim($client_id));
                    if (!isset($row)) {
                        echo "ERROR: Client " . $client_id . " was not found.<br>";
                        echo "<a href='client-index.php' class='btn btn-secondary'>Cancel</a>";
                    } else {
                        echo "Client name " . $row['first_name'] . " " . $row['last_name'] . "<br>";
                    }
                    ?>

                    <form action="client-image-upload.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <label for="clientImage" class="form-label">Select client image file. (max size 500kb)</label>
                        <input class="form-control" type="file" id="clientImage" name="clientImage">
                        </br>
                        <input class="btn btn-primary" type="submit" value="Upload Image">
                        <a class="btn btn-secondary" href="client-index.php">Cancel</a>
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