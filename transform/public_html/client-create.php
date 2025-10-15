<?php
include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';
require_once 'sql_functions.php';

/* ---------- 1. initialise field values ---------- */
$first_name=$last_name=$date_of_birth=$email=$phone_number='';
$cause_number=$other_concerns='';
$referral_type_id=$ethnicity_id=$gender_id=$client_stage_id='';
$required_sessions=$fee='';
$case_manager_id=$therapy_group_id='';
$emergency_contact=$referral_date=$orientation_date='';
$exit_date=$exit_reason_id=$exit_note='';
$identification_number=$identification_type_id=$other_id_type_description='';
$weekly_attendance='';
$program_id=$_SESSION['program_id'];

$attends_sunday=$attends_monday=$attends_tuesday=
$attends_wednesday=$attends_thursday=$attends_friday=
$attends_saturday=0;

/* ---------- 2. initialise error strings ---------- */
$first_name_err=$last_name_err=$program_id_err='';
$date_of_birth_err=$email_err=$phone_number_err='';
$cause_number_err=$referral_type_id_err=$ethnicity_id_err='';
$gender_id_err=$required_sessions_err=$fee_err='';
$case_manager_id_err=$therapy_group_id_err=$client_stage_id_err='';
$other_concerns_err=$emergency_contact_err=$referral_date_err=$orientation_date_err='';
$identification_number_err=$identification_type_id_err=$other_id_type_description_err='';
$exit_date_err=$exit_reason_id_err=$exit_note_err='';
$weekly_attendance_err='';

