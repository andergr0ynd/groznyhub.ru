<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

if (preg_match('#^/u/([A-Za-z0-9_]+)/og\.png$#', $uri, $m)) {
    $_GET['nick'] = $m[1];
    require __DIR__ . '/u/og.php';
    return true;
}

if (preg_match('#^/u/([A-Za-z0-9_]+)/?$#', $uri, $m)) {
    $_GET['nick'] = $m[1];
    require __DIR__ . '/u/index.php';
    return true;
}

$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if ($uri !== '/' && is_file($file)) {
    return false;
}

if (is_dir($file)) {
    $indexPhp = rtrim($file, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
    if (is_file($indexPhp)) {
        require $indexPhp;
        return true;
    }

    $indexHtml = rtrim($file, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
    if (is_file($indexHtml)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($indexHtml);
        return true;
    }
}

http_response_code(404);
echo 'Not found';
return true;
