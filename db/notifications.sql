CREATE TABLE IF NOT EXISTS `notification_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED NOT NULL,
  `push_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `push_subscription` text DEFAULT NULL,
  `push_morning` tinyint(1) NOT NULL DEFAULT 1,
  `push_morning_time` time NOT NULL DEFAULT '08:00:00',
  `push_noon` tinyint(1) NOT NULL DEFAULT 1,
  `push_noon_time` time NOT NULL DEFAULT '12:00:00',
  `push_evening` tinyint(1) NOT NULL DEFAULT 1,
  `push_evening_time` time NOT NULL DEFAULT '18:00:00',
  `email` varchar(255) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_weekly_digest` tinyint(1) NOT NULL DEFAULT 0,
  `email_digest_day` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Sonntag',
  `email_missing_alert` tinyint(1) NOT NULL DEFAULT 0,
  `email_missing_days` int(11) NOT NULL DEFAULT 3,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_ns_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS `notification_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_sent` (`user_id`,`sent_at`),
  CONSTRAINT `fk_nl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
