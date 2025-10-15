<?php
declare(strict_types=1);

/* ───────── 1.  Auth & DB ───────── */
include_once 'auth.php';
include_once '../config/config.php';
check_loggedin($con, '../index.php');        // $con → auth ; $link → DB config
$link->set_charset('utf8mb4');

/* ───────── 2.  Helpers ───────── */
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function n($v): ?string { $v = trim((string)$v); return $v === '' ? null : $v; }

/* ───────── 3.  Look-up maps for drop-downs ───────── */
$MAP_GENDER   = ['1'=>'Not specified','2'=>'Male','3'=>'Female'];
$MAP_PROGRAM  = ['2'=>'BIPP (male)','3'=>'BIPP (female)'];
$MAP_RACE     = ['0'=>'Hispanic','1'=>'African American','2'=>'Asian','3'=>'Middle Easterner','4'=>'Caucasian','5'=>'Other'];
$MAP_REFERRAL = ['0'=>'Other / Unknown','1'=>'Probation','2'=>'Parole','3'=>'Pre-trial','4'=>'CPS','5'=>'Attorney','6'=>'Self'];
$MAP_EDU      = ['0'=>'GED','1'=>'High School','2'=>'Some College','3'=>'Associates','4'=>'Bachelors','5'=>'Masters','6'=>'Doctorates','7'=>'None of the Above'];

/* ───────── 4.  Identify row ───────── */
$id = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$id || !ctype_digit((string)$id)) {
    http_response_code(400);
    exit('Missing or invalid intake_id');
}

/* ───────── 5.  Save on POST ───── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // $id is already defined just above this block
    $skip = ['id','packet_complete','staff_verified','imported_to_client'];
    $cols = $vals = [];

    foreach ($_POST as $k => $v) {
        if (in_array($k, $skip, true)) continue;
        $cols[] = "$k = ?";
        $vals[] = n($v);
    }
    $vals[] = $id;                       // for the WHERE clause

    $sql  = 'UPDATE intake_packet SET '.implode(',', $cols).' WHERE intake_id = ?';
    $stmt = $link->prepare($sql) or exit('Prepare failed: '.$link->error);
    $stmt->bind_param(str_repeat('s', count($vals)), ...$vals);
    if (!$stmt->execute()) exit('Save failed: '.$stmt->error);

    header("Location: intake-review.php?id=$id");
    exit;
}

/* ───────── 6.  Load row for form ───────── */
$stmt = $link->prepare('SELECT * FROM intake_packet WHERE intake_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    exit('Intake packet not found');
}


