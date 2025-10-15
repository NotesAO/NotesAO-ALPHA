<?php
declare(strict_types=1);
require_once '../config/config.php';
require_once 'auth.php'; // optional if you want sessions; not required

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$email = $_GET['e'] ?? '';
$token = $_GET['t'] ?? '';

$ok = false; $msg = 'Invalid request.';

if (filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/^[a-f0-9]{64}$/', $token)) {
  // Verify or insert
  $stmt = $con->prepare("SELECT token FROM email_unsubscribed WHERE email=?");
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $stmt->bind_result($tok);
  if ($stmt->fetch()) {
    $ok = hash_equals($tok ?? '', $token);
    $msg = $ok ? 'You are already unsubscribed.' : 'Token mismatch.';
  }
  $stmt->close();

  if (!$ok) {
    // Insert new
    $stmt = $con->prepare("INSERT INTO email_unsubscribed (email, token) VALUES (?, ?) ON DUPLICATE KEY UPDATE token=VALUES(token)");
    $stmt->bind_param('ss', $email, $token);
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
      <div class="card-header"><strong>Lakeview — Unsubscribe</strong></div>
      <div class="card-body">
        <div class="alert alert-<?= $ok ? 'success' : 'danger' ?>"><?= h($msg) ?></div>
        <p class="mb-0 small text-muted"><?= h($email) ?></p>
      </div>
    </div>
  </div></div>
</div>
</body></html>
