<?php
/**
 * evaluation-review.php — Staff review page for Evaluation forms (PAI + VTC)
 *
 * How it works
 *  - Staff-only (uses auth.php) with shared navbar.php
 *  - Accepts ?key= base64(json:{first,last,dob}) from evaluation-index.php
 *    Optional: &form=pai|vtc|combined (default combined)
 *              &pai_id=123 to view a specific PAI submission (else latest)
 *              &vtc_id=456 to view a specific VTC submission (else latest)
 *  - Shows Demographics at top (merged from whichever form has data)
 *  - Combined view renders PAI and VTC side-by-side; single view shows one form full-width
 *  - Includes selectors to switch among multiple submissions for a form
 *  - Lays groundwork for future auto-scoring (HAM-A, BHS, etc.)
 */

declare(strict_types=1);

include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$link = $con; $link->set_charset('utf8mb4');
if (session_status()===PHP_SESSION_NONE) session_start();

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

// Map an item number to likely column names in the DB (q001..q344 etc.)
function pai_qkey_candidates(int $n): array {
  $n3 = sprintf('%03d', $n);
  return ["q$n3","Q$n3","q$n","Q$n"];  // try q001, Q001, q1, Q1
}

// Return the stored answer (raw) for an item number
function pai_answer_for(array $row, int $n): string {
  foreach (pai_qkey_candidates($n) as $k) {
    if (array_key_exists($k, $row)) return trim((string)$row[$k]);
  }
  return '';
}

// Render PAI Q&A table using the item text + the client's answer
function render_pai_qna(array $row, array $PAI_ITEMS): string {
  if (!$row) return '<div class="text-muted">No data</div>';

  $out  = '<div class="table-responsive">';
  $out .= '<table class="table table-sm table-bordered mb-0" style="table-layout:fixed;width:100%">';
  // # (64px) | Answer Key (100px) | Item (flex) | Answer (100px)
  $out .= '<colgroup>
            <col style="width:64px">
            <col style="width:100px">
            <col>
            <col style="width:100px">
          </colgroup>';

  $out .= '<thead class="thead-light"><tr>
            <th class="text-center">#</th>
            <th class="text-center">Answer Key</th>
            <th>Item</th>
            <th class="text-center">Answer</th>
          </tr></thead><tbody>';

  foreach ($PAI_ITEMS as $num => $text) {
    $ans = pai_answer_for($row, (int)$num);
    $key = pai_answer_key($ans);
    $isEmpty = ($ans === '' || $ans === null);

    $ansOut = $isEmpty ? '<span class="text-muted">—</span>' : nl2br(h($ans));
    $keyOut = $key === '' ? '<span class="text-muted">—</span>' : h($key);

    $out .= '<tr class="kv-row" data-empty="'.($isEmpty?'1':'0').'">';
    $out .= '<td class="text-center">'.(int)$num.'</td>';
    $out .= '<td class="text-center text-nowrap">'.$keyOut.'</td>';  // << moved here
    $out .= '<td>'.h($text).'</td>';                                  // Item expands
    $out .= '<td class="text-center text-nowrap">'.$ansOut.'</td>';
    $out .= '</tr>';
  }

  $out .= '</tbody></table></div>';
  return $out;
}

/* =========================
 * VTC Evaluation — grouping + helpers
 * (ADD this block after your PAI helper functions)
 * ========================= */

// Demographics (2-col table at top)
$VTC_DEMOGRAPHIC_FIELDS = [
  'first_name','last_name','email','phone_primary','dob','age','race','gender',
  'education_level','employed','employer','occupation',
  'address1','address2','city','state','zip','country',
  'dl_number','cid_number','referral_source','vtc_officer_name',
  'attorney_name','attorney_email',
  'marital_status','living_situation','has_children','children_live_with_you',
  'names_and_ages_of_your_children_if_applicable',
  'emergency_contact_name','emergency_contact_relationship','emergency_contact_phone',
  'military_service',// APPEND to $VTC_DEMOGRAPHIC_FIELDS
  'child_abused_physically',
  'child_abused_sexually',
  'child_abused_emotionally',
  'child_neglected',
  'cps_notified',
  'cps_care_or_supervision',

];

// Narrative / open-text (between Demographics and Assessments)
$VTC_NARRATIVE_FIELDS = [
  // clinical/narrative one-offs
  'counseling_details','depressed_details','suicide_last_attempt_when','psych_meds_list',
  'sexual_abuse_history','head_trauma_details','weapon_possession_history','childhood_abuse_history',
  'upbringing_legacy_description','legal_history_legacy_description','military_experiences_legacy_description',
  'medications_impact','addiction_experiences','religious_views','legal_offense_summary',

  // upbringing*
  'upbringing_where_grow_up','upbringing_who_raised_you','upbringing_raised_by_both_parents',
  'upbringing_parents_caretakers_names','upbringing_divorce_explain','upbringing_caretaker_addiction',
  'upbringing_caretaker_mental_health','upbringing_finances_growing_up',
  'upbringing_traumatic_experiences','upbringing_school_experience',
  'upbringing_caretakers_help_schoolwork','upbringing_anything_else_for_court',

  // legal*
  'legal_first_arrest_details','legal_multiple_arrests_details','legal_prevention_plan','legal_hopes_from_vtc',

  // military*
  'military_join_details','military_trauma_description','military_impact_beliefs',
  'military_grief_counseling','military_culture_mh_attitudes',

  // medications*
  'medications_prescribed_history','medications_first_prescribed_details','medications_current_and_desired',

  // addiction*
  'addiction_impact_on_life','addiction_overcome_attempts',

  // future/hope/beliefs
  'sobriety_future_impact','hope_for_future_narrative',
  'beliefs_impact_on_life','beliefs_extraordinary_experiences','beliefs_shape_future'
];

/* =========================
 * VTC — Inventories (BDI/BAI/BHS/HAM-D/HAM-A)
 * ========================= */

/** Instrument field lists (from evaluations-vtc.php $PAGES[16..20]) */
$VTC_BDI_KEYS = [
  'punishment_feelings','sadness','pessimism','self_dislike','past_failure',
  'self_criticalness','loss_of_pleasure','suicidal_thoughts_or_wishes','guilty_feelings',
  'crying','agitation','irritability','loss_of_interest','loss_of_interest_in_sex',
  'indecisiveness','concentration_difficulty','worthlessness','tiredness_or_fatigue',
  'loss_of_energy','changes_in_appetite','changes_in_sleeping_pattern'
];
$VTC_BAI_KEYS = [
  'feeling_hot','wobbliness_in_legs','numbness_or_tingling','unable_to_relax',
  'fear_of_worst_happening','dizzy_or_lightheaded','heart_pounding_racing','unsteady',
  'terrified_or_afraid','nervous','feeling_of_choking','hands_trembling','shaky_unsteady',
  'fear_of_losing_control','difficulty_breathing','fear_of_dying','scared','face_flushed',
  'indigestion_or_discomfort_in_abdomen','faint_lightheaded','sweating_not_due_to_heat'
];
$VTC_BHS_KEYS = [
  '1_i_look_forward_to_the_future_with_hope_and_enthusiasm',
  '2_i_might_as_well_give_up_because_i_cant_make_things_better_for_',
  '3_when_things_are_going_badly_i_am_helped_by_knowing_they_cant_s',
  '4_i_cant_imagine_what_my_life_would_be_like_in_10_years',
  '5_i_have_enough_time_to_accomplish_the_things_i_most_want_to_do',
  '6_in_the_future_i_expect_to_succeed_in_what_concerns_me_most',
  '7_my_future_seems_dark_to_me',
  '8_i_expect_to_get_more_good_things_in_life_than_the_average_pers',
  '9_i_just_dont_get_the_breaks_and_theres_no_reason_to_believe_i_w',
  '10_my_past_experiences_have_prepared_me_well_for_the_future',
  '11_all_i_can_see_ahead_of_me_is_unpleasantness_rather_than_pleas',
  '12_i_dont_expect_to_get_what_i_really_want',
  '13_when_i_look_ahead_to_the_future_i_expect_i_will_be_happier_th',
  '14_things_just_wont_work_out_the_way_i_want_them_to',
  '15_i_have_great_faith_in_the_future',
  '16_i_never_get_what_i_want_so_its_foolish_to_want_anything',
  '17_it_is_very_unlikely_that_i_will_get_any_real_satisfaction_in_',
  '18_the_future_seems_vague_and_uncertain_to_me',
  '19_i_can_look_forward_to_more_good_times_than_bad_times',
  '20_theres_no_use_in_really_trying_to_get_something_i_want_becaus'
];
// === HAM-D item keys shown on review (21-item form) ===
$VTC_HAMD_KEYS = [
  'depressed_mood_gloomy_attitude_pessimism_about_the_future_feelin',  // 1 (0–4)
  'feelings_of_guilt',                                                  // 2 (0–4)
  'suicide',                                                            // 3 (0–4)
  'insomnia_initial_difficulty_in_falling_asleep',                      // 4 (0–2)
  'insomnia_middle_complains_of_being_restless_and_disturbed_during',   // 5 (0–2)
  'insomnia_delayed_waking_in_early_hours_of_the_morning_and_unable',   // 6 (0–2)
  'work_and_interests',                                                 // 7 (0–4)
  'retardation_slowness_of_thought_speech_and_activity_apathy_stupo',   // 8 (0–4)
  'agitation_restlessness_associated_with_anxiety',                     // 9 (0–4)
  'anxiety',                                                            // 10 (0–4)  // psychic
  'anxiety_somatic_gastrointestinal_indigestion_cardiovascular_palp',   // 11 (0–4)  // somatic
  'somatic_symptoms_gastrointestinal_loss_of_appetite_heavy_feeling',   // 12 (0–2)
  'somatic_symptoms_general',                                           // 13 (0–2)  // <-- NEW, now included
  'genital_symptoms_loss_of_libido_menstrual_disturbances',             // 14 (0–2)
  'hypochondriasis',                                                    // 15 (0–4)
  'weight_loss',                                                        // 16 (0–2)
  'insight_insight_must_be_interpreted_in_terms_of_patients_underst',   // 17 (0–2)
  // 18–21 are additional (not used in the 17-item score)
  'diurnal_variation_symptoms_worse_in_morning_or_evening_note_whic',   // 18
  'depersonalization_and_derealization_feelings_of_unreality_nihili',   // 19
  'paranoid_symptoms_not_with_a_depressive_quality',                    // 20
  'obsessional_symptoms_obsessive_thoughts_and_compulsions_against_',   // 21
];
// === Keys used for HAM-D-17 scoring (order doesn’t matter for sum) ===
$VTC_HAMD_SCORABLE_17 = [
  'depressed_mood_gloomy_attitude_pessimism_about_the_future_feelin',
  'feelings_of_guilt',
  'suicide',
  'insomnia_initial_difficulty_in_falling_asleep',
  'insomnia_middle_complains_of_being_restless_and_disturbed_during',
  'insomnia_delayed_waking_in_early_hours_of_the_morning_and_unable',
  'work_and_interests',
  'retardation_slowness_of_thought_speech_and_activity_apathy_stupo',
  'agitation_restlessness_associated_with_anxiety',
  'anxiety', // psychic
  'anxiety_somatic_gastrointestinal_indigestion_cardiovascular_palp', // somatic
  'somatic_symptoms_gastrointestinal_loss_of_appetite_heavy_feeling',
  'somatic_symptoms_general', // <-- the new one you added
  'genital_symptoms_loss_of_libido_menstrual_disturbances',
  'hypochondriasis',
  'weight_loss',
  'insight_insight_must_be_interpreted_in_terms_of_patients_underst',
];

