<?php
/**
 * evaluations-dast.php  (Public)
 * Drug Abuse Screening Test (DAST-20)
 *
 * • GET  : render multi-step one-page-per-field form
 * • POST : CSRF check → validate → compute score → INSERT into evaluations_dast
 *          → render confirmation with score + severity
 *
 * Connection comes from shared /config/config.php ($link is mysqli).
 */

declare(strict_types=1);
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../config/config.php';   // provides $link (mysqli)
$link->set_charset('utf8mb4');

/* ---------------------------- bootstrap table ---------------------------- */
$link->query("
CREATE TABLE IF NOT EXISTS evaluations_dast (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  email             VARCHAR(190) NOT NULL,
  first_name        VARCHAR(190) NOT NULL,
  last_name         VARCHAR(190) NOT NULL,
  today_date        DATE         NOT NULL,
  q1  TINYINT(1) NOT NULL, q2  TINYINT(1) NOT NULL, q3  TINYINT(1) NOT NULL, q4  TINYINT(1) NOT NULL, q5  TINYINT(1) NOT NULL,
  q6  TINYINT(1) NOT NULL, q7  TINYINT(1) NOT NULL, q8  TINYINT(1) NOT NULL, q9  TINYINT(1) NOT NULL, q10 TINYINT(1) NOT NULL,
  q11 TINYINT(1) NOT NULL, q12 TINYINT(1) NOT NULL, q13 TINYINT(1) NOT NULL, q14 TINYINT(1) NOT NULL, q15 TINYINT(1) NOT NULL,
  q16 TINYINT(1) NOT NULL, q17 TINYINT(1) NOT NULL, q18 TINYINT(1) NOT NULL, q19 TINYINT(1) NOT NULL, q20 TINYINT(1) NOT NULL,
  total_score       TINYINT UNSIGNED NOT NULL,
  severity_label    VARCHAR(24)  NOT NULL,
  submit_ip         VARBINARY(16) NULL,
  user_agent        VARCHAR(255)  NULL,
  PRIMARY KEY (id),
  KEY idx_email_date (email, today_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ------------------------------- helpers -------------------------------- */
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void {
  if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    http_response_code(403); exit('Invalid CSRF token');
  }
}
function inet_pton_nullable(string $ip) { $bin = @inet_pton($ip); return $bin === false ? null : $bin; }
function dast_score(array $ans): array {
  // DAST-20 scoring: 1 point for “Yes” on all items EXCEPT #4 and #5 which are reverse-scored (No=1).
  $score = 0;
  for ($i=1; $i<=20; $i++) {
    $v = isset($ans["q$i"]) ? (int)!!$ans["q$i"] : 0; // 1=yes, 0=no from UI
    if ($i === 4 || $i === 5) {
      // reverse: Yes=0, No=1 → since we store 1=yes, we add (1-$v)
      $score += (1 - $v);
    } else {
      $score += $v;
    }
  }
  // Severity (standard cutoffs)
  if ($score <= 0)       $sev = 'None';
  elseif ($score <= 5)   $sev = 'Low';
  elseif ($score <= 10)  $sev = 'Moderate';
  elseif ($score <= 15)  $sev = 'Substantial';
  else                   $sev = 'Severe';
  return [$score, $sev];
}

/* --------------------------------- POST ---------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $email = trim((string)($_POST['email'] ?? ''));
  $first = trim((string)($_POST['first_name'] ?? ''));
  $last  = trim((string)($_POST['last_name'] ?? ''));
  $tday  = trim((string)($_POST['today_date'] ?? ''));

  $errors = [];
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
  if ($first === '') $errors[] = 'First name required.';
  if ($last === '')  $errors[] = 'Last name required.';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tday)) $errors[] = 'Today date required.';

  $answers = [];
  for ($i=1; $i<=20; $i++) {
    $k = "q$i";
    $v = $_POST[$k] ?? null;
    if ($v === null || !in_array($v, ['0','1','yes','no'], true)) $errors[] = "Question $i is required.";
    $answers[$k] = ($v === '1' || $v === 'yes') ? 1 : 0; // normalize to 0/1
  }

  if ($errors) {
    http_response_code(422);
    // Re-render with errors in a minimal way
    echo "<!doctype html><meta charset='utf-8'><title>DAST-20 | Errors</title>";
    echo "<div style='max-width:720px;margin:40px auto;font-family:system-ui,Arial'>";
    echo "<h1>DAST-20 — Submission errors</h1><ul>";
    foreach ($errors as $e) echo "<li>".h($e)."</li>";
    echo "</ul><p><a href='javascript:history.back()'>Go back</a></p></div>";
    exit;
  }

  [$score, $severity] = dast_score($answers);

  $stmt = $link->prepare("
    INSERT INTO evaluations_dast
      (email, first_name, last_name, today_date,
       q1,q2,q3,q4,q5,q6,q7,q8,q9,q10,q11,q12,q13,q14,q15,q16,q17,q18,q19,q20,
       total_score, severity_label, submit_ip, user_agent)
    VALUES
      (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ");
  $ipbin = inet_pton_nullable($_SERVER['REMOTE_ADDR'] ?? '');
  $ua    = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

  // NEW
    $typeStr = 'ssss' . str_repeat('i', 21) . 'sss'; // 4 strings, 21 ints, 3 strings
    $stmt->bind_param(
        $typeStr,
        $email,$first,$last,$tday,
        $answers['q1'],$answers['q2'],$answers['q3'],$answers['q4'],$answers['q5'],
        $answers['q6'],$answers['q7'],$answers['q8'],$answers['q9'],$answers['q10'],
        $answers['q11'],$answers['q12'],$answers['q13'],$answers['q14'],$answers['q15'],
        $answers['q16'],$answers['q17'],$answers['q18'],$answers['q19'],$answers['q20'],
        $score,
        $severity,
        $ipbin,   // binary ok as "s"; null stays null
        $ua
    );
    // no send_long_data

  $ok = $stmt->execute();
  $stmt->close();

    ob_end_clean(); // optional: clear any buffered output before rendering
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="utf-8">
    <title>Thank you – DAST-20</title>

    <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
    <link rel="stylesheet"
            href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body{font-family:system-ui,Arial;background:#f5f6fa;padding:2rem}
        .card{max-width:720px;margin:0 auto;border:0;border-radius:8px;
            box-shadow:0 2px 10px rgba(0,0,0,.08)}
    </style>
    </head>
    <body>

    <div class="jumbotron bg-white text-center shadow-sm py-4 mb-4">
    <a href="https://lakevieweducation.com/" target="_blank" rel="noopener">
        <img src="lakeviewlogo.png" alt="Lakeview Education"
            class="img-fluid mb-1" style="max-width:60%;height:auto">
    </a>
    </div>

    <div class="card shadow-sm">
    <div class="card-body text-center p-5">
        <h2 class="mb-3">Thank you!</h2>
        <p class="lead mb-2">Your questionnaire was submitted successfully.</p>
        <p class="mb-4">We’ve recorded your responses. A facilitator will review them shortly.</p>
        <a href="https://lakevieweducation.com/" class="btn btn-primary btn-lg">
        Lakeview Education Home
        </a>
    </div>
    </div>

    </body>
    </html>
    HTML;
    exit;

}

/* ---------------------------------- GET ---------------------------------- */
$csrf = csrf_token();
$today = date('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Drug Use Questionnaire (DAST-20)</title>
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
      <h1 class="title">Lakeview — Drug Use Questionnaire (DAST-20)</h1>
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
      <h1 class="h4 mb-0">Drug Use Questionnaire (DAST-20)</h1>
      <span class="small text-muted" id="progressLabel">Step 1/26</span>
    </div>
    <div class="progress mt-2"><div id="bar" class="progress-bar" role="progressbar" style="width:0%"></div></div>
  </div>

  <div class="card">
    <div class="card-body">
      <form id="dastForm" method="post" novalidate>
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

        <!-- 5: Intro 1 -->
        <div class="step">
          <p>
            The following questions concern your potential involvement with drugs <strong>not including alcoholic beverages</strong> during the past 12 months.
            Read each statement and answer Yes or No. “Drug abuse” refers to: (1) using prescribed or over-the-counter drugs in excess of directions, or
            (2) any non-medical use of drugs. Drug classes include cannabis, solvents, tranquilizers, barbiturates, cocaine, stimulants, hallucinogens, or narcotics.
          </p>
          <p>These questions do not include alcoholic beverages.</p>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 6: Intro 2 -->
        <div class="step">
          <p>Please answer every question. If a statement is difficult, choose the response that is most right. These questions refer to the past 12 months.</p>
          <button type="button" class="btn btn-primary next">Start</button>
        </div>

        <?php
        // Questions 1..20, Yes/No (radio)
        $questions = [
          1 => "Have you ever used drugs other than those required for medical reasons?",
          2 => "Have you abused prescription drugs?",
          3 => "Do you abuse more than one drug at a time?",
          4 => "Can you get through the week without using drugs?",
          5 => "Are you always able to stop using drugs when you want to?",
          6 => "Have you had 'blackouts' or 'flashbacks' as a result of drug use?",
          7 => "Do you ever feel bad or guilty about your drug use?",
          8 => "Does your spouse (or parents) ever complain about your involvement with drugs?",
          9 => "Has drug abuse created problems between you and your spouse or your parents?",
          10 => "Have you lost friends because of your use of drugs?",
          11 => "Have you neglected your family because of your use of drugs?",
          12 => "Have you been in trouble at work because of drug abuse?",
          13 => "Have you lost a job because of drug abuse?",
          14 => "Have you gotten into fights when under the influence of drugs?",
          15 => "Have you engaged in illegal activities in order to obtain drugs?",
          16 => "Have you been arrested for possession of illegal drugs?",
          17 => "Have you ever experienced withdrawal symptoms (felt sick) when you stopped taking drugs?",
          18 => "Have you had medical problems as a result of your drug use (e.g., memory loss, hepatitis, convulsions, bleeding, etc.)?",
          19 => "Have you gone to anyone for help for a drug problem?",
          20 => "Have you been involved in a treatment program specifically related to drug use?",
        ];
        foreach ($questions as $i => $q):
        ?>
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3"><?= h($i.'. '.$q) ?> <span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="q<?= $i ?>_y" name="q<?= $i ?>" value="1" required>
              <label class="custom-control-label" for="q<?= $i ?>_y">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="q<?= $i ?>_n" name="q<?= $i ?>" value="0" required>
              <label class="custom-control-label" for="q<?= $i ?>_n">No</label>
            </div>
          </fieldset>
          <?php if ($i < 20): ?>
            <button type="button" class="btn btn-primary next mt-3">Continue</button>
          <?php else: ?>
            <button type="submit" class="btn btn-success mt-3">Submit</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>

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
    bar.style.width = pct + '%';
    label.textContent = 'Step ' + (i+1) + '/' + total;
    idx = i;
    window.scrollTo({top:0, behavior:'smooth'});
  }

  function stepValid(stepEl){
    // Ensure required inputs in this step are filled
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
      if (!stepValid(stepEl)) {
        alert('Please complete this step.');
        return;
      }
      show(Math.min(idx+1, steps.length-1));
    });
  });

  show(0);
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('dastForm') || document.querySelector('form');


  function activeStepEl() {
    // support either `.step.active` or the only visible `.step`
    return document.querySelector('.step.active') ||
           Array.from(document.querySelectorAll('.step')).find(s => s.offsetParent !== null);
  }

  function stepValid(step) {
    if (!step) return false;
    const req = step.querySelectorAll('input[required], select[required], textarea[required]');
    for (const el of req) {
      // native validity checks current field only
      if (!el.checkValidity()) return false;
    }
    return true;
  }

  // Intercept Enter anywhere in the form
  form.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;

    // allow newline in textareas
    if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') return;

    // stop browser from submitting the whole form
    e.preventDefault();

    const step = activeStepEl();

    // If this step has a visible submit button, allow Enter to submit only when valid
    const submitBtn = step ? Array.from(step.querySelectorAll('button[type="submit"], input[type="submit"]'))
                               .find(b => b.offsetParent !== null) : null;

    if (submitBtn) {
      if (stepValid(step)) submitBtn.click();
      return;
    }

    // Otherwise treat Enter as "Continue" only when this step is valid
    const nextInStep = step ? step.querySelector('button.next') : null;
    if (stepValid(step) && nextInStep && nextInStep.offsetParent !== null) {
        nextInStep.click();
    }

    // If not valid, do nothing
  }, true);
});
</script>


</body>
</html>
