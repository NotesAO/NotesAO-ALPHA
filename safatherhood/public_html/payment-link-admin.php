<?php
// payment-link-admin.php — Admin page to set per-group payment URLs
require_once __DIR__ . '/auth.php';     // provides $con (mysqli) + session
check_loggedin($con);

require_once __DIR__ . '/helpers.php';  // for h()
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

// --- CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];

// --- flash
$flash_ok = [];
$flash_err = [];

// --- helpers
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

function get_settings_like(mysqli $con, string $prefix): array {
  $out = [];
  $sql = "SELECT setting_key, setting_value FROM portal_setting WHERE setting_key LIKE CONCAT(?, '%')";
  if (!$stmt = $con->prepare($sql)) return $out;
  $stmt->bind_param('s', $prefix);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $out[$row['setting_key']] = (string)$row['setting_value'];
  $stmt->close();
  return $out;
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

// --- load groups (Virtual only, no orientations)
$groups = [];
$q = "
  SELECT id, program_id, name, address
  FROM therapy_group
  WHERE address = 'Virtual'
    AND name NOT LIKE '%Orientation%'
    AND name NOT LIKE '%ORE%'
  ORDER BY program_id, id
";
if ($rs = $con->query($q)) {
  while ($r = $rs->fetch_assoc()) $groups[] = $r;
  $rs->close();
}


// --- load existing paylink settings
$paylink_map = get_settings_like($con, 'paylink.tg.');
$val_fallback = get_setting($con, 'payment_link_url'); // optional global fallback
// --- load existing promo settings
$promo_map      = get_settings_like($con, 'promo.tg.');
$promo_fallback = get_setting($con, 'promo.note'); // optional global fallback note


// --- POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'save') {
  if (!isset($_POST['csrf']) || !hash_equals($CSRF, (string)$_POST['csrf'])) {
    $flash_err[] = 'Security check failed. Please refresh and try again.';
  } else {
    $is_valid_url = function(string $url): bool {
      if ($url === '') return true; // allow blank (means use fallback)
      return (bool)preg_match('~^https?://[^\s]+$~i', $url);
    };

    // Global fallback
    $new_fallback = trim((string)($_POST['payment_link_fallback'] ?? ''));
    if (!$is_valid_url($new_fallback)) {
      $flash_err[] = 'Invalid URL for global fallback.';
    }

    // Per-group values
    $updates = $_POST['payment_url'] ?? []; // payment_url[ID] => url
    foreach ($updates as $id => $url) {
      $id  = (int)$id;
      $url = trim((string)$url);
      if (!$is_valid_url($url)) {
        $flash_err[] = "Invalid URL for group #{$id}.";
      }
    }

    if (!$flash_err) {
      // save fallback
      if (!save_setting($con, 'payment_link_url', $new_fallback)) {
        $flash_err[] = 'Failed to save global fallback link.';
      }

      // save each per-group url
      $ok_all = true;
      foreach ($updates as $id => $url) {
        $id = (int)$id;
        $k = "paylink.tg.$id";
        $ok_all = save_setting($con, $k, trim((string)$url)) && $ok_all;
      }

      if ($ok_all && !$flash_err) {
        $flash_ok[] = 'Payment links saved.';
        // refresh maps
        $paylink_map = get_settings_like($con, 'paylink.tg.');
        $val_fallback = get_setting($con, 'payment_link_url');
      } else {
        $flash_err[] = 'A database error occurred while saving one or more links.';
      }
    }
    // (optional) promo fallback (free text)
    $promo_fallback_new = trim((string)($_POST['promo_note_fallback'] ?? ''));
    // no special validation needed; keep it short if you want:
    if (mb_strlen($promo_fallback_new) > 140) {
      $flash_err[] = 'Global promo note is too long (max 140 chars).';
    }

    // per-group promo notes
    $promo_updates = $_POST['promo_note'] ?? []; // promo_note[ID] => text
    foreach ($promo_updates as $id => $txt) {
      $id  = (int)$id;
      $txt = trim((string)$txt);
      if ($id <= 0) continue;
      if (mb_strlen($txt) > 140) $flash_err[] = "Promo note too long for group #{$id} (max 140 chars).";
    }

    if (!$flash_err) {
      // save promo fallback
      if (!save_setting($con, 'promo.note', $promo_fallback_new)) {
        $flash_err[] = 'Failed to save global promo note.';
      }

      // save each per-group promo
      $ok_all_promos = true;
      foreach ($promo_updates as $id => $txt) {
        $id = (int)$id;
        $k  = "promo.tg.$id";
        $ok_all_promos = save_setting($con, $k, trim((string)$txt)) && $ok_all_promos;
      }

      if ($ok_all_promos && !$flash_err) {
        // refresh maps
        $promo_map      = get_settings_like($con, 'promo.tg.');
        $promo_fallback = get_setting($con, 'promo.note');
        $flash_ok[]     = 'Promo notes saved.';
      } else if (!$flash_err) {
        $flash_err[] = 'A database error occurred while saving one or more promo notes.';
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
<title>Client Portal — Group Payment Links</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" crossorigin="anonymous">
<link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0-14/css/all.min.css"
        integrity="sha512-YVm6dLGBSj6KG3uUb1L5m25JXXYrd9yQ1P7RKDSstzYiPxI2vYLCCGyfrlXw3YcN/EM3UJ/IAqsCmfdc6pk/Tg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
  body { padding-top:56px; background:#f6f7fb; }
  .safe-card { background:#fff; border:1px solid #dee2e6; border-radius:.5rem; box-shadow:0 2px 6px rgba(0,0,0,.06); }
  .form-help { font-size:.9rem; color:#6b7280; }
  .sticky-actions { position: sticky; bottom: 0; background:#fff; padding:.75rem; border-top:1px solid #e5e7eb; }
  .table td, .table th { vertical-align: middle; }
  .searchbar { max-width: 380px; }
</style>
</head>
<body>

<?php require_once 'navbar.php'; ?>

<section class="pt-4">
  <div class="container" style="max-width:1100px">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h4 mb-0">Client Portal — Group Payment Links</h1>
      <form class="form-inline" onsubmit="return false;">
        <input type="search" id="q" class="form-control searchbar" placeholder="Filter groups…">
      </form>
    </div>

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
        Set <strong>one payment link per therapy group</strong>. If a group’s link is blank,
        the portal falls back to the global link below. You can also set
        <strong>promo notes</strong> per group (or a global fallback) that the client portal will display.
      </p>


      <form method="post">
        <input type="hidden" name="csrf" value="<?=h($CSRF)?>">
        <input type="hidden" name="act" value="save">

        <div class="mb-3">
          <label class="font-weight-bold">Global fallback payment link</label>
          <input type="url" class="form-control" name="payment_link_fallback" placeholder="https://…" value="<?=h($val_fallback)?>">
          <small class="form-help">Key: <code>payment_link_url</code></small>
        </div>

        <div class="mb-3">
          <label class="font-weight-bold">Global promo note (optional)</label>
          <input type="text" class="form-control" name="promo_note_fallback"
                 maxlength="140"
                 placeholder="e.g., Use code REDUCED at checkout."
                 value="<?= h($promo_fallback) ?>">

          <small class="form-help">Key: <code>promo.note</code> (shown if group has no specific promo)</small>
        </div>


        <div class="table-responsive">
          <table class="table table-sm table-bordered" id="gtable">
            <thead class="thead-light">


              <tr>
                <th style="width:90px;">TG ID</th>
                <th>Program</th>
                <th>Name</th>
                <th style="width:160px;">Address / Modality</th>
                <th>Payment URL (paylink.tg.&lt;id&gt;)</th>
                <th style="width:260px;">Promo note (promo.tg.&lt;id&gt;)</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($groups as $g):
              $id  = (int)$g['id'];
              $pid = (int)$g['program_id'];
              $key = "paylink.tg.$id";
              $val = $paylink_map[$key] ?? '';
              $prog = ($pid===1?'Anger':($pid===2?'Men’s BIPP':($pid===3?'Women’s BIPP':($pid===4?'Theft':'Other'))));
              $addr = trim((string)($g['address'] ?? ''));
              $name = (string)$g['name'];
              if (strcasecmp($addr, 'Virtual') !== 0) continue;
              if (stripos($name, 'orientation') !== false || stripos($name, 'ORE') !== false) continue;

              $tgId = (int)$g['id'];
              $key  = "paylink.tg.$tgId";
              $val  = $paylink_map[$key] ?? '';

            ?>
              <tr data-name="<?=h($g['name'])?>" data-prog="<?=h($prog)?>" data-addr="<?=h($addr)?>">
                <td><code><?=$id?></code></td>
                <td><?=h($prog)?></td>
                <td><?=h($g['name'])?></td>
                <td><?=h($addr ?: '—')?></td>
                <td>
                  <input type="url" class="form-control form-control-sm" name="payment_url[<?=$id?>]" placeholder="https://…" value="<?=h($val)?>">
                  <small class="text-muted">Key: <code><?=$key?></code></small>
                </td>
                <?php
                  $promo_key = "promo.tg.$id";
                  $promo_val = $promo_map[$promo_key] ?? '';
                ?>
                <td>
                  <input type="text" class="form-control form-control-sm"
                         name="promo_note[<?=$id?>]" maxlength="140"
                         placeholder="Leave blank to use global"
                         value="<?= h($promo_val) ?>">

                  <small class="text-muted">Key: <code><?= $promo_key ?></code></small>
                </td>

              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="sticky-actions mt-3 d-flex justify-content-between">
          <div class="form-help">Tip: leave blank to inherit the global fallback.</div>
          <div>
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="" class="btn btn-light">Cancel</a>
          </div>
        </div>
      </form>
    </div>

    <div class="small text-muted">
      <strong>Portal behavior:</strong> in <code>clientportal.php</code> we already resolve the group’s payment link via
      <code>paylink.tg.{therapy_group_id}</code> with fallback <code>payment_link_url</code>.
      The promo code banner remains driven by the client’s <em>fee</em>.
    </div>

  </div>
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script>
  // simple client-side filter
  $('#q').on('input', function(){
    const q = this.value.toLowerCase();
    $('#gtable tbody tr').each(function(){
      const hay = (this.dataset.name + ' ' + this.dataset.prog + ' ' + this.dataset.addr).toLowerCase();
      this.style.display = hay.indexOf(q) !== -1 ? '' : 'none';
    });
  });
</script>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"
        integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="
        crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>
</html>
