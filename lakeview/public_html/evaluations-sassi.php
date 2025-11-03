<?php
/**
 * evaluations-sassi.php (Public)
 * SASSI Adult-4 (lifetime) – one page per field (except first step has First/Last together)
 *
 * • GET  : render multi-step form (names → phone → email → age → household → gender → employment
 *          → marital → highest grade → arrests total → arrests DWI → prior treatment → instructions
 *          → TF #1..#74 → FVA instructions → FVA 12 items → FVD instructions → FVD 19 items)
 * • POST : CSRF check → validate → INSERT into evaluations_sassi
 *          → show branded thank-you (no scores shown to client)
 *
 * Requires: /config/config.php for $link (mysqli)
 */

declare(strict_types=1);
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../config/config.php';   // provides $link (mysqli)
/** @var mysqli $link */
$link->set_charset('utf8mb4');

/* ------------------------------- helpers -------------------------------- */
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void {
  if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    http_response_code(403); exit('Invalid CSRF token.');
  }
}
function inet_pton_nullable(string $ip) { $bin = @inet_pton($ip); return $bin === false ? null : $bin; }
function humanize_label(string $code): string {
  $t = preg_replace('/_+/', ' ', $code);
  $t = trim($t);
  $t = ucfirst($t);
  $t = str_replace(' i ', ' I ', $t);
  $t = str_replace(' couldnt ', " couldn't ", $t);
  $t = str_replace(' didnt ', " didn't ", $t);
  return $t;
}

