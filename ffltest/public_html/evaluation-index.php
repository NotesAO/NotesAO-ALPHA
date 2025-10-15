<?php
/**
 * evaluation-index.php — Staff index for Evaluation forms (PAI + VTC)
 *
 * Purpose
 *  - List all unique clients (matched by First, Last, DOB) who have submitted either evaluation form
 *  - Show whether each client has completed PAI, VTC, or both (+ submission counts and latest timestamps)
 *  - Sort by most recent submission (default) or by name; search and date filters included
 *  - Links to detail pages (evaluation-review.php) for combined or per-form review (to be implemented next)
 *
 * Integration Notes
 *  - Uses auth.php: this page is staff-only (logged-in)
 *  - Uses helpers.php (optional); provides a fallback h() sanitizer if not present
 *  - Table & column autodetection included — set $PAI_CFG and $VTC_CFG below to your actual table names
 *  - If your column names differ, add them to the candidate arrays (id/first/last/dob/created)
 *
 * Styling
 *  - Reuses Bootstrap 4.5 + Font Awesome similar to intake-index.php
 *  - Light, responsive, with chips for status and counts
 */

declare(strict_types=1);

include_once 'auth.php';
check_loggedin($con);                // $con provided by auth.php
require_once 'helpers.php';
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$link = $con; $link->set_charset('utf8mb4');
if (session_status()===PHP_SESSION_NONE) session_start();

/* ------------------------------------------------------------------
 * Configuration — set your actual table names here
 * ------------------------------------------------------------------*/
$PAI_CFG = [
  'table'   => 'evaluations_pai',
  'id'      => ['pai_id','id','submission_id'],
  'first'   => ['name_first','fname','given_name'],
  'last'    => ['name_last','lname','surname','family_name'],
  'dob'     => ['date_of_birth','dob','birth_date'],
  'created' => ['created_at','submitted_at','created','ts','timestamp']
];

$VTC_CFG = [
  'table'   => 'evaluations_vtc',
  'id'      => ['vtc_id','id','submission_id'],
  'first'   => ['first_name','fname','given_name'],
  'last'    => ['last_name','lname','surname','family_name'],
  'dob'     => ['date_of_birth','dob','birth_date'],
  'created' => ['created_at','submitted_at','created','ts','timestamp']
];

/* ------------------------------------------------------------------
 * Helpers: schema detection, date & name normalization, sorting, etc.
 * ------------------------------------------------------------------*/
function table_exists(mysqli $db, string $table): bool {
  $tableEsc = mysqli_real_escape_string($db, $table);
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tableEsc' LIMIT 1";
  $res = $db->query($sql);
  return $res && $res->num_rows > 0;
}

function resolve_columns(mysqli $db, array $cfg): array {
  // Ensure table exists
  if (!table_exists($db, $cfg['table'])) return [];

  $cols = [];
  $res = $db->query("SHOW COLUMNS FROM `{$cfg['table']}`");
  if ($res) {
    while($row = $res->fetch_assoc()) $cols[strtolower($row['Field'])] = true;
  }
  $pick = function(array $cands) use($cols){
    foreach ($cands as $c) { if (isset($cols[strtolower($c)])) return $c; }
    return null;
  };

  return [
    'table'   => $cfg['table'],
    'id'      => $pick($cfg['id']),
    'first'   => $pick($cfg['first']),
    'last'    => $pick($cfg['last']),
    'dob'     => $pick($cfg['dob']),
    'created' => $pick($cfg['created'])
  ];
}

function normalize_dob($v): ?string {
  if ($v===null || $v==='') return null;
  $v = trim((string)$v);
  // Try YYYY-MM-DD
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
  // Try MM/DD/YYYY
  if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $v, $m)) {
    return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[1], (int)$m[2]);
  }
  // Try other parseable formats
  $ts = strtotime($v);
  if ($ts!==false) return date('Y-m-d', $ts);
  return null;
}

function normalize_name($v): string {
  $v = trim((string)$v);
  // collapse spaces, lowercase for keying
  $v = preg_replace('/\s+/', ' ', $v);
  return mb_strtolower($v, 'UTF-8');
}

function fmt_dt(?string $ts): string {
  if (!$ts) return '';
  $time = strtotime($ts);
  if ($time===false) return h($ts);
  return date('M j, Y g:i a', $time);
}

function fmt_dob(?string $d): string {
  if (!$d) return '';
  $time = strtotime($d);
  return $time? date('M j, Y', $time) : h($d);
}

/* ------------------------------------------------------------------
 * Inputs (filters & sorting)
 * ------------------------------------------------------------------*/
