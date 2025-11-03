<?php
// payment-link-admin.php — Admin page to set global payment link and reduced-fee promo note
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
   Load existing values (GLOBAL ONLY)
   - payment_link_url : the single link used by the portal (your $25 “regular” link)
   - promo.note       : text shown only when client fee is 10 or 15
   ─────────────────────────────────────────────────────────────────────────── */
$payment_link_url = get_setting($con, 'payment_link_url');
$promo_note       = get_setting($con, 'promo.note');

/* ───────────────────────────────────────────────────────────────────────────
   POST: save
   ─────────────────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save') {
  if (!isset($_POST['csrf']) || !hash_equals($CSRF, (string)$_POST['csrf'])) {
    $flash_err[] = 'Security check failed. Please refresh and try again.';
  } else {
    $new_link = trim((string)($_POST['payment_link_url'] ?? ''));
    $new_note = trim((string)($_POST['promo_note'] ?? ''));

    // validate URL (required)
    if ($new_link === '' || !preg_match('~^https?://[^\s]+$~i', $new_link)) {
      $flash_err[] = 'Please provide a valid HTTPS URL for the payment link.';
    }

    // optional, but keep promo note reasonably short
    if (mb_strlen($new_note) > 140) {
      $flash_err[] = 'Promo note is too long (max 140 chars).';
    }

    if (!$flash_err) {
      $ok1 = save_setting($con, 'payment_link_url', $new_link);
      $ok2 = save_setting($con, 'promo.note', $new_note);

      if ($ok1 && $ok2) {
        $flash_ok[] = 'Settings saved.';
        // refresh
        $payment_link_url = get_setting($con, 'payment_link_url');
        $promo_note       = get_setting($con, 'promo.note');
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
</style>
</head>
<body>

<?php require_once 'navbar.php'; ?>

<section class="pt-4">
  <div class="container" style="max-width:820px">

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
        Configure a <strong>single global payment link</strong> used by the client portal (this is your
        regular <strong>$25</strong> link). If a client’s fee is <strong>$10 or $15</strong>, the portal will
        display the <strong>promo note</strong> you set below (e.g., “Use code REDUCED at checkout.”).
      </p>

      <form method="post">
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
        <input type="hidden" name="act" value="save">

        <div class="mb-3">
          <label class="font-weight-bold">Global payment link (regular $25)</label>
          <input type="url"
                 class="form-control"
                 name="payment_link_url"
                 placeholder="https://…"
                 value="<?= h($payment_link_url) ?>"
                 required>
          <small class="form-help">Key: <code>payment_link_url</code></small>
        </div>

        <div class="mb-3">
          <label class="font-weight-bold">Promo note for reduced fees ($10/$15)</label>
          <input type="text"
                 class="form-control"
                 name="promo_note"
                 maxlength="140"
                 placeholder="e.g., Use code REDUCED at checkout."
                 value="<?= h($promo_note) ?>">
          <small class="form-help">Key: <code>promo.note</code> (shown only when client fee is 10 or 15)</small>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Save changes</button>
          <a href="" class="btn btn-light ml-2">Cancel</a>
        </div>
      </form>
    </div>

    <div class="small text-muted">
      <strong>Portal behavior:</strong>
      the client portal reads <code>payment_link_url</code> for the “Pay Now” button.
      If the client’s <em>fee</em> is <strong>10</strong> or <strong>15</strong>, it also shows
      <code>promo.note</code>. No per-group links or promos are used.
    </div>

  </div>
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" crossorigin="anonymous"></script>
</body>
</html>
