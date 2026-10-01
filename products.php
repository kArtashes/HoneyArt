<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli("localhost", "root", "root", "honey_art_db", 8889);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'] ?? 0;

$stmt = $conn->prepare("
    SELECT 
        p.*,
        w.id AS wishlist_id
    FROM products p
    LEFT JOIN wishlist w 
        ON p.id = w.product_id
        AND w.user_id = ?
    ORDER BY p.created_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

include('header.php'); ?>

<div id="product_general_image">
    <img src="./images/products_general1.webp" alt="">
    <div>
        <span>All Products</span>
    </div>
</div>



<div id="products">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="product" onclick="window.location.href='product.php?id=<?php echo $row['id']; ?>'">
                <button 
                    type="button" 
                    class="wishlist-btn <?= $row['wishlist_id'] ? 'active' : '' ?>"
                    data-product-id="<?= $row['id'] ?>"
                >
                    <?= $row['wishlist_id'] ? '♥' : '♡' ?>
                </button>
                    <?php
                    if (!empty($row["image_data"])): ?>
                        <img src="image.php?id=<?php echo $row['id']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                    <?php else: ?>
                        <img src="default.png" alt="No Image">
                    <?php endif; ?>
                    <?php
                    $weight = $row['weight_kg'];

                    if (floor($weight) == $weight) {
                        // Integer → no decimals
                        $formatted = number_format($weight, 0);
                    } else {
                        // Float → 1 decimal
                        $formatted = number_format($weight, 1);
                    }?>
                    <h2><?php echo htmlspecialchars($row['name']) ." ". $formatted . " kg" ?></h2>
                    <div class="price">֏<?php echo number_format($row['price'], 0); ?></div>
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

<script>
    document.querySelectorAll(".wishlist-btn").forEach(button => {

    button.addEventListener("click", function(event) {

        event.preventDefault();
        event.stopPropagation();

        const productId = this.dataset.productId;

        fetch("wishlist.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "product_id=" + encodeURIComponent(productId)
        })
        .then(response => response.json())
        .then(data => {

            if (data.success) {

                if (data.action === "added") {
                    this.textContent = "♥";
                    this.classList.add("active");
                } 
                else if (data.action === "removed") {
                    this.textContent = "♡";
                    this.classList.remove("active");
                }

            } else {
                alert(data.message);
            }

        })
        .catch(error => {
            console.error(error);
        });

    });

});
</script>

<?php include('footer.php'); ?>
