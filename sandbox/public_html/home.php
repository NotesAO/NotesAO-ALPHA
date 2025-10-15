<?php
// home.php

// 1) Ensure the user is logged in
include_once 'auth.php';
check_loggedin($con); // from your existing code

// (A) Program switch logic
// Hardcode or query the programs available
$hardcoded_programs = [
    1 => "Thinking for a Change",
    2 => "BIPP (male)",
    3 => "BIPP (female)",
    4 => "Anger Control"
];

// Current session-based program ID/Name
$program_id   = $_SESSION['program_id']   ?? 2;              // default to ID=2 (BIPP male), etc.
$program_name = $_SESSION['program_name'] ?? 'BIPP (male)';

// If user POSTed a new program to switch
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "switch_program") {
    $posted_program_id = intval($_POST["switch_program_id"] ?? 0);

    if (isset($hardcoded_programs[$posted_program_id])) {
        $_SESSION['program_id']   = $posted_program_id;
        $_SESSION['program_name'] = $hardcoded_programs[$posted_program_id];

        // Optional: Regenerate session ID for security
        session_regenerate_id(true);

        // Reload home so the new program session is in effect
        header("Location: home.php");
        exit;
    }
}

// For date manipulations:
$today            = date('Y-m-d');
$yesterday        = date('Y-m-d', strtotime('-1 day'));
$eightDaysFromNow = date('Y-m-d', strtotime('-8 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Home</title>
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
    <!-- Bootstrap CSS/JS -->
    <link rel="stylesheet" 
          href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

    <!-- Font Awesome (optional for icons) -->
    <link rel="stylesheet" 
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <!-- Chart.js for pie charts, bar charts, line charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            margin-top: 0px; /* If navbar is fixed, adjust accordingly */
            background-color:rgb(226, 230, 234); /* Light background */
        }
        .card {
            margin-bottom: 20px;
            /* Add a subtle shadow */
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .card-header i {
            margin-right: 6px;
        }
        .custom-switch-btn {
            background-color: #004080; /* Darker Blue */
            border-color: #003366;     /* Even Darker Border */
            color: white;              /* White Text */
        }

        .custom-switch-btn:hover {
            background-color: #002b5e; /* Even Darker Blue on Hover */
            border-color: #001f45;     /* Darkest Border on Hover */
            color: white;
        }

    </style>
</head>
<?php require_once('navbar.php'); // Existing navbar ?>
<body>
<div class="container-lg mt-4">

    <!-- Jumbotron / Header Section -->
    <div class="jumbotron bg-white text-center shadow-sm py-3">
        <!-- Responsive Image -->
        
        <h1 class="display-5">Welcome to</h1>
        
        <img 
            src="notesao.png" 
            alt="NotesAO Logo" 
            class="img-fluid mb-2"
            style="max-width: 40%; height: auto;"
        >
        <h1 class="lead">Your Agency Dashboard at a Glance</h1>
    </div>


    <!-- 5) QUICK ACTIONS -->
    <div class="card border-secondary mb-5">
        <div class="card-header bg-secondary text-white">
            <i class="fas fa-bolt"></i> Quick Actions
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <a href="client-index.php" class="btn btn-primary btn-block">
                        <i class="fas fa-list"></i> Client Index
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="reportgen.php" class="btn btn-primary btn-block">
                        <i class="fas fa-file-alt"></i> Report Generator
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="therapy_session-create.php" class="btn btn-success btn-block">
                        <i class="fas fa-user-md"></i> Create New Session
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="check_in_step1.php" class="btn btn-info btn-block">
                        <i class="fas fa-door-open"></i> Check In
                    </a>
                </div>
            </div>

            <!-- ───────────── Program Switch | MAR | Upload (single aligned row) ───────────── -->
            <div class="row mt-3 align-items-end"><!-- align-items-end ⇒ bottoms line up -->

                <!-- ▸ Switch programme (left) ------------------------------------------- -->
                <div class="col-md-6">
                    <form action="home.php" method="POST">
                        <input type="hidden" name="action" value="switch_program">

                        <div class="form-row align-items-end">
                            <!-- select = 8/12 on md+, full width on < md -->
                            <div class="col-12 col-md-8 mb-2 mb-md-0">
                                <label for="switch_program_id" class="mb-1">Current Program:</label>
                                <select id="switch_program_id" name="switch_program_id" class="form-control">
                                    <?php
                                    foreach ($hardcoded_programs as $pid=>$pname){
                                        $sel = $pid==$program_id ? 'selected' : '';
                                        echo "<option value=\"$pid\" $sel>$pname</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <!-- switch button = 4/12 on md+, full width on < md -->
                            <div class="col-12 col-md-4">
                                <label class="d-none d-md-block mb-1">&nbsp;</label><!-- keeps heights equal -->
                                <button class="btn btn-primary btn-block custom-switch-btn">Switch</button>
                            </div>
                        </div>
                    </form>
                </div><!-- /.col-md-6 -->

                <!-- ▸ MAR + Upload (right) ---------------------------------------------- -->
                <div class="col-md-6">
                    <div class="form-row">
                        <!-- MAR button -->
                        <div class="col-12 col-md-6 mb-2 mb-md-0">
                            <button type="button"
                                    class="btn btn-warning btn-block"
                                    onclick="showMarModal();">
                                <i class="fas fa-file-invoice"></i> MAR Report
                            </button>
                        </div>

                        <!-- Upload button -->
                        <div class="col-12 col-md-6">
                            <a href="officedocuments.php"
                            class="btn btn-block"
                            style="background:#6c757d;border-color:#545b62;color:#fff;"
                            onmouseover="this.style.background='#5a6268';this.style.borderColor='#4e555b';"
                            onmouseout="this.style.background='#6c757d';this.style.borderColor='#545b62';">
                                <i class="fas fa-upload"></i> Upload Documents
                            </a>
                        </div>
                    </div>
                </div><!-- /.col-md-6 -->

            </div><!-- /.row -->



            <!-- Upload Modal -->
            <div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-labelledby="uploadModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-md" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="uploadModalLabel">Upload New Document</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form method="post" action="officedocuments.php" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="file_type">Document Type:</label>
                                    <select name="file_type" id="file_type" class="form-control" required>
                                        <option value="Client Note Updates">Client Note Updates</option>
                                        <option value="Templates">Templates</option>
                                        <option value="Client Data">Client Data</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <input type="file" name="document" class="form-control-file" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Upload</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MAR MODAL (with iframe) -->
            <div class="modal fade" id="marModal" tabindex="-1" role="dialog" aria-labelledby="marModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered" role="document"> 
                <!-- modal-xl => wide modal; adjust to taste -->
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="marModalLabel">Monthly Activity Report (MAR)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <!-- Give the modal-body a fixed height so the iframe can scroll within it -->
                <div class="modal-body p-0" style="height:80vh;">
                    <iframe 
                    id="marFrame" 
                    src="" 
                    style="width:100%; height:100%; border:none;" 
                    frameborder="0"
                    ></iframe>
                </div>
                </div>
            </div>
            </div>

            <script>
            function showMarModal() {
                // We'll replicate the "last month" date logic:
                var programId = <?php echo json_encode($program_id); ?>;

                // Start: first day of the previous month
                var startDate = new Date();
                startDate.setMonth(startDate.getMonth() - 1);
                startDate.setDate(1);
                var startDateFormatted = startDate.toISOString().split('T')[0];

                // End: last day of the previous month
                var endDate = new Date();
                endDate.setDate(0); 
                var endDateFormatted = endDate.toISOString().split('T')[0];

                // Construct the URL for mar2.php
                var marReportUrl = "mar2.php"
                    + "?start_date=" + encodeURIComponent(startDateFormatted)
                    + "&end_date=" + encodeURIComponent(endDateFormatted)
                    + "&program_id=" + encodeURIComponent(programId)
                    + "&action=Generate"
                    + "&popout=1";

                // Set iframe src and show the modal
                document.getElementById("marFrame").src = marReportUrl;
                $('#marModal').modal('show');
            }
            </script>
        </div>
    </div>





    <!-- 4) PIE CHART FOR 'NOT EXITED' CLIENTS BY PROGRAM -->
    <div class="card border-primary">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-chart-pie"></i> Active Clients by Program
        </div>
        <div class="card-body" style="height:400px;">
            <canvas id="activeClientsPie" width="400" height="200"></canvas>
            <?php
            // Pie chart: count of active clients by program
            $sqlPie = "
                SELECT p.name AS program_name, COUNT(*) AS total
                  FROM client c
                  JOIN program p ON c.program_id = p.id
                  JOIN exit_reason e ON c.exit_reason_id = e.id
                 WHERE e.reason = 'Not Exited'
                   AND p.id <> 5
                 GROUP BY p.name
            ";
            $chartLabels = [];
            $chartData   = [];

            if ($resPie = $con->query($sqlPie)) {
                while ($row = $resPie->fetch_assoc()) {
                    $chartLabels[] = $row['program_name'];
                    $chartData[]   = $row['total'];
                }
            }
            // Convert to JSON for Chart.js
            $chartLabelsJson = json_encode($chartLabels);
            $chartDataJson   = json_encode($chartData);
            ?>
            <script>
            var ctxPie = document.getElementById('activeClientsPie').getContext('2d');
            var activePieChart = new Chart(ctxPie, {
                type: 'pie',
                data: {
                    labels: <?= $chartLabelsJson ?>,
                    datasets: [{
                        label: 'Active Clients',
                        data: <?= $chartDataJson ?>,
                        backgroundColor: [
                            '#007bff',
                            '#28a745',
                            '#ffc107',
                            '#dc3545',
                            '#6f42c1',
                            '#17a2b8',
                            '#343a40'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                    
                }
            });
            </script>
        </div>
    </div>

    <!-- Row: New Clients, New Absences, Missing Info -->
    <div class="row">
        <div class="col-md-6">
            <!-- 1) NEW CLIENTS -->
            <!-- 1) NEW CLIENTS -->
            <div class="card border-success mb-4">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-user-plus"></i>
                    New Clients (<?= date('F j, Y', strtotime($yesterday)) ?>)
                </div>
                
                <!-- Adjust p-2 for slightly tighter padding if you wish -->
                <div class="card-body p-2">
                    <div id="newClientsContent" style="max-height:200px; overflow:hidden; transition:max-height 0.4s ease;">
                    <?php
                    $sqlNewClients = "
                        SELECT c.first_name, c.last_name,
                                c.orientation_date,
                                p.name AS program_name
                            FROM client c
                            JOIN program p ON c.program_id = p.id
                        WHERE c.orientation_date = ?
                        ORDER BY p.name, c.last_name
                    ";
                    if ($stmt = $con->prepare($sqlNewClients)) {
                        $stmt->bind_param("s", $yesterday);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        $newClientsData = [];
                        while ($row = $result->fetch_assoc()) {
                            $progName = $row['program_name'];
                            if (!isset($newClientsData[$progName])) {
                                $newClientsData[$progName] = [];
                            }
                            $fullName = $row['first_name'] . ' ' . $row['last_name'];
                            $newClientsData[$progName][] = $fullName;
                        }
                        $stmt->close();

                        if (!empty($newClientsData)) {
                            foreach ($newClientsData as $programName => $clients) {
                                echo "<h5 class='font-weight-bold mt-3'>$programName</h5>";
                                echo "<ul class='list-unstyled pl-3'>";
                                foreach ($clients as $cName) {
                                    echo "<li><i class='fas fa-user text-secondary'></i> $cName</li>";
                                }
                                echo "</ul>";
                            }
                        } else {
                            echo "<p>No New Clients</p>";
                        }
                    } else {
                        echo "<p class='text-danger'>[Error preparing new clients query]</p>";
                    }
                    ?>
                    </div><!-- /#newClientsContent -->
                </div><!-- /.card-body -->

                <div class="card-footer text-center">
                    <button id="toggleNewClientsBtn" class="btn btn-sm btn-outline-secondary">Show More</button>
                </div>
                </div><!-- /.card -->

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var toggleBtn = document.getElementById('toggleNewClientsBtn');
                    var contentDiv = document.getElementById('newClientsContent');
                    var isCollapsed = true;

                    toggleBtn.addEventListener('click', function() {
                        if (isCollapsed) {
                            // Expand
                            contentDiv.style.maxHeight = '2000px';
                            toggleBtn.textContent = 'Show Less';
                        } else {
                            // Collapse
                            contentDiv.style.maxHeight = '200px';
                            toggleBtn.textContent = 'Show More';
                        }
                        isCollapsed = !isCollapsed;
                    });
                });
                </script>


            <!-- 2) NEW ABSENCES -->
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-user-times"></i>
                    New Absences (<?= date('F j, Y', strtotime($eightDaysFromNow)) ?>)
                </div>

                <div class="card-body p-2">
                    <div id="newAbsencesContent" style="max-height:550px; overflow:hidden; transition:max-height 0.4s ease;">
                    <?php
                    $sqlAbsences = "
                        SELECT a.date AS absence_date,
                            c.first_name, c.last_name,
                            p.name AS program_name
                        FROM absence a
                        JOIN client c ON a.client_id = c.id
                        JOIN program p ON c.program_id = p.id
                        WHERE a.date = ?
                        AND a.excused = 0
                        ORDER BY p.name, c.last_name
                    ";

                    if ($stmt = $con->prepare($sqlAbsences)) {
                        $stmt->bind_param("s", $eightDaysFromNow);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        $absencesData = []; 
                        while ($row = $result->fetch_assoc()) {
                            $prog = $row['program_name'];
                            if (!isset($absencesData[$prog])) {
                                $absencesData[$prog] = [];
                            }
                            $absencesData[$prog][] = $row['first_name'] . ' ' . $row['last_name'];
                        }
                        $stmt->close();

                        if (!empty($absencesData)) {
                            foreach ($absencesData as $programName => $clients) {
                                echo "<h5 class='font-weight-bold mt-3'>$programName</h5>";
                                echo "<ul class='list-unstyled pl-3'>";
                                foreach ($clients as $cName) {
                                    echo "<li><i class='fas fa-user text-secondary'></i> $cName</li>";
                                }
                                echo "</ul>";
                            }
                        } 

                        // Move "No New Absences" lower for better visual alignment
                        echo "<h6 class='font-weight-bold mt-3'></h6>"; // Empty header to align with other sections
                        if (empty($absencesData)) {
                            echo "<p class='pl-3'>No New Absences</p>";
                        }
                    } else {
                        echo "<p class='text-danger'>[Error preparing absences query]</p>";
                    }
                    ?>
                    </div>
                </div>


                <div class="card-footer text-center">
                    <button id="toggleNewAbsencesBtn" class="btn btn-sm btn-outline-secondary">Show More</button>
                </div>
                </div><!-- /.card -->

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var toggleBtn = document.getElementById('toggleNewAbsencesBtn');
                    var contentDiv = document.getElementById('newAbsencesContent');
                    var isCollapsed = true;

                    toggleBtn.addEventListener('click', function() {
                        if (isCollapsed) {
                            // Expand
                            contentDiv.style.maxHeight = '2000px';
                            toggleBtn.textContent = 'Show Less';
                        } else {
                            // Collapse
                            contentDiv.style.maxHeight = '550px';
                            toggleBtn.textContent = 'Show More';
                        }
                        isCollapsed = !isCollapsed;
                    });
                });
                </script>

        </div><!-- /.col-md-6 -->

        <div class="col-md-6">
        <!-- 3) CLIENTS WITH MISSING INFORMATION -->
        <div class="card border-warning">
            <div class="card-header bg-warning text-white">
                <i class="fas fa-exclamation-circle"></i> Clients with Missing Info
            </div>
            <!-- Adjust card-body padding to taste; p-2 is just a bit tighter -->
            <div class="card-body p-2">
                <!-- Collapsible Wrapper -->
                <div id="missingInfoContent" style="max-height:805px; overflow:hidden; transition:max-height 0.4s ease;">
                    <?php
                    $sqlMissing = "
                        SELECT c.id, c.first_name, c.last_name,
                            cm.last_name AS cm_lastname,
                            p.name AS program_name,
                            c.orientation_date, c.phone_number,
                            c.required_sessions,
                            e.reason AS exit_reason,
                            c.gender_id,
                            c.intake_packet
                        FROM client c
                        JOIN program p       ON c.program_id = p.id
                    LEFT JOIN case_manager cm ON c.case_manager_id = cm.id
                    LEFT JOIN exit_reason e   ON c.exit_reason_id = e.id
                        WHERE c.exit_reason_id = 1    -- Only those who are 'Not Exited'
                        AND p.id <> 5              -- Exclude program id=5
                        AND (
                                cm.last_name = '1 officer not listed'
                            OR (c.orientation_date IS NULL OR c.orientation_date = '0000-00-00')
                            OR (c.phone_number IS NULL OR c.phone_number = '')
                            OR (c.gender_id = 1)
                            OR (c.required_sessions NOT IN (18,27,30,52))
                            OR (
                                    p.id NOT IN (4,5)
                                AND (c.intake_packet IS NULL OR c.intake_packet = 0)
                                )
                        )
                        ORDER BY p.name, c.last_name
                    ";

                    if ($resMissing = $con->query($sqlMissing)) {
                        if ($resMissing->num_rows > 0) {
                            $missingData = [];
                            while ($row = $resMissing->fetch_assoc()) {
                                $prog = $row['program_name'];
                                if (!isset($missingData[$prog])) {
                                    $missingData[$prog] = [];
                                }

                                // Build an array of which fields are "missing"
                                $reasons = [];
                                if ($row['cm_lastname'] === '1 officer not listed') {
                                    $reasons[] = "No Officer Listed";
                                }
                                if (empty($row['orientation_date']) || $row['orientation_date'] === '0000-00-00') {
                                    $reasons[] = "No Orientation Date";
                                }
                                if (empty($row['phone_number'])) {
                                    $reasons[] = "No Phone Number";
                                }
                                if ($row['gender_id'] == 1) {
                                    $reasons[] = "Gender Not Specified";
                                }
                                // Check required_sessions
                                if (!in_array($row['required_sessions'], [18,27,30,52]) &&
                                    $row['exit_reason'] === 'Not Exited') {
                                    $reasons[] = "Required Sessions Invalid";
                                }
                                if (empty($row['intake_packet'])) {
                                    $reasons[] = "No Intake Packet";
                                }

                                $clientName = $row['first_name'] . ' ' . $row['last_name'];
                                $missingData[$prog][] = [
                                    'name'   => $clientName,
                                    'issues' => $reasons
                                ];
                            }

                            // Output all missing-info clients grouped by Program
                            foreach ($missingData as $programName => $clients) {
                                echo "<h5 class='font-weight-bold mt-3'>$programName</h5>";
                                echo "<ul class='list-unstyled pl-4 mb-0'>";
                                foreach ($clients as $info) {
                                    echo "<li class='mb-3'>";
                                    echo "<strong><i class='fas fa-user text-danger'></i> {$info['name']}</strong><br>";
                                    foreach ($info['issues'] as $i) {
                                        echo "<span class='text-danger'>- $i</span><br>";
                                    }
                                    echo "</li>";
                                }
                                echo "</ul>";
                            }
                        } else {
                            echo "<p>No clients with missing info found.</p>";
                        }
                    } else {
                        echo "<p class='text-danger'>[Error executing missing info query]</p>";
                    }
                    ?>
                </div> <!-- /#missingInfoContent -->
            </div><!-- /.card-body -->

            <!-- Collapsible Toggle Button -->
            <div class="card-footer text-center">
                <button id="toggleMissingBtn" class="btn btn-sm btn-outline-secondary">Show More</button>
            </div>
        </div><!-- /.card -->


        <!-- 4) CLIENTS WITH NEW PHONE NUMBERS -->
        <div class="card border-dark">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-phone-alt"></i> Clients with New Phone Numbers (past 7 days)
            </div>

            <div class="card-body p-2">
                <div id="newPhonesContent" style="max-height:400px; overflow:hidden; transition:max-height 0.4s ease;">
                <?php
                // get the cut-off date
                $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
                $baselineTs   = '2025-05-09 13:21:40';

                $sqlNewPhones = "
                    SELECT c.first_name, c.last_name, c.phone_number,
                        p.name AS program_name
                    FROM client c
                    JOIN program p ON c.program_id = p.id
                    WHERE c.phone_number <> ''                  -- has a number
                    AND c.phone_updated_at >= ?  
                    AND c.phone_updated_at  >  ?             -- ≥ 7-days-ago
                    AND c.exit_reason_id     = 1
                    AND p.id                <> 5
                    AND (
                         c.orientation_date is NULL
                         OR DATE(c.phone_updated_at) <> c.orientation_date
                        )
                    ORDER BY p.name, c.last_name

                ";

                if ($stmt = $con->prepare($sqlNewPhones)) {
                    $stmt->bind_param("ss", $sevenDaysAgo, $baselineTs);
                    $stmt->execute();
                    $res = $stmt->get_result();

                    $data = [];
                    while ($row = $res->fetch_assoc()) {
                        $prog = $row['program_name'];
                        if (!isset($data[$prog])) $data[$prog] = [];
                        $data[$prog][] = $row;
                    }
                    $stmt->close();

                    if (!empty($data)) {
                        foreach ($data as $programName => $clients) {
                            echo "<h5 class='font-weight-bold mt-3'>$programName</h5>";
                            echo "<ul class='list-unstyled pl-3'>";
                            foreach ($clients as $c) {
                                $name = htmlspecialchars($c['first_name'].' '.$c['last_name']);
                                $phone = htmlspecialchars($c['phone_number']);
                                echo "<li><i class='fas fa-user text-secondary'></i> $name &nbsp; <span class='text-muted'>( $phone )</span></li>";
                            }
                            echo "</ul>";
                        }
                    } else {
                        echo "<p>No phone-number changes found.</p>";
                    }
                } else {
                    echo "<p class='text-danger'>[Error preparing phone query]</p>";
                }
                ?>
            </div><!-- /#newPhonesContent -->
        </div>

        <div class="card-footer text-center">
            <button id="toggleNewPhonesBtn" class="btn btn-sm btn-outline-secondary">Show More</button>
        </div>
    </div>

    <!-- 5) CLIENTS WITH BEHAVIOR CONTRACTS -->
    <div class="card border-danger">
        <div class="card-header bg-danger text-white">
            <i class="fas fa-file-signature"></i>
            Clients with Behavior Contracts
        </div>

        <div class="card-body p-2">
            <div id="contractContent" style="max-height:400px; overflow:hidden; transition:max-height .4s ease;">

        <?php
        /* ---------- date helpers ---------- */
        $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));

        /* ---------- 1) NEEDED ---------- */
        $sqlNeeded = "
        SELECT c.first_name, c.last_name, p.name AS program_name
            FROM client c
            JOIN program p ON p.id = c.program_id
        WHERE c.behavior_contract_status = 'Needed'
            AND c.exit_reason_id = 1      -- still active
            AND p.id <> 5                 -- exclude Veterans Court
        ORDER BY p.name, c.last_name
        ";

        /* ---------- 2) SIGNED in last 7 days ---------- */
        $sqlSigned = "
        SELECT c.first_name, c.last_name, p.name AS program_name
            FROM client c
            JOIN program p ON p.id = c.program_id
        WHERE c.behavior_contract_status = 'Signed'
            AND c.behavior_contract_signed_date >= ?
            AND c.exit_reason_id = 1
            AND p.id <> 5
        ORDER BY p.name, c.last_name
        ";

        /* Helper to render a section */
        function renderContractSection($con, $sql, $typeLabel, $param = null) {
            if ($stmt = $con->prepare($sql)) {
                if ($param !== null) $stmt->bind_param("s", $param);
                $stmt->execute();
                $res = $stmt->get_result();

                $byProg = [];
                while ($row = $res->fetch_assoc()) {
                    $byProg[$row['program_name']][] =
                        htmlspecialchars($row['first_name'].' '.$row['last_name']);
                }
                $stmt->close();

                /* nothing? -> return false without printing anything */
                if (empty($byProg)) {
                    return false;
                }

                /* we have rows → show header and list */
                echo "<h5 class='font-weight-bold mt-2'>$typeLabel</h5>";
                foreach ($byProg as $prog => $names) {
                    echo "<strong class='pl-2'>$prog</strong>";
                    echo "<ul class='list-unstyled pl-4 mb-2'>";
                    foreach ($names as $n)
                        echo "<li><i class='fas fa-user text-secondary'></i> $n</li>";
                    echo "</ul>";
                }
                return true;   // something printed
            }

            /* query failed – show error but count it as “printed” so the fallback isn’t shown too */
            echo "<p class='text-danger'>[Error preparing $typeLabel query]</p>";
            return true;
        }



        /* ---------- output Needed & Signed sections ---------- */
        /* ---------- output Needed & Signed sections ---------- */
        $anyContracts = false;          // ← NEW LINE

        $anyContracts |= renderContractSection($con, $sqlNeeded, 'Needed');                    // ← EDIT
        $anyContracts |= renderContractSection($con, $sqlSigned, 'Signed (last 7 days)', $sevenDaysAgo); // ← EDIT

        if (!$anyContracts) {
            /* was mb-0 → change to mb-2 (or drop the class entirely) */
            echo "<p class='pl-2 mb-2'>No new behavior contracts found.</p>";
        }



        ?>

            </div><!-- /#contractContent -->
        </div>

        <div class="card-footer text-center">
            <button id="toggleContractsBtn" class="btn btn-sm btn-outline-secondary">Show More</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded',function(){
    const btn  = document.getElementById('toggleContractsBtn');
    const wrap = document.getElementById('contractContent');
    let collapsed = true;
    btn.addEventListener('click',()=>{
        wrap.style.maxHeight = collapsed ? '3000px' : '400px';
        btn.textContent      = collapsed ? 'Show Less' : 'Show More';
        collapsed = !collapsed;
    });
    });
    </script>


    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn   = document.getElementById('toggleNewPhonesBtn');
        var wrap  = document.getElementById('newPhonesContent');
        var collapsed = true;

        btn.addEventListener('click', function () {
            if (collapsed) {
                wrap.style.maxHeight = '3000px';
                btn.textContent = 'Show Less';
            } else {
                wrap.style.maxHeight = '400px';
                btn.textContent = 'Show More';
            }
            collapsed = !collapsed;
        });
    });
    </script>



