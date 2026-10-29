-- Orders keep the price paid at checkout, so later product price changes
-- don't rewrite old orders. Existing orders are filled with the current price.
ALTER TABLE `orders` ADD COLUMN `unit_price` int NULL AFTER `quantity`;

UPDATE `orders`
JOIN `products` ON `products`.`id` = `orders`.`product_id`
SET `orders`.`unit_price` = `products`.`price`
WHERE `orders`.`unit_price` IS NULL;

ALTER TABLE `orders` MODIFY `unit_price` int NOT NULL;
