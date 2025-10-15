<?php
/** evaluations-pai.php — PAI (Personality Assessment Inventory) Response Booklet
 * GET: render multi-step form (demographics + items with full text)
 * POST: validate + insert into evaluations_pai
 */

declare(strict_types=1);
ob_start();
session_start();
require_once dirname(__DIR__) . '/config/config.php';   // should provide $link OR $con
$mysqli = isset($link) ? $link : (isset($con) ? $con : null);
if (!$mysqli) { http_response_code(500); die('DB connection not found.'); }

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_check(): void {
  if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf_token'])) {
    http_response_code(403); exit('Invalid CSRF token');
  }
}
function postv(string $k): ?string {
  if (!isset($_POST[$k])) return null; $v = $_POST[$k];
  if (is_string($v)) { $v = trim($v); return $v === '' ? null : $v; }
  return null;
}
function post_enum(string $k, array $allowed): ?string {
  $v = postv($k); if ($v === null) return null; return in_array($v, $allowed, true) ? $v : null;
}
function padq(int $n): string { return sprintf('q%03d', $n); }
function old(string $k, ?string $default = ''): string { return htmlspecialchars((string)($_POST[$k] ?? $_GET[$k] ?? $default), ENT_QUOTES, 'UTF-8'); }

$PAI_CHOICES = ['F','ST','MT','VT']; // False / Slightly True / Mainly True / Very True
$GENDERS = ['Male','Female'];
$MARITALS = ['Single','Married','Divorced','Widowed','Other'];
$COURTS = ["Veteran's Treatment Court","Mental Health Treatment Court"];

