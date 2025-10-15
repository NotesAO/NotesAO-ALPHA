<?php
/*******************************************************
 *  NotesAO — Reset-Password landing page
 *******************************************************/
ini_set('display_errors',1);error_reporting(E_ALL);
require_once '/home/notesao/lib/mailer.php';   // gives us global_config + autoload

$clinic = $_GET['clinic'] ?? '';
$token  = $_GET['token']  ?? '';

if (!$clinic || !$token) { exit('Invalid link'); }

/* Translate short code -> folder (same map as forgot_password.php) */
$map = [
  'ffl'=>'ffltest','sandbox'=>'sandbox','dwag'=>'dwag',
  'saf'=>'safatherhood','ctc'=>'ctc','transform'=>'transform','tbo'=>'bestoption'
];
$folder = $map[$clinic] ?? '';

if (!$folder || !file_exists("/home/notesao/$folder/config/config.php")) {
    exit('Clinic not recognised');
}
include "/home/notesao/$folder/config/config.php";
$con = new mysqli(db_host,db_user,db_pass,db_name);
if ($con->connect_error) { exit('DB error'); }

/* Validate token */
$stmt = $con->prepare(
  'SELECT user_id,expires_at,used_at FROM password_reset_tokens WHERE token=?'
);
$stmt->bind_param('s',$token);
$stmt->execute();$stmt->store_result();
if(!$stmt->num_rows){ exit('Invalid or expired link'); }
$stmt->bind_result($uid,$exp,$used);$stmt->fetch();
if($used || strtotime($exp)<time()) { exit('Link expired'); }

$err=$ok='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $p1=$_POST['new_password']; $p2=$_POST['confirm_password'];
    if ($p1!==$p2)        $err='Passwords do not match';
    elseif(strlen($p1)<8) $err='Password must be at least 8 characters';
    else {
        $hash = password_hash($p1,PASSWORD_DEFAULT);
        $upd  = $con->prepare('UPDATE accounts SET password=? WHERE id=?');
        $upd->bind_param('si',$hash,$uid);$upd->execute();
        $con->query("UPDATE password_reset_tokens SET used_at=NOW() WHERE token='$token'");
        $ok = 'Password updated. You may now <a href="/login.php">log in</a>.';
    }
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><title>Reset Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{min-height:100vh;display:flex;justify-content:center;align-items:center;background:#f4f7fb;}
.card{max-width:420px;width:100%;box-shadow:0 4px 12px rgba(0,0,0,.1);}
</style></head><body>
<div class="card p-4">
<h4 class="mb-3 text-center">Choose a new password</h4>
<?php if($err): ?><div class="alert alert-danger"><?=$err?></div><?php endif;?>
<?php if($ok):  ?><div class="alert alert-success"><?=$ok?></div><?php endif;?>

<?php if(!$ok): ?>
<form method="post">
  <div class="mb-3">
    <label class="form-label">New password</label>
    <input type="password" name="new_password" class="form-control" required>
  </div>
  <div class="mb-3">
    <label class="form-label">Confirm password</label>
    <input type="password" name="confirm_password" class="form-control" required>
  </div>
  <button class="btn btn-primary w-100">Update password</button>
</form>
<?php endif; ?>
</div>
</body></html>

