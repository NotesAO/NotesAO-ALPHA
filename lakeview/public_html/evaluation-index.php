<?php
/**
 * evaluation-index.php — Lakeview Evaluations (program-scoped)
 *
 * Programs:
 *   - DWII:  evaluations_sassi, evaluations_dwii_a, evaluations_dwii_b, evaluations_dwii_exit
 *   - DWIE:  evaluations_bdi, evaluations_ndp
 *   - DOEP:  evaluations_dast
 *   - All :  union of the above
 *
 * Features:
 *   - Program switcher (chips) + search + date range + sort + pagination
 *   - Table/column autodetection (safe if a table is missing)
 *   - Groups by email (preferred) or by first+last fallback
 *   - Shows per-form counts and latest submission timestamp
 *
 * Requires:
 *   - auth.php (provides $con) and optional helpers.php (h())
 */

declare(strict_types=1);

include_once 'auth.php';
check_loggedin($con);
require_once 'helpers.php';
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$link = $con; $link->set_charset('utf8mb4');
if (session_status()===PHP_SESSION_NONE) session_start();

/* ------------------------------------------------------------------
 * Program selection
 *   Priority: ?program= → $_SESSION['program_code'] → 'dwii'
 *   Allowed:  dwii | dwie | doep | all
 * ------------------------------------------------------------------*/
$allowed_programs = ['dwii','dwie','doep','all'];
$program = strtolower(trim($_GET['program'] ?? ($_SESSION['program_code'] ?? 'dwii')));
if (!in_array($program, $allowed_programs, true)) $program = 'dwii';

/* ------------------------------------------------------------------
 * Forms per program
 *   Each entry describes: table, and candidate columns
 *   We do not assume DOB; we key by email or by (first+last) fallback.
 * ------------------------------------------------------------------*/
$FORM_DEFS = [
  // --- DWII ---
  'sassi' => [
    'label'   => 'SASSI',
    'table'   => 'evaluations_sassi',
    'id'      => ['id','submission_id'],
    'first'   => ['first_name','fname','given_name'],
    'last'    => ['last_name','lname','surname','family_name'],
    'email'   => ['email'],
    'created' => ['created_at','submitted_at','created','ts','timestamp']
  ],
  'dwii_a' => [
    'label'   => 'DWII A',
    'table'   => 'evaluations_dwii_a',
    'id'      => ['id','submission_id'],
    'first'   => ['first_name'],
    'last'    => ['last_name'],
    'email'   => ['email'], // may not exist; resolver will tolerate
    'created' => ['created_at','submitted_at','created']
  ],
  'dwii_b' => [
    'label'   => 'DWII B',
    'table'   => 'evaluations_dwii_b',
    'id'      => ['id','submission_id'],
    'first'   => ['first_name'],
    'last'    => ['last_name'],
    'email'   => ['email'],
    'created' => ['created_at','submitted_at','created']
  ],
  'dwii_exit' => [
    'label'   => 'DWII Exit',
    'table'   => 'evaluations_dwii_exit',
    'id'      => ['id','submission_id'],
    'first'   => ['first_name'],
    'last'    => ['last_name'],
    'email'   => ['email'],
    'created' => ['created_at','submitted_at','created']
  ],
  // --- DWIE ---
  'bdi' => [
    'label'   => 'BDI',
    'table'   => 'evaluations_bdi',
    'id'      => ['id','submission_id'],
    'first'   => ['first_name','fname','given_name'],
    'last'    => ['last_name','lname','surname','family_name'],
    'email'   => ['email'],
    'created' => ['created_at','submitted_at','created','ts','timestamp']
  ],
  'ndp' => [
    'label'   => 'NDP',
    'table'   => 'evaluations_ndp',
    'id'      => ['id','submission_id'],
    'first'   => ['first_name','first','fname'],
    'last'    => ['last_name','last','lname'],
    'email'   => ['email'],
    'created' => ['created_at','submitted_at','created','ts','timestamp']
  ],
  // --- DOEP ---
  'dast' => [
    'label'   => 'DAST',
    'table'   => 'evaluations_dast',
    'id'      => ['id','submission_id'],
    'first'   => ['first_name','fname','given_name'],
    'last'    => ['last_name','lname','surname','family_name'],
    'email'   => ['email'],
    'created' => ['created_at','submitted_at','created','ts','timestamp']
  ],
];

