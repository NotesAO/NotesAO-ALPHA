<?php
include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';
require_once 'sql_functions.php';

/* ---------- 1. initialise field values ---------- */
$first_name=$last_name=$date_of_birth='';
$program_id = $_SESSION['program_id'];   // keep existing behavior
$referral_type_id=$client_stage_id=$case_manager_id='';
$enrollment_date = date('Y-m-d');        // defaults to today

/* ---------- 2. initialise error strings ---------- */
$first_name_err=$last_name_err=$program_id_err='';
$date_of_birth_err=$referral_type_id_err=$client_stage_id_err='';
$case_manager_id_err=$enrollment_date_err='';

/* ---------- helpers ---------- */
function valid_date($s){
  if (!$s) return false;
  $d = DateTime::createFromFormat('Y-m-d', $s);
  return $d && $d->format('Y-m-d') === $s;
}

/* ---------- 3. POST: read + server-side guard rails ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {

  /* read & trim ------------------------------------------------ */
  $first_name       = trim($_POST['first_name'] ?? '');
  $last_name        = trim($_POST['last_name'] ?? '');
  $date_of_birth    = trim($_POST['date_of_birth'] ?? '');
  $program_id       = trim($_POST['program_id'] ?? $program_id);
  $referral_type_id = trim($_POST['referral_type_id'] ?? '');
  $client_stage_id  = trim($_POST['client_stage_id'] ?? '');
  $case_manager_id  = trim($_POST['case_manager_id'] ?? '');
  $enrollment_date  = trim($_POST['enrollment_date'] ?? $enrollment_date);

  $hasErrors=false;

  /* required: names -------------------------------------------- */
  if($first_name===''){ $first_name_err='Required.'; $hasErrors=true; }
  if($last_name===''){  $last_name_err='Required.';  $hasErrors=true; }

  /* DOB 18-110 yrs --------------------------------------------- */
  if(!valid_date($date_of_birth)){
    $date_of_birth_err='Use YYYY-MM-DD.'; $hasErrors=true;
  } else {
    $dob_ts = strtotime($date_of_birth);
    $age = $dob_ts ? (time() - $dob_ts) / 31557600 : 0;
    if ($age < 18 || $age > 110) {
      $date_of_birth_err='Age must be 18–110.'; $hasErrors=true;
    }
  }

  /* required selects ------------------------------------------- */
  if($program_id==='' || !ctype_digit((string)$program_id)){
    $program_id_err='Select a program.'; $hasErrors=true;
  }
  if($referral_type_id==='' || !ctype_digit((string)$referral_type_id)){
    $referral_type_id_err='Select a referral type.'; $hasErrors=true;
  }
  if($client_stage_id==='' || !ctype_digit((string)$client_stage_id)){
    $client_stage_id_err='Select a client stage.'; $hasErrors=true;
  }
  if($case_manager_id==='' || !ctype_digit((string)$case_manager_id)){
    $case_manager_id_err='Select a case manager.'; $hasErrors=true;
  }

  /* enrollment date (default to today if blank) ---------------- */
  if($enrollment_date===''){ $enrollment_date = date('Y-m-d'); }
  if(!valid_date($enrollment_date)){
    $enrollment_date_err='Use YYYY-MM-DD or leave blank for today.'; $hasErrors=true;
  }

  /* DB insert if all clear ------------------------------------- */
  if(!$hasErrors){
    try{
      $pdo = new PDO(
        'mysql:host='.db_host.';dbname='.db_name.';charset=utf8mb4',
        db_user, db_pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
      );

      // orientation_date is explicitly NULL at creation
      $stmt=$pdo->prepare(
        "INSERT INTO client (
           program_id, first_name, last_name, date_of_birth,
           referral_type_id, client_stage_id, case_manager_id,
           enrollment_date, orientation_date
         ) VALUES (?,?,?,?,?,?,?,?,?)"
      );

      $stmt->execute([
        (int)$program_id, $first_name, $last_name, $date_of_birth,
        (int)$referral_type_id, (int)$client_stage_id, (int)$case_manager_id,
        $enrollment_date, null
      ]);

      header('Location: client-index.php'); exit;
    } catch(Throwable $e){
      $enrollment_date_err = 'Database error: '.htmlspecialchars($e->getMessage());
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>NotesAO – New Client (Quick Enroll)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
      href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
      crossorigin="anonymous">
<style>
body{padding-top:56px;font-size:16px}
.is-warning{border-color:#fd7e14!important;box-shadow:0 0 0 .2rem rgba(253,126,20,.4)}
.is-invalid + .form-text.text-danger{display:block}
.card{border-radius:.75rem}
</style>
</head>
<?php require_once 'navbar.php'; ?>
<body>
<section class="pt-5"><div class="container-fluid">

<h2>New Client Enrollment</h2>

<form id="clientForm" action="<?=htmlspecialchars($_SERVER['PHP_SELF'])?>" method="post">

<!-- ========== Row 1: Name / DOB / Enrollment ============================ -->
<div class="row">
  <div class="col-12 col-sm-6 col-md-4 col-lg-3">
    <div class="form-group">
      <label>First Name <span class="text-danger">*</span></label>
      <input id="first_name" name="first_name" maxlength="45"
             class="form-control <?= $first_name_err?'is-invalid':'' ?>"
             value="<?=htmlspecialchars($first_name)?>">
      <span class="form-text text-danger"><?=$first_name_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-3">
    <div class="form-group">
      <label>Last Name <span class="text-danger">*</span></label>
      <input id="last_name" name="last_name" maxlength="45"
             class="form-control <?= $last_name_err?'is-invalid':'' ?>"
             value="<?=htmlspecialchars($last_name)?>">
      <span class="form-text text-danger"><?=$last_name_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-3">
    <div class="form-group">
      <label>Date of Birth <span class="text-danger">*</span></label>
      <input type="date" id="date_of_birth" name="date_of_birth"
             class="form-control <?= $date_of_birth_err?'is-invalid':'' ?>"
             value="<?=htmlspecialchars($date_of_birth)?>">
      <span class="form-text text-danger"><?=$date_of_birth_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-3">
    <div class="form-group">
      <label>Enrollment Date <span class="text-danger">*</span></label>
      <input type="date" id="enrollment_date" name="enrollment_date"
             class="form-control <?= $enrollment_date_err?'is-invalid':'' ?>"
             value="<?=htmlspecialchars($enrollment_date)?>">
      <span class="form-text text-danger"><?=$enrollment_date_err?></span>
      <small class="text-muted">Defaults to today if left blank.</small>
    </div>
  </div>
</div>

<!-- ========== Row 2: Program / Referral / Stage / Case Manager ========== -->
<div class="row">
  <div class="col-12 col-sm-6 col-md-3">
    <div class="form-group">
      <label>Program <span class="text-danger">*</span></label>
      <select id="program_id" name="program_id"
              class="form-control <?= $program_id_err?'is-invalid':'' ?>">
        <option value="" disabled <?=empty($program_id)?'selected':''?>>Select One</option>
        <?php foreach(get_programs() as $p): ?>
          <option value="<?=$p['id']?>" <?=$p['id']==$program_id?'selected':''?>>
            <?=htmlspecialchars($p['name'])?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="form-text text-danger"><?=$program_id_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-3">
    <div class="form-group">
      <label>Referral Type <span class="text-danger">*</span></label>
      <select id="referral_type_id" name="referral_type_id"
              class="form-control <?= $referral_type_id_err?'is-invalid':'' ?>">
        <option value="" disabled <?=empty($referral_type_id)?'selected':''?>>Select One</option>
        <?php foreach(get_referral_types() as $r): ?>
          <option value="<?=$r['id']?>" <?=$r['id']==$referral_type_id?'selected':''?>>
            <?=htmlspecialchars($r['referral_type'])?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="form-text text-danger"><?=$referral_type_id_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-3">
    <div class="form-group">
      <label>Client Stage <span class="text-danger">*</span></label>
      <select id="client_stage_id" name="client_stage_id"
              class="form-control <?= $client_stage_id_err?'is-invalid':'' ?>">
        <option value="" disabled <?=empty($client_stage_id)?'selected':''?>>Select One</option>
        <?php foreach(get_client_stages() as $s): ?>
          <option value="<?=$s['id']?>" <?=$s['id']==$client_stage_id?'selected':''?>>
            <?=htmlspecialchars($s['stage'])?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="form-text text-danger"><?=$client_stage_id_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-3">
    <div class="form-group">
      <label>Case Manager <span class="text-danger">*</span></label>
      <select id="case_manager_id" name="case_manager_id"
              class="form-control <?= $case_manager_id_err?'is-invalid':'' ?>">
        <option value="" disabled <?=empty($case_manager_id)?'selected':''?>>Select One</option>
        <?php foreach(get_case_managers() as $m): ?>
          <option value="<?=$m['id']?>" <?=$m['id']==$case_manager_id?'selected':''?>>
            <?=htmlspecialchars($m['last_name'].', '.$m['first_name'].' - '.$m['office'])?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="form-text text-danger"><?=$case_manager_id_err?></span>
    </div>
  </div>
</div>

<!-- ========== Actions ==================================================== -->
<div class="row mb-5">
  <div class="col-12">
    <button id="submitBtn" type="submit" class="btn btn-primary">Submit</button>
    <a href="client-index.php" class="btn btn-secondary ml-2">Cancel</a>
  </div>
</div>

</form>
</div></section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" crossorigin="anonymous"></script>
</body></html>
