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

// Получение username из POST
$username = isset($_POST['username']) ? trim($_POST['username']) : null;

if (!$username) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Username не указан']);
    exit;
}

try {
    $db = getDB();
    
    // Удаляем пользователя из всех таблиц
    $usernameLower = strtolower($username);
    
    // Удаляем из user_time_tracking
    $stmt = $db->prepare("DELETE FROM user_time_tracking WHERE LOWER(username) = ?");
    $stmt->execute([$usernameLower]);
    
    // Удаляем из chat_messages
    $stmt = $db->prepare("DELETE FROM chat_messages WHERE LOWER(username) = ?");
    $stmt->execute([$usernameLower]);
    
    // Удаляем из active_sessions
    $stmt = $db->prepare("DELETE FROM active_sessions WHERE LOWER(username) = ?");
    $stmt->execute([$usernameLower]);
    
    // Удаляем из captcha_challenges
    $stmt = $db->prepare("DELETE FROM captcha_challenges WHERE LOWER(username) = ?");
    $stmt->execute([$usernameLower]);

    $stmt = $db->prepare("DELETE FROM case_purchases WHERE LOWER(username) = ?");
    $stmt->execute([$usernameLower]);

    $stmt = $db->prepare("DELETE FROM jackpot_entries WHERE LOWER(username) = ?");
    $stmt->execute([$usernameLower]);

    ensureDailyBonusTables();
    $stmt = $db->prepare("DELETE FROM daily_bonus_streaks WHERE LOWER(username) = ?");
    $stmt->execute([$usernameLower]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Пользователь удален']);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}

