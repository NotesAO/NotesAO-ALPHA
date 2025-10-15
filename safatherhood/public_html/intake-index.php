<?php
include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';

// ------------------------------------------------------------------
// Session: current program constraint
// ------------------------------------------------------------------
$program_id = $_SESSION['program_id'] ?? null; // required for filtering
$program_name = $_SESSION['program_name'] ?? '';

// ------------------------------------------------------------------
// Filters
// ------------------------------------------------------------------
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$verified = isset($_GET['verified']) ? $_GET['verified'] : 'all';     // all|yes|no
$imported = isset($_GET['imported']) ? $_GET['imported'] : 'all';     // all|yes|no

// Sorting (removed email/phone/program from allowed order fields)
$orderByWhitelist = [
  'created_at', 'first_name', 'last_name', 'date_of_birth',
  'referral_type_id', 'packet_complete', 'staff_verified', 'imported_to_client'
];
$order = (isset($_GET['order']) && in_array($_GET['order'], $orderByWhitelist, true))
  ? $_GET['order'] : 'created_at';
$sortBy = ['asc','desc'];
$sort = (isset($_GET['sort']) && in_array($_GET['sort'], $sortBy, true))
  ? $_GET['sort'] : 'desc'; // newest first

// Referral labels (for display only)
$REFERRAL_MAP = [
  0 => 'Other',
  1 => 'Probation',
  2 => 'Parole',
  3 => 'Pretrial',
  4 => 'CPS',
  5 => 'Attorney',
  6 => 'VTC',
];

// Convenience escape (if helpers.php didn’t define h())
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NotesAO - Intake Index</title>

  <!-- FAVICON LINKS (match client-index.php) -->
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

  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
        integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk"
        crossorigin="anonymous">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0-14/css/all.min.css"
        integrity="sha512-YVm6dLGBSj6KG3uUb1L5m25JXXYrd9yQ1P7RKDSstzYiPxI2vYLCCGyfrlXw3YcN/EM3UJ/IAqsCmfdc6pk/Tg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    table tr td:last-child a { margin-right: 8px; }
    .table td { vertical-align: middle; }
  </style>
