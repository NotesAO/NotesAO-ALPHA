<?php
/*****************************************************************************
 * NotesAO • clinic-index.php      (Admin view with dual progress bars)
 * --------------------------------------------------------------------------
 * 2025-07-30  rev-C2
 *****************************************************************************/

declare(strict_types=1);

include_once 'auth.php';
check_loggedin($con);                        // $con = mysqli connection

require_once 'sql_functions.php';           // run() & sales_pct_complete()
/* ───── CONFIG ─────────────────────────────────────────────────────────── */
$SALES_CAT    = 'Sales';                     // 1st bar
$ONBOARD_CATS = ['Client Data','Templates']; // 2nd bar combines these
/* ----------------------------------------------------------------------- */

/* 1. Totals per bucket (active tasks) ----------------------------------- */
$taskTotals = [$SALES_CAT => 0, 'onboard' => 0];
$qTot = $con->query(
    "SELECT category AS task_category
       FROM onboarding_task
      WHERE is_active = 1");

while ($r = $qTot->fetch_assoc()) {
    if ($r['task_category'] === $SALES_CAT) {
        $taskTotals[$SALES_CAT]++;
    } elseif (in_array($r['task_category'], $ONBOARD_CATS, true)) {
        $taskTotals['onboard']++;
    }
}

/* 2. Clinic list -------------------------------------------------------- */
$clinics = [];                    // [id=>name] for fast lookup
$res = $con->query("SELECT id, name FROM clinic ORDER BY name");
while ($row = $res->fetch_assoc()) {
    $clinics[(int)$row['id']] = $row['name'];
}

/* 3. Done counts per clinic & category (status = 'Complete') ------------ */
$done = [];                       // [clinic_id][task_category] = n
if ($clinics) {
    $sql = "
        SELECT cot.clinic_id,
              ot.category,
              COUNT(*) AS n
          FROM clinic_onboarding_task cot
          JOIN onboarding_task        ot ON ot.id = cot.task_id
        WHERE cot.status = 'Complete'
          AND ot.is_active = 1
      GROUP BY cot.clinic_id, ot.category";

    $dRS = $con->query($sql);
    while ($r = $dRS->fetch_assoc()) {
        $cid = (int)$r['clinic_id'];
        $cat = $r['category'];   // not task_category

        $done[$cid][$cat] = (int)$r['n'];
    }
}

/* helper: percent ------------------------------------------------------- */
function pct(int $done, int $total): int
{
    return $total ? (int)round($done * 100 / $total) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NotesAO – Clinic Index</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>.progress{height:.85rem}</style>
</head>
<?php require 'navbar.php'; ?>
<body>
<section class="pt-5">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <div class="page-header clearfix mb-3">
          <h2 class="float-left">Clinic Onboarding Status</h2>
          <a href="clinic-create.php" class="btn btn-success float-right">
            <i class="fas fa-plus"></i> Create New Clinic
          </a>
          <a href="home.php" class="btn btn-secondary float-right mr-2">Admin Home</a>
        </div>

        <table class="table table-bordered table-striped">
          <thead class="thead-light">
            <tr>
              <th>Clinic</th>
              <th style="width:32%">Sales&nbsp;Progress</th>
              <th style="width:32%">Onboarding&nbsp;Progress</th>
              <th style="width:12%">Actions</th>
            </tr>
          </thead>
          <tbody>
<?php foreach ($clinics as $cid => $name):

        $doneSales = $done[$cid][$SALES_CAT] ?? 0;

        $doneOn = 0;
        foreach ($ONBOARD_CATS as $cat) {
            $doneOn += $done[$cid][$cat] ?? 0;
        }

        $pctSales = sales_pct_complete($cid);

        $pctOn    = pct($doneOn, $taskTotals['onboard']); ?>
            <tr>
              <td><strong><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong></td>

              <td>
                <div class="progress">
                  <div class="progress-bar bg-info"
                       role="progressbar"
                       style="width: <?= $pctSales ?>%;"
                       aria-valuenow="<?= $pctSales ?>" aria-valuemin="0" aria-valuemax="100">
                       <?= $pctSales ?>%
                  </div>
                </div>
              </td>

              <td>
                <div class="progress">
                  <div class="progress-bar bg-success"
                       role="progressbar"
                       style="width: <?= $pctOn ?>%;"
                       aria-valuenow="<?= $pctOn ?>" aria-valuemin="0" aria-valuemax="100">
                       <?= $pctOn ?>%
                  </div>
                </div>
              </td>

              <td class="text-center">
                <a href="clinic-review.php?id=<?= $cid ?>" class="mr-2" title="View">
                  <i class="far fa-eye"></i>
                </a>
                <a href="clinic-update.php?id=<?= $cid ?>" title="Update">
                  <i class="far fa-edit"></i>
                </a>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>
</html>

<?php
/* ── quick sanity check ────────────────────────────────────────────────
   Un-comment the block below to print what the DB is returning.

echo "<pre>";
var_dump($taskTotals);
var_dump($done);
echo "</pre>";
exit;
*/
?>
