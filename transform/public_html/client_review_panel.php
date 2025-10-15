<?php
/******************************************************************************
 *  client_review_panel.php
 *  ──────────────────────────────────────────────────────────────────────────
 *  Shows a single client’s demographics, identification info, attendance
 *  history, conduct flags, balance, image, etc.  Layout now mirrors the
 *  create-form: Identification row replaces the old Cause-Number cell.
 ******************************************************************************/

require_once "../config/config.php";
require_once "sql_functions.php";
include_once "helpers.php";

/* --------------------------------------------------------------------------
   1.  Get client-id
-------------------------------------------------------------------------- */
$client_id = $_GET['client_id']   ?? $_POST['client_id'] ?? null;
if (!$client_id) { header("location: error.php"); exit; }

/* --------------------------------------------------------------------------
   2.  Fetch data
-------------------------------------------------------------------------- */
$client = get_client_info(trim($client_id));
if (!$client) { header("location: error.php"); exit; }

$attendance             = get_client_attendance_days($client_id);
[$excused,$unexcused]   = get_client_absence_days($client_id);

/* --------------------------------------------------------------------------
   3.  Helper functions (identical to original versions)
-------------------------------------------------------------------------- */
function buildCalendar($date,$attendance,$excused,$unexcused){
    $today      = new DateTime('today');
    $day        = date('d', $date);
    $month      = date('m', $date);
    $year       = date('Y', $date);
    $first_day  = mktime(0,0,0,$month,1,$year);
    $curDay     = new DateTime(); $curDay->setTimestamp($first_day);
    $title      = date('F', $first_day);
    $day_of_week= date('D', $first_day);

    switch($day_of_week){
        case "Sun": $blank = 0; break;
        case "Mon": $blank = 1; break;
        case "Tue": $blank = 2; break;
        case "Wed": $blank = 3; break;
        case "Thu": $blank = 4; break;
        case "Fri": $blank = 5; break;
        case "Sat": $blank = 6; break;
    }
    $days_in_month = cal_days_in_month(0,$month,$year);

    echo "<table border=1 width=392>";
    echo "<tr><th colspan=7>$title&nbsp;$year</th></tr>";
    echo "<tr><td width=56>S</td><td width=56>M</td><td width=56>T</td><td width=56>W</td><td width=56>R</td><td width=56>F</td><td width=56>S</td></tr>";

    $day_count = 1;  echo "<tr>";
    while ($blank > 0){ echo "<td></td>"; $blank--; $day_count++; }

    $day_num = 1;
    while ($day_num <= $days_in_month){
        echo "<td>";
        echo ($curDay==$today? "<b>$day_num</b>" : $day_num);
        if($day_num<10) echo "&nbsp;"; echo "&nbsp;";

        $cnt=arrayCount($curDay->format("Y-m-d"),$attendance); for($i=0;$i<$cnt;$i++) echo "&#x2705";
        $cnt=arrayCount($curDay->format("Y-m-d"),$excused);    for($i=0;$i<$cnt;$i++) echo "&#x2716";
        $cnt=arrayCount($curDay->format("Y-m-d"),$unexcused);  for($i=0;$i<$cnt;$i++) echo "&#x274C";

        echo "</td>";

        $day_num++; $day_count++; $curDay->modify('+1 day');
        if($day_count>7){ echo "</tr>"; $day_count=1; }
    }
    while($day_count>1 && $day_count<=7){ echo "<td></td>"; $day_count++; }
    echo "</tr></table>";
}

