<?php
    require_once "../config/config.php";
    require_once "sql_functions.php";
    include "helpers.php";

    /* ---- add helpers here ---- */
    if (!function_exists('sql_select_one')) {
        function sql_select_one($con, $sql, $params=[]) {
            $stmt = $con->prepare($sql);
            if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            $stmt->execute(); $res = $stmt->get_result(); return $res? $res->fetch_assoc(): null;
        }
        function sql_select_all($con, $sql, $params=[]) {
            $stmt = $con->prepare($sql);
            if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
            $stmt->execute(); $res = $stmt->get_result(); return $res? $res->fetch_all(MYSQLI_ASSOC): [];
        }
    }

    function column_exists(mysqli $con, string $table, string $col): bool {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) return false;
        $col_esc = $con->real_escape_string($col);
        $sql = "SHOW COLUMNS FROM `$table` LIKE '$col_esc'";
        $res = $con->query($sql);
        return ($res && $res->num_rows > 0);
    }

    function pick_label_column(mysqli $con, string $table, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (column_exists($con, $table, $c)) return $c;
        }
        return null;
    }

    function fetch_label_by_id(mysqli $con, string $table, string $id_col, $id_val, array $candidates): string {
        foreach ([$table,$id_col] as $id) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $id)) return (string)$id_val;
        }
        $label_col = pick_label_column($con, $table, $candidates);
        if (!$label_col) return (string)$id_val;
        $sql = "SELECT `$label_col` AS label FROM `$table` WHERE `$id_col`=?";
        $row = sql_select_one($con, $sql, [$id_val]);
        return $row['label'] ?? (string)$id_val;
    }
    /* ---- helpers end ---- */

    $client_id = "";
    if (isset($_GET['client_id'])) $client_id = $_GET['client_id'];
    if (isset($_POST['client_id'])) $client_id = $_POST['client_id'];
    if(!isset($client_id)){ header("location: error.php"); exit(); }

    $client = get_client_info(trim($client_id));
    if(!$client){ header("location: error.php"); exit(); }

    // fetch program flags from DB (no hard-coded lists)
    $prog = sql_select_one(
        $con,
        "SELECT p.id AS program_id, p.uses_weekly_attendance, p.uses_milestones
           FROM client c JOIN program p ON p.id=c.program_id
          WHERE c.id=?",
        [$client_id]
    );
    $program_id = (int)($prog['program_id'] ?? 0);
    $uses_milestones = ((int)($prog['uses_milestones'] ?? 0) === 1);
    $uses_weekly_attendance = ((int)($prog['uses_weekly_attendance'] ?? 0) === 1);

    // labels
    $client_stage_name = '';
    if (!empty($client['client_stage_id'])) {
        $client_stage_name = fetch_label_by_id($con, 'client_stage', 'id', $client['client_stage_id'], ['name','stage','label','title']);
    }
    $therapy_group_name = '';
    if (!empty($client['therapy_group_id'])) {
        $therapy_group_name = fetch_label_by_id($con, 'therapy_group', 'id', $client['therapy_group_id'], ['name','group_name','title']);
    }
    $program_name = '';
    if (!empty($client['program_id'])) {
      $program_name = fetch_label_by_id($con, 'program', 'id', $client['program_id'], ['name','program','title']);
    }

    // ---------- Milestones / Attendance prep (consolidated) ----------
    // Grouping plan per program (hard-coded Phase 1, per spec)
    // 1 DOEP: 2–2–2–1–1, 2 DWIE: 2–3–2, 3 DWII: 1 per module, 4 Parenting: 4–4, 6 LSAT: 4–4
    $MILESTONE_GROUPING = [
        1 => [2,2,2,1,1], // DOEP
        2 => [2,3,2],     // DWIE
        3 => 1,           // DWII (one module per day)
        4 => [4,4],       // Parenting
        6 => [4,4],       // LSAT
    ];
    function group_curriculum_for_program(array $curriculum, int $program_id, array $map): array {
        if (!$curriculum) return [];
        $plan = $map[$program_id] ?? 4;
        $groups = []; $idx = 0; $day = 1;
        if (is_int($plan)) {
            $chunk = max(1, $plan);
            while ($idx < count($curriculum)) {
                $groups[$day++] = array_slice($curriculum, $idx, $chunk);
                $idx += $chunk;
            }
        } else {
            $sizes = array_values(array_filter($plan, fn($n)=> (int)$n>0)); if (!$sizes) $sizes=[4];
            $si=0; while ($idx < count($curriculum)) {
                $take = (int)$sizes[min($si, count($sizes)-1)];
                $groups[$day++] = array_slice($curriculum, $idx, $take);
                $idx += $take; $si++;
            }
        }
        return $groups;
    }

    // Expected DOW fallback when attends_* not set (limited where predictable)
    // 0=Sun ... 6=Sat
    $EXPECTED_DOWS = [
        4 => [6,0], // Parenting: Sat+Sun
        6 => [6,0], // LSAT: Sat+Sun
        // DOEP/DWIE vary; DWII uses weekly flags
    ];
    function dows_to_flags(array $dows): array {
        $f = ['sunday'=>0,'monday'=>0,'tuesday'=>0,'wednesday'=>0,'thursday'=>0,'friday'=>0,'saturday'=>0];
        foreach ($dows as $n) {
            $name = strtolower((new DateTime("Sunday +$n day"))->format('l'));
            $f[$name] = 1;
        }
        return $f;
    }
    function next_expected_from_flags(array $flags): ?string {
        $today = new DateTime('today');
        for ($i=0; $i<14; $i++) {
            $d = (clone $today)->modify("+$i day");
            $dow = strtolower($d->format('l'));
            if (!empty($flags[$dow])) return $d->format('Y-m-d');
        }
        return null;
    }

    // Defaults
    $curriculum = []; $progress = []; $done = []; $groups = [];
    $minDate = ''; $maxDate = date('Y-m-d');

    if ($uses_milestones) {
        // curriculum items by strict sort_order then id
        $curriculum = sql_select_all(
            $con, "SELECT * FROM curriculum WHERE program_id=? ORDER BY sort_order ASC, id ASC", [$program_id]
        );

        // completions from attendance_curriculum
        $rows = sql_select_all(
            $con, "SELECT curriculum_id AS cid,
                          DATE_FORMAT(completed_at,'%Y-%m-%d %H:%i') AS completed_at
                     FROM attendance_curriculum
                    WHERE client_id=? AND program_id=?",
            [$client_id, $program_id]
        );
        foreach ($rows as $r) {
            $cid = (int)($r['cid'] ?? 0);
            if ($cid > 0) {
                $progress[$cid] = true;
                $done[$cid] = $r['completed_at'];
            }
        }

        // group into days
        $groups = group_curriculum_for_program($curriculum, $program_id, $MILESTONE_GROUPING);

        // back/forward dating bound
        if (!empty($client['orientation_date'])) {
            $minDate = (new DateTime($client['orientation_date']))->format('Y-m-d');
        }
    }

    // Attendance arrays for calendars and Missed box
    $attendance = get_client_attendance_days(trim($client_id));
    $temp = get_client_absence_days(trim($client_id));
    $excused = $temp[0];
    $unexcused = $temp[1];

    // Last attended + next expected
    $last_present = sql_select_one(
        $con,
        "SELECT CAST(ts.`date` AS DATE) AS session_date
           FROM attendance_record ar
           JOIN therapy_session ts ON ts.id = ar.therapy_session_id
          WHERE ar.client_id=?
          ORDER BY ts.`date` DESC
          LIMIT 1",
        [$client_id]
    );
    $last_attended = $last_present['session_date'] ?? '—';

    $flags = [
        'sunday'    => (int)($client['attends_sunday']??0),
        'monday'    => (int)($client['attends_monday']??0),
        'tuesday'   => (int)($client['attends_tuesday']??0),
        'wednesday' => (int)($client['attends_wednesday']??0),
        'thursday'  => (int)($client['attends_thursday']??0),
        'friday'    => (int)($client['attends_friday']??0),
        'saturday'  => (int)($client['attends_saturday']??0),
    ];
    if (array_sum($flags) === 0) {
        $flags = dows_to_flags($EXPECTED_DOWS[$program_id] ?? []);
    }
    $next_expected = next_expected_from_flags($flags);

    // --- Calendar + Conduct helpers (unchanged) ---
    function buildCalendar($date, $attendance, $excused, $unexcused){
      $today   = new DateTime('today');
      $month   = (int)date('m', $date);
      $year    = (int)date('Y', $date);
      $first   = mktime(0, 0, 0, $month, 1, $year);
      $curDay  = (new DateTime())->setTimestamp($first);
      $title   = date('F', $first);

      $dow = date('w', $first); // 0=Sun..6=Sat
      $blank = (int)$dow;
      $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

      echo '<table class="table table-bordered table-sm w-100 text-center mb-3">';
      echo '<thead class="table-light"><tr><th colspan="7">'.htmlspecialchars($title.' '.$year).'</th></tr>';
      echo '<tr><th>S</th><th>M</th><th>T</th><th>W</th><th>R</th><th>F</th><th>S</th></tr></thead><tbody>';

      $day_count = 1;
      echo '<tr>';
      while ($blank-- > 0) { echo '<td></td>'; $day_count++; }

      for ($d = 1; $d <= $days_in_month; $d++) {
          $dateStr = $curDay->format('Y-m-d');
          $isToday = ($dateStr === $today->format('Y-m-d'));

          echo '<td>';
          echo $isToday ? '<b>'.$d.'</b>' : $d;
          if ($d < 10) echo '&nbsp;'; echo '&nbsp;';

          $c = arrayCount($dateStr, $attendance); for ($i=0; $i<$c; $i++) echo '&#x2705';
          $c = arrayCount($dateStr, $excused);    for ($i=0; $i<$c; $i++) echo '&#x2716';
          $c = arrayCount($dateStr, $unexcused);  for ($i=0; $i<$c; $i++) echo '&#x274C';

          echo '</td>';

          $curDay->modify('+1 day');
          if (++$day_count > 7) { echo '</tr><tr>'; $day_count = 1; }
      }

      while ($day_count > 1 && $day_count <= 7) { echo '<td></td>'; $day_count++; }
      echo '</tr></tbody></table>';
    }

    function displayConduct($description, $varName, $client, $goodValue) {
        $raw = $client[$varName] ?? 0; // tinyint(1)
        $val = ($raw === 1 || $raw === '1' || $raw === true || $raw === 'true') ? 'true' : 'false';
        $good = ($goodValue === 'true');
        $isGood = (($val === 'true') === $good);
        $cls = $isGood ? 'text-success' : 'text-danger';
        echo "<h5 class='{$cls}'>" . htmlspecialchars($description) . ": " . htmlspecialchars($val) . "</h5>";
    }
