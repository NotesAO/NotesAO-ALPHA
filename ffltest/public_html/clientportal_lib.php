<?php
// -----------------------------------------------------------------------------
// clientportal_lib.php  (LIBRARY ONLY)
// -----------------------------------------------------------------------------
// PURPOSE:
//   Given a client id, return the "regular group" URL exactly like the client
//   portal would. NO output, NO session_start, NO constant definitions, NO
//   DB connect/close. Uses the provided $con (mysqli) from the caller.
//
// HOW TO USE:
//   1) Paste your existing $groupData mapping into the section below.
//      Keep each entry structure like:
//         [
//           'program_id'        => 2,
//           'referral_type_id'  => 2,
//           'gender_id'         => 2,           // 1=male, 2=female, 3=any (example)
//           'required_sessions' => 18,
//           'fee'               => 15,
//           'therapy_group_id'  => 106,         // exact DB group id (if applicable)
//           'label'             => "Saturday Men's Parole/CPS 18 Week (9AM)",
//           'day_time'          => "Saturday 9AM",
//           'link'              => "https://..."
//         ]
//   2) Include this file from pages that need {{group_link}}:
//          require_once 'clientportal_lib.php';
//   3) The reminders page will call:
//          notesao_regular_group_link($con, $clientId)
// -----------------------------------------------------------------------------

declare(strict_types=1);

/* ============================================================================
 * 1) GROUP DATA (PASTE YOUR MAPPING HERE)
 * ========================================================================== */
