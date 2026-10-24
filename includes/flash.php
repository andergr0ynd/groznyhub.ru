<?php

function setFlashMessage(string $type, string $message, string $description = '', string $code = ''): void
{
    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
        'description' => $description,
        'code' => $code,
    ];
}

function pullFlashMessages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return is_array($messages) ? $messages : [];
}

function redirectToIndexWithFlash(string $type, string $message, string $description = '', string $code = ''): void
{
    setFlashMessage($type, $message, $description, $code);
    header('Location: ' . siteUrl('index.php'));
    exit;
}

function getIndexAuthErrorMessages(): array
{
    return [
        'no_code' => __('Код авторизации не получен'),
        'invalid_state' => __('Неверное состояние авторизации'),
        'token_failed' => __('Ошибка получения токена'),
        'user_info_failed' => __('Ошибка получения информации о пользователе'),
        'no_user_data' => __('Данные пользователя не найдены'),
        'redirect_mismatch' => __('Redirect URI не совпадает с зарегистрированным в Twitch Console'),
        'access_denied' => __('Доступ запрещен'),
        'kick_not_configured' => __('Привязка Kick не настроена на сервере'),
        'kick_auth_required' => __('Сначала войдите через Twitch'),
        'kick_no_code' => __('Код авторизации Kick не получен'),
        'kick_invalid_state' => __('Сессия Kick истекла. Попробуйте привязку снова.'),
        'kick_token_failed' => __('Не удалось получить токен Kick'),
        'kick_user_failed' => __('Не удалось получить профиль Kick'),
        'kick_slug_failed' => __('Не удалось определить ник Kick'),
        'kick_username_taken' => __('Этот Kick-ник уже привязан к другому аккаунту'),
        'kick_already_linked' => __('Этот Kick уже привязан к другому аккаунту'),
        'kick_link_failed' => __('Не удалось привязать Kick'),
    ];
}

/**
 * Старые ссылки с ?error= / ?kick_linked=1 → flash + чистый URL (не повторяется при F5).
 */
function consumeIndexUrlNotifications(): array
{
    if (!isset($_GET['kick_linked']) && !isset($_GET['error'])) {
        return pullFlashMessages();
    }

    if (isset($_GET['kick_linked']) && (string)$_GET['kick_linked'] === '1') {
        setFlashMessage('success', __('Kick успешно привязан к вашему аккаунту!'));
    }

    if (isset($_GET['error'])) {
        $code = (string)$_GET['error'];
        $messages = getIndexAuthErrorMessages();
        $description = isset($_GET['description']) ? urldecode((string)$_GET['description']) : '';
        setFlashMessage('error', $messages[$code] ?? $code, $description, $code);
    }

    header('Location: ' . siteUrl('index.php'));
    exit;
}

function renderIndexFlashMessages(array $flashMessages): void
{
    foreach ($flashMessages as $flash) {
        $type = (string)($flash['type'] ?? '');
        $message = (string)($flash['message'] ?? '');
        $description = (string)($flash['description'] ?? '');
        $code = (string)($flash['code'] ?? '');
        if ($message === '') {
            continue;
        }
        if ($type === 'success') {
            echo '<div class="message message-success fade-in">';
            echo htmlspecialchars($message);
            echo '</div>';
            continue;
        }
        echo '<div class="message message-error fade-in">';
        echo htmlspecialchars($message);
        if ($description !== '') {
            echo '<br><small style="opacity: 0.8; margin-top: 0.5rem; display: block;">';
            echo htmlspecialchars($description);
            echo '</small>';
        }
        if ($code === 'redirect_mismatch') {
            echo '<br><small style="opacity: 0.8; margin-top: 0.5rem; display: block;">';
            echo htmlspecialchars(__('Убедитесь, что в настройках приложения Twitch указан точно такой же Redirect URI:'));
            echo ' <strong>' . htmlspecialchars(TWITCH_REDIRECT_URI) . '</strong>';
            echo '</small>';
        }
        echo '</div>';
    }
}