</head>
<?php require_once('navbar.php'); ?>
<body>
<section class="pt-5">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <div class="page-header clearfix">
          <h2 class="float-left">
            Intake Listing
            
          </h2>
          <a href="intake-index.php" class="btn btn-info float-right">Reset View</a>
          <a href="home.php" class="btn btn-secondary float-right mr-2">Home</a>
        </div>

        <?php if (!$program_id): ?>
          <div class="alert alert-warning mt-3">
            No current program found in session. Showing all intakes. (Select a program from Program Selection to filter.)
          </div>
        <?php endif; ?>

        <?php
        // ------------------------------------------------------------------
        // Build SQL
        // ------------------------------------------------------------------
        $where = [];

  

        // Verified / Imported toggles
        if ($verified === 'yes') $where[] = "staff_verified = 1";
        if ($verified === 'no')  $where[] = "staff_verified = 0";
        if ($imported === 'yes') $where[] = "imported_to_client = 1";
        if ($imported === 'no')  $where[] = "imported_to_client = 0";

        $sql = "SELECT 
                  intake_id,
                  created_at,
                  first_name,
                  last_name,
                  date_of_birth,
                  referral_type_id,
                  packet_complete,
                  staff_verified,
                  verified_by,
                  verified_at,
                  imported_to_client,
                  imported_client_id,
                  email,
                  phone_cell,
                  program_id
                FROM intake_packet";

        // Search (keeps email/phone searchable even though we hid columns)
        if ($search !== '') {
          $s = mysqli_real_escape_string($link, $search);
          $like = "'%$s%'";
          $where[] =
            "(first_name LIKE $like OR
              last_name LIKE $like OR
              email LIKE $like OR
              phone_cell LIKE $like OR
              CAST(referral_type_id AS CHAR) LIKE $like)";
        }

        if (!empty($where)) {
          $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY $order $sort";

        $result = mysqli_query($link, $sql);
        if (!$result) {
          echo "<div class='alert alert-danger'>ERROR: ".h(mysqli_error($link))."</div>";
        }

        // Flip sort for header links
        $nextSort = ($sort === 'asc') ? 'desc' : 'asc';

        // Header link prefix
        $url_prefix = "search=".urlencode($search)
                    ."&verified=".urlencode($verified)
                    ."&imported=".urlencode($imported)
                    ."&sort=".$nextSort;
        ?>

        <form action="intake-index.php" method="get" class="mt-3">
          <input type="hidden" id="order" name="order" value="<?php echo h($order); ?>">
          <input type="hidden" id="sort" name="sort" value="<?php echo h($sort); ?>">
          <div class="form-row">
            <div class="col-sm-3 mb-2">
              <small class="text-muted">Quick Search</small>
              <input type="text" class="form-control" name="search" placeholder="Name, email, phone..."
                     value="<?php echo h($search); ?>">
            </div>
            <div class="col-sm-2 mb-2">
              <small class="text-muted">Verified</small>
              <select class="form-control" name="verified">
                <option value="all" <?php if($verified==='all') echo "selected"; ?>>All</option>
                <option value="yes" <?php if($verified==='yes') echo "selected"; ?>>Verified</option>
                <option value="no"  <?php if($verified==='no')  echo "selected"; ?>>Unverified</option>
              </select>
            </div>
            <div class="col-sm-2 mb-2">
              <small class="text-muted">Imported</small>
              <select class="form-control" name="imported">
                <option value="all" <?php if($imported==='all') echo "selected"; ?>>All</option>
                <option value="yes" <?php if($imported==='yes') echo "selected"; ?>>Imported</option>
                <option value="no"  <?php if($imported==='no')  echo "selected"; ?>>Not Imported</option>
              </select>
            </div>
            <div class="col-sm-2 mb-2 align-self-end">
              <button type="submit" class="btn btn-primary">Search</button>
            </div>
          </div>
        </form>

        <div class="table-responsive mt-3">
          <table class="table table-bordered table-striped">
            <thead>
            <tr>
              <th><a href="?<?php echo $url_prefix; ?>&order=created_at">Submitted</a></th>
              <th><a href="?<?php echo $url_prefix; ?>&order=first_name">First Name</a></th>
              <th><a href="?<?php echo $url_prefix; ?>&order=last_name">Last Name</a></th>
              <th><a href="?<?php echo $url_prefix; ?>&order=date_of_birth">DOB</a></th>
              <th><a href="?<?php echo $url_prefix; ?>&order=referral_type_id">Referral</a></th>
              <th><a href="?<?php echo $url_prefix; ?>&order=packet_complete">Complete</a></th>
              <th><a href="?<?php echo $url_prefix; ?>&order=staff_verified">Verified</a></th>
              <th><a href="?<?php echo $url_prefix; ?>&order=imported_to_client">Imported</a></th>
              <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php
            if ($result && mysqli_num_rows($result) > 0):
              while ($r = mysqli_fetch_assoc($result)):
                // Formats
                $createdFmt = '';
                if (!empty($r['created_at'])) {
                  $ts = strtotime($r['created_at']);
                  $createdFmt = $ts ? date('m/d/Y h:i A', $ts) : h($r['created_at']);
                }
                $dobFmt = '';
                if (!empty($r['date_of_birth']) && $r['date_of_birth'] !== '0000-00-00') {
                  $td = strtotime($r['date_of_birth']);
                  $dobFmt = $td ? date('m/d/Y', $td) : h($r['date_of_birth']);
                }

                // New badge (7 days)
                $isNew = false;
                if (!empty($r['created_at'])) {
                  $isNew = (time() - strtotime($r['created_at'])) < (7*24*60*60);
                }

                // Referral label
                $referralLabel = isset($REFERRAL_MAP[(int)$r['referral_type_id']]) ? $REFERRAL_MAP[(int)$r['referral_type_id']] : $r['referral_type_id'];
            ?>
            <tr>
              <td>
                <?php echo h($createdFmt); ?>
                <?php if ($isNew): ?><span class="badge badge-warning ml-1">NEW</span><?php endif; ?>
              </td>
              <td><?php echo h($r['first_name']); ?></td>
              <td><?php echo h($r['last_name']); ?></td>
              <td><?php echo h($dobFmt); ?></td>
              <td><?php echo h($referralLabel); ?></td>

              <td>
                <?php if ((int)$r['packet_complete'] === 1): ?>
                  <span class="badge badge-success">Complete</span>
                <?php else: ?>
                  <span class="badge badge-secondary">Incomplete</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ((int)$r['staff_verified'] === 1): ?>
                  <span class="badge badge-primary" title="<?php
                    $vb = trim(($r['verified_by'] ?? ''));
                    $va = trim(($r['verified_at'] ?? ''));
                    if ($vb || $va) {
                      $vaFmt = $va ? date('m/d/Y h:i A', strtotime($va)) : '';
                      echo h(trim($vb.' '.$vaFmt));
                    }
                  ?>">Verified</span>
                <?php else: ?>
                  <span class="badge badge-light">Unverified</span>
                <?php endif; ?>
              </td>

              <td>
                <?php if ((int)$r['imported_to_client'] === 1): ?>
                  <span class="badge badge-info" title="<?php
                    $imp = (int)($r['imported_client_id'] ?? 0);
                    echo $imp ? ('Client ID '.$imp) : '';
                  ?>">Imported</span>
                <?php else: ?>
                  <span class="badge badge-light">Not Imported</span>
                <?php endif; ?>
              </td>

              <td>
                <a href="intake-review.php?id=<?php echo (int)$r['intake_id']; ?>" title="Review" data-toggle="tooltip">
                  <i class="far fa-eye"></i>
                </a>
                <a href="intake-update.php?id=<?php echo (int)$r['intake_id']; ?>" title="Update Record" data-toggle="tooltip">
                  <i class="far fa-edit"></i>
                </a>
              </td>
            </tr>
            <?php
              endwhile;
              mysqli_free_result($result);
            else:
            ?>
            <tr><td colspan="9" class="text-center text-muted">No intakes found.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php mysqli_close($link); ?>
      </div>
    </div>
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
<script>
  $(function(){ $('[data-toggle="tooltip"]').tooltip(); });
</script>
</body>
</html>
