-- GroznyHub — Twitch / сайт (MySQL 5.7)
-- База: groznyhub
-- Импорт: phpMyAdmin → выбрать БД → Импорт → этот файл

SET NAMES utf8mb4;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Сайт: пользователи Twitch OAuth
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `twitch_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` text COLLATE utf8mb4_unicode_ci,
  `access_token` text COLLATE utf8mb4_unicode_ci,
  `refresh_token` text COLLATE utf8mb4_unicode_ci,
  `admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 — админ',
  `is_private` tinyint(1) DEFAULT '0',
  `radmir_nickname` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `radmir_server` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kick_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kick_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kick_linked_at` datetime DEFAULT NULL,
  `telegram_chat_id` bigint(20) DEFAULT NULL,
  `telegram_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telegram_link_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telegram_link_expires_at` datetime DEFAULT NULL,
  `telegram_linked_at` datetime DEFAULT NULL,
  `notify_stream` tinyint(1) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `twitch_id` (`twitch_id`),
  UNIQUE KEY `uniq_users_kick_id` (`kick_id`),
  UNIQUE KEY `uniq_users_kick_username` (`kick_username`),
  KEY `idx_twitch_id` (`twitch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Twitch-бот + сайт: рефка, чат, игры
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_time_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `twitch_id` varchar(255) DEFAULT NULL,
  `total_seconds` int(11) NOT NULL DEFAULT '0',
  `session_start` datetime DEFAULT NULL,
  `last_activity` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `active_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `session_start` datetime NOT NULL,
  `last_message_time` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `active_bonus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `multiplier` decimal(5,2) NOT NULL,
  `started_at` datetime NOT NULL,
  `started_by` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `daily_bonus_streaks` (
  `username` varchar(255) NOT NULL,
  `current_streak` int(11) NOT NULL DEFAULT '0',
  `longest_streak` int(11) NOT NULL DEFAULT '0',
  `last_claim_date` date DEFAULT NULL,
  `total_claims` int(11) NOT NULL DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `refka_spam_state` (
  `username` varchar(255) NOT NULL,
  `warning_count` int(11) NOT NULL DEFAULT '0',
  `paused_until` datetime DEFAULT NULL,
  `last_warning_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `captcha_challenges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `captcha_type` varchar(50) NOT NULL,
  `question` text NOT NULL,
  `answer` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bot_time_ledger` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `action_type` varchar(64) NOT NULL,
  `delta_seconds` int(11) NOT NULL,
  `balance_before` int(11) DEFAULT NULL,
  `balance_after` int(11) DEFAULT NULL,
  `meta_json` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ledger_username` (`username`),
  KEY `idx_ledger_created` (`created_at`),
  KEY `idx_ledger_action` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Кейсы
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `case_collections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `case_sale_windows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `started_by` varchar(255) NOT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ends_at` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_status_ends` (`status`,`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `case_purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `collection_id` int(11) NOT NULL,
  `collection_name` varchar(255) NOT NULL,
  `cost_seconds` int(11) NOT NULL DEFAULT '5400',
  `status` varchar(20) NOT NULL DEFAULT 'opening',
  `issued_in_game` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `displayed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sale_purchase` (`sale_id`),
  KEY `idx_status` (`status`),
  KEY `idx_username` (`username`),
  KEY `idx_sale` (`sale_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_issued` (`issued_in_game`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Джекпот
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `jackpot_rounds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `started_by` varchar(255) NOT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ends_at` datetime NOT NULL,
  `total_seconds` int(11) NOT NULL DEFAULT '0',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `winner_username` varchar(255) DEFAULT NULL,
  `winner_seconds` int(11) DEFAULT NULL,
  `winning_number` tinyint(3) unsigned DEFAULT NULL,
  `winner_displayed` tinyint(1) NOT NULL DEFAULT '0',
  `winner_announced` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_status_ends` (`status`,`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `jackpot_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `round_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `bet_number` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `bet_seconds` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_round_user_number` (`round_id`,`username`,`bet_number`),
  KEY `idx_round` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Контейнеры
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `container_duels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `bet_seconds` int(11) NOT NULL,
  `player_prizes` longtext NOT NULL,
  `bot_prizes` longtext NOT NULL,
  `player_total` int(11) NOT NULL,
  `bot_total` int(11) NOT NULL,
  `winner` varchar(16) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'opening',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `displayed_at` datetime DEFAULT NULL,
  `coin_pick` varchar(16) DEFAULT NULL,
  `coin_result` varchar(16) DEFAULT NULL,
  `first_turn` varchar(16) DEFAULT NULL,
  `opponent_username` varchar(255) DEFAULT NULL,
  `duel_mode` varchar(16) NOT NULL DEFAULT 'bot',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_username` (`username`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Розыгрыш (!розыгрыш)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `raffle_rounds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) NOT NULL,
  `started_by` varchar(255) NOT NULL,
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ends_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `winner_username` varchar(255) DEFAULT NULL,
  `winner_picked_at` datetime DEFAULT NULL,
  `response_deadline` datetime DEFAULT NULL,
  `response_seconds` int(11) NOT NULL DEFAULT '60',
  `responded_at` datetime DEFAULT NULL,
  `winner_announced` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `raffle_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `round_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `matched_message` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_round_user` (`round_id`,`username`),
  KEY `idx_round` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `raffle_excluded` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `round_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `reason` varchar(32) DEFAULT 'no_response',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_round_excluded` (`round_id`,`username`),
  KEY `idx_round` (`round_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Сайт: статус стрима
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `twitch_stream_status` (
  `channel` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_live` tinyint(1) DEFAULT '0',
  `started_at` datetime DEFAULT NULL,
  `stream_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `game_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_notified_at` datetime DEFAULT NULL,
  `last_checked` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Сайт: лотерея (колесо на groznyhub.ru)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lottery_stage_cells` (
  `stage` tinyint(3) unsigned NOT NULL,
  `cell_index` tinyint(3) unsigned NOT NULL,
  `cell_type` varchar(16) NOT NULL DEFAULT 'empty',
  `prize_title` varchar(255) NOT NULL DEFAULT '',
  `prize_icon` varchar(255) DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`stage`,`cell_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `lottery_games` (
  `id` varchar(36) NOT NULL,
  `stage` tinyint(3) unsigned NOT NULL,
  `opened_cells` json NOT NULL,
  `board_cells` json DEFAULT NULL,
  `is_ended` tinyint(1) NOT NULL DEFAULT '0',
  `end_reason` varchar(32) DEFAULT NULL,
  `matched_prize` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lottery_games_stage` (`stage`),
  KEY `idx_lottery_games_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Справочные данные (кейсы + канал стрима)
-- ---------------------------------------------------------------------------
INSERT INTO `case_collections` (`name`, `slug`, `is_active`, `sort_order`) VALUES
('Коллекция «Форсаж»', 'case-1', 1, 1),
('Коллекция «Темные дела»', 'case-2', 1, 2),
('Коллекция «Велесова ночь»', 'case-3', 1, 3),
('Коллекция «Оперской»', 'case-4', 1, 4),
('Коллекция «Зимняя сказка»', 'case-5', 1, 5),
('Коллекция «Чёрное золото»', 'case-6', 1, 6),
('Коллекция «Охотничий»', 'case-7', 1, 7),
('Коллекция «Египетская сила»', 'case-8', 1, 8),
('Коллекция «Велесова Ночь 2»', 'case-9', 1, 9),
('Коллекция «Рождественский экспресс»', 'case-10', 1, 10),
('Коллекция «Путь воина»', 'case-11', 1, 11),
('Коллекция «Пиратский»', 'case-12', 1, 12),
('Коллекция «Проклятие»', 'case-13', 1, 13),
('Коллекция «Новогодний»', 'case-14', 1, 14),
('Коллекция «Бандитский»', 'case-15', 1, 15),
('Коллекция «Клоунский»', 'case-16', 1, 16),
('Коллекция «Мандариновый»', 'case-17', 1, 17)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `sort_order` = VALUES(`sort_order`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `twitch_stream_status` (`channel`, `is_live`) VALUES
('mark_grozny', 0)
ON DUPLICATE KEY UPDATE `channel` = VALUES(`channel`);

SET FOREIGN_KEY_CHECKS = 1;
