<?php

// ----------------------------------------------------------------------------
// 1) Basic Setup & Logging
// ----------------------------------------------------------------------------
ini_set('log_errors', '1');
ini_set('error_log', '/home/notesao/bestoption/public_html/reportgen_errors.log');

// You can hide or show errors on-screen as desired:
ini_set('display_errors', '1');
ini_set('display_startup_errors', '0');

error_reporting(E_ALL);

// Force JSON header if action param is present
if (isset($_POST['action']) || isset($_GET['action'])) {
    header('Content-Type: application/json');
}

// Logging details
$log_file = '/home/notesao/bestoption/public_html/reportgen_errors.log';
ini_set('error_log', $log_file);

ini_set('max_execution_time', 30000);
set_time_limit(30000);

error_log("Current log_errors setting: " . ini_get('log_errors'));
error_log("Error log file path: " . ini_get('error_log'));
error_log("reportgen.php script started.");
error_log("PHP Version: " . phpversion());
error_log("Loaded Configuration File: " . php_ini_loaded_file());
error_log("Additional .ini Files Parsed: " . print_r(php_ini_scanned_files(), true));

// ----------------------------------------------------------------------------
// 2) Load Config, Ensure DB Connection, Check Auth
// ----------------------------------------------------------------------------
$clinic_folder  = 'bestoption';
$configPath     = "/home/notesao/$clinic_folder/config/config.php";
$fetchDataScript= "/home/notesao/NotePro-Report-Generator/fetch_data.php";

if (file_exists($configPath)) {
    error_log("Config file found at $configPath");
    include_once $configPath;
    if (!isset($link)) {
        error_log("Database connection not established. Ensure \$link is set in config.php.");
        echo json_encode(["status" => "error", "message" => "Database connection missing."]);
        exit;
    }
    error_log("Loaded config from $configPath");
} else {
    error_log("Config file not found for clinic: $clinic_folder at $configPath");
    echo json_encode(["status" => "error", "message" => "Clinic config file missing."]);
    exit;
}

$con = $link;

include_once 'auth.php';
check_loggedin($link);

// ----------------------------------------------------------------------------
// 3) "Cleanup" Action (unchanged)
// ----------------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'cleanup') {
    $today_date_formatted = $_POST['date'] ?? null;
    if (!$today_date_formatted) {
        echo json_encode(['status' => 'error', 'message' => 'Date parameter missing for cleanup action.']);
        exit;
    }

    $public_dir   = "/home/notesao/bestoption/public_html/GeneratedDocuments/$today_date_formatted";
    $internal_dir = "/home/notesao/NotePro-Report-Generator/GeneratedDocuments/bestoption/$today_date_formatted";
    $zip_path     = "/home/notesao/bestoption/public_html/GeneratedDocuments/Generated_Reports_$today_date_formatted.zip";

    // Recursive delete function
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
    // Remove ZIP
    if (file_exists($zip_path) && !unlink($zip_path)) {
        $errors[] = "Failed to remove zip file.";
    }
    // Remove public folder
    if (file_exists($public_dir) && !rrmdir($public_dir)) {
        $errors[] = "Failed to remove public folder.";
    }
    // Remove internal folder
    if (file_exists($internal_dir) && !rrmdir($internal_dir)) {
        $errors[] = "Failed to remove internal folder.";
    }

    if (!empty($errors)) {
        echo json_encode(['status' => 'error', 'message' => implode(' ', $errors)]);
        exit;
    }

    // Reset session parameters if you use them
    $_SESSION['start_date'] = null;
    $_SESSION['end_date']   = null;
    $_SESSION['selected_reports'] = null;

    echo json_encode(['status' => 'success', 'message' => 'Cleanup completed successfully.']);
    exit;
}
/**
 * Embedded fetch_data functionality with robust error handling
 */
