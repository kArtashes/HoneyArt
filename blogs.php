<?php
require_once __DIR__ . '/db.php';

$result = $conn->query("SELECT id, title, description, (image_data IS NOT NULL) AS has_image FROM blog_posts ORDER BY created_at DESC");


$pageTitle = 'Blog';
include('header.php'); 
?>


<div id="post_general_image">
    <img src="./images/blog-head-image.jpg" alt="">
    <div>
        <span>Blog page</span>
    </div>
</div>
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
                    <p><?php echo htmlspecialchars($row['description'])?></p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No posts found.</p>
        <?php endif; ?>
</div>

<?php include('footer.php'); ?>
