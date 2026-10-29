-- Queue of order e-mails waiting to be sent by send_order_notifications.php
CREATE TABLE IF NOT EXISTS `order_notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payload` json NOT NULL,
  `status` enum('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` tinyint unsigned NOT NULL DEFAULT 0,
  `locked_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
