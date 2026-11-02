<?php
// Database connection
require_once __DIR__ . '/db.php';

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $notFoundMessage = 'Article not found';
    include __DIR__ . '/not_found.php';
}

$id = intval($_GET['id']);

// Fetch main product info
$stmt = $conn->prepare("SELECT id, title, content FROM blog_posts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    $notFoundMessage = 'Article not found';
    include __DIR__ . '/not_found.php';
}


$pageTitle = $post['title'];
include('header.php');
?>    

<div id="post">
    <?php
        // Content is HTML written by the admin in TinyMCE, stored as-is
        echo $post['content'];
    ?>
</div>


<?php include('footer.php');