$PROGRAM_FORMS = [
  'dwii' => ['sassi','dwii_a','dwii_b','dwii_exit'],
  'dwie' => ['bdi','ndp'],
  'doep' => ['dast'],
  'all'  => array_keys($FORM_DEFS),
];

/* Which forms are *active* for this page */
$FORMS = $PROGRAM_FORMS[$program];

/* ------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------*/
function table_exists(mysqli $db, string $table): bool {
  $esc = $db->real_escape_string($table);
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$esc' LIMIT 1";
  $res = $db->query($sql);
  return $res && $res->num_rows > 0;
}

function resolve_columns(mysqli $db, array $def): array {
  $table = $def['table'];
  if (!table_exists($db, $table)) return [];

  $cols = [];
  if ($res = $db->query("SHOW COLUMNS FROM `$table`")) {
    while ($r = $res->fetch_assoc()) $cols[strtolower($r['Field'])] = true;
  }
  $pick = function(array $cands) use($cols){
    foreach ($cands as $c) if (isset($cols[strtolower($c)])) return $c;
    return null;
  };
  return [
    'table'   => $table,
    'label'   => $def['label'],
    'id'      => $pick($def['id']),
    'first'   => $pick($def['first']),
    'last'    => $pick($def['last']),
    'email'   => $pick($def['email'] ?? []),
    'created' => $pick($def['created']),
  ];
}

function normalize_name_key(string $s): string {
  $s = preg_replace('/\s+/',' ', trim($s));
  return mb_strtolower($s, 'UTF-8');
}
function fmt_dt(?string $ts): string {
  if (!$ts) return '';
  $t = strtotime($ts); return $t ? date('M j, Y g:i a', $t) : h($ts);
}

/* ------------------------------------------------------------------
 * Filters / sort / pagination
 * ------------------------------------------------------------------*/
$search    = trim($_GET['search'] ?? '');
$from_date = trim($_GET['from']   ?? '');
$to_date   = trim($_GET['to']     ?? '');
$order     = trim($_GET['order']  ?? 'recent'); // recent|name
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = min(200, max(10, (int)($_GET['per'] ?? 50)));
if (!in_array($order, ['recent','name'], true)) $order = 'recent';

/* ------------------------------------------------------------------
 * Resolve all active forms
 * ------------------------------------------------------------------*/
$formsMeta = [];
$warnings  = [];
foreach ($FORMS as $k) {
  $meta = resolve_columns($link, $FORM_DEFS[$k]);
  if (!$meta || !$meta['table'] || !$meta['id'] || !$meta['first'] || !$meta['last'] || !$meta['created']) {
    $warnings[] = "{$FORM_DEFS[$k]['label']} table '{$FORM_DEFS[$k]['table']}' missing or columns unresolved.";
    continue;
  }
  $formsMeta[$k] = $meta;
}

/* ------------------------------------------------------------------
 * Fetch rows for each active form with coarse SQL filters
 * Key by:  email (lowercased) if present; else last|first (normalized)
 * ------------------------------------------------------------------*/
