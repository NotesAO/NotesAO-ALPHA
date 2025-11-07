<?php
// payment-link-admin.php — Admin page to set per-fee payment links and promo notes
require_once __DIR__ . '/auth.php';     // provides $con (mysqli) + session
check_loggedin($con);

require_once __DIR__ . '/helpers.php';  // for h()
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* ───────────────────────────────────────────────────────────────────────────
   CSRF
   ─────────────────────────────────────────────────────────────────────────── */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

/* ───────────────────────────────────────────────────────────────────────────
   Flash
   ─────────────────────────────────────────────────────────────────────────── */
$flash_ok  = [];
$flash_err = [];

/* ───────────────────────────────────────────────────────────────────────────
   Helpers: settings
   ─────────────────────────────────────────────────────────────────────────── */
function current_account_id(): ?int {
  foreach (['id','account_id','user_id','uid'] as $k) {
    if (isset($_SESSION[$k]) && ctype_digit((string)$_SESSION[$k])) {
      $n = (int)$_SESSION[$k];
      if ($n > 0) return $n;
    }
  }
  return null;
}

function save_setting(mysqli $con, string $key, string $value, ?int $user_id = null): bool {
  $uid = $user_id ?? current_account_id();
  if ($uid === null) {
    $sql = "INSERT INTO portal_setting (setting_key, setting_value, updated_by, updated_at)
            VALUES (?, ?, NULL, NOW())
            ON DUPLICATE KEY UPDATE
              setting_value = VALUES(setting_value),
              updated_by    = VALUES(updated_by),
              updated_at    = VALUES(updated_at)";
    if (!$stmt = $con->prepare($sql)) return false;
    $stmt->bind_param('ss', $key, $value);
  } else {
    $sql = "INSERT INTO portal_setting (setting_key, setting_value, updated_by, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
              setting_value = VALUES(setting_value),
              updated_by    = VALUES(updated_by),
              updated_at    = VALUES(updated_at)";
    if (!$stmt = $con->prepare($sql)) return false;
    $stmt->bind_param('ssi', $key, $value, $uid);
  }
  $ok = $stmt->execute();
  if (!$ok) error_log('portal_setting upsert failed: '.$stmt->error);
  $stmt->close();
  return $ok;
}

function get_setting(mysqli $con, string $key): string {
  $sql = "SELECT setting_value FROM portal_setting WHERE setting_key = ? LIMIT 1";
  if (!$stmt = $con->prepare($sql)) return '';
  $stmt->bind_param('s', $key);
  $stmt->execute();
  $stmt->bind_result($val);
  $out = $stmt->fetch() ? (string)$val : '';
  $stmt->close();
  return $out;
}

/* ───────────────────────────────────────────────────────────────────────────
   Load existing values
   - payment_link_url        : required, used for $25 and as fallback
   - payment_link_url_15     : optional override when fee == 15
   - payment_link_url_10     : optional override when fee == 10
   - promo.note.25/.15/.10   : optional per-fee notes shown next to button
   - promo.note              : legacy fallback used if .15/.10 absent
   ─────────────────────────────────────────────────────────────────────────── */
$link25 = get_setting($con, 'payment_link_url');     // legacy key retained
$link15 = get_setting($con, 'payment_link_url_15');
$link10 = get_setting($con, 'payment_link_url_10');

$note25 = get_setting($con, 'promo.note.25');
$note15 = get_setting($con, 'promo.note.15');
$note10 = get_setting($con, 'promo.note.10');
$noteLegacy = get_setting($con, 'promo.note');       // fallback for 10/15 if per-fee blank

