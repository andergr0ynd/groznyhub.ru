<?php

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$apiKey = getenv('DEEPSEEK_API_KEY') ?: '';
if ($apiKey === '') {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'DeepSeek не настроен'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$type = (string)($input['type'] ?? '');
$facts = is_array($input['facts'] ?? null) ? $input['facts'] : [];

if (!in_array($type, ['coin', 'victory'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный type'], JSON_UNESCAPED_UNICODE);
    exit;
}

function narrateFallback(string $type, array $facts): string
{
    $strip = static fn($name) => trim(preg_replace('/^@+/', '', (string)$name));

    if ($type === 'coin') {
        $side = (string)($facts['coin_side'] ?? 'орёл');
        $who = $strip($facts['first_player'] ?? 'зритель');
        $isPvp = !empty($facts['is_pvp']);
        $guessed = !empty($facts['guessed']);

        if ($isPvp) {
            return "Выпало {$side}. Первым ходит {$who}.";
        }
        if ($guessed) {
            return "Выпало {$side}. {$who} угадал и ходит первым.";
        }
        return "Выпало {$side}. Не угадал — первым ходит бот.";
    }

    $winner = (string)($facts['winner'] ?? 'draw');
    $player = $strip($facts['player_name'] ?? 'зритель');
    $opponent = $strip($facts['opponent_name'] ?? 'бот');
    $prize = (string)($facts['prize_text'] ?? '');
    $isPvp = !empty($facts['is_pvp']);

    if ($winner === 'player') {
        return $prize !== '' ? "{$player} победил! Приз: {$prize}." : "{$player} победил!";
    }
    if ($winner === 'bot') {
        if ($isPvp) {
            return $prize !== '' ? "{$opponent} победил! Приз: {$prize}." : "{$opponent} победил!";
        }
        return 'Победил бот. Ставка сгорела.';
    }
    return $isPvp ? 'Ничья! Обе ставки возвращены.' : 'Ничья! Ставка возвращена.';
}

function deepseekNarrationLine(string $apiKey, string $type, array $facts, string $fallback): ?string
{
    $base = rtrim(getenv('DEEPSEEK_API_BASE') ?: 'https://api.deepseek.com', '/');
    $model = getenv('DEEPSEEK_MODEL') ?: 'deepseek-chat';

    $factsJson = json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $system = 'Ты диктор стрима на русском. Пиши ОДНО короткое предложение для озвучки (до 20 слов). '
        . 'Без эмодзи, без кавычек, без markdown. Только текст для TTS.';

    if ($type === 'coin') {
        $user = "Монетка выпала. Факты JSON: {$factsJson}. Объяви, кто ходит первым.";
    } else {
        $user = "Дуэль контейнеров завершена. Факты JSON: {$factsJson}. Объяви победителя.";
    }

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ],
        'max_tokens' => 80,
        'temperature' => 0.6,
        'stream' => false,
    ];

    $ch = curl_init($base . '/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 8,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    $data = json_decode($response, true);
    $text = trim((string)($data['choices'][0]['message']['content'] ?? ''));
    $text = trim($text, " \t\n\r\0\x0B\"'«»");

    if ($text === '' || mb_strlen($text) > 200) {
        return null;
    }

    return $text;
}

$fallback = narrateFallback($type, $facts);
$text = deepseekNarrationLine($apiKey, $type, $facts, $fallback) ?: $fallback;

echo json_encode([
    'success' => true,
    'text' => $text,
    'source' => $text === $fallback ? 'template' : 'deepseek',
], JSON_UNESCAPED_UNICODE);
