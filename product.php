<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Database connection
$conn = new mysqli("localhost", "root", "root", "honey_art_db", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "No product selected.";
    exit;
}

$id = intval($_GET['id']);

// Fetch main product info
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo "Product not found.";
    exit;
}

// Fetch additional images (exclude duplicates)
$imagesResult = $conn->query("SELECT id FROM product_images WHERE product_id = $id");
$extraImages = [];
while ($row = $imagesResult->fetch_assoc()) {
    $extraImages[] = $row['id']; // store only IDs of extra images
}
echo '<pre>';
var_dump($_SESSION);
echo '</pre>';
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
if(isRecentlyViewed($conn, $_SESSION['user_id'], $product['id'])){
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
    addRecentlyViewed($conn, $_SESSION['user_id'], $product['id']);
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
            <form method="POST" action="add_to_cart.php">
                <input type="hidden" name="product_id" value="<?php echo $_GET['id'] ?>">
                <button type="submit" id="product-add">Add to basket</button>
            </form>
        </div>
        <?php
        $content = $product['description'];
        
        // Replace literal "\r\n" sequences with <br>
        $content = str_replace("\\r\\n", "
        ", $content);
        
        // Optionally replace remaining \r or \n just in case
        $content = str_replace(["\\r","\\n"], "<br>", $content);
        
        // Remove slashes if any (from magic quotes or insert method)
        $content = stripslashes($content);
        
        // Decode HTML entities (if any)
        $content = html_entity_decode($content);

        // Decode HTML entities (if any)
        $content = html_entity_decode($content);
        ?>

        <div class="description"><?php echo nl2br(htmlspecialchars($content)); ?></div>
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
