<?php
declare(strict_types=1);
include_once 'auth.php';
check_loggedin($con);
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);

$client_id = isset($in['client_id']) ? (int)$in['client_id'] : 0;
$date      = isset($in['date']) ? (string)$in['date'] : '';
$excused   = isset($in['excused']) ? (int)$in['excused'] : 0;
$note      = trim((string)($in['note'] ?? ''));

if ($client_id <= 0 || !$date) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad input']);
  exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad date']);
  exit;
}

/* idempotent: do nothing if already exists */
$st = $con->prepare("SELECT 1 FROM absence WHERE client_id=? AND `date`=? LIMIT 1");
$st->bind_param('is', $client_id, $date);
$st->execute();
$st->store_result();
if ($st->num_rows > 0) {
  echo json_encode(['ok'=>true, 'already_exists'=>true]);
  exit;
}
$st->close();

/* insert */
$st = $con->prepare("INSERT INTO absence (client_id, `date`, excused, note) VALUES (?,?,?,?)");
$st->bind_param('isis', $client_id, $date, $excused, $note);
if ($st->execute()) {
  echo json_encode(['ok'=>true, 'id'=>$st->insert_id]);
} else {

  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'insert failed']);
}
$st->close();
