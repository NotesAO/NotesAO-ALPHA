<?php
/**
 * intake-review.php
 * -----------------
 * Read-only, comprehensive view of ONE row in clinicnotepro_ffltest.intake_packet.
 * URL: intake-review.php?id=123
 */

declare(strict_types=1);

include_once 'auth.php';
include_once '../config/config.php';
check_loggedin($con, '../index.php'); // $con used by auth; $link from config

/* ------------------------------------------------------------------ */
/* Helpers                                                            */
/* ------------------------------------------------------------------ */
function h($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function v($v): string {
    return ($v === null || $v === '') ? '—' : h($v);
}
function yn($v): string {
    if ($v === null || $v === '') return '—';
    return ((int)$v) === 1 ? 'Yes' : 'No';
}
function dt($v): string {
    if (!$v || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') return '—';
    return h($v);
}
/** Render one card/table block (no column wrapper) */
function cardTable(string $title, array $rows): void {
    echo '<div class="section-card mb-4">';
    echo '  <h5 class="mb-3">'.$title.'</h5>';
    echo '  <table class="table table-sm table-borderless kv">';
    foreach ($rows as $label => $value) {
        // horizontal rule (spacer + line)
        if ($label === '__hr') {
            echo '<tr class="table-section-sep"><td colspan="2"><hr></td></tr>';
            continue;
        }
        // sub-section title
        if (strpos($label, '__sub:') === 0) {
            $sub = substr($label, 7);
            echo '<tr class="table-subhead"><th colspan="2">'.h($sub).'</th></tr>';
            continue;
        }
        echo '<tr class="data-row"><th class="text-muted">'.$label.'</th><td>'.$value.'</td></tr>';
    }
    echo '  </table>';
    echo '</div>';
}

function csrf_token(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_check(): void {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'msg' => 'Invalid CSRF token']));
    }
}
/* -------- NEW HELPERS ------------------------------------------------ */

/** mm/dd/yyyy */
function dt_date($v): string {
    if (!$v || $v === '0000-00-00') return '—';
    $ts = strtotime($v);
    return $ts ? date('m/d/Y', $ts) : '—';
}

/** mm/dd/yyyy hh:mm AM/PM */
function dt_datetime($v): string {
    if (!$v || $v === '0000-00-00 00:00:00') return '—';
    $ts = strtotime($v);
    return $ts ? date('m/d/Y h:i A', $ts) : '—';
}

/** 10–digit numbers → xxx-xxx-xxxx */
function phone_fmt($v): string {
    if ($v === null || $v === '') return '—';
    $digits = preg_replace('/\D+/', '', $v);
    if (strlen($digits) === 10) {
        return substr($digits,0,3) . '-' . substr($digits,3,3) . '-' . substr($digits,6);
    }
    return h($v);
}

/** tiny int 0/1 → Yes/No (or — if null/blank) */
function yesno($v): string {
    if ($v === null || $v === '') return '—';
    return ((int)$v) ? 'Yes' : 'No';
}



/* ------------------------------------------------------------------ */
/* Lookups                                                            */
/* ------------------------------------------------------------------ */
$genderMap = [ '1' => 'Not specified', '2' => 'Male', '3' => 'Female' ];
$raceMap   = [
    '0' => 'Hispanic',
    '1' => 'African American',
    '2' => 'Asian',
    '3' => 'Middle Easterner',
    '4' => 'Caucasian',
    '5' => 'Other'
];
$referralMap = [
    '0' => 'Other / Unknown',
    '1' => 'Probation',
    '2' => 'Parole',
    '3' => 'Pre-trial',
    '4' => 'CPS',
    '5' => 'Attorney',
    '6' => 'Self'
];
$programMap = [
    '2' => 'BIPP (male)',
    '3' => 'BIPP (female)',
];

$educationMap = [
    '0' => 'GED',
    '1' => 'High School',
    '2' => 'Some College',
    '3' => 'Associates',
    '4' => 'Bachelors',
    '5' => 'Masters',
    '6' => 'Doctorates',
    '7' => 'None of the Above',
];

/** victim_contact_provided: 0 = No, 1 = Yes */
$victimContactMap = [
    '0' => 'No',
    '1' => 'Yes',
];


/* ------------------------------------------------------------------ */
/* Input + Fetch                                                      */
/* ------------------------------------------------------------------ */
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Missing or invalid id');
}

