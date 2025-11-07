<?php
/**
 * evaluations-ndp.php  (Public)
 * Numerical Drinking Profile (NDP) – one page per field
 *
 * • GET  : render multi-step form (email → first → last → date → intro → Q1..Q30)
 * • POST : CSRF check → validate → INSERT into evaluations_ndp
 *          → show branded thank-you (no scores shown to client)
 *
 * Requires: /config/config.php for $link (mysqli)
 * Expects DB table: evaluations_ndp (we’ll create via separate SQL)
 */

declare(strict_types=1);
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../config/config.php';   // provides $link (mysqli)
/** @var mysqli $link */
$link->set_charset('utf8mb4');

/* -------------------------------- helpers -------------------------------- */
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void {
  if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    http_response_code(403); exit('Invalid CSRF token.');
  }
}
function inet_pton_nullable(string $ip) { $bin = @inet_pton($ip); return $bin === false ? null : $bin; }

/* ------------------------------- POST ------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $email = trim((string)($_POST['email'] ?? ''));
  $first = trim((string)($_POST['first_name'] ?? ''));
  $last  = trim((string)($_POST['last_name'] ?? ''));
  $tday  = trim((string)($_POST['today_date'] ?? ''));

  $errors = [];
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
  if ($first === '')  $errors[] = 'First name required.';
  if ($last === '')   $errors[] = 'Last name required.';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tday)) $errors[] = 'Valid date required.';

  // Q1 (0..5), Q2 yes/no, Q3 radio (with whom), Q4 radio (belief), Q5..Q30 yes/no
  // Allowed sets for Q3, Q4:
  $Q3_ALLOWED = ['husband_wife'=>'Husband/Wife','strangers'=>'Strangers','relatives'=>'Relatives','friends'=>'Friends','alone'=>'Alone'];
  $Q4_ALLOWED = [
    'yes'         => 'Yes',
    'no'          => 'No',
    'not_sure'    => 'Not sure',
    'no_used_to'  => 'No, but it used to cause me problems',
  ];

  // Normalize answers
  // Q1
  $q1 = $_POST['q1'] ?? null;
  if ($q1 === null || !preg_match('/^[0-5]$/', (string)$q1)) $errors[] = 'Question 1 is required.';
  $q1 = (int)$q1;

  // Q2 (1/0)
  $yn = function($k) use (&$errors) {
    $v = $_POST[$k] ?? null;
    if ($v === null || !in_array($v, ['0','1'], true)) $errors[] = "Question ".substr($k,1)." is required.";
    return ($v === '1') ? 1 : 0;
  };
  $q2 = $yn('q2');

  // Q3 (with whom)
  $q3 = $_POST['q3'] ?? null;
  if ($q3 === null || !array_key_exists($q3, $Q3_ALLOWED)) $errors[] = 'Question 3 is required.';
  // Store token (easier to analyze later)
  $q3_token = $q3;

  // Q4 (belief)
  $q4 = $_POST['q4'] ?? null;
  if ($q4 === null || !array_key_exists($q4, $Q4_ALLOWED)) $errors[] = 'Question 4 is required.';
  $q4_token = $q4;

  // Q5..Q30 all yes/no
  $q = [];
  for ($i=5; $i<=30; $i++) { $q[$i] = $yn('q'.$i); }

  if ($errors) {
    http_response_code(422);
    echo "<!doctype html><meta charset='utf-8'><title>NDP | Errors</title>";
    echo "<div style='max-width:720px;margin:40px auto;font-family:system-ui,Arial'>";
    echo "<h1>Numerical Drinking Profile — Submission errors</h1><ul>";
    foreach ($errors as $e) echo "<li>".h($e)."</li>";
    echo "</ul><p><a href='javascript:history.back()'>Go back</a></p></div>";
    exit;
  }

  // --- sanitize/map ---
    $email = trim((string)($_POST['email'] ?? ''));
    $first = trim((string)($_POST['first_name'] ?? ''));
    $last  = trim((string)($_POST['last_name'] ?? ''));
    $tday  = trim((string)($_POST['today_date'] ?? ''));

    // q1 is numeric 0..5
    $q1 = (int)($_POST['q1'] ?? 0);
    if ($q1 < 0) $q1 = 0;
    if ($q1 > 5) $q1 = 5;

    // helper for yes/no radios (expects '1' or '0')
    $yn = function(string $k): int {
    $v = $_POST[$k] ?? null;
    return ($v === '1' || $v === 1 || $v === true) ? 1 : 0;
    };

    // q2..q30 (booleans)
    $q2  = $yn('q2');
    $q5  = $yn('q5');
    $q6  = $yn('q6');
    $q7  = $yn('q7');
    $q8  = $yn('q8');
    $q9  = $yn('q9');
    $q10 = $yn('q10');
    $q11 = $yn('q11');
    $q12 = $yn('q12');
    $q13 = $yn('q13');
    $q14 = $yn('q14');
    $q15 = $yn('q15');
    $q16 = $yn('q16');
    $q17 = $yn('q17');
    $q18 = $yn('q18');
    $q19 = $yn('q19');
    $q20 = $yn('q20');
    $q21 = $yn('q21');
    $q22 = $yn('q22');
    $q23 = $yn('q23');
    $q24 = $yn('q24');
    $q25 = $yn('q25');
    $q26 = $yn('q26');
    $q27 = $yn('q27');
    $q28 = $yn('q28');
    $q29 = $yn('q29');
    $q30 = $yn('q30');

    // q3 (with whom) — keep as short string from allowed set
    $q3 = (string)($_POST['q3'] ?? '');
    $allowed_q3 = ['husband_wife','strangers','relatives','friends','alone'];
    if (!in_array($q3, $allowed_q3, true)) $q3 = 'alone';

    // q4 (belief about problems) — keep as short string from allowed set
    $q4 = (string)($_POST['q4'] ?? '');
    $allowed_q4 = ['yes','no','not_sure','no_used_to'];
    if (!in_array($q4, $allowed_q4, true)) $q4 = 'not_sure';

    // meta
    $ipbin = isset($_SERVER['REMOTE_ADDR']) ? @inet_pton($_SERVER['REMOTE_ADDR']) : null;
    $ua    = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    // --- INSERT that matches table exactly (36 values) ---
    $sql = "
    INSERT INTO evaluations_ndp
    (email, first_name, last_name, today_date,
    q1, q2, q3, q4,
    q5, q6, q7, q8, q9, q10, q11, q12, q13, q14, q15, q16,
    q17, q18, q19, q20, q21, q22, q23, q24, q25, q26, q27, q28, q29, q30,
    submit_ip, user_agent)
    VALUES (
    ?, ?, ?, ?,
    ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
    ?, ?
    )";

    $stmt = $link->prepare($sql);

    // types: 4 strings, then q1=int, q2=int, q3=string, q4=string,
    //        q5..q30 = 26 ints, then 2 strings = total 36
    $typeStr = 'ssss' . 'iiss' . str_repeat('i', 26) . 'ss';

    $stmt->bind_param(
    $typeStr,
    $email, $first, $last, $tday,
    $q1, $q2, $q3, $q4,
    $q5, $q6, $q7, $q8, $q9, $q10, $q11, $q12, $q13, $q14, $q15, $q16,
    $q17, $q18, $q19, $q20, $q21, $q22, $q23, $q24, $q25, $q26, $q27, $q28, $q29, $q30,
    $ipbin, $ua
    );

    $ok = $stmt->execute();
    if (!$ok) {
    throw new RuntimeException('Insert failed: ' . $stmt->error);
    }
    $stmt->close();




  // Thank you (no visible scoring)
  echo "<!DOCTYPE html>
  <html lang='en'>
  <head>
    <meta charset='utf-8'>
    <title>Thank you – Numerical Drinking Profile</title>
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
        <h2 class='mb-3'>Thank you!</h2>
        <p class='lead mb-2'>Your Numerical Drinking Profile was submitted successfully.</p>
        <p class='mb-4'>We’ve recorded your responses.</p>
        <a href='https://lakevieweducation.com/' class='btn btn-primary btn-lg'>Lakeview Education Home</a>
      </div>
    </div>
  </body>
  </html>";
  exit;
}

/* --------------------------------- GET ----------------------------------- */
$csrf = csrf_token();
$today = date('Y-m-d');

