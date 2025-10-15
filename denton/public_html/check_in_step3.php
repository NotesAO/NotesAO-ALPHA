<?php
include_once 'auth.php';
check_loggedin($con);
require_once "helpers.php";
require_once "sql_functions.php";

$program_id = $_SESSION['program_id'];

$therapy_session_id = "";
if (isset($_GET['therapy_session_id'])) {
    $therapy_session_id = $_GET['therapy_session_id'];
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $therapy_session_id = trim($_POST["therapy_session_id"]);
}
$therapy_session_id_err = "";
$therapy_group_id = "";

$search = "";
if (isset($_GET['search'])) {
    $search = $_GET['search'];
}
if (isset($_POST["search"])) {
    $search = trim($_POST["search"]);
}

$include_othergroup = false;
if (isset($_GET['include_othergroup'])) {
    $include_othergroup = true;
}
if (isset($_POST["include_othergroup"])) {
    $include_othergroup = true;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>TherapyTrack - Client Check In</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0-14/css/all.min.css" integrity="sha512-YVm6dLGBSj6KG3uUb1L5m25JXXYrd9yQ1P7RKDSstzYiPxI2vYLCCGyfrlXw3YcN/EM3UJ/IAqsCmfdc6pk/Tg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>

<body>
    <?php require_once('navbar.php'); ?>

    <section class="pt-3">
        <div class="container-fluid">
            <!--  Headder Row -->
            <div class="row bg-light">
                <div class="col page-header clearfix">
                    <h2 class="float-left">Client Check In</h2>
                </div>
                <div class="col">
                    <a href="check_in_step1.php" class="btn btn-primary float-right">Change Session</a>
                </div>
            </div>

            <div class="row bg-light">
                <div class="col">
                    <?php
                    $sessionInfo = get_therapy_session_info($therapy_session_id);
                    if (isset($sessionInfo)) {
                        $therapy_group_id = $sessionInfo['therapy_group_id'];
                        $value = $sessionInfo['group_name'] . " - " . $sessionInfo['group_address'];
                        echo '<p class="h4">' . htmlspecialchars($value) . '</p>';
                        $value = $sessionInfo['weekday'] . " " . $sessionInfo['date'] . " - " . $sessionInfo['facilitator'];
                        echo '<p class="h4">' . htmlspecialchars($value) . '</p>';
                    } else {
                        echo '<div class="alert alert-warning" role="alert"> Session not found
                        <a href="check_in_step1.php" class="btn btn-primary float-right">Return to Step 1</a>
                        </div>';
                        exit();
                    }
                    ?>
                </div>
            </div>

            <?php
                $orderBy = array('c.first_name', 'c.last_name', 'c.date_of_birth', 'c.phone_number', 'required_sessions', 'sessions_attended', 'case_mgr', 'note', 'orientation_date');
                $order = 'c.last_name';
                if (isset($_GET['order']) && in_array($_GET['order'], $orderBy)) {
                    $order = $_GET['order'];
                }

                //Column sort order
                $sortBy = array('asc', 'desc');
                $sort = 'asc';
                if (isset($_GET['sort']) && in_array($_GET['sort'], $sortBy)) {
                    $sort = $_GET['sort'];
                }
            ?>
            <form action="?" method="get">
                <input type="hidden" id="therapy_session_id" name="therapy_session_id" value="<?php echo $therapy_session_id ?>" />
                <input type="hidden" id="sort" name="sort" value="<?php echo $sort ?>" />
                <input type="hidden" id="order" name="order" value="<?php echo $order ?>" />

                <div class="row bg-light">
                    <div class="col-auto">
                        <small class="text-muted">Search Criteria</small>
                        <input type="text" class="form-control" placeholder="Search Criteria" name="search" <?php if (isset($search)) {
                                                                                                                echo 'value="' . "$search" . '"';
                                                                                                            } ?>>
                    </div>
                    <div class="col-auto align-self-end">
                        <input class="form-check-input" type="checkbox" value="true" name="include_othergroup" id="include_othergroup" <?php if ($include_othergroup) {
                                                                                                                                            echo 'checked';
                                                                                                                                        } ?>>
                        <label>
                            <h5 class="float-left">Include clients from other groups</h3>
                        </label>
                    </div>
                    <div class="col align-self-end">
                        <input type="submit" class="btn btn-info" value="Search">
                    </div>
                </div>

                <div class="row bg-light">
                    <div class="col">
                        <label>You can search by first name, last name, phone number, or date of birth and partial values are accepted. (case insensitive)</label>
                    </div>
                </div>
            </form>
            <?php
            // Include config file
            require_once "../config/config.php";
            require_once "helpers.php";


            $sql = "SELECT c.id, c.first_name, c.last_name, c.date_of_birth, c.phone_number, tg.name group_name, c.orientation_date,
            c.exit_date, c.required_sessions, c.note, sessions_attended, last_attended, concat(cm.first_name, ' ', cm.last_name) case_mgr
            from client c 
            LEFT JOIN case_manager cm ON c.case_manager_id = cm.id
            LEFT JOIN therapy_group tg ON c.therapy_group_id = tg.id
            LEFT OUTER JOIN (select ar.client_id client_id, count(ar.client_id) sessions_attended from attendance_record ar group by ar.client_id) as client_total_attendance ON 
            c.id = client_total_attendance.client_id
            LEFT OUTER JOIN (select ar.client_id client_id, max(ts.date) last_attended from attendance_record ar left join therapy_session ts on ar.therapy_session_id = ts.id group by ar.client_id) as client_last_attendance ON 
            c.id = client_last_attendance.client_id
            WHERE c.program_id = $program_id";
            $sql .= " and exit_date is null";

            if (!$include_othergroup) {
                $sql .= " and c.therapy_group_id = $therapy_group_id";
            }

            if (!empty($search)) {
                $sql .= " and CONCAT_WS (c.first_name,c.last_name,date_of_birth,c.phone_number,note,concat(cm.first_name, ' ', cm.last_name),tg.name) LIKE '%$search%'";
            } else {
                $search = "";
            }

            if (!empty($order)) {
                $sql .= " ORDER BY $order $sort";
            }

            if ($result = mysqli_query($link, $sql)) {
                echo "<p class='lead'><em>" . mysqli_num_rows($result) . " records found</em></p>";

                echo "<table class='table table-bordered table-striped'>";
                echo "<thead>";
                echo "<tr>";

                // Toggle sort direction
                if ($sort == 'asc') {
                    $sort = 'desc';
                } else {
                    $sort = 'asc';
                }

                if($include_othergroup) {
                    $url_prefix="therapy_session_id=$therapy_session_id&search=$search&include_othergroup=$include_othergroup&sort=$sort";
                }
                else {
                    $url_prefix="therapy_session_id=$therapy_session_id&search=$search&sort=$sort";
                }

                echo "<th><a href=?$url_prefix&order=c.first_name>First Name</th>";
                echo "<th><a href=?$url_prefix&order=c.last_name>Last Name</th>";
                echo "<th><a href=?$url_prefix&order=date_of_birth>Date of Birth</th>";
                echo "<th><a href=?$url_prefix&order=phone_number>Phone Number</th>";
                echo "<th><a href=?$url_prefix&order=required_sessions>Sessions Required</th>";
                echo "<th><a href=?$url_prefix&order=sessions_attended>Sessions Attended</th>";
                echo "<th><a href=?$url_prefix&order=last_attended>Last Attended</th>";
                echo "<th><a href=?$url_prefix&order=case_mgr>Case Manager</th>";
                echo "<th><a href=?$url_prefix&order=group_name>Group</th>";
                echo "<th><a href=?$url_prefix&order=note>Client Note</th>";
                echo "<th><a href=?$url_prefix&order=orientation_date>Orientation Date</th>";
                echo "<th>Select</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";
                if (mysqli_num_rows($result) > 0) {
                    while ($row = mysqli_fetch_array($result)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['date_of_birth']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['phone_number']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['required_sessions']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['sessions_attended']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['last_attended']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['case_mgr']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['group_name']) . "</td>";
                        echo "<td title='" . htmlspecialchars($row['note']) . "'>" . htmlspecialchars(mb_strimwidth($row['note'], 0, 80, ' ...')) . "</td>";
                        echo "<td>" . htmlspecialchars($row['orientation_date']) . "</td>";
                        echo "<td>";
                        echo "<a href='check_in_step4.php?therapy_group_id=$therapy_group_id&therapy_session_id=$therapy_session_id&client_id=" . $row['id'] . "' title='Check In' data-toggle='tooltip' class='btn btn-primary'>Check In</a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    mysqli_free_result($result);
                }
                echo "</tbody>";
                echo "</table>";
            } else {
                echo "ERROR: Could not able to execute $sql. " . mysqli_error($link);
            }

            // Close connection
            mysqli_close($link);
            ?>
        </div>
    </section>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>

</html>