<?php
/*────────────────────────────────────────────────────────────
  client-victim-delete.php
  – Confirm & delete a victim row that belongs to a client
  – mirrors ledger-delete.php structure / styling
────────────────────────────────────────────────────────────*/
include_once 'auth.php';
check_loggedin($con);

/* db config + helpers (mysqli $link already created in config.php) */
require_once '../config/config.php';
require_once 'helpers.php';

/* ---------- figure out client & victim ids ----------------------- */
$client_id = '';
if (isset($_GET['client_id']))            $client_id = $_GET['client_id'];
if ($_SERVER['REQUEST_METHOD']==='POST')  $client_id = trim($_POST['client_id']);

/* ---------- POST: actually delete -------------------------------- */
if (isset($_POST['id']) && $_POST['id']!=='') {

    $sql = "DELETE FROM victim WHERE id = ?";
    if ($stmt = mysqli_prepare($link,$sql)) {

        $param_id = trim($_POST['id']);
        mysqli_stmt_bind_param($stmt,'i',$param_id);

        if (mysqli_stmt_execute($stmt)) {
            /* success → back to victim list */
            header("location: client-victim.php?client_id=$client_id"); exit;
        } else {
            echo "Oops! Something went wrong. Please try again later.<br>".$stmt->error;
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_close($link);

} else {
    /* first visit – ensure victim id present */
    if (!isset($_GET['id']) || trim($_GET['id'])==='') {
        header('location:error.php'); exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NotesAO – Victim Delete</title>

<!-- favicons + Bootstrap (same set used elsewhere) -->
<link rel="icon" type="image/x-icon"             href="/favicons/favicon.ico">
<link rel="icon" type="image/png"  sizes="32x32"  href="/favicons/favicon-32x32.png">
<link rel="icon" type="image/png"  sizes="16x16"  href="/favicons/favicon-16x16.png">
<link rel="icon" type="image/svg+xml"            href="/favicons/favicon.svg">
<link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">
<link rel="manifest"  href="/favicons/site.webmanifest">
<meta name="apple-mobile-web-app-title" content="NotesAO">

<link rel="stylesheet"
      href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
      crossorigin="anonymous">
<style>body{padding-top:56px;font-size:16px}</style>
</head>

<?php require_once 'navbar.php'; ?>

<body>
<section class="pt-5">
<div class="container-fluid">
<div class="row">
    <div class="col-md-6 mx-auto">

        <h1>Delete Victim Record</h1>
        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="alert alert-danger">
                <input type="hidden" name="id"        value="<?= htmlspecialchars(trim($_GET['id'])); ?>">
                <input type="hidden" name="client_id" value="<?= htmlspecialchars($client_id); ?>">
                <p>Are you sure you want to remove this victim entry?</p>
                <p class="mb-0">
                    <button type="submit" class="btn btn-danger">Yes, delete</button>
                    <a href="client-victim.php?client_id=<?= htmlspecialchars($client_id); ?>"
                       class="btn btn-secondary">No, cancel</a>
                </p>
            </div>
        </form>

    </div>
</div>
</div>
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>
</html>
