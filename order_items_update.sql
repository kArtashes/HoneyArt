-- One order can hold several products. Run once (after accounts_stock_update.sql).
-- Existing orders keep their numbers and each becomes an order with one product.

CREATE TABLE `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` int NOT NULL,   -- price at the moment of purchase
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_order_fk` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_product_fk` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `unit_price`)
SELECT `id`, `product_id`, `quantity`, `unit_price` FROM `orders`;

-- The delivery address is copied into the order, so later address changes don't rewrite old orders.
-- Older orders never stored it: those columns stay empty.
ALTER TABLE `orders`
  ADD COLUMN `total` int NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN `delivery_phone` varchar(32) NULL AFTER `total`,
  ADD COLUMN `delivery_city` varchar(100) NULL AFTER `delivery_phone`,
  ADD COLUMN `delivery_region` varchar(100) NULL AFTER `delivery_city`,
  ADD COLUMN `delivery_address_line1` varchar(255) NULL AFTER `delivery_region`,
  ADD COLUMN `delivery_address_line2` varchar(255) NULL AFTER `delivery_address_line1`,
  ADD COLUMN `delivery_notes` text NULL AFTER `delivery_address_line2`;

UPDATE `orders` SET `total` = `unit_price` * `quantity`;

ALTER TABLE `orders`
  DROP FOREIGN KEY `orders_ibfk_2`,
  DROP KEY `product_id`,
  DROP COLUMN `product_id`,
  DROP COLUMN `quantity`,
  DROP COLUMN `unit_price`;