?>

<div class="container-fluid">
  <div class="row mb-3">
    <div class="col">
      <div class="row align-items-center gx-3 gy-2 flex-wrap">
        <div class="col-auto">
          <h2 class="mb-0">Client Information</h2>
        </div>
        <div class="col-auto">
          <h5 class="mb-0"><a class="nav-link p-0" href="./client-update.php?id=<?= htmlspecialchars($client_id); ?>">Edit Client</a></h5>
        </div>
        <div class="col-auto">
          <h5 class="mb-0"><a class="nav-link p-0" href="./client-attendance.php?client_id=<?= htmlspecialchars($client_id); ?>">Attendance</a></h5>
        </div>
        <div class="col-auto">
          <h5 class="mb-0"><a class="nav-link p-0" href="./client-ledger.php?client_id=<?= htmlspecialchars($client_id); ?>">Payments</a></h5>
        </div>
        <div class="col-auto">
          <h5 class="mb-0"><a class="nav-link p-0" href="./client-event.php?client_id=<?= htmlspecialchars($client_id); ?>">Event History</a></h5>
        </div>
        <div class="col-auto">
          <h5 class="mb-0"><a class="nav-link p-0" href="./client-victim.php?client_id=<?= htmlspecialchars($client_id); ?>">Victim Info</a></h5>
        </div>
      </div>
    </div>
  </div>


  <div class="row bg-light">
    <div class="col-12 bg-white">
      <!-- TOP ROW: left = cards, right = milestones and/or attendance calendars -->
      <div class="row">
        <!-- LEFT: grouped read-only cards -->
        <div class="col-8 pt-3 pb-3">
          <!-- Identity -->
          <div class="card mb-3">
            <div class="card-header">Identity</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-3"><small class="text-muted d-block">First Name</small><strong><?= htmlspecialchars($client['first_name']) ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Last Name</small><strong><?= htmlspecialchars($client['last_name']) ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">DOB (Age)</small><strong><?= htmlspecialchars(($client['date_of_birth'] ?? '').(!empty($client['age']) ? ' ('.$client['age'].')' : '')) ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Birth Place</small><strong><?= htmlspecialchars($client['birth_place'] ?? '') ?></strong></div>
              </div>
              <div class="row mt-2">
                <div class="col-md-3"><small class="text-muted d-block">SID</small><strong><?= htmlspecialchars($client['sid'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">DL / SSN</small><strong><?= htmlspecialchars($client['ssl_dln'] ?? '') ?></strong></div>
              </div>
            </div>
          </div>

          <!-- Demographics & Status -->
          <div class="card mb-3">
            <div class="card-header">Demographics & Status</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-3"><small class="text-muted d-block">Gender</small><strong><?= htmlspecialchars($client['gender'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Ethnicity</small><strong><?= htmlspecialchars($client['ethnicity'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Marital Status</small><strong><?= htmlspecialchars($client['marital_status'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Employed</small><strong><?= htmlspecialchars($client['employed'] ?? '') ?></strong></div>
              </div>
              <div class="row mt-2">
                <div class="col-md-3"><small class="text-muted d-block">UA Positive</small><strong><?= htmlspecialchars($client['UA_positive'] ?? '') ?></strong></div>
                <div class="col-md-9"><small class="text-muted d-block">Prescription Use</small><strong><?= htmlspecialchars($client['prescription_use'] ?? '') ?></strong></div>
              </div>
            </div>
          </div>

          <!-- Program & Group -->
          <div class="card mb-3">
            <div class="card-header">Program & Group</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-3"><small class="text-muted d-block">Program</small><strong><?= htmlspecialchars($program_name ?: ($client['program_id'] ?? '')) ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Therapy Group</small><strong><?= htmlspecialchars($therapy_group_name ?: ($client['therapy_group_id'] ?? '')) ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Case Manager</small><strong><?= htmlspecialchars($client['case_manager'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Progress Stage</small><strong><?= htmlspecialchars($client_stage_name ?: ($client['client_stage_id'] ?? '')) ?></strong></div>
              </div>
              <div class="row mt-2">
                <div class="col-md-2"><small class="text-muted d-block">Required Sessions</small><strong><?= htmlspecialchars($client['sessions_required'] ?? $client['required_sessions'] ?? '') ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Sessions / Week</small><strong><?= htmlspecialchars($client['weekly_attendance'] ?? '') ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Fee / Session</small><strong><?= htmlspecialchars(isset($client['fee']) ? ('$'.$client['fee']) : '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Orientation Date</small><strong><?= htmlspecialchars($client['orientation_date'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Instructor</small><strong><?= htmlspecialchars($client['instructor'] ?? '') ?></strong></div>
              </div>
            </div>
          </div>

          <!-- Referral & Case -->
          <div class="card mb-3">
            <div class="card-header">Referral & Case</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-3"><small class="text-muted d-block">Referral Type</small><strong><?= htmlspecialchars($client['referral_type'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Cause Number</small><strong><?= htmlspecialchars($client['cause_number'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">County</small><strong><?= htmlspecialchars($client['county'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">PO Office</small><strong><?= htmlspecialchars($client['po_office'] ?? '') ?></strong></div>
              </div>
              <div class="row mt-2">
                <div class="col-md-6"><small class="text-muted d-block">Referral Email</small><strong><?= htmlspecialchars($client['referral_email'] ?? '') ?></strong></div>
              </div>
            </div>
          </div>

          <!-- Contact & Address -->
          <div class="card mb-3">
            <div class="card-header">Contact & Address</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-3"><small class="text-muted d-block">Phone</small><strong><?= htmlspecialchars($client['phone_number'] ?? '') ?></strong></div>
                <div class="col-md-5"><small class="text-muted d-block">Email</small><strong><?= htmlspecialchars($client['email'] ?? '') ?></strong></div>
                <div class="col-md-4"><small class="text-muted d-block">Emergency Contact</small><strong><?= htmlspecialchars($client['emergency_contact'] ?? '') ?></strong></div>
              </div>
              <div class="row mt-2">
                <div class="col-md-6"><small class="text-muted d-block">Address</small><strong><?= htmlspecialchars($client['address'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">City</small><strong><?= htmlspecialchars($client['city'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">State / ZIP</small><strong><?= htmlspecialchars($client['state_zip'] ?? '') ?></strong></div>
              </div>
            </div>
          </div>

          <!-- Attendance (summary) -->
          <div class="card mb-4">
            <div class="card-header">Attendance</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-3">
                  <small class="text-muted d-block">Sessions Attended</small>
                  <strong><?= htmlspecialchars(($client['sessions_attended'] ?? '').' of '.($client['sessions_required'] ?? $client['required_sessions'] ?? '')) ?></strong>
                </div>
                <div class="col-md-3">
                  <small class="text-muted d-block">Balance</small>
                  <?php $bal = $client['balance'] ?? null; ?>
                  <strong class="<?= isset($bal) && $bal < 0 ? 'text-danger':'text-success' ?>">
                    <?= isset($bal) ? '$'.htmlspecialchars($bal) : '' ?>
                  </strong>
                </div>
                <div class="col-md-3">
                  <small class="text-muted d-block">Received Intake Packet</small>
                  <?php $gotPacket = intval($client['intake_packet'] ?? 0); ?>
                  <strong class="<?= $gotPacket ? 'text-success' : 'text-danger' ?>">
                    <?= $gotPacket ? 'Yes' : 'No' ?>
                  </strong>
                </div>
                <div class="col-md-3">
                  <small class="text-muted d-block">Reentry</small>
                  <strong><?= htmlspecialchars($client['reentry'] ?? '') ?></strong>
                </div>
              </div>

              <div class="row mt-2">
                <div class="col-md-12">
                  <small class="text-muted d-block">Reentry Plan</small>
                  <div><strong><?= htmlspecialchars($client['reentry_plan'] ?? '') ?></strong></div>
                </div>
              </div>

              <!-- ensure following card doesn't overlap -->
              <div class="clearfix"></div>
            </div>
          </div>


          <!-- Payments (summary) -->
          <div class="card mb-3">
            <div class="card-header">Payments</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-2"><small class="text-muted d-block">Paid Amount</small><strong><?= htmlspecialchars(isset($client['paid_amount']) ? '$'.$client['paid_amount'] : '') ?></strong></div>
                <div class="col-md-2"><small class="text-muted d-block">Source</small><strong><?= htmlspecialchars($client['paid_source'] ?? '') ?></strong></div>
                <div class="col-md-8"><small class="text-muted d-block">Payment Note</small><strong><?= htmlspecialchars($client['paid_note'] ?? '') ?></strong></div>
              </div>
            </div>
          </div>

          <!-- Notes & Behavior Contract -->
          <div class="card mb-3">
            <div class="card-header">Notes & Behavior Contract</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6"><small class="text-muted d-block">Client Notes</small><div><?= nl2br(htmlspecialchars($client['client_note'] ?? $client['note'] ?? '')) ?></div></div>
                <div class="col-md-6"><small class="text-muted d-block">Other Concerns</small><div><?= nl2br(htmlspecialchars($client['other_concerns'] ?? '')) ?></div></div>
              </div>
              <div class="row mt-2">
                <div class="col-md-3"><small class="text-muted d-block">Contract Status</small><strong><?= htmlspecialchars($client['behavior_contract_status'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Signed Date</small><strong><?= htmlspecialchars($client['behavior_contract_signed_date'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Exit Date</small><strong><?= htmlspecialchars($client['exit_date'] ?? '') ?></strong></div>
                <div class="col-md-3"><small class="text-muted d-block">Exit Reason</small><strong><?= htmlspecialchars($client['exit_reason'] ?? '') ?></strong></div>
              </div>
            </div>
          </div>

          <!-- Conduct -->
          <div class="card mb-3">
            <div class="card-header">Conduct</div>
            <div class="card-body">
              <?php
                displayConduct('Excessive speaking', 'speaksSignificantlyInGroup', $client, 'false');
                displayConduct('Respectful towards group', 'respectfulTowardsGroup', $client, 'true');
                displayConduct('Takes responsibility', 'takesResponsibilityForPastBehavior', $client, 'true');
                displayConduct('Disruptive', 'disruptiveOrArgumentitive', $client, 'false');
                displayConduct('Inappropriate humor', 'inappropriateHumor', $client, 'false');
                displayConduct('Blames victim', 'blamesVictim', $client, 'false');
                displayConduct('Alcohol or Drugs', 'drug_alcohol', $client, 'false');
                displayConduct('Inappropriate behavior', 'inappropriate_behavior_to_staff', $client, 'false');
              ?>
            </div>
          </div>
        </div>

        <!-- RIGHT: milestones and/or attendance calendars -->
        <div class="col-4 pt-3 pb-3">
          <?php if ($uses_milestones): ?>
            <h5>Milestones</h5>
            <div class="alert alert-warning py-2 mb-2" role="alert" style="font-size:0.95rem;">
              Check only when applicable. Changes save immediately. Each check stores a timestamp.
            </div>

            <div class="mb-2">
              <label for="milestone_session_date" class="small text-muted">Session date</label>
              <input type="date" id="milestone_session_date" class="form-control form-control-sm"
                    value="<?= $maxDate ?>"
                    <?= $minDate ? 'min="'.htmlspecialchars($minDate).'"' : '' ?>
                    max="<?= $maxDate ?>" style="max-width:180px;">
            </div>

            <div id="milestone-list">
              <?php if (empty($groups)): ?>
                <div class="text-muted">No curriculum items found for this program.</div>
              <?php else: ?>
                <?php foreach ($groups as $dayNum => $items): ?>
                  <h6 class="mt-3 mb-2">Day <?= intval($dayNum) ?></h6>
                  <?php foreach ($items as $item):
                    $cid     = (int)$item['id'];
                    $checked = isset($progress[$cid]) ? 'checked' : '';
                    $label   = ($item['short_description'] ?? '') ?: ($item['long_description'] ?? ('Item '.$cid));
                    $dateTxt = $done[$cid] ?? '';
                  ?>
                    <div class="d-flex align-items-center mb-2">
                      <div class="form-check m-0">
                        <input class="form-check-input milestone-box"
                               type="checkbox"
                               id="milestone_<?= $cid ?>"
                               data-client-id="<?= (int)$client_id ?>"
                               data-program-id="<?= (int)$program_id ?>"
                               data-curriculum-id="<?= $cid ?>" <?= $checked ?>>
                        <label class="form-check-label" for="milestone_<?= $cid ?>">
                          <?= htmlspecialchars($label) ?>
                        </label>
                      </div>
                      <small class="ml-3 text-muted" id="milestone_date_<?= $cid ?>">
                        <?= $dateTxt ? htmlspecialchars($dateTxt) : '' ?>
                      </small>
                    </div>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div id="milestone-status" class="small text-muted mt-1" aria-live="polite"></div>
          <?php endif; ?>

          <?php if ($uses_weekly_attendance): ?>
            <h5 class="mt-4">Attendance History</h5>
            <div class="mb-2"></div>
            <?php
              // Build calendars from orientation → (exit_date or today)
              $startDate = !empty($client['orientation_date']) ? new DateTime($client['orientation_date']) : null;
              if ($startDate) {
                  $endDate = !empty($client['exit_date']) ? new DateTime($client['exit_date']) : new DateTime('today');
                  $startMonth = (clone $startDate)->modify('first day of this month');
                  $endMonth   = (clone $endDate)->modify('first day of this month');
                  for ($d = $startMonth; $d <= $endMonth; $d->modify('first day of next month')) {
                      buildCalendar($d->getTimestamp(), $attendance, $excused, $unexcused);
                  }
              } else {
                  echo '<div class="text-muted">No attendance range available.</div>';
              }
            ?>
          <?php endif; ?>

          <!-- Missed box (works for both modes) + Photo -->
          <div class="mt-4">
            <h6>Missed</h6>
            <table class="table table-sm mb-2 w-100" id="missed-box">
              <tbody>
                <tr><th class="w-25">Last attended</th><td id="last_attended"><?= htmlspecialchars($last_attended) ?></td></tr>
                <tr><th>Next expected</th><td id="next_expected"><?= htmlspecialchars($next_expected ?? '—') ?></td></tr>
                <tr><th>Unrecorded no-shows</th><td id="unrec_noshow"></td></tr>
              </tbody>
            </table>

            <div class="text-muted small">No-shows appear when an expected day has no attendance record.</div>

            <div class="mt-3">
              <img src="getImage.php?id=<?= $client_id; ?>" class="img-thumbnail mb-2 d-block mx-auto" alt="client picture"
                   onerror="this.onerror=null; this.src='img/male-placeholder.jpg'">
              <div class="text-center">
                <a class="btn btn-outline-secondary btn-sm" target="_blank"
                   href="./client-image-upload.php?client_id=<?= htmlspecialchars($client_id); ?>">
                  Update Image
                </a>
              </div>
            </div>
          </div>
        </div> <!-- /RIGHT -->
      </div> <!-- /row -->
    </div>
  </div>
</div>

<script>
async function refreshMissed(){
  const resp = await fetch('missed_state.php?client_id=<?= (int)$client_id ?>');
  const data = await resp.json();
  if (!resp.ok || data.ok!==true) return;

  const last = document.getElementById('last_attended');
  const next = document.getElementById('next_expected');
  if (last) last.textContent = data.last_attended || '—';
  if (next) next.textContent = data.next_expected || '—';

  const cell = document.getElementById('unrec_noshow');
  if (!cell) return;

  const gaps = data.gaps || [];
  if (!gaps.length) { cell.textContent = ''; return; }

  cell.innerHTML = gaps.map((item, idx) => {
    const sep = idx < gaps.length - 1 ? ', ' : '';
    if (item.has_absence) {
      return `<span class="text-danger">${item.date}</span>${sep}`;
    } else {
      return `<button type="button"
                      class="btn btn-link p-0 gap-date text-primary"
                      style="text-decoration: underline;"
                      data-date="${item.date}">${item.date}</button>${sep}`;
    }
  }).join('');
}

document.addEventListener('DOMContentLoaded', refreshMissed);

// handle clicks on available dates
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.gap-date');
  if (!btn) return;
  const date = btn.getAttribute('data-date');
  if (!date) return;

  btn.disabled = true;
  try {
    const resp = await fetch('absence_quick_create.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        client_id: <?= (int)$client_id ?>,
        date: date,
        excused: 0,
        note: 'Created via Client Review Panel'
      })
    });
    const data = await resp.json();
    if (!resp.ok || data.ok !== true) throw new Error(data.error || 'Create failed');
    await refreshMissed();
  } catch (err) {
    alert('Unable to create absence for ' + date);
  } finally {
    btn.disabled = false;
  }
});

// ---- Milestone checkbox handler ----
document.addEventListener('change', async (e) => {
  const box = e.target.closest('.milestone-box');
  if (!box) return;

  const checked = box.checked ? 1 : 0;
  const cid  = parseInt(box.dataset.curriculumId, 10);
  const pid  = parseInt(box.dataset.programId, 10);
  const clid = parseInt(box.dataset.clientId, 10);
  const dateInput = document.getElementById('milestone_session_date');
  const session_date = dateInput && dateInput.value ? dateInput.value : null;

  box.disabled = true;
  const status = document.getElementById('milestone-status');

  try {
    const resp = await fetch('milestone_toggle.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        client_id: clid,
        program_id: pid,
        curriculum_id: cid,
        complete: checked,
        session_date: session_date,
        source: 'panel'
      })
    });
    const data = await resp.json();
    if (!resp.ok || data.ok !== true) {
      throw new Error(data.error || 'Update failed');
    }
    // stamp per-item timestamp
    const stamp = document.getElementById(`milestone_date_${cid}`);
    if (stamp) stamp.textContent = data.completed_at || '';
    if (status) status.textContent = 'Saved.';
  } catch (err) {
    // revert on failure
    box.checked = !checked;
    if (status) status.textContent = 'Error saving change.';
    alert('Milestone update failed.');
  } finally {
    box.disabled = false;
  }
});

</script>