/* ───────── 5.  Metadata for form generation ─────────
   [ label , type , options? ]
   type:  text | textarea | date | yesno | select
--------------------------------------------------------------------*/
$fields = [

/* === 1. Contact & Demographics === */
'__h:Contact & Demographics' => [],

'first_name'     => ['First Name','text'],
'last_name'      => ['Last Name','text'],
'date_of_birth'  => ['Date of Birth','date'],
'gender_id'      => ['Gender','select',$MAP_GENDER],
'program_id'     => ['Program','select',$MAP_PROGRAM],
'id_number'      => ['DL / ID Number','text'],
'email'          => ['Email','text'],
'phone_cell'     => ['Cell Phone','text'],

'address_street' => ['Street','text'],
'address_city'   => ['City','text'],
'address_state'  => ['State','text'],
'address_zip'    => ['Zip','text'],
'birth_city'     => ['City of Birth','text'],
'race_id'        => ['Race','select',$MAP_RACE],
'education_level'=> ['Education','select',$MAP_EDU],

/* === 2. Employment === */
'__h:Employment' => [],

'employed'   => ['Currently Employed','yesno'],
'employer'   => ['Employer','text'],
'occupation' => ['Occupation','text'],

/* === 3. Emergency / Military === */
'__h:Emergency & Military' => [],

'emergency_name'     => ['Emergency Name','text'],
'emergency_phone'    => ['Emergency Phone','text'],
'emergency_relation' => ['Emergency Relation','text'],
'military_branch'    => ['Military Branch','text'],
'military_date'      => ['Military Date','text'],

/* === 4. Referral === */
'__h:Referral' => [],

'referral_type_id'        => ['Referral Type','select',$MAP_REFERRAL],
'referring_officer_name'  => ['Officer / Case Manager','text'],
'referring_officer_email' => ['Officer Email','text'],
'additional_charge_dates'  => ['Additional Charge Dates','text'],
'additional_charge_details'=> ['Additional Charge Details','textarea'],

/* === 5. Family === */
'__h:Marital & Family' => [],

'living_situation'        => ['Living Situation','text'],
'marital_status'          => ['Marital Status','text'],
'has_children'            => ['Has Children','yesno'],
'children_live_with_you'  => ['Children Live With You','yesno'],
'children_names_ages'     => ['Children Names & Ages','textarea'],
'child_abuse_physical'    => ['Child Abuse – Physical','yesno'],
'child_abuse_sexual'      => ['Child Abuse – Sexual','yesno'],
'child_abuse_emotional'   => ['Child Abuse – Emotional','yesno'],
'child_abuse_neglect'     => ['Child Abuse – Neglect','yesno'],
'cps_notified'            => ['CPS Notified','yesno'],
'cps_care'                => ['CPS Care','yesno'],
'discipline_desc'         => ['Discipline Description','textarea'],

/* === 6. Substance Use === */
'__h:Substance Use' => [],

'alcohol_past'            => ['Alcohol – Past','yesno'],
'alcohol_frequency'       => ['Alcohol – Past Frequency','text'],
'alcohol_current'         => ['Alcohol – Current','yesno'],
'alcohol_current_details' => ['Alcohol – Current Details','text'],
'drug_past'               => ['Drug – Past','yesno'],
'drug_past_details'       => ['Drug – Past Details','text'],
'drug_current'            => ['Drug – Current','yesno'],
'drug_current_details'    => ['Drug – Current Details','text'],
'alcohol_during_abuse'    => ['Alcohol During Incident','yesno'],
'drug_during_abuse'       => ['Drug During Incident','yesno'],

/* === 7. Mental-Health === */
'__h:Mental Health' => [],

'counseling_history'   => ['Counseling History','yesno'],
'counseling_reason'    => ['Counseling Reason','textarea'],
'depressed_currently'  => ['Currently Depressed','yesno'],
'depression_reason'    => ['Depression Reason','textarea'],
'attempted_suicide'    => ['Attempted Suicide','yesno'],
'suicide_last_attempt' => ['Suicide – Last Attempt','text'],
'mental_health_meds'   => ['Mental-Health Meds','yesno'],
'mental_meds_list'     => ['Meds List','textarea'],
'mental_doctor_name'   => ['Doctor Name','text'],
'sexual_abuse_history' => ['Sexual Abuse History','yesno'],
'head_trauma_history'  => ['Head Trauma History','yesno'],
'head_trauma_desc'     => ['Head Trauma Description','textarea'],
'weapon_possession_history'=> ['Weapon Possession History','yesno'],
'abuse_trauma_history' => ['Abuse / Trauma as Child','yesno'],
'violent_incident_desc'=> ['Violent Incident Description','textarea'],

/* === 8. Victim === */
'__h:Victim' => [],

'victim_contact_provided'=> ['Victim Contact Provided','yesno'],
'victim_relationship'    => ['Victim Relationship','text'],
'victim_first_name'      => ['Victim First Name','text'],
'victim_last_name'       => ['Victim Last Name','text'],
'victim_age'             => ['Victim Age','number'],
'victim_gender'          => ['Victim Gender','text'],
'victim_phone'           => ['Victim Phone','text'],
'victim_email'           => ['Victim Email','text'],
'victim_address'         => ['Victim Address','text'],
'victim_city'            => ['Victim City','text'],
'victim_state'           => ['Victim State','text'],
'victim_zip'             => ['Victim Zip','text'],
'live_with_victim'       => ['Live With Victim','yesno'],
'children_with_victim'   => ['Children With Victim','text'],

/* === 9. Plan & Notes === */
'__h:Plan & Notes' => [],

'reasons'             => ['Reasons (comma list)','text'],
'other_reason_text'   => ['Other Reason Text','text'],
'offense_reason'      => ['Offense Reason','text'],
'offense_description' => ['Offense Description','textarea'],
'personal_goal'       => ['Personal Goal','textarea'],
'counselor_name'      => ['Counselor','text'],
'chosen_group_time'   => ['Chosen Group Time','text'],
'intake_date'         => ['Intake Date','date'],

/* === 10. Consents & Signature === */
'__h:Consents & Signature' => [],

'consent_confidentiality'   => ['Consent – Confidentiality','yesno'],
'consent_disclosure'        => ['Consent – Disclosure','yesno'],
'consent_program_agreement' => ['Consent – Program Agreement','yesno'],
'consent_responsibility'    => ['Consent – Responsibility','yesno'],
'consent_policy_termination'=> ['Consent – Policy / Termination','yesno'],
'digital_signature'         => ['Digital Signature','text'],
'signature_date'            => ['Signature Date','date'],
];


