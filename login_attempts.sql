-- Failed log-in attempts, used to slow down password guessing (see login_limiter.php)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `scope` varchar(20) NOT NULL,          -- 'customer' or 'admin'
  `identifier` varchar(255) NOT NULL,    -- e-mail (customer) or username (admin), lower-case
  `ip` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `by_identifier` (`scope`, `identifier`, `attempted_at`),
  KEY `by_ip` (`scope`, `ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
