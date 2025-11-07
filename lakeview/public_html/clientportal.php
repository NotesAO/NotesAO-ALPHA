<?php
declare(strict_types=1);
ob_start();
session_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* -----------------------------------------------------------
 * DB CONFIG  (client-facing portal uses direct credentials)
 * ----------------------------------------------------------- */
define('db_host', '50.28.37.79');
define('db_name', 'clinicnotepro_lakeview');
define('db_user', 'clinicnotepro_lakeview_app');
define('db_pass', 'PF-m[T-+pF%g');

/* -----------------------------------------------------------
 * DB CONNECT
 * ----------------------------------------------------------- */
$con = @new mysqli(db_host, db_user, db_pass, db_name);
if ($con->connect_errno) {
    http_response_code(500);
    exit('Database connection error.');
}
$con->set_charset('utf8mb4');

/* -----------------------------------------------------------
 * LIB (includes SQL helpers + group/billing/date utilities)
 * ----------------------------------------------------------- */
require_once __DIR__ . '/clientportal_lib.php';

/* -----------------------------------------------------------
 * Small helpers specific to this page
 * ----------------------------------------------------------- */
function program_name(mysqli $con, int $id): string {
    $row = sql_select_one($con, "SELECT name FROM program WHERE id = ? LIMIT 1", [(string)$id]);
    return $row['name'] ?? 'Program';
}
function referral_name(mysqli $con, int $id): string {
    $row = sql_select_one($con, "SELECT referral_type FROM referral_type WHERE id = ? LIMIT 1", [(string)$id]);
    return $row['referral_type'] ?? 'Referral';
}

/**
 * Load primary therapy group meta safely (checks optional columns).
 * Returns:
 *  [
 *    'id'=>int, 'name'=>string, 'address'=>string|null,
 *    'day_label'=>string|null, 'time_label'=>string|null,
 *    'is_virtual'=>bool, 'join_link'=>string
 *  ] or null
 */
function load_primary_group_meta(mysqli $con, int $therapy_group_id, int $program_id): ?array {
    if ($therapy_group_id <= 0) return null;

    $cols = ['id'];
    foreach (['name','address','day_label','time_label'] as $c) {
        if (column_exists($con, 'therapy_group', $c)) $cols[] = $c;
    }
    $sql = "SELECT ".implode(',', $cols)." FROM therapy_group WHERE id = ? LIMIT 1";
    $tg  = sql_select_one($con, $sql, [(string)$therapy_group_id]);
    if (!$tg) return null;

    $name      = (string)($tg['name'] ?? '');
    $address   = isset($tg['address']) ? (string)$tg['address'] : '';
    $dayLabel  = isset($tg['day_label']) ? (string)$tg['day_label'] : '';
    $timeLabel = isset($tg['time_label']) ? (string)$tg['time_label'] : '';

    // All programs are virtual; prefer the program-level link so every slot uses the same URL.
    $isVirtual = true;
    $joinLink  = resolve_join_link_for(0, $program_id) ?: resolve_join_link_for($therapy_group_id, $program_id);

    return [
        'id'         => (int)$tg['id'],
        'name'       => $name,
        'address'    => $address,
        'day_label'  => $dayLabel,
        'time_label' => $timeLabel,
        'is_virtual' => $isVirtual,
        'join_link'  => $joinLink,
    ];
}


/**
 * Load “other weekly” groups for the same program (excluding primary).
 * If therapy_group.program_id exists, query it; otherwise fall back to
 * the static mapping from the lib (get_weekly_slots_for_program()).
 *
 * Returns array of rows each with keys similar to load_primary_group_meta, but some
 * may be partial if using the static fallbacks.
 */
