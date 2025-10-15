<?php
declare(strict_types=1);
session_start();

/**
 * NotesAO – Start Enrollment
 * - Default plan: p200 ($200/mo)
 * - Negotiated pages: start-100.php, start-120.php, start-140.php, start-150.php (all route here)
 * - Also supports ?plan=p100|p120|p140|p150|p200
 *
 * Square:
 *   - Use Subscriptions with TWO PHASES configured in Square Catalog:
 *       Phase 1: Onboarding fee (1 period, charges now)
 *       Phase 2: Monthly price (recurs monthly)
 *   - Put each plan's CatalogObject ID below in $PLANS[...] ['square_plan_id']
 *
 * ENV required:
 *   SQUARE_ACCESS_TOKEN  = <your production token>
 *   SQUARE_ENV           = production | sandbox   (default: production)
 */

$secrets = require '/home/notesao/secure/notesao_secrets.php';

// Merge fixed plan metadata (labels) with Square plan IDs from secrets
$PLAN_META = [
  'p100' => ['label' => '$100 / month', 'monthly_cents' => 10000],
  'p120' => ['label' => '$120 / month', 'monthly_cents' => 12000],
  'p140' => ['label' => '$140 / month', 'monthly_cents' => 14000],
  'p150' => ['label' => '$150 / month', 'monthly_cents' => 15000],
  'p200' => ['label' => '$200 / month', 'monthly_cents' => 20000],
];

$PLANS = [];
foreach ($PLAN_META as $code => $meta) {
  $PLANS[$code] = $meta + [
    'square_plan_id' => $secrets['PLANS'][$code]['square_plan_id'] ?? ''
  ];
}

$SQUARE_ENV          = $secrets['SQUARE_ENV'];           // 'sandbox'
$SQUARE_ACCESS_TOKEN = $secrets['SQUARE_ACCESS_TOKEN'];  // your sandbox token
$SQUARE_API_BASE     = $secrets['SQUARE_API_BASE'];      // sandbox URL

$planId = $PLANS[$planCode]['square_plan_id'] ?? '';
$canCheckout = $planId !== '';



// Optional: map “nice” onboarding copy to show on page (amount is defined in Square Phase 1)
$ONBOARDING_COPY = 'Pay your onboarding fee today, then your subscription begins automatically.';

// Where to return after Square checkout completes/succeeds
$RETURN_BASE = 'https://enroll.notesao.com/thank-you.php';


// ---- Determine selected plan ------------------------------------------------
$defaultPlan = 'p200';
$planCode = $defaultPlan;

// 1) Allow ?plan=p100|p120|...
if (isset($_GET['plan']) && array_key_exists($_GET['plan'], $PLANS)) {
  $planCode = $_GET['plan'];
}

// 2) Allow negotiated-page filenames like start-100.php → p100
$scriptBase = basename($_SERVER['SCRIPT_NAME'], '.php');           // e.g., 'start-100'
if (preg_match('/^start\-(\d{3})$/', $scriptBase, $m)) {
  $maybe = 'p' . $m[1];
  if (array_key_exists($maybe, $PLANS)) $planCode = $maybe;
}

$plan = $PLANS[$planCode];

// ---- CSRF helpers -----------------------------------------------------------
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
function csrf_check(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
      http_response_code(400);
      exit('Invalid request.');
    }
  }
}

