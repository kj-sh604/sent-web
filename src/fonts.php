<?php
// fonts.php — LIST server-side fonts via fontconfig

header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600');

$output = shell_exec('fc-list --format="%{family}|%{file}\n" 2>/dev/null');
if (!$output) {
    echo json_encode([]);
    exit;
}

$lines = array_filter(explode("\n", trim($output)));
$fonts = [];
$seen  = [];

foreach ($lines as $line) {
    $parts = explode('|', $line, 2);
    if (count($parts) < 2) continue;

    /* take first family name (some entries are comma-separated) */
    $families = explode(',', $parts[0]);
    $family   = trim($families[0]);

    if (empty($family) || isset($seen[$family])) continue;

    $file = trim($parts[1]);
    if (!file_exists($file)) continue;

    $seen[$family] = true;

    $ext    = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $format = match ($ext) {
        'ttf'   => 'truetype',
        'otf'   => 'opentype',
        'woff'  => 'woff',
        'woff2' => 'woff2',
        default => 'truetype',
    };

    $fonts[] = [
        'family' => $family,
        'file'   => base64_encode($file),
        'format' => $format,
    ];
}

usort($fonts, fn($a, $b) => strcasecmp($a['family'], $b['family']));
echo json_encode($fonts);