function load_other_weekly_groups(mysqli $con, int $program_id, ?int $exclude_tg_id = null): array {
    $rows = [];

    $hasProgramId = column_exists($con, 'therapy_group', 'program_id');
    $selCols = ['id'];
    foreach (['name','address','day_label','time_label','program_id'] as $c) {
        if (column_exists($con, 'therapy_group', $c)) $selCols[] = $c;
    }

    if ($hasProgramId) {
        $sql = "SELECT ".implode(',', $selCols)." FROM therapy_group WHERE program_id = ? ORDER BY name";
        $rowsDb = sql_select_all($con, $sql, [(string)$program_id]);

        // Count non-default choices (excluding the current primary if provided)
        $nonDefault = array_values(array_filter($rowsDb, function($r) use ($exclude_tg_id) {
            $isDefault  = isset($r['name']) && preg_match('/default/i', (string)$r['name']);
            $isExcluded = $exclude_tg_id && isset($r['id']) && (int)$r['id'] === (int)$exclude_tg_id;
            return !$isDefault && !$isExcluded;
        }));

        foreach ($rowsDb as $r) {
            $id = (int)$r['id'];
            if ($exclude_tg_id && $id === (int)$exclude_tg_id) continue;

            $name = (string)($r['name'] ?? '');
            $isDefault = preg_match('/default/i', $name) === 1;

            // Hide “Default Group” when there is at least one other option
            if ($isDefault && count($nonDefault) > 0) continue;

            $rows[] = [
                'id'         => $id,
                'name'       => $name,
                'address'    => (string)($r['address'] ?? ''),
                'day_label'  => (string)($r['day_label'] ?? ''),
                'time_label' => (string)($r['time_label'] ?? ''),
                // All virtual. Use the program-level link for consistency.
                'is_virtual' => true,
                'join_link'  => resolve_join_link_for(0, $program_id),
            ];
        }
    } else {
        // Static fallback
        $slots = get_weekly_slots_for_program($program_id);
        foreach ($slots as $label) {
            $rows[] = [
                'id'         => 0,
                'name'       => $label,
                'address'    => '',
                'day_label'  => '',
                'time_label' => '',
                'is_virtual' => true,
                'join_link'  => resolve_join_link_for(0, $program_id),
            ];
        }
    }

    return $rows;
}


/**
 * Build billing view model.
 * Status possibilities:
 *  - 'no_charge' (CPS)
 *  - 'paid'      (Parenting fee met or other heuristic)
 *  - 'due'       (needs payment; link provided)
 *  - 'unknown'   (fallback text)
 */
function build_billing_view_model(mysqli $con, array $client): array {
    $refId = (int)($client['referral_type_id'] ?? 0);
    $pid   = (int)($client['program_id'] ?? 0);

    // CPS
    if ($refId === 4) {
        return [
            'status'   => 'no_charge',
            'headline' => 'CPS — No Charge',
            'subtext'  => 'Your case is listed as CPS. Payment is not required.',
            'pay_link' => '',
        ];
    }

    // Use optional payment status helper if columns exist
    $pstat = client_payment_status_for_portal($con, $client); // checks for paid_amount/paid_source safely
    if (($pstat['known'] ?? false) && ($pstat['status'] ?? '') === 'paid') {
        return [
            'status'   => 'paid',
            'headline' => 'Paid',
            'subtext'  => $pstat['detail'] ?? '',
            'pay_link' => '',
        ];
    }

    // Otherwise, show link
    $link = portal_payment_link_for_client($client);
    return [
        'status'   => $link ? 'due' : 'unknown',
        'headline' => $link ? 'Payment Due' : 'Billing Information',
        'subtext'  => $link ? 'Please complete your payment using the link below.' : 'Please contact the office for billing assistance.',
        'pay_link' => $link,
    ];
}

/* -----------------------------------------------------------
 * Sign out
 * ----------------------------------------------------------- */
