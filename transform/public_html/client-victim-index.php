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
        $sql = "
            SELECT v.id,
                  v.client_id,
                  v.name,
                  v.relationship_to_victim AS relationship,
                  g.gender,
                  v.living_with_client
            FROM   victim v
            LEFT   JOIN gender g ON g.id = v.victim_gender_id
        ";

        /* quick search */
        if ($search !== '') {
            $s  = mysqli_real_escape_string($con, $search);
            $sql .= "
                WHERE CONCAT_WS(' ', v.name,
                                    v.relationship_to_victim,
                                    g.gender)
                      LIKE '%$s%'
            ";
        }

        /* valid order-by columns */
        $allowed_order = ['v.name', 'relationship', 'gender'];
        if (!in_array($order, $allowed_order, true)) $order = 'v.name';
        $sort = strtolower($sort) === 'desc' ? 'desc' : 'asc';
        $sql .= " ORDER BY $order $sort";

        /* links */
        $toggle_sort = $sort === 'asc' ? 'desc' : 'asc';
        $url_prefix  = "search=" . urlencode($search) . "&sort=$toggle_sort";

        
        /* ------------------------------------------------------------------
          *  Execute the query and render the result table
          * ---------------------------------------------------------------- */
          if ($result = mysqli_query($con, $sql)) {

              echo '<table class="table table-bordered table-striped">';
              echo '<thead><tr>';

              /* ---- header cells with sort links --------------------------- */
              // each link **must** use a value that also exists in $allowed_order
              echo '<th><a href="?'.$url_prefix.'&order=v.name">Victim&nbsp;Name</a></th>';
              echo '<th><a href="?'.$url_prefix.'&order=relationship">Relationship</a></th>';
              echo '<th><a href="?'.$url_prefix.'&order=g.gender">Gender</a></th>';
              echo '<th>Living&nbsp;with&nbsp;Client?</th>';
              echo '<th>Action</th>';

              echo '</tr></thead><tbody>';

              /* ---- data rows --------------------------------------------- */
              if (mysqli_num_rows($result) > 0) {
                  while ($row = mysqli_fetch_assoc($result)) {

                      echo '<tr>';
                      echo '<td>'.htmlspecialchars($row['name']).'</td>';
                      echo '<td>'.htmlspecialchars($row['relationship']).'</td>';
                      echo '<td>'.htmlspecialchars($row['gender']).'</td>';
                      echo '<td>'.($row['living_with_client'] ? 'Yes' : 'No').'</td>';
                      echo '<td>
                              <a class="btn btn-sm btn-info"
                                href="client-victim.php?client_id='.(int)$row['client_id'].'"
                                title="View Victims">
                                  <i class="fas fa-eye"></i>
                              </a>
                            </td>';
                      echo '</tr>';
                  }
              } else {
                  echo '<tr><td colspan="5" class="text-center">
                          No victim records found.
                        </td></tr>';
              }

              echo '</tbody></table>';
              mysqli_free_result($result);

          } else {
              /* the query failed – show the SQL error so you know why */
              echo '<div class="alert alert-danger">Database error: '
                  .htmlspecialchars(mysqli_error($con)).
                  '</div>';
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