$search    = trim($_GET['search']   ?? '');      // name or DOB (MM/DD/YYYY or YYYY-MM-DD)
$from_date = trim($_GET['from']     ?? '');      // filter created >= from
$to_date   = trim($_GET['to']       ?? '');      // filter created <= to
$which     = trim($_GET['which']    ?? 'all');   // all|both|pai_only|vtc_only
$order     = trim($_GET['order']    ?? 'recent'); // recent|name
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = min(200, max(10, (int)($_GET['per'] ?? 50)));

$whitelist_order = ['recent','name']; if (!in_array($order,$whitelist_order,true)) $order='recent';
$whitelist_which = ['all','both','pai_only','vtc_only']; if (!in_array($which,$whitelist_which,true)) $which='all';

/* ------------------------------------------------------------------
 * Detect actual columns in each table
 * ------------------------------------------------------------------*/
$pai = resolve_columns($link, $PAI_CFG);
$vtc = resolve_columns($link, $VTC_CFG);

$errors = [];
if (!$pai) $errors[] = "PAI table '{$PAI_CFG['table']}' not found.";
if (!$vtc) $errors[] = "VTC table '{$VTC_CFG['table']}' not found.";

// It is OK if one exists and the other not; the index still works with available data.

/* ------------------------------------------------------------------
 * Fetch rows from PAI / VTC (only fields we need)
 *  - We include basic SQL filtering when possible; remaining filters applied in PHP
 * ------------------------------------------------------------------*/
function fetch_rows(mysqli $db, array $meta, string $label, string $search, string $from_date, string $to_date): array {
  if (!$meta || !$meta['id'] || !$meta['first'] || !$meta['last'] || !$meta['dob'] || !$meta['created']) return [];
  $t = $meta['table']; $id=$meta['id']; $fn=$meta['first']; $ln=$meta['last']; $dob=$meta['dob']; $cr=$meta['created'];

  $w = [];
  if ($from_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from_date)) $w[] = "`$cr` >= '".mysqli_real_escape_string($db,$from_date)." 00:00:00'";
  if ($to_date   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to_date))   $w[] = "`$cr` <= '".mysqli_real_escape_string($db,$to_date)." 23:59:59'";

  // Simple name search when search has at least 2 letters
  $searchSql = '';
  $s = trim($search);
  if ($s !== '') {
    $sDob = normalize_dob($s);
    $esc = mysqli_real_escape_string($db, $s);
    $like = "LIKE '%$esc%'";
    $parts = [];
    $parts[] = "`$fn` $like";
    $parts[] = "`$ln` $like";
    if ($sDob) $parts[] = "DATE(`$dob`) = '".mysqli_real_escape_string($db,$sDob)."'";
    $searchSql = '(' . implode(' OR ', $parts) . ')';
  }

  $where = [];
  if ($w) $where[] = '(' . implode(' AND ', $w) . ')';
  if ($searchSql) $where[] = $searchSql;
  $whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

  $sql = "SELECT `$id` AS id, `$fn` AS first_name, `$ln` AS last_name, `$dob` AS dob, `$cr` AS created_at FROM `$t` $whereSql";
  $res = $db->query($sql);
  $rows = [];
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $rows[] = $r + ['_form'=>$label];
    }
  }
  return $rows;
}

$paiRows = $pai ? fetch_rows($link, $pai, 'pai', $search, $from_date, $to_date) : [];
$vtcRows = $vtc ? fetch_rows($link, $vtc, 'vtc', $search, $from_date, $to_date) : [];

/* ------------------------------------------------------------------
 * Combine into unique clients keyed by First|Last|DOB
 * ------------------------------------------------------------------*/
$clients = [];
$push = function(array $row) use (&$clients){
  $first = normalize_name($row['first_name'] ?? '');
  $last  = normalize_name($row['last_name'] ?? '');
  $dob   = normalize_dob($row['dob'] ?? '') ?: '';
  if ($first==='' || $last==='' || $dob==='') return; // skip incomplete keys
  $key = $first.'|'.$last.'|'.$dob;

  if (!isset($clients[$key])) {
    $clients[$key] = [
      'first_name_raw' => $row['first_name'],
      'last_name_raw'  => $row['last_name'],
      'dob_raw'        => $dob,
      'pai' => ['count'=>0,'latest'=>null,'ids'=>[]],
      'vtc' => ['count'=>0,'latest'=>null,'ids'=>[]],
      'latest_any' => null
    ];
  }

  $created = $row['created_at'] ?? null;
  if ($row['_form']==='pai') {
    $clients[$key]['pai']['count']++;
    $clients[$key]['pai']['ids'][] = (string)($row['id'] ?? '');
    if (!$clients[$key]['pai']['latest'] || strtotime($created) > strtotime($clients[$key]['pai']['latest']))
      $clients[$key]['pai']['latest'] = $created;
  } else if ($row['_form']==='vtc') {
    $clients[$key]['vtc']['count']++;
    $clients[$key]['vtc']['ids'][] = (string)($row['id'] ?? '');
    if (!$clients[$key]['vtc']['latest'] || strtotime($created) > strtotime($clients[$key]['vtc']['latest']))
      $clients[$key]['vtc']['latest'] = $created;
  }

  // Update latest across both
  $latests = array_filter([$clients[$key]['pai']['latest'], $clients[$key]['vtc']['latest']]);
  $clients[$key]['latest_any'] = $latests ? max($latests) : null;
};

