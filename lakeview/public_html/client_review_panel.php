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
        // whitelist identifiers
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $col)) return false;
        // cannot bind identifiers in SHOW COLUMNS; use escaped literal
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
        // whitelist identifiers
        foreach ([$table,$id_col] as $id) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $id)) return (string)$id_val;
        }
        $label_col = pick_label_column($con, $table, $candidates);
        if (!$label_col) return (string)$id_val;

        // build SELECT with validated identifiers, bind only the value
        $sql = "SELECT `$label_col` AS label FROM `$table` WHERE `$id_col`=?";
        $row = sql_select_one($con, $sql, [$id_val]);
        return $row['label'] ?? (string)$id_val;
    }


    /* ---- helpers end ---- */

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

    // fetch program flags
    $prog = sql_select_one($con, "SELECT p.id AS program_id, p.uses_milestones
                                    FROM client c JOIN program p ON p.id=c.program_id
                                WHERE c.id=?", [$client_id]);

    $program_id = intval($prog['program_id'] ?? 0);
    $uses_milestones = intval($prog['uses_milestones'] ?? 0);

    // --- after $program_id / $uses_milestones are set ---

    $client_stage_name = '';
    if (!empty($client['client_stage_id'])) {
        $client_stage_name = fetch_label_by_id($con, 'client_stage', 'id', $client['client_stage_id'], ['name','stage','label','title']);
    }

    $therapy_group_name = '';
    if (!empty($client['therapy_group_id'])) {
        $therapy_group_name = fetch_label_by_id($con, 'therapy_group', 'id', $client['therapy_group_id'], ['name','group_name','title']);
    }




    // curriculum for this program
    $curriculum = [];
    $progress = [];
    if ($uses_milestones) {
        $curriculum = sql_select_all($con, "SELECT *
                                    FROM curriculum
                                    WHERE program_id=?
                                    ORDER BY id ASC", [$program_id]);

        // completed milestones
        $progress_rows = sql_select_all(
            $con,
            "SELECT curriculum_id AS cid
                FROM client_milestone
                WHERE client_id=?",
            [$client_id]
        );
        foreach ($progress_rows as $r) {
            $cid = intval($r['cid'] ?? 0);
            if ($cid > 0) { $progress[$cid] = true; }
        }

        // pull completion timestamps
        $done_rows = sql_select_all(
            $con,
            "SELECT curriculum_id AS cid, DATE_FORMAT(completed_at,'%Y-%m-%d %H:%i') AS completed_at
            FROM client_milestone
            WHERE client_id=?",
            [$client_id]
        );
        $done = [];
        foreach ($done_rows as $r) { $done[(int)$r['cid']] = $r['completed_at']; }


    }


    $attendance = get_client_attendance_days(trim($client_id));
    $temp = get_client_absence_days(trim($client_id));
    $excused = $temp[0];
    $unexcused = $temp[1];

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
            if ($curDay->format('Y-m-d') === $today->format('Y-m-d')) {
                echo "<b>{$day_num}</b>";
            } else {
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
        $raw = $client[$varName] ?? 0; // tinyint(1)
        $val = ($raw === 1 || $raw === '1' || $raw === true || $raw === 'true') ? 'true' : 'false';
        $good = ($goodValue === 'true');
        $isGood = (($val === 'true') === $good);
        $cls = $isGood ? 'text-success' : 'text-danger';
        echo "<h5 class='{$cls}'>" . htmlspecialchars($description) . ": " . htmlspecialchars($val) . "</h5>";
    }


?>


<div class="container-fluid">
  <div class="row">
    <div class="col">
      <div class="row">
        <div class="col-2">
          <h2>Client Information</h2>
        </div>
        <div class="col-1">
          <h5><a class="nav-link" href="./client-update.php?id=<?= htmlspecialchars($client_id); ?>">Edit Client</a></h5>
        </div>
        <div class="col-1">
          <h5><a class="nav-link" href="./client-attendance.php?client_id=<?= htmlspecialchars($client_id); ?>">Attendance</a></h5>
        </div>
        <div class="col-1">
          <h5><a class="nav-link" href="./client-ledger.php?client_id=<?= htmlspecialchars($client_id); ?>">Payments</a></h5>
        </div>
        <div class="col-1">
          <h5><a class="nav-link" href="./client-event.php?client_id=<?= htmlspecialchars($client_id); ?>">Event History</a></h5>
        </div>
        <div class="col-1">
          <h5><a class="nav-link" href="./client-victim.php?client_id=<?= htmlspecialchars($client_id); ?>">Victim Info</a></h5>
        </div>
      </div>
    </div>
  </div>

  <div class="row bg-light">
    <!-- TOP ROW: demographics full width + image -->
    <div class="col-10 pt-3 pb-3">
      <!-- Demographics -->
      <div class="row">
        <div class="col-3">
          <small class="text-muted">First Name</small>
          <h5><?= htmlspecialchars($client["first_name"]); ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">Last Name</small>
          <h5><?= htmlspecialchars($client["last_name"]); ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">DOB</small>
          <h5><?= htmlspecialchars($client["date_of_birth"]) . " (" . htmlspecialchars($client["age"]) . ")"; ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">Gender</small>
          <h5><?= htmlspecialchars($client["gender"]); ?></h5>
        </div>
      </div>

      <div class="row">
        <div class="col-3">
          <small class="text-muted">Phone</small>
          <h5><?= htmlspecialchars($client["phone_number"]); ?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">E-Mail</small>
          <h5><?= htmlspecialchars($client["email"]); ?></h5>
        </div>
      </div>

      <div class="row">
        <div class="col-2">
          <small class="text-muted">Referral Type</small>
          <h5><?= htmlspecialchars($client["referral_type"]); ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">Cause Number</small>
          <h5><?= htmlspecialchars($client["cause_number"]); ?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">Case Manager</small>
          <h5><?= htmlspecialchars($client["case_manager"]); ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">Parole Office</small>
          <h5><?= htmlspecialchars($client["po_office"]); ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">County</small>
          <h5><?= htmlspecialchars($client["county"] ?? ""); ?></h5>
        </div>
      </div>

      <div class="row">
        <div class="col-3">
            <small class="text-muted">Orientation Date</small>
            <h5><?= htmlspecialchars($client["orientation_date"] ?? ""); ?></h5>
        </div>
        <div class="col-3">
            <small class="text-muted">Enrollment Date</small>
            <h5><?= htmlspecialchars($client["enrollment_date"] ?? $client["enrollment_date_dt"] ?? ""); ?></h5>
        </div>
        <div class="col-3">
            <small class="text-muted">Exit Date</small>
            <h5><?= htmlspecialchars($client["exit_date"] ?? $client["exit_date_dt"] ?? ""); ?></h5>
        </div>
        <div class="col-3">
            <small class="text-muted">Exit Note</small>
            <h5><?= htmlspecialchars($client["exit_note"] ?? ""); ?></h5>
        </div>
        </div>


      <div class="row">
        <div class="col-3">
          <small class="text-muted">Instructor</small>
          <h5><?= htmlspecialchars($client["instructor"] ?? ""); ?></h5>
        </div>
        <div class="col-5">
          <small class="text-muted">Referral Email</small>
          <h5><?= htmlspecialchars($client["referral_email"] ?? ""); ?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">SID</small>
          <h5><?= htmlspecialchars($client["sid"] ?? ""); ?></h5>
        </div>
      </div>

      <div class="row">
        <div class="col-5">
          <small class="text-muted">Address</small>
          <h5><?= htmlspecialchars($client["address"] ?? ""); ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">City</small>
          <h5><?= htmlspecialchars($client["city"] ?? ""); ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">State/ZIP</small>
          <h5><?= htmlspecialchars($client["state_zip"] ?? ""); ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">DL/SSN</small>
          <h5><?= htmlspecialchars($client["ssl_dln"] ?? ""); ?></h5>
        </div>
      </div>

      <div class="row">
        <div class="col-3">
          <small class="text-muted">Marital Status</small>
          <h5><?= htmlspecialchars($client["marital_status"] ?? ""); ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">Employed</small>
          <h5><?= htmlspecialchars($client["employed"] ?? ""); ?></h5>
        </div>
        <div class="col-2">
          <small class="text-muted">UA Positive</small>
          <h5><?= htmlspecialchars($client["UA_positive"] ?? ""); ?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">Prescription Use</small>
          <h5><?= htmlspecialchars($client["prescription_use"] ?? ""); ?></h5>
        </div>
      </div>

      <!-- Program status and scores -->
      <div class="row">
        <div class="col-3">
            <small class="text-muted">Client Stage</small>
            <h5><?= htmlspecialchars($client_stage_name ?: ($client["client_stage_id"] ?? "")) ?></h5>
        </div>
        <div class="col-3">
            <small class="text-muted">Therapy Group</small>
            <h5><?= htmlspecialchars($therapy_group_name ?: ($client["therapy_group_id"] ?? "")) ?></h5>
        </div>
        <div class="col-3">
            <small class="text-muted">Ethnicity</small>
            <h5><?= htmlspecialchars($client["ethnicity"] ?? $client["ethnicity_id"] ?? "") ?></h5>
        </div>
        <div class="col-3">
            <small class="text-muted">Score</small>
            <h5><?= htmlspecialchars($client["score"] ?? "") ?></h5>
        </div>
        </div>

      <div class="row">
      <div class="col-3">
          <small class="text-muted">NDP Risk</small>
          <h5><?= htmlspecialchars($client["ndp_score_risk"] ?? ""); ?></h5>
      </div>
      <div class="col-3">
          <small class="text-muted">SII Risk</small>
          <h5><?= htmlspecialchars($client["sii_score_risk"] ?? ""); ?></h5>
      </div>
      <div class="col-3">
          <small class="text-muted">Pretest</small>
          <h5><?= htmlspecialchars($client["pretest"] ?? ""); ?></h5>
      </div>
      <div class="col-3">
          <small class="text-muted">Posttest</small>
          <h5><?= htmlspecialchars($client["posttest"] ?? ""); ?></h5>
      </div>
      </div>
      <div class="row">
      <div class="col-3">
          <small class="text-muted">Knowledge Increase</small>
          <h5><?= htmlspecialchars($client["knowledge_increase"] ?? ""); ?></h5>
      </div>
      </div>


      <div class="row">
        <div class="col-3">
          <small class="text-muted">Paid Amount</small>
          <h5><?= htmlspecialchars($client["paid_amount"] ?? ""); ?></h5>
        </div>
        <div class="col-3">
          <small class="text-muted">Paid Source</small>
          <h5><?= htmlspecialchars($client["paid_source"] ?? ""); ?></h5>
        </div>
        <div class="col-6">
          <small class="text-muted">Payment Note</small>
          <h5><?= htmlspecialchars($client["paid_note"] ?? ""); ?></h5>
        </div>
      </div>

      <div class="row">
        <div class="col">
          <small class="text-muted">Emergency Contact</small>
          <h5><?= htmlspecialchars($client["emergency_contact"]); ?></h5>
        </div>
      </div>

      <div class="row">
        <div class="col">
          <small class="text-muted">Client Notes</small>
          <h5><?= htmlspecialchars($client["client_note"] ?? $client["note"] ?? ""); ?></h5>
        </div>
      </div>

      <div class="row">
        <div class="col">
          <small class="text-muted">Other Concerns</small>
          <h5><?= htmlspecialchars($client["other_concerns"]); ?></h5>
        </div>
      </div>

      
    </div>

    <div class="col-2 pt-3 pb-3">
      <!-- Image -->
      <div class="row">
        <img src="getImage.php?id=<?= $client_id; ?>" class="img-thumbnail" alt="client picture"
             onerror="this.onerror=null; this.src='img/male-placeholder.jpg'">
      </div>
      <div class="row justify-content-center">
        <a class="nav-link" target="_blank"
           href="./client-image-upload.php?client_id=<?= htmlspecialchars($client_id); ?>">Update Image</a>
      </div>
    </div>

    <!-- BOTTOM ROW: conduct + milestones/attendance -->
    <div class="col-6 pt-3 pb-3">
      <div class="row">
        <div class="col-12">
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
      </div>

      <div class="row mt-3">
        <div class="col-4">
          <small class="text-muted">Behavior Contract Status</small>
          <?php
            $contract_status = $client["behavior_contract_status"] ?? "Not Needed";
            $color_class = "text-muted";
            if ($contract_status === "Signed") { $color_class = "text-success"; }
            elseif ($contract_status === "Needed") { $color_class = "text-danger"; }
          ?>
          <h5 class="<?= $color_class; ?>"><?= htmlspecialchars($contract_status); ?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">Signed Date</small>
          <h5><?= htmlspecialchars($client["behavior_contract_signed_date"] ?? "N/A"); ?></h5>
        </div>
        <div class="col-4">
          <small class="text-muted">Received Intake Packet</small>
          <?php $gotPacket = intval($client['intake_packet'] ?? 0); ?>
          <?php if ($gotPacket): ?>
            <h5 class="text-success">Yes</h5>
          <?php else: ?>
            <h5 class="text-danger">No</h5>
          <?php endif; ?>
        </div>

        <!-- Reentry details -->
        <div class="row mt-3">
        <div class="col-4">
            <small class="text-muted">Reentry</small>
            <h5><?= htmlspecialchars($client["reentry"] ?? ""); ?></h5>
        </div>
        <div class="col-8">
            <small class="text-muted">Reentry Plan</small>
            <h5><?= htmlspecialchars($client["reentry_plan"] ?? ""); ?></h5>
        </div>
        </div>

      </div>
    </div>

    <div class="col-6 pt-3 pb-3">
      <?php if ($uses_milestones): ?>
        <?php
          
        ?>
        <h5>Milestones</h5>
        <div class="alert alert-warning py-2 mb-2" role="alert" style="font-size:0.95rem;">
          Check only when applicable. Changes save immediately. Each check stores a timestamp.
        </div>
        <?php
        // --- Milestone grouping map by program_id ---
        $MILESTONE_GROUPING = [
        4 => [4,4],   // Parenting Education
        6 => [4,4],   // Life Skills/Anti Theft
        1 => 5,       // DOEP: groups of 5  (change if needed)
        // 3 => [5,5,5], // Example: hybrid DWII 3 sets of 5
        ];

        function group_curriculum_for_program(array $curriculum, int $program_id, array $map): array {
            if (!$curriculum) return [];
            $plan = $map[$program_id] ?? 4;
            $groups = [];
            $idx = 0;
            if (is_int($plan)) {
                $chunk = max(1, $plan);
                $day = 1;
                while ($idx < count($curriculum)) {
                    $groups[$day] = array_slice($curriculum, $idx, $chunk);
                    $idx += $chunk; $day++;
                }
            } else {
                $sizes = array_values(array_filter($plan, fn($n)=>intval($n)>0));
                if (!$sizes) $sizes = [4];
                $day = 1; $si = 0;
                while ($idx < count($curriculum)) {
                    $take = intval($sizes[min($si, count($sizes)-1)]);
                    $groups[$day] = array_slice($curriculum, $idx, $take);
                    $idx += $take; $day++; $si++;
                }
            }
            return $groups;
        }

        // Expected DOWs by program when weekly attends_* flags are not used
        // 0=Sun ... 6=Sat
        $EXPECTED_DOWS = [
        4 => [6,0], // Parenting: Sat+Sun
        6 => [6,0], // Life Skills/Anti Theft: Sat+Sun
        1 => [5],   // DOEP example: Sat only (adjust if different)
        // 3 => [2,4,6], // Example mapping if needed for other programs
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

        // build grouped list
        $groups = group_curriculum_for_program($curriculum, $program_id, $MILESTONE_GROUPING);
        ?>

        <!-- optional back/forward dating of the milestone day -->
        <?php
        $minDate = !empty($client['orientation_date'])
            ? (new DateTime($client['orientation_date']))->format('Y-m-d')
            : '';
        $maxDate = date('Y-m-d');
        ?>
        <div class="mb-2">
        <label for="milestone_session_date" class="small text-muted">Session date</label>
        <input type="date" id="milestone_session_date" class="form-control form-control-sm"
                value="<?= $maxDate ?>"
                <?= $minDate ? 'min="'.htmlspecialchars($minDate).'"' : '' ?>
                max="<?= $maxDate ?>" style="max-width:180px;">
        </div>

        <?php

        // compute per-day completion
        $dayComplete = []; // [dayNum => bool]
        $daySize     = []; // [dayNum => int]
        foreach ($groups as $dayNum => $items) {
            $daySize[$dayNum] = count($items);
            $cnt = 0;
            foreach ($items as $it) {
                $cid = (int)$it['id'];
                if (!empty($progress[$cid])) $cnt++;
            }
            $dayComplete[$dayNum] = ($cnt >= $daySize[$dayNum] && $daySize[$dayNum] > 0);
        }
        ?>


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
                    <input class="form-check-input milestone-box" type="checkbox"
                        id="milestone_<?= $cid ?>" data-curriculum-id="<?= $cid ?>" <?= $checked ?>>
                    <label class="form-check-label" for="milestone_<?= $cid ?>">
                    <?= htmlspecialchars($label) ?>
                    </label>
                </div>
                <small class="ms-3 text-muted" id="milestone_date_<?= $cid ?>">
                    <?= $dateTxt ? htmlspecialchars($dateTxt) : '' ?>
                </small>
                </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <div id="milestone-status" class="small text-muted mt-1" aria-live="polite"></div>

        <?php

        // last PRESENT
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

        // find gaps since last_attended up to yesterday for expected days
        $gaps = [];
        $start = $last_present ? new DateTime($last_attended) : new DateTime($client['orientation_date']);
        $start->modify('+1 day');
        $yday = new DateTime('yesterday');

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
            $fallback = $EXPECTED_DOWS[$program_id] ?? [];
            $flags = dows_to_flags($fallback);
        }

        for ($d = clone $start; $d <= $yday; $d->modify('+1 day')) {
            $dow = strtolower($d->format('l'));
            if (!$flags[$dow]) continue;
            $exists = sql_select_one(
                $con,
                "SELECT 1
                    FROM attendance_record ar
                    JOIN therapy_session ts ON ts.id = ar.therapy_session_id
                    WHERE ar.client_id=? AND CAST(ts.`date` AS DATE)=?
                    LIMIT 1",
                [$client_id, $d->format('Y-m-d')]
            );



            if (!$exists) $gaps[] = $d->format('Y-m-d');
        }
        $next_expected = next_expected_from_flags($flags);
        ?>
        <div class="mt-4">
        <h6>Missed</h6>
        <table class="table table-sm mb-2" style="max-width:520px;" id="missed-box">
            <tbody>
                <tr><th class="w-25">Last attended</th><td id="last_attended"><?= htmlspecialchars($last_attended) ?></td></tr>
                <tr><th>Next expected</th><td id="next_expected"><?= htmlspecialchars($next_expected ?? '—') ?></td></tr>
                <tr><th>Unrecorded no-shows</th><td id="unrec_noshow"></td></tr>

            </tbody>
        </table>

        <div class="text-muted small">No-shows appear when an expected day has no attendance record.</div>
        </div>


        <script>
        document.addEventListener('change', async (e) => {
            const el = e.target;
            if (!el.classList.contains('milestone-box')) return;
            el.disabled = true;
            const cid = el.getAttribute('data-curriculum-id');
            const checked = el.checked ? 1 : 0;
            const status = document.getElementById('milestone-status');
            const dateEl = document.getElementById('milestone_session_date');
            const session_date = dateEl ? dateEl.value : '';
            const stampEl = document.getElementById('milestone_date_' + cid);
            try {
                status.textContent = 'Saving...';
                const resp = await fetch('milestone_toggle.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({
                    client_id: '<?= (int)$client_id ?>',
                    curriculum_id: cid,
                    complete: checked,
                    session_date: session_date
                })
                });
                const data = await resp.json();
                if (!resp.ok || data.ok !== true) throw new Error(data.error || 'Save failed');
                stampEl.textContent = data.completed_at || '';
                status.textContent = 'Saved';
                await refreshMissed();
            } catch (err) {
                status.textContent = 'Error saving';
                el.checked = !checked;
            } finally {
                setTimeout(()=>{ status.textContent=''; }, 1200);
                el.disabled = false;
            }
        });
        </script>



      <?php else: ?>
        <!-- Weekly programs: original attendance column -->
        <div class="row">
          <div class="col">
            <small class="text-muted">Regular Group</small>
            <h5><?= htmlspecialchars($client["group_name"]); ?></h5>
          </div>
          <div class="col-3">
            <small class="text-muted">Sessions Attended</small>
            <h5><?= htmlspecialchars($client["sessions_attended"] . " of " . $client["sessions_required"]); ?></h5>
          </div>
          <div class="col-2">
            <small class="text-muted">Fee</small>
            <h5><?= htmlspecialchars("$" . $client["fee"]); ?></h5>
          </div>
          <div class="col-2">
            <small class="text-muted">Balance</small>
            <?= ($client["balance"] < 0) ? "<h5 class='text-danger'>" : "<h5 class='text-success'>"; ?>
            <?= htmlspecialchars("$" . $client["balance"]) ?></h5>
          </div>
        </div>

        <div class="row">
          <div class="col-3">
            <small class="text-muted">Orientation Date</small>
            <h5><?= htmlspecialchars($client["orientation_date"]); ?></h5>
          </div>
          <div class="col-2">
            <small class="text-muted">Exit Date</small>
            <h5><?= htmlspecialchars($client["exit_date"]); ?></h5>
          </div>
          <div class="col">
            <small class="text-muted">Exit Reason</small>
            <h5><?= htmlspecialchars($client["exit_reason"]); ?></h5>
          </div>
          <div class="col-3">
            <small class="text-muted">Place of Birth</small>
            <h5><?= htmlspecialchars($client["birth_place"] ?? ""); ?></h5>
          </div>
        </div>

        <div class="row"><div class="col">
          <small class="text-muted">Attendance History</small>
        </div></div>

        <div class="row"><div class="col">
          <?php
            $exit = new DateTime($client["exit_date"]);
            $date = new DateTime($client["orientation_date"]);
            buildCalendar($date->getTimestamp(), $attendance, $excused, $unexcused);
            $date->modify('first day of next month');
            while ($date <= $exit) {
              buildCalendar($date->getTimestamp(), $attendance, $excused, $unexcused);
              $date->modify('first day of next month');
            }
          ?>
        </div></div>
      <?php endif; ?>
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
</script>
