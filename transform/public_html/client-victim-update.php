<?php
/*───────────────────────────────────────────────────────────
  client-victim-update.php
  – Edit an existing victim row
  – mirrors ledger-update.php layout / flow
───────────────────────────────────────────────────────────*/
include_once 'auth.php';
check_loggedin($con);

require_once '../config/config.php';   // db creds
require_once 'helpers.php';
require_once 'sql_functions.php';

/* ---------- 1. init vars -------------------------------------------- */
$client_id                    = '';
$relationship_to_victim       = '';
$victim_gender_id             = '';
$victim_age                   = '';
$residing_with_victim         = 0;
$num_children_under_18        = '';

/* ----- error placeholders ------------------------------------------- */
$client_id_err=$relationship_err=$victim_age_err=$num_children_err='';

/* ---------- 2. POST – update ---------------------------------------- */
if (isset($_POST['id']) && $_POST['id']!=='') {

    $id                      = $_POST['id'];                 // victim row id
    $client_id               = trim($_POST['client_id']);
    $relationship_to_victim  = trim($_POST['relationship_to_victim']);
    $victim_gender_id        = trim($_POST['victim_gender_id']);
    $victim_age              = trim($_POST['victim_age']);
    $residing_with_victim    = isset($_POST['residing_with_victim']) ? 1 : 0;
    $num_children_under_18   = trim($_POST['num_children_under_18']);

    /* ---- minimal validation --------------------------------------- */
    if ($relationship_to_victim==='') {
        $relationship_err='Relationship required';
    }
    if ($victim_age!=='' && !preg_match('/^\d+$/',$victim_age)) {
        $victim_age_err='Enter positive integer';
    }
    if ($num_children_under_18!=='' && !preg_match('/^\d+$/',$num_children_under_18)){
        $num_children_err='Enter positive integer';
    }

    if (!$relationship_err && !$victim_age_err && !$num_children_err) {

        $pdo=new PDO('mysql:host='.db_host.';dbname='.db_name.';charset=utf8mb4',
                     db_user,db_pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

        $stmt=$pdo->prepare("
            UPDATE victim
               SET client_id              = ?,
                   relationship_to_victim = ?,
                   victim_gender_id       = ?,
                   victim_age             = ?,
                   residing_with_victim   = ?,
                   num_children_under_18  = ?
             WHERE id = ?");
        $stmt->execute([
            $client_id,
            $relationship_to_victim,
            $victim_gender_id ?: null,
            $victim_age       ?: null,
            $residing_with_victim,
            $num_children_under_18 ?: null,
            $id
        ]);

        header("location: client-victim.php?client_id=$client_id"); exit;
    }

/* ---------- 3. initial GET – pull row ------------------------------ */
} else {

    if(!(isset($_GET['id']) && $_GET['id']!=='')) {
        header('location:error.php'); exit;
    }
    $id = trim($_GET['id']);

    $sql = "SELECT * FROM victim WHERE id = ?";
    if ($stmt=mysqli_prepare($link,$sql)) {
        mysqli_stmt_bind_param($stmt,'i',$id);
        if (mysqli_stmt_execute($stmt)) {
            $res=mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($res)==1) {
                $row=mysqli_fetch_array($res,MYSQLI_ASSOC);

                $client_id               = $row['client_id'];
                $relationship_to_victim  = $row['relationship_to_victim'];
                $victim_gender_id        = $row['victim_gender_id'];
                $victim_age              = $row['victim_age'];
                $residing_with_victim    = $row['residing_with_victim'];
                $num_children_under_18   = $row['num_children_under_18'];
            } else { header('location:error.php'); exit; }
        } else { echo "DB error."; exit; }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NotesAO – Update Victim</title>

<!-- favicons + bootstrap (match ledger pages) -->
<link rel="icon" type="image/x-icon"             href="/favicons/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32"   href="/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16"   href="/favicons/favicon-16x16.png">
<link rel="icon" type="image/svg+xml"            href="/favicons/favicon.svg">
<link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#211c56">
<link rel="manifest"  href="/favicons/site.webmanifest">
<link rel="stylesheet"
      href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
      crossorigin="anonymous">
<style>body{padding-top:56px;font-size:16px}</style>
</head>
<?php require_once 'navbar.php'; ?>
<body>
<section class="pt-5">
<div class="container-fluid">
<div class="row"><div class="col-md-6 mx-auto">

    <h2>Update Victim Record</h2>
    <p>Edit the fields and click Submit to save.</p>

    <form method="post" action="<?= htmlspecialchars(basename($_SERVER['REQUEST_URI'])); ?>">

        <!-- client selector (allow change) -->
        <div class="form-group">
            <label>Client</label>
            <select name="client_id" class="form-control <?= $client_id_err?'is-invalid':'' ?>">
                <?php
                $sql="SELECT id,concat(first_name,' ',last_name,' - ',date_of_birth) AS d
                      FROM client ORDER BY first_name,last_name";
                $rs=mysqli_query($link,$sql);
                while($r=mysqli_fetch_assoc($rs)){
                    $sel=$r['id']==$client_id?'selected':'';
                    echo "<option value='{$r['id']}' $sel>".htmlspecialchars($r['d'])."</option>";
                }
                ?>
            </select>
            <span class="form-text text-danger"><?= $client_id_err ?></span>
        </div>

        <div class="form-group">
            <label>Relationship to Victim <span class="text-danger">*</span></label>
            <input type="text" name="relationship_to_victim" maxlength="64"
                   class="form-control <?= $relationship_err?'is-invalid':'' ?>"
                   value="<?= htmlspecialchars($relationship_to_victim) ?>">
            <span class="form-text text-danger"><?= $relationship_err ?></span>
        </div>

        <div class="form-group">
            <label>Victim Gender</label>
            <select name="victim_gender_id" class="form-control">
                <option value="">-- Unknown / N/A --</option>
                <?php foreach (get_genders() as $g): ?>
                    <option value="<?= $g['id']; ?>"
                        <?= $g['id']==$victim_gender_id?'selected':'' ?>>
                        <?= htmlspecialchars($g['gender']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group col-3">
                <label>Victim Age</label>
                <input type="number" min="0" name="victim_age"
                       class="form-control <?= $victim_age_err?'is-invalid':'' ?>"
                       value="<?= htmlspecialchars($victim_age) ?>">
                <span class="form-text text-danger"><?= $victim_age_err ?></span>
            </div>

            <div class="form-group col-4">
                <label>Children &lt; 18 in Home</label>
                <input type="number" min="0" name="num_children_under_18"
                       class="form-control <?= $num_children_err?'is-invalid':'' ?>"
                       value="<?= htmlspecialchars($num_children_under_18) ?>">
                <span class="form-text text-danger"><?= $num_children_err ?></span>
            </div>

            <div class="form-group col-5 align-self-end">
                <div class="form-check">
                    <input type="checkbox" id="reside" name="residing_with_victim"
                           class="form-check-input"
                           <?= $residing_with_victim ? 'checked' : '' ?>>
                    <label class="form-check-label" for="reside">
                        Client resides with victim
                    </label>
                </div>
            </div>
        </div>

        <!-- hidden victim row id -->
        <input type="hidden" name="id" value="<?= $id ?>"/>

        <button type="submit" class="btn btn-primary">Submit</button>
        <a href="client-victim.php?client_id=<?= $client_id ?>"
           class="btn btn-secondary">Cancel</a>
    </form>

</div></div>
</div>
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>
</html>