foreach ($paiRows as $r) $push($r);
foreach ($vtcRows as $r) $push($r);

// Optional additional filter: which (both / only PAI / only VTC)
if ($which !== 'all') {
  $clients = array_filter($clients, function($c) use($which){
    $hasPai = $c['pai']['count'] > 0; $hasVtc = $c['vtc']['count'] > 0;
    if ($which==='both')     return $hasPai && $hasVtc;
    if ($which==='pai_only') return $hasPai && !$hasVtc;
    if ($which==='vtc_only') return !$hasPai && $hasVtc;
    return true;
  });
}

/* ------------------------------------------------------------------
 * Sorting & pagination
 * ------------------------------------------------------------------*/
$rows = array_values($clients);

usort($rows, function($a,$b) use($order){
  if ($order==='name') {
    $an = normalize_name(($a['last_name_raw']??'').' '.($a['first_name_raw']??''));
    $bn = normalize_name(($b['last_name_raw']??'').' '.($b['first_name_raw']??''));
    return $an <=> $bn;
  }
  // recent (default): latest_any desc, then name
  $al = $a['latest_any'] ? strtotime($a['latest_any']) : 0;
  $bl = $b['latest_any'] ? strtotime($b['latest_any']) : 0;
  if ($al!==$bl) return $bl <=> $al;
  $an = normalize_name(($a['last_name_raw']??'').' '.($a['first_name_raw']??''));
  $bn = normalize_name(($b['last_name_raw']??'').' '.($b['first_name_raw']??''));
  return $an <=> $bn;
});

$total = count($rows);
$pages = max(1, (int)ceil($total / $per_page));
$page = min($page, $pages);
$offset = ($page-1)*$per_page;
$view = array_slice($rows, $offset, $per_page);

$url_prefix = http_build_query([
  'search'=>$search,'from'=>$from_date,'to'=>$to_date,'which'=>$which,'order'=>$order,'per'=>$per_page
]);

