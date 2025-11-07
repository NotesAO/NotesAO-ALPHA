<?php
// lakeview/public_html/update_clients.php

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=UTF-8');

// --- 0) Auth + DB ---------------------------------------------------
require_once '/home/notesao/lakeview/config/config.php';   // provides $link (mysqli)
require_once '/home/notesao/lakeview/public_html/auth.php';
require_once __DIR__ . '/helpers.php';

// Normalize handle names
/** @var mysqli $link */
$con = $link;

// Enforce login
check_loggedin($con, 'https://notesao.com/login.php');

// Enforce POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Invalid request method. Use POST.']);
    exit;
}

// --- 1) Helpers ------------------------------------------------------

/**
 * Sanitize a CSV file by stripping disallowed characters from each cell.
 * Returns path to a new temporary file.
 */
function sanitize_csv_file($file_path) {
    $temp_file = tempnam(sys_get_temp_dir(), 'sanitized_csv_');
    $out = fopen($temp_file, 'w');

    if (($in = fopen($file_path, 'r')) !== false) {
        while (($row = fgetcsv($in)) !== false) {
            $row = array_map(function ($cell) {
                return preg_replace('#[^\p{L}\p{N}\s\.,!?;:\'\"()\-_/]#u', '', (string)$cell);
            }, $row);
            fputcsv($out, $row);
        }
        fclose($in);
    }
    fclose($out);
    return $temp_file;
}

/**
 * Locate today's Report 5 CSV for this clinic, supporting multiple name patterns:
 *  - Lakeview_report5_YYYYMMDD.csv
 *  - lakeview_report5_YYYYMMDD.csv
 *  - report5_dump_YYYYMMDD.csv  (legacy)
 * Fallback: newest *report5*.csv in the clinic folder.
 */
function find_today_csv(string $csv_root, string $clinic_folder): ?string {
    $ymd = date('Ymd');
    $dir = rtrim($csv_root, '/').'/'.$clinic_folder;
    $cCap = ucfirst($clinic_folder);
    $cLow = strtolower($clinic_folder);

    $candidates = [
        "$dir/{$cCap}_report5_{$ymd}.csv",
        "$dir/{$cLow}_report5_{$ymd}.csv",
        "$dir/report5_dump_{$ymd}.csv",
    ];
    foreach ($candidates as $p) {
        if (is_file($p)) return $p;
    }
    $glob = glob("$dir/*report5*.csv");
    if ($glob) {
        usort($glob, fn($a,$b) => filemtime($b) <=> filemtime($a));
        return $glob[0];
    }
    return null;
}

/**
 * After DB updates, mirror client_stage and client_note back into the most recent report5 CSV,
 * so a second "Fetch Data" is unnecessary.
 */