$VTC_HAMA_KEYS = [
  'tension_feelings_of_tension_fatigability_startle_response_moved_',
  'anxious_worries_anticipation_of_the_worst_fearful_anticipation_i',
  'fears_of_dark_of_strangers_of_being_left_alone_of_animals_of_tra',
  'insomnia_difficulty_in_falling_asleep_broken_sleep_unsatisfying_',
  'intellectual_cognitive_difficulty_in_concentration_poor_memory',
  'depressed_mood_loss_of_interest_lack_of_pleasure_in_hobbies_depr',
  'somatic_muscular_pains_and_aches_twitching_stiffness_myoclonic_j',
  'somatic_sensory_tinnitus_blurring_of_vision_hot_and_cold_flushes',
  'cardiovascular_symptoms_tachycardia_palpitations_pain_in_chest_t',
  'respiratory_symptoms_pressure_or_constriction_in_chest_choking_f',
  'gastrointestinal_symptoms_difficulty_in_swallowing_wind_abdomina',
  'genitourinary_symptoms_frequency_of_micturition_urgency_of_mictu',
  'autonomic_symptoms_dry_mouth_flushing_pallor_tendency_to_sweat_g',
  'behavior_fidgeting_restlessness_or_pacing_tremor_of_hands_furrow'
];

// === PTSD / PCL-M (17 items) ===
// Likert answers like "Not at all" ... "Extremely". Columns exist in VTC table.
$VTC_PCLM_KEYS = [
  '1_repeated_disturbing_memories_thoughts_or_images_of_a_stressful',
  '2_repeated_disturbing_dreams_of_a_stressful_military_experience',
  '3_suddenly_acting_or_feeling_as_if_a_stressful_military_experien',
  '4_feeling_very_upset_when_something_reminded_you_of_a_stressful_',
  '5_having_physical_reactions_e_g_heart_pounding_trouble_breathing',
  '6_avoid_thinking_about_or_talking_about_a_stressful_military_exp',
  '7_avoid_activities_or_talking_about_a_stressful_military_experie',
  '8_trouble_remembering_important_parts_of_a_stressful_military_ex',
  '9_loss_of_interest_in_things_that_you_used_to_enjoy',
  '10_feeling_distant_or_cut_off_from_other_people',
  '11_feeling_emotionally_numb_or_being_unable_to_have_loving_feeli',
  '12_feeling_as_if_your_future_will_somehow_be_cut_short',
  '13_trouble_falling_or_staying_asleep',
  '14_feeling_irritable_or_having_angry_outbursts',
  '15_having_difficulty_concentrating',
  '16_being_super_alert_or_watchful_on_guard',
  '17_feeling_jumpy_or_easily_startled',
];

// === TBI / NSI (22 items) ===
$VTC_TBI_KEYS = [
  '1_feeling_dizzy',
  '2_loss_of_balance',
  '3_poor_coordination_clumsy',
  '4_headaches',
  '5_nausea',
  '6_vision_problems_blurring_trouble_seeing',
  '7_sensitivity_to_light',
  '8_hearing_difficulty',
  '9_sensitivity_to_noise',
  '10_numbness_to_tingling_on_parts_of_body',
  '11_change_in_taste_and_or_smell',
  '12_loss_or_increase_of_appetite',
  '13_poor_concentration_or_easily_distracted',
  '14_forgetfulness_cant_remember_things',
  '15_difficulty_making_decisions',
  '16_slowed_thinking_cant_finish_things',
  '17_fatigue_loss_of_energy_easily_tired',
  '18_difficulty_falling_or_staying_asleep',
  '19_feeling_anxious_or_tense',
  '20_feeling_depressed_or_sad',
  '21_irritability_easily_annoyed',
  '22_poor_frustration_tolerance_overwhelmed'
];


// === SASSI (True/False statements; T/F text captured as free text) ===
// These are present in your VTC table. We'll render what exists.
$VTC_SASSI_TF_KEYS = [
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


// === SASSI Alcohol (past 6 months) ===
$VTC_SASSI_ALC_KEYS = [
  '1_had_drinks_beer_wine_liquor_with_lunch',
  '2_taken_a_drink_or_drinks_to_help_you_talk_about_your_feelings_o',
  '3_taken_a_drink_or_drinks_to_relieve_a_tired_feeling_or_give_you',
  '4_had_more_to_drink_than_you_intended_to',
  '5_experienced_physical_problems_after_drinking_e_g_nausea_seeing',
  '6_gotten_into_trouble_on_the_job_in_school_or_with_the_law_becau',
  '7_became_depressed_after_having_sobered_up',
  '8_argued_with_your_family_or_friends_because_of_your_drinking',
  '9_had_the_effects_of_drinking_recur_after_not_drinking_for_a_whi',
  '10_had_problems_in_relationships_because_of_your_drinking_e_g_lo',
  '11_became_nervous_or_had_the_shakes_after_having_sobered_up',
  '12_tried_to_commit_suicide_while_drunk',
  '13_found_myself_craving_a_drink_or_a_particular_drug',
];

// === SASSI Drug / Medication Misuse ===
$VTC_SASSI_DRUG_KEYS = [
  '1_misused_medications_or_took_drugs_to_improve_your_thinking_and',
  '2_misused_medications_or_took_drugs_to_help_you_feel_better_abou',
  '3_misused_medications_or_took_drugs_to_become_more_aware_of_your',
  '4_misused_medications_or_took_drugs_to_improve_your_enjoyment_of',
  '5_misused_medications_or_took_drugs_to_help_forget_that_you_feel',
  '6_misused_medications_or_took_drugs_to_forget_school_work_or_fam',
  '7_gotten_into_trouble_at_home_work_or_with_the_police_because_of',
  '8_gotten_really_stoned_or_wiped_out_on_drugs_more_than_just_high',
  '9_tried_to_get_a_hold_of_some_prescription_drug_e_g_tranquilizer',
  '10_spent_your_spare_time_in_drug_related_activities_e_g_talking_',
  '11_used_drugs_or_medications_and_alcohol_at_the_same_time',
  '12_kept_taking_medications_or_drugs_in_order_to_avoid_pain_or_wi',
  '13_felt_your_misuse_of_medications_alcohol_or_drugs_has_kept_you',
  '14_took_a_higher_dose_or_different_medications_than_your_doctor_',
  '15_used_prescription_drugs_that_were_not_prescribed_for_you',
  '16_your_doctor_denied_your_request_for_medications_you_needed',
  '17_been_accepted_into_a_treatment_program_because_of_misuse_of_m',
  '18_engaged_in_activity_that_could_have_been_physically_dangerous',

];

$VTC_CLINICAL_QUICK_FIELDS = [
  'depressed_now',
  'counseling_history',
  'suicide_attempt_history',
  'psych_meds_current',
  'psych_meds_physician',
  'head_trauma_history',
  'alcohol_past_use','alcohol_past_details',
  'alcohol_current_use','alcohol_current_details',
  'drug_past_use','drug_past_details',
  'drug_current_use','drug_current_details',
];

/** Utilities */
function vtc_any_answered(array $row, array $keys): bool {
  foreach ($keys as $k) if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) return true;
  return false;
}
function vtc_render_inventory_table(array $row, array $keys, string $heading): string {
  $any = vtc_any_answered($row, $keys);
  if (!$any) return ''; // skip empty instruments

  $out  = '<h6 class="mb-2">'.h($heading).'</h6>';
  $out .= '<div class="table-responsive"><table class="table table-sm table-bordered mb-3" style="table-layout:fixed;width:100%">';
  $out .= '<colgroup><col style="width:64px"><col><col style="width:180px"></colgroup>';
  $out .= '<thead class="thead-light"><tr><th class="text-center">#</th><th>Item</th><th class="text-center">Answer</th></tr></thead><tbody>';

  $i = 1;
  foreach ($keys as $k) {
    $val = $row[$k] ?? '';
    if ($val === null || $val === '') $val = '<span class="text-muted">—</span>';
    else $val = h((string)$val);
    $out .= '<tr><td class="text-center">'.($i++).'</td><td>'.h(vtc_labelize($k)).'</td><td class="text-center text-nowrap">'.$val.'</td></tr>';
  }
  $out .= '</tbody></table></div>';
  return $out;
}

