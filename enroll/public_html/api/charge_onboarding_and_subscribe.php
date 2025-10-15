<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

$secrets = require '/home/notesao/secure/notesao_secrets.php';
require __DIR__ . '/../../config/config.php';

function http($method, $url, $body=null, $token=''){
  $ch = curl_init($url);
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

  $in        = json_decode(file_get_contents('php://input'), true) ?: [];
  $planCode  = $in['plan'] ?? 'p200';
  $consentId = (int)($in['consent_id'] ?? 0);
  if ($consentId <= 0) throw new RuntimeException('Missing consent id');

  $PLANS = $secrets['PLANS'];
  if (empty($PLANS[$planCode])) throw new RuntimeException('Unknown plan code');
  $plan   = $PLANS[$planCode];

  $API = rtrim($secrets['SQUARE_API_BASE'],'/');
  $AT  = $secrets['SQUARE_ACCESS_TOKEN'];
  $LOC = $secrets['SQUARE_LOCATION_ID'];
  $TZ  = $secrets['TIMEZONE'] ?? 'America/Chicago';

  // Pull the card and customer saved during card-auth step
  $stmt = $db->prepare("SELECT square_customer_id, square_card_id FROM clinic_agreement_acceptance WHERE id=? LIMIT 1");
  $stmt->execute([$consentId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row || !$row['square_customer_id'] || !$row['square_card_id']) {
    throw new RuntimeException('Card or customer not found for this consent.');
  }
  $custId = $row['square_customer_id'];
  $cardId = $row['square_card_id'];

  // 1) Charge onboarding (e.g., $500)
  $onboardingCents = (int)($plan['onboarding_cents'] ?? 0);
  if ($onboardingCents > 0) {
    [$h,$d] = http('POST', $API.'/v2/payments', [
      'idempotency_key'=>bin2hex(random_bytes(16)),
      'amount_money'   =>['amount'=>$onboardingCents,'currency'=>'USD'],
      'source_id'      =>$cardId,
      'customer_id'    =>$custId,
      'location_id'    =>$LOC,
      'autocomplete'   =>true,
      'note'           =>'NotesAO Onboarding ('.$planCode.')',
      'reference_id'   =>'onboarding-'.$consentId
    ], $AT);
    if (!in_array($h,[200,201],true)) {
      throw new RuntimeException('Onboarding payment failed: '.json_encode($d));
    }
    $paymentId = $d['payment']['id'] ?? null;

    // persist payment id
    $db->prepare("UPDATE clinic_agreement_acceptance SET square_payment_id=? WHERE id=? LIMIT 1")
       ->execute([$paymentId,$consentId]);
  }

  // 2) Create an order template (DRAFT) for the plan monthly price
  $priceCents = (int)($plan['price_cents'] ?? 0);
  if ($priceCents <= 0) throw new RuntimeException('Plan price not configured');

  [$h,$d] = http('POST', $API.'/v2/orders', [
    'idempotency_key'=>bin2hex(random_bytes(16)),
    'order'=>[
      'location_id'=>$LOC,
      'state'=>'DRAFT',
      'line_items'=>[[
        'name'=>'NotesAO Subscription '.$plan['label'],
        'quantity'=>'1',
        // Using ad-hoc price here; you can switch to a catalog item variation later.
        'base_price_money'=>['amount'=>$priceCents,'currency'=>'USD'],
      ]],
    ]
  ], $AT);
  if ($h!==200) throw new RuntimeException('CreateOrder failed: '.json_encode($d));
  $orderTemplateId = $d['order']['id'] ?? null;
  if (!$orderTemplateId) throw new RuntimeException('Order template missing');

  // 3) Create the subscription
  // Start on the first day of next month (merchant timezone)
  $tz = new DateTimeZone($TZ);
  $start = new DateTime('first day of next month', $tz);
  $start_date = $start->format('Y-m-d');

  $planVariationId = $plan['square_plan_variation_id'] ?? '';
  if (!$planVariationId) {
    throw new RuntimeException('Plan variation ID not configured.');
  }

  [$h,$d] = http('POST',$API.'/v2/subscriptions', [
    'idempotency_key'   => bin2hex(random_bytes(16)),
    'location_id'       => $LOC,
    'plan_variation_id' => $planVariationId,
    'customer_id'       => $custId,
    'card_id'           => $cardId,
    'start_date'        => $start_date,
    'timezone'          => $TZ,
    'phases' => [[
      'ordinal'            => 0,
      'order_template_id'  => $orderTemplateId
    ]]
  ], $AT);


  if ($h!==200) throw new RuntimeException('Subscription failed: '.json_encode($d));
  $subscriptionId = $d['subscription']['id'] ?? null;

  // Save subscription id
  $db->prepare("UPDATE clinic_agreement_acceptance SET square_subscription_id=? WHERE id=? LIMIT 1")
     ->execute([$subscriptionId,$consentId]);

  echo json_encode(['ok'=>true,'subscription_id'=>$subscriptionId]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
