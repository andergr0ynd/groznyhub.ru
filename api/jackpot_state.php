<?php

require_once __DIR__ . '/../config.php';



header('Content-Type: application/json; charset=utf-8');

header('Cache-Control: no-store, no-cache, must-revalidate');

header('Access-Control-Allow-Origin: *');



expireStaleWidgetRecords();



if (!isJackpotPlayAllowed()) {
    echo json_encode(['mode' => 'hidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

function jackpotStateEntries($roundId) {

    try {

        return getJackpotEntriesForRound((int)$roundId);

    } catch (Throwable $e) {

        error_log('Ошибка участников рулетки: ' . $e->getMessage());

        return [];

    }

}



$state = getJackpotRoundForWidget();



if ($state['mode'] === 'active') {

    $round = $state['round'];

    $endsAt = strtotime($round['ends_at']);

    $defaultMin = (int)(JACKPOT_DEFAULT_BET_SECONDS / 60);

    $houseBank = getJackpotHouseBankSeconds();

    $potSeconds = (int)$round['total_seconds'];

    echo json_encode([

        'mode' => 'active',

        'round_id' => (int)$round['id'],

        'seconds_left' => max(0, $endsAt - time()),

        'duration_seconds' => JACKPOT_DURATION_SECONDS,

        'ends_at' => $endsAt,

        'house_bank_seconds' => $houseBank,

        'house_bank_text' => formatSecondsShort($houseBank),

        'bank_seconds' => $houseBank,

        'bank_text' => formatSecondsShort($houseBank),

        'pot_seconds' => $potSeconds,

        'pot_text' => formatSecondsShort($potSeconds),

        'participants' => (int)$state['participants'],

        'entries' => jackpotStateEntries((int)$round['id']),

        'command' => '!ставка',

        'min_minutes' => (int)(JACKPOT_MIN_BET_SECONDS / 60),

        'default_minutes' => $defaultMin,

        'multiplier' => ROULETTE_STRAIGHT_MULTIPLIER,

        'hint' => '!ставка 17 · !ставка ' . $defaultMin . ' 1,27,17 · до 3 чисел · x' . ROULETTE_STRAIGHT_MULTIPLIER . ' · банк ' . formatSecondsShort($houseBank),

    ], JSON_UNESCAPED_UNICODE);

    exit;

}



if ($state['mode'] === 'drawing') {

    $round = $state['round'];

    $houseBank = getJackpotHouseBankSeconds();

    echo json_encode([

        'mode' => 'drawing',

        'round_id' => (int)$round['id'],

        'house_bank_text' => formatSecondsShort($houseBank),

        'bank_text' => formatSecondsShort($houseBank),

        'pot_text' => formatSecondsShort((int)$round['total_seconds']),

        'entries' => jackpotStateEntries((int)$round['id']),

    ], JSON_UNESCAPED_UNICODE);

    exit;

}



if ($state['mode'] === 'winner') {

    $round = $state['round'];

    $winningNumber = (int)($round['winning_number'] ?? -1);

    $winningColor = rouletteNumberColor($winningNumber);

    $winners = getJackpotWinnersForRound((int)$round['id'], $winningNumber);

    $houseBank = getJackpotHouseBankSeconds();

    echo json_encode([

        'mode' => 'winner',

        'round_id' => (int)$round['id'],

        'winning_number' => $winningNumber,

        'winning_color' => $winningColor,

        'winning_color_label' => rouletteColorLabel($winningColor),

        'username' => $round['winner_username'],

        'bank_text' => formatSecondsShort($houseBank),

        'house_bank_text' => formatSecondsShort($houseBank),

        'bank_seconds' => (int)$round['winner_seconds'],

        'payout_text' => formatSecondsShort((int)$round['winner_seconds']),

        'multiplier' => ROULETTE_STRAIGHT_MULTIPLIER,

        'winners' => $winners,

        'entries' => jackpotStateEntries((int)$round['id']),

    ], JSON_UNESCAPED_UNICODE);

    exit;

}



echo json_encode(['mode' => 'hidden'], JSON_UNESCAPED_UNICODE);