function vtc_render_simple_block(array $row, array $keys, string $title): string {
  if (!$keys || !vtc_any_answered($row, $keys)) return '';
  $out  = '<h6 class="mb-2">'.h($title).'</h6>';
  $out .= '<div class="table-responsive"><table class="table table-sm table-bordered mb-3" style="table-layout:fixed;width:100%">';
  $out .= '<colgroup><col style="width:64px"><col><col style="width:180px"></colgroup>';
  $out .= '<thead class="thead-light"><tr><th class="text-center">#</th><th>Item</th><th class="text-center">Answer</th></tr></thead><tbody>';
  $i = 1;
  foreach ($keys as $k) {
    $raw = $row[$k] ?? '';
    if ($raw === '' || $raw === null) {
      $val = '<span class="text-muted">—</span>';
    } else {
      $s = strtolower(trim((string)$raw));
      // Only remap booleans on SASSI blocks
      if (stripos($title, 'sassi') !== false && ($s === '0' || $s === '1')) {
        $val = ($s === '0') ? 'True' : 'False';
      } else {
        $val = h((string)$raw);
      }
    }

    $out .= '<tr><td class="text-center">'.($i++).'</td><td>'.h(vtc_labelize($k)).'</td><td class="text-center text-nowrap">'.$val.'</td></tr>';
  }
  $out .= '</tbody></table></div>';
  return $out;
}
// --- PCL-M SCORING HELPERS ---

// Map textual or mixed "N – Label" responses to 1..5
function vtc_pclm_score_of($raw): ?int {
  if ($raw === null) return null;
  $s = trim((string)$raw);
  if ($s === '') return null;

  // Normalize unicode dashes to simple hyphen and collapse spaces
  $s = str_replace(["\u{2013}", "\u{2014}"], '-', $s); // en/em dash -> '-'
  $sl = strtolower(preg_replace('/\s+/u',' ', $s));

  // 1) pure numeric
  if (ctype_digit($sl)) {
    $n = (int)$sl;
    return ($n >= 1 && $n <= 5) ? $n : null;
  }

  // 2) "N - label" or "N label" variants
  if (preg_match('/^\s*([1-5])\s*(?:-|\:|\.|\)|\s)?/u', $sl, $m)) {
    return (int)$m[1];
  }

  // 3) label-only text
  if (strpos($sl, 'not at all') !== false)   return 1;
  if (strpos($sl, 'a little bit') !== false) return 2;
  if (strpos($sl, 'moderately') !== false)   return 3;
  if (strpos($sl, 'quite a bit') !== false)  return 4;
  if (strpos($sl, 'extremely') !== false)    return 5;

  return null;
}


// Compute PCL-M totals + DSM cluster criteria
function vtc_score_pclm(array $row, array $keys): array {
  // Indices: B = 1–5, C = 6–12, D = 13–17 (1-based)
  $scores = [];
  $total = 0; $answered = 0;
  $B = 0; $C = 0; $D = 0;

  foreach ($keys as $i => $k) {
    $score = vtc_pclm_score_of($row[$k] ?? null);
    $scores[$k] = $score;
    if ($score !== null) {
      $total += $score; $answered++;
      if ($score >= 3) {
        $idx = $i + 1; // to 1-based
        if ($idx >= 1 && $idx <= 5)       $B++;
        elseif ($idx >= 6 && $idx <= 12)  $C++;
        elseif ($idx >= 13 && $idx <= 17) $D++;
      }
    }
  }

  $meets_B = ($B >= 1);
  $meets_C = ($C >= 3);
  $meets_D = ($D >= 2);
  $meets_DSM = ($meets_B && $meets_C && $meets_D);

  // Cut points
  $cut_general  = 44;
  $cut_military = 50;

  return [
    'scores'        => $scores,
    'total'         => $total,
    'answered'      => $answered,
    'B' => $B, 'C' => $C, 'D' => $D,
    'meets_B' => $meets_B, 'meets_C' => $meets_C, 'meets_D' => $meets_D,
    'meets_DSM' => $meets_DSM,
    'cut_general' => $cut_general,
    'cut_military'=> $cut_military,
    'screen_general'  => ($meets_DSM && $total >= $cut_general),
    'screen_military' => ($meets_DSM && $total >= $cut_military),
  ];
}


function vtc_render_pclm_block(array $row): string {
  $keys = $GLOBALS['VTC_PCLM_KEYS'] ?? [];
  if (!$keys || !vtc_any_answered($row, $keys)) return '';

  $S = vtc_score_pclm($row, $keys);
  $max_total = 17 * 5;

  $pill = function(bool $ok, string $yes='Yes', string $no='No'){
    return $ok
      ? '<span class="badge bg-success">'.$yes.'</span>'
      : '<span class="badge bg-secondary">'.$no.'</span>';
  };

  $hdr  = '<h6 class="mb-2">PTSD — PCL-M</h6>';
  $hdr .= '<div class="mb-2 small">';
  $hdr .= '<div class="d-flex flex-wrap gap-3">';
  $hdr .= '<div><strong>Total Severity:</strong> '.h((string)$S['total']).' / '.$max_total.'</div>';
  $hdr .= '<div><strong>Clusters (≥3 = symptomatic):</strong> B '.$S['B'].'/5, C '.$S['C'].'/7, D '.$S['D'].'/5</div>';
  $hdr .= '<div><strong>DSM cluster criteria met:</strong> '.$pill($S['meets_DSM']).'</div>';
  $hdr .= '<div><strong>Cut (General ≥ '.$S['cut_general'].'):</strong> '.$pill($S['total'] >= $S['cut_general']).'</div>';
  $hdr .= '<div><strong>Cut (Military ≥ '.$S['cut_military'].'):</strong> '.$pill($S['total'] >= $S['cut_military']).'</div>';
  $hdr .= '<div><strong>Screen (General, combined):</strong> '.$pill($S['screen_general'],'Positive','Negative').'</div>';
  $hdr .= '<div><strong>Screen (Military, combined):</strong> '.$pill($S['screen_military'],'Positive','Negative').'</div>';
  $hdr .= '</div></div>';

  // Table with numeric score next to label
  $out  = $hdr;
  $out .= '<div class="table-responsive"><table class="table table-sm table-bordered mb-3" style="table-layout:fixed;width:100%">';
  $out .= '<colgroup><col style="width:64px"><col><col style="width:210px"><col style="width:90px"></colgroup>';
  $out .= '<thead class="thead-light"><tr><th class="text-center">#</th><th>Item</th><th class="text-center">Answer</th><th class="text-center">Score</th></tr></thead><tbody>';

  $i = 1;
  foreach ($keys as $k) {
    $raw = $row[$k] ?? '';
    $sc  = $S['scores'][$k];
    $ans = ($raw === '' || $raw === null) ? '<span class="text-muted">—</span>' : h((string)$raw);
    $sct = ($sc === null) ? '<span class="text-muted">—</span>' : (string)$sc;
    $out .= '<tr>'
          . '<td class="text-center">'.$i++.'</td>'
          . '<td>'.h(vtc_labelize($k)).'</td>'
          . '<td class="text-center">'.$ans.($sc ? ' <span class="text-muted">('.$sc.')</span>' : '').'</td>'
          . '<td class="text-center">'.$sct.'</td>'
          . '</tr>';
  }
  $out .= '</tbody></table></div>';
  $out .= '<div class="text-muted small">Scoring: total severity is the sum of 17 items (1–5). '
        . 'DSM cluster rule = ≥1 B item (Q1–5) + ≥3 C (Q6–12) + ≥2 D (Q13–17) at ≥3 (“moderately”). '
        . 'Screening “Positive” here requires cluster rule AND total ≥ cut point (General: 44; Military: 50).</div>';

  return $out;
}

function vtc_render_tbi_block(array $row): string {
  return vtc_render_simple_block($row, $GLOBALS['VTC_TBI_KEYS'] ?? [], 'TBI / NSI Symptoms');
}
function vtc_render_sassi_tf_block(array $row): string {
  return vtc_render_simple_block($row, $GLOBALS['VTC_SASSI_TF_KEYS'] ?? [], 'SASSI — True/False Statements');
}
function vtc_render_sassi_alcohol_block(array $row): string {
  return vtc_render_simple_block($row, $GLOBALS['VTC_SASSI_ALC_KEYS'] ?? [], 'Alcohol Behaviors');
}
function vtc_render_sassi_drug_block(array $row): string {
  return vtc_render_simple_block($row, $GLOBALS['VTC_SASSI_DRUG_KEYS'] ?? [], 'Drug/Medication Behaviors');
}



/** Small utilities (no re-defs of h()) */
function vtc_has(array $row, string $k): bool {
  return array_key_exists($k,$row) && $row[$k] !== '' && $row[$k] !== null;
}
function vtc_labelize(string $k): string {
  $k = preg_replace('/_id$/','',$k);
  $k = preg_replace('/\b(dob)\b/i','DOB',$k);
  $k = preg_replace('/\bssn\b/i','SSN',$k);
  $k = str_replace('_',' ', $k);
  return ucwords($k);
}
function vtc_yesno_badge($v): string {
  $t = is_string($v) ? strtolower(trim($v)) : $v;
  $yes = ($v===1||$v==='1'||$t==='yes'||$t==='y'||$t===true||$t==='true');
  $no  = ($v===0||$v==='0'||$t==='no'||$t==='n'||$t===false||$t==='false');
  if ($yes) return '<span class="badge bg-success">Yes</span>';
  if ($no)  return '<span class="badge bg-secondary">No</span>';
  return '<span class="text-muted">—</span>';
}

/** Simple 2-col table for short fields */
function vtc_render_kv_table(array $row, array $keys): string {
  $any = false;
  $out = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><tbody>';
  foreach ($keys as $k) {
    if (!vtc_has($row,$k)) continue;
    $any = true;
    $out .= '<tr><th style="width:30%;">'.h(vtc_labelize($k)).'</th><td>'.nl2br(h((string)$row[$k])).'</td></tr>';
  }
  $out .= '</tbody></table></div>';
  return $any ? $out : '<div class="text-muted">No data.</div>';
}

/** Narrative blocks: each long text gets its own box */
function vtc_render_narratives(array $row, array $keys): string {
  $buf = '';
  foreach ($keys as $k) {
    if (!vtc_has($row,$k)) continue;
    $val = trim((string)$row[$k]);
    if ($val === '') continue;
    $buf .= '<div class="mb-3"><h6 class="mb-1">'.h(vtc_labelize($k)).'</h6>';
    $buf .= '<div class="border rounded p-2" style="background:#fff">'.nl2br(h($val)).'</div></div>';
  }
  return $buf !== '' ? $buf : '<div class="text-muted">No narrative responses.</div>';
}

