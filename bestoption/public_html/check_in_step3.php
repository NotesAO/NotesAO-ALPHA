<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";
require_once "sql_functions.php";

// 1) The default: we include clients from other groups
$include_othergroup = true;

// If the user has unchecked the box, no "include_othergroup" param will be sent
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['include_othergroup'])) {
        $include_othergroup = false;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['include_othergroup'])) {
        $include_othergroup = false;
    }
}

// Program ID from session
$program_id = $_SESSION['program_id'] ?? null;

// Therapy session & group ID
$therapy_session_id = $_GET['therapy_session_id'] ?? $_POST['therapy_session_id'] ?? '';
$sessionInfo = get_therapy_session_info($therapy_session_id);

$therapy_group_id = '';
if ($sessionInfo) {
    $therapy_group_id = $sessionInfo['therapy_group_id'];
} else {
    // handle missing session
}

// Search term
$search = $_GET['search'] ?? $_POST['search'] ?? '';

// Sorting
$orderBy = ['c.first_name','c.last_name','c.date_of_birth','c.phone_number','required_sessions',
            'sessions_attended','case_mgr','note','orientation_date'];
$order = ($_GET['order'] ?? 'c.last_name');
if (!in_array($order, $orderBy)) {
    $order = 'c.last_name';
}

$sort = ($_GET['sort'] ?? 'asc');
if (!in_array($sort, ['asc','desc'])) {
    $sort = 'asc';
}

// Build the query
require_once "../config/config.php"; // $link
$sql = "
  SELECT c.id, c.first_name, c.last_name, c.date_of_birth, c.phone_number, tg.name AS group_name,
         c.orientation_date, c.exit_date, c.required_sessions, c.note, sessions_attended, last_attended,
         CONCAT(cm.first_name, ' ', cm.last_name) AS case_mgr
  FROM client c
  LEFT JOIN case_manager cm ON c.case_manager_id = cm.id
  LEFT JOIN therapy_group tg ON c.therapy_group_id = tg.id
  LEFT JOIN (
      SELECT ar.client_id, COUNT(ar.client_id) AS sessions_attended
      FROM attendance_record ar
      GROUP BY ar.client_id
  ) AS client_total_attendance ON c.id = client_total_attendance.client_id
  LEFT JOIN (
      SELECT ar.client_id, MAX(ts.date) AS last_attended
      FROM attendance_record ar
      LEFT JOIN therapy_session ts ON ar.therapy_session_id = ts.id
      GROUP BY ar.client_id
  ) AS client_last_attendance ON c.id = client_last_attendance.client_id
  WHERE c.program_id = $program_id
    AND c.exit_date IS NULL
";

// If user does NOT want to include other groups, limit to the current group
if (!$include_othergroup) {
    $sql .= " AND c.therapy_group_id = $therapy_group_id";
}

// If search is provided
if (!empty($search)) {
    // partial matching on name, phone, etc.
    $sql .= " AND CONCAT_WS(
                c.first_name,
                c.last_name,
                c.date_of_birth,
                c.phone_number,
                c.note,
                CONCAT(cm.first_name,' ',cm.last_name),
                tg.name
              ) LIKE '%$search%'";
}

// Sorting
$sql .= " ORDER BY $order $sort";

// Execute the query
$result = mysqli_query($link, $sql);
$count = $result ? mysqli_num_rows($result) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Check-In</title>
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
    <!-- Your existing CSS/JS includes -->
    <!-- Bootstrap 4.5 and Font Awesome (Ensure these match home.php) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
<?php require_once('navbar.php'); ?>

