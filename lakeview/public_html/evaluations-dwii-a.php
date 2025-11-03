<?php
/**
 * evaluations-dwii-a.php  — DWII Individual Session (between weeks six and seven)
 * One page per field. Calendly step includes a required confirmation checkbox.
 *
 * • GET  : render form
 * • POST : CSRF → validate → INSERT into evaluations_dwii_a → thank-you page
 */

declare(strict_types=1);
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../config/config.php';   // provides $link (mysqli)
/** @var mysqli $link */
$link->set_charset('utf8mb4');

/* ------------------------------ bootstrap table ------------------------------ */
$link->query("
CREATE TABLE IF NOT EXISTS evaluations_dwii_a (
  id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  first_name               VARCHAR(190) NOT NULL,
  last_name                VARCHAR(190) NOT NULL,
  email                    VARCHAR(190) NOT NULL,
  today_date               DATE         NOT NULL,
  po_attorney              VARCHAR(255) NOT NULL,      -- 'Name of probation officer, attorney, etc'
  feelings_about_course    TEXT         NOT NULL,
  still_using              TEXT         NOT NULL,      -- 'Are you still drinking or doing drugs?'
  support_system           TEXT         NOT NULL,
  stressful_problem        TEXT         NOT NULL,
  workbook_up_to_date      TINYINT(1)   NOT NULL,      -- 1=yes 0=no
  self_improvement         TEXT         NOT NULL,
  scheduled_confirm        TINYINT(1)   NOT NULL,      -- user confirmed they scheduled
  scheduling_link          VARCHAR(255) NOT NULL,
  submit_ip                VARBINARY(16) NULL,
  user_agent               VARCHAR(255)  NULL,
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

  $first  = trim((string)($_POST['first_name'] ?? ''));
  $last   = trim((string)($_POST['last_name'] ?? ''));
  $email  = trim((string)($_POST['email'] ?? ''));
  $tday   = trim((string)($_POST['today_date'] ?? ''));
  $poatt  = trim((string)($_POST['po_attorney'] ?? ''));
  $feel   = trim((string)($_POST['feelings_about_course'] ?? ''));
  $using  = trim((string)($_POST['still_using'] ?? ''));
  $support= trim((string)($_POST['support_system'] ?? ''));
  $stress = trim((string)($_POST['stressful_problem'] ?? ''));
  $wbu    = (string)($_POST['workbook_up_to_date'] ?? '');
  $improve= trim((string)($_POST['self_improvement'] ?? ''));
  $schedC = (string)($_POST['scheduled_confirm'] ?? '');
  $schedLink = 'https://calendly.com/lakevieweducation/dwii_one_on_one_session';

  $errors = [];
  if ($first === '') $errors[] = 'First name required.';
  if ($last === '')  $errors[] = 'Last name required.';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tday)) $errors[] = 'Valid date required.';
  if ($poatt === '') $errors[] = 'Name of probation officer, attorney, etc. required. Enter NA if not applicable.';
  if ($feel === '')  $errors[] = 'Course feelings required.';
  if ($using === '') $errors[] = 'Use status required.';
  if ($support === '') $errors[] = 'Support system required.';
  if ($stress === '')  $errors[] = 'Stressful problem required.';
  if (!in_array($wbu, ['0','1'], true)) $errors[] = 'Workbook status required.';
  if ($improve === '') $errors[] = 'Self-improvement techniques required.';
  if ($schedC !== '1') $errors[] = 'Scheduling confirmation required.';

  if ($errors) {
    http_response_code(422);
    echo "<!doctype html><meta charset='utf-8'><title>DWII — Errors</title>";
    echo "<div style='max-width:720px;margin:40px auto;font-family:system-ui,Arial'>";
    echo "<h1>DWII Individual Session — Submission errors</h1><ul>";
    foreach ($errors as $e) echo "<li>".h($e)."</li>";
    echo "</ul><p><a href='javascript:history.back()'>Go back</a></p></div>";
    exit;
  }

  $wbu_i   = ($wbu === '1') ? 1 : 0;
  $sched_i = 1;

  $ipbin = inet_pton_nullable($_SERVER['REMOTE_ADDR'] ?? '');
  $ua    = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

  $stmt = $link->prepare("
    INSERT INTO evaluations_dwii_a
        (first_name,last_name,email,today_date,po_attorney,feelings_about_course,still_using,
        support_system,stressful_problem,workbook_up_to_date,self_improvement,
        scheduled_confirm,scheduling_link,submit_ip,user_agent)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        'sssssssssisisbs',  // 8s, i, s, i, s, b, s  => 14 total
        $first,$last,$email,$tday,$poatt,$feel,$using,
        $support,$stress,$wbu_i,$improve,
        $sched_i,$schedLink,$ipbin,$ua
    );
    $stmt->send_long_data(12, $ipbin ?? ""); // index 12 = submit_ip


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
        <p class='lead mb-2'>Your DWII Individual Session form was submitted.</p>
        <p class='mb-4'>Bring your workbook to the one-on-one.</p>
        <a href='https://lakevieweducation.com/' class='btn btn-primary btn-lg'>Lakeview Education Home</a>
      </div>
    </div>
  </body>
  </html>";
  exit;
}

