<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => __('Ставка только в чате: !контейнер <минуты> @ник'),
], JSON_UNESCAPED_UNICODE);