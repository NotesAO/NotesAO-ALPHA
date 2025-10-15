<?php
/*───────────────────────────────────────────────────────────
  client-victim-create.php
  – Add a NEW victim row that belongs to the given client
  – pattern & styling matches ledger-create.php
───────────────────────────────────────────────────────────*/
include_once 'auth.php';
check_loggedin($con);

require_once 'helpers.php';
require_once 'sql_functions.php';

/* ---------- 1. read client_id ---------------------------------------- */
$client_id = $_GET['client_id'] ?? ($_POST['client_id'] ?? '');
if (!$client_id) {
    header('location:error.php'); exit;
}

/* ---------- 2. initialise form fields & errors ----------------------- */
$relationship_to_victim   = '';
$victim_gender_id         = '';
$victim_age               = '';
$residing_with_victim     = 0;
$num_children_under_18    = '';

$relationship_err = $victim_age_err = $num_children_err = '';

/* ---------- 3. on POST – validate & insert --------------------------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {

    /* trim incoming --------------------------------------------------- */
    $relationship_to_victim = trim($_POST['relationship_to_victim'] ?? '');
    $victim_gender_id       = trim($_POST['victim_gender_id']       ?? '');
    $victim_age             = trim($_POST['victim_age']             ?? '');
    $residing_with_victim   = isset($_POST['residing_with_victim']) ? 1 : 0;
    $num_children_under_18  = trim($_POST['num_children_under_18']  ?? '');

    /* minimal validation --------------------------------------------- */
    if ($relationship_to_victim==='') {
        $relationship_err = 'Relationship is required.';
    }

    if ($victim_age !== '' && !preg_match('/^\d+$/',$victim_age)) {
        $victim_age_err = 'Age must be a positive number.';                 }

    if ($num_children_under_18 !== '' &&
        !preg_match('/^\d+$/',$num_children_under_18)) {
        $num_children_err = 'Enter 0 or a positive integer.';               }

    /* insert when clean ---------------------------------------------- */
    if ($relationship_err.$victim_age_err.$num_children_err === '') {

        $pdo = new PDO('mysql:host='.db_host.';dbname='.db_name.';charset=utf8mb4',
                       db_user, db_pass,
                       [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

        $stmt = $pdo->prepare("
            INSERT INTO victim
              (client_id, relationship_to_victim, victim_gender_id,
               victim_age, residing_with_victim, num_children_under_18)
            VALUES (?,?,?,?,?,?)");

        $stmt->execute([
            $client_id,
            $relationship_to_victim,
            $victim_gender_id ?: null,
            $victim_age       ?: null,
            $residing_with_victim,
            $num_children_under_18 ?: null
        ]);

        header("location: client-victim.php?client_id=$client_id"); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO – Add Victim</title>

    <!-- favicon & bootstrap identical to ledger-create.php -->
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

                <h2>Add Victim Information</h2>
                <p>Fill in the details and submit to attach victim info to this client.</p>

                <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <!-- keep client ID hidden -->
                    <input type="hidden" name="client_id" value="<?= $client_id ?>">

                    <div class="form-group">
                        <label>Relationship to Victim <span class="text-danger">*</span></label>
                        <input type="text" name="relationship_to_victim" maxlength="64"
                               class="form-control <?= $relationship_err?'is-invalid':'' ?>"
                               value="<?= htmlspecialchars($relationship_to_victim) ?>">
                        <span class="form-text text-danger"><?= $relationship_err ?></span>
                    </div>

                    <div class="form-group">
                        <label>Victim Gender</label>
                        <select name="victim_gender_id" class="form-control">
                            <option value="">Select One</option>
                            <?php foreach (get_genders() as $g): ?>
                                <option value="<?= $g['id'] ?>"
                                    <?= $g['id']==$victim_gender_id?'selected':'' ?>>
                                    <?= htmlspecialchars($g['gender']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-3">
                            <label>Victim Age</label>
                            <input type="number" min="0" name="victim_age"
                                   class="form-control <?= $victim_age_err?'is-invalid':'' ?>"
                                   value="<?= htmlspecialchars($victim_age) ?>">
                            <span class="form-text text-danger"><?= $victim_age_err ?></span>
                        </div>

                        <div class="form-group col-4">
                            <label>Children&nbsp;&lt; 18 in Home</label>
                            <input type="number" min="0" name="num_children_under_18"
                                   class="form-control <?= $num_children_err?'is-invalid':'' ?>"
                                   value="<?= htmlspecialchars($num_children_under_18) ?>">
                            <span class="form-text text-danger"><?= $num_children_err ?></span>
                        </div>

                        <div class="form-group col-5 align-self-end">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input"
                                       id="reside" name="residing_with_victim"
                                       <?= $residing_with_victim ? 'checked' : '' ?>>
                                <label class="form-check-label" for="reside">
                                    Client resides with victim
                                </label>
                            </div>
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
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>
</html>