function fetch_rows(mysqli $db, array $m, string $tag, string $search, string $from_date, string $to_date): array {
  $t = $m['table']; $id=$m['id']; $fn=$m['first']; $ln=$m['last']; $cr=$m['created'];
  $email = $m['email'];

  $w = [];
  if ($from_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from_date)) $w[] = "`$cr` >= '".mysqli_real_escape_string($db,$from_date)." 00:00:00'";
  if ($to_date   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to_date))   $w[] = "`$cr` <= '".mysqli_real_escape_string($db,$to_date)." 23:59:59'";

  $searchSql = '';
  if ($search !== '') {
    $esc = mysqli_real_escape_string($db,$search);
    $parts = ["`$fn` LIKE '%$esc%'", "`$ln` LIKE '%$esc%'"];
    if ($email) $parts[] = "`$email` LIKE '%$esc%'";
    $searchSql = '(' . implode(' OR ', $parts) . ')';
  }

  $where = [];
  if ($w) $where[] = '(' . implode(' AND ',$w) . ')';
  if ($searchSql) $where[] = $searchSql;
  $whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

  $sel = "`$id` AS id, `$fn` AS first_name, `$ln` AS last_name, `$cr` AS created_at";
  if ($email) $sel .= ", `$email` AS email";

  $sql = "SELECT $sel FROM `$t` $whereSql";
  $rows = [];
  if ($res = $db->query($sql)) {
    while ($r = $res->fetch_assoc()) $rows[] = $r + ['_form'=>$tag];
  }
  return $rows;
}

$rowsPerForm = []; // tag => rows[]
foreach ($formsMeta as $tag => $meta) {
  $rowsPerForm[$tag] = fetch_rows($link, $meta, $tag, $search, $from_date, $to_date);
}

/* ------------------------------------------------------------------
 * Combine into "clients" keyed by email or name
 * Structure:
 *   clients[key] = [
 *     first,last,email?, forms => [tag => ['count'=>N,'latest'=>ts]]
 *     latest_any => ts
 *   ]
 * ------------------------------------------------------------------*/
$clients = [];
$push = function(array $row) use (&$clients){
  $first = (string)($row['first_name'] ?? '');
  $last  = (string)($row['last_name'] ?? '');
  $email = strtolower(trim((string)($row['email'] ?? '')));
  $created = (string)($row['created_at'] ?? '');
  $tag = (string)($row['_form'] ?? '');

  $nameKey = normalize_name_key($last.'|'.$first);
  $key = $email !== '' ? ('e:'.$email) : ('n:'.$nameKey);

  if (!isset($clients[$key])) {
    $clients[$key] = [
      'first' => $first, 'last'=>$last, 'email'=>$email,
      'forms' => [], 'latest_any'=>null
    ];
  }
  if (!isset($clients[$key]['forms'][$tag])) {
    $clients[$key]['forms'][$tag] = ['count'=>0,'latest'=>null];
  }
  $clients[$key]['forms'][$tag]['count']++;
  if (!$clients[$key]['forms'][$tag]['latest'] || strtotime($created) > strtotime($clients[$key]['forms'][$tag]['latest']))
    $clients[$key]['forms'][$tag]['latest'] = $created;

  // update overall latest
  $la = $clients[$key]['latest_any'];
  if (!$la || strtotime($created) > strtotime($la)) $clients[$key]['latest_any'] = $created;
};

foreach ($rowsPerForm as $tag => $rows) {
  foreach ($rows as $r) $push($r);
}

/* Sorting & pagination */
$rows = array_values($clients);
usort($rows, function($a,$b) use($order){
  if ($order === 'name') {
    $an = normalize_name_key(($a['last']??'').' '.($a['first']??'')); 
    $bn = normalize_name_key(($b['last']??'').' '.($b['first']??'')); 
    return $an <=> $bn;
  }
  $al = $a['latest_any'] ? strtotime($a['latest_any']) : 0;
  $bl = $b['latest_any'] ? strtotime($b['latest_any']) : 0;
  if ($al !== $bl) return $bl <=> $al;
  $an = normalize_name_key(($a['last']??'').' '.($a['first']??'')); 
  $bn = normalize_name_key(($b['last']??'').' '.($b['first']??'')); 
  return $an <=> $bn;
});
$total = count($rows);
$pages = max(1, (int)ceil($total / $per_page));
$page  = min($page, $pages);
$offset = ($page-1)*$per_page;
$view  = array_slice($rows, $offset, $per_page);

/* Dynamic column order for the active program */
$colOrder = $FORMS; // e.g., ['sassi','dwii_a','dwii_b','dwii_exit'] for DWII

