<?php
// --- DB settings (adminclinic) ------------------------------------
define('db_host','50.28.37.79');
define('db_name','clinicnotepro_adminclinic');
define('db_user','clinicnotepro_adminclinic_app');
define('db_pass','PF-m[T-+pF%g');
define('db_charset','utf8mb4');

// --- App settings (optional) --------------------------------------
$no_of_records_per_page = 10;
$appname = 'enroll';
$default_program_id = 2;

// Twilio etc. (kept for parity with your snippet)
define('twilio_sid','ACaa510297045a5128383b0133575db5c6');
define('twilio_token','db3545587c9ee46d23d76241e9189214');
define('twilio_number','+18176316949');
$sendSMS = true;

define('auto_login_after_register',false);
define('account_activation',false);
define('mail_from','Your Company Name <no-reply@ffl.notesao.com>');
define('activation_link','https://notesao.com/phplogin/activate.php');

// --- MYSQLI (for legacy code that expects $link) -------------------
$link = mysqli_init();
mysqli_real_connect($link, db_host, db_user, db_pass, db_name);
if (!$link) { die('DB connect failed'); }
mysqli_set_charset($link, db_charset);

// --- PDO (preferred; most of our new code uses $db) ---------------
try {
  $dsn = "mysql:host=".db_host.";dbname=".db_name.";charset=".db_charset;
  $db = new PDO($dsn, db_user, db_pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (Throwable $e) {
  error_log("[enroll/config] PDO failed: ".$e->getMessage());
  die('DB connect failed');
}
