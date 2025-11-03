<?php
/**
 * client-group-dates.php — Admin to post upcoming one-off dates for programs where "dates vary"
 * Example use-cases: DOEP (program_id=1), DWII (program_id=3)
 *
 * Requires pre-created table: clientportal_group_dates
 *   Columns: id, program_id, therapy_group_id (nullable), starts_at (DATETIME), note (optional)
 */

declare(strict_types=1);
include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function postv(string $k, $d=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function intv(string $k, $d=null){ return isset($_POST[$k]) && $_POST[$k] !== '' ? (int)$_POST[$k] : $d; }

/* -----------------------------------------------------------
 * Load dropdown options
 * ----------------------------------------------------------- */
$programs = [];
$groups   = [];

if ($res = $con->query("SELECT id, name FROM program ORDER BY name")) {
  while ($r = $res->fetch_assoc()) $programs[] = $r;
  $res->free();
}
if ($res = $con->query("SELECT id, name FROM therapy_group ORDER BY name")) {
  while ($r = $res->fetch_assoc()) $groups[] = $r;
  $res->free();
}

/* -----------------------------------------------------------
 * Handle POST
 * ----------------------------------------------------------- */
$flash = '';
$err   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (function_exists('csrf_check')) csrf_check();

  $action = $_POST['action'] ?? '';

  // Delete
  if ($action === 'delete') {
    $id = intv('id', 0);
    if ($id > 0) {
      $st = $con->prepare("DELETE FROM clientportal_group_dates WHERE id=?");
      $st->bind_param('i', $id);
      if ($st->execute()) $flash = 'Entry deleted.';
      else $err = 'Delete failed: '.$st->error;
      $st->close();
    }
  }

  // Create / Update
  if ($action === 'create' || $action === 'update') {
    $id               = intv('id', 0);
    $program_id       = intv('program_id');
    $therapy_group_id = postv('therapy_group_id') === '' ? null : intv('therapy_group_id');
    $starts_at_raw    = postv('starts_at'); // from <input type="datetime-local">
    $note             = postv('note');

    // Validate + normalize datetime
    $missing = [];
    if (!$program_id)           $missing[] = 'Program';
    if ($starts_at_raw === '')  $missing[] = 'Start date/time';

    $starts_at = null;
    if ($starts_at_raw !== '') {
      $ts = strtotime($starts_at_raw);
      if ($ts !== false) $starts_at = date('Y-m-d H:i:s', $ts);
      if (!$starts_at) $missing[] = 'Valid date/time';
    }

    if ($missing) {
      $err = 'Missing: '.implode(', ', $missing);
    } else {
      if ($action === 'create') {
        if ($therapy_group_id !== null) {
          $sql = "INSERT INTO clientportal_group_dates (program_id, therapy_group_id, starts_at, note)
                  VALUES (?, ?, ?, ?)";
          $st  = $con->prepare($sql);
          $st->bind_param('iiss', $program_id, $therapy_group_id, $starts_at, $note);
        } else {
          $sql = "INSERT INTO clientportal_group_dates (program_id, therapy_group_id, starts_at, note)
                  VALUES (?, NULL, ?, ?)";
          $st  = $con->prepare($sql);
          $st->bind_param('iss', $program_id, $starts_at, $note);
        }
        if ($st->execute()) $flash = 'Date added.';
        else $err = 'Insert failed: '.$st->error;
        $st->close();

      } else { // update
        if ($therapy_group_id !== null) {
          $sql = "UPDATE clientportal_group_dates
                     SET program_id=?, therapy_group_id=?, starts_at=?, note=?, updated_at=NOW()
                   WHERE id=?";
          $st  = $con->prepare($sql);
          $st->bind_param('iissi', $program_id, $therapy_group_id, $starts_at, $note, $id);
        } else {
          $sql = "UPDATE clientportal_group_dates
                     SET program_id=?, therapy_group_id=NULL, starts_at=?, note=?, updated_at=NOW()
                   WHERE id=?";
          $st  = $con->prepare($sql);
          $st->bind_param('issi', $program_id, $starts_at, $note, $id);
        }
        if ($st->execute()) $flash = 'Date updated.';
        else $err = 'Update failed: '.$st->error;
        $st->close();
      }
    }
  }
}

/* -----------------------------------------------------------
 * Edit prefill
 * ----------------------------------------------------------- */
$edit = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
  $id = (int)$_GET['edit'];
  $st = $con->prepare("SELECT * FROM clientportal_group_dates WHERE id=?");
  $st->bind_param('i',$id);
  $st->execute();
  $edit = $st->get_result()->fetch_assoc() ?: null;
  $st->close();
}

/* -----------------------------------------------------------
 * Load rows
 * ----------------------------------------------------------- */
$rows_upcoming = [];
$rows_past     = [];

$sqlUp = "SELECT gd.*, p.name AS program_name, tg.name AS group_name
          FROM clientportal_group_dates gd
          LEFT JOIN program p ON p.id = gd.program_id
          LEFT JOIN therapy_group tg ON tg.id = gd.therapy_group_id
          WHERE gd.starts_at >= NOW()
          ORDER BY gd.starts_at ASC";
if ($res = $con->query($sqlUp)) {
  while ($r = $res->fetch_assoc()) $rows_upcoming[] = $r;
  $res->free();
}

