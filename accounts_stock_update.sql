-- Accounts, password reset, "Remember me" and stock tracking. Run once (after the other .sql files).

-- ---------- users ----------
ALTER TABLE `users`
  MODIFY `username` varchar(60) NOT NULL,
  MODIFY `email` varchar(255) NOT NULL,
  MODIFY `password` varchar(255) NOT NULL,
  ADD COLUMN `remember_selector` varchar(32) NULL AFTER `password`,
  ADD COLUMN `password_reset_hash` char(64) NULL AFTER `remember_expiry`,
  ADD COLUMN `password_reset_expires` datetime NULL AFTER `password_reset_hash`,
  ADD COLUMN `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD UNIQUE KEY `unique_remember_selector` (`remember_selector`),
  ADD UNIQUE KEY `unique_password_reset_hash` (`password_reset_hash`);

-- Old-format "Remember me" tokens stop working; those users simply log in once more
UPDATE `users` SET `remember_token` = NULL, `remember_expiry` = NULL;

-- The real sign-up date of existing users is unknown; their first order is the closest date we have
UPDATE `users` u
JOIN (SELECT `user_id`, MIN(`created_at`) AS `first_order` FROM `orders` GROUP BY `user_id`) o ON o.`user_id` = u.`id`
SET u.`created_at` = o.`first_order`;

-- ---------- cart ----------
DELETE c FROM `cart` c LEFT JOIN `users` u ON u.`id` = c.`user_id` WHERE u.`id` IS NULL;
DELETE c FROM `cart` c LEFT JOIN `products` p ON p.`id` = c.`product_id` WHERE p.`id` IS NULL;
DELETE c1 FROM `cart` c1 JOIN `cart` c2
  ON c1.`user_id` = c2.`user_id` AND c1.`product_id` = c2.`product_id` AND c1.`id` > c2.`id`;

ALTER TABLE `cart`
  ADD UNIQUE KEY `unique_cart` (`user_id`, `product_id`),
  ADD KEY `product_id` (`product_id`),
  ADD CONSTRAINT `cart_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

-- ---------- stock ----------
UPDATE `products` SET `stock_quantity` = 0 WHERE `stock_quantity` IS NULL;
ALTER TABLE `products` MODIFY `stock_quantity` int NOT NULL DEFAULT 0;

-- 1 = this order took its quantity out of stock. Older orders never did, so cancelling them must not add stock.
ALTER TABLE `orders` ADD COLUMN `stock_deducted` tinyint(1) NOT NULL DEFAULT 0 AFTER `unit_price`;

-- ---------- unused table ----------
DROP TABLE IF EXISTS `user_products`;
