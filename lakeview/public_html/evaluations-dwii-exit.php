<?php
/**
 * evaluations-dwii-exit.php — DWII EXIT INTERVIEW (Lakeview)
 * One page per field, Enter = Continue, conditional steps for AA frequency and Help resources.
 *
 * • GET  : render form
 * • POST : CSRF → validate → INSERT into evaluations_dwii_exit → branded thank-you
 *
 * Requires: /config/config.php for $link (mysqli)
 */

declare(strict_types=1);
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../config/config.php';   // provides $link (mysqli)
/** @var mysqli $link */
$link->set_charset('utf8mb4');

/* ------------------------------- bootstrap table ------------------------------- */
$link->query("
CREATE TABLE IF NOT EXISTS evaluations_dwii_exit (
  id                         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  created_at                 DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  email                      VARCHAR(190) NOT NULL,
  today_date                 DATE         NOT NULL,
  first_name                 VARCHAR(190) NOT NULL,
  last_name                  VARCHAR(190) NOT NULL,

  po_referral                VARCHAR(255) NOT NULL,   -- Name of Probation Officer / Referral Source

  workbook_up_to_date        TINYINT(1)   NOT NULL,   -- 1=yes 0=no
  action_plan_completed      TINYINT(1)   NOT NULL,   -- 1=yes 0=no
  action_plan_realistic      TINYINT(1)   NOT NULL,   -- 1=yes 0=no

  currently_attending_aa     TINYINT(1)   NOT NULL,   -- 1=yes 0=no
  aa_frequency               VARCHAR(190)     NULL,   -- required if currently_attending_aa=1

  still_drinking             TINYINT(1)   NOT NULL,   -- 1=yes 0=no
  need_additional_help       TINYINT(1)   NOT NULL,   -- 1=yes 0=no

  help_resources             VARCHAR(255)     NULL,   -- comma-joined allowed tokens
  help_other_text            VARCHAR(255)     NULL,   -- if 'other' chosen

  reasoning_if_continue      TEXT          NOT NULL,  -- required per prompt
  additional_comments        TEXT              NULL,

  submit_ip                  VARBINARY(16)     NULL,
  user_agent                 VARCHAR(255)      NULL,

  PRIMARY KEY (id),
  KEY idx_email_date (email, today_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* ------------------------------------ helpers ------------------------------------ */
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void {
  if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    http_response_code(403); exit('Invalid CSRF token');
  }
}
function inet_pton_nullable(string $ip) { $bin = @inet_pton($ip); return $bin === false ? null : $bin; }

/* ------------------------------------- POST ------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $email   = trim((string)($_POST['email'] ?? ''));
  $tday    = trim((string)($_POST['today_date'] ?? ''));
  $first   = trim((string)($_POST['first_name'] ?? ''));
  $last    = trim((string)($_POST['last_name'] ?? ''));

  $poRef   = trim((string)($_POST['po_referral'] ?? ''));

  $wbu     = (string)($_POST['workbook_up_to_date'] ?? '');
  $apComp  = (string)($_POST['action_plan_completed'] ?? '');
  $apReal  = (string)($_POST['action_plan_realistic'] ?? '');

  $aaNow   = (string)($_POST['currently_attending_aa'] ?? '');
  $aaFreq  = trim((string)($_POST['aa_frequency'] ?? ''));

  $still   = (string)($_POST['still_drinking'] ?? '');
  $need    = (string)($_POST['need_additional_help'] ?? '');

  $HELP_ALLOWED = ['counseling','aa_na_smart','inpatient','outpatient','lakeview','other'];
  $helpSel      = (array)($_POST['help_resources'] ?? []);
  $helpSel      = array_values(array_intersect($helpSel, $HELP_ALLOWED));
  $helpOther    = trim((string)($_POST['help_resources_other'] ?? ''));

  $reasoning = trim((string)($_POST['reasoning_if_continue'] ?? ''));
  $comments  = trim((string)($_POST['additional_comments'] ?? ''));

  // ---- validation ----
  $errors = [];
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tday)) $errors[] = 'Valid date is required.';
  if ($first === '') $errors[] = 'First name is required.';
  if ($last  === '') $errors[] = 'Last name is required.';

  if ($poRef === '') $errors[] = 'Probation Officer / Referral Source is required (enter NA if not applicable).';

  foreach ([
    'workbook_up_to_date'   => $wbu,
    'action_plan_completed' => $apComp,
    'action_plan_realistic' => $apReal,
    'currently_attending_aa'=> $aaNow,
    'still_drinking'        => $still,
    'need_additional_help'  => $need,
  ] as $label => $val) {
    if (!in_array($val, ['0','1'], true)) $errors[] = "Selection required for: {$label}.";
  }

  if ($aaNow === '1' && $aaFreq === '') {
    $errors[] = 'AA frequency is required when currently attending AA.';
  }

  if ($need === '1') {
    if (count($helpSel) === 0) $errors[] = 'Please choose at least one resource.';
    if (in_array('other', $helpSel, true) && $helpOther === '') {
      $errors[] = 'Please describe the “Other” resource.';
    }
  } else {
    // normalize help to empty if they said no
    $helpSel   = [];
    $helpOther = '';
  }

  // The prompt marks this as required regardless of still_drinking
  if ($reasoning === '') $errors[] = 'Reasoning is required.';

  if ($errors) {
    http_response_code(422);
    echo "<!doctype html><meta charset='utf-8'><title>DWII Exit | Errors</title>";
    echo "<div style='max-width:720px;margin:40px auto;font-family:system-ui,Arial'>";
    echo "<h1>DWII Exit Interview — Submission errors</h1><ul>";
    foreach ($errors as $e) echo "<li>".h($e)."</li>";
    echo "</ul><p><a href='javascript:history.back()'>Go back</a></p></div>";
    exit;
  }

  $wbu_i   = (int)$wbu;
  $apC_i   = (int)$apComp;
  $apR_i   = (int)$apReal;
  $aa_i    = (int)$aaNow;
  $still_i = (int)$still;
  $need_i  = (int)$need;

  $helpStr = implode(',', $helpSel);

    $ipbin = inet_pton_nullable($_SERVER['REMOTE_ADDR'] ?? '');
    $ua    = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $sql = "
        INSERT INTO evaluations_dwii_exit
        (email,today_date,first_name,last_name,
        po_referral,
        workbook_up_to_date,action_plan_completed,action_plan_realistic,
        currently_attending_aa,aa_frequency,
        still_drinking,need_additional_help,
        help_resources,help_other_text,
        reasoning_if_continue,additional_comments,
        submit_ip,user_agent)
        VALUES (?,?,?,?, ?,?,?,?, ?,?, ?,?, ?,?, ?,?, ?,?)
    ";

    $stmt = $link->prepare($sql);

    // 18 params total:
    // ssss  -> email, today_date, first_name, last_name
    // s     -> po_referral
    // iii   -> workbook_up_to_date, action_plan_completed, action_plan_realistic
    // is    -> currently_attending_aa (int), aa_frequency (string)
    // ii    -> still_drinking, need_additional_help
    // ss    -> help_resources, help_other_text
    // ss    -> reasoning_if_continue, additional_comments
    // ss    -> submit_ip (pass '' if null), user_agent
    $typeStr = 'ssss' . 's' . 'iii' . 'is' . 'ii' . 'ss' . 'ss' . 'ss';

    // ensure submit_ip is a string for 's' binding
    $ipForBind = $ipbin ?? '';

    $stmt->bind_param(
        $typeStr,
        $email,$tday,$first,$last,
        $poRef,
        $wbu_i,$apC_i,$apR_i,
        $aa_i,$aaFreq,
        $still_i,$need_i,
        $helpStr,$helpOther,
        $reasoning,$comments,
        $ipForBind,$ua
    );

    if (!$stmt->execute()) {
        throw new RuntimeException('Insert failed: '.$stmt->error);
    }
    $stmt->close();


  // Thank you (no scores)
  echo "<!DOCTYPE html>
  <html lang='en'>
  <head>
    <meta charset='utf-8'>
    <title>Thank you – DWII Exit Interview</title>
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
        <p class='lead mb-2'>Your Exit Interview was submitted successfully.</p>
        <p class='mb-4'>We’ve recorded your responses.</p>
        <a href='https://lakevieweducation.com/' class='btn btn-primary btn-lg'>Lakeview Education Home</a>
      </div>
    </div>
  </body>
  </html>";
  exit;
}

/* -------------------------------------- GET -------------------------------------- */
$csrf  = csrf_token();
$today = date('Y-m-d');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>EXIT INTERVIEW (DWII)</title>
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
      <h1 class="title">Lakeview — EXIT INTERVIEW (DWII)</h1>
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
      <h1 class="h4 mb-0">EXIT INTERVIEW</h1>
      <span class="small text-muted" id="progressLabel">Step 1</span>
    </div>
    <div class="progress mt-2"><div id="bar" class="progress-bar" role="progressbar" style="width:0%"></div></div>
  </div>

  <div class="card">
    <div class="card-body">
      <form id="exitForm" method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <!-- 1: Email, Date, Name -->
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

            <div class="form-group">
                <label for="email">Email<span class="text-danger">*</span></label>
                <input id="email" name="email" type="email" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="today_date">Today’s Date<span class="text-danger">*</span></label>
                <input id="today_date" name="today_date" type="date" class="form-control" required value="<?= h($today) ?>">
            </div>

            <button type="button" class="btn btn-primary next">Continue</button>
            </div>


        <!-- 2: PO / Referral -->
        <div class="step">
          <div class="form-group">
            <label for="po_referral">Name of Probation Officer / Referral Source<span class="text-danger">*</span></label>
            <input id="po_referral" name="po_referral" type="text" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 3: Workbook up to date -->
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

        <!-- 4: Action plan completed -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Have you completed your Action Plan?<span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="apc_y" name="action_plan_completed" value="1" required>
              <label class="custom-control-label" for="apc_y">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="apc_n" name="action_plan_completed" value="0" required>
              <label class="custom-control-label" for="apc_n">No</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- 5: Action plan realistic -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Is your Action Plan realistic and workable?<span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="apr_y" name="action_plan_realistic" value="1" required>
              <label class="custom-control-label" for="apr_y">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="apr_n" name="action_plan_realistic" value="0" required>
              <label class="custom-control-label" for="apr_n">No</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- 6: Currently attending AA -->
        <div class="step" id="step-attending-aa">
          <fieldset>
            <legend class="h6 mb-3">Are you currently attending AA?<span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="aa_y" name="currently_attending_aa" value="1" required>
              <label class="custom-control-label" for="aa_y">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="aa_n" name="currently_attending_aa" value="0" required>
              <label class="custom-control-label" for="aa_n">No</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- 7: AA Frequency (conditional) -->
        <div class="step" id="step-aa-frequency">
          <div class="form-group">
            <label for="aa_frequency">If yes, how often?<span class="text-danger">*</span></label>
            <input id="aa_frequency" name="aa_frequency" type="text" class="form-control" placeholder="e.g., 2x/week at Tuesday 7pm and Thursday 6pm">
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 8: Still drinking -->
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Are you still drinking?<span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="sd_y" name="still_drinking" value="1" required>
              <label class="custom-control-label" for="sd_y">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="sd_n" name="still_drinking" value="0" required>
              <label class="custom-control-label" for="sd_n">No</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- 9: Need additional help -->
        <div class="step" id="step-need-help">
          <fieldset>
            <legend class="h6 mb-3">Do you need additional help for your drinking problem?<span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="nh_y" name="need_additional_help" value="1" required>
              <label class="custom-control-label" for="nh_y">Yes</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="nh_n" name="need_additional_help" value="0" required>
              <label class="custom-control-label" for="nh_n">No</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- 10: Help resources (conditional) -->
        <div class="step" id="step-help-resources">
          <fieldset>
            <legend class="h6 mb-3">If yes, what resources will you use?<span class="text-danger">*</span></legend>
            <?php
              $HELP = [
                'counseling'   => 'Counseling',
                'aa_na_smart'  => 'AA / NA / SMART Recovery',
                'inpatient'    => 'In-patient treatment',
                'outpatient'   => 'Out-patient treatment',
                'lakeview'     => 'Lakeview Education',
                'other'        => 'Other (describe)'
              ];
              foreach ($HELP as $val => $label):
            ?>
            <div class="custom-control custom-checkbox mb-2">
              <input class="custom-control-input" type="checkbox" id="help_<?= h($val) ?>" name="help_resources[]" value="<?= h($val) ?>">
              <label class="custom-control-label" for="help_<?= h($val) ?>"><?= h($label) ?></label>
            </div>
            <?php endforeach; ?>

            <div class="form-group mt-2">
              <label for="help_resources_other">If “Other”, describe</label>
              <input id="help_resources_other" name="help_resources_other" type="text" class="form-control" placeholder="Describe other resource">
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- 11: Reasoning -->
        <div class="step">
          <div class="form-group">
            <label for="reasoning_if_continue">If you choose to continue to drink/use despite negative consequences, explain reasoning.<span class="text-danger">*</span></label>
            <textarea id="reasoning_if_continue" name="reasoning_if_continue" class="form-control" rows="4" required></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 12: Additional comments (optional) -->
        <div class="step">
          <div class="form-group">
            <label for="additional_comments">Additional comments (optional):</label>
            <textarea id="additional_comments" name="additional_comments" class="form-control" rows="4"></textarea>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- 13: Final page (instructor note + Calendly + submit) -->
        <div class="step">
        <p class="mb-1"><strong>Specific Recommendations:</strong></p>

        <div class="border rounded p-3 my-3 bg-light">
            <p class="mb-2"><strong>Schedule your one-hour exit meeting:</strong></p>
            <a class="btn btn-outline-primary btn-sm"
            href="https://calendly.com/lakevieweducation/1-hour-meeting"
            target="_blank" rel="noopener">
            Open Calendly in a new tab
            </a>
            <div class="calendly-inline-widget mt-3"
                data-url="https://calendly.com/lakevieweducation/1-hour-meeting"
                style="min-width:320px;height:680px;"></div>
            <script src="https://assets.calendly.com/assets/external/widget.js" async></script>
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
  const bar = document.getElementById('bar');
  const label = document.getElementById('progressLabel');
  let idx = 0;

  const aaRadio = document.querySelectorAll('input[name="currently_attending_aa"]');
  const needHelpRadio = document.querySelectorAll('input[name="need_additional_help"]');

  const aaFreqInput = document.getElementById('aa_frequency');
  const helpOther   = document.getElementById('help_resources_other');

  function show(i){
    steps.forEach((s, k) => s.classList.toggle('active', k===i));
    const total = steps.length;
    const pct = Math.round(((i+1)/total)*100);
    if (bar) bar.style.width = pct + '%';
    if (label) label.textContent = 'Step ' + (i+1) + '/' + total;
    idx = i;
    window.scrollTo({top:0, behavior:'smooth'});
  }

  function radiosChecked(name){
    const grp = document.querySelectorAll('input[name="'+name+'"]');
    return Array.from(grp).some(r => r.checked);
  }

  function stepValid(stepEl){
    const required = stepEl.querySelectorAll('[required]');
    for (const el of required) {
      if (el.type === 'radio') {
        const group = stepEl.querySelectorAll('input[name="'+el.name+'"]');
        if (![...group].some(r=>r.checked)) return false;
      } else if (!el.value) {
        return false;
      }
    }
    // Special cases:
    if (stepEl.id === 'step-aa-frequency') {
      const isAA = radiosChecked('currently_attending_aa') && document.getElementById('aa_y').checked;
      if (isAA && !aaFreqInput.value) return false;
    }
    if (stepEl.id === 'step-help-resources') {
      const need = radiosChecked('need_additional_help') && document.getElementById('nh_y').checked;
      if (need) {
        const anyChecked = Array.from(document.querySelectorAll('input[name="help_resources[]"]')).some(c => c.checked);
        if (!anyChecked) return false;
        const otherChecked = document.getElementById('help_other') ? document.getElementById('help_other').checked : false;
        // handle 'other' by id properly:
        const otherBox = document.getElementById('help_other') || document.getElementById('help_other_text');
        const otherIsChecked = document.getElementById('help_other') ? document.getElementById('help_other').checked : document.getElementById('help_other_text')?.checked;
        const realOtherChecked = document.getElementById('help_other') ? document.getElementById('help_other').checked : document.getElementById('help_other_text')?.checked;
        const otherChecked2 = document.getElementById('help_other') ? document.getElementById('help_other').checked : document.getElementById('help_other')?.checked;
        // Simpler: find checkbox with value="other"
        const otherCk = Array.from(document.querySelectorAll('input[name="help_resources[]"]')).find(x => x.value === 'other');
        if (otherCk && otherCk.checked && !helpOther.value) return false;
      }
    }
    return true;
  }

  function nextIndexFrom(i){
    // Conditional jumps after certain steps:
    // after 'currently_attending_aa' (idx for that step), if No -> skip AA frequency step
    const stepEl = steps[i];
    if (stepEl && stepEl.id === 'step-attending-aa') {
      const aaNo = document.getElementById('aa_n').checked;
      if (aaNo) {
        if (aaFreqInput) aaFreqInput.value = '';
        return Math.min(i+2, steps.length-1); // skip frequency
      }
    }
    if (stepEl && stepEl.id === 'step-need-help') {
      const helpNo = document.getElementById('nh_n').checked;
      if (helpNo) {
        // clear help selections
        document.querySelectorAll('input[name="help_resources[]"]').forEach(c=>c.checked=false);
        if (helpOther) helpOther.value = '';
        return Math.min(i+2, steps.length-1); // skip help resources step
      }
    }
    return Math.min(i+1, steps.length-1);
  }

  nextButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const stepEl = steps[idx];
      if (!stepValid(stepEl)) { alert('Please complete this step.'); return; }
      show(nextIndexFrom(idx));
    });
  });

  show(0);
})();
</script>

