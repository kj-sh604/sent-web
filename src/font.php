<?php
/* font.php — serve font files from the server's font directories */

$encoded = $_GET['f'] ?? '';
if (empty($encoded)) {
    http_response_code(400);
    exit('Missing parameter');
}

$file = base64_decode($encoded, true);
if ($file === false || !file_exists($file)) {
    http_response_code(404);
    exit('Font not found');
}

$real    = realpath($file);
$allowed = ['/usr/share/fonts', '/usr/local/share/fonts'];
$ok      = false;

foreach ($allowed as $dir) {
    if (str_starts_with($real, $dir)) {
        $ok = true;
        break;
    }
}

if (!$ok) {
    http_response_code(403);
    exit('Access denied');
}

$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'ttf'   => 'font/ttf',
    'otf'   => 'font/otf',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    default => 'application/octet-stream',
};

header("Content-Type: $mime");
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . filesize($file));
readfile($file);
