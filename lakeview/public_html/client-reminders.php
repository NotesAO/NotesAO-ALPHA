<?php
// ------------------------------------------------------------
// client-reminders.php  (lakeview)
// Index: list groups (today-first), quick-send, broadcast composer.
// Group view: roster + per-group editor + send selected.
// In-house WYSIWYG (no external deps), unsubscribe headers/links,
// client-specific {{group_link}} via clientportal_lib.php.
// ------------------------------------------------------------
declare(strict_types=1);
date_default_timezone_set('America/Chicago');

require_once '../config/config.php';
require_once 'auth.php';
check_loggedin($con, '../index.php');
require_once 'clientportal_lib.php'; // exposes notesao_regular_group_link(...) + $groupData in your copy

/* -----------------------------------------------
   CSRF
-------------------------------------------------*/
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

/* -----------------------------------------------
   DB helpers
-------------------------------------------------*/
function qall(mysqli $con, string $sql, array $params = [], string $types = ''): array {
    $stmt = $con->prepare($sql);
    if (!$stmt) throw new RuntimeException("Prepare failed: {$con->error}");
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}
function qone(mysqli $con, string $sql, array $params = [], string $types = ''): ?array {
    $r = qall($con, $sql, $params, $types);
    return $r[0] ?? null;
}

/* -----------------------------------------------
   Group helpers
-------------------------------------------------*/
function parse_weekday_time(string $groupName): array {
    // Return [weekdayIdx (0..6 or null), 'Monday', '7:30 PM']
    $wdMap = ['SUNDAY'=>0,'MONDAY'=>1,'TUESDAY'=>2,'WEDNESDAY'=>3,'THURSDAY'=>4,'FRIDAY'=>5,'SATURDAY'=>6];
    $weekday = null; $weekdayIdx = null; $timeDisp = null;
    foreach ($wdMap as $word=>$idx) {
        if (stripos($groupName, $word)!==false) { $weekdayIdx=$idx; $weekday=ucfirst(strtolower($word)); break; }
    }
    if (preg_match('/\b(\d{1,2})(?::(\d{2}))?\s*(AM|PM)\b/i', $groupName, $m)) {
        $hh=(int)$m[1]; $mm=$m[2]??'00'; $ampm=strtoupper($m[3]);
        $timeDisp = sprintf('%d:%s %s',$hh,str_pad($mm,2,'0',STR_PAD_LEFT),$ampm);
    }
    return [$weekdayIdx,$weekday,$timeDisp];
}
function day_ordering(): array {
    $todayIdx=(int)date('w'); $seq=[];
    for ($i=0;$i<7;$i++) $seq[] = ($todayIdx+$i)%7;
    return $seq;
}
function day_name_from_idx(int $i): string {
    return ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][$i];
}

function get_all_groups(mysqli $con): array {
    // therapy_group: id, program_id, name, address, city, state, zip
    $sql="SELECT id, program_id, name, address, city, state, zip
          FROM therapy_group
          ORDER BY name";
    return qall($con,$sql);
}
function get_clients_for_group(mysqli $con, int $groupId): array {
    $sql="SELECT id, first_name, last_name, email, program_id, therapy_group_id, gender_id, case_manager_id

          FROM client
          WHERE therapy_group_id=?
            AND exit_date IS NULL
            AND email IS NOT NULL AND email <> ''
          ORDER BY last_name, first_name";
    return qall($con,$sql,[$groupId],'i');
}
function get_all_active_clients(mysqli $con): array {
    $sql="SELECT id, first_name, last_name, email, program_id, therapy_group_id, gender_id, case_manager_id

          FROM client
          WHERE exit_date IS NULL
            AND email IS NOT NULL AND email <> ''
          ORDER BY last_name, first_name";
    return qall($con,$sql);
}

