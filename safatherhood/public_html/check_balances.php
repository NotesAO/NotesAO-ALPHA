<?php
// check_balances.php  (safatherhood)
// Build balances CSV from DB -> sanitize -> call Python -> verify PDF -> copy to public -> JSON.

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

// -------------------------
// Includes / Auth
// -------------------------
include_once '/home/notesao/safatherhood/config/config.php'; // provides $link (mysqli)
include_once 'auth.php';
header('Content-Type: application/json; charset=UTF-8');

require_once 'helpers.php';
check_loggedin($link);

// -------------------------
// Params
// -------------------------
$program_id     = (int)($_REQUEST['program_id']     ?? 0);
$include_exited = (int)($_REQUEST['include_exited'] ?? 0);
$min_balance    = is_numeric($_REQUEST['min_balance'] ?? null) ? (float)$_REQUEST['min_balance'] : 0.01;
$report_date    = $_REQUEST['report_date'] ?? date('m/d/Y');

// -------------------------
// Paths
// -------------------------
$clinic_folder = 'safatherhood';
$base_dir      = '/home/notesao/NotePro-Report-Generator';
$venv_python   = "$base_dir/venv311/bin/python3";
$python_script = "$base_dir/check_balances.py";

$csv_directory    = "$base_dir/csv/$clinic_folder/";
$output_directory = "$base_dir/balance_reports/$clinic_folder/";
$public_directory = "/home/notesao/safatherhood/public_html/documents/balance_reports/";

// Ensure directories exist
foreach ([$csv_directory, $output_directory, $public_directory] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
        echo json_encode(['status' => 'error', 'message' => "Failed to create directory: $dir"]);
        exit;
    }
}

// -------------------------
// CSV filenames (dated)
// -------------------------
$todayYmd = date('Ymd');
$raw_csv  = $csv_directory . "BalanceReport_Input_$todayYmd.csv";
$pdf_out  = $output_directory . "BalanceReport_$todayYmd.pdf";
$pub_pdf  = $public_directory . "BalanceReport_$todayYmd.pdf";

// -------------------------
// CSV sanitizer
// -------------------------
function sanitize_csv_file($file_path) {
    $temp_file = tempnam(sys_get_temp_dir(), 'bal_sanitized_');
    $out = fopen($temp_file, 'w');
    if (($in = fopen($file_path, 'r')) !== false) {
        while (($row = fgetcsv($in)) !== false) {
            $san = array_map(function ($cell) {
                // allow $, % in notes; keep punctuation; remove control chars
                return preg_replace('#[^A-Za-z0-9\s\.\,\!\?\;\:\'\"()\-\_/\$%]#u', '', (string)$cell);
            }, $row);
            fputcsv($out, $san);
        }
        fclose($in);
    }
    fclose($out);
    return $temp_file;
}


// -------------------------
// Build CSV from DB
// -------------------------
$fh = fopen($raw_csv, 'w');
if (!$fh) {
    echo json_encode(['status' => 'error', 'message' => "Cannot write CSV: $raw_csv"]);
    exit;
}

// Header matches what check_balances.py will read
$header = [
    'client_id','first_name','last_name','program_id','program_name',
    'fee','required_sessions','attended_sessions',
    'total_paid','expected_paid_to_date','current_balance','total_expected_by_graduation','issue'
];
fputcsv($fh, $header);

// Query
$sql = "
SELECT
    c.id AS client_id,
    c.program_id,
    p.name AS program_name,
    c.first_name,
    c.last_name,
    COALESCE(c.required_sessions, 0) AS required_sessions,
    COALESCE(c.fee, 0)               AS fee,
    (
      SELECT COUNT(DISTINCT ar.therapy_session_id)
      FROM attendance_record ar
      WHERE ar.client_id = c.id
    ) AS attended_sessions,
    (
      SELECT COALESCE(SUM(CASE WHEN l.amount > 0 THEN l.amount ELSE 0 END), 0)
      FROM ledger l
      WHERE l.client_id = c.id
    ) AS total_paid,
    c.exit_date
FROM client c
LEFT JOIN program p ON p.id = c.program_id
WHERE (? = 1 OR c.exit_date IS NULL)
  AND (? = 0 OR c.program_id = ?)
ORDER BY c.last_name, c.first_name
";
$stmt = $link->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $link->error]);
    exit;
}
$stmt->bind_param('iii', $include_exited, $program_id, $program_id);
$stmt->execute();
$res = $stmt->get_result();

// Collect rows and the client ids that have issues
$rows_written     = 0;
$issue_client_ids = [];   // <<< define BEFORE the loop