/** Identify consent-ish keys; group at bottom */
function vtc_is_consent_key(string $k): bool {
  // catches: consent, agree, hipaa, release, sworn, *ack, policy, rights, rule, roi, final_*, free_for_life_group...,
  // and the two very long opening statements that start with "as_a_client" or "a_licensee_shall"
  static $rx = null;
  if ($rx === null) {
    $rx = '/^(consent|agree|hipaa|release|sworn|.+_ack($|_)|policy|rights|rule|roi|final_|free_for_life_group|as_a_client|a_licensee_shall)/i';

  }
  return (bool)preg_match($rx, $k);
}

function vtc_render_consents(array $row): string {
  $keys = [];
  foreach ($row as $k => $v) if (vtc_is_consent_key((string)$k)) $keys[] = $k;
  sort($keys, SORT_NATURAL|SORT_FLAG_CASE);
  if (!$keys) return '<div class="text-muted">No consents found.</div>';
  $out  = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
  $out .= '<thead class="thead-light"><tr><th>Consent</th><th style="width:140px" class="text-center">Selected</th></tr></thead><tbody>';
  foreach ($keys as $k) {
    $out .= '<tr><td>'.h(vtc_labelize($k)).'</td><td class="text-center">'.vtc_yesno_badge($row[$k]).'</td></tr>';
  }
  $out .= '</tbody></table></div>';
  return $out;
}

// ---- BHS (Beck Hopelessness Scale) scoring & renderer ----

// TEMP: DB stores BHS flipped (True=0, False=1). Invert once here.
function vtc_bhs_bool01($raw): ?int {
  $b = vtc_bool01($raw);           // 1=true, 0=false, null
  if ($b === null) return null;
  return 1 - $b;                   // flip for BHS only
}

// normalize stored answers to 1/0/null (true/false)
function vtc_bool01($raw): ?int {
  if ($raw === '' || $raw === null) return null;
  if (is_int($raw) || is_float($raw) || ctype_digit((string)$raw)) {
    $v = (int)$raw; return ($v === 1 || $v === 0) ? $v : null;
  }
  $s = strtolower(trim((string)$raw));
  if (in_array($s, ['1','true','t','yes','y'], true))  return 1;
  if (in_array($s, ['0','false','f','no','n'], true))  return 0;
  return null;
}

function vtc_bhs_score(array $row, array $keys): array {
  // Reverse-scored: FALSE=1, TRUE=0
  $reverse_nums = [1,3,5,6,8,10,13,15,19];

  $sum = 0; $answered = 0; $total = count($keys);

  foreach ($keys as $k) {
    // extract leading item number from key like "12_i_text..."
    $num = null;
    if (preg_match('/^(\d+)_/', (string)$k, $m)) $num = (int)$m[1];

    $b = vtc_bhs_bool01($row[$k] ?? null);  // 1=true, 0=false, null=blank
    if ($b === null) continue;

    if ($num !== null && in_array($num, $reverse_nums, true)) {
      // reverse: false=1, true=0
      $sum += ($b === 0) ? 1 : 0;
    } else {
      // normal: true=1, false=0
      $sum += ($b === 1) ? 1 : 0;
    }
    $answered++;
  }

  // Category bands (standard)
  if ($answered === 0)       { $cat = 'No responses'; }
  elseif ($sum <= 3)         { $cat = 'Minimal'; }
  elseif ($sum <= 8)         { $cat = 'Mild'; }
  elseif ($sum <= 14)        { $cat = 'Moderate'; }
  else                       { $cat = 'Severe'; }

  return ['score'=>$sum,'answered'=>$answered,'total'=>$total,'category'=>$cat,'max'=>20];
}

function vtc_render_bhs_block(array $row): string {
  $keys = $GLOBALS['VTC_BHS_KEYS'] ?? [];
  if (!$keys || !vtc_any_answered($row, $keys)) return '';

  $s = vtc_bhs_score($row, $keys);
  $cls = [
    'Minimal'      => 'bg-success',
    'Mild'         => 'bg-info',
    'Moderate'     => 'bg-warning',
    'Severe'       => 'bg-danger',
    'No responses' => 'bg-secondary'
  ][$s['category']] ?? 'bg-secondary';

  $out  = '<h6 class="mb-2">BHS — Beck Hopelessness Scale ';
  $out .= '<span class="badge bg-dark">Score: '.(int)$s['score'].' / '.(int)$s['max'].'</span> ';
  $out .= '<span class="badge '.$cls.'">'.h($s['category']).'</span> ';
  $out .= '<span class="text-muted small ms-2">('.(int)$s['answered'].' of '.(int)$s['total'].' answered)</span>';
  $out .= '</h6>';

  // Keep showing the stored answers (True/False as-is)
  $out .= '<div class="table-responsive"><table class="table table-sm table-bordered mb-3" style="table-layout:fixed;width:100%">';
  $out .= '<colgroup><col style="width:64px"><col><col style="width:180px"></colgroup>';
  $out .= '<thead class="thead-light"><tr><th class="text-center">#</th><th>Item</th><th class="text-center">Answer</th></tr></thead><tbody>';

    $i = 1;
    foreach ($keys as $k) {
      $raw = $row[$k] ?? '';
      if ($raw === '' || $raw === null) {
        $val = '<span class="text-muted">—</span>';
      } else {
        $b = vtc_bhs_bool01($raw);           // 1, 0, or null
        if     ($b === 1) $val = 'T';    // show T for 1/true
        elseif ($b === 0) $val = 'F';    // show F for 0/false
        else              $val = h((string)$raw); // fallback: show as-is
      }
      $out .= '<tr><td class="text-center">'.($i++).'</td><td>'.h(vtc_labelize($k)).'</td><td class="text-center text-nowrap">'.$val.'</td></tr>';
    }

  $out .= '</tbody></table></div>';

  return $out;
}
// ---- HAM-A (Hamilton Anxiety Rating Scale) scoring & renderer ----
function vtc_hama_map_value($raw): ?int {
  if ($raw === '' || $raw === null) return null;

  // Numeric inputs
  if (is_numeric($raw)) {
    $n = (int)$raw;
    if ($n >= 1 && $n <= 5) return $n;        // already 1..5
    if ($n >= 0 && $n <= 4) return $n + 1;    // normalize 0..4 -> 1..5
    if ($n < 1)  return 1;
    if ($n > 5)  return 5;
  }

  // Text inputs -> 1..5
  $s = strtolower(trim((string)$raw));
  if ($s === '1' || $s === 'not at all')    return 1;
  if ($s === '2' || $s === 'a little bit')  return 2;
  if ($s === '3' || $s === 'moderately')    return 3;
  if ($s === '4' || $s === 'quite a bit')   return 4;
  if ($s === '5' || $s === 'extremely')     return 5;

  // Fallback: extract first integer and clamp
  if (preg_match('/-?\d+/', $s, $m)) {
    $v = (int)$m[0];
    if ($v >= 1 && $v <= 5) return $v;
    if ($v >= 0 && $v <= 4) return $v + 1;
    return max(1, min(5, $v));
  }

  return null;
}


function vtc_hama_score(array $row, array $keys): array {
  $sum_raw = 0; $answered = 0; $total = count($keys);
  foreach ($keys as $k) {
    $v = vtc_hama_map_value($row[$k] ?? null); // 1..5
    if ($v === null) continue;
    $sum_raw += $v;
    $answered++;
  }

  // Convert 1..5 to the official 0..4 metric for classification
  $sum_norm = $answered > 0 ? ($sum_raw - $answered) : 0;   // 0..4 per item
  // Category bands (standard HAM-A)
  if ($answered === 0)        { $cat = 'No responses'; }
  elseif ($sum_norm < 17)     { $cat = 'Mild'; }
  elseif ($sum_norm <= 24)    { $cat = 'Mild to Moderate'; }
  elseif ($sum_norm <= 30)    { $cat = 'Moderate to Severe'; }
  else                        { $cat = 'Severe'; }

  return [
    'score_raw' => $sum_raw,
    'score_norm'=> $sum_norm,
    'answered'  => $answered,
    'total'     => $total,
    'category'  => $cat,
    'max_raw'   => ($total * 5),  // 1..5 scale
    'max_norm'  => ($total * 4),  // 0..4 scale
  ];
}


function vtc_render_hama_block(array $row): string {
  $keys = $GLOBALS['VTC_HAMA_KEYS'] ?? [];
  if (!$keys || !vtc_any_answered($row, $keys)) return '';

  $s = vtc_hama_score($row, $keys);
  $cls = [
    'Mild'                => 'bg-success',
    'Mild to Moderate'    => 'bg-info',
    'Moderate to Severe'  => 'bg-warning',
    'Severe'              => 'bg-danger',
    'No responses'        => 'bg-secondary'
  ][$s['category']] ?? 'bg-secondary';

  $out  = '<h6 class="mb-2">HAM-A — Hamilton Anxiety Rating Scale ';
  $out .= '<span class="badge bg-dark">Score: '.(int)$s['score_raw'].' / '.(int)$s['max_raw'].'</span> ';
  $out .= '<span class="badge '.$cls.'">'.h($s['category']).'</span> ';
  $out .= '<span class="text-muted small ms-2">(standardized: '.(int)$s['score_norm'].' / '.(int)$s['max_norm'].')</span> ';
  $out .= '<span class="text-muted small ms-2">('.(int)$s['answered'].' of '.(int)$s['total'].' answered)</span>';
  $out .= '</h6>';


  // Keep showing the original TEXT answers
  $out .= '<div class="table-responsive"><table class="table table-sm table-bordered mb-3" style="table-layout:fixed;width:100%">';
  $out .= '<colgroup><col style="width:64px"><col><col style="width:220px"></colgroup>';
  $out .= '<thead class="thead-light"><tr><th class="text-center">#</th><th>Item</th><th class="text-center">Answer</th></tr></thead><tbody>';

  $i = 1;
  foreach ($keys as $k) {
    $raw = $row[$k] ?? '';
    $val = ($raw === '' || $raw === null) ? '<span class="text-muted">—</span>' : h((string)$raw);
    $out .= '<tr><td class="text-center">'.($i++).'</td><td>'.h(vtc_labelize($k)).'</td><td class="text-center text-nowrap">'.$val.'</td></tr>';
  }
  $out .= '</tbody></table></div>';

  return $out;
}
// ---- HAM-D (17-item scoring, 21 shown) ----
function vtc_hamd_item_max(string $k): int {
  static $max2 = [
    'insomnia_initial_difficulty_in_falling_asleep',
    'insomnia_middle_complains_of_being_restless_and_disturbed_during',
    'insomnia_delayed_waking_in_early_hours_of_the_morning_and_unable',
    'somatic_symptoms_gastrointestinal_loss_of_appetite_heavy_feeling',
    'somatic_symptoms_general',
    'genital_symptoms_loss_of_libido_menstrual_disturbances',
    'weight_loss',
    'insight_insight_must_be_interpreted_in_terms_of_patients_underst',
  ];
  return in_array($k, $max2, true) ? 2 : 4;
}

