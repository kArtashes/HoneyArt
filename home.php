<?php include 'header.php'; ?>

<?php
    $conn = new mysqli("localhost", "root", "root", "honey_art_db", 8889);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $result = $conn->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 3");
?>


<section class="hero">
    <?php include('slider_home.php'); ?>
    <div class="hero-content">
        <h1>Welcome to HoneyArt</h1>
        <p>Pure. Natural. Armenian Honey.</p>
    </div>
</section>

<section class="featured-products">
    <h2>Featured Products</h2>
<div id="products" style="flex-wrap: nowrap;">
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
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No products found.</p>
        <?php endif; ?>
</div>
</section>

<section class="latest-posts">
    <h2>Latest Posts</h2>

    <?php $result = $conn->query("SELECT * FROM blog_posts ORDER BY created_at DESC LIMIT 3");?>
<div id="posts">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="post" onclick="window.location.href='blog.php?id=<?php echo $row['id']; ?>'">
                    <?php if (!empty($row["image_data"])): ?>
                        <img src="post_image.php?id=<?php echo $row['id']; ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
                    <?php else: ?>
                        <img src="default.png" alt="No Image">
                    <?php endif; ?>
                    <h2><?php echo htmlspecialchars($row['title']); ?></h2>
                    <form method="POST">
                        <input type="hidden" name="post_id" value="<?php echo $row['id'] ?>">
                        <button type="submit">See more</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No posts found.</p>
        <?php endif; ?>
</div>
</section>

<?php include 'footer.php'; ?>

<link rel="stylesheet" href="home.css">