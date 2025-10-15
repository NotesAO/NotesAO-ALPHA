<?php
declare(strict_types=1);
session_start();

/**
 * NotesAO – Agreement & Authorization
 * - Fixed header & footer (matches start.php vibe)
 * - Reads hardcoded EUA HTML from /_agreements/eua-2025-08-29.html
 *   • If the file contains a full <html> page, extracts just <body>...</body>
 * - Ensures a matching version row exists in consent_document (auto-bumps -vN if content changed)
 * - Captures checkboxes, typed name, sign date, and drawn signature
 * - Generates a PDF (dompdf if available; falls back gracefully)
 * - Creates a Square subscription checkout link (Phase 1 onboarding, Phase 2 monthly)
 */

// --- bootstrap / deps --------------------------------------------------------
$secrets = require '/home/notesao/secure/notesao_secrets.php';  // provides SQUARE_* and PLANS
require __DIR__ . '/../config/config.php';                      // defines $db (PDO)

// optional composer autoload (for dompdf)
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($composerAutoload)) { require $composerAutoload; }

// --- helpers -----------------------------------------------------------------
function inet6_aton(string $ip): string { return @inet_pton($ip) ?: str_repeat("\0", 16); }
function sha256(string $s): string { return hash('sha256', $s); }
function valid_date(string $s): bool {
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return false;
  [$y,$m,$d] = array_map('intval', explode('-', $s));
  return checkdate($m,$d,$y);
}

// --- CSRF --------------------------------------------------------------------
if (empty($_SESSION['csrf_agree'])) {
  $_SESSION['csrf_agree'] = bin2hex(random_bytes(16));
}
function csrf_check(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept'])) {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_agree'] ?? '', $_POST['csrf'])) {
      http_response_code(400);
      exit('Invalid request.');
    }
  }
}

// --- Square env --------------------------------------------------------------
$SQUARE_API_BASE     = $secrets['SQUARE_API_BASE'];
$SQUARE_ACCESS_TOKEN = $secrets['SQUARE_ACCESS_TOKEN'];
$PLANS               = $secrets['PLANS'];

// --- resolve plan + carry contact fields from start.php ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan']) && !isset($_POST['accept'])) {
  $_SESSION['enroll'] = [
    'plan'    => $_POST['plan'],
    'email'   => trim($_POST['buyer_email'] ?? ''),
    'name'    => trim($_POST['buyer_name'] ?? ''),
    'company' => trim($_POST['company'] ?? ''),
    'phone'   => trim($_POST['phone'] ?? ''),
  ];
  header('Location: '.$_SERVER['PHP_SELF']); exit;
}

$ctx  = $_SESSION['enroll'] ?? null;
if (!$ctx) { header('Location: /start.php'); exit; }

$planCode   = $ctx['plan'];
$planVarId  = $PLANS[$planCode]['square_plan_variation_id'] ?? '';
$planOk     = !empty($planVarId);

$planLabels = [

  'p100'=>'$100 / month','p120'=>'$120 / month','p140'=>'$140 / month','p150'=>'$150 / month','p200'=>'$200 / month'
];
$planLabel = $planLabels[$planCode] ?? 'Unknown';

// --- EUA: hardcoded file + DB versioning ------------------------------------
$EUA_FILE    = __DIR__.'/_agreements/eua-2025-08-29.html'; // hardcoded
$EUA_VERSION = 'EUA-2025-08-29';                            // base version label