while ($r = $res->fetch_assoc()) {
    $fee      = (float)$r['fee'];
    $required = (int)$r['required_sessions'];
    $attended = (int)$r['attended_sessions'];
    $paid     = (float)$r['total_paid'];

    // Core computations
    $expected_to_date = round($attended * $fee, 2);
    $total_expected   = round($required * $fee, 2);
    $balance          = round($expected_to_date - $paid, 2);

    // Classify issues
    $issues = [];
    if ($fee <= 0)                     $issues[] = 'Missing/zero fee';
    if ($required <= 0)                $issues[] = 'Missing required_sessions';
    if ($attended > 0 && $paid == 0.0) $issues[] = 'No payments yet';
    if ($balance >= $min_balance)      $issues[] = 'Owes for attended';

    // Optional: treat any negative balance as overpaid (symmetry with min_balance)
    if ($balance <= -$min_balance)     $issues[] = 'Overpaid';
    // If you prefer your old rule (at least one session’s fee): use this instead:
    // elseif ($balance <= -$fee && $fee > 0) $issues[] = 'Overpaid';

    // Only include rows with issues
    if (empty($issues)) continue;

    fputcsv($fh, [
        (int)$r['client_id'],
        (string)$r['first_name'],
        (string)$r['last_name'],
        (int)$r['program_id'],
        (string)($r['program_name'] ?? ''),
        number_format($fee, 2, '.', ''),
        $required,
        $attended,
        number_format($paid, 2, '.', ''),
        number_format($expected_to_date, 2, '.', ''),
        number_format($balance, 2, '.', ''),
        number_format($total_expected, 2, '.', ''),
        implode(' | ', $issues),
    ]);

    // Track client id for per-ledger details
    $issue_client_ids[] = (int)$r['client_id'];

    $rows_written++;
}
fclose($fh);

// -------------------------
// Build ledger-lines CSV for clients with issues
// -------------------------
$ledger_csv = $csv_directory . "BalanceReport_Ledger_$todayYmd.csv";
$lfh = fopen($ledger_csv, 'w');
if (!$lfh) {
    echo json_encode(['status' => 'error', 'message' => "Cannot write CSV: $ledger_csv"]);
    exit;
}
// include date + note
fputcsv($lfh, ['client_id','ledger_date','amount','note']);

$issue_client_ids = $issue_client_ids ?? []; // ensure defined
if (!empty($issue_client_ids)) {
    $ids_sql = implode(',', array_map('intval', array_unique($issue_client_ids)));
    $sqlL = "
        SELECT
            client_id,
            DATE_FORMAT(create_date, '%Y-%m-%d') AS ledger_date,
            amount,
            note
        FROM ledger
        WHERE client_id IN ($ids_sql)
        ORDER BY client_id, create_date, id
    ";
    if ($resL = $link->query($sqlL)) {
        while ($rowL = $resL->fetch_assoc()) {
            $amt = is_numeric($rowL['amount']) ? number_format((float)$rowL['amount'], 2, '.', '') : '0.00';
            fputcsv($lfh, [
                (int)$rowL['client_id'],
                (string)$rowL['ledger_date'],
                $amt,
                (string)$rowL['note'],
            ]);
        }
        $resL->free();
    }
}
fclose($lfh);



// -------------------------
// Sanitize for Python
// -------------------------
$sanitized_csv        = sanitize_csv_file($raw_csv);
$sanitized_ledger_csv = sanitize_csv_file($ledger_csv);

// -------------------------
// Call Python to render PDF
// -------------------------
$report_date_arg = escapeshellarg($report_date);
$csv_arg         = escapeshellarg($sanitized_csv);
$out_dir_arg     = escapeshellarg($output_directory);
$ledger_arg      = escapeshellarg($sanitized_ledger_csv);

$cmd = "$venv_python $python_script --csv_file $csv_arg --ledger_csv $ledger_arg --report_date $report_date_arg --output_directory $out_dir_arg";
$py_output = shell_exec($cmd . ' 2>&1');

// -------------------------
// Verify PDF
// -------------------------
if (!file_exists($pdf_out)) {
    error_log("check_balances.py failed or PDF missing. Output:\n" . ($py_output ?? '[no output]'));
    http_response_code(500);
    @unlink($sanitized_csv);
    @unlink($sanitized_ledger_csv);
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate balance PDF. See error_log.']);
    exit;
}

// -------------------------
// Copy to public and finish
// -------------------------
if (!copy($pdf_out, $pub_pdf)) {
    http_response_code(500);
    @unlink($sanitized_csv);
    @unlink($sanitized_ledger_csv);
    echo json_encode(['status' => 'error', 'message' => 'Failed to copy report to public folder.']);
    exit;
}
chmod($pub_pdf, 0644);

@unlink($sanitized_csv);
@unlink($sanitized_ledger_csv);

echo json_encode([
    'status'        => 'success',
    'rows'          => $rows_written,
    'download_link' => "/documents/balance_reports/BalanceReport_$todayYmd.pdf"
]);