if (isset($_GET['action']) && $_GET['action'] === 'fetch_data') {
    error_log("AJAX request detected with action=fetch_data for clinic: $clinic_folder");

    try {
        error_log("Running Fetch Data Script");
        // Define the clinic_folder explicitly for fetch_data.php
        $_GET['clinic_folder'] = $clinic_folder;

        // Capture the output of fetch_data.php
        ob_start();
        include $fetchDataScript;
        $output = ob_get_clean();

        // Log the output for debugging
        error_log("Fetch Data Script output: " . $output);

        if (strpos($output, 'Data fetching completed successfully.') !== false) {
            error_log("Data fetching completed successfully.");
            echo json_encode(["status" => "success", "message" => "Data fetching completed successfully."]);
        } else {
            throw new Exception("Unexpected output or error during data fetching.");
        }
    } catch (Exception $e) {
        error_log("Exception occurred during data fetching: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error during data fetching: " . $e->getMessage()]);
    }
    exit;
}


// -----------------------------------------------------------------------------
// 6) HTML + JS (UI)  
// -----------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NotesAO - Report Generator</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- FAVICONS, etc. -->
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

    <!-- Bootstrap 4.5 CSS -->
    <link rel="stylesheet" 
          href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css">
    
    <!-- Font Awesome 5.15.3 -->
    <link rel="stylesheet" 
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <style>
        body {
            background-color: rgb(226, 230, 234);
            margin: 0; padding: 0;
        }
        .page-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 15px;
        }
        .section-divider {
            border: 1px solid #ccc; 
            margin: 30px 0;
        }
        .form-check-label {
            margin-left: 5px;
        }
        #progress_cur_container {
            display: none; /* hidden unless "BIPP" is selected */
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .card-header i {
            margin-right: 6px;
        }
        #gamePopout {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050;
        }
    </style>
</head>
<body>
    <?php
    // Include navbar if you have it
    require_once('navbar.php');
    ?>

