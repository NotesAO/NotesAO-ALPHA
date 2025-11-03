<?php
/**
 * evaluations-dwii-b.php — DWII Individual Session (between weeks 11 and 12)
 * One page per field. Progress bar. CSRF. Prepared INSERT.
 *
 * • GET  : render form
 * • POST : CSRF → validate → INSERT into evaluations_dwii_b → thank-you page
 */

declare(strict_types=1);
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../config/config.php';   // provides $link (mysqli)
/** @var mysqli $link */
$link->set_charset('utf8mb4');

/* ------------------------------ bootstrap table ------------------------------ */
$link->query("
CREATE TABLE IF NOT EXISTS evaluations_dwii_b (
  id                             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at                     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  first_name                     VARCHAR(190) NOT NULL,
  last_name                      VARCHAR(190) NOT NULL,
  email                          VARCHAR(190) NOT NULL,
  today_date                     DATE         NOT NULL,
  po_attorney                    VARCHAR(255) NOT NULL,  -- 'Name of probation officer, attorney, etc'
  workbook_up_to_date            TINYINT(1)   NOT NULL,  -- 1=yes 0=no
  aa_attended                    TINYINT(1)   NOT NULL,  -- 1=yes 0=no
  aa_when_where                  TEXT         NOT NULL,  -- When/where + experience
  aa_continue                    TINYINT(1)   NOT NULL,  -- 1=yes 0=no
  still_using                    TEXT         NOT NULL,  -- confidentiality note in label
  repeatedly_tried_quit_failed   TINYINT(1)   NOT NULL,  -- 1=yes 0=no
  need_additional_help           TINYINT(1)   NOT NULL,  -- 1=yes 0=no
  lifestyle_changes              TEXT         NOT NULL,
  stress_techniques              TEXT         NOT NULL,
  submit_ip                      VARBINARY(16) NULL,
  user_agent                     VARCHAR(255)  NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* --------------------------------- helpers --------------------------------- */
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void {
  if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    http_response_code(403); exit('Invalid CSRF token');
  }
}
function inet_pton_nullable(string $ip) { $bin = @inet_pton($ip); return $bin === false ? null : $bin; }

/* ---------------------------------- POST ---------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $first   = trim((string)($_POST['first_name'] ?? ''));
  $last    = trim((string)($_POST['last_name'] ?? ''));
  $email   = trim((string)($_POST['email'] ?? ''));
  $tday    = trim((string)($_POST['today_date'] ?? ''));
  $poatt   = trim((string)($_POST['po_attorney'] ?? ''));
  $wbu     = (string)($_POST['workbook_up_to_date'] ?? '');
  $aaAtt   = (string)($_POST['aa_attended'] ?? '');
  $aaWhen  = trim((string)($_POST['aa_when_where'] ?? ''));
  $aaCont  = (string)($_POST['aa_continue'] ?? '');
  $using   = trim((string)($_POST['still_using'] ?? ''));
  $quit    = (string)($_POST['repeatedly_tried_quit_failed'] ?? '');
  $need    = (string)($_POST['need_additional_help'] ?? '');
  $lifechg = trim((string)($_POST['lifestyle_changes'] ?? ''));
  $stress  = trim((string)($_POST['stress_techniques'] ?? ''));

  $errors = [];
  if ($first === '') $errors[] = 'First name required.';
  if ($last === '')  $errors[] = 'Last name required.';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tday)) $errors[] = 'Valid date required.';
  if ($poatt === '') $errors[] = 'Name of probation officer, attorney, etc. required. Enter NA if not applicable.';
  if (!in_array($wbu,   ['0','1'], true)) $errors[] = 'Workbook status required.';
  if (!in_array($aaAtt, ['0','1'], true)) $errors[] = 'AA attendance required.';
  if ($aaWhen === '') $errors[] = 'AA when/where and experience required.';
  if (!in_array($aaCont, ['0','1'], true)) $errors[] = 'AA continue selection required.';
  if ($using === '') $errors[] = 'Use status required.';
  if (!in_array($quit, ['0','1'], true)) $errors[] = 'Quit attempts required.';
  if (!in_array($need, ['0','1'], true)) $errors[] = 'Additional help selection required.';
  if ($lifechg === '') $errors[] = 'Lifestyle changes required.';
  if ($stress === '')  $errors[] = 'Stress techniques required.';

  if ($errors) {
    http_response_code(422);
    echo "<!doctype html><meta charset='utf-8'><title>DWII-B | Errors</title>";
    echo "<div style='max-width:720px;margin:40px auto;font-family:system-ui,Arial'>";
    echo "<h1>DWII Individual Session (Weeks 11–12) — Submission errors</h1><ul>";
    foreach ($errors as $e) echo "<li>".h($e)."</li>";
    echo "</ul><p><a href='javascript:history.back()'>Go back</a></p></div>";
    exit;
  }

  // cast radios
  $wbu_i  = ($wbu   === '1') ? 1 : 0;
  $aaA_i  = ($aaAtt === '1') ? 1 : 0;
  $aaC_i  = ($aaCont=== '1') ? 1 : 0;
  $quit_i = ($quit  === '1') ? 1 : 0;
  $need_i = ($need  === '1') ? 1 : 0;

  // meta
  $ipbin = inet_pton_nullable($_SERVER['REMOTE_ADDR'] ?? '') ?? '';
  $ua    = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

  // INSERT (15 values)
  $stmt = $link->prepare("
    INSERT INTO evaluations_dwii_b
      (first_name,last_name,email,today_date,po_attorney,
       workbook_up_to_date,aa_attended,aa_when_where,aa_continue,
       still_using,repeatedly_tried_quit_failed,need_additional_help,
       lifestyle_changes,stress_techniques,submit_ip,user_agent)
    VALUES (?,?,?,?,?,?,?,?,?, ?,?,?, ?,?,?, ?)
  ");
  $stmt->bind_param(
    'sssssiisisiissss', // 15 params: ssss i i s i s i i s s s s
    $first,$last,$email,$tday,$poatt,
    $wbu_i,$aaA_i,$aaWhen,$aaC_i,
    $using,$quit_i,$need_i,
    $lifechg,$stress,$ipbin,$ua
  );
  $ok = $stmt->execute();
  if (!$ok) { throw new RuntimeException('Insert failed: '.$stmt->error); }
  $stmt->close();

  // Thank you
  echo "<!DOCTYPE html>
  <html lang='en'>
  <head>
    <meta charset='utf-8'>
    <title>Thank you – DWII Individual Session</title>
    <link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css'>
    <meta name='viewport' content='width=device-width,initial-scale=1'>
    <style>
      body{font-family:system-ui,Arial;background:#f5f6fa;padding:2rem}
      .card{max-width:720px;margin:0 auto;border:0;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
    </style>
  </head>
  <body>
    <div class='jumbotron bg-white text-center shadow-sm py-4 mb-4'>
      <a href='https://lakevieweducation.com/' target='_blank' rel='noopener'>
        <img src='lakeviewlogo.png' alt='Lakeview Education' class='img-fluid mb-1' style='max-width:60%;height:auto'>
      </a>
    </div>
    <div class='card shadow-sm'>
      <div class='card-body text-center p-5'>
        <h2 class='mb-3'>Thank you</h2>
        <p class='lead mb-2'>Your DWII Individual Session (weeks 11–12) form was submitted.</p>
        <p class='mb-1'><strong>Reminder:</strong> You must do two 12-step (AA) meetings before the 11th and 12th classes.</p>
        <a href='https://lakevieweducation.com/' class='btn btn-primary btn-lg mt-3'>Lakeview Education Home</a>
      </div>
    </div>
  </body>
  </html>";
  exit;
}

/* ----------------------------------- GET ----------------------------------- */
$csrf  = csrf_token();
$today = date('Y-m-d');
$CAL_LINK_B = 'https://calendly.com/lakevieweducation/1-on-1-meeting-dwii-1-clone';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>DWII Individual Session (Weeks 11–12)</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
  <style>
    body{background:#f5f6fa}
    .step{display:none}
    .step.active{display:block}
    .card{border:0;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
    .progress{height:8px}
    :root{--brand-bg:#151a22;--brand-fg:#fff}
    .brandbar{position:fixed;top:0;left:0;right:0;z-index:1000;background:var(--brand-bg);color:var(--brand-fg);height:56px;display:flex;align-items:center;box-shadow:0 1px 4px rgba(0,0,0,.2)}
    .brandbar .wrap{width:100%;max-width:1120px;margin:0 auto;padding:0 16px;display:flex;align-items:center;justify-content:space-between}
    .brandbar .title{font-size:16px;font-weight:600;margin:0}
    .brandbar .subtitle{opacity:.7;font-size:12px;margin-left:8px}
    .brandbar .links a{color:var(--brand-fg);text-decoration:none;font-size:12px;margin-left:16px;opacity:.9}
    .brandbar img{height:28px;margin-left:12px}
    body{padding-top:64px}
    @media (max-width:600px){.brandbar .subtitle{display:none}; body{padding-top:56px}}
  </style>
</head>
<body>
<div class="brandbar">
  <div class="wrap">
    <div class="left">
      <h1 class="title">Lakeview — DWII Individual Session</h1>
      <span class="subtitle">NotesAO Form</span>
    </div>
    <div class="links">
      <a href="https://lakevieweducation.com/">Home</a>
      <img src="lakeviewlogo.png" alt="Lakeview logo">
    </div>
  </div>
</div>

<div class="container py-4" style="max-width:760px">
  <div class="mb-3">
    <div class="d-flex justify-content-between align-items-center">
      <h1 class="h4 mb-0">DWII Individual Session (between weeks 11 and 12)</h1>
      <span class="small text-muted" id="progressLabel">Step 1</span>
    </div>
    <div class="progress mt-2"><div id="bar" class="progress-bar" role="progressbar" style="width:0%"></div></div>
  </div>

  <div class="card">
    <div class="card-body">
      <form id="dwiiForm" method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <!-- 1: First + Last -->
        <div class="step active">
          <div class="form-row">
            <div class="form-group col-sm-6">
              <label for="first_name">First Name<span class="text-danger">*</span></label>
              <input id="first_name" name="first_name" type="text" class="form-control" required>
            </div>
            <div class="form-group col-sm-6">
              <label for="last_name">Last Name<span class="text-danger">*</span></label>
              <input id="last_name" name="last_name" type="text" class="form-control" required>
            </div>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <div class="step">
          <div class="form-group">
            <label for="email">Email<span class="text-danger">*</span></label>
            <input id="email" name="email" type="text" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 2: Today date -->
        <div class="step">
          <div class="form-group">
            <label for="today_date">Today’s Date<span class="text-danger">*</span></label>
            <input id="today_date" name="today_date" type="date" class="form-control" value="<?= h($today) ?>" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 3: PO/Attorney -->
        <div class="step">
          <div class="form-group">
            <label for="po_attorney">Name of probation officer, attorney, etc. <span class="text-danger">*</span><br>
              <small class="text-muted">Enter “NA” if not applicable.</small>
            </label>
            <input id="po_attorney" name="po_attorney" type="text" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 4: Workbook up to date -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Is your workbook up to date?<span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="wbu_y" name="workbook_up_to_date" value="1" required>
              <label class="custom-control-label" for="wbu_y">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="wbu_n" name="workbook_up_to_date" value="0" required>
              <label class="custom-control-label" for="wbu_n">No</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- 5: AA attended + experience -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Have you attended any 12-step meetings (AA)? How was it?<span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="aa_yes" name="aa_attended" value="1" required>
              <label class="custom-control-label" for="aa_yes">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="aa_no" name="aa_attended" value="0" required>
              <label class="custom-control-label" for="aa_no">No</label>
            </div>
          </fieldset>
          <div class="form-group mt-3">
            <label for="aa_when_where">When and where? Describe your experience.<span class="text-danger">*</span></label>
            <textarea id="aa_when_where" name="aa_when_where" class="form-control" rows="4" required></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 6: Continue AA -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Will you continue 12-step meetings (AA)?<span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="aac_y" name="aa_continue" value="1" required>
              <label class="custom-control-label" for="aac_y">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="aac_n" name="aa_continue" value="0" required>
              <label class="custom-control-label" for="aac_n">No</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- 7: Still using -->
        <div class="step">
          <div class="form-group">
            <label for="still_using">Are you still drinking or doing drugs?<span class="text-danger">*</span><br>
              <small class="text-muted">We ensure/value confidentiality.</small>
            </label>
            <textarea id="still_using" name="still_using" class="form-control" rows="4" required></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 8: Repeatedly tried to quit -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Have you repeatedly tried to quit drinking or drugging but failed?<span class="text-danger">*</span><br>
              <small class="text-muted">Information will remain private.</small>
            </legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="quit_y" name="repeatedly_tried_quit_failed" value="1" required>
              <label class="custom-control-label" for="quit_y">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="quit_n" name="repeatedly_tried_quit_failed" value="0" required>
              <label class="custom-control-label" for="quit_n">No</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- 9: Need additional help -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">If yes, do you need additional help for your drinking problem?<span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="need_y" name="need_additional_help" value="1" required>
              <label class="custom-control-label" for="need_y">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="need_n" name="need_additional_help" value="0" required>
              <label class="custom-control-label" for="need_n">No</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- 10: Lifestyle changes -->
        <div class="step">
          <div class="form-group">
            <label for="lifestyle_changes">Have you seen any changes in your lifestyle since you began attending this course? Please describe.<span class="text-danger">*</span></label>
            <textarea id="lifestyle_changes" name="lifestyle_changes" class="form-control" rows="4" required></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 11: Stress techniques -->
        <div class="step">
          <div class="form-group">
            <label for="stress_techniques">What techniques are you currently using to relieve the stress in your life? Please describe.<span class="text-danger">*</span></label>
            <textarea id="stress_techniques" name="stress_techniques" class="form-control" rows="4" required></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- Final Step: Reminder + Calendly embed + Submit -->
        <div class="step">
        <p class="mb-3">
            <strong>Reminder:</strong> You must do two 12-step (AA) meetings before the 11th and 12th classes.
        </p>

        <div class="border rounded p-3 mb-3 bg-light">
            <p class="mb-2">Schedule your one-on-one (if needed):</p>
            <a class="btn btn-outline-primary btn-sm" href="<?= h($CAL_LINK_B) ?>" target="_blank" rel="noopener">
            Open Calendly: DWII One-on-One
            </a>
            <div class="mt-3">
            <div class="calendly-inline-widget"
                data-url="<?= h($CAL_LINK_B) ?>"
                style="min-width:320px;height:680px;"></div>
            <script src="https://assets.calendly.com/assets/external/widget.js" async></script>
            </div>
        </div>

        <button type="submit" class="btn btn-success">Submit</button>
        </div>


      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const steps = Array.from(document.querySelectorAll('.step'));
  const nextButtons = Array.from(document.querySelectorAll('.next'));
  const bar   = document.getElementById('bar');
  const label = document.getElementById('progressLabel');
  let idx = 0;

  function show(i){
    steps.forEach((s,k)=>s.classList.toggle('active', k===i));
    const total = steps.length;
    const pct = Math.round(((i+1)/total)*100);
    if (bar) bar.style.width = pct + '%';
    if (label) label.textContent = 'Step ' + (i+1) + '/' + total;
    idx = i; window.scrollTo({top:0, behavior:'smooth'});
  }
  function stepValid(stepEl){
    const req = stepEl.querySelectorAll('[required]');
    for (const el of req) {
      if (el.type === 'radio') {
        const grp = stepEl.querySelectorAll('input[name="'+el.name+'"]');
        if (![...grp].some(r=>r.checked)) return false;
      } else if (!el.checkValidity() || !el.value) {
        return false;
      }
    }
    return true;
  }
  nextButtons.forEach(b=>{
    b.addEventListener('click', ()=>{
      const stepEl = steps[idx];
      if (!stepValid(stepEl)) { alert('Please complete this step.'); return; }
      show(Math.min(idx+1, steps.length-1));
    });
  });
  show(0);
})();
</script>

<script>
// Enter behaves like Continue. Never submit early.
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('dwiiForm');
  function activeStepEl(){
    return document.querySelector('.step.active') ||
           Array.from(document.querySelectorAll('.step')).find(s=>s.offsetParent!==null);
  }
  function stepIsValid(step){
    if (!step) return false;
    const req = step.querySelectorAll('[required]');
    for (const el of req) {
      if (el.type === 'radio') {
        const grp = step.querySelectorAll('input[name="'+el.name+'"]');
        if (![...grp].some(r=>r.checked)) return false;
      } else if (!el.checkValidity()) return false;
    }
    return true;
  }
  form.addEventListener('keydown', e=>{
    if (e.key !== 'Enter') return;
    if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') return;
    e.preventDefault();
    const step = activeStepEl();
    const submitBtn = step ? Array.from(step.querySelectorAll('button[type="submit"],input[type="submit"]')).find(b=>b.offsetParent!==null) : null;
    if (submitBtn) { if (stepIsValid(step)) submitBtn.click(); return; }
    const nextBtn = step ? Array.from(step.querySelectorAll('.next')).find(b=>b.offsetParent!==null) : null;
    if (stepIsValid(step) && nextBtn) nextBtn.click();
  }, true);
});
</script>
</body>
</html>
