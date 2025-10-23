<?php
$conn = new mysqli("localhost", "root", "root", "honey_art_db", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
?>

<?php include('header.php'); ?>
<div id="product_general_image">
    <img src="./images/products_general.png" alt="">
    <div>
        <span>All Products</span>
    </div>
</div>
<div id="products">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="product" onclick="window.location.href='product.php?id=<?php echo $row['id']; ?>'">
                    <?php if (!empty($row["image_data"])): ?>
                        <img src="image.php?id=<?php echo $row['id']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                    <?php else: ?>
                        <img src="default.png" alt="No Image">
                    <?php endif; ?>
                    <h2><?php echo htmlspecialchars($row['name']); ?></h2>
                    <div class="price">$<?php echo number_format($row['price'], 0); ?></div>
                    <form method="POST" action="add_to_cart.php">
                        <input type="hidden" name="product_id" value="<?php echo $row['id'] ?>">
                        <button type="submit">Quick add</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No products found.</p>
        <?php endif; ?>
</div>

<?php include('footer.php'); ?>
