<?php
require_once dirname(__DIR__) . '/config.php';

if (!function_exists('imagecreatetruecolor')) {
    http_response_code(503);
    exit('GD extension required');
}

$nick = isset($_GET['nick']) ? $_GET['nick'] : '';
$profile = getPublicProfile($nick);

$width = 1200;
$height = 630;

function ogResolveFontPath(): ?string
{
    $candidates = [
        dirname(__DIR__) . '/fonts/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        'C:\\Windows\\Fonts\\arial.ttf',
        'C:\\Windows\\Fonts\\segoeui.ttf',
    ];
    foreach ($candidates as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }
    return null;
}

function ogLoadRemoteImage(?string $url)
{
    if (!$url) {
        return null;
    }
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'MarkGrozny-OG/1.0',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        return null;
    }
    $image = @imagecreatefromstring($data);
    return $image ?: null;
}

function ogAllocateColor($image, string $hex): int
{
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return imagecolorallocate($image, $r, $g, $b);
}

function ogDrawText($image, string $text, int $size, int $x, int $y, int $color, ?string $fontPath, int $maxWidth = 0): void
{
    $text = trim($text);
    if ($text === '') {
        return;
    }

    if ($fontPath) {
        if ($maxWidth > 0) {
            while ($size > 14 && imagettfbbox($size, 0, $fontPath, $text)[2] - imagettfbbox($size, 0, $fontPath, $text)[0] > $maxWidth) {
                $size -= 2;
            }
        }
        imagettftext($image, $size, 0, $x, $y, $color, $fontPath, $text);
        return;
    }

    imagestring($image, 5, $x, $y - 14, mb_substr($text, 0, 60), $color);
}

function ogDrawCircleAvatar($canvas, $avatar, int $cx, int $cy, int $radius): void
{
    $size = $radius * 2;
    $square = imagecreatetruecolor($size, $size);
    imagealphablending($square, false);
    imagesavealpha($square, true);
    $transparent = imagecolorallocatealpha($square, 0, 0, 0, 127);
    imagefill($square, 0, 0, $transparent);

    $srcW = imagesx($avatar);
    $srcH = imagesy($avatar);
    imagecopyresampled($square, $avatar, 0, 0, 0, 0, $size, $size, $srcW, $srcH);

    $mask = imagecreatetruecolor($size, $size);
    imagealphablending($mask, false);
    imagesavealpha($mask, true);
    imagefill($mask, 0, 0, $transparent);
    $opaque = imagecolorallocate($mask, 255, 255, 255);
    imagefilledellipse($mask, $radius, $radius, $size, $size, $opaque);

    for ($x = 0; $x < $size; $x++) {
        for ($y = 0; $y < $size; $y++) {
            $maskColor = imagecolorat($mask, $x, $y);
            if (($maskColor & 0xFF) === 0) {
                imagesetpixel($square, $x, $y, $transparent);
            }
        }
    }

    imagecopy($canvas, $square, $cx - $radius, $cy - $radius, 0, 0, $size, $size);
    imagedestroy($square);
    imagedestroy($mask);
}

function ogDrawPlaceholderAvatar($canvas, string $letter, int $cx, int $cy, int $radius, int $bg, int $textColor): void
{
    imagefilledellipse($canvas, $cx, $cy, $radius * 2, $radius * 2, $bg);
    $font = ogResolveFontPath();
    if ($font) {
        $box = imagettfbbox(48, 0, $font, $letter);
        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];
        imagettftext($canvas, 48, 0, (int)($cx - $textWidth / 2), (int)($cy + $textHeight / 2), $textColor, $font, $letter);
    } else {
        imagestring($canvas, 5, $cx - 8, $cy - 8, $letter, $textColor);
    }
}

$image = imagecreatetruecolor($width, $height);
imagealphablending($image, true);

$bgPrimary = ogAllocateColor($image, '#0e0e10');
$bgPanel = ogAllocateColor($image, '#18181b');
$textPrimary = ogAllocateColor($image, '#efeff1');
$textSecondary = ogAllocateColor($image, '#adadb8');
$purple = ogAllocateColor($image, '#9146ff');
$orange = ogAllocateColor($image, '#ff5e00');
$gold = ogAllocateColor($image, '#ffcd00');
$border = ogAllocateColor($image, '#3a3a3d');
$success = ogAllocateColor($image, '#00f593');

