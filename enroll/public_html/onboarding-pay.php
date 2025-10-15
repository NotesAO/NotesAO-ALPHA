<?php
declare(strict_types=1);
session_start();

$secrets = require '/home/notesao/secure/notesao_secrets.php';
require __DIR__ . '/../config/config.php';

$ctx = $_SESSION['enroll'] ?? null;
if (!$ctx) { header('Location: /start.php'); exit; }

$planCode   = $_GET['plan'] ?? ($_SESSION['enroll']['plan'] ?? 'p200');
$consentId  = (int)($_GET['consent'] ?? 0);
$plan       = $secrets['PLANS'][$planCode] ?? $secrets['PLANS']['p200'];

$onboardUsd = number_format(($plan['onboarding_cents'] ?? 50000)/100, 2);

// First day of next month in America/Chicago
$tz = new DateTimeZone($secrets['TIMEZONE'] ?? 'America/Chicago');
$start = new DateTime('now', $tz);
$start->modify('first day of next month');
$start_date = $start->format('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Onboarding Payment • NotesAO</title>

  <!-- Favicons (match agreement.php) -->
  <link rel="icon" href="https://notesao.com/favicons/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="https://notesao.com/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="https://notesao.com/favicons/favicon-16x16.png">
  <link rel="manifest" href="https://notesao.com/favicons/site.webmanifest">

  <!-- Fonts & Bootstrap -->
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* Base – mirror agreement.php */
    *{box-sizing:border-box}
    html, body { height: 100%; }
    body{
      font-family:'Montserrat',sans-serif;
      color:#333; background:#f7f9fa; line-height:1.6;
      display:flex; flex-direction:column; /* sticky footer layout */
    }
    a{text-decoration:none;color:inherit}
    h1,h2,h3{font-weight:700}

    /* Fixed header */
    .site-header{
      background:#fff;padding:.75rem 2rem;display:flex;justify-content:space-between;align-items:center;
      box-shadow:0 2px 8px rgba(0,0,0,.06);position:fixed;top:0;left:0;right:0;z-index:999
    }
    .site-header .logo img{height:40px}
    .nav-list{display:flex;gap:1.5rem;list-style:none;margin:0;padding:0}
    .nav-list a{font-weight:500}

    /* Main + panels */
    main{ margin-top:80px; flex: 1 0 auto; } /* push footer to bottom if short */
    .panel{background:#fff;border:1px solid #e6e6f0;border-radius:8px;box-shadow:0 10px 24px rgba(0,0,0,.06)}
    .btn-brand{background:#211c56;color:#fff}.btn-brand:hover{background:#38308f}
    .small-muted{color:#6b7280;font-size:.9rem}

    /* Footer sticks to bottom */
    footer{background:#1c1c1c;color:#fff;padding:2rem;text-align:center;margin-top:2rem}
    footer a{color:#ccc;margin:0 .5rem}
    footer a:hover{color:#fff}

    @media(max-width:768px){ .nav-list{gap:.9rem} }
  </style>
</head>
<body>

<header class="site-header">
  <a class="logo" href="https://notesao.com/">
    <img src="https://notesao.com/assets/images/NotesAO Logo.png" alt="NotesAO logo">
  </a>
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
        <div class="panel p-3">
          <h1 class="h4 mb-3">Onboarding Payment</h1>
          <p class="small-muted mb-3">
            We’ll charge your saved card <strong>$<?= $onboardUsd ?></strong> now for the one-time onboarding fee.
            Your <?= htmlspecialchars($plan['label'] ?? '$200 / month') ?> subscription will start on
            <strong><?= htmlspecialchars($start_date) ?></strong> and then bill monthly.
          </p>
          <button id="go" class="btn btn-brand btn-lg">Charge & Start Subscription</button>
          <div id="err" class="text-danger mt-2" style="display:none"></div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="panel p-3">
          <h2 class="h6">Contact on File</h2>
          <div><?= htmlspecialchars($ctx['name']) ?></div>
          <div><?= htmlspecialchars($ctx['email']) ?></div>
          <div><?= htmlspecialchars($ctx['company']) ?> <?= htmlspecialchars($ctx['phone'] ? ' • '.$ctx['phone'] : '') ?></div>
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
  <p>© <?= date('Y') ?> NotesAO. All rights reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
(() => {
  const btn = document.getElementById('go');
  const err = document.getElementById('err');

  btn.addEventListener('click', async () => {
    err.style.display='none'; err.textContent='';
    btn.disabled = true; const old = btn.textContent; btn.textContent = 'Processing…';

    try {
      const res = await fetch('/api/charge_onboarding_and_subscribe.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ plan: "<?= htmlspecialchars($planCode) ?>", consent_id: <?= (int)$consentId ?> })
      });
      const data = await res.json();
      if (data.ok) {
        window.location = '/thank-you.php?plan=<?= urlencode($planCode) ?>&consent=<?= urlencode((string)$consentId) ?>&sub=' + encodeURIComponent(data.subscription_id);
      } else {
        throw new Error(data.error || 'Unable to complete payment.');
      }
    } catch (e) {
      err.textContent = e && e.message ? e.message : 'Unable to complete payment.';
      err.style.display='block';
      btn.disabled = false; btn.textContent = old;
    }
  });
})();
</script>
</body>
</html>
