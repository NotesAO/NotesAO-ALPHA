<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";

$link = isset($link) ? $link : $con;
$program_id = (int)($_SESSION['program_id'] ?? 0);

$include_exits = isset($_GET['include_exits']) ? $_GET['include_exits'] : "not_exited";
$search = !empty($_GET['search']) ? ($_GET['search']) : "";

/* ---------- helpers ---------- */
function table_exists(mysqli $db, string $table): bool {
    $t = $db->real_escape_string($table);
    $q = $db->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' LIMIT 1");
    return $q && $q->num_rows > 0;
}
function column_exists(mysqli $db, string $table, string $col): bool {
    $t = $db->real_escape_string($table);
    $c = $db->real_escape_string($col);
    $q = $db->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1");
    return $q && $q->num_rows > 0;
}
function is_milestone_program(mysqli $db, int $program_id): bool {
    if (!table_exists($db,'program') || !column_exists($db,'program','uses_milestones')) return false;
    $res = $db->query("SELECT uses_milestones FROM program WHERE id=$program_id");
    if ($res && ($r=$res->fetch_assoc())) return (int)$r['uses_milestones'] === 1;
    return false;
}
$MILESTONE_MODE = is_milestone_program($link,$program_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Client Index</title>

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

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0-14/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style type="text/css">
        table tr td:last-child a { margin-right: 5px; }
        .sticky-toolbar { position: sticky; top: 0; z-index: 20; background: #fff; padding: .75rem; border: 1px solid #e9ecef; border-radius: .25rem; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<?php require_once('navbar.php'); ?>

<body>
<section class="pt-5">
<div class="container-fluid">
<div class="row">
<div class="col">
    <div class="page-header clearfix">
        <h2 class="float-left">Client Listing</h2>
        <a href="client-create.php" class="btn btn-success float-right">Add New Client</a>
        <a href="client-index.php" class="btn btn-info float-right mr-2">Reset View</a>
        <a href="home.php" class="btn btn-secondary float-right mr-2">Home</a>
    </div>

    <?php if (!empty($_GET['mc_ok'])): ?>
        <div class="alert alert-success">Milestone check-in completed.</div>
    <?php endif; if (!empty($_GET['mc_err'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['mc_err']); ?></div>
    <?php endif; ?>

    <?php
    $orderBy = array('c.first_name', 'c.last_name', 'date_of_birth', 'phone_number', 'required_sessions', 'sessions_attended', 'case_mgr', 'note', 'orientation_date', 'exit_date', 'stage_of_change', 'group_name', 'last_attended');
    $order = 'c.last_name';
    if (isset($_GET['order']) && in_array($_GET['order'], $orderBy)) { $order = $_GET['order']; }

    $sortBy = array('asc', 'desc');
    $sort = 'asc';
    if (isset($_GET['sort']) && in_array($_GET['sort'], $sortBy)) { $sort = $_GET['sort']; }
    ?>

    <form action="client-index.php" method="get">
        <input type="hidden" id="order" name="order" value="<?php echo htmlspecialchars($order); ?>" />
        <input type="hidden" id="sort"  name="sort"  value="<?php echo htmlspecialchars($sort); ?>" />

        <div class="row">
            <div class="col-2">
                <small class="text-muted">Quick Search</small>
                <input type="text" class="form-control" placeholder="Search this table" name="search" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-2">
                <small class="text-muted">Include Exited</small>
                <select class="form-control" id="include_exits" name="include_exits">
                    <option value="not_exited" <?php if ($include_exits == "not_exited") echo "selected='selected'"; ?>>Not Exited</option>
                    <option value="exited"     <?php if ($include_exits == "exited")     echo "selected='selected'"; ?>>Exited</option>
                    <option value="both"       <?php if ($include_exits == "both")       echo "selected='selected'"; ?>>Both exited and not-exited</option>
                </select>
            </div>
            <div class="col-2 align-self-end">
                <input type="submit" class="btn btn-primary" value="Search">
            </div>
        </div>
    </form>
    <br>

    <?php if ($MILESTONE_MODE): ?>
    <!-- Milestone mass check-in toolbar -->
    <form id="massCheckinForm" method="post" action="mass_checkin.php">
        <div class="sticky-toolbar mb-3">
            <div class="form-row align-items-end">
                <div class="col-auto">
                    <label class="small text-muted mb-1">Check-in Date</label>
                    <input type="date" class="form-control" name="checkin_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-auto">
                    <label class="small text-muted mb-1">Note (optional)</label>
                    <input type="text" class="form-control" name="note" placeholder="Milestone day">
                </div>
                <div class="col-auto">
                    <input type="hidden" name="program_id" value="<?php echo (int)$program_id; ?>">
                    <button type="submit" class="btn btn-success">Check in selected (full day)</button>
                </div>
                <div class="col-auto">
                    <div class="text-muted small">Advances Day → Lessons per client and writes attendance + tags.</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $sql = "SELECT c.id, c.first_name, c.last_name, c.date_of_birth, c.phone_number, tg.name group_name, c.orientation_date,
            c.exit_date, c.required_sessions, cs.stage stage_of_change, c.note, sessions_attended, last_attended,
            CONCAT(cm.first_name, ' ', cm.last_name) case_mgr
            FROM client c
            LEFT JOIN case_manager cm ON c.case_manager_id = cm.id
            LEFT JOIN therapy_group tg ON c.therapy_group_id = tg.id
            LEFT OUTER JOIN client_stage cs ON c.client_stage_id = cs.id
            LEFT OUTER JOIN (SELECT ar.client_id client_id, COUNT(ar.client_id) sessions_attended FROM attendance_record ar GROUP BY ar.client_id) AS client_total_attendance
                ON c.id = client_total_attendance.client_id
            LEFT OUTER JOIN (
                SELECT ar.client_id client_id, DATE_FORMAT(MAX(ts.date), '%Y-%m-%d') last_attended
                FROM attendance_record ar LEFT JOIN therapy_session ts ON ar.therapy_session_id = ts.id
                GROUP BY ar.client_id
            ) AS client_last_attendance ON c.id = client_last_attendance.client_id
            WHERE c.program_id = $program_id";

    if ($include_exits == 'not_exited') {
        $sql .= " AND c.exit_date IS NULL";
    } else if ($include_exits  == 'exited') {
        $sql .= " AND c.exit_date IS NOT NULL";
    }

    if (!empty($search)) {
        $s = mysqli_real_escape_string($link, $search);
        $sql .= " AND CONCAT_WS (c.first_name,c.last_name,date_of_birth,c.phone_number,note,tg.name,concat(cm.first_name, ' ', cm.last_name),tg.name) LIKE '%$s%'";
    }

    if (!empty($order)) {
        $sql .= " ORDER BY $order $sort";
    }

    if ($result = mysqli_query($link, $sql)) {
        $sort = ($sort === 'asc') ? 'desc' : 'asc';
        $url_prefix = "search=".urlencode($search)."&include_exits=$include_exits&sort=$sort";

        echo "<table class='table table-bordered table-striped'>";
        echo   "<thead><tr>";
        if ($MILESTONE_MODE) echo "<th class='nowrap'><input type='checkbox' id='select_all'> Select</th>";
        echo     "<th><a href=\"?$url_prefix&order=c.first_name\">First&nbsp;Name</a></th>";
        echo     "<th><a href=\"?$url_prefix&order=c.last_name\">Last&nbsp;Name</a></th>";
        echo     "<th><a href=\"?$url_prefix&order=date_of_birth\">Date&nbsp;of&nbsp;Birth</a></th>";
        echo     "<th><a href=\"?$url_prefix&order=phone_number\">Phone&nbsp;Number</a></th>";
        echo     "<th><a href=\"?$url_prefix&order=last_attended\">Last&nbsp;Attended</a></th>";
        echo     "<th><a href=\"?$url_prefix&order=sessions_attended\">Attended</a></th>";
        echo     "<th><a href=\"?$url_prefix&order=required_sessions\">Required&nbsp;Groups</a></th>";
        echo     "<th><a href=\"?$url_prefix&order=stage_of_change\">Stage&nbsp;of&nbsp;Change</a></th>";
        echo     "<th><a href=\"?$url_prefix&order=case_mgr\">Case&nbsp;Manager</a></th>";
        echo     "<th><a href=\"?$url_prefix&order=group_name\">Group</a></th>";
        echo     "<th><a href=\"?$url_prefix&order=orientation_date\">Orientation</a></th>";
        echo     "<th><a href=\"?$url_prefix&order=exit_date\">Exit&nbsp;Date</a></th>";
        echo     "<th>Actions</th>";
        echo   "</tr></thead><tbody>";

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_array($result)) {
                $dob     = $row['date_of_birth'] ? date('m/d/Y', strtotime($row['date_of_birth'])) : '';
                $orient  = ($row['orientation_date'] && $row['orientation_date'] !== '0000-00-00') ? date('m/d/Y', strtotime($row['orientation_date'])) : '';
                $exit    = $row['exit_date'] ? date('m/d/Y', strtotime($row['exit_date'])) : '';
                $lastAtt = $row['last_attended'] ? date('m/d/Y', strtotime($row['last_attended'])) : '';

                echo "<tr>";
                if ($MILESTONE_MODE) {
                    echo "<td class='text-center'><input type='checkbox' class='row_ck' name='client_ids[]' value='".(int)$row['id']."'></td>";
                }
                echo   "<td>" . htmlspecialchars($row['first_name']        ?? '') . "</td>";
                echo   "<td>" . htmlspecialchars($row['last_name']         ?? '') . "</td>";
                echo   "<td>" . htmlspecialchars($dob)                         . "</td>";
                echo   "<td>" . htmlspecialchars($row['phone_number']      ?? '') . "</td>";
                echo   "<td>" . htmlspecialchars($lastAtt)                     . "</td>";
                echo   "<td>" . htmlspecialchars($row['sessions_attended'] ?? '') . "</td>";
                echo   "<td>" . htmlspecialchars($row['required_sessions'] ?? '') . "</td>";
                echo   "<td>" . htmlspecialchars($row['stage_of_change']   ?? '') . "</td>";
                echo   "<td>" . htmlspecialchars($row['case_mgr']          ?? '') . "</td>";
                echo   "<td>" . htmlspecialchars($row['group_name']        ?? '') . "</td>";
                echo   "<td>" . htmlspecialchars($orient) . "</td>";
                echo   "<td>" . htmlspecialchars($exit)   . "</td>";
                echo   "<td>";
                echo     "<a href='client-review.php?client_id={$row['id']}' title='Summary'><i class='far fa-eye'></i></a>";
                echo     "<a href='client-update.php?id={$row['id']}'       title='Update'><i class='far fa-edit'></i></a>";
                echo   "</td>";
                echo "</tr>";
            }
            mysqli_free_result($result);
        }

        echo "</tbody></table>";

    } else {
        echo "ERROR: Could not execute query. " . mysqli_error($link);
    }

    mysqli_close($link);
    ?>
    <?php if ($MILESTONE_MODE): ?></form><?php endif; ?>
</div>
</div>
</div>
</section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" crossorigin="anonymous"></script>
<script type="text/javascript">
    $(function(){
        $('#select_all').on('change', function(){ $('.row_ck').prop('checked', this.checked); });
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>
</body>
</html>
