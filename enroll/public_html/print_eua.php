<?php
declare(strict_types=1);

// Candidates (first one that exists wins)
$candidates = [
  __DIR__.'/_agreements/pdf/eua.pdf',  // your file
  __DIR__.'/_agreements/pdf/eua.phf',  // fallback if you haven't renamed yet
];

$file = null;
foreach ($candidates as $p) { if (is_file($p)) { $file = $p; break; } }

if (!$file) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  echo "EUA PDF not found.\nLooked in:\n - ".implode("\n - ", $candidates);
  exit;
}

// Make sure nothing else is buffered
while (function_exists('ob_get_level') && ob_get_level()) { ob_end_clean(); }

$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = ($ext === 'pdf') ? 'application/pdf' : 'application/octet-stream';

header('Content-Type: '.$mime);
header('Content-Disposition: inline; filename="NotesAO-End-User-Agreement.pdf"');
header('Content-Length: '.filesize($file));
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');

readfile($file);