$groupData = [
    [
        'program_id'        => 1,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 30,
        'fee'               => 20,
        'therapy_group_id'  => 3,
        'label'             => 'Fort Worth — Thinking for a Change (In-Person)',
        'day_time'          => 'Mon 10:00 AM; Mon 7:00 PM; Wed 7:00 PM; Fri 10:00 AM',
        'link'              => 'Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX 76102]',
    ],
    [
        'program_id'        => 1,
        'referral_type_id'  => 1,
        'gender_id'         => 3,
        'required_sessions' => 30,
        'fee'               => 20,
        'therapy_group_id'  => 3,
        'label'             => 'Fort Worth — Thinking for a Change (In-Person)',
        'day_time'          => 'Mon 10:00 AM; Mon 7:00 PM; Wed 7:00 PM; Fri 10:00 AM',
        'link'              => 'Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]',
    ],
    [
        'program_id'        => 1,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 30,
        'fee'               => 20,
        'therapy_group_id'  => 6,
        'label'             => 'Arlington — Thinking for a Change (In-Person)',
        'day_time'          => 'Thu 7:00 PM; Sun 2:30 PM; Sun 5:00 PM',
        'link'              => 'Arlington Office — [6850 Manhattan Blvd., Fort Worth, TX, 76120]',
    ],
    [
        'program_id'        => 1,
        'referral_type_id'  => 1,
        'gender_id'         => 3,
        'required_sessions' => 30,
        'fee'               => 20,
        'therapy_group_id'  => 6,
        'label'             => 'Arlington — Thinking for a Change (In-Person)',
        'day_time'          => 'Thu 7:00 PM; Sun 2:30 PM; Sun 5:00 PM',
        'link'              => 'Arlington Office — [6850 Manhattan Blvd., Fort Worth, TX, 76120]',
    ],
    [
        'program_id'        => 1,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 30,
        'fee'               => 20,
        'therapy_group_id'  => 6,
        'label'             => 'Arlington — Thinking for a Change (In-Person)',
        'day_time'          => 'Thu 7:00 PM; Sun 2:30 PM; Sun 5:00 PM',
        'link'              => 'Arlington Office — [6850 Manhattan Blvd., Fort Worth, TX, 76120]',
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 20,
        'therapy_group_id'  => 7,
        'label'             => "Tuesday Men's Probation 27 Week (8PM)",
        'day_time'          => "Tuesday 8PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-27-week-8pm/',
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 27,
        'fee'               => 15,
        'therapy_group_id'  => 7,
        'label'             => "Tuesday Men's Parole/CPS 27 Week (8PM)",
        'day_time'          => "Tuesday 8PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-parole-cps-18-week-8pm/',
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 15,
        'therapy_group_id'  => 7,
        'label'             => "Tuesday Men's Probation 18 Week (15 Reduced 8PM)",
        'day_time'          => "Tuesday 8PM",
        'link'              => 'https://freeforlifegroup.com/tuesday-mens-probation-27-week-15-reduced-8pm/',
    ],
    [
        'program_id'        => 2,
        'referral_type_id'  => 1,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 30,
        'therapy_group_id'  => 103,
        'label'             => "Saturday Men's BIPP 9AM (In-Person)",
        // short day_time if you want
        'day_time'          => "Saturday 9AM (In-Person)",
        // no real link, but you can set it empty or "#"
        'link'              => 'Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]',
    ],

    
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
        'referral_type_id'  => 2,
        'gender_id'         => 2,
        'required_sessions' => 18,
        'fee'               => 20,
        'therapy_group_id'  => 108,
        'label'             => "Sunday Men's ",
        'day_time'          => "Sunday 2PM",
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
    [
        'program_id'        => 2,
        'referral_type_id'  => 5,
        'gender_id'         => 2,
        'required_sessions' => 24,
        'fee'               => 20,
        'therapy_group_id'  => 110,
        'label'             => "Wednesday Men's 24 Week (7:30PM)",
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
        'link'              => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
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

    // ===================== THERAPY GROUP 103 — remaining combos =====================
    [
        'program_id' => 2, 'referral_type_id' => 2, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 103,
        'label' => "Saturday Men's BIPP 9AM (In-Person)", 'day_time' => "Saturday 9AM (In-Person)", 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 103,
        'label' => "Saturday Men's BIPP 9AM (In-Person)", 'day_time' => "Saturday 9AM (In-Person)", 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 20, 'therapy_group_id' => 103,
        'label' => "Saturday Men's BIPP 9AM (In-Person)", 'day_time' => "Saturday 9AM (In-Person)", 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 25, 'therapy_group_id' => 103,
        'label' => "Saturday Men's BIPP 9AM (In-Person)", 'day_time' => "Saturday 9AM (In-Person)", 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 0, 'therapy_group_id' => 103,
        'label' => "Saturday Men's BIPP 9AM (In-Person)", 'day_time' => "Saturday 9AM (In-Person)", 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],

    // ===================== THERAPY GROUP 104 =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 104,
        'label' => "Monday Men's Probation 27 Week (7:30PM)", 'day_time' => "Monday 7:30PM", 'link' => 'https://freeforlifegroup.com/monday-mens-probation-27-week/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 20, 'therapy_group_id' => 104,
        'label' => "Monday Men's Probation 18 Week (20 Reduced 7:30PM)", 'day_time' => "Monday 7:30PM", 'link' => 'https://freeforlifegroup.com/monday-mens-probation-18-week-20-reduced/',
    ],

    // ===================== THERAPY GROUP 105 =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 105,
        'label' => "Monday Men's Probation 27 Week (8PM)", 'day_time' => 'Monday 8:00PM', 'link' => 'https://freeforlifegroup.com/monday-mens-probation-27-week-8pm/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 15, 'therapy_group_id' => 105,
        'label' => "Monday Men's Probation 27 Week (8PM)", 'day_time' => 'Monday 8:00PM', 'link' => 'https://freeforlifegroup.com/monday-mens-probation-27-week-15-reduced-8pm/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 2, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 0, 'therapy_group_id' => 105,
        'label' => "Monday Men's Parole/CPS 27 Week (8PM)", 'day_time' => 'Monday 8:00PM', 'link' => 'https://freeforlifegroup.com/monday-mens-probation-18-week-8pm/',
    ],

    // ===================== THERAPY GROUP 106 =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 106,
        'label' => "Saturday Men's Probation 27 Week (9AM)", 'day_time' => 'Saturday 9AM', 'link' => 'https://freeforlifegroup.com/saturday-mens-probation-27-week/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 10, 'therapy_group_id' => 106,
        'label' => "Saturday Men's Probation 27 Week (10 Reduced 9AM)", 'day_time' => 'Saturday 9AM', 'link' => 'https://freeforlifegroup.com/saturday-mens-parole-cps-18-week-10-reduced/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 0, 'therapy_group_id' => 106,
        'label' => "Saturday Men's 18 Week (9AM)", 'day_time' => 'Saturday 9AM', 'link' => 'https://freeforlifegroup.com/saturday-mens-probation-27-week/',
    ],

    // ===================== THERAPY GROUP 107 =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 107,
        'label' => "Sunday Men's Probation 27 Week (In-Person)", 'day_time' => 'Sunday 5PM (In-Person)', 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 30, 'therapy_group_id' => 107,
        'label' => "Sunday Men's Probation 27 Week (In-Person)", 'day_time' => 'Sunday 5PM (In-Person)', 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 2, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 107,
        'label' => "Sunday Men's Parole/CPS 27 Week (In-Person)", 'day_time' => 'Sunday 5PM (In-Person)', 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],

    // ===================== THERAPY GROUP 108 =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 108,
        'label' => "Sunday Men's Probation 27 Week (2PM)", 'day_time' => 'Sunday 2PM', 'link' => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-group-hr-27-probation-2p/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 108,
        'label' => "Sunday Men's Probation 18 Week (15 Reduced 2PM)", 'day_time' => 'Sunday 2PM', 'link' => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-group-hr-27-probation-15-reduced-2pm/',
    ],

    // ===================== THERAPY GROUP 109 (deduped) =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 109,
        'label' => "Tuesday Men's Probation 27 Week (7:30PM)", 'day_time' => 'Tuesday 7:30PM', 'link' => 'https://freeforlifegroup.com/tuesday-mens-probation-27-week/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 5, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 109,
        'label' => "Sunday Men's 27 Week (7:30PM)", 'day_time' => 'Tuesday 7:30PM', 'link' => 'https://freeforlifegroup.com/tuesday-mens-probation-27-week/',
    ],

    // ===================== THERAPY GROUP 110 (deduped) =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 110,
        'label' => "Wednesday Men's Probation 27 Week (7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-mens-probation-27-week/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 15, 'therapy_group_id' => 110,
        'label' => "Wednesday Men's Probation 27 Week (15 Reduced 7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-mens-parole-cps-18-week/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 25, 'therapy_group_id' => 110,
        'label' => "Wednesday Men's Probation 18 Week (25 Reduced 7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-mens-probation-18-week-25-reduced/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 2, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 15, 'therapy_group_id' => 110,
        'label' => "Wednesday Men's Parole/CPS 27 Week (7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-mens-parole-cps-18-week/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 4, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 110,
        'label' => "Wednesday Men's 18 Week (7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-mens-parole-cps-18-week/',
    ],

    // ===================== THERAPY GROUP 112 =====================
    [
        'program_id' => 3, 'referral_type_id' => 1, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 30, 'therapy_group_id' => 112,
        'label' => "Sunday Women's Probation 18 Week (2PM)", 'day_time' => 'Sunday 2PM', 'link' => 'https://freeforlifegroup.com/sunday-womens-probation-18-week/',
    ],
    [
        'program_id' => 3, 'referral_type_id' => 2, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 112,
        'label' => "Sunday Women's Parole 18 Week (15 Reduced 2PM)", 'day_time' => 'Sunday 2PM', 'link' => 'https://freeforlifegroup.com/sunday-womens-parole-cps-18-week/',
    ],
    [
        'program_id' => 3, 'referral_type_id' => 5, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 30, 'therapy_group_id' => 112,
        'label' => "Sunday Women's 18 Week (2PM)", 'day_time' => 'Sunday 2PM', 'link' => 'https://freeforlifegroup.com/sunday-womens-probation-18-week/',
    ],
    [
        'program_id' => 3, 'referral_type_id' => 1, 'gender_id' => 3, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 112,
        'label' => "Sunday Women's Probation 27 Week (2PM)", 'day_time' => 'Sunday 2PM', 'link' => 'https://freeforlifegroup.com/sunday-womens-probation-27-week/',
    ],

    // ===================== THERAPY GROUP 114 =====================
    [
        'program_id' => 4, 'referral_type_id' => 2, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 114,
        'label' => 'Saturday Anger Control (9AM)', 'day_time' => 'Saturday 9AM', 'link' => 'https://freeforlifegroup.com/saturday-anger-control-parole-18-week/',
    ],
    [
        'program_id' => 4, 'referral_type_id' => 2, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 114,
        'label' => 'Saturday Anger Control (9AM)', 'day_time' => 'Saturday 9AM', 'link' => 'https://freeforlifegroup.com/saturday-anger-control-parole-18-week/',
    ],
    [
        'program_id' => 4, 'referral_type_id' => 6, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 114,
        'label' => 'Saturday Anger Control (9AM)', 'day_time' => 'Saturday 9AM', 'link' => 'https://freeforlifegroup.com/saturday-anger-control-parole-18-week/',
    ],
    [
        'program_id' => 4, 'referral_type_id' => 3, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 114,
        'label' => 'Saturday Anger Control (9AM)', 'day_time' => 'Saturday 9AM', 'link' => 'https://freeforlifegroup.com/saturday-anger-control-parole-18-week/',
    ],
    [
        'program_id' => 4, 'referral_type_id' => 5, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 114,
        'label' => 'Saturday Anger Control (9AM)', 'day_time' => 'Saturday 9AM', 'link' => 'https://freeforlifegroup.com/saturday-anger-control-parole-18-week/',
    ],
    [
        'program_id' => 4, 'referral_type_id' => 1, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 114,
        'label' => 'Saturday Anger Control (9AM)', 'day_time' => 'Saturday 9AM', 'link' => 'https://freeforlifegroup.com/saturday-anger-control-parole-18-week/',
    ],
    [
        'program_id' => 4, 'referral_type_id' => 3, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 114,
        'label' => 'Saturday Anger Control (9AM)', 'day_time' => 'Saturday 9AM', 'link' => 'https://freeforlifegroup.com/saturday-anger-control-parole-18-week/',
    ],

    // ===================== THERAPY GROUP 116 =====================
    [
        'program_id' => 1, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 30, 'fee' => 20, 'therapy_group_id' => 116,
        'label' => 'Virtual Thinking for a Change', 'day_time' => '2xSunday or Monday/Wednesday', 'link' => "Sunday: https://freeforlifegroup.com/t4c-sunday-virtual-group/ or Monday/Wednesday: https://freeforlifegroup.com/t4c-monday-wednesday-virtual-group/",
    ],
    [
        'program_id' => 1, 'referral_type_id' => 1, 'gender_id' => 3, 'required_sessions' => 30, 'fee' => 0, 'therapy_group_id' => 116,
        'label' => 'Virtual Thinking for a Change', 'day_time' => '2xSunday or Monday/Wednesday', 'link' => "Sunday: https://freeforlifegroup.com/t4c-sunday-virtual-group/ or Monday/Wednesday: https://freeforlifegroup.com/t4c-monday-wednesday-virtual-group/",
    ],

    // ===================== THERAPY GROUP 117 =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 30, 'therapy_group_id' => 117,
        'label' => "Tuesday Men's BIPP 7PM (In-Person)", 'day_time' => 'Tuesday 7PM', 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 2, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 117,
        'label' => "Tuesday Men's BIPP 7PM (In-Person)", 'day_time' => 'Tuesday 7PM', 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 117,
        'label' => "Tuesday Men's BIPP 7PM (In-Person)", 'day_time' => 'Tuesday 7PM', 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 3, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 30, 'therapy_group_id' => 117,
        'label' => "Tuesday Men's BIPP 7PM (In-Person)", 'day_time' => 'Tuesday 7PM', 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 4, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 117,
        'label' => "Tuesday Men's BIPP 7PM (In-Person)", 'day_time' => 'Tuesday 7PM', 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 25, 'therapy_group_id' => 117,
        'label' => "Tuesday Men's BIPP 7PM (In-Person)", 'day_time' => 'Tuesday 7PM', 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 10, 'therapy_group_id' => 117,
        'label' => "Tuesday Men's BIPP 7PM (In-Person)", 'day_time' => 'Tuesday 7PM', 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 0, 'therapy_group_id' => 117,
        'label' => "Tuesday Men's BIPP 7PM (In-Person)", 'day_time' => 'Tuesday 7PM', 'link' => "Fort Worth Office — [1100 E. Lancaster Ave., Fort Worth, TX, 76102]",
    ],

    // ===================== THERAPY GROUP 118 =====================
    [
        'program_id' => 3, 'referral_type_id' => 1, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 30, 'therapy_group_id' => 118,
        'label' => "Wednesday Women's Probation 18 Week (7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-womens-probation-18-week/',
    ],
    [
        'program_id' => 3, 'referral_type_id' => 1, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 25, 'therapy_group_id' => 118,
        'label' => "Wednesday Women's Probation 18 Week (25 Reduced 7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-womens-probation-18-week-25-reduced/',
    ],
    [
        'program_id' => 3, 'referral_type_id' => 1, 'gender_id' => 3, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 118,
        'label' => "Wednesday Women's Probation 27 Week (20 Reduced 7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-womens-probation-27-week/',
    ],
    [
        'program_id' => 3, 'referral_type_id' => 2, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 118,
        'label' => "Wednesday Women's Parole/CPS 18 Week (7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-womens-parole-cps-18-week/',
    ],
    [
        'program_id' => 3, 'referral_type_id' => 3, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 118,
        'label' => "Wednesday Women's 18 Week (7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-womens-parole-cps-18-week/',
    ],
    [
        'program_id' => 3, 'referral_type_id' => 3, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 30, 'therapy_group_id' => 118,
        'label' => "Wednesday Women's 18 Week (7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-womens-probation-18-week/',
    ],
    [
        'program_id' => 3, 'referral_type_id' => 5, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 25, 'therapy_group_id' => 118,
        'label' => "Wednesday Women's 18 Week (25 Reduced 7:30PM)", 'day_time' => 'Wednesday 7:30PM', 'link' => 'https://freeforlifegroup.com/wednesday-womens-probation-18-week-25-reduced/',
    ],

    // ===================== THERAPY GROUP 119 =====================
    [
        'program_id' => 4, 'referral_type_id' => 2, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 119,
        'label' => 'Sunday Anger Control (9:30AM)', 'day_time' => 'Sunday 9:30AM', 'link' => 'https://freeforlifegroup.com/sunday-anger-control-parole-18-week/',
    ],
    [
        'program_id' => 4, 'referral_type_id' => 2, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 119,
        'label' => 'Sunday Anger Control (9:30AM)', 'day_time' => 'Sunday 9:30AM', 'link' => 'https://freeforlifegroup.com/sunday-anger-control-parole-18-week/',
    ],
    [
        'program_id' => 4, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 119,
        'label' => 'Sunday Anger Control (9:30AM)', 'day_time' => 'Sunday 9:30AM', 'link' => 'https://freeforlifegroup.com/sunday-anger-control-parole-18-week/',
    ],
    [
        'program_id' => 4, 'referral_type_id' => 1, 'gender_id' => 3, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 119,
        'label' => 'Sunday Anger Control (9:30AM)', 'day_time' => 'Sunday 9:30AM', 'link' => 'https://freeforlifegroup.com/sunday-anger-control-parole-18-week/',
    ],
    [
        'program_id' => 4, 'referral_type_id' => 3, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 119,
        'label' => 'Sunday Anger Control (9:30AM)', 'day_time' => 'Sunday 9:30AM', 'link' => 'https://freeforlifegroup.com/sunday-anger-control-parole-18-week/',
    ],
    [
        'program_id' => 4, 'referral_type_id' => 6, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 119,
        'label' => 'Sunday Anger Control (9:30AM)', 'day_time' => 'Sunday 9:30AM', 'link' => 'https://freeforlifegroup.com/sunday-anger-control-parole-18-week/',
    ],

    // ===================== THERAPY GROUP 120 =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 120,
        'label' => "Sunday Men's Probation 27 Week (5PM)", 'day_time' => 'Sunday 5PM', 'link' => 'https://freeforlifegroup.com/sunday-mens-probation-27-week/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 5, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 30, 'therapy_group_id' => 120,
        'label' => "Sunday Men's 27 Week (5PM)", 'day_time' => 'Sunday 5PM', 'link' => 'https://freeforlifegroup.com/sunday-mens-probation-18-week/',
    ],

    // ===================== THERAPY GROUP 121 =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 121,
        'label' => "Wednesday Men's Probation 27 Week (8PM)", 'day_time' => 'Wednesday 8PM', 'link' => 'https://freeforlifegroup.com/wednesday-mens-probation-27-week-8pm/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 2, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 15, 'therapy_group_id' => 121,
        'label' => "Wednesday Men's Parole/CPS 27 Week (8PM)", 'day_time' => 'Wednesday 8PM', 'link' => 'https://freeforlifegroup.com/wednesday-mens-probation-27-week-15-reduced-8pm/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 6, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 30, 'therapy_group_id' => 121,
        'label' => "Wednesday Men's 27 Week (8PM)", 'day_time' => 'Wednesday 8PM', 'link' => 'https://freeforlifegroup.com/wednesday-mens-probation-18-week-8pm/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 15, 'therapy_group_id' => 121,
        'label' => "Wednesday Men's Probation 27 Week (15 Reduced 8PM)", 'day_time' => 'Wednesday 8PM', 'link' => 'https://freeforlifegroup.com/wednesday-mens-probation-27-week-15-reduced-8pm/',
    ],

    // ===================== THERAPY GROUP 122 (deduped) =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 122,
        'label' => "Saturday Men's Probation 27 Week (10AM)", 'day_time' => 'Saturday 10AM', 'link' => 'https://freeforlifegroup.com/saturday-10am-mens-probation-27-week/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 25, 'therapy_group_id' => 122,
        'label' => "Saturday Men's Probation 27 Week (25 Reduced 10AM)", 'day_time' => 'Saturday 10AM', 'link' => 'https://freeforlifegroup.com/saturday-10am-mens-probation-18-week-25-reduced/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 6, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 30, 'therapy_group_id' => 122,
        'label' => "Saturday Men's 27 Week (10AM)", 'day_time' => 'Saturday 10AM', 'link' => 'https://freeforlifegroup.com/saturday-10am-mens-probation-18-week/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 30, 'therapy_group_id' => 122,
        'label' => "Saturday Men's 27 Probation Week (10AM)", 'day_time' => 'Saturday 10AM', 'link' => 'https://freeforlifegroup.com/saturday-10am-mens-probation-18-week/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 2, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 122,
        'label' => "Saturday Men's 27 Parole/CPS Week (10AM)", 'day_time' => 'Saturday 10AM', 'link' => 'https://freeforlifegroup.com/saturday-10am-mens-parole-cps-18-week/',
    ],

    // ===================== THERAPY GROUP 123 =====================
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 27, 'fee' => 20, 'therapy_group_id' => 123,
        'label' => "Sunday Men's Probation 27 Week (2:30PM)", 'day_time' => 'Sunday 2:30PM', 'link' => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-230pm-group-hr-27-probation/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 15, 'therapy_group_id' => 123,
        'label' => "Sunday Men's Probation 18 Week (15 Reduced 2:30PM)", 'day_time' => 'Sunday 2:30PM', 'link' => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-230pm-group-parole-cps/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 1, 'gender_id' => 2, 'required_sessions' => 25, 'fee' => 15, 'therapy_group_id' => 123,
        'label' => "Sunday Men's Probation (15 Reduced 2:30PM)", 'day_time' => 'Sunday 2:30PM', 'link' => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-230pm-group-hr-27-probation-15-reduced/',
    ],
    [
        'program_id' => 2, 'referral_type_id' => 2, 'gender_id' => 2, 'required_sessions' => 18, 'fee' => 20, 'therapy_group_id' => 123,
        'label' => "Sunday Men's Probation (20 Reduced 2:30PM)", 'day_time' => 'Sunday 2:30PM', 'link' => 'https://freeforlifegroup.com/sunday-mens-virtual-bipp-230pm-group-probation-20-reduced/',
    ],


];

