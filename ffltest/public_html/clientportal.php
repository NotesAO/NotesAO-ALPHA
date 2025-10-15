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
define('db_name', 'clinicnotepro_ffltest');
define('db_user', 'clinicnotepro_ffltest_app');
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
    // ===================== SATURDAY 9AM Men’s Virtual BIPP (id=106) =====================
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 106,
        'label'             => "Saturday Men's Parole/CPS 18 Week (9AM)",
        'day_time'          => "Saturday 9AM",
        'link'              => 'https://freeforlifegroup.com/saturday-mens-parole-cps-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 106,
        'label'             => "Saturday Men's Probation 18 Week (9AM)",
        'day_time'          => "Saturday 9AM",
        'link'              => 'https://freeforlifegroup.com/saturday-mens-probation-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 106,
        'label'             => "Saturday Men's Probation 18 Week (20 Reduced 9AM)",
        'day_time'          => "Saturday 9AM",
        'link'              => 'https://freeforlifegroup.com/saturday-mens-probation-18-week-20-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 106,
        'label'             => "Saturday Men's Probation 18 Week (25 Reduced 9AM)",
        'day_time'          => "Saturday 9AM",
        'link'              => 'https://freeforlifegroup.com/saturday-mens-probation-18-week-25-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 106,
        'label'             => "Saturday Men's Probation 27 Week (9AM)",
        'day_time'          => "Saturday 9AM",
        'link'              => 'https://freeforlifegroup.com/saturday-mens-probation-27-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 15,
        'therapy_group_id'  => 106,
        'label'             => "Saturday Men's Probation 27 Week (15 Reduced 9AM)",
        'day_time'          => "Saturday 9AM",
        'link'              => 'https://freeforlifegroup.com/saturday-mens-probation-27-week-15-reduced/'
    ],
            // ===================== SATURDAY 10 AM Men’s Virtual BIPP (id = 122) =====================
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,          // Parole / CPS
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 122,
        'label'             => "Saturday Men's Parole/CPS 18 Week (10AM)",
        'day_time'          => "Saturday 10AM",
        'link'              => 'https://freeforlifegroup.com/saturday-10am-mens-parole-cps-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,          // Probation
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 122,
        'label'             => "Saturday Men's Probation 18 Week (10AM)",
        'day_time'          => "Saturday 10AM",
        'link'              => 'https://freeforlifegroup.com/saturday-10am-mens-probation-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 122,
        'label'             => "Saturday Men's Probation 18 Week (25 Reduced 10AM)",
        'day_time'          => "Saturday 10AM",
        'link'              => 'https://freeforlifegroup.com/saturday-10am-mens-probation-18-week-25-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 122,
        'label'             => "Saturday Men's Probation 18 Week (20 Reduced 10AM)",
        'day_time'          => "Saturday 10AM",
        'link'              => 'https://freeforlifegroup.com/saturday-10am-mens-probation-18-week-20-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 122,
        'label'             => "Saturday Men's Probation 27 Week (10AM)",
        'day_time'          => "Saturday 10AM",
        'link'              => 'https://freeforlifegroup.com/saturday-10am-mens-probation-27-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 15,
        'therapy_group_id'  => 122,
        'label'             => "Saturday Men's Probation 27 Week (15 Reduced 10AM)",
        'day_time'          => "Saturday 10AM",
        'link'              => 'https://freeforlifegroup.com/saturday-10am-mens-probation-27-week-15-reduced/'
    ],

    // ===================== SUNDAY 2PM Men’s Virtual BIPP (id=108) =====================
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 108,
        'label'             => "Sunday Men's Parole/CPS 18 Week (2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-group-parole-cps-2p/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 108,
        'label'             => "Sunday Men's Probation 18 Week (2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-group-probation-2p/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 108,
        'label'             => "Sunday Men's Probation 27 Week (2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-group-hr-27-probation-2p/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 15,
        'therapy_group_id'  => 108,
        'label'             => "Sunday Men's Probation 27 Week (15 Reduced 2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-group-hr-27-probation-15-reduced-2pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 108,
        'label'             => "Sunday Men's Probation 18 Week (25 Reduced 2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-group-probation-25-reduced-2pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 108,
        'label'             => "Sunday Men's Probation 18 Week (20 Reduced 2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-group-probation-20-reduced-2pm/'
    ],
    // ===================== SUNDAY 2 : 30 PM Men’s Virtual BIPP (id = 123) =====================
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,          // Parole / CPS
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 123,
        'label'             => "Sunday Men's Parole/CPS 18 Week (2:30PM)",
        'day_time'          => "Sunday 2:30PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-230pm-group-parole-cps/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,          // Probation
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 123,
        'label'             => "Sunday Men's Probation 18 Week (2:30PM)",
        'day_time'          => "Sunday 2:30PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-230pm-group-probation/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 123,
        'label'             => "Sunday Men's Probation 18 Week (25 Reduced 2:30PM)",
        'day_time'          => "Sunday 2:30PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-230pm-group-probation-25-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 123,
        'label'             => "Sunday Men's Probation 18 Week (20 Reduced 2:30PM)",
        'day_time'          => "Sunday 2:30PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-230pm-group-probation-20-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 123,
        'label'             => "Sunday Men's Probation 27 Week (2:30PM)",
        'day_time'          => "Sunday 2:30PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-230pm-group-hr-27-probation/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 15,
        'therapy_group_id'  => 123,
        'label'             => "Sunday Men's Probation 27 Week (15 Reduced 2:30PM)",
        'day_time'          => "Sunday 2:30PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-230pm-group-hr-27-probation-15-reduced/'
    ],

    // ===================== SUNDAY 5PM Men’s Virtual BIPP (id=120) =====================
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 120,
        'label'             => "Sunday Men's Parole/CPS 18 Week (5PM)",
        'day_time'          => "Sunday 5PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-parole-cps-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 120,
        'label'             => "Sunday Men's Probation 18 Week (5PM)",
        'day_time'          => "Sunday 5PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-probation-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 120,
        'label'             => "Sunday Men's Probation 18 Week (20 Reduced 5PM)",
        'day_time'          => "Sunday 5PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-probation-18-week-20-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 120,
        'label'             => "Sunday Men's Probation 18 Week (25 Reduced 5PM)",
        'day_time'          => "Sunday 5PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-probation-18-week-25-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 120,
        'label'             => "Sunday Men's Probation 27 Week (5PM)",
        'day_time'          => "Sunday 5PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-probation-27-week/'
    ],

    // ===================== SUNDAY 2PM Women’s Virtual BIPP (id=112) =====================
    [
        'program_id'        => 3,
        'referral_type_id'  => 2,
        'gender_id'         => 3,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 112,
        'label'             => "Sunday Women's Parole/CPS 18 Week (2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-womens-parole-cps-18-week/'
    ],
    [
        'program_id'        => 3,
        'referral_type_id'  => 1,
        'gender_id'         => 3,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 112,
        'label'             => "Sunday Women's Probation 18 Week (2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-womens-probation-18-week/'
    ],
    [
        'program_id'        => 3,
        'referral_type_id'  => 1,
        'gender_id'         => 3,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 112,
        'label'             => "Sunday Women's Probation 18 Week (20 Reduced 2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-womens-probation-18-week-20-reduced/'
    ],
    [
        'program_id'        => 3,
        'referral_type_id'  => 1,
        'gender_id'         => 3,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 112,
        'label'             => "Sunday Women's Probation 18 Week (25 Reduced 2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-womens-probation-18-week-25-reduced/'
    ],
    [
        'program_id'        => 3,
        'referral_type_id'  => 1,
        'gender_id'         => 3,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 112,
        'label'             => "Sunday Women's Probation 27 Week (2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-womens-probation-27-week/'
    ],
    [
        'program_id'        => 3,
        'referral_type_id'  => 1,
        'gender_id'         => 3,
        'required_sessions' => 27,
        'fee'               => 15,
        'therapy_group_id'  => 112,
        'label'             => "Sunday Women's Probation 27 Week (15 Reduced 2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-womens-probation-27-week-15-reduced/'
    ],

    // ===================== MONDAY 7:30PM Men’s Virtual BIPP (id=104) =====================
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 104,
        'label'             => "Monday Men's Parole/CPS 18 Week (7:30PM)",
        'day_time'          => "Monday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-parole-cps-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 104,
        'label'             => "Monday Men's Probation 18 Week (7:30PM)",
        'day_time'          => "Monday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 104,
        'label'             => "Monday Men's Probation 18 Week (20 Reduced 7:30PM)",
        'day_time'          => "Monday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-18-week-20-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 104,
        'label'             => "Monday Men's Probation 18 Week (25 Reduced 7:30PM)",
        'day_time'          => "Monday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-18-week-25-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 104,
        'label'             => "Monday Men's Probation 27 Week (7:30PM)",
        'day_time'          => "Monday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-27-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 15,
        'therapy_group_id'  => 104,
        'label'             => "Monday Men's Probation 27 Week (15 Reduced 7:30PM)",
        'day_time'          => "Monday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-27-week-15-reduced/'
    ],

    // ===================== MONDAY 8PM Men’s Virtual BIPP (id=105) =====================
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 15,
        'therapy_group_id'  => 105,
        'label'             => "Monday Men's Probation 27 Week (15 Reduced 8PM)",
        'day_time'          => "Monday 8PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-27-week-15-reduced-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 105,
        'label'             => "Monday Men's Probation 27 Week (8PM)",
        'day_time'          => "Monday 8PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-27-week-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 105,
        'label'             => "Monday Men's Probation 18 Week (25 Reduced 8PM)",
        'day_time'          => "Monday 8PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-18-week-25-reduced-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 105,
        'label'             => "Monday Men's Probation 18 Week (15 SHA 8PM)",
        'day_time'          => "Monday 8PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-18-week-15-sha-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 105,
        'label'             => "Monday Men's Probation 18 Week (8PM)",
        'day_time'          => "Monday 8PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-18-week-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 105,
        'label'             => "Monday Men's Probation 18 Week (20 Reduced 8PM)",
        'day_time'          => "Monday 8PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-18-week-20-reduced-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 105,
        'label'             => "Monday Men's Parole/CPS 18 Week (8PM)",
        'day_time'          => "Monday 8PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-parole-cps-18-week-8pm/'
    ],

    // ===================== TUESDAY 7:30PM Men’s Virtual BIPP (id=109) =====================
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 109,
        'label'             => "Tuesday Men's Parole/CPS 18 Week (7:30PM)",
        'day_time'          => "Tuesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-parole-cps-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 109,
        'label'             => "Tuesday Men's Probation 18 Week (7:30PM)",
        'day_time'          => "Tuesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 109,
        'label'             => "Tuesday Men's Probation 18 Week (20 Reduced 7:30PM)",
        'day_time'          => "Tuesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-18-week-20-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 109,
        'label'             => "Tuesday Men's Probation 18 Week (25 Reduced 7:30PM)",
        'day_time'          => "Tuesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-18-week-25-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 109,
        'label'             => "Tuesday Men's Probation 27 Week (7:30PM)",
        'day_time'          => "Tuesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-27-week/'
    ],

    // ===================== TUESDAY 8PM Men’s Virtual BIPP (id=7) =====================
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 7,
        'label'             => "Tuesday Men's Probation 27 Week (8PM)",
        'day_time'          => "Tuesday 8PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-27-week-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 7,
        'label'             => "Tuesday Men's Probation 18 Week (25 Reduced 8PM)",
        'day_time'          => "Tuesday 8PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-18-week-25-reduced-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 7,
        'label'             => "Tuesday Men's Probation 18 Week (20 Reduced 8PM)",
        'day_time'          => "Tuesday 8PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-18-week-20-reduced-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 7,
        'label'             => "Tuesday Men's Probation 18 Week (8PM)",
        'day_time'          => "Tuesday 8PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-18-week-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 7,
        'label'             => "Tuesday Men's Parole/CPS 18 Week (8PM)",
        'day_time'          => "Tuesday 8PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-parole-cps-18-week-8pm/'
    ],

    // ===================== WEDNESDAY 7:30PM Men’s Virtual BIPP (id=110) =====================
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 110,
        'label'             => "Wednesday Men's Parole/CPS 18 Week (7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-parole-cps-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 110,
        'label'             => "Wednesday Men's Parole/CPS 18 Week (7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-parole-cps-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 110,
        'label'             => "Wednesday Men's Probation 18 Week (7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-probation-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 110,
        'label'             => "Wednesday Men's Probation 18 Week (20 Reduced 7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-probation-18-week-20-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 110,
        'label'             => "Wednesday Men's Probation 18 Week (25 Reduced 7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-probation-18-week-25-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 110,
        'label'             => "Wednesday Men's Probation 27 Week (7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-probation-27-week/'
    ],

    // ===================== WEDNESDAY 7:30PM Women’s Virtual BIPP (id=118) =====================
    [
        'program_id'        => 3,
        'referral_type_id'  => 2,
        'gender_id'         => 3,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 118,
        'label'             => "Wednesday Women's Parole/CPS 18 Week (7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-womens-parole-cps-18-week/'
    ],
    [
        'program_id'        => 3,
        'referral_type_id'  => 1,
        'gender_id'         => 3,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 118,
        'label'             => "Wednesday Women's Probation 18 Week (7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-womens-probation-18-week/'
    ],
    [
        'program_id'        => 3,
        'referral_type_id'  => 1,
        'gender_id'         => 3,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 118,
        'label'             => "Wednesday Women's Probation 18 Week (20 Reduced 7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-womens-probation-18-week-20-reduced/'
    ],
    [
        'program_id'        => 3,
        'referral_type_id'  => 1,
        'gender_id'         => 3,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 118,
        'label'             => "Wednesday Women's Probation 18 Week (25 Reduced 7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-womens-probation-18-week-25-reduced/'
    ],
    [
        'program_id'        => 3,
        'referral_type_id'  => 1,
        'gender_id'         => 3,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 118,
        'label'             => "Wednesday Women's Probation 27 Week (7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-womens-probation-27-week/'
    ],
    // ===================== IN-PERSON MEN’S BIPP =====================

    // Saturday Men’s BIPP 9AM in-person (id=103)
    [
        'program_id'        => 2,  // Men’s BIPP
        'referral_type_id'  => 1,  // e.g. Probation
        'gender_id'         => 2,  // male
        'required_sessions' => 18, // or 27 if needed
        'fee'               => 30, // or 20, etc. as needed
        'therapy_group_id'  => 103,
        // This label is never a link, but we keep it for "Your Assigned Group:"
        'label'             => "Saturday Men's BIPP 9AM (In-Person)",
        // short day_time if you want
        'day_time'          => "Saturday 9AM (In-Person)",
        // no real link, but you can set it empty or "#"
        'link'              => ''
    ],

    // Sunday Men’s BIPP 5PM in-person (id=107)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 107,
        'label'             => "Sunday Men's BIPP 5PM (In-Person)",
        'day_time'          => "Sunday 5PM (In-Person)",
        'link'              => ''
    ],

    // Tuesday Men’s BIPP 7PM in-person (id=117)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 117,
        'label'             => "Tuesday Men's BIPP 7PM (In-Person)",
        'day_time'          => "Tuesday 7PM (In-Person)",
        'link'              => ''
    ],

    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 117,
        'label'             => "Tuesday Men's BIPP 7PM (In-Person)",
        'day_time'          => "Tuesday 7PM (In-Person)",
        'link'              => ''
    ],

    // ===================== WEDNESDAY 8PM Men’s Virtual BIPP (id=121) =====================
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 121,
        'label'             => "Wednesday Men's Parole/CPS 18 Week (8PM)",
        'day_time'          => "Wednesday 8PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-parole-cps-18-week-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 121,
        'label'             => "Wednesday Men's Probation 18 Week (8PM)",
        'day_time'          => "Wednesday 8PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-probation-18-week-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 121,
        'label'             => "Wednesday Men's Probation 18 Week (20 Reduced 8PM)",
        'day_time'          => "Wednesday 8PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-probation-18-week-20-reduced-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 25,
        'therapy_group_id'  => 121,
        'label'             => "Wednesday Men's Probation 18 Week (25 Reduced 8PM)",
        'day_time'          => "Wednesday 8PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-probation-18-week-25-reduced-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 121,
        'label'             => "Wednesday Men's Probation 27 Week (8PM)",
        'day_time'          => "Wednesday 8PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-probation-27-week-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 15,
        'therapy_group_id'  => 121,
        'label'             => "Wednesday Men's Probation 27 Week (15 Reduced 8PM)",
        'day_time'          => "Wednesday 8PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-probation-27-week-15-reduced-8pm/'
    ],
    // ===================== ANGER CONTROL (program_id=4) =====================

    // Saturday Anger Control (id=114) - 9AM
    [
        'program_id' => 4,
        'referral_type_id' => 0,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 0,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 0,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 1,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 1,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 1,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 2,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 2,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 2,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 3,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 3,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 3,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 4,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 4,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 4,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 5,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 5,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 5,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 6,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 6,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 6,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 114,
        'label' => "Saturday Anger Control (9AM)",
        'day_time' => "Saturday 9AM",
        'link' => "https://freeforlifegroup.com/saturday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 0,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 0,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 0,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 1,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 1,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 1,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 2,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 2,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 2,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 3,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 3,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 3,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 4,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 4,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 4,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 5,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 5,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 5,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 6,
        'gender_id' => 3,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 6,
        'gender_id' => 1,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    [
        'program_id' => 4,
        'referral_type_id' => 6,
        'gender_id' => 2,
        'required_sessions' => 18,
        'fee' => 15,
        'therapy_group_id' => 119,
        'label' => "Sunday Anger Control (9:30AM)",
        'day_time' => "Sunday 9:30AM",
        'link' => "https://freeforlifegroup.com/sunday-anger-control-parole-18-week/"
    ],
    // ===================== END ANGER CONTROL =====================       

    [
        'program_id'        => 2,
        'referral_type_id'  => 5,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 106,
        'label'             => "Saturday Men's Attorney 18 Week (9AM)",
        'day_time'          => "Saturday 9AM",
        'link'              => 'https://freeforlifegroup.com/saturday-mens-probation-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 5,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 108,
        'label'             => "Sunday Men's Attorney 18 Week (2PM)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-group-probation-2p/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 5,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 120,
        'label'             => "Sunday Men's Attorney 18 Week (5PM)",
        'day_time'          => "Sunday 5PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-probation-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 5,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 104,
        'label'             => "Monday Men's Attorney 18 Week (7:30PM)",
        'day_time'          => "Monday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 5,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 105,
        'label'             => "Monday Men's Attorney 18 Week (8PM)",
        'day_time'          => "Monday 8PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-probation-18-week-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 5,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 109,
        'label'             => "Tuesday Men's Attorney 18 Week (7:30PM)",
        'day_time'          => "Tuesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 5,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 7,
        'label'             => "Tuesday Men's Attorney 18 Week (8PM)",
        'day_time'          => "Tuesday 8PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-18-week-8pm/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 5,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 110,
        'label'             => "Wednesday Men's Attorney 18 Week (7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-probation-18-week/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 5,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 121,
        'label'             => "Wednesday Men's Attorney 18 Week (8PM)",
        'day_time'          => "Wednesday 8PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-probation-18-week-8pm/'
    ],

        // ===================== PROBATION $10 (route to Parole/CPS 10-reduced) =====================
    // Monday 7:30PM (id=104)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,   // Probation
        'gender_id'         => 2,   // Male
        'required_sessions' => 18,
        'fee'               => 10,
        'therapy_group_id'  => 104,
        'label'             => "Monday Men's Probation 18 Week (10 Reduced 7:30PM)",
        'day_time'          => "Monday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-parole-cps-18-week-10-reduced/'
    ],

    // Monday 8PM (id=105)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 10,
        'therapy_group_id'  => 105,
        'label'             => "Monday Men's Probation 18 Week (10 Reduced 8PM)",
        'day_time'          => "Monday 8PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-parole-cps-18-week-10-reduced-8pm/'
    ],

    // Saturday 9AM virtual (ids=106 virtual, 103 in-person share same link)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 10,
        'therapy_group_id'  => 106,
        'label'             => "Saturday Men's Probation 18 Week (10 Reduced 9AM Virtual)",
        'day_time'          => "Saturday 9AM",
        'link'              => 'https://freeforlifegroup.com/saturday-mens-parole-cps-18-week-10-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 10,
        'therapy_group_id'  => 103,
        'label'             => "Saturday Men's Probation 18 Week (10 Reduced 9AM In-Person)",
        'day_time'          => "Saturday 9AM",
        'link'              => 'https://freeforlifegroup.com/saturday-mens-parole-cps-18-week-10-reduced/'
    ],

    // Sunday 5PM virtual (ids=120 virtual, 107 in-person share same link)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 10,
        'therapy_group_id'  => 120,
        'label'             => "Sunday Men's Probation 18 Week (10 Reduced 5PM Virtual)",
        'day_time'          => "Sunday 5PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-parole-cps-18-week-10-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 10,
        'therapy_group_id'  => 107,
        'label'             => "Sunday Men's Probation 18 Week (10 Reduced 5PM In-Person)",
        'day_time'          => "Sunday 5PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-parole-cps-18-week-10-reduced/'
    ],

    // Sunday 2PM virtual (id=108)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 10,
        'therapy_group_id'  => 108,
        'label'             => "Sunday Men's Probation 18 Week (10 Reduced 2PM Virtual)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-group-parole-cps-10-reduced-2pm/'
    ],

    // Tuesday 7:30PM virtual (id=109)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 10,
        'therapy_group_id'  => 109,
        'label'             => "Tuesday Men's Probation 18 Week (10 Reduced 7:30PM)",
        'day_time'          => "Tuesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-parole-cps-18-week-10-reduced/'
    ],

    // Tuesday 8PM virtual (id=7)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 10,
        'therapy_group_id'  => 7,
        'label'             => "Tuesday Men's Probation 18 Week (10 Reduced 8PM)",
        'day_time'          => "Tuesday 8PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-parole-cps-18-week-10-reduced-8pm/'
    ],

    // Wednesday 7:30PM virtual (id=110)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 10,
        'therapy_group_id'  => 110,
        'label'             => "Wednesday Men's Probation 18 Week (10 Reduced 7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-parole-cps-18-week-10-reduced/'
    ],

    // Wednesday 8PM virtual (id=121)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 10,
        'therapy_group_id'  => 121,
        'label'             => "Wednesday Men's Probation 18 Week (10 Reduced 8PM)",
        'day_time'          => "Wednesday 8PM",
        'link'              => 'https://freeforlifegroup.com/?page_id=25277'
    ],

        // ===================== PROBATION $10 — 27 WEEK (route to Parole/CPS 10-reduced) =====================
    // Monday 7:30PM (id=104)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,   // Probation
        'gender_id'         => 2,   // Male
        'required_sessions' => 27,
        'fee'               => 10,
        'therapy_group_id'  => 104,
        'label'             => "Monday Men's Probation 27 Week (10 Reduced 7:30PM)",
        'day_time'          => "Monday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-parole-cps-18-week-10-reduced/'
    ],

    // Monday 8PM (id=105)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 10,
        'therapy_group_id'  => 105,
        'label'             => "Monday Men's Probation 27 Week (10 Reduced 8PM)",
        'day_time'          => "Monday 8PM",
        'link'              => 'https://freeforlifegroup.com/monday-mens-parole-cps-18-week-10-reduced-8pm/'
    ],

    // Saturday 9AM virtual/in-person share same link (ids=106 virtual, 103 in-person)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 10,
        'therapy_group_id'  => 106,
        'label'             => "Saturday Men's Probation 27 Week (10 Reduced 9AM Virtual)",
        'day_time'          => "Saturday 9AM",
        'link'              => 'https://freeforlifegroup.com/saturday-mens-parole-cps-18-week-10-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 10,
        'therapy_group_id'  => 103,
        'label'             => "Saturday Men's Probation 27 Week (10 Reduced 9AM In-Person)",
        'day_time'          => "Saturday 9AM",
        'link'              => 'https://freeforlifegroup.com/saturday-mens-parole-cps-18-week-10-reduced/'
    ],

    // Sunday 5PM virtual/in-person share same link (ids=120 virtual, 107 in-person)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 10,
        'therapy_group_id'  => 120,
        'label'             => "Sunday Men's Probation 27 Week (10 Reduced 5PM Virtual)",
        'day_time'          => "Sunday 5PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-parole-cps-18-week-10-reduced/'
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 10,
        'therapy_group_id'  => 107,
        'label'             => "Sunday Men's Probation 27 Week (10 Reduced 5PM In-Person)",
        'day_time'          => "Sunday 5PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-parole-cps-18-week-10-reduced/'
    ],

    // Sunday 2PM virtual (id=108)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 10,
        'therapy_group_id'  => 108,
        'label'             => "Sunday Men's Probation 27 Week (10 Reduced 2PM Virtual)",
        'day_time'          => "Sunday 2PM",
        'link'              => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-group-parole-cps-10-reduced-2pm/'
    ],

    // Tuesday 7:30PM virtual (id=109)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 10,
        'therapy_group_id'  => 109,
        'label'             => "Tuesday Men's Probation 27 Week (10 Reduced 7:30PM)",
        'day_time'          => "Tuesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-parole-cps-18-week-10-reduced/'
    ],

    // Tuesday 8PM virtual (id=7)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 10,
        'therapy_group_id'  => 7,
        'label'             => "Tuesday Men's Probation 27 Week (10 Reduced 8PM)",
        'day_time'          => "Tuesday 8PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-parole-cps-18-week-10-reduced-8pm/'
    ],

    // Wednesday 7:30PM virtual (id=110)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 10,
        'therapy_group_id'  => 110,
        'label'             => "Wednesday Men's Probation 27 Week (10 Reduced 7:30PM)",
        'day_time'          => "Wednesday 7:30PM",
        'link'              => 'https://freeforlifegroup.com/wednesday-mens-parole-cps-18-week-10-reduced/'
    ],

    // Wednesday 8PM virtual (id=121)
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 10,
        'therapy_group_id'  => 121,
        'label'             => "Wednesday Men's Probation 27 Week (10 Reduced 8PM)",
        'day_time'          => "Wednesday 8PM",
        'link'              => 'https://freeforlifegroup.com/?page_id=25277'
    ],


    

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
    <a href="https://freeforlifegroup.com">
        <img alt="Free for Life Group" src="ffllogo.png" class="img-fluid mb-3">
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

                if ($attends['sunday']) {
                    $groupDisplay[] = "<a href='https://freeforlifegroup.com/t4c-sunday-virtual-group/' target='_blank'>
                                          Sunday Virtual T4C Group
                                       </a>";
                }
                if ($attends['monday'] || $attends['wednesday']) {
                    $groupDisplay[] = "<a href='https://freeforlifegroup.com/t4c-monday-wednesday-virtual-group/' target='_blank'>
                                          Monday & Wednesday Virtual T4C Group
                                       </a>";
                }

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
          <a href="https://freeforlifegroup.com/virtual-bipp-intake/" target="_blank">
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