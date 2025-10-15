<?php
/* ─── TEMPORARY DEBUG ───────────────────────────────────────── */
error_reporting(E_ALL);
ini_set('display_errors', 1);           // show on-screen
ini_set('log_errors',    1);           // also write to PHP’s error_log
/* ───────────────────────────────────────────────────────────── */

/*****************************************************************************
 * NotesAO • clinic-create.php
 * --------------------------------------------------------------------------
 * Lets an admin add a clinic with as little as “Name = …”.
 * • `code` auto-fills to the upcoming AUTO_INCREMENT id (e.g. "42").
 * • `subdomain` auto-fills to slug(name) (e.g. "acmecounseling").
 * • `status` defaults to "Prospect".
 * Requires: auth.php ($con - mysqli), helpers.php (optional).
 *****************************************************************************/
include_once 'auth.php';
check_loggedin($con);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once 'helpers.php';           // if you keep format helpers

/* ---------- helpers ---------- */
function slug($s){ return strtolower(preg_replace('/[^a-z0-9]/','',$s)); }
function old($k,$def=''){ global $input; return htmlspecialchars($input[$k] ?? $def); }
function err($k){ global $errors; return $errors[$k] ?? ''; }

/* ---------- init ---------- */
$errors = $input = [];

/* ---------- POST ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST'){

    /* 1. gather ----------------------------------------------------------- */
    foreach([
        'name','status',
        'code','subdomain','go_live_date',
        'primary_contact_name','primary_contact_email','primary_contact_phone',
        'first_meeting_date','estimated_client_count',
        'sales_contact_name','sales_contact_email','sales_contact_phone',
        'method','accepts_partial','used_by_facilitators',
        'notesao_processor_opt_in','additional_details'
    ] as $f){
        $input[$f] = trim($_POST[$f] ?? '');
    }
    $input['accepts_partial']         = isset($_POST['accepts_partial'])         ? 1 : 0;
    $input['used_by_facilitators']    = isset($_POST['used_by_facilitators'])    ? 1 : 0;
    $input['notesao_processor_opt_in']= isset($_POST['notesao_processor_opt_in'])? 1 : 0;

    /* 2. minimum validation ---------------------------------------------- */
    if ($input['name'] === '') $errors['name'] = 'Required';

    if ($input['primary_contact_email'] &&
        !filter_var($input['primary_contact_email'],FILTER_VALIDATE_EMAIL))
        $errors['primary_contact_email'] = 'Invalid email';

    if ($input['sales_contact_email'] &&
        !filter_var($input['sales_contact_email'],FILTER_VALIDATE_EMAIL))
        $errors['sales_contact_email'] = 'Invalid email';

    /* 3. generate fall-backs so DB NOT-NULLs are happy -------------------- */
    if (!$errors){

        /* get upcoming AUTO_INCREMENT for unique numeric code */
        $rs = $con->query(
          "SELECT AUTO_INCREMENT AS nxt
           FROM information_schema.tables
           WHERE table_schema = DATABASE() AND table_name = 'clinic'");
        $nextId = ($rs && ($row=$rs->fetch_assoc())) ? (int)$row['nxt'] : time();

        if ($input['code']==='')      $input['code']      = (string)$nextId;
        if ($input['subdomain']==='') $input['subdomain'] = slug($input['name']);
        if ($input['status']==='')    $input['status']    = 'Prospect';
        if ($input['method']==='')    $input['method']    = 'None';

        /* after generating $input['code'] and $input['subdomain'] … */

        # subdomain unique?
        $st = $con->prepare("SELECT 1 FROM clinic WHERE subdomain=? LIMIT 1");
        $st->bind_param('s',$input['subdomain']); $st->execute(); $st->store_result();
        if ($st->num_rows) { $errors['subdomain'] = 'Already in use'; }
        $st->close();

        # code unique?
        $st = $con->prepare("SELECT 1 FROM clinic WHERE code=? LIMIT 1");
        $st->bind_param('s',$input['code']); $st->execute(); $st->store_result();
        if ($st->num_rows) { $errors['code'] = 'Already in use'; }
        $st->close();

        if ($errors) { /* re-render form with errors */ }


        try {
            $con->begin_transaction();

            /* … existing INSERT blocks (clinic, sales, payment) … */

            $con->commit();
            header('Location: clinic-index.php'); exit;
        } catch (Throwable $e) {
            $con->rollback();
            error_log('clinic-create failed: '.$e->getMessage());
            $errors['name'] = 'Save failed. Please review fields and try again.';
        }


        /* clinic ------------------------------------------------------- */
        $goLive = $input['go_live_date'] ?: null;

        $stmt = $con->prepare(
          "INSERT INTO clinic
          (code,name,subdomain,status,go_live_date,
            primary_contact_name,primary_contact_email,primary_contact_phone)
          VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssss',
          $input['code'], $input['name'], $input['subdomain'], $input['status'],
          $goLive,
          $input['primary_contact_name'], $input['primary_contact_email'], $input['primary_contact_phone']);
        $stmt->execute();
        $clinic_id = $stmt->insert_id;
        $stmt->close();

        /* sales profile ----------------------------------------------- */
        $firstMeeting = $input['first_meeting_date'] ?: null;
        $estClients   = $input['estimated_client_count'] !== ''
                        ? (int)$input['estimated_client_count'] : null;

        if ($firstMeeting || $input['sales_contact_name'] || $estClients !== null) {
            $stmt = $con->prepare(
              "INSERT INTO clinic_sales_profile
              (clinic_id,first_meeting_date,estimated_client_count,
                contact_name,contact_email,contact_phone)
              VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('isisss',
              $clinic_id, $firstMeeting, $estClients,
              $input['sales_contact_name'], $input['sales_contact_email'], $input['sales_contact_phone']);
            $stmt->execute();
            $stmt->close();
        }

        /* payment profile --------------------------------------------- */
        $method = $input['method'];
        $addtl  = $input['additional_details'];

        /* Only persist if method is a real, allowed value */
        $allowed = ['Stripe','Square','WooCommerce','Cash','Check','Other'];
        if (in_array($method, $allowed, true)) {
            $stmt = $con->prepare(
              "INSERT INTO clinic_payment_profile
              (clinic_id,method,accepts_partial,used_by_facilitators,
                notesao_processor_opt_in,additional_details)
              VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('isiiis',
              $clinic_id, $method, $input['accepts_partial'],
              $input['used_by_facilitators'], $input['notesao_processor_opt_in'],
              $addtl);
            $stmt->execute();
            $stmt->close();
        }



        $con->commit();
        header('Location: clinic-index.php'); exit;
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><title>NotesAO – New Clinic</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<style>
body{padding-top:56px;font-size:16px}
fieldset{border:1px solid #dee2e6;padding:1rem;margin-bottom:1.5rem}
legend{font-size:1rem;width:auto;padding:0 6px;font-weight:600}
.is-invalid{border-color:#dc3545}
.form-text{font-size:.8rem}
</style></head>
<?php require 'navbar.php'; ?>
<body>
<section class="pt-5"><div class="container-fluid">
<h2>Add New Clinic</h2>

<form method="post">

<!-- ===== Clinic core ================================================= -->
<fieldset><legend>Clinic Info</legend>
<div class="form-row">
  <div class="form-group col-md-3">
    <label>Code (auto if blank)</label>
    <input name="code" class="form-control <?=err('code')?'is-invalid':''?>"
           value="<?=old('code')?>"><small class="text-danger"><?=err('code')?></small>
  </div>
  <div class="form-group col-md-5">
    <label>Name *</label>
    <input name="name" class="form-control <?=err('name')?'is-invalid':''?>"
           value="<?=old('name')?>"><small class="text-danger"><?=err('name')?></small>
  </div>
  <div class="form-group col-md-4">
    <label>Sub-domain (auto if blank)</label>
    <input name="subdomain" class="form-control" value="<?=old('subdomain')?>">
  </div>
</div>

<div class="form-row">
  <div class="form-group col-md-3">
    <label>Status</label>
    <select name="status" class="form-control">
      <?php foreach(['Prospect','Onboarding','Live','Paused'] as $s):?>
        <option <?=$s==old('status','Prospect')?'selected':''?>><?=$s?></option>
      <?php endforeach;?>
    </select>
  </div>
  <div class="form-group col-md-3">
    <label>Go-Live Date</label>
    <input type="date" name="go_live_date" class="form-control" value="<?=old('go_live_date')?>">
  </div>
</div>

<h6 class="mt-3">Primary Contact (optional)</h6>
<div class="form-row">
  <div class="form-group col-md-4"><label>Name</label>
    <input name="primary_contact_name" class="form-control" value="<?=old('primary_contact_name')?>"></div>
  <div class="form-group col-md-4"><label>Email</label>
    <input name="primary_contact_email" class="form-control <?=err('primary_contact_email')?'is-invalid':''?>"
           value="<?=old('primary_contact_email')?>"><small class="text-danger"><?=err('primary_contact_email')?></small></div>
  <div class="form-group col-md-4"><label>Phone</label>
    <input name="primary_contact_phone" class="form-control" value="<?=old('primary_contact_phone')?>"></div>
</div>
</fieldset>

<!-- ===== Sales profile (optional) ====================================== -->
<fieldset><legend>Sales Profile</legend>
<div class="form-row">
  <div class="form-group col-md-3"><label>First Meeting</label>
    <input type="date" name="first_meeting_date" class="form-control" value="<?=old('first_meeting_date')?>"></div>
  <div class="form-group col-md-3"><label>Est. Clients</label>
    <input type="number" name="estimated_client_count" class="form-control" value="<?=old('estimated_client_count')?>"></div>
</div>
<h6 class="mt-2">Sales Contact</h6>
<div class="form-row">
  <div class="form-group col-md-4"><label>Name</label>
    <input name="sales_contact_name" class="form-control" value="<?=old('sales_contact_name')?>"></div>
  <div class="form-group col-md-4"><label>Email</label>
    <input name="sales_contact_email" class="form-control <?=err('sales_contact_email')?'is-invalid':''?>"
           value="<?=old('sales_contact_email')?>"><small class="text-danger"><?=err('sales_contact_email')?></small></div>
  <div class="form-group col-md-4"><label>Phone</label>
    <input name="sales_contact_phone" class="form-control" value="<?=old('sales_contact_phone')?>"></div>
</div>
</fieldset>

<!-- ===== Payment profile (optional) ==================================== -->
<fieldset><legend>Payment Profile</legend>
<div class="form-row">
  <div class="form-group col-md-3"><label>Processor</label>
    <select name="method" class="form-control">
      <?php foreach(['None','Stripe','Square','WooCommerce','Cash','Check','Other'] as $m):?>
        <option <?=$m==old('method','None')?'selected':''?>><?=$m?></option>
      <?php endforeach;?>
    </select>
  </div>
  <div class="form-group col-md-3 align-self-end">
    <div class="form-check">
      <input type="checkbox" name="accepts_partial" class="form-check-input" <?=old('accepts_partial')?'checked':''?>>
      <label class="form-check-label">Accepts Partial</label>
    </div>
    <div class="form-check">
      <input type="checkbox" name="used_by_facilitators" class="form-check-input" <?=old('used_by_facilitators')?'checked':''?>>
      <label class="form-check-label">Used by Facilitators</label>
    </div>
    <div class="form-check">
      <input type="checkbox" name="notesao_processor_opt_in" class="form-check-input" <?=old('notesao_processor_opt_in')?'checked':''?>>
      <label class="form-check-label">NotesAO Processor</label>
    </div>
  </div>
  <div class="form-group col-md-6"><label>Additional Details</label>
    <textarea name="additional_details" rows="3" class="form-control"><?=old('additional_details')?></textarea></div>
</div>
</fieldset>

<button class="btn btn-primary">Create Clinic</button>
<a href="clinic-index.php" class="btn btn-secondary ml-2">Cancel</a>

</form>
</div></section>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body></html>