function patch_report5_csv(string $clinic_folder, array $updatesById, string $csv_root_dir = '/home/notesao/NotePro-Report-Generator/csv'): array {
    $candidates = glob($csv_root_dir.'/'.$clinic_folder.'/*report5*.csv');
    if (!$candidates) return ['csv_path'=>null,'rows_updated'=>0,'skipped'=>'No report5 CSV found'];
    usort($candidates, fn($a,$b)=>filemtime($b) <=> filemtime($a));
    $csvPath = $candidates[0];

    $in = fopen($csvPath, 'r');
    if (!$in) return ['csv_path'=>$csvPath,'rows_updated'=>0,'skipped'=>'Unable to open CSV for reading'];

    $tmpPath = $csvPath.'.tmp_'.getmypid();
    $out = fopen($tmpPath, 'w');
    if (!$out) { fclose($in); return ['csv_path'=>$csvPath,'rows_updated'=>0,'skipped'=>'Unable to open temp file for writing']; }

    $header = fgetcsv($in);
    if ($header === false) {
        fclose($in); fclose($out); @unlink($tmpPath);
        return ['csv_path'=>$csvPath,'rows_updated'=>0,'skipped'=>'CSV missing header'];
    }

    $idx = [];
    foreach ($header as $i => $col) $idx[strtolower(trim($col))] = $i;

    fputcsv($out, $header);

    $rowsUpdated = 0;
    while (($row = fgetcsv($in)) !== false) {
        $cid = isset($idx['client_id'], $row[$idx['client_id']]) && is_numeric($row[$idx['client_id']])
            ? (int)$row[$idx['client_id']] : null;

        if ($cid !== null && isset($updatesById[$cid])) {
            $u = $updatesById[$cid];
            if (isset($idx['client_stage']) && array_key_exists('client_stage', $u)) {
                $row[$idx['client_stage']] = (string)$u['client_stage'];
            }
            if (isset($idx['client_note']) && array_key_exists('client_note', $u)) {
                $row[$idx['client_note']] = (string)$u['client_note'];
            }
            $rowsUpdated++;
        }
        fputcsv($out, $row);
    }
    fclose($in); fclose($out);

    if (!rename($tmpPath, $csvPath)) {
        @unlink($tmpPath);
        return ['csv_path'=>$csvPath,'rows_updated'=>0,'skipped'=>'Failed to replace original CSV'];
    }
    return ['csv_path'=>$csvPath,'rows_updated'=>$rowsUpdated,'skipped'=>null];
}

// --- 2) Inputs ------------------------------------------------------
$start_date = $_POST['start_date'] ?? '';
$end_date   = $_POST['end_date']   ?? '';

// --- 3) Paths -------------------------------------------------------
$clinic_folder = 'lakeview';
$csv_root      = '/home/notesao/NotePro-Report-Generator/csv';
$csv_path      = find_today_csv($csv_root, $clinic_folder);

if (!$csv_path || !is_file($csv_path)) {
    echo json_encode(['status'=>'error','message'=>'Report 5 CSV not found for today or recent run.']);
    exit;
}

$python_script = '/home/notesao/NotePro-Report-Generator/Update_Clients.py';
$venv_python   = '/home/notesao/NotePro-Report-Generator/venv311/bin/python3';
$templates_dir = "/home/notesao/NotePro-Report-Generator/templates/$clinic_folder";

if (!is_file($python_script)) {
    echo json_encode(['status'=>'error','message'=>"Python script missing: $python_script"]);
    exit;
}
if (!is_file($venv_python)) {
    echo json_encode(['status'=>'error','message'=>"Python interpreter missing: $venv_python"]);
    exit;
}

// Output CSV from Python
$output_csv_path = "$csv_root/$clinic_folder/updated_report5_dump_".date('Ymd').'.csv';

// --- 4) Sanitize → minimally validate → write a "valid rows" CSV ----
$sanitized_csv_path = sanitize_csv_file($csv_path);
$valid_csv_path = tempnam(sys_get_temp_dir(), 'valid_csv_');

$preSkips = [];
$totalCount = 0; $validCount = 0;

$in  = fopen($sanitized_csv_path, 'r');
$out = fopen($valid_csv_path, 'w');
if (!$in || !$out) {
    if ($in) fclose($in);
    if ($out) fclose($out);
    @unlink($sanitized_csv_path);
    @unlink($valid_csv_path);
    echo json_encode(['status'=>'error','message'=>'Unable to open temp files for CSV processing.']);
    exit;
}

$header = fgetcsv($in);
if ($header === false) {
    fclose($in); fclose($out);
    @unlink($sanitized_csv_path);
    @unlink($valid_csv_path);
    echo json_encode(['status'=>'error','message'=>'CSV appears empty or lacks a header row.']);
    exit;
}
fputcsv($out, $header);

// Only require client_id to avoid dropping boolean-mode programs (DOEP/SAE).
$hdrIdx = array_flip($header);
$needClientId = isset($hdrIdx['client_id']);

if (!$needClientId) {
    fclose($in); fclose($out);
    @unlink($sanitized_csv_path);
    @unlink($valid_csv_path);
    echo json_encode(['status'=>'error','message'=>'CSV must contain client_id column.']);
    exit;
}

