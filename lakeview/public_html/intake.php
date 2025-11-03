<?php

declare(strict_types=1);
ob_start();
session_start();

const ADMIN_ALERT_EMAIL = 'admin@notesao.com';


require_once dirname(__DIR__) . '/config/config.php';  
/** @var mysqli $link */
$db = $link;
$db->set_charset('utf8mb4');


$programs = [];
$tbl = null;
foreach (['program','programs'] as $t) {
    $res = $db->query("SHOW TABLES LIKE '$t'");
    if ($res && $res->num_rows) { $tbl = $t; break; }
}
if ($tbl) {
    $cols = [];
    if ($res = $db->query("SHOW COLUMNS FROM `$tbl`")) {
        while ($r = $res->fetch_assoc()) { $cols[$r['Field']] = true; }
    }
    $idCol = isset($cols['id']) ? 'id' : (isset($cols['program_id']) ? 'program_id' : null);
    $labelCol = null;
    foreach (['name','program_name','title','label','program'] as $c) {
        if (isset($cols[$c])) { $labelCol = $c; break; }
    }
    if ($idCol && $labelCol) {
        $sql = "SELECT `$idCol` AS id, `$labelCol` AS label
                FROM `$tbl`
                WHERE `$labelCol` IS NOT NULL AND `$labelCol` <> ''
                ORDER BY `$labelCol`";
        if ($res = $db->query($sql)) {
            while ($r = $res->fetch_assoc()) {
                $programs[(int)$r['id']] = $r['label'];
            }
        }
    }
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_check(): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf'], $_POST['csrf_token'])) {
        http_response_code(403); exit('Invalid CSRF token');
    }
}

function postv(string $k): ?string { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : null; }
function postb(string $k): int     { return (isset($_POST[$k]) && $_POST[$k]) ? 1 : 0; }


function require_fields(array $names): void {
  $miss=[]; foreach ($names as $n) {
    if (!isset($_POST[$n])) { $miss[]=$n; continue; }
    $v=$_POST[$n]; if (is_array($v)) { if (!array_filter($v, fn($x)=>trim((string)$x)!=='')) $miss[]=$n; }
    else { if (trim((string)$v)==='') $miss[]=$n; }
  }
  if ($miss) { http_response_code(422); exit('Missing required: '.implode(', ',$miss)); }
}
function posta_csv(string $k): string {
  $v=$_POST[$k]??[]; if(!is_array($v)) $v=[$v];
  $v=array_map(static fn($x)=>trim((string)$x),$v);
  return implode(', ', array_filter($v,static fn($x)=>$x!==''));
}
function detect_program_code(string $name): string {
  $n=mb_strtolower($name);
  if (str_contains($n,'parent')) return 'parent';
  if (preg_match('/\\bdwi\\s*ii\\b|repeat/',$n)) return 'dwii';
  if (str_contains($n,'dwie')||str_contains($n,'dwi education')) return 'dwie';
  if (str_contains($n,'doep')||str_contains($n,'drug offender')) return 'doep';
  if (str_contains($n,'life')||str_contains($n,'anti')||str_contains($n,'anti-theft')) return 'lsat';
  if (str_contains($n,'bipp')) return 'bipp';
  return 'bipp';
}
function yn_to_bit($v) {
  $v = strtolower(trim((string)$v));
  if ($v === 'yes' || $v === '1') return 1;
  if ($v === 'no'  || $v === '0') return 0;
  return null; // for 'na' or blank
}