// ------------------------------------------------------------------
// Handle "Mark Verified" AJAX POST
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    header('Content-Type: application/json');

    csrf_check();

    $verifyId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$verifyId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Invalid id']);
        exit;
    }

    $verifiedBy = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Unknown';

    $sql = "UPDATE intake_packet
               SET staff_verified = 1,
                   verified_by    = ?,
                   verified_at    = NOW()
             WHERE intake_id = ?";
    $stmt = $link->prepare($sql);
    $stmt->bind_param('si', $verifiedBy, $verifyId);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        echo json_encode(['ok' => false, 'msg' => 'DB error']);
        exit;
    }

    // Return what the UI needs to refresh itself
    echo json_encode([
        'ok'           => true,
        'verified_by'  => $verifiedBy,
        'verified_at'  => date('Y-m-d H:i:s'),
    ]);
    exit;
}

// ------------------------------------------------------------------
// Handle "Import to Client" AJAX POST  (stub starter)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_to_client') {
    header('Content-Type: application/json');
    csrf_check();

    $intakeId  = filter_input(INPUT_POST, 'intake_id', FILTER_VALIDATE_INT);
    $clientId  = filter_input(INPUT_POST, 'client_id_override', FILTER_VALIDATE_INT);
    if (!$clientId) {
        // If override is empty, you can fallback to the suspected one you found earlier,
        // but since this is a new request (no $suspectedClient var here), refetch the row and repeat the lookup,
        // or send the suspected client id in a hidden input in the form (we already did).
        $clientId = filter_input(INPUT_POST, 'client_id', FILTER_VALIDATE_INT);
    }

    if (!$intakeId || !$clientId) {
        echo json_encode(['ok' => false, 'msg' => 'Missing intake_id or client_id']);
        exit;
    }

    // TODO: copy fields from intake_packet → client table here...

    // mark intake row as imported
    $stmt = $link->prepare("
        UPDATE intake_packet
           SET imported_to_client = 1,
               imported_client_id = ?
         WHERE intake_id = ?
    ");
    $stmt->bind_param('ii', $clientId, $intakeId);
    $ok = $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => (bool)$ok]);
    exit;
}



$sql = "SELECT * FROM intake_packet WHERE intake_id = ?";
$stmt = $link->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

// --- Suspected client lookup -------------------------------------------------
$CLIENT_TBL = 'client'; // <<< change to your real client table name
$suspectedClient = null;

if ($row) {
    $first = trim((string)($row['first_name'] ?? ''));
    $last  = trim((string)($row['last_name']  ?? ''));
    $dob   = (string)($row['date_of_birth'] ?? '');

    if ($first !== '' && $last !== '' && $dob !== '') {
        $sqlSus = "
            SELECT id AS client_id, first_name, last_name, date_of_birth
            FROM {$CLIENT_TBL}
            WHERE LOWER(first_name) = LOWER(?)
              AND LOWER(last_name)  = LOWER(?)
              AND date_of_birth     = ?
            LIMIT 1
        ";
        if ($sus = $link->prepare($sqlSus)) {
            $sus->bind_param('sss', $first, $last, $dob);
            $sus->execute();
            $susRes = $sus->get_result();
            $suspectedClient = $susRes->fetch_assoc() ?: null;
            $sus->close();
        }
    }
}


$csrf = csrf_token();   // <= ADD THIS
$alreadyVerified = (int)($row['staff_verified'] ?? 0) === 1;

$stmt->close();

if (!$row) {
    http_response_code(404);
    exit('Packet not found.');
}

/* convenience */
$g   = $row['gender_id']        ?? null;
$r   = $row['race_id']          ?? null;
$ref = $row['referral_type_id'] ?? null;

$username     = $_SESSION['name'] ?? 'User';
$program_name = $_SESSION['program_name'] ?? 'Program';