/* ---------- 3. POST: read + server-side guard rails ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {

  /* read & trim ------------------------------------------------ */
  $first_name        =trim($_POST['first_name']??'');
  $last_name         =trim($_POST['last_name']??'');
  $date_of_birth     =trim($_POST['date_of_birth']??'');
  $email             =trim($_POST['email']??'');
  $phone_number      =formatPhone(trim($_POST['phone_number']??''));
  $cause_number      =trim($_POST['cause_number']??'');
  $referral_type_id  =trim($_POST['referral_type_id']??'');
  $ethnicity_id      =trim($_POST['ethnicity_id']??'');
  $gender_id         =trim($_POST['gender_id']??'');
  $required_sessions =trim($_POST['required_sessions']??'');
  $fee               =trim($_POST['fee']??'');
  $case_manager_id   =trim($_POST['case_manager_id']??'');
  $therapy_group_id  =trim($_POST['therapy_group_id']??'');
  $client_stage_id   =trim($_POST['client_stage_id']??'');
  $other_concerns    =trim($_POST['other_concerns']??'');
  $emergency_contact =trim($_POST['emergency_contact']??'');
  $referral_date     =trim($_POST['referral_date']??'');
  $orientation_date  =trim($_POST['orientation_date']??'');
  $exit_date         =trim($_POST['exit_date']??'');
  $exit_reason_id    =trim($_POST['exit_reason_id']??'');
  $exit_note         =trim($_POST['exit_note']??'');
  $identification_number =trim($_POST['identification_number']??'');
  $identification_type_id =trim($_POST['identification_type_id']??'');
  $other_id_type_description =trim($_POST['other_id_type_description']??'');
  $weekly_attendance =trim($_POST['weekly_attendance']??'');

  $attends_sunday    =isset($_POST['attends_sunday']);
  $attends_monday    =isset($_POST['attends_monday']);
  $attends_tuesday   =isset($_POST['attends_tuesday']);
  $attends_wednesday =isset($_POST['attends_wednesday']);
  $attends_thursday  =isset($_POST['attends_thursday']);
  $attends_friday    =isset($_POST['attends_friday']);
  $attends_saturday  =isset($_POST['attends_saturday']);

  $hasErrors=false;

  /* blank-field guard (critical ones only) --------------------- */
  foreach([
      'first_name'        => &$first_name_err,
      'last_name'         => &$last_name_err,
      'date_of_birth'     => &$date_of_birth_err,
      'phone_number'      => &$phone_number_err,
      'email'             => &$email_err,
      'emergency_contact' => &$emergency_contact_err,
      'referral_date'     => &$referral_date_err,
      'orientation_date'  => &$orientation_date_err,
      'gender_id'         => &$gender_id_err,
      'ethnicity_id'      => &$ethnicity_id_err,
      'referral_type_id'  => &$referral_type_id_err,
      'identification_number' => &$identification_number_err,
      'identification_type_id' => &$identification_type_id_err,
      'weekly_attendance' => &$weekly_attendance_err,
      'fee'               => &$fee_err,
      'case_manager_id'   => &$case_manager_id_err,
      'therapy_group_id'  => &$therapy_group_id_err
  ] as $field=>&$err){
      if(empty($$field)){ $err='Required.'; $hasErrors=true; }
  }

  /* phone must already be 999-999-9999 from JS ----------------- */
  if(!preg_match('/^\d{3}-\d{3}-\d{4}$/',$phone_number)){
      $phone_number_err='Phone must be 999-999-9999.'; $hasErrors=true;
  }

  /* e-mail ----------------------------------------------------- */
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
      $email_err='Invalid e-mail.'; $hasErrors=true;
  }

  /* DOB 18-110 yrs -------------------------------------------- */
  $dob_ts=strtotime($date_of_birth?:'');
  $age=$dob_ts?(time()-$dob_ts)/31557600:0;
  if(!$dob_ts||$age<18||$age>110){
      $date_of_birth_err='Age must be 18-110.'; $hasErrors=true;
  }

  /* sessions / week exactly 1 or 2 ---------------------------- */
  if(!in_array($weekly_attendance,['1','2'],true)){
      $weekly_attendance_err='Must be 1 or 2.'; $hasErrors=true;
  }

  /* day count matches ----------------------------------------- */
  $dayCnt=$attends_sunday+$attends_monday+$attends_tuesday+
          $attends_wednesday+$attends_thursday+
          $attends_friday+$attends_saturday;
  if(($weekly_attendance==='1'&&$dayCnt!==1)||
     ($weekly_attendance==='2'&&($dayCnt<1||$dayCnt>2))){
      $weekly_attendance_err='Day count mismatch.'; $hasErrors=true;
  }

  /* required sessions numeric --------------------------------- */
  if($required_sessions!==''&&!preg_match('/^\d+$/',$required_sessions)){
      $required_sessions_err='Must be a number.'; $hasErrors=true;
  }

  /* fee positive ≤100 ----------------------------------------- */
  if(!is_numeric($fee)||$fee<=0||$fee>100){
      $fee_err='Fee must be ≤100.'; $hasErrors=true;
  }

  /* DB insert if all clear ------------------------------------ */
  if(!$hasErrors){

      $exit_reason_id = 1;
      $exit_note     = null;
      $exit_date    = null;

      $pdo=new PDO(
        'mysql:host='.db_host.';dbname='.db_name.';charset=utf8mb4',
        db_user,db_pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

      $stmt=$pdo->prepare(
      "INSERT INTO client (
        program_id,first_name,last_name,date_of_birth,gender_id,email,
        phone_number,cause_number,referral_type_id,ethnicity_id,required_sessions,
        fee,case_manager_id,therapy_group_id,client_stage_id,other_concerns,
        emergency_contact,referral_date,orientation_date,exit_date,exit_reason_id,exit_note,identification_number,
        identification_type_id,other_id_type_description,
        weekly_attendance,attends_sunday,attends_monday,attends_tuesday,
        attends_wednesday,attends_thursday,attends_friday,attends_saturday
      ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

      $stmt->execute([
        $program_id,$first_name,$last_name,$date_of_birth,$gender_id,$email,
        $phone_number,$cause_number,$referral_type_id,$ethnicity_id,$required_sessions,
        $fee,$case_manager_id,$therapy_group_id,$client_stage_id,$other_concerns,
        $emergency_contact,$referral_date,$orientation_date,$exit_date,$exit_reason_id,$exit_note,$identification_number,
        $identification_type_id,$other_id_type_description,
        $weekly_attendance,$attends_sunday,$attends_monday,$attends_tuesday,
        $attends_wednesday,$attends_thursday,$attends_friday,$attends_saturday
      ]);
      header('Location: client-index.php'); exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>NotesAO – New Client</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" crossorigin="anonymous">
<style>
body{padding-top:56px;font-size:16px}
.is-warning{border-color:#fd7e14!important;box-shadow:0 0 0 .2rem rgba(253,126,20,.4)}
</style>
</head>
<?php require_once 'navbar.php'; ?>
<body>
<section class="pt-5"><div class="container-fluid">

<h2>New Client Enrollment</h2>

<form id="clientForm" action="<?=htmlspecialchars($_SERVER['PHP_SELF'])?>" method="post">

<!-- ========== Row 1 ====================================================== -->
<div class="row">
  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>First Name (required)</label>
      <input id="first_name" name="first_name" maxlength="45"
             class="form-control is-invalid"
             value="<?=htmlspecialchars($first_name)?>">
      <span class="form-text text-danger"><?=$first_name_err?></span>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Last Name (required)</label>
      <input id="last_name" name="last_name" maxlength="45"
             class="form-control is-invalid"
             value="<?=htmlspecialchars($last_name)?>">
      <span class="form-text text-danger"><?=$last_name_err?></span>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Date of Birth (required)</label>
      <input type="date" id="date_of_birth" name="date_of_birth"
             class="form-control is-invalid"
             value="<?=htmlspecialchars($date_of_birth)?>">
      <span class="form-text text-danger"><?=$date_of_birth_err?></span>
    </div>
  </div>
</div>

<!-- ========== Row 2 (Gender / Ethnicity / Program) ======================= -->
<div class="row">
  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Gender (required)</label>
      <select id="gender_id" name="gender_id" class="form-control is-invalid">
        <option value="" disabled <?=empty($gender_id)?'selected':''?>>Select One</option>
        <?php foreach(get_genders() as $g):?>
          <option value="<?=$g['id']?>" <?=$g['id']==$gender_id?'selected':''?>>
            <?=htmlspecialchars($g['gender'])?></option>
        <?php endforeach;?>
      </select>
      <span class="form-text text-danger"><?=$gender_id_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Ethnicity (required)</label>
      <select id="ethnicity_id" name="ethnicity_id" class="form-control is-invalid">
        <option value="" disabled <?=empty($ethnicity_id)?'selected':''?>>Select One</option>
        <?php foreach(get_ethnicities() as $e):?>
          <option value="<?=$e['id']?>" <?=$e['id']==$ethnicity_id?'selected':''?>>
            <?=htmlspecialchars($e['code'].' - '.$e['name'])?></option>
        <?php endforeach;?>
      </select>
      <span class="form-text text-danger"><?=$ethnicity_id_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Program</label>
      <select id="program_id" name="program_id" class="form-control is-invalid" readonly>
        <?php foreach(get_programs() as $p):?>
          <option value="<?=$p['id']?>" <?=$p['id']==$program_id?'selected':''?>>
            <?=htmlspecialchars($p['name'])?></option>
        <?php endforeach;?>
      </select>
      <span class="form-text text-danger"><?=$program_id_err?></span>
    </div>
  </div>
</div>

<!-- ========== Row 3 (Phone / Email / Emergency) ========================= -->
<div class="row">
  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Phone Number (required)</label>
      <input id="phone_number" name="phone_number" class="form-control is-invalid"
             placeholder="999-999-9999" value="<?=htmlspecialchars($phone_number)?>">
      <span class="form-text text-danger"><?=$phone_number_err?></span>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>E-Mail (required)</label>
      <input id="email" name="email" maxlength="64" class="form-control is-invalid"
             value="<?=htmlspecialchars($email)?>">
      <span class="form-text text-danger"><?=$email_err?></span>
    </div>
  </div>
  <div class="col-10 col-md-6">
    <div class="form-group">
      <label>Emergency Contact: Name, Phone, & Relation (required)</label>
      <input id="emergency" name="emergency_contact" maxlength="512"
             class="form-control is-invalid"
             value="<?=htmlspecialchars($emergency_contact)?>">
      <span class="form-text text-danger"><?=$emergency_contact_err?></span>
    </div>
  </div>
</div>

<!-- ========== Row 4 (Referral / ReqSess / Sessions/Wk / Fee / CM / Cause) -->
<div class="row">
  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Referral Type (required)</label>
      <select id="referral_type_id" name="referral_type_id" class="form-control is-invalid">
        <option value="" disabled <?=empty($referral_type_id)?'selected':''?>>Select One</option>
        <?php foreach(get_referral_types() as $r):?>
          <option value="<?=$r['id']?>" <?=$r['id']==$referral_type_id?'selected':''?>>
            <?=htmlspecialchars($r['referral_type'])?></option>
        <?php endforeach;?>
      </select>
      <span class="form-text text-danger"><?=$referral_type_id_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Required Sessions (required)</label>
      <input id="required_sessions" name="required_sessions" type="number"
             class="form-control is-invalid" value="<?=htmlspecialchars($required_sessions)?>">
      <span class="form-text text-danger"><?=$required_sessions_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Sessions / Week (required)</label>
      <input id="sessions_per_week" name="weekly_attendance" type="number"
             class="form-control is-invalid" value="<?=htmlspecialchars($weekly_attendance)?>">
      <span class="form-text text-danger"><?=$weekly_attendance_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Group Fee (required)</label>
      <input id="fee" name="fee" type="number" class="form-control is-invalid"
             value="<?=htmlspecialchars($fee)?>">
      <span class="form-text text-danger"><?=$fee_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Case Manager (required)</label>
      <select id="case_manager_id" name="case_manager_id" class="form-control is-invalid">
        <option value="" disabled <?=empty($case_manager_id)?'selected':''?>>Select One</option>
        <?php foreach(get_case_managers() as $m):?>
          <option value="<?=$m['id']?>" <?=$m['id']==$case_manager_id?'selected':''?>>
            <?=htmlspecialchars($m['last_name'].', '.$m['first_name'].' - '.$m['office'])?></option>
        <?php endforeach;?>
      </select>
      <span class="form-text text-danger"><?=$case_manager_id_err?></span>
    </div>
  </div>
</div>
<!-- ========== Row 5 (Therapy / Orientation / Stage) ===================== -->
<div class="row">
  <div class="col-12 col-sm-12 col-md-6 col-lg-4">
    <div class="form-group">
      <label>Therapy Group (required)</label>
      <select id="therapy_group_id" name="therapy_group_id" class="form-control is-invalid">
        <option value="" disabled <?=empty($therapy_group_id)?'selected':''?>>Select One</option>
        <?php foreach(get_therapy_groups($_SESSION['program_id']) as $g):?>
          <option value="<?=$g['id']?>" <?=$g['id']==$therapy_group_id?'selected':''?>>
            <?=htmlspecialchars($g['name'].' - '.$g['address'])?></option>
        <?php endforeach;?>
      </select>
      <span class="form-text text-danger"><?=$therapy_group_id_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Referral Date (required)</label>
      <input type="date" id="referral_date" name="referral_date"
             class="form-control is-invalid" value="<?=htmlspecialchars($referral_date)?>">
      <span class="form-text text-danger"><?=$referral_date_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Orientation Date (required)</label>
      <input type="date" id="orientation_date" name="orientation_date"
             class="form-control is-invalid" value="<?=htmlspecialchars($orientation_date)?>">
      <span class="form-text text-danger"><?=$orientation_date_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Progress Stage</label>
      <select id="client_stage_id" name="client_stage_id" class="form-control">
        <?php foreach(get_client_stages() as $s):?>
          <option value="<?=$s['id']?>" <?=$s['id']==$client_stage_id?'selected':''?>>
            <?=htmlspecialchars($s['stage'])?></option>
        <?php endforeach;?>
      </select>
      <span class="form-text text-danger"><?=$client_stage_id_err?></span>
    </div>
  </div>
</div>

<!-- NEW Row just below “Therapy Group” row -->
<div class="row">
  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Identification Number (required)</label>
      <input id="identification_number" name="identification_number" maxlength="128"
             class="form-conFtrol is-invalid"
             value="<?=htmlspecialchars($identification_number)?>">
      <span class="form-text text-danger"><?=$identification_number_err?></span>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-md-4 col-lg-2">
    <div class="form-group">
      <label>Identification Type (required)</label>
      <select id="identification_type_id" name="identification_type_id" class="form-control is-invalid">
        <option value="" disabled <?=empty($identification_type_id)?'selected':''?>>Select One</option>
        <?php foreach(get_identification_types() as $t): ?>
          <option value="<?=$t['id']?>" <?=$t['id']==$identification_type_id?'selected':''?>>
            <?=htmlspecialchars($t['name'])?></option>
        <?php endforeach; ?>
      </select>
      <span class="form-text text-danger"><?=$identification_type_id_err?></span>
    </div>
  </div>

  <div class="col-12 col-md-4 col-lg-3">
    <div class="form-group">
      <label>Other ID Info</label>
      <input name="other_id_type_description" maxlength="128"
             class="form-control"
             value="<?=htmlspecialchars($other_id_type_description)?>">
      <span class="form-text"><?=$other_id_type_description_err?></span>
    </div>
  </div>
</div>


<!-- ========== Row 6 (Attendance Day check-boxes) ======================== -->
<div class="row">
  <div class="col-12">
    <label>Attendance Day(s) (requried)</label><br>
    <small class="text-muted">
      Select 1&nbsp;day if Sessions / Week = 1, or 2 days if Sessions / Week = 2.
    </small>
  </div>
</div>

<div class="row">
  <div class="col-12 col-md-8">

    <?php
    $days = [
      'sunday'    => 'Sunday',
      'monday'    => 'Monday',
      'tuesday'   => 'Tuesday',
      'wednesday' => 'Wednesday',
      'thursday'  => 'Thursday',
      'friday'    => 'Friday',
      'saturday'  => 'Saturday'
    ];
    foreach ($days as $k => $lbl) {
      $chk = ${'attends_'.$k} ? 'checked' : '';
      echo '
      <div class="form-check form-check-inline mb-2">
        <input class="form-check-input" type="checkbox" name="attends_'.$k.'" '.$chk.'>
        <label class="form-check-label">'.$lbl.'</label>
      </div>';
    }
    ?>

  </div>
</div>

<!-- ========== Row 7 (Other Concerns) ==================================== -->
<div class="row mt-3">
  <div class="col-12 col-md-8">
    <div class="form-group">
      <label>Other Concerns&nbsp;|&nbsp;Class Conduct</label>
      <textarea name="other_concerns" rows="3" maxlength="2048"
                class="form-control"><?=htmlspecialchars($other_concerns)?></textarea>
      <span class="form-text"><?=$other_concerns_err?></span>
    </div>
  </div>
</div>

<!-- ========== Row 8 (Buttons) =========================================== -->
<div class="row mb-5">
  <div class="col-12">
    <button id="submitBtn" type="submit" class="btn btn-primary">Submit</button>
    <a href="client-index.php" class="btn btn-secondary ml-2">Cancel</a>
  </div>
</div>
</section>

<!-- ======== Front-end live validator =================================== -->
<script>
document.addEventListener('DOMContentLoaded',()=>{

const F={first:'first_name',last:'last_name',dob:'date_of_birth',
phone:'phone_number',email:'email',gender:'gender_id',ethnicity:'ethnicity_id',
program:'program_id',referral:'referral_type_id',reqSess:'required_sessions',
sessWeek:'sessions_per_week',fee:'fee',caseMgr:'case_manager_id',
therapyGrp:'therapy_group_id',orient:'orientation_date',emerg:'emergency',referr:'referral_date',identnum:'identification_number',
identtype:'identification_type_id',};

const critical=new Set([F.first,F.last,F.dob,F.phone,F.email,F.gender,F.ethnicity,
F.program,F.referral,F.sessWeek,F.caseMgr,F.therapyGrp,F.orient,F.referr,F.identnum,F.identtype]);

Object.values(F).forEach(id=>{
  const el=document.getElementById(id);
  if(!el) return;
  ['input','change','blur'].forEach(evt=>el.addEventListener(evt,()=>validate(el)));
  validate(el);
});

function validate(el){
  const v=el.value.trim(); let state='invalid';

  switch(el.id){
    case F.phone:{
      const d=v.replace(/\D/g,'');
      if(d.length===10){state='valid';
        if(!/-/.test(v)) el.value=d.replace(/(\d{3})(\d{3})(\d{4})/,'$1-$2-$3');
      } break;}
    case F.email: state=/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)?'valid':'invalid';break;
    case F.dob:{
      const d=new Date(v); const age=(Date.now()-d)/3.15576e10;
      state=!isNaN(d)&&age>=18&&age<=110?'valid':'invalid';break;}
    case F.sessWeek: state=(v==='1'||v==='2')?'valid':'invalid';break;
    case F.reqSess:
      state=['18','27','30'].includes(v)?'valid':(/^\d+$/.test(v)&&+v>0?'warning':'invalid');
      break;
    case F.fee:{
      let f=parseFloat(v);
      if([32,30].includes(f))f=30; else if([21,20].includes(f))f=20;
      else if([16,15].includes(f))f=15;
      state=[15,20,30].includes(f)?'valid':(f>0&&f<=100?'warning':'invalid');
      if(!isNaN(f)) el.value=f; break;}
    default: state=v!==''?'valid':'invalid';
  }
  el.classList.remove('is-valid','is-warning','is-invalid');
  el.classList.add(state==='valid'?'is-valid':state==='warning'?'is-warning':'is-invalid');
  lockSubmit();
}

function lockSubmit(){
  const bad=[...critical].some(id=>document.getElementById(id).classList.contains('is-invalid'));
  document.getElementById('submitBtn').disabled=bad;
}
});
</script>

<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" crossorigin="anonymous"></script>
</body></html>