/* -----------------------------------------------
   T4C rosters (program_id = 1)
   - In-person via attends_<day> booleans
   - Virtual as therapy_group_id = 116
-------------------------------------------------*/
function get_t4c_day_rosters(mysqli $con): array {
    $days=['sunday','monday','tuesday','wednesday','thursday','friday','saturday'];
    $rosters=[];
    foreach ($days as $d) {
        $col="attends_{$d}";
        $sql="SELECT id, first_name, last_name, email, program_id, therapy_group_id, gender_id, case_manager_id

              FROM client
              WHERE program_id=1
                AND exit_date IS NULL
                AND $col=1
                AND email IS NOT NULL AND email <> ''
              ORDER BY last_name, first_name";
        $rosters[$d]=qall($con,$sql);
    }
    $virtual=qall($con,"SELECT id, first_name, last_name, email, program_id, therapy_group_id, gender_id, case_manager_id

                        FROM client
                        WHERE program_id=1
                          AND exit_date IS NULL
                          AND therapy_group_id=116
                          AND email IS NOT NULL AND email <> ''
                        ORDER BY last_name, first_name");
    return [$rosters,$virtual];
}

/* -----------------------------------------------
   Subject/Body helpers for placeholders
-------------------------------------------------*/
function program_short(int $pid): string {
    switch ($pid) {
        case 1: return 'T4C';
        case 2: return 'BIPP';
        case 3: return 'BIPP';
        case 4: return 'Anger Control';
        default: return 'Program';
    }
}
function gender_possessive_label(int $genderId): string {
    // 2 = Male, 3 = Female (your schema uses gender_id)
    if ($genderId === 2) return "Men's";
    if ($genderId === 3) return "Women's";
    return '';
}

function ordinal_suffix(int $n): string {
    $n = abs($n) % 100;
    if ($n >= 11 && $n <= 13) return 'th';
    switch ($n % 10) { case 1: return 'st'; case 2: return 'nd'; case 3: return 'rd'; default: return 'th'; }
}
function next_group_date_str(array $gmeta, string $tz = 'America/Chicago'): string {
    // Needs $gmeta['weekday'] like 'Tuesday' and $gmeta['time'] like '7:30 PM'
    $wdName = $gmeta['weekday'] ?? '';
    if ($wdName === '') {
        $dt = new DateTime('now', new DateTimeZone($tz));
        return $dt->format('F j') . ordinal_suffix((int)$dt->format('j'));
    }
    $wdIndex = ['Sunday'=>0,'Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6][$wdName] ?? null;
    $now = new DateTime('now', new DateTimeZone($tz));
    $target = clone $now;

    // Default time 12:00 PM if missing
    $time = trim((string)($gmeta['time'] ?? '12:00 PM'));
    if (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(AM|PM)$/i', $time, $m)) {
        $hh = (int)$m[1]; $mm = (int)($m[2] ?? 0); $ampm = strtoupper($m[3]);
        if ($ampm === 'PM' && $hh < 12) $hh += 12;
        if ($ampm === 'AM' && $hh === 12) $hh = 0;
    } else { $hh = 12; $mm = 0; }

    if ($wdIndex !== null) {
        $todayIdx = (int)$now->format('w');
        $daysAhead = ($wdIndex - $todayIdx + 7) % 7;
        // If today and time already passed, push to next week
        $candidate = (clone $now)->setTime($hh,$mm,0);
        if ($daysAhead === 0 && $candidate <= $now) $daysAhead = 7;
        if ($daysAhead > 0) $target->modify("+{$daysAhead} days");
    }
    $target->setTime($hh,$mm,0);
    return $target->format('F j') . ordinal_suffix((int)$target->format('j'));
}
function fetch_case_manager(mysqli $con, ?int $cmId): ?array {
    if (!$cmId) return null;
    $row = qone($con, "SELECT id, first_name, last_name, office, email, phone_number, referral_source FROM case_manager WHERE id=?", [$cmId], 'i');
    return $row ?: null;
}


/* -----------------------------------------------
   Client-specific group link (hook to portal logic)
-------------------------------------------------*/
function resolve_group_link(mysqli $con, array $client): string {
    if (function_exists('notesao_regular_group_link')) {
        try { return (string)notesao_regular_group_link($con, (int)$client['id']); }
        catch (Throwable $e) { /* fall through */ }
    }
    // Safe fallback: portal landing (adjust if needed)
    return (defined('APP_BASE_URL') ? APP_BASE_URL : 'https://lakeview.notesao.com') . '/client.php';
}


// Cached case manager lookup
function get_case_manager(mysqli $con, ?int $cmId): array {
    static $cache = [];
    if (!$cmId) return ['name'=>'','office'=>''];
    if (isset($cache[$cmId])) return $cache[$cmId];
    $row = qone($con, "SELECT first_name, last_name, office FROM case_manager WHERE id=?", [$cmId], 'i');
    $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
    $cache[$cmId] = ['name'=>$name, 'office'=>$row['office'] ?? ''];
    return $cache[$cmId];
}

/* -----------------------------------------------
   Placeholder builders for subject/body (Step 3)
-------------------------------------------------*/
function subject_prefix_for(array $client): string {
    $pid = (int)($client['program_id'] ?? 0);
    $gid = (int)($client['gender_id'] ?? 0); // 1=unspecified, 2=male, 3=female

    if ($pid === 2 || $pid === 3) { // BIPP (men/women programs both map to BIPP in text)
        if ($gid === 3) return "Women's BIPP";
        if ($gid === 2) return "Men's BIPP";
        return "BIPP"; // fallback when unspecified
    }
    if ($pid === 1) return "Thinking for a Change";
    if ($pid === 4) return "Anger Control";
    return "Program";
}


function referral_type_label(?int $refId): string {
    // TODO: adjust to your actual referral_type_id values
    $map = [
        1 => 'Court',
        2 => 'Parole',
        3 => 'Probation',
        4 => 'CPS',
        5 => 'Self-Pay',
    ];
    return $map[$refId ?? 0] ?? '';
}

function build_officer_sentence(array $off): string {
    $name = trim($off['name'] ?? '');
    $office = trim($off['office'] ?? '');
    if ($name && $office) return $office . " and " . $name . " expect your presence.";
    if ($name) return $name . " expects your presence.";
    if ($office) return $office . " expects your presence.";
    return "";
}

function build_referral_sentence(array $client, array $off, string $programShort): string {
    $label = referral_type_label(isset($client['referral_type_id']) ? (int)$client['referral_type_id'] : null);
    $office = trim($off['office'] ?? '');
    if ($office !== '') return "Your " . $office . " mandated " . $programShort . " group is scheduled.";
    if ($label !== '') return "Your " . $label . " mandated " . $programShort . " group is scheduled.";
    return "Your " . $programShort . " group is scheduled.";
}

/**
 * Where line:
 *  - In-person: "Where: 6850 Manhattan Blvd., Fort Worth, TX 76120"
 *  - Virtual:   "Where: Virtual — use your group link: Tuesday 7:30 PM Group Link" (clickable)
 */
function build_meeting_where(array $gmeta, string $groupLink, ?string $weekday, ?string $timeDisp): string {
    $addr = trim($gmeta['address'] ?? '');
    if ($addr !== '') {
        return 'Where: ' . htmlspecialchars($addr, ENT_QUOTES, 'UTF-8');
    }
    $label = trim(trim((string)$weekday) . ' ' . trim((string)$timeDisp));
    $label = $label !== '' ? ($label . ' Group Link') : 'Group Link';
    $alink = '<a href="' . htmlspecialchars($groupLink, ENT_QUOTES, 'UTF-8') . '">'
           . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
           . '</a>';
    return 'Where: Virtual (Use your group link):<br>' . $alink;
}





/* -----------------------------------------------
   Placeholder expansion
-------------------------------------------------*/
function expand_placeholders(array $client, array $gmeta, string $subject, string $html, mysqli $con): array {
    // --- program/gender ---
    $pid = (int)($client['program_id'] ?? 0);
    $gid = (int)($client['gender_id'] ?? 0); // 1=unspecified, 2=male, 3=female

    $programFull  = [1=>"Thinking for a Change", 2=>"Men's BIPP", 3=>"Women's BIPP", 4=>"Anger Control"][$pid] ?? 'Program';
    $programShort = ($pid === 1) ? 'T4C' : (($pid === 2 || $pid === 3) ? 'BIPP' : ($pid === 4 ? 'Anger Control' : 'Program'));
    $genderPossessive = ($gid === 2) ? "Men's" : (($gid === 3) ? "Women's" : "Client's");

    // --- next occurrence + lockout (10 min after start) ---
    $weekday = $gmeta['weekday'] ?? null;
    $timeDisp = $gmeta['time'] ?? null;
    $next_group_date = '';
    $lockout_time = '';
    if ($weekday && $timeDisp && preg_match('/^\s*(\d{1,2})(?::(\d{2}))?\s*(AM|PM)\s*$/i', $timeDisp, $m)) {
        $wdMap = ['sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
        $idx = $wdMap[strtolower($weekday)] ?? null;
        if ($idx !== null) {
            $hh=(int)$m[1]; $mm=(int)($m[2] ?? 0); $ampm=strtoupper($m[3]);
            if ($hh === 12) $hh = 0;
            if ($ampm === 'PM') $hh += 12;
            $tz = new DateTimeZone('America/Chicago');
            $now = new DateTime('now', $tz);
            $target = (clone $now)->setTime($hh, $mm, 0);
            $todayIdx = (int)$now->format('w');
            $daysAhead = ($idx - $todayIdx + 7) % 7;
            if ($daysAhead === 0 && $target <= $now) $daysAhead = 7;
            if ($daysAhead > 0) $target->modify("+{$daysAhead} day");
            $next_group_date = $target->format('l, F j');
            $lockout_time = (clone $target)->modify('+10 minutes')->format('g:i A');
        }
    }

    // --- per-client link & officer/case manager ---
    $groupLink = resolve_group_link($con, $client);
    $off = get_case_manager($con, isset($client['case_manager_id']) ? (int)$client['case_manager_id'] : null);

    // --- custom sentences ---
    $subject_prefix     = subject_prefix_for($client);
    $referral_sentence  = build_referral_sentence($client, $off, $programShort);
    $officer_sentence   = build_officer_sentence($off);

    // --- meeting text (with labeled link for virtual) ---
    $meeting_where = build_meeting_where($gmeta, $groupLink, $weekday, $timeDisp);

    // --- replacements (provide both compact and spaced variants) ---
    $rep = [
        '{{first_name}}'           => $client['first_name'] ?? '',
        '{{ last_name }}'          => $client['last_name'] ?? '',       '{{last_name}}' => $client['last_name'] ?? '',

        '{{gender_possessive}}'    => $genderPossessive,                '{{ gender_possessive }}' => $genderPossessive,
        '{{program_short}}'        => $programShort,                    '{{ program_short }}'     => $programShort,
        '{{program_name}}'         => $programFull,                     '{{ program_name }}'      => $programFull,

        '{{group_name}}'           => $gmeta['name'] ?? '',             '{{ group_name }}'        => $gmeta['name'] ?? '',
        '{{group_day}}'            => $weekday ?? '',                   '{{ group_day }}'         => $weekday ?? '',
        '{{group_time}}'           => $timeDisp ?? '',                  '{{ group_time }}'        => $timeDisp ?? '',
        '{{meeting_location}}'     => $gmeta['address'] ?? '',          '{{ meeting_location }}'  => $gmeta['address'] ?? '',
        '{{group_link}}'           => $groupLink,                       '{{ group_link }}'        => $groupLink,

        '{{subject_prefix}}'       => $subject_prefix,                  '{{ subject_prefix }}'    => $subject_prefix,
        '{{referral_sentence}}'    => $referral_sentence,               '{{ referral_sentence }}' => $referral_sentence,
        '{{officer_sentence}}'     => $officer_sentence,                '{{ officer_sentence }}'  => $officer_sentence,
        '{{meeting_where}}'        => $meeting_where,                   '{{ meeting_where }}'     => $meeting_where,

        '{{next_group_date}}'      => $next_group_date,                 '{{ next_group_date }}'   => $next_group_date,
        '{{lockout_time}}'         => $lockout_time,                    '{{ lockout_time }}'      => $lockout_time,
    ];

    $subject = strtr($subject, $rep);
    $html    = strtr($html, $rep);
    return [$subject, $html];
}






/* -----------------------------------------------
   HTML sanitization (allow-list)
-------------------------------------------------*/
function sanitize_email_html(string $html): string {
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><a>'
             . '<h1><h2><h3><h4><h5><h6><table><thead><tbody><tr><th><td>';
    $html = strip_tags($html, $allowed);

    // Strip inline event handlers and style attributes
    $html = preg_replace('/\son\w+\s*=\s*("|\').*?\1/si', '', $html);
    $html = preg_replace('/\sstyle\s*=\s*("|\').*?\1/si', '', $html);

    // Normalize <a> tags: allow only http(s) or mailto
    $html = preg_replace_callback('/<a\b[^>]*>/i', function ($m) {
        $tag = $m[0];
        if (!preg_match('/href\s*=\s*("|\')([^"\']+)\1/i', $tag, $h)) return '<a>';
        $href = $h[2];
        if (!preg_match('#^(https?://|mailto:)#i', $href)) $href = '#';
        return '<a href="'.htmlspecialchars($href, ENT_QUOTES, 'UTF-8').'">';
    }, $html);

    return $html;
}

/* -----------------------------------------------
   Unsubscribe helpers
-------------------------------------------------*/
function unsubscribe_token(string $email): string {
    $email = strtolower(trim($email));
    $salt  = defined('EMAIL_UNSUB_SALT') ? EMAIL_UNSUB_SALT : (__FILE__ . php_uname());
    return hash('sha256', $email . $salt);
}
function unsubscribe_url(string $email): string {
    $base = defined('APP_BASE_URL') ? APP_BASE_URL : 'https://lakeview.notesao.com';
    return rtrim($base, '/') . '/unsubscribe.php?e='
        . urlencode($email) . '&t=' . unsubscribe_token($email);
}
function is_unsubscribed(mysqli $con, string $email): bool {
    $row = qone($con, "SELECT 1 FROM email_unsubscribed WHERE email=? LIMIT 1",
                [strtolower(trim($email))], 's');
    return (bool)$row;
}
function wrap_email_html_with_footer(string $html, string $email): string {
    $u = unsubscribe_url($email);
    $footer = '<hr style="border-top:1px solid #ddd;margin:24px 0;">'
            . '<p style="font:12px/1.4 Arial,Helvetica,sans-serif;color:#666">'
            . 'This reminder was sent by Lakeview Education. If you no longer wish to receive emails at this address, '
            . '<a href="'.htmlspecialchars($u, ENT_QUOTES, 'UTF-8').'">unsubscribe here</a>.'
            . '</p>';
    return $html . $footer;
}

/* -----------------------------------------------
   Mail
-------------------------------------------------*/
function send_html_mail(string $to, string $subject, string $htmlBody, string $from = null): bool {
    $from    = $from ?: (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@lakeview.notesao.com');
    $replyTo = defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : 'admin@notesao.com';

    $headers = [
        "From: Lakeview Education <{$from}>",
        "Reply-To: {$replyTo}",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        'List-Unsubscribe: <' . unsubscribe_url($to) . '>',
        'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
        'Precedence: bulk',
        'X-Mailer: NotesAO-Reminders/1.0'
    ];
    $envelope = "-f {$from}";

    // --- prepend header logo ---
    $base    = defined('APP_BASE_URL') ? APP_BASE_URL : 'https://lakeview.notesao.com';
    $logoUrl = rtrim($base, '/') . '/lakeviewlogo.png';
    $header  = '<div style="text-align:center;margin:0 0 12px 0">'
             . '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Lakeview Education" style="max-width:100%;height:auto;display:inline-block;">'
             . '</div>';

    // --- append footer with unsubscribe ---
    $htmlBody = $header . $htmlBody;
    $htmlBody = wrap_email_html_with_footer($htmlBody, $to);

    return @mail($to, $subject, $htmlBody, implode("\r\n", $headers), $envelope);
}


/* -----------------------------------------------
   Default templates
   Subject example: "Men's BIPP Group Reminder: Tuesday at 7:30 PM"
-------------------------------------------------*/
$DEF_SUBJECT = "{{subject_prefix}} Group Reminder: {{group_day}} at {{group_time}}";

$DEF_BODY = <<<HTML
<p>Hi {{first_name}},</p>

<p><strong>Your {{program_name}} group is {{group_day}} at {{group_time}} ({{next_group_date}}).</strong></p>

<p>
  {{referral_sentence}} {{officer_sentence}}
  Please arrive early. <em>Lockout is {{lockout_time}}</em>. Your presence is a mandatory stipulation.
  Incarceration occurs for non-attendance. Remain in good standing with CSCD &amp; TDCJ.
  If God brought you this far, you've got this.
</p>

<p>{{meeting_where}}</p>

<p>
  Client Portal (for Make-Up Group Options):<br>
  <a href="https://lakeview.notesao.com/clientportal.php">Lakeview – Client Portal</a>
</p>

<p>
  Questions? Reply to this email with any attendance or payement concerns OR call during office hours (Mon–Fri, 8am–4pm).
</p>

<p>Blessings,<br>Lakeview Education</p>
HTML;

$DEF_SUBJECT_ALL = "Lakeview Education — Reminder";
$DEF_BODY_ALL = <<<HTML
<p>Hi {{first_name}},</p>
<p>This is a reminder from Lakeview Education. If you have a session coming up, please arrive a few minutes early.</p>
<p>Check your personal link and details in your portal:</p>
<p><a href="https://lakeview.notesao.com/clientportal.php">Lakeview – Client Portal</a></p>
<p>— Lakeview Education</p>
HTML;


/* -----------------------------------------------
   Group meta cache
-------------------------------------------------*/
function group_meta_cache(mysqli $con): array {
    $cache = [];
    foreach (get_all_groups($con) as $g) {
        [$idx,$wd,$tm] = parse_weekday_time($g['name']);
        $addrParts = array_filter([$g['address'] ?? '', $g['city'] ?? '', $g['state'] ?? '', $g['zip'] ?? '']);
        $addr = trim(preg_replace('/\s+/', ' ', implode(' ', $addrParts)));
        $cache[(int)$g['id']] = [
            'name'       => $g['name'],
            'weekday'    => $wd ?: '',
            'time'       => $tm ?: '',
            'address'    => $addr,
            'program_id' => (int)$g['program_id'],
        ];
    }
    return $cache;
}
$GROUP_META = group_meta_cache($con);

/* -----------------------------------------------
   POST actions
-------------------------------------------------*/
$flash = null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'quick') {
        // Quick send default to a single group (gid numeric or t4c:<day>)
        $gid = $_POST['gid'] ?? '';
        [$sent,$failed,$errs] = [0,0,[]];

        if (preg_match('/^\d+$/',$gid)) {
            $gid = (int)$gid;
            $clients = get_clients_for_group($con,$gid);
            $gmeta = $GROUP_META[$gid] ?? ['name'=>'','weekday'=>'','time'=>'','address'=>''];

            foreach ($clients as $c) {
                if (empty($c['email'])) { $failed++; $errs[]="Missing email for #{$c['id']}"; continue; }
                if (is_unsubscribed($con, $c['email'])) { $failed++; $errs[]="Unsubscribed: {$c['email']}"; continue; }

                // Build → then sanitize → then send
                [$subj,$html] = expand_placeholders($c,$gmeta,$DEF_SUBJECT,$DEF_BODY,$con);
                $html = sanitize_email_html($html);
                $to = $c['email'];

                if (send_html_mail($to,$subj,$html)) {
                    $sent++;
                    @qall($con,"INSERT INTO client_event (client_id, client_event_type_id, note) VALUES (?,1,?)",
                        [(int)$c['id'],'Automated reminder email sent (quick)'], 'is');
                } else { $failed++; $errs[]="Failed: {$c['email']}"; }
            }

        } elseif (preg_match('/^t4c:(sunday|monday|tuesday|wednesday|thursday|friday|saturday)$/',$gid,$mm)) {
            $day = $mm[1];
            [$t4cByDay,$t4cVirtual] = get_t4c_day_rosters($con);
            $clients = $t4cByDay[$day] ?? [];
            $gmeta = ['name'=>"Thinking for a Change — ".ucfirst($day),'weekday'=>ucfirst($day),'time'=>'','address'=>''];

            foreach ($clients as $c) {
                if (empty($c['email'])) { $failed++; $errs[]="Missing email for #{$c['id']}"; continue; }
                if (is_unsubscribed($con, $c['email'])) { $failed++; $errs[]="Unsubscribed: {$c['email']}"; continue; }

                [$subj,$html] = expand_placeholders($c,$gmeta,$DEF_SUBJECT,$DEF_BODY,$con);
                $html = sanitize_email_html($html);
                $to = $c['email'];

                if (send_html_mail($to,$subj,$html)) {
                    $sent++;
                    @qall($con,"INSERT INTO client_event (client_id, client_event_type_id, note) VALUES (?,1,?)",
                        [(int)$c['id'],'Automated reminder email sent (quick T4C)'], 'is');
                } else { $failed++; $errs[]="Failed: {$c['email']}"; }
            }

        } else {
            $flash = ['type'=>'danger','msg'=>'Unknown group id.'];
        }

        if (!$flash) {
            $type = $failed ? 'warning' : 'success';
            $msg  = "Quick send completed. Sent: <b>$sent</b> • Failed: <b>$failed</b>";
            if ($failed && $errs) $msg .= '<br>'.htmlspecialchars(implode('; ',$errs));
            $flash = ['type'=>$type,'msg'=>$msg];
        }
    }

    if ($action === 'send_group') {
        $gid = $_POST['gid'] ?? '';
        $subjectTpl = trim($_POST['email_subject'] ?? '');
        $bodyTpl = trim($_POST['email_body'] ?? '');   // NOT sanitize here

        $ids        = $_POST['client_ids'] ?? [];
        if ($subjectTpl==='' || $bodyTpl==='' || empty($ids)) {
            $flash = ['type'=>'danger','msg'=>'Subject, body, and at least one recipient are required.'];
        } else {
            [$sent,$failed,$errs] = [0,0,[]];
            if (preg_match('/^\d+$/',$gid)) {
                $gid = (int)$gid;
                $gmeta = $GROUP_META[$gid] ?? ['name'=>'','weekday'=>'','time'=>'','address'=>''];
            } elseif (preg_match('/^t4c:(.+)$/',$gid,$mm)) {
                $d = $mm[1]; $gmeta = ['name'=>"Thinking for a Change — ".ucfirst($d),'weekday'=>ucfirst($d),'time'=>'','address'=>''];
            } else { $gmeta = ['name'=>'','weekday'=>'','time'=>'','address'=>'']; }

            foreach ($ids as $cid) {
                $c = qone($con,"SELECT id, first_name, last_name, email, program_id, therapy_group_id, gender_id, case_manager_id
                                FROM client WHERE id=?",[(int)$cid],'i');
                if (!$c || empty($c['email'])) { $failed++; $errs[] = "Missing email for #$cid"; continue; }
                if (is_unsubscribed($con, $c['email'])) { $failed++; $errs[] = "Unsubscribed: {$c['email']}"; continue; }

                [$subj,$html] = expand_placeholders($c,$gmeta,$subjectTpl,$bodyTpl,$con);
                $html = sanitize_email_html($html);
                $to = $c['email'];

                if (send_html_mail($to,$subj,$html)) {
                    $sent++;
                    @qall($con,"INSERT INTO client_event (client_id, client_event_type_id, note) VALUES (?,1,?)",
                        [(int)$c['id'],'Automated reminder email sent (group view)'],'is');
                } else { $failed++; $errs[]="Failed: {$c['email']}"; }
            }

            $type = $failed ? 'warning' : 'success';
            $msg  = "Send completed. Sent: <b>$sent</b> • Failed: <b>$failed</b>";
            if ($failed && $errs) $msg .= '<br>'.htmlspecialchars(implode('; ',$errs));
            $flash = ['type'=>$type,'msg'=>$msg];
        }
    }

    if ($action === 'send_all') {
        $subjectTpl = trim($_POST['email_subject'] ?? '');
        $bodyTpl    = sanitize_email_html(trim($_POST['email_body'] ?? ''));
        if ($subjectTpl==='' || $bodyTpl==='') {
            $flash = ['type'=>'danger','msg'=>'Subject and body are required.'];
        } else {
            [$sent,$failed,$errs] = [0,0,[]];
            $clients = get_all_active_clients($con);
            $gmeta = ['name'=>'','weekday'=>'','time'=>'','address'=>'']; // generic

            foreach ($clients as $c) {
                if (empty($c['email'])) { $failed++; $errs[] = "Missing email for #{$c['id']}"; continue; }
                if (is_unsubscribed($con, $c['email'])) { $failed++; $errs[] = "Unsubscribed: {$c['email']}"; continue; }

                [$subj,$html] = expand_placeholders($c,$gmeta,$subjectTpl,$bodyTpl,$con);
                $html = sanitize_email_html($html);
                $to = $c['email'];

                if (send_html_mail($to,$subj,$html)) {
                    $sent++;
                    @qall($con,"INSERT INTO client_event (client_id, client_event_type_id, note) VALUES (?,1,?)",
                        [(int)$c['id'],'Automated reminder email sent (broadcast)'],'is');
                } else { $failed++; $errs[]="Failed: {$c['email']}"; }
            }

            $type = $failed ? 'warning' : 'success';
            $msg  = "Broadcast completed. Sent: <b>$sent</b> • Failed: <b>$failed</b>";
            if ($failed && $errs) $msg .= '<br>'.htmlspecialchars(implode('; ',$errs));
            $flash = ['type'=>$type,'msg'=>$msg];
        }
    }
}

/* -----------------------------------------------
   View router & data
-------------------------------------------------*/
$gid = $_GET['gid'] ?? '';
$allGroups = get_all_groups($con);
[$t4cByDay,$t4cVirtual] = get_t4c_day_rosters($con);
$order = day_ordering();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Reminders</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap + Icons -->
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"
        crossorigin="anonymous">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
        crossorigin="anonymous" />

  <style>
    .sticky-actions { position: sticky; top: 64px; z-index: 100; }
    .muted { color:#6c757d; }
    .card + .card { margin-top: 1rem; }
    .table-sm td, .table-sm th { padding: .35rem .5rem; }
    .select-all { cursor:pointer; }

    /* --- In-house WYSIWYG --- */
    .wysiwrap { border:1px solid #dee2e6; border-radius:.25rem; }
    .wysi-toolbar { display:flex; flex-wrap:wrap; gap:.25rem; padding:.35rem; border-bottom:1px solid #dee2e6; background:#f8f9fa; }
    .wysi-toolbar button { border:1px solid #ced4da; background:#fff; padding:.25rem .5rem; border-radius:.25rem; font-size:.875rem; }
    .wysi-editor { min-height:220px; padding:.5rem; outline:none; }
    .wysi-source { display:none; width:100%; min-height:220px; padding:.5rem; font-family:monospace; font-size:.9rem; }
    .wysiwrap.source .wysi-editor { display:none; }
    .wysiwrap.source .wysi-source { display:block; }
  </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container-fluid mt-3">
  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
      <?= $flash['msg'] ?>
    </div>
  <?php endif; ?>

  <?php if ($gid===''): /* ---------------- INDEX VIEW ---------------- */ ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="mb-0"><i class="fas fa-bell"></i> Group Reminders</h3>
      <div>
        <a href="client-index.php" class="btn btn-light">
          <i class="fas fa-home"></i> Home
        </a>
      </div>
    </div>

    <div class="row">
      <!-- Right: Broadcast compose -->
      <div class="col-lg-4 order-lg-2">
        <div class="card sticky-actions">
          <div class="card-header">
            <strong><i class="fas fa-bullhorn"></i> Compose to All Clients</strong>
          </div>
          <div class="card-body">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="send_all">
              <div class="form-group">
                <label>From</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars(defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@lakeview.notesao.com') ?>" readonly>
              </div>
              <div class="form-group">
                <label>Subject</label>
                <input type="text" class="form-control" name="email_subject"
                       value="<?= htmlspecialchars($DEF_SUBJECT_ALL) ?>">
              </div>

              <div class="form-group">
                <label>Body</label>
                <div class="wysiwrap" data-for="email_body_all">
                  <div class="wysi-toolbar">
                    <button type="button" data-cmd="bold"><i class="fas fa-bold"></i></button>
                    <button type="button" data-cmd="italic"><i class="fas fa-italic"></i></button>
                    <button type="button" data-cmd="underline"><i class="fas fa-underline"></i></button>
                    <button type="button" data-cmd="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
                    <button type="button" data-cmd="insertOrderedList"><i class="fas fa-list-ol"></i></button>
                    <button type="button" data-cmd="formatBlock" data-value="p">P</button>
                    <button type="button" data-cmd="formatBlock" data-value="h3">H3</button>
                    <button type="button" data-cmd="createLink">Link</button>
                    <button type="button" data-cmd="removeFormat">Clear</button>
                    <button type="button" data-toggle-source>Source</button>
                  </div>
                  <div class="wysi-editor" contenteditable="true"><?= $DEF_BODY_ALL ?></div>
                  <textarea class="wysi-source form-control"></textarea>
                </div>
                <!-- Hidden field actually submitted -->
                <textarea name="email_body" id="email_body_all" class="d-none"></textarea>

                <small class="muted">Tip: this blast is general. Group-specific placeholders (like {{group_day}}) may be empty.</small>
              </div>

              <button class="btn btn-primary btn-block"><i class="fas fa-paper-plane"></i> Send Broadcast</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Left: groups list (today-first) -->
      <div class="col-lg-8 order-lg-1">
        <?php
          // Organize DB groups by weekday + render
          $byDay=[0=>[],1=>[],2=>[],3=>[],4=>[],5=>[],6=>[]];
          foreach ($allGroups as $g) {
              if ((int)$g['program_id']===1 && (int)$g['id']===116) continue; // T4C virtual shown later
              [$idx,$wd,$tm] = parse_weekday_time($g['name']);
              if ($idx!==null) $byDay[$idx][] = $g;
          }

          foreach ($order as $didx) {
              $groupsForDay = $byDay[$didx] ?? [];
              $t4cKey = strtolower(day_name_from_idx($didx));
              $hasT4C = !empty($t4cByDay[$t4cKey]);

              if (empty($groupsForDay) && !$hasT4C) continue;

              echo '<div class="card"><div class="card-header d-flex justify-content-between align-items-center">';
              echo '<div><strong>'.htmlspecialchars(day_name_from_idx($didx)).'</strong></div>';
              echo '</div><div class="card-body p-0">';

              echo '<div class="list-group list-group-flush">';

              // T4C in-person pseudo group
              if ($hasT4C) {
                  $label = 'Thinking for a Change — '.ucfirst($t4cKey).' (In-Person)';
                  $gidPseudo = 't4c:'.$t4cKey;
                  echo '<div class="list-group-item d-flex justify-content-between align-items-center">';
                  echo '<div><i class="fas fa-users mr-2"></i>'.htmlspecialchars($label).'</div>';
                  echo '<div class="btn-group">';
                  echo '<a class="btn btn-sm btn-outline-primary" href="client-reminders.php?gid='.urlencode($gidPseudo).'"><i class="fas fa-folder-open"></i> Open</a>';
                  echo '<form method="post" class="ml-2 mb-0">';
                  echo '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token()).'">';
                  echo '<input type="hidden" name="action" value="quick">';
                  echo '<input type="hidden" name="gid" value="'.htmlspecialchars($gidPseudo).'">';
                  echo '<button class="btn btn-sm btn-outline-success"><i class="fas fa-bolt"></i> Quick Send</button>';
                  echo '</form>';
                  echo '</div></div>';
              }

              // Regular (non-T4C) groups
              foreach ($groupsForDay as $g) {
                  [$ix,$wd,$tm] = parse_weekday_time($g['name']);
                  $addr = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([$g['address']??'',$g['city']??'',$g['state']??'',$g['zip']??'']))));
                  echo '<div class="list-group-item d-flex justify-content-between align-items-center">';
                  echo '<div>';
                  echo '<div><i class="fas fa-users mr-2"></i><strong>'.htmlspecialchars($g['name']).'</strong>';
                  if ($tm) echo ' <span class="muted">('.htmlspecialchars($tm).')</span>';
                  echo '</div>';
                  if ($addr) echo '<div class="muted small"><i class="fas fa-map-marker-alt"></i> '.htmlspecialchars($addr).'</div>';
                  echo '</div>';
                  echo '<div class="btn-group">';
                  echo '<a class="btn btn-sm btn-outline-primary" href="client-reminders.php?gid='.(int)$g['id'].'"><i class="fas fa-folder-open"></i> Open</a>';
                  echo '<form method="post" class="ml-2 mb-0">';
                  echo '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token()).'">';
                  echo '<input type="hidden" name="action" value="quick">';
                  echo '<input type="hidden" name="gid" value="'.(int)$g['id'].'">';
                  echo '<button class="btn btn-sm btn-outline-success"><i class="fas fa-bolt"></i> Quick Send</button>';
                  echo '</form>';
                  echo '</div>';
                  echo '</div>';
              }

              echo '</div>'; // list-group
              echo '</div></div>'; // card-body, card
          }

          // T4C virtual (regular group id = 116)
          foreach ($allGroups as $g) {
              if ((int)$g['id'] !== 116) continue;
              [$ix,$wd,$tm] = parse_weekday_time($g['name']);
              $addr = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([$g['address']??'',$g['city']??'',$g['state']??'',$g['zip']??'']))));
              echo '<div class="card mt-3"><div class="card-header"><strong>T4C — Virtual</strong></div><div class="card-body p-0">';
              echo '<div class="list-group list-group-flush">';
              echo '<div class="list-group-item d-flex justify-content-between align-items-center">';
              echo '<div><i class="fas fa-video mr-2"></i><strong>'.htmlspecialchars($g['name']).'</strong>';
              if ($tm) echo ' <span class="muted">('.htmlspecialchars($tm).')</span>';
              if ($addr) echo '<div class="muted small"><i class="fas fa-map-marker-alt"></i> '.htmlspecialchars($addr).'</div>';
              echo '</div>';
              echo '<div class="btn-group">';
              echo '<a class="btn btn-sm btn-outline-primary" href="client-reminders.php?gid='.(int)$g['id'].'"><i class="fas fa-folder-open"></i> Open</a>';
              echo '<form method="post" class="ml-2 mb-0">';
              echo '<input type="hidden" name="csrf" value="'.htmlspecialchars(csrf_token()).'">';
              echo '<input type="hidden" name="action" value="quick">';
              echo '<input type="hidden" name="gid" value="116">';
              echo '<button class="btn btn-sm btn-outline-success"><i class="fas fa-bolt"></i> Quick Send</button>';
              echo '</form>';
              echo '</div></div>';
              echo '</div></div></div>';
          }
        ?>
      </div>
    </div>

  <?php else: /* ---------------- GROUP VIEW ---------------- */ ?>

    <?php
      $isT4C = false; $groupTitle=''; $gmeta=['name'=>'','weekday'=>'','time'=>'','address'=>'']; $clients=[]; $gidSafe=$gid;

      if (preg_match('/^\d+$/',$gid)) {
          $gidNum=(int)$gid;
          $g = qone($con,"SELECT id, program_id, name, address, city, state, zip FROM therapy_group WHERE id=?",[$gidNum],'i');
          if ($g) {
              [$ix,$wd,$tm]=parse_weekday_time($g['name']);
              $addr = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([$g['address']??'',$g['city']??'',$g['state']??'',$g['zip']??'']))));
              $gmeta = ['name'=>$g['name'],'weekday'=>$wd ?: '','time'=>$tm ?: '','address'=>$addr,'program_id'=>(int)$g['program_id']];
              $groupTitle = $g['name'];
              $clients = get_clients_for_group($con,$gidNum);
          } else {
              $groupTitle = 'Unknown Group #'.$gidNum;
          }
      } elseif (preg_match('/^t4c:(sunday|monday|tuesday|wednesday|thursday|friday|saturday)$/',$gid,$mm)) {
          $isT4C = true; $day=$mm[1];
          [$t4cByDay,$t4cVirtual] = get_t4c_day_rosters($con);
          $clients = $t4cByDay[$day] ?? [];
          $groupTitle = 'Thinking for a Change — '.ucfirst($day).' (In-Person)';
          $gmeta = ['name'=>$groupTitle,'weekday'=>ucfirst($day),'time'=>'','address'=>'','program_id'=>1];
      } else {
          $groupTitle = 'Unknown Group';
      }
    ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="mb-0"><i class="fas fa-users"></i> <?= htmlspecialchars($groupTitle) ?></h3>
        <?php if (!empty($gmeta['weekday']) || !empty($gmeta['time'])): ?>
          <div class="muted small">
            <?= htmlspecialchars(trim(($gmeta['weekday'] ? $gmeta['weekday'].' ' : '').($gmeta['time'] ?? ''))) ?>
            <?php if (!empty($gmeta['address'])): ?>
              • <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($gmeta['address']) ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <a href="client-reminders.php" class="btn btn-light"><i class="fas fa-arrow-left"></i> Back to Groups</a>
        <a href="client-index.php" class="btn btn-light"><i class="fas fa-home"></i> Home</a>
      </div>
    </div>

    <div class="row">
      <!-- Right: per-group editor -->
      <div class="col-lg-4 order-lg-2">
        <div class="card sticky-actions">
          <div class="card-header"><strong><i class="fas fa-envelope"></i> Compose Reminder (This Group)</strong></div>
          <div class="card-body">
            <form method="post" id="sendForm">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
              <input type="hidden" name="action" value="send_group">
              <input type="hidden" name="gid" value="<?= htmlspecialchars($gidSafe) ?>">
              <div class="form-group">
                <label>From</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars(defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@lakeview.notesao.com') ?>" readonly>
              </div>
              <div class="form-group">
                <label>Subject</label>
                <input type="text" class="form-control" name="email_subject"
                       value="<?= htmlspecialchars($DEF_SUBJECT) ?>">
                <small class="muted">Placeholders: {{first_name}}, {{last_name}}, {{program_name}},
                  {{group_name}}, {{group_day}}, {{group_time}}, {{meeting_location}}, {{group_link}}</small>
              </div>

              <div class="form-group">
                <label>Body</label>
                <div class="wysiwrap" data-for="email_body_group">
                  <div class="wysi-toolbar">
                    <button type="button" data-cmd="bold"><i class="fas fa-bold"></i></button>
                    <button type="button" data-cmd="italic"><i class="fas fa-italic"></i></button>
                    <button type="button" data-cmd="underline"><i class="fas fa-underline"></i></button>
                    <button type="button" data-cmd="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
                    <button type="button" data-cmd="insertOrderedList"><i class="fas fa-list-ol"></i></button>
                    <button type="button" data-cmd="formatBlock" data-value="p">P</button>
                    <button type="button" data-cmd="formatBlock" data-value="h3">H3</button>
                    <button type="button" data-cmd="createLink">Link</button>
                    <button type="button" data-cmd="removeFormat">Clear</button>
                    <button type="button" data-toggle-source>Source</button>
                  </div>
                  <div class="wysi-editor" contenteditable="true"><?= $DEF_BODY ?></div>
                  <textarea class="wysi-source form-control"></textarea>
                </div>
                <!-- Hidden field actually submitted -->
                <textarea name="email_body" id="email_body_group" class="d-none"></textarea>
              </div>

              <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-paper-plane"></i> Send to Selected
              </button>
              <div class="small muted mt-2">Select recipients from the roster.</div>
            </form>
          </div>
        </div>
      </div>

      <!-- Left: roster -->
      <div class="col-lg-8 order-lg-1">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div><strong>Roster</strong></div>
            <div class="select-all text-primary" data-target="grp-roster"><i class="far fa-check-square"></i> Select all</div>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                  <tr><th style="width:36px;"></th><th>Client</th><th>Email</th></tr>
                </thead>
                <tbody id="grp-roster">
                  <?php if (!empty($clients)): foreach ($clients as $c): ?>
                    <tr>
                      <td><input type="checkbox" class="client-check" name="client_ids[]" form="sendForm" value="<?= (int)$c['id'] ?>"></td>
                      <td><?= htmlspecialchars($c['last_name'].', '.$c['first_name']) ?></td>
                      <td><?= htmlspecialchars($c['email']) ?></td>
                    </tr>
                  <?php endforeach; else: ?>
                    <tr><td></td><td colspan="2"><em>No active clients with email</em></td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="mt-3">
          <form method="post" onsubmit="return confirm('Send default reminder to ALL clients in this group?');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="quick">
            <input type="hidden" name="gid" value="<?= htmlspecialchars($gidSafe) ?>">
            <button class="btn btn-outline-success">
              <i class="fas fa-bolt"></i> Quick Send Default to Entire Group
            </button>
          </form>
        </div>
      </div>
    </div>

  <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

<script>
// In-house WYSIWYG bootstrap
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.wysiwrap').forEach(function (wrap) {
    const editor = wrap.querySelector('.wysi-editor');
    const source = wrap.querySelector('.wysi-source');
    const toolbar = wrap.querySelector('.wysi-toolbar');
    const hiddenName = wrap.getAttribute('data-for');
    const hidden = document.getElementById(hiddenName);

    function toSource() { source.value = editor.innerHTML; }
    function toEditor() { editor.innerHTML = source.value; }

    toolbar.addEventListener('click', function (e) {
      const btn = e.target.closest('button');
      if (!btn) return;

      if (btn.hasAttribute('data-toggle-source')) {
        if (wrap.classList.contains('source')) { // back to WYSIWYG
          toEditor();
          wrap.classList.remove('source');
        } else {
          toSource();
          wrap.classList.add('source');
        }
        return;
      }

      const cmd = btn.getAttribute('data-cmd');
      if (!cmd) return;

      if (cmd === 'createLink') {
        const url = prompt('Enter URL (https:// or mailto:):','https://');
        if (url) document.execCommand('createLink', false, url);
        return;
      }
      if (cmd === 'formatBlock') {
        const val = btn.getAttribute('data-value') || 'p';
        document.execCommand('formatBlock', false, val);
        return;
      }
      document.execCommand(cmd, false, null);
    });

    // On form submit, copy editor HTML into hidden textarea actually posted
    const form = wrap.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        if (wrap.classList.contains('source')) toEditor(); // ensure editor reflects source
        if (hidden) hidden.value = editor.innerHTML;
      });
    }
  });

  // Select-all for roster checkboxes (group view)
  document.querySelectorAll('.select-all').forEach(function (el) {
    el.addEventListener('click', function () {
      const targetId = el.getAttribute('data-target');
      const box = document.getElementById(targetId);
      if (!box) return;
      const anyUnchecked = Array.from(box.querySelectorAll('input.client-check')).some(i => !i.checked);
      box.querySelectorAll('input.client-check').forEach(i => { i.checked = anyUnchecked; });
    });
  });
});
</script>
</body>
</html>