$created_at   = $row['created_at'] ?? $row['created'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Intake Packet | Review</title>
<!-- re‑use your existing favicon / Bootstrap links -->
      <link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
      <link rel="stylesheet"
            href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <style>
        body{font-family:system-ui,Arial;background:#f5f6fa;padding:2rem}
        .card{max-width:720px;margin:0 auto;border:0;border-radius:8px;
              box-shadow:0 2px 8px rgba(0,0,0,.08)}
      </style>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- FAVICON LINKS (from index.html) -->
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
<!-- Bootstrap CSS/JS -->
<link rel="stylesheet" 
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
<link rel="stylesheet"
      href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
<style>
  body{padding-top:70px;background:#f5f6fa;}
  .section-card{
    background:#fff;border-radius:6px;
    box-shadow:0 1px 2px rgba(0,0,0,.06);
    padding:1.25rem;
  }
  .kv tr.table-section-sep td { padding: .65rem 0 .35rem; }
  .kv tr.table-section-sep hr { border-top: 1px solid #e6e9ef; margin: .75rem 0; }
  .kv tr.table-subhead th {
    padding-top: .75rem;
    font-size: .775rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #6c757d;
    }
    .kv th { width: 38%; }
    @media (max-width: 991.98px){
      .kv th { width: auto; }
    }

</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid pt-4">

  <!-- Page header / actions -->
    <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Intake Packet #<?= h($id) ?></h1>
    <div class="btn-group">
        <a class="btn btn-secondary btn-md" href="intake-index.php">
        <i class="fas fa-arrow-left"></i> Back to Intake Packets
        </a>

        <button id="btnVerify"
                class="btn btn-outline-primary btn-md"
                data-toggle="modal"
                data-target="#verifyModal"
                <?= $alreadyVerified ? 'disabled' : '' ?>>
        <i class="fas fa-check"></i> Mark Verified
        </button>

        <button id="btnImport"
                class="btn btn-outline-success btn-md"
                data-toggle="modal"
                data-target="#importModal"
                <?= $suspectedClient ? '' : 'disabled' ?>
                data-client-id="<?= $suspectedClient ? (int)$suspectedClient['client_id'] : '' ?>">
          <i class="fas fa-user-plus"></i> Import to Client
        </button>

    </div>
    </div>

    <!-- Verify Modal -->
    <div class="modal fade" id="verifyModal" tabindex="-1" role="dialog" aria-labelledby="verifyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="verifyForm" class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="verifyModalLabel">Verify Intake Packet</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="modal-body">
            <p>
            I, <strong><?= h($_SESSION['name'] ?? $_SESSION['username'] ?? 'Unknown') ?></strong>,
            verify this intake packet has been reviewed and is completely correct.
            </p>

            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="id" value="<?= h($id) ?>">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Yes, Mark Verified</button>
        </div>
        </form>
    </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form id="importForm" class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="importModalLabel">Import to Client</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <?php if ($suspectedClient): ?>
              <div class="alert alert-info">
                Suspected match:
                <strong><?= h($suspectedClient['first_name'].' '.$suspectedClient['last_name']) ?></strong>
                (<?= dt_date($suspectedClient['date_of_birth']) ?>) – ID:
                <strong><?= (int)$suspectedClient['client_id'] ?></strong>
              </div>
            <?php else: ?>
              <div class="alert alert-warning">
                No suspected client found. You can still create a brand new client from this intake (future step).
              </div>
            <?php endif; ?>

            <input type="hidden" name="action" value="import_to_client">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="intake_id" value="<?= (int)$id ?>">
            <input type="hidden" name="client_id"  value="<?= $suspectedClient ? (int)$suspectedClient['client_id'] : '' ?>">

            <p class="mb-1"><strong>What will happen (you’ll finish this):</strong></p>
            <ul class="mb-3">
              <li>Copy intake fields into the existing client (or create a new client).</li>
              <li>Mark this intake as <em>imported_to_client = 1</em> and store <em>imported_client_id</em>.</li>
            </ul>

            <div class="form-group">
              <label>Confirm / override Client ID to import into:</label>
              <input type="number" class="form-control" name="client_id_override"
                    value="<?= $suspectedClient ? (int)$suspectedClient['client_id'] : '' ?>">
              <small class="form-text text-muted">Leave blank to use the suspected client above, or enter a specific client ID.</small>
            </div>

          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button id="btnDoImport" type="submit" class="btn btn-success" <?= $suspectedClient ? '' : 'disabled' ?>>Import</button>
          </div>
        </form>
      </div>
    </div>




  <!-- META (full width) -->
  <div class="row">
    <div class="col-12">
      <div class="section-card mb-4">
        <h5 class="mb-3">Meta / Status</h5>

        <div class="meta-badges mb-2">
            <span class="badge badge-<?= ((int)$row['packet_complete']) ? 'success' : 'secondary' ?>">
                Packet Complete: <?= yn($row['packet_complete']) ?>
            </span>
            <span id="badgeStaffVerified" class="badge badge-<?= ((int)$row['staff_verified']) ? 'success' : 'secondary' ?>">
                Staff Verified: <?= yn($row['staff_verified']) ?>
            </span>
            <span class="badge badge-<?= ((int)$row['imported_to_client']) ? 'success' : 'secondary' ?>">
                Imported: <?= yn($row['imported_to_client']) ?>
            </span>
            </div>

            <table class="table table-sm table-borderless kv">
              <tr><th>Created At</th><td><?= dt_datetime($row['created_at'] ?? null) ?></td></tr>
              <tr><th>Verified By</th><td id="tdVerifiedBy"><?= v($row['verified_by'] ?? null) ?></td></tr>
              <tr><th>Verified At</th><td id="tdVerifiedAt"><?= dt_datetime($row['verified_at'] ?? null) ?></td></tr>
              

              <tr>
                <th>Suspected Client</th>
                <td>
                  <?php if ($suspectedClient): ?>
                    <a href="client-review.php?client_id=<?= (int)$suspectedClient['client_id'] ?>" target="_blank">
                      <?= h($suspectedClient['first_name'] . ' ' . $suspectedClient['last_name']) ?>
                      (<?= dt_date($suspectedClient['date_of_birth']) ?>)
                    </a>
                    <span class="badge badge-info ml-2">Match</span>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
              <tr><th>Imported Client ID</th><td><?= v($row['imported_client_id'] ?? null) ?></td></tr>
            </table>


      </div>
    </div>
  </div>

  <!-- Row 1: 1 left, 2+3 right (stacked) -->
  <div class="row">
    <div class="col-xl-6">
      <?php
      cardTable('1. Contact & Demographics', [
        '__sub:iidentity'        => '',
        'First Name'            => v($row['first_name']),
        'Last Name'             => v($row['last_name']),
        'Email'                 => v($row['email']),
        'Cell Phone'        => phone_fmt($row['phone_cell']),
        'Date of Birth'     => dt_date($row['date_of_birth']),
        'Gender'                => h($genderMap[(string)$g] ?? (string)$g),
        'Program'           => h($programMap[(string)($row['program_id'] ?? '')] ?? (string)($row['program_id'] ?? '')),
        'DL / ID Number'        => v($row['id_number']),

        '__hr'                  => '',

        '__sub:aaddress'         => '',
        'Street Address'        => v($row['address_street']),
        'City'                  => v($row['address_city']),
        'State'                 => v($row['address_state']),
        'Zip'                   => v($row['address_zip']),

        '__hr'                    => '',

        '__sub:Bbackground'      => '',
        'City of Birth'         => v($row['birth_city']),
        'Race'                  => h($raceMap[(string)$r] ?? (string)$r),
        'Highest Education' => h($educationMap[(string)($row['education_level'] ?? '')] ?? (string)($row['education_level'] ?? '')),

        '__hr'                  => '',

        '__sub:Eemployment'      => '',
        'Employed'              => yn($row['employed']),
        'Employer'              => v($row['employer']),
        'Occupation'            => v($row['occupation']),
      ]);
      ?>
    </div>

    <div class="col-xl-6">
      <?php
      cardTable('2. Emergency Contact & Military', [
        '__sub:Eemergency Contact' => '',
        'Emergency Name'          => v($row['emergency_name']),
        'Emergency Phone' => phone_fmt($row['emergency_phone']),
        'Emergency Relation'      => v($row['emergency_relation']),

        '__hr'                    => '',

        '__sub:Mmilitary'          => '',
        'Military Branch'         => v($row['military_branch']),
        'Military Date'           => v($row['military_date']),
      ]);

      cardTable('3. Referral', [
        'Referral Type'             => h($referralMap[(string)$ref] ?? (string)$ref),
        'Officer / Case Manager'    => v($row['referring_officer_name']),
        'Officer E‑mail'            => v($row['referring_officer_email']),
        '__hr'                    => '',
        '__sub:Eextra'      => '',
        'Additional Charge Dates'   => v($row['additional_charge_dates']),
        'Additional Charge Details' => v($row['additional_charge_details']),
      ]);
      ?>
    </div>
  </div>

  <!-- Row 2: 4 left, 5 right -->
  <div class="row">
    <div class="col-xl-6">
      <?php
      cardTable('4. Marital & Family', [
        '__sub:Hhousehold'          => '',
        'Living Situation'         => v($row['living_situation']),
        'Marital Status'           => v($row['marital_status']),

        '__hr'                     => '',

        '__sub:Cchildren'           => '',
        'Has Children'             => yn($row['has_children']),
        'Children Live With You'   => yn($row['children_live_with_you']),
        'Children Names & Ages'    => v($row['children_names_ages']),

        '__hr'                     => '',

        '__sub:Aabuse / CPS'        => '',
        'Child Abuse – Physical'   => yn($row['child_abuse_physical']),
        'Child Abuse – Sexual'     => yn($row['child_abuse_sexual']),
        'Child Abuse – Emotional'  => yn($row['child_abuse_emotional']),
        'Child Abuse – Neglect'    => yn($row['child_abuse_neglect']),
        'CPS Notified'             => yn($row['cps_notified']),
        'CPS Care'                 => yn($row['cps_care']),

        '__hr'                     => '',

        '__sub:Ddiscipline'         => '',
        'Discipline Description'   => v($row['discipline_desc']),
      ]);
      ?>
    </div>
    <div class="col-xl-6">
      <?php
      cardTable('5. Substance Use', [
        '__sub:Aalcohol'              => '',
        'Alcohol – Past'             => yn($row['alcohol_past']),
        'Alcohol – Past Frequency'   => v($row['alcohol_frequency']),
        'Alcohol – Current'          => yn($row['alcohol_current']),
        'Alcohol – Current Details'  => v($row['alcohol_current_details']),

        

        '__sub:Ddrugs'                => '',
        'Drug – Past'                => yn($row['drug_past']),
        'Drug – Past Details'        => v($row['drug_past_details']),
        'Drug – Current'             => yn($row['drug_current']),
        'Drug – Current Details'     => v($row['drug_current_details']),

        '__hr'                       => '',

        '__sub:Dduring Incident'      => '',
        'Alcohol During Abuse'       => yn($row['alcohol_during_abuse']),
        'Drug During Abuse'          => yn($row['drug_during_abuse']),
      ]);
      ?>
    </div>
  </div>

  <!-- Row 3: 6 left, 7 right -->
  <div class="row">
    <div class="col-xl-6">
      <?php
      cardTable('6. Counseling / Mental Health', [
        '__sub:Hhistory'                => '',
        'Counseling History'           => yn($row['counseling_history']),
        'Counseling Reason'            => v($row['counseling_reason']),
        'Currently Depressed'          => yn($row['depressed_currently']),
        'Depression Reason'            => v($row['depression_reason']),
        'Attempted Suicide'            => yn($row['attempted_suicide']),
        'Suicide – Last Attempt'       => v($row['suicide_last_attempt']),

        '__hr'                          => '',

        '__sub:Mmedication'              => '',
        'Mental-Health Meds'            => yn($row['mental_health_meds']),
        'Meds List'                     => v($row['mental_meds_list']),
        'Doctor Name'                   => v($row['mental_doctor_name']),

        '__hr'                          => '',

        '__sub:oOther'                   => '',
        'Sexual Abuse History'          => yn($row['sexual_abuse_history']),
        'Head Trauma History'           => yn($row['head_trauma_history']),
        'Head Trauma Desc'              => v($row['head_trauma_desc']),
        'Weapon Possession History'     => yn($row['weapon_possession_history']),
        'Abuse / Trauma as Child'       => yn($row['abuse_trauma_history']),
        'Violent Incident Desc'         => v($row['violent_incident_desc']),
      ]);
      ?>
    </div>
    <div class="col-xl-6">
      <?php
      cardTable('7. Victim', [
        '__sub:Ggeneral'              => '',
        'Victim Contact Provided' => h($victimContactMap[(string)($row['victim_contact_provided'] ?? '')] ?? '—'),
        'Victim Relationship'        => v($row['victim_relationship']),

        '__hr'                        => '',

        '__sub:iidentity'             => '',
        'First Name'                 => v($row['victim_first_name']),
        'Last Name'                  => v($row['victim_last_name']),
        'Age'                        => v($row['victim_age']),
        'Gender'                     => v($row['victim_gender']),

        '__hr'                        => '',

        '__sub:Ccontact'              => '',
        'Phone'                   => phone_fmt($row['victim_phone']),
        'Email'                      => v($row['victim_email']),
        'Address'                    => v($row['victim_address']),
        'City'                       => v($row['victim_city']),
        'State'                      => v($row['victim_state']),
        'Zip'                        => v($row['victim_zip']),

        '__hr'                        => '',

        '__sub:Hhousehold'            => '',
        'Live With Victim'           => yn($row['live_with_victim']),
        'Children with Victim'       => v($row['children_with_victim']),
      ]);
      ?>
    </div>
  </div>

  <!-- Row 4: Plan (left) | Consents + Signature (right, stacked) -->
  <div class="row">
    <div class="col-xl-6">
      <?php
      cardTable('Individualized Plan & Case Notes', [
        '__sub:Pplan'              => '',
        'Reasons'                 => v($row['reasons']),
        'Other Reason Text'       => v($row['other_reason_text']),
        'Offense Description'     => v($row['offense_description']),
        'Personal Goal'           => v($row['personal_goal']),

        '__hr'                    => '',

        '__sub:Ccase'              => '',
        'Counselor'               => v($row['counselor_name']),
        'Chosen Group Time'       => v($row['chosen_group_time']),
        'Intake Date'     => dt_date($row['intake_date']),
      ]);
      ?>
    </div>
    <div class="col-xl-6">
      <?php
      cardTable('Client Consents', [
        'Confidentiality'         => yn($row['consent_confidentiality']),
        'Disclosure'              => yn($row['consent_disclosure']),
        'Program Agreement'       => yn($row['consent_program_agreement']),
        'Responsibility'          => yn($row['consent_responsibility']),
        'Policy / Termination'    => yn($row['consent_policy_termination']),
      ]);

      cardTable('Signature', [
        'Digital Signature'       => v($row['digital_signature']),
        'Signature Date'  => dt_date($row['signature_date']),
      ]);
      ?>
    </div>
  </div>

</div><!-- /.container-fluid -->

<script>
(function() {
  const form = document.getElementById('verifyForm');
  if (!form) return;

  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    try {
      const body = new URLSearchParams(new FormData(form));
      const res  = await fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body
      });
      const json = await res.json();

      if (!json.ok) {
        alert(json.msg || 'Could not verify.');
        submitBtn.disabled = false;
        return;
      }

      // Update UI
      const badge = document.getElementById('badgeStaffVerified');
      if (badge) {
        badge.classList.remove('badge-secondary');
        badge.classList.add('badge-success');
        badge.textContent = 'Staff Verified: Yes';
      }
      const tdBy  = document.getElementById('tdVerifiedBy');
      const tdAt  = document.getElementById('tdVerifiedAt');
      if (tdBy) tdBy.textContent = json.verified_by || '—';
      if (tdAt) {
        const d = new Date(json.verified_at.replace(' ', 'T')); // basic parse
        const opts = { month:'2-digit', day:'2-digit', year:'numeric',
                        hour:'2-digit', minute:'2-digit', hour12:true };
        tdAt.textContent = d.toLocaleString(undefined, opts);
      }

      const btnVerify = document.getElementById('btnVerify');
      if (btnVerify) btnVerify.disabled = true;

      $('#verifyModal').modal('hide');
    } catch (err) {
      console.error(err);
      alert('Network / server error.');
      submitBtn.disabled = false;
    }
  });
})();
</script>

<script>
(function() {
  const importForm = document.getElementById('importForm');
  if (!importForm) return;

  importForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    const submitBtn = importForm.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    try {
      const body = new URLSearchParams(new FormData(importForm));
      const res  = await fetch(location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body
      });
      const json = await res.json();
      if (!json.ok) {
        alert(json.msg || 'Could not import.');
        submitBtn.disabled = false;
        return;
      }
      // Update UI (mark imported, set imported_client_id, etc.)
      alert('Imported successfully.');
      location.reload();
    } catch (err) {
      console.error(err);
      alert('Network / server error.');
      submitBtn.disabled = false;
    }
  });
})();
</script>


<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