function get_json_array($arr) {
  if (!is_array($arr)) return null;
  $clean = array_values(array_filter(array_map('trim', $arr), 'strlen'));
  return $clean ? json_encode($clean) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // resolve program_id first so validation can require it
    $posted_program = postv('program_id');
    $gender = postv('gender_id'); // may be null here

    if ($posted_program !== null && isset($programs[(int)$posted_program])) {
        $program_id = (int)$posted_program;
    } else {
        // fallback pick based on gender and first BIPP match
        $wantFemale = ($gender === '3');
        $pick = null;
        foreach ($programs as $pid => $pname) {
            $n = mb_strtolower($pname);
            if (strpos($n,'bipp') !== false) {
                if ($wantFemale && (strpos($n,'female') !== false || strpos($n,'women') !== false)) { $pick = (int)$pid; break; }
                if (!$wantFemale && (strpos($n,'male') !== false || strpos($n,'men')  !== false)) { $pick = (int)$pid; break; }
            }
        }
        if ($pick === null) $pick = (int)array_key_first($programs);
        $program_id = $pick;
    }
    $_POST['program_id'] = (string)$program_id;

    $program_name = $programs[$program_id] ?? '';
    $pcode = detect_program_code($program_name);

    require_fields(['program_id','first_name','last_name','email','gender_id']);


    switch ($pcode) {
      case 'dwie': {
        $_POST['first_name']  ??= $_POST['dwie_first_name']  ?? null;
        $_POST['last_name']   ??= $_POST['dwie_last_name']   ?? null;
        $_POST['email']       ??= $_POST['dwie_email']       ?? null;
        $_POST['phone_cell']  ??= $_POST['dwie_phone']       ?? null;

        $_POST['drivers_license_number']             ??= $_POST['dwie_dl_number'] ?? null;
        $_POST['date_of_birth']                      ??= $_POST['dwie_dob'] ?? null;
        $_POST['street_address']                     ??= $_POST['dwie_street_address'] ?? null;
        $_POST['race_ethnicity']                     ??= $_POST['race_id'] ?? null;
        $_POST['marital_status']                     ??= $_POST['dwie_marital_status'] ?? null;
        $_POST['times_married']                      ??= $_POST['dwie_times_married'] ?? null;
        $_POST['family_problems_due_to_use']         ??= $_POST['dwie_substance_contrib_family'] ?? null;
        $_POST['education_level']                    ??= $_POST['dwie_education_level'] ?? null;
        $_POST['employment_history_list']            ??= $_POST['dwie_job_history'] ?? null;
        $_POST['unemployment_total_last_3_years']    ??= $_POST['dwie_unemployed_total_3yrs'] ?? null;
        $_POST['case_cause_number']                  ??= $_POST['dwie_case_cause'] ?? null;
        $_POST['county_of_arrest']                   ??= $_POST['dwie_county_of_arrest'] ?? null;

        $_POST['arrests_total_count']                ??= $_POST['dwie_times_arrested'] ?? null;
        $_POST['arrests_dwi_count']                  ??= $_POST['dwie_times_arrested_dwi'] ?? null;
        $_POST['bac']                                ??= $_POST['dwie_bac'] ?? null;
        $_POST['bac_unknown_over_0_15']              ??= $_POST['dwie_bac_over_0_15'] ?? null;
        $_POST['license_conditions_history']         ??= $_POST['dwie_license_conditions_history'] ?? null;
        $_POST['license_status_at_arrest']           ??= $_POST['dwie_license_status_at_arrest'] ?? null;
        $_POST['accident_involved']                  ??= $_POST['dwie_accident_involved'] ?? null;
        $_POST['anyone_injured']                     ??= $_POST['dwie_anyone_injured'] ?? null;

        if (empty($_POST['alcohol_use_locations']) && !empty($_POST['dwie_use_places'])) {
          $_POST['alcohol_use_locations'] = (array)$_POST['dwie_use_places'];
        }
        $_POST['age_began_drinking']                 ??= $_POST['dwie_age_began_drinking'] ?? null;
        $_POST['age_first_arrest']                   ??= $_POST['dwie_age_first_arrest'] ?? null;
        $_POST['age_first_alcohol_related_arrest']   ??= $_POST['dwie_age_first_alcohol_arrest'] ?? null;
        $_POST['thought_has_alcohol_problem']        ??= $_POST['dwie_thought_alcohol_problem'] ?? null;
        $_POST['received_help']                      ??= $_POST['dwie_received_help'] ?? null;
        if (empty($_POST['attended_help_types']) && !empty($_POST['dwie_attended'])) {
          $_POST['attended_help_types'] = (array)$_POST['dwie_attended'];
        }
        break;
      }
      case 'dwii': {
        $_POST['participant_agreement']          ??= $_POST['dwii_agree_participant_rules'] ?? null;
        $_POST['compliance_acknowledgement']     ??= $_POST['dwii_agree_comply'] ?? null;

        $_POST['drivers_license_number']         ??= $_POST['dwii_dl_number'] ?? null;
        $_POST['date_of_birth']                  ??= $_POST['dwii_dob'] ?? null;
        $_POST['street_address']                 ??= $_POST['dwii_street_address'] ?? null;
        $_POST['marital_status']                 ??= $_POST['dwii_marital_status'] ?? null;
        $_POST['times_married']                  ??= $_POST['dwii_times_married'] ?? null;
        $_POST['family_problems_due_to_use']     ??= $_POST['dwii_substance_contrib_family'] ?? null;
        $_POST['education_level']                ??= $_POST['dwii_education_level'] ?? null;
        $_POST['employment_status']              ??= $_POST['dwii_employment_status'] ?? null;
        $_POST['employment_history_list']        ??= $_POST['dwii_job_history'] ?? null;
        $_POST['unemployment_total_last_3_years']??= $_POST['dwii_unemployed_total_3yrs'] ?? null;

        $_POST['case_cause_number']              ??= $_POST['dwii_case_cause'] ?? null;
        $_POST['county_of_arrest']               ??= $_POST['dwii_county_of_arrest'] ?? null;
        $_POST['referral_contact_name_and_county'] ??= (
          isset($_POST['referral_contact_name_and_county']) ? $_POST['referral_contact_name_and_county'] :
          (isset($_POST['dwii_po_attorney_name'], $_POST['dwii_po_attorney_county'])
            ? ($_POST['dwii_po_attorney_name'] . ', ' . $_POST['dwii_po_attorney_county'])
            : null)
        );

        $_POST['arrests_total_count']            ??= $_POST['dwii_times_arrested_any'] ?? null;
        $_POST['arrests_dwi_count']              ??= $_POST['dwii_times_arrested_dwi'] ?? null;
        $_POST['bac']                            ??= $_POST['dwii_bac'] ?? null;
        $_POST['bac_unknown_over_0_15']          ??= $_POST['dwii_bac_over_0_15'] ?? null;
        $_POST['license_conditions_history']     ??= $_POST['dwii_license_conditions_history'] ?? null;
        $_POST['license_status_at_arrest']       ??= $_POST['dwii_license_status_at_arrest'] ?? null;
        $_POST['accident_involved']              ??= $_POST['dwii_accident_involved'] ?? null;
        $_POST['anyone_injured']                 ??= $_POST['dwii_anyone_injured'] ?? null;

        if (empty($_POST['alcohol_use_locations']) && !empty($_POST['dwii_use_places'])) {
          $_POST['alcohol_use_locations'] = (array)$_POST['dwii_use_places'];
        }
        $_POST['age_began_drinking']             ??= $_POST['dwii_age_began_drinking'] ?? null;
        $_POST['age_first_arrest']               ??= $_POST['dwii_age_first_arrest'] ?? null;
        $_POST['age_first_alcohol_related_arrest'] ??= $_POST['dwii_age_first_alcohol_arrest'] ?? null;
        $_POST['thought_has_alcohol_problem']    ??= $_POST['dwii_thought_alcohol_problem'] ?? null;
        $_POST['thought_has_drug_problem']       ??= $_POST['dwii_thought_drug_problem'] ?? null;
        $_POST['received_help']                  ??= $_POST['dwii_received_help'] ?? null;
        if (empty($_POST['attended_help_types']) && !empty($_POST['dwii_attended'])) {
          $_POST['attended_help_types'] = (array)$_POST['dwii_attended'];
        }
        break;
      }
      case 'doep': {
        $_POST['today_date']                     ??= $_POST['doep_today_date'] ?? null;
        $_POST['drivers_license_number']         ??= $_POST['doep_dl_number'] ?? null;
        $_POST['date_of_birth']                  ??= $_POST['doep_dob'] ?? null;
        $_POST['race_ethnicity']                 ??= $_POST['race_id'] ?? null;
        $_POST['marital_status']                 ??= $_POST['doep_marital_status'] ?? null;
        $_POST['times_married']                  ??= $_POST['doep_times_married'] ?? null;
        $_POST['family_problems_due_to_use']     ??= $_POST['doep_substance_contrib_family'] ?? null;
        $_POST['education_level']                ??= $_POST['doep_education_level'] ?? null;
        $_POST['employment_history_list']        ??= $_POST['doep_job_history'] ?? null;
        $_POST['unemployment_total_last_3_years']??= $_POST['doep_unemployed_total_3yrs'] ?? null;
        $_POST['case_cause_number']              ??= $_POST['doep_case_cause'] ?? null;
        $_POST['county_of_arrest']               ??= $_POST['doep_county_of_arrest'] ?? null;
        $_POST['referral_contact_county']        ??= $_POST['doep_probation_attorney_county'] ?? null;

        // Build street_address from parts if provided
        if (empty($_POST['street_address']) && isset($_POST['doep_street'])) {
          $parts = array_filter([
            $_POST['doep_street'] ?? null,
            $_POST['doep_city']   ?? null,
            $_POST['doep_state']  ?? null,
            $_POST['doep_zip']    ?? null,
          ]);
          $_POST['street_address'] = $parts ? implode(', ', $parts) : null;
        }

        $_POST['arrests_total_count']            ??= $_POST['doep_times_arrested'] ?? null;
        $_POST['bac']                            ??= $_POST['doep_bac'] ?? null;
        $_POST['license_status_at_arrest']       ??= $_POST['doep_license_status_at_arrest'] ?? null;
        $_POST['send_completion_to_dps']         ??= $_POST['doep_send_certificate_to_dps'] ?? null;
        $_POST['confidential_info_notice_page']  ??= $_POST['doep_confidential_info_notice'] ?? $_POST['doep_confidential_notice'] ?? null;

        if (empty($_POST['usual_use_locations']) && !empty($_POST['doep_use_places'])) {
          $_POST['usual_use_locations'] = (array)$_POST['doep_use_places'];
        }
        $_POST['age_began_use']                  ??= $_POST['doep_age_began_use'] ?? null;
        $_POST['age_first_arrest']               ??= $_POST['doep_age_first_arrest'] ?? null;
        $_POST['age_first_drug_related_arrest']  ??= $_POST['doep_age_first_drug_arrest'] ?? null;
        $_POST['thought_has_alcohol_or_drug_problem'] ??= $_POST['doep_thought_problem'] ?? null;
        $_POST['received_help']                  ??= $_POST['doep_received_help'] ?? null;
        if (empty($_POST['attended_help_types']) && !empty($_POST['doep_attended'])) {
          $_POST['attended_help_types'] = (array)$_POST['doep_attended'];
        }
        $_POST['consent_progress_shared_with_court'] ??= $_POST['doep_consent_share_with_court'] ?? null;
        break;
      }
    }

    $req = [];
    switch ($pcode) {
      case 'lsat':
        $req=['today_date','drivers_license_number','date_of_birth','county_of_arrest',
              'charge_reason','referral_contact_location','offense_level','rules_page','agree_disclosure'];

        break;
      case 'parent':
        $req = [
          'first_name','last_name','email','phone_number','gender_id',
          'po_attorney_caseworker_name','today_date',
          'confidentiality_notice_page','failed_drug_test','received_substance_treatment',
          'current_charge_or_situation','consent_share_attendance_ack'
        ];
        $_POST['consent_share_attendance_ack'] ??= $_POST['ack_confidentiality'] ?? null;

        break;

      case 'doep':
        $req=[
          'today_date','drivers_license_number','street_address','date_of_birth','race_ethnicity',
          'marital_status','times_married','family_problems_due_to_use','education_level',
          'employment_history_list','unemployment_total_last_3_years','case_cause_number',
          'county_of_arrest','referral_contact_county','arrests_total_count','bac',
          'license_status_at_arrest','send_completion_to_dps','confidential_info_notice_page',
          'usual_use_locations','age_began_use','age_first_drug_related_arrest','received_help',
          'consent_progress_shared_with_court'
        ];
        break;
      case 'dwie':
        $req=['drivers_license_number','street_address','date_of_birth','race_ethnicity','marital_status',
              'times_married','family_problems_due_to_use','education_level','employment_history_list',
              'unemployment_total_last_3_years','case_cause_number','county_of_arrest',
              'probation_attorney_name_and_county','arrests_count_and_years','license_status_at_arrest',
              'accident_involved','anyone_injured','send_completion_to_dps','alcohol_use_locations',
              'age_began_drinking','age_first_arrest','age_first_alcohol_related_arrest',
              'thought_has_alcohol_problem','received_help','attended_help_types',
              'consent_progress_shared_with_court'];
              $_POST['probation_attorney_name_and_county'] ??=
                (isset($_POST['dwie_probation_attorney_name'], $_POST['dwie_probation_attorney_county'])
                  ? ($_POST['dwie_probation_attorney_name'] . ', ' . $_POST['dwie_probation_attorney_county'])
                  : null);

              // combine count + years for required field
              $_POST['arrests_count_and_years'] ??=
                (isset($_POST['dwie_times_arrested'], $_POST['dwie_years_of_arrests'])
                  ? ($_POST['dwie_times_arrested'] . '; ' . $_POST['dwie_years_of_arrests'])
                  : null);
        break;
      case 'dwii':
        $req=['participant_agreement','compliance_acknowledgement','date_of_birth','drivers_license_number',
              'street_address','case_cause_number','county_of_arrest','send_completion_to_dps',
              'times_married','family_problems_due_to_use','household_size','employment_history_list',
              'unemployment_total_last_3_years','arrests_total_count','arrests_dwi_count','bac',
              'bac_unknown_over_0_15','license_conditions_history','license_status_at_arrest',
              'accident_involved','anyone_injured','alcohol_use_locations','age_began_drinking',
              'age_first_arrest','age_first_alcohol_related_arrest','thought_has_alcohol_problem',
              'thought_has_drug_problem','received_help','attended_help_types',
              'consent_progress_shared_with_court'];
        break;
      case 'bipp':
        break;
      default:
        $pcode = 'bipp';
        break;

    }
    if ($req) require_fields($req);

    $phone_number = postv('phone_number') ?? postv('phone_cell');
    $referral_contact_name = postv('referral_contact_name')
      ?? postv('referring_officer_name')
      ?? postv('po_attorney_caseworker_name');

    $commonReq = ['first_name','last_name','date_of_birth','email','digital_signature'];
    foreach ($commonReq as $req) {
      if (postv($req) === '') exit("<h3>Missing required field: $req</h3>");
    }

    if ($pcode === 'bipp') {
      if (in_array('Other', $_POST['reasons'] ?? [], true) && postv('other_reason_text') === '') {
        exit('<h3>Please explain the “Other” reason.</h3>');
      }

      foreach (['agree_confidentiality','agree_disclosure','agree_program','agree_responsibility','agree_termination'] as $ck) {
        if (postb($ck) !== 1) exit("<h3>Missing required consent: $ck</h3>");
      }
    }



    $gender = postv('gender_id');   
    $posted_program = postv('program_id');

    if ($posted_program !== null && isset($programs[(int)$posted_program])) {
        $program_id = (int)$posted_program;
    } else {
        $wantFemale = ($gender === '3');
        $pick = null;
        foreach ($programs as $pid => $pname) {
            $n = mb_strtolower($pname);
            if (strpos($n, 'bipp') !== false) {
                if ($wantFemale && (strpos($n, 'female') !== false || strpos($n, 'women') !== false)) { $pick = (int)$pid; break; }
                if (!$wantFemale && (strpos($n, 'male') !== false || strpos($n, 'men') !== false))     { $pick = (int)$pid; break; }
            }
        }
        if ($pick === null) $pick = (int)array_key_first($programs);
        $program_id = $pick;
    }

    $intake_date_raw    = postv('intake_date')    ?: date('Y-m-d');
    $signature_date_raw = postv('signature_date') ?: date('Y-m-d');

    $intake_ts    = $intake_date_raw    ? strtotime($intake_date_raw)    : null;
    $signature_ts = $signature_date_raw ? strtotime($signature_date_raw) : null;

    if ($intake_ts && $signature_ts && $signature_ts > $intake_ts) {
        $signature_date_raw = date('Y-m-d', $intake_ts);
    }


    $fields = [
        'first_name'            => postv('first_name'),
        'last_name'             => postv('last_name'),
        'email'                 => postv('email'),
        'phone_cell'            => postv('phone_cell'),
        'date_of_birth'         => postv('date_of_birth'),
        'gender_id'             => $gender,
        'program_id'            => $program_id,
        'id_number'             => postv('id_number'),

        'address_street'        => postv('address_street'),
        'address_city'          => postv('address_city'),
        'address_state'         => postv('address_state'),
        'address_zip'           => postv('address_zip'),
        'birth_city'            => postv('birth_city'),
        'race_id'                  => postv('race_id'),
        'education_level'       => postv('education_level'),

        'employed'              => postb('employed'),
        'employer'              => postv('employer'),
        'occupation'            => postv('occupation'),

        'emergency_name'        => postv('emergency_name'),
        'emergency_phone'       => postv('emergency_phone'),
        'emergency_relation'    => postv('emergency_relation'),
        'military_branch'       => postv('military_branch'),
        'military_date'         => postv('military_date'),

        'referral_type_id'         => postv('referral_type_id'),
        'referring_officer_name'   => postv('referring_officer_name'),
        'referring_officer_email'  => postv('referring_officer_email'),
        'additional_charge_dates'  => postv('additional_charge_dates'),
        'additional_charge_details'=> postv('additional_charge_details'),

        'living_situation'      => postv('living_situation'),
        'marital_status'        => postv('marital_status'),
        'has_children'          => postb('has_children'),
        'children_live_with_you'=> postb('children_live_with_you'),
        'children_names_ages'   => postv('children_names_ages'),
        'child_abuse_physical'  => postb('abused_physically'),
        'child_abuse_sexual'    => postb('abused_sexually'),
        'child_abuse_emotional' => postb('abused_emotionally'),
        'child_abuse_neglect'   => postb('children_neglected'),
        'cps_notified'          => postb('cps_notified'),
        'cps_care'              => postb('cps_care'),
        'discipline_desc'       => postv('discipline_desc'),

        'alcohol_past'          => postb('alcohol_past'),
        'alcohol_frequency'  => postv('alcohol_frequency'),
        'alcohol_current'       => postb('alcohol_current'),
        'alcohol_current_details'=> postv('alcohol_current_details'),
        'drug_past'             => postb('drug_past'),
        'drug_past_details'     => postv('drug_past_details'),
        'drug_current'          => postb('drug_current'),
        'drug_current_details'  => postv('drug_current_details'),
        'alcohol_during_abuse'  => postb('alcohol_during_abuse'),
        'drug_during_abuse'     => postb('drug_during_abuse'),

        'counseling_history'    => postb('counseling_history'),
        'counseling_reason'     => postv('counseling_reason'),
        'depressed_currently'   => postb('depressed_currently'),
        'depression_reason'     => postv('depression_reason'),
        'attempted_suicide'     => postb('attempted_suicide'),
        'suicide_last_attempt'  => postv('suicide_last_attempt'),
        'mental_health_meds'    => postb('mental_health_meds'),
        'mental_meds_list'      => postv('mental_meds_list'),
        'mental_doctor_name'    => postv('mental_doctor_name'),
        'sexual_abuse_history'  => postb('sexual_abuse_history'),
        'head_trauma_history'   => postb('head_trauma_history'),
        'head_trauma_desc'      => postv('head_trauma_desc'),
        'weapon_possession_history' => postb('weapon_possession_history'),
        'abuse_trauma_history'  => postb('abuse_trauma_history'),
        'violent_incident_desc' => postv('violent_incident_desc'),

        'victim_contact_provided' => postb('victim_knowledge'),
        'victim_relationship'     => postv('victim_relationship'),
        'victim_first_name'       => postv('victim_first_name'),
        'victim_last_name'        => postv('victim_last_name'),
        'victim_age'              => postv('victim_age'),
        'victim_gender'           => postv('victim_gender'),
        'victim_phone'            => postv('victim_phone'),
        'victim_email'            => postv('victim_email'),
        'victim_address'          => postv('victim_address'),
        'victim_city'             => postv('victim_city'),
        'victim_state'            => postv('victim_state'),
        'victim_zip'              => postv('victim_zip'),
        'live_with_victim'        => postb('live_with_victim'),
        'children_with_victim'    => postv('children_under_18'),

        'consent_confidentiality'   => postb('agree_confidentiality'),
        'consent_disclosure'        => postb('agree_disclosure'),
        'consent_program_agreement' => postb('agree_program'),
        'consent_responsibility'    => postb('agree_responsibility'),
        'consent_policy_termination'=> postb('agree_termination'),

        'reasons'               => implode(', ', $_POST['reasons'] ?? []),
        'other_reason_text'     => postv('other_reason_text'),
        'offense_description'   => postv('describe_reason'),
        'personal_goal'         => postv('personal_goal_bipp'),
        'counselor_name'        => postv('counselor'),
        'chosen_group_time'     => postv('group_time'),

        'intake_date'           => $intake_date_raw,
        'digital_signature'     => postv('digital_signature'),
        'signature_date'        => $signature_date_raw,

        'packet_complete'       => 1
    ];
    $fields += [
      'program_id'            => (string)$program_id,
      'first_name'            => postv('first_name'),
      'last_name'             => postv('last_name'),
      'email'                 => postv('email'),
      'phone_number'          => $phone_number,
      'gender_id'             => postv('gender_id'),
      'referral_contact_name' => $referral_contact_name,
    ];

    switch ($pcode) {
      case 'lsat':
        $fields += [
          'today_date'                => postv('today_date'),
          'drivers_license_number'    => postv('drivers_license_number'),
          'date_of_birth'             => postv('date_of_birth'),
          'county_of_arrest'          => postv('county_of_arrest'),
          'charge_reason'             => postv('charge_reason'),
          'referral_contact_location' => postv('referral_contact_location'),
          'offense_level'             => postv('offense_level'),
          'rules_page'                => postb('rules_page'),
          'thank_you_page'            => 1,
        ];
        break;

      case 'parent':
        $fields += [
          'today_date'                      => postv('today_date'),
          'confidentiality_notice_page'     => postb('confidentiality_notice_page'),
          'failed_drug_test'                => postv('failed_drug_test'),
          'received_substance_treatment'    => postv('received_substance_treatment'),
          'drug_of_choice'                  => postv('drug_of_choice'),
          'current_charge_or_situation'     => postv('current_charge_or_situation'),
          'consent_share_attendance_ack'    => postb('consent_share_attendance_ack'),
        ];
        break;

      case 'doep':
        $fields += [
          'today_date'                      => postv('today_date'),
          'drivers_license_number'          => postv('drivers_license_number'),
          'street_address'                  => postv('street_address'),
          'date_of_birth'                   => postv('date_of_birth'),
          'race_ethnicity'                  => postv('race_ethnicity'),
          'marital_status'                  => postv('marital_status'),
          'times_married'                   => postv('times_married'),
          'family_problems_due_to_use'      => postv('family_problems_due_to_use'),
          'education_level'                 => postv('education_level'),
          'employment_history_list'         => postv('employment_history_list'),
          'unemployment_total_last_3_years' => postv('unemployment_total_last_3_years'),
          'case_cause_number'               => postv('case_cause_number'),
          'county_of_arrest'                => postv('county_of_arrest'),
          'referral_contact_county'         => postv('referral_contact_county'),
          'arrests_count_and_years'         => postv('arrests_count_and_years'),
          'bac'                         => postv('bac'),
          'license_status_at_arrest'        => postv('license_status_at_arrest'),
          'accident_involved'               => postv('accident_involved'),
          'anyone_injured'                  => postv('anyone_injured'),
          'send_completion_to_dps'          => postv('send_completion_to_dps'),
          'confidential_info_notice_page'   => postb('confidential_info_notice_page'),
          'usual_use_locations'             => posta_csv('usual_use_locations'),
          'age_began_use'                   => postv('age_began_use'),
          'age_first_arrest'                => postv('age_first_arrest'),
          'age_first_drug_related_arrest'   => postv('age_first_drug_related_arrest'),
          'thought_has_alcohol_or_drug_problem' => postv('thought_has_alcohol_or_drug_problem'),
          'received_help'                   => postv('received_help'),
          'attended_help_types'             => posta_csv('attended_help_types'),
          'consent_progress_shared_with_court' => postb('consent_progress_shared_with_court'),
        ];
        break;

      case 'dwie':
        $fields += [
          'drivers_license_number'             => postv('drivers_license_number'),
          'street_address'                     => postv('street_address'),
          'date_of_birth'                      => postv('date_of_birth'),
          'race_ethnicity'                     => postv('race_ethnicity'),
          'marital_status'                     => postv('marital_status'),
          'times_married'                      => postv('times_married'),
          'family_problems_due_to_use'         => postv('family_problems_due_to_use'),
          'education_level'                    => postv('education_level'),
          'employment_history_list'            => postv('employment_history_list'),
          'unemployment_total_last_3_years'    => postv('unemployment_total_last_3_years'),
          'case_cause_number'                  => postv('case_cause_number'),
          'county_of_arrest'                   => postv('county_of_arrest'),
          'probation_attorney_name_and_county' => postv('probation_attorney_name_and_county'),
          'arrests_count_and_years'            => postv('arrests_count_and_years'),
          'bac'                            => postv('bac'),
          'license_status_at_arrest'           => postv('license_status_at_arrest'),
          'accident_involved'                  => postv('accident_involved'),
          'anyone_injured'                     => postv('anyone_injured'),
          'send_completion_to_dps'             => postv('send_completion_to_dps'),
          'alcohol_use_locations'              => posta_csv('alcohol_use_locations'),
          'age_began_drinking'                 => postv('age_began_drinking'),
          'age_first_arrest'                   => postv('age_first_arrest'),
          'age_first_alcohol_related_arrest'   => postv('age_first_alcohol_related_arrest'),
          'thought_has_alcohol_problem'        => postv('thought_has_alcohol_problem'),
          'received_help'                      => postv('received_help'),
          'attended_help_types'                => posta_csv('attended_help_types'),
          'consent_progress_shared_with_court' => postb('consent_progress_shared_with_court'),
        ];
        break;

      case 'dwii':
        $fields += [
          'participant_agreement'           => postb('participant_agreement'),
          'compliance_acknowledgement'      => postb('compliance_acknowledgement'),
          'date_of_birth'                   => postv('date_of_birth'),
          'drivers_license_number'          => postv('drivers_license_number'),
          'street_address'                  => postv('street_address'),
          'case_cause_number'               => postv('case_cause_number'),
          'county_of_arrest'                => postv('county_of_arrest'),
          'referral_contact_name_and_county'=> postv('referral_contact_name_and_county'),
          'send_completion_to_dps'          => postv('send_completion_to_dps'),
          'marital_status'                  => postv('marital_status'),
          'times_married'                   => postv('times_married'),
          'family_problems_due_to_use'      => postv('family_problems_due_to_use'),
          'household_size'                  => postv('household_size'),
          'education_level'                 => postv('education_level'),
          'employment_status'               => postv('employment_status'),
          'employment_history_list'         => postv('employment_history_list'),
          'unemployment_total_last_3_years' => postv('unemployment_total_last_3_years'),
          'arrests_total_count'             => postv('arrests_total_count'),
          'arrests_dwi_count'               => postv('arrests_dwi_count'),
          'bac'                             => postv('bac'),
          'bac_unknown_over_0_15'           => postv('bac_unknown_over_0_15'),
          'license_conditions_history'      => postv('license_conditions_history'),
          'license_status_at_arrest'        => postv('license_status_at_arrest'),
          'accident_involved'               => postv('accident_involved'),
          'anyone_injured'                  => postv('anyone_injured'),
          'alcohol_use_locations'           => posta_csv('alcohol_use_locations'),
          'age_began_drinking'              => postv('age_began_drinking'),
          'age_first_arrest'                => postv('age_first_arrest'),
          'age_first_alcohol_related_arrest'=> postv('age_first_alcohol_related_arrest'),
          'thought_has_alcohol_problem'     => postv('thought_has_alcohol_problem'),
          'thought_has_drug_problem'        => postv('thought_has_drug_problem'),
          'received_help'                   => postv('received_help'),
          'attended_help_types'             => posta_csv('attended_help_types'),
          'consent_progress_shared_with_court' => postb('consent_progress_shared_with_court'),
        ];
        break;
    }

    $colsRes = $db->query("SHOW COLUMNS FROM intake_packet");
    $validCols = [];
    while ($row = $colsRes->fetch_assoc()) $validCols[$row['Field']] = true;
    $fields = array_intersect_key($fields, $validCols);
    if (!$fields) exit('No valid columns to insert.');

    $cols  = array_keys($fields);
    $place = array_fill(0, count($cols), '?');
    $sql   = 'INSERT INTO intake_packet ('.implode(',', $cols).') VALUES ('.implode(',', $place).')';

    if (substr_count($sql,'?') !== count($fields)) {
        exit('Developer error: placeholder / param count mismatch');
    }

    $stmt = $db->prepare($sql) or exit('Server error.');
    $types = str_repeat('s', count($fields)); 
    $stmt->bind_param($types, ...array_values($fields));
    $stmt->execute();
    if ($stmt->error) { error_log($stmt->error); exit('Could not save packet.'); }

    $_SESSION['show_thank_you_once'] = true;

    $fname  = preg_replace('/[\r\n]+/', ' ', (string)postv('first_name'));
    $lname  = preg_replace('/[\r\n]+/', ' ', (string)postv('last_name'));
    $reply  = preg_replace('/[\r\n]+/', ' ', (string)postv('email'));

    $headers  = "From: reporting@lakeview.notesao.com\r\n";
    $headers .= "Reply-To: $reply\r\n";
    $headers .= "X-Mailer: PHP/".PHP_VERSION;

    @mail(
        ADMIN_ALERT_EMAIL,
        "Lakeview Education has received a new Intake Packet for $fname $lname",
        "A new online intake packet was submitted.\n\nView pending & submitted packets:\nhttps://{$_SERVER['HTTP_HOST']}/intake-index.php",
        $headers,
        '-freporting@lakeview.notesao.com'
    );

    header('Location: ' . $_SERVER['PHP_SELF'], true, 303);
    exit;


}