// Question text (rendered below)
$QTEXT = [
  1  => "How many times have you been arrested on charges involving alcohol? (Do not count the present DWI arrest)",
  2  => "Is someone close to you concerned about your drinking?",
  3  => "With whom did you do most of your drinking before this arrest?",
  4  => "Do you believe your drinking may be causing you problems?",
  5  => "Do you want help for a drinking problem?",
  6  => "Do you feel you are a normal drinker? <strong>Note:</strong> No indicates abnormal drinking pattern.",
  7  => "Have you ever awakened in the morning after some drinking the night before and found that you could not remember part of the evening before?",
  8  => "Does your wife, husband, a parent, or other near relative ever worry or complain about your drinking?",
  9  => "Can you stop drinking without a struggle after one or two drinks?",
  10 => "Do you ever feel bad about your drinking?",
  11 => "Do your friends or relatives think you are a normal drinker?",
  12 => "Do you ever try to limit your drinking to certain times of the day or to certain places?",
  13 => "Are you always able to stop drinking when you want to?",
  14 => "Have you ever attended a meeting of Alcoholics Anonymous?",
  15 => "Have you ever gotten into fights when drinking?",
  16 => "Has drinking ever created problems between you and your wife, husband, parent, or other near relatives?",
  17 => "Has your wife, husband, a parent, or other near relative gone to anyone for help about your drinking?",
  18 => "Have you ever lost friends because of drinking?",
  19 => "Have you ever gotten into trouble at work because of drinking?",
  20 => "Have you ever lost a job because of drinking?",
  21 => "Have you ever neglected your obligations, your family, or your work for 2 or more days in a row because you were drinking?",
  22 => "Do you drink before noon fairly often?",
  23 => "Have you ever been told you have liver trouble? Cirrhosis?",
  24 => "After heavy drinking, have you ever had Delirium Tremens (DT’s) or severe shaking?",
  25 => "After heavy drinking, have you ever heard voices or seen things that weren’t really there?",
  26 => "Have you ever gone to anyone for help about your drinking?",
  27 => "Have you ever been in hospital because of drinking?",
  28 => "Have you ever been a patient in a psychiatric hospital or on a psychiatric ward of a general hospital?",
  29 => "Have you ever been in a hospital to be “dried out” (detoxified) because of drinking?",
  30 => "Have you ever been in jail, even for a few hours, because of drunk behavior? (Count the present arrest)",
];