// map raw answer -> integer within item max
function vtc_hamd_map_value(string $k, $raw): ?int {
  if ($raw === '' || $raw === null) return null;
  $max = vtc_hamd_item_max($k);

  // numeric
  if (is_numeric($raw) || ctype_digit((string)$raw)) {
    $v = (int)$raw;
    if ($v < 0) $v = 0;
    if ($v > $max) $v = $max;
    return $v;
  }

  // text (very tolerant)
  $s = strtolower(trim((string)$raw));
  if ($s === '') return null;

  // common words
  if (strpos($s,'none') !== false || strpos($s,'absent') !== false || strpos($s,'not present') !== false) return 0;
  if (strpos($s,'mild') !== false)     return min(1, $max);
  if (strpos($s,'moderate') !== false) return min(2, $max);
  if (strpos($s,'marked') !== false)   return min(3, $max);
  if (strpos($s,'severe') !== false)   return min($max,  ($max>=3?3:2)); // severe→3 (or 2 if 0–2 scale)
  if (strpos($s,'very severe') !== false || strpos($s,'extreme') !== false) return ($max>=4?4:$max);

  // fallback: first integer token
  if (preg_match('/-?\d+/', $s, $m)) {
    $v = (int)$m[0];
    if ($v < 0) $v = 0;
    if ($v > $max) $v = $max;
    return $v;
  }
  return null;
}

function vtc_hamd_score(array $row, array $scoreKeys): array {
  $sum = 0; $answered = 0;
  foreach ($scoreKeys as $k) {
    $v = vtc_hamd_map_value($k, $row[$k] ?? null);
    if ($v === null) continue;
    $sum += $v;
    $answered++;
  }
  // Max for HAM-D-17: 9 items @ 0–4 (36) + 8 items @ 0–2 (16) = 52
  $max = 52;

  // Standard HAMD-17 severity bands
  if ($answered === 0)           { $cat = 'No responses'; $cls='bg-secondary'; }
  elseif ($sum <= 7)             { $cat = 'Normal';       $cls='bg-success'; }
  elseif ($sum <= 13)            { $cat = 'Mild';         $cls='bg-info'; }
  elseif ($sum <= 18)            { $cat = 'Moderate';     $cls='bg-warning'; }
  elseif ($sum <= 22)            { $cat = 'Severe';       $cls='bg-danger'; }
  else                           { $cat = 'Very Severe';  $cls='bg-danger'; }

  return ['score'=>$sum,'answered'=>$answered,'max'=>$max,'category'=>$cat,'badge_class'=>$cls];
}

function vtc_render_hamd_block(array $row): string {
  $allKeys   = $GLOBALS['VTC_HAMD_KEYS'] ?? [];
  $scoreKeys = $GLOBALS['VTC_HAMD_SCORABLE_17'] ?? [];
  if (!$allKeys) return '';

  // Score
  $s = vtc_hamd_score($row, $scoreKeys);

  // Header
  $out  = '<h6 class="mb-2">HAM-D — Hamilton Depression Rating Scale ';
  $out .= '<span class="badge bg-dark">Score (17): '.(int)$s['score'].' / '.(int)$s['max'].'</span> ';
  $out .= '<span class="badge '.$s['badge_class'].'">'.h($s['category']).'</span> ';
  $out .= '<span class="text-muted small ms-2">('.(int)$s['answered'].' of 17 answered)</span>';
  $out .= '</h6>';

  // Table: show all 21 items with stored answers
  $out .= '<div class="table-responsive"><table class="table table-sm table-bordered mb-3" style="table-layout:fixed;width:100%">';
  $out .= '<colgroup><col style="width:64px"><col><col style="width:180px"></colgroup>';
  $out .= '<thead class="thead-light"><tr><th class="text-center">#</th><th>Item</th><th class="text-center">Answer</th></tr></thead><tbody>';

  $i = 1;
  foreach ($allKeys as $k) {
    $raw = $row[$k] ?? '';
    $val = ($raw === '' || $raw === null) ? '<span class="text-muted">—</span>' : nl2br(h((string)$raw));
    // Use your label override if present, else prettify
    $label = function_exists('vtc_label_for') ? vtc_label_for($k) : vtc_labelize($k);
    $out .= '<tr><td class="text-center">'.($i++).'</td><td>'.nl2br(h($label)).'</td><td class="text-center">'.$val.'</td></tr>';
  }
  $out .= '</tbody></table></div>';

  return $out;
}


/** Assessments wrapper — keeps inventories together */
function vtc_render_assessments(array $row): string {
  $chunks = [];

  // Scored instruments you already added
  $chunks[] = vtc_render_bdi_block($row);
  $chunks[] = vtc_render_bai_block($row);
  $chunks[] = vtc_render_bhs_block($row);
  $chunks[] = vtc_render_hama_block($row);

  // NEW: HAM-D with scoring (shows 21 items; scores first 17)
  $chunks[] = vtc_render_hamd_block($row);

    // Add PTSD/TBI/SASSI
  $chunks[] = vtc_render_pclm_block($row);
  $chunks[] = vtc_render_tbi_block($row);
  $chunks[] = vtc_render_sassi_tf_block($row);
  $chunks[] = vtc_render_sassi_alcohol_block($row);
  $chunks[] = vtc_render_sassi_drug_block($row);


  // Remove empties & join
  $chunks = array_values(array_filter($chunks, static fn($h) => $h !== ''));
  return $chunks ? implode('', $chunks) : '<div class="text-muted">No assessments present in this VTC submission.</div>';
}


/** One-call renderer in the order you requested */
function vtc_render_review(array $row, array $demokeys, array $narkeys): string {
  $html  = '<div class="card mb-3"><div class="card-header fw-bold">Demographic Information</div><div class="card-body">';
  $html .= vtc_render_kv_table($row, $demokeys).'</div></div>';

  $html .= '<div class="card mb-3"><div class="card-header fw-bold">History & Narrative</div><div class="card-body">';
  $html .= vtc_render_narratives($row, $narkeys).'</div></div>';

  $html .= '<div class="card mb-3"><div class="card-header fw-bold">Clinical History (Quick)</div><div class="card-body">';
  $html .= vtc_render_kv_table($row, $GLOBALS['VTC_CLINICAL_QUICK_FIELDS']).'</div></div>';


  $html .= '<div class="card mb-3"><div class="card-header fw-bold">Assessments & Inventories</div><div class="card-body">';
  $html .= vtc_render_assessments($row).'</div></div>';

  $html .= '<div class="card mb-3"><div class="card-header fw-bold">Consents & Acknowledgements</div><div class="card-body">';
  $html .= vtc_render_consents($row).'</div></div>';

  return $html;
}

// ---- BDI scoring & renderer ----
function vtc_bdi_score(array $row, array $keys): array {
  $sum = 0; $answered = 0; $total = count($keys);
  foreach ($keys as $k) {
    if (!isset($row[$k]) || $row[$k] === '' || $row[$k] === null) continue;
    $raw = $row[$k];
    if (is_numeric($raw)) {
      $val = (int)$raw;
    } elseif (preg_match('/-?\d+/', (string)$raw, $m)) {
      $val = (int)$m[0];
    } else {
      continue;
    }
    // clamp to 0–3 just in case
    if ($val < 0) $val = 0;
    if ($val > 3) $val = 3;
    $sum += $val; $answered++;
  }

  // Category per your bands
  if ($answered === 0)      { $cat = 'No responses'; }
  elseif ($sum <= 13)       { $cat = 'Minimal to No Depression'; }
  elseif ($sum <= 19)       { $cat = 'Mild'; }
  elseif ($sum <= 28)       { $cat = 'Moderate'; }
  else                      { $cat = 'Severe'; }

  return ['score'=>$sum,'answered'=>$answered,'total'=>$total,'category'=>$cat,'max'=>($total*3)];
}

function vtc_render_bdi_block(array $row): string {
  $keys = $GLOBALS['VTC_BDI_KEYS'] ?? [];
  if (!$keys || !vtc_any_answered($row, $keys)) return '';

  $s = vtc_bdi_score($row, $keys);
  $cls = [
    'Minimal to No Depression' => 'bg-success',
    'Mild'                     => 'bg-info',
    'Moderate'                 => 'bg-warning',
    'Severe'                   => 'bg-danger',
    'No responses'             => 'bg-secondary'
  ][$s['category']] ?? 'bg-secondary';

  $out  = '<h6 class="mb-2">BDI — Beck Depression Inventory ';
  $out .= '<span class="badge bg-dark">Score: '.(int)$s['score'].' / '.(int)$s['max'].'</span> ';
  $out .= '<span class="badge '.$cls.'">'.h($s['category']).'</span> ';
  $out .= '<span class="text-muted small ms-2">('.(int)$s['answered'].' of '.(int)$s['total'].' answered)</span>';
  $out .= '</h6>';

  // Table (same style as other inventories)
  $out .= '<div class="table-responsive"><table class="table table-sm table-bordered mb-3" style="table-layout:fixed;width:100%">';
  $out .= '<colgroup><col style="width:64px"><col><col style="width:180px"></colgroup>';
  $out .= '<thead class="thead-light"><tr><th class="text-center">#</th><th>Item</th><th class="text-center">Answer</th></tr></thead><tbody>';

  $i = 1;
  foreach ($keys as $k) {
    $val = $row[$k] ?? '';
    $val = ($val === '' || $val === null) ? '<span class="text-muted">—</span>' : h((string)$val);
    $out .= '<tr><td class="text-center">'.($i++).'</td><td>'.h(vtc_labelize($k)).'</td><td class="text-center text-nowrap">'.$val.'</td></tr>';
  }
  $out .= '</tbody></table></div>';

  return $out;
}

