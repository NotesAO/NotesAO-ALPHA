<?php
ob_start();
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// -------------------------
// LOGGING SETUP (optional)
// -------------------------
$log_file = '/home/notesao/logs/client.error.log';
ini_set('log_errors', 1);
ini_set('error_log', $log_file);

function log_event($message) {
    global $log_file;
    error_log("[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, 3, $log_file);
}

log_event("=== New page load: clientportal.php ===");

// -------------------------
// DB CONFIG
// -------------------------
define('db_host', '50.28.37.79');
define('db_name', 'clinicnotepro_saferpath');
define('db_user', 'clinicnotepro_saferpath_app');
define('db_pass', 'PF-m[T-+pF%g');

// -------------------------
// CONNECT TO CLIENT DB
// -------------------------
$con = new mysqli(db_host, db_user, db_pass, db_name);
if ($con->connect_error) {
    log_event("❌ DB connection failed: " . $con->connect_error);
    die("Database connection failed.");
}
log_event("✅ DB connected");

// -------------------------
// HELPER: Convert IDs to text
// -------------------------
function getProgramName($program_id) {
    switch($program_id) {
        case 1: return "Thinking for a Change";
        case 2: return "Men's BIPP";
        case 3: return "Women's BIPP";
        case 4: return "Anger Control";  // <-- Added for program_id=4
        default: return "Other/Unknown Program";
    }
}

function getReferralName($referral_id) {
    switch($referral_id) {
        case 1: return "Probation";
        case 2: return "Parole / CPS";
        case 3: return "Pretrial";
        case 4: return "CPS";
        case 5: return "Attorney";
        case 6: return "VTC";
        default: return "Other/Unknown Referral";
    }
}

/**
 * Normalise referral IDs so CPS (4) is treated like Parole (2).
 * Extend or alter the map array if you ever need more aliases.
 */
function normalizeReferralId(int $id): int
{
    static $map = [
        4 => 1,   // CPS -> Parole
        // 7 => 2, // example: something else -> Parole
    ];
    return $map[$id] ?? $id;
}


// -----------------------------------------------------------------------------
// FULL GROUP DATA
// Each entry includes 'day_time' for short label in make-up list
// -----------------------------------------------------------------------------
$groupData = [
    

];

foreach ($groupData as &$g) {          // & makes it modify in-place
    $g['referral_type_id'] = normalizeReferralId((int)$g['referral_type_id']);
}
unset($g);                             // break the reference


// ----------------------------------------------------
// PROCESS LOGIN / LOOKUP
// ----------------------------------------------------
$foundClient   = null;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $dob_year  = trim($_POST['dob_year'] ?? '');
    $dob_month = trim($_POST['dob_month'] ?? '');
    $dob_day   = trim($_POST['dob_day'] ?? '');
    $birth_place = trim($_POST['birth_place'] ?? '');
   
    // Basic validation: ensure all are non-empty
    if ($first_name && $last_name && $dob_year && $dob_month && $dob_day) {
        // reassemble into YYYY-MM-DD
        // zero-pad month/day to 2 digits if needed
        $dob = sprintf('%04d-%02d-%02d', $dob_year, $dob_month, $dob_day);

        log_event("📨 POST received | fn={$first_name}, ln={$last_name}, dob={$dob}, bp={$birth_place}");

        // ... proceed with your existing logic ...
    } else {
        $error_message = "First name, last name, and full DOB are required.";
        log_event("⚠️ Required fields missing (fn, ln, or dob).");
    }

    log_event("📨 POST received | fn={$first_name}, ln={$last_name}, dob={$dob}, bp={$birth_place}");

    if ($first_name && $last_name && $dob) {
        // Pull from client table
        $sql = "
            SELECT 
                c.first_name, c.last_name, c.date_of_birth, c.birth_place,
                c.gender_id, c.referral_type_id, c.required_sessions, c.fee,
                c.therapy_group_id,
                c.program_id,
                c.weekly_attendance,
                c.attends_sunday, c.attends_monday, c.attends_tuesday,
                c.attends_wednesday, c.attends_thursday, c.attends_friday,
                c.attends_saturday
            FROM client c
            WHERE LOWER(c.first_name) = LOWER(?)
              AND LOWER(c.last_name) = LOWER(?)
              AND c.date_of_birth = ?
              AND (c.birth_place IS NULL OR LOWER(c.birth_place) = LOWER(?))
            LIMIT 1
        ";
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            $error_message = "Server error (SQL).";
            log_event("❌ SQL prepare failed: " . $con->error);
        } else {
            $stmt->bind_param('ssss', $first_name, $last_name, $dob, $birth_place);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($client = $result->fetch_assoc()) {
                $_SESSION['client_verified'] = true;
                $foundClient = $client;
                log_event("✅ Match found: " . json_encode($client));
            } else {
                $error_message = "No matching client record found.";
                log_event("❌ No match found for that name/DOB/birth_place.");
            }
            $stmt->close();
        }
    } else {
        $error_message = "First name, last name, and DOB are required.";
        log_event("⚠️ Required fields missing.");
    }
}