// Ensure consent_document exists
$db->exec("
  CREATE TABLE IF NOT EXISTS consent_document (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doc_type ENUM('EUA','PAYMENT_AUTH') NOT NULL,
    version_label VARCHAR(32) NOT NULL,
    html MEDIUMTEXT NOT NULL,
    sha256 CHAR(64) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_type_ver (doc_type, version_label)
  ) ENGINE=InnoDB;
");

// helper to find next -vN suffix if needed
$nextVersion = function(PDO $db, string $base) : string {
  $ver = $base; $n = 2;
  $stmt = $db->prepare("SELECT 1 FROM consent_document WHERE doc_type='EUA' AND version_label=? LIMIT 1");
  while (true) {
    $stmt->execute([$ver]);
    if (!$stmt->fetchColumn()) return $ver;
    $ver = $base.'-v'.$n++;
  }
};

if (!is_file($EUA_FILE)) { http_response_code(500); exit('EUA file missing: '.$EUA_FILE); }
$euaHtml = file_get_contents($EUA_FILE);
if ($euaHtml === false || trim($euaHtml) === '') { http_response_code(500); exit('EUA file unreadable/empty'); }
// If full HTML, extract body content only
if (preg_match('/<body[^>]*>(.*)<\/body>/is', $euaHtml, $m)) {
  $euaHtml = trim($m[1]);
}
$fileSha = sha256($euaHtml);

// try load requested version
$stmt = $db->prepare("SELECT id, version_label, html, sha256
                      FROM consent_document
                      WHERE doc_type='EUA' AND version_label=? LIMIT 1");
$stmt->execute([$EUA_VERSION]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
  $ins = $db->prepare("INSERT INTO consent_document (doc_type,version_label,html,sha256)
                       VALUES ('EUA',?,?,?)");
  $ins->execute([$EUA_VERSION, $euaHtml, $fileSha]);
  $doc = ['id' => (int)$db->lastInsertId(), 'version_label'=>$EUA_VERSION, 'html'=>$euaHtml, 'sha256'=>$fileSha];
} else {
  // content changed after same-label update → bump -vN
  if (!hash_equals($doc['sha256'], $fileSha)) {
    $bumped = $nextVersion($db, $EUA_VERSION);
    $ins = $db->prepare("INSERT INTO consent_document (doc_type,version_label,html,sha256)
                         VALUES ('EUA',?,?,?)");
    $ins->execute([$bumped, $euaHtml, $fileSha]);
    $doc = ['id' => (int)$db->lastInsertId(), 'version_label'=>$bumped, 'html'=>$euaHtml, 'sha256'=>$fileSha];
  }
}

// --- POST accept -> save signature, pdf, DB; then create Square link ----------
$errors = [];

// Guard: plan configured in secrets?
if (!$planOk) {
  $errors[] = 'This plan is not configured for checkout yet. Please contact support.';
}


if (isset($_POST['accept'])) {
  csrf_check();

  $agreedEua  = isset($_POST['agree_eua']);
  $agreedPay  = isset($_POST['agree_payment']);
  $typedName  = trim($_POST['typed_name'] ?? '');
  $sigDataUrl = $_POST['sig_data'] ?? '';
  $signDate   = trim($_POST['sign_date'] ?? '');

  if ($signDate === '') { $signDate = date('Y-m-d'); }

  // Validate inputs
  if (!$agreedEua || !$agreedPay) $errors[] = 'Please agree to both checkboxes.';
  if ($typedName === '')          $errors[] = 'Please type your full name.';
  if (!valid_date($signDate))     $errors[] = 'Please provide a valid sign date (YYYY-MM-DD).';
  if (strtotime($signDate) > time() + 60) $errors[] = 'Sign date cannot be in the future.';
  if (strpos($sigDataUrl, 'data:image/png;base64,') !== 0) $errors[] = 'Signature is required.';

  if (!$errors) {
    // Save signature PNG
    $sigBin = base64_decode(substr($sigDataUrl, 22));
    $stamp  = date('Ymd_His');
    @mkdir(__DIR__.'/_agreements/sig', 0750, true);
    @mkdir(__DIR__.'/_agreements/pdf', 0750, true);
    $sigPath = "_agreements/sig/sig_{$stamp}.png";
    file_put_contents(__DIR__.'/'.$sigPath, $sigBin);

    // Compose printable HTML (header + EUA body + signer block)
    $html  = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,Arial} .meta{color:#555;font-size:12px}</style></head><body>';
    $html .= '<h2 style="margin:0">NotesAO End User Agreement</h2>';
    $html .= '<div class="meta">Version: '.htmlspecialchars($doc['version_label']).' • Signed: '.htmlspecialchars($signDate).' '.date('H:i:s').'</div><hr>';
    $html .= '<div>'.$doc['html'].'</div>';
    $html .= '<hr><h3>Agreement & Authorization</h3>';
    $html .= '<p><strong>Card Authorization:</strong> I authorize NotesAO (Free for Life Group PC) to charge the payment method I provide for the one-time onboarding fee now and for the recurring '
          . htmlspecialchars($planLabel)
          . ' subscription, until I cancel in accordance with the Terms.</p>';
    $html .= '<p><strong>Signer:</strong> '.htmlspecialchars($typedName).' • '.htmlspecialchars($ctx['email']).' • '.htmlspecialchars($ctx['company']).'</p>';
    $html .= '<p><strong>Sign Date:</strong> '.htmlspecialchars($signDate).'</p>';
    $html .= '<p><img src="'.$sigPath.'" style="height:80px;border:1px solid #888;padding:4px" alt="signature"> <br>Signature captured on '.htmlspecialchars($signDate).' '.date('H:i:s').'</p>';
    $html .= '</body></html>';

    // Generate PDF (dompdf if available; else fallback to HTML + wkhtmltopdf if present)
    $pdfPath = "_agreements/pdf/eua_{$stamp}.pdf";
    $pdfAbs  = __DIR__.'/'.$pdfPath;
    $usedFallback = false;

    if (class_exists('\Dompdf\Dompdf')) {
      $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled'=>true]);
      $dompdf->loadHtml($html);
      $dompdf->setPaper('Letter','portrait');
      $dompdf->render();
      file_put_contents($pdfAbs, $dompdf->output());
    } else {
      $usedFallback = true;
      $htmlTmp = __DIR__."/_agreements/pdf/eua_{$stamp}.html";
      file_put_contents($htmlTmp, $html);
      @exec('which wkhtmltopdf', $out, $code);
      if ($code === 0) {
        @exec('wkhtmltopdf '.escapeshellarg($htmlTmp).' '.escapeshellarg($pdfAbs));
        $usedFallback = !is_file($pdfAbs);
      }
    }
    $pdfSha = is_file($pdfAbs) ? sha256(file_get_contents($pdfAbs)) : sha256($html);

    // Insert acceptance
    $signedAt = $signDate.' '.date('H:i:s'); // store user-entered date with current time
    $stmt = $db->prepare("INSERT INTO clinic_agreement_acceptance
      (clinic_id, plan_code, signer_name, signer_email, organization, phone, doc_id, agreed_eua, agreed_payment, ip_address, user_agent, signed_at, signature_path, pdf_path, pdf_sha256)
      VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
      $planCode, $typedName, $ctx['email'], $ctx['company'], $ctx['phone'],
      $doc['id'], $agreedEua?1:0, $agreedPay?1:0,
      inet6_aton($_SERVER['REMOTE_ADDR'] ?? ''), substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,255),
      $signedAt, $sigPath, $pdfPath, $pdfSha
    ]);
    $consentId = (int)$db->lastInsertId();

    // >>> NEW: send them to the Card Authorization step
    $query = http_build_query(['plan' => $planCode, 'consent' => $consentId]);

    // Use a relative path since card-auth.php is in the same docroot:
    header('Location: card-auth.php?' . $query);

    // If you prefer absolute from domain root, use:
    // header('Location: /card-auth.php?' . $query);

    exit;

    
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Agreement • NotesAO</title>
  <link rel="icon" href="https://notesao.com/favicons/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="https://notesao.com/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="https://notesao.com/favicons/favicon-16x16.png">
  <link rel="manifest" href="https://notesao.com/favicons/site.webmanifest">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Base – mirror site styles */
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
    .scrollbox{height:320px;overflow:auto;border:1px solid #e5e7eb;border-radius:6px;padding:1rem;background:#fff}
    .sig-pad{border:1px dashed #9ca3af;border-radius:6px;background:#fff;height:140px}
    .btn-brand{background:#211c56;color:#fff}.btn-brand:hover{background:#38308f}
    .small-muted{color:#6b7280;font-size:.9rem}

    footer{background:#1c1c1c;color:#fff;padding:2rem;text-align:center;margin-top:2rem}
    footer a{color:#ccc;margin:0 .5rem}
    footer a:hover{color:#fff}

    @media(max-width:768px){ .nav-list{gap:.9rem} }

    /* ---------------- EUA content overrides inside #euaBox ---------------- */
    #euaBox{font-size:.95rem;line-height:1.55}
    #euaBox p{margin:.45rem 0}

    /* Links: make TOC + body links blue & underlined */
    #euaBox a, #euaBox .toc a{color:#1d4ed8;text-decoration:underline}
    #euaBox a:hover{ text-decoration:underline }

    /* Tighten headings */
    #euaBox h1{font-size:1.25rem;margin:1rem 0 .4rem}
    #euaBox h2{font-size:1.10rem;margin:.8rem 0 .35rem;padding-top:.35rem;border-top:1px solid #eee}
    #euaBox h3{font-size:1rem;margin:.6rem 0 .3rem;color:#1f2937}

    /* If the embedded EUA includes its own wrapper (#eua-root .doc), override it explicitly */
    #euaBox #eua-root .doc p{font-size:.95rem !important;line-height:1.55 !important;margin:.45rem 0 !important;font-family:inherit !important}
    #euaBox #eua-root .doc h1{font-size:1.25rem !important;margin:1rem 0 .4rem !important}
    #euaBox #eua-root .doc h2{font-size:1.10rem !important;margin:.8rem 0 .35rem !important;padding-top:.35rem !important;border-top:1px solid #eee !important}
    #euaBox #eua-root .doc h3{font-size:1rem !important;margin:.6rem 0 .3rem !important;color:#1f2937 !important}

    /* Table of contents; remove duplicate numbering/bullets */
    #euaBox .toc{margin:.6rem 0 .8rem;padding:.5rem .75rem;background:#fafafa;border:1px solid #e5e7eb;border-radius:6px}
    #euaBox .toc ol, #euaBox .toc ul{list-style:none;padding-left:0;margin:0}
    #euaBox .toc li{margin:.15rem 0}

    /* Hide filler lines some editors leave behind */
    #euaBox p.blank{display:none}
  </style>

</head>
<body>

<header class="site-header">
  <a class="logo" href="https://notesao.com/"><img src="https://notesao.com/assets/images/NotesAO Logo.png" alt="NotesAO logo"></a>
  <nav><ul class="nav-list">
    <li><a href="https://notesao.com/#features">Features</a></li>
    <li><a href="https://notesao.com/login.php">Login</a></li>
    <li><a href="https://notesao.com/signup.php">Schedule a Consultation</a></li>
  </ul></nav>
</header>

<main>
  <div class="container my-4">
    <h1 class="h3 mb-3">Agreement & Authorization</h1>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="panel p-3">
          <?php if ($errors): ?>
            <div class="alert alert-danger"><?php echo implode('<br>', array_map('htmlspecialchars',$errors)); ?></div>
          <?php endif; ?>
          <div class="mb-2 small-muted">Plan: <strong><?php echo htmlspecialchars($planLabel); ?></strong></div>
          <div class="scrollbox mb-3" id="euaBox">
            <?php echo $doc['html']; ?>
          </div>
          <form method="post" onsubmit="return captureSig()">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf_agree']); ?>">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="agree_eua" name="agree_eua" required>
              <label class="form-check-label" for="agree_eua">I have read and agree to the End User Agreement.</label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="agree_payment" name="agree_payment" required>
              <label class="form-check-label" for="agree_payment">I authorize recurring billing for the selected plan and the one-time onboarding fee.</label>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Type your name (signature text)</label>
                <input class="form-control" name="typed_name" value="<?php echo htmlspecialchars($ctx['name']); ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Sign Date</label>
                <input class="form-control" type="date" name="sign_date" value="<?php echo htmlspecialchars(date('Y-m-d')); ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label">Signature (draw)</label>
                <canvas id="sig" class="sig-pad w-100"></canvas>
                <div class="mt-2"><button class="btn btn-sm btn-outline-secondary" type="button" onclick="sigPad.clear()">Clear</button></div>
              </div>
            </div>
            <input type="hidden" name="sig_data" id="sig_data">
            <button class="btn btn-brand btn-lg" name="accept" value="1" type="submit" <?= $planOk ? '' : 'disabled'; ?>>

                Agree & Continue to Card Authorization
            </button>

            <?php if(!$planOk): ?>
              <div class="small-muted mt-2">
                Configure the <code>square_plan_variation_id</code> for
                <?php echo htmlspecialchars($planCode); ?> in <code>notesao_secrets.php</code>.
              </div>
            <?php endif; ?>

            <a class="btn btn-outline-secondary btn-lg ms-2"
                href="/print_eua.php" target="_blank" rel="noopener">
                Print / Save PDF
            </a>
          </form>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="panel p-3">
          <h2 class="h6">Contact on File</h2>
          <div><?php echo htmlspecialchars($ctx['name']); ?></div>
          <div><?php echo htmlspecialchars($ctx['email']); ?></div>
          <div><?php echo htmlspecialchars($ctx['company']); ?> <?php echo htmlspecialchars($ctx['phone'] ? ' • '.$ctx['phone'] : ''); ?></div>
          <hr>
          <p class="small-muted mb-0">Next you’ll authorize your card securely. Card numbers never hit our servers.</p>

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
const canvas = document.getElementById('sig');
function fitCanvas() { canvas.width = canvas.offsetWidth; canvas.height = 140; }
window.addEventListener('resize', fitCanvas); fitCanvas();
const sigPad = new SignaturePad(canvas, { minWidth: 0.8, maxWidth: 2.5 });
function captureSig() {
  if (sigPad.isEmpty()) { alert('Please draw your signature.'); return false; }
  document.getElementById('sig_data').value = sigPad.toDataURL('image/png');
  return true;
}
</script>
<script>
(function(){
  const box = document.getElementById('euaBox');
  if (!box) return;
  box.querySelectorAll('p').forEach(p=>{
    const t = (p.textContent || '').replace(/\u00a0/g,' ').trim();
    if (!t || t === '—' || t === '-' || t === '|') p.style.display = 'none';
  });
})();
</script>

</body>
</html>