imagefilledrectangle($image, 0, 0, $width, $height, $bgPrimary);
imagefilledrectangle($image, 0, 0, $width, 6, $purple);
imagefilledrectangle($image, 400, 0, $width, 6, $orange);

imagefilledrectangle($image, 48, 48, $width - 48, $height - 48, $bgPanel);
imagerectangle($image, 48, 48, $width - 48, $height - 48, $border);

$fontPath = ogResolveFontPath();
$avatarX = 170;
$avatarY = 315;
$avatarRadius = 88;

if ($profile['status'] === 'public') {
    $stats = $profile['stats'];
    $displayName = $profile['display_name'];
    $username = $profile['username'];
    $avatar = ogLoadRemoteImage($profile['avatar_url'] ?? null);

    if ($avatar) {
        imagefilledellipse($image, $avatarX, $avatarY, $avatarRadius * 2 + 8, $avatarRadius * 2 + 8, $border);
        ogDrawCircleAvatar($image, $avatar, $avatarX, $avatarY, $avatarRadius);
        imagedestroy($avatar);
    } else {
        ogDrawPlaceholderAvatar($image, strtoupper(substr($displayName, 0, 1)), $avatarX, $avatarY, $avatarRadius, $border, $textPrimary);
    }

    $textX = 300;
    ogDrawText($image, $displayName, 46, $textX, 170, $textPrimary, $fontPath, 820);
    ogDrawText($image, '@' . $username, 28, $textX, 220, $textSecondary, $fontPath, 820);

    $statusText = $stats['is_active'] ? 'В рефке' : 'Не в рефке';
    $statusColor = $stats['is_active'] ? $success : $textSecondary;
    ogDrawText($image, $statusText, 22, $textX, 305, $statusColor, $fontPath);

    $timeText = $stats['hours'] . 'ч ' . $stats['minutes'] . 'м';
    $messagesText = number_format($stats['messages_count'], 0, ',', ' ') . ' сообщ.';
    $streakText = $profile['bonus_streak'] > 0 ? $profile['bonus_streak'] . '/7' : '—';

    $cards = [
        ['label' => 'Время рефки', 'value' => $timeText],
        ['label' => 'Сообщений', 'value' => $messagesText],
        ['label' => 'Серия бонусов', 'value' => $streakText],
    ];

    $cardY = 360;
    $cardW = 250;
    $cardH = 120;
    $gap = 24;
    $startX = 300;

    foreach ($cards as $index => $card) {
        $x = $startX + $index * ($cardW + $gap);
        imagefilledrectangle($image, $x, $cardY, $x + $cardW, $cardY + $cardH, $bgPrimary);
        imagerectangle($image, $x, $cardY, $x + $cardW, $cardY + $cardH, $border);
        ogDrawText($image, $card['value'], 34, $x + 20, $cardY + 58, $textPrimary, $fontPath, $cardW - 30);
        ogDrawText($image, mb_strtoupper($card['label']), 16, $x + 20, $cardY + 95, $textSecondary, $fontPath, $cardW - 30);
    }
} elseif ($profile['status'] === 'private') {
    ogDrawPlaceholderAvatar($image, 'P', $avatarX, $avatarY, 70, $border, $textPrimary);
    ogDrawText($image, 'Профиль скрыт', 44, 300, 250, $textPrimary, $fontPath);
    ogDrawText($image, '@' . ($profile['username'] ?? ''), 30, 300, 310, $textSecondary, $fontPath);
    ogDrawText($image, 'Статистика недоступна', 24, 300, 360, $textSecondary, $fontPath);
} else {
    ogDrawPlaceholderAvatar($image, '?', $avatarX, $avatarY, 70, $border, $textPrimary);
    ogDrawText($image, 'Mark Grozny', 44, 300, 250, $textPrimary, $fontPath);
    ogDrawText($image, 'Профиль рефки', 28, 300, 310, $textSecondary, $fontPath);
    if (!empty($profile['username'])) {
        ogDrawText($image, '@' . $profile['username'], 24, 300, 360, $textSecondary, $fontPath);
    }
}

ogDrawText($image, 'mark-grozny · refka stats', 20, 72, $height - 72, $textSecondary, $fontPath);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=300');
imagepng($image);
imagedestroy($image);
