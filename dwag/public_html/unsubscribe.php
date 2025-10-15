<?php
declare(strict_types=1);
require_once '../config/config.php';
// require_once 'auth.php'; // not needed here

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function unsubscribe_token(string $email): string {
  $email = strtolower(trim($email));
  $salt  = defined('EMAIL_UNSUB_SALT') ? EMAIL_UNSUB_SALT : (__FILE__ . php_uname());
  return hash('sha256', $email . $salt);
}

$emailRaw = $_GET['e'] ?? '';
$token    = $_GET['t'] ?? '';

$email = strtolower(trim($emailRaw));   // normalize once

$ok = false; $msg = 'Invalid request.';

if (filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/^[a-f0-9]{64}$/', $token)) {
  // recompute expected token and compare
  $expected = unsubscribe_token($email);

  if (!hash_equals($expected, $token)) {
    $ok = false;
    $msg = 'Token mismatch.';
  } else {
    // idempotent insert/update
    $stmt = $con->prepare(
      "INSERT INTO email_unsubscribed (email, token) VALUES (?, ?)
       ON DUPLICATE KEY UPDATE token=VALUES(token)"
    );
    $stmt->bind_param('ss', $email, $expected);
    $ok = $stmt->execute();
    $stmt->close();
    $msg = $ok ? 'You have been unsubscribed successfully.' : 'Database error.';
  }
}
?>
<!doctype html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Unsubscribe</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head><body class="p-4">
<div class="container">
  <div class="row justify-content-center"><div class="col-md-6">
    <div class="card">
      <div class="card-header"><strong>Dutch Will Assessment Group — Unsubscribe</strong></div>
      <div class="card-body">
        <div class="alert alert-<?= $ok ? 'success' : 'danger' ?>"><?= h($msg) ?></div>
        <?php if ($email): ?><p class="mb-0 small text-muted"><?= h($email) ?></p><?php endif; ?>
      </div>
    </div>
  </div></div>
</div>
</body></html>
