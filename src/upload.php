<?php
/* upload.php — handle image uploads */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['image']['error'] ?? 'unknown';
    http_response_code(400);
    echo json_encode(['error' => "Upload failed (code: $code)"]);
    exit;
}

$file    = $_FILES['image'];
$allowed = [
    'image/png', 'image/jpeg', 'image/gif',
    'image/webp', 'image/svg+xml', 'image/bmp',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => "Invalid file type: $mime"]);
    exit;
}

$ext = match ($mime) {
    'image/png'     => 'png',
    'image/jpeg'    => 'jpg',
    'image/gif'     => 'gif',
    'image/webp'    => 'webp',
    'image/svg+xml' => 'svg',
    'image/bmp'     => 'bmp',
    default         => 'bin',
};

/* generate safe filename */
$basename = pathinfo($file['name'], PATHINFO_FILENAME);
$basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
$basename = substr($basename, 0, 64);
$filename = $basename . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$dest = $uploadDir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

echo json_encode([
    'filename' => $filename,
    'url'      => 'uploads/' . $filename,
]);
