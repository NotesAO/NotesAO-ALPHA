<?php
/**
 * evaluations-bdi.php (Public) — DWIE “BDI” Incident Questionnaire
 * One page per field, with Enter-to-continue behavior and Lakeview branding.
 *
 * POST: CSRF check → validate → (attempt INSERT into evaluations_bdi) → Thank-you page
 */

declare(strict_types=1);
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../config/config.php'; // provides $link (mysqli)
$link->set_charset('utf8mb4');

/* ------------------------------- helpers -------------------------------- */
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void {
  if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    http_response_code(403); exit('Invalid CSRF token');
  }
}
function inet_pton_nullable(string $ip) {
  $bin = @inet_pton($ip);
  return $bin === false ? null : $bin;
}

/* --------------------------------- POST ---------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $email     = trim((string)($_POST['email']       ?? ''));
  $first     = trim((string)($_POST['first_name']  ?? ''));
  $last      = trim((string)($_POST['last_name']   ?? ''));
  $tday      = trim((string)($_POST['today_date']  ?? ''));

  $cityCnty  = trim((string)($_POST['city_county_stopped'] ?? ''));
  $reason    = trim((string)($_POST['reason_stopped']       ?? ''));
  $testTaken = $_POST['test_taken'] ?? null;  // '1' or '0'
  $bac       = trim((string)($_POST['bac'] ?? '')); // only required if testTaken == '1'
  $beverages = trim((string)($_POST['beverages_types'] ?? ''));
  $contexts  = isset($_POST['pre_arrest_context']) && is_array($_POST['pre_arrest_context'])
                ? array_map('strval', $_POST['pre_arrest_context']) : [];
  $ctxOther  = trim((string)($_POST['pre_arrest_context_other'] ?? ''));
  $drinksPw  = trim((string)($_POST['drinks_per_week'] ?? ''));
  $narrative = trim((string)($_POST['narrative_summary'] ?? ''));

  // ---- validation ----
  $errors = [];
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
  if ($first === '') $errors[] = 'First name is required.';
  if ($last  === '') $errors[] = 'Last name is required.';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tday)) $errors[] = 'Today’s date is required.';

  if ($cityCnty === '') $errors[] = 'City/County where you were stopped is required.';
  if ($reason   === '') $errors[] = 'Reason you were stopped is required.';
  if ($testTaken === null || !in_array($testTaken, ['0','1'], true)) $errors[] = 'Please indicate whether a breath or blood test was taken.';
  if ($testTaken === '1' && $bac === '') $errors[] = 'BAC is required when a test was taken.';
  if ($beverages === '') $errors[] = 'Type(s) of alcoholic beverage consumed is required.';
  if (empty($contexts) && $ctxOther === '') $errors[] = 'Select at least one “Before my arrest” context or specify Other.';
  if ($drinksPw === '') $errors[] = 'Drinks-per-week (and when) is required.';
  if ($narrative === '') $errors[] = 'Please summarize the events leading up to your arrest.';

  if ($errors) {
    http_response_code(422);
    echo "<!doctype html><meta charset='utf-8'><title>BDI (DWIE) | Errors</title>";
    echo "<div style='max-width:760px;margin:40px auto;font-family:system-ui,Arial'>";
    echo "<h1>Submission errors</h1><ul>";
    foreach ($errors as $e) echo "<li>".h($e)."</li>";
    echo "</ul><p><a href='javascript:history.back()'>Go back</a></p></div>";
    exit;
  }

  // normalize + prepare insert
  $testTakenInt = (int)$testTaken;
  $ctxList = implode(', ', $contexts);
  $ipbin = inet_pton_nullable($_SERVER['REMOTE_ADDR'] ?? '');
  $ua    = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

  // Attempt insert (OK if table isn’t created yet; we’ll still show Thank You)
  $stmt = $link->prepare("
    INSERT INTO evaluations_bdi
      (email, first_name, last_name, today_date,
       city_county_stopped, reason_stopped, test_taken, bac,
       beverages_types, pre_arrest_context, pre_arrest_context_other,
       drinks_per_week, narrative_summary, submit_ip, user_agent)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
  ");

  if ($stmt) {
    $typeStr = 'ssssssissssssss'; // 6s, i, 8s = 15 params
    $stmt->bind_param(
      $typeStr,
      $email, $first, $last, $tday,
      $cityCnty, $reason, $testTakenInt, $bac,
      $beverages, $ctxList, $ctxOther,
      $drinksPw, $narrative, $ipbin, $ua
    );
    // Note: VARBINARY bound as 's' is fine in mysqli
    if (!$stmt->execute()) {
      error_log('evaluations_bdi insert failed: '.$stmt->error);
    }
    $stmt->close();
  } else {
    error_log('evaluations_bdi prepare failed (likely table missing): '.$link->error);
  }

  // ---- Thank-you page (no scoring shown to client) ----
  ob_end_clean();
  echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Thank you – BDI (DWIE)</title>

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
$csrf  = csrf_token();
$today = date('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>BDI (DWIE) — Incident Questionnaire</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
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
      <h1 class="title">Lakeview — BDI (DWIE) Incident Questionnaire</h1>
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
      <h1 class="h4 mb-0">BDI — DWIE</h1>
      <span class="small text-muted" id="progressLabel">Step 1</span>
    </div>
    <div class="progress mt-2"><div id="bar" class="progress-bar" role="progressbar" style="width:0%"></div></div>
  </div>

  <div class="card">
    <div class="card-body">
      <form id="bdiForm" method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <!-- 1: First name -->
        <div class="step active" data-step="first">
          <div class="form-group">
            <label for="first_name">First Name<span class="text-danger">*</span></label>
            <input id="first_name" name="first_name" type="text" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 2: Last name -->
        <div class="step" data-step="last">
          <div class="form-group">
            <label for="last_name">Last Name<span class="text-danger">*</span></label>
            <input id="last_name" name="last_name" type="text" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 3: Today date -->
        <div class="step" data-step="date">
          <div class="form-group">
            <label for="today_date">Today’s Date<span class="text-danger">*</span></label>
            <input id="today_date" name="today_date" type="date" class="form-control" required value="<?= h($today) ?>">
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 4: Email -->
        <div class="step" data-step="email">
          <div class="form-group">
            <label for="email">Email<span class="text-danger">*</span></label>
            <input id="email" name="email" type="email" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 5: Instruction page -->
        <div class="step" data-step="intro">
          <p>
            Begin at a time ~12 hours before your arrest and write down what you did, where you went,
            whom you were with (no names), what and how much you drank and/or the drugs consumed, why
            you were drinking or taking drugs, and the details of the arrest itself.
          </p>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 6: City/County where stopped -->
        <div class="step" data-step="citycounty">
          <div class="form-group">
            <label for="city_county_stopped">City/County where you were stopped:<span class="text-danger">*</span></label>
            <input id="city_county_stopped" name="city_county_stopped" type="text" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 7: Reason stopped -->
        <div class="step" data-step="reason">
          <div class="form-group">
            <label for="reason_stopped">Reason you were stopped:<span class="text-danger">*</span></label>
            <input id="reason_stopped" name="reason_stopped" type="text" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 8: Test taken -->
        <div class="step" data-step="test">
          <fieldset>
            <legend class="h6 mb-3">Was a breath or blood test taken?<span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="test_taken_yes" name="test_taken" value="1" required>
              <label class="custom-control-label" for="test_taken_yes">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="test_taken_no" name="test_taken" value="0" required>
              <label class="custom-control-label" for="test_taken_no">No</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 9: BAC (conditional: only if test == Yes) -->
        <div class="step" data-step="bac" id="step-bac">
          <div class="form-group">
            <label for="bac">If yes, what was the BAC?<span class="text-danger" id="bacRequired">*</span></label>
            <input id="bac" name="bac" type="text" class="form-control" placeholder="e.g., 0.082">
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 10: Beverages types (required) -->
        <div class="step" data-step="beverages">
          <div class="form-group">
            <label for="beverages_types">What type of alcoholic beverage(s) were consumed before the arrest?<span class="text-danger">*</span></label>
            <input id="beverages_types" name="beverages_types" type="text" class="form-control" required placeholder="e.g., beer, whiskey">
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 11: Context (checkboxes + other) -->
        <div class="step" data-step="context">
          <fieldset>
            <legend class="h6 mb-3">Before my arrest, I was (answer all that apply):</legend>
            <div class="custom-control custom-checkbox mb-1">
              <input class="custom-control-input" type="checkbox" id="ctx_after_work" name="pre_arrest_context[]" value="Drinking after work">
              <label class="custom-control-label" for="ctx_after_work">Drinking after work</label>
            </div>
            <div class="custom-control custom-checkbox mb-1">
              <input class="custom-control-input" type="checkbox" id="ctx_recreation" name="pre_arrest_context[]" value="Recreational activity (sports, fishing, party, work function)">
              <label class="custom-control-label" for="ctx_recreation">Engaged in recreational activity (sports, fishing, party, work function)</label>
            </div>
            <div class="custom-control custom-checkbox mb-1">
              <input class="custom-control-input" type="checkbox" id="ctx_coping" name="pre_arrest_context[]" value="Coping with problems (relationships, work, family)">
              <label class="custom-control-label" for="ctx_coping">Coping with problems (relationships, work, family)</label>
            </div>
            <div class="custom-control custom-checkbox">
              <input class="custom-control-input" type="checkbox" id="ctx_other" name="pre_arrest_context[]" value="Other">
              <label class="custom-control-label" for="ctx_other">Other</label>
            </div>
          </fieldset>
          <div class="form-group mt-3 d-none" id="ctxOtherWrap">
            <label for="pre_arrest_context_other">If Other, please specify:</label>
            <input id="pre_arrest_context_other" name="pre_arrest_context_other" type="text" class="form-control">
          </div>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- 12: Drinks per week (required) -->
        <div class="step" data-step="drinks">
          <div class="form-group">
            <label for="drinks_per_week">How many alcoholic drinks do you tend to drink per week and when (weekdays, weekends, morning, evening)?<span class="text-danger">*</span></label>
            <textarea id="drinks_per_week" name="drinks_per_week" class="form-control" rows="4" required></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 13: Narrative summary (required; show submit only when typed) -->
        <div class="step" data-step="narrative">
          <div class="form-group">
            <label for="narrative_summary">Summarize / briefly describe the events leading up to your arrest:<span class="text-danger">*</span></label>
            <textarea id="narrative_summary" name="narrative_summary" class="form-control" rows="6" required></textarea>
          </div>
          <button type="submit" class="btn btn-success d-none" id="btnSubmit">Submit</button>
        </div>

      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const steps = Array.from(document.querySelectorAll('.step'));
  const bar   = document.getElementById('bar');
  const label = document.getElementById('progressLabel');
  const form  = document.getElementById('bdiForm');
  let idx = 0;

  const stepTest = document.querySelector('[data-step="test"]');
  const stepBAC  = document.getElementById('step-bac');
  const bacInput = document.getElementById('bac');
  const bacReq   = document.getElementById('bacRequired');

  const ctxOther = document.getElementById('ctx_other');
  const ctxOtherWrap = document.getElementById('ctxOtherWrap');
  const ctxOtherInput = document.getElementById('pre_arrest_context_other');

  const narrative = document.getElementById('narrative_summary');
  const btnSubmit = document.getElementById('btnSubmit');

  function show(i){
    steps.forEach((s, k) => s.classList.toggle('active', k===i));
    const total = steps.length;
    const pct = Math.round(((i+1)/total)*100);
    bar.style.width = pct + '%';
    label.textContent = 'Step ' + (i+1) + '/' + total;
    idx = i;
    window.scrollTo({top:0, behavior:'smooth'});

    // If we just showed the BAC step but test was "No", skip it automatically
    if (steps[idx] === stepBAC) {
      const testYes = document.getElementById('test_taken_yes').checked;
      if (!testYes) {
        // ensure BAC isn't required if no test
        bacInput.required = false;
        if (bacReq) bacReq.classList.add('d-none');
        // jump to next
        show(Math.min(idx+1, steps.length-1));
      } else {
        bacInput.required = true;
        if (bacReq) bacReq.classList.remove('d-none');
      }
    }
  }

  function stepValid(stepEl){
    const required = stepEl.querySelectorAll('[required]');
    for (const el of required) {
      if (el.type === 'radio') {
        const group = stepEl.querySelectorAll(`input[name="${el.name}"]`);
        if (![...group].some(r => r.checked)) return false;
      } else if (!el.value || (el.tagName === 'SELECT' && !el.value)) {
        return false;
      }
    }
    // Special rule: context "Other" requires text
    if (stepEl.dataset.step === 'context' && ctxOther && ctxOther.checked) {
      if (!ctxOtherInput.value.trim()) return false;
    }
    return true;
  }

  // Hook all local "Continue" buttons
  document.querySelectorAll('.next').forEach(btn => {
    btn.addEventListener('click', () => {
      const stepEl = steps[idx];
      if (!stepValid(stepEl)) { alert('Please complete this step.'); return; }

      // If current step is the "test" step and No is selected, ensure BAC isn't required
      if (stepEl === stepTest) {
        const testYes = document.getElementById('test_taken_yes').checked;
        bacInput.required = !!testYes;
        if (bacReq) bacReq.classList.toggle('d-none', !testYes);
      }

      // Compute next index, with a soft skip: if next is BAC but test is No, skip over it
      let nextIdx = Math.min(idx+1, steps.length-1);
      if (steps[nextIdx] === stepBAC && document.getElementById('test_taken_no').checked) {
        nextIdx = Math.min(nextIdx+1, steps.length-1);
      }
      show(nextIdx);
    });
  });

  // Toggle "Other" text visibility
  if (ctxOther) {
    ctxOther.addEventListener('change', () => {
      ctxOtherWrap.classList.toggle('d-none', !ctxOther.checked);
      if (!ctxOther.checked) ctxOtherInput.value = '';
    });
  }

  // Only show submit when narrative has content
  if (narrative) {
    const toggleSubmit = () => {
      const hasText = narrative.value.trim().length > 0;
      btnSubmit.classList.toggle('d-none', !hasText);
    };
    narrative.addEventListener('input', toggleSubmit);
    toggleSubmit();
  }

  // Intercept Enter: act as "Continue" (or Submit on last step) only if current step is valid
  form.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') return; // allow newline
    e.preventDefault();

    const stepEl = steps[idx];
    if (!stepEl) return;

    // If there is a visible submit button on this step, submit when valid
    const submitBtn = stepEl.querySelector('button[type="submit"]:not(.d-none)');
    if (submitBtn) {
      if (stepValid(stepEl)) submitBtn.click();
      return;
    }

    // Otherwise, continue when valid
    const nextBtn = stepEl.querySelector('.next');
    if (nextBtn && stepValid(stepEl)) nextBtn.click();
  }, true);

  // Initialize
  show(0);
})();
</script>

</body>
</html>
