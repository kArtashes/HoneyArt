-- A product can be hidden from the shop while its old orders keep working. Run once (after order_items_update.sql).
ALTER TABLE `products` ADD COLUMN `is_hidden` tinyint(1) NOT NULL DEFAULT 0 AFTER `stock_quantity`;
