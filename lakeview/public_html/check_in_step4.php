<?php
include_once 'auth.php';
check_loggedin($con);
include "helpers.php";

require_once "../config/config.php";
require_once "sql_functions.php";

/* ---------------------------
   Local DB handle resolution
----------------------------*/
$dbh = isset($link) && $link instanceof mysqli ? $link : (isset($con) ? $con : null);
if (!$dbh) {
    http_response_code(500);
    exit('Database connection unavailable.');
}

$therapy_session_id = "";
if (isset($_GET['therapy_session_id'])) {
    $therapy_session_id = $_GET['therapy_session_id'];
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $therapy_session_id = trim($_POST["therapy_session_id"]);
}

$client_id = "";
if (isset($_GET['client_id'])) {
    $client_id = $_GET['client_id'];
}
if (isset($_POST['client_id'])) {
    $client_id = $_POST['client_id'];
}
$client = get_client_info(trim($client_id));
$paid_source = $client['paid_source'] ?? 'Weekly';
$paid_amount = isset($client['paid_amount']) ? (float)$client['paid_amount'] : 0.0;
$is_weekly   = ($paid_source === 'Weekly');
$paid_intake = (!$is_weekly && $paid_amount > 0);

if (!isset($client)) {
    header("location: error.php");
    exit();
}

/* --------------------------------------
   Program flags + milestone mini-queries
---------------------------------------*/
$program_flags = [
    'program_id' => null,
    'program_name' => '',
    'uses_weekly_attendance' => 1,
    'uses_milestones' => 0,
    'expected_sessions' => $client['sessions_required'] ?? null,
];
$total_milestones_any = 0;
$completed_milestones = 0;
$pending_milestones = []; // up to 3 to render