/* ------------------------------------------------------------------
 * HTML — header + table
 * ------------------------------------------------------------------*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Evaluations (PAI + VTC)</title>
<link rel="icon" href="/favicons/favicon.ico">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
 body{padding-top:56px;background:#f5f6fa}
 .chip{display:inline-flex;align-items:center;border-radius:999px;padding:.1rem .5rem;font-size:.8rem;border:1px solid #e5e7eb;background:#fff}
 .chip i{font-size:.9rem;margin-right:.25rem}
 .chip.ok{border-color:#b8e0c2;background:#f0fff4}
 .chip.miss{border-color:#ffd5d5;background:#fff5f5}
 .td-actions a{margin-right:4px}
 .subtle{color:#6b7280}
 .hdr{position:fixed;top:0;left:0;right:0;z-index:1030}
</style>
</head>
<body>

<?php include 'navbar.php'; ?>

<main class="container-fluid pt-4">
  <div class="row"><div class="col-12">

  <div class="d-flex align-items-center justify-content-between mt-2 mb-3">
    <h2 class="mb-0">Evaluations (PAI + VTC)</h2>
    <div>
      <a class="btn btn-secondary" href="evaluation-index.php">Reset View</a>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-warning"><strong>Heads up:</strong> <?=h(implode(' ', $errors))?></div>
  <?php endif; ?>

  <form class="mb-3" method="get">
    <input type="hidden" name="order" value="<?=h($order)?>">
    <div class="form-row">
      <div class="col-md-3 mb-2">
        <small class="text-muted">Quick Search</small>
        <input type="text" name="search" class="form-control" placeholder="name or DOB (YYYY-MM-DD)" value="<?=h($search)?>">
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
        <small class="text-muted d-block">Filter</small>
        <div class="btn-group" role="group">
          <a class="btn btn-sm btn-outline-primary<?= $which==='all'?' active':'' ?>" href="?<?=h(http_build_query(array_merge($_GET,['which'=>'all','page'=>1])))?>">All</a>
          <a class="btn btn-sm btn-outline-primary<?= $which==='both'?' active':'' ?>" href="?<?=h(http_build_query(array_merge($_GET,['which'=>'both','page'=>1])))?>">Both</a>
          <a class="btn btn-sm btn-outline-primary<?= $which==='pai_only'?' active':'' ?>" href="?<?=h(http_build_query(array_merge($_GET,['which'=>'pai_only','page'=>1])))?>">PAI only</a>
          <a class="btn btn-sm btn-outline-primary<?= $which==='vtc_only'?' active':'' ?>" href="?<?=h(http_build_query(array_merge($_GET,['which'=>'vtc_only','page'=>1])))?>">VTC only</a>
        </div>
      </div>
      <div class="col-md-2 align-self-end mb-2">
        <button class="btn btn-primary btn-block">Apply</button>
      </div>
    </div>
  </form>

  <div class="d-flex align-items-center justify-content-between mb-2">
    <div class="subtle">Showing <strong><?=count($view)?></strong> of <strong><?=$total?></strong> clients
      <?php if($total>0): ?>
        • Page <?=$page?> / <?=$pages?>
      <?php endif; ?>
    </div>
    <div>
      <div class="btn-group" role="group">
        <a class="btn btn-sm btn-outline-secondary<?= $order==='recent'?' active':'' ?>" href="?<?=h(http_build_query(array_merge($_GET,['order'=>'recent','page'=>1])))?>"><i class="fas fa-clock mr-1"></i>Most Recent</a>
        <a class="btn btn-sm btn-outline-secondary<?= $order==='name'?' active':'' ?>" href="?<?=h(http_build_query(array_merge($_GET,['order'=>'name','page'=>1])))?>"><i class="fas fa-sort-alpha-down mr-1"></i>Name</a>
      </div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered table-striped">
      <thead class="thead-light">
        <tr>
          <th style="min-width:220px">Client</th>
          <th style="min-width:130px">DOB</th>
          <th style="min-width:240px">PAI</th>
          <th style="min-width:240px">VTC</th>
          <th style="min-width:180px">Latest</th>
          <th style="min-width:220px">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$view): ?>
        <tr><td colspan="6" class="text-center text-muted">No matching clients found.</td></tr>
      <?php else: ?>
        <?php foreach ($view as $row): ?>
          <?php
            $fn = trim((string)($row['first_name_raw']??''));
            $ln = trim((string)($row['last_name_raw']??''));
            $dob = $row['dob_raw'] ?? '';
            $paiOk = $row['pai']['count']>0; $vtcOk = $row['vtc']['count']>0;
            $paiLatest = $row['pai']['latest'];
            $vtcLatest = $row['vtc']['latest'];
            $latest = $row['latest_any'];
            $cntPai = $row['pai']['count'];
            $cntVtc = $row['vtc']['count'];
            $clientKey = base64_encode(json_encode(['first'=>$fn,'last'=>$ln,'dob'=>$dob]));
          ?>
          <tr>
            <td><strong><?=h($ln)?>, <?=h($fn)?></strong></td>
            <td><?=h(fmt_dob($dob))?></td>
            <td>
              <span class="chip <?= $paiOk?'ok':'miss' ?>">
                <i class="fas <?= $paiOk?'fa-check-circle text-success':'fa-times-circle text-danger'?>"></i>
                PAI <?= $paiOk? '• '.h(fmt_dt($paiLatest)) : 'Missing' ?>
              </span>
              <?php if ($cntPai>1): ?>
                <span class="badge badge-pill badge-info ml-1" title="Number of PAI submissions">x<?=$cntPai?></span>
              <?php endif; ?>
            </td>
            <td>
              <span class="chip <?= $vtcOk?'ok':'miss' ?>">
                <i class="fas <?= $vtcOk?'fa-check-circle text-success':'fa-times-circle text-danger'?>"></i>
                VTC <?= $vtcOk? '• '.h(fmt_dt($vtcLatest)) : 'Missing' ?>
              </span>
              <?php if ($cntVtc>1): ?>
                <span class="badge badge-pill badge-info ml-1" title="Number of VTC submissions">x<?=$cntVtc?></span>
              <?php endif; ?>
            </td>
            <td><?=h(fmt_dt($latest))?></td>
            <td class="td-actions">
              <a class="btn btn-sm btn-outline-primary" href="evaluation-review.php?key=<?=urlencode($clientKey)?>"><i class="fas fa-user-check mr-1"></i>Combined</a>
              <a class="btn btn-sm btn-outline-secondary<?= $paiOk? '':' disabled' ?>" href="evaluation-review.php?form=pai&key=<?=urlencode($clientKey)?>"><i class="fas fa-clipboard-check mr-1"></i>PAI</a>
              <a class="btn btn-sm btn-outline-secondary<?= $vtcOk? '':' disabled' ?>" href="evaluation-review.php?form=vtc&key=<?=urlencode($clientKey)?>"><i class="fas fa-clipboard-list mr-1"></i>VTC</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages>1): ?>
    <nav aria-label="Page navigation">
      <ul class="pagination">
        <?php
          $mk = function($p) use($url_prefix){ return 'evaluation-index.php?'.$url_prefix.'&page='.$p; };
          $prev = max(1, $page-1); $next = min($pages, $page+1);
        ?>
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