while (($row = fgetcsv($in, 10000, ',')) !== false) {
    $totalCount++;
    $cid = $row[$hdrIdx['client_id']] ?? '';
    if ($cid === '' || !is_numeric($cid)) {
        $preSkips[] = ['client_id'=>'', 'reason'=>'Missing or non-numeric client_id'];
        continue;
    }
    fputcsv($out, $row);
    $validCount++;
}
fclose($in); fclose($out);

// --- 5) Run Python once --------------------------------------------
$cmd =
    escapeshellarg($venv_python) . ' ' .
    escapeshellarg($python_script) .
    ' --csv_file '       . escapeshellarg($valid_csv_path) .
    ' --start_date '     . escapeshellarg($start_date) .
    ' --end_date '       . escapeshellarg($end_date) .
    ' --output_csv_path '. escapeshellarg($output_csv_path) .
    ' --templates_dir '  . escapeshellarg($templates_dir);

exec($cmd . ' 2>&1', $pyOut, $pyCode);

if ($pyCode !== 0 || !is_file($output_csv_path)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error generating updated CSV from Python.',
        'python'  => ['exit_code'=>$pyCode, 'output'=>implode("\n", $pyOut)]
    ]);
    @unlink($sanitized_csv_path);
    @unlink($valid_csv_path);
    exit;
}

// --- 6) Parse updated CSV ------------------------------------------
$rows = [];
if (($h = fopen($output_csv_path, 'r')) !== false) {
    while (($data = fgetcsv($h, 10000, ',')) !== false) {
        if (!empty(array_filter($data))) $rows[] = array_map('trim', $data);
    }
    fclose($h);
}

if (count($rows) < 2) {
    echo json_encode(['status'=>'error','message'=>'Updated CSV is empty or malformed.']);
    @unlink($sanitized_csv_path);
    @unlink($valid_csv_path);
    exit;
}

$keys = array_shift($rows); // header
$idx  = array_flip($keys);

// Fields we will accept from CSV → DB
$updatable = [
    'client_stage',      // maps to client_stage_id via lookup
    'client_note',       // maps to client.note
    'orientation_date',  // YYYY-MM-DD
    'exit_date',         // YYYY-MM-DD
    'exit_reason',       // maps to exit_reason_id via lookup
    // 'referral_type'   // intentionally omitted unless schema is confirmed
];

foreach (['client_id'] as $required) {
    if (!isset($idx[$required])) {
        echo json_encode(['status'=>'error','message'=>"Updated CSV missing required column: $required"]);
        @unlink($sanitized_csv_path);
        @unlink($valid_csv_path);
        exit;
    }
}

// Determine which columns are present and actionable
$present = array_values(array_filter($updatable, fn($k)=>isset($idx[$k])));
if (empty($present)) {
    // Nothing to update in DB. Still report success and mirror CSV if needed.
    $present = [];
}

// --- 7) Build dynamic UPDATE only if we have columns ----------------
$processed = 0; $updated = 0; $skipped = 0;
$csvMirrorUpdates = []; // [cid => ['client_stage'=>..., 'client_note'=>...]]

