-- ============================================================
-- Visitor Logs Table
-- Run this once on your database to enable visitor tracking
-- ============================================================
CREATE TABLE IF NOT EXISTS `visitor_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_key` VARCHAR(64)  NOT NULL COMMENT 'Hashed session+IP, used to deduplicate per-day',
    `page`        VARCHAR(255) NOT NULL DEFAULT '/' COMMENT 'Page/entry point visited',
    `ip_address`  VARCHAR(45)  NOT NULL DEFAULT '' COMMENT 'Visitor IP (IPv4 or IPv6)',
    `user_agent`  VARCHAR(512) NOT NULL DEFAULT '',
    `referer`     VARCHAR(512) NOT NULL DEFAULT '',
    `visited_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_visited_at`  (`visited_at`),
    INDEX `idx_session_key` (`session_key`),
    INDEX `idx_page`        (`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
