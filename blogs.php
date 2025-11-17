<?php
include('db.php');

$result = $conn->query("SELECT * FROM blog_posts ORDER BY created_at DESC");


include('header.php'); 
?>


<div id="post_general_image">
    <img src="./images/post_general.png" alt="">
    <div>
        <span>Blog page</span>
    </div>
</div>
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
                    <p><?php echo htmlspecialchars($row['description'])?></p>
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

<?php include('footer.php'); ?>