$con->close();
log_event("🔒 DB connection closed.");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Client Portal - NotesAO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

    <style>
        body {
            background: #eef2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            max-width: 700px;
            width: 100%;
        }
        input, button {
            border-radius: 8px;
        }
        .error-message {
            background: #d9534f;
            color: white;
            padding: 10px;
            border-radius: 8px;
            margin-top: 10px;
        }
        .info-table td { padding: 4px 8px; }
    </style>
</head>
<body>

<div class="container">
    <a href="https://www.saferpathfvs.org">
        <img alt="Safer Path" src="saferpathlogo.png" class="img-fluid mb-3">
    </a>
    <h2 class="text-center">Client Portal</h2>

    <?php if (!empty($error_message)) : ?>
        <div class="error-message">❌ <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- Simple lookup form -->
    <form method="post">
        <label>First Name:</label>
        <input type="text" name="first_name" class="form-control" required>

        <label>Last Name:</label>
        <input type="text" name="last_name" class="form-control" required>

        <label>Date of Birth:</label>
        <div class="form-row">
        <div class="col">
            <select name="dob_month" class="form-control" required>
            <option value="">Month</option>
            <?php
            for ($m = 1; $m <= 12; $m++) {
                $monthName = date("F", mktime(0, 0, 0, $m, 1));
                echo "<option value='$m'>$monthName</option>";
            }
            ?>
            </select>
        </div>
        <div class="col">
            <select name="dob_day" class="form-control" required>
            <option value="">Day</option>
            <?php
            for ($d = 1; $d <= 31; $d++) {
                echo "<option value='$d'>$d</option>";
            }
            ?>
            </select>
        </div>
        <div class="col">
            <select name="dob_year" class="form-control" required>
            <option value="">Year</option>
            <?php
            for ($y = 1930; $y <= (int)date('Y'); $y++) {
                echo "<option value='$y'>$y</option>";
            }
            ?>
            </select>
        </div>
        </div>




        <button type="submit" class="btn btn-primary btn-block mt-3">Submit</button>
    </form>
</div>

