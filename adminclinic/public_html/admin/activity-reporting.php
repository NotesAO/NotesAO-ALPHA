<?php
// Include authentication and configuration files
include_once '../auth.php';
require_once "../../config/config.php";

// Check if the user is logged-in
check_loggedin($con, '../index.php');

// Ensure the role is set in the session and validate permissions
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    error_log("Access Denied: Role is " . ($_SESSION['role'] ?? 'not set'));
    exit('You do not have permission to access this page!');
}

// Include additional helpers if needed
require_once "../helpers.php";
require_once "../sql_functions.php";

// Get start and end dates with defaults
$start_date = getParam('start_date', date("Y-m-01"));
$end_date = getParam('end_date', date("Y-m-t"));

// Attendance by Program Query
$attendanceByProgram = "
SELECT program, DATE_FORMAT(MIN(subq.session_date), '%M %d %Y') AS week, SUM(IFNULL(client_count, 0)) AS count
FROM (
    SELECT p.name AS program, tg.name AS therapy_group, ts.date AS session_date, cps.client_count
    FROM therapy_session ts
    LEFT JOIN therapy_group tg ON ts.therapy_group_id = tg.id
    LEFT JOIN program p ON tg.program_id = p.id
    LEFT JOIN (
        SELECT therapy_session_id, COUNT(*) AS client_count
        FROM attendance_record
        GROUP BY therapy_session_id
    ) cps ON cps.therapy_session_id = ts.id
    WHERE ts.date BETWEEN ? AND ?
) AS subq
GROUP BY program, YEARWEEK(subq.session_date)
ORDER BY MIN(subq.session_date) DESC;
";

// Fetch results
$resultarray = [];
if ($stmt = mysqli_prepare($link, $attendanceByProgram)) {
    $start = "$start_date 00:00:00";
    $end = "$end_date 23:59:59";
    mysqli_stmt_bind_param($stmt, "ss", $start, $end);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $resultarray = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Query Error: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
} else {
    error_log("Statement Preparation Failed: " . mysqli_error($link));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Activity Report</title>
    <!-- Match index.php: Bootstrap 4.5 + Font Awesome -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
    >

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script
      src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js">
    </script>
    <script
      src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js">
    </script>

    <style>
        /* Match index.php styling */
        body {
            padding-top: 70px; /* same as index.php’s navbar offset */
            background-color: #f8f9fa;
        }
        .page-header h2 {
            margin-top: 0;
        }
        .admin-btn {
            margin: 10px 5px;
        }
    </style>
</head>

<body>

<?php require_once('admin_navbar.php'); ?>
<!-- Now the same fixed-top navbar from index.php will display. -->

<div class="container">
    <div class="page-header my-3">
        <h2>Activity Reporting</h2>
    </div>

    <!-- Date Range Form -->
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" class="mb-4">
        <div class="form-row">
            <div class="form-group col-md-4">
                <label>Start Date</label>
                <input
                  type="date"
                  name="start_date"
                  class="form-control"
                  value="<?php echo htmlspecialchars($start_date); ?>"
                >
            </div>

            <div class="form-group col-md-4">
                <label>End Date</label>
                <input
                  type="date"
                  name="end_date"
                  class="form-control"
                  value="<?php echo htmlspecialchars($end_date); ?>"
                >
            </div>

            <div class="form-group col-md-4 align-self-end">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-filter"></i> Filter Results
                </button>
            </div>
        </div>
    </form>

    <hr>

    <!-- Attendance by Program -->
    <h4>Attendance by Program</h4>
    <?php if (!empty($resultarray)): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Week</th>
                        <th>Client Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultarray as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['program']); ?></td>
                            <td><?php echo htmlspecialchars($row['week']); ?></td>
                            <td><?php echo htmlspecialchars($row['count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">No attendance records found for the selected date range.</div>
    <?php endif; ?>

    <hr>

    <!-- CSV Export Options -->
    <div class="mt-4">
        <h4>Export Reports</h4>
        <div class="d-flex flex-wrap">
            <a
              class="btn btn-primary mr-2 mb-2"
              href="activity-reporting-dumpcsv.php?file=attendance&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
            >
                <i class="fas fa-file-csv"></i> Download Attendance CSV
            </a>
            <a
              class="btn btn-info mr-2 mb-2"
              href="activity-reporting-dumpcsv.php?file=clients&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
            >
                <i class="fas fa-file-csv"></i> Download Clients CSV
            </a>
            <a
              class="btn btn-success mb-2"
              href="activity-reporting-dumpcsv.php?file=revenue&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>"
            >
                <i class="fas fa-file-csv"></i> Download Revenue CSV
            </a>
        </div>
    </div>

    <hr>

    <!-- Optional: “Back to Admin Panel” or other nav link -->
    <a href="index.php" class="btn btn-dark">
        <i class="fas fa-arrow-left"></i> Back to Admin Panel
    </a>
</div>

</body>
</html>
