<?php
/*────────────────────────────────────────────────────────────
  client-victim-delete.php
  – Confirm & delete a victim row for the given client
────────────────────────────────────────────────────────────*/
include_once 'auth.php';
check_loggedin($con);

require_once 'helpers.php';

/* ---------- Identify victim and client ID ------------------- */
$client_id = '';
if (isset($_GET['client_id']))            $client_id = $_GET['client_id'];
if ($_SERVER['REQUEST_METHOD']==='POST')  $client_id = trim($_POST['client_id']);

/* ---------- POST: delete the record ------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && $_POST['id'] !== '') {
    $sql = "DELETE FROM victim WHERE id = ?";
    if ($stmt = mysqli_prepare($con, $sql)) {
        $victim_id = trim($_POST['id']);
        mysqli_stmt_bind_param($stmt, 'i', $victim_id);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            header("Location: client-victim.php?client_id=$client_id");
            exit;
        } else {
            echo "Oops! Something went wrong. Please try again later.<br>" . mysqli_error($con);
        }
    }
} else {
    if (!isset($_GET['id']) || trim($_GET['id']) === '') {
        header('Location: error.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NotesAO – Delete Victim</title>
  <link rel="icon" href="/favicons/favicon.ico">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>body { padding-top: 56px; font-size: 16px; }</style>
</head>
<body>
<?php require_once 'navbar.php'; ?>
<section class="pt-5">
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-6 mx-auto">

        <h1>Delete Victim Record</h1>
        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">
          <div class="alert alert-danger">
            <input type="hidden" name="id"        value="<?= htmlspecialchars(trim($_GET['id'])); ?>">
            <input type="hidden" name="client_id" value="<?= htmlspecialchars($client_id); ?>">
            <p>Are you sure you want to permanently delete this victim record?</p>
            <p class="mb-0">
              <button type="submit" class="btn btn-danger">Yes, delete</button>
              <a href="client-victim.php?client_id=<?= htmlspecialchars($client_id); ?>" class="btn btn-secondary">No, cancel</a>
            </p>
          </div>
        </form>

      </div>
    </div>
  </div>
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
