<?php
// ----------------------------------------------------------------------------
// 1) Basic Setup & Logging
// ----------------------------------------------------------------------------
ini_set('log_errors', '1');
ini_set('error_log', '/home/notesao/lakeview/public_html/reportgen_errors.log');
ini_set('display_errors', '1');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json');
}

$log_file = '/home/notesao/lakeview/public_html/reportgen_errors.log';
ini_set('error_log', $log_file);

ini_set('max_execution_time', 30000);
set_time_limit(30000);

error_log("reportgen.php started; PHP ".phpversion());

// ----------------------------------------------------------------------------
// 2) Load Config, Ensure DB Connection, Check Auth
// ----------------------------------------------------------------------------
$clinic_folder   = 'lakeview';
$configPath      = "/home/notesao/$clinic_folder/config/config.php";
$fetchDataScript = "/home/notesao/NotePro-Report-Generator/fetch_data.php";

if (!file_exists($configPath)) {
    error_log("Config not found: $configPath");
    echo json_encode(["status"=>"error","message"=>"Clinic config file missing."]);
    exit;
}
include_once $configPath;
if (!isset($link)) {
    error_log("Missing \$link in config.php");
    echo json_encode(["status"=>"error","message"=>"Database connection missing."]);
    exit;
}
$con = $link;

include_once 'auth.php';
check_loggedin($con);

