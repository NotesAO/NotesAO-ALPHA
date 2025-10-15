<?php
include_once 'auth.php';
check_loggedin($con);

require_once "helpers.php";
require_once "sql_functions.php";

$therapy_group_id = "";
if (isset($_GET['therapy_group_id'])) {
    $therapy_group_id = $_GET['therapy_group_id'];
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $therapy_group_id = trim($_POST["therapy_group_id"]);
}
$therapy_group_id_err = "";

$search = "";
if (isset($_GET['search'])) {
    $search = $_GET['search'];
}
if (isset($_POST["search"])) {
    $search = trim($_POST["search"]);
}

$include_past_sessions = false;
if (isset($_GET['include_past_sessions'])) {
    $include_past_sessions = true;
}
if (isset($_POST["include_past_sessions"])) {
    $include_past_sessions = true;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Therapy Session Info</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0-14/css/all.min.css" integrity="sha512-YVm6dLGBSj6KG3uUb1L5m25JXXYrd9yQ1P7RKDSstzYiPxI2vYLCCGyfrlXw3YcN/EM3UJ/IAqsCmfdc6pk/Tg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style type="text/css">
        .page-header h2 {
            margin-top: 0;
        }

        table tr td:last-child a {
            margin-right: 5px;
        }

        body {
            font-size: 14px;
        }
    </style>
</head>

<body>
    <?php require_once('navbar.php'); ?>

    <section class="pt-3">
        <div class="container-fluid">
            <!--  Headder Row -->
            <div class="row bg-light">
                <div class="col page-header clearfix">
                    <h3 class="float-left">Session Check In - Select Session</h3>
                </div>
            </div>

            <form action="check_in_step1.php" method="get">
                <div class="row bg-light">
                    <div class="col-1">
                        <label for="therapy_group_id">Therapy Group</label>
                    </div>
                    <div class="col-4">
                        <select class="form-control" id="therapy_group_id" name="therapy_group_id">
                            <option value="" selected="selected">All Groups</option>
                            <?php
                            $groups = get_therapy_groups($_SESSION['program_id']);
                            foreach ($groups as $group) {
                                $value = htmlspecialchars($group["name"] . " - " . $group["address"]);
                                if ($group["id"] == $therapy_group_id) {
                                    echo '<option value="' . "$group[id]" . '"selected="selected">' . "$value" . '</option>';
                                } else {
                                    echo '<option value="' . "$group[id]" . '">' . "$value" . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <span class="form-text"><?php echo $therapy_group_id_err; ?></span>
                    </div>
                </div>

                <div class="row bg-light">
                    <div class="col-1">
                        <label for="include_past_sessions">Include past Sessions</label>
                    </div>
                    <div class="col-1">
                        <input class="form-check-input" type="checkbox" value="true" name="include_past_sessions" id="include_past_sessions" <?php if ($include_past_sessions) {
                                                                                                                                                    echo 'checked';
                                                                                                                                                } ?>>
                    </div>
                </div>
                <div class="row bg-light">
                    <div class="col">
                        <input type="submit" class="btn btn-info" value="Search">
                    </div>
                </div>

                <div class="row bg-light">
                    <div class="col">
                        <p></p>
                    </div>
                </div>
            </form>
            <?php
            // Include config file
            require_once "../config/config.php";
            require_once "helpers.php";

            // SELECT
            $select_clause = "SELECT
                    s.id id,
                    g.name group_name,
                    s.date session_date,
                    s.duration_minutes duration_minutes,
                    concat(f.first_name, ' ', f.last_name) facilitator,
                    c.short_description curriculum,
                    ifnull(attendance_count.num_attended, 0) num_attended,
                    s.note session_note
                FROM
                    therapy_session s
                INNER JOIN therapy_group g ON
                    s.therapy_group_id = g.id and g.program_id = " . $_SESSION['program_id'] . "
                LEFT OUTER JOIN facilitator f ON
                    s.facilitator_id = f.id
                LEFT OUTER JOIN curriculum c ON
                    s.curriculum_id = c.id
                LEFT OUTER JOIN (SELECT therapy_session_id, count(therapy_session_id) num_attended from attendance_record group by therapy_session_id) as attendance_count ON
                    s.id = attendance_count.therapy_session_id
                    ";

            // WHERE
            $where_clause = " WHERE 1=1 ";
            if (!$include_past_sessions) {
                $where_clause .= " and s.date > timestamp(current_date)";
            }
            if ($therapy_group_id != "") {
                $where_clause .= " and s.therapy_group_id = $therapy_group_id";
            }

            // ORDER BY
            $order_clause = " ORDER by s.date ASC";
            $sql = $select_clause . $where_clause . $order_clause;

            echo '<div class="row"><div class="col">';  // Data Table Div

            if ($result = mysqli_query($link, $sql)) {
                echo "<p class='lead'><em>" . mysqli_num_rows($result) . " records found</em></p>";
                if (mysqli_num_rows($result) > 0) {
                    echo "<table class='table table-bordered table-striped'>";
                    echo "<thead>";
                    echo "<tr>";
                    echo "<th>Group Name</th>";
                    echo "<th>Session Date</th>";
                    echo "<th>Duration</th>";
                    echo "<th>Facilitator</th>";
                    echo "<th>Curriculum</th>";
                    echo "<th>Attendance</th>";
                    echo "<th>Session Note</th>";
                    echo "<th>Action</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody>";
                    while ($row = mysqli_fetch_array($result)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['group_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['session_date']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['duration_minutes']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['facilitator']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['curriculum']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['num_attended']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['session_note']) . "</td>";
                        echo "<td>";
                        echo "<a href='check_in_step3.php?therapy_session_id=" . $row['id'] . "' title='Check In' data-toggle='tooltip' class='btn btn-primary'>Select Session</a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    echo "</tbody>";
                    echo "</table>";
            ?>
            <?php
                    // Free result set
                    mysqli_free_result($result);
                }
            } else {
                echo "ERROR: Could not able to execute $sql. " . mysqli_error($link);
            }
            // Close connection
            mysqli_close($link);
            ?>

        </div>


        </div>
        </form>
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