/* --------------------------- SASSI definitions --------------------------- */
/* True/False items (1..74). Use the exact codes you provided (column-safe). */
$SASSI_TF_CODES = [
  '1_people_know_they_can_count_on_me_for_solutions',
  '2_most_people_make_some_mistakes_in_their_lives',
  '3_i_usually_go_along_and_do_what_others_are_doing',
  '4_i_have_never_been_in_trouble_with_the_police',
  '5_i_was_always_well_behaved_in_school',
  '6_i_like_doing_things_on_the_spur_of_the_moment',
  '7_i_have_not_lived_the_way_i_should',
  '8_i_can_be_friendly_with_people_who_do_many_wrong_things',
  '9_i_do_not_like_to_sit_and_daydream',
  '10_no_one_has_ever_criticized_or_punished_me',
  '11_sometimes_i_have_a_hard_time_sitting_still',
  '12_people_would_be_better_off_if_they_took_my_advice',
  '13_at_times_i_feel_worn_out_for_no_special_reason',
  '14_i_am_a_restless_person',
  '15_it_is_better_not_to_talk_about_personal_problems',
  '16_i_have_had_days_weeks_or_months_when_i_couldnt_get_much_done_',
  '17_i_am_very_respectful_of_authority',
  '18_i_come_up_with_good_strategies',
  '19_i_have_been_tempted_to_leave_home',
  '20_i_often_feel_that_strangers_look_at_me_with_disapproval',
  '21_other_people_would_fall_apart_if_they_had_to_deal_with_what_i',
  '22_i_have_avoided_people_i_did_not_want_to_speak_to',
  '23_some_crooks_are_so_clever_that_i_hope_they_get_away_with_what',
  '24_my_school_teachers_had_some_problems_with_me',
  '25_i_have_never_done_anything_dangerous_just_for_fun',
  '26_i_need_to_have_something_to_do_so_i_dont_get_bored',
  '27_i_have_sometimes_drunk_too_much',
  '28_much_of_my_life_is_uninteresting',
  '29_sometimes_i_wish_i_could_control_myself_better',
  '30_i_believe_that_people_sometimes_get_confused',
  '31_sometimes_i_am_no_good_for_anything_at_all',
  '32_i_break_more_laws_than_many_people',
  '33_if_some_friends_and_i_were_in_trouble_together_i_would_rather',
  '34_crying_does_not_help',
  '35_i_think_there_is_something_wrong_with_my_memory',
  '36_i_have_sometimes_been_tempted_to_hit_people',
  '37_most_people_would_lie_to_get_what_they_want',
  '38_i_always_feel_sure_of_myself',
  '39_i_have_never_broken_a_major_law',
  '40_there_have_been_times_when_i_have_done_things_i_couldnt_remem',
  '41_i_think_carefully_about_all_my_actions',
  '42_i_have_used_too_much_alcohol_or_pot_or_used_too_often',
  '43_nearly_everyone_enjoys_being_picked_on_and_made_fun_of',
  '44_i_like_to_obey_the_law',
  '45_i_frequently_make_lists_of_things_to_do',
  '46_i_think_i_know_some_pretty_undesirable_types',
  '47_most_people_will_laugh_at_a_joke_now_and_then',
  '48_i_have_rarely_been_punished',
  '49_i_use_tobacco_regularly',
  '50_at_times_i_have_been_so_full_of_energy_that_i_felt_i_didnt_ne',
  '51_i_have_sometimes_sat_around_when_i_should_have_been_working',
  '52_i_am_often_resentful',
  '53_i_take_all_my_responsibilities_seriously',
  '54_i_do_most_of_my_drinking_or_drug_use_away_from_home',
  '55_i_have_had_a_drink_first_thing_in_the_morning_to_steady_my_ne',
  '56_while_i_was_a_teenager_i_began_drinking_or_using_other_drugs_',
  '57_one_of_my_parents_was_is_a_heavy_drinker_or_drug_user',
  '58_when_i_drink_or_use_drugs_i_tend_to_get_into_trouble',
  '59_my_drinking_or_other_drug_use_causes_problems_between_me_and_',
  '60_new_activities_can_be_a_strain_if_i_cant_drink_or_use_when_i_',
  '61_i_frequently_use_non_prescription_antacids_or_digestion_medic',
  '62_i_have_never_felt_sad_over_anything',
  '63_i_have_neglected_obligations_to_family_or_work_because_of_my_',
  '64_i_am_usually_happy',
  '65_im_good_at_figuring_out_the_plot_in_a_spy_drama_or_murder_mys',
  '66_i_have_wished_i_could_cut_down_my_drinking_or_drug_use',
  '67_i_am_a_binge_drinker_drug_user',
  '68_i_often_use_energy_drinks_or_other_over_the_counter_products_',
  '69_im_reluctant_to_tell_my_doctors_about_all_the_medications_im_',
  '70_my_doctors_have_not_prescribed_me_enough_medication_to_get_th',
  '71_i_know_that_my_drinking_using_is_making_my_problems_worse',
  '72_i_have_built_up_a_tolerance_to_the_alcohol_drugs_or_medicatio',
  '73_over_time_i_have_noticed_i_drink_or_use_more_than_i_used_to',
  '74_i_have_worried_about_my_parent_s_drinking_or_drug_use',
];

/* Face-Valid Alcohol (12 items, 4-choice) */
$FVA_ITEMS = [
  'fva_drinks_with_lunch'                    => 'Had drinks (beer, wine, liquor) with lunch?',
  'fva_drinks_to_help_talk'                  => 'Taken a drink or drinks to help you talk about your feelings or ideas?',
  'fva_drinks_to_relieve_tired'              => 'Taken a drink or drinks to relieve a tired feeling or give you energy to keep going?',
  'fva_more_than_intended'                   => 'Had more to drink than you intended to?',
  'fva_physical_problems_after'              => 'Experienced physical problems after drinking (e.g. nausea, seeing/hearing problems, dizziness, etc.)?',
  'fva_trouble_due_to_drinking'              => 'Gotten into trouble on the job, in school, or with the law because of your drinking?',
  'fva_depressed_after_sober'                => 'Became depressed after having sobered up?',
  'fva_argued_due_to_drinking'               => 'Argued with your family or friends because of your drinking?',
  'fva_effects_recur_after_abstinence'       => 'Had the effects of drinking recur after not drinking for a while (e.g., flashbacks, hallucinations, etc.)?',
  'fva_relationship_problems'               => 'Had problems in relationships because of your drinking (e.g., loss of friends, separation, divorce, etc.)?',
  'fva_nervous_shakes_after_sober'           => 'Became nervous or had the shakes after having sobered up?',
  'fva_tried_suicide_while_drunk'            => 'Tried to commit suicide while drunk?',
];

