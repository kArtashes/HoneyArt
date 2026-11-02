<?php
$pageTitle = '';
$pageStyles = ['home.css'];
include 'header.php';
?>

<?php
    require_once __DIR__ . '/db.php';

    $result = $conn->query("SELECT id, name, price, (image_data IS NOT NULL) AS has_image FROM products WHERE is_hidden = 0 ORDER BY created_at DESC LIMIT 3");
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
<div id="products">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="product">
                    <?php if (!empty($row["has_image"])): ?>
                        <img loading="lazy" src="image.php?id=<?php echo $row['id']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                    <?php else: ?>
                        <img src="images/default.webp" alt="No Image">
                    <?php endif; ?>
                    <h2><a class="card-link" href="product.php?id=<?php echo (int) $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></a></h2>
                    <div class="price">֏<?php echo number_format($row['price'], 0); ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No products found.</p>
        <?php endif; ?>
</div>
</section>

<section class="latest-posts">
    <h2>Latest Posts</h2>

    <?php $result = $conn->query("SELECT id, title, (image_data IS NOT NULL) AS has_image FROM blog_posts ORDER BY created_at DESC LIMIT 3");?>
<div id="posts">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="post">
                    <?php if (!empty($row["has_image"])): ?>
                        <img loading="lazy" src="post_image.php?id=<?php echo $row['id']; ?>" alt="<?php echo htmlspecialchars($row['title']); ?>">
                    <?php else: ?>
                        <img src="images/default.webp" alt="No Image">
                    <?php endif; ?>
                    <h2><a class="card-link" href="blog.php?id=<?php echo (int) $row['id']; ?>"><?php echo htmlspecialchars($row['title']); ?></a></h2>
                    <a class="see-more" href="blog.php?id=<?php echo (int) $row['id']; ?>" tabindex="-1" aria-hidden="true">See more</a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No posts found.</p>
        <?php endif; ?>
</div>
</section>

<?php include 'footer.php'; ?>
