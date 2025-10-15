<?php
/**
 * intake-index.php — staff view / duplicate review-merge
 */
declare(strict_types=1);

include_once 'auth.php';
check_loggedin($con);          // $con from auth.php
require_once 'helpers.php';
if (!function_exists('h')) { function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');} }

$link=$con; $link->set_charset('utf8mb4');
if (session_status()===PHP_SESSION_NONE) session_start();

/* ------------------------------------------------------------------
 *  AJAX actions
 * ------------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD']==='POST'){
    header('Content-Type: application/json');
    if (!hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')){
        echo json_encode(['ok'=>false,'msg'=>'CSRF']); exit;
    }

    /* preview rows ------------------------------------------------*/
    if (($_POST['action']??'')==='preview'){
        $ids = array_filter($_POST['ids']??[], 'ctype_digit');
        if (count($ids)<2){echo json_encode(['ok'=>false,'msg'=>'Need ≥2 IDs']);exit;}
        $rows=[];
        $q="SELECT * FROM intake_packet WHERE intake_id IN(".implode(',',$ids).")";
        $r=mysqli_query($link,$q) or die(json_encode(['ok'=>false,'msg'=>mysqli_error($link)]));
        while($row=mysqli_fetch_assoc($r)) $rows[$row['intake_id']]=$row;
        echo json_encode(['ok'=>true,'rows'=>$rows]); exit;
    }

    /* do merge ----------------------------------------------------*/
    if (($_POST['action']??'')==='doMerge'){
        $winner=(int)($_POST['winner_id']??0);
        $losers=array_filter($_POST['losers']??[],fn($v)=>ctype_digit($v)&&$v!=$winner);
        $keep=$_POST['keep']??[];
        if(!$winner||!$losers){echo json_encode(['ok'=>false,'msg'=>'IDs missing']);exit;}

        foreach($keep as $col=>$srcId){
            $srcId=(int)$srcId; $col=preg_replace('/[^a-z0-9_]/i','',$col);
            $valRes=mysqli_query($link,"SELECT `$col` FROM intake_packet WHERE intake_id=$srcId");
            $val=mysqli_fetch_row($valRes)[0]??null;
            $valSQL=is_null($val)?'NULL':"'".mysqli_real_escape_string($link,$val)."'";
            mysqli_query($link,"UPDATE intake_packet SET `$col`=$valSQL WHERE intake_id=$winner")
              or die(json_encode(['ok'=>false,'msg'=>mysqli_error($link)]));
        }
        mysqli_query($link,"DELETE FROM intake_packet WHERE intake_id IN(".implode(',',array_map('intval',$losers)).")")
          or die(json_encode(['ok'=>false,'msg'=>mysqli_error($link)]));
        echo json_encode(['ok'=>true]); exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Bad action']); exit;
}

/* ------------------------------------------------------------------
 *  Build listing
 * ------------------------------------------------------------------*/
$program_id=$_SESSION['program_id']??0;
$whereProg=$program_id?"WHERE program_id=".(int)$program_id:'';

$search=$_GET['search']??'';
$order =$_GET['order']??'signature_date';
$sort  =strtolower($_GET['sort']??'desc'); $sort=$sort==='asc'?'asc':'desc';
$allowed=['signature_date','first_name','last_name','email','phone_cell',
          'packet_complete','staff_verified','imported_to_client'];
if(!in_array($order,$allowed,true)) $order='signature_date';
$toggle_sort=$sort==='asc'?'desc':'asc';
$url_prefix='search='.urlencode($search).'&sort='.$toggle_sort;

/* duplicate map */
$dupes=[];
$qDup="SELECT LOWER(first_name) fn,LOWER(last_name) ln,date_of_birth dob,COUNT(*) c
       FROM intake_packet $whereProg GROUP BY fn,ln,dob HAVING c>1";
if($rDup=mysqli_query($link,$qDup)) while($d=mysqli_fetch_assoc($rDup))
    $dupes[$d['fn'].'-'.$d['ln'].'-'.$d['dob']]=true;

/* main sql */
$sql="SELECT intake_id,signature_date,DATE_FORMAT(signature_date,'%Y-%m-%d %H:%i') created_fmt,
             first_name,last_name,date_of_birth,email,phone_cell,
             packet_complete,staff_verified,imported_to_client
      FROM intake_packet $whereProg";
if($search!==''){
   $esc=mysqli_real_escape_string($link,$search);
   $sql.=($whereProg?' AND ':' WHERE ')."CONCAT_WS(' ',first_name,last_name,email,phone_cell) LIKE '%$esc%'";
}
$sql.=($order==='signature_date')
     ?" ORDER BY signature_date DESC,last_name ASC,first_name ASC"
     :" ORDER BY $order $sort";
$result=mysqli_query($link,$sql); $rowCount=$result?mysqli_num_rows($result):0;

/* ---------------------------------------------------------------
 *  Build list of *all* columns to compare
 * ---------------------------------------------------------------*/
$skip=['intake_id','signature_date','verified_at'];      // skip meta fields
$fieldsToCompare=[];
$desc=mysqli_query($link,"DESCRIBE intake_packet");
while($c=mysqli_fetch_assoc($desc)){
    if(in_array($c['Field'],$skip,true)) continue;
    $fieldsToCompare[$c['Field']]=ucwords(str_replace('_',' ',$c['Field']));
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="utf-8"><title>Intake Packets</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" href="/favicons/favicon.ico">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<style>
 body{padding-top:56px;background:#f5f6fa}
 .dup-row{background:#fff9e6!important}
 .table td:last-child a,.table td:last-child button{margin-right:4px}
 .diff-cell{background:#fff3cd}
</style></head><body>
<?php include 'navbar.php'; ?>

<section class="pt-5"><div class="container-fluid"><div class="row"><div class="col">

<div class="page-header clearfix mb-3">
 <h2 class="float-left">Intake Packets</h2>
 <a href="intake-index.php" class="btn btn-info float-right">Reset View</a>
 <a href="home.php" class="btn btn-secondary float-right mr-2">Home</a>
</div>

<form class="mb-3">
 <input type="hidden" name="order" value="<?=h($order)?>">
 <input type="hidden" name="sort"  value="<?=h($sort)?>">
 <div class="form-row">
  <div class="col-md-3">
   <small class="text-muted">Quick Search</small>
   <input type="text" name="search" class="form-control" placeholder="name / email / phone" value="<?=h($search)?>">
  </div>
  <div class="col-md-2 align-self-end"><button class="btn btn-primary">Search</button></div>
 </div>
</form>

<table class="table table-bordered table-striped">
<thead><tr>
 <th><a href="?<?=$url_prefix?>&order=signature_date">Submitted</a></th>
 <th><a href="?<?=$url_prefix?>&order=first_name">First</a></th>
 <th><a href="?<?=$url_prefix?>&order=last_name">Last</a></th>
 <th>DOB</th>
 <th><a href="?<?=$url_prefix?>&order=email">E-mail</a></th>
 <th><a href="?<?=$url_prefix?>&order=phone_cell">Phone</a></th>
 <th><a href="?<?=$url_prefix?>&order=packet_complete">Complete?</a></th>
 <th><a href="?<?=$url_prefix?>&order=staff_verified">Verified?</a></th>
 <th><a href="?<?=$url_prefix?>&order=imported_to_client">Imported?</a></th>
 <th>Action</th>
</tr></thead>
<tbody>
<?php if($rowCount): while($r=mysqli_fetch_assoc($result)):
 $key=strtolower($r['first_name']).'-'.strtolower($r['last_name']).'-'.$r['date_of_birth'];
 $dup=isset($dupes[$key]); ?>
<tr<?=$dup?' class="dup-row"':''?>>
 <td><?=h($r['created_fmt'])?></td>
 <td><?=h($r['first_name'])?></td>
 <td><?=h($r['last_name'])?><?=$dup?' <span class="badge badge-warning">dup</span>':''?></td>
 <td><?=h($r['date_of_birth'])?></td>
 <td><?=h($r['email'])?></td>
 <td><?=h($r['phone_cell'])?></td>
 <td><?=$r['packet_complete']?'Yes':'No'?></td>
 <td><?=$r['staff_verified']?'Yes':'No'?></td>
 <td><?=$r['imported_to_client']?'Yes':'No'?></td>
 <td>
  <a href="intake-review.php?id=<?=$r['intake_id']?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
  <a href="intake-update.php?id=<?=$r['intake_id']?>" class="btn btn-sm btn-secondary"><i class="fas fa-edit"></i></a>
  <?php if($dup): ?>
   <button type="button" class="btn btn-sm btn-danger merge-btn"
           data-toggle="modal" data-target="#mergeModal"
           data-id="<?=$r['intake_id']?>"
           data-fn="<?=h($r['first_name'])?>"
           data-ln="<?=h($r['last_name'])?>"
           data-dob="<?=$r['date_of_birth']?>"><i class="fas fa-compress-arrows-alt"></i></button>
  <?php endif;?>
 </td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="10" class="text-center">No packets found</td></tr>
<?php endif;?>
</tbody></table>
<?php if($result) mysqli_free_result($result); ?>
</div></div></div></section>

<!-- Merge Modal -->
<div class="modal fade" id="mergeModal" tabindex="-1">
 <div class="modal-dialog modal-xl modal-dialog-centered">
  <form id="mergeForm" class="modal-content">
   <div class="modal-header">
    <h5 class="modal-title">Review &amp; Merge duplicate packets</h5>
    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
   </div>
   <div class="modal-body">
    <p id="mergeIntro" class="mb-3"></p>
    <div id="diffHolder" class="table-responsive mb-3" style="max-height:70vh;overflow:auto"></div>
    <div class="form-group">
     <label>Winner (kept):</label>
     <select id="winnerSelect" name="winner_id" class="form-control"></select>
    </div>
    <div class="form-group">
     <label>Loser packets (deleted):</label>
     <div id="loserList"></div>
    </div>
    <input type="hidden" name="action" value="doMerge">
    <input type="hidden" name="csrf"   value="<?=h($_SESSION['csrf']=$_SESSION['csrf']??bin2hex(random_bytes(16)))?>">
   </div>
   <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
    <button id="doMergeBtn" type="submit" class="btn btn-danger">Merge</button>
   </div>
  </form>
 </div></div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.bundle.min.js"></script>
<script>
const fieldsLabel = <?=json_encode($fieldsToCompare)?>;

/* build diff table -----------------------------------------------------------------*/
function buildDiffTable(rows){
  const ids=Object.keys(rows);
  if(ids.length<2) return '';
  let thead='<thead><tr><th>Field</th>'; ids.forEach(id=>thead+=`<th>#${id}</th>`); thead+='</tr></thead>';
  let tbody='';
  Object.entries(fieldsLabel).forEach(([col,label])=>{
    const vals=ids.map(id=>rows[id][col]??'');
    const uniq=Array.from(new Set(vals.filter(v=>v!=='')));
    const differ=uniq.length>1;
    tbody+=`<tr><td><strong>${label}</strong></td>`;
    ids.forEach(id=>{
      const v=rows[id][col]??'';
      if(differ){
        tbody+=`<td class="diff-cell"><label class="d-block small mb-0">
          <input type="radio" name="keep[${col}]" value="${id}" required> ${v||'—'}
        </label></td>`;
      }else if(!differ && uniq.length===1){  /* exactly one non-blank */
        const checked=v!==''?'checked':'';
        tbody+=`<td class="diff-cell"><label class="d-block small mb-0">
          <input type="radio" name="keep[${col}]" value="${id}" ${checked} required> ${v||'—'}
        </label></td>`;
      }else{
        tbody+=`<td>${v||'—'}</td>`;
      }
    });
    tbody+='</tr>';
  });
  return `<table class="table table-sm table-bordered mb-0">${thead}<tbody>${tbody}</tbody></table>`;
}

$(function(){
  /* open modal */
  $('.merge-btn').on('click',function(){
    const fn=$(this).data('fn'), ln=$(this).data('ln'), dob=$(this).data('dob');
    const sel=`.merge-btn[data-fn="${fn}"][data-ln="${ln}"][data-dob="${dob}"]`;
    const $all=$(sel); const ids=$all.map((_,b)=>$(b).data('id')).get();

    $('#winnerSelect').empty(); $('#loserList').empty(); $('#diffHolder').html('');
    ids.forEach(id=>{
      const txt='#'+id+' – '+$(`.merge-btn[data-id="${id}"]`).closest('tr').find('td:first').text();
      $('#winnerSelect').append(`<option value="${id}">${txt}</option>`);
      $('#loserList').append(`<div class="form-check">
         <input class="form-check-input" type="checkbox" name="losers[]" value="${id}" id="loser${id}">
         <label class="form-check-label" for="loser${id}">${txt}</label></div>`);
    });
    $('#winnerSelect').val(ids[0]); $(`#loserList input[value="${ids[0]}"]`).prop('disabled',true);
    $('#mergeIntro').text(`Found ${ids.length} packets for ${fn} ${ln} (${dob}). Review differences:`);

    $.post('intake-index.php',{action:'preview',ids:ids,csrf:'<?=h($_SESSION['csrf'])?>'},res=>{
       if(!res.ok){alert(res.msg);return;}
       $('#diffHolder').html(buildDiffTable(res.rows));
    },'json');
  });

  /* submit */
  $('#mergeForm').on('submit',function(e){
    e.preventDefault(); $('#doMergeBtn').prop('disabled',true);
    $.post('intake-index.php',$(this).serialize(),r=>{
      if(!r.ok){alert(r.msg||'Merge failed'); $('#doMergeBtn').prop('disabled',false);return;}
      location.reload();
    },'json').fail(()=>{alert('Server error'); $('#doMergeBtn').prop('disabled',false);});
  });
});
</script>
</body></html>
