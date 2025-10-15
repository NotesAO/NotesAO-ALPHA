<?php
/*************************************************************************
 * clinic_review_panel.php – Admin-clinic dashboard panel
 * 2025-07-30  rev-SAFE
 *************************************************************************/
require_once '../config/config.php';
require_once 'sql_functions.php';
require_once 'helpers.php';            // h(), getParam(), etc.

/* ───── helpers ───── */
$yn = fn($v) => $v ? 'Yes' : 'No';
$nw = fn($v) => ($v === null || $v === '') ? '—' : $v;

/* ───── 1. which clinic? ───── */
$cid        = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$clinicName = getParam('clinic');

if (!$cid && $clinicName) {
    $row = run('SELECT id FROM clinic WHERE name = ?', [$clinicName]);
    $cid = $row[0]['id'] ?? 0;
}

$clinic = $cid ? get_clinic_info($cid) : null;
if (!$clinic) { header('location: error.php'); exit; }

/* ───── 2. look-ups ───── */
$pctSales  = sales_pct_complete($cid);
$tasks     = get_onboarding_tasks($cid);
$programs  = get_clinic_programs($cid);
$schedule  = get_clinic_schedule($cid);

$sales     = run('SELECT * FROM clinic_sales_profile   WHERE clinic_id = ?', [$cid])[0] ?? [];
$pay       = run('SELECT * FROM clinic_payment_profile WHERE clinic_id = ?', [$cid])[0] ?? [];

/* buckets for Sales vs Onboarding (weights already stored) */
$bucket = ['Sales'=>['done'=>0,'total'=>0], 'Onboarding'=>['done'=>0,'total'=>0]];
foreach ($tasks as $t) {
    $phase = ($t['phase'] === 'Sales') ? 'Sales' : 'Onboarding';
    $bucket[$phase]['total'] += $t['weight'];
    if ($t['status'] === 'Complete') $bucket[$phase]['done'] += $t['weight'];
}
foreach ($bucket as $k => $v) {
    $bucket[$k]['pct'] = $v['total'] ? round($v['done'] / $v['total'] * 100) : 0;
}
$bucket['Sales']['pct'] = $pctSales;   // override with view-based %

?>
<!-- ========== HEADER ========== -->
<div class="row">
  <div class="col">
    <h2><?= h($clinic['name']) ?></h2>
    <p class="mb-2">
      <strong>Status:</strong> <?= h($clinic['status']) ?> &nbsp;•&nbsp;
      <strong>Created:</strong> <?= h($clinic['created_at']) ?>
      <?php if ($clinic['go_live_date'])
              echo ' • <strong>Go-Live:</strong> '.h($clinic['go_live_date']); ?>
    </p>
    <p>
      <strong>Sub-domain:</strong> <?= h($nw($clinic['subdomain'])) ?> &nbsp;•&nbsp;
      <strong>Code:</strong> <?= h($nw($clinic['code'])) ?>

  </div>
</div>

<div class="row">
  <!-- primary contact -->
  <div class="col-md-4">
    <h5>Primary Contact</h5>
    <p class="mb-0">
      <?= h($nw($clinic['primary_contact_name'])) ?><br>
      <?= h($nw($clinic['primary_contact_email'])) ?><br>
      <?= h($nw($clinic['primary_contact_phone'])) ?>

    </p>
  </div>

  <!-- progress bars -->
  <div class="col-md-4">
    <h5 class="mb-2">Onboarding Progress</h5>

    <small class="text-muted">Sales</small>
    <div class="progress mb-2" style="height:.9rem">
      <div class="progress-bar bg-info" style="width:<?= $bucket['Sales']['pct'] ?>%">
        <?= $bucket['Sales']['pct'] ?>%
      </div>
    </div>

    <small class="text-muted">Onboarding (Client Data + Templates)</small>
    <div class="progress" style="height:.9rem">
      <div class="progress-bar bg-success" style="width:<?= $bucket['Onboarding']['pct'] ?>%">
        <?= $bucket['Onboarding']['pct'] ?>%
      </div>
    </div>
  </div>
</div>

<hr>

<?php /* ───── extra data blocks ───── */ ?>