/* ───────── 6.  HTML ───────── */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Edit Intake Packet #<?= h($id) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<!-- Favicons  -->
<link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
<link rel="manifest" href="/favicons/site.webmanifest">

<!-- Bootstrap / FontAwesome -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
  textarea{min-height:90px}
  .yesno-select{min-width:90px}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4 mb-5">
  <h2 class="mb-4">Edit Intake Packet #<?= h($id) ?></h2>

  <form method="post" autocomplete="off">
    <input type="hidden" name="id" value="<?= h($id) ?>">

    <?php
    /* ---- render the form ---- */
    foreach ($fields as $col => $meta) {

        /* section heading sentinel */
        if (strpos($col,'__h:') === 0) {
            echo '<h4 class="mt-4">'.h(substr($col,4)).'</h4><hr>';
            continue;
        }

        [$label,$type,$opts] = $meta + [null,null,[]];   // $opts only for select
        $val = $row[$col] ?? '';

        echo '<div class="form-group">';
        echo "<label>$label</label>";

        switch ($type) {
            case 'textarea':
                echo '<textarea class="form-control" name="'.$col.'">'.h($val).'</textarea>';
                break;

            case 'date':
                echo '<input type="date" class="form-control" name="'.$col.'" value="'.h($val).'">';
                break;

            case 'number':
                echo '<input type="number" step="1" class="form-control" name="'.$col.'" value="'.h($val).'">';
                break;

            case 'yesno':
                echo '<select name="'.$col.'" class="form-control yesno-select">';
                echo '<option value="" '.($val===''?'selected':'').'>–</option>';
                echo '<option value="1" '.($val==='1'?'selected':'').'>Yes</option>';
                echo '<option value="0" '.($val==='0'?'selected':'').'>No</option>';
                echo '</select>';
                break;

            case 'select':   // use mapping array in $opts
                echo '<select name="'.$col.'" class="form-control">';
                echo '<option value="" '.($val===''?'selected':'').'>—</option>';
                foreach ($opts as $k=>$labelOpt) {
                    $sel = ((string)$k === (string)$val) ? 'selected' : '';
                    echo "<option value=\"$k\" $sel>".h($labelOpt)."</option>";
                }
                echo '</select>';
                break;

            default: // text
                echo '<input type="text" class="form-control" name="'.$col.'" value="'.h($val).'">';
        }

        echo '</div>';
    }

    ?>

    <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
    <a href="intake-review.php?id=<?= h($id) ?>" class="btn btn-secondary mt-3">Cancel</a>
  </form>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
