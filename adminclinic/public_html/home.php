<?php
/* ───────── TEMPORARY DEBUG ────────── */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/home_error.log');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
/* ───────────────────────────────────── */
//  NotesAO • home.php  (Admin Dashboard – overview only)
//  Shows onboarding-progress per clinic + latest error logs
//  Quick-add/quick-actions removed (2025-08-05)
/* ───────────────────────────────────── */

include_once 'auth.php';
check_loggedin($con);
require_once 'sql_functions.php';   // provides db() & get_onboarding_progress()

//──────────── CONFIG ─────────────────────────────────────────
$CLINIC_ROOT   = '/home/notesao';
$LOG_PATTERNS  = ['/public_html/error_log', '/public_html/admin/error_log'];
$LINES_TO_SHOW = 40;
//--------------------------------------------------------------

// 1) Clinic list (name, id) – will also drive progress bars
$clinicRows = [];
$rs = $con->query("SELECT id, name FROM clinic ORDER BY name");
while ($row = $rs->fetch_assoc()) $clinicRows[] = $row;

// 2) Scan for error_log files
function tailFile(string $file, int $lines = 40): string {
    $f = fopen($file, 'rb'); if (!$f) return '';
    $out = ''; $pos = -1; $cnt = 0;
    while ($cnt < $lines && fseek($f, $pos, SEEK_END) === 0) {
        $c = fgetc($f); $out = $c.$out;
        if ($c === "\n" && ++$cnt >= $lines) break;
        $pos--;
    }
    fclose($f);
    return trim($out);
}
$errorLogs = [];
foreach (glob($CLINIC_ROOT.'/*', GLOB_ONLYDIR) as $dir) {
    $clinic = basename($dir);
    foreach ($LOG_PATTERNS as $rel) {
        $log = $dir.$rel;
        if (is_file($log)) {
            $errorLogs[$clinic] = [
                'sizeKB'  => round(filesize($log)/1024, 1),
                'modified'=> date('Y-m-d H:i', filemtime($log)),
                'tail'    => tailFile($log, $LINES_TO_SHOW)
            ];
            break;
        }
    }
}
ksort($errorLogs, SORT_NATURAL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NotesAO Admin Dashboard</title>
<link rel="icon" href="/favicons/favicon.ico">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
body{background:#e2e6ea;padding-top:56px;}
.card-title{font-size:1.1rem;}
.scrollbox{max-height:260px;overflow-y:auto;font-family:monospace;font-size:.75rem;white-space:pre;}
.progress-bar{min-width:2rem;}
.progress-card .progress{height:.8rem;}
</style>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</head>
<?php require 'navbar.php'; ?>
<body>
<div class="container-fluid px-lg-5">
  <div class="jumbotron bg-white text-center shadow-sm py-4 mb-4">
    <h1 class="display-5">NotesAO Admin Dashboard</h1>
    <p class="lead mb-0">High-level clinic overview</p>
  </div>

  <!-- ───────────── ROW 1 : Onboarding Progress ───────────── -->
  <div class="row">
    <div class="col-lg-6 mb-4">
      <div class="card shadow-sm progress-card h-100">
        <div class="card-header"><i class="fas fa-chart-line mr-1"></i>Onboarding Progress</div>
        <div class="card-body p-2">
        <?php if(!$clinicRows): ?>
          <small class="text-muted">No clinics found.</small>
        <?php else:
          foreach ($clinicRows as $row):
              $p = get_onboarding_progress($row['id']); ?>
            <div class="mb-2">
              <div class="d-flex justify-content-between small">
                <strong><?= htmlspecialchars($row['name']) ?></strong>
                <span><?= $p['done'] ?> / <?= $p['total'] ?></span>
              </div>
              <div class="progress">
                <div class="progress-bar bg-info" style="width:<?= $p['percent'] ?>%;">
                  <?= $p['percent'] ?>%
                </div>
              </div>
            </div>
        <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- ROW 1 right half intentionally left blank for future widgets -->
    <div class="col-lg-6 mb-4"></div>
  </div>

  <!-- ───────────── ROW 2 : Error Logs grid ───────────── -->
  <h5 class="mt-4 mb-2"><i class="fas fa-exclamation-triangle mr-1"></i>Latest Clinic Error Logs</h5>
  <div class="row">
  <?php foreach($errorLogs as $clinic=>$info): ?>
    <div class="col-lg-4 mb-4">
      <div class="card shadow-sm">
        <div class="card-header py-2">
          <strong><?= htmlspecialchars($clinic) ?></strong>
          <span class="badge badge-light float-right"><?= $info['sizeKB'] ?> KB</span><br>
          <small class="text-muted">Updated <?= htmlspecialchars($info['modified']) ?></small>
        </div>
        <div class="card-body p-2">
          <div class="scrollbox"><?= htmlspecialchars($info['tail']) ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  </div><!-- /error log grid -->
</div><!-- /container -->
</body>
</html>
