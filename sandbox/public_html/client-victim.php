<?php
/*****************************************************************
 *  client-victim.php – view / manage a client’s victim record(s)
 *****************************************************************/
include_once 'auth.php';
check_loggedin($con);

require_once 'helpers.php';
require_once 'sql_functions.php';

/* --------------------------------------------------------------
   1.  Resolve client_id (GET or POST) and pull client header data
   -------------------------------------------------------------- */
$client_id = getParam('client_id');
if (!$client_id || !ctype_digit($client_id)) {
    header('Location: error.php'); exit;
}

$client = get_client_info($client_id);
if (!$client) { header('Location: error.php'); exit; }

/* --------------------------------------------------------------
   2.  Load the victim rows for this client
       (you'll need to implement this helper in sql_functions.php
        to JOIN in `gender` lookup for readability)
   -------------------------------------------------------------- */
$victims = get_client_victims($client_id);  // returns [] if none

/* --------------------------------------------------------------
   3.  HTML / Bootstrap
   -------------------------------------------------------------- */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NotesAO | Victim Info</title>

<!--  favicon links & bootstrap / font-awesome (same CDNs you use elsewhere) -->
<link rel="icon" type="image/x-icon"            href="/favicons/favicon.ico">
<link rel="icon" type="image/png"  sizes="32x32" href="/favicons/favicon-32x32.png">
<link rel="manifest"                            href="/favicons/site.webmanifest">

<link rel="stylesheet"
      href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
      crossorigin="anonymous">
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
      crossorigin="anonymous">

<style>
    body{padding-top:56px;font-size:16px}
    table tr td:last-child a{margin-right:5px}
</style>
</head>
<body>
<?php require_once 'navbar.php'; ?>

<section class="pt-3">
<div class="container-fluid">

<!-- ░░░░░░  CLIENT HEADER  ░░░░░░ -->
<div class="row">
    <div class="col">
        <div class="row">
            <div class="col-3"><h2>Client Information</h2></div>
            <div class="col-3">
                <h5><a class="nav-link"
                       href="client-update.php?id=<?=htmlspecialchars($client_id)?>">Update Client</a></h5>
            </div>
        </div>
    </div>
</div>

<div class="row bg-light">
    <div class="col-7 pt-3 pb-3">
        <div class="row">
            <div class="col-2">
                <small class="text-muted">First</small>
                <h5><?=htmlspecialchars($client['first_name'])?></h5>
            </div>
            <div class="col-2">
                <small class="text-muted">Last</small>
                <h5><?=htmlspecialchars($client['last_name'])?></h5>
            </div>
            <div class="col-3">
                <small class="text-muted">DOB</small>
                <h5><?=htmlspecialchars($client['date_of_birth']).' ('.$client['age'].')'?></h5>
            </div>
            <div class="col-2">
                <small class="text-muted">Phone</small>
                <h5><?=htmlspecialchars($client['phone_number'])?></h5>
            </div>
            <div class="col-3">
                <small class="text-muted">E-Mail</small>
                <h5><?=htmlspecialchars($client['email'])?></h5>
            </div>
        </div>
        <div class="row">
            <div class="col-3">
                <small class="text-muted">Referral</small>
                <h5><?=htmlspecialchars($client['referral_type'])?></h5>
            </div>
            <div class="col-3">
                <small class="text-muted">Case Mgr</small>
                <h5><?=htmlspecialchars($client['case_manager'])?></h5>
            </div>
            <div class="col-4">
                <small class="text-muted">Group</small>
                <h5><?=htmlspecialchars($client['group_name'])?></h5>
            </div>
        </div>
    </div>
    <!-- picture -->
    <div class="col-1 pt-3 pb-3">
        <img src="getImage.php?id=<?=$client_id?>"
             class="img-thumbnail"
             alt="client picture"
             onerror="this.onerror=null;this.src='img/male-placeholder.jpg'">
        <div class="text-center">
            <a class="nav-link" target="_blank"
               href="client-image-upload.php?client_id=<?=$client_id?>">Update Image</a>
        </div>
    </div>
</div>

<!-- ░░░░░░  VICTIM INFO  ░░░░░░ -->
<div class="row mt-3">
    <div class="col-3"><h3>Victim Information</h3></div>
</div>

<div class="row bg-light p-3">
    <div class="col">
      <table class="table table-bordered table-striped">
        <thead class="thead-light">
          <tr>
            <th>Name</th>
            <th>Relationship</th>
            <th>Gender</th>
            <th>Age</th>
            <th>Address</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Living with?</th>
            <th># Children <18</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($victims)): ?>
          <tr><td colspan="10" class="text-center">No victim data recorded.</td></tr>
        <?php else: foreach ($victims as $v): ?>
          <tr>
            <td><?=htmlspecialchars($v['name'])?></td>
            <td><?=htmlspecialchars($v['relationship'])?></td>
            <td><?=htmlspecialchars($v['gender'])?></td>
            <td><?=htmlspecialchars($v['age'])?></td>
            <td>
              <?=htmlspecialchars($v['address_line1'])?><br>
              <?php if ($v['address_line2']): ?>
                <?=htmlspecialchars($v['address_line2'])?><br>
              <?php endif; ?>
              <?=htmlspecialchars("{$v['city']}, {$v['state']} {$v['zip']}")?>
            </td>
            <td><?=htmlspecialchars($v['phone'])?></td>
            <td><?=htmlspecialchars($v['email'])?></td>
            <td><?= $v['living_with_client'] ? 'Yes' : 'No' ?></td>
            <td><?=htmlspecialchars($v['children_under_18'])?></td>
            <td>
              <a href="client-victim-update.php?id=<?=$v['id']?>&client_id=<?=$client_id?>"
                 class="btn btn-sm btn-primary">Edit</a>
              <a href="client-victim-delete.php?id=<?=$v['id']?>&client_id=<?=$client_id?>"
                 class="btn btn-sm btn-danger"
                 onclick="return confirm('Delete this victim record?');">Delete</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- BUTTONS -->
  <div class="row mt-3 mb-5">
    <div class="col-auto">
      <a href="client-victim-create.php?client_id=<?=$client_id?>" class="btn btn-success">
        <i class="fas fa-plus"></i> Add Victim
      </a>
    </div>
    <div class="col-auto">
      <a href="client-review.php?client_id=<?=$client_id?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Client
      </a>
    </div>
</div>

</div><!-- /.container-fluid -->
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" crossorigin="anonymous"></script>
</body>
</html>
