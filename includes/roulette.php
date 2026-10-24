<?php

/** Европейская рулетка 0–36: выплата за точное число (35:1 + ставка). */
const ROULETTE_STRAIGHT_MULTIPLIER = 36;

const ROULETTE_RED_NUMBERS = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];

function rouletteIsValidNumber(int $n): bool
{
    return $n >= 0 && $n <= 36;
}

function rouletteNumberColor(int $n): string
{
    if ($n === 0) {
        return 'green';
    }
    return in_array($n, ROULETTE_RED_NUMBERS, true) ? 'red' : 'black';
}

/** Фишка по размеру ставки: <30м зелёная, 30–59м синяя, 60м+ красная. */
function rouletteChipTier(int $betSeconds): string
{
    if ($betSeconds >= 3600) {
        return 'red';
    }
    if ($betSeconds >= 1800) {
        return 'blue';
    }
    return 'green';
}

function rouletteChipIcon(string $tier): string
{
    $map = [
        'green' => 'greenfishka.png',
        'blue' => 'bluefishka.png',
        'red' => 'redfishka.png',
    ];
    return $map[$tier] ?? $map['green'];
}

function rouletteColorLabel(string $color): string
{
    $labels = [
        'green' => 'зелёное',
        'red' => 'красное',
        'black' => 'чёрное',
    ];
    return $labels[$color] ?? $color;
}

function rouletteSpinNumber(): int
{
    return random_int(0, 36);
}