// ---- BAI scoring & renderer ----
function vtc_bai_map_value($raw): ?int {
  if ($raw === '' || $raw === null) return null;
  if (is_numeric($raw)) {
    $v = (int)$raw; if ($v<0) $v=0; if ($v>3) $v=3; return $v;
  }
  $s = strtolower(trim((string)$raw));
  if (preg_match('/^not\b|^not at all\b/', $s)) return 0;
  if (preg_match('/^mild\b|^mildly\b/', $s))  return 1;
  if (preg_match('/^moderate\b|^moderately\b/', $s)) return 2;
  if (preg_match('/^severe\b|^severely\b/', $s)) return 3;
  if (preg_match('/-?\d+/', $s, $m)) { // catch "score: 2" etc.
    $v = (int)$m[0]; if ($v<0) $v=0; if ($v>3) $v=3; return $v;
  }
  return null;
}

function vtc_bai_score(array $row, array $keys): array {
  $sum = 0; $answered = 0; $total = count($keys);
  foreach ($keys as $k) {
    $val = vtc_bai_map_value($row[$k] ?? null);
    if ($val === null) continue;
    $sum += $val; $answered++;
  }
  if ($answered === 0)      { $cat = 'No responses'; }
  elseif ($sum <= 7)        { $cat = 'Minimal to No Anxiety'; }
  elseif ($sum <= 15)       { $cat = 'Mild'; }
  elseif ($sum <= 25)       { $cat = 'Moderate'; }
  else                      { $cat = 'Severe'; }

  return ['score'=>$sum,'answered'=>$answered,'total'=>$total,'category'=>$cat,'max'=>($total*3)];
}

function vtc_render_bai_block(array $row): string {
  $keys = $GLOBALS['VTC_BAI_KEYS'] ?? [];
  if (!$keys || !vtc_any_answered($row, $keys)) return '';

  $s = vtc_bai_score($row, $keys);
  $cls = [
    'Minimal to No Anxiety' => 'bg-success',
    'Mild'                  => 'bg-info',
    'Moderate'              => 'bg-warning',
    'Severe'                => 'bg-danger',
    'No responses'          => 'bg-secondary'
  ][$s['category']] ?? 'bg-secondary';

  $out  = '<h6 class="mb-2">BAI — Beck Anxiety Inventory ';
  $out .= '<span class="badge bg-dark">Score: '.(int)$s['score'].' / '.(int)$s['max'].'</span> ';
  $out .= '<span class="badge '.$cls.'">'.h($s['category']).'</span> ';
  $out .= '<span class="text-muted small ms-2">('.(int)$s['answered'].' of '.(int)$s['total'].' answered)</span>';
  $out .= '</h6>';

  // Keep showing the original TEXT answers
  $out .= '<div class="table-responsive"><table class="table table-sm table-bordered mb-3" style="table-layout:fixed;width:100%">';
  $out .= '<colgroup><col style="width:64px"><col><col style="width:180px"></colgroup>';
  $out .= '<thead class="thead-light"><tr><th class="text-center">#</th><th>Item</th><th class="text-center">Answer</th></tr></thead><tbody>';

  $i = 1;
  foreach ($keys as $k) {
    $raw = $row[$k] ?? '';
    $val = ($raw === '' || $raw === null) ? '<span class="text-muted">—</span>' : h((string)$raw);
    $out .= '<tr><td class="text-center">'.($i++).'</td><td>'.h(vtc_labelize($k)).'</td><td class="text-center text-nowrap">'.$val.'</td></tr>';
  }
  $out .= '</tbody></table></div>';

  return $out;
}


// Map F→1, ST→2, MT→3, VT→4 (pass through 1–4 if already numeric)
function pai_answer_key(string $ans): string {
  $a = strtoupper(trim($ans));
  if ($a === 'F'  || $a === 'FALSE')            return '1';
  if ($a === 'ST' || strpos($a,'SLIGHT') === 0) return '2';
  if ($a === 'MT' || strpos($a,'MAIN')   === 0) return '3';
  if ($a === 'VT' || strpos($a,'VERY')   === 0) return '4';
  if (in_array($a, ['1','2','3','4'], true))    return $a; // already numeric
  return '';
}


/* ------------------------------------------------------------------
 * Config — set your actual table names & typical id/name/dob/created columns
 * ------------------------------------------------------------------*/
$PAI_CFG = [
  'table'    => 'evaluations_pai',
  'pk'       => 'id',
  'first'    => 'name_first',
  'middle'   => 'name_middle',
  'last'     => 'name_last',
  'dob'      => 'date_of_birth',
  'email'    => 'email',
  'gender'   => 'gender',
  'age'      => 'age',
  'occupation'=> 'occupation',
  // prefer submitted_at; fall back to created_at if null
  'created_candidates' => ['submitted_at', 'created_at'],
  'updated' => 'updated_at',
];
$VTC_CFG = [
  'table'    => 'evaluations_vtc',
  'pk'       => 'id',
  'first'    => 'first_name',
  'last'     => 'last_name',
  'dob'      => 'dob',
  'email'    => 'email',
  'gender'   => 'gender',
  'age'      => 'age',
  'occupation'=> 'occupation',
  'phone'    => 'phone_primary',
  'addr1'    => 'address1',
  'addr2'    => 'address2',
  'city'     => 'city',
  'state'    => 'state',
  'zip'      => 'zip',
  'created'  => 'created_at',
  'updated'  => 'updated_at',
  // optional display niceties if present
  'legal_first' => 'legal_first_name',
  'legal_last'  => 'legal_last_name',
  'eval_first'  => 'evaluator_first_name',
  'eval_last'   => 'evaluator_last_name',
];

/* ------------------------------------------------------------------
 * Helpers: schema resolution, parsing, formatting
 * ------------------------------------------------------------------*/
function table_exists(mysqli $db, string $table): bool {
  $t = mysqli_real_escape_string($db,$table);
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
  $res = $db->query($sql);
  return $res && $res->num_rows>0;
}

function resolve_columns(mysqli $db, array $cfg): array {
  if (!table_exists($db, $cfg['table'])) return [];
  $cols = [];
  $res = $db->query("SHOW COLUMNS FROM `{$cfg['table']}`");
  if ($res) while($r=$res->fetch_assoc()) $cols[strtolower($r['Field'])] = $r['Field'];

  $pick = function($cands) use($cols){
    if (is_string($cands)) {
      $k = strtolower($cands);
      return $cols[$k] ?? null;
    }
    if (is_array($cands)) {
      foreach ($cands as $c) { $k = strtolower($c); if (isset($cols[$k])) return $cols[$k]; }
    }
    return null;
  };

  // created column: prefer created_candidates if provided, then 'created'
  $created = null;
  if (!empty($cfg['created_candidates']) && is_array($cfg['created_candidates'])) {
    $created = $pick($cfg['created_candidates']);
  }
  if (!$created) {
    $created = $pick($cfg['created'] ?? []);
  }

  return [
    'table'   => $cfg['table'],
    'id'      => $pick($cfg['pk'] ?? ($cfg['id'] ?? [])),
    'first'   => $pick($cfg['first']),
    'last'    => $pick($cfg['last']),
    'dob'     => $pick($cfg['dob']),
    'created' => $created,
  ];
}


function normalize_dob($v): ?string {
  if ($v===null||$v==='') return null; $v=trim((string)$v);
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)) return $v;
  if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/',$v,$m)) return sprintf('%04d-%02d-%02d',(int)$m[3],(int)$m[1],(int)$m[2]);
  $ts=strtotime($v); return $ts?date('Y-m-d',$ts):null;
}
function normalize_name($v): string { $v=trim((string)$v); $v=preg_replace('/\s+/', ' ', $v); return mb_strtolower($v,'UTF-8'); }
function fmt_dt(?string $ts): string { if(!$ts) return ''; $t=strtotime($ts); return $t?date('M j, Y g:i a',$t):h($ts); }
function fmt_dob(?string $d): string { if(!$d) return ''; $t=strtotime($d); return $t?date('M j, Y',$t):h($d); }

function uc_label(string $k): string {
  // Friendly label from snake/camel + common acronyms
  $map = [
    'dob'=>'Date of Birth','ssn'=>'SSN','phone'=>'Phone','email'=>'Email','zip'=>'ZIP','address'=>'Address',
    'hama'=>'HAM-A','bhs'=>'BHS','vtc'=>'VTC','pai'=>'PAI','id'=>'ID'
  ];
  $base = preg_replace('/[_\-]+/',' ', $k);
  $base = preg_replace('/([a-z])([A-Z])/','\1 \2',$base);
  $base = ucwords($base);
  foreach($map as $needle=>$rep){ if (stripos($base,$needle) !== false) { $base = preg_replace('/'.preg_quote($needle,'/').'/i',$rep,$base); } }
  return $base;
}

// pick first non-empty from a row by candidate list
function pick_from_row(array $row, array $cands) {
  foreach ($cands as $c) {
    if (isset($row[$c]) && trim((string)$row[$c]) !== '') return $row[$c];
  }
  return null;
}

// pick "created at" from PAI with fallback to created_at
function created_from(array $row, array $cands) {
  foreach ($cands as $c) {
    if (array_key_exists($c, $row) && !empty($row[$c])) return $row[$c];
  }
  return null;
}


/* ------------------------------------------------------------------
 * Extra formatting helpers (multiline cleanup + demo value rendering)
 * ------------------------------------------------------------------*/
function clean_multiline($s){
  if ($s === null) return null;
  $s = (string)$s;
  $s = str_replace(["\r\n","\n","\\n","\\r\\n","/n"], "\n", $s);
  $s = preg_replace("/\n{3,}/", "\n\n", $s);
  return trim($s);
}


function dv(array $demo, string $k): string {
  $v = $demo[$k] ?? null;
  if ($v === null || trim((string)$v) === '') return '<span class="text-muted">—</span>';
  $v = clean_multiline($v);
  return nl2br(h((string)$v));
}

/* ------------------------------------------------------------------
 * Input parsing
 * ------------------------------------------------------------------*/