if (!empty($present)) {
    $setParts = [];
    $paramOrder = [];
    if (in_array('client_stage', $present, true)) {
        $setParts[] = "client_stage_id = (SELECT id FROM client_stage WHERE stage LIKE ? LIMIT 1)";
        $paramOrder[] = 'client_stage';
    }
    if (in_array('client_note', $present, true)) {
        $setParts[] = "note = ?";
        $paramOrder[] = 'client_note';
    }
    if (in_array('orientation_date', $present, true)) {
        $setParts[] = "orientation_date = NULLIF(STR_TO_DATE(?, '%Y-%m-%d'),'0000-00-00')";
        $paramOrder[] = 'orientation_date';
    }
    if (in_array('exit_date', $present, true)) {
        $setParts[] = "exit_date = NULLIF(STR_TO_DATE(?, '%Y-%m-%d'),'0000-00-00')";
        $paramOrder[] = 'exit_date';
    }
    if (in_array('exit_reason', $present, true)) {
        $setParts[] = "exit_reason_id = (SELECT id FROM exit_reason WHERE reason LIKE ? LIMIT 1)";
        $paramOrder[] = 'exit_reason';
    }

    if (!empty($setParts)) {
        $sql = "UPDATE client SET ".implode(', ', $setParts)." WHERE client.id = ?";
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            echo json_encode(['status'=>'error','message'=>'Database prepare error: '.$con->error]);
            @unlink($sanitized_csv_path);
            @unlink($valid_csv_path);
            exit;
        }

        // Build type string
        $types = str_repeat('s', count($paramOrder)) . 'i';

        foreach ($rows as $r) {
            $cid = $r[$idx['client_id']] ?? '';
            if ($cid === '' || !is_numeric($cid)) { $skipped++; continue; }

            $bind = [];
            foreach ($paramOrder as $k) {
                $bind[] = $r[$idx[$k]] ?? null;
            }
            $bind[] = (int)$cid;

            $stmt->bind_param($types, ...$bind);
            if (!$stmt->execute() || $stmt->errno) {
                error_log("Update failed for client_id=$cid: ".$stmt->error);
                $skipped++;
                continue;
            }
            if ($stmt->affected_rows > 0) $updated++;
            $processed++;

            // Collect for CSV mirroring
            $mirror = [];
            if (in_array('client_stage', $present, true) && ($r[$idx['client_stage']] ?? '') !== '') {
                $mirror['client_stage'] = (string)$r[$idx['client_stage']];
            }
            if (in_array('client_note', $present, true) && ($r[$idx['client_note']] ?? '') !== '') {
                $mirror['client_note'] = (string)$r[$idx['client_note']];
            }
            if (!empty($mirror)) $csvMirrorUpdates[(int)$cid] = $mirror;
        }
        $stmt->close();
    }
} else {
    // No actionable columns. Still scan for mirroring if present in CSV.
    foreach ($rows as $r) {
        $cid = $r[$idx['client_id']] ?? '';
        if ($cid === '' || !is_numeric($cid)) continue;
        $mirror = [];
        if (isset($idx['client_stage']) && ($r[$idx['client_stage']] ?? '') !== '') {
            $mirror['client_stage'] = (string)$r[$idx['client_stage']];
        }
        if (isset($idx['client_note']) && ($r[$idx['client_note']] ?? '') !== '') {
            $mirror['client_note'] = (string)$r[$idx['client_note']];
        }
        if (!empty($mirror)) $csvMirrorUpdates[(int)$cid] = $mirror;
    }
}

// --- 8) Cleanup temps -----------------------------------------------
@unlink($sanitized_csv_path);
@unlink($valid_csv_path);

// --- 9) Patch latest report5 CSV with mirrored fields ---------------
$csvPatch = ['csv_path'=>null,'rows_updated'=>0,'skipped'=>null];
if (!empty($csvMirrorUpdates)) {
    $csvPatch = patch_report5_csv($clinic_folder, $csvMirrorUpdates, $csv_root);
}

// --- 10) Respond ----------------------------------------------------
echo json_encode([
    'status'  => 'success',
    'message' => 'Update complete.',
    'summary' => [
        'processed_after_python' => $processed,
        'updated_after_python'   => $updated,
        'skipped_after_python'   => $skipped,
    ],
    'pre_python_skips' => [
        'total_rows'   => $totalCount,
        'valid_rows'   => $validCount,
        'skipped_rows' => array_map(fn($s)=>['client_id'=>$s['client_id'],'reason'=>$s['reason']], $preSkips),
    ],
    'python' => [
        'exit_code' => $pyCode,
        'output'    => implode("\n", $pyOut),
        'input_csv' => basename($csv_path),
        'output_csv'=> basename($output_csv_path),
    ],
    'csv_patch' => $csvPatch,
]);
exit;
