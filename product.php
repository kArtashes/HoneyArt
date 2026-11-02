<?php
require_once __DIR__ . '/auth.php';



// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $notFoundMessage = 'Product not found';
    include __DIR__ . '/not_found.php';
}

$id = intval($_GET['id']);

// Fetch main product info
$stmt = $conn->prepare("SELECT id, name, price, description, stock_quantity, is_hidden FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

// A hidden product doesn't exist for customers; the admin can still open it
$isHidden = $product && (int) $product['is_hidden'] === 1;
$adminPreview = $isHidden && ($_SESSION['loggedin'] ?? false) === true;

if (!$product || ($isHidden && !$adminPreview)) {
    $notFoundMessage = 'Product not found';
    include __DIR__ . '/not_found.php';
}

// Fetch additional images (exclude duplicates)
$imagesResult = $conn->query("SELECT id FROM product_images WHERE product_id = $id");
$extraImages = [];
while ($row = $imagesResult->fetch_assoc()) {
    $extraImages[] = $row['id']; // store only IDs of extra images
}
$pageTitle = $product['name'];
include('header.php');
function isRecentlyViewed(mysqli $conn, int $userId, int $productId): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM recently_viewed
        WHERE user_id = ? AND product_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("ii", $userId, $productId);
    $stmt->execute();
    $stmt->store_result();

    return $stmt->num_rows > 0;
}

function addRecentlyViewed(mysqli $conn, int $userId, int $productId): bool
{
    $sql = "
        INSERT INTO recently_viewed (user_id, product_id, viewed_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            viewed_at = NOW()
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $productId);

    return $stmt->execute();
}
// Recently viewed is tracked only for logged-in users
$userId = (int) ($_SESSION['user_id'] ?? 0);
$productId = (int) $product['id'];

if ($userId > 0 && !$isHidden) {
    if(isRecentlyViewed($conn, $userId, $productId)){
        $sql = "
            UPDATE recently_viewed
            SET viewed_at = NOW()
            WHERE user_id = ? AND product_id = ?
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $userId, $productId);

        $stmt->execute();
    }
    else{
        addRecentlyViewed($conn, $userId, $productId);
    }
}

?>    
<div id="product">
    <div id="product-photos">
        <div id="slider">
            <button class="nav-btn left" onclick="prevImage()">&#10094;</button>
            <img id="mainImage" src="image.php?id=<?php echo $product['id']; ?>" alt="Product Image">
            <button class="nav-btn right" onclick="nextImage()">&#10095;</button>
        </div>
        <div class="thumbnails">
            <img src="image.php?id=<?php echo $product['id']; ?>" class="active" onclick="showImage(0)">
            <?php foreach ($extraImages as $index => $imgId): ?>
                <img src="image_secondary.php?id=<?php echo $imgId; ?>" onclick="showImage(<?php echo $index + 1; ?>)">
            <?php endforeach; ?>
        </div>
    </div>
    <div id="product-details">
        <p><?php echo htmlspecialchars($product['name']); ?></p>

        <div id="price-add">
            <div class="price">֏<?php echo number_format($product['price'], 0); ?></div>
            <?php if ($isHidden): ?>
            <div class="out-of-stock" id="product-out-of-stock">Hidden from the shop</div>
            <?php elseif ((int) $product['stock_quantity'] > 0): ?>
            <form method="POST" action="add_to_cart.php">
                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                <button type="submit" id="product-add">Add to basket</button>
            </form>
            <?php else: ?>
            <div class="out-of-stock" id="product-out-of-stock">Out of stock</div>
            <?php endif; ?>
        </div>
        <div class="description"><?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?></div>
    </div>
</div>

<script>
    const images = [
        "image.php?id=<?php echo $product['id']; ?>",
        <?php foreach ($extraImages as $imgId): ?>
        "image_secondary.php?id=<?php echo $imgId; ?>",
        <?php endforeach; ?>
    ];
    let currentIndex = 0;

    function showImage(index) {
        currentIndex = index;
        document.getElementById('mainImage').src = images[currentIndex];
        document.querySelectorAll('.thumbnails img').forEach((img, i) => {
            img.classList.toggle('active', i === index);
        });
    }

    function nextImage() {
        currentIndex = (currentIndex + 1) % images.length;
        showImage(currentIndex);
    }

    function prevImage() {
        currentIndex = (currentIndex - 1 + images.length) % images.length;
        showImage(currentIndex);
    }

    // ✅ Add swipe functionality for mobile
    const slider = document.getElementById('slider');
    let startX = 0;

    slider.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
    });

    slider.addEventListener('touchend', (e) => {
        let endX = e.changedTouches[0].clientX;
        if (startX - endX > 50) {
            nextImage(); // swipe left → next
        } else if (endX - startX > 50) {
            prevImage(); // swipe right → previous
        }
    });
</script>
<?php include('footer.php');
