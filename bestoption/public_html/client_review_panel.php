<?php
    require_once "../config/config.php";
    require_once "sql_functions.php";
    include "helpers.php";

    $client_id = "";
    if (isset($_GET['client_id'])) {
        $client_id = $_GET['client_id'];
    }
    if (isset($_POST['client_id'])) {
        $client_id = $_POST['client_id'];
    }
    if(!isset($client_id)){
        header("location: error.php");
        exit();
    }
    $client_id_err = "";

    $client = get_client_info(trim($client_id));
    if(!isset($client)){
        // URL doesn't contain valid id parameter. Redirect to error page
        header("location: error.php");
        exit();
    }

    $attendance = get_client_attendance_days(trim($client_id));
    $temp = get_client_absence_days(trim($client_id));
    $excused = $temp[0];
    $unexcused = $temp[1];

    // ----- Exit causes for this client -----
    $exit_causes = [];        // array of labels
    $exit_cause_other = '';   // free text when OTHER is selected

    $q = "
      SELECT ec.code, ec.label, COALESCE(cec.other_text,'') AS other_text
      FROM client_exit_cause cec
      JOIN exit_cause ec ON ec.id = cec.exit_cause_id
      WHERE cec.client_id = ".(int)$client_id."
      ORDER BY ec.sort_order, ec.id
    ";
    if ($res = mysqli_query($link, $q)) {
        while ($row = mysqli_fetch_assoc($res)) {
            $exit_causes[] = $row['label'];
            if ($row['code'] === 'OTHER' && $row['other_text'] !== '') {
                $exit_cause_other = $row['other_text'];
            }
        }
        mysqli_free_result($res);
    }


    // facilitator for this client (nullable)
    $facilitator_name = '';
    $facilitator_phone = '';
    if (!empty($client['facilitator_id'])) {
        $fid = (int)$client['facilitator_id'];
        $q = "SELECT CONCAT(last_name, ', ', first_name) AS name, COALESCE(phone,'') AS phone
              FROM facilitator WHERE id = $fid";
        if ($res = mysqli_query($link, $q)) {
            if ($row = mysqli_fetch_assoc($res)) {
                $facilitator_name  = $row['name'] ?? '';
                $facilitator_phone = $row['phone'] ?? '';
            }
            mysqli_free_result($res);
        }
    }


    function buildCalendar($date, $attendance, $excused, $unexcused){
        $today = new DateTime('today');
        $day = date('d', $date) ; 
        $month = date('m', $date) ; 
        $year = date('Y', $date) ;

        // Here we generate the first day of the month 
        $first_day = mktime(0,0,0,$month, 1, $year) ; 
        $curDay = new DateTime();
        $curDay -> setTimestamp($first_day);

        // This gets us the month name 
        $title = date('F', $first_day);
        
        //Here we find out what day of the week the first day of the month falls on 
        $day_of_week = date('D', $first_day) ; 

        /*Once we know what day of the week it falls on, we know how many
        blank days occure before it. If the first day of the week is a 
        Sunday then it would be zero*/

        switch($day_of_week){ 
            case "Sun": $blank = 0; break; 
            case "Mon": $blank = 1; break; 
            case "Tue": $blank = 2; break; 
            case "Wed": $blank = 3; break; 
            case "Thu": $blank = 4; break; 
            case "Fri": $blank = 5; break; 
            case "Sat": $blank = 6; break; 
        }

        //We then determine how many days are in the current month
        $days_in_month = cal_days_in_month(0, $month, $year) ; 

        /*Here we take a closer look at the days of the month and prepare to make 
        our calendar table  . The first thing we do is determine what day of the week 
        the first of the month falls. Once we know that, we use the switch () function 
        to determine how many blank days we need in our calendar before the first day.*/
        //Here we start building the table heads 
        echo "<table border=1 width=392>";
        echo "<tr><th colspan=7> $title $year </th></tr>";
        echo "<tr><td width=56>S</td><td width=56>M</td><td 
            width=56>T</td><td width=56>W</td><td width=56>R</td><td 
            width=56>F</td><td width=56>S</td></tr>";

        //This counts the days in the week, up to 7
        $day_count = 1;

        echo "<tr>";
        //first we take care of those blank days
        while ( $blank > 0 ) 
        { 
            echo "<td></td>"; 
            $blank = $blank-1; 
            $day_count++;
        }

        /*The first part of this code very simply echos the table tags, the month name, 
        and the headings for the days of the week. Then we start a while loop. 
        What we are doing is echoing empty table details, one for each blank day 
        we count down. Once the blank days are done it stops. At the same time, 
        our $day_count is going up by 1 each time through the loop. This is to keep 
        count so that we do not try to put more than seven days in a week.*/

        /*To finish our calendar we use one last while loop. This one fills in the 
        rest of our calendar with blank table details if needed. 
        Then we close our table and our script is done.*/

        //sets the first day of the month to 1 
        $day_num = 1;

        //count up the days, until we've done all of them in the month
        while ( $day_num <= $days_in_month ) 
        {   
            echo "<td>";
            if($curDay == $today) {
                echo "<b>" . $day_num . "</b>";  // Bold today
            }
            else {
                echo $day_num;
            }
            if($day_num < 10){
                echo "&nbsp;";    
            }
            echo "&nbsp;";
                
            $count = arrayCount($curDay->format("Y-m-d"), $attendance);
            for($i=0; $i<$count; $i++) {
                echo "&#x2705";  // Green Check for present
            }

            $count = arrayCount($curDay->format("Y-m-d"), $excused);
            for($i=0; $i<$count; $i++) {
                echo "&#x2716";  // Grey x for excused absence
            }

            $count = arrayCount($curDay->format("Y-m-d"), $unexcused);
            for($i=0; $i<$count; $i++) {
                    echo "&#x274C";  // Red X for unexcused
            }

            echo "</td>"; 

            $day_num++; 
            $day_count++;
            $curDay->modify('+ 1 day');

            //Make sure we start a new row every week
            if ($day_count > 7)
            {
                echo "</tr>";
                $day_count = 1;
            }
        }

        /*Now we need to fill in the days of the month. We do this with another while loop, 
        but this time we are counting up to the last day of the month. Each cycle echos 
        a table detail with the day of the month, and it repeats until we reach the last 
        day of the month. Our loop also contains a conditional statement. This checks 
        if the days of the week have reached 7, the end of the week. If it has, 
        it starts a new row, and resets the counter back to 1 (the first day of the week).*/

        //Finaly we finish out the table with some blank details if needed
        while ( $day_count >1 && $day_count <=7 )
        {
            echo "<td> </td>";
            $day_count++;
        }

        echo "</tr></table>";

    }

    function displayConduct($description, $varName, $client, $goodValue) {
        $value = $client[$varName];

        if($value == $goodValue) {
            echo "<h5 class='text-success'>" . $description . ": " . htmlspecialchars($value) . "</h5>";
        }
        else {
            echo "<h5 class='text-danger'>" . $description . ": " . htmlspecialchars($value) . "</h5>";
        }
    }

