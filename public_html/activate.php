<?php
/* ---- DEBUG ONLY  – remove after we fix ---- */
ini_set('display_errors', '1');          // show in browser
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

ini_set('log_errors', '1');              // also write to a file we control
ini_set('error_log', __DIR__ . '/activate.error.log');
/* ------------------------------------------ */

/* -----------------------------------------------------------
   NOTESAO  -  ACCOUNT ACTIVATION (Step 1 – verify + show form)
   URL sample:  https://notesao.com/activate.php?clinic=ffl&code=xxxxxxxx
   ----------------------------------------------------------- */

define('CLINIC_MAP', [               // short-code → folder name
    'ffl'   => 'ffltest',
    'sandbox' => 'sandbox',
    'dwag'  => 'dwag',
    'saf'   => 'safatherhood',
    'ctc'   => 'ctc',
    'tbo'   => 'bestoption',
    'transform' => 'transform',
    'admin' => 'adminclinic',
    'lankford' => 'lankford',
    'sage' => 'sage',
    'saferpath' => 'saferpath',
    'lakeview' => 'lakeview',
]);

/* ---------- 1. Grab & sanitise URL params ---------- */
$clinic = $_GET['clinic'] ?? '';
$code   = $_GET['code']   ?? '';

if (!preg_match('/^[a-z0-9]{2,12}$/i', $clinic) ||
    !preg_match('/^[a-f0-9]{64}$/', $code)) {
    exit('Invalid activation link.');
}

/* ---------- 2. Resolve clinic folder + config ---------- */
if (!isset(CLINIC_MAP[$clinic])) {
    exit('Clinic Code not recognized. Please contact support: admin@notesao.com');
}

$folder     = CLINIC_MAP[$clinic];
$clinicRoot = "/home/notesao/{$folder}";

require_once "$clinicRoot/config/config.php";   // DB constants
require_once '/home/notesao/lib/mailer.php';    // helper

/* -----------------------------------------------------------
   If main.php hasn’t set $con, open our own connection
   ----------------------------------------------------------- */
if (!isset($con) || !($con instanceof mysqli)) {
    $con = mysqli_connect(db_host, db_user, db_pass, db_name);
    if (mysqli_connect_errno()) {
        error_log('Activation-connect error: '.mysqli_connect_error());
        exit('Database connection failed – please try again later.');
    }
}



/* ---------- 3. Look up activation code ---------- */
$stmt = $con->prepare(
    'SELECT id, username FROM accounts
     WHERE activation_code = ?'
);
$stmt->bind_param('s', $code);
$stmt->execute();
$stmt->bind_result($uid, $uname);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    exit('This activation link is invalid or has already been used.');
}

/* ---------- 4. Handle the POST (save new password) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p1 = $_POST['pass1'] ?? '';
    $p2 = $_POST['pass2'] ?? '';

    /* basic checks */
    if ($p1 !== $p2)       { exit('Passwords do not match – please go back.'); }
    if (strlen($p1) < 8)   { exit('Password must be at least 8 characters.'); }

    /* hash + update */
    $hash = password_hash($p1, PASSWORD_DEFAULT);

    $stmt = $con->prepare(
        'UPDATE accounts
            SET password              = ?,
                activation_code       = "activated",
                password_force_reset  = 0,
                password_changed_at   = NOW()
          WHERE id = ?'
    );
    $stmt->bind_param('si', $hash, $uid);
    $stmt->execute();
    $stmt->close();

    /* done – jump to login page */
    header('Location: https://notesao.com/login.php?activated=1');
    exit;
}


/* ---------- 4. Show simple password-set form ---------- */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Activate your NotesAO account</title>
    <link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-5">
<h2 class="mb-4">Welcome, <?=htmlspecialchars($uname)?>!</h2>

<p class="mb-3">
    Please choose a password to finish activating your account.
</p>

<form method="post"
      action="activate.php?clinic=<?=urlencode($clinic)?>&code=<?=urlencode($code)?>">


    <div class="mb-3">
        <label class="form-label fw-bold" for="pass1">Password</label>
        <input type="password" name="pass1" id="pass1"
               class="form-control" required minlength="8">
    </div>
    <div class="mb-3">
        <label class="form-label fw-bold" for="pass2">Confirm Password</label>
        <input type="password" name="pass2" id="pass2"
               class="form-control" required minlength="8">
    </div>

    <button type="submit" class="btn btn-primary">Set Password &amp; Activate</button>
</form>
</body>
</html>

