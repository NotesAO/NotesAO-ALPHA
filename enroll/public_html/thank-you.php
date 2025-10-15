<?php
declare(strict_types=1);
session_start();

$secrets = require '/home/notesao/secure/notesao_secrets.php';
require __DIR__ . '/../config/config.php';

// --- Square helper (fix: pull version from $secrets)
function httpSquare(string $method, string $url, string $token, $body = null): array {
  global $secrets;
  $ver = $secrets['SQUARE_API_VERSION'] ?? '2025-07-16';
  $ch = curl_init($url);
  $hdr = [
    'Content-Type: application/json',
    'Square-Version: '.$ver,
    'Authorization: Bearer '.$token
  ];
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER    => $hdr,
    CURLOPT_POSTFIELDS    => $body ? json_encode($body) : null,
    CURLOPT_RETURNTRANSFER=> true,
    CURLOPT_TIMEOUT       => 20
  ]);
  $res  = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$http, json_decode($res, true) ?: []];
}

$ctx       = $_SESSION['enroll'] ?? null;   // name/email/company/phone
$planCode  = $_GET['plan'] ?? ($_SESSION['enroll']['plan'] ?? 'p200');
$consentId = (int)($_GET['consent'] ?? 0);
$subId     = $_GET['sub'] ?? '';

$PLANS     = $secrets['PLANS'] ?? [];
$plan      = $PLANS[$planCode] ?? ($PLANS['p200'] ?? ['label'=>'$200 / month','onboarding_cents'=>50000]);

