<?php
require_once __DIR__ . '/auth.php';

if (!isset($_SESSION['user_id'])) {
    echo '<p>Your basket is empty. Please log in to see your items.</p>';
} else {
    $user_id = (int) $_SESSION['user_id'];

    $basket = $conn->query("SELECT products.id, cart.product_id, cart.quantity, products.name, products.price, products.is_hidden,
        (products.image_data IS NOT NULL) AS has_image
    FROM cart
    INNER JOIN products ON cart.product_id = products.id
    WHERE cart.user_id=$user_id
    ORDER BY cart.created_at; ");
    ?>


    <div id="basket-products">
            <?php if ($basket->num_rows > 0): ?>
                <?php while ($row = $basket->fetch_assoc()): ?>
                    <div class="basket-item">
                        
                        <div class="item-details" data-id="<?php echo $row['id']; ?>">
                            <div id="basket_item_image">
                                <?php if (!empty($row["has_image"])): ?>
                                    <a href="product.php?id=<?php echo (int) $row['id']; ?>"><img loading="lazy" src="image.php?id=<?php echo $row['product_id']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>"></a>
                                <?php else: ?>
                                    <a href="product.php?id=<?php echo (int) $row['id']; ?>"><img src="./images/default.webp" alt="No Image"></a>
                                <?php endif; ?>
                            </div>
                            <div id="basket_item_det">
                                <div class="item-name"><?php echo htmlspecialchars($row['name']); ?></div>
                                <?php if ((int) $row['is_hidden'] === 1): ?>
                                    <div class="item-unavailable">No longer available</div>
                                <?php endif; ?>
                                <div class="item-price">֏<?php echo number_format($row['price'], 0); ?></div>
                                <div class="item-qty">
                                    <button class="qty-btn decrease-btn">−</button>
                                    <span class="qty-value"><?php echo $row['quantity']; ?></span>
                                    <button class="qty-btn increase-btn">+</button>
                                </div>
                                <button class="remove-btn">Remove</button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No products found.</p>
            <?php endif; ?>
    </div>
<?php

}