<section class="pt-3">
    <div class="container-fluid">
        <!-- Header Row -->
        <div class="row bg-light">
            <div class="col">
                <h2>Client Check In</h2>
            </div>
            <div class="col">
                <a href="check_in_step1.php" class="btn btn-primary float-right">Change Session</a>
            </div>
        </div>

        <!-- Session Info -->
        <div class="row bg-light">
            <div class="col">
                <?php if ($sessionInfo): ?>
                    <p class="h4">
                        <?= htmlspecialchars($sessionInfo['group_name'] . ' - ' . $sessionInfo['group_address']) ?>
                    </p>
                    <p class="h4">
                        <?= htmlspecialchars($sessionInfo['weekday'] . ' ' . $sessionInfo['date'] . ' - ' . $sessionInfo['facilitator']) ?>
                    </p>
                <?php else: ?>
                    <div class="alert alert-warning">
                        Session not found
                        <a href="check_in_step1.php" class="btn btn-primary float-right">Return to Step 1</a>
                    </div>
                    <?php exit; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search Form -->
        <form action="?" method="get">
            <input type="hidden" name="therapy_session_id" value="<?= htmlspecialchars($therapy_session_id) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">

            <div class="row bg-light">
                <div class="col-auto">
                    <small class="text-muted">Search Criteria</small>
                    <input
                      type="text"
                      class="form-control"
                      placeholder="Search Criteria"
                      name="search"
                      value="<?= htmlspecialchars($search) ?>"
                    >
                </div>
                <div class="col-auto align-self-end">
                    <!-- Notice we do NOT hardcode checked. 
                         We conditionally output checked if $include_othergroup is true. -->
                    <input
                      class="form-check-input"
                      type="checkbox"
                      value="true"
                      name="include_othergroup"
                      id="include_othergroup"
                      <?= $include_othergroup ? 'checked' : '' ?>
                    >
                    <label class="ml-4" for="include_othergroup">
                        <h5>Include clients from other groups</h5>
                    </label>
                </div>
                <div class="col align-self-end">
                    <input type="submit" class="btn btn-info" value="Search">
                </div>
            </div>

            <div class="row bg-light">
                <div class="col">
                    <label class="text-muted">
                      You can search by first name, last name, phone number, or date of birth.
                      Partial values are accepted (case insensitive).
                    </label>
                </div>
            </div>
        </form>

        <?php if ($result): ?>
            <p class="lead"><em><?= $count ?> records found</em></p>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <?php
                        // Toggle sort direction for next click
                        $nextSort = ($sort === 'asc') ? 'desc' : 'asc';

                        // Build the URL prefix
                        $url_prefix = "therapy_session_id=" . urlencode($therapy_session_id)
                                    . "&search=" . urlencode($search)
                                    . ($include_othergroup ? "&include_othergroup=true" : "")
                                    . "&sort=" . urlencode($nextSort);

                        // Output column headers with clickable sort
                        ?>
                        <th><a href="?<?= $url_prefix ?>&order=c.first_name">First Name</a></th>
                        <th><a href="?<?= $url_prefix ?>&order=c.last_name">Last Name</a></th>
                        <th><a href="?<?= $url_prefix ?>&order=date_of_birth">Date of Birth</a></th>
                        <th><a href="?<?= $url_prefix ?>&order=phone_number">Phone Number</a></th>
                        <th><a href="?<?= $url_prefix ?>&order=required_sessions">Sessions Required</a></th>
                        <th><a href="?<?= $url_prefix ?>&order=sessions_attended">Sessions Attended</a></th>
                        <th><a href="?<?= $url_prefix ?>&order=last_attended">Last Attended</a></th>
                        <th><a href="?<?= $url_prefix ?>&order=case_mgr">Case Manager</a></th>
                        <th><a href="?<?= $url_prefix ?>&order=group_name">Group</a></th>
                        <th><a href="?<?= $url_prefix ?>&order=note">Client Note</a></th>
                        <th><a href="?<?= $url_prefix ?>&order=orientation_date">Orientation Date</a></th>
                        <th>Select</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($count > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['first_name']) ?></td>
                                <td><?= htmlspecialchars($row['last_name']) ?></td>
                                <td><?= htmlspecialchars($row['date_of_birth']) ?></td>
                                <td><?= htmlspecialchars($row['phone_number']) ?></td>
                                <td><?= htmlspecialchars($row['required_sessions']) ?></td>
                                <td><?= htmlspecialchars($row['sessions_attended']) ?></td>
                                <td><?= htmlspecialchars($row['last_attended']) ?></td>
                                <td><?= htmlspecialchars($row['case_mgr']) ?></td>
                                <td><?= htmlspecialchars($row['group_name']) ?></td>
                                <td title="<?= htmlspecialchars($row['note']) ?>">
                                    <?= htmlspecialchars(mb_strimwidth($row['note'], 0, 80, ' ...')) ?>
                                </td>
                                <td><?= htmlspecialchars($row['orientation_date']) ?></td>
                                <td>
                                    <a class="btn btn-primary" 
                                       href="check_in_step4.php?therapy_group_id=<?= $therapy_group_id ?>&therapy_session_id=<?= $therapy_session_id ?>&client_id=<?= $row['id'] ?>">
                                       Check In
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php mysqli_free_result($result); ?>
        <?php else: ?>
            <p class="text-danger">ERROR: Could not execute query. <?= mysqli_error($link) ?></p>
        <?php endif; ?>

        <?php mysqli_close($link); ?>
    </div>
</section>

<!-- Your scripts: jQuery, Bootstrap, etc. -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
</body>
</html>