// ---- Handle form POST → Create a Square subscription checkout link ----------
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // (Optional) collect lead info to prefill buyer; do not require
  $buyer_email = trim($_POST['buyer_email'] ?? '');
  $buyer_name  = trim($_POST['buyer_name'] ?? '');
  $company     = trim($_POST['company'] ?? '');
  $phone       = trim($_POST['phone'] ?? '');

  // Build redirect with lightweight state
  $qs = http_build_query([
    'plan'    => $planCode,
    'email'   => $buyer_email,
    'company' => $company
  ]);
  $redirectUrl = $RETURN_BASE . '?' . $qs;

  // Assemble payload for CreatePaymentLink (subscription plan)
  $payload = [
    'idempotency_key' => bin2hex(random_bytes(16)),
    'subscription_plan_id' => $plan['square_plan_id'],
    'checkout_options' => [
      'redirect_url' => $redirectUrl,
    ],
    'pre_populated_data' => array_filter([
      'buyer_email_address' => $buyer_email ?: null,
      'buyer_phone_number'  => $phone ?: null,
      // Square currently ignores name fields for subscription links; still pass if supported later
      'buyer_address'       => null,
    ]),
  ];

  // Call Square
  $ch = curl_init($SQUARE_API_BASE . "/v2/online-checkout/payment-links");
  curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
      "Authorization: Bearer {$SQUARE_ACCESS_TOKEN}",
      "Content-Type: application/json",
      // Pin a stable Square-Version your account supports; update periodically
      "Square-Version: 2025-08-15"
    ],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
  ]);
  $res  = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($res === false || $http >= 400) {
    $errors[] = "Checkout link error. HTTP {$http}" . ($err ? " – {$err}" : '');
  } else {
    $data = json_decode($res, true);
    $url  = $data['payment_link']['url'] ?? '';
    if ($url) {
      header("Location: {$url}", true, 302);
      exit;
    }
    $errors[] = "Checkout link not returned by Square.";
  }
}

