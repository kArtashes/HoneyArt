<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$user_id = $_SESSION['user_id'] ?? 0;

// ?q=... comes from the search bar (Enter / "See all results")
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$searchLike = '%' . addcslashes($searchQuery, '%_\\') . '%';

$stmt = $conn->prepare("
    SELECT 
        p.*,
        w.id AS wishlist_id
    FROM products p
    LEFT JOIN wishlist w 
        ON p.id = w.product_id
        AND w.user_id = ?
    WHERE ? = '' OR p.name LIKE ? OR p.category LIKE ?
    ORDER BY p.created_at DESC
");

$stmt->bind_param("isss", $user_id, $searchQuery, $searchLike, $searchLike);
$stmt->execute();

$result = $stmt->get_result();

$pageTitle = $searchQuery === '' ? 'All Products' : 'Search: ' . $searchQuery;
include('header.php'); ?>

<div id="product_general_image">
    <img src="./images/products_general1.webp" alt="">
    <div>
        <span><?php echo $searchQuery === '' ? 'All Products' : 'Search results'; ?></span>
    </div>
</div>



<?php if ($searchQuery !== '' && $result->num_rows > 0): ?>
    <div class="search-summary">
        <span>
            <?php echo $result->num_rows; ?> result<?php echo $result->num_rows === 1 ? '' : 's'; ?>
            for “<?php echo htmlspecialchars($searchQuery); ?>”
        </span>
        <a href="products.php">Show all products</a>
    </div>
<?php endif; ?>

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
                        <img src="images/default.webp" alt="No Image">
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
            <?php if ($searchQuery !== ''): ?>
                <p class="products-empty">No products match “<?php echo htmlspecialchars($searchQuery); ?>”. Try another word, or <a href="products.php">see all products</a>.</p>
            <?php else: ?>
                <p class="products-empty">No products found.</p>
            <?php endif; ?>
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
