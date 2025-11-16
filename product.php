<?php
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
include('header.php');
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
            <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
            <form method="POST" action="add_to_cart.php">
                <input type="hidden" name="product_id" value="<?php echo $_GET['id'] ?>">
                <button type="submit" id="product-add">Quick add</button>
            </form>
        </div>


        <div class="description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></div>
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