/* ============================================================================
 * 2) HELPERS (pure functions; no side effects)
 * ========================================================================== */

/**
 * Default client portal URL when no mapping can be resolved.
 */
function clientportal_default_url(): string {
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : 'https://ffltest.notesao.com';
    return rtrim($base, '/') . '/client.php';
}

/**
 * Some portals normalize referral types (e.g., aliases collapse to a canonical id).
 * If you have a specific mapping in your original portal, apply it here.
 * Current default: identity.
 */
function normalizeReferralId(int $refId): int {
    // TODO: If your portal maps multiple IDs to a canonical value, apply here.
    // Example:
    // $map = [ 10 => 2, 11 => 2, 12 => 2 ]; // map 10/11/12 to 2
    // return $map[$refId] ?? $refId;
    return $refId;
}

/**
 * Gender compatibility:
 *   - 3 in groupData = "any"
 *   - otherwise must equal client's gender
 */
function gender_matches(int $groupGender, int $clientGender): bool {
    return ($groupGender === 3) || ($groupGender === $clientGender);
}

/**
 * Compute a score for how well a groupData row matches the client profile.
 * Higher score = better match.
 */
function score_group_candidate(array $g, array $c): int {
    $score = 0;
    // Hard filter already: program & gender
    // Strong match on exact therapy_group_id
    if ((int)$g['therapy_group_id'] === (int)$c['therapy_group_id']) $score += 8;
    // Referral type match (after normalization)
    if ((int)$g['referral_type_id'] === normalizeReferralId((int)$c['referral_type_id'])) $score += 4;
    // Required sessions match
    if ((int)$g['required_sessions'] === (int)$c['required_sessions']) $score += 2;
    // Fee match
    if ((int)$g['fee'] === (int)$c['fee']) $score += 1;

    return $score;
}