if (isset($_GET['signout'])) {
    unset($_SESSION['client_verified'], $_SESSION['client_id']);
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

/* -----------------------------------------------------------
 * POST: identity check (First, Last, DOB, optional Birth Place)
 * ----------------------------------------------------------- */
$error_message = '';
$client = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name  = trim((string)($_POST['first_name'] ?? ''));
    $last_name   = trim((string)($_POST['last_name'] ?? ''));
    $dob_year    = trim((string)($_POST['dob_year'] ?? ''));
    $dob_month   = trim((string)($_POST['dob_month'] ?? ''));
    $dob_day     = trim((string)($_POST['dob_day'] ?? ''));
    $birth_place = trim((string)($_POST['birth_place'] ?? ''));

    if ($first_name !== '' && $last_name !== '' && $dob_year !== '' && $dob_month !== '' && $dob_day !== '') {
        $dob = sprintf('%04d-%02d-%02d', (int)$dob_year, (int)$dob_month, (int)$dob_day);

        // Build SQL dynamically so birth_place is only enforced when provided
        $sql = "SELECT id, first_name, last_name, date_of_birth, birth_place,
                       program_id, therapy_group_id, referral_type_id,
                       required_sessions, fee, gender_id
                FROM client
                WHERE LOWER(first_name)=LOWER(?) AND LOWER(last_name)=LOWER(?)
                  AND date_of_birth = ?";
        $params = [$first_name, $last_name, $dob];

        if ($birth_place !== '') {
            $sql .= " AND LOWER(birth_place) = LOWER(?)";
            $params[] = $birth_place;
        }

        $sql .= " LIMIT 1";
        $client = sql_select_one($con, $sql, $params);

        if ($client) {
            $_SESSION['client_verified'] = true;
            $_SESSION['client_id'] = (int)$client['id'];
            header('Location: '.$_SERVER['PHP_SELF']);
            exit;
        } else {
            $error_message = 'No matching client found.';
        }
    } else {
        $error_message = 'All fields are required.';
    }
}

/* -----------------------------------------------------------
 * If session is verified, pull client again by id
 * ----------------------------------------------------------- */
if (empty($client) && !empty($_SESSION['client_verified']) && !empty($_SESSION['client_id'])) {
    $client = sql_select_one(
        $con,
        "SELECT id, first_name, last_name, date_of_birth, birth_place,
                program_id, therapy_group_id, referral_type_id,
                required_sessions, fee, gender_id
         FROM client
         WHERE id = ? LIMIT 1",
        [(string)$_SESSION['client_id']]
    );
}

