<?php
/* -------------------------------------------------
 *  Global JSON error / exception handler — must be
 *  the first thing in the script.
 * -------------------------------------------------*/
set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'    => 0,
        'error' => 'Server error: ' . $e->getMessage(),
        'at'    => $e->getFile() . ':' . $e->getLine()
    ]);
    exit;
});
set_error_handler(function ($no, $str, $file, $line) {
    throw new ErrorException($str, $no, 0, $file, $line);
});

/*-------------------------------------------------
 * Portable MIME-type helper
 *------------------------------------------------*/
if (!function_exists('mime_content_type')) {

    if (function_exists('finfo_open')) {
        /* ---------- Use finfo_*() if the Fileinfo
         *          extension is compiled in but the
         *          convenience wrapper was stripped
         * -----------------------------------------*/
        function mime_content_type(string $file): string
        {
            static $fi = null;
            if ($fi === null) {
                $fi = finfo_open(FILEINFO_MIME_TYPE);
            }
            return finfo_file($fi, $file) ?: 'application/octet-stream';
        }

    } else {
        /* ---------- Last-resort fallback ----------
         *          1. Try getimagesize()
         *          2. Guess by extension
         * ------------------------------------------*/
        function mime_content_type(string $file): string
        {
            // 1. getimagesize() exposes 'mime' for valid images
            if (function_exists('getimagesize')) {
                $info = @getimagesize($file);
                if ($info && isset($info['mime'])) {
                    return $info['mime'];
                }
            }

            // 2. Cheap extension map
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $map = [
                'png'  => 'image/png',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                'gif'  => 'image/gif',
            ];
            return $map[$ext] ?? 'application/octet-stream';
        }
    }
}


/* ── the rest of upload_logo.php follows ───────────────────────────── */
// e.g. require_once '../sql_functions.php';
//      validate $_FILES['logo'] …

/* ─────────  CONFIG  ───────── */
require_once __DIR__ . '/sql_functions.php';      // db(), run()
$maxBytes   = 2 * 1024 * 1024;                    // 2 MB
$rootUpload = __DIR__ . '/uploads/clinic_logos';  // adjust as needed
$publicRoot = '/uploads/clinic_logos';            // URL path that maps to the dir

/* ───────  JSON-safe output helpers  ─────── */
function jfail(string $msg, int $http = 400) {
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => 0, 'error' => $msg]); exit;
}
function jok(array $data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => 1] + $data); exit;
}

/* ───────  suppress stray warnings/notices  ─────── */
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

/* ───────  basic parameter checks  ─────── */
$cid = (int)($_POST['clinic_id'] ?? 0);
if ($cid < 1) jfail('Missing clinic_id');

if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    jfail('No file received or upload error');
}

/* ───────  validate the image  ─────── */
$fTmp  = $_FILES['logo']['tmp_name'];
$size  = $_FILES['logo']['size'];
$mime  = mime_content_type($fTmp);

if ($size > $maxBytes)  jfail('File too large (max 2 MB)');
if (!in_array($mime, ['image/png','image/jpeg','image/webp'])) {
    jfail('Only PNG, JPG/JPEG or WEBP accepted');
}

/* ───────  move into per-clinic folder  ─────── */
$ext   = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION) ?: 'png';
$fname = 'logo_' . time() . '.' . $ext;
$destDir = $rootUpload . '/' . $cid;
if (!is_dir($destDir)) mkdir($destDir, 0775, true);

$destFs  = $destDir . '/' . $fname;
if (!move_uploaded_file($fTmp, $destFs)) {
    jfail('Failed to move uploaded file', 500);
}

/* ───────  DB: store / update single-row logo  ─────── */
run("INSERT INTO clinic_logo (clinic_id, file_name)
      VALUES (?, ?)  ON DUPLICATE KEY UPDATE file_name = VALUES(file_name)",
    [$cid, $fname]);

$publicUrl = $publicRoot . '/' . $cid . '/' . $fname;
jok(['url' => $publicUrl, 'name' => $fname]);
