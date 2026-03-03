<?php
// fonts.php — LIST server-side fonts via fontconfig

header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600');

/* get list of installed fonts using fc-list */
$cmd  = ['fc-list', '--format=%{family}|%{style}|%{file}\n'];
$desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open($cmd, $desc, $pipes);

$output = '';
if (is_resource($proc)) {
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
}
if (!$output) {
    echo json_encode([]);
    exit;
}

$lines = array_filter(explode("\n", trim($output)));
$best  = []; // family => ['file' => ..., 'score' => ...]

/* lower score = higher priority */
$style_score = static function (string $style): int {
    $s = strtolower(trim($style));
    if ($s === 'regular' || $s === 'roman' || $s === 'book' || $s === 'text') return 0;
    if ($s === 'bold')                                                        return 1;
    if (str_contains($s, 'italic') || str_contains($s, 'oblique'))            return 2;
    return 3;
};

foreach ($lines as $line) {
    $parts = explode('|', $line, 3);
    if (count($parts) < 3) continue;

    /* take first family name (some entries are comma-separated) */
    $families = explode(',', $parts[0]);
    $family   = trim($families[0]);

    if (empty($family)) continue;

    $style = trim(explode(',', $parts[1])[0]);
    $file  = trim($parts[2]);
    if (!file_exists($file)) continue;

    $score = $style_score($style);

    if (!isset($best[$family]) || $score < $best[$family]['score']) {
        $best[$family] = ['file' => $file, 'score' => $score];
    }
}

$fonts = [];
foreach ($best as $family => $entry) {
    $file   = $entry['file'];
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
