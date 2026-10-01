<?php
$conn = new mysqli("localhost", "root", "root", "honey_art_db", 8889);

$id = $_POST['id'];
$title = $_POST['title'];
$content = $_POST['content'];

$image_sql = "";

// Check if new image uploaded
if (!empty($_FILES['image']['name'])) {
    $image_name = time() . "_" . $_FILES['image']['name'];
    move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $image_name);
    $image_sql = ", image='$image_name'";
}

// Update query
$sql = "UPDATE blog_posts SET 
        title='$title', 
        content='$content'
        $image_sql
        WHERE id=$id";

$conn->query($sql);

// Go back to admin panel
header("Location: admin_add_blog.php?updated=1");
exit();
