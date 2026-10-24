<?php
require_once '../config.php';

// Проверка авторизации
$user = getCurrentUser();
if (!$user) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

// Проверка прав администратора
if (!isset($user['admin']) || $user['admin'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен']);
    exit;
}

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса']);
    exit;
}

// Проверка подтверждения
$confirm = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';

if (!$confirm) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Требуется подтверждение']);
    exit;
}

try {
    $db = getDB();
    
    // Очищаем все таблицы
    $db->exec("TRUNCATE TABLE user_time_tracking");
    $db->exec("TRUNCATE TABLE chat_messages");
    $db->exec("TRUNCATE TABLE active_sessions");
    $db->exec("TRUNCATE TABLE captcha_challenges");
    $db->exec("TRUNCATE TABLE case_purchases");
    $db->exec("TRUNCATE TABLE case_sale_windows");
    $db->exec("TRUNCATE TABLE jackpot_entries");
    $db->exec("TRUNCATE TABLE jackpot_rounds");
    $db->exec("TRUNCATE TABLE daily_bonus_streaks");
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Все данные обнулены']);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}