<div class="container page-container">
    <h2 class="my-4 text-center">Report Generator for The Best Option</h2>

    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">

            <!-- FETCH DATA -->
            <div class="mb-4">
                <button 
                    class="btn btn-primary btn-block mb-2" 
                    id="fetchDataButton" 
                    title="Fetch Data" 
                    type="button"
                    onclick="fetchData()"
                >
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
                        <input 
                            id="update_client_start_date" 
                            name="start_date" 
                            class="form-control"
                            type="date"
                            required 
                        >
                    </div>
                    <div class="form-group col-sm-6 mb-3">
                        <label for="update_client_end_date">End Date:</label>
                        <input 
                            id="update_client_end_date" 
                            name="end_date" 
                            class="form-control"
                            type="date"
                            required
                        >
                    </div>
                </div>
                <button 
                    class="btn btn-success btn-block" 
                    title="Update Clients"
                    type="button" 
                    onclick="updateClients()"
                >
                    <i class="fas fa-user-edit"></i> Update Clients
                </button>
            </form>
            <div id="update-clients-message" class="mt-2 text-dark"></div>

            <hr class="section-divider"/>

            <!-- CHECK ABSENCES -->
            <div class="mb-4">
                <button 
                    class="btn btn-info btn-block mb-2" 
                    id="checkAbsencesButton" 
                    title="Check Absences"
                    type="button" 
                    onclick="checkAbsences()"
                >
                    <i class="fas fa-user-clock"></i> Check Absences
                </button>
                <div id="check-absences-message" class="mt-3 text-dark"></div>
            </div>

            <hr class="section-divider"/>

            <!-- REMINDER -->
            <p class="text-info mb-4">
                <strong>Reminder:</strong> Use <em>Fetch Data</em> <u>right before</u> generating any reports.
            </p>

            <hr class="section-divider"/>

            <!-- GENERATE REPORTS FORM (no action, we use AJAX) -->
            <form id="generate-reports-form" method="post">
                <!-- Date range selection -->
                <div class="form-row">
                    <div class="form-group col-sm-6 mb-3">
                        <label for="generate_reports_start_date">Start Date:</label>
                        <input 
                            id="generate_reports_start_date" 
                            name="start_date" 
                            class="form-control"
                            type="date" 
                            required
                        >
                    </div>
                    <div class="form-group col-sm-6 mb-3">
                        <label for="generate_reports_end_date">End Date:</label>
                        <input 
                            id="generate_reports_end_date" 
                            name="end_date" 
                            class="form-control"
                            type="date" 
                            required
                        >
                    </div>
                </div>

                <!-- Program and client selection -->
                <div class="form-row">
                    <div class="form-group col-sm-6 mb-3">
                        <label for="program">Select Program:</label>
                        <select 
                            id="program" 
                            name="program" 
                            class="form-control"
                            required
                        >
                            <option disabled selected value="- Select -">- Select -</option>
                            <option value="BIPP">BIPP</option>
                            <option value="Anger Control">Anger Control</option>
                            <option value="Substance Abuse">Substance Abuse</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-6 mb-3">
                        <label for="client_name">Client Name (Optional):</label>
                        <input 
                            type="text" 
                            id="client_name" 
                            name="client_name" 
                            class="form-control"
                            placeholder="John Doe"
                        />
                    </div>
                </div>

                <!-- Reports checkboxes -->
                <div class="form-group mb-4">
                    <label>Select Reports:</label>
                    <div class="form-check">
                        <input 
                            id="entrance_checkbox" 
                            name="reports[]" 
                            class="form-check-input" 
                            type="checkbox"
                            value="Entrance Notifications"
                        >
                        <label class="form-check-label" for="entrance_checkbox">
                            Entrance Notifications
                        </label>
                    </div>
                    <div class="form-check">
                        <input 
                            id="progress_sog_checkbox" 
                            name="reports[]" 
                            class="form-check-input" 
                            type="checkbox"
                            value="Progress Reports (Stage of Change)"
                        >
                        <label class="form-check-label" for="progress_sog_checkbox">
                            Progress Reports (Stage of Change)
                        </label>
                    </div>
                    <div class="form-check" id="progress_cur_container">
                        <input 
                            id="progress_cur_checkbox" 
                            name="reports[]" 
                            class="form-check-input" 
                            type="checkbox"
                            value="Progress Reports (Curriculum)"
                        >
                        <label class="form-check-label" for="progress_cur_checkbox">
                            Progress Reports (Curriculum)
                        </label>
                    </div>
                    <div class="form-check">
                        <input 
                            id="unexcused_absence_checkbox" 
                            name="reports[]" 
                            class="form-check-input" 
                            type="checkbox"
                            value="Unexcused Absences"
                        >
                        <label class="form-check-label" for="unexcused_absence_checkbox">
                            Unexcused Absences
                        </label>
                    </div>
                    <div class="form-check">
                        <input 
                            id="completion_checkbox" 
                            name="reports[]" 
                            class="form-check-input" 
                            type="checkbox"
                            value="Completion Documents"
                        >
                        <label class="form-check-label" for="completion_checkbox">
                            Completion Documents
                        </label>
                    </div>
                    <div class="form-check">
                        <input 
                            id="exit_checkbox" 
                            name="reports[]" 
                            class="form-check-input" 
                            type="checkbox"
                            value="Exit Notices"
                        >
                        <label class="form-check-label" for="exit_checkbox">
                            Exit Notices
                        </label>
                    </div>
                    <div class="form-check" id="behavior_contract_container" style="display: none;">
                        <input 
                            id="behavior_contract_checkbox" 
                            name="reports[]" 
                            class="form-check-input" 
                            type="checkbox"
                            value="Behavior Contracts"
                        >
                        <label class="form-check-label" for="behavior_contract_checkbox">
                            Behavior Contracts
                        </label>
                    </div>
                    <div class="form-check" id="victim_letter_container" style="display: none;">
                        <input 
                            id="victim_letter_checkbox" 
                            name="reports[]" 
                            class="form-check-input" 
                            type="checkbox"
                            value="Victim Letters"
                        >
                        <label class="form-check-label" for="victim_letter_checkbox">
                            Victim Letters
                        </label>
                    </div>
                </div>

                <!-- Generate -->
                <button 
                    class="btn btn-primary btn-block mb-3" 
                    id="generateButton"
                    title="Generate Reports" 
                    type="submit"
                >
                    <i class="fas fa-cogs"></i> Generate Reports
                </button>

                <div id="generate-reports-message" class="mt-2 text-dark"></div>

                <!-- Download & Cleanup Buttons (remain the same) -->
                <button 
                    id="download-reports-btn" 
                    class="btn btn-secondary btn-block mt-3"
                    type="button"
                    style="display: none;"
                    onclick="downloadReports()"
                >
                    <i class="fas fa-file-download"></i> Download Reports
                </button>
            </form>
        </div>
    </div>
