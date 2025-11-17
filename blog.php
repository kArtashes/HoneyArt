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
$stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    echo "Post not found.";
    exit;
}


include('header.php');
?>    

<div id="post">
    <?php
        $content = $post['content'];
        
        // Replace literal "\r\n" sequences with <br>
        $content = str_replace("\\r\\n", "<br>", $content);
        
        // Optionally replace remaining \r or \n just in case
        $content = str_replace(["\\r","\\n"], "<br>", $content);
        
        // Remove slashes if any (from magic quotes or insert method)
        $content = stripslashes($content);
        
        // Decode HTML entities (if any)
        $content = html_entity_decode($content);
        
        
        // Output the content
        echo $content;
    ?>
</div>


<?php include('footer.php');