$sqlPast = "SELECT gd.*, p.name AS program_name, tg.name AS group_name
            FROM clientportal_group_dates gd
            LEFT JOIN program p ON p.id = gd.program_id
            LEFT JOIN therapy_group tg ON tg.id = gd.therapy_group_id
            WHERE gd.starts_at < NOW()
            ORDER BY gd.starts_at DESC
            LIMIT 50";
if ($res = $con->query($sqlPast)) {
  while ($r = $res->fetch_assoc()) $rows_past[] = $r;
  $res->free();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Client Group Dates — Admin</title>

  <!-- Favicons -->
  <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
  <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">
  <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">
  <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
  <link rel="manifest" href="/favicons/site.webmanifest">

  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

  <style>
    body { background:#e2e6ea; }
    .card { box-shadow:0 2px 5px rgba(0,0,0,.1); }
    .table td, .table th { vertical-align: middle; }
    .nowrap { white-space: nowrap; }
  </style>
</head>
<body>

<?php
  $NAV_ACTIVE = 'client_group_dates';
  require_once 'navbar.php';
?>

<div class="container-fluid py-4">
  <?php if ($flash): ?><div class="alert alert-success"><?= h($flash) ?></div><?php endif; ?>
  <?php if ($err):   ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header">
      <strong><?= $edit ? 'Edit Date' : 'Add Upcoming Date' ?></strong>
    </div>
    <div class="card-body">
      <form method="post">
        <?php if (function_exists('csrf_field')) csrf_field(); ?>
        <input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">

        <div class="form-row">
          <div class="form-group col-md-4">
            <label>Program</label>
            <select name="program_id" class="form-control" required>
              <option value=""></option>
              <?php foreach ($programs as $p): ?>
                <option value="<?= (int)$p['id'] ?>" <?= (isset($edit['program_id']) && (int)$edit['program_id']===(int)$p['id'])?'selected':'' ?>>
                  <?= h($p['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Therapy Group (optional)</label>
            <select name="therapy_group_id" class="form-control">
              <option value="">—</option>
              <?php foreach ($groups as $tg): ?>
                <option value="<?= (int)$tg['id'] ?>" <?= (isset($edit['therapy_group_id']) && (int)$edit['therapy_group_id']===(int)$tg['id'])?'selected':'' ?>>
                  <?= h($tg['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Starts At</label>
            <input type="datetime-local" name="starts_at" class="form-control"
                   value="<?php
                     if (!empty($edit['starts_at'])) {
                       $ts = strtotime($edit['starts_at']);
                       echo h(date('Y-m-d\TH:i', $ts)); // HTML5 datetime-local format
                     }
                   ?>"
                   required>
          </div>
        </div>

        <div class="form-group">
          <label>Note (optional, e.g., "Orientation only", "Weekend cohort")</label>
          <input type="text" name="note" class="form-control" value="<?= h($edit['note'] ?? '') ?>">
        </div>

        <div class="text-right">
          <?php if ($edit): ?>
            <a class="btn btn-secondary mr-2" href="client-group-dates.php">Cancel</a>
          <?php endif; ?>
          <button type="submit" name="action" value="<?= $edit ? 'update' : 'create' ?>" class="btn btn-primary">
            <?= $edit ? 'Update Date' : 'Add Date' ?>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Upcoming -->
  <div class="card mb-4">
    <div class="card-header"><strong>Upcoming Dates</strong></div>
    <div class="card-body table-responsive">
      <table class="table table-striped table-bordered">
        <thead class="thead-light">
          <tr>
            <th>Program</th>
            <th>Therapy Group</th>
            <th class="nowrap">Starts At</th>
            <th>Note</th>
            <th style="width:140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows_upcoming as $r): ?>
          <tr>
            <td><?= h($r['program_name'] ?? $r['program_id']) ?></td>
            <td><?= h($r['group_name'] ?? ($r['therapy_group_id'] ? $r['therapy_group_id'] : '—')) ?></td>
            <td class="nowrap">
              <?php $ts = strtotime($r['starts_at']); echo h(date('l, F j, Y g:i A', $ts)); ?>
            </td>
            <td><?= h($r['note'] ?? '') ?></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-info" href="?edit=<?= (int)$r['id'] ?>"><i class="fas fa-edit"></i></a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this date?');">
                <?php if (function_exists('csrf_field')) csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows_upcoming): ?>
          <tr><td colspan="5" class="text-center text-muted">No upcoming dates posted.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Past -->
  <div class="card">
    <div class="card-header"><strong>Recent Past (last 50)</strong></div>
    <div class="card-body table-responsive">
      <table class="table table-striped table-bordered">
        <thead class="thead-light">
          <tr>
            <th>Program</th>
            <th>Therapy Group</th>
            <th class="nowrap">Started</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows_past as $r): ?>
          <tr>
            <td><?= h($r['program_name'] ?? $r['program_id']) ?></td>
            <td><?= h($r['group_name'] ?? ($r['therapy_group_id'] ? $r['therapy_group_id'] : '—')) ?></td>
            <td class="nowrap">
              <?php $ts = strtotime($r['starts_at']); echo h(date('l, F j, Y g:i A', $ts)); ?>
            </td>
            <td><?= h($r['note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows_past): ?>
          <tr><td colspan="4" class="text-center text-muted">No past items.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