/**
 * Tie-break: prefer candidates with therapy_group_id exact match, then lower fee,
 * then first encountered (stable).
 */
function better_than(array $a, array $b, array $client): bool {
    if ($a['score'] !== $b['score']) return $a['score'] > $b['score'];

    $tgA = (int)$a['row']['therapy_group_id'];
    $tgB = (int)$b['row']['therapy_group_id'];
    $want = (int)$client['therapy_group_id'];

    $aExact = ($tgA === $want);
    $bExact = ($tgB === $want);
    if ($aExact !== $bExact) return $aExact; // exact wins

    // Prefer lower fee
    $feeA = (int)$a['row']['fee'];
    $feeB = (int)$b['row']['fee'];
    if ($feeA !== $feeB) return $feeA < $feeB;

    // Otherwise keep existing winner (stable)
    return false;
}

/* ============================================================================
 * 3) PUBLIC API
 * ========================================================================== */

/**
 * Return the client's regular group URL using $groupData mapping.
 *
 * - Pull minimal client profile (single query).
 * - Filter candidates by program_id and gender (3 = any).
 * - Score candidates by therapy_group, referral_type, required_sessions, fee.
 * - Return best link; fallback to portal if none.
 */
function notesao_regular_group_link(mysqli $con, int $clientId): string {
    // Fetch minimal profile for matching
    $stmt = $con->prepare("
        SELECT id, program_id, referral_type_id, gender_id, required_sessions, fee, therapy_group_id
        FROM client
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        // Can't prepare => safe fallback
        return clientportal_default_url();
    }
    $stmt->bind_param('i', $clientId);
    $stmt->execute();
    $res = $stmt->get_result();
    $client = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$client) {
        return clientportal_default_url();
    }

    // Ensure ints
    $client['program_id']        = (int)$client['program_id'];
    $client['referral_type_id']  = normalizeReferralId((int)$client['referral_type_id']);
    $client['gender_id']         = (int)$client['gender_id'];
    $client['required_sessions'] = (int)$client['required_sessions'];
    $client['fee']               = (int)$client['fee'];
    $client['therapy_group_id']  = (int)$client['therapy_group_id'];

    // Use the global mapping (paste above). If empty, fallback.
    global $groupData;
    if (empty($groupData) || !is_array($groupData)) {
        return clientportal_default_url();
    }

    // Filter & score candidates
    $best = null; // ['row' => array, 'score' => int]
    foreach ($groupData as $row) {
        // Skip incomplete rows
        if (!isset(
            $row['program_id'], $row['referral_type_id'], $row['gender_id'],
            $row['required_sessions'], $row['fee'],
            $row['therapy_group_id'], $row['link']
        )) {
            continue;
        }

        // Program must match
        if ((int)$row['program_id'] !== $client['program_id']) continue;

        // Gender check (3 = any)
        if (!gender_matches((int)$row['gender_id'], $client['gender_id'])) continue;

        // Score
        $score = score_group_candidate($row, $client);
        $candidate = ['row' => $row, 'score' => $score];

        if ($best === null || better_than($candidate, $best, $client)) {
            $best = $candidate;
        }
    }

    if ($best && !empty($best['row']['link'])) {
        return (string)$best['row']['link'];
    }

    return clientportal_default_url();
}