$form = strtolower(trim($_GET['form'] ?? 'combined'));
if (!in_array($form,['combined','pai','vtc'],true)) $form = 'combined';
$key  = $_GET['key'] ?? '';
$pai_id_sel = isset($_GET['pai_id']) ? trim((string)$_GET['pai_id']) : null;
$vtc_id_sel = isset($_GET['vtc_id']) ? trim((string)$_GET['vtc_id']) : null;

$who = ['first'=>'','last'=>'','dob'=>''];
if ($key !== '') {
  $raw = base64_decode(strtr($key, ' ', '+')); // in case of URL-space
  $arr = json_decode((string)$raw, true);
  if (is_array($arr)) {
    $who['first'] = (string)($arr['first'] ?? '');
    $who['last']  = (string)($arr['last'] ?? '');
    $who['dob']   = normalize_dob($arr['dob'] ?? '') ?: '';
  }
}
// allow direct override via query
$who['first'] = $_GET['first'] ?? $who['first'];
$who['last']  = $_GET['last']  ?? $who['last'];
$who['dob']   = normalize_dob($_GET['dob'] ?? $who['dob']) ?: $who['dob'];

$first_key = normalize_name($who['first']);
$last_key  = normalize_name($who['last']);
$dob_key   = $who['dob'];

$errors = [];
if ($first_key==='' || $last_key==='' || $dob_key==='') {
  $errors[] = 'Missing or invalid client key (first, last, dob).';
}

/* ------------------------------------------------------------------
 * Resolve actual columns
 * ------------------------------------------------------------------*/
$pai = resolve_columns($link, $PAI_CFG);
$vtc = resolve_columns($link, $VTC_CFG);
if (!$pai) $errors[] = "PAI table '{$PAI_CFG['table']}' not found.";
if (!$vtc) $errors[] = "VTC table '{$VTC_CFG['table']}' not found.";

/* ------------------------------------------------------------------
 * Fetch all matching rows per form for this client (ordered newest first)
 * ------------------------------------------------------------------*/
function fetch_form_rows(mysqli $db, array $meta, string $first_key, string $last_key, string $dob_key): array {
  if (!$meta || !$meta['id'] || !$meta['first'] || !$meta['last'] || !$meta['dob']) return [];
  $t=$meta['table']; $id=$meta['id']; $fn=$meta['first']; $ln=$meta['last']; $dob=$meta['dob']; $cr=$meta['created'];
  $orderCol = $cr ?: $id;
  $sql = "SELECT * FROM `$t`\n          WHERE LOWER(TRIM(`$fn`))=? AND LOWER(TRIM(`$ln`))=? AND DATE(`$dob`)=?\n          ORDER BY `$orderCol` DESC";
  $stmt = $db->prepare($sql);
  if (!$stmt) return [];
  $stmt->bind_param('sss', $first_key, $last_key, $dob_key);
  if(!$stmt->execute()) return [];
  $res = $stmt->get_result();
  $rows = [];
  while($r=$res->fetch_assoc()) $rows[] = $r;
  return $rows;
}

$pai_rows = $pai ? fetch_form_rows($link, $pai, $first_key, $last_key, $dob_key) : [];
$vtc_rows = $vtc ? fetch_form_rows($link, $vtc, $first_key, $last_key, $dob_key) : [];

// Choose selected row (by id if provided; else newest)
function pick_row(array $rows, ?string $id_key): ?array {
  if (!$rows) return null;
  if ($id_key===null || $id_key==='') return $rows[0];
  foreach($rows as $r){ if ((string)reset($r) === $id_key || in_array($id_key,$r,true)) return $r; }
  // fallback try matching common id fields
  foreach($rows as $r){ foreach(['id','pai_id','vtc_id','submission_id'] as $k){ if(isset($r[$k]) && (string)$r[$k]===$id_key) return $r; } }
  return $rows[0];
}

$one_pai = pick_row($pai_rows, $pai_id_sel);
$one_vtc = pick_row($vtc_rows, $vtc_id_sel);

// Build unified demographics, preferring VTC where it has richer info
$pai_row = $one_pai;
$vtc_row = $one_vtc;

$demographics = [
  'first_name' => pick_from_row($vtc_row ?? [], [$VTC_CFG['first']]) ?: pick_from_row($pai_row ?? [], [$PAI_CFG['first']]),
  'last_name'  => pick_from_row($vtc_row ?? [], [$VTC_CFG['last']])  ?: pick_from_row($pai_row ?? [], [$PAI_CFG['last']]),
  'dob'        => pick_from_row($vtc_row ?? [], [$VTC_CFG['dob']])   ?: pick_from_row($pai_row ?? [], [$PAI_CFG['dob']]),
  'email'      => pick_from_row($vtc_row ?? [], [$VTC_CFG['email']]) ?: pick_from_row($pai_row ?? [], [$PAI_CFG['email']]),
  'phone'      => pick_from_row($vtc_row ?? [], [$VTC_CFG['phone']]),
  'gender'     => pick_from_row($vtc_row ?? [], [$VTC_CFG['gender']]) ?: pick_from_row($pai_row ?? [], [$PAI_CFG['gender']]),
  'address1'   => pick_from_row($vtc_row ?? [], [$VTC_CFG['addr1']]),
  'address2'   => pick_from_row($vtc_row ?? [], [$VTC_CFG['addr2']]),
  'city'       => pick_from_row($vtc_row ?? [], [$VTC_CFG['city']]),
  'state'      => pick_from_row($vtc_row ?? [], [$VTC_CFG['state']]),
  'zip'        => pick_from_row($vtc_row ?? [], [$VTC_CFG['zip']]),
];

// PAI timestamps (prefer submitted_at)
$pai_created_at = $pai_row ? created_from($pai_row, $PAI_CFG['created_candidates']) : null;
$vtc_created_at = $vtc_row[$VTC_CFG['created']] ?? null;

// Ensure core demo fields use key values when empty
if (!$demographics['first_name']) $demographics['first_name'] = $who['first'];
if (!$demographics['last_name'])  $demographics['last_name']  = $who['last'];
if (!$demographics['dob'])        $demographics['dob']        = $who['dob'];

/* ------------------------------------------------------------------
 * Prepare rendering helpers for form data
 * ------------------------------------------------------------------*/
$exclude_keys_common = [
  // identifiers & metadata
  'id','pai_id','vtc_id','submission_id','created_at','updated_at','submitted_at','ts','timestamp','ip','ip_address','user_agent','csrf_token','signature','sig','clinic_folder',
  // demographics (various spellings)
  'first_name','fname','given_name','name_first','name_middle','name_last',
  'last_name','lname','surname','family_name','legal_first_name','legal_last_name',
  'date_of_birth','dob','birth_date','age','gender','occupation',
  'phone','phone_primary','phone_number','mobile','cell',
  'email','email_address',
  'address','address1','address_line1','street','street_address','home_address','address2','address_line2','apt','unit',
  'city','town','state','province','region','zip','zipcode','postal','postal_code'
];


function render_kv_table(array $row, array $exclude=[]): string {
  if (!$row) return '<div class="text-muted">No data</div>';
  $out = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">
';
  foreach(array_keys($row) as $k){
    if (in_array($k,$exclude,true)) continue;
    $raw = $row[$k];
    $val = clean_multiline($raw);
    $isEmpty = ($val===null || trim((string)$val)==='');
    $label = uc_label($k);
    $valOut = $isEmpty ? '<span class="text-muted">—</span>' : nl2br(h((string)$val));
    $out .= '<tr class="kv-row" data-empty="'.($isEmpty?'1':'0').'"><th style="width:30%">'.h($label).'</th><td>'.$valOut.'</td></tr>
';
  }
  $out .= '</table></div>';
  return $out;
}

// --- PAI Q&A helpers (adds Answer Key column) ---
if (!function_exists('pai_qkey_candidates')) {
  function pai_qkey_candidates(int $n): array {
    $n3 = sprintf('%03d',$n);
    return ["q$n3","Q$n3","q$n","Q$n"];  // try q001, Q001, q1, Q1
  }
  function pai_answer_for(array $row, int $n): string {
    foreach (pai_qkey_candidates($n) as $k) {
      if (array_key_exists($k, $row)) return trim((string)$row[$k]);
    }
    return '';
  }
  // Map F→1, ST→2, MT→3, VT→4 (also pass through 1-4 if stored numerically)
  function pai_answer_key(string $ans): string {
    $a = strtoupper(trim($ans));
    if ($a === 'F'  || $a === 'FALSE')           return '1';
    if ($a === 'ST' || strpos($a,'SLIGHT') === 0) return '2';
    if ($a === 'MT' || strpos($a,'MAIN')  === 0) return '3';
    if ($a === 'VT' || strpos($a,'VERY')  === 0) return '4';
    if (in_array($a, ['1','2','3','4'], true))    return $a; // already numeric
    return '';
  }
  function get_pai_items(): array {
    // If you've pasted $PAI_ITEMS = [1=>'...', ... 344=>'...']; use it.
    if (isset($GLOBALS['PAI_ITEMS']) && is_array($GLOBALS['PAI_ITEMS'])) return $GLOBALS['PAI_ITEMS'];
    // Fallback labels if not present
    $items = [];
    for ($i=1;$i<=344;$i++) $items[$i] = 'Q'.sprintf('%03d',$i);
    return $items;
  }
  function render_pai_qna(array $row, array $PAI_ITEMS): string {
    $items = $PAI_ITEMS ?: get_pai_items();
    $out  = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
    $out .= '<thead class="thead-light"><tr>
              <th style="width:70px">#</th>
              <th>Item</th>
              <th style="width:220px">Answer</th>
              <th style="width:140px">Answer Key</th>
            </tr></thead><tbody>';
    foreach ($items as $num => $text) {
      $ans = pai_answer_for($row, (int)$num);
      $key = pai_answer_key($ans);
      $isEmpty = ($ans === '' || $ans === null);
      $ansOut = $isEmpty ? '<span class="text-muted">—</span>' : nl2br(h($ans));
      $keyOut = $key === '' ? '<span class="text-muted">—</span>' : h($key);
      $out .= '<tr class="kv-row" data-empty="'.($isEmpty?'1':'0').'">
                <td>'.(int)$num.'</td><td>'.h($text).'</td><td>'.$ansOut.'</td><td>'.$keyOut.'</td>
              </tr>';
    }
    $out .= '</tbody></table></div>';
    return $out;
  }
}