// PAI item text (1..344)
$PAI_ITEMS = [
  1 => 'My friends are available if I need them.',
  2 => 'I have some inner struggles that cause problems for me.',
  3 => 'My health condition has restricted my activities.',
  4 => 'I am so tense in certain situations that I have great difficulty getting by.',
  5 => 'I have to do some things a certain way or I get nervous.',
  6 => "Much of the time I'm sad for no real reason.",
  7 => 'Often I think and talk so quickly that other people cannot follow my train of thought.',
  8 => 'Most of the people I know can be trusted.',
  9 => 'Sometimes I cannot remember who I am.',
  10 => 'I have some ideas that others think are strange.',
  11 => 'I was usually well-behaved at school.',
  12 => "I've seen a lot of doctors over the years.",
  13 => "I'm a very sociable person.",
  14 => 'My mood can shift quite suddenly.',
  15 => 'Sometimes I feel guilty about how much I drink.',
  16 => "I'm a 'take charge' type of person.",
  17 => 'My attitude about myself changes a lot.',
  18 => 'People would be surprised if I yelled at someone.',
  19 => 'My relationships have been stormy.',
  20 => 'At times I wish I were dead.',
  21 => 'People are afraid of my temper.',
  22 => "Sometimes I use drugs to feel better.",
  23 => "I've tried just about every type of drug.",
  24 => "Sometimes I let little things bother me too much.",
  25 => "I often have trouble concentrating because I'm nervous.",
  26 => "I often fear I might slip up and say something wrong.",
  27 => "I feel that I've let everyone down.",
  28 => "I have many brilliant ideas.",
  29 => 'Certain people go out of their way to bother me.',
  30 => "I just don't seem to relate to people very well.",
  31 => "I've borrowed money knowing I wouldn't pay it back.",
  32 => "Much of the time I don't feel well.",
  33 => 'I often feel jittery.',
  34 => "I keep reliving something horrible that happened to me.",
  35 => 'I hardly have any energy.',
  36 => "I can be very demanding when I want things done quickly.",
  37 => "People usually treat me pretty fairly.",
  38 => 'My thinking has become confused.',
  39 => 'I get a kick out of doing dangerous things.',
  40 => 'My favorite poet is Raymond Kertezc.',
  41 => 'I like being around my family.',
  42 => "I need to make some important changes in my life.",
  43 => "I've had illnesses that my doctors could not explain.",
  44 => "I can't do some things well because of nervousness.",
  45 => 'I have impulses that I fight to keep under control.',
  46 => "I've forgotten what it's like to feel happy.",
  47 => "I take on so many commitments that I can't keep up.",
  48 => "I have to be alert to the possibility that people will be unfaithful.",
  49 => 'I have visions in which I see myself forced to commit crimes.',
  50 => 'Other people sometimes put thoughts into my head.',
  51 => "I've deliberately damaged someone's property.",
  52 => 'My health problems are very complicated.',
  53 => "It's easy for me to make new friends.",
  54 => 'My moods get quite intense.',
  55 => 'I have trouble controlling my use of alcohol.',
  56 => "I'm a natural leader.",
  57 => "Sometimes I feel terribly empty inside.",
  58 => 'I tell people off when they deserve it.',
  59 => "I want to let certain people know how much they've hurt me.",
  60 => "I've thought about ways to kill myself.",
  61 => 'Sometimes my temper explodes and I completely lose control.',
  62 => 'People have told me that I have a drug problem.',
  63 => "I never use drugs to help me cope with the world.",
  64 => "Sometimes I'll avoid someone I really don't like.",
  65 => "It's often hard for me to enjoy myself because I am worrying about things.",
  66 => "I have exaggerated fears.",
  67 => "Sometimes I think I'm worthless.",
  68 => 'I have some very special talents that few others have.',
  69 => 'Some people do things to make me look bad.',
  70 => "I don't have much to say to anyone.",
  71 => "I'll take advantage of others if they leave themselves open to it.",
  72 => 'I suffer from a lot of pain.',
  73 => "I worry so much that at times I feel like I am going to faint.",
  74 => "Thoughts about my past often bother me while I'm thinking about something else.",
  75 => 'I have no trouble falling asleep.',
  76 => 'I get quite irritated if people try to keep me from accomplishing my goals.',
  77 => 'I seem to have as much luck in life as others do.',
  78 => 'My thoughts get scrambled sometimes.',
  79 => "I do a lot of wild things just for the thrill of it.",
  80 => "Sometimes I get ads in the mail that I don't really want.",
  81 => "If I'm having problems, I have people I can talk to.",
  82 => "I need to change some things about myself, even if it hurts.",
  83 => "I've had numbness in parts of my body that I can't explain.",
  84 => 'Sometimes I am afraid for no reason.',
  85 => 'It bothers me when things are out of place.',
  86 => 'Everything seems like a big effort.',
  87 => "Recently I've had much more energy than usual.",
  88 => 'Most people have good intentions.',
  89 => 'Since the day I was born, I was destined to be unhappy.',
  90 => "Sometimes it seems that my thoughts are broadcast so that others can hear them.",
  91 => "I've done some things that weren't exactly legal.",
  92 => "It's a struggle for me to get things done with the medical problems I have.",
  93 => 'I like to meet new people.',
  94 => 'My mood is very steady.',
  95 => "There have been times when I've had to cut down on my drinking.",
  96 => "I would be good at a job where I tell others what to do.",
  97 => "I worry a lot about other people leaving me.",
  98 => 'When I get mad at other drivers on the road, I let them know.',
  99 => "People once close to me have let me down.",
  100 => "I've made plans about how to kill myself.",
  101 => "Sometimes I'm very violent.",
  102 => 'My drug use has caused me financial strain.',
  103 => "I've never had problems at work because of drugs.",
  104 => 'I sometimes complain too much.',
  105 => "I'm often so worried and nervous that I can barely stand it.",
  106 => 'I get very nervous when I have to do something in front of others.',
  107 => "I don't feel like trying anymore.",
  108 => 'My plans will make me famous someday.',
  109 => 'People around me are faithful to me.',
  110 => "I'm a loner.",
  111 => "I'll do most things if the price is right.",
  112 => 'I am in good health.',
  113 => "Sometimes I feel dizzy when I've been under a lot of pressure.",
  114 => "I've been troubled by memories of a bad experience for a long time.",
  115 => 'I rarely have trouble sleeping.',
  116 => "Sometimes I get upset because others don't understand my plans.",
  117 => "I've given a lot, but I haven't gotten much in return.",
  118 => 'Sometimes I have trouble keeping different thoughts separate.',
  119 => 'My behavior is pretty wild at times.',
  120 => 'My favorite sports event on television is the high jump.',
  121 => 'I spend most of my time alone.',
  122 => 'I need some help to deal with important problems.',
  123 => "I've had episodes of double vision or blurred vision.",
  124 => "I'm not the kind of person who panics easily.",
  125 => "I can relax even if my home is a mess.",
  126 => 'Nothing seems to give me much pleasure.',
  127 => 'At times my thoughts move very quickly.',
  128 => "I usually assume people are telling the truth.",
  129 => 'I think I have three or four completely different personalities inside of me.',
  130 => 'Others can read my thoughts.',
  131 => 'I used to lie a lot to get out of tight situations.',
  132 => 'My medical problems always seem to be hard to treat .',
  133 => 'I am a warm person.',
  134 => 'I have little control over my anger.',
  135 => 'My drinking seems to cause problems in my relationships with others.',
  136 => 'I have trouble standing up for myself.',
  137 => "I often wonder what I should do with my life.",
  138 => "I'm not afraid to yell at someone to get my point across.",
  139 => 'I rarely feel very lonely.',
  140 => "I've recently been thinking about suicide.",
  141 => "Sometimes I smash things when I'm upset.",
  142 => 'I never use illegal drugs.',
  143 => 'I sometimes do things so impulsively that I get into trouble.',
  144 => "Sometimes I'm too impatient.",
  145 => 'My friends say I worry too much.',
  146 => "I'm not easily frightened.",
  147 => "I can't seem to concentrate very well.",
  148 => "I have accomplished some remarkable things.",
  149 => 'Some people try to keep me from getting ahead.',
  150 => "I don't feel close to anyone.",
  151 => 'I can talk my way out of just about anything.',
  152 => 'I seldom have complaints about how I feel physically.',
  153 => "I can often feel my heart pounding.",
  154 => "I can't seem to get over something from my past.",
  155 => "I've been moving more slowly than usual.",
  156 => 'I have great plans and it irritates me that people try to interfere.',
  157 => "People don't appreciate what I've done for them.",
  158 => 'Sometimes it feels as if somebody is blocking my thoughts.',
  159 => 'If I get tired of a place, I just pick up and leave.',
  160 => 'Most people would rather win than lose.',
  161 => "Most people I'm close to are very supportive.",
  162 => "I'm curious why I behave the way I do.",
  163 => 'There have been times when my eyesight got worse and then better again.',
  164 => 'I am a very calm and relaxed person.',
  165 => "People say that I'm a perfectionist.",
  166 => "I've lost interest in things I used to enjoy.",
  167 => "My friends can't keep up with my social activities.",
  168 => "People generally hide their real motives.",
  169 => "People don't understand how much I suffer.",
  170 => "I've heard voices that no one else could hear.",
  171 => 'I like to see how much I can get away with.',
  172 => "I've had only the usual health problems that most people have.",
  173 => 'It takes me a while to warm up to people.',
  174 => "I've always been a pretty happy person.",
  175 => 'Drinking helps me get along in social situations.',
  176 => "I feel best in situations where I am the leader.",
  177 => "I can't handle separation from those close to me very well.",
  178 => 'I always avoid arguments if I can.',
  179 => "I've made some real mistakes in the people I've picked as friends.",
  180 => 'I have thought about suicide for a long time.',
  181 => "I've threatened to hurt people.",
  182 => "I've used prescription drugs to get high.",
  183 => "When I'm upset, I typically do something to hurt myself.",
  184 => "I don't take criticism very well.",
  185 => "I don't worry about things any more than most people.",
  186 => "I don't mind driving on freeways.",
  187 => 'No matter what I do, nothing works.',
  188 => 'I think I have the answers to some very important questions.',
  189 => 'There are people who want to hurt me.',
  190 => 'I enjoy the company of other people.',
  191 => "I don't like being tied to one person.",
  192 => "I have a bad back.",
  193 => "It's easy for me to relax.",
  194 => 'I have had some horrible experiences that make me feel guilty.',
  195 => "I often wake up very early in the morning and can't get back to sleep.",
  196 => "It bothers me when other people are too slow to understand my ideas.",
  197 => "Usually I've gotten credit for what I've done.",
  198 => "My thoughts tend to quickly shift around to different things.",
  199 => 'The idea of "settling down" has never appealed to me.',
  200 => "My favorite hobbies are archery and stamp-collecting.",
  201 => 'People I know care about me.',
  202 => "I'm comfortable with myself the way I am.",
  203 => "I've had episodes when I've lost the feeling in my hands.",
  204 => 'I often feel as if something terrible is about to happen.',
  205 => "I'm usually aware of objects that have a lot of germs.",
  206 => "I have no interest in life.",
  207 => "I feel like I need to keep active and not rest.",
  208 => "People think I'm too suspicious.",
  209 => 'Every once in a while I totally lose my memory.',
  210 => 'There are people who try to control my thoughts.',
  211 => 'I was never expelled or suspended from school when I was young.',
  212 => "I've had some unusual diseases and illnesses.",
  213 => 'It takes a while for people to get to know me.',
  214 => "I've had times when I was so mad I couldn't do enough to express all my anger.",
  215 => 'Some people around me think I drink too much alcohol.',
  216 => 'I prefer to let others make decisions.',
  217 => "I don't get bored very easily.",
  218 => "I don't like raising my voice.",
  219 => "Once someone is my friend, we stay friends.",
  220 => 'Death would be a relief.',
  221 => "I've never started a physical fight as an adult.",
  222 => 'My drug use is out of control.',
  223 => "I'm too impulsive for my own good.",
  224 => 'Sometimes I put things off until the last minute.',
  225 => "I don't worry about things that I can't control.",
  226 => "I don't mind heights.",
  227 => 'I think good things will happen to me in the future.',
  228 => 'I think I would be a good comedian.',
  229 => "People seldom treat me badly on purpose.",
  230 => 'I like to be around other people if I can.',
  231 => "I don't like to stay in a relationship very long.",
  232 => 'I have a weak stomach.',
  233 => "When I'm under a lot of pressure, I sometimes have trouble breathing.",
  234 => 'I keep having nightmares about my past.',
  235 => 'I have a good appetite.',
  236 => 'I have no patience with people who try to hold me back.',
  237 => 'People who are successful generally earned their success.',
  238 => 'Sometimes I wonder if my thoughts are being taken away.',
  239 => 'I like to drive fast.',
  240 => "I don't like to have to buy things that are overpriced.",
  241 => 'In my family, we argue more than we talk.',
  242 => 'Many of my problems are my own doing.',
  243 => "I've had times when my legs became so weak that I couldn't walk.",
  244 => 'I seldom feel anxious or tense.',
  245 => "People see me as a person who pays a lot of attention to detail.",
  246 => "Lately I've been happy much of the time.",
  247 => 'Recently I have needed less sleep than usual.',
  248 => "Things are rarely as they seem on the surface.",
  249 => 'Sometimes my vision is only in black and white.',
  250 => "I have a sixth sense that tells me what is going to happen.",
  251 => "I've never been in trouble with the law.",
  252 => 'For my age, my health is pretty good.',
  253 => 'I try to include people who seem left out.',
  254 => 'Sometimes I have an alcoholic drink first thing in the morning.',
  255 => "My drinking has caused me problems at home.",
  256 => "I say what's on my mind.",
  257 => 'I usually do what other people tell me to do.',
  258 => 'I have a bad temper.',
  259 => "It takes a lot to make me angry.",
  260 => "I've thought about what I would say in a suicide note.",
  261 => "I can't think of reasons to go on living.",
  262 => "I've had health problems because of my drug use.",
  263 => "I spend money too easily.",
  264 => "I sometimes make promises I can't keep.",
  265 => 'I usually worry about things more than I should.',
  266 => "I will not ride in airplanes.",
  267 => "I have something worthwhile to contribute.",
  268 => 'Lately I feel so confident that I think I can accomplish anything.',
  269 => 'People have had it in for me.',
  270 => 'I make friends easily.',
  271 => "I look after myself first; let others take care of themselves.",
  272 => 'I get more headaches than most people.',
  273 => 'I get sweaty hands often.',
  274 => "Since I had a very bad experience, I am no longer interested in some things that I used to enjoy.",
  275 => 'I often wake up in the middle of the night.',
  276 => 'At times I am very touchy and easily annoyed.',
  277 => "I'm not the type of person to hold a grudge.",
  278 => 'Thoughts in my head suddenly disappear.',
  279 => "I'm not a person who turns down a dare.",
  280 => "Most people look forward to a trip to the dentist.",
  281 => 'I spend little time with my family.',
  282 => 'I can solve my problems by myself.',
  283 => 'At times parts of my body have been paralyzed.',
  284 => "I am easily startled.",
  285 => "I keep myself under tight control.",
  286 => "I'm almost always a happy and positive person.",
  287 => 'I hardly ever buy things on impulse.',
  288 => 'People have to earn my trust.',
  289 => "I don't have any good memories from my childhood.",
  290 => "I don't believe that there are people who can read minds.",
  291 => "I've never taken money or property that wasn't mine.",
  292 => 'I like to talk with people about their medical problems.',
  293 => "I'm an affectionate person.",
  294 => "I never drive when I've been drinking.",
  295 => "I hardly ever drink alcohol.",
  296 => 'People listen to my opinions.',
  297 => "If I get poor service from a business, I let the manager know about it.",
  298 => 'My temper never gets me into trouble.',
  299 => "My anger never gets out of control.",
  300 => "I've thought about how others would react if I killed myself.",
  301 => 'I have a lot to live for.',
  302 => 'My best friends are those I use drugs with.',
  303 => "I'm a reckless person.",
  304 => 'There have been times when I could have been more thoughtful than I was.',
  305 => "Sometimes I get so nervous that I'm afraid I'm going to die.",
  306 => "I don't mind traveling in a bus or train.",
  307 => "I'm pretty successful at what I do.",
  308 => 'I could never imagine myself being famous.',
  309 => "I'm the target of a conspiracy.",
  310 => 'I keep in touch with my friends.',
  311 => "When I make a promise, I really don't need to keep it.",
  312 => 'I frequently have diarrhea.',
  313 => 'I have very steady hands.',
  314 => 'I avoid certain things that bring back bad memories.',
  315 => 'I have little interest in sex.',
  316 => 'I have little patience with those who disagree with my plans.',
  317 => 'Being helpful to other people pays off in the end.',
  318 => 'I can concentrate now as well as I ever could.',
  319 => 'I never take risks if I can avoid it.',
  320 => 'In my free time I might read, watch TV, or just relax.',
  321 => 'I have a lot of money problems.',
  322 => 'My life is very unpredictable.',
  323 => 'There have been many changes in my life recently.',
  324 => "There isn't much stability at home.",
  325 => "Things are not going well in my family.",
  326 => "I'm happy with my job situation.",
  327 => "I worry about having enough money to get by.",
  328 => 'My relationship with my spouse or partner is not going well.',
  329 => 'I have severe psychological problems that began very suddenly.',
  330 => "I'm a sympathetic person.",
  331 => 'Close relationships are important to me.',
  332 => "I'm very impatient with people.",
  333 => 'I have more friends than most people I know.',
  334 => 'My drinking has never gotten me into trouble.',
  335 => "My drinking has caused problems with my work.",
  336 => "I don't like letting people know when I disagree with them.",
  337 => "I'm a very independent person.",
  338 => "When I get mad, it's hard for me to calm down.",
  339 => "People think I'm aggressive.",
  340 => "I'm considering suicide.",
  341 => 'Things have never been so bad that I thought about suicide.',
  342 => "My drug use has never caused problems with my family or friends.",
  343 => "I'm careful about how I spend my money.",
  344 => 'I rarely get in a bad mood.',
];

