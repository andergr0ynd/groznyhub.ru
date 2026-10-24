<?php
require_once __DIR__ . '/../config.php';

$icon = isset($_GET['icon']) ? trim((string)$_GET['icon']) : '';
$transparentPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

if ($icon !== '') {
    $localFile = lotteryLocalIconFileFromRef($icon);
    if ($localFile === null && str_starts_with($icon, 'case:')) {
        $caseIndex = lotteryCaseIconIndexFromStore($icon);
        if ($caseIndex !== null) {
            $localFile = lotteryLocalCaseIconFile($caseIndex);
        }
    }
    if ($localFile !== null) {
        $path = __DIR__ . '/../icons/' . $localFile;
        if (is_readable($path)) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=604800');
            header('Content-Length: ' . (string)filesize($path));
            readfile($path);
            exit;
        }
    }
}

if ($icon === '' || !wikiThumbCacheExists($icon)) {
    http_response_code(404);
    header('Content-Type: image/png');
    header('Cache-Control: no-store');
    echo $transparentPng;
    exit;
}

$meta = wikiThumbCacheMeta($icon);
$file = wikiThumbCacheFilePath($icon);

header('Content-Type: ' . ($meta['mime'] ?? 'image/png'));
header('Cache-Control: public, max-age=604800');
header('Content-Length: ' . (string)filesize($file));
readfile($file);