</div>

<!-- The clicker game modal and floating button -->
<div id="clickerGameModal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
            <div class="modal-header">
                <h5 class="modal-title">Waiting for Reports? Play a Game!</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Click the button below to earn points while waiting.</p>
                <button id="clicker-button" class="btn btn-warning btn-lg">Click Me!</button>
                <p class="mt-3">Points: <span id="clicker-score">0</span></p>
            </div>
        </div>
    </div>
</div>

<button id="gamePopout" class="btn btn-info btn-sm">
    <i class="fas fa-gamepad"></i>
</button>

<!-- jQuery, Popper, Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

<script>
/* -------------------------------------------------------------------
   1) FETCH DATA (Background)
   ------------------------------------------------------------------- */
function fetchData() {
    const messageElement = document.getElementById('fetch-data-message');
    messageElement.innerText = 'Fetching data...';
    $.ajax({
        url: 'reportgen.php?action=fetch_data', // <--- changed to start_fetch_data
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                messageElement.innerText = response.message || 'Data fetched successfully!';
            } else {
                messageElement.innerText = 'Error: ' + response.message;
            }
        },
        error: function(xhr) {
            const errorMessage = 'Error occurred: ' + xhr.status + ' ' + xhr.statusText;
            messageElement.innerText = errorMessage;
        }
    });
}

/* -------------------------------------------------------------------
   2) UPDATE CLIENTS
   ------------------------------------------------------------------- */
function updateClients() {
    document.getElementById('update-clients-message').innerText = 'Processing...';
    const formData = new FormData(document.getElementById('update-clients-form'));
    $.ajax({
        url: 'update_clients.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                document.getElementById('update-clients-message').innerText =
                    'Client data updated successfully!';
            } else {
                document.getElementById('update-clients-message').innerText =
                    'Error: ' + response.message;
            }
        },
        error: function(xhr) {
            let errorMessage = 'Error occurred: ' + xhr.status + ' ' + xhr.statusText;
            document.getElementById('update-clients-message').innerText = errorMessage;
        }
    });
}

/* -------------------------------------------------------------------
   3) CHECK ABSENCES
   ------------------------------------------------------------------- */
function checkAbsences() {
    document.getElementById('check-absences-message').innerText = 'Processing...';
    $.ajax({
        url: 'check_absences.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                if (response.download_link) {
                    const downloadLink = response.download_link;
                    const filename = downloadLink.substring(downloadLink.lastIndexOf('/') + 1);

                    const a = document.createElement('a');
                    a.href = downloadLink;
                    a.download = filename;
                    a.target = '_blank';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);

                    document.getElementById('check-absences-message').innerText =
                        'Absence Report downloaded successfully.';
                } else {
                    document.getElementById('check-absences-message').innerText =
                        'Error: No download link provided.';
                }
            } else {
                document.getElementById('check-absences-message').innerText =
                    'Error: ' + response.message;
            }
        },
        error: function(xhr) {
            const errorMessage = 'Error occurred: ' + xhr.status + ' ' + xhr.statusText;
            document.getElementById('check-absences-message').innerText = errorMessage;
        }
    });
}

/* -------------------------------------------------------------------
   4) GENERATE REPORTS (Background)
   ------------------------------------------------------------------- */
function generateReports(event) {
    event.preventDefault();
    const messageElement  = $('#generate-reports-message');
    const downloadButton  = $('#download-reports-btn');
    messageElement.text('Processing... Please wait while reports are being generated.');
    downloadButton.hide();

    // Show "clicker game" modal
    $('#clickerGameModal').modal('show');

    // Gather form data
    const formData = new FormData(document.getElementById('generate-reports-form'));

    // Instead of posting directly to generate_reports.php, we call:
    // reportgen.php?action=start_generate_reports
    $.ajax({
        url: 'generate_reports.php',
        type: 'POST',
        data: formData,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                window.generatedReportsDate = response.date;
                messageElement.text('All selected reports have been generated.');

                if (response.show_download_button) {
                    downloadButton.show();
                }
                $('#clickerGameModal').modal('hide');
            } else {
                messageElement.text('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            const errorMessage = 'AJAX Error: ' + xhr.status + ' ' + xhr.statusText;
            messageElement.text(errorMessage);
            console.error(errorMessage);
        }
    });
}

