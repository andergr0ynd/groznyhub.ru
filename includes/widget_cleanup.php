<?php

/**
 * Закрывает «зависшие» игры в БД, чтобы OBS-виджеты не оживали сами по старым записям.
 */
function isStreamLiveForWidgets(): bool
{
    try {
        $info = getStreamDisplayForHomepage(180);
        return !empty($info['is_live']);
    } catch (Throwable $e) {
        return false;
    }
}

function widgetStreamGateAllowsDisplay(): bool
{
    if (isset($_GET['force']) && (string)$_GET['force'] === '1') {
        return true;
    }
    return isStreamLiveForWidgets();
}

function expireStaleWidgetRecords(): void
{
    expireStaleCaseWidgetRecords();
    expireStaleJackpotWidgetRecords();
    expireStaleRaffleWidgetRecords();
    expireStaleContainerWidgetRecords();
}

function expireStaleCaseWidgetRecords(): void
{
    try {
        ensureCaseTables();
        $db = getDB();
        $db->exec("
            UPDATE case_purchases
            SET status = 'done', displayed_at = COALESCE(displayed_at, NOW())
            WHERE status = 'opening'
              AND created_at < DATE_SUB(NOW(), INTERVAL 20 MINUTE)
        ");
        expireCaseSales();
    } catch (PDOException $e) {
        error_log('expireStaleCaseWidgetRecords: ' . $e->getMessage());
    }
}

function expireStaleJackpotWidgetRecords(): void
{
    try {
        ensureJackpotTables();
        $db = getDB();
        $db->exec("
            UPDATE jackpot_rounds
            SET winner_displayed = 1
            WHERE status = 'done'
              AND winner_displayed = 0
              AND winner_username IS NOT NULL
              AND COALESCE(ends_at, started_at) < DATE_SUB(NOW(), INTERVAL 20 MINUTE)
        ");
        $db->exec("
            UPDATE jackpot_rounds
            SET status = 'cancelled'
            WHERE status IN ('active', 'drawing')
              AND COALESCE(ends_at, started_at) < DATE_SUB(NOW(), INTERVAL 20 MINUTE)
        ");
    } catch (PDOException $e) {
        error_log('expireStaleJackpotWidgetRecords: ' . $e->getMessage());
    }
}

function expireStaleRaffleWidgetRecords(): void
{
    try {
        ensureRaffleTables();
        $db = getDB();
        $db->exec("
            UPDATE raffle_rounds
            SET status = 'cancelled'
            WHERE status = 'active'
              AND ends_at IS NULL
              AND started_at < DATE_SUB(NOW(), INTERVAL 3 HOUR)
        ");
        $db->exec("
            UPDATE raffle_rounds
            SET status = 'cancelled'
            WHERE status = 'active'
              AND ends_at IS NOT NULL
              AND ends_at < DATE_SUB(NOW(), INTERVAL 20 MINUTE)
        ");
        $db->exec("
            UPDATE raffle_rounds
            SET status = 'done'
            WHERE status IN ('awaiting_response', 'responded')
              AND COALESCE(response_deadline, winner_picked_at, started_at)
                  < DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ");
    } catch (PDOException $e) {
        error_log('expireStaleRaffleWidgetRecords: ' . $e->getMessage());
    }
}

function expireStaleContainerWidgetRecords(): void
{
    if (!ensureContainerTables()) {
        return;
    }
    try {
        $db = getDB();
        $db->exec("
            UPDATE container_duels
            SET status = 'done', displayed_at = COALESCE(displayed_at, NOW())
            WHERE status = 'displaying'
              AND displayed_at IS NOT NULL
              AND displayed_at < DATE_SUB(NOW(), INTERVAL 3 MINUTE)
        ");
        $db->exec("
            UPDATE container_duels
            SET status = 'done', displayed_at = COALESCE(displayed_at, NOW())
            WHERE status IN ('opening', 'displaying', 'awaiting_coin')
              AND created_at < DATE_SUB(NOW(), INTERVAL 20 MINUTE)
        ");
    } catch (PDOException $e) {
        error_log('expireStaleContainerWidgetRecords: ' . $e->getMessage());
    }
}
