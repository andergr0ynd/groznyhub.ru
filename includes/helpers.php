<?php

function formatSecondsShort($seconds) {
    $seconds = max(0, (int)$seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    if ($hours > 0) {
        return $hours . 'ч ' . $minutes . 'м';
    }
    if ($minutes > 0) {
        return $minutes . 'м ' . $secs . 'с';
    }
    return $secs . 'с';
}

function formatContainerRubles(int $amount): string
{
    return number_format($amount, 0, ',', ' ') . ' ₽';
}