// Resolve program for this client and load flags
if ($stmt = $dbh->prepare("
    SELECT p.id AS program_id, p.name AS program_name,
           p.uses_weekly_attendance, p.uses_milestones, p.expected_sessions
    FROM client c
    JOIN program p ON p.id = c.program_id
    WHERE c.id = ?
    LIMIT 1
")) {
    $stmt->bind_param('i', $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $program_flags['program_id'] = (int)$row['program_id'];
        $program_flags['program_name'] = (string)$row['program_name'];
        $program_flags['uses_weekly_attendance'] = (int)$row['uses_weekly_attendance'];
        $program_flags['uses_milestones'] = (int)$row['uses_milestones'];
        $program_flags['expected_sessions'] = $row['expected_sessions'];
    }
    $stmt->close();
}

// Count curriculum rows regardless of flags
if ($program_flags['program_id']) {
    if ($stmt = $dbh->prepare("
        SELECT COUNT(*) AS cnt
        FROM curriculum
        WHERE program_id = ? AND COALESCE(is_hidden,0) = 0
    ")) {
        $stmt->bind_param('i', $program_flags['program_id']);
        $stmt->execute();
        $stmt->bind_result($total_milestones_any);
        $stmt->fetch();
        $stmt->close();
    }
}

$pid = (int)($program_flags['program_id'] ?? 0);
$uses_milestones = ((int)$program_flags['uses_milestones'] === 1);
$uses_weekly_att = ((int)$program_flags['uses_weekly_attendance'] === 1);

// If milestones, compute completed + fetch next 3 pending
if ($uses_milestones && $program_flags['program_id']) {
    if ($stmt = $dbh->prepare("
        SELECT COUNT(*) AS cnt
        FROM attendance_curriculum
        WHERE client_id = ? AND program_id = ?
    ")) {
        $stmt->bind_param('ii', $client_id, $program_flags['program_id']);
        $stmt->execute();
        $stmt->bind_result($completed_milestones);
        $stmt->fetch();
        $stmt->close();
    }


    if ($stmt = $dbh->prepare("
        SELECT cu.id,
               COALESCE(NULLIF(TRIM(cu.short_description),''), CONCAT('Milestone #', cu.id)) AS short_description
          FROM curriculum cu
          LEFT JOIN attendance_curriculum ac
                 ON ac.client_id = ?
                AND ac.program_id = ?
                AND ac.curriculum_id = cu.id
         WHERE cu.program_id = ?
           AND COALESCE(cu.is_hidden,0) = 0
           AND ac.client_id IS NULL
         ORDER BY (cu.sort_order IS NULL), cu.sort_order, cu.id
         LIMIT 3
    ")) {
        $stmt->bind_param('iii', $client_id, $program_flags['program_id'], $program_flags['program_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($r = $res->fetch_assoc())) $pending_milestones[] = $r;
        $stmt->close();
    }
}

$client_stage_id   = $client['client_stage_id'];
$therapy_group_id  = $client['therapy_group_id'];
$therapy_groups    = get_active_therapy_groups();

$attends_sunday    = $client['attends_sunday'];
$attends_monday    = $client['attends_monday'];
$attends_tuesday   = $client['attends_tuesday'];
$attends_wednesday = $client['attends_wednesday'];
$attends_thursday  = $client['attends_thursday'];
$attends_friday    = $client['attends_friday'];
$attends_saturday  = $client['attends_saturday'];
$intake_packet     = $client['intake_packet'];

$attendance = get_client_attendance_days(trim($client_id));
$temp = get_client_absence_days(trim($client_id));
$excused = $temp[0];
$unexcused = $temp[1];

$stages = get_client_stages();

/* -------------------------------------------------------
   Suggested Stage: milestone-aware percent calculation
--------------------------------------------------------*/
$percent_complete = 0.0;
if ($uses_milestones && $total_milestones_any > 0) {
    $percent_complete = $completed_milestones / $total_milestones_any;
} else {
    $req = (float)($client["sessions_required"] ?: 0);
    $att = (float)($client["sessions_attended"] ?: 0);
    $percent_complete = ($req > 0) ? ($att / $req) : 0.0;
}

if ($percent_complete < 0.20)      $calculated_stage = "Precontemplation";
else if ($percent_complete < 0.40) $calculated_stage = "Contemplation";
else if ($percent_complete < 0.60) $calculated_stage = "Preparation";
else if ($percent_complete < 0.80) $calculated_stage = "Action";
else                               $calculated_stage = "Maintenance";

$client_stage = '';
foreach ($stages as $stage) {
    if ($stage["id"] == $client_stage_id) $client_stage = $stage["stage"];
}

/* ---------------------------
   Legacy calendar renderer
----------------------------*/
function buildCalendar($date, $attendance, $excused, $unexcused)
{
    $month = date('m', $date);
    $year  = date('Y', $date);

    $first_day = mktime(0, 0, 0, $month, 1, $year);
    $curDay = new DateTime();
    $curDay->setTimestamp($first_day);

    $title = date('F', $first_day);
    $day_of_week = date('D', $first_day);

    switch ($day_of_week) {
        case "Sun": $blank = 0; break;
        case "Mon": $blank = 1; break;
        case "Tue": $blank = 2; break;
        case "Wed": $blank = 3; break;
        case "Thu": $blank = 4; break;
        case "Fri": $blank = 5; break;
        case "Sat": $blank = 6; break;
    }

    $days_in_month = cal_days_in_month(0, $month, $year);

    echo "<table class='table table-sm table-bordered text-center' style='width: 100%;'>";
    echo "<thead class='thead-light'><tr><th colspan='7' class='py-1'>$title $year</th></tr>";
    echo "<tr class='small'><th>S</th><th>M</th><th>T</th><th>W</th><th>R</th><th>F</th><th>S</th></tr></thead><tbody>";

    $day_count = 1;

    echo "<tr>";
    while ($blank > 0) {
        echo "<td class='py-1'></td>";
        $blank = $blank - 1;
        $day_count++;
    }

    $day_num = 1;

    while ($day_num <= $days_in_month) {
        echo "<td class='py-1'>";
        echo (int)$curDay->format('d');

        $dateKey = $curDay->format("Y-m-d");

        $count = arrayCount($dateKey, $attendance);
        for ($i = 0; $i < $count; $i++) echo " &#x2705;";

        $count = arrayCount($dateKey, $excused);
        for ($i = 0; $i < $count; $i++) echo " &#x2716;";

        $count = arrayCount($dateKey, $unexcused);
        for ($i = 0; $i < $count; $i++) echo " &#x274C;";

        echo "</td>";

        $day_num++;
        $day_count++;
        $curDay->modify('+ 1 day');

        if ($day_count > 7) {
            echo "</tr><tr>";
            $day_count = 1;
        }
    }

    while ($day_count > 1 && $day_count <= 7) {
        echo "<td class='py-1'></td>";
        $day_count++;
    }

    echo "</tr></tbody></table>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Check-In</title>
    <!-- FAVICONS -->
    <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">
    <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="apple-touch-icon" sizes="167x167" href="/favicons/apple-touch-icon-ipad-pro.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-touch-icon-ipad.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-touch-icon-120x120.png">
    <link rel="manifest" href="/favicons/site.webmanifest">
    <meta name="apple-mobile-web-app-title" content="NotesAO">
    <!-- Bootstrap 4.5 and Font Awesome -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .kv-label{font-size:.8rem;color:#6c757d}
        .kv-value{font-size:1rem}
        .chip{display:inline-block;padding:.15rem .45rem;border-radius:.25rem;background:#f1f3f5;font-size:.8rem}
        .tight .form-control{height:calc(1.5em + .5rem + 2px);padding:.25rem .5rem}
        .tight small{display:block;margin-bottom:.15rem}
        .stat h5{margin:.15rem 0}
        .muted-badge{background:#e9ecef;color:#495057}
        .w120{min-width:120px}
        .w140{min-width:140px}
    </style>
</head>
<body>
<?php require_once('navbar.php'); ?>

<section class="pt-3">
    <div class="container-fluid">
        <div class="row">
            <div class="col-2"><h4>Session Check In</h4></div>
        </div>
        <div class="row bg-light">
            <div class="col">
                <?php
                $sessionInfo = get_therapy_session_info($therapy_session_id);
                if (isset($sessionInfo)) {
                    $value = $sessionInfo['group_name'] . " - " . $sessionInfo['group_address'];
                    echo '<p class="h4 mb-1">' . htmlspecialchars($value) . '</p>';
                    $value = $sessionInfo['weekday'] . " " . $sessionInfo['date'] . " - " . $sessionInfo['facilitator'];
                    echo '<p class="h5 text-muted">' . htmlspecialchars($value) . '</p>';
                } else {
                    echo '<div class="alert alert-warning" role="alert"> Session not found
                    <a href="check_in_step1.php" class="btn btn-primary float-right">Return to Step 1</a>
                    </div>';
                    exit();
                }
                ?>
            </div>
        </div>
        <div class="row">
            <div class="col-6"></div>
            <div class="col"><h5><a class="nav-link" target="_blank" href="./client-review.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Client Review</a></h5></div>
            <div class="col"><h5><a class="nav-link" target="_blank" href="./client-update.php?id=<?php echo htmlspecialchars($client_id); ?>">Edit Client</a></h5></div>
            <div class="col"><h5><a class="nav-link" target="_blank" href="./client-attendance.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Attendance</a></h5></div>
            <div class="col"><h5><a class="nav-link" target="_blank" href="./client-ledger.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Payments</a></h5></div>
        </div>
    </div>

    <div class="container-fluid">
        <form action="check_in_step5.php" method="post" class="tight">
            <input type="hidden" id="therapy_session_id" name="therapy_session_id" value="<?php echo $therapy_session_id ?>" />
            <input type="hidden" id="client_id" name="client_id" value="<?php echo $client_id ?>" />

            <div class="row bg-light">
                <div class="col-8">
                    <!-- Row 1 -->
                    <div class="row">
                        <div class="col-2">
                            <small class="kv-label">First Name</small>
                            <div class="kv-value"><?php echo htmlspecialchars($client["first_name"]); ?></div>
                        </div>
                        <div class="col-2">
                            <small class="kv-label">Last Name</small>
                            <div class="kv-value"><?php echo htmlspecialchars($client["last_name"]); ?></div>
                        </div>
                        <div class="col-2">
                            <small class="kv-label">DOB</small>
                            <div class="kv-value"><?php echo htmlspecialchars($client["date_of_birth"]) . " (" . htmlspecialchars($client["age"]) . ")"; ?></div>
                        </div>
                    </div>

                    <!-- Row 2 -->
                    <div class="row mt-2">
                        <div class="col-2">
                            <small class="kv-label">Ref Type</small>
                            <div class="kv-value chip muted-badge"><?php echo htmlspecialchars($client["referral_type"]); ?></div>
                        </div>
                        <div class="col-auto">
                            <small class="kv-label">Case Manager - Office</small>
                            <select class="form-control form-control-sm" id="case_manager_id" name="case_manager_id">
                                <?php
                                $managers = get_case_managers();
                                foreach ($managers as $manager) {
                                    $value = htmlspecialchars($manager["last_name"] . ", " . $manager["first_name"]. " - " . $manager["office"]);
                                    $sel = ($manager["id"] == $client["case_manager_id"]) ? ' selected="selected"' : '';
                                    echo '<option value="'.$manager['id'].'"'.$sel.'>'.$value.'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-auto">
                            <small class="kv-label">Therapy Group</small>
                            <select class="form-control form-control-sm" id="therapy_group_id" name="therapy_group_id" required>
                                <?php foreach ($therapy_groups as $tg): ?>
                                    <option value="<?= $tg['id']; ?>" <?= $tg['id'] == $therapy_group_id ? 'selected' : ''; ?>><?= htmlspecialchars($tg['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Row 3 -->
                    <div class="row mt-2">
                        <div class="col-2">
                            <small class="kv-label">Phone</small>
                            <input type="text" name="phone_number" maxlength="45" class="form-control form-control-sm" value="<?php echo htmlspecialchars($client["phone_number"]); ?>">
                        </div>
                        <div class="col-3">
                            <small class="kv-label">E-Mail</small>
                            <input type="text" name="email" maxlength="64" class="form-control form-control-sm" value="<?php echo htmlspecialchars($client["email"]); ?>">
                        </div>
                        <div class="col-4">
                            <small class="kv-label">Emergency Contact</small>
                            <div class="kv-value"><?php echo htmlspecialchars($client["emergency_contact"]); ?></div>
                        </div>
                    </div>

                    <!-- Row 4: Finance -->
                    <div class="row mt-2">
                        <div class="col-1 stat">
                            <small class="kv-label">Balance</small>
                            <?php if ($client["balance"] < 0): ?>
                                <h5 class='text-danger mb-0'><?= htmlspecialchars("$".$client["balance"]) ?></h5>
                            <?php else: ?>
                                <h5 class='text-success mb-0'><?= htmlspecialchars("$".$client["balance"]) ?></h5>
                            <?php endif; ?>
                        </div>

                        <div class="col-auto">
                            <small class="kv-label">Payment Plan</small>
                            <div class="kv-value">
                                <?php if ($is_weekly): ?>
                                    <span class="badge badge-primary">Weekly</span>
                                <?php else: ?>
                                    <span class="badge badge-info">One-time: <?= htmlspecialchars($paid_source) ?></span>
                                    <?php if ($paid_intake): ?>
                                        <span class="badge badge-success">Paid at intake</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Not paid at intake</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-2">
                            <small class="kv-label">Pays</small>
                            <input name="fee"
                                   value="<?= htmlspecialchars($client["fee"]) ?>"
                                   type="number" placeholder="0.00" step="0.01" min="0" max="999"
                                   class="form-control form-control-sm w120"
                                   <?= $is_weekly ? '' : 'disabled' ?> />
                            <?php if (!$is_weekly): ?>
                                <input type="hidden" name="fee" value="<?= htmlspecialchars($client["fee"]) ?>">
                            <?php endif; ?>
                        </div>

                        <div class="col-2">
                            <small class="kv-label">Amount Collected @ Check In</small>
                            <input name="paid"
                                   value="<?= $is_weekly ? htmlspecialchars($client["fee"]) : '0.00' ?>"
                                   type="number" placeholder="0.00" step="0.01" min="0" max="999"
                                   class="form-control form-control-sm w140"
                                   <?= $paid_intake ? 'readonly' : '' ?> />
                            <?php if ($paid_intake): ?>
                                <small class="text-muted">Already paid at intake</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Row 5a: Progress counts -->
                    <div class="row mt-3">
                        <div class="col-2 stat">
                            <small class="kv-label">Attended</small>
                            <div class="kv-value"><strong><?php echo htmlspecialchars($client["sessions_attended"] . " of " . $client["sessions_required"]); ?></strong></div>
                        </div>
                        <div class="col-2 stat">
                            <small class="kv-label">Unexcused</small>
                            <div class="kv-value"><strong><?php echo htmlspecialchars($client["absence_unexcused"]); ?></strong></div>
                        </div>

                        <?php if ($uses_milestones): ?>
                        <div class="col-4">
                            <small class="kv-label d-block">Milestones</small>
                            <?php $mp = ($total_milestones_any > 0) ? (int)round(100 * $completed_milestones / $total_milestones_any) : 0; ?>
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar" role="progressbar" style="width: <?= $mp ?>%;" aria-valuenow="<?= $mp ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small class="text-muted"><?= htmlspecialchars($completed_milestones . " of " . $total_milestones_any) ?></small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Row 5b: Stage + packet -->
                    <div class="row mt-2">
                        <div class="col-3">
                            <small class="kv-label">Stage of Change</small>
                            <select class="form-control form-control-sm" id="client_stage_id" name="client_stage_id">
                                <?php
                                foreach ($stages as $stage) {
                                    $value = htmlspecialchars($stage["stage"]);
                                    $sel = ($stage["id"] == $client_stage_id) ? ' selected="selected"' : '';
                                    echo '<option value="'.$stage['id'].'"'.$sel.'>'.$value.'</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-3">
                            <small class="kv-label">Suggested Stage</small>
                            <?php
                                $match = (isset($client_stage) && strcmp($client_stage, $calculated_stage) === 0);
                                $cls = $match ? 'badge-success' : 'badge-danger';
                            ?>
                            <div class="kv-value"><span class="badge <?= $cls ?>"><?= htmlspecialchars($calculated_stage) ?></span></div>
                        </div>
                        <div class="col-3">
                            <small class="kv-label">Received Intake Packet</small>
                            <?php $gotPacket = intval($client['intake_packet'] ?? 0); ?>
                            <div class="kv-value">
                                <?php if ($gotPacket): ?>
                                    <span class="badge badge-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">No</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Row 6: Attendance day checkboxes (weekly only) -->
                    <?php if ($uses_weekly_att): ?>
                    <div class="row mt-3">
                        <div class="col">
                            <small class="text-muted">Attendance Day(s) — Select the days the client plans to attend</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col small">
                            <?php
                            $days = [
                                'sunday' => 'Sunday', 'monday' => 'Monday', 'tuesday' => 'Tuesday',
                                'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday'
                            ];
                            foreach ($days as $key => $label) {
                                $field = "attends_" . $key;
                                $checked = ($client[$field] == "1") ? ' checked' : '';
                                echo '<div class="form-check form-check-inline">';
                                echo '<input class="form-check-input" type="checkbox" name="'.$field.'"'.$checked.'>';
                                echo '<label class="form-check-label">'.$label.'</label>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Row 7: Conduct -->
                    <div class="row mt-3">
                        <div class="col"><small class="kv-label">Conduct</small></div>
                    </div>
                    <div class="row">
                        <div class="col small">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="speaksSignificantlyInGroup" <?php if ($client["speaksSignificantlyInGroup"] == "true") echo "checked"; ?>>
                                <label class="form-check-label">Excessive speaking</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="respectfulTowardsGroup" <?php if ($client["respectfulTowardsGroup"] == "true") echo "checked"; ?>>
                                <label class="form-check-label">Respectful</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="takesResponsibilityForPastBehavior" <?php if ($client["takesResponsibilityForPastBehavior"] == "true") echo "checked"; ?>>
                                <label class="form-check-label">Takes responsibility</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="disruptiveOrArgumentitive" <?php if ($client["disruptiveOrArgumentitive"] == "true") echo "checked"; ?>>
                                <label class="form-check-label">Disruptive</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="inappropriateHumor" <?php if ($client["inappropriateHumor"] == "true") echo "checked"; ?>>
                                <label class="form-check-label">Inappropriate humor</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="blamesVictim" <?php if ($client["blamesVictim"] == "true") echo "checked"; ?>>
                                <label class="form-check-label">Blames victim</label>
                            </div>
                            <!-- corrected keys -->
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="drug_alcohol" <?php if ($client["drug_alcohol"] == "true") echo "checked"; ?>>
                                <label class="form-check-label">Alcohol or drugs</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="inappropriate_behavior_to_staff" <?php if ($client["inappropriate_behavior_to_staff"] == "true") echo "checked"; ?>>
                                <label class="form-check-label">Inappropriate behavior</label>
                            </div>
                        </div>
                    </div>

                    <!-- Row 8: Notes -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <small class="kv-label">Client Notes</small>
                            <div class="kv-value"><?= htmlspecialchars($client["client_note"]) ?></div>
                        </div>
                    </div>
                    <div class="row mt-2 mb-3">
                        <div class="col-7">
                            <small class="kv-label">Other Concerns</small>
                            <textarea name="other_concerns" maxlength="2048" class="form-control form-control-sm"><?=
                                htmlspecialchars($client["other_concerns"]) ?></textarea>
                        </div>
                    </div>
                </div> <!-- End first Column -->

                <!-- Middle column: Milestones OR Calendar -->
                <div class="col-2">
                    <div class="row bg-light">
                        <?php if ($uses_milestones): ?>
                            <small class="kv-label d-block mb-1">Milestones (most relevant)</small>
                            <?php $mp = ($total_milestones_any > 0) ? (int)round(100 * $completed_milestones / $total_milestones_any) : 0; ?>
                            <div class="progress mb-1" style="height:10px;">
                                <div class="progress-bar" role="progressbar" style="width: <?= $mp ?>%;" aria-valuenow="<?= $mp ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small class="text-muted mb-2"><?= htmlspecialchars($completed_milestones . " of " . $total_milestones_any) ?></small>

                            <?php if (!empty($pending_milestones)): ?>
                                <div class="w-100">
                                    <?php foreach ($pending_milestones as $m): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   name="milestone_complete[]"
                                                   id="ms-<?php echo (int)$m['id']; ?>"
                                                   value="<?php echo (int)$m['id']; ?>">
                                            <label class="form-check-label small" for="ms-<?php echo (int)$m['id']; ?>">
                                                <?php echo htmlspecialchars($m['short_description']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                    <small class="text-muted">Checked items will be recorded on submit.</small>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($uses_weekly_att): ?>
                            <small class="kv-label d-block">Attendance History (most recent 2 months)</small>
                            <?php
                            $date = new DateTime(); // today
                            $date->modify('first day of last month');
                            buildCalendar($date->getTimestamp(), $attendance, $excused, $unexcused);

                            $date->modify('first day of next month');
                            buildCalendar($date->getTimestamp(), $attendance, $excused, $unexcused);
                            ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right column: Image -->
                <div class="col-2 pr-4 pl-4">
                    <small class="kv-label">Client Image</small>
                    <div class="row">
                        <img src="getImage.php?id=<?= $client_id; ?>" class="img-thumbnail" alt="client picture" onerror="this.onerror=null; this.src='img/male-placeholder.jpg'">
                    </div>
                    <div class="row justify-content-center">
                        <a class="nav-link" target="_blank" href="./client-image-upload.php?client_id=<?php echo htmlspecialchars($client_id); ?>">Update Image</a>
                    </div>
                </div>
            </div>

            <div class="row bg-light pt-3 pb-3">
                <div class="col-auto">
                    <input type="submit" class="btn btn-primary" value="Check In">
                </div>
                <div class="col-auto">
                    <a href="check_in_step3.php?&therapy_session_id=<?= $therapy_session_id; ?>" class="btn btn-secondary">Back</a>
                </div>
            </div>
        </form>
    </div>
</section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" crossorigin="anonymous"></script>
</body>
</html>