function displayConduct($label,$var,$client,$good){
    $val = $client[$var];
    $class = ($val==$good? 'text-success' : 'text-danger');
    echo "<h5 class=\"$class\">$label: ".htmlspecialchars($val)."</h5>";
}
?>
<div class="container-fluid">
  <div class="row">
    <div class="col">
      <div class="row">
        <div class="col-2"><h2>Client Information</h2></div>
        <div class="col-1"><h5><a class="nav-link" href="client-update.php?id=<?=htmlspecialchars($client_id)?>">Edit Client</a></h5></div>
        <div class="col-1"><h5><a class="nav-link" href="client-attendance.php?client_id=<?=htmlspecialchars($client_id)?>">Attendance</a></h5></div>
        <div class="col-1"><h5><a class="nav-link" href="client-ledger.php?client_id=<?=htmlspecialchars($client_id)?>">Payments</a></h5></div>
        <div class="col-1"><h5><a class="nav-link" href="client-event.php?client_id=<?=htmlspecialchars($client_id)?>">Event History</a></h5></div>
        <div class="col-1"><h5><a class="nav-link" href="client-victim.php?client_id=<?=htmlspecialchars($client_id)?>">Victim Info</a></h5></div>
        <!-- Victim Info link – paste inside the row that holds the other nav buttons -->
        <div class="col-1"><h5><a class="nav-link" href="./client-victim.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Victim&nbsp;Info</a></h5></div>
      </div>
    </div>
  </div>

  <div class="row bg-light">
    <!-- ============== LEFT COLUMN – Demographic & Conduct ============== -->
    <div class="col-5 pt-3 pb-3">

      <!-- Row A: name / dob / gender -->
      <div class="row">
        <div class="col-3"><small class="text-muted">First Name</small><h5><?=htmlspecialchars($client['first_name'])?></h5></div>
        <div class="col-3"><small class="text-muted">Last Name</small><h5><?=htmlspecialchars($client['last_name'])?></h5></div>
        <div class="col-3"><small class="text-muted">DOB (Age)</small><h5><?=htmlspecialchars($client['date_of_birth'])." (".$client['age'].")"?></h5></div>
        <div class="col-3"><small class="text-muted">Gender</small><h5><?=htmlspecialchars($client['gender'])?></h5></div>
      </div>

      <!-- Row B: phone / email -->
      <div class="row">
        <div class="col-3"><small class="text-muted">Phone</small><h5><?=htmlspecialchars($client['phone_number'])?></h5></div>
        <div class="col-4"><small class="text-muted">E-Mail</small><h5><?=htmlspecialchars($client['email'])?></h5></div>
      </div>

      <!-- Row C: referral / case mgr / PO -->
      <div class="row">
        <div class="col-3"><small class="text-muted">Referral Type</small><h5><?=htmlspecialchars($client['referral_type'])?></h5></div>
        <div class="col-4"><small class="text-muted">Case Manager</small><h5><?=htmlspecialchars($client['case_manager'])?></h5></div>
        <div class="col-3"><small class="text-muted">Parole Office</small><h5><?=htmlspecialchars($client['po_office'])?></h5></div>
      </div>

      <!-- Row D: Identification -->
      <div class="row">
        <div class="col-4"><small class="text-muted">ID Number</small><h5><?=htmlspecialchars($client['identification_number'])?></h5></div>
        <div class="col-4"><small class="text-muted">ID Type</small><h5><?=htmlspecialchars($client['identification_type'])?></h5></div>
        <div class="col-4"><small class="text-muted">Other ID Info</small><h5><?=htmlspecialchars($client['other_id_type_description'])?></h5></div>
      </div>

      <!-- Row E: emergency contact -->
      <div class="row"><div class="col"><small class="text-muted">Emergency Contact</small><h5><?=htmlspecialchars($client['emergency_contact'])?></h5></div></div>

      <!-- Row F: notes / concerns -->
      <div class="row"><div class="col"><small class="text-muted">Client Notes</small><h5><?=htmlspecialchars($client['client_note'])?></h5></div></div>
      <div class="row"><div class="col"><small class="text-muted">Other Concerns</small><h5><?=htmlspecialchars($client['other_concerns'])?></h5></div></div>

      <!-- Row G: conduct flags -->
      <div class="row"><div class="col-6"><small class="text-muted">Conduct</small>
        <?php
          displayConduct('Excessive speaking','speaksSignificantlyInGroup',$client,'false');
          displayConduct('Respectful towards group','respectfulTowardsGroup',$client,'true');
          displayConduct('Takes responsibility','takesResponsibilityForPastBehavior',$client,'true');
          displayConduct('Disruptive','disruptiveOrArgumentitive',$client,'false');
          displayConduct('Inappropriate humor','inappropriateHumor',$client,'false');
          displayConduct('Blames victim','blamesVictim',$client,'false');
          displayConduct('Alcohol / Drugs','drug_alcohol',$client,'false');
          displayConduct('Inappropriate behavior','inappropriate_behavior_to_staff',$client,'false');
        ?>
      </div></div>

      <!-- Row H: behavior contract -->
      <div class="row">
        <div class="col-4">
          <small class="text-muted">Behavior Contract</small>
          <?php
            $status = $client['behavior_contract_status'] ?? 'Not Needed';
            $class  = $status==='Signed' ? 'text-success' : ($status==='Needed' ? 'text-danger' : 'text-muted');
          ?>
          <h5 class="<?= $class ?>"><?=htmlspecialchars($status)?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">Signed Date</small>
          <h5><?=htmlspecialchars($client['behavior_contract_signed_date'] ?? 'N/A')?></h5>
        </div>
        <div class="col-4">
          <small>&nbsp;</small><br>
          <a class="btn btn-primary" href="client-contract-upload.php?client_id=<?=urlencode($client_id)?>">Upload Contract</a>
        </div>
      </div>

    </div><!-- /col-5 -->

    <!-- ============== MIDDLE COLUMN – Attendance / Financial ============== -->
    <div class="col-5 pt-3 pb-3">
      <div class="row">
        <div class="col"><small class="text-muted">Regular Group</small><h5><?=htmlspecialchars($client['group_name'])?></h5></div>
        <div class="col-3"><small class="text-muted">Sessions Attended</small><h5><?=htmlspecialchars($client['sessions_attended']." of ".$client['sessions_required'])?></h5></div>
        <div class="col-2"><small class="text-muted">Fee</small><h5>$<?=htmlspecialchars($client['fee'])?></h5></div>
        <div class="col-2">
          <small class="text-muted">Balance</small>
          <?php $bal=$client['balance']; ?>
          <h5 class="<?= $bal<0?'text-danger':'text-success' ?>">$<?=htmlspecialchars($bal)?></h5>
        </div>
      </div>
      <div class="row">
        <div class="col-3"><small class="text-muted">Orientation Date</small><h5><?=htmlspecialchars($client['orientation_date'])?></h5></div>
        <div class="col-2"><small class="text-muted">Referral Date</small><h5><?=htmlspecialchars($client['referral_date'])?></h5></div>
        <div class="col-2"><small class="text-muted">Exit Date</small><h5><?=htmlspecialchars($client['exit_date'])?></h5></div>
        <div class="col"><small class="text-muted">Exit Reason</small><h5><?=htmlspecialchars($client['exit_reason'])?></h5></div>
        <div class="col-3"><small class="text-muted">Birth Place</small><h5><?=htmlspecialchars($client['birth_place'] ?? '')?></h5></div>
      </div>
      <div class="row"><div class="col"><small class="text-muted">Attendance History</small></div></div>
      <div class="row"><div class="col">
        <?php
          $last = new DateTime($client['exit_date'] ?: 'today');
          $cur  = new DateTime($client['orientation_date']);
          while ($cur <= $last){
              buildCalendar($cur->getTimestamp(),$attendance,$excused,$unexcused);
              $cur->modify('first day of next month');
          }
        ?>
      </div></div>
    </div><!-- /col-5 -->

    <!-- ============== RIGHT COLUMN – Image ============== -->
    <div class="col-2 pt-3 pb-3 text-center">
      <img src="getImage.php?id=<?=htmlspecialchars($client_id)?>"
           class="img-thumbnail mb-2"
           alt="client picture"
           onerror="this.onerror=null;this.src='img/male-placeholder.jpg'">
      <a class="nav-link" target="_blank"
         href="client-image-upload.php?client_id=<?=htmlspecialchars($client_id)?>">Update Image</a>
    </div>
  </div><!-- /.row -->
</div><!-- /.container-fluid -->
