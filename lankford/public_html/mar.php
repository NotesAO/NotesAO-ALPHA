<?php
include_once 'auth.php';
check_loggedin($con);

// Include config file
require_once "../config/config.php";
require_once "helpers.php";
require_once "sql_functions.php";

// -----------------------------------------------------------
// 1) SET/GET START & END DATES
// -----------------------------------------------------------
$start_date = date("Y-m-d", mktime(0, 0, 0, date("m") - 1, 1));
if (isset($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}
if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
    $start_date = trim($_POST["start_date"]);
}

$end_date = date("Y-m-d", mktime(0, 0, 0, date("m"), 0));
if (isset($_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}
if (isset($_POST['end_date']) && !empty($_POST['end_date'])) {
    $end_date = trim($_POST["end_date"]);
}

// Program ID
$program_id = "";
if (isset($_POST['program_id']) && !empty($_POST['program_id'])) {
    $program_id = trim($_POST["program_id"]);
}

// -----------------------------------------------------------
// 2) HELPER FUNCTIONS
// -----------------------------------------------------------

/**
 * Runs a query that returns counts by referral_type
 * and accumulates them into an associative array.
 */
function getReferralBreakdown($sql, $date1, $date2 = null)
{
    global $link;
    $results = [
        'probation' => 0,
        'parole'    => 0,
        'pretrial'  => 0,
        'other'     => 0,
    ];

    if ($stmt = mysqli_prepare($link, $sql)) {
        if (isset($date2)) {
            mysqli_stmt_bind_param($stmt, "ss", $date1, $date2);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $date1);
        }
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $ref_type = strtolower($row["referral_type"]);
                $count    = (int)$row["count"];
                switch ($ref_type) {
                    case 'probation':
                        $results['probation'] += $count;
                        break;
                    case 'parole':
                        $results['parole'] += $count;
                        break;
                    case 'pretrial':
                        $results['pretrial'] += $count;
                        break;
                    default:
                        $results['other'] += $count;
                        break;
                }
            }
        } else {
            echo "Error in SQL: " . $stmt->error;
        }
        mysqli_stmt_close($stmt);
    }

    return $results;
}

/**
 * Prints a single row (label + Probation + Parole + Pretrial + Other + Total).
 */
function buildManualRow($label, $data)
{
    // $data is assumed to be an array with keys: probation, parole, pretrial, other.
    $prob  = $data['probation'];
    $parol = $data['parole'];
    $pret  = $data['pretrial'];
    $oth   = $data['other'];
    $total = $prob + $parol + $pret + $oth;

    echo "<tr>";
    echo "<td>$label</td>";
    echo "<td>$prob</td>";
    echo "<td>$parol</td>";
    echo "<td>$pret</td>";
    echo "<td>$oth</td>";
    echo "<td>$total</td>";
    echo "</tr>";
}

/**
 * buildMARRow() is still used for queries we want printed immediately (like Exits, Demographics).
 * It runs a query and prints the row on the spot.
 */
function buildMARRow($displayText, $sql, $date1, $date2 = null)
{
    global $link;
    $breakdown = getReferralBreakdown($sql, $date1, $date2);
    buildManualRow($displayText, $breakdown);
}

function runCountSql($sql, $date1, $date2 = null)
{
    global $link;
    $count = 0;

    if ($stmt = mysqli_prepare($link, $sql)) {
        if (isset($date2)) {
            mysqli_stmt_bind_param($stmt, "ss", $date1, $date2);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $date1);
        }
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $count = (int)$row["count"];
            }
        } else {
            echo "Error in SQL: " . $stmt->error;
        }
        mysqli_stmt_close($stmt);
    }
    return $count;
}

/* ---------- SESSION-LENGTH CONSTANTS ---------- */
const GROUP_MIN      = 120;   // fallback if ts.duration_minutes is NULL
const INDIV_MIN      = 60;
const ORIENT_MIN     = 60;    // 1-hour orientation
const INTAKE_MIN     = 60;    // 1-hour intake
const INTAKE_SESS_PER_CLIENT = 1;