</div><!-- /.col-md-6 -->

            <!-- Script to Expand/Collapse the Missing-Info block -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var toggleMissingBtn = document.getElementById('toggleMissingBtn');
                var missingInfoDiv   = document.getElementById('missingInfoContent');
                var isCollapsed      = true;

                toggleMissingBtn.addEventListener('click', function() {
                    if (isCollapsed) {
                        // Expand to something large so all content is visible
                        missingInfoDiv.style.maxHeight = '4000px';
                        toggleMissingBtn.textContent = 'Show Less';
                    } else {
                        // Collapse back
                        missingInfoDiv.style.maxHeight = '805px';
                        toggleMissingBtn.textContent = 'Show More';
                    }
                    isCollapsed = !isCollapsed;
                });
            });
            </script>

    </div><!-- /.row -->


    <!-- ================== ADDITIONAL CHARTS SECTION ================== -->
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') : ?>
    <?php
    /**
     * Now we gather the data for the last 12 months (stacked bar & line charts).
     * "Success" = exit_reason_id ∈ (1,3,8)
     * "Fail"    = exit_reason_id ∈ (2,4,5,6,7)
     * Omit program_id=5
     */

    // 1) Determine 12-month range
    $oneYearAgo = date('Y-m-01', strtotime('-11 months'));

    // 2) SQL to collect monthly success/fail per program
    $sqlData = "
      SELECT
        p.name AS program_name,
        YEAR(c.orientation_date) AS yr,
        MONTH(c.orientation_date) AS mn,
        SUM(CASE WHEN c.exit_reason_id IN (1,3,8) THEN 1 ELSE 0 END) AS success_count,
        SUM(CASE WHEN c.exit_reason_id IN (2,4,5,6,7) THEN 1 ELSE 0 END) AS fail_count
      FROM client c
      JOIN program p ON c.program_id = p.id
      WHERE c.orientation_date >= ?
        AND p.id <> 5
      GROUP BY p.name, YEAR(c.orientation_date), MONTH(c.orientation_date)
      ORDER BY p.name, yr, mn
    ";

    $programData = []; // $programData[$progName][$YYYYMM] = [ 'success'=>X, 'fail'=>Y ]

    if ($stmt = $con->prepare($sqlData)) {
        $stmt->bind_param("s", $oneYearAgo);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $progName   = $row['program_name'];
            $yr         = $row['yr'];
            $mn         = $row['mn'];
            $success    = $row['success_count'];
            $fail       = $row['fail_count'];

            $monthKey = sprintf('%04d-%02d', $yr, $mn);
            if (!isset($programData[$progName])) {
                $programData[$progName] = [];
            }
            $programData[$progName][$monthKey] = [
                'success' => $success,
                'fail'    => $fail
            ];
        }
        $stmt->close();
    } else {
        echo "<p class='text-danger'>[Error preparing stacked bar query]</p>";
    }

    // 3) Build an array of 12 consecutive month labels
    $monthLabels = [];
    $start = new DateTime($oneYearAgo);
    $end   = new DateTime(date('Y-m-01')); 

    while ($start <= $end) {
        $mLabel = $start->format('Y-m'); // e.g. "2024-03"
        $monthLabels[] = $mLabel;
        $start->modify('+1 month');
    }

    // We'll now build 2 sets of datasets:
    //  -- One for a stacked bar chart
    //  -- One for a separate line chart

    // ============== CHART #1: STACKED BAR ==============
    $datasetsBar = [];
    $globalMax   = 0;

    $colorSuccess = ['#28a745','#17a2b8','#007bff','#ffc107', '#20B2AA','#8B008B'];
    $colorFail    = ['#dc3545','#6f42c1','#fd7e14','#343a40', '#FF1493','#FF00FF'];

    $progIndex = 0;
    foreach ($programData as $progName => $monthMap) {
        $successVals = [];
        $failVals    = [];

        foreach ($monthLabels as $ml) {
            $sVal = isset($monthMap[$ml]) ? $monthMap[$ml]['success'] : 0;
            $fVal = isset($monthMap[$ml]) ? $monthMap[$ml]['fail']   : 0;
            $successVals[] = $sVal;
            $failVals[]    = $fVal;

            $monthTotal = $sVal + $fVal;
            if ($monthTotal > $globalMax) {
                $globalMax = $monthTotal;
            }
        }

        // One bar dataset for success
        $datasetsBar[] = [
            'label'           => "$progName - Completions",
            'data'            => $successVals,
            'backgroundColor' => $colorSuccess[$progIndex % count($colorSuccess)],
            'stack'           => $progName,  // group success/fail
            'type'            => 'bar'
        ];

        // One bar dataset for fail
        $datasetsBar[] = [
            'label'           => "$progName - Exits",
            'data'            => $failVals,
            'backgroundColor' => $colorFail[$progIndex % count($colorFail)],
            'stack'           => $progName,
            'type'            => 'bar'
        ];

        $progIndex++;
    }

    // ============== CHART #2: SINGLE LINE (TOTAL) PER PROGRAM ==============
    // We'll compute average monthly growth from the FIRST non-zero month to the LAST non-zero month.
    $datasetsLine = [];
    $progIndex    = 0;

    $colorLine = [
        '#28a745','#17a2b8','#007bff','#ffc107','#20B2AA','#8B008B',
        '#dc3545','#6f42c1','#fd7e14','#343a40','#FF1493','#FF00FF'
    ];

    // Number of months in our array
    $numMonths = count($monthLabels);

    foreach ($programData as $progName => $monthMap) {
        // 1) Build array of monthly totals
        $totalVals = [];
        foreach ($monthLabels as $ml) {
            $sVal = isset($monthMap[$ml]) ? $monthMap[$ml]['success'] : 0;
            $fVal = isset($monthMap[$ml]) ? $monthMap[$ml]['fail']    : 0;
            $totalVals[] = $sVal + $fVal;
        }

        // 2) Find the earliest (first) non-zero index and the latest (last) non-zero index
        $firstIndex = null;
        $lastIndex  = null;
        for ($i = 0; $i < $numMonths; $i++) {
            if ($totalVals[$i] != 0) {
                if ($firstIndex === null) {
                    $firstIndex = $i;
                }
                $lastIndex = $i;
            }
        }

        // *** NEW CODE: Exclude current month from ratio if lastIndex is the current month ***
        $currentMonthLabel = date('Y-m'); // e.g., "2025-02"
        if ($lastIndex !== null && $lastIndex > $firstIndex) {
            if (isset($monthLabels[$lastIndex]) && $monthLabels[$lastIndex] === $currentMonthLabel) {
                // shift lastIndex back by 1 if possible
                if (($lastIndex - 1) >= $firstIndex) {
                    $lastIndex--;
                }
            }
        }

        // 3) Compute average monthly growth. If everything is zero, or only one non-zero month, show 0%.
        $avgGrowthPct = 0.0;
        if ($firstIndex !== null && $lastIndex !== null && $firstIndex < $lastIndex) {
            $startVal = $totalVals[$firstIndex];
            $endVal   = $totalVals[$lastIndex];
            $numIntervals = ($lastIndex - $firstIndex);

            if ($startVal > 0 && $endVal > 0) {
                $ratio = $endVal / $startVal;
                if ($ratio > 0) {
                    $avgGrowth = pow($ratio, 1 / $numIntervals) - 1;
                    $avgGrowthPct = $avgGrowth * 100;
                }
            }
        }


        $roundedPct = round($avgGrowthPct, 1);
        if ($roundedPct > 0) {
            $roundedPct = '+'.$roundedPct;
        }

        // Example legend label: "Anger Control (Avg +2.5%/mo)"
        $labelWithGrowth = "$progName (Avg {$roundedPct}%/mo)";

        // 4) Build the single line dataset for this program's monthly totals
        $datasetsLine[] = [
            'label'           => $labelWithGrowth,
            'data'            => $totalVals,
            'borderColor'     => $colorLine[$progIndex % count($colorLine)],
            'backgroundColor' => $colorLine[$progIndex % count($colorLine)],
            'fill'            => false,
            'type'            => 'line',
            'borderWidth'     => 2
        ];

        $progIndex++;
    }

    // Optional padding above max
    $maxY = $globalMax;
    // $maxY = $globalMax * 1.1; // if you want extra space at top

    ?>

    <!-- CARD FOR STACKED BAR CHART -->
    <div class="card border-secondary">
      <div class="card-header bg-secondary text-white">
        <i class="fas fa-chart-bar"></i> (ADMIN) Monthly Stacked Attendance by Program (Exits over Completions)
      </div>
      <div class="card-body" style="height:400px;">
        <canvas id="stackedBarChart"></canvas>
        <script>
        var ctxBar = document.getElementById('stackedBarChart').getContext('2d');
        var stackedBarChart = new Chart(ctxBar, {
          type: 'bar',
          data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: <?= json_encode($datasetsBar) ?>
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: {
                type: 'category'
              },
              y: {
                stacked: true,
                beginAtZero: true,
                max: <?= $maxY ?>,
                title: {
                  display: true,
                  text: '# of Clients'
                }
              }
            },
            plugins: {
              legend: {
                position: 'top'
              }
            }
          }
        });
        </script>
      </div>
    </div><!-- /.card -->


    <!-- CARD FOR SEPARATE LINE CHART -->
    <div class="card border-secondary">
      <div class="card-header bg-secondary text-white">
        <i class="fas fa-chart-line"></i> (ADMIN) Total Attendance Line (Month to Month)
      </div>
      <div class="card-body" style="height:400px;">
        <canvas id="lineChartSuccessFail"></canvas>
        <script>
        var ctxLine = document.getElementById('lineChartSuccessFail').getContext('2d');
        var lineChart = new Chart(ctxLine, {
          type: 'line',
          data: {
            labels: <?= json_encode($monthLabels) ?>,
            datasets: <?= json_encode($datasetsLine) ?>
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              x: {
                type: 'category'
              },
              y: {
                beginAtZero: true,
                title: {
                  display: true,
                  text: '# of Clients'
                }
              }
            },
            plugins: {
              legend: {
                position: 'top'
              }
            }
          }
        });
        </script>
      </div>
    </div><!-- /.card -->

    <?php
    // === 1) Query for active clients by program & referral type, ignoring program_id=5 and referral_type_id=6 ===
    $sqlReferralBreakdown = "
        SELECT
            p.id            AS program_id,
            p.name          AS program_name,
            r.referral_type AS referral_type_label,
            COUNT(*)        AS total_count
        FROM client c
        JOIN program p       ON c.program_id = p.id
        JOIN referral_type r ON c.referral_type_id = r.id
        JOIN exit_reason e   ON c.exit_reason_id = e.id
        WHERE e.reason = 'Not Exited'
        AND p.id <> 5       -- Exclude Veterans Court (program_id=5)
        AND r.id <> 6       -- Exclude referral type VTC (referral_type_id=6)
        GROUP BY p.id, r.id
        ORDER BY p.id, r.id
    ";

    $chartDataByProgram = [];
    if ($res = $con->query($sqlReferralBreakdown)) {
        while ($row = $res->fetch_assoc()) {
            $progName = $row['program_name'];
            $refLabel = $row['referral_type_label'];
            $count    = (int)$row['total_count'];

            if (!isset($chartDataByProgram[$progName])) {
                $chartDataByProgram[$progName] = [
                    'labels' => [],
                    'data'   => []
                ];
            }
            $chartDataByProgram[$progName]['labels'][] = $refLabel;
            $chartDataByProgram[$progName]['data'][]   = $count;
        }
    } else {
        echo "<p class='text-danger'>[Error executing referral breakdown query]</p>";
    }
    ?>

    <!-- 4 Program Pie Charts in One Block (same style as other charts) -->
    <div class="card border-secondary mt-4">
    <div class="card-header bg-secondary text-white">
        <i class="fas fa-chart-pie"></i> (ADMIN) Program Active Clients by Referral Type
    </div>
    <div class="card-body">
        <?php if (!empty($chartDataByProgram)): ?>
        <div class="row">
            <?php 
            $chartIndex = 1;
            foreach ($chartDataByProgram as $programName => $chartInfo):
                // Unique canvas ID per program
                $canvasId = "refPieChart_" . $chartIndex;
                $labels   = json_encode($chartInfo['labels']);
                $data     = json_encode($chartInfo['data']);
            ?>
            <!-- Two charts per row, side by side -->
            <div class="col-md-6 mb-4">
                <div class="card">
                <div class="card-header bg-light">
                    <strong><?php echo htmlspecialchars($programName); ?></strong>
                </div>
                <div class="card-body" style="height: 300px;">
                    <canvas id="<?php echo $canvasId; ?>"></canvas>
                </div>
                </div>
                <script>
                (function(){
                    var ctx = document.getElementById('<?php echo $canvasId; ?>').getContext('2d');
                    new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo $labels; ?>,
                        datasets: [{
                        data: <?php echo $data; ?>,
                        backgroundColor: [
                            '#007bff',
                            '#28a745',
                            '#ffc107',
                            '#dc3545',
                            '#6f42c1',
                            '#17a2b8',
                            '#343a40',
                            '#20B2AA',
                            '#8B008B',
                            '#FF1493'
                        ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                        legend: {
                            position: 'bottom'
                        }
                        }
                    }
                    });
                })();
                </script>
            </div>
            <?php 
            $chartIndex++;
            endforeach;
            ?>
        </div><!-- /.row -->
        <?php else: ?>
        <p>No active clients found to display.</p>
        <?php endif; ?>
    </div>
    </div><!-- /.card -->


    


    <?php endif; // end if Admin ?>
</div><!-- .container -->


<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
  <!-- Floating Sandbox Reset FAB -->
  <a href="sandbox_reset.php"
     class="sandbox-reset-fab"
     onclick="return confirm('⚠️  This will restore the sandbox DB to today’s backup. Continue?');"
     title="Reset Sandbox">
    <i class="fas fa-sync-alt"></i>
  </a>

  <style>
    .sandbox-reset-fab {
      position: fixed;
      bottom: 1rem;
      right: 1rem;
      z-index: 1050;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 3.5rem;
      height: 3.5rem;
      background-color: #dc3545;
      color: #fff;
      border-radius: 50%;
      box-shadow: 0 2px 6px rgba(0,0,0,0.3);
      font-size: 1.25rem;
      text-decoration: none;
    }
    .sandbox-reset-fab:hover {
      background-color: #c82333;
      text-decoration: none;
      color: #fff;
    }
  </style>
<?php endif; ?>


</body>
</html>