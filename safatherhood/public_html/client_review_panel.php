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

    /**
     * Intake Packet lookup (for “Intake Packet Received” + link):
     * 1) Primary: intake_packet.imported_client_id = client_id
     * 2) Fallback: exact match FN+LN+DOB (latest by created_at / intake_id)
     */
    $dbx = isset($link) ? $link : (isset($con) ? $con : null);
    $intakeId = null;

    if ($dbx && !empty($client_id)) {
        // 1) Authoritative mapping
        if ($stmt = $dbx->prepare(
            "SELECT intake_id
               FROM intake_packet
              WHERE imported_client_id = ?
              ORDER BY created_at DESC, intake_id DESC
              LIMIT 1"
        )) {
            $cid = (int)$client_id;
            $stmt->bind_param('i', $cid);
            if ($stmt->execute() && ($res = $stmt->get_result())) {
                if ($row = $res->fetch_assoc()) {
                    $intakeId = (int)$row['intake_id'];
                }
            }
            $stmt->close();
        }

        // 2) Fallback: exact FN+LN+DOB (if not linked)
        if (!$intakeId) {
            $fn  = trim((string)($client['first_name'] ?? ''));
            $ln  = trim((string)($client['last_name'] ?? ''));
            $dob = trim((string)($client['date_of_birth'] ?? ''));
            if ($fn !== '' && $ln !== '' && $dob !== '' && $dob !== '0000-00-00') {
                if ($stmt = $dbx->prepare(
                    "SELECT intake_id
                       FROM intake_packet
                      WHERE UPPER(TRIM(first_name)) = UPPER(TRIM(?))
                        AND UPPER(TRIM(last_name))  = UPPER(TRIM(?))
                        AND date_of_birth = ?
                      ORDER BY created_at DESC, intake_id DESC
                      LIMIT 1"
                )) {
                    $stmt->bind_param('sss', $fn, $ln, $dob);
                    if ($stmt->execute() && ($res = $stmt->get_result())) {
                        if ($row = $res->fetch_assoc()) {
                            $intakeId = (int)$row['intake_id'];
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }

    // If the client table has a boolean intake_packet column, honor it as an additional signal
    $hasPacketBool = !empty($client['intake_packet']) ? (int)$client['intake_packet'] : 0;
    $hasPacket = (bool)($intakeId ?: $hasPacketBool);
    $intakeLink = $intakeId ? ("intake-review.php?id=" . (int)$intakeId) : '';

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

        switch($day_of_week){ 
            case "Sun": $blank = 0; break; 
            case "Mon": $blank = 1; break; 
            case "Tue": $blank = 2; break; 
            case "Wed": $blank = 3; break; 
            case "Thu": $blank = 4; break; 
            case "Fri": $blank = 5; break; 
            case "Sat": $blank = 6; break; 
        }

        $days_in_month = cal_days_in_month(0, $month, $year) ; 

        echo "<table border=1 width=392>";
        echo "<tr><th colspan=7> $title $year </th></tr>";
        echo "<tr><td width=56>S</td><td width=56>M</td><td 
            width=56>T</td><td width=56>W</td><td width=56>R</td><td 
            width=56>F</td><td width=56>S</td></tr>";

        $day_count = 1;

        echo "<tr>";
        while ( $blank > 0 ) 
        { 
            echo "<td></td>"; 
            $blank = $blank-1; 
            $day_count++;
        }

        $day_num = 1;

        while ( $day_num <= $days_in_month ) 
        {   
            echo "<td>";
            if($curDay == $today) {
                echo "<b>" . $day_num . "</b>";
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
                echo "&#x2705";
            }

            $count = arrayCount($curDay->format("Y-m-d"), $excused);
            for($i=0; $i<$count; $i++) {
                echo "&#x2716";
            }

            $count = arrayCount($curDay->format("Y-m-d"), $unexcused);
            for($i=0; $i<$count; $i++) {
                echo "&#x274C";
            }

            echo "</td>"; 

            $day_num++; 
            $day_count++;
            $curDay->modify('+ 1 day');

            if ($day_count > 7)
            {
                echo "</tr>";
                $day_count = 1;
            }
        }

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
                <div class="col-1">
                    <h5><a class="nav-link" href="./client-update.php?id=<?php echo htmlspecialchars($client_id); ?>">Edit Client</a></h5>
                </div>                    
                <div class="col-1">
                    <h5><a class="nav-link" href="./client-attendance.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Attendance</a></h5>
                </div>                    
                <div class="col-1">
                    <h5><a class="nav-link" href="./client-ledger.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Payments</a></h5>
                </div>                    
                <div class="col-1">
                    <h5><a class="nav-link" href="./client-event.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Event History</a></h5>
                </div>
                <!-- Victim Info link -->
                <div class="col-1">
                    <h5><a class="nav-link" href="./client-victim.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Victim&nbsp;Info</a></h5>
                </div>                   
            </div>
        </div>
    </div>

    <div class="row bg-light">
        <div class="col-5 pt-3 pb-3"> <!-- Client demographic info -->
            <div class="row">
                <div class="col-3">
                    <small class="text-muted">First Name</small>
                    <h5><?php echo htmlspecialchars($client["first_name"]); ?></h5>
                </div>                    
                <div class="col-3">
                    <small class="text-muted">Last Name</small>
                    <h5><?php echo htmlspecialchars($client["last_name"]); ?></h5>
                </div>
                <div class="col-3">
                    <small class="text-muted">DOB</small>
                    <h5><?php echo htmlspecialchars($client["date_of_birth"]) . " (". htmlspecialchars($client["age"]) . ")"; ?></h5>
                </div>
                <div class="col-3">
                    <small class="text-muted">Gender</small>
                    <h5><?php echo htmlspecialchars($client["gender"]); ?></h5>
                </div>
            </div>

            <div class="row">
                <div class="col-3">
                    <small class="text-muted">Phone</small>
                    <h5><?php echo htmlspecialchars($client["phone_number"]); ?></h5>
                </div>
                <div class="col-4">
                    <small class="text-muted">E-Mail</small>
                    <h5><?php echo htmlspecialchars($client["email"]); ?></h5>
                </div>
                
            </div>

            <div class="row">
                <div class="col-2">
                    <small class="text-muted">Referral Type</small>
                    <h5><?php echo htmlspecialchars($client["referral_type"]); ?></h5>
                </div>
                <div class="col-3">
                    <small class="text-muted">Cause Number</small>
                    <h5><?php echo htmlspecialchars($client["cause_number"]); ?></h5>
                </div>
                <div class="col-4">
                    <small class="text-muted">Case Manager</small>
                    <h5><?php echo htmlspecialchars($client["case_manager"]); ?></h5>
                </div>
                <div class="col-3">
                    <small class="text-muted">Parole Office</small>
                    <h5><?php echo htmlspecialchars($client["po_office"]); ?></h5>
                </div>
            </div>

            <div class="row">
                <div class="col">
                    <small class="text-muted">Emergency Contact</small>
                    <h5><?php echo htmlspecialchars($client["emergency_contact"]); ?></h5>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <small class="text-muted">Client Notes</small>
                    <h5><?php echo htmlspecialchars($client["client_note"]); ?></h5>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <small class="text-muted">Other Concerns</small>
                    <h5><?php echo htmlspecialchars($client["other_concerns"]); ?></h5>
                </div>
            </div>

            <div class="row">
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
            </div>

            <div class="row">
                <div class="col-4">
                    <small class="text-muted">Behavior Contract Status</small>
                    <?php
                        $contract_status = $client["behavior_contract_status"] ?? "Not Needed";
                        $color_class = "text-muted";
                        if ($contract_status === "Signed") {
                            $color_class = "text-success";
                        } elseif ($contract_status === "Needed") {
                            $color_class = "text-danger";
                        }
                    ?>
                    <h5 class="<?= $color_class; ?>">
                        <?= htmlspecialchars($contract_status); ?>
                    </h5>
                </div>
                <div class="col-4">
                    <small class="text-muted">Signed Date</small>
                    <h5>
                        <?= htmlspecialchars($client["behavior_contract_signed_date"] ?? "N/A"); ?>
                    </h5>
                </div>
                <div class="col-4">
                    <small class="text-muted">&nbsp;</small><br>
                    <a class="btn btn-primary" 
                       href="client-contract-upload.php?client_id=<?= urlencode($client_id); ?>">
                        Upload Contract
                    </a>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-8">
                    <small class="text-muted">Intake Packet</small>
                    <h5>
                        <?php if (!empty($intakeLink)): ?>
                            <a href="<?php echo htmlspecialchars($intakeLink, ENT_QUOTES, 'UTF-8'); ?>">Yes</a>
                        <?php else: ?>
                            <?php echo !empty($hasPacket) ? 'Yes' : 'No'; ?>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="col-4 d-flex align-items-end">
                    <!-- keeps the Upload Contract button nearby visually; nothing needed here -->
                </div>
            </div>


        </div>

        <div class="col-5 pt-3 pb-3">  <!-- Attendance column -->
            <div class="row">
                <div class="col">
                    <small class="text-muted">Regular Group</small>
                    <h5><?php echo htmlspecialchars( $client["group_name"] ); ?></h5>
                </div>
                <div class="col-3">
                    <small class="text-muted">Sessions Attended</small>
                    <h5><?php echo htmlspecialchars( $client["sessions_attended"] . " of " . $client["sessions_required"]); ?></h5>
                </div>
                <div class="col-2">
                    <small class="text-muted">Fee</small>
                    <h5><?php echo htmlspecialchars( "$" . $client["fee"] ); ?></h5>
                </div>
                <div class="col-2">
                    <small class="text-muted">Balance</small>
                    <?php 
                        if($client["balance"] < 0) {
                            echo "<h5 class='text-danger'>";
                        }
                        else {
                            echo "<h5 class='text-success'>";
                        }
                        echo htmlspecialchars("$" . $client["balance"]) . "</h5>";
                    ?>
                </div>
            </div>

            <div class="row">
                <div class="col-3">
                    <small class="text-muted">Orientation Date</small>
                    <h5><?php echo htmlspecialchars( $client["orientation_date"] ); ?></h5>
                </div>
                <div class="col-2">
                    <small class="text-muted">Exit Date</small>
                    <h5><?php echo htmlspecialchars( $client["exit_date"] ); ?></h5>
                </div>
                <div class="col">
                    <small class="text-muted">Exit Reason</small>
                    <h5><?php echo htmlspecialchars( $client["exit_reason"] ); ?></h5>
                </div>
                <div class="col-3">
                    <small class="text-muted">Place of Birth</small>
                    <h5><?php echo htmlspecialchars($client["birth_place"] ?? ""); ?></h5>
                </div>
            </div>

            <div class="row">
                <div class="col">
                    <small class="text-muted">Attendance History</small>
                </div>
            </div>

            <div class="row">
                <div class="col">
                <?php
                    $exit = new DateTime($client["exit_date"]);  // will default to day if not exited

                    // use orientation date to pick first Month
                    $date = new DateTime($client["orientation_date"]);  
                    buildCalendar($date->getTimestamp(), $attendance, $excused, $unexcused);
                    $date->modify( 'first day of next month' );

                    while ($date <= $exit) {
                        buildCalendar($date->getTimestamp(), $attendance, $excused, $unexcused);
                        $date->modify( 'first day of next month' );
                    }
                ?>
                </div>
            </div>
        </div>

        <div class="col-2 pt-3 pb-3">  <!-- Image column -->
            <div class="row">
                <img src="getImage.php?id=<?=$client_id;?>" class="img-thumbnail" alt="client picture" onerror="this.onerror=null; this.src='img/male-placeholder.jpg'">
            </div>
            <div class="row justify-content-center">
                <a class="nav-link" target="_blank" href="./client-image-upload.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Update Image</a>
            </div>
        </div>
    </div>
</div>
