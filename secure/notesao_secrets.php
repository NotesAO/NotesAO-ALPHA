<?php
/**
 * NotesAO secrets (DO NOT COMMIT)
 * Path: /home/notesao/secure/notesao_secrets.php
 *
 * Set $env = 'sandbox' or 'production'.
 * All values inside the selected env must belong to the same Square environment.
 */

declare(strict_types=1);

$env = 'sandbox'; // 'sandbox' | 'production'

$config = [
  'sandbox' => [
    // --- Square credentials (SANDBOX) ---
    'SQUARE_APPLICATION_ID' => 'sandbox-sq0idb-SNpksB8EGLp5NQORG66ycQ',
    'SQUARE_ACCESS_TOKEN'   => 'EAAAl73e7_3vEEbDzsM4NjrRJ4ruF0pXRBEtBpvG_psbb_pIWz2Nwf1x_gyKrOgJ',
    'SQUARE_LOCATION_ID'    => 'LJ2BQE9G7TZTV', // TODO: put your Sandbox Location ID here
    'SQUARE_API_BASE'       => 'https://connect.squareupsandbox.com',
    'SQUARE_API_VERSION'    => '2025-08-20',   // used by server calls
    'SQUARE_WEBHOOK_SECRET' => 'REPLACE_WITH_SANDBOX_WEBHOOK_SIGNATURE_KEY',

    // Merchant timezone (used for scheduling subscription start)
    'TIMEZONE'              => 'America/Chicago',

    // Plan catalog: label is for display, onboarding_cents for upfront fee,
    // square_plan_variation_id is required for /v2/subscriptions,
    // square_plan_id supports legacy Payment Links (kept for compatibility).
    'PLANS' => [
      'p200' => [
        'label'                     => '$200 / month',
        'price_cents'              => 20000,
        'onboarding_cents'          => 50000, // $200.00 onboarding fee (adjust if needed)
        'square_plan_variation_id'  => 'SJQGU5KN7QHZZZ5G5EATOJ2O', // required
        // 'plan_phase_uid'            => 'ZQFE7FYD2YGP3JHINGNWYYAK',           // optional
      ],
      'p150' => [
        'label'                     => '$150 / month',
        'onboarding_cents'          => 50000,
        'square_plan_variation_id'  => 'REPLACE_SANDBOX_PLAN_VARIATION_ID_P150',
        'square_plan_id'            => 'REPLACE_SANDBOX_PLAN_ID_P150',
      ],
      'p140' => [
        'label'                     => '$140 / month',
        'onboarding_cents'          => 50000,
        'square_plan_variation_id'  => 'REPLACE_SANDBOX_PLAN_VARIATION_ID_P140',
        'square_plan_id'            => 'REPLACE_SANDBOX_PLAN_ID_P140',
      ],
      'p120' => [
        'label'                     => '$120 / month',
        'onboarding_cents'          => 50000,
        'square_plan_variation_id'  => 'REPLACE_SANDBOX_PLAN_VARIATION_ID_P120',
        'square_plan_id'            => 'REPLACE_SANDBOX_PLAN_ID_P120',
      ],
      'p100' => [
        'label'                     => '$100 / month',
        'onboarding_cents'          => 50000,
        'square_plan_variation_id'  => 'REPLACE_SANDBOX_PLAN_VARIATION_ID_P100',
        'square_plan_id'            => 'REPLACE_SANDBOX_PLAN_ID_P100',
      ],
    ],
  ],

  
];

$cur = $config[$env];

// Return shape expected by the app.
// Provide both APPLICATION_ID and APP_ID keys for compatibility.
return [
  'SQUARE_ENV'             => $env,
  'SQUARE_APPLICATION_ID'  => $cur['SQUARE_APPLICATION_ID'],
  'SQUARE_APP_ID'          => $cur['SQUARE_APPLICATION_ID'], // alias
  'SQUARE_ACCESS_TOKEN'    => $cur['SQUARE_ACCESS_TOKEN'],
  'SQUARE_LOCATION_ID'     => $cur['SQUARE_LOCATION_ID'],
  'SQUARE_API_BASE'        => $cur['SQUARE_API_BASE'],
  'SQUARE_API_VERSION'     => $cur['SQUARE_API_VERSION'],
  'SQUARE_WEBHOOK_SECRET'  => $cur['SQUARE_WEBHOOK_SECRET'],
  'TIMEZONE'               => $cur['TIMEZONE'],
  'PLANS'                  => $cur['PLANS'],
];
