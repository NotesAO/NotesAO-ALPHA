<?php
/*******************************************************
 *  NotesAO — Forgot-Password Handler
 *  Location: /home/notesao/public_html/forgot_password.php
 *******************************************************/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/home/notesao/public_html/forgot_password.error.log');

/* ----------------------------------------------------
 * 1.  Shared mail helper (pulls in PHPMailer + globals)
 * -------------------------------------------------- */
require_once '/home/notesao/lib/mailer.php';   // contains send_email() + SMTP constants

/* ----------------------------------------------------
 * 2.  Map short clinic codes to folder names
 *     (same mapping used in login.php)
 * -------------------------------------------------- */
function clinic_folder_from_short($short) {
    $map = [
        'ffl'        => 'ffltest',
        'sandbox'    => 'sandbox',
        'dwag'       => 'dwag',
        'saf'        => 'safatherhood',
        'ctc'        => 'ctc',
        'transform'  => 'transform',
        'tbo'        => 'bestoption',
    ];
    return $map[strtolower($short)] ?? null;
}

/* ----------------------------------------------------
 * 3.  Handle POST - generate token & send mail
 * -------------------------------------------------- */
$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $short    = trim($_POST['clinic']   ?? '');

    if ($username === '' || $short === '') {
        $error = 'Please enter both username and clinic code.';
    } else {
        $clinic_folder = clinic_folder_from_short($short);
        if (!$clinic_folder) {
            $error = 'Clinic code not recognised.';
        } else {
            /* load clinic-specific DB constants */
            $clinic_cfg = "/home/notesao/{$clinic_folder}/config/config.php";
            if (!file_exists($clinic_cfg)) {
                $error = 'Clinic configuration missing.';
            } else {
                include $clinic_cfg;               // defines db_host, db_user, db_pass, db_name
                $con = new mysqli(db_host, db_user, db_pass, db_name);
                if ($con->connect_error) {
                    $error = 'Database connection error.';
                } else {
                    /* look up user */
                    $stmt = $con->prepare('SELECT id, email FROM accounts WHERE username = ?');
                    $stmt->bind_param('s', $username);
                    $stmt->execute();
                    $stmt->store_result();

                    if ($stmt->num_rows === 0) {
                        $error = 'Account not found (check spelling / clinic).';
                    } else {
                        $stmt->bind_result($uid, $email);
                        $stmt->fetch();
                        $stmt->close();

                        /* purge any previous tokens */
                        $con->query("DELETE FROM password_reset_tokens WHERE user_id = {$uid}");

                        /* create new token */
                        $token   = bin2hex(random_bytes(32));
                        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));

                        $ins = $con->prepare(
                          'INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?,?,?)'
                        );
                        $ins->bind_param('iss', $uid, $token, $expires);
                        $ins->execute();
                        $ins->close();

                        /* compose email */
                        $reset_link = "https://notesao.com/reset_password.php?clinic={$short}&token={$token}";

                        $html = "
                            <p>Hi {$username},</p>
                            <p>We received a request to reset your NotesAO password. Click the button below to continue:</p>
                            <p>
                              <a href=\"{$reset_link}\"
                                 style=\"display:inline-block;padding:10px 18px;background:#211c56;color:#fff;
                                        text-decoration:none;border-radius:6px;\">Reset Password</a>
                            </p>
                            <p>This link will expire in 30&nbsp;minutes. If you did not request a reset, you can ignore this email.</p>
                            <p>— NotesAO Support</p>";

                        if (send_email($email, 'NotesAO password reset', $html)) {
                            $sent = true;
                        } else {
                            $error = 'Failed to send email (SMTP error).';
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>NotesAO – Forgot Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {min-height:100vh;display:flex;justify-content:center;align-items:center;background:#f4f7fb;}
.card {max-width:420px;width:100%;box-shadow:0 4px 12px rgba(0,0,0,.1);}
</style>
</head>
<body>
<div class="card p-4">
  <h4 class="mb-3 text-center">Reset your password</h4>

  <?php if ($sent): ?>
      <div class="alert alert-success">
        If the account exists, a reset link has been emailed.
      </div>
  <?php elseif ($error): ?>
      <div class="alert alert-danger"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>

  <?php if (!$sent): ?>
  <form method="post">
    <div class="mb-3">
      <label class="form-label">Username</label>
      <input type="text" name="username" class="form-control" required
             value="<?=htmlspecialchars($_POST['username'] ?? '')?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Clinic code (e.g. <code>sandbox</code>)</label>
      <input type="text" name="clinic" class="form-control" required
             value="<?=htmlspecialchars($_POST['clinic'] ?? '')?>">
    </div>
    <button class="btn btn-primary w-100">Send reset link</button>
  </form>
  <?php endif; ?>

  <p class="mt-3 text-center">
    <a href="/login.php">Back to login</a>
  </p>
</div>
</body>
</html>