// Download reports ZIP (calls generate_reports.php action=download_zip)
function downloadReports() {
    const messageElement = $('#generate-reports-message');

    // Validate if a date is set
    if (!window.generatedReportsDate) {
        messageElement.text('Error: Cannot download reports without a valid date.');
        console.error('Error: No valid date set for downloading reports.');
        return;
    }

    // Display loading message
    messageElement.text('Preparing reports for download...');

    $.ajax({
        url: 'generate_reports.php',
        type: 'POST',
        data: {
            action: 'download_zip',
            date: window.generatedReportsDate
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                // Create a temporary download link
                const link = document.createElement('a');
                link.href = response.download_url;
                link.download = response.download_url.split('/').pop();
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                messageElement.text(
                    'Reports downloaded successfully. Cleanup will occur automatically in 30 seconds.'
                );
                console.log('Download initiated for: ' + response.download_url);

                // Automatically trigger cleanup after 30 seconds
                setTimeout(finalizeCleanup, 30000);
            } else {
                messageElement.text('Error: ' + response.message);
                console.error('Server error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            const errorMessage = 'AJAX Error: ' + xhr.status + ' ' + xhr.statusText;
            messageElement.text(errorMessage);
            console.error('AJAX Error Details:', {
                status: xhr.status,
                statusText: xhr.statusText,
                error: error
            });
        }
    });
}

// Cleanup
function finalizeCleanup() {
    const messageElement = $('#generate-reports-message');
    messageElement.text('Finalizing cleanup...');

    $.ajax({
        url: 'reportgen.php',
        type: 'POST',
        data: { action: 'cleanup', date: window.generatedReportsDate },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                messageElement.text('Cleanup completed. Ready for new documents.');
                $('#download-reports-btn').hide();

                // Reset form
                $('#generate_reports_start_date').val('');
                $('#generate_reports_end_date').val('');
                $('#program').val('- Select -');
                $('#client_name').val('');
                $('input[type="checkbox"]').prop('checked', false);

                window.generatedReportsDate = null;
            } else {
                messageElement.text('Cleanup error: ' + response.message);
            }
        },
        error: function(xhr) {
            const errorMessage = 'AJAX Error: ' + xhr.status + ' ' + xhr.statusText;
            messageElement.text(errorMessage);
            console.error(errorMessage);
        }
    });
}

/* -------------------------------------------------------------------
   6) Document Ready: Binds & UI logic
   ------------------------------------------------------------------- */
$(document).ready(function() {
    // Bind generateReports on form submit
    $('#generate-reports-form').on('submit', function(e) {
        generateReports(e);
    });

    // Show or hide Curriculum checkbox if "BIPP"
    $('#program').change(function() {
        let selectedProgram = $(this).val() || '';
        if (selectedProgram.indexOf('BIPP') !== -1) {
            // Show BIPP-specific checkboxes
            $('#progress_cur_container').show();
            $('#behavior_contract_container').show();
            $('#victim_letter_container').show();
        } else {
            // Hide them if not BIPP
            $('#progress_cur_container').hide();
            $('#progress_cur_checkbox').prop('checked', false);

            $('#behavior_contract_container').hide();
            $('#behavior_contract_checkbox').prop('checked', false);

            $('#victim_letter_container').hide();
            $('#victim_letter_checkbox').prop('checked', false);
        }
    });


    // Clicker game logic
    let reportsGenerating = false;
    let score = 0;

    $(document).on('click', '#clicker-button', function() {
        score += Math.floor(Math.random() * 5) + 1;
        $('#clicker-score').text(score);
    });

    $('#gamePopout').click(function() {
        $('#clickerGameModal').modal('show');
    });

    $('#clickerGameModal').on('hidden.bs.modal', function() {
        if (reportsGenerating) {
            $('#gamePopout').fadeIn();
        }
    });

    // On Ajax start
    $(document).ajaxStart(function() {
        reportsGenerating = true;
    });
    // On Ajax complete
    $(document).ajaxComplete(function() {
        reportsGenerating = false;
    });
});
</script>
</body>
</html>