/* ------------------------------------------------------------------
 * Page
 * ------------------------------------------------------------------*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Evaluation Review — <?=h($demographics['last_name']?($demographics['last_name'].', '.$demographics['first_name']):'Client')?> (<?=h(fmt_dob($demographics['dob']))?>)</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
  body{padding-top:56px;background:#f5f6fa}
  .chip{display:inline-flex;align-items:center;border-radius:999px;padding:.15rem .5rem;font-size:.8rem;border:1px solid #e5e7eb;background:#fff}
  .chip i{font-size:.9rem;margin-right:.25rem}
  .chip.ok{border-color:#b8e0c2;background:#f0fff4}
  .chip.miss{border-color:#ffd5d5;background:#fff5f5}
  .card{box-shadow:0 2px 8px rgba(0,0,0,.04)}
  .sticky-hdr{position:sticky;top:56px;z-index:10;background:#f5f6fa;padding:8px 0 0}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<main class="container-fluid pt-3">
  <div class="sticky-hdr">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div>
        <a href="evaluation-index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i>Back to Index</a>
      </div>
      <div>
        <div class="btn-group" role="group" aria-label="view">
          <?php $base = 'evaluation-review.php?'.http_build_query(['key'=>$key]); ?>
          <a class="btn btn-sm btn-outline-primary<?= $form==='combined'?' active':'' ?>" href="<?=$base?>">Combined</a>
          <a class="btn btn-sm btn-outline-primary<?= $form==='pai'?' active':'' ?>" href="<?=$base.'&form=pai'?>">PAI</a>
          <a class="btn btn-sm btn-outline-primary<?= $form==='vtc'?' active':'' ?>" href="<?=$base.'&form=vtc'?>">VTC</a>
        </div>
      </div>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><strong>Unable to load evaluation.</strong> <?=h(implode(' ', $errors))?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-body">
      <div class="d-md-flex align-items-center justify-content-between">
        <div>
          <h4 class="mb-1"><?=h($demographics['last_name'])?>, <?=h($demographics['first_name'])?> <small class="text-muted">(<?=h(fmt_dob($demographics['dob']))?>)</small></h4>
          <div class="text-muted small">Client key matched by First + Last + DOB</div>
        </div>
        <div class="mt-2 mt-md-0">
          <span class="chip <?= $one_pai?'ok':'miss' ?> mr-1"><i class="fas <?= $one_pai?'fa-check-circle text-success':'fa-times-circle text-danger'?>"></i>PAI <?= $one_pai? ('• '.h(fmt_dt($one_pai[$pai['created']] ?? ''))):'Missing' ?></span>
          <span class="chip <?= $one_vtc?'ok':'miss' ?>"><i class="fas <?= $one_vtc?'fa-check-circle text-success':'fa-times-circle text-danger'?>"></i>VTC <?= $one_vtc? ('• '.h(fmt_dt($one_vtc[$vtc['created']] ?? ''))):'Missing' ?></span>
        </div>
      </div>

      <div class="row mt-3">
  <div class="col-md-4 col-lg-3">
    <div class="text-muted small">Email</div>
    <div><?= dv($demographics,'email') ?></div>
  </div>
  <div class="col-md-4 col-lg-3">
    <div class="text-muted small">Phone</div>
    <div><?= dv($demographics,'phone') ?></div>
  </div>
  <div class="col-md-4 col-lg-3">
    <div class="text-muted small">Gender</div>
    <div><?= dv($demographics,'gender') ?></div>
  </div>
</div>
<div class="row mt-2">
  <div class="col-lg-4">
    <div class="text-muted small">Address 1</div>
    <div><?= dv($demographics,'address1') ?></div>
  </div>
  <div class="col-lg-4">
    <div class="text-muted small">Address 2</div>
    <div><?= dv($demographics,'address2') ?></div>
  </div>
  <div class="col-sm-6 col-lg-2">
    <div class="text-muted small">City</div>
    <div><?= dv($demographics,'city') ?></div>
  </div>
  <div class="col-6 col-lg-1">
    <div class="text-muted small">State</div>
    <div><?= dv($demographics,'state') ?></div>
  </div>
<div class="col-6 col-lg-1">
    <div class="text-muted small">ZIP</div>
    <div><?= dv($demographics,'zip') ?></div>
  </div>
</div>
      </div>
    </div>
  </div>

  <?php if ($form==='pai' || $form==='vtc'): ?>
    <?php
      $isPai = ($form==='pai');
      $meta  = $isPai ? $pai : $vtc;
      $rows  = $isPai ? $pai_rows : $vtc_rows;
      $one   = $isPai ? $one_pai : $one_vtc;
      $idFld = $meta['id'] ?? 'id';
      $crFld = $meta['created'] ?? $idFld;
    ?>
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div><strong><?= $isPai? 'PAI Evaluation' : 'VTC Evaluation' ?></strong> <?= $one? '<span class="text-muted small">(Submitted '.h(fmt_dt($one[$crFld] ?? '')).')</span>' : '' ?></div>
        <div>
          <?php if (count($rows)>1): ?>
            <form method="get" class="form-inline my-0">
              <input type="hidden" name="form" value="<?=h($form)?>">
              <input type="hidden" name="key" value="<?=h($key)?>">
              <label class="mr-2 mb-0 small">Submission:</label>
              <select name="<?= $isPai?'pai_id':'vtc_id' ?>" class="form-control form-control-sm" onchange="this.form.submit()">
                <?php foreach($rows as $r): $optId=$r[$idFld] ?? reset($r); ?>
                  <?php $label = ($r[$crFld] ?? '') ? fmt_dt($r[$crFld]) : ('ID '.h((string)$optId)); ?>
                  <option value="<?=h((string)$optId)?>" <?= ($one && (string)($one[$idFld]??'')===(string)$optId)?'selected':'' ?>><?=h($label)?></option>
                <?php endforeach; ?>
              </select>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="small text-muted">Toggle: <a href="#" id="toggleEmpty" onclick="return false;">Hide empty fields</a></div>
          <div class="small text-muted">Records: <?=count($rows)?></div>
        </div>
        <?php if ($one): ?>
        <?php if ($isPai): ?>
            <?= render_pai_qna($one, isset($PAI_ITEMS)?$PAI_ITEMS:[]) ?>
            <?php
                // Optional: also show non-item metadata, hiding q001..q344
                $exclude_pai_items = $exclude_keys_common;
                for ($i=1; $i<=344; $i++) { $exclude_pai_items[] = sprintf('q%03d', $i); }
                echo render_kv_table($one, $exclude_pai_items);
            ?>
            <?php else: ?>
                <?= vtc_render_review($one, $VTC_DEMOGRAPHIC_FIELDS, $VTC_NARRATIVE_FIELDS) ?>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-muted">No matching submission found for this client.</div>
        <?php endif; ?>


      </div>
    </div>
  <?php else: ?>
    <div class="row">
      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header d-flex align-items-center justify-content-between">
            <div><strong>PAI Evaluation</strong> <?= $one_pai? '<span class="text-muted small">(Submitted '.h(fmt_dt($one_pai[$pai['created']] ?? '')).')</span>' : '<span class="text-danger small">Missing</span>' ?></div>
            <div>
              <?php if (count($pai_rows)>1): ?>
                <form method="get" class="form-inline my-0">
                  <input type="hidden" name="key" value="<?=h($key)?>">
                  <label class="mr-2 mb-0 small">Submission:</label>
                  <select name="pai_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    <?php foreach($pai_rows as $r): $optId=$r[$pai['id']] ?? reset($r); ?>
                      <?php $label = ($r[$pai['created']] ?? '') ? fmt_dt($r[$pai['created']]) : ('ID '.h((string)$optId)); ?>
                      <option value="<?=h((string)$optId)?>" <?= ($one_pai && (string)($one_pai[$pai['id']]??'')===(string)$optId)?'selected':'' ?>><?=h($label)?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body">
            <?php if ($one_pai): ?>
                <?= render_pai_qna($one_pai, isset($PAI_ITEMS)?$PAI_ITEMS:[]) ?>
            <?php else: ?>
                <div class="text-muted">No PAI submission for this client.</div>
            <?php endif; ?>


          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card mb-3">
          <div class="card-header d-flex align-items-center justify-content-between">
            <div><strong>VTC Evaluation</strong> <?= $one_vtc? '<span class="text-muted small">(Submitted '.h(fmt_dt($one_vtc[$vtc['created']] ?? '')).')</span>' : '<span class="text-danger small">Missing</span>' ?></div>
            <div>
              <?php if (count($vtc_rows)>1): ?>
                <form method="get" class="form-inline my-0">
                  <input type="hidden" name="key" value="<?=h($key)?>">
                  <label class="mr-2 mb-0 small">Submission:</label>
                  <select name="vtc_id" class="form-control form-control-sm" onchange="this.form.submit()">
                    <?php foreach($vtc_rows as $r): $optId=$r[$vtc['id']] ?? reset($r); ?>
                      <?php $label = ($r[$vtc['created']] ?? '') ? fmt_dt($r[$vtc['created']]) : ('ID '.h((string)$optId)); ?>
                      <option value="<?=h((string)$optId)?>" <?= ($one_vtc && (string)($one_vtc[$vtc['id']]??'')===(string)$optId)?'selected':'' ?>><?=h($label)?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body">
            <?= $one_vtc ? vtc_render_review($one_vtc, $VTC_DEMOGRAPHIC_FIELDS, $VTC_NARRATIVE_FIELDS) : '<div class="text-muted">No VTC submission for this client.</div>' ?>

          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

</main>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
<script>
  (function(){
    var hideEmpty = true;
    function applyToggle(){
      var rows = document.querySelectorAll('.kv-row');
      rows.forEach(function(tr){
        var isEmpty = tr.getAttribute('data-empty') === '1';
        tr.style.display = (hideEmpty && isEmpty) ? 'none' : '';
      });
      var a = document.getElementById('toggleEmpty');
      if (a) a.textContent = hideEmpty ? 'Show empty fields' : 'Hide empty fields';
    }
    document.addEventListener('click', function(e){
      if (e.target && e.target.id==='toggleEmpty'){ hideEmpty = !hideEmpty; applyToggle(); }
    });
    applyToggle();
  })();
</script>
</body>
</html>