<script>
// Enter behaves like Continue (never submit early), allow Enter in textareas as newline.
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('exitForm');
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
      } else if (!el.checkValidity()) { return false; }
    }
    // Handle conditional pages
    if (step.id === 'step-aa-frequency') {
      if (document.getElementById('aa_y').checked && !document.getElementById('aa_frequency').value) return false;
    }
    if (step.id === 'step-help-resources') {
      if (document.getElementById('nh_y').checked) {
        const any = Array.from(document.querySelectorAll('input[name="help_resources[]"]')).some(c=>c.checked);
        if (!any) return false;
        const otherBox = Array.from(document.querySelectorAll('input[name="help_resources[]"]')).find(c=>c.value==='other');
        if (otherBox && otherBox.checked && !document.getElementById('help_resources_other').value) return false;
      }
    }
    return true;
  }

  function nextIndexFrom(step) {
    const steps = Array.from(document.querySelectorAll('.step'));
    const idx = steps.findIndex(s => s === step);
    if (idx < 0) return 0;
    if (step.id === 'step-attending-aa' && document.getElementById('aa_n').checked) {
      return Math.min(idx+2, steps.length-1);
    }
    if (step.id === 'step-need-help' && document.getElementById('nh_n').checked) {
      return Math.min(idx+2, steps.length-1);
    }
    return Math.min(idx+1, steps.length-1);
  }

  form.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    const ae = document.activeElement;
    if (ae && ae.tagName === 'TEXTAREA') return; // allow newline in textareas
    e.preventDefault();

    const step = activeStepEl();
    const submitBtn = step ? Array.from(step.querySelectorAll('button[type="submit"],input[type="submit"]'))
                               .find(b => b.offsetParent !== null) : null;
    if (submitBtn) {
      if (stepIsValid(step)) submitBtn.click();
      return;
    }
    const nextBtn = step ? Array.from(step.querySelectorAll('.next')).find(b => b.offsetParent !== null) : null;
    if (stepIsValid(step) && nextBtn) {
      // support conditional jumping
      const steps = Array.from(document.querySelectorAll('.step'));
      const idx = steps.findIndex(s => s === step);
      const target = nextIndexFrom(step);
      steps.forEach((s, k) => s.classList.toggle('active', k===target));
      const bar = document.getElementById('bar'); const label = document.getElementById('progressLabel');
      if (bar && label) {
        const pct = Math.round(((target+1)/steps.length)*100);
        bar.style.width = pct + '%';
        label.textContent = 'Step ' + (target+1) + '/' + steps.length;
      }
      window.scrollTo({top:0, behavior:'smooth'});
    }
  }, true);
});
</script>
</body>
</html>