/* ----------------------------------- GET ----------------------------------- */
$csrf  = csrf_token();
$today = date('Y-m-d');
$CAL_LINK = 'https://calendly.com/lakevieweducation/dwii_one_on_one_session';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>DWII Individual Session</title>
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
      <h1 class="h4 mb-0">DWII Individual Session</h1>
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

        <!-- 4..9: Text areas and yes/no -->
        <div class="step">
          <div class="form-group">
            <label for="feelings_about_course">How do you feel about being in this course?<span class="text-danger">*</span></label>
            <textarea id="feelings_about_course" name="feelings_about_course" class="form-control" rows="4" required></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <div class="step">
          <div class="form-group">
            <label for="still_using">Are you still drinking or doing drugs? If yes, how much and how often?<span class="text-danger">*</span><br>
              <small class="text-muted">We ensure and value confidentiality.</small>
            </label>
            <textarea id="still_using" name="still_using" class="form-control" rows="4" required></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <div class="step">
          <div class="form-group">
            <label for="support_system">Do you have a support system at home? Are they coming to Family Week (Weeks 9 and 10)?<span class="text-danger">*</span></label>
            <textarea id="support_system" name="support_system" class="form-control" rows="4" required></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <div class="step">
          <div class="form-group">
            <label for="stressful_problem">Presently, what is the most stressful problem in your life? Is drinking or doing drugs contributing to this?<span class="text-danger">*</span></label>
            <textarea id="stressful_problem" name="stressful_problem" class="form-control" rows="4" required></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Are your workbook assignments up to date?<span class="text-danger">*</span></legend>
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

        <div class="step">
          <div class="form-group">
            <label for="self_improvement">What self-improvement techniques have you used since beginning this program?<span class="text-danger">*</span></label>
            <textarea id="self_improvement" name="self_improvement" class="form-control" rows="4" required></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 10: Scheduling step -->
        <div class="step">
          <p class="mb-2"><strong>Schedule your required one-on-one meeting here</strong> (usually 5 minutes). Bring your workbook.</p>
          <!-- Optional inline Calendly embed; safe if the link 404s -->
          <div class="border rounded p-3 mb-3 bg-light">
            <p class="mb-2">Open the link in a new tab if the embed does not load:</p>
            <a class="btn btn-outline-primary btn-sm" href="<?= h($CAL_LINK) ?>" target="_blank" rel="noopener">
              Open Calendly: DWII One-on-One
            </a>
            <div class="mt-3">
              <div class="calendly-inline-widget" data-url="<?= h($CAL_LINK) ?>" style="min-width:320px;height:680px;"></div>
              <script src="https://assets.calendly.com/assets/external/widget.js" async></script>
            </div>
          </div>
          <div class="custom-control custom-checkbox mb-3">
            <input class="custom-control-input" type="checkbox" id="scheduled_confirm" name="scheduled_confirm" value="1" required>
            <label class="custom-control-label" for="scheduled_confirm">I confirm I scheduled my one-on-one.</label>
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
      } else if (el.type === 'checkbox') {
        if (!el.checked) return false;
      } else if (!el.checkValidity() || !el.value) { return false; }
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
      } else if (el.type === 'checkbox') {
        if (!el.checked) return false;
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
