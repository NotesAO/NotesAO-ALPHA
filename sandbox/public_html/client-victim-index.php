<?php
include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';

$search = $_GET['search'] ?? '';
$order = $_GET['order'] ?? 'v.name';
$sort = $_GET['sort'] ?? 'asc';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NotesAO – Victim Index</title>
  <link rel="icon" href="/favicons/favicon.ico">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <style>
    body { padding-top: 56px; font-size: 16px; }
    table tr td:last-child a { margin-right: 5px; }
  </style>
</head>

<body>
<?php require_once 'navbar.php'; ?>

<section class="pt-5">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <div class="page-header clearfix">
          <h2 class="float-left">Victim Listing</h2>
          <a href="client-victim-index.php" class="btn btn-info float-right">Reset View</a>
          <a href="home.php" class="btn btn-secondary float-right mr-2">Home</a>
        </div>

        <form method="get" action="client-victim-index.php">
          <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
          <div class="row mb-3">
            <div class="col-3">
              <small class="text-muted">Quick Search</small>
              <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search this table">
            </div>
            <div class="col-2 align-self-end">
              <input type="submit" class="btn btn-primary" value="Search">
            </div>
          </div>
        </form>

        <?php
        $sql = "SELECT v.id, v.client_id, v.name, v.relationship, v.gender,
                       v.living_with_client, v.address_line1, v.address_line2,
                       v.city, v.state, v.zip
                FROM victim v";

        if (!empty($search)) {
          $search_escaped = mysqli_real_escape_string($con, $search);
          $sql .= " WHERE CONCAT_WS(' ', v.name, v.relationship, v.gender, v.city, v.state, v.zip) LIKE '%$search_escaped%'";
        }

        $allowed_order = ['v.name','v.relationship','v.gender','v.city','v.state','v.zip'];
        if (!in_array($order, $allowed_order)) $order = 'v.name';
        $sort = strtolower($sort) === 'desc' ? 'desc' : 'asc';

        $sql .= " ORDER BY $order $sort";

        $toggle_sort = $sort === 'asc' ? 'desc' : 'asc';
        $url_prefix = "search=" . urlencode($search) . "&sort=$toggle_sort";
        
        if ($result = mysqli_query($con, $sql)) {
          echo "<table class='table table-bordered table-striped'>";
          echo "<thead>";
          echo "<tr>";
          echo "<th><a href='?$url_prefix&order=v.name'>Victim Name</a></th>";
          echo "<th><a href='?$url_prefix&order=v.relationship'>Relationship</a></th>";
          echo "<th><a href='?$url_prefix&order=v.gender'>Gender</a></th>";
          echo "<th>Living with Client?</th>";
          echo "<th><a href='?$url_prefix&order=v.city'>City</a></th>";
          echo "<th><a href='?$url_prefix&order=v.state'>State</a></th>";
          echo "<th><a href='?$url_prefix&order=v.zip'>ZIP</a></th>";
          echo "<th>Address</th>";
          echo "<th>Action</th>";
          echo "</tr></thead><tbody>";

          if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
              $address = htmlspecialchars($row['address_line1'] ?? '');
              if (!empty($row['address_line2'])) {
                $address .= "<br>" . htmlspecialchars($row['address_line2']);
              }

              echo "<tr>";
              echo "<td>" . htmlspecialchars($row['name']) . "</td>";
              echo "<td>" . htmlspecialchars($row['relationship']) . "</td>";
              echo "<td>" . htmlspecialchars($row['gender']) . "</td>";
              echo "<td>" . ($row['living_with_client'] ? 'Yes' : 'No') . "</td>";
              echo "<td>" . htmlspecialchars($row['city']) . "</td>";
              echo "<td>" . htmlspecialchars($row['state']) . "</td>";
              echo "<td>" . htmlspecialchars($row['zip']) . "</td>";
              echo "<td>$address</td>";
              echo "<td><a href='client-victim.php?client_id=" . $row['client_id'] . "' title='View Victims' class='btn btn-sm btn-info'><i class='fas fa-eye'></i></a></td>";
              echo "</tr>";
            }
          } else {
            echo "<tr><td colspan='9' class='text-center'>No victim records found.</td></tr>";
          }

          echo "</tbody></table>";
          mysqli_free_result($result);
        } else {
          echo "<div class='alert alert-danger'>Database error: " . mysqli_error($con) . "</div>";
        }

        mysqli_close($con);
        ?>
      </div>
    </div>
  </div>
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.bundle.min.js"></script>
<script>
  $(document).ready(function() {
    $('[data-toggle="tooltip"]').tooltip();
  });
</script>
</body>
</html>
