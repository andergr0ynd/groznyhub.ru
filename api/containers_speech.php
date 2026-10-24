<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/containers_tts.php';

header('Cache-Control: public, max-age=1800');
header('Access-Control-Allow-Origin: *');

$text = trim((string)($_GET['text'] ?? ''));
if ($text === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'text required';
    exit;
}

$voice = getenv('TTS_VOICE') ?: 'ru-RU-DmitryNeural';
$audio = synthesizeContainersTtsMp3($text, $voice);

if ($audio === null) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'TTS unavailable';
    exit;
}

header('Content-Type: audio/mpeg');
header('Content-Length: ' . strlen($audio));
echo $audio;