// Radio option maps for Q3, Q4
$Q3_OPTS = ['husband_wife'=>'Husband/Wife','strangers'=>'Strangers','relatives'=>'Relatives','friends'=>'Friends','alone'=>'Alone'];
$Q4_OPTS = [
  'yes'=>'Yes','no'=>'No','not_sure'=>'Not sure',
  'no_used_to'=>'No, but it used to cause me problems'
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Numerical Drinking Profile (NDP)</title>
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
      <h1 class="title">Lakeview — Numerical Drinking Profile (NDP)</h1>
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
      <h1 class="h4 mb-0">Numerical Drinking Profile (NDP)</h1>
      <span class="small text-muted" id="progressLabel">Step 1</span>
    </div>
    <div class="progress mt-2"><div id="bar" class="progress-bar" role="progressbar" style="width:0%"></div></div>
  </div>

  <div class="card">
    <div class="card-body">
      <form id="ndpForm" method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <!-- 1: Email -->
        <div class="step active">
          <div class="form-group">
            <label for="email">Email<span class="text-danger">*</span></label>
            <input id="email" name="email" type="email" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 2: First name -->
        <div class="step">
          <div class="form-group">
            <label for="first_name">First Name<span class="text-danger">*</span></label>
            <input id="first_name" name="first_name" type="text" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 3: Last name -->
        <div class="step">
          <div class="form-group">
            <label for="last_name">Last Name<span class="text-danger">*</span></label>
            <input id="last_name" name="last_name" type="text" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 4: Today date -->
        <div class="step">
          <div class="form-group">
            <label for="today_date">Today’s Date<span class="text-danger">*</span></label>
            <input id="today_date" name="today_date" type="date" class="form-control" required value="<?= h($today) ?>">
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 5: Intro -->
        <div class="step">
          <p>Please read each question carefully, and then check the box to the most correct answer in the box provided. Check only one box for each question.</p>
          <button type="button" class="btn btn-primary next">Start</button>
        </div>

        <!-- Q1 -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3"><?= h('1. '.$QTEXT[1]) ?> <span class="text-danger">*</span></legend>
            <?php for ($i=0; $i<=5; $i++): ?>
              <div class="custom-control custom-radio mb-1">
                <input class="custom-control-input" type="radio" id="q1_<?= $i ?>" name="q1" value="<?= $i ?>" required>
                <label class="custom-control-label" for="q1_<?= $i ?>"><?= $i ?></label>
              </div>
            <?php endfor; ?>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-3">Continue</button>
        </div>

        <!-- Q2 -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3"><?= h('2. '.$QTEXT[2]) ?> <span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="q2_y" name="q2" value="1" required>
              <label class="custom-control-label" for="q2_y">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="q2_n" name="q2" value="0" required>
              <label class="custom-control-label" for="q2_n">No</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-3">Continue</button>
        </div>

        <!-- Q3 -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3"><?= h('3. '.$QTEXT[3]) ?> <span class="text-danger">*</span></legend>
            <?php foreach ($Q3_OPTS as $val => $label): ?>
              <div class="custom-control custom-radio mb-2">
                <input class="custom-control-input" type="radio" id="q3_<?= h($val) ?>" name="q3" value="<?= h($val) ?>" required>
                <label class="custom-control-label" for="q3_<?= h($val) ?>"><?= h($label) ?></label>
              </div>
            <?php endforeach; ?>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-3">Continue</button>
        </div>

        <!-- Q4 -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3"><?= h('4. '.$QTEXT[4]) ?> <span class="text-danger">*</span></legend>
            <?php foreach ($Q4_OPTS as $val => $label): ?>
              <div class="custom-control custom-radio mb-2">
                <input class="custom-control-input" type="radio" id="q4_<?= h($val) ?>" name="q4" value="<?= h($val) ?>" required>
                <label class="custom-control-label" for="q4_<?= h($val) ?>"><?= h($label) ?></label>
              </div>
            <?php endforeach; ?>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-3">Continue</button>
        </div>

        <?php
          // Q5..Q30 yes/no blocks
          for ($i=5; $i<=30; $i++):
            $isLast = ($i === 30);
        ?>
          <div class="step">
            <fieldset>
              <legend class="h6 mb-3"><?= h($i.'. '.$QTEXT[$i]) ?> <span class="text-danger">*</span></legend>
              <div class="custom-control custom-radio mb-2">
                <input class="custom-control-input" type="radio" id="q<?= $i ?>_y" name="q<?= $i ?>" value="1" required>
                <label class="custom-control-label" for="q<?= $i ?>_y">Yes</label>
              </div>
              <div class="custom-control custom-radio">
                <input class="custom-control-input" type="radio" id="q<?= $i ?>_n" name="q<?= $i ?>" value="0" required>
                <label class="custom-control-label" for="q<?= $i ?>_n">No</label>
              </div>
            </fieldset>
            <?php if ($isLast): ?>
              <button type="submit" class="btn btn-success mt-3">Submit</button>
            <?php else: ?>
              <button type="button" class="btn btn-primary next mt-3">Continue</button>
            <?php endif; ?>
          </div>
        <?php endfor; ?>

      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const steps = Array.from(document.querySelectorAll('.step'));
  const nextButtons = Array.from(document.querySelectorAll('.next'));
  const bar = document.getElementById('bar');
  const label = document.getElementById('progressLabel');
  let idx = 0;

  function show(i){
    steps.forEach((s, k) => s.classList.toggle('active', k===i));
    const total = steps.length;
    const pct = Math.round(((i+1)/total)*100);
    if (bar) bar.style.width = pct + '%';
    if (label) label.textContent = 'Step ' + (i+1) + '/' + total;
    idx = i;
    window.scrollTo({top:0, behavior:'smooth'});
  }

  function stepValid(stepEl){
    const required = stepEl.querySelectorAll('[required]');
    for (const el of required) {
      if (el.type === 'radio') {
        const group = stepEl.querySelectorAll(`input[name="${el.name}"]`);
        if (![...group].some(r => r.checked)) return false;
      } else if (!el.value) {
        return false;
      }
    }
    return true;
  }

  nextButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const stepEl = steps[idx];
      if (!stepValid(stepEl)) { alert('Please complete this step.'); return; }
      show(Math.min(idx+1, steps.length-1));
    });
  });

  show(0);
})();
</script>

<script>
// Treat Enter as "Continue" (never submit early)
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('ndpForm');
  function activeStepEl() {
    return document.querySelector('.step.active') ||
           Array.from(document.querySelectorAll('.step')).find(s => s.offsetParent !== null);
  }
  function stepIsValid(step) {
    if (!step) return false;
    const req = step.querySelectorAll('[required]');
    for (const el of req) {
      if (el.type === 'radio') {
        const group = step.querySelectorAll('input[name="'+el.name+'"]');
        if (![...group].some(r => r.checked)) return false;
      } else if (!el.checkValidity()) return false;
    }
    return true;
  }
  form.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') return;
    e.preventDefault();

    const step = activeStepEl();
    const submitBtn = step ? Array.from(step.querySelectorAll('button[type="submit"],input[type="submit"]'))
                               .find(b => b.offsetParent !== null) : null;

    if (submitBtn) {
      if (stepIsValid(step)) submitBtn.click();
      return;
    }
    const nextBtn = step ? Array.from(step.querySelectorAll('.next')).find(b => b.offsetParent !== null) : null;
    if (stepIsValid(step) && nextBtn) nextBtn.click();
  }, true);
});
</script>
</body>
</html>
