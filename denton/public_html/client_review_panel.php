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
                        <div class="col-1">
                            <h5><a class="nav-link" href="./client-update.php?id=<?php echo htmlspecialchars($client_id); ?>">Edit Client</a></h5>
                        </div>                    
                        <div class="col-1">
                            <h5><a class="nav-link" href="./client-attendance.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Attendance</a></h5>
                        </div>                    
                        <div class="col-1">
                            <h5><a class="nav-link" href="./client-ledger.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Payments</a></h5>
                        </div>                    
                        <div class="col">
                            <h5><a class="nav-link" href="./client-event.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Event History</a></h5>
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
