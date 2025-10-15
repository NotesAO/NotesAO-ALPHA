<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

$secrets = require '/home/notesao/secure/notesao_secrets.php';
require __DIR__ . '/../../config/config.php';

function http($method, $url, $body=null, $token=''){
  $ch = curl_init($url);
    global $secrets;
    $ver = $secrets['SQUARE_API_VERSION'] ?? '2025-07-16';
    $hdr = [
      'Content-Type: application/json',
      'Square-Version: '.$ver,
      'Authorization: Bearer '.$token
    ];

  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => $method,
    CURLOPT_HTTPHEADER    => $hdr,
    CURLOPT_POSTFIELDS    => $body ? json_encode($body) : null,
    CURLOPT_RETURNTRANSFER=> true,
    CURLOPT_TIMEOUT       => 30
  ]);
  $res  = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return [$http, json_decode($res,true) ?: []];
}

try {
  $ctx = $_SESSION['enroll'] ?? null;
  if (!$ctx) throw new RuntimeException('Session expired. Start again.');

  $in = json_decode(file_get_contents('php://input'), true) ?: [];
  $verificationToken = $in['verification_token'] ?? null;

  $planCode   = $in['plan'] ?? 'p200';   // optional; for logging
  $consentId  = (int)($in['consent_id'] ?? 0);
  $token      = $in['card_token'] ?? '';
  $holder     = trim($in['cardholder_name'] ?? '');
  $zip        = trim($in['billing_postal_code'] ?? '');
  $country    = strtoupper(trim($in['country'] ?? 'US'));
  $sigDataUrl = $in['sig_data'] ?? '';
  $signDate   = $in['sign_date'] ?? date('Y-m-d');

    if ($consentId <= 0)                 throw new RuntimeException('Missing consent id');
    if (!$token || !$holder)             throw new RuntimeException('Missing card details');
    // $zip is optional. If you require it, re-add the check.


  $API = rtrim($secrets['SQUARE_API_BASE'],'/');
  $AT  = $secrets['SQUARE_ACCESS_TOKEN'];

  // 1) Find/create customer by email
  [$h,$d] = http('POST', $API.'/v2/customers/search', [
    'query'=>['filter'=>['email_address'=>['exact'=>$ctx['email']]]]
  ], $AT);
  if ($h===200 && !empty($d['customers'][0]['id'])) {
    $custId = $d['customers'][0]['id'];
  } else {
    [$h,$d] = http('POST',$API.'/v2/customers', [
      'idempotency_key'=>bin2hex(random_bytes(16)),
      'email_address'  =>$ctx['email'],
      'given_name'     =>$ctx['name'],
      'company_name'   =>$ctx['company'] ?: null,
      'phone_number'   =>$ctx['phone'] ?: null
    ], $AT);
    if ($h!==200) throw new RuntimeException('Could not create customer');
    $custId = $d['customer']['id'];
  }

  // 2) Save the signed authorization image (audit)
  if (strpos($sigDataUrl,'data:image/png;base64,')===0) {
    @mkdir(__DIR__.'/../_agreements/sig',0750,true);
    $sigBin  = base64_decode(substr($sigDataUrl,22));
    $sigPath = __DIR__.'/../_agreements/sig/cardauth_'.date('Ymd_His').'.png';
    file_put_contents($sigPath,$sigBin);
  }

    // 3) Save card on file
    $cardPayload = [
      'idempotency_key' => bin2hex(random_bytes(16)),
      'source_id'       => $token, // from Web Payments SDK
      'card' => [
        'cardholder_name' => $holder,
        'billing_address' => array_filter([
          'postal_code' => $zip ?: null,
          'country'     => $country ?: null, // "US", "CA", etc.
        ]),
        'customer_id'    => $custId,                // <-- move inside "card"
        'reference_id'   => 'consent-'.$consentId,  // optional but useful
      ],
    ];

    // include SCA verification when present
    if (!empty($verificationToken)) {
      $cardPayload['verification_token'] = $verificationToken;
    }

    [$h,$d] = http('POST', $API.'/v2/cards', $cardPayload, $AT);


  if ($h!==200) {
    error_log('CreateCard failed: HTTP '.$h.' resp='.json_encode($d, JSON_UNESCAPED_SLASHES));
    throw new RuntimeException('Failed to save card: '.json_encode($d));
  }

  $cardId = $d['card']['id']; // e.g., ccof:...

  // 4) Persist for step 2
  $stmt = $db->prepare("UPDATE clinic_agreement_acceptance
    SET square_customer_id=?, square_card_id=?
    WHERE id=? LIMIT 1");
  $stmt->execute([$custId,$cardId,$consentId]);

  echo json_encode(['ok'=>true,'customer_id'=>$custId,'card_id'=>$cardId]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