// -------------------------- HTML begins -------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Start Enrollment • NotesAO</title>

  <!-- Favicons / Fonts / Bootstrap (match notesao.com) -->
  <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
  <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">
  <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
  <link rel="manifest" href="/favicons/site.webmanifest">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">

  <style>
    /* Base – mirror site styles */
    *{box-sizing:border-box} body{font-family:'Montserrat',sans-serif;color:#333;background:#f7f9fa;line-height:1.6}
    a{text-decoration:none;color:inherit}
    h1,h2,h3{font-weight:700}
    .site-header{background:#fff;padding:.75rem 2rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.06);position:fixed;top:0;left:0;right:0;z-index:999}
    .site-header .logo img{height:40px}
    .nav-list{display:flex;gap:1.5rem;list-style:none;margin:0;padding:0}
    .nav-list a{font-weight:500}
    main{margin-top:80px}
    /* Hero */
    .hero{display:flex;align-items:center;justify-content:space-between;padding:4rem 2rem;background:#eef3f8}
    .hero h1{font-size:2.2rem;margin-bottom:.5rem}
    .hero p{color:#555}
    .hero .price-pill{display:inline-block;background:#211c56;color:#fff;border-radius:999px;padding:.35rem .75rem;font-weight:600;font-size:.95rem;margin-right:.5rem}
    .panel{background:#fff;border:1px solid #e6e6f0;border-radius:8px;box-shadow:0 10px 24px rgba(0,0,0,.06)}
    .panel .panel-hd{padding:1rem 1.25rem;border-bottom:1px solid #eee}
    .panel .panel-bd{padding:1.25rem}
    .btn-brand{background:#211c56;color:#fff}
    .btn-brand:hover{background:#38308f;color:#fff}
    footer{background:#1c1c1c;color:#fff;padding:2rem;text-align:center;margin-top:2rem}
    footer a{color:#ccc;margin:0 .5rem}
    footer a:hover{color:#fff}
    @media(max-width:768px){.hero{flex-direction:column;text-align:center}}
    .small-muted{color:#6b7280;font-size:.9rem}
    .err{background:#ffe8e8;border:1px solid #ffbcbc;color:#7a1b1b;padding:.75rem 1rem;border-radius:6px}
  </style>
</head>
<body>
<header class="site-header">
  <a class="logo" href="https://notesao.com/"><img src="/assets/images/NotesAO Logo.png" alt="NotesAO logo"></a>
  <nav><ul class="nav-list">
    <li><a href="https://notesao.com/#features">Features</a></li>
    <li><a href="https://notesao.com/login.php">Login</a></li>
    <li><a href="https://notesao.com/signup.php">Schedule a Consultation</a></li>
  </ul></nav>
</header>

<main>
  <!-- HERO -->
  <section class="hero">
    <div class="hero-text" style="max-width:640px">
      <span class="price-pill"><?=htmlspecialchars($plan['label'])?></span>
      <h1>Start Your NotesAO Enrollment</h1>
      <p><?=$ONBOARDING_COPY?></p>
    </div>
    <div class="hero-image" style="flex:1;display:flex;justify-content:center">
      <img src="/assets/images/hero-illustration.png" alt="NotesAO Enrollment" style="max-width:420px">
    </div>
  </section>

  <div class="container my-4">
    <!-- Checkout panel -->
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="panel">
          <div class="panel-hd">
            <strong>Contact Details</strong>
          </div>
          <div class="panel-bd">
            <?php if ($errors): ?>
              <div class="err mb-3">
                <?=implode('<br>', array_map('htmlspecialchars', $errors))?>
              </div>
            <?php endif; ?>

            <!-- Updated: ids, required, validation; posts to agreement.php -->
            <form id="enrollForm" method="post" action="agreement.php" autocomplete="on" class="row g-3">
              <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'])?>">
              <input type="hidden" name="plan" value="<?=htmlspecialchars($planCode)?>">

              <div class="col-md-6">
                <label class="form-label">Work Email</label>
                <input class="form-control"
                       type="email"
                       name="buyer_email"
                       id="buyer_email"
                       placeholder="you@organization.com"
                       required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Contact Name</label>
                <input class="form-control"
                       type="text"
                       name="buyer_name"
                       id="buyer_name"
                       placeholder="First Last"
                       required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Organization / Clinic</label>
                <input class="form-control"
                       type="text"
                       name="company"
                       id="company"
                       placeholder="Clinic / Agency"
                       required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Phone (required)</label>
                <input class="form-control"
                       type="tel"
                       name="phone"
                       id="phone"
                       placeholder="(555) 555-1234"
                       inputmode="tel"
                       pattern="^[0-9().+\-\s]{10,}$"
                       title="Enter at least 10 digits (you can include spaces, dashes, dots, or parentheses)."
                       required>
              </div>

              <div class="col-12 d-flex align-items-center gap-3 mt-2">
                <!-- Updated: disabled until valid -->
                <button id="startBtn"
                        class="btn btn-brand btn-lg"
                        type="submit"
                        disabled>
                  Start Enrollment
                </button>
                <a class="btn btn-outline-secondary btn-lg" href="https://notesao.com/signup.php">Schedule a Consultation</a>
              </div>

              <p class="small-muted mt-3 mb-0">
                You’ll be redirected to our secure Square checkout. Your card will be charged the onboarding fee today.
                Your <?=htmlspecialchars($plan['label'])?> subscription will begin automatically on the schedule shown at checkout.
              </p>
            </form>
          </div>
        </div>
      </div>

      <!-- Summary / trust -->
      <div class="col-lg-5">
        <div class="panel mb-4">
          <div class="panel-hd"><strong>What you get</strong></div>
          <div class="panel-bd">
            <ul class="mb-2">
              <li>Secure portal for clinic operations</li>
              <li>Automated documentation & reporting</li>
              <li>Attendance & session management</li>
              <li>Compliance-ready exports</li>
            </ul>
            <div class="small-muted">Need help? Email <a href="mailto:sales@notesao.com">sales@notesao.com</a>.</div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-hd"><strong>Plan</strong></div>
          <div class="panel-bd">
            <div class="d-flex justify-content-between">
              <span>Selected tier</span><strong><?=htmlspecialchars($plan['label'])?></strong>
            </div>
            <div class="small-muted mt-2"><?=$ONBOARDING_COPY?></div>
          </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

<!-- Enable Start Enrollment only when all 4 fields are valid -->
<script>
(function(){
  const form    = document.getElementById('enrollForm');
  const email   = document.getElementById('buyer_email');
  const nameEl  = document.getElementById('buyer_name');
  const company = document.getElementById('company');
  const phone   = document.getElementById('phone');
  const btn     = document.getElementById('startBtn');

  function emailValid(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
  function phoneValid(v){ return v.replace(/[^\d]/g,'').length >= 10; }

  function update(){
    const ok = emailValid(email.value.trim())
            && nameEl.value.trim().length > 1
            && company.value.trim().length > 1
            && phoneValid(phone.value.trim());
    btn.disabled = !ok;
  }

  ['input','change','blur'].forEach(ev=>{
    email.addEventListener(ev, update);
    nameEl.addEventListener(ev, update);
    company.addEventListener(ev, update);
    phone.addEventListener(ev, update);
  });

  // Block accidental submit if invalid
  form.addEventListener('submit', function(e){
    update();
    if (btn.disabled) e.preventDefault();
  });

  update(); // initial state
})();
</script>
</body>
</html>
