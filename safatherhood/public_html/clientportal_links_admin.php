<?php
// clientportal_links_admin.php — Simplified admin for Client Portal links
include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
function postv($k,$d=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function intv($k,$d=null){ return isset($_POST[$k]) && $_POST[$k] !== '' ? (int)$_POST[$k] : $d; }

$flash = '';
$err   = '';

// ---- Load dropdown options ----
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

// ---- Create / Update / Delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (function_exists('csrf_check')) csrf_check();

  // Delete
  if (($_POST['action'] ?? '') === 'delete') {
    $id = intv('id', 0);
    if ($id > 0) {
      $st = $con->prepare("DELETE FROM clientportal_links WHERE id=?");
      $st->bind_param('i', $id);
      if ($st->execute()) $flash = 'Link deleted.';
      else $err = 'Delete failed: '.$st->error;
      $st->close();
    }
  }

  // Create / Update
  if (in_array(($_POST['action'] ?? ''), ['create','update'], true)) {
    $id               = intv('id', 0);
    $program_id       = intv('program_id');
    $therapy_group_id = intv('therapy_group_id', 0);
    $label            = postv('label');      // optional
    $day_time         = postv('day_time');   // short label shown to clients
    $join_url         = postv('join_url');

    // basic validation
    $missing = [];
    foreach ([
      'Program'       => $program_id,
      'Therapy group' => $therapy_group_id,
      'Day/Time'      => $day_time,
      'Meeting link'  => $join_url,
    ] as $k=>$v) { if ($v === '' || $v === null) $missing[] = $k; }

    if ($missing) {
      $err = 'Missing: '.implode(', ', $missing);
    } else {
      if (($_POST['action'] ?? '') === 'create') {
        // Set now-irrelevant columns to NULL
        $sql = "INSERT INTO clientportal_links
                  (program_id, referral_type_id, gender_id, required_sessions, fee,
                   therapy_group_id, label, day_time, join_url)
                VALUES (?, NULL, NULL, NULL, NULL, ?, ?, ?, ?)";
        $st  = $con->prepare($sql);
        $st->bind_param('iisss', $program_id, $therapy_group_id, $label, $day_time, $join_url);
        if ($st->execute()) $flash = 'Link added.';
        else $err = 'Insert failed: '.$st->error;
        $st->close();
      } else {
        $sql = "UPDATE clientportal_links
                  SET program_id=?, therapy_group_id=?, label=?, day_time=?, join_url=?, updated_at=NOW(),
                      referral_type_id=NULL, gender_id=NULL, required_sessions=NULL, fee=NULL
                WHERE id=?";
        $st  = $con->prepare($sql);
        $st->bind_param('iisssi', $program_id, $therapy_group_id, $label, $day_time, $join_url, $id);
        if ($st->execute()) $flash = 'Link updated.';
        else $err = 'Update failed: '.$st->error;
        $st->close();
      }
    }
  }
}

// ---- Editing state (prefill if ?edit=ID) ----
$edit = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
  $id = (int)$_GET['edit'];
  $st = $con->prepare("SELECT * FROM clientportal_links WHERE id=?");
  $st->bind_param('i',$id);
  $st->execute();
  $edit = $st->get_result()->fetch_assoc() ?: null;
  $st->close();
}

// ---- Load all rows to list ----
$rows = [];
$sqlList = "SELECT cpl.*,
                   p.name  AS program_name,
                   tg.name AS group_name
            FROM clientportal_links cpl
            LEFT JOIN program p       ON p.id  = cpl.program_id
            LEFT JOIN therapy_group tg ON tg.id = cpl.therapy_group_id
            ORDER BY p.name, tg.name, cpl.day_time";
if ($res = $con->query($sqlList)) {
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Client Portal Links — Admin</title>

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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

  <style>
    body { background:#e2e6ea; }
    .card { box-shadow:0 2px 5px rgba(0,0,0,.1); }
    .table td, .table th { vertical-align: middle; }
  </style>
</head>
<body>

<?php
  $NAV_ACTIVE = 'clientportal_links';
  require_once 'navbar.php';
?>

<div class="container-fluid py-4">

  <?php if ($flash): ?><div class="alert alert-success"><?= h($flash) ?></div><?php endif; ?>
  <?php if ($err):   ?><div class="alert alert-danger"><?= h($err) ?></div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header">
      <strong><i class="fas fa-plus-circle"></i> <?= $edit ? 'Edit Link' : 'Add New Link' ?></strong>
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
            <label>Therapy Group</label>
            <select name="therapy_group_id" class="form-control" required>
              <option value=""></option>
              <?php foreach ($groups as $tg): ?>
                <option value="<?= (int)$tg['id'] ?>" <?= (isset($edit['therapy_group_id']) && (int)$edit['therapy_group_id']===(int)$tg['id'])?'selected':'' ?>>
                  <?= h($tg['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-4">
            <label>Day/Time (shown to clients)</label>
            <input type="text" name="day_time" class="form-control"
                   value="<?= h($edit['day_time'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>Label (optional, longer)</label>
            <input type="text" name="label" class="form-control"
                   value="<?= h($edit['label'] ?? '') ?>">
          </div>
          <div class="form-group col-md-6">
            <label>Meeting Link (URL)</label>
            <input type="url" name="join_url" class="form-control"
                   value="<?= h($edit['join_url'] ?? '') ?>" required>
          </div>
        </div>

        <div class="text-right">
          <?php if ($edit): ?>
            <a class="btn btn-secondary mr-2" href="clientportal_links_admin.php">
              <i class="fas fa-times"></i> Cancel
            </a>
          <?php endif; ?>
          <button type="submit" name="action" value="<?= $edit ? 'update':'create' ?>" class="btn btn-primary">
            <i class="fas fa-save"></i> <?= $edit ? 'Update':'Add Link' ?>
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <strong><i class="fas fa-list"></i> Existing Links</strong>
    </div>
    <div class="card-body table-responsive">
      <table class="table table-striped table-bordered">
        <thead class="thead-light">
          <tr>
            <th>Program</th>
            <th>Therapy Group</th>
            <th>Day/Time</th>
            <th>Label</th>
            <th>Link</th>
            <th style="width:120px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h($r['program_name'] ?? $r['program_id']) ?></td>
              <td><?= h($r['group_name'] ?? $r['therapy_group_id']) ?></td>
              <td><?= h($r['day_time']) ?></td>
              <td><?= h($r['label']) ?></td>
              <td style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                <a target="_blank" href="<?= h($r['join_url']) ?>">open</a>
              </td>
              <td class="text-nowrap">
                <a class="btn btn-sm btn-info" href="?edit=<?= (int)$r['id'] ?>">
                  <i class="fas fa-edit"></i>
                </a>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this link?');">
                  <?php if (function_exists('csrf_field')) csrf_field(); ?>
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="6" class="text-center text-muted">No links yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