/* -----------------------------------------------------------
 * Render
 * ----------------------------------------------------------- */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Client Portal - NotesAO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicons (optional) -->
    <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
    <link rel="manifest" href="/favicons/site.webmanifest">

    <!-- Bootstrap 4.5 + deps -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

    <style>
        body {
            background: #eef2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            max-width: 860px;
            width: 100%;
        }
        .error { background:#d9534f; color:#fff; padding:10px; border-radius:8px; margin-top:10px; }
        label { margin-top: .75rem; font-weight: 500; }
        .small-muted { color:#6c757d; font-size:.875rem; }
        .code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
        .list-card .row + .row { border-top: 1px solid #eee; margin-top: .75rem; padding-top: .75rem; }
        .badge-soft { background:#f1f3f5; color:#555; font-weight:600; }
    </style>
</head>
<body>
<div class="container">
  <div class="text-center">
    <a href="https://lakevieweducation.com/">
        <img src="lakeviewlogo.png" alt="Lakeview Education"
             class="img-fluid mb-3" style="max-height:150px;">
    </a>
  </div>

  <h2 class="text-center">Client Portal</h2>

  <?php if (!empty($error_message)): ?>
    <div class="error"><?= h($error_message) ?></div>
  <?php endif; ?>

  <?php if (empty($client)): ?>
    <!-- Sign-in -->
    <form method="post">
        <label>First Name:</label>
        <input type="text" name="first_name" class="form-control" required>

        <label>Last Name:</label>
        <input type="text" name="last_name" class="form-control" required>

        <label>Date of Birth:</label>
        <div class="form-row">
            <div class="col">
                <select name="dob_month" class="form-control" required>
                    <option value="">Month</option>
                    <?php for ($m=1; $m<=12; $m++):
                        $monthName = date("F", mktime(0,0,0,$m,1)); ?>
                        <option value="<?= $m ?>"><?= $monthName ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col">
                <select name="dob_day" class="form-control" required>
                    <option value="">Day</option>
                    <?php for ($d=1; $d<=31; $d++): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col">
                <select name="dob_year" class="form-control" required>
                    <option value="">Year</option>
                    <?php for ($y=1930; $y<= (int)date('Y'); $y++): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <label>Birth Place (optional):</label>
        <input type="text" name="birth_place" class="form-control" placeholder="City or City, State">

        <button type="submit" class="btn btn-primary btn-block mt-3">Submit</button>
    </form>

  <?php else: ?>
    <?php
      $pid     = (int)($client['program_id'] ?? 0);
      $tgid    = (int)($client['therapy_group_id'] ?? 0);
      $refId   = (int)($client['referral_type_id'] ?? 0);
      $fee     = isset($client['fee']) && $client['fee'] !== '' ? (float)$client['fee'] : null;
      $reqSess = (int)($client['required_sessions'] ?? 0);

      $pName   = program_name($con, $pid);
      $rName   = referral_name($con, $refId);


      // Primary group meta + Other weekly sessions
      $primary = $tgid > 0 ? load_primary_group_meta($con, $tgid, $pid) : null;
      $others  = load_other_weekly_groups($con, $pid, $tgid ?: null);

      // Upcoming variable dates (program and TG-scoped)
      $upcomingDates = get_upcoming_group_dates($con, $pid, $tgid ?: null, 5);

      // Billing view model
      $billing = build_billing_view_model($con, $client);
    ?>

    <!-- Header card -->
    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title mb-3"><?= h($client['first_name'].' '.$client['last_name']) ?></h5>
        <div class="row">
          <div class="col-md-6 col-6">
            <div class="small-muted">Program</div>
            <div><strong><?= h($pName) ?></strong></div>
          </div>
          <div class="col-md-6 col-6">
            <div class="small-muted">Referral</div>
            <div><strong><?= h($rName) ?></strong></div>
          </div>
        </div>

        <div class="row mt-3">
          <div class="col-md-6 col-6">
            <div class="small-muted">Required Sessions</div>
            <div><strong><?= $reqSess > 0 ? h((string)$reqSess) : '—' ?></strong></div>
          </div>
          <div class="col-md-6 col-6">
            <div class="small-muted">Your Fee</div>
            <div><strong>
              <?php
                if ($billing['status'] === 'no_charge') {
                    echo 'CPS — No Charge';
                } elseif ($billing['status'] === 'paid') {
                    echo 'Paid';
                } else {
                    echo $fee !== null ? ('$'.number_format($fee, 2). ' per session') : 'See billing section';
                }
              ?>
            </strong></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Primary Group -->
    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title">Your Primary Group</h5>
        <?php if ($primary): ?>
          <div class="list-card">
            <div class="row">
              <div class="col-md-7">
                <div><strong><?= h($primary['name'] ?: 'Group #'.$primary['id']) ?></strong></div>
                <?php if ($primary['day_label'] || $primary['time_label']): ?>
                  <div class="small-muted">
                    <?= h(trim(($primary['day_label'] ?? '').' '.($primary['time_label'] ?? ''))) ?>
                  </div>
                <?php endif; ?>
                <?php if (!$primary['is_virtual'] && $primary['address']): ?>
                  <div class="small-muted">Location: <?= h($primary['address']) ?></div>
                <?php elseif ($primary['is_virtual']): ?>
                  <span class="badge badge-soft">Virtual</span>
                <?php endif; ?>
              </div>
              <div class="col-md-5 text-md-right mt-2 mt-md-0">
                <?php
                    // Always use the program-level Zoom for Lakeview (all virtual).
                    $plink = trim((string)($primary['join_link'] ?? ''));
                    if ($plink === '') { $plink = resolve_join_link_for($tgid, $pid); }
                ?>
                <?php if ($plink !== ''): ?>
                    <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
                    href="<?= h($plink) ?>">Open Class Link</a>
                    <div class="small-muted mt-2">
                    Zoom: <span class="code"><?= h($plink) ?></span>
                    </div>
                <?php else: ?>
                    <div class="small-muted">Your facilitator will provide the access link if needed.</div>
                <?php endif; ?>
              </div>


            </div>
          </div>
        <?php else: ?>
          <p class="mb-0">No primary group is assigned to your account yet. Please contact the office.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Upcoming Dates (for programs where dates vary) -->
    <?php if (!empty($upcomingDates)): ?>
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Upcoming Dates</h5>
          <?php foreach ($upcomingDates as $d): ?>
            <div class="row">
              <div class="col-md-8">
                <div><strong><?= h(format_portal_datetime($d['starts_at'])) ?></strong></div>
                <?php if (!empty($d['note'])): ?>
                  <div class="small-muted"><?= h($d['note']) ?></div>
                <?php endif; ?>
              </div>
              <div class="col-md-4 text-md-right mt-2 mt-md-0">
                <?php
                  // If primary is virtual and has a link, offer it; else fall back to program default link if any
                  $join = resolve_join_link_for($tgid, $pid);

                ?>
                <?php if ($join): ?>
                  <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="<?= h($join) ?>">Open Class Link</a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="small-muted mt-2">If you do not see a date that works, please contact the office.</div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Other Weekly Sessions -->
    <?php if (!empty($others)): ?>
        <div class="card mb-3">
            <div class="card-body">
            <h5 class="card-title">Other Weekly Sessions You Can Attend</h5>
            <div class="list-card">
                <?php foreach ($others as $row): ?>
                <div class="row">
                    <div class="col-md-7">
                    <div><strong><?= h($row['name'] ?: 'Group #'.$row['id']) ?></strong></div>
                    <?php if (!empty($row['day_label']) || !empty($row['time_label'])): ?>
                        <div class="small-muted"><?= h(trim(($row['day_label'] ?? '').' '.($row['time_label'] ?? ''))) ?></div>
                    <?php endif; ?>
                    <?php if (empty($row['is_virtual']) && !empty($row['address'])): ?>
                        <div class="small-muted">Location: <?= h($row['address']) ?></div>
                    <?php elseif (!empty($row['is_virtual'])): ?>
                        <span class="badge badge-soft">Virtual</span>
                    <?php endif; ?>
                    </div>
                    <div class="col-md-5 text-md-right mt-2 mt-md-0">
                    <?php $olink = trim((string)($row['join_link'] ?? '')); ?>
                    <?php if ($olink !== ''): ?>
                        <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
                        href="<?= h($olink) ?>">Open Class Link</a>
                    <?php else: ?>
                        <div class="small-muted">Your facilitator will provide the access link if needed.</div>
                    <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="small-muted mt-2">Note: Make-up attendance policies may apply. Contact your facilitator for guidance.</div>
            </div>
        </div>
    <?php endif; ?>


    <!-- Billing -->
    <div class="card mb-3">
      <div class="card-body">
        <h5 class="card-title">Billing</h5>
        <p class="mb-2"><strong><?= h($billing['headline'] ?? '') ?></strong></p>
        <?php if (!empty($billing['subtext'])): ?>
          <p class="small-muted"><?= h($billing['subtext']) ?></p>
        <?php endif; ?>

        <?php if (($billing['status'] ?? '') === 'due' && !empty($billing['pay_link'])): ?>
          <a class="btn btn-primary" target="_blank" rel="noopener" href="<?= h($billing['pay_link']) ?>">Pay Now</a>
          <div class="small-muted mt-2"><?= h($billing['pay_link']) ?></div>
        <?php elseif (($billing['status'] ?? '') === 'no_charge'): ?>
          <div class="text-success">No payment is required for your case.</div>
        <?php elseif (($billing['status'] ?? '') === 'paid'): ?>
          <div class="text-success">Thank you! Your payment has been recorded.</div>
        <?php else: ?>
          <div class="small-muted">For assistance with payments, please contact the office.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="text-center">
      <a class="btn btn-link" href="<?= h($_SERVER['PHP_SELF']) ?>?signout=1">Close</a>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
