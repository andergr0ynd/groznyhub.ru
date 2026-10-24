<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400');
header('Access-Control-Allow-Origin: *');

$icon = isset($_GET['icon']) ? trim((string)$_GET['icon']) : '';
$width = isset($_GET['width']) ? (int)$_GET['width'] : 128;

if ($icon === '') {
    echo json_encode(['success' => false, 'error' => 'icon required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = resolveLotteryPrizeIconUrl($icon, $width);
if ($url === null) {
    echo json_encode(['success' => false, 'error' => 'invalid icon'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'icon' => $icon,
    'url' => $url,
    'origin' => WIKI_RADMIR_ORIGIN,
], JSON_UNESCAPED_UNICODE);