/* Build a clean querystring base for links */
$baseQS = [
  'program'=>$program, 'search'=>$search, 'from'=>$from_date, 'to'=>$to_date,
  'order'=>$order, 'per'=>$per_page
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Evaluations — Lakeview (<?=h(strtoupper($program))?>)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="/favicons/favicon.ico">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
 body{padding-top:56px;background:#f5f6fa}
 .chip{display:inline-flex;align-items:center;border-radius:999px;padding:.1rem .5rem;font-size:.8rem;border:1px solid #e5e7eb;background:#fff}
 .chip i{font-size:.9rem;margin-right:.25rem}
 .ok{border-color:#b8e0c2;background:#f0fff4}
 .miss{border-color:#ffd5d5;background:#fff5f5}
 .subtle{color:#6b7280}
 .hdr{position:fixed;top:0;left:0;right:0;z-index:1030}
 th.sticky{position:sticky;top:0;background:#f8f9fa;z-index:2}
 a.chip{display:inline-flex;align-items:center;border-radius:999px;padding:.1rem .5rem;border:1px solid #e5e7eb;background:#fff;color:inherit}
 a.chip.ok{border-color:#b8e0c2;background:#f0fff4}
 a.chip.miss{border-color:#ffd5d5;background:#fff5f5}
 a.chip:hover{text-decoration:none;box-shadow:0 0 0 2px rgba(0,123,255,.12)}

</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<main class="container-fluid pt-4">
  <div class="row"><div class="col-12">

  <div class="d-flex align-items-center justify-content-between mt-2 mb-3">
    <h2 class="mb-0">Evaluations — <span class="text-uppercase"><?=h($program)?></span></h2>
    <div class="btn-group" role="group" aria-label="Program">
      <?php
        foreach (['dwii'=>'DWII','dwie'=>'DWIE','doep'=>'DOEP','all'=>'All'] as $code=>$label) {
          $active = $program===$code ? ' active' : '';
          $qs = http_build_query(array_merge($baseQS,['program'=>$code,'page'=>1]));
          echo '<a class="btn btn-sm btn-outline-primary'.$active.'" href="?'.h($qs).'">'.h($label).'</a>';
        }
      ?>
    </div>
  </div>

  <?php if ($warnings): ?>
    <div class="alert alert-warning">
      <strong>Heads up:</strong> <?= h(implode(' ', $warnings)) ?>
    </div>
  <?php endif; ?>

  <form class="mb-3" method="get">
    <input type="hidden" name="program" value="<?=h($program)?>">
    <input type="hidden" name="order" value="<?=h($order)?>">
    <div class="form-row">
      <div class="col-md-3 mb-2">
        <small class="text-muted">Quick Search</small>
        <input type="text" name="search" class="form-control" placeholder="name or email" value="<?=h($search)?>">
      </div>
      <div class="col-md-2 mb-2">
        <small class="text-muted">From date</small>
        <input type="date" name="from" class="form-control" value="<?=h($from_date)?>">
      </div>
      <div class="col-md-2 mb-2">
        <small class="text-muted">To date</small>
        <input type="date" name="to" class="form-control" value="<?=h($to_date)?>">
      </div>
      <div class="col-md-3 mb-2">
        <small class="text-muted d-block">Sort</small>
        <div class="btn-group" role="group">
          <a class="btn btn-sm btn-outline-secondary<?= $order==='recent'?' active':'' ?>" href="?<?=h(http_build_query(array_merge($baseQS,['order'=>'recent','page'=>1])))?>"><i class="fas fa-clock mr-1"></i>Most Recent</a>
          <a class="btn btn-sm btn-outline-secondary<?= $order==='name'?' active':'' ?>" href="?<?=h(http_build_query(array_merge($baseQS,['order'=>'name','page'=>1])))?>"><i class="fas fa-sort-alpha-down mr-1"></i>Name</a>
        </div>
      </div>
      <div class="col-md-2 align-self-end mb-2">
        <button class="btn btn-primary btn-block">Apply</button>
      </div>
    </div>
  </form>

  <div class="d-flex align-items-center justify-content-between mb-2">
    <div class="subtle">Showing <strong><?=count($view)?></strong> of <strong><?=$total?></strong> clients
      <?php if($total>0): ?> • Page <?=$page?> / <?=$pages?><?php endif; ?>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered table-striped">
      <thead class="thead-light">
        <tr>
          <th class="sticky" style="min-width:240px">Client</th>
          <th class="sticky" style="min-width:200px">Email</th>
          <?php foreach ($colOrder as $tag): ?>
            <th class="sticky" style="min-width:220px"><?=h($FORM_DEFS[$tag]['label'])?></th>
          <?php endforeach; ?>
          <th class="sticky" style="min-width:180px">Latest</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$view): ?>
        <tr><td colspan="<?= 3 + count($colOrder) ?>" class="text-center text-muted">No matching clients found.</td></tr>
      <?php else: ?>
        <?php foreach ($view as $row): ?>
          <?php
            $fn = trim((string)$row['first']);
            $ln = trim((string)$row['last']);
            $em = trim((string)$row['email']);
            $latest = $row['latest_any'];
            $keyPayload = ['first'=>$fn,'last'=>$ln,'email'=>$em];
            $clientKey = base64_encode(json_encode($keyPayload));
          ?>
          <tr>
            <td><strong><?=h($ln)?>, <?=h($fn)?></strong></td>
            <td><?= $em ? h($em) : '<span class="text-muted">—</span>' ?></td>

            <?php foreach ($colOrder as $tag):
              $stat = $row['forms'][$tag] ?? null;
              $ok   = $stat && $stat['count']>0;
              $cnt  = $ok ? (int)$stat['count'] : 0;
              $when = $ok ? $stat['latest'] : null;
            ?>
              <td>
                <?php if ($ok): ?>
                  <a class="chip ok"
                    style="text-decoration:none;color:inherit"
                    href="evaluation-review.php?form=<?=rawurlencode($tag)?>&key=<?=urlencode($clientKey)?>"
                    title="View <?=h($FORM_DEFS[$tag]['label'])?> responses">
                    <i class="fas fa-check-circle text-success"></i>
                    <?= h($FORM_DEFS[$tag]['label']) ?> • <?= h(fmt_dt($when)) ?>
                  </a>
                  <?php if ($cnt>1): ?>
                    <span class="badge badge-pill badge-info ml-1" title="Number of submissions">x<?=$cnt?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="chip miss">
                    <i class="fas fa-times-circle text-danger"></i>
                    <?= h($FORM_DEFS[$tag]['label']) ?> Missing
                  </span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>

            <td><?= h(fmt_dt($latest)) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>


  <?php if ($pages>1): 
    $mk = function($p) use($baseQS){ return 'evaluation-index.php?'.http_build_query(array_merge($baseQS,['page'=>$p])); };
    $prev = max(1,$page-1); $next = min($pages,$page+1);
  ?>
    <nav aria-label="Page navigation">
      <ul class="pagination">
        <li class="page-item<?= $page<=1?' disabled':''?>"><a class="page-link" href="<?=$mk($prev)?>">&laquo;</a></li>
        <?php for ($p=1;$p<=$pages;$p++): ?>
          <li class="page-item<?= $p===$page?' active':''?>"><a class="page-link" href="<?=$mk($p)?>"><?=$p?></a></li>
        <?php endfor; ?>
        <li class="page-item<?= $page>=$pages?' disabled':''?>"><a class="page-link" href="<?=$mk($next)?>">&raquo;</a></li>
      </ul>
    </nav>
  <?php endif; ?>

  <div class="text-muted small mt-3">
    <strong>Legend:</strong>
    <span class="chip ok ml-1"><i class="fas fa-check-circle text-success"></i> Form present</span>
    <span class="chip miss ml-1"><i class="fas fa-times-circle text-danger"></i> Missing</span>
  </div>

  </div></div>
</main>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>
</html>