// Pull acceptance row for PDF & payment id
$pdfUrl = null;
// Pull acceptance row for payment reference (no need to build $pdfUrl here)
$paymentId = null;
try {
  if ($consentId > 0) {
    $stmt = $db->prepare("SELECT square_payment_id FROM clinic_agreement_acceptance WHERE id=? LIMIT 1");
    $stmt->execute([$consentId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $paymentId = $row['square_payment_id'] ?? null;
    }
  }
} catch (Throwable $e) { /* non-fatal */ }


// Optional: receipt URL
$receiptUrl = null;
try {
  if ($paymentId) {
    $API = rtrim($secrets['SQUARE_API_BASE'],'/');
    $AT  = $secrets['SQUARE_ACCESS_TOKEN'];
    [$h,$d] = httpSquare('GET', $API.'/v2/payments/'.rawurlencode($paymentId), $AT);
    if ($h === 200 && !empty($d['payment']['receipt_url'])) $receiptUrl = $d['payment']['receipt_url'];
  }
} catch (Throwable $e) {}

// Optional: subscription status
$subStatus = null;
$nextBill  = null;
try {
  if ($subId) {
    $API = rtrim($secrets['SQUARE_API_BASE'],'/');
    $AT  = $secrets['SQUARE_ACCESS_TOKEN'];
    [$h,$d] = httpSquare('GET', $API.'/v2/subscriptions/'.rawurlencode($subId), $AT);
    if ($h === 200 && !empty($d['subscription'])) {
      $sub = $d['subscription'];
      $subStatus = $sub['status'] ?? null;
      $nextBill  = $sub['charged_through_date'] ?? ($sub['start_date'] ?? null);
    }
  }
} catch (Throwable $e) {}

// Fallback bill date (first of next month, merchant TZ)
if (!$nextBill) {
  $tz = new DateTimeZone($secrets['TIMEZONE'] ?? 'America/Chicago');
  $start = new DateTime('first day of next month', $tz);
  $nextBill = $start->format('Y-m-d');
}

$onboardUsd = number_format(($plan['onboarding_cents'] ?? 50000)/100, 2);
$planLabel  = $plan['label'] ?? '$200 / month';

// --- One-time confirmation email (idempotent by session flag)
$sentKey = 'sent_confirm_'.$consentId;
if ($ctx && !empty($ctx['email']) && $consentId && empty($_SESSION[$sentKey])) {
  $to   = $ctx['email'];
  $subj = "Welcome to NotesAO — Enrollment confirmed";
  $loginUrl = 'https://notesao.com/login.php';
  $pdfLink  = $pdfUrl ? ('<p><a href="'.htmlspecialchars($pdfUrl).'">Download your signed agreement (PDF)</a></p>') : '';
  $receipt  = $receiptUrl ? ('<p><a href="'.htmlspecialchars($receiptUrl).'" target="_blank" rel="noopener">View payment receipt</a></p>') : '';

  $html = '
  <div style="font-family:Montserrat,Arial,sans-serif;max-width:640px;margin:auto;padding:24px;background:#f7f9fa">
    <div style="background:#fff;border:1px solid #e6e6f0;border-radius:8px;padding:24px">
      <img src="https://notesao.com/assets/images/NotesAO%20Logo.png" alt="NotesAO" style="height:36px;margin-bottom:12px">
      <h2 style="margin:0 0 8px">Enrollment complete</h2>
      <p style="color:#555;margin:0 0 16px">Thanks for enrolling! We have your signed agreement'.($consentId ? ' (Reference #'.$consentId.')' : '').'.</p>

      <div style="border:1px solid #eee;border-radius:6px;padding:12px;margin:12px 0">
        <div style="display:flex;justify-content:space-between"><span>Onboarding fee</span><strong>$'.$onboardUsd.'</strong></div>
        <div style="display:flex;justify-content:space-between"><span>Subscription</span><strong>'.htmlspecialchars($planLabel).'</strong></div>
        <div style="display:flex;justify-content:space-between"><span>Next bill date</span><strong>'.htmlspecialchars($nextBill).'</strong></div>'.
        ($subId ? '<div style="display:flex;justify-content:space-between"><span>Subscription ID</span><code>'.$subId.'</code></div>' : '').
      '</div>

      <p><a href="'.$loginUrl.'" style="display:inline-block;background:#211c56;color:#fff;padding:10px 14px;border-radius:6px;text-decoration:none">Go to Login</a></p>'.
      $receipt.$pdfLink.'
      <p style="color:#6b7280;font-size:13px;margin-top:16px">Need help? Email <a href="mailto:support@notesao.com">support@notesao.com</a>.</p>
    </div>
  </div>';

  $headers = [];
  $headers[] = 'MIME-Version: 1.0';
  $headers[] = 'Content-type: text/html; charset=UTF-8';
  $headers[] = 'From: NotesAO <noreply@notesao.com>';
  $headers[] = 'Reply-To: NotesAO Support <support@notesao.com>';

  // fire & forget; don’t block the page if mail fails
  @mail($to, $subj, $html, implode("\r\n",$headers));
  $_SESSION[$sentKey] = 1;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Enrollment Complete • NotesAO</title>
  <link rel="icon" href="https://notesao.com/favicons/favicon.ico">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    *{box-sizing:border-box}
    html,body{height:100%}
    body{min-height:100vh;display:flex;flex-direction:column;font-family:'Montserrat',sans-serif;color:#333;background:#f7f9fa;line-height:1.6}
    a{text-decoration:none;color:inherit}
    h1,h2,h3{font-weight:700}

    .site-header{background:#fff;padding:.75rem 2rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.06);position:fixed;top:0;left:0;right:0;z-index:999}
    .site-header .logo img{height:40px}
    .nav-list{display:flex;gap:1.5rem;list-style:none;margin:0;padding:0}
    .nav-list a{font-weight:500}
    main{flex:1;margin-top:80px}

    .panel{background:#fff;border:1px solid #e6e6f0;border-radius:8px;box-shadow:0 10px 24px rgba(0,0,0,.06)}
    .btn-brand{background:#211c56;color:#fff}.btn-brand:hover{background:#38308f}
    .small-muted{color:#6b7280;font-size:.9rem}

    footer{background:#1c1c1c;color:#fff;padding:2rem;text-align:center}
    footer a{color:#ccc;margin:0 .5rem}
    footer a:hover{color:#fff}
    @media(max-width:768px){ .nav-list{gap:.9rem} }
    .ref{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace}
  </style>
</head>
<body>
<header class="site-header">
  <a class="logo" href="https://notesao.com/"><img src="https://notesao.com/assets/images/NotesAO%20Logo.png" alt="NotesAO logo"></a>
  <nav><ul class="nav-list">
    <li><a href="https://notesao.com/#features">Features</a></li>
    <li><a href="https://notesao.com/login.php">Login</a></li>
    <li><a href="https://notesao.com/signup.php">Schedule a Consultation</a></li>
  </ul></nav>
</header>

<main>
  <div class="container my-4">
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="panel p-4">
          <h1 class="h4 mb-2">Enrollment complete</h1>
          <p class="small-muted mb-3">
            Thanks for enrolling! We’ve saved your signed agreement<?php if ($consentId) echo ' (Reference #<span class="ref">'.$consentId.'</span>)'; ?> and set up your billing.
          </p>

          <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between"><span>Onboarding fee</span><strong>$<?=$onboardUsd?></strong></div>
            <div class="d-flex justify-content-between"><span>Subscription</span><strong><?=htmlspecialchars($planLabel)?></strong></div>
            <div class="d-flex justify-content-between"><span>Next bill date</span><strong><?=htmlspecialchars($nextBill)?></strong></div>
            <?php if ($subId): ?>
              <div class="d-flex justify-content-between"><span>Subscription ID</span><span class="ref"><?=htmlspecialchars($subId)?></span></div>
            <?php endif; ?>
          </div>

          <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-brand" href="https://notesao.com/login.php">Go to Login</a>
            <?php if ($receiptUrl): ?>
              <a class="btn btn-outline-secondary" href="<?=htmlspecialchars($receiptUrl)?>" target="_blank" rel="noopener">View Payment Receipt</a>
            <?php endif; ?>
            <?php if ($pdfUrl): ?>
              <a class="btn btn-outline-secondary" href="/print_eua.php" target="_blank" rel="noopener">
                Download Agreement (PDF)
              </a>

            <?php endif; ?>
          </div>

          <?php if ($subStatus): ?>
            <p class="small-muted mt-3 mb-0">Subscription status: <strong><?=htmlspecialchars($subStatus)?></strong>.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="panel p-4">
          <h2 class="h6">Contact on file</h2>
          <?php if ($ctx): ?>
            <div><?=htmlspecialchars($ctx['name'] ?? '')?></div>
            <div><?=htmlspecialchars($ctx['email'] ?? '')?></div>
            <div><?=htmlspecialchars($ctx['company'] ?? '')?> <?=htmlspecialchars(!empty($ctx['phone']) ? ' • '.$ctx['phone'] : '')?></div>
          <?php else: ?>
            <div class="small-muted">Session details unavailable.</div>
          <?php endif; ?>
          <hr>
          <p class="small-muted mb-0">A confirmation email will follow. If you need any help, contact <a href="mailto:support@notesao.com">support@notesao.com</a>.</p>
        </div>
      </div>
    </div>
  </div>
</main>

<footer>
  <div class="footer-links">
    <a href="https://notesao.com/">Home</a> •
    <a href="https://notesao.com/login.php">Login</a> •
    <a href="https://notesao.com/legal/privacy.html">Privacy</a> •
    <a href="https://notesao.com/legal/terms.html">Terms</a>
  </div>
  <p>© <?=date('Y')?> NotesAO. All rights reserved.</p>
</footer>
</body>
</html>