/* Helper: returns minutes instead of a count ------------------ */
function runMinutesSql($sql, $params)
{
    global $link;
    $mins = 0;
    if ($stmt = mysqli_prepare($link, $sql)) {
        /* build the types string dynamically */
        $types = '';
        foreach ($params as $p) { $types .= is_int($p) ? 'i' : 's'; }

        mysqli_stmt_bind_param($stmt, $types, ...$params);
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($res)) {
                $mins = (int)$row['mins'];
            }
        }
        mysqli_stmt_close($stmt);
    }
    return $mins;
}


// -----------------------------------------------------------
// 3) GENERATE REPORT IF REQUESTED
// -----------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - MAR</title>
    
    <!-- FAVICON LINKS (from index.html) -->
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
    <link rel="stylesheet" 
          href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" 
          integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" 
          crossorigin="anonymous">
</head>
<body>
<section class="pt-2">
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <div class="page-header">
                    <h2>Monthly Activity Report - Lankford Avenue</h2>
                </div>
            </div>
        </div>

        <!-- FILTER FORM -->
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" 
              method="post" enctype="multipart/form-data">
            <div class="form-group">
                <div class="row">
                    <div class="col-auto">
                        <small class="text-muted">Start Date</small>
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo date("Y-m-d", strtotime($start_date)); ?>">
                    </div>
                    <div class="col-auto">
                        <small class="text-muted">End Date</small>
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo date("Y-m-d", strtotime($end_date)); ?>">
                    </div>
                    <div class="col-auto">
                        <small class="text-muted">Program</small>
                        <select class="form-control" id="program_id" name="program_id">
                            <?php
                            $programs = get_programs();
                            foreach ($programs as $program) {
                                $value = htmlspecialchars($program["name"]);
                                $selected = ($program["id"] == $program_id) ? 'selected="selected"' : '';
                                echo '<option value="' . $program["id"] . '" ' . $selected . '>' . $value . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-auto align-self-end">
                        <input type="submit" class="btn btn-success" name="action" value="Generate">
                    </div>
                    <div class="col-auto align-self-end">
                        <a href="index.php" class="btn btn-dark">Cancel</a>
                    </div>
                    <!-- Print Button -->
                    <div class="col-auto align-self-end">
                        <button class="btn btn-primary" onclick="window.print();">
                            <i class="fas fa-print"></i> Print Page
                        </button>
                    </div>
                </div>
            </div>
            <br>
        </form>

        <?php if (isset($_POST['action']) && $_POST['action'] == 'Generate') : ?>
            <div class="row">
                <div class="col-6">

                    <!-- =================== TABLE #1: Placements ================== -->
                    <h3>BIPP Monthly Activity (Placements)</h3>
                    <table class='table table-bordered table-striped'>
                        <thead>
                            <tr>
                                <th></th>
                                <th>Probation</th>
                                <th>Parole</th>
                                <th>Pretrial</th>
                                <th>Other</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // --- 1) Beginning of Month Counts (restored) ---
                            // This was the old logic: "orientation_date < start_date" and
                            // (exit_date is null OR exit_date > start_date).
                            /*  First group actually attended  ----------------------------------
                                – one row per client (their MIN(ts.date))
                                – keep only those first-session dates inside the MAR window       */
                            $sqlPlacements = "
                                SELECT sub.referral_type, COUNT(*) AS count
                                FROM (
                                    SELECT  c.id,
                                            rt.referral_type,
                                            MIN(ts.date) AS first_session
                                    FROM     client c
                                    JOIN     attendance_record ar ON ar.client_id = c.id
                                    JOIN     therapy_session  ts ON ts.id = ar.therapy_session_id
                                    LEFT JOIN referral_type   rt ON c.referral_type_id = rt.id
                                    WHERE    c.program_id = $program_id
                                    GROUP BY c.id
                                ) AS sub
                                WHERE sub.first_session BETWEEN ? AND ?
                                GROUP BY sub.referral_type;
                            ";

                            $sqlBegin = "
                                SELECT rt.referral_type, COUNT(c.id) AS count
                                FROM client c
                                LEFT JOIN referral_type rt ON c.referral_type_id = rt.id
                                WHERE c.program_id = $program_id
                                  AND c.orientation_date < ?
                                  AND (c.exit_date IS NULL OR c.exit_date > ?)
                                GROUP BY rt.referral_type;
                            ";
                            buildMARRow("Beginning of Month Counts", $sqlBegin, $start_date, $start_date);

                            // --- 2) Total Referrals ---
                            // orientation_date within the range
                            $sqlReferrals = "
                                SELECT rt.referral_type, count(c.id) AS count
                                FROM client c
                                LEFT JOIN referral_type rt ON c.referral_type_id = rt.id
                                WHERE c.program_id = $program_id
                                  AND orientation_date BETWEEN ? AND ?
                                GROUP BY rt.referral_type;
                            ";
                            buildMARRow("Total Referrals", $sqlReferrals, $start_date, $end_date);

                            // --- 3) New Placements (first attended group) ---
                            buildMARRow("New Placements", $sqlPlacements, $start_date, $end_date);

                            // --- 4) Total Served = Beginning of Month + Total Referrals
                            //     We need to sum up the results of those two queries. We'll do that manually.
                            $beginBreakdown  = getReferralBreakdown($sqlBegin, $start_date, $start_date);
                            $placementBreakdown = getReferralBreakdown($sqlPlacements, $start_date, $end_date);

                            $served = [
                                'probation' => $beginBreakdown['probation'] + $placementBreakdown['probation'],
                                'parole'    => $beginBreakdown['parole']    + $placementBreakdown['parole'],
                                'pretrial'  => $beginBreakdown['pretrial']  + $placementBreakdown['pretrial'],
                                'other'     => $beginBreakdown['other']     + $placementBreakdown['other']
                            ];
                            buildManualRow("Total Served", $served);

                            /* ---------- HOURS CALCULATIONS ---------- */
                            // Total Exits
                            $sqlTotalExits = "
                                SELECT rt.referral_type, COUNT(c.id) AS count
                                FROM client c
                                LEFT JOIN exit_reason er ON c.exit_reason_id = er.id
                                LEFT JOIN referral_type rt ON c.referral_type_id = rt.id
                                WHERE c.program_id = $program_id
                                  AND exit_date BETWEEN ? AND ?
                                GROUP BY rt.referral_type;
                            ";
                            /* 2a) new placement count (for intake hrs) */
                            $new_placements_total = array_sum($placementBreakdown);   // already built above

                            /* 2b) GROUP HOURS – sessions for *all* clients in this program & window */
                            $sqlGroupMins = "
                                SELECT COALESCE(SUM(IFNULL(ts.duration_minutes," . GROUP_MIN . ")),0) AS mins
                                FROM   attendance_record ar
                                JOIN   therapy_session  ts ON ts.id = ar.therapy_session_id
                                JOIN   client           c  ON c.id = ar.client_id
                                WHERE  c.program_id = ?
                                AND  DATE(ts.date) BETWEEN ? AND ?;
                            ";

                            /* run it with three parameters (program, start, end) */
                            $group_minutes = runMinutesSql($sqlGroupMins,
                                [ $program_id, $start_date, $end_date ]);

                            $group_hours = $group_minutes / 60;   // <-- nothing else changes



                            $ind_hours = 0;   // will be 0 if none found

                            /* 2d) ORIENTATION & INTAKE HOURS (one-to-one with clients) */
                            $orientation_count = runCountSql("
                                SELECT COUNT(*) AS count
                                FROM client
                                WHERE program_id = $program_id
                                AND orientation_date BETWEEN ? AND ?;",
                                $start_date, $end_date);

                            $orientation_hours = ($orientation_count * ORIENT_MIN) / 60;
                            $intake_hours      = ($new_placements_total * INTAKE_SESS_PER_CLIENT * INTAKE_MIN) / 60;


                            $exitsBreakdown = getReferralBreakdown($sqlTotalExits,$start_date,$end_date);
                            $endMonth = [
                                'probation'=>$served['probation']-$exitsBreakdown['probation'],
                                'parole'   =>$served['parole']   -$exitsBreakdown['parole'],
                                'pretrial' =>$served['pretrial'] -$exitsBreakdown['pretrial'],
                                'other'    =>$served['other']    -$exitsBreakdown['other']
                            ];
                            buildManualRow("End of Month Counts", $endMonth);

                            ?>
                        </tbody>
                    </table>

                    <!-- =================== TABLE #2: Exits ================== -->
                    <h3>BIPP Monthly Activity (Exits)</h3>
                    <table class='table table-bordered table-striped'>
                        <thead>
                            <tr>
                                <th></th>
                                <th>Probation</th>
                                <th>Parole</th>
                                <th>Pretrial</th>
                                <th>Other</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Completion of Program
                            $sqlComple = "
                                SELECT rt.referral_type, COUNT(c.id) AS count
                                FROM client c
                                LEFT JOIN exit_reason er ON c.exit_reason_id = er.id
                                LEFT JOIN referral_type rt ON c.referral_type_id = rt.id
                                WHERE c.program_id = $program_id
                                  AND exit_date BETWEEN ? AND ?
                                  AND er.reason = 'Completion of Program'
                                GROUP BY rt.referral_type;
                            ";
                            buildMARRow("Completion of Program", $sqlComple, $start_date, $end_date);

                            // Violation of Requirements
                            $sqlViol = "
                                SELECT rt.referral_type, COUNT(c.id) AS count
                                FROM client c
                                LEFT JOIN exit_reason er ON c.exit_reason_id = er.id
                                LEFT JOIN referral_type rt ON c.referral_type_id = rt.id
                                WHERE c.program_id = $program_id
                                  AND exit_date BETWEEN ? AND ?
                                  AND er.reason = 'Violation of Requirements'
                                GROUP BY rt.referral_type;
                            ";
                            buildMARRow("Violation of Requirements", $sqlViol, $start_date, $end_date);

                            // Unable to Participate
                            $sqlUnable = "
                                SELECT rt.referral_type, COUNT(c.id) AS count
                                FROM client c
                                LEFT JOIN exit_reason er ON c.exit_reason_id = er.id
                                LEFT JOIN referral_type rt ON c.referral_type_id = rt.id
                                WHERE c.program_id = $program_id
                                  AND exit_date BETWEEN ? AND ?
                                  AND er.reason = 'Unable to Participate'
                                GROUP BY rt.referral_type;
                            ";
                            buildMARRow("Unable to Participate", $sqlUnable, $start_date, $end_date);

                            // Death
                            $sqlDeath = "
                                SELECT rt.referral_type, COUNT(c.id) AS count
                                FROM client c
                                LEFT JOIN exit_reason er ON c.exit_reason_id = er.id
                                LEFT JOIN referral_type rt ON c.referral_type_id = rt.id
                                WHERE c.program_id = $program_id
                                  AND exit_date BETWEEN ? AND ?
                                  AND er.reason = 'Death'
                                GROUP BY rt.referral_type;
                            ";
                            buildMARRow("Death", $sqlDeath, $start_date, $end_date);

                            // Moved
                            $sqlMoved = "
                                SELECT rt.referral_type, COUNT(c.id) AS count
                                FROM client c
                                LEFT JOIN exit_reason er ON c.exit_reason_id = er.id
                                LEFT JOIN referral_type rt ON c.referral_type_id = rt.id
                                WHERE c.program_id = $program_id
                                  AND exit_date BETWEEN ? AND ?
                                  AND er.reason = 'Moved'
                                GROUP BY rt.referral_type;
                            ";
                            buildMARRow("Moved", $sqlMoved, $start_date, $end_date);

                            // Other Exits
                            $sqlOtherExits = "
                                SELECT rt.referral_type, COUNT(c.id) AS count
                                FROM client c
                                LEFT JOIN exit_reason er ON c.exit_reason_id = er.id
                                LEFT JOIN referral_type rt ON c.referral_type_id = rt.id
                                WHERE c.program_id = $program_id
                                  AND exit_date BETWEEN ? AND ?
                                  AND er.reason NOT IN (
                                    'Completion of Program',
                                    'Violation of Requirements',
                                    'Unable to Participate',
                                    'Moved',
                                    'Death'
                                  )
                                GROUP BY rt.referral_type;
                            ";
                            buildMARRow("Other Exits", $sqlOtherExits, $start_date, $end_date);

                            
                            buildMARRow("Total Exits", $sqlTotalExits, $start_date, $end_date);
                            ?>
                        </tbody>
                    </table>

                    <!-- ============================================= -->
                    <!-- NEW PLACEMENT DEMOGRAPHICS: AGE              -->
                    <!-- ============================================= -->
                    <h3>New Placement Demographics</h3>
                    <h4>Age</h4>
                    <table class='table table-bordered table-striped'>
                        <thead>
                            <tr>
                                <th></th>
                                <th>Probation</th>
                                <th>Parole</th>
                                <th>Pretrial</th>
                                <th>Other</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <?php
                        /*
                        |-----------------------------------------------------------------------
                        |  All age / ethnicity counts MUST match the “New Placements” totals.
                        |  We therefore drive every query off the identical first-session
                        |  placement sub-query used in the main MAR table.
                        |-----------------------------------------------------------------------
                        |  • Two bound parameters  →  ?  ?   (start_date , end_date)
                        |  • $program_id is inlined (same pattern as elsewhere in this file)
                        */
                        $ageBands = [
                            '<= 21'  => 'p.age <= 21',
                            '22-25'  => 'p.age BETWEEN 22 AND 25',
                            '26-29'  => 'p.age BETWEEN 26 AND 29',
                            '30-39'  => 'p.age BETWEEN 30 AND 39',
                            '40-49'  => 'p.age BETWEEN 40 AND 49',
                            '50 +'   => 'p.age >= 50'
                        ];

                        foreach ($ageBands as $label => $whereClause) {

                            $sql = "
                                SELECT p.referral_type, COUNT(*) AS count
                                FROM (
                                    SELECT  c.id                               AS client_id,
                                            rt.referral_type,
                                            MIN(ts.date)                       AS first_session,
                                            TIMESTAMPDIFF(
                                                YEAR, c.date_of_birth, MIN(ts.date)
                                            )                                  AS age
                                    FROM client            c
                                    JOIN attendance_record ar ON ar.client_id       = c.id
                                    JOIN therapy_session   ts ON ts.id              = ar.therapy_session_id
                                    LEFT JOIN referral_type rt ON rt.id             = c.referral_type_id
                                    WHERE c.program_id = $program_id
                                    GROUP BY c.id
                                    HAVING first_session BETWEEN ? AND ?
                                ) AS p
                                WHERE $whereClause
                                GROUP BY p.referral_type;
                            ";

                            buildMARRow($label, $sql, $start_date, $end_date);
                        }

                        /* ---- Grand-total (all placements, no age filter) ------------------- */
                        $sqlAgeTotal = "
                            SELECT p.referral_type, COUNT(*) AS count
                            FROM (
                                SELECT  c.id,
                                        rt.referral_type,
                                        MIN(ts.date) AS first_session
                                FROM client            c
                                JOIN attendance_record ar ON ar.client_id       = c.id
                                JOIN therapy_session   ts ON ts.id              = ar.therapy_session_id
                                LEFT JOIN referral_type rt ON rt.id             = c.referral_type_id
                                WHERE c.program_id = $program_id
                                GROUP BY c.id
                                HAVING first_session BETWEEN ? AND ?
                            ) AS p
                            GROUP BY p.referral_type;
                        ";
                        buildMARRow("Total", $sqlAgeTotal, $start_date, $end_date);
                        ?>
                    </table>

                    <!-- ============================================= -->
                    <!-- NEW PLACEMENT DEMOGRAPHICS: RACE / ETHNICITY -->
                    <!-- ============================================= -->
                    <h4>Race / Ethnicity</h4>
                    <table class='table table-bordered table-striped'>
                        <thead>
                            <tr>
                                <th></th>
                                <th>Probation</th>
                                <th>Parole</th>
                                <th>Pretrial</th>
                                <th>Other</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <?php
                        /*
                        |  Map friendly labels → ethnicity codes
                        |  (B = Black/African-American, W = White, H = Hispanic/Latino, etc.)
                        */
                        $ethnicMap = [
                            'African American' => "e.code = 'B'",
                            'Caucasian'        => "e.code = 'W'",
                            'Hispanic'         => "e.code = 'H'",
                            'Other'            => "e.code NOT IN ('B','W','H')"
                        ];

                        foreach ($ethnicMap as $label => $ethWhere) {

                            $sql = "
                                SELECT p.referral_type, COUNT(*) AS count
                                FROM (
                                    /* first-session placement list --------------------------- */
                                    SELECT  c.id                               AS client_id,
                                            rt.referral_type,
                                            MIN(ts.date)                       AS first_session
                                    FROM client            c
                                    JOIN attendance_record ar ON ar.client_id       = c.id
                                    JOIN therapy_session   ts ON ts.id              = ar.therapy_session_id
                                    LEFT JOIN referral_type rt ON rt.id             = c.referral_type_id
                                    WHERE c.program_id = $program_id
                                    GROUP BY c.id
                                    HAVING first_session BETWEEN ? AND ?
                                ) AS p
                                /* re-use client to reach ethnicity */
                                JOIN client c            ON c.id = p.client_id
                                LEFT JOIN ethnicity e    ON e.id = c.ethnicity_id
                                WHERE $ethWhere
                                GROUP BY p.referral_type;
                            ";

                            buildMARRow($label, $sql, $start_date, $end_date);
                        }

                        /* ---- Grand-total (all placements) ---------------------------------- */
                        $sqlEthTotal = "
                            SELECT p.referral_type, COUNT(*) AS count
                            FROM (
                                SELECT  c.id,
                                        rt.referral_type,
                                        MIN(ts.date) AS first_session
                                FROM client            c
                                JOIN attendance_record ar ON ar.client_id       = c.id
                                JOIN therapy_session   ts ON ts.id              = ar.therapy_session_id
                                LEFT JOIN referral_type rt ON rt.id             = c.referral_type_id
                                WHERE c.program_id = $program_id
                                GROUP BY c.id
                                HAVING first_session BETWEEN ? AND ?
                            ) AS p
                            GROUP BY p.referral_type;
                        ";
                        buildMARRow("Total", $sqlEthTotal, $start_date, $end_date);
                        ?>
                    </table>


                    <!-- =================== VICTIM NOTIFICATION LETTERS ================== -->
                    <h3>Victim Notification Letters</h3>
                    <table class='table table-bordered table-striped'>
                        <thead>
                            <tr>
                                <th>Letters Sent</th>
                                <th>Totals</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Sent Victim Letters<br>
                                </td>
                                <td>
                                    <?php
                                    $sqlVictimLetters = "
                                        SELECT COUNT(v.id) AS count
                                        FROM client  c
                                        JOIN victim  v ON v.client_id = c.id
                                        WHERE c.program_id       = ?
                                        AND c.orientation_date BETWEEN ? AND ?
                                    ";
                                    $victim_letters = 0;
                                    if ($stmt = mysqli_prepare($link, $sqlVictimLetters)) {
                                        mysqli_stmt_bind_param(
                                            $stmt,
                                            "iss",
                                            $program_id,
                                            $start_date,
                                            $end_date
                                        );
                                        mysqli_stmt_execute($stmt);
                                        $res = mysqli_stmt_get_result($stmt);
                                        if ($row = mysqli_fetch_assoc($res)) {
                                            $victim_letters = (int)$row['count'];
                                        }
                                        mysqli_stmt_close($stmt);
                                    }
                                    echo $victim_letters;
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <!-- =================== END VICTIM NOTIFICATION LETTERS ============== -->


                    <!-- ============================================= -->
                    <!-- CONTACTS AND TRAINING                         -->
                    <!-- ============================================= -->
                    <h3>Contacts and Training</h3>
                    <table class='table table-bordered table-striped'>
                        <thead>
                            <tr>
                                <th>Intervention Sessions</th>
                                <th>Totals</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Participant hours in intake sessions</td>
                                <td><?php echo $intake_hours; ?></td>
                            </tr>
                            <tr>
                                <td>Participant hours in orientation sessions</td>
                                <td><?php echo $orientation_hours; ?></td>
                            </tr>
                            <tr>
                                <td>Participant hours in group sessions</td>
                                <td><?php echo $group_hours; ?></td>
                            </tr>
                            <tr>
                                <td>Participant hours in individual sessions</td>
                                <td><?php echo $ind_hours; // will be 0 if none occurred ?></td>
                            </tr>
                        </tbody>

                    </table>

                    <!-- 
                        If needed, add your "Victim Contacts", 
                        "Training for Criminal Justice System", 
                        or "Persons Receiving Training" sections here 
                        using similar logic or manual input fields.
                    -->

                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"
        integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="
        crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>
</html>
