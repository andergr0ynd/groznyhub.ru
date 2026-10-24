<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

expireStaleWidgetRecords();

$stream = getStreamDisplayForHomepage(90);

$raffleFull = getRaffleStateForWidget();
$raffleMode = (string)($raffleFull['mode'] ?? 'hidden');
$raffle = [
    'mode' => $raffleMode,
    'active' => in_array($raffleMode, ['collecting', 'active', 'awaiting_response', 'responded'], true),
    'keyword' => $raffleFull['keyword'] ?? null,
    'participants' => (int)($raffleFull['participants'] ?? 0),
    'entry_seconds_left' => $raffleFull['entry_seconds_left'] ?? null,
];

ensureCaseTables();
expireCaseSales();
$case = ['mode' => 'hidden', 'cost_minutes' => (int)(CASE_COST_SECONDS / 60)];
$sale = getActiveCaseSale();
if ($sale) {
    $endsAt = strtotime((string)$sale['ends_at']);
    $case['mode'] = 'sale';
    $case['seconds_left'] = max(0, $endsAt - time());
}

$containers = getContainerHubSnapshot();

echo json_encode([
    'stream' => [
        'is_live' => !empty($stream['is_live']),
        'label' => !empty($stream['is_live']) ? 'В эфире' : 'Оффлайн',
        'uptime' => $stream['uptime_text'] ?? '',
    ],
    'config' => [
        'site_url' => SITE_URL,
        'site_host' => SITE_HOST,
        'case_cost_minutes' => (int)(CASE_COST_SECONDS / 60),
        'containers_min_minutes' => (int)(CONTAINERS_MIN_BET_SECONDS / 60),
        'raffle_example_keyword' => 'Скин',
    ],
    'raffle' => $raffle,
    'case' => $case,
    'containers' => $containers,
], JSON_UNESCAPED_UNICODE);
