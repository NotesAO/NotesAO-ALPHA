<?php
declare(strict_types=1);
$secrets = require '/home/notesao/secure/notesao_secrets.php';
$api = rtrim($secrets['SQUARE_API_BASE'],'/');
$ver = $secrets['SQUARE_API_VERSION'] ?? '2025-08-20';
$at  = $secrets['SQUARE_ACCESS_TOKEN'];

$ch = curl_init($api.'/v2/catalog/list?types=SUBSCRIPTION_PLAN');
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => ["Square-Version: $ver", "Authorization: Bearer $at"],
  CURLOPT_RETURNTRANSFER => true,
]);
$out = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($http);
header('Content-Type: application/json');
echo $out;