?>


<div class="container-fluid">
  <div class="row">
    <div class="col">
      <div class="row">
        <div class="col-2">
          <h2>Client Information</h2>
        </div>
        <div class="col-2">
          <h5><a class="nav-link" href="./client-update.php?id=<?= htmlspecialchars($client_id) ?>">Edit Client</a></h5>
        </div>
        <div class="col-2">
          <h5><a class="nav-link" href="./client-attendance.php?client_id=<?= htmlspecialchars($client_id) ?>">Attendance</a></h5>
        </div>
        <div class="col-2">
          <h5><a class="nav-link" href="./client-ledger.php?client_id=<?= htmlspecialchars($client_id) ?>">Payments</a></h5>
        </div>
        <div class="col-2">
          <h5><a class="nav-link" href="./client-event.php?client_id=<?= htmlspecialchars($client_id) ?>">Event History</a></h5>
        </div>
        <div class="col-2">
          <h5><a class="nav-link" href="./client-victim.php?client_id=<?= htmlspecialchars($client_id) ?>">Victim Info</a></h5>
        </div>
      </div>
    </div>
  </div>

  <!-- ========= TOP BAR ========= -->
  <div class="row align-items-start pt-3 pb-2">
    <!-- Left: identity + metrics -->
    <div class="col-9">
      <!-- Row 1: External/Names -->
      <div class="row">
        <div class="col-2">
          <small class="text-muted">External ID</small>
          <h5><?= htmlspecialchars($client['external_id'] ?? '') ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">First Name</small>
          <h5><?= htmlspecialchars($client['first_name'] ?? '') ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">Preferred</small>
          <h5><?= htmlspecialchars($client['preferred_name'] ?? '') ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">Middle</small>
          <h5><?= htmlspecialchars($client['middle_name'] ?? '') ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">Last Name</small>
          <h5><?= htmlspecialchars($client['last_name'] ?? '') ?></h5>
        </div>
      </div>

      <!-- Row 2: DOB / Gender / Email -->
      <div class="row mt-2">
        <div class="col-3">
          <small class="text-muted">DOB</small>
          <h5><?= htmlspecialchars(($client['date_of_birth'] ?? '')) . (isset($client['age']) ? " (" . htmlspecialchars($client['age']) . ")" : "") ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">Gender</small>
          <h5><?= htmlspecialchars($client['gender'] ?? '') ?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">E-Mail</small>
          <h5><?= htmlspecialchars($client['email'] ?? '') ?></h5>
        </div>
      </div>
      <!-- Address -->
      <div class="row mt-2">
        <div class="col-5">
          <small class="text-muted">Street</small>
          <h5><?= htmlspecialchars($client['street'] ?? '') ?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">City/State/Zip</small>
          <h5><?= htmlspecialchars(trim(($client['city'] ?? '') . ' ' . ($client['state'] ?? '') . ' ' . ($client['zip'] ?? ''))) ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">County</small>
          <h5><?= htmlspecialchars($client['county'] ?? '') ?></h5>
        </div>
      </div>

      <!-- Phones (email moved up) -->
      <div class="row mt-3">
        <div class="col-4">
          <small class="text-muted">Mobile Phone</small>
          <h5><?= htmlspecialchars($client['phone_number'] ?? '') ?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">Home Phone</small>
          <h5><?= htmlspecialchars($client['phone_number_home'] ?? '') ?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">Work Phone</small>
          <h5><?= htmlspecialchars($client['phone_number_work'] ?? '') ?></h5>
        </div>
      </div>

      <!-- Row 3: Fee / Balance / Sessions -->
      <div class="row">
        <div class="col-2">
          <small class="text-muted">Fee</small>
          <h5>$<?= htmlspecialchars($client['fee'] ?? 0) ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">Balance</small>
          <?php
            $balance  = floatval($client['balance'] ?? 0);
            $balClass = $balance < 0 ? 'text-danger' : 'text-success';
          ?>
          <h5 class="<?= $balClass; ?>">$<?= htmlspecialchars(number_format($balance, 2)) ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">Sessions Attended</small>
          <h5><?= htmlspecialchars(($client['sessions_attended'] ?? 0) . " of " . ($client['sessions_required'] ?? 0)) ?></h5>
        </div>
        <div class="col">
          <small class="text-muted">Diagnostic Codes</small>
          <h5><?= nl2br(htmlspecialchars($client['diagnostic_codes'] ?? '')) ?></h5>
        </div>
      </div>
    </div>

    <!-- Right: image -->
    <div class="col-2 text-center">
      <img src="getImage.php?id=<?= (int)$client_id; ?>" class="img-thumbnail"
           alt="client picture"
           onerror="this.onerror=null; this.src='img/male-placeholder.jpg'">
      <div class="mt-2">
        <a class="nav-link p-0" target="_blank" href="./client-image-upload.php?client_id=<?= htmlspecialchars($client_id); ?>">Update Image</a>
      </div>
    </div>
  </div>

  <!-- ========= DETAILS ========= -->
  <div class="row bg-light pt-3 pb-2">
    <div class="col-12">
      <!-- Program / Dates -->
      <div class="row">
        <div class="col">
          <small class="text-muted">Facilitator</small>
          <h5>
            <?= htmlspecialchars($facilitator_name ?: 'Not assigned') ?>
            <?php if ($facilitator_phone): ?>
              <small class="text-muted">(<?= htmlspecialchars($facilitator_phone) ?>)</small>
            <?php endif; ?>
          </h5>
        </div>
        <div class="col">
          <small class="text-muted">Regular Group</small>
          <h5><?= htmlspecialchars($client['group_name'] ?? '') ?></h5>
        </div>
        <div class="col">
          <small class="text-muted">Orientation Date</small>
          <h5><?= htmlspecialchars($client['orientation_date'] ?? '') ?></h5>
        </div>
        <div class="col">
          <small class="text-muted">Exit Date</small>
          <h5><?= htmlspecialchars($client['exit_date'] ?? '') ?></h5>
        </div>
        <div class="col">
          <small class="text-muted">Exit Reason</small>
          <h5><?= htmlspecialchars($client['exit_reason'] ?? '') ?></h5>
        </div>

        <div class="col">
          <small class="text-muted">Exit Cause(s)</small>
          <h5>
            <?= htmlspecialchars(implode(', ', $exit_causes) ?: '—') ?>
            <?php if (!empty($exit_cause_other)): ?>
              <div><small class="text-muted">Other: <?= htmlspecialchars($exit_cause_other) ?></small></div>
            <?php endif; ?>
          </h5>
        </div>

        <div class="col">
          <small class="text-muted">Place of Birth</small>
          <h5><?= htmlspecialchars($client['birth_place'] ?? '') ?></h5>
        </div>
      </div>

      <!-- Referral / Case -->
      <div class="row mt-3">
        <div class="col-2">
          <small class="text-muted">Referral Type</small>
          <h5><?= htmlspecialchars($client['referral_type'] ?? '') ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">Cause Number</small>
          <h5><?= htmlspecialchars($client['cause_number'] ?? '') ?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">Case Manager</small>
          <h5><?= htmlspecialchars($client['case_manager'] ?? '') ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">Parole Office</small>
          <h5><?= htmlspecialchars($client['po_office'] ?? '') ?></h5>
        </div>
      </div>

      

      
    

      <!-- Marital / Religion / Employment / Employer -->
      <div class="row mt-3">
        <div class="col-3">
          <small class="text-muted">Marital Status</small>
          <h5><?= htmlspecialchars($client['marital_status'] ?? '') ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">Religion</small>
          <h5><?= htmlspecialchars($client['religion'] ?? '') ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">Employment</small>
          <h5><?= htmlspecialchars($client['employment'] ?? '') ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">Employer</small>
          <h5><?= htmlspecialchars($client['employer'] ?? '') ?></h5>
        </div>
      </div>

      

      <!-- Emergency Contact -->
      <div class="row mt-3">
        <div class="col">
          <small class="text-muted">Emergency Contact</small>
          <h5><?= htmlspecialchars($client['emergency_contact'] ?? '') ?></h5>
        </div>
      </div>

      <!-- Client Notes / Other Concerns -->
      <div class="row mt-2">
        <div class="col">
          <small class="text-muted">Client Notes</small>
          <h5><?= htmlspecialchars($client['client_note'] ?? '') ?></h5>
        </div>
      </div>
      <div class="row">
        <div class="col">
          <small class="text-muted">Other Concerns</small>
          <h5><?= htmlspecialchars($client['other_concerns'] ?? '') ?></h5>
        </div>
      </div>

      <!-- Conduct + Attendance (side by side) -->
      <div class="row mt-2 align-items-start">
        <div class="col-6">
            <small class="text-muted">Conduct</small>
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

        <!-- Right-aligned calendar with ~50px padding to match image edge -->
        <div class="col-6 text-left" style="padding-right:50px;">
            <small class="text-muted d-block">Attendance History</small>
            <div class="d-inline-block">
            <?php
                try { $start = new DateTime($client["orientation_date"] ?? 'today'); }
                catch (Throwable $t) { $start = new DateTime('today'); }

                try {
                $exit = !empty($client["exit_date"]) ? new DateTime($client["exit_date"]) : new DateTime('today');
                } catch (Throwable $t) {
                $exit = new DateTime('today');
                }

                $date = clone $start;
                buildCalendar($date->getTimestamp(), $attendance, $excused, $unexcused);
                $date->modify('first day of next month');

                while ($date <= $exit) {
                buildCalendar($date->getTimestamp(), $attendance, $excused, $unexcused);
                $date->modify('first day of next month');
                }
            ?>
            </div>
        </div>
      </div>


      


      

      <!-- Contract / Intake Packet -->
      <div class="row mt-2">
        <div class="col-2">
          <small class="text-muted">Behavior Contract Status</small>
          <?php
            $contract_status = $client["behavior_contract_status"] ?? "Not Needed";
            $color_class = "text-muted";
            if ($contract_status === "Signed")      $color_class = "text-success";
            elseif ($contract_status === "Needed")  $color_class = "text-danger";
          ?>
          <h5 class="<?= $color_class; ?>"><?= htmlspecialchars($contract_status); ?></h5>
        </div>
        <div class="col-1">
          <small class="text-muted">Signed Date</small>
          <h5><?= htmlspecialchars($client["behavior_contract_signed_date"] ?? "N/A"); ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">Received Intake Packet</small>
          <?php $gotPacket = intval($client['intake_packet'] ?? 0); ?>
          <h5 class="<?= $gotPacket ? 'text-success' : 'text-danger' ?>"><?= $gotPacket ? 'Yes' : 'No' ?></h5>
        </div>
      </div>
    </div>
  </div>
</div>
