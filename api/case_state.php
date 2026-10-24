<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

ensureCaseTables();
expireStaleCaseWidgetRecords();
expireCaseSales();

$db = getDB();

$stmt = $db->query("
    SELECT id, username, collection_id, collection_name, cost_seconds, status, created_at
    FROM case_purchases
    WHERE status = 'opening'
    ORDER BY id ASC
    LIMIT 1
");
$opening = $stmt->fetch();

if ($opening) {
    $iconIndex = caseCollectionIconIndex($opening['collection_name'] ?? null, (int)($opening['collection_id'] ?? 0));
    echo json_encode([
        'mode' => 'purchased',
        'id' => (int)$opening['id'],
        'username' => $opening['username'],
        'collection_name' => $opening['collection_name'] ?? '',
        'case_icon_index' => $iconIndex,
        'case_icon_url' => caseCollectionIconUrl($opening['collection_name'] ?? null, (int)($opening['collection_id'] ?? 0)),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sale = getActiveCaseSale();
if ($sale) {
    $endsAt = strtotime($sale['ends_at']);
    $secondsLeft = max(0, $endsAt - time());

    echo json_encode([
        'mode' => 'sale',
        'sale_id' => (int)$sale['id'],
        'seconds_left' => $secondsLeft,
        'ends_at' => $endsAt,
        'cost_minutes' => (int)(CASE_COST_SECONDS / 60),
        'command' => '!купить кейс',
        'title' => 'Рандомный кейс',
        'stock' => 1,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'mode' => 'hidden',
], JSON_UNESCAPED_UNICODE);