/* Face-Valid Drugs (19 items, 4-choice) */
$FVD_ITEMS = [
  'fvd_craving_drink_or_drug'                => 'Found myself craving a drink or a particular drug?',
  'fvd_misuse_to_improve_thinking'           => 'Misused medications or took drugs to improve your thinking and feelings?',
  'fvd_misuse_to_feel_better_about_problem'  => 'Misused medications or took drug to help you feel better about a problem?',
  'fvd_misuse_to_increase_senses'            => 'Misused medications or took drugs to become more aware of your senses (e.g., sight, hearing, touch, etc.)?',
  'fvd_misuse_to_improve_sex'                => 'Misused medications or took drugs to improve your enjoyment of sex?',
  'fvd_misuse_to_forget_helplessness'        => 'Misused medications or took drugs to help forget that you feel helpless and unworthy?',
  'fvd_misuse_to_forget_pressures'           => 'Misused medications or took drugs to forget school, work or family pressures?',
  'fvd_trouble_due_to_drug_activities'       => 'Gotten into trouble at home, work, or with the police because of medications or drug-related activities?',
  'fvd_really_stoned_or_wiped_out'           => 'Gotten really stoned or wiped out on drugs (more than just high)?',
  'fvd_tried_to_get_rx_drugs'                => 'Tried to get a hold of some prescription drug (e.g., tranquilizers, pain killers, pills to calm nerves, sleep aids, etc.)?',
  'fvd_spare_time_drug_activities'           => 'Spent your spare time in drug-related activities (e.g., talking about drugs, buying, selling, taking, etc.)?',
  'fvd_used_drugs_and_alcohol_together'      => 'Used drugs or medications and alcohol at the same time?',
  'fvd_keep_using_to_avoid_withdrawal'       => 'Kept taking medications or drugs in order to avoid pain or withdrawal?',
  'fvd_misuse_kept_from_goals'               => 'Felt your misuse of medications, alcohol, or drugs has kept you from getting what you want out of life?',
  'fvd_higher_dose_than_prescribed'          => 'Took a higher dose or different medications than your doctor prescribed in order to get the relief you need?',
  'fvd_used_rx_not_prescribed'               => 'Used prescription drugs that were not prescribed for you?',
  'fvd_doctor_denied_meds'                   => 'Your doctor denied your request for medications you needed?',
  'fvd_accepted_into_treatment'              => 'Been accepted into a treatment program because of misuse of medications, alcohol, or drugs?',
  'fvd_risky_activity_while_using'           => 'Engaged in activity that could have been physically dangerous after (or while) drinking or using drugs or medications?',
];

/* Allowed sets for demographics */
$GENDER = ['male','female','nonbinary','other','prefer_not_to_say'];
$EMPLOY = ['fulltime','parttime','unemployed','student','disabled','retired'];
$MARITAL = ['married','never_married','divorced','separated','widowed','unmarried_couple'];
$GRADE = ['1_4','5_8','9','10','11','12_ged','some_college','two_year_college','four_year_college','graduate_degree'];
$TREAT = ['none','education','out_patient','in_patient','multiple','other'];

/* 4-choice map (store as 0..3) */
$FREQ_MAP = [
  'never'         => 0,
  'once_or_twice' => 1,
  'several_times' => 2,
  'repeatedly'    => 3
];

