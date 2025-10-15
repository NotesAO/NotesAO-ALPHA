<?php
declare(strict_types=1);
session_start();

$secrets = require '/home/notesao/secure/notesao_secrets.php';
require __DIR__ . '/../config/config.php';

$ctx = $_SESSION['enroll'] ?? null;
if (!$ctx) { header('Location: /start.php'); exit; }

$planCode   = $_GET['plan'] ?? ($_SESSION['enroll']['plan'] ?? 'p200');
$consentId  = (int)($_GET['consent'] ?? 0);
if ($consentId <= 0) { header('Location: /start.php'); exit; }

$PLANS      = $secrets['PLANS'] ?? [];
$plan       = $PLANS[$planCode] ?? ($PLANS['p200'] ?? ['label'=>'$200 / month']);

$appId      = $secrets['SQUARE_APPLICATION_ID'] ?? '';
$locationId = $secrets['SQUARE_LOCATION_ID'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Card Authorization • NotesAO</title>
  <link rel="icon" href="https://notesao.com/favicons/favicon.ico">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* Base – mirror site styles (same as agreement.php) */
    *{box-sizing:border-box}
    body{font-family:'Montserrat',sans-serif;color:#333;background:#f7f9fa;line-height:1.6}
    a{text-decoration:none;color:inherit}
    h1,h2,h3{font-weight:700}

    /* Fixed header */
    .site-header{background:#fff;padding:.75rem 2rem;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.06);position:fixed;top:0;left:0;right:0;z-index:999}
    .site-header .logo img{height:40px}
    .nav-list{display:flex;gap:1.5rem;list-style:none;margin:0;padding:0}
    .nav-list a{font-weight:500}
    main{margin-top:80px}

    .panel{background:#fff;border:1px solid #e6e6f0;border-radius:8px;box-shadow:0 10px 24px rgba(0,0,0,.06)}
    .sig-pad{border:1px dashed #9ca3af;border-radius:6px;background:#fff;height:140px}
    .btn-brand{background:#211c56;color:#fff}.btn-brand:hover{background:#38308f}
    .small-muted{color:#6b7280;font-size:.9rem}

    footer{background:#1c1c1c;color:#fff;padding:2rem;text-align:center;margin-top:2rem}
    footer a{color:#ccc;margin:0 .5rem}
    footer a:hover{color:#fff}
    @media(max-width:768px){ .nav-list{gap:.9rem} }
  </style>

  <script src="https://sandbox.web.squarecdn.com/v1/square.js" crossorigin="anonymous"></script>

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
        <div class="panel p-3">
          <h1 class="h4 mb-3">Card on File Authorization</h1>
          <p class="small-muted mb-2">
            This authorizes NotesAO to store your card for recurring billing of your selected plan
            (<?php echo htmlspecialchars($plan['label'] ?? '$200 / month'); ?>).
            You will review and pay the onboarding fee on the next screen.
          </p>

          <form id="authForm" class="row g-3" onsubmit="return false;">
            <!-- agreement checkbox -->
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="agree_cpf" required>
                <label class="form-check-label" for="agree_cpf">
                  I authorize NotesAO to save my card on file and charge it for the
                  <?php echo htmlspecialchars($plan['label'] ?? '$200 / month'); ?> subscription
                  until I cancel according to the Terms.
                </label>
              </div>
            </div>

            <!-- name + date -->
            <div class="col-md-6">
              <label class="form-label">Cardholder Name</label>
              <input class="form-control" id="cardholder_name" placeholder="Name on card" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Sign Date</label>
              <input class="form-control" id="sign_date" type="date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
            </div>

            

            <!-- Square card element -->
            <div class="col-12">
              <label class="form-label">Card</label>
              <div id="card-container" class="form-control" style="height:auto;padding:.75rem"></div>
            </div>

            <!-- signature -->
            <div class="col-12">
              <label class="form-label">Signature (draw)</label>
              <canvas id="sig" class="sig-pad w-100"></canvas>
              <div class="mt-2">
                <button id="clearSig" class="btn btn-sm btn-outline-secondary" type="button">Clear</button>
              </div>
            </div>

            

            <input type="hidden" id="sig_data">
            <button id="submitBtn" class="btn btn-brand btn-lg" type="button">
              Authorize Card on File
            </button>
            <div id="err" class="text-danger mt-2" style="display:none"></div>
          </form>

          <p class="small-muted mt-3 mb-0">
            This form mirrors a standard credit-card authorization form used to keep a card on file.
          </p>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="panel p-3">
          <h2 class="h6">Contact on File</h2>
          <div><?php echo htmlspecialchars($ctx['name']); ?></div>
          <div><?php echo htmlspecialchars($ctx['email']); ?></div>
          <div><?php echo htmlspecialchars($ctx['company']); ?> <?php echo htmlspecialchars($ctx['phone'] ? ' • '.$ctx['phone'] : ''); ?></div>
          <hr>
          <p class="small-muted mb-0">We tokenize the card in your browser and send it to Square. Card numbers never hit our servers.</p>
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
  <p>© <?php echo date('Y'); ?> NotesAO. All rights reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
const appId = "<?=htmlspecialchars($appId)?>";
const locationId = "<?=htmlspecialchars($locationId)?>";
const planCode = "<?=htmlspecialchars($planCode)?>";
const consentId = <?= (int)$consentId ?>;
const buyerEmail = "<?=htmlspecialchars($ctx['email'] ?? '')?>";

// Signature pad
const canvas = document.getElementById('sig');
function fitCanvas(){ canvas.width = canvas.offsetWidth; canvas.height = 140; }
addEventListener('resize', fitCanvas); fitCanvas();
const sigPad = new SignaturePad(canvas, { minWidth: 0.8, maxWidth: 2.5 });
document.getElementById('clearSig').onclick = ()=> sigPad.clear();

(async function init(){
  const err = document.getElementById('err');
  if (!appId || !locationId) {
    err.style.display='block';
    err.textContent = 'Square configuration is missing. Please contact support.';
    return;
  }
  if (!window.Square) {
    err.style.display='block';
    err.textContent='Square SDK failed to load.';
    return;
  }

  try {
    const payments = window.Square.payments(appId, locationId);
    const card = await payments.card();
    await card.attach('#card-container');

    const btn = document.getElementById('submitBtn');
    btn.addEventListener('click', async () => {
    err.style.display='none'; err.textContent = '';

    const name = document.getElementById('cardholder_name').value.trim();
    const zipEl  = document.getElementById('postal_code');
    const ctryEl = document.getElementById('country');
    const zip  = (zipEl  ? zipEl.value  : '').trim();
    const ctry = (ctryEl ? ctryEl.value : 'US').trim();
    

    const date = document.getElementById('sign_date').value;

    if (!document.getElementById('agree_cpf').checked || !name || !date || sigPad.isEmpty()) {
        err.textContent = 'Please complete the authorization, name, signature, and date.';
        err.style.display='block'; return;
    }

    document.getElementById('sig_data').value = sigPad.toDataURL('image/png');

    btn.disabled = true; const old = btn.textContent; btn.textContent = 'Authorizing…';

    // Tokenize the card with billing ZIP
    // Build verification details for "STORE" (card on file)
    const nameParts = name.split(' ').filter(Boolean);
    const verificationDetails = {
        intent: 'STORE',
        customerInitiated: true,
        sellerKeyedIn: false,          // set true if staff is keying for the buyer
        billingContact: {
            givenName: nameParts[0] || 'Customer',
            familyName: nameParts.slice(1).join(' ') || undefined,
            email: buyerEmail || undefined,
            countryCode: (ctry || 'US').toUpperCase(),
            postalCode: zip || undefined, // include if you collect it
        },
    };



    // 1) Tokenize
    const result = await card.tokenize(verificationDetails);
        if (result.status !== 'OK') {
        btn.disabled = false; btn.textContent = old;
        err.textContent = (result.errors && result.errors[0] && result.errors[0].message) || 'Card tokenization failed.';
        err.style.display='block'; return;
    }


    // 2) (Often needed in Sandbox/EMEA) Verify buyer to get a verification token
    let verificationToken = null;
    try {
        const vr = await payments.verifyBuyer(result.token, verificationDetails);
        if (vr && vr.status === 'OK') verificationToken = vr.token;
    } catch (e) {
        console.debug('tokenize status:', result.status, 'verificationToken:', verificationToken);

    }

    // Save card on file (no charge here)
    const resp = await fetch('/api/save_card_only.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            plan: planCode,
            consent_id: consentId,
            card_token: result.token,
            verification_token: verificationToken || null,
            cardholder_name: name,
            billing_postal_code: zip || null,
            country: ctry || 'US',
            sign_date: date,
            sig_data: document.getElementById('sig_data').value
        })
    });

    // Try to parse JSON either way
    let data = null;
    let text = '';
    try { text = await resp.text(); data = JSON.parse(text); } catch (_) { /* keep text for error */ }

    if (!resp.ok || !data || data.ok !== true) {
        btn.disabled = false; btn.textContent = old;
        err.textContent = (data && data.error) || text || 'Unable to save card on file.';
        err.style.display = 'block';
        console.error('save_card_only.php error', { status: resp.status, body: text });
        return;
    }



    // Next step: onboarding payment + subscription
    window.location.href =
        '/onboarding-pay.php?plan=' + encodeURIComponent(planCode) +
        '&consent=' + encodeURIComponent(consentId);
    });


  } catch (e) {
    err.style.display='block';
    err.textContent = 'Unable to initialize payments. ' + (e && e.message ? e.message : '');
  }
})();
</script>
</body>
</html>
