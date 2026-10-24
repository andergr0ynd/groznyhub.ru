-- GroznyHub — баны лаунчера / Minecraft-сервера (по Twitch ID)
-- База: groznyhub

CREATE TABLE IF NOT EXISTS `launcher_bans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `twitch_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banned_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banned_by_twitch_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_launcher_bans_twitch` (`twitch_id`),
  KEY `idx_launcher_bans_active` (`active`),
  KEY `idx_launcher_bans_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