<div class="row">
  <!-- SALES PROFILE -->
  <div class="col-lg-4 mb-4">
    <div class="card shadow-sm h-100">
      <div class="card-header font-weight-bold">Sales Profile</div>
      <div class="card-body py-2">
        <p class="mb-1"><strong>First meeting:</strong> <?= $nw($sales['first_meeting_date'] ?? null) ?></p>
        <p class="mb-1"><strong>Est. clients:</strong> <?= $nw($sales['estimated_client_count'] ?? null) ?></p>
        <p class="mb-1"><strong>Admin accts:</strong> <?= $nw($sales['admin_account_count'] ?? null) ?></p>
        <p class="mb-1"><strong>Facilitator accts:</strong> <?= $nw($sales['facilitator_account_count'] ?? null) ?></p>
        <p class="mb-1"><strong>Price-point:</strong> <?= $nw($sales['pricepoint'] ?? null) ?></p>
        <p class="mb-3"><strong>Onboarding fee:</strong> <?= $yn($sales['regular_onboarding_fee'] ?? 0) ?></p>

        <h6 class="font-weight-bold mb-1">Sales Contact</h6>
        <p class="mb-0">
          <?= h($nw($sales['contact_name'] ?? null)) ?><br>
          <?= h($nw($sales['contact_email'] ?? null)) ?><br>
          <?= h($nw($sales['contact_phone'] ?? null)) ?>

        </p>
      </div>
    </div>
  </div>

  <!-- PAYMENT PROFILE -->
  <div class="col-lg-4 mb-4">
    <div class="card shadow-sm h-100">
      <div class="card-header font-weight-bold">Payment Profile</div>
      <div class="card-body py-2">
        <?php if (!$pay) { ?>
          <p class="text-muted mb-0">No payment profile on file.</p>
          <a href="clinic-update.php?id=<?= (int)$cid ?>#payment"
            class="btn btn-sm btn-outline-primary mt-2">Add payment profile</a>
        <?php } else { ?>
          <p class="mb-1"><strong>Processor:</strong> <?= h($pay['method']) ?></p>
          <p class="mb-1"><strong>Accepts partial:</strong> <?= $yn($pay['accepts_partial']) ?></p>
          <p class="mb-1"><strong>Used by facilitators:</strong> <?= $yn($pay['used_by_facilitators']) ?></p>
          <p class="mb-1"><strong>NotesAO processor:</strong> <?= $yn($pay['notesao_processor_opt_in']) ?></p>
          <p class="mb-0"><strong>Details:</strong><br><?= nl2br(h($pay['additional_details'])) ?></p>
        <?php } ?>
      </div>

    </div>
  </div>

  <!-- PROGRAMS -->
  <div class="col-lg-4 mb-4">
    <div class="card shadow-sm h-100">
      <div class="card-header font-weight-bold">Programs</div>
      <div class="card-body p-2">
        <?php if ($programs) { ?>
        <table class="table table-sm table-striped mb-0">
          <thead class="thead-light"><tr>
            <th>Name</th><th>Virtual?</th><th>Weekly&nbsp;Times</th>
          </tr></thead><tbody>
          <?php
            foreach ($programs as $p) {
              $times = $p['weekly_times'];
              /* If helper decodes to array, flatten for display */
              if (is_array($times)) $times = implode(', ', $times);
          ?>
            <tr>
              <td><?= h($p['name']) ?></td>
              <td><?= $yn($p['is_virtual']) ?></td>
              <td style="white-space:pre-wrap"><?= $nw($times) ?></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
        <?php } else { echo '<p class="text-muted mb-0">No programs defined.</p>'; } ?>
      </div>
    </div>
  </div>
</div><!-- /row -->

<!-- GROUP SCHEDULE (unchanged) -->
<h5>Group Schedule</h5>
<?php if ($schedule) { ?>
<table class="table table-sm table-bordered">
  <thead class="thead-light"><tr>
    <th>Program</th><th>Day</th><th>Start</th><th>End</th>
    <th>Location</th><th>Link</th>
  </tr></thead><tbody>
  <?php foreach ($schedule as $s) { ?>
    <tr>
      <td><?= h($s['program']) ?></td>
      <td><?= h($s['day_of_week']) ?></td>
      <td><?= $s['start_time'] ?></td>
      <td><?= $s['end_time'] ?></td>
      <td><?= $nw($s['location'] ?? null) ?></td>
      <td><?= $s['perm_link']
             ? '<a href="'.h($s['perm_link']).'" target="_blank">link</a>' : '—' ?></td>
    </tr>
  <?php } ?>
  </tbody>
</table>
<?php } else { echo '<p class="text-muted">No schedule rows.</p>'; } ?>

<!-- ONBOARDING TASK ACCORDION (unchanged) -->
<div class="accordion mt-4" id="taskAcc">
  <div class="card">
    <div class="card-header" id="headingTasks">
      <h2 class="mb-0">
        <button class="btn btn-link p-0" type="button"
                data-toggle="collapse" data-target="#collapseTasks"
                aria-expanded="true" aria-controls="collapseTasks">
          Detailed Onboarding Task List
        </button>
      </h2>
    </div>
    <div id="collapseTasks" class="collapse" aria-labelledby="headingTasks" data-parent="#taskAcc">
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="thead-light"><tr>
            <th>Phase</th><th>Category</th><th>Task</th><th>Status</th><th>Notes</th>
          </tr></thead><tbody>
          <?php foreach ($tasks as $t) { ?>
            <tr>
              <td><?= h($t['phase']) ?></td>
              <td><?= h($t['category']) ?></td>
              <td><?= h($t['task_description']) ?></td>
              <td><?= h($t['status']) ?></td>
              <td><?= h($t['notes'] ?? '') ?></td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