/* ───────────────────────────────────────────────────────────────────────────
   POST: save
   ─────────────────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save') {
  if (!isset($_POST['csrf']) || !hash_equals($CSRF, (string)$_POST['csrf'])) {
    $flash_err[] = 'Security check failed. Please refresh and try again.';
  } else {
    $new25 = trim((string)($_POST['payment_link_url_25'] ?? ''));
    $new15 = trim((string)($_POST['payment_link_url_15'] ?? ''));
    $new10 = trim((string)($_POST['payment_link_url_10'] ?? ''));

    $n25 = trim((string)($_POST['promo_note_25'] ?? ''));
    $n15 = trim((string)($_POST['promo_note_15'] ?? ''));
    $n10 = trim((string)($_POST['promo_note_10'] ?? ''));
    $nLegacy = trim((string)($_POST['promo_note_legacy'] ?? '')); // optional legacy

    $is_url = function(string $u): bool {
      if ($u === '') return true; // allow blank for optional overrides
      return (bool)preg_match('~^https?://[^\s]+$~i', $u);
    };

    // required: $25 link
    if ($new25 === '' || !$is_url($new25)) {
      $flash_err[] = 'Provide a valid HTTPS URL for the $25 link.';
    }
    if (!$is_url($new15)) $flash_err[] = 'Invalid $15 link URL.';
    if (!$is_url($new10)) $flash_err[] = 'Invalid $10 link URL.';

    foreach ([['$25 note',$n25],['$15 note',$n15],['$10 note',$n10],['legacy note',$nLegacy]] as [$label,$val]) {
      if (mb_strlen($val) > 140) $flash_err[] = "$label is too long (max 140 chars).";
    }

    if (!$flash_err) {
      $ok = true;
      $ok = $ok && save_setting($con, 'payment_link_url', $new25);   // keep legacy key
      $ok = $ok && save_setting($con, 'payment_link_url_15', $new15);
      $ok = $ok && save_setting($con, 'payment_link_url_10', $new10);

      $ok = $ok && save_setting($con, 'promo.note.25', $n25);
      $ok = $ok && save_setting($con, 'promo.note.15', $n15);
      $ok = $ok && save_setting($con, 'promo.note.10', $n10);
      // keep legacy for older portal code still reading promo.note
      $ok = $ok && save_setting($con, 'promo.note', $nLegacy);

      if ($ok) {
        $flash_ok[] = 'Settings saved.';

        // refresh
        $link25 = get_setting($con, 'payment_link_url');
        $link15 = get_setting($con, 'payment_link_url_15');
        $link10 = get_setting($con, 'payment_link_url_10');

        $note25 = get_setting($con, 'promo.note.25');
        $note15 = get_setting($con, 'promo.note.15');
        $note10 = get_setting($con, 'promo.note.10');
        $noteLegacy = get_setting($con, 'promo.note');
      } else {
        $flash_err[] = 'A database error occurred while saving settings.';
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Client Portal — Payment Settings</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" crossorigin="anonymous">
<style>
  body { padding-top:56px; background:#f6f7fb; }
  .safe-card { background:#fff; border:1px solid #dee2e6; border-radius:.5rem; box-shadow:0 2px 6px rgba(0,0,0,.06); }
  .form-help { font-size:.9rem; color:#6b7280; }
  .fee-badge { font-weight:600; }
</style>
</head>
<body>

<?php require_once 'navbar.php'; ?>

<section class="pt-4">
  <div class="container" style="max-width:880px">

    <h1 class="h4 mb-3">Client Portal — Payment Settings</h1>

    <?php if ($flash_err): ?>
      <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($flash_err as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <?php if ($flash_ok): ?>
      <div class="alert alert-success mb-3">
        <?php foreach ($flash_ok as $m): ?><div><?=h($m)?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="safe-card p-3 p-sm-4 mb-4">
      <p class="form-help mb-3">
        Configure per-fee payment behavior. The <span class="fee-badge">$25</span> link is required and acts as the default. 
        The <span class="fee-badge">$15</span> and <span class="fee-badge">$10</span> links are optional; if left blank the portal falls back to the $25 link.
        Notes are optional and shown next to the button for the matching fee.
      </p>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
        <input type="hidden" name="act" value="save">

        <!-- $25 -->
        <fieldset class="border rounded p-3 mb-4">
          <legend class="w-auto px-2 small mb-0">Fee = $25 (Regular)</legend>
          <div class="mb-3">
            <label class="font-weight-bold">Payment link (required)</label>
            <input type="url" class="form-control" name="payment_link_url_25"
                   placeholder="https://…" value="<?=h($link25)?>" required>
            <small class="form-help">Key: <code>payment_link_url</code></small>
          </div>
          <div class="mb-0">
            <label class="font-weight-bold">Note shown next to button (optional)</label>
            <input type="text" class="form-control" name="promo_note_25" maxlength="140"
                   placeholder="Optional message for $25 payers"
                   value="<?=h($note25)?>">
            <small class="form-help">Key: <code>promo.note.25</code></small>
          </div>
        </fieldset>

        <!-- $15 -->
        <fieldset class="border rounded p-3 mb-4">
          <legend class="w-auto px-2 small mb-0">Fee = $15 (Reduced)</legend>
          <div class="mb-3">
            <label class="font-weight-bold">Payment link override (optional)</label>
            <input type="url" class="form-control" name="payment_link_url_15"
                   placeholder="https://…  (leave blank to use $25 link)"
                   value="<?=h($link15)?>">
            <small class="form-help">Key: <code>payment_link_url_15</code></small>
          </div>
          <div class="mb-0">
            <label class="font-weight-bold">Note shown next to button (optional)</label>
            <input type="text" class="form-control" name="promo_note_15" maxlength="140"
                   placeholder="e.g., Use code REDUCED15 at checkout."
                   value="<?=h($note15)?>">
            <small class="form-help">Key: <code>promo.note.15</code></small>
          </div>
        </fieldset>

        <!-- $10 -->
        <fieldset class="border rounded p-3 mb-4">
          <legend class="w-auto px-2 small mb-0">Fee = $10 (Reduced)</legend>
          <div class="mb-3">
            <label class="font-weight-bold">Payment link override (optional)</label>
            <input type="url" class="form-control" name="payment_link_url_10"
                   placeholder="https://…  (leave blank to use $25 link)"
                   value="<?=h($link10)?>">
            <small class="form-help">Key: <code>payment_link_url_10</code></small>
          </div>
          <div class="mb-0">
            <label class="font-weight-bold">Note shown next to button (optional)</label>
            <input type="text" class="form-control" name="promo_note_10" maxlength="140"
                   placeholder="e.g., Use code REDUCED10 at checkout."
                   value="<?=h($note10)?>">
            <small class="form-help">Key: <code>promo.note.10</code></small>
          </div>
        </fieldset>

        <!-- Legacy promo note for backward compatibility -->
        <details class="mb-3">
          <summary class="text-muted">Legacy compatibility</summary>
          <div class="mt-2">
            <label class="font-weight-bold">Legacy promo note for reduced fees</label>
            <input type="text" class="form-control" name="promo_note_legacy" maxlength="140"
                   placeholder="Used only by older portal code"
                   value="<?=h($noteLegacy)?>">
            <small class="form-help">Key: <code>promo.note</code> (used if <code>promo.note.15</code>/<code>.10</code> are blank)</small>
          </div>
        </details>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Save changes</button>
          <a href="" class="btn btn-light ml-2">Cancel</a>
        </div>
      </form>
    </div>

    <div class="small text-muted">
      <strong>Portal selection logic:</strong>
      <pre class="mt-2 mb-0"><code>
// $fee is 25, 15, or 10 (int)
$link = get_setting($con, 'payment_link_url');               // $25 base
if ($fee === 15) $link = get_setting($con, 'payment_link_url_15') ?: $link;
if ($fee === 10) $link = get_setting($con, 'payment_link_url_10') ?: $link;

$note = '';
if ($fee === 25) $note = get_setting($con, 'promo.note.25') ?: '';
if ($fee === 15) $note = get_setting($con, 'promo.note.15') ?: (get_setting($con, 'promo.note') ?: '');
if ($fee === 10) $note = get_setting($con, 'promo.note.10') ?: (get_setting($con, 'promo.note') ?: '');
      </code></pre>
    </div>

  </div>
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" crossorigin="anonymous"></script>
</body>
</html>
