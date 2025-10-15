<?php
// --------------------------------------------------
// NotesAO – Public Signup Form (lead capture)
// --------------------------------------------------
// * 100 % static e-mail (no DB) – keeps things simple.
// * Visuals & typography match index.html + login.php.
// * On success, sends the lead to admin@notesao.com and
//   shows a friendly confirmation.
// --------------------------------------------------

// === CONFIG =============================================================
$admin_email = 'admin@notesao.com';   // destination for all form entries
$sales_email = 'sales@notesao.com';
$from_email  = 'no-reply@notesao.com';// envelope sender (helps SPF/DKIM)

$recaptcha_site_key = "6LdY4oErAAAAAERQSs57zvI-_6H8D9yGcHYQFvlJ";
$recaptcha_secret   = "6LdY4oErAAAAAA8Y5niZLvgv9PAt8gFfOhU6vQLb";

$success     = false;
$errors      = [];

// Pre-declare variables so they exist for the first GET load
$name = $clinic = $clients = $software = $email = $phone = $message = '';

$logFile = __DIR__ . '/signup.log';
file_put_contents($logFile, date('c') . ' – ' . $_SERVER['REMOTE_ADDR'] .
                   ' – ' . json_encode($_POST) . PHP_EOL, FILE_APPEND | LOCK_EX);