// ----------------------------------------------------------------------------
// 3) Actions
// ----------------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'cleanup') {
    $today_date_formatted = $_POST['date'] ?? null;
    if (!$today_date_formatted) {
        echo json_encode(['status'=>'error','message'=>'Date parameter missing for cleanup.']);
        exit;
    }
    $public_dir   = "/home/notesao/lakeview/public_html/GeneratedDocuments/$today_date_formatted";
    $internal_dir = "/home/notesao/NotePro-Report-Generator/GeneratedDocuments/lakeview/$today_date_formatted";
    $zip_path     = "/home/notesao/lakeview/public_html/GeneratedDocuments/Generated_Reports_$today_date_formatted.zip";

    function rrmdir($dir) {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            if (!rrmdir($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        return rmdir($dir);
    }

    $errors = [];
    if (file_exists($zip_path) && !@unlink($zip_path))   $errors[] = "Failed to remove zip.";
    if (file_exists($public_dir) && !rrmdir($public_dir))   $errors[] = "Failed to remove public folder.";
    if (file_exists($internal_dir) && !rrmdir($internal_dir)) $errors[] = "Failed to remove internal folder.";

    if ($errors) {
        echo json_encode(['status'=>'error','message'=>implode(' ', $errors)]);
        exit;
    }

    $_SESSION['start_date'] = null;
    $_SESSION['end_date']   = null;
    $_SESSION['selected_reports'] = null;

    echo json_encode(['status'=>'success','message'=>'Cleanup completed.']);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'fetch_data') {
    error_log("AJAX action=fetch_data for clinic: $clinic_folder");
    try {
        $_GET['clinic_folder'] = $clinic_folder; // handed to fetch_data.php
        ob_start();
        include $fetchDataScript;
        $output = ob_get_clean();
        error_log("fetch_data output: ".$output);

        if (strpos($output, 'Data fetching completed successfully.') !== false) {
            echo json_encode(["status"=>"success","message"=>"Data fetching completed successfully."]);
        } else {
            throw new Exception("Unexpected output from fetch_data.php");
        }
    } catch (Exception $e) {
        error_log("fetch_data exception: ".$e->getMessage());
        echo json_encode(["status"=>"error","message"=>"Error during data fetching: ".$e->getMessage()]);
    }
    exit;
}

// ----------------------------------------------------------------------------
// 4) Load Programs dynamically (no omissions)
// ----------------------------------------------------------------------------
$programs = [];
$res = $con->query("SELECT id, name FROM program ORDER BY name");
if ($res) {
    while ($r = $res->fetch_assoc()) $programs[] = $r;
} else {
    error_log("Program query failed: ".$con->error);
}

// ----------------------------------------------------------------------------
// 5) HTML
// ----------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>NotesAO - Report Generator</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/x-icon" href="/favicons/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
<link rel="icon" type="image/png" sizes="96x96" href="/favicons/favicon-96x96.png">
<link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">
<link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="167x167" href="/favicons/apple-touch-icon-ipad-pro.png">
<link rel="apple-touch-icon" sizes="152x152" href="/favicons/apple-touch-icon-ipad.png">
<link rel="apple-touch-icon" sizes="120x120" href="/favicons/apple-touch-icon-120x120.png">
<link rel="manifest" href="/favicons/site.webmanifest">
<meta name="apple-mobile-web-app-title" content="NotesAO">

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<style>
  body { background-color: rgb(226,230,234); margin:0; padding:0; }
  .page-container { max-width: 900px; margin: 2rem auto; padding: 0 15px; }
  .section-divider { border: 1px solid #ccc; margin: 30px 0; }
  .card { margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
  .card-header i { margin-right: 6px; }
  #progress_cur_container { display: none; }
  #gamePopout { position: fixed; bottom: 20px; right: 20px; z-index: 1050; }
</style>
</head>
<body>
<?php require_once('navbar.php'); ?>

<div class="container page-container">
  <h2 class="my-4 text-center">Report Generator for Lakeview</h2>

  <div class="card shadow-sm mb-4">
    <div class="card-body p-4">

      <!-- FETCH DATA -->
      <div class="mb-4">
        <button class="btn btn-primary btn-block mb-2" id="fetchDataButton" type="button" onclick="fetchData()">
          <i class="fas fa-download"></i> Fetch Data
        </button>
        <div id="fetch-data-message" class="mt-3 text-dark"></div>
      </div>

      <hr class="section-divider"/>

      <!-- UPDATE CLIENTS -->
      <form id="update-clients-form" class="mb-4">
        <div class="form-row">
          <div class="form-group col-sm-6 mb-3">
            <label for="update_client_start_date">Start Date:</label>
            <input id="update_client_start_date" name="start_date" class="form-control" type="date" required>
          </div>
          <div class="form-group col-sm-6 mb-3">
            <label for="update_client_end_date">End Date:</label>
            <input id="update_client_end_date" name="end_date" class="form-control" type="date" required>
          </div>
        </div>
        <button class="btn btn-success btn-block" type="button" onclick="updateClients()">
          <i class="fas fa-user-edit"></i> Update Clients
        </button>
      </form>
      <div id="update-clients-message" class="mt-2 text-dark"></div>

      <hr class="section-divider"/>

      <!-- CHECK ABSENCES -->
      <div class="mb-4">
        <button class="btn btn-info btn-block mb-2" id="checkAbsencesButton" type="button" onclick="checkAbsences()">
          <i class="fas fa-user-clock"></i> Check Absences
        </button>
        <div id="check-absences-message" class="mt-3 text-dark"></div>
      </div>

      <hr class="section-divider"/>

      <p class="text-info mb-4">
        <strong>Reminder:</strong> Use <em>Fetch Data</em> right before generating any reports.
      </p>

      <hr class="section-divider"/>

      <!-- GENERATE REPORTS -->
      <form id="generate-reports-form" method="post">
        <div class="form-row">
          <div class="form-group col-sm-6 mb-3">
            <label for="generate_reports_start_date">Start Date:</label>
            <input id="generate_reports_start_date" name="start_date" class="form-control" type="date" required>
          </div>
          <div class="form-group col-sm-6 mb-3">
            <label for="generate_reports_end_date">End Date:</label>
            <input id="generate_reports_end_date" name="end_date" class="form-control" type="date" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-sm-6 mb-3">
            <label for="program">Select Program:</label>
            <select id="program" name="program" class="form-control" required>
              <option disabled selected value="">- Select -</option>
              <?php
                foreach ($programs as $p) {
                    $id   = (int)$p['id'];
                    $name = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
                    $is_bipp = (stripos($p['name'], 'bipp') !== false) ? '1' : '0';
                    echo "<option value=\"$id\" data-is-bipp=\"$is_bipp\">$name</option>";
                }
              ?>
            </select>
            <!-- keep both id and name for downstream compatibility -->
            <input type="hidden" id="program_name" name="program_name" value="">
          </div>
          <div class="form-group col-sm-6 mb-3">
            <label for="client_name">Client Name (Optional):</label>
            <input type="text" id="client_name" name="client_name" class="form-control" placeholder="John Doe">
          </div>
        </div>

        <div class="form-group mb-4">
          <label>Select Reports:</label>

          <div class="form-check">
            <input id="enrollment_checkbox" name="reports[]" class="form-check-input" type="checkbox" value="Enrollment Letters">
            <label class="form-check-label" for="enrollment_checkbox">Enrollment Letters</label>
          </div>

          <div class="form-check">
            <input id="entrance_checkbox" name="reports[]" class="form-check-input" type="checkbox" value="Entrance Notifications">
            <label class="form-check-label" for="entrance_checkbox">Entrance Notifications</label>
          </div>

          <div class="form-check">
            <input id="progress_sog_checkbox" name="reports[]" class="form-check-input" type="checkbox" value="Progress Reports (Stage of Change)">
            <label class="form-check-label" for="progress_sog_checkbox">Progress Reports (Stage of Change)</label>
          </div>

          <div class="form-check" id="progress_cur_container" style="display:none;">
            <input id="progress_cur_checkbox" name="reports[]" class="form-check-input" type="checkbox" value="Progress Reports (Curriculum)">
            <label class="form-check-label" for="progress_cur_checkbox">Progress Reports (Curriculum)</label>
          </div>

          <div class="form-check">
            <input id="unexcused_absence_checkbox" name="reports[]" class="form-check-input" type="checkbox" value="Unexcused Absences">
            <label class="form-check-label" for="unexcused_absence_checkbox">Unexcused Absences</label>
          </div>

          <div class="form-check">
            <input id="completion_checkbox" name="reports[]" class="form-check-input" type="checkbox" value="Completion Documents">
            <label class="form-check-label" for="completion_checkbox">Completion Documents</label>
          </div>

          <div class="form-check">
            <input id="exit_checkbox" name="reports[]" class="form-check-input" type="checkbox" value="Exit Notices">
            <label class="form-check-label" for="exit_checkbox">Exit Notices</label>
          </div>

          <div class="form-check" id="behavior_contract_container" style="display:none;">
            <input id="behavior_contract_checkbox" name="reports[]" class="form-check-input" type="checkbox" value="Behavior Contracts">
            <label class="form-check-label" for="behavior_contract_checkbox">Behavior Contracts</label>
          </div>

          <div class="form-check" id="victim_letter_container" style="display:none;">
            <input id="victim_letter_checkbox" name="reports[]" class="form-check-input" type="checkbox" value="Victim Letters">
            <label class="form-check-label" for="victim_letter_checkbox">Victim Letters</label>
          </div>
        </div>

        <button class="btn btn-primary btn-block mb-3" id="generateButton" type="submit">
          <i class="fas fa-cogs"></i> Generate Reports
        </button>

        <div id="generate-reports-message" class="mt-2 text-dark"></div>

        <button id="download-reports-btn" class="btn btn-secondary btn-block mt-3" type="button" style="display:none;" onclick="downloadReports()">
          <i class="fas fa-file-download"></i> Download Reports
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Game modal -->
<div id="clickerGameModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-header">
        <h5 class="modal-title">Waiting for Reports? Play a Game!</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <p>Click the button below to earn points while waiting.</p>
        <button id="clicker-button" class="btn btn-warning btn-lg">Click Me!</button>
        <p class="mt-3">Points: <span id="clicker-score">0</span></p>
      </div>
    </div>
  </div>
</div>
<button id="gamePopout" class="btn btn-info btn-sm"><i class="fas fa-gamepad"></i></button>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

<script>
// 1) FETCH DATA
function fetchData() {
  const el = document.getElementById('fetch-data-message');
  el.innerText = 'Fetching data...';
  $.ajax({
    url: 'reportgen.php?action=fetch_data',
    type: 'GET',
    dataType: 'json',
    success: function(r){
      el.innerText = (r.status === 'success') ? (r.message || 'Data fetched successfully!') : ('Error: ' + r.message);
    },
    error: function(xhr){ el.innerText = 'Error: ' + xhr.status + ' ' + xhr.statusText; }
  });
}

// 2) UPDATE CLIENTS
function updateClients() {
  $('#update-clients-message').text('Processing...');
  const fd = new FormData(document.getElementById('update-clients-form'));
  $.ajax({
    url: 'update_clients.php',
    type: 'POST',
    data: fd, contentType: false, processData: false, dataType: 'json',
    success: function(r){
      $('#update-clients-message').text(r.status === 'success' ? 'Client data updated successfully!' : ('Error: ' + r.message));
    },
    error: function(xhr){ $('#update-clients-message').text('Error: ' + xhr.status + ' ' + xhr.statusText); }
  });
}

// 3) CHECK ABSENCES
function checkAbsences() {
  $('#check-absences-message').text('Processing...');
  $.ajax({
    url: 'check_absences.php',
    type: 'GET',
    dataType: 'json',
    success: function(r){
      if (r.status === 'success' && r.download_link) {
        const a = document.createElement('a');
        a.href = r.download_link; a.download = r.download_link.split('/').pop(); a.target = '_blank';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        $('#check-absences-message').text('Absence Report downloaded successfully.');
      } else {
        $('#check-absences-message').text('Error: ' + (r.message || 'No download link provided.'));
      }
    },
    error: function(xhr){ $('#check-absences-message').text('Error: ' + xhr.status + ' ' + xhr.statusText); }
  });
}

// 4) GENERATE REPORTS
function generateReports(e){
  e.preventDefault();
  const msg  = $('#generate-reports-message');
  const dl   = $('#download-reports-btn');
  msg.text('Processing... Please wait while reports are being generated.');
  dl.hide();

  // keep program name in a hidden field for server compatibility
  const sel = document.getElementById('program');
  const pname = sel && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex].text : '';
  document.getElementById('program_name').value = pname;

  $('#clickerGameModal').modal('show');

  const fd = new FormData(document.getElementById('generate-reports-form'));
  $.ajax({
    url: 'generate_reports.php',
    type: 'POST',
    data: fd, contentType: false, processData: false, dataType: 'json',
    success: function(r){
      if (r.status === 'success') {
        window.generatedReportsDate = r.date;
        msg.text('All selected reports have been generated.');
        if (r.show_download_button) dl.show();
        $('#clickerGameModal').modal('hide');
      } else {
        msg.text('Error: ' + r.message);
      }
    },
    error: function(xhr){ msg.text('AJAX Error: ' + xhr.status + ' ' + xhr.statusText); }
  });
}

function downloadReports(){
  const msg = $('#generate-reports-message');
  if (!window.generatedReportsDate) { msg.text('Error: Cannot download reports without a valid date.'); return; }
  msg.text('Preparing reports for download...');
  $.ajax({
    url: 'generate_reports.php',
    type: 'POST',
    data: { action: 'download_zip', date: window.generatedReportsDate },
    dataType: 'json',
    success: function(r){
      if (r.status === 'success') {
        const a = document.createElement('a');
        a.href = r.download_url; a.download = r.download_url.split('/').pop();
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        msg.text('Reports downloaded. Cleanup will occur automatically in 30 seconds.');
        setTimeout(finalizeCleanup, 30000);
      } else {
        msg.text('Error: ' + r.message);
      }
    },
    error: function(xhr){ msg.text('AJAX Error: ' + xhr.status + ' ' + xhr.statusText); }
  });
}

function finalizeCleanup(){
  const msg = $('#generate-reports-message');
  msg.text('Finalizing cleanup...');
  $.ajax({
    url: 'reportgen.php',
    type: 'POST',
    data: { action: 'cleanup', date: window.generatedReportsDate },
    dataType: 'json',
    success: function(r){
      if (r.status === 'success') {
        msg.text('Cleanup completed. Ready for new documents.');
        $('#download-reports-btn').hide();
        $('#generate-reports-form')[0].reset();
        window.generatedReportsDate = null;
      } else {
        msg.text('Cleanup error: ' + r.message);
      }
    },
    error: function(xhr){ msg.text('AJAX Error: ' + xhr.status + ' ' + xhr.statusText); }
  });
}

// 5) UI wiring
$(document).ready(function(){
  $('#generate-reports-form').on('submit', generateReports);

  function applyProgramToggles(){
    const opt   = $('#program option:selected');
    const isBipp = (opt.data('is-bipp') === 1) || (opt.data('is-bipp') === '1');
    if (isBipp) {
      $('#progress_cur_container').show();
      $('#behavior_contract_container').show();
      $('#victim_letter_container').show();
    } else {
      $('#progress_cur_container').hide(); $('#progress_cur_checkbox').prop('checked', false);
      $('#behavior_contract_container').hide(); $('#behavior_contract_checkbox').prop('checked', false);
      $('#victim_letter_container').hide(); $('#victim_letter_checkbox').prop('checked', false);
    }
  }
  $('#program').on('change', function(){
    // keep program_name synced
    const t = $('#program option:selected').text() || '';
    $('#program_name').val(t);
    applyProgramToggles();
  });
  applyProgramToggles(); // initial
  $('#program').trigger('change'); // seed program_name

  // clicker game
  let score = 0, generating = false;
  $(document).on('click','#clicker-button', function(){ score += Math.floor(Math.random()*5)+1; $('#clicker-score').text(score); });
  $('#gamePopout').click(function(){ $('#clickerGameModal').modal('show'); });
  $(document).ajaxStart(function(){ generating = true; });
  $(document).ajaxComplete(function(){ generating = false; });
});
</script>
</body>
</html>