/* ------------------------------- POST ------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // Demographics
  $first = trim((string)($_POST['first_name'] ?? ''));
  $last  = trim((string)($_POST['last_name'] ?? ''));
  $phone = trim((string)($_POST['phone_number'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $age   = (string)($_POST['age'] ?? '');
  $hh    = (string)($_POST['household_size'] ?? '');
  $gender= (string)($_POST['gender'] ?? '');
  $emp   = (string)($_POST['employment'] ?? '');
  $mar   = (string)($_POST['marital_status'] ?? '');
  $grade = (string)($_POST['highest_grade'] ?? '');
  $arTot = (string)($_POST['arrests_total'] ?? '');
  $arDwi = (string)($_POST['arrests_dwi'] ?? '');
  $treat = (string)($_POST['prior_treatment'] ?? '');

  $errors = [];
  if ($first === '') $errors[] = 'First name is required.';
  if ($last === '')  $errors[] = 'Last name is required.';
  if ($phone === '') $errors[] = 'Phone number is required.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
  if ($age === '' || !preg_match('/^\d{1,3}$/', $age)) $errors[] = 'Valid age is required.';
  if ($hh === '' || !preg_match('/^\d{1,2}$/', $hh))   $errors[] = 'Valid number in household is required.';
  if (!in_array($gender, $GENDER, true))  $errors[] = 'Gender selection is required.';
  if (!in_array($emp, $EMPLOY, true))     $errors[] = 'Employment selection is required.';
  if (!in_array($mar, $MARITAL, true))    $errors[] = 'Marital status is required.';
  if (!in_array($grade, $GRADE, true))    $errors[] = 'Highest grade selection is required.';
  if ($arTot === '' || !preg_match('/^\d{1,3}$/', $arTot)) $errors[] = 'Total arrests (integer) is required.';
  if ($arDwi === '' || !preg_match('/^\d{1,3}$/', $arDwi)) $errors[] = 'Total DWI/DUI arrests (integer) is required.';
  if (!in_array($treat, $TREAT, true)) $errors[] = 'Prior treatment selection is required.';

  $age   = (int)$age;
  $hh    = (int)$hh;
  $arTot = (int)$arTot;
  $arDwi = (int)$arDwi;

  // T/F items → 0/1
  $tfAnswers = [];
  foreach ($SASSI_TF_CODES as $code) {
    $v = $_POST['tf_'.$code] ?? null;
    if ($v === null || !in_array($v, ['0','1'], true)) {
      $errors[] = "All True/False items must be answered (missing: {$code}).";
    } else {
      $tfAnswers[$code] = ($v === '1') ? 1 : 0;
    }
  }

  // Face-Valid Alcohol → 0..3
  $fvaAnswers = [];
  foreach ($FVA_ITEMS as $code => $label) {
    $v = $_POST[$code] ?? null;
    if (!array_key_exists($v ?? '', $FREQ_MAP)) {
      $errors[] = "All alcohol frequency items must be answered (missing: {$label}).";
    } else {
      $fvaAnswers[$code] = $FREQ_MAP[$v];
    }
  }

  // Face-Valid Drugs → 0..3
  $fvdAnswers = [];
  foreach ($FVD_ITEMS as $code => $label) {
    $v = $_POST[$code] ?? null;
    if (!array_key_exists($v ?? '', $FREQ_MAP)) {
      $errors[] = "All drug frequency items must be answered (missing: {$label}).";
    } else {
      $fvdAnswers[$code] = $FREQ_MAP[$v];
    }
  }

  if ($errors) {
    http_response_code(422);
    echo "<!doctype html><meta charset='utf-8'><title>SASSI | Errors</title>";
    echo "<div style='max-width:720px;margin:40px auto;font-family:system-ui,Arial'>";
    echo "<h1>SASSI — Submission errors</h1><ul>";
    foreach ($errors as $e) echo "<li>".h($e)."</li>";
    echo "</ul><p><a href='javascript:history.back()'>Go back</a></p></div>";
    exit;
  }

  // Build INSERT dynamically to avoid placeholder mismatches.
  $cols = [
    'first_name','last_name','phone_number','email',
    'age','household_size','gender','employment','marital_status','highest_grade',
    'arrests_total','arrests_dwi','prior_treatment'
  ];
  $types = 'ssss' . 'ii' . 's' . 's' . 's' . 's' . 'ii' . 's'; // ssss, ii, s, s, s, s, ii, s

  $vals = [$first,$last,$phone,$email,$age,$hh,$gender,$emp,$mar,$grade,$arTot,$arDwi,$treat];

  // Add TF (ints)
  foreach ($SASSI_TF_CODES as $code) { $cols[] = $code; $types .= 'i'; $vals[] = $tfAnswers[$code]; }

  // Add FVA (ints 0..3)
  foreach ($FVA_ITEMS as $code => $_) { $cols[] = $code; $types .= 'i'; $vals[] = $fvaAnswers[$code]; }

  // Add FVD (ints 0..3)
  $lastKeyOfFVD = array_key_last($FVD_ITEMS); // last item decides submit button in UI
  foreach ($FVD_ITEMS as $code => $_) { $cols[] = $code; $types .= 'i'; $vals[] = $fvdAnswers[$code]; }

  // Meta
  $cols[] = 'submit_ip';   $types .= 's'; $vals[] = inet_pton_nullable($_SERVER['REMOTE_ADDR'] ?? '') ?? '';
  $cols[] = 'user_agent';  $types .= 's'; $vals[] = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

  $placeholders = implode(',', array_fill(0, count($cols), '?'));
  $colList = '`' . implode('`,`', $cols) . '`';

  $sql = "INSERT INTO `evaluations_sassi` ($colList) VALUES ($placeholders)";
  $stmt = $link->prepare($sql);
  if (!$stmt) { throw new RuntimeException('Prepare failed: '.$link->error); }

  $stmt->bind_param($types, ...$vals);
  $ok = $stmt->execute();
  if (!$ok) {
    throw new RuntimeException('Insert failed: '.$stmt->error);
  }
  $stmt->close();

  // Thank you (no visible scoring)
  echo "<!DOCTYPE html>
  <html lang='en'>
  <head>
    <meta charset='utf-8'>
    <title>Thank you – SASSI Adult-4</title>
    <link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css'>
    <meta name='viewport' content='width=device-width,initial-scale=1'>
    <style>
      body{font-family:system-ui,Arial;background:#f5f6fa;padding:2rem}
      .card{max-width:720px;margin:0 auto;border:0;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
    </style>
  </head>
  <body>
    <div class='jumbotron bg-white text-center shadow-sm py-4 mb-4'>
      <a href='https://lakevieweducation.com/' target='_blank' rel='noopener'>
        <img src='lakeviewlogo.png' alt='Lakeview Education' class='img-fluid mb-1' style='max-width:60%;height:auto'>
      </a>
    </div>
    <div class='card shadow-sm'>
      <div class='card-body text-center p-5'>
        <h2 class='mb-3'>Thank you!</h2>
        <p class='lead mb-2'>Your SASSI responses were submitted successfully.</p>
        <p class='mb-4'>We’ve recorded your answers.</p>
        <a href='https://lakevieweducation.com/' class='btn btn-primary btn-lg'>Lakeview Education Home</a>
      </div>
    </div>
  </body>
  </html>";
  exit;
}

/* --------------------------------- GET ----------------------------------- */
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>SASSI Adult-4 (Lifetime)</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
  <style>
    body{background:#f5f6fa}
    .step{display:none}
    .step.active{display:block}
    .card{border:0;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.06)}
    .progress{height:8px}
    :root{--brand-bg:#151a22;--brand-fg:#fff}
    .brandbar{position:fixed;top:0;left:0;right:0;z-index:1000;background:var(--brand-bg);color:var(--brand-fg);height:56px;display:flex;align-items:center;box-shadow:0 1px 4px rgba(0,0,0,.2)}
    .brandbar .wrap{width:100%;max-width:1120px;margin:0 auto;padding:0 16px;display:flex;align-items:center;justify-content:space-between}
    .brandbar .title{font-size:16px;font-weight:600;margin:0}
    .brandbar .subtitle{opacity:.7;font-size:12px;margin-left:8px}
    .brandbar .links a{color:var(--brand-fg);text-decoration:none;font-size:12px;margin-left:16px;opacity:.9}
    .brandbar img{height:28px;margin-left:12px}
    body{padding-top:64px}
    @media (max-width:600px){.brandbar .subtitle{display:none}; body{padding-top:56px}}
  </style>
</head>
<body>
<div class="brandbar">
  <div class="wrap">
    <div class="left">
      <h1 class="title">Lakeview — SASSI Adult-4</h1>
      <span class="subtitle">NotesAO Form</span>
    </div>
    <div class="links">
      <a href="https://lakevieweducation.com/">Home</a>
      <img src="lakeviewlogo.png" alt="Lakeview logo">
    </div>
  </div>
</div>

<div class="container py-4" style="max-width:760px">
  <div class="mb-3">
    <div class="d-flex justify-content-between align-items-center">
      <h1 class="h4 mb-0">SASSI Adult-4 (Lifetime)</h1>
      <span class="small text-muted" id="progressLabel">Step 1</span>
    </div>
    <div class="progress mt-2"><div id="bar" class="progress-bar" role="progressbar" style="width:0%"></div></div>
  </div>

  <div class="card">
    <div class="card-body">
      <form id="sassiForm" method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <!-- STEP 1: First + Last (two fields in one page as requested) -->
        <div class="step active">
          <div class="form-row">
            <div class="form-group col-sm-6">
              <label for="first_name">First Name<span class="text-danger">*</span></label>
              <input id="first_name" name="first_name" type="text" class="form-control" required>
            </div>
            <div class="form-group col-sm-6">
              <label for="last_name">Last Name<span class="text-danger">*</span></label>
              <input id="last_name" name="last_name" type="text" class="form-control" required>
            </div>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- One page per field -->
        <div class="step">
          <div class="form-group">
            <label for="phone_number">Phone Number<span class="text-danger">*</span></label>
            <input id="phone_number" name="phone_number" type="tel" inputmode="tel" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <div class="step">
          <div class="form-group">
            <label for="email">Email<span class="text-danger">*</span></label>
            <input id="email" name="email" type="email" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <div class="step">
          <div class="form-group">
            <label for="age">Age<span class="text-danger">*</span></label>
            <input id="age" name="age" type="number" min="10" max="120" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <div class="step">
          <div class="form-group">
            <label for="household_size">Number in Household<span class="text-danger">*</span></label>
            <input id="household_size" name="household_size" type="number" min="1" max="20" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Gender <span class="text-danger">*</span></legend>
            <?php foreach ($GENDER as $g): ?>
              <div class="custom-control custom-radio mb-2">
                <input class="custom-control-input" type="radio" id="gender_<?= h($g) ?>" name="gender" value="<?= h($g) ?>" required>
                <label class="custom-control-label" for="gender_<?= h($g) ?>"><?= h(ucwords(str_replace('_',' ',$g))) ?></label>
              </div>
            <?php endforeach; ?>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Employment <span class="text-danger">*</span></legend>
            <?php foreach ($EMPLOY as $e): ?>
              <div class="custom-control custom-radio mb-2">
                <input class="custom-control-input" type="radio" id="employment_<?= h($e) ?>" name="employment" value="<?= h($e) ?>" required>
                <label class="custom-control-label" for="employment_<?= h($e) ?>"><?= h(ucwords(str_replace('_',' ',$e))) ?></label>
              </div>
            <?php endforeach; ?>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Marital Status <span class="text-danger">*</span></legend>
            <?php foreach ($MARITAL as $m): ?>
              <div class="custom-control custom-radio mb-2">
                <input class="custom-control-input" type="radio" id="marital_<?= h($m) ?>" name="marital_status" value="<?= h($m) ?>" required>
                <label class="custom-control-label" for="marital_<?= h($m) ?>"><?= h(ucwords(str_replace('_',' ',$m))) ?></label>
              </div>
            <?php endforeach; ?>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Highest Grade Completed <span class="text-danger">*</span></legend>
            <?php foreach ($GRADE as $g): ?>
              <div class="custom-control custom-radio mb-2">
                <input class="custom-control-input" type="radio" id="grade_<?= h($g) ?>" name="highest_grade" value="<?= h($g) ?>" required>
                <label class="custom-control-label" for="grade_<?= h($g) ?>"><?= h(strtoupper(str_replace('_',' ', $g))) ?></label>
              </div>
            <?php endforeach; ?>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <div class="step">
          <div class="form-group">
            <label for="arrests_total">Total number of arrests (any reason)<span class="text-danger">*</span></label>
            <input id="arrests_total" name="arrests_total" type="number" min="0" max="999" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <div class="step">
          <div class="form-group">
            <label for="arrests_dwi">Total number of DWI/DUI arrests<span class="text-danger">*</span></label>
            <input id="arrests_dwi" name="arrests_dwi" type="number" min="0" max="999" class="form-control" required>
          </div>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <div class="step">
          <fieldset>
            <legend class="h6 mb-3">Prior Treatment<span class="text-danger">*</span></legend>
            <?php foreach ($TREAT as $t): ?>
              <div class="custom-control custom-radio mb-2">
                <input class="custom-control-input" type="radio" id="treat_<?= h($t) ?>" name="prior_treatment" value="<?= h($t) ?>" required>
                <label class="custom-control-label" for="treat_<?= h($t) ?>"><?= h(ucwords(str_replace('_',' ',$t))) ?></label>
              </div>
            <?php endforeach; ?>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>

        <!-- TF Instructions -->
        <div class="step">
          <p><strong>Please provide one answer, True (T) or False (F) for each question.</strong> There are no right or wrong answers; just answer the way you feel. Answer every question; blank responses are not permitted.</p>
          <button type="button" class="btn btn-primary next">Start</button>
        </div>

        <!-- T/F Items (1..74) -->
        <?php
          $n = 1;
          foreach ($SASSI_TF_CODES as $code):
            $label = humanize_label(preg_replace('/^\d+_/', '', $code));
            $isLastTF = ($n === count($SASSI_TF_CODES));
        ?>
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3"><?= h($n.'. '.$label) ?> <span class="text-danger">*</span></legend>
            <div class="custom-control custom-radio mb-2">
              <input class="custom-control-input" type="radio" id="tf_<?= h($code) ?>_t" name="tf_<?= h($code) ?>" value="1" required>
              <label class="custom-control-label" for="tf_<?= h($code) ?>_t">True</label>
            </div>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" id="tf_<?= h($code) ?>_f" name="tf_<?= h($code) ?>" value="0" required>
              <label class="custom-control-label" for="tf_<?= h($code) ?>_f">False</label>
            </div>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-3">Continue</button>
        </div>
        <?php $n++; endforeach; ?>

        <!-- Face-Valid Alcohol Instructions -->
        <div class="step">
          <p><strong>Face Valid Alcohol</strong><br>
          Remember this is over your entire life. “Drinks” and “drinking” refer to any type of alcohol - beer, wine, hard liquor, etc.</p>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- FVA 12 items -->
        <?php
          $i = 1; $cntFva = count($FVA_ITEMS);
          foreach ($FVA_ITEMS as $code => $label):
        ?>
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3"><?= h($label) ?> <span class="text-danger">*</span></legend>
            <?php foreach (['repeatedly'=>'Repeatedly','several_times'=>'Several times','once_or_twice'=>'Once or twice','never'=>'Never'] as $val=>$txt): ?>
              <div class="custom-control custom-radio mb-2">
                <input class="custom-control-input" type="radio" id="<?= h($code.'_'.$val) ?>" name="<?= h($code) ?>" value="<?= h($val) ?>" required>
                <label class="custom-control-label" for="<?= h($code.'_'.$val) ?>"><?= h($txt) ?></label>
              </div>
            <?php endforeach; ?>
          </fieldset>
          <button type="button" class="btn btn-primary next mt-2">Continue</button>
        </div>
        <?php $i++; endforeach; ?>

        <!-- Face-Valid Drugs Instructions -->
        <div class="step">
          <p><strong>Face Valid Drugs</strong><br>
          Remember this is over your entire life. The word “misuse” means taking medications in larger amounts than prescribed, longer than prescribed, or using medications not prescribed for you. “Drugs” include things like pot, cocaine, meth, heroin, etc.</p>
          <button type="button" class="btn btn-primary next">Continue</button>
        </div>

        <!-- FVD 19 items (last one shows Submit) -->
        <?php
          $i = 1; $cnt = count($FVD_ITEMS);
          $keys = array_keys($FVD_ITEMS);
          foreach ($keys as $idx => $code):
            $label = $FVD_ITEMS[$code];
            $isLast = ($idx === $cnt - 1);
        ?>
        <div class="step">
          <fieldset>
            <legend class="h6 mb-3"><?= h($label) ?> <span class="text-danger">*</span></legend>
            <?php foreach (['repeatedly'=>'Repeatedly','several_times'=>'Several times','once_or_twice'=>'Once or twice','never'=>'Never'] as $val=>$txt): ?>
              <div class="custom-control custom-radio mb-2">
                <input class="custom-control-input" type="radio" id="<?= h($code.'_'.$val) ?>" name="<?= h($code) ?>" value="<?= h($val) ?>" required>
                <label class="custom-control-label" for="<?= h($code.'_'.$val) ?>"><?= h($txt) ?></label>
              </div>
            <?php endforeach; ?>
          </fieldset>
          <?php if ($isLast): ?>
            <button type="submit" class="btn btn-success mt-3">Submit</button>
          <?php else: ?>
            <button type="button" class="btn btn-primary next mt-3">Continue</button>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>

      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const steps = Array.from(document.querySelectorAll('.step'));
  const nextButtons = Array.from(document.querySelectorAll('.next'));
  const bar = document.getElementById('bar');
  const label = document.getElementById('progressLabel');
  let idx = 0;

  function show(i){
    steps.forEach((s, k) => s.classList.toggle('active', k===i));
    const total = steps.length;
    const pct = Math.round(((i+1)/total)*100);
    if (bar) bar.style.width = pct + '%';
    if (label) label.textContent = 'Step ' + (i+1) + '/' + total;
    idx = i;
    window.scrollTo({top:0, behavior:'smooth'});
  }

  function stepValid(stepEl){
    const required = stepEl.querySelectorAll('[required]');
    for (const el of required) {
      if (el.type === 'radio') {
        const group = stepEl.querySelectorAll(`input[name="${el.name}"]`);
        if (![...group].some(r => r.checked)) return false;
      } else if (!el.checkValidity()) {
        return false;
      }
    }
    return true;
  }

  nextButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const stepEl = steps[idx];
      if (!stepValid(stepEl)) { alert('Please complete this step.'); return; }
      show(Math.min(idx+1, steps.length-1));
    });
  });

  show(0);
})();
</script>

<script>
// Treat Enter as "Continue" (never submit early)
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('sassiForm');
  function activeStepEl() {
    return document.querySelector('.step.active') ||
           Array.from(document.querySelectorAll('.step')).find(s => s.offsetParent !== null);
  }
  function stepIsValid(step) {
    if (!step) return false;
    const req = step.querySelectorAll('[required]');
    for (const el of req) {
      if (el.type === 'radio') {
        const group = step.querySelectorAll('input[name="'+el.name+'"]');
        if (![...group].some(r => r.checked)) return false;
      } else if (!el.checkValidity()) return false;
    }
    return true;
  }
  form.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') return;
    e.preventDefault();

    const step = activeStepEl();
    const submitBtn = step ? Array.from(step.querySelectorAll('button[type="submit"],input[type="submit"]'))
                               .find(b => b.offsetParent !== null) : null;

    if (submitBtn) {
      if (stepIsValid(step)) submitBtn.click();
      return;
    }
    const nextBtn = step ? Array.from(step.querySelectorAll('.next')).find(b => b.offsetParent !== null) : null;
    if (stepIsValid(step) && nextBtn) nextBtn.click();
  }, true);
});
</script>
</body>
</html>