// === HANDLE POST ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // tiny helper
    $g = fn(string $key) => trim($_POST[$key] ?? '');

    // gather
    $name     = $g('name');
    $clinic   = $g('clinic');
    $clients  = $g('clients');
    $software = $g('software');
    $email    = $g('email');
    $phone    = $g('phone');
    $message  = $g('message');

    // --- reCAPTCHA check -------------------------------------------------
    $captcha_resp = $g('g-recaptcha-response');
    $captcha_ok   = false;

    if ($captcha_resp !== '') {
        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $params     = http_build_query([
            'secret'   => $recaptcha_secret,
            'response' => $captcha_resp,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
        $json = @file_get_contents("$verify_url?$params");
        $captcha_ok = $json && (json_decode($json, true)['success'] ?? false);
    }

    if (!$captcha_ok) {
        $errors['recaptcha'] = 'Please complete the CAPTCHA.';
    }
    // --- honeypot check ----------------------------------------------------
    if ($g('website') !== '') {          // field should be empty
        exit;                            // stop processing – treat as spam
    }


    // validate
    if ($name === '')                       $errors['name']    = 'Required.';
    if ($clinic === '')                     $errors['clinic']  = 'Required.';
    if ($clients === '' || !ctype_digit($clients) || (int)$clients < 1)
                                            $errors['clients'] = 'Enter a positive number.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                                            $errors['email']   = 'Invalid e-mail.';

    // send e-mail
    if (!$errors) {
        $subject = 'New NotesAO Interest Lead';

        $submitted = date('Y-m-d H:i:s');
        $body = <<<EMAIL
Name:       $name
Clinic:     $clinic
# Clients:  $clients
Software:   $software
E-Mail:     $email
Phone:      $phone

Message:
$message

EMAIL;

        $headers  = "From: $from_email\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "Content-Type: text/plain; charset=utf-8\r\n";

        @mail($admin_email, $subject, $body, $headers);
        @mail($sales_email, $subject, $body, $headers);
        $success = true;

        // ------------------------------------------------------------------
        // 2)  thank-you mail to visitor  (HTML, branded)
        // ------------------------------------------------------------------
        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $user_subject = 'Thank you for contacting NotesAO';

        $user_body = <<<HTML
<!doctype html>
<html>
<head><meta charset="utf-8"><title>NotesAO – Thank You</title></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:Montserrat,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td align="center" style="padding:40px 16px;">
        <table width="600" cellpadding="0" cellspacing="0"
               style="background:#fff;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,.08);">
          <tr>
            <td align="center" style="padding:32px 32px 24px;">
              <img src="https://notesao.com/assets/images/NotesAO%20Logo.png"
                   alt="NotesAO Logo" style="height:48px;margin-bottom:20px">
              <h1 style="margin:0;font-size:22px;color:#211c56;">Thank you for reaching out!</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:0 48px 32px;font-size:15px;line-height:1.6;color:#444;">
              <p style="margin-top:0;">Hi <strong>{$safeName}</strong>,</p>
              <p>Thanks for your interest in <strong>NotesAO</strong>. A member of our sales
              team will review the details you provided and get in touch shortly to answer
              questions and schedule a live demo.</p>
              <p>We look forward to showing you how NotesAO can streamline documentation,
              automate reporting, and simplify your workflow.</p>
              <p style="margin:28px 0 0;">Warm regards,<br>The NotesAO Team</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="background:#211c56;color:#fff;padding:14px;
                                      border-bottom-left-radius:8px;border-bottom-right-radius:8px;
                                      font-size:13px;">
              NotesAO — Streamlining Therapeutic Documentation
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $user_headers  = "MIME-Version: 1.0\r\n";
        $user_headers .= "Content-Type: text/html; charset=utf-8\r\n";
        $user_headers .= "From: NotesAO <{$from_email}>\r\n";
        $user_headers .= "Reply-To: support@notesao.com\r\n";

        @mail($email, $user_subject, $user_body, $user_headers, "-f $from_email");
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="description"
        content="NotesAO: Streamline your therapeutic documentation. Automate reports, manage clients, and track attendance."/>
  <title>NotesAO Sign Up</title>

  <script src="https://www.google.com/recaptcha/api.js" async defer></script>


  <!-- Favicons -->
  <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
  <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">
  <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">
  <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
  <link rel="manifest" href="/favicons/site.webmanifest">
  <meta name="apple-mobile-web-app-title" content="NotesAO">

  <!-- Fonts / Icons / Bootstrap -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
        referrerpolicy="no-referrer">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet" crossorigin="anonymous">

  <style>
    /* ─────────── BASE + PAGE STYLES (from index.html) ─────────── */
    *{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'Montserrat',sans-serif;color:#333;background:#f7f9fa;line-height:1.6;display:flex;flex-direction:column;min-height:100vh;}
    a{text-decoration:none;color:inherit;} img{max-width:100%;height:auto;}
    h1,h2,h3{font-weight:700;} p{font-size:1rem;}

    /* ========= SHARED HEADER ========= */
    .site-header{
      background:#fff;padding:.75rem 2rem;
      display:flex;justify-content:space-between;align-items:center;
      box-shadow:0 2px 8px rgba(0,0,0,.06);
      position:fixed;top:0;left:0;right:0;z-index:999;
    }
    .site-header .logo img{height:40px}
    .nav-list{display:flex;gap:1.5rem;list-style:none;margin:0;padding:0}
    .nav-list>li{position:relative;display:flex;align-items:center;}
    .nav-list>li:last-child .dropdown-menu{left:auto;right:0;}
    .nav-list a,.drop-toggle{
      font-weight:500;color:#333;background:none;border:none;
      text-decoration:none;font-family:inherit;cursor:pointer;
      padding:.25rem .1rem;transition:color .3s;}
    .nav-list a:hover,.drop-toggle:hover{color:#211c56}

    /* Dropdown */
    .dropdown{position:relative}
    .dropdown-menu{
      position:absolute;left:0;top:100%;min-width:11rem;
      background:#fff;border:1px solid #e5e5e5;border-radius:6px;
      box-shadow:0 4px 12px rgba(0,0,0,.08);
      display:none;flex-direction:column;padding:.5rem 0;z-index:1000;}
    .dropdown.open>.dropdown-menu{display:flex;}
    .dropdown-menu a{padding:.35rem 1rem;white-space:nowrap}
    .dropdown-menu a:hover{background:#f7f9fa}
    .drop-toggle:focus-visible{outline:2px solid #007BFF;outline-offset:2px}

    /* Offset for fixed header */
    main{margin-top:80px;flex:1;display:flex;align-items:center;justify-content:center;padding:2rem;}

    /* ========= SIGN-UP CARD ========= */
    .card{
      border:none;border-radius:15px;
      box-shadow:0 8px 16px rgba(0,0,0,.1);
      width:100%;max-width:720px;
      overflow:hidden;
    }
    .card-header{background:#211c56;color:#fff;text-align:center;padding:2rem 1rem;}
    .card-header h2{font-size:1.75rem;margin-bottom:.25rem}
    .card-header p{margin:0;color:#d1d1f4}
    .btn-brand{background:#211c56;color:#fff;font-weight:600;border:none;}
    .btn-brand:hover{background:rgb(56,48,143)}
    .form-control:focus{box-shadow:0 0 0 .2rem rgba(33,28,86,.25);border-color:#211c56}

    /* Success alert */
    .alert-success{background:#e6fff3;border:1px solid #a8f0c2;}

    /* ─── Cookie-consent (shared) ─── */
    #cookie-bar{
      position:fixed;left:0;right:0;bottom:0;z-index:1000;
      background:#0c0a24;color:#fff;box-shadow:0 -4px 12px rgba(0,0,0,.15);
      transform:translateY(100%);transition:transform .4s ease;
      padding:1rem 1.5rem;display:flex;flex-wrap:wrap;gap:1rem;font-size:.95rem;line-height:1.5;
    }
    #cookie-bar.show{transform:translateY(0);}
    #cookie-bar button{border:none;border-radius:4px;padding:.5rem 1.25rem;font-weight:600;cursor:pointer;}
    #btn-accept{background:#00d08b;color:#000;}
    #btn-reject{background:#f8f9fa;color:#211c56;}
    #btn-settings{background:#ffc107;color:#211c56;}
    a.cookie-policy{color:#fff;text-decoration:underline;}
    #cookie-fab{
      position:fixed;bottom:1rem;right:1rem;z-index:1000;display:none;
      width:44px;height:44px;border-radius:50%;background:#0c0a24;color:#fff;font-size:20px;
      align-items:center;justify-content:center;cursor:pointer;
      box-shadow:0 2px 6px rgba(0,0,0,.2);
    }
    #cookie-fab.show{display:flex;}

    /* Footer */
    footer{background:#1c1c1c;color:#fff;padding:2rem;text-align:center;margin-top:2rem;}
    footer .footer-links{margin-bottom:1rem;}
    footer .footer-links a{margin:0 1rem;color:#ccc;font-size:.9rem;transition:color .3s;}
    footer .footer-links a:hover{color:#fff;} footer p{font-size:.9rem;color:#ccc;}
  </style>
</head>

<body>
<!-- ───────── HEADER (identical to index.html) ───────── -->
<header class="site-header">
  <a href="/" class="logo"><img src="/assets/images/NotesAO Logo.png" alt="NotesAO logo"></a>

  <nav class="main-nav" role="navigation" aria-label="Primary">
    <ul class="nav-list">
      <!-- Home dropdown -->
      <li class="dropdown">
        <button class="drop-toggle" aria-expanded="false">Home</button>
        <ul class="dropdown-menu">
          <li><a href="/#features">Features</a></li>
          <li><a href="/#testimonials">Testimonials</a></li>
        </ul>
      </li>

      <li><a href="/login.php">Login</a></li>
      <li><a href="/signup.php">Sign&nbsp;Up</a></li>

      <!-- Legal dropdown -->
      <li class="dropdown">
        <button class="drop-toggle" aria-expanded="false">Legal</button>
        <ul class="dropdown-menu">
          <li><a href="/legal/privacy.html">Privacy&nbsp;Policy</a></li>
          <li><a href="/legal/terms.html">Terms&nbsp;of&nbsp;Service</a></li>
          <li><a href="/legal/accessibility.html">Accessibility</a></li>
          <li><a href="/legal/security.html">Security</a></li>
        </ul>
      </li>
    </ul>
  </nav>
</header>

<!-- ───────── COOKIE CONSENT BAR (shared) ───────── -->
<div id="cookie-bar" role="dialog" aria-label="Cookie consent">
  <span>We use cookies to keep you signed in and to improve your experience. Choose an option:</span>
  <div class="ms-auto d-flex gap-2 flex-wrap">
    <button id="btn-reject">Reject non-essential</button>
    <button id="btn-settings">Customise</button>
    <button id="btn-accept">Accept all</button>
  </div>
  <a href="/legal/privacy.html" class="cookie-policy ms-3">Privacy policy</a>
</div>
<div id="cookie-fab" title="Cookie settings"><i class="fas fa-cookie-bite"></i></div>

<!-- Settings Modal -->
<div class="modal fade" id="cookieModal" tabindex="-1" aria-labelledby="cookieModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-light">
        <h5 class="modal-title" id="cookieModalLabel">Customise cookies</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small">Strictly-necessary cookies are always on (they keep you logged-in and secure).</p>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="ck-analytics">
          <label class="form-check-label fw-semibold" for="ck-analytics">Analytics cookies</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="ck-marketing">
          <label class="form-check-label fw-semibold" for="ck-marketing">Marketing / remarketing cookies</label>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-brand" id="btn-save-pref">Save preferences</button>
      </div>
    </div>
  </div>
</div>

<!-- ───────── MAIN CONTENT ───────── -->
<main class="d-flex align-items-center justify-content-center flex-column py-5">
<?php if ($success): ?>
  <div class="alert alert-success text-center shadow-sm p-5" role="alert" style="max-width:600px;">
    <h4 class="alert-heading mb-3"><i class="fas fa-check-circle me-2"></i>Thank you!</h4>
    <p>Your details have been received – we’ll be in touch shortly.</p>
    <hr>
    <a href="/" class="btn btn-brand mt-2 px-4">Back to Home</a>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-header">
      <h2>Register Your Interest</h2>
      <p class="small">Tell us a little about your practice and we’ll reach out.</p>
    </div>

    <div class="card">

      <!-- Flyer Info Block -->
      <div class="p-3 text-center bg-light border-bottom">
        <img src="/NAOflyer.png" alt="NotesAO Flyer" class="img-fluid mb-2" style="max-height:300px; object-fit:contain;">
        <div>
          <a href="/NAOflyer.png" download class="btn btn-brand btn-sm">
            <i class="fas fa-download me-1"></i> Download Flyer
          </a>
        </div>
      </div>


    <div class="card-body p-4">
      <form method="post" novalidate class="needs-validation">

        <!-- ROW 1 -->
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Your Name <span class="text-danger">*</span></label>
            <input type="text" name="name"
                  value="<?=htmlspecialchars($name)?>"
                  class="form-control<?=isset($errors['name']) ? ' is-invalid' : ''?>" required>
            <?php if(isset($errors['name'])): ?>
              <div class="invalid-feedback"><?=$errors['name']?></div>
            <?php endif; ?>
          </div>

          <div class="col-md-6">
            <label class="form-label">Clinic / Organization <span class="text-danger">*</span></label>
            <input type="text" name="clinic"
                  value="<?=htmlspecialchars($clinic)?>"
                  class="form-control<?=isset($errors['clinic']) ? ' is-invalid' : ''?>" required>
            <?php if(isset($errors['clinic'])): ?>
              <div class="invalid-feedback"><?=$errors['clinic']?></div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ROW 2 -->
        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label class="form-label"># Clients Served <span class="text-danger">*</span></label>
            <input type="number" name="clients" min="1"
                  value="<?=htmlspecialchars($clients)?>"
                  class="form-control<?=isset($errors['clients']) ? ' is-invalid' : ''?>" required>
            <?php if(isset($errors['clients'])): ?>
              <div class="invalid-feedback"><?=$errors['clients']?></div>
            <?php endif; ?>
          </div>

          <div class="col-md-6">
            <label class="form-label">Current Software</label>
            <input type="text" name="software"
                  value="<?=htmlspecialchars($software)?>"
                  class="form-control">
          </div>
        </div>

        <!-- ROW 3 -->
        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <label class="form-label">E-Mail <span class="text-danger">*</span></label>
            <input type="email" name="email"
                  value="<?=htmlspecialchars($email)?>"
                  class="form-control<?=isset($errors['email']) ? ' is-invalid' : ''?>" required>
            <?php if(isset($errors['email'])): ?>
              <div class="invalid-feedback"><?=$errors['email']?></div>
            <?php endif; ?>
          </div>

          <div class="col-md-6">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone"
                  value="<?=htmlspecialchars($phone)?>"
                  class="form-control">
          </div>
        </div>

        <!-- MESSAGE -->
        <div class="mt-3">
          <label class="form-label">Anything else you’d like us to know?</label>
          <textarea name="message" rows="4" class="form-control"><?=htmlspecialchars($message)?></textarea>
        </div>

        <!-- Honeypot: must stay empty -->
        <input type="text" name="website" style="display:none">

        <!-- reCAPTCHA -->
        <div class="g-recaptcha mt-3" data-sitekey="<?=$recaptcha_site_key?>"></div>
        <?php if(isset($errors['recaptcha'])): ?>
          <div class="invalid-feedback d-block"><?=$errors['recaptcha']?></div>
        <?php endif; ?>

        <!-- SUBMIT -->
        <div class="text-center mt-4">
          <button class="btn btn-brand px-5" type="submit">
            <i class="fas fa-paper-plane me-2"></i>Submit
          </button>
        </div>

      </form>
    </div>
  </div>

<?php endif; ?>
</main>

<!-- ───────── FOOTER ───────── -->
<footer>
  <div class="footer-links">
    <a href="/">Home</a>
    <a href="/login.php">Login</a>
    <a href="/signup.php">Sign Up</a>
    <a href="/legal/privacy.html">Privacy Policy</a>
    <a href="/legal/terms.html">Terms of Service</a>
    <a href="/legal/accessibility.html">Accessibility</a>
    <a href="/legal/security.html">Security</a>
  </div>
  <p>© 2025 NotesAO. All rights reserved.</p>
</footer>

<!-- ───────── SCRIPTS ───────── -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
<script defer src="/assets/js/nav.js"></script> <!-- dropdown behaviour -->

<script>
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  forms.forEach(f => f.addEventListener('submit', e => {
    if (!f.checkValidity()) {           // HTML5 validation failed
      e.preventDefault();               // stop form
      e.stopPropagation();
    }
    f.classList.add('was-validated');   // add Bootstrap styling
  }));
})();
</script>


<!-- Cookie-consent logic (shared) -->
<script>
(() => {
  const key  = 'notesaoConsentV1';
  const bar  = document.getElementById('cookie-bar');
  const fab  = document.getElementById('cookie-fab');
  const modal= new bootstrap.Modal(document.getElementById('cookieModal'));
  const save = p=>localStorage.setItem(key,JSON.stringify(p));
  const load = ()=>JSON.parse(localStorage.getItem(key)||'{}');

  const pref = load();
  if(!pref.choice){bar.classList.add('show');}
  else{fab.classList.add('show');applyConsent(pref);}

  document.getElementById('btn-accept').onclick=()=>{
    const p={choice:'all',analytics:true,marketing:true};
    save(p);bar.classList.remove('show');fab.classList.add('show');applyConsent(p);
  };
  document.getElementById('btn-reject').onclick=()=>{
    const p={choice:'necessary',analytics:false,marketing:false};
    save(p);bar.classList.remove('show');fab.classList.add('show');applyConsent(p);
  };
  document.getElementById('btn-settings').onclick=()=>{
    document.getElementById('ck-analytics').checked=!!pref.analytics;
    document.getElementById('ck-marketing').checked=!!pref.marketing;
    modal.show();
  };
  document.getElementById('btn-save-pref').onclick=()=>{
    const p={
      choice:'custom',
      analytics:document.getElementById('ck-analytics').checked,
      marketing:document.getElementById('ck-marketing').checked
    };
    save(p);modal.hide();bar.classList.remove('show');fab.classList.add('show');applyConsent(p);
  };
  fab.onclick=()=>modal.show();

  function applyConsent(p){
    /* hook GA/FB loaders here */
  }
})();
</script>
</body>
</html>
