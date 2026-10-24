<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=30');

$info = getStreamDisplayForHomepage(90);
$isLive = !empty($info['is_live']);

echo json_encode([
    'success' => true,
    'is_live' => $isLive,
    'label' => $isLive ? 'В эфире' : 'Оффлайн',
    'uptime' => $info['uptime_text'] ?? '',
    'channel' => $info['channel'] ?? TWITCH_CHANNEL_LOGIN,
], JSON_UNESCAPED_UNICODE);