if (!empty($_SESSION['show_thank_you_once'])) {
    unset($_SESSION['show_thank_you_once']);  
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title>Thank you – BIPP Intake</title>

      <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
      <link rel="stylesheet"
            href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <style>
        body{font-family:system-ui,Arial;background:#f5f6fa;padding:2rem}
        .card{max-width:720px;margin:0 auto;border:0;border-radius:8px;
              box-shadow:0 2px 8px rgba(0,0,0,.08)}
      </style>
    </head>
    <body>

    <div class="jumbotron bg-white text-center shadow-sm py-4 mb-4">
      <a href="https://lakevieweducation.com/" target="_blank" rel="noopener">
        <img src="lakeviewlogo.png" alt="Lakeview Education"
             class="img-fluid mb-1" style="max-width:60%;height:auto">
      </a>
    </div>

    <div class="card shadow-sm">
      <div class="card-body text-center p-5">
        <h2 class="mb-3">Thank you!</h2>
        <p class="lead mb-2">Your Intake Packet was submitted successfully.</p>
        <p class="mb-2">
          We have marked it <em>received</em> in your chart.
          We look forward to seeing you in group!
        </p>
        <p class="mb-4">
          <strong>Remember:</strong> use the <em>first link</em> in your e‑mail
          to join your group session.
        </p>
        <a href="https://lakevieweducation.com/" class="btn btn-primary btn-lg">
          Lakeview Education Home
        </a>
      </div>
    </div>

    <script>localStorage.clear();</script>
    </body>
    </html>
    <?php
    ob_end_flush();
    exit;
}

$selected_program = postv('program_id');

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Lakeview Education | BIPP Intake</title>
<link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
<link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">

<link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">

<link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="167x167" href="/favicons/apple-touch-icon-ipad-pro.png">
<link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-touch-icon-ipad.png">
<link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-touch-icon-120x120.png">

<link rel="manifest" href="/favicons/site.webmanifest">
<meta name="apple-mobile-web-app-title" content="NotesAO">
<link rel="stylesheet" 
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

<link rel="stylesheet" 
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
 body{font-family:system-ui,Arial,sans-serif;margin:0;background:#f5f6fa;padding:0 1rem}
 h1{text-align:center;font-size:2.0rem;margin:1rem 0}
 form{background:#fff;border-radius:8px;max-width:900px;margin:1rem auto;padding:2rem;box-shadow:0 2px 6px rgba(0,0,0,.08)}
 fieldset{border:0;margin:0 0 1.75rem;padding:0}
 legend{font-weight:700;margin-bottom:.5rem}
 .row{display:flex;gap:1rem;flex-wrap:wrap}
 .col{flex:1;min-width:220px}
 label{font-weight:600;display:block;margin-bottom:.25rem}
 input,select,textarea{width:100%;padding:.5rem;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;font-size:.95rem}
 textarea{min-height:90px}
 input[type=checkbox]{width:auto;margin-right:.35rem}
 .required:after{content:"*";color:#b00;margin-left:.25rem}
 button{display:block;margin:2rem auto 0;padding:.75rem 2rem;border:0;border-radius:4px;font-size:1rem;background:#1076d5;color:#fff;cursor:pointer}
 small{color:#555;display:block;margin-top:.25rem}
 .policy{border:1px solid #ddd;border-radius:4px;background:#fafafa;padding:1rem;margin-bottom:.75rem;max-height:180px;overflow-y:auto;font-size:.9rem}
 .invalid-field{border:2px solid #d93025 !important;background:#ffecec !important;}

  .intro{
    border:1px solid #c7c7c7;    
    border-left:4px solid #0077cc; 
    background:#e9f4ff;               
    padding:1.5rem;
    border-radius:6px;
    margin-bottom:2rem;
    font-size:.95rem;
    line-height:1.45;
  }

  .intro h2{
    margin:.25rem 0 .5rem;
    font-size:1.15rem;
    color:#333;                       
  }

  .intro ul{margin:.25rem 0 .75rem 1.25rem;padding-left:1.25rem}
  .intro li{margin-bottom:.25rem}

  .intro .note{color:#d2302c;font-weight:600;margin-top:1rem}

  /* Wizard */
  fieldset.step { display:none; }
  fieldset.step.active { display:block; }
  noscript fieldset.step { display:block !important; }

  #progressBar {
    height:6px; background:#c7c7c7; border-radius:3px; overflow:hidden; margin-bottom:1rem;
  }
  #progressBar span {
    display:block; height:100%; width:0; background:#0077cc;
    transition: width .3s ease;
  }
  .nav-buttons { margin-top:1rem; display:flex; justify-content:space-between; }
  .no-gap{gap:0;}
  .radio-option {
    display: flex;
    align-items: flex-start;
    margin-bottom: 0.5rem;
    max-width: 100%;
  }

  .radio-option input[type="radio"] {
    margin-right: 0.5rem;
    margin-top: 2px; /* vertically align with first line of label */
    flex-shrink: 0;
  }

  .radio-option label {
    flex-grow: 1;
    margin-bottom: 0;
    text-align: left;
  }
  .form-check {
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    margin-bottom: 0.5rem;
    padding-left: 0 !important;
  }

  .form-check-label {
    text-align: left;
  }

  .form-check-input[type="radio"] {
    display: inline-block !important;
    margin-left: 0 !important;
    margin-right: 0.5rem;
    position: relative;
    left: 0;
  }
  .victim-knowledge-option {
    width: 100%;
    margin-bottom: 10px;
    padding: 12px;
    border: 2px solid #007bff;
    border-radius: 5px;
    text-align: center;
    cursor: pointer;
    background-color: #fff;
    color: #007bff;
    font-weight: 500;
    transition: background-color 0.2s, color 0.2s;
  }

  .victim-knowledge-option:hover {
    background-color: #e9f3ff;
  }

  .victim-knowledge-option.active {
    background-color: #007bff;
    color: #fff;
  }

  .program-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-top:.5rem}
  .program-card{display:flex;flex-direction:column;align-items:flex-start;border:1px solid #ddd;border-radius:8px;padding:1rem;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.05);cursor:pointer;transition:transform .1s,box-shadow .1s,border-color .1s}
  .program-card:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(0,0,0,.08)}
  .program-card.active{border-color:#0077cc;box-shadow:0 0 0 3px rgba(0,119,204,.15)}
  .program-title{font-weight:600}
  .program-note{font-size:.9rem;color:#666;margin-top:.25rem}
  .program-card{ color:#222; }
  .program-card .program-title{ color:#222; }
  .program-card .program-title:empty::before{
    content: attr(data-fallback);
  }

  .scroll-box {
    max-height: 180px;
    overflow: auto;
    padding: 1rem;
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    background: #f8f9fa;
  }

</style>
</head>
<body>

    <div class="jumbotron bg-white text-center shadow-sm py-4">
      <a href="https://lakevieweducation.com" target="_blank">
          <img 
              src="lakeviewlogo.png" 
              alt="Lakeview Education" 
              class="img-fluid mb-1"
              style="max-width: 60%; height: auto;"
          >
      </a>
    </div>




<h1 id="formTitle">Intake Packet</h1>
<form method="post" id="intakeForm" novalidate>

  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(),ENT_QUOTES) ?>">
  <input type="hidden" name="program_id" id="program_id_hidden" value="">
  <input type="hidden" name="program_code" id="program_code_hidden" value="">


    <div class="intro">
    <h2>Why This Form Is Important</h2>
    <p>This form collects essential information we need to:</p>
    <ul>
        <li>Evaluate your situation.</li>
        <li>Provide tailored support.</li>
        <li>Ensure you meet program requirements.</li>
    </ul>

    <h2>Key Instructions</h2>
    <ol style="margin-left:1.25rem">
        <li><strong>Honesty is Essential:</strong> be truthful and accurate when answering every question. Providing false or incomplete information can delay or even prevent your enrollment in the program.</li>
        <li><strong>Complete All Required Fields:</strong> most fields are marked as <span style="color:#d2302c;font-weight:700">required</span> and must be filled out. You will not be able to submit the form, move forward, or begin the BIPP program until the form is fully completed.</li>
        <li><strong>Review Before Submitting:</strong> double-check your answers to ensure they are correct. Once submitted, changes may not be possible without contacting our team.</li>
    </ol>

    <h2>Sections of the Form</h2>
    <p>The form includes the following sections:</p>
    <ul>
        <li><strong>Personal Information</strong></li>
        <ul>
        <li>Full Legal Name&nbsp;&nbsp;·&nbsp;&nbsp;Date of Birth&nbsp;&nbsp;·&nbsp;&nbsp;Contact Information (Phone, Email, Address)</li>
        <li>Emergency Contact Details</li>
        </ul>
        <li><strong>Legal Information</strong></li>
        <ul>
        <li>Case Number (if applicable)</li>
        <li>Court Details (if referred by court)</li>
        <li>Probation Officer Information (if applicable)</li>
        </ul>
        <li><strong>Program-Related Information</strong></li>
        <ul>
        <li>Referral Source</li>
        <li>Reasons for Enrollment</li>
        <li>History of Participation in Similar Programs</li>
        </ul>
    </ul>

    <h2>Tips for Filling Out the Form</h2>
    <ul>
        <li><strong>Take Your Time:</strong> ensure all information is accurate. The form is designed to save your progress in case you need to return later (if applicable).</li>
        <li><strong>Use Clear Language:</strong> avoid abbreviations or vague descriptions.</li>
        <li><strong>Double-Check Required Fields:</strong> look for any fields marked with an asterisk (*) and make sure they are complete.</li>
    </ul>

    <h2>What Happens After Submission?</h2>
    <ul>
        <li>Our team will review your responses to confirm form completion.</li>
        <li>The form will be added to your BIPP chart.</li>
    </ul>

    <p>If you have questions while filling out the form or encounter any technical difficulties, please contact our support team at: <strong>[contact number]</strong>.</p>

    <p class="note">Note: Incomplete or inaccurate forms will not be accepted. Thank you for your cooperation!</p>
    </div>


    <div id="progressBar"><span></span></div>
    <div id="stepAlert" class="alert alert-danger" style="display:none"></div>

  <fieldset class="step active" id="step0-program">
    <legend>Choose Your Program</legend>

    <?php if (!$programs): ?>
      <div class="alert alert-warning mb-3">No programs found.</div>
    <?php else: ?>
      <p class="text-muted">Select your program to load the correct intake pages.</p>
      <div class="program-grid">
        <?php foreach ($programs as $pid => $pname): ?>
          <button type="button"
                  class="program-card"
                  data-program-id="<?= (int)$pid ?>"
                  data-program-name="<?= htmlspecialchars($pname,ENT_QUOTES) ?>">
            <div class="program-title"
                data-fallback="<?= htmlspecialchars($pname,ENT_QUOTES) ?>">
              <?= htmlspecialchars($pname,ENT_QUOTES) ?>
            </div>

            <div class="program-note">Select</div>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="nav-buttons">
      <span></span>
      <button type="button" class="btn btn-primary" id="programContinue" disabled>Continue</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>First Name</legend>
    <label class="required">First Name</label>
    <input name="first_name" required>
    <div class="nav-buttons">
      <span></span>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Last Name</legend>
    <label class="required">Last Name</label>
    <input name="last_name" required>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Email</legend>
    <label class="required">Email</label>
    <input type="email" name="email" required>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Consent to Disclose</legend>
    <div class="policy" style="max-height:220px; overflow:auto">
      <p><strong id="lsatConsentIntro"
                 data-template="Lastly, {{name}}, lets get the legal stuff out of the way."
                 data-default="Lastly, lets get the legal stuff out of the way. This question is required.">Lastly, lets get the legal stuff out of the way.</strong></p>
      <p>I authorize Lakeview Education to disclose to CSCD, caseworkers and/or Attorney(s) the results of the Education Program including: Recommendations, Test Scores, Successful Completion/Unsuccessful Discharge, or any other pertinent information related to the Education Program. I understand that all Education Programs shall abide by and obtain any consent to the disclosure required by applicable Federal and State Laws regarding confidentiality of patient/client records including, as applicable and without limitation, 42 U.S. Code § 290dd–2. Confidentiality of records, and Health and Safety Code, Chapter 611. I also understand that I may revoke this consent in writing at any time except to the extent that action has been taken in response to it, and that in any event, this consent expires 60 days after completion of the Education Program in which I am enrolled has been completed.</p>
    </div>
    <label class="required" style="display:block">
      <input type="checkbox" id="agree_disclosure" name="agree_disclosure" required>
      I Agree
    </label>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Today’s Date</legend>
    <label class="required">Today’s Date</label>
    <input type="date" name="today_date" required>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Phone Number</legend>
    <label class="required">Cell Phone</label>
    <input name="phone_number" required>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Driver’s License</legend>
    <label class="required">Driver’s License Number</label>
    <input name="drivers_license_number" required>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Gender</legend>
    <label class="required">Gender</label>
    <select name="gender_id" required>
      <option value="">-- Select --</option>
      <option value="2">Male</option>
      <option value="3">Female</option>
      <option value="1">Not Specified</option>
    </select>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Date of Birth</legend>
    <label class="required">Date of Birth</label>
    <input type="date" name="date_of_birth" required>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>County of Arrest</legend>
    <label class="required">County of Arrest</label>
    <input name="county_of_arrest" required>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Charge Reason</legend>
    <label class="required">Charge or Reason for Arrest</label>
    <input name="charge_reason" required>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>PO / Attorney / Caseworker</legend>
    <label class="required">PO, Attorney, or Caseworker Name</label>
    <input name="referral_contact_name" required>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Location</legend>
    <label class="required">PO / Attorney / Caseworker County or Location</label>
    <input name="referral_contact_location" required>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Offense Level</legend>
    <label class="required">Offense Level</label>
    <select name="offense_level" required>
      <option value="">--</option>
      <option>Misdemeanor</option>
      <option>Felony</option>
    </select>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="lsat">
    <legend>Rules and Regulations</legend>
    <div class="policy" style="max-height:220px; overflow:auto">
      <p><strong>Rules and Regulations:</strong></p>
      <ul>
        <li>Respect the privacy of others. Be respectful to others in the class.</li>
        <li>Be on time. Do not attend the class under the influence of alcohol or other substance.</li>
        <li>Keep up with your payments.</li>
        <li>Stay off your cell phone. (In class)</li>
        <li>Participate and share with others (you might be helping someone)</li>
      </ul>
    </div>
    <label class="required" style="display:block">
      <input class="form-check-input" type="checkbox" id="rules_page" name="rules_page" required>
      I read and agree to the Rules and Regulations
    </label>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="parent">
    <legend>Email</legend>
    <label class="required">Email</label>
    <input type="email" name="email" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="parent">
    <legend>Gender</legend>
    <label class="required">Gender</label>
    <select name="gender_id" required>
      <option value="">-- Select --</option>
      <option value="2">Male</option>
      <option value="3">Female</option>
      <option value="1">Not Specified</option>
    </select>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>
  
  <fieldset class="step" data-program-match="parent">
    <legend>First Name</legend>
    <label class="required">First Name</label>
    <input type="text" name="first_name" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="parent">
    <legend>Last Name</legend>
    <label class="required">Last Name</label>
    <input type="text" name="last_name" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="parent">
    <legend>Today’s Date</legend>
    <label class="required">Today’s Date</label>
    <input type="date" id="parent_today_date" name="today_date" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="parent">
    <legend>Phone Number</legend>
    <label class="required">Phone Number</label>
    <input type="tel" name="phone_number" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="parent">
    <legend>PO / Attorney / Caseworker</legend>
    <label class="required">PO, Attorney, or Caseworker Name</label>
    <input type="text" name="po_attorney_caseworker_name" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="parent">
    <legend>Confidentiality Notice</legend>
    <div class="scroll-box">
      <p><strong>Confidentiality:</strong> The following information will be kept confidential as per state and federal guidelines.</p>
      <p>You acknowledge that program staff will protect your information in accordance with applicable laws and policies.</p>
    </div>
    <div class="form-check mt-3">
      <input class="form-check-input" type="checkbox" id="conf_notice_ack" name="confidentiality_notice_page" value="1" required>
      <label class="form-check-label" for="conf_notice_ack">I have read and understand the confidentiality notice.</label>
    </div>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="parent">
    <legend>Drug Test History</legend>
    <label class="required" for="failed_drug_test_select">Have you ever failed a drug test?</label>
    <select id="failed_drug_test_select" name="failed_drug_test" class="form-control" required>
      <option value="" selected disabled>-- Select --</option>
      <option value="1">Yes</option>
      <option value="0">No</option>
    </select>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="parent">
    <legend>Substance Treatment History</legend>
    <label class="required" for="received_substance_treatment_select">
      Have you ever received treatment for substance addiction(s)?
    </label>
    <select id="received_substance_treatment_select"
            name="received_substance_treatment"
            class="form-control"
            required>
      <option value="" selected disabled>-- Select --</option>
      <option value="1">Yes</option>
      <option value="0">No</option>
    </select>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>


  <fieldset class="step" data-program-match="parent">
    <legend>Drug of Choice</legend>
    <label>Drug of Choice (if any)</label>
    <input type="text" name="drug_of_choice" class="form-control" placeholder="(e.g., Alcohol, Marijuana, Cocaine, etc.)">
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="parent">
    <legend>Current Charge / Situation</legend>
    <label class="required">Current arrest/charge/investigation or situation.</label>
    <textarea name="current_charge_or_situation" class="form-control" rows="4" required></textarea>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="parent">
    <legend>Consent to Share Attendance Only</legend>
    <div class="scroll-box">
      <p>I understand and give my consent to share only information concerning my participation and attendance in the program with the referral source provided. I understand that I can withdraw this approval at any time.</p>
    </div>
    <div class="form-check mt-3">
      <input class="form-check-input" type="checkbox" id="ack_confidentiality" name="ack_confidentiality" value="1" required>
      <label class="form-check-label" for="ack_confidentiality">I agree</label>
    </div>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Today's Date</legend>
    <input type="date" name="doep_today_date" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>First Name</legend>
    <input type="text" name="doep_first_name" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Last Name</legend>
    <input type="text" name="doep_last_name" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Email</legend>
    <input type="email" name="doep_email" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Phone Number</legend>
    <input type="tel" name="doep_phone" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Driver License Number</legend>
    <input type="text" name="doep_dl_number" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Street Address</legend>
    <input type="text" name="doep_street" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>City</legend>
    <input type="text" name="doep_city" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>State</legend>
    <input type="text" name="doep_state" maxlength="2" placeholder="TX" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>ZIP</legend>
    <input type="text" name="doep_zip" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Gender</legend>
    <select name="doep_gender" class="form-select" required>
      <option value="">-- Select --</option>
      <option>Male</option><option>Female</option><option>Non-binary</option>
      <option>Prefer not to say</option><option>Other</option>
    </select>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Date of Birth</legend>
    <input type="date" name="doep_dob" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Race / Ethnicity</legend>
    <select name="race_id" class="form-select" required>
      <option value="">-- Select --</option>
      <option value="1">African American</option>
      <option value="0">Hispanic</option>
      <option value="2">Asian</option>
      <option value="3">Middle Easterner</option>
      <option value="4">Caucasian</option>
      <option value="5">Other</option>
    </select>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>


  <fieldset class="step" data-program-match="doep">
    <legend>Marital Status</legend>
    <select name="doep_marital_status" class="form-select" required>
      <option value="">-- Select --</option>
      <option>Single</option><option>Married</option><option>Separated</option>
      <option>Divorced</option><option>Widowed</option>
    </select>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>How many times have you been married?</legend>
    <input type="number" min="0" name="doep_times_married" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Did drinking/drugging contribute to marital/family problems?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_substance_contrib_family" value="Yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_substance_contrib_family" value="No"> No</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_substance_contrib_family" value="Maybe"> Maybe</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_substance_contrib_family" value="N/A"> N/A</label>
    </div>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Education Level</legend>
    <input type="text" name="doep_education_level" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Jobs held in last 3 years</legend>
    <label class="required">Job — Year(s) — Reason for leaving</label>
    <textarea name="doep_job_history" class="form-control" rows="6" required></textarea>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Total time unemployed in last 3 years</legend>
    <input type="text" name="doep_unemployed_total_3yrs" class="form-control" placeholder="e.g., 5 months" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Case / Cause #</legend>
    <input type="text" name="doep_case_cause" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>County of Arrest</legend>
    <input type="text" name="doep_county_of_arrest" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Probation / Attorney Name</legend>
    <input type="text" name="doep_probation_attorney_name" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Probation / Attorney County</legend>
    <input type="text" name="doep_probation_attorney_county" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>How many times have you been arrested?</legend>
    <input type="number" min="0" name="doep_times_arrested" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Year(s) of arrest(s)</legend>
    <input type="text" name="doep_years_of_arrests" class="form-control" placeholder="e.g., 2018, 2020" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Were you charged with DWI?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_charged_with_dwi" value="Yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_charged_with_dwi" value="No"> No</label>
    </div>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep" data-show-if="doep_charged_with_dwi=Yes">
    <legend>Blood Alcohol Concentration (BAC)</legend>
    <input type="text" name="doep_bac" class="form-control">
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep" data-show-if="doep_charged_with_dwi=Yes">
    <legend>License status (current)</legend>
    <select name="doep_license_status_current" class="form-select">
      <option value="">-- Select --</option>
      <option>Suspended</option><option>Revoked</option>
      <option>Business/Work Only</option><option>Other</option>
    </select>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep" data-show-if="doep_charged_with_dwi=Yes">
    <legend>License status at time of arrest</legend>
    <select name="doep_license_status_at_arrest" class="form-select">
      <option value="">-- Select --</option>
      <option>Valid</option><option>Suspended</option><option>Revoked</option>
      <option>Business/Work Only</option><option>No License</option>
    </select>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Was there an accident involved?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_accident_involved" value="Yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_accident_involved" value="No"> No</label>
    </div>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Was anyone injured?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_anyone_injured" value="Yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_anyone_injured" value="No"> No</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_anyone_injured" value="N/A"> N/A</label>
    </div>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Send completion certificate to DPS (Austin)?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_send_certificate_to_dps" value="Yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_send_certificate_to_dps" value="No"> No</label>
    </div>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Confidentiality Notice</legend>
    <div class="scroll-box">
      <p><strong>*** The Following Information Will Be Kept Confidential ***</strong></p>
      <p>You acknowledge that program staff will protect your information in accordance with applicable laws.</p>
    </div>
    <div class="form-check mt-3">
      <input class="form-check-input" type="checkbox" id="conf_info_ack" name="confidential_info_notice_page" value="1" required>
      <label class="form-check-label" for="conf_info_ack">I have read and understand the confidentiality notice.</label>
    </div>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>


  <fieldset class="step" data-program-match="doep">
    <legend>Where do/did you usually use drugs or alcohol?</legend>
    <div class="row">
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_use_places[]" value="Party/Social Event" required> Party/Social Event</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_use_places[]" value="Night Club/Bar"> Night Club/Bar</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_use_places[]" value="Home with Family/Friends"> Home with Family/Friends</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_use_places[]" value="Home Alone"> Home Alone</label>
      </div>
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_use_places[]" value="On the Street"> On the Street</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_use_places[]" value="Work/School"> Work/School</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_use_places[]" value="Restaurant"> Restaurant</label>
        <div class="d-flex align-items-center gap-2">
          <label class="form-check m-0"><input class="form-check-input" type="checkbox" name="doep_use_places[]" value="Other"> Other</label>
          <input type="text" class="form-control" name="doep_use_places_other" placeholder="Describe">
        </div>
      </div>
    </div>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Age when you began drinking/using</legend>
    <input type="number" min="0" name="doep_age_began_use" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Age of first arrest</legend>
    <input type="number" min="0" name="doep_age_first_arrest" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Age of first drug-related arrest</legend>
    <input type="number" min="0" name="doep_age_first_drug_arrest" class="form-control" required>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Ever thought you might have an alcohol or drug problem?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_thought_problem" value="Yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_thought_problem" value="No"> No</label>
    </div>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep" data-show-if="doep_thought_problem=Yes">
    <legend>If so, have you ever received help?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_received_help" value="Yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="doep_received_help" value="No"> No</label>
    </div>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Have you ever attended any of the following?</legend>
    <div class="row">
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_attended[]" value="AA or NA" required> AA or NA</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_attended[]" value="Drug/Alcohol Rehab"> Drug/Alcohol Rehab</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_attended[]" value="Relative"> Relative</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_attended[]" value="Church"> Church</label>
      </div>
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_attended[]" value="Psychiatrist/Psychologist"> Psychiatrist/Psychologist</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_attended[]" value="Substance Abuse Counselor"> Substance Abuse Counselor</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="doep_attended[]" value="Doctor"> Doctor</label>
        <div class="d-flex align-items-center gap-2">
          <label class="form-check m-0"><input class="form-check-input" type="checkbox" name="doep_attended[]" value="Other"> Other</label>
          <input type="text" class="form-control" name="doep_attended_other" placeholder="Describe">
        </div>
      </div>
    </div>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Consent: Disclose Results</legend>
    <label class="form-check">
      <input class="form-check-input" type="checkbox" name="doep_consent_disclose_results" required>
      I authorize Lakeview Education to disclose to CSCD and/or Attorney(s) the results of the Education Program including: Recommendations, Test Scores, Successful Completion/Unsuccessful Discharge, or any other pertinent information related to the Education Program. <strong>This question is required.</strong>
    </label>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Consent: Confidentiality Laws</legend>
    <label class="form-check">
      <input class="form-check-input" type="checkbox" name="doep_consent_confidentiality_laws" required>
      I understand that all Education Programs shall abide by and obtain any consent to disclosure required by applicable Federal and State Laws including 42 U.S. Code § 290dd–2 and Health and Safety Code, Chapter 611. I may revoke this consent in writing except to the extent action has been taken in response; this consent expires 60 days after program completion. <strong>This question is required.</strong>
    </label>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>Consent: Share With Court</legend>
    <label class="form-check">
      <input class="form-check-input" type="checkbox" name="doep_consent_share_with_court" required>
      I understand and agree that information about my progress in the DWI Education Program will be shared with the Court and do hereby authorize such use. This information will otherwise be held confidential and not released without my signed consent. <strong>This question is required.</strong>
    </label>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>First Name</legend>
    <input type="text" name="dwie_first_name" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Last Name</legend>
    <input type="text" name="dwie_last_name" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Email</legend>
    <input type="email" name="dwie_email" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Disclosure Consent</legend>
    <label class="required">
      <input type="checkbox" name="dwie_consent_disclose_results" required>
      I authorize Lakeview Education to disclose to CSCD and/or Attorney(s) the results of the Education Program including: Recommendations, Test Scores, Successful Completion/Unsuccessful Discharge, or any other pertinent information related to the Education Program. <strong>This question is required.</strong>
    </label>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Confidentiality Laws Acknowledgment</legend>
    <label class="required">
      <input type="checkbox" name="dwie_consent_confidentiality_laws" required>
      I understand that all Education Programs shall abide by and obtain any consent to disclosure required by applicable Federal and State Laws including, as applicable and without limitation, 42 U.S. Code § 290dd–2 and Health and Safety Code, Chapter 611. I may revoke this consent in writing except to the extent action has been taken, and in any event this consent expires 60 days after completion of the Education Program. <strong>This question is required.</strong>
    </label>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Driver License Number</legend>
    <input type="text" name="dwie_dl_number" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Street Address</legend>
    <input type="text" name="dwie_street_address" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Phone</legend>
    <input type="tel" name="dwie_phone" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Gender</legend>
    <select name="dwie_gender" class="form-select" required>
      <option value="">-- Select --</option>
      <option>Male</option><option>Female</option><option>Non-binary</option>
      <option>Prefer not to say</option><option>Other</option>
    </select>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Date of Birth</legend>
    <input type="date" name="dwie_dob" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Race / Ethnicity</legend>
    <select name="race_id" class="form-select" required>
      <option value="">-- Select --</option>
      <option value="1">African American</option>
      <option value="0">Hispanic</option>
      <option value="2">Asian</option>
      <option value="3">Middle Easterner</option>
      <option value="4">Caucasian</option>
      <option value="5">Other</option>
    </select>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Marital Status</legend>
    <select name="dwie_marital_status" class="form-select" required>
      <option value="">-- Select --</option>
      <option>Never Married</option><option>Married</option><option>Divorced</option>
      <option>Separated</option><option>Widowed</option><option>Other</option>
    </select>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>How many times have you been married? (if any)</legend>
    <input type="number" min="0" name="dwie_times_married" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Did drinking/drugging contribute to marital/family problems?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_substance_contrib_family" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_substance_contrib_family" value="no"> No</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_substance_contrib_family" value="maybe"> Maybe</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_substance_contrib_family" value="na"> N/A</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Education Level</legend>
    <select name="dwie_education_level" class="form-select" required>
      <option value="">-- Select --</option>
      <option>High School/GED</option>
      <option>College</option>
      <option>Some College</option>
      <option>Some High School</option>
      <option>Other</option>
    </select>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Job History (last 3 years)</legend>
    <label class="required">List all jobs you have held in the past 3 years, beginning with your present job. Include: Job Description, Year(s) of Employment, Reason for Leaving.</label>
    <textarea name="dwie_job_history" class="form-control" rows="6" required></textarea>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Total time unemployed in last 3 years</legend>
    <input type="text" name="dwie_unemployed_total_3yrs" class="form-control" placeholder="e.g., 5 months" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Case / Cause Number</legend>
    <input type="text" name="dwie_case_cause" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>County of Arrest</legend>
    <input type="text" name="dwie_county_of_arrest" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Probation / Attorney Name</legend>
    <input type="text" name="dwie_probation_attorney_name" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Probation / Attorney County</legend>
    <input type="text" name="dwie_probation_attorney_county" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Times Arrested (any reason)</legend>
    <input type="number" min="0" name="dwie_times_arrested" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Years of Arrest(s)</legend>
    <input type="text" name="dwie_years_of_arrests" class="form-control" placeholder="e.g., 2018, 2020" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>If charged with DWI, what was the BAC?</legend>
    <input type="text" name="dwie_bac" class="form-control">
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>License status at time of arrest</legend>
    <select name="dwie_license_status_at_arrest" class="form-select" required>
      <option value="">-- Select --</option>
      <option>Valid</option><option>Suspended</option><option>Revoked</option>
      <option>Business/Work Only</option><option>No License</option>
    </select>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Was there an accident involved?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_accident_involved" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_accident_involved" value="no"> No</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Was anyone injured?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_anyone_injured" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_anyone_injured" value="no"> No</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Send completion certificate to DPS (Austin)?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_send_certificate_to_dps" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_send_certificate_to_dps" value="no"> No</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Where do/did you usually use alcohol? (check all that apply)</legend>
    <div class="row">
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_use_places[]" value="party/social event" required> Party/Social Event</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_use_places[]" value="night club/bar"> Night Club/Bar</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_use_places[]" value="home with family/friends"> Home with Family/Friends</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_use_places[]" value="home alone"> Home Alone</label>
      </div>
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_use_places[]" value="on the street"> On the Street</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_use_places[]" value="work/school"> Work/School</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_use_places[]" value="restaurant"> Restaurant</label>
        <div class="d-flex align-items-center gap-2">
          <label class="form-check m-0"><input class="form-check-input" type="checkbox" name="dwie_use_places[]" value="other"> Other</label>
          <input type="text" class="form-control" name="dwie_use_places_other" placeholder="Describe">
        </div>
      </div>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Age when you began drinking</legend>
    <input type="number" min="0" name="dwie_age_began_drinking" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Age of first arrest</legend>
    <input type="number" min="0" name="dwie_age_first_arrest" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Age of first alcohol-related arrest</legend>
    <input type="number" min="0" name="dwie_age_first_alcohol_arrest" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Have you ever thought you might have an alcohol problem?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_thought_problem" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_thought_problem" value="no"> No</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>If so, have you ever received help?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_received_help" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwie_received_help" value="no"> No</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Have you ever attended any of the following?</legend>
    <div class="row">
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_attended[]" value="aa or na" required> AA or NA</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_attended[]" value="drug/alcohol rehab"> Drug/Alcohol Rehab</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_attended[]" value="relative"> Relative</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_attended[]" value="church"> Church</label>
      </div>
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_attended[]" value="psychiatrist/psychologist"> Psychiatrist/Psychologist</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_attended[]" value="substance abuse counselor"> Substance Abuse Counselor</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwie_attended[]" value="doctor"> Doctor</label>
        <div class="d-flex align-items-center gap-2">
          <label class="form-check m-0"><input class="form-check-input" type="checkbox" name="dwie_attended[]" value="other"> Other</label>
          <input type="text" class="form-control" name="dwie_attended_other" placeholder="Describe">
        </div>
      </div>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>Court Sharing Consent</legend>
    <label class="required">
      <input type="checkbox" name="dwie_consent_share_with_court" required>
      I understand and agree that information about my progress in the DWI Intervention Program will be shared with the Court and do hereby authorize such use, with the further understanding that this information will otherwise be held confidential and not released to other individuals without my signed consent. <strong>This question is required.</strong>
    </label>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="submit" class="btn btn-success">Submit</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Email</legend>
    <input type="email" name="dwii_email" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Texas DWI Intervention — Participant Agreement</legend>
    <label class="required">
      <input type="checkbox" name="dwii_agree_participant_rules" required>
      As a participant in this program, you must follow these agreements and program requirements: • Participate in group discussions, 1-to-1 sessions, and homework • Express opinions without disrupting class • Bring a significant other to Modules 9 and 10 (Family Week) • Develop an action plan • Be on time; ≥15 minutes late or no-show may result in drop or added sessions • Return from breaks on time • No visitors except Family Week • Abstain from mood-altering chemicals • Attend at least two A.A. meetings between Modules 11 and 12 • For unavoidable absences, call to schedule a make-up before the next class; no more than two absences.
    </label>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Agreement to Comply</legend>
    <label class="required">
      <input type="checkbox" name="dwii_agree_comply" required>
      I agree to comply with all requirements, complete assignments and projects, and fully participate in class discussions.
    </label>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Disclosure Consent</legend>
    <label class="required">
      <input type="checkbox" name="dwii_consent_disclose_results" required>
      I authorize Lakeview Education to disclose to CSCD and/or Attorney(s) the results of the Education Program including Recommendations, Test Scores, Successful Completion/Unsuccessful Discharge, or any other pertinent information related to the Education Program.
    </label>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Confidentiality Laws Acknowledgment</legend>
    <label class="required">
      <input type="checkbox" name="dwii_consent_confidentiality_laws" required>
      I understand applicable Federal and State confidentiality laws, including 42 U.S.C. §290dd-2 and Texas Health and Safety Code Chapter 611. I may revoke consent in writing except where action has already been taken. This consent expires 60 days after program completion.
    </label>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>First Name</legend>
    <input type="text" name="dwii_first_name" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Last Name</legend>
    <input type="text" name="dwii_last_name" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Date of Birth</legend>
    <input type="date" name="dwii_dob" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Driver License Number</legend>
    <input type="text" name="dwii_dl_number" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Street Address</legend>
    <input type="text" name="dwii_street_address" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Phone Number</legend>
    <input type="tel" name="dwii_phone" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Gender</legend>
    <select name="dwii_gender" class="form-select" required>
      <option value="">-- Select --</option>
      <option>Male</option><option>Female</option><option>Non-binary</option>
      <option>Prefer not to say</option><option>Other</option>
    </select>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Case / Cause Number</legend>
    <input type="text" name="dwii_case_cause" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>County of Arrest</legend>
    <input type="text" name="dwii_county_of_arrest" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>PO or Attorney Name</legend>
    <input type="text" name="dwii_po_attorney_name" class="form-control">
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>PO or Attorney County</legend>
    <input type="text" name="dwii_po_attorney_county" class="form-control">
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Send completion to DPS (Austin) for driver’s license?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_send_certificate_to_dps" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_send_certificate_to_dps" value="no"> No</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Marital Status</legend>
    <select name="dwii_marital_status" class="form-select" required>
      <option value="">-- Select --</option>
      <option>Never Married</option><option>Married</option><option>Divorced</option>
      <option>Separated</option><option>Widowed</option><option>Other</option>
    </select>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>How many times have you been married?</legend>
    <input type="number" min="0" name="dwii_times_married" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Did drinking/drugging contribute to marital/family problems?</legend>
    <div class="d-flex flex-wrap gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_substance_contrib_family" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_substance_contrib_family" value="no"> No</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_substance_contrib_family" value="maybe"> Maybe</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_substance_contrib_family" value="na"> N/A</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_substance_contrib_family" value="other"> Other</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>How many people reside in your home?</legend>
    <input type="number" min="0" name="dwii_household_count" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Education Level</legend>
    <select name="dwii_education_level" class="form-select" required>
      <option value="">-- Select --</option>
      <option>High School/GED</option><option>College</option>
      <option>Some College</option><option>Some High School</option><option>Other</option>
    </select>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Employment Status</legend>
    <select name="dwii_employment_status" class="form-select" required>
      <option value="">-- Select --</option>
      <option>Employed Full-Time</option><option>Employed Part-Time</option>
      <option>Unemployed</option><option>Student</option><option>Retired</option><option>Other</option>
    </select>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Job History (last 3 years)</legend>
    <label class="required">List all jobs for the last 3 years: Job Description — Year(s) — Reason for Leaving.</label>
    <textarea name="dwii_job_history" class="form-control" rows="6" required></textarea>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Total time unemployed in last 3 years</legend>
    <input type="text" name="dwii_unemployed_total_3yrs" class="form-control" placeholder="e.g., 5 months" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Times arrested (any reason)</legend>
    <input type="number" min="0" name="dwii_times_arrested_any" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Times arrested for DWI / DUI</legend>
    <input type="number" min="0" name="dwii_times_arrested_dwi" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Blood Alcohol Concentration (BAC)</legend>
    <input type="text" name="dwii_bac" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>If BAC unknown, was it greater than 0.15?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_bac_gt_015" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_bac_gt_015" value="no"> No</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_bac_gt_015" value="unknown"> Unknown</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>License conditions (including now)</legend>
    <div class="d-flex flex-column gap-2">
      <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_license_conditions[]" value="Suspended" required> Suspended</label>
      <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_license_conditions[]" value="Revoked"> Revoked</label>
      <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_license_conditions[]" value="Business/Work Only"> Business/Work Only</label>
      <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_license_conditions[]" value="None of the above"> None of the above</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>License status at time of arrest</legend>
    <select name="dwii_license_status_at_arrest" class="form-select" required>
      <option value="">-- Select --</option>
      <option>Valid</option><option>Suspended</option><option>Revoked</option>
      <option>Business/Work Only</option><option>No License</option>
    </select>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Was there an accident involved?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_accident_involved" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_accident_involved" value="no"> No</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Was anyone injured?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_anyone_injured" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_anyone_injured" value="no"> No</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Where do/did you usually use alcohol? (check all that apply)</legend>
    <div class="row">
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_use_places[]" value="party/social event" required> Party/Social Event</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_use_places[]" value="night club/bar"> Night Club/Bar</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_use_places[]" value="home with family/friends"> Home with Family/Friends</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_use_places[]" value="home alone"> Home Alone</label>
      </div>
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_use_places[]" value="on the street"> On the Street</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_use_places[]" value="work/school"> Work/School</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_use_places[]" value="restaurant"> Restaurant</label>
        <div class="d-flex align-items-center gap-2">
          <label class="form-check m-0"><input class="form-check-input" type="checkbox" name="dwii_use_places[]" value="other"> Other</label>
          <input type="text" class="form-control" name="dwii_use_places_other" placeholder="Describe">
        </div>
      </div>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Age you began drinking</legend>
    <input type="number" min="0" name="dwii_age_began_drinking" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Age of your first arrest (any reason)</legend>
    <input type="number" min="0" name="dwii_age_first_arrest" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Age of your first alcohol-related arrest</legend>
    <input type="number" min="0" name="dwii_age_first_alcohol_arrest" class="form-control" required>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Have you ever thought you might have an alcohol problem?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_thought_alcohol_problem" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_thought_alcohol_problem" value="no"> No</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Have you ever thought you might have a drug problem?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_thought_drug_problem" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_thought_drug_problem" value="no"> No</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>If so, have you ever received help?</legend>
    <div class="d-flex gap-3">
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_received_help" value="yes" required> Yes</label>
      <label class="form-check"><input class="form-check-input" type="radio" name="dwii_received_help" value="no"> No</label>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Have you ever attended any of the following for help?</legend>
    <div class="row">
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_attended[]" value="aa or na" required> AA or NA</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_attended[]" value="drug/alcohol rehab"> Drug/Alcohol Rehab</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_attended[]" value="relative"> Relative</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_attended[]" value="church"> Church</label>
      </div>
      <div class="col-md-6">
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_attended[]" value="psychiatrist/psychologist"> Psychiatrist/Psychologist</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_attended[]" value="substance abuse counselor"> Substance Abuse Counselor</label>
        <label class="form-check"><input class="form-check-input" type="checkbox" name="dwii_attended[]" value="doctor"> Doctor</label>
        <div class="d-flex align-items-center gap-2">
          <label class="form-check m-0"><input class="form-check-input" type="checkbox" name="dwii_attended[]" value="other"> Other</label>
          <input type="text" class="form-control" name="dwii_attended_other" placeholder="Describe">
        </div>
      </div>
    </div>
    <div class="nav-buttons mt-4"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Court Sharing Consent</legend>
    <label class="required">
      <input type="checkbox" name="dwii_consent_share_with_court" required>
      I understand and agree that information about my progress in the Drug Offender Education Program will be shared with the Court and do hereby authorize such use, with the further understanding that this information will otherwise be held confidential and not released to other individuals without my signed consent.
    </label>
    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="submit" class="btn btn-success">Submit</button>
    </div>
  </fieldset>

  <fieldset class="step" id="contact_demographics"
          data-program-match="!parent,!lsat,!doep,!dwie,!dwii"
          data-bipp-fallback="1">

    <legend>1&nbsp;&nbsp;Contact Information</legend>
    <div class="row">
      <div class="col">
        <div class="d-none">
          <label>Program</label>
          <select name="program_id" id="program_select"><?php  ?>

            <option value="">-- Select --</option>
            <?php foreach ($programs as $pid => $pname): ?>
              <option value="<?= (int)$pid ?>"><?= htmlspecialchars($pname, ENT_QUOTES) ?></option>
            <?php endforeach; ?>
          </select>
          <small id="programHint" class="text-muted">Hidden. Use Page 0.</small>
        </div>
        <label>Program</label>
        <input type="text" id="program_readonly" class="form-control" readonly placeholder="Select on previous page">
      </div>
    </div>

    <div style="height:1rem"></div>
    <div class="row">
      <div class="col"><label class="required">First Name</label><input name="first_name" required></div>
      <div class="col"><label class="required">Last Name</label><input name="last_name" required></div>
    </div>
        <div class="row">
      <div class="col"><label class="required">Email</label><input type="email" name="email" required></div>
      <div class="col"><label class="required">Cell Phone</label><input name="phone_number" required></div>
    </div>

    <div style="height: 1.5rem;"></div>
    <div class="row">
      <div class="col"><label class="required">Date of Birth</label><input type="date" name="date_of_birth" required></div>
      <div class="col"><label class="required">Gender</label>
        <select name="gender_id" required>
          <option value="">-- Select --</option><option value="2">Male</option><option value="3">Female</option><option value="1">Not Specified</option>
        </select>
      </div>
      <div class="col"><label class="required">Driver’s License Number</label>
        <input name="drivers_license_number" required>
      </div>
    </div>

    <div style="height: 1.5rem;"></div>

    <label class="required">Street Address</label><input name="address_street" required>
    <div class="row">
      <div class="col"><label class="required">City</label><input name="address_city" required></div>
      <div class="col"><label class="required">State</label><input name="address_state" maxlength="2" required></div>
      <div class="col"><label class="required">Zip</label><input name="address_zip" maxlength="10" required></div>
    </div>

    <div style="height: 1.5rem;"></div>

    <div class="row">
      <div class="col"><label class="required">City of Birth</label><input name="birth_city" required></div>
      <div class="col"><label class="required">Race</label>
        <select name="race_id">
            <option value="">-- Select --</option><option value="1">African American</option><option value="0">Hispanic</option><option value="2">Asian</option><option value="3">Middle Easterner</option><option value="4">Caucasian</option><option value="5">Other</option>
        </select>
      </div>
      <div class="col"><label class="required">Highest Education</label>
        <select name="education_level">
            <option value="">-- Select --</option><option value="1">High School</option><option value="0">GED</option><option value="2">Some College</option><option value="3">Associates</option><option value="4">Bachelors</option><option value="5">Masters</option><option value="6">Doctorates</option><option value="7">None of the Above</option>
        </select>
      </div>
    </div>

    <div style="height: 1.5rem;"></div>

    <div class="row">
      <div class="col">
        <label class="required">Currently Employed?</label>
        <select name="employed">
          <option value="">-- Select --</option><option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>
      <div class="col employer-fields" style="display:none;">
        <label>Employer</label>
        <input name="employer">
      </div>
      <div class="col employer-fields" style="display:none;">
        <label>Occupation</label>
        <input name="occupation">
      </div>
      <script>
      document.addEventListener('DOMContentLoaded', function() {
        const employedSelect = document.querySelector('select[name="employed"]');
        const employerFields = document.querySelectorAll('.employer-fields');
        function toggleEmployerFields() {
          if (employedSelect && employedSelect.value === "1") {
            employerFields.forEach(el => el.style.display = "");
          } else {
            employerFields.forEach(el => el.style.display = "none");
          }
        }
        if (employedSelect) {
          employedSelect.addEventListener('change', toggleEmployerFields);
          toggleEmployerFields();
        } else {
          employerFields.forEach(el => el.style.display = "none");
        }
      });
      </script>

    </div>
    <div class="nav-buttons">
        <button type="button" class="btn btn-primary next">Next</button>
    </div>

  </fieldset>

  <fieldset class="step"
          data-program-match="!parent,!lsat,!doep,!dwie,!dwii"
          data-bipp-fallback="1">
    <div class="policy">
      <p>Confidentiality is defined as keeping private the information shared by you, the client, with your
      counselor. On occasion, other employees may need access to your record for agency teaching,
      supervision, and administrative purposes. These staff members will also respect the privacy of your
      records. In accordance with the Texas Department of Criminal Justice – Community Justice Assistance Division
      and Texas Council on Family Violence Battering Intervention &amp; Prevention Program guidelines, clients are
      required to sign Consent for Release of Information, which permits information to be released to the
      victim/partner and/or her designated representative, law enforcement, the courts, correction
      agencies, and any others in accordance with agency policy.</p>

      <p><strong>As a client, you have the right to withhold or release information to other individuals or
      agencies.</strong> A statement signed by you is required before any information may be released to anyone
      outside Lakeview Education – BIPP. This right applies with the following exceptions:</p>

      <ul>
          <li>When a court of law subpoenas information shared by you with your counselor.</li>
          <li>When there is reasonable concern that harm may come to you or others, as in child abuse, elder
              abuse, and abuse of a disabled person. Staff will notify appropriate agencies, including TDPRS
              (Texas Department of Protective and Regulatory Services), in accordance with applicable laws.</li>
          <li>When staff determines there is a probability of imminent physical injury to self or others.
              Staff may notify medical or law-enforcement personnel and/or the victim/partner
              (Section 611.004(a) of the Texas Health and Safety Code).</li>
          <li>When there is disclosure of sexual misconduct or sexual exploitation by a previous therapist or
              mental-health professional.</li>
      </ul>

      <p><strong>A licensee shall report if required by any of the following laws:</strong></p>
      <ul>
          <li><em>Health and Safety Code, Chapter 161, Subchapter K</em>, concerning abuse, neglect, or
              illegal, unprofessional, or unethical conduct in facilities providing mental-health services.</li>
          <li><em>Civil Practice and Remedies Code, §81.006</em>, concerning sexual exploitation by a
              mental-health service provider.</li>
          <li>All personal data and possibly additional information will be submitted to TDCJ-CJAD for program
              assessments and research.</li>
          <li><strong>Media involvement:</strong> Any media contact arranged by the Lakeview Education program
              will include the presence of a Lakeview Education employee to protect victim confidentiality.</li>
      </ul>

      <p><strong>We ask that you keep confidential information you may learn about other clients who are
      receiving services from Lakeview Education – BIPP.</strong></p>

      <p><strong>Lakeview Education requires facilitators and participants to:</strong></p>
      <ul>
          <li>Disable any devices that could collect information from the environment, such as Google Home
              Assistant, Amazon Alexa, or Apple Siri.</li>
          <li>Not record or take screenshots of group discussions.</li>
          <li>Ensure they are in a private space and not in any public area such as a park, yard, or open
              area. Other people not in the group should not hear or observe the group.</li>
          <li>Not use the virtual group session to expel their partner or children from the residence.
              Participants must relocate to another location or private room in the residence.</li>
          <li>Ensure that children are safe and cared for, but not interrupting the session or listening to
              group discussions.</li>
      </ul>

      <p><strong>Observers may occasionally sit in on a group.</strong> Observers must sign a confidentiality
      statement. Observers may include student interns, trainees, other professionals, or community
      members. This facility is video-recorded for security purposes, and treatment sessions may be
      video/audio recorded for quality assurance.</p>

      <p><strong>Ethics &amp; Grievances:</strong> All agency services will be delivered in as professional and
      ethical a manner as possible. While specific results cannot be guaranteed, if you have concerns
      about the professional performance of your counselor:</p>
      <ul>
          <li>Inform your counselor directly.</li>
          <li>If unresolved, report concerns to your counselor's immediate supervisor, Executive Director
              [contact name], at [contact number].</li>
          <li>If further resolution is needed, contact the Texas Council on Family Violence at 800-525-1978.</li>
      </ul>

      <p><em>By clicking “I Agree” below, I confirm that I have read, understood, and agree to abide by the
      terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and
      I accept these terms as a condition of participation in the Lakeview Education – Batterers Intervention &amp;
      Prevention Program.</em></p>
    </div>

    <label class="required" style="display:block;margin-top:.75rem">
      <input type="checkbox" name="agree_confidentiality" required>
      I&nbsp;Agree&nbsp;– I have read, understood and accept the terms above
    </label>

    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="doep">
    <legend>DOEP</legend>

    <div class="row">
      <div class="col"><label class="required">Today’s Date</label><input type="date" name="today_date" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Race / Ethnicity</label><input name="race_ethnicity" required></div>
      <div class="col"><label class="required">Marital Status</label><input name="marital_status" required></div>
      <div class="col"><label class="required">Times Married</label><input name="times_married" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Education Level</label><input name="education_level" required></div>
      <div class="col"><label class="required">Employment History (job|years|reason)</label><input name="employment_history_list" required></div>
      <div class="col"><label class="required">Unemployment total last 3 years</label><input name="unemployment_total_last_3_years" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Case/Cause #</label><input name="case_cause_number" required></div>
      <div class="col"><label class="required">County of Arrest</label><input name="county_of_arrest" required></div>
      <div class="col"><label class="required">Referral County</label><input name="referral_contact_county" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Arrests count and years</label><input name="arrests_count_and_years" required></div>
      <div class="col"><label class="required">DWI BAC</label><input name="dwi_bac" required></div>
      <div class="col"><label class="required">License status at arrest</label><input name="license_status_at_arrest" required></div>
    </div>

    <div class="row">
      <div class="col"><label>Accident involved</label><select name="accident_involved"><option>yes</option><option>no</option></select></div>
      <div class="col"><label>Anyone injured</label><select name="anyone_injured"><option>yes</option><option>no</option><option>na</option></select></div>
      <div class="col"><label class="required">Send completion to DPS</label><select name="send_completion_to_dps" required><option>yes</option><option>no</option></select></div>
    </div>

    <div class="form-check my-2">
      <input class="form-check-input" type="checkbox" id="doep_conf" name="confidential_info_notice_page" required>
      <label class="form-check-label" for="doep_conf">I read the Confidential Information Notice</label>
    </div>

    <label>Usual use locations</label>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="usual_use_locations[]" value="home"><label class="form-check-label">home</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="usual_use_locations[]" value="bar"><label class="form-check-label">bar</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="usual_use_locations[]" value="car"><label class="form-check-label">car</label></div>

    <div class="row mt-2">
      <div class="col"><label class="required">Age began use</label><input name="age_began_use" required></div>
      <div class="col"><label>Age first arrest</label><input name="age_first_arrest"></div>
      <div class="col"><label class="required">Age first drug-related arrest</label><input name="age_first_drug_related_arrest" required></div>
    </div>

    <div class="row">
      <div class="col"><label>Thinks has alcohol/drug problem</label><input name="thought_has_alcohol_or_drug_problem"></div>
      <div class="col"><label class="required">Received help</label><input name="received_help" required></div>
    </div>

    <label>Attended help types</label>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="attended_help_types[]" value="aa"><label class="form-check-label">AA</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="attended_help_types[]" value="na"><label class="form-check-label">NA</label></div>

    <div class="form-check mt-2">
      <input class="form-check-input" type="checkbox" id="doep_share" name="consent_progress_shared_with_court" required>
      <label class="form-check-label" for="doep_share">Consent to share progress with court</label>
    </div>

    <div class="nav-buttons"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwie">
    <legend>DWIE</legend>

    <div class="row">
      <div class="col"><label class="required">Race/Ethnicity</label><input name="race_ethnicity" required></div>
      <div class="col"><label class="required">Marital Status</label><input name="marital_status" required></div>
      <div class="col"><label class="required">Times Married</label><input name="times_married" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Family problems due to use</label><select name="family_problems_due_to_use" required><option>yes</option><option>no</option><option>maybe</option><option>na</option></select></div>
      <div class="col"><label class="required">Education Level</label><input name="education_level" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Employment History</label><input name="employment_history_list" required></div>
      <div class="col"><label class="required">Unemployment total last 3 years</label><input name="unemployment_total_last_3_years" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Case/Cause #</label><input name="case_cause_number" required></div>
      <div class="col"><label class="required">County of Arrest</label><input name="county_of_arrest" required></div>
    </div>

    <label class="required">Probation/Attorney name and county</label><input name="probation_attorney_name_and_county" required>

    <div class="row">
      <div class="col"><label class="required">Arrests count and years</label><input name="arrests_count_and_years" required></div>
      <div class="col"><label class="required">License status at arrest</label><input name="license_status_at_arrest" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Accident involved</label><select name="accident_involved" required><option>yes</option><option>no</option></select></div>
      <div class="col"><label class="required">Anyone injured</label><select name="anyone_injured" required><option>yes</option><option>no</option><option>na</option></select></div>
      <div class="col"><label class="required">Send completion to DPS</label><select name="send_completion_to_dps" required><option>yes</option><option>no</option></select></div>
    </div>

    <label class="required">Alcohol use locations</label>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="alcohol_use_locations[]" value="home"><label class="form-check-label">home</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="alcohol_use_locations[]" value="bar"><label class="form-check-label">bar</label></div>

    <div class="row mt-2">
      <div class="col"><label class="required">Age began drinking</label><input name="age_began_drinking" required></div>
      <div class="col"><label class="required">Age first arrest</label><input name="age_first_arrest" required></div>
      <div class="col"><label class="required">Age first alcohol-related arrest</label><input name="age_first_alcohol_related_arrest" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Thinks has alcohol problem</label><input name="thought_has_alcohol_problem" required></div>
      <div class="col"><label class="required">Received help</label><input name="received_help" required></div>
    </div>

    <label class="required">Attended help types</label>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="attended_help_types[]" value="aa"><label class="form-check-label">AA</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="attended_help_types[]" value="counseling"><label class="form-check-label">Counseling</label></div>

    <div class="form-check mt-2">
      <input class="form-check-input" type="checkbox" id="dwie_share" name="consent_progress_shared_with_court" required>
      <label class="form-check-label" for="dwie_share">Consent to share progress with court</label>
    </div>

    <div class="nav-buttons"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="dwii">
    <legend>Texas DWI Repeat Offender</legend>

    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="dwii_pa" name="participant_agreement" required>
      <label class="form-check-label" for="dwii_pa">Participant Agreement</label>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="dwii_comp" name="compliance_acknowledgement" required>
      <label class="form-check-label" for="dwii_comp">Compliance Acknowledgement</label>
    </div>

    <div class="row">
      <div class="col"><label class="required">Case/Cause #</label><input name="case_cause_number" required></div>
      <div class="col"><label class="required">County of Arrest</label><input name="county_of_arrest" required></div>
    </div>

    <label>Referral contact name and county</label><input name="referral_contact_name_and_county">

    <div class="row">
      <div class="col"><label class="required">Send completion to DPS</label><select name="send_completion_to_dps" required><option>yes</option><option>no</option></select></div>
      <div class="col"><label>Marital Status</label><input name="marital_status"></div>
      <div class="col"><label>Times Married</label><input name="times_married"></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Family problems due to use</label><select name="family_problems_due_to_use" required><option>yes</option><option>no</option><option>maybe</option><option>na</option><option>other</option></select></div>
      <div class="col"><label class="required">Household size</label><input name="household_size" required></div>
    </div>

    <div class="row">
      <div class="col"><label>Education Level</label><input name="education_level"></div>
      <div class="col"><label>Employment Status</label><input name="employment_status"></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Employment History</label><input name="employment_history_list" required></div>
      <div class="col"><label class="required">Unemployment total last 3 years</label><input name="unemployment_total_last_3_years" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Arrests total count</label><input name="arrests_total_count" required></div>
      <div class="col"><label class="required">Arrests DWI count</label><input name="arrests_dwi_count" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">BAC</label><input name="bac" required></div>
      <div class="col">
        <label class="required">BAC unknown or &gt; 0.15</label>
        <select name="bac_unknown_over_0_15" required><option>yes</option><option>no</option></select>
      </div>
    </div>

    <div class="row">
      <div class="col"><label class="required">License conditions history</label><select name="license_conditions_history" required><option>suspended</option><option>revoked</option><option>business_only</option><option>none</option></select></div>
      <div class="col"><label class="required">License status at arrest</label><input name="license_status_at_arrest" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Accident involved</label><select name="accident_involved" required><option>yes</option><option>no</option></select></div>
      <div class="col"><label class="required">Anyone injured</label><select name="anyone_injured" required><option>yes</option><option>no</option></select></div>
    </div>

    <label class="required">Alcohol use locations</label>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="alcohol_use_locations[]" value="home"><label class="form-check-label">home</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="alcohol_use_locations[]" value="bar"><label class="form-check-label">bar</label></div>

    <div class="row mt-2">
      <div class="col"><label class="required">Age began drinking</label><input name="age_began_drinking" required></div>
      <div class="col"><label class="required">Age first arrest</label><input name="age_first_arrest" required></div>
      <div class="col"><label class="required">Age first alcohol-related arrest</label><input name="age_first_alcohol_related_arrest" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Thinks has alcohol problem</label><input name="thought_has_alcohol_problem" required></div>
      <div class="col"><label class="required">Thinks has drug problem</label><input name="thought_has_drug_problem" required></div>
    </div>

    <div class="row">
      <div class="col"><label class="required">Received help</label><input name="received_help" required></div>
    </div>

    <label class="required">Attended help types</label>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="attended_help_types[]" value="aa"><label class="form-check-label">AA</label></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="attended_help_types[]" value="treatment"><label class="form-check-label">Treatment</label></div>

    <div class="form-check mt-2">
      <input class="form-check-input" type="checkbox" id="dwii_share" name="consent_progress_shared_with_court" required>
      <label class="form-check-label" for="dwii_share">Consent to share progress with court</label>
    </div>

    <div class="nav-buttons"><button type="button" class="btn btn-secondary prev">Previous</button><button type="button" class="btn btn-primary next">Next</button></div>
  </fieldset>

  <fieldset class="step" data-program-match="bipp">
    <legend>Review & Signature</legend>
    <p><strong>Entering your name constitutes a digital signature.</strong></p>
    <label class="required" for="digital_signature">Signature – type your full legal name</label>
    <input type="text" id="digital_signature" name="digital_signature" class="form-control" required>

    <div class="mt-3">
      <label for="signature_date_alt">Date Signed</label>
      <input type="date" id="signature_date_alt" name="signature_date" class="form-control" readonly>
    </div>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="submit" class="btn btn-success" id="submit_nonbipp">Submit Intake Packet</button>
    </div>
  </fieldset>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const d = new Date().toISOString().split('T')[0];
      const f = document.getElementById('signature_date_alt');
      if (f) f.value = d;
    });
  </script>

  <fieldset class="step"
          data-program-match="!parent,!lsat,!doep,!dwie,!dwii"
          data-bipp-fallback="1">

    <div class="row">
      <div class="col"><label class="required">Name</label><input name="emergency_name" required></div>
    </div>
    <div class="row">
      <div class="col"><label class="required">Phone</label><input name="emergency_phone" required></div>
    </div>
    <div class="row">
      <div class="col"><label class="required">Relationship</label><input name="emergency_relation" required></div>
    </div>

    <div style="height: 1.5rem;"></div>

    <legend>2b&nbsp;&nbsp;Military Service</legend>
    <div class="row">
      <div class="col"><label>Branch</label><input name="military_branch"></div>
    </div>
    <div class="row">
      <div class="col"><label>Date of Service</label><input name="military_date"></div>
    </div>

    <div class="nav-buttons">
        <button type="button" class="btn btn-secondary prev">Previous</button>
        <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step"
          data-program-match="!parent,!lsat,!doep,!dwie,!dwii"
          data-bipp-fallback="1">
    <div class="row">
      <div class="col">
        <label class="required">Referral Type</label>
        <select name="referral_type_id" required>
          <option value="">-- Select --</option>
          <option value="1">Probation</option><option value="2">Parole</option>
          <option value="3">Pre-trial</option><option value="4">CPS</option>
          <option value="5">Attorney</option><option value="6">Self</option>
          <option value="0">Other / Unknown</option>
        </select>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label class="required">Officer / Case Manager Name</label>
        <input name="referring_officer_name" required>
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label>Officer E-mail</label>
        <input type="email" name="referring_officer_email">
      </div>
    </div>

    <div style="height: 1.5rem;"></div>

    <div class="row">
      <div class="col">
        <label>Additional Charge or Arrest Dates (if applicable)</label>
        <input name="additional_charge_dates" placeholder="e.g. January 4, 2023, August 6, 2024">
      </div>
    </div>
    <div class="row">
      <div class="col">
        <label>Additional Charge Details</label>
        <input name="additional_charge_details">
      </div>
    </div>
    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" id="step-household"
          data-program-match="!parent,!lsat,!doep,!dwie,!dwii"
          data-bipp-fallback="1">
    <legend>4&nbsp;&nbsp;Marital &amp; Family Information</legend>

    <div class="row no-gap">
      <div class="col-md-4">
        <label class="form-label" for="living_situation">
          Living Situation <span class="text-danger">*</span>
        </label>
        <select id="living_situation" name="living_situation" class="form-select" required>
          <option value="" disabled selected>Choose&hellip;</option>
          <option>Alone</option>
          <option>With Partner</option>
          <option>With Relatives</option>
          <option>With Friends</option>
          <option>Homeless</option>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label" for="marital_status">
          Marital Status <span class="text-danger">*</span>
        </label>
        <select id="marital_status" name="marital_status" class="form-select" required>
          <option value="" disabled selected>Choose&hellip;</option>
          <option>Married</option>
          <option>Separated</option>
          <option>Divorced</option>
          <option>Single</option>
          <option>Dating</option>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label" for="has_children">
          Do you have any children? <span class="text-danger">*</span>
        </label>
        <select id="has_children" name="has_children" class="form-select" required>
          <option value="" disabled selected>--</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>
    </div>

    <div id="children-details" class="mt-4 d-none">
      <div class="row no-gap">
        <div class="col-md-4">
          <label class="form-label" for="children_live_with_you">
            Do your children live with you? <span class="text-danger">*</span>
          </label>
          <select id="children_live_with_you" name="children_live_with_you" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>

        <div class="col-md-8">
          <label class="form-label" for="children_names_ages">
            Names &amp; Ages of Your Children <span class="text-danger">*</span>
          </label>
          <input id="children_names_ages" name="children_names_ages"
                class="form-control" placeholder="e.g. Alice 7, Ben 5, Carla 3">
        </div>
      </div>

      <div class="row no-gap mt-3">
        <div class="col-md-4">
          <label class="form-label" for="abused_physically">
            Have any of your children ever been abused&nbsp;PHYSICALLY? <span class="text-danger">*</span>
          </label>
          <select id="abused_physically" name="abused_physically" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="abused_sexually">
            Have any of your children ever been abused&nbsp;SEXUALLY? <span class="text-danger">*</span>
          </label>
          <select id="abused_sexually" name="abused_sexually" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="abused_emotionally">
            Have any of your children ever been abused&nbsp;EMOTIONALLY? <span class="text-danger">*</span>
          </label>
          <select id="abused_emotionally" name="abused_emotionally" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-md-12">
          <label class="form-label" for="children_neglected">
            Have any of your children ever been&nbsp;neglected? <span class="text-danger">*</span>
          </label>
          <select id="children_neglected" name="children_neglected" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
      </div>

      <div id="cps-block" class="row no-gap mt-3 d-none">
        <div class="col-md-6">
          <label class="form-label" for="cps_notified">
            Has&nbsp;Child Protective Services ever been notified? <span class="text-danger">*</span>
          </label>
          <select id="cps_notified" name="cps_notified" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="cps_care">
            Have any children been under CPS care? <span class="text-danger">*</span>
          </label>
          <select id="cps_care" name="cps_care" class="form-select">
            <option value="" disabled selected>--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <label class="form-label" for="discipline_desc">
        Describe how you discipline your children. Please provide examples: (if applicable)
      </label>
      <textarea id="discipline_desc" name="discipline_desc" rows="3" class="form-control"
                placeholder="Type your answer here&hellip;"></textarea>
    </div>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" id="step-substance"
          data-program-match="!parent,!lsat,!doep,!dwie,!dwii"
          data-bipp-fallback="1">

    <legend>5&nbsp;&nbsp;Substance Use History</legend>

    <div class="row no-gap mb-3">

      <div class="col-md-4">
        <label class="form-label" for="alcohol_past">
          Use of alcohol in the past? <span class="text-danger">*</span>
        </label>
        <select id="alcohol_past" name="alcohol_past" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>

      <div class="col-md-8 d-none" id="alcohol_frequency_grp">
        <label class="form-label" for="alcohol_frequency">
          If <em>Yes</em>, how often&nbsp;&amp;&nbsp;how much?
          <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="alcohol_frequency" name="alcohol_frequency"
              class="form-control" placeholder="e.g. 2 drinks, 3× per week">
      </div>
    </div>

    <div class="row no-gap mb-3">

      <div class="col-md-4">
        <label class="form-label" for="alcohol_current">
          Use of alcohol currently? <span class="text-danger">*</span>
        </label>
        <select id="alcohol_current" name="alcohol_current" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>

      <div class="col-md-8 d-none" id="alcohol_current_details_grp">
        <label class="form-label" for="alcohol_current_details">
          If <em>Yes</em>, how often&nbsp;&amp;&nbsp;how much?
          <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="alcohol_current_details" name="alcohol_current_details"
              class="form-control" placeholder="e.g. Nightly, 1–2 beers">
      </div>
    </div>

    <div class="row no-gap mb-3">

      <div class="col-md-4">
        <label class="form-label" for="drug_past">
          Use of drugs in the past? <span class="text-danger">*</span>
        </label>
        <select id="drug_past" name="drug_past" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>

      <div class="col-md-8 d-none" id="drug_past_details_grp">
        <label class="form-label" for="drug_past_details">
          If <em>Yes</em>, how often&nbsp;&amp;&nbsp;what drug?
          <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="drug_past_details" name="drug_past_details"
              class="form-control" placeholder="e.g. Marijuana daily">
      </div>
    </div>

    <div class="row no-gap mb-3">

      <div class="col-md-4">
        <label class="form-label" for="drug_current">
          Use of drugs currently? <span class="text-danger">*</span>
        </label>
        <select id="drug_current" name="drug_current" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>

      <div class="col-md-8 d-none" id="drug_current_details_grp">
        <label class="form-label" for="drug_current_details">
          If <em>Yes</em>, how often&nbsp;&amp;&nbsp;what drug?
          <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="drug_current_details" name="drug_current_details"
              class="form-control" placeholder="e.g. Prescription opioids weekly">
      </div>
    </div>

    <div class="row no-gap mb-4">
      <div class="col-md-6">
        <label class="form-label" for="alcohol_during_abuse">
          Were you using alcohol when you were abusive? <span class="text-danger">*</span>
        </label>
        <select id="alcohol_during_abuse" name="alcohol_during_abuse" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label" for="drug_during_abuse">
          Were you using drugs when you were abusive? <span class="text-danger">*</span>
        </label>
        <select id="drug_during_abuse" name="drug_during_abuse" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>
    </div>

    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" id="step-mental"
          data-program-match="!parent,!lsat,!doep,!dwie,!dwii"
          data-bipp-fallback="1">

    <legend>6&nbsp;&nbsp;Counseling History&nbsp;&amp; Mental‑Health Background</legend>

    <div class="row no-gap mb-3">
      <div class="col-md-6">
        <label class="form-label" for="counseling_history">
          Have you ever been in counseling? <span class="text-danger">*</span>
        </label>
        <select id="counseling_history" name="counseling_history" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>

      <div class="col-md-6 d-none" id="counseling_history_details_grp">
        <label class="form-label" for="counseling_reason">
          If YES, for what reason: <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="counseling_reason" name="counseling_reason" class="form-control"
              placeholder="e.g. PTSD, depression">
      </div>
    </div>

    <div class="row no-gap mb-3">
      <div class="col-md-6">
        <label class="form-label" for="depressed_currently">
          Are you currently depressed? <span class="text-danger">*</span>
        </label>
        <select id="depressed_currently" name="depressed_currently" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>

      <div class="col-md-6 d-none" id="depressed_currently_details_grp">
        <label class="form-label" for="depression_reason">
          If YES, why are you depressed: <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="depression_reason" name="depression_reason" class="form-control">
      </div>
    </div>

    <div class="row no-gap mb-3">
      <div class="col-md-6">
        <label class="form-label" for="attempted_suicide">
          Have you ever attempted suicide? <span class="text-danger">*</span>
        </label>
        <select id="attempted_suicide" name="attempted_suicide" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>

      <div class="col-md-6 d-none" id="attempted_suicide_details_grp">
        <label class="form-label" for="suicide_last_attempt">
          If YES, when was the last attempt: <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="suicide_last_attempt" name="suicide_last_attempt" class="form-control"
              placeholder="e.g. May 2023">
      </div>
    </div>

    <div class="row no-gap mb-3">
      <div class="col-md-12">
        <label class="form-label" for="mental_health_meds">
          Are you taking any medications for a mental‑health condition? <span class="text-danger">*</span>
        </label>
        <select id="mental_health_meds" name="mental_health_meds" class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>
    </div>

    <div class="row no-gap mb-3 d-none" id="mental_health_meds_details_grp">
      <div class="col-md-6">
        <label class="form-label" for="mental_meds_list">
          If YES, what medications? <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="mental_meds_list" name="mental_meds_list" class="form-control">
      </div>
      <div class="col-md-6">
        <label class="form-label" for="mental_doctor_name">
          If YES, what is your doctor’s name? <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="mental_doctor_name" name="mental_doctor_name" class="form-control">
      </div>
    </div>

    <div class="row no-gap mb-3">
      <div class="col-md-12">
        <label class="form-label" for="sexual_abuse_history">
          History of sexual abuse? <span class="text-danger">*</span>
        </label>
        <select id="sexual_abuse_history" name="sexual_abuse_history"
                class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>
    </div>

    <div class="row no-gap mb-3" id="head_trauma_row">
      <div class="col-md-6">
        <label class="form-label" for="head_trauma_history">
          Do you have any history of head trauma, brain injury, stroke,
          including overdose blackouts? <span class="text-danger">*</span>
        </label>
        <select id="head_trauma_history" name="head_trauma_history"
                class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>

      <div class="col-md-6 d-none" id="head_trauma_details_grp">
        <label class="form-label" for="head_trauma_desc">
          If YES, please describe:
          <span class="text-danger req-star d-none">*</span>
        </label>
        <input type="text" id="head_trauma_desc" name="head_trauma_desc"
              class="form-control">
      </div>
    </div>

    <div class="row no-gap mb-3">
      <div class="col-md-12">
        <label class="form-label" for="weapon_possession_history">
          History of weapon possession? <span class="text-danger">*</span>
        </label>
        <select id="weapon_possession_history" name="weapon_possession_history"
                class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>
    </div>

    <div class="row no-gap mb-3">
      <div class="col-md-12">
        <label class="form-label" for="abuse_trauma_history">
          History of abuse / trauma as a child? <span class="text-danger">*</span>
        </label>
        <select id="abuse_trauma_history" name="abuse_trauma_history"
                class="form-select" required>
          <option value="" disabled selected>Choose…</option>
          <option value="1">Yes</option><option value="0">No</option>
        </select>
      </div>
    </div>

    <div class="mt-3">
      <label class="form-label" for="violent_incident_desc">
        Describe the most recent violent incident / offense / etc:
      </label>
      <textarea id="violent_incident_desc" name="violent_incident_desc"
                class="form-control" rows="3"></textarea>
    </div>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" id="step-victim"
          data-program-match="!parent,!lsat,!doep,!dwie,!dwii"
          data-bipp-fallback="1">
    <legend>7&nbsp;&nbsp;Victim Information</legend>

    <div class="row no-gap mb-3">
      <div class="col-md-12">
        <p class="form-label fw-bold">Please select one:</p>
        <div id="victim-knowledge-buttons">
          <div class="victim-knowledge-option" data-value="0">
            I do <strong>not</strong> have knowledge of the victim's contact information
          </div>
          <div class="victim-knowledge-option" data-value="1">
            I do have knowledge of the victim's contact information (must provide below)
          </div>
        </div>
        <input type="hidden" name="victim_knowledge" id="victim_knowledge" required>
      </div>
    </div>

    <div id="victim-info-block" class="d-none mt-3">

      <div class="row">
        <div class="col-md-12">
          <label class="required">Relationship to victim</label>
          <select name="victim_relationship" class="form-select" required>
            <option value="">Select…</option>
            <option>Current Partner</option>
            <option>Ex-Partner</option>
            <option>Other</option>
          </select>
        </div>
      </div>

      <div id="victim-contact-block">
        <div class="row no-gap mt-3">
          <div class="col-md-6">
            <label>Victim's First Name</label>
            <input name="victim_first_name" class="form-control">
          </div>
          <div class="col-md-6">
            <label>Victim's Last Name</label>
            <input name="victim_last_name" class="form-control">
          </div>
        </div>

        <div class="row no-gap mt-3">
          <div class="col-md-6">
            <label>Victim's Age</label>
            <input name="victim_age" type="number" min="0" class="form-control">
          </div>
          <div class="col-md-6">
            <label>Victim's Gender</label>
            <select name="victim_gender" class="form-select">
              <option value="">Select…</option>
              <option>Male</option>
              <option>Female</option>
              <option>Not Specified</option>
            </select>
          </div>
        </div>

        <div class="row no-gap mt-3">
          <div class="col-md-6">
            <label>Victim's Phone</label>
            <input name="victim_phone" class="form-control">
          </div>
          <div class="col-md-6">
            <label>Victim's Email</label>
            <input name="victim_email" type="email" class="form-control">
          </div>
        </div>

        <div class="mt-3">
          <label>Victim's Address</label>
          <input name="victim_address" class="form-control">
        </div>

        <div class="row no-gap mt-3">
          <div class="col-md-4">
            <label>City</label>
            <input name="victim_city" class="form-control">
          </div>
          <div class="col-md-4">
            <label>State</label>
            <input name="victim_state" class="form-control">
          </div>
          <div class="col-md-4">
            <label>Zip Code</label>
            <input name="victim_zip" class="form-control">
          </div>
        </div>
      </div>

      <div class="row no-gap mt-3">
        <div class="col-md-6">
          <label class="required" for="live_with_victim">Will you be living with the victim while attending BIPP?</label>
          <select id="live_with_victim" name="live_with_victim" class="form-select" required>
            <option value="">--</option>
            <option value="1">Yes</option>
            <option value="0">No</option>
          </select>
        </div>
        <div class="col-md-6 d-none" id="children_under18_block">
          <label for="children_under_18">If YES, how many children under the age of 18 live in the home?</label>
          <input name="children_under_18" type="text" class="form-control">
        </div>
      </div>
    </div>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" id="step-client-consents"
          data-program-match="!parent,!lsat,!doep,!dwie,!dwii"
          data-bipp-fallback="1">

    <legend>8&nbsp;&nbsp;Client Consents</legend>

    <div class="mb-4">
      <label class="form-label fw-bold">Consent for Disclosure of Information</label>
      <div class="border p-3 mb-2 rounded overflow-auto" style="max-height: 240px; background-color: #f8f9fa;">
        <p>I understand that such disclosure will be made for the purposes of progress reports, referrals, and facilitating victim safety.</p>
        <p>Disclosure is limited to information regarding attendance, participation, information exchange, and referrals for services.</p>
        <p>I understand that I may revoke this consent at any time and that my request for revocation must be in writing. If not earlier revoked, this consent for disclosure of information shall expire 1 year after my completion of or termination from Lakeview Education - Batterers Intervention & Prevention Program.</p>
        <p>I understand my right to confidentiality. I further understand that this consent form gives Lakeview Education - BIPP permission to share confidential information about me in the way described above.</p>
        <p>I understand that Victim will be contacted by the Victim Advocate and offered counseling services. She/He will be provided enrollment, completion, or termination information from Lakeview Education - BIPP.</p>
        <p>Release of information is voluntary; I understand I have a right to refuse Lakeview Education - BIPP's request for this disclosure.</p>
        <p>Lakeview Education - BIPP reserves the right to dismiss any client who refuses to meet the provisions of The Texas Department of Criminal Justice-Community Justice Assistance Division and Texas Council on Family Violence Battering Intervention & Prevention Project guidelines.</p>
        <p>Information disclosed by batterers during an assessment (intake), group sessions, and exit is confidential and shall not be shared with victims.</p>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="agree_disclosure" name="agree_disclosure" required>
        <label class="form-check-label" for="agree_disclosure">
          By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the Lakeview Education - Batterers Intervention & Prevention Program.
        </label>
      </div>
    </div>

    <div class="mb-4">
      <label class="form-label fw-bold">Lakeview Education Program Agreement</label>
      <div class="border p-3 mb-2 rounded overflow-auto" style="max-height: 240px; background-color: #f8f9fa;">
        <p>Fee per session is: Parole Intake Orientation $20, $15.00 per group ($310 total), $20 Completion; Probation 18 Week – Intake Orientation $30, $30 per group, $30 Completion ($600 total); Probation 27 Week – Intake Orientation $30, $20 per group, $30 Completion ($600 total). This fee is only one type of demonstration of your accountability and restitution for violent behavior. Breaks, Assessment (intake), Orientation, or Exit Session are not to be included towards the 36 hours (18 weeks) or 54 hours (27 weeks).</p>
        <p>Battering Intervention and Prevention Program consists of Assessment (Intake) and Orientation and at least 36 hours of group sessions in a minimum of 24 weekly sessions. Not to exceed one session per week. If dismissed, the client must apply to re-enter into Lakeview Education BIPP. Re-entry is considered on a case-by-case basis. I understand that I cannot re-enter the program until I have paid off my previous balance.</p>
        <p>Clients who miss (3) consecutive sessions (group or individual) or a total of (5 sessions), you will be discharged from the program. Your referral sources will determine what happens with your case as a result of your absences. <em>There are no excused absences. Incarceration is an inexcusable absence.</em> Clients have the option to attend “Attendance Review” if the client is facing discharge.</p>
        <p>You must be focused and facing your webcam during the entire group; <strong>NO</strong> watching TV or working on your computer. You will only be given one warning. Second warning will result in no credit for the session and/or a discharge violation from Lakeview Education BIPP program.</p>
        <p>You must be in a quiet room; not driving, in your car, or doing any other activity. Otherwise, you will receive no credit for that BIPP session and/or receive a discharge violation from Lakeview Education BIPP Program.</p>
        <p>If there is any interruption during your Lakeview Education Group by a child or adult, you will lose credit for your class and/or receive a discharge violation from Lakeview Education Program.</p>
        <p>If there is any appearance of alcohol or vaping during Lakeview Education Group, it will result in a request for a drug test. If found dirty, you must sign a behavioral contract or be unsuccessfully discharged from Lakeview Education BIPP Program.</p>
        <p>During your Lakeview Education, if there is any woman present even for a moment, especially the victim, you shall receive no credit for the class and/or be unsuccessfully discharged from Lakeview Education BIPP Program.</p>
        <p>If after a restroom break you do not return, or you take more than 5 minutes, you shall receive no credit for your Lakeview Education BIPP Group session.</p>
        <p>Payment for services is due at the time service is rendered. You will not be credited for attending groups or individual sessions unless payment is received. The client is required to maintain no more than a $30 balance. I will continue to attend until I have a zero balance. Attendance may exceed 24 weeks if payment is not completed.</p>
        <p>I hereby agree to arrive to all of my sessions on time. If you are 5 minutes late after the designated start time, you will not receive credit for attending group.</p>
        <p>I understand that I must register into the group upon online login with the group facilitator. I will not be counted present for the session unless I register in with the group facilitator.</p>
        <p>I will notify Lakeview Education BIPP of any change of address or phone number.</p>
        <p>I hereby agree to contact BIPP by phone at [contact number] when I am unable to attend a scheduled session. Failure to contact Lakeview Education within 2 consecutive absences is an automatic discharge from Lakeview Education Program.</p>
        <p>I agree not to attend group under the influence of alcohol or drugs; refusal of a Drug Screening is an automatic discharge. It will be my responsibility to arrange transportation home or any safety measures so I'm not a danger to myself or others for driving under the influence. The referring agency will be notified of the incident.</p>
        <p>I hereby agree not to be abusive towards any staff person or other group member. I understand that I may not use sexist or racist language.</p>
        <p>I hereby agree to respect the confidentiality rights of my fellow client/group members. I further understand that a violation of this rule shall result in immediate termination from the program and shall be reported to the proper authorities.</p>
        <p>I hereby agree to notify a staff person of any and all emergencies that I am either part of or witness to.</p>
        <p>I understand that Lakeview Education BIPP is committed to helping me gain a better understanding of my problems and how to find productive solutions and that it is the main goal of my psychoeducational classes.</p>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="agree_program" name="agree_program" required>
        <label class="form-check-label" for="agree_program">
          By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the Lakeview Education - Batterers Intervention & Prevention Program.
        </label>
      </div>
    </div>

    <div class="mb-4">
      <label class="form-label fw-bold">Taking Responsibility</label>
      <div class="border p-3 mb-2 rounded overflow-auto" style="max-height: 240px; background-color: #f8f9fa;">
        <p>During group discussions, participants may not blame anyone else for their own behaviors.</p>
        <p>Participants agree to not use any form of violence, abusive, threatening, and controlling behaviors including stalking during the weeks they are in the program. A participant who uses violence may be terminated from the program. This action will be reported to the participant’s referral agencies. Participants will cease violent, abusive, threatening, and controlling behaviors, including stalking and violation of a protective order. Participants who are terminated for this reason and wish to re-enter the program will re-start from the 1st week.</p>
        <p>Participants will develop and adhere to a non-violence plan as outlined in the program curriculum.</p>
        <p>Lakeview Education requires me to disable any devices that could collect information from the environment, such as Google Home Assistant, Amazon Alexa, or Apple Siri, and I agree that I will not record nor take screenshots of the group. Lakeview Education requires me to be in a private space and not in any public area such as a park, yard, or open area; other people not in the group should not be exposed to the content nor hear or observe the group.</p>
        <p>This includes changing locations, walking around the house, neighborhood, or any public space. The responsibility of having a private area is mine. I cannot use the virtual group session to expel my partner or children from the residence. I must relocate to another location or private room in the residence. If I am a parent, I agree to ensure my children are safe and taken care of but are not interrupting the session or listening to group discussions.</p>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="agree_responsibility" name="agree_responsibility" required>
        <label class="form-check-label" for="agree_responsibility">
          By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the Lakeview Education - Batterers Intervention & Prevention Program.
        </label>
      </div>
    </div>

    <div class="mb-4">
      <label class="form-label fw-bold">BIPP Client Policy & Termination Policy</label>
      <div class="border p-3 mb-2 rounded overflow-auto" style="max-height: 240px; background-color: #f8f9fa;">
        <p>I have received a copy of the "Policy for Clients" for Lakeview Education - BIPP. I understand my rights and responsibilities, and I agree to enter Lakeview Education - BIPP.</p>
        <p>I understand that in accordance with Guideline 31 of the Texas Department of Criminal Justice-Community Justice Assistance Division and Texas Council on Family Violence Battering Intervention Prevention Program guidelines, I am being provided a written agreement that clearly delineates the obligation of the Lakeview Education - BIPP to the client. I understand that the Lakeview Education - BIPP shall:</p>
        <ul>
          <li>Provide services in a manner that I can understand.</li>
          <li>Provide copies of all written agreements.</li>
          <li>Notify me of changes in group time and schedules.</li>
          <li>Comply with anti-discrimination laws.</li>
          <li>Report quarterly to probation, courts of law, and/or other referral agencies regarding my progress or lack of progress during group.</li>
          <li>Provide reports weekly and/or monthly about my BIPP progress to my referral source: Probation, Parole, Child Protective Services, Courts, Attorney.</li>
          <li>Report to me regarding my status and participation.</li>
          <li>Provide fair and humane treatment.</li>
        </ul>
        <p>As a client of Lakeview Education - BIPP, you have the right to terminate services with our agency at any moment. The risk of terminating services will be explained to you by a counselor/instructor. You have the right to choose other agencies for your services, and Lakeview Education - BIPP will provide you with a list of known community agencies that may provide the services you need, except for clients referred by Probation; clients will be referred back to their Supervision Officer.</p>
        <p>Lakeview Education - BIPP also has the right to terminate services with clients if:</p>
        <ul>
          <li>Continued abuse, particularly physical violence.</li>
          <li>Client has accumulated (3) consecutive absences or a total of (5) sessions.</li>
          <li>Client has failed to pay for services over $30.</li>
          <li>Client is believed to be violent/aggressive towards others or staff.</li>
          <li>Client is involved in illegal activities on the premises.</li>
          <li>Client need for treatment is incompatible with types of services.</li>
          <li>Lakeview Education - BIPP client violates any of the BIPP rules.</li>
        </ul>
        <p>A report will be made within 5 working days to your referral source of any known law violations, incidents or physical violence, and/or termination from BIPP.</p>
        <p>Clients have the right to seek other resources outside of Lakeview Education - BIPP, and when possible, Lakeview Education - BIPP staff will provide or make a referral.</p>
        <p>The above Termination Policy applies to clients who are attending services on a voluntary basis or court-ordered to receive services or who are mandated to receive services by other entities; however, clients are responsible to check with those entities who mandate them to come regarding the alternatives for receiving services in another agency or consequences for choosing to stop services before making this final decision.</p>
        <p>Lakeview Education - BIPP will provide batterers at the time of assessment (intake) with a copy of the circumstances under which they can be terminated before completion.</p>
        <p>I have read and understand the above statements and voluntarily enter into counseling services from the staff of Lakeview Education - BIPP - by entering my name below. (By clicking SUBMIT, I hereby confirm the above information to the best of my knowledge is correct and true, with no misleading or false content in accordance with Texas Perjury Statute, Sec. 37.02 (a) (2) Chapter 32, Civil Practice and Remedies Code.)</p>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="agree_termination" name="agree_termination" required>
        <label class="form-check-label" for="agree_termination">
          By checking "I Agree", I confirm that I have read, understood, and agree to abide by the terms and conditions outlined above. I acknowledge my rights and responsibilities as described, and I accept these terms as a condition of participation in the Lakeview Education - Batterers Intervention & Prevention Program.
        </label>
      </div>
    </div>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step"
          data-program-match="!parent,!lsat,!doep,!dwie,!dwii"
          data-bipp-fallback="1">

    <h5 class="mt-3">9A. Individualized Plan</h5>
    <label>Choose your reason(s) for attending the Battering Intervention & Prevention Program (BIPP): <span class="text-danger">*</span></label>

    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="protective_order" name="reasons[]" value="Protective Order">
      <label class="form-check-label" for="protective_order">Protective Order</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="aggravated_assault" name="reasons[]" value="Aggravated Assault">
      <label class="form-check-label" for="aggravated_assault">Aggravated Assault</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="violation_po" name="reasons[]" value="Violation of Protective Order">
      <label class="form-check-label" for="violation_po">Violation of Protective Order</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="choking" name="reasons[]" value="Choking/Strangulation Charge">
      <label class="form-check-label" for="choking">Choking / Strangulation Charge</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="interfering_911" name="reasons[]" value="Interfering with a 911 Call">
      <label class="form-check-label" for="interfering_911">Interfering with a 911 Call</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="terroristic_threat" name="reasons[]" value="Terroristic Threat">
      <label class="form-check-label" for="terroristic_threat">Terroristic Threat</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="injury_child" name="reasons[]" value="Injury to a Child">
      <label class="form-check-label" for="injury_child">Injury to a Child</label>
    </div>
    <div class="form-check">
      <input class="form-check-input reason-checkbox" type="checkbox" id="other_reason" name="reasons[]" value="Other">
      <label class="form-check-label" for="other_reason">Other</label>
    </div>

    <div class="mt-2 d-none" id="other_reason_text_container">
      <label for="other_reason_text">If other was chosen, please explain: <span class="text-danger">*</span></label>
      <input type="text" id="other_reason_text" name="other_reason_text" class="form-control">
    </div>

    <div class="mt-3">
      <label for="describe_reason">Describe the reason you are here (offense) <span class="text-danger">*</span></label>
      <textarea name="describe_reason" id="describe_reason" class="form-control" required></textarea>
    </div>

    <div class="mt-3">
      <label for="personal_goal_bipp">Your personal goal for attending BIPP <span class="text-danger">*</span></label>
      <textarea name="personal_goal_bipp" id="personal_goal_bipp" class="form-control" required></textarea>
      <div class="alert alert-info mt-2">
        <strong>EXAMPLE:</strong> Objective: Client will increase his knowledge regarding the issue of abuse, domestic violence and skills that can help him change behaviors and eliminate abuse and violence from his relationships. Strategies: Client will attend the BIPP group weekly for 90 minutes and will participate actively and display receptiveness to the information presented. Client will make consistent application of skills presented by thinking about the new information presented, reviewing the handouts, talking about what he’s learning with others, asking questions, making application of skills, completing assigned homework, giving examples in group of the progress he is making and by only focusing on him and his relationship with his partner. Client will practice POSITIVE SELF-TALK by stating I DON’T ARGUE, I DON’T FIGHT AND IF NEEDED I TAKE A TIME-OUT SO THAT I KEEP ME AND MY FAMILY MEMBERS SAFE FROM ABUSE AND VIOLENCE.
      </div>
    </div>

    <h5 class="mt-4">9B. Case Notes</h5>

    <div class="mb-3">
      <label for="counselor">Counselor</label>
      <input type="text" id="counselor" name="counselor" class="form-control" value="Facilitator Name" readonly>
    </div>

    <div class="mb-3">
      <label for="group_time">Chosen Group Time <span class="text-danger">*</span></label>
      <select id="group_time" name="group_time" class="form-select" required>
        <option value="" disabled selected>Select your group time…</option>
      </select>
    </div>



    <div class="mb-3">
      <label for="intake_date">Intake Date <span class="text-danger">*</span></label>
      <input type="date" id="intake_date" name="intake_date" class="form-control" required placeholder="yyyy-mm-dd">
      <small class="form-text text-muted">
        The date you will start BIPP & attend your first group. <br>
        <strong>Example:</strong> If today is Tuesday and you selected “Saturday 9AM” as your group time, enter this coming Saturday’s date.
      </small>
    </div>


    <div class="nav-buttons">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="button" class="btn btn-primary next">Next</button>
    </div>
  </fieldset>

  <fieldset class="step" data-program-match="bipp">
    <legend>10&nbsp;&nbsp;Digital Signature</legend>

    <p><strong>Entering your name constitutes a digital signature.</strong> By clicking <strong>SUBMIT</strong>, I hereby confirm the above information to the best of my knowledge is correct and true, with no misleading or false content in accordance with Texas Perjury Statute, Sec. 37.02 (a) (2) Chapter 32, Civil Practice and Remedies Code.</p>

    <label class="required">Signature – type your full legal name</label>
    <input type="text" name="digital_signature" class="form-control" required>

    <div class="mt-3">
      <label for="signature_date">Date Signed</label>
      <input type="date" id="signature_date" name="signature_date" class="form-control" readonly>
    </div>

    <div class="nav-buttons mt-4">
      <button type="button" class="btn btn-secondary prev">Previous</button>
      <button type="submit" class="btn btn-success">Submit Intake Packet</button>
    </div>
  </fieldset>

  <!-- Signature + Submit for all NON-BIPP programs -->
  <fieldset id="step-sign-submit-nonbipp" class="step" data-program-match="!bipp">
    <legend>Signature and Submit</legend>

    <div class="mb-3">
      <label for="sig_name_nonbipp" class="form-label">Full legal name</label>
      <input type="text" id="sig_name_nonbipp" name="digital_signature" class="form-control" required>
    </div>

    <div class="mb-3">
      <label for="sig_date_nonbipp" class="form-label">Date</label>
      <input type="date" id="sig_date_nonbipp" name="signature_date" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Draw signature</label>
      <div class="border rounded p-2">
        <canvas id="sigCanvasNonBipp" width="600" height="180" style="width:100%;max-width:600px;height:180px;touch-action:none;"></canvas>
      </div>
      <input type="hidden" id="sig_png_nonbipp" name="sig_png" required>
      <button type="button" id="sigClearNonBipp" class="btn btn-sm btn-secondary mt-2">Clear</button>
    </div>

    <div class="form-check mb-4">
      <input class="form-check-input" type="checkbox" id="certifyNonBipp" name="certify_nonbipp" required>
      <label class="form-check-label" for="certifyNonBipp">I certify the information provided is true and correct.</label>
    </div>

    <div class="d-flex justify-content-between">
      <button type="button" class="btn btn-outline-secondary prev">Previous</button>
      <button type="submit" class="btn btn-primary">Submit</button>
    </div>
  </fieldset>


</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // ---- DOM refs ----
  const form            = document.getElementById('intakeForm');
  const barFill         = document.querySelector('#progressBar span');
  const alertBox        = document.getElementById('stepAlert');

  const programCards    = Array.from(document.querySelectorAll('.program-card'));
  const btnContinue     = document.getElementById('programContinue');

  const programIdHidden  = document.getElementById('program_id_hidden');
  const programCodeHidden = document.getElementById('program_code_hidden');
  const programReadonly   = document.getElementById('program_readonly');

  window.ALL_STEPS = Array.from(document.querySelectorAll('fieldset.step'));
  const ALL_STEPS  = window.ALL_STEPS;

  // Fallback to the first fieldset if #step0-program is missing
  const chooserFs   = document.getElementById('step0-program') || ALL_STEPS[0];

  // ---- State ----
  let CURRENT_CODE  = 'bipp';
  let CURRENT_INDEX = 0;
  let ACTIVE_STEPS  = [];
  let SELECTED = { id: null, label: '', code: 'bipp' };

  function programCodeFromLabel(name) {
    const n = (name || '').toLowerCase();
    if (n.includes('parent')) return 'parent';
    if (/\bdwi\s*ii\b/.test(n) || n.includes('repeat')) return 'dwii';
    if (n.includes('dwie') || n.includes('dwi education') || n.includes('dwi ed')) return 'dwie';
    if (n.includes('doep') || n.includes('drug offender')) return 'doep';
    if (n.includes('life') || n.includes('anti-theft') || n.includes('anti theft') || n.includes('anti')) return 'lsat';
    if (n.includes('bipp')) return 'bipp';
    return 'bipp';
  }

  function matchesProgram(fs, code) {
    const raw = (fs.getAttribute('data-program-match') || '').trim();
    if (!raw) {
      if (fs.getAttribute('data-bipp-fallback') === '1') return code === 'bipp';
      return code === 'bipp';
    }
    const tokens    = raw.split(',').map(s => s.trim().toLowerCase()).filter(Boolean);
    const positives = tokens.filter(t => !t.startsWith('!'));
    const negatives = tokens.filter(t =>  t.startsWith('!')).map(t => t.slice(1));
    if (positives.length > 0 && !positives.includes(code)) return false;
    if (negatives.includes(code)) return false;
    return true;
  }

  function setDisabledByMatch(code){
    const all = (typeof ALL_STEPS !== 'undefined' && ALL_STEPS.length)
      ? ALL_STEPS
      : Array.from(document.querySelectorAll('fieldset.step'));
    for (const fs of all){
      const match = matchesProgram(fs, code);
      fs.querySelectorAll('input,select,textarea,button').forEach(el=>{
        if (el.type === 'button') return;
        el.disabled = !match && fs !== chooserFs;
      });
    }
  }

  function conditionPasses(fs) {
    const cond = fs.getAttribute('data-show-if');
    if (!cond) return true;
    const [field, expected] = cond.split('=').map(s => (s ?? '').trim());
    if (!field) return true;
    const els = Array.from(form.querySelectorAll(`[name="${field}"]`));
    if (!els.length) return false;
    if (els[0].type === 'radio') {
      const checked = els.find(e => e.checked);
      return (checked ? (checked.value || '').trim() : '') === expected;
    }
    if (els[0].type === 'checkbox') {
      const checks = els.filter(e => e.checked).map(e => (e.value || '').trim());
      return checks.includes(expected);
    }
    return (els[0].value || '').trim() === expected;
  }

  function buildActiveSteps() {
    ACTIVE_STEPS = [];
    if (chooserFs) ACTIVE_STEPS.push(chooserFs);
    for (const fs of ALL_STEPS) {
      if (fs === chooserFs) continue;
      if (matchesProgram(fs, CURRENT_CODE)) ACTIVE_STEPS.push(fs);
    }
  }


   function showOnly(step) {
    if (!step) return;
    ALL_STEPS.forEach(fs => fs.classList.remove('active'));
    step.classList.add('active');
    const maxIndex = Math.max(ACTIVE_STEPS.length - 1, 1);
    const pct = Math.min(100, Math.round((CURRENT_INDEX / maxIndex) * 100));
    if (barFill) barFill.style.width = pct + '%';
    if (alertBox) { alertBox.style.display = 'none'; alertBox.textContent = ''; }
  }

  function firstVisibleIndexFrom(start, direction) {
    let i = start;
    while (i >= 0 && i < ACTIVE_STEPS.length) {
      const fs = ACTIVE_STEPS[i];
      if (i === 0 || conditionPasses(fs)) return i;
      i += direction;
    }
    return Math.min(Math.max(0, start), ACTIVE_STEPS.length - 1);
  }

  // Validate required fields on the current step (visible ones only)
  function validateStep(fs) {
    let ok = true;
    const invalid = [];

    const requiredEls = Array.from(fs.querySelectorAll('[required]'));
    for (const el of requiredEls) {
      // Skip hidden-by-condition subcontrols
      if (!el.closest('fieldset.step')?.classList.contains('active')) continue;

      let isValid = true;
      if (el.type === 'radio') {
        const name = el.name;
        const group = Array.from(fs.querySelectorAll(`input[type="radio"][name="${name}"]`));
        isValid = group.some(r => r.checked);
      } else if (el.type === 'checkbox') {
        // For single required checkbox, it must be checked
        // For groups, the form typically has each required; accept at least one checked in the group
        const name = el.name;
        const group = Array.from(fs.querySelectorAll(`input[type="checkbox"][name="${name}"]`));
        if (group.length > 1) {
          isValid = group.some(c => c.checked);
        } else {
          isValid = el.checked;
        }
      } else {
        isValid = (el.value || '').trim() !== '';
      }

      el.classList.toggle('invalid-field', !isValid);
      if (!isValid) {
        ok = false;
        invalid.push(el);
      }
    }

    if (!ok) {
      alertBox.textContent = 'Please complete the required field(s) on this page.';
      alertBox.style.display = '';
      // focus first invalid
      if (invalid[0] && typeof invalid[0].focus === 'function') invalid[0].focus();
    } else {
      alertBox.style.display = 'none';
      alertBox.textContent = '';
    }

    return ok;
  }

  function goTo(index) {
    if (!ACTIVE_STEPS.length) return;
    CURRENT_INDEX = Math.min(Math.max(index, 0), ACTIVE_STEPS.length - 1);
    if (CURRENT_INDEX > 0 && !conditionPasses(ACTIVE_STEPS[CURRENT_INDEX])) {
      CURRENT_INDEX = firstVisibleIndexFrom(CURRENT_INDEX, +1);
    }
    showOnly(ACTIVE_STEPS[CURRENT_INDEX]);
  }

  function resetToChooser() {
    // Clear active markers
    programCards.forEach(c => c.classList.remove('active'));
    btnContinue.disabled = true;
    SELECTED = { id: null, label: '', code: 'bipp' };
    CURRENT_CODE = 'bipp';
    programIdHidden.value = '';
    if (programReadonly) programReadonly.value = '';
    buildActiveSteps();
    goTo(0);
  }

  // ---- Wire up program chooser ----
  programCards.forEach(card => {
    card.addEventListener('click', () => {
      programCards.forEach(c => c.classList.remove('active'));
      card.classList.add('active');

      const pid   = card.getAttribute('data-program-id');
      const label = card.getAttribute('data-program-name') || card.querySelector('.program-title')?.textContent || '';

      SELECTED.id    = pid ? String(pid) : null;
      SELECTED.label = label.trim();
      SELECTED.code  = programCodeFromLabel(SELECTED.label);
      if (programCodeHidden) programCodeHidden.value = SELECTED.code;


      btnContinue.disabled = !SELECTED.id;
    });
  });

  if (btnContinue) btnContinue.addEventListener('click', () => {

    if (!SELECTED.id) return;

    programIdHidden.value = SELECTED.id;
    if (programReadonly) programReadonly.value = SELECTED.label;
    if (programCodeHidden) programCodeHidden.value = SELECTED.code;


    CURRENT_CODE = SELECTED.code;
    setDisabledByMatch(CURRENT_CODE);
    buildActiveSteps();
    // Jump to first page *after* chooser that passes conditions
    const firstIdx = firstVisibleIndexFrom(1, +1);
    goTo(firstIdx);

    // Small UX: set today's date on known fields if empty
    const today = new Date().toISOString().slice(0,10);
    const parentToday = document.getElementById('parent_today_date');
    if (parentToday && !parentToday.value) parentToday.value = today;
  });

  // ---- Prev / Next / Submit handlers ----
  if (form) form.addEventListener('click', (e) => {
    const btn = e.target.closest('button');
    if (!btn) return;

    if (btn.classList.contains('next')) {
      e.preventDefault();
      const fs = ACTIVE_STEPS[CURRENT_INDEX];
      if (!validateStep(fs)) return;

      let nextIdx = firstVisibleIndexFrom(CURRENT_INDEX + 1, +1);
      // If we run past the end, do nothing here (final page likely has submit)
      if (nextIdx < ACTIVE_STEPS.length) goTo(nextIdx);
      return;
    }

    if (btn.classList.contains('prev')) {
      e.preventDefault();
      // If we’re on the first program page, return to chooser cleanly
      if (CURRENT_INDEX <= 1) {
        resetToChooser();
      } else {
        const prevIdx = firstVisibleIndexFrom(CURRENT_INDEX - 1, -1);
        goTo(prevIdx);
      }
      return;
    }
  });

  // Prevent submit if current step invalid
  setDisabledByMatch(CURRENT_CODE);
  if (form) form.addEventListener('submit', (e) => {
    if (programCodeHidden && !programCodeHidden.value) programCodeHidden.value = CURRENT_CODE;

    const fs = ACTIVE_STEPS[CURRENT_INDEX] || chooserFs;
    if (!validateStep(fs)) {
      e.preventDefault();
      e.stopPropagation();
    }
  });

  // ---- Initial render (start at chooser) ----
  buildActiveSteps();
  goTo(0);
});
</script>



<script>
document.addEventListener('DOMContentLoaded', () => {
  const parentDate = document.getElementById('parent_today_date');
  if (parentDate && !parentDate.value) {
    parentDate.value = new Date().toISOString().slice(0, 10);
  }
});
</script>

<script>
(function(){
  const canvas = document.getElementById('sigCanvasNonBipp');
  if (!canvas) return;

  const pngOut   = document.getElementById('sig_png_nonbipp');
  const clearBtn = document.getElementById('sigClearNonBipp');
  const ctx = canvas.getContext('2d');

  let drawing = false;
  let hasInk  = false;
  let last = {x:0, y:0};

  function pos(e){
    const r = canvas.getBoundingClientRect();
    const p = (e.touches && e.touches[0]) || e;
    return { x: p.clientX - r.left, y: p.clientY - r.top };
  }

  function start(e){
    e.preventDefault();
    drawing = true;
    hasInk  = true;
    last = pos(e);
  }
  function move(e){
    if (!drawing) return;
    const p2 = pos(e);
    ctx.beginPath();
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p2.x, p2.y);
    ctx.stroke();
    last = p2;
    // keep PNG updated while drawing
    pngOut.value = canvas.toDataURL('image/png');
  }
  function end(){
    drawing = false;
    if (hasInk) pngOut.value = canvas.toDataURL('image/png');
  }
  function clearCanvas(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    hasInk = false;
    pngOut.value = '';
  }

  // Pointer + touch support
  canvas.addEventListener('mousedown', start);
  canvas.addEventListener('mousemove', move);
  window.addEventListener('mouseup', end);

  canvas.addEventListener('touchstart', start, {passive:false});
  canvas.addEventListener('touchmove',  move,  {passive:false});
  canvas.addEventListener('touchend',   end);

  clearBtn?.addEventListener('click', clearCanvas);

  // Optional: set today on load if empty
  const dateEl = document.getElementById('sig_date_nonbipp');
  if (dateEl && !dateEl.value) dateEl.value = new Date().toISOString().slice(0,10);
})();
</script>



</body>
</html>