$errors = [];
$insert_ok = false;
$record_id = null;

// ──────────────────────────────────────────────────────────────────────────────
// Handle POST
// ──────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // Demographics
  $client_id         = postv('client_id');
  $submitted_by_user = postv('submitted_by_user_id');
  $name_first        = postv('name_first');
  $name_middle       = postv('name_middle');
  $name_last         = postv('name_last');
  $date_of_birth     = postv('date_of_birth');
  $age               = postv('age');
  $email             = postv('email');
  $referring_court   = post_enum('referring_court', $COURTS);
  $gender            = post_enum('gender', $GENDERS);
  $marital_status    = post_enum('marital_status', $MARITALS);
  $education_label   = postv('education_label');
  $education_years   = postv('education_years');
  $occupation        = postv('occupation');
  $todays_date       = postv('todays_date');

  // Required demographic fields
  if (!$name_first)    $errors['name_first']    = 'First name is required.';
  if (!$name_last)     $errors['name_last']     = 'Last name is required.';
  if (!$date_of_birth) $errors['date_of_birth'] = 'Date of birth is required.';

  // Server-side fallback to set education_years from label if missing
  $edu_map = [
    '8th Grade'=>8,'9th Grade'=>9,'10th Grade'=>10,'11th Grade'=>11,
    'High School Graduate (12)'=>12,'GED'=>12,'Some College'=>13,
    'Associates (14)'=>14,"Associate's Degree"=>14,
    'Bachelors (16)'=>16,'Bachelor\'s Degree (16)'=>16,
    'Masters (18)'=>18,'Master\'s Degree'=>18,
    'Doctorate (20+)'=>20,'Doctorate / Professional (PhD/MD/JD)'=>20
  ];
  if ($education_label && !$education_years && isset($edu_map[$education_label])) {
    $education_years = (string)$edu_map[$education_label];
  }

  // Items
  $items = [];
  for ($i=1; $i<=344; $i++) { $k = padq($i); $items[$k] = post_enum($k, $PAI_CHOICES); }
  $missing = array_keys(array_filter($items, fn($v) => $v === null));
  if (count($missing) > 0) {
    $errors['items'] = 'Please answer all items. Missing: ' . implode(', ', $missing);
  }

  if (!$errors) {
    // Build insert
    $cols = ['client_id','submitted_by_user_id','form_key','form_id','name_first','name_middle','name_last','date_of_birth','age','email','referring_court','gender','marital_status','education_label','education_years','occupation','todays_date'];
    for ($i=1; $i<=344; $i++) { $cols[] = padq($i); }
    $cols[] = 'submitted_at';

    $placeholders = '(' . rtrim(str_repeat('?,', count($cols)), ',') . ')';
    $sql = 'INSERT INTO `evaluations_pai` (' . implode(',', $cols) . ') VALUES ' . $placeholders;

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      $errors['db_prepare'] = 'Prepare failed: ' . $mysqli->error;
    } else {
      $form_key = 'pai'; $form_id = 14;
      $params = [];
      $params[] = $client_id ? (int)$client_id : null;             // i|null
      $params[] = $submitted_by_user ? (int)$submitted_by_user : null; // i|null
      $params[] = $form_key;                                        // s
      $params[] = $form_id;                                         // i
      $params[] = $name_first;                                      // s*
      $params[] = $name_middle;                                     // s?
      $params[] = $name_last;                                       // s*
      $params[] = $date_of_birth;                                   // s(date)
      $params[] = $age !== null ? (int)$age : null;                 // i|null
      $params[] = $email;                                           // s?
      $params[] = $referring_court;                                 // s?
      $params[] = $gender;                                          // s?
      $params[] = $marital_status;                                  // s?
      $params[] = $education_label;                                 // s?
      $params[] = $education_years !== null ? (int)$education_years : null; // i|null
      $params[] = $occupation;                                      // s?
      $params[] = $todays_date;                                     // s(date)
      foreach ($items as $v) { $params[] = $v; }                    // 344 × (s)
      $params[] = date('Y-m-d H:i:s');                              // s

      // Build mysqli bind types. Use 'i' for ints, 's' for strings/dates; NULLs are passed as null bound to their type.
      $types = '';
      foreach ($params as $p) {
        $types .= is_int($p) ? 'i' : 's';
      }

      // bind_param requires references
      $bind = [$types];
      foreach ($params as $i => $v) { $bind[] = &$params[$i]; }
      call_user_func_array([$stmt, 'bind_param'], $bind);

      if ($stmt->execute()) { $insert_ok = true; $record_id = $stmt->insert_id; }
      else { $errors['db_execute'] = 'Execute failed: ' . $stmt->error; }
      $stmt->close();
    }
  }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PAI Response Booklet — NotesAO</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    :root{ --nx-bg:#0f172a; --nx-fg:#e5e7eb; --nx-accent:#1d4ed8; }
    body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Liberation Sans', sans-serif; }
    .np-header { background: var(--nx-bg); color: var(--nx-fg); padding:.85rem 0; border-bottom: 3px solid var(--nx-accent); }
    .np-title { font-weight:700; letter-spacing:.2px; }
    .np-sub { opacity:.85; }

    .step { display:none; }
    .step.active { display:block; }
    .qgrid { display:grid; grid-template-columns: minmax(360px,1fr) repeat(4, auto); gap: .5rem 1rem; align-items:center; }
    .qgrid .hdr { font-weight:600; color:#374151; }
    .qrow { border-bottom: 1px dashed #e5e7eb; padding:.4rem 0; }
    .sticky-actions { position: sticky; bottom: 0; padding:.75rem 0; background: #fff; border-top: 1px solid #e5e7eb; margin-top: 1rem; }
    .required { color:#dc2626; }
    .progress { height: 10px; }
    .legend-badge { font-size:.8rem; border:1px solid #d1d5db; border-radius:.5rem; padding:.1rem .5rem; }

    .block-section { background:#fff; border:1px solid #e5e7eb; border-radius:.75rem; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .block-head { border-bottom:1px solid #e5e7eb; padding:.85rem 1rem; font-weight:600; background:#f8fafc; border-top-left-radius:.75rem; border-top-right-radius:.75rem; }
    .block-body { padding:1rem; }
    .help-callout { background:#f1f5f9; border:1px solid #e2e8f0; border-left:4px solid var(--nx-accent); border-radius:.5rem; padding:.8rem 1rem; }
    /* PAI compact table layout */
    .pai-table { table-layout: fixed; width: 100%; }
    .pai-col-item   { width: 68%; }        /* question text */
    .pai-col-choice { width: 8%;  }        /* four equal columns ≈ 32% */
    .pai-table th, .pai-table td { padding: .35rem .4rem; }
    .pai-table thead th { font-weight: 600; font-size: .9rem; text-align: center; }
    .pai-table td:first-child { white-space: normal; word-break: break-word; line-height: 1.35; }
    .pai-table .form-check-input { margin: 0; transform: scale(0.95); }
    .pai-table tbody tr:nth-child(odd)  { background-color: #ffffff; }
    .pai-table tbody tr:nth-child(even) { background-color: #f1f5f9; }
    .pai-table td.text-center { vertical-align: middle; }
    /* Make the choice cells clickable squares */
    .pai-table td.choice-cell {
    cursor: pointer;
    text-align: center;
    vertical-align: middle;
    padding: 0;
    }

    .pai-table td.choice-cell input[type="radio"] {
    /* hide the default tiny circle */
    opacity: 0;
    position: absolute;
    }

    .pai-table td.choice-cell label {
    display: block;
    width: 100%;
    height: 100%;
    padding: 1.2rem 0;   /* adjust to make the squares taller/shorter */
    margin: 0;
    cursor: pointer;
    background: #ffffff;
    transition: background 0.2s, box-shadow 0.2s;
    }

    .pai-table td.choice-cell label:hover {
    background: #e0e7ff; /* hover highlight */
    }

    .pai-table td.choice-cell input[type="radio"]:checked + label {
    background: #1d4ed8;   /* filled when selected */
    color: #fff;
    font-weight: 600;
    }
    /* keep header visible */
    .np-header { position: sticky; top: 0; z-index: 1030;
    background: linear-gradient(180deg, #0f172a 0%, #111827 100%);
    border-bottom: 3px solid #1d4ed8; /* accent line you already had */
    }

    /* layout spacing */
    .np-header .container { gap: .75rem; }

    /* logo chip: makes the white background intentional */
    .np-logo-chip {
    display: inline-flex;
    align-items: center;
    padding: .35rem .5rem;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: .5rem;
    box-shadow: 0 3px 10px rgba(0,0,0,.08);
    }
    .np-logo-chip img {
    display: block;
    max-height: 36px;   /* tweak as needed */
    width: auto;
    height: auto;
    }

    /* small screens: shrink chip */
    @media (max-width: 576px) {
    .np-logo-chip { padding: .25rem .4rem; }
    .np-logo-chip img { max-height: 30px; }
    }

    /* Mini answer-key bubble shown under header after scrolling */
    .mini-key {
    position: fixed;
    left: 50%;
    transform: translateX(-50%);
    top: 72px;                 /* JS will adjust this to match header height */
    z-index: 1040;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    padding: .35rem .75rem;
    box-shadow: 0 8px 24px rgba(0,0,0,.10);
    display: none;             /* hidden by default */
    font-size: .9rem;
    white-space: nowrap;
    }
    .mini-key .legend-badge {
    border-radius: .35rem;
    padding: .05rem .45rem;
    font-size: .8rem;
    border: 1px solid #d1d5db;
    }
    .mini-key .hint { color:#6b7280; margin-left:.5rem; }
    @media (max-width: 576px) {
    .mini-key { font-size: .85rem; padding: .30rem .6rem; }
    .mini-key .legend-badge { font-size: .75rem; }
    }



  </style>
</head>
<body class="bg-light">

<?php if (file_exists(__DIR__ . '/includes/header.php')) { include __DIR__ . '/includes/header.php'; } ?>
<?php if (file_exists(__DIR__ . '/includes/favicon-switch.php')) { include __DIR__ . '/includes/favicon-switch.php'; } ?>

<div class="np-header">
  <div class="container d-flex align-items-center justify-content-between">
    <div>
      <div class="np-title h5 mb-0">Free for Life Group — Personality Assessment Inventory</div>
      <div class="np-sub small">NotesAO Form</div>
    </div>

    <!-- company logo on the right -->
    <a href="https://freeforlifegroup.com" target="_blank" rel="noopener" class="np-logo-chip">
      <img src="ffllogo.png" alt="Free for Life Group">
    </a>

  </div>
</div>

<!-- Mini answer-key bubble (appears after scrolling past full key) -->
<div id="miniKey" class="mini-key" aria-hidden="true">
  <span class="legend-badge">F</span> False,
  <span class="legend-badge">ST</span> Slightly True,
  <span class="legend-badge">MT</span> Mainly True,
  <span class="legend-badge">VT</span> Very True.
</div>


<div class="container py-3">
  <div class="progress mb-3"><div id="progressBar" class="progress-bar" role="progressbar" style="width:0%"></div></div>

  <div id="directions-callout" class="help-callout mb-3">
    <strong>DIRECTIONS</strong>
    <div class="mt-1">COMPLETE THE FOLLOWING 8 STEPS.</div>
    <ol class="mt-2 mb-0">
      <li>Fill in your name and birth date.</li>
      <li>Write your age in the boxes and fill in the correct circles.</li>
      <li>Fill in the circle for your gender.</li>
      <li>Fill in the circle for your marital status.</li>
      <li>Fill in the circle that represents the number of years of formal education you have completed.</li>
      <li>Fill in your occupation.</li>
      <li>Fill in today’s date.</li>
      <li>Turn the page and read the instructions before beginning.</li>
    </ol>
  </div>

  <div id="answerkey-callout" class="help-callout mb-3" style="display:none">
    <strong>Answer Key:</strong>
    <span class="legend-badge">F</span> False,
    <span class="legend-badge">ST</span> Slightly True,
    <span class="legend-badge">MT</span> Mainly True,
    <span class="legend-badge">VT</span> Very True.
    <span class="ms-2 text-muted">Select one option per statement.</span>

    <hr class="my-2">

    <p class="mb-1">Read each statement and decide whether it is an accurate statement about you:</p>
    <ul class="mb-2">
        <li>If the statement is <strong>FALSE, NOT AT ALL TRUE</strong>, circle F.</li>
        <li>If the statement is <strong>SLIGHTLY TRUE</strong>, circle ST.</li>
        <li>If the statement is <strong>MAINLY TRUE</strong>, circle MT.</li>
        <li>If the statement is <strong>VERY TRUE</strong>, circle VT.</li>
    </ul>
    <p class="mb-1">Give your own opinion of yourself. Be sure to answer every statement.</p>
    <p class="mb-0">If you need to change an answer, erase your answer completely or make an "X" through the incorrect response, then circle the answer you want to choose. Begin with the first statement and respond to every statement.</p>
  </div>


  <h1 class="h3 mb-1">PAI Response Booklet</h1>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <div class="fw-semibold mb-1">Please correct the following:</div>
      <ul class="mb-0">
        <?php foreach ($errors as $ek => $ev): ?>
          <li><code><?=htmlspecialchars((string)$ek)?></code>: <?=htmlspecialchars((string)$ev)?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($insert_ok): ?>
    <div class="alert alert-success">
      <div class="h5 mb-1">Thank you! Your responses were recorded.</div>
      <div>Record ID: <code>#<?= (int)$record_id ?></code></div>
    </div>
  <?php else: ?>

  <form method="post" id="paiForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>" />

    <!-- STEP 0: IDs + Demographics -->
    <div class="step active" data-step="0">
      <div class="block-section mb-3">
        <div class="block-head">Demographics</div>
        <div class="block-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">First Name <span class="required">*</span></label>
              <input required type="text" class="form-control" name="name_first" value="<?= old('name_first') ?>" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Middle Name</label>
              <input type="text" class="form-control" name="name_middle" value="<?= old('name_middle') ?>" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Last Name <span class="required">*</span></label>
              <input required type="text" class="form-control" name="name_last" value="<?= old('name_last') ?>" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Date of Birth <span class="required">*</span></label>
              <input required type="date" class="form-control" name="date_of_birth" value="<?= old('date_of_birth') ?>" />
            </div>
            <div class="col-md-2">
              <label class="form-label">Age<span class="required">*</span></label>
              <input type="number" min="0" max="120" class="form-control" name="age" value="<?= old('age') ?>" />
            </div>
            <div class="col-md-4">
              <label class="form-label">Email<span class="required">*</span></label>
              <input type="email" class="form-control" name="email" value="<?= old('email') ?>" />
            </div>
            <div class="col-md-3">
              <label class="form-label">Occupation<span class="required">*</span></label>
              <input type="text" class="form-control" name="occupation" value="<?= old('occupation') ?>" />
            </div>
          </div>
        </div>
      </div>

      <div class="block-section mb-3">
        <div class="block-head">Background</div>
        <div class="block-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Referring Court<span class="required">*</span></label>
              <select class="form-select" name="referring_court">
                <option value="">— select —</option>
                <?php foreach ($COURTS as $c): $sel = (old('referring_court')===$c)?'selected':''; ?>
                  <option value="<?= htmlspecialchars($c) ?>" <?=$sel?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Gender<span class="required">*</span></label>
              <select class="form-select" name="gender">
                <option value="">— select —</option>
                <?php foreach ($GENDERS as $g): $sel = (old('gender')===$g)?'selected':''; ?>
                  <option value="<?= htmlspecialchars($g) ?>" <?=$sel?>><?= htmlspecialchars($g) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Marital Status<span class="required">*</span></label>
              <select class="form-select" name="marital_status">
                <option value="">— select —</option>
                <?php foreach ($MARITALS as $m): $sel = (old('marital_status')===$m)?'selected':''; ?>
                  <option value="<?= htmlspecialchars($m) ?>" <?=$sel?>><?= htmlspecialchars($m) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Education<span class="required">*</span></label>
              <select class="form-select" name="education_label" id="education_select">
                <option value="">— select —</option>
                <?php
                  $edu_options = [
                    ['8th Grade',8],['9th Grade',9],['10th Grade',10],['11th Grade',11],
                    ['High School Graduate (12)',12],['GED',12],['Some College',13],
                    ['Associates (14)',14],['Bachelors (16)',16],['Masters (18)',18],['Doctorate / Professional (PhD/MD/JD)',20]
                  ];
                  $edu_old = old('education_label');
                  foreach ($edu_options as [$label,$yrs]) {
                    $sel = ($edu_old === $label) ? 'selected' : '';
                    echo '<option value="'.htmlspecialchars($label).'" data-years="'.(int)$yrs.'" '.$sel.'>'.htmlspecialchars($label)."</option>";
                  }
                ?>
              </select>
              <input type="hidden" name="education_years" id="education_years" value="<?= old('education_years') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Today’s Date<span class="required">*</span></label>
              <input type="date" class="form-control" name="todays_date" value="<?= old('todays_date', date('Y-m-d')) ?>" />
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end">
        <button type="button" class="btn btn-primary" id="nextBtn0">Next</button>
      </div>
    </div>

    <?php
    // Replace the existing render_pai_page_full with this table-based version
    function render_pai_page_full(int $page, int $perPage, array $PAI_ITEMS, array $PAI_CHOICES): void {
    $start = ($page-1) * $perPage + 1;
    $end   = min($start + $perPage - 1, 344);

    echo '<div class="step" data-step="'.(int)$page.'">';

    // Answer-range label
    echo '<div class="text-muted mb-2">Items '.$start.' &ndash; '.$end.'</div>';

    echo '<div class="table-responsive">';
    echo '  <table class="table table-bordered table-sm align-middle pai-table table-striped">';

    echo '    <colgroup>';
    echo '      <col class="pai-col-item">';
    echo '      <col class="pai-col-choice"><col class="pai-col-choice"><col class="pai-col-choice"><col class="pai-col-choice">';
    echo '    </colgroup>';
    echo '    <thead class="table-light">';
    echo '      <tr>';
    echo '        <th class="text-start">PAI</th>';
    echo '        <th>F</th><th>ST</th><th>MT</th><th>VT</th>';
    echo '      </tr>';
    echo '    </thead>';
    echo '    <tbody>';

    for ($i=$start; $i<=$end; $i++) {
        $name  = sprintf('q%03d', $i);
        $label = $PAI_ITEMS[$i] ?? ('Item '.$i);

        echo '<tr>';
        echo '  <td class="text-start"><label for="'.$name.'_F" class="form-label mb-0">'.$i.'. '.htmlspecialchars($label).'</label></td>';

        foreach ($PAI_CHOICES as $j => $opt) {
        $id      = $name.'_'.$opt;
        $checked = (isset($_POST[$name]) && $_POST[$name] === $opt) ? 'checked' : '';
        // require on first radio enforces one-per-row without duplicating required
        $required = ($j === 0) ? 'required' : '';
        echo '  <td class="choice-cell">';
        echo '    <input type="radio" name="'.$name.'" id="'.$id.'" value="'.$opt.'" '.$checked.' '.$required.'>';
        echo '    <label for="'.$id.'">'.$opt.'</label>';
        echo '  </td>';

        }

        echo '</tr>';
    }

    echo '    </tbody>';
    echo '  </table>';
    echo '</div>'; // .table-responsive

    // Sticky nav
    echo '<div class="d-flex justify-content-between sticky-actions">';
    echo '  <button type="button" class="btn btn-outline-secondary" data-prev>Previous</button>';
    if ($end < 344) echo '  <button type="button" class="btn btn-primary" data-next>Next</button>';
    else            echo '  <button type="submit" class="btn btn-success">Submit Responses</button>';
    echo '</div>';

    echo '</div>'; // .step
    }
    for ($p=1; $p<=8; $p++) { render_pai_page_full($p, 43, $PAI_ITEMS, $PAI_CHOICES); }
    ?>

  </form>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  // Progress + step nav
  const steps = Array.from(document.querySelectorAll('.step'));
  const progressBar = document.getElementById('progressBar');
  let current = 0;
  function updateProgress(){
    const pct = Math.round((current) / (steps.length - 1) * 100);
    progressBar.style.width = pct + '%';
    progressBar.setAttribute('aria-valuenow', pct);
  }
  function showStep(n){
    steps.forEach((s,i)=> s.classList.toggle('active', i === n));
    current = n; updateProgress(); window.scrollTo({top:0, behavior:'smooth'});
    updateCallouts();
  }
  function stepRange(n){
    // For item pages: return [start,end] indices
    if (n === 0) return null; // demographics
    const per = 43; const start = (n-1)*per+1; const end = Math.min(start+per-1, 344); return [start,end];
  }
  function validateCurrentStep(){
    if (current === 0) {
      const req = document.querySelectorAll('.step[data-step="0"] [required]');
      for (const el of req) { if (!el.value || (el.type==='radio' && !el.checked)) return false; }
      return true;
    } else {
      // ensure all radios on this page are answered
      const range = stepRange(current); if (!range) return true;
      const [start,end] = range; for (let i=start;i<=end;i++) {
        const name = 'q'+String(i).padStart(3,'0');
        if (!document.querySelector('input[name="'+name+'"]:checked')) return false;
      }
      return true;
    }
  }

  document.getElementById('nextBtn0')?.addEventListener('click', ()=>{
    if (!validateCurrentStep()) { alert('Please complete all required fields on this step.'); return; }
    showStep(1);
  });
  document.querySelectorAll('[data-next]').forEach(btn => btn.addEventListener('click', ()=>{
    if (!validateCurrentStep()) { alert('Please answer every item on this page before continuing.'); return; }
    if (current < steps.length-1) showStep(current+1);
  }));
  document.querySelectorAll('[data-prev]').forEach(btn => btn.addEventListener('click', ()=>{
    if (current > 0) showStep(current-1);
  }));

  function updateCallouts(){
    const directions = document.getElementById('directions-callout');
    const answerkey  = document.getElementById('answerkey-callout');
    if (current <= 0) { directions.style.display=''; answerkey.style.display='none'; }
    else { directions.style.display='none'; answerkey.style.display=''; }
  }
  showStep(0);

  // Education years autopopulate
  const sel = document.getElementById('education_select');
  const yrs = document.getElementById('education_years');
  if (sel && yrs) {
    sel.addEventListener('change', function(){
      const opt = sel.options[sel.selectedIndex];
      yrs.value = opt.getAttribute('data-years') || '';
    });
  }
})();
</script>
<script>
(function(){
  const bubble = document.getElementById('miniKey');
  const header = document.querySelector('.np-header');
  const fullKey = document.getElementById('answerkey-callout');
  if (!bubble || !header || !fullKey) return;

  // Track whether the full Answer Key is in view
  let fullKeyInView = true;

  function headerHeight() {
    return header?.offsetHeight || 64;
  }
  function setBubbleTop(){
    bubble.style.top = (headerHeight() + 8) + 'px';
  }
  function activeStepIndex(){
    const el = document.querySelector('.step.active');
    return el ? (parseInt(el.getAttribute('data-step'), 10) || 0) : 0;
  }
  function updateBubbleVisibility() {
    const onItemsPages = activeStepIndex() >= 1;   // hide on step 0
    const shouldShow = onItemsPages && !fullKeyInView;
    bubble.style.display = shouldShow ? 'inline-flex' : 'none';
    bubble.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
  }

  // Keep bubble pinned just under the sticky header
  setBubbleTop();
  window.addEventListener('resize', setBubbleTop);

  // Observe the full key: when it leaves the viewport, we may show the bubble
  const io = new IntersectionObserver((entries)=>{
    if (!entries[0]) return;
    fullKeyInView = entries[0].isIntersecting;
    updateBubbleVisibility();
  }, { root: null, threshold: 0 });
  io.observe(fullKey);

  // Watch for step changes (next/prev buttons) and class toggles
  document.addEventListener('click', (e)=>{
    if (e.target.matches('[data-next], [data-prev], #nextBtn0')) {
      setTimeout(updateBubbleVisibility, 0);
    }
  });
  const mo = new MutationObserver(()=> updateBubbleVisibility());
  document.querySelectorAll('.step')
    .forEach(s=> mo.observe(s, {attributes:true, attributeFilter:['class']}));

  // Initial state
  updateBubbleVisibility();
})();
</script>

</body>
</html>
