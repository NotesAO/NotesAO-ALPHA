<?php
declare(strict_types=1);
$secrets = require '/home/notesao/secure/notesao_secrets.php';

$api   = rtrim($secrets['SQUARE_API_BASE'], '/'); // should be https://connect.squareupsandbox.com
$token = $secrets['SQUARE_ACCESS_TOKEN'];
$ver   = $secrets['SQUARE_API_VERSION'] ?? '2025-07-16';

$ch = curl_init($api.'/v2/locations');
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer '.$token,
    'Square-Version: '.$ver,
    'Content-Type: application/json',
  ],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 20,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$body = json_decode($res, true);
$ids  = array_map(fn($l) => $l['id'] ?? '', $body['locations'] ?? []);

header('Content-Type: application/json');
echo json_encode([
  'env_config'         => $secrets['SQUARE_ENV'] ?? null,
  'api_base'           => $api,
  'configured_location'=> $secrets['SQUARE_LOCATION_ID'] ?? null,
  'http'               => $code,
  'sandbox_location_ids_from_api' => $ids,
], JSON_PRETTY_PRINT);
