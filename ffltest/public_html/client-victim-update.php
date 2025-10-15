<?php
/*───────────────────────────────────────────────────────────
  client-victim-update.php  –  Edit an existing victim row
───────────────────────────────────────────────────────────*/
include_once 'auth.php';
check_loggedin($con);

require_once 'helpers.php';
require_once 'sql_functions.php';

/* ---------- 1. Initialize variables & errors -------------- */
$id                        = $_GET['id']    ?? ($_POST['id'] ?? '');
$client_id                 = '';
$name                      = '';
$relationship              = '';
$gender                 = '';
$age                       = '';
$living_with_client      = 0;
$children_under_18     = '';
$address_line1             = '';
$address_line2             = '';
$city                      = '';
$state                     = '';
$zip                       = '';
$phone                     = '';
$email                     = '';

$name_err       = $relationship_err = $age_err = $children_err = '';
$zip_err        = $email_err       = '';

/* ---------- 2. POST: validate & update ------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
    // pull in POST
    $client_id              = $_POST['client_id']              ?? '';
    $name                   = trim($_POST['name']               ?? '');
    $relationship           = trim($_POST['relationship']       ?? '');
    $gender              = trim($_POST['gender']          ?? '');
    $age                    = trim($_POST['age']                ?? '');
    $living_with_client   = isset($_POST['living_with_client']) ? 1 : 0;
    $children_under_18  = trim($_POST['children_under_18'] ?? '');
    $address_line1          = trim($_POST['address_line1']      ?? '');
    $address_line2          = trim($_POST['address_line2']      ?? '');
    $city                   = trim($_POST['city']               ?? '');
    $state                  = trim($_POST['state']              ?? '');
    $zip                    = trim($_POST['zip']                ?? '');
    $phone                  = trim($_POST['phone']              ?? '');
    $email                  = trim($_POST['email']              ?? '');

    // validation
    if ($name === '') {
        $name_err = 'Name is required.';
    }
    if ($relationship === '') {
        $relationship_err = 'Relationship is required.';
    }
    if ($age !== '' && !preg_match('/^\d+$/', $age)) {
        $age_err = 'Enter a positive integer.';
    }
    if ($children_under_18 !== '' && !preg_match('/^\d+$/', $children_under_18)) {
        $children_err = 'Enter 0 or a positive integer.';
    }
    if ($zip !== '' && !preg_match('/^\d{5}(-\d{4})?$/', $zip)) {
        $zip_err = 'Enter a valid ZIP code.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = 'Enter a valid email.';
    }

    if (!$name_err && !$relationship_err && !$age_err && !$children_err && !$zip_err && !$email_err) {
        $sql = "
          UPDATE victim SET
            client_id               = ?,
            name                    = ?,
            relationship            = ?,
            gender               = ?,
            age                     = ?,
            living_with_client    = ?,
            children_under_18   = ?,
            address_line1           = ?,
            address_line2           = ?,
            city                    = ?,
            state                   = ?,
            zip                     = ?,
            phone                   = ?,
            email                   = ?
          WHERE id = ?";
        if ($stmt = mysqli_prepare($con, $sql)) {
            //
            //  build _param vars so we never bind an undefined or NULL
            //
            $gender_p            = $gender             !== '' ? $gender             : null;
            $age_p               = $age                !== '' ? (int)$age            : null;
            $living_p            = $living_with_client;                // always 0 or 1
            $children_p          = $children_under_18  !== '' ? (int)$children_under_18 : null;
            $addr1_p             = $address_line1      !== '' ? $address_line1      : null;
            $addr2_p             = $address_line2      !== '' ? $address_line2      : null;
            $city_p              = $city               !== '' ? $city               : null;
            $state_p             = $state              !== '' ? $state              : null;
            $zip_p               = $zip                !== '' ? $zip                : null;
            $phone_p             = $phone              !== '' ? $phone              : null;
            $email_p             = $email              !== '' ? $email              : null;

            mysqli_stmt_bind_param(
              $stmt,
              'isssiiisssssssi',
              $client_id,
              $name,
              $relationship,
              $gender_p,
              $age_p,
              $living_p,
              $children_p,
              $addr1_p,
              $addr2_p,
              $city_p,
              $state_p,
              $zip_p,
              $phone_p,
              $email_p,
              $id
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            header("Location: client-victim.php?client_id=$client_id");
            exit;
        } else {
            echo "DB prepare error: " . mysqli_error($con);
            exit;
        }
    }
/* ---------- 3. GET: fetch existing for form --------------- */
} elseif ($id) {
    $sql = "SELECT * FROM victim WHERE id = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $client_id              = $row['client_id'];
        $name                   = $row['name'];
        $relationship           = $row['relationship'];
        $gender              = $row['gender'];
        $age                    = $row['age'];
        $living_with_client   = $row['living_with_client'];
        $children_under_18  = $row['children_under_18'];
        $address_line1          = $row['address_line1'];
        $address_line2          = $row['address_line2'];
        $city                   = $row['city'];
        $state                  = $row['state'];
        $zip                    = $row['zip'];
        $phone                  = $row['phone'];
        $email                  = $row['email'];
    } else {
        header('Location: error.php');
        exit;
    }
    mysqli_stmt_close($stmt);
} else {
    header('Location: error.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NotesAO – Update Victim</title>
  <link rel="icon" href="/favicons/favicon.ico">
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style> body{ padding-top:56px; font-size:16px; } </style>
</head>
<body>
<?php require_once 'navbar.php'; ?>
<section class="pt-5">
  <div class="container">
    <div class="row">
      <div class="col-md-8 mx-auto">
        <h2>Update Victim Record</h2>
        <p class="text-muted">Edit fields below and click Submit.</p>

        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="hidden" name="client_id" value="<?= $client_id ?>">

          <div class="form-group">
            <label>Victim Name <span class="text-danger">*</span></label>
            <input type="text" name="name"
                   class="form-control <?= $name_err?'is-invalid':'' ?>"
                   value="<?= htmlspecialchars($name) ?>">
            <div class="invalid-feedback"><?= $name_err ?></div>
          </div>

          <div class="form-group">
            <label>Relationship <span class="text-danger">*</span></label>
            <input type="text" name="relationship"
                   class="form-control <?= $relationship_err?'is-invalid':'' ?>"
                   value="<?= htmlspecialchars($relationship) ?>">
            <div class="invalid-feedback"><?= $relationship_err ?></div>
          </div>

          <div class="form-group">
            <label>Gender</label>
            <select name="gender" class="form-control">
              <option value="">(none)</option>
              <?php foreach(get_genders() as $g): ?>
                <option value="<?= $g['id'] ?>"
                  <?= $g['id']==$gender?'selected':'' ?>>
                  <?= htmlspecialchars($g['gender']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-row">
            <div class="form-group col-md-3">
              <label>Age</label>
              <input type="number" name="age" min="0"
                     class="form-control <?= $age_err?'is-invalid':'' ?>"
                     value="<?= htmlspecialchars($age) ?>">
              <div class="invalid-feedback"><?= $age_err ?></div>
            </div>
            <div class="form-group col-md-4">
              <label># Children &lt;18</label>
              <input type="number" name="children_under_18" min="0"
                     class="form-control <?= $children_err?'is-invalid':'' ?>"
                     value="<?= htmlspecialchars($children_under_18) ?>">
              <div class="invalid-feedback"><?= $children_err ?></div>
            </div>
            <div class="form-group col-md-5 align-self-end">
              <div class="form-check">
                <input type="checkbox" name="living_with_client" id="reside"
                       class="form-check-input"
                       <?= $living_with_client?'checked':'' ?>>
                <label class="form-check-label" for="reside">
                  Client resides with victim
                </label>
              </div>
            </div>
          </div>

          <hr>
          <h5 class="mt-4">Contact & Address</h5>

          <div class="form-group">
            <label>Address Line 1</label>
            <input type="text" name="address_line1" class="form-control"
                   value="<?= htmlspecialchars($address_line1) ?>">
          </div>
          <div class="form-group">
            <label>Address Line 2</label>
            <input type="text" name="address_line2" class="form-control"
                   value="<?= htmlspecialchars($address_line2) ?>">
          </div>

          <div class="form-row">
            <div class="form-group col-md-4">
              <label>City</label>
              <input type="text" name="city" class="form-control"
                     value="<?= htmlspecialchars($city) ?>">
            </div>
            <div class="form-group col-md-4">
              <label>State</label>
              <input type="text" name="state" class="form-control"
                     value="<?= htmlspecialchars($state) ?>">
            </div>
            <div class="form-group col-md-4">
              <label>ZIP</label>
              <input type="text" name="zip"
                     class="form-control <?= $zip_err?'is-invalid':'' ?>"
                     value="<?= htmlspecialchars($zip) ?>">
              <div class="invalid-feedback"><?= $zip_err ?></div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Phone</label>
              <input type="text" name="phone" class="form-control"
                     value="<?= htmlspecialchars($phone) ?>">
            </div>
            <div class="form-group col-md-6">
              <label>Email</label>
              <input type="email" name="email"
                     class="form-control <?= $email_err?'is-invalid':'' ?>"
                     value="<?= htmlspecialchars($email) ?>">
              <div class="invalid-feedback"><?= $email_err ?></div>
            </div>
          </div>

          <button type="submit" class="btn btn-primary">Submit</button>
          <a href="client-victim.php?client_id=<?= $client_id ?>"
             class="btn btn-secondary">Cancel</a>
        </form>

      </div>
    </div>
  </div>
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script
  src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.bundle.min.js">
</script>
</body>
</html>