<?php if ($foundClient): ?>
<!-- Modal showing group info -->
<div class="modal fade" id="clientModal" tabindex="-1" role="dialog" aria-labelledby="clientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content p-3">
      <div class="modal-header">
        <h5 class="modal-title">Welcome, <?= htmlspecialchars($foundClient['first_name']) ?>!</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
      <?php
        // ------------------------------------------------------
        // 1) Extract client data
        // ------------------------------------------------------
        $clientProgramId   = (int)$foundClient['program_id'];
        $clientReferralId  = (int)$foundClient['referral_type_id'];
        $clientReferralId = normalizeReferralId($clientReferralId);
        $clientGenderId    = (int)$foundClient['gender_id'];
        $clientSessions    = (int)$foundClient['required_sessions'];
        $clientFee         = (float)$foundClient['fee'];
        $clientGroupId     = (int)$foundClient['therapy_group_id'];

        $progName     = getProgramName($clientProgramId);
        $referralName = getReferralName($clientReferralId);

        // ------------------------------------------------------
        // 2) Always display Program/Referral/RequiredSessions/Fee
        // ------------------------------------------------------
        echo '<table class="info-table">';
        echo '<tr><td><strong>Program:</strong></td><td>'   . htmlspecialchars($progName)     . '</td></tr>';
        echo '<tr><td><strong>Referral:</strong></td><td>'  . htmlspecialchars($referralName) . '</td></tr>';
        echo '<tr><td><strong>Required Sessions:</strong></td><td>' 
            . htmlspecialchars($clientSessions) . '</td></tr>';
        echo '<tr><td><strong>Fee:</strong></td><td>$'
            . htmlspecialchars($clientFee) . '</td></tr>';
        echo '</table>';
        echo '<hr>';

        // We'll use a simple flag to skip the $finalGroup logic if T4C
        $skipGroupLogic = false;

        // ------------------------------------------------------
        // 3) If T4C (program_id=1), show T4C block & skip $finalGroup
        // ------------------------------------------------------
        if ($clientProgramId === 1) {
            $skipGroupLogic = true;

            echo "<p><strong>Your Assigned Group(s):</strong></p>";

            // Convert 'attends_...' columns to booleans for T4C days
            $attends = [
                'sunday'    => (int)$foundClient['attends_sunday'],
                'monday'    => (int)$foundClient['attends_monday'],
                'wednesday' => (int)$foundClient['attends_wednesday'],
                'thursday'  => (int)$foundClient['attends_thursday'],
                'friday'    => (int)$foundClient['attends_friday']
            ];

            // Check if therapy_group_id indicates Virtual T4C (116)
            if ($clientGroupId === 116) {
                // ---------- Virtual T4C ----------
                $groupDisplay = [];


                if (!empty($groupDisplay)) {
                    echo "<ul>";
                    foreach ($groupDisplay as $item) {
                        echo "<li>$item</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p class='text-danger'>
                            ⚠️ No valid attendance days marked for T4C virtual group.
                          </p>";
                }

            } else {
                // ---------- In-Person T4C ----------
                $groupDisplay = [];

                if ($attends['sunday']) {
                    $groupDisplay[] = "Sunday: 2:30PM AND/OR 5PM — 6850 Manhattan Blvd Ste. 205, Arlington, TX 76120";
                }
                if ($attends['monday']) {
                    $groupDisplay[] = "Monday: 10AM OR 7PM — 1100 East Lancaster Ave, Fort Worth, TX 76102";
                }
                if ($attends['wednesday']) {
                    $groupDisplay[] = "Wednesday: 7PM — 1100 East Lancaster Ave, Fort Worth, TX 76102";
                }
                if ($attends['thursday']) {
                    $groupDisplay[] = "Thursday: 7PM — 6850 Manhattan Blvd Ste. 205, Arlington, TX 76120";
                }
                if ($attends['friday']) {
                    $groupDisplay[] = "Friday: 10AM — 1100 East Lancaster Ave, Fort Worth, TX 76102";
                }

                if (!empty($groupDisplay)) {
                    echo "<ul>";
                    foreach ($groupDisplay as $line) {
                        echo "<li>" . htmlspecialchars($line) . "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p class='text-danger'>
                            ⚠️ No attendance days marked for T4C in-person groups.
                          </p>";
                }
            }

            // Done with T4C. We won't do finalGroup logic below.

        } // end if T4C

        // ------------------------------------------------------
        // 4) If not T4C, do pass-1 / pass-2 finalGroup logic
        // ------------------------------------------------------
        if (!$skipGroupLogic) {
            // PASS 1: EXACT match
            $exactMatch = null;
            foreach ($groupData as $g) {
                // If Anger Control or T4C, skip referral check:
                // (Though T4C is absent from $groupData, so it won't matter)
                $refOK = in_array($clientProgramId, [1, 4])
                    ? true
                    : (isset($g['referral_type_id']) && $g['referral_type_id'] === $clientReferralId);

                if (
                    $g['program_id']        === $clientProgramId &&
                    $refOK &&
                    $g['gender_id']         === $clientGenderId &&
                    $g['required_sessions'] === $clientSessions &&
                    (float)$g['fee']        === $clientFee &&
                    $g['therapy_group_id']  === $clientGroupId
                ) {
                    $exactMatch = $g;
                    break;
                }
            }

            // PASS 2: fallback ignoring fee
            $finalGroup = $exactMatch;
            if (!$exactMatch) {
                log_event("⚠️ No exact match on fee. Attempting fallback ignoring fee.");

                foreach ($groupData as $g) {
                    $refOK = ($clientProgramId === 4)
                        ? true
                        : (isset($g['referral_type_id']) && $g['referral_type_id'] === $clientReferralId);

                    if (
                        $g['program_id']        === $clientProgramId &&
                        $refOK &&
                        $g['gender_id']         === $clientGenderId &&
                        $g['required_sessions'] === $clientSessions &&
                        $g['therapy_group_id']  === $clientGroupId
                    ) {
                        $finalGroup = $g;
                        break;
                    }
                }
            }

            // If we have a finalGroup, show BIPP or Anger info
            if ($finalGroup) {
                // If fallback, show note
                if (!$exactMatch) {
                    echo "<div class='alert alert-warning' role='alert'>
                            <strong>Note:</strong> The fee on your record ($"
                          . htmlspecialchars($clientFee)
                          . ") did not match exactly. We matched on your other info.
                          </div>";
                }

                // Distinguish BIPP in-person vs virtual vs Anger
                $inPersonIds    = [103, 107, 117];
                $isInPersonBIPP = in_array($finalGroup['therapy_group_id'], $inPersonIds);

                echo "<p><strong>Your Assigned Group:</strong></p>";

                if ($clientProgramId === 4) {
                    // ---------- ANGER CONTROL ----------
                    echo "<p>" . htmlspecialchars($finalGroup['label']) . "</p>";
                    echo "<p>"
                       . "<a href='" . htmlspecialchars($finalGroup['link']) . "' target='_blank'>"
                       . htmlspecialchars($finalGroup['link'])
                       . "</a></p>";

                    // Show only the "other day" as single makeup
                    $otherId = ($finalGroup['therapy_group_id'] === 114) ? 119 : 114;

                    // Attempt to find that single "other day" group
                    $otherGroup = null;
                    foreach ($groupData as $mg) {
                        if (
                            $mg['program_id']       === 4 &&
                            $mg['therapy_group_id'] === $otherId &&
                            $mg['required_sessions']=== $clientSessions &&
                            (float)$mg['fee']       === $clientFee
                        ) {
                            $otherGroup = $mg;
                            break;
                        }
                    }

                    if ($otherGroup) {
                        echo "<hr><p><strong>Make‐Up Group:</strong></p>";
                        echo "<p>" . htmlspecialchars($otherGroup['label']) . "<br>";
                        echo "<a href='" . htmlspecialchars($otherGroup['link']) . "' target='_blank'>";
                        echo htmlspecialchars($otherGroup['link']);
                        echo "</a></p>";
                    } else {
                        echo "<hr><p><em>No make‐up group found.</em></p>";
                    }

                } elseif ($isInPersonBIPP) {
                    // ---------- BIPP IN-PERSON ----------
                    echo "<p>" . htmlspecialchars($finalGroup['label']) . "</p>";
                    echo "<p>Location: 1100 East Lancaster Ave, Fort Worth, TX 76102</p>";
                    echo "<p>Time: " . htmlspecialchars($finalGroup['day_time']) . "</p>";

                    // Gather possible in-person BIPP makeups
                    $makeupGroups = [];
                    foreach ($groupData as $mg) {
                        // skip same row
                        if ($mg === $finalGroup) continue;
                        if ($mg['therapy_group_id'] === $clientGroupId) continue;

                        if (
                            $mg['program_id']       === $clientProgramId &&
                            isset($mg['referral_type_id']) &&
                            $mg['referral_type_id']=== $clientReferralId &&
                            $mg['gender_id']        === $clientGenderId &&
                            $mg['required_sessions']== $clientSessions
                        ) {
                            // must match same fee if you want
                            if ((float)$mg['fee'] === $clientFee) {
                                if (in_array($mg['therapy_group_id'], $inPersonIds)) {
                                    $makeupGroups[] = $mg;
                                }
                            }
                        }
                    }

                    if (!empty($makeupGroups)) {
                        echo "<hr><p><strong>Make‐Up Groups (In-Person):</strong></p>";
                        echo "<ul>";
                        foreach ($makeupGroups as $mu) {
                            echo "<li><strong>" . htmlspecialchars($mu['label']) . "</strong><br>";
                            echo "Location: 1100 East Lancaster Ave, Fort Worth, TX 76102<br>";
                            echo "Time: " . htmlspecialchars($mu['day_time']) . "</li>";
                        }
                        echo "</ul>";
                    } else {
                        echo "<hr><p><em>No make‐up groups found matching your same fee/program/referral/sessions.</em></p>";
                    }

                } else {
                    // ---------- BIPP VIRTUAL ----------
                    echo "<p>" . htmlspecialchars($finalGroup['label']) . "<br>";
                    echo "<a href='" . htmlspecialchars($finalGroup['link']) . "' target='_blank'>";
                    echo htmlspecialchars($finalGroup['link']);
                    echo "</a></p>";

                    // Gather BIPP virtual makeups
                    $makeupGroups = [];
                    foreach ($groupData as $mg) {
                        // skip same row
                        if ($mg === $finalGroup) continue;
                        if ($mg['therapy_group_id'] === $clientGroupId) continue;

                        if (
                            $mg['program_id']       === $clientProgramId &&
                            isset($mg['referral_type_id']) &&
                            $mg['referral_type_id']=== $clientReferralId &&
                            $mg['gender_id']        === $clientGenderId &&
                            $mg['required_sessions']== $clientSessions
                        ) {
                            if ((float)$mg['fee'] === $clientFee) {
                                // must not be in-person
                                if (!in_array($mg['therapy_group_id'], $inPersonIds)) {
                                    $makeupGroups[] = $mg;
                                }
                            }
                        }
                    }

                    if (!empty($makeupGroups)) {
                        echo "<hr><p><strong>Make‐Up Groups (Virtual):</strong></p>";
                        echo "<ul>";
                        foreach ($makeupGroups as $mu) {
                            $short = $mu['day_time'] ?? $mu['label'];
                            echo "<li><a href='" . htmlspecialchars($mu['link']) . "' target='_blank'>"
                                 . htmlspecialchars($short) . "</a></li>";
                        }
                        echo "</ul>";
                    } else {
                        echo "<hr><p><em>No make‐up groups found matching your same fee/program/referral/sessions.</em></p>";
                    }
                }

            } else {
                // No finalGroup
                echo "<div class='alert alert-danger' role='alert'>
                        <strong>No matching link found</strong> for your group.
                        Please verify your data or contact the administrator.
                      </div>";
            }
        } // end if !$skipGroupLogic
      ?>
      <hr>

      <?php if ($clientProgramId === 2 || $clientProgramId === 3): ?>
        <p>
          <strong>Additional Admin/Reference:</strong><br>
          Virtual BIPP Intake Packet:
          <a href="https://saferpath.notesao.com/intake.php/" target="_blank">
            Click Here
          </a>
        </p>
      <?php endif; ?>
      
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('#clientModal').modal('show');
});
</script>
<?php endif; ?>


</body>
</html>