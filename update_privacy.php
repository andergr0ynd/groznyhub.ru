<?php
require_once 'config.php';

// Проверка авторизации
$user = getCurrentUser();
if (!$user) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса']);
    exit;
}

// Получение значения приватности
$isPrivate = isset($_POST['is_private']) && $_POST['is_private'] === '1';

try {
    // Обновляем настройку приватности
    $success = updatePrivacySetting($user['id'], $isPrivate);
    
    if ($success) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => $isPrivate ? 'Профиль скрыт из поиска' : 'Профиль виден в поиске'
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Ошибка обновления настройки']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
}

