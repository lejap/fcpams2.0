-- ============================================================
-- Visitor Logs Table — Run this once on your database
-- ============================================================
CREATE TABLE IF NOT EXISTS `visitor_logs` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_key`     VARCHAR(64)  NOT NULL COMMENT 'Hashed IP+UA+date — same person same day = same key',
    `page`            VARCHAR(255) NOT NULL DEFAULT '/',
    `ip_address`      VARCHAR(45)  NOT NULL DEFAULT '',
    `user_agent`      VARCHAR(512) NOT NULL DEFAULT '',
    `referer`         VARCHAR(512) NOT NULL DEFAULT '',
    `is_unique_today` TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 = first visit of this person today',
    `visited_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_visited_at`    (`visited_at`),
    INDEX `idx_session_key`   (`session_key`),
    INDEX `idx_page`          (`page`),
    INDEX `idx_unique_today`  (`is_unique_today`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If you already created the table without is_unique_today, run this ALTER:
-- ALTER TABLE `visitor_logs` ADD COLUMN `is_unique_today` TINYINT(1) NOT NULL DEFAULT 1 AFTER `referer`;
-- UPDATE `visitor_logs` vl
--   JOIN (SELECT MIN(id) AS first_id FROM visitor_logs GROUP BY session_key, DATE(visited_at)) AS fi
--     ON vl.id = fi.first_id
--   SET vl.is_unique_today = 1;
-- UPDATE `visitor_logs` SET is_unique_today = 0
--   WHERE id NOT IN (SELECT first_id FROM (SELECT MIN(id) AS first_id FROM visitor_logs GROUP BY session_key, DATE(visited_at)) t);
