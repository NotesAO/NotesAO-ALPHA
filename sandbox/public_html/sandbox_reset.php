<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit('Unauthorized');
}

/* 1. locate today’s (or yesterday’s) post-update dump */
$backupFile = null;
foreach ([0,1] as $daysAgo) {
    $d = date('Y-m-d', strtotime("-$daysAgo day"));
    $f = dirname(__DIR__) . "/backups/sandbox_backup_post_update_{$d}.sql.gz";
    if (is_readable($f)) { $backupFile = $f; break; }
}
if (!$backupFile) {
    header("Location: home.php?reset_error=BackupFileMissing");
    exit;
}

/* 2. read & gunzip the dump */
$dumpSql = gzdecode(file_get_contents($backupFile));
if ($dumpSql === false) {
    header("Location: home.php?reset_error=ReadDumpFail");
    exit;
}

/* 3. connect to DB (reuse app creds) */
require_once dirname(__DIR__) . '/config/config.php';
$mysqli = new mysqli(db_host, db_user, db_pass, db_name);
if ($mysqli->connect_errno) {
    header("Location: home.php?reset_error=DBConnectFail");
    exit;
}
$mysqli->set_charset('utf8mb4');

/* 4. import */
$mysqli->query('SET FOREIGN_KEY_CHECKS = 0');
if (!$mysqli->multi_query($dumpSql)) {
    $mysqli->query('SET FOREIGN_KEY_CHECKS = 1');
    header("Location: home.php?reset_error=SQL:" . urlencode($mysqli->error));
    exit;
}
do { if ($res = $mysqli->store_result()) $res->free(); }
while ($mysqli->more_results() && $mysqli->next_result());
$mysqli->query('SET FOREIGN_KEY_CHECKS = 1');
$mysqli->close();

/* 5. done */
header("Location: home.php?reset_success=1");
exit;
