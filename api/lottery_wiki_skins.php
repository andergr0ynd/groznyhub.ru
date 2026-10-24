<?php

require_once __DIR__ . '/../config.php';



header('Content-Type: application/json; charset=utf-8');

header('Cache-Control: no-store, no-cache, must-revalidate');

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

header('Access-Control-Allow-Headers: Content-Type');



if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(204);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $catalog = loadWikiCatalogCache();

    $categories = [];

    foreach (getWikiCatalogSourceDefinitions() as $key => $def) {

        $categories[$key] = (string)$def['label'];

    }

    echo json_encode([

        'success' => true,

        'updated_at' => $catalog['skins_updated_at'] ?? $catalog['cars_updated_at'] ?? $catalog['cases_updated_at'] ?? null,

        'skins_updated_at' => $catalog['skins_updated_at'] ?? null,

        'cars_updated_at' => $catalog['cars_updated_at'] ?? null,

        'cases_updated_at' => $catalog['cases_updated_at'] ?? null,

        'count' => count(wikiCatalogAllItems($catalog)),

        'skins_count' => count($catalog['skins']),

        'cars_count' => count($catalog['cars']),

        'cases_count' => count($catalog['cases']),

        'thumbs_saved' => wikiThumbCacheCount(),

        'skins' => wikiCatalogAllItems($catalog),

        'catalog' => $catalog,

        'groups' => getWikiCatalogGroups(),

        'bundles' => listWikiJsonBundles(),

        'categories' => $categories,

        'wiki_origin' => WIKI_RADMIR_ORIGIN,

    ], JSON_UNESCAPED_UNICODE);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['success' => false, 'error' => 'method not allowed'], JSON_UNESCAPED_UNICODE);

    exit;

}



if (!isSiteAdmin()) {

    http_response_code(403);

    echo json_encode(['success' => false, 'error' => 'Только для админов'], JSON_UNESCAPED_UNICODE);

    exit;

}



$raw = file_get_contents('php://input');

$body = json_decode($raw !== false ? $raw : '', true);

if (!is_array($body)) {

    http_response_code(400);

    echo json_encode(['success' => false, 'error' => 'Неверный JSON'], JSON_UNESCAPED_UNICODE);

    exit;

}



$skins = null;
if (isset($body['skins']) && is_array($body['skins'])) {
    $skins = $body['skins'];
} elseif (isset($body['items']) && is_array($body['items'])) {
    $skins = $body['items'];
} elseif (array_is_list($body)) {
    $skins = $body;
}

if (!is_array($skins)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ожидается массив skins'], JSON_UNESCAPED_UNICODE);
    exit;
}



$bundle = trim((string)($body['bundle'] ?? ''));

if ($bundle === '' && !empty($body['group'])) {

    $bundle = trim((string)$body['group']);

}

if ($bundle === '' && !empty($skins[0]['group'])) {

    $bundle = trim((string)$skins[0]['group']);

}

if ($bundle === '' && !empty($skins[0]['category'])) {

    $bundle = wikiCatalogGroupForCategory((string)$skins[0]['category']);

}

if (!isWikiCatalogBundle($bundle)) {

    $bundle = 'skins';

}



$merge = !isset($body['replace']) || !$body['replace'];

$result = saveWikiSkinsCache($skins, $merge, $bundle);

if (empty($result['success'])) {

    http_response_code(500);

}



echo json_encode($result, JSON_UNESCAPED_UNICODE);

