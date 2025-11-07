<?php
/**
 * intake_print.php — SAFATHERHOOD BIPP Intake: print-friendly packet
 * Renders the exact form language and injects saved answers.
 * Usage: /intake_print.php?id=123
 */

declare(strict_types=1);
ob_start();
session_start();

ini_set('display_errors','1');
error_reporting(E_ALL);

require_once dirname(__DIR__).'/config/config.php'; // provides $link
/** @var mysqli $link */
$db = $link;
$db->set_charset('utf8mb4');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  exit('Missing or invalid id.');
}

$stmt = $db->prepare('SELECT * FROM intake_packet WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$res  = $stmt->get_result();
$row  = $res->fetch_assoc();
if (!$row) {
  http_response_code(404);
  exit('Packet not found.');
}

// helpers
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function v(array $row, string $k, ?string $fallbackKey=null): string {
  if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') return h((string)$row[$k]);
  if ($fallbackKey && array_key_exists($fallbackKey, $row)) return h((string)$row[$fallbackKey]);
  return '';
}
function yn(array $row, string $k): string {
  $val = $row[$k] ?? null;
  if ($val === null || $val === '') return '—';
  return ((string)$val === '1') ? 'Yes' : 'No';
}
function date_disp(?string $ymd): string {
  if (!$ymd) return '';
  $ts = strtotime($ymd);
  return $ts ? date('F j, Y', $ts) : h($ymd);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Print: BIPP Intake Packet — <?= h(($row['first_name']??'').' '.($row['last_name']??'')) ?> (ID <?= (int)$row['id'] ?>)</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<style>
  :root { --ink:#111; --muted:#666; --rule:#ddd; }
  body { color:var(--ink); }
  .packet { max-width: 900px; margin: 24px auto; background:#fff; }
  .hdr { text-align:center; margin-bottom: 12px; }
  .hdr img { max-width: 60%; height:auto; }
  .sheet { padding: 20px 24px; border:1px solid var(--rule); border-radius:8px; margin-bottom:18px; }
  .meta small { color:var(--muted); }
  h1,h2,h3,h4,h5,h6 { page-break-inside: avoid; }
  .policy { border:1px solid #ddd; border-radius:4px; background:#fafafa; padding:1rem; }
  .sigline { display:flex; gap:16px; margin-top:8px; flex-wrap:wrap; }
  .sigline .blk { flex:1 1 320px; }
  .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
  .rule-hanging { position:relative; padding-left: 60px; }
  .rule-hanging .initials-box-print { position:absolute; left:0; top:0; width:50px; text-align:center; border:1px solid #ccc; padding:2px 4px; }
  .kv { margin: 0 0 6px; }
  .kv b { display:inline-block; min-width: 200px; }
  .pagebreak { page-break-before: always; }
  @media print {
    .noprint { display:none !important; }
    .sheet { border:none; border-radius:0; }
    .packet { margin:0; }
    a[href]:after { content:""; } /* suppress link URLs in print */
  }
</style>
</head>
<body>
<div class="packet">

  <div class="hdr">
    <a href="https://aitscm.org/" target="_blank" rel="noopener">
      <img src="safatherhoodlogo.png" alt="San Antonio Fatherhood Campaign">
    </a>
  </div>

  <div class="sheet">
    <h2 class="h4 mb-2">BIPP Intake Packet — Participant</h2>
    <div class="kv"><b>Name:</b> <?= h(($row['first_name']??'').' '.($row['last_name']??'')) ?></div>
    <div class="kv"><b>DOB:</b> <?= date_disp($row['date_of_birth'] ?? '') ?></div>
    <div class="kv"><b>Email:</b> <?= h($row['email'] ?? '') ?></div>
    <div class="kv"><b>Cell:</b> <?= h($row['phone_cell'] ?? '') ?></div>
    <div class="kv"><b>Address:</b> <?= h(trim(($row['address_street']??'').', '.($row['address_city']??'').', '.($row['address_state']??'').' '.($row['address_zip']??''), " ,")) ?></div>
    <div class="kv"><b>Packet ID:</b> <?= (int)$row['id'] ?> &nbsp; <b>Submitted:</b> <?= date_disp($row['signature_date'] ?? $row['intake_date'] ?? '') ?></div>
  </div>

  <!-- 1. Statement of Confidentiality & Consent for Treatment -->
  <div class="sheet">
    <h3 class="h5">Statement of Confidentiality &amp; Consent for Treatment</h3>

    <div class="policy">
      <p>Confidentiality is defined as keeping private the information shared by you, the client, with your
      counselor. On occasion, other employees may need access to your record for agency teaching,
      supervision, and administrative purposes. These staff members will also respect the privacy of your
      records. In accordance with the Texas Department of Criminal Justice – Community Justice Assistance Division
      and Texas Council on Family Violence Battering Intervention &amp; Prevention Program guidelines, clients are
      required to sign Consent for Release of Information, which permits information to be released to the
      victim/partner and/or their designated representative, law enforcement, the courts, correction
      agencies, and any others in accordance with agency policy.</p>

      <p><strong>As a client, you have the right to withhold or release information to other individuals or
      agencies.</strong> A statement signed by you is required before any information may be released to anyone
      outside San Antonio Fatherhood Campaign – BIPP. This right applies with the following exceptions:</p>

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
          <li><strong>Media involvement:</strong> Any media contact arranged by the San Antonio Fatherhood Campaign program
              will include the presence of a San Antonio Fatherhood Campaign employee to protect victim confidentiality.</li>
      </ul>

      <p><strong>We ask that you keep confidential information you may learn about other clients who are
      receiving services from San Antonio Fatherhood Campaign – BIPP.</strong></p>

      <p><strong>San Antonio Fatherhood Campaign requires facilitators and participants to:</strong></p>
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

      <p><strong>I agree to receive phone calls and text messages from San Antonio Fatherhood Campaign.</strong></p>

      <p>I hereby agree that my groups shall be video/audio recorded merely for the purposes of security, training, and quality assurance to be viewed by San Antonio Fatherhood Campaign facilitators and building security. These video/audio recordings will depict varius educational services offered by San Antonio Fatherhood Campaign. The video/audio recordings may be kept for up to 14 days after session is conducted for each program unless they are being retained for internal training purposes or we have been notified of pending litigation and have been request not to destroy the recording(s).</p>

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
              Greg Marshall, at 210-227-3463 ext. 2012.</li>
          <li>If further resolution is needed, contact the Texas Council on Family Violence at 800-525-1978.</li>
      </ul>

      <p><strong>Important:</strong> Any no-shows, late arrivals, dismissals, missing classes, appointments, drops, or cancellations for any services may result in termination from this project. We reserve the right to modify this Agreement at any time without providing prior notice. If you have any concerns, please inform the staff.</p>

      <p>By signing this form, you agree to complete the assigned task that meets the needs prescribed to you by your referring agency (which includes yourself) and our staff. Additionally, you understand that failure to adhere to the project’s efforts will not be tolerated and may result in dismissal and notification to the referral agency.</p>

      <p><strong>My Signature below authorizes</strong> my American Indians in Texas team members to send SMS messages to the phone number provided during the enrollment/signup process for service-related needs. I understand that I may opt out of receiving SMS messages by replying to text messages with <strong>STOP</strong>, and that I may request help at any time by texting <strong>HELP</strong>. Furthermore, I understand that the frequency of SMS messages varies, and message and data rates from my mobile phone carrier may apply.</p>
    </div>

    <div class="kv mt-2"><b>I Agree:</b> <?= yn($row,'consent_confidentiality') ?></div>
    <div class="sigline">
      <div class="blk">
        <div><b>Signature (typed):</b></div>
        <div class="mono"><?= v($row,'confidentiality_sig_p1','consent_p1_signature') ?></div>
      </div>
      <div class="blk">
        <div><b>Date:</b></div>
        <div class="mono"><?= date_disp($row['confidentiality_date_p1'] ?? $row['consent_p1_date'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <!-- Page 7 — Consent for Release of Information and Sworn Statement -->
  <div class="sheet">
    <h3 class="h5">Page 7 — Consent for Release of Information and Limits to Confidentiality</h3>

    <p class="mb-2">I understand that throughout the duration of the program the staff of Fatherhood Campaign
    may contact the person with whom I have been violent for descriptions of abusive and
    controlling behaviors I utilize.</p>

    <div class="sigline">
      <div class="blk">
        <div><b>Participant Name (signature):</b></div>
        <div class="mono"><?= v($row,'consent_release_sig_name') ?></div>
      </div>
      <div class="blk">
        <div><b>Date:</b></div>
        <div class="mono"><?= date_disp($row['consent_release_signed_date'] ?? '') ?></div>
      </div>
    </div>

    <hr>

    <h4 class="h6 mt-3">Sworn Statement (required if “No knowledge of victim contact information”)</h4>
    <div class="kv"><b>Victim contact provided:</b> <?= ($row['victim_contact_provided']??'0')==='1'?'Yes':'No' ?></div>
    <div class="sigline">
      <div class="blk">
        <div><b>Participant Name (signature):</b></div>
        <div class="mono"><?= v($row,'sworn_sig_name') ?></div>
      </div>
      <div class="blk">
        <div><b>Date:</b></div>
        <div class="mono"><?= date_disp($row['sworn_signed_date'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <!-- 8a — Referral – Consent for Disclosure of Information -->
  <div class="sheet">
    <h3 class="h5">8a — Referral – Consent for Disclosure of Information</h3>

    <div class="policy">
      <p>I understand that such disclosure will be made for the purposes of information exchange, progress reports, coordination of services, other investigative departments and referrals and facilitating victim safety. Disclosure is limited to information regarding attendance, participation, information exchange, coordination of services and referrals &amp; facilitating victim safety.</p>
      <p>I understand that I may revoke this consent at any time and that may request for revocation must be in writing. If not earlier revoked, this consent for disclosure of information shall expire 1 year after completion of or termination from Fatherhood Campaign - BIPP. I understand the right to confidentiality. I further understand that this consent form gives Fatherhood Campaign - BIPP permission to share confidential information in the way described above.</p>
      <p>Release of information is voluntary, I understand I have a right to refuse Fatherhood Campaign - BIPF request for this disclosure. Fatherhood Campaign - BIPP reserves the right to dismiss any client who refuses to meet the provisions of The Texas Department of Criminal Justice-Community Justice Assistance Division and Texas Council on Family Violence Battering Intervention &amp; Prevention Project guidelines.</p>
      <p>I also understand that it will be necessary for Fatherhood Campaign staff to contact other individuals regarding my abusive and controlling behaviors and issues affecting my participation in the program. This could include law enforcement, the courts, community supervision and corrections officers and others, according to agency policy. Fatherhood Campaign has my permission to release and obtain information concerning my behavior and program participation to and from the above organizations/persons:</p>
      <p>That when it is determined that there is probability of imminent physical injury to oneself or others, stall will take safety initiatives and may, if appropriate, notify medical or law enforcement personnel and/or the victim and referral source; and If the assessment (intake) or subsequent contact reveals the possibility of incidents of child abuse or neglect, or abuse of the elderly or disabled, it must be reported to the Texas Department of Family and Protective Services (TDFPS).</p>
      <p>That personal data and possibly additional information will be submitted to TDCL-CJAD by the program or provided for the purposes of performing program assessments and other research.</p>
      <p>Case records are subject to subpoena; and Information disclosed by batterers during an assessment (intake), group sessions, and exit is confidential and shall not be shared with victims.</p>
    </div>

    <div class="kv"><b>I Agree:</b> <?= yn($row,'consent_disclosure') ?></div>
    <div class="sigline">
      <div class="blk">
        <div><b>Participant Signature:</b></div>
        <div class="mono">
          <?php // support either column naming
            $sig = v($row,'participant_signature','consent8a_signature');
            echo $sig;
          ?>
        </div>
      </div>
      <div class="blk">
        <div><b>Date:</b></div>
        <div class="mono"><?= date_disp($row['participant_signature_date'] ?? $row['consent8a_date'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <!-- 8b — Consent for Disclosure of Information for Partners -->
  <div class="sheet">
    <h3 class="h5">8b — Consent for Disclosure of Information for Partners</h3>

    <div class="policy">
      <p>I understand that such disclosure will be made for the purposes of progress reports, referrals and facilitating victim safety.</p>
      <p>Disclosure is limited to information regarding attendance, participation, information exchange and referrals for services. I understand that I may revoke this consent at any time and that my request for revocation must be in writing. If not earlier revoked, this consent for disclosure of information shall expire 1 year after my completion of or termination from Fatherhood Campaign - Batterers Intervention &amp; Prevention Program.</p>
      <p>I understand my right to confidentiality. I further understand that this consent form gives Fatherhood Campaign - BIPP permission to share confidential information about me in the way described above. I understand that Victim will be contacted by the Victim Advocate and offered counseling services. They will be provided enrollment, completion or termination information from Fatherhood Campaign - BIPP</p>
      <p>Release of information is voluntary; I understand I have a right to refuse Fatherhood Campaign - BIPP request for this disclosure.</p>
      <p>Fatherhood Campaign - BIPP reserves the right to dismiss any client who refuses to meet the provisions of The Texas Department of Criminal Justice-Community Justice Assistance Division and Texas Council on Family Violence Battering Intervention &amp; Prevention Project guidelines. Information disclosed by batterers during an assessment (intake), group sessions, and exit is confidential and shall not be shared with victims.</p>
    </div>

    <div class="kv"><b>I Agree:</b> <?= yn($row,'consent_partner_info') ?></div>
    <div class="sigline">
      <div class="blk">
        <div><b>Participant Signature:</b></div>
        <div class="mono"><?= v($row,'disclosure_signature_8b') ?></div>
      </div>
      <div class="blk">
        <div><b>Date:</b></div>
        <div class="mono"><?= date_disp($row['disclosure_date_8b'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <!-- 8c — SAFC Program Agreement & Taking Responsibility -->
  <div class="sheet">
    <h3 class="h5">8c — SAFC Program Agreement &amp; Taking Responsibility</h3>

    <div class="policy">
      <h6 class="mb-2">Program Agreement</h6>
      <p>Fee per session is $25.00; this fee is only one type of demonstration of your accountability and restitution for violent behavior. Breaks, assessment (intake), and orientation are not to be included towards the 36 hours.</p>

      <p>
        I agree to start <span class="mono"><?= date_disp($row['start_date_8c'] ?? '') ?></span>
        on <span class="mono"><?= h($row['start_dow_8c'] ?? '') ?></span>
        from <span class="mono"><?= h($row['start_time_8c'] ?? '') ?></span>.
      </p>

      <p>Battering Intervention and Prevention Program consist of Assessment (Intake) and Orientation and at least 36 hours of group sessions in a minimum of (20) weekly sessions, not to exceed one session per week,</p>
      <p>Exit Session. If dismissed, the client must apply to re-enter into the Fatherhood Campaign - BIPP. Re-entry is considered on a case by case basis. I understand that I can not re-enter the program until I have paid off my previous balance.</p>
      <p>Clients who miss (3) consecutive sessions (group or individual) or a (total of 5 sessions), you will be discharged from the program. Your referral sources will determine what happens with your case as a result of your absences.</p>
      <p>There are no excused absences. Incarceration is an inexcusable absence. Any new offense related to domestic violence is an automatic dismissal.</p>
      <p>If you have a cell phone it must be turned off or on silent and placed out of sight; text messaging is not allowed.</p>
      <p>No Food allowed in group room or virtual meeting.</p>
      <p>If you destroy or damage property, you will be liable for the damages.</p>
      <p>Restroom breaks should take no longer than 5 minutes unless you have prior approval from BIPP staff.</p>
      <p>Payment for services is due at the time service is rendered. You will not be credited for attending groups or individual sessions unless payment is received. Client is required to maintain no more than a $25 balance. I will continue to attend until I have a zero balance. Attendance may exceed 24wks if payment is not completed.</p>
      <p>This building is designated as a Non-Smoking facility or virtual meeting.</p>
      <p>I hereby agree to arrive to all of my sessions on time. If you are (5) MINUTES AFTER THE DESIGNATED START TIME-you will not receive credit for attending the group.</p>
      <p>I understand that I MUST sign the group attendance roster in the group room or check in during the virtual meeting. I WILL NOT be counted present for the session unless I sign the roster or check in during the virtual meeting.</p>
      <p>I will notify the Fatherhood Campaign - BIPP of any change of address or phone numbers.</p>
      <p>All homework assignments must be completed. Clients will not receive credit for incomplete assignments.</p>
      <p>I hereby agree to contact BIPP by phone at 210-664-0102 when I am unable to attend a scheduled session. Failure to contact Fatherhood Campaign - BIPP within (2) consecutive absences is an automatic dismissal.</p>
      <p>I agree not to attend a group or during a virtual meeting under the influence of alcohol or drugs; refusal of a UA is an automatic discharge. It will be my responsibility to arrange transportation home so I’m not a danger to myself or others for driving under the influences. The referring agency will be notified of this incident. I will be required to pay $35 to take a UA and will not return to the group until the results are reported to the Fatherhood Campaign - BIPP.</p>
      <p>I hereby agree not to be abusive towards any staff person or other group members. I understand that I may not use sexist or racist language.</p>
      <p>I hereby agree not to be in possession of a weapon of any kind. I also agree to follow federal firearm restriction laws related to domestic violence offenses.</p>
      <p>I hereby agree to respect the confidentiality rights of my fellow client/group members. I further understand that a violation of this rule shall result in immediate termination from the program and shall be reported to the proper authorities.</p>
      <p>I hereby agree to notify a staff person of any and all emergencies that I am either a part of or a witness to.</p>
      <p>I understand that Fatherhood Campaign - BIPP is committed to helping me gain a better understanding of my problems and how to find productive solutions and that it is the main goal of my psycho educational classes.</p>
      <p>I understand that I will always keep my phone and contact information current with San Antonio Fatherhood Campaign so that I can receive phone calls and text messages pertaining to group and clinical services.</p>
      <p>I understand that Community Supervisions &amp; Corrections Department (CSCD), Probation, Parole, CPS, or TDCJ will notify San Antonio Fatherhood Campaign about my attendance and that San Antonio Fatherhood Campaign needs to be able to reach me so they may guide me in succssfully adhering to program stipulations.</p>

      <div class="kv"><b>I Agree (Program Agreement):</b> <?= yn($row,'consent_program_agreement') ?: yn($row,'consent_program_agreement') ?></div>
      <div class="sigline">
        <div class="blk">
          <div><b>Participant Signature:</b></div>
          <div class="mono"><?= v($row,'program_signature_8ca') ?></div>
        </div>
        <div class="blk">
          <div><b>Date:</b></div>
          <div class="mono"><?= date_disp($row['program_date_8ca'] ?? '') ?></div>
        </div>
      </div>

      <hr>

      <h6 class="mb-2">Taking Responsibility</h6>
      <p>During group discussions, participants may not blame anyone else for their own behaviors.</p>
      <p>Participants agree to not use any form of violence, abusive, threatening and controlling behaviors including stalking during the weeks they are in the program. A participant who uses violence may be terminated from the program. This action will be reported to participants' referral agencies. Participants will cease violent, abusive, threatening, and controlling behaviors, including stalking and violation of a protective order. Participants who are terminated for this reason and wish to re-enter the program will re-start from 1st week.</p>
      <p>Participants will develop and adhere to a non-violence plan as outlined in the program curriculum.</p>

      <div class="kv"><b>I Agree (Taking Responsibility):</b> <?= yn($row,'consent_responsibility') ?></div>
      <div class="sigline">
        <div class="blk">
          <div><b>Participant Signature:</b></div>
          <div class="mono"><?= v($row,'program_signature_8cb') ?></div>
        </div>
        <div class="blk">
          <div><b>Date:</b></div>
          <div class="mono"><?= date_disp($row['program_date_8cb'] ?? '') ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- 8d — Virtual Group Rules -->
  <div class="sheet">
    <h3 class="h5">8d — Virtual Group Rules</h3>
    <p class="mb-2">Please Review and Adhere to the Group rules for a successful group experience:</p>

    <ol class="mb-3 pl-3">
      <?php
        $rules = [
          "Payment on-line does not guarantee credit for attending a group session.",
          "Attendance is taken during the group-that will determine credit for attending.",
          "Participant’s faces/eyes must always be visible – always keep a close-up of your face on webcam unless you’re on break.",
          "No drowsiness or sleeping during group.",
          "Mic’s stay off unless/until you are speaking.",
          "Participants must be alone.",
          "Cameras stay on unless you are taking a break; while on break, you must stay connected to Zoom.",
          "No other devices can be in use during group time: TV, cell phone, stereo, etc.",
          "While in class, set your phone on “Don’t disturb” mode.",
          "No children, relatives, friends, or pets can be present.",
          "No sunglasses, hats, hoodies, or filters that can disguise or camouflage your physical self or background.",
          "No eating or smoking/vaping. Non-alcoholic beverages only.",
          "No multi-tasking, reading, texting, walking around, driving, self-grooming, working, etc."
          // If your form has rules 14–19, add their exact text below in order:
          // 14...
          // 15...
          // 16...
          // 17...
          // 18...
          // 19...
        ];
        for ($i=1; $i<=count($rules); $i++):
          $col = 'vgr_initial_'.$i;
      ?>
      <li class="rule-hanging">
        <span class="initials-box-print"><?= h($row[$col] ?? '') ?></span>
        <?= h($rules[$i-1]) ?>
      </li>
      <?php endfor; ?>
    </ol>

    <div class="kv"><b>Derived consent (all initials + sign/date present):</b> <?= ($row['consent_virtual_rules']??'0')==='1'?'Yes':'No' ?></div>
    <div class="sigline">
      <div class="blk">
        <div><b>Participant Signature:</b></div>
        <div class="mono"><?= v($row,'vgr_signature_8d') ?></div>
      </div>
      <div class="blk">
        <div><b>Date:</b></div>
        <div class="mono"><?= date_disp($row['vgr_date_8d'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <!-- 8e — Policy For Clients and Termination Policy -->
  <div class="sheet">
    <h3 class="h5">8e — Policy For Clients and Termination Policy</h3>

    <!-- Paste the exact 8e policy text block from the form here -->
    <!-- BEGIN 8e EXACT TEXT -->
    <!-- If you have the 8e <div class="border ..."> block, copy it verbatim below -->
    <!-- END 8e EXACT TEXT -->

    <div class="kv"><b>I Agree:</b> <?= yn($row,'consent_policy_termination') ?></div>
    <div class="sigline">
      <div class="blk">
        <div><b>Participant Signature:</b></div>
        <div class="mono"><?= v($row,'termination_signature_8e') ?></div>
      </div>
      <div class="blk">
        <div><b>Date:</b></div>
        <div class="mono"><?= date_disp($row['termination_date_8e'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <!-- 8f — Hold Harmless Agreement -->
  <div class="sheet">
    <h3 class="h5">8f — Hold Harmless Agreement</h3>

    <!-- Paste the exact 8f Hold Harmless text block from the form here -->
    <!-- BEGIN 8f EXACT TEXT -->
    <!-- Copy your 8f <div class="border ..."> content verbatim -->
    <!-- END 8f EXACT TEXT -->

    <div class="kv"><b>I Agree:</b> <?= yn($row,'consent_hold_harmless') ?></div>
    <div class="sigline">
      <div class="blk">
        <div><b>Participant Signature:</b></div>
        <div class="mono"><?= v($row,'hold_harmless_signature') ?></div>
      </div>
      <div class="blk">
        <div><b>Date:</b></div>
        <div class="mono"><?= date_disp($row['hold_harmless_date'] ?? '') ?></div>
      </div>
    </div>
  </div>

  <div class="sheet noprint text-center">
    <button class="btn btn-primary" onclick="window.print()">Print</button>
  </div>

</div>
</body>
</html>
