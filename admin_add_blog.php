<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: adminlogin.php");
    exit;
}


$conn = new mysqli("localhost", "root", "root", "honey_art_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = isset($_POST['id']) ? $_POST['id'] : null;

if (isset($_POST['save'])) {

    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $content = $conn->real_escape_string($_POST['content']);

    $image_data = null;
    $image_type = null;

    if (!empty($_FILES['image_file']['tmp_name'])) {
        $image_data = file_get_contents($_FILES['image_file']['tmp_name']);
        $image_type = $_FILES['image_file']['type'];
    }

    if ($id) {

        // UPDATE
        if ($image_data !== null) {

            $stmt = $conn->prepare("
                UPDATE blog_posts
                SET title=?, image_data=?, image_type=?, description=?, content=?
                WHERE id=?
            ");

            $stmt->bind_param("bsssi", $image_data, $image_type, $description, $content, $id);

        } else {

            $stmt = $conn->prepare("
                UPDATE blog_posts
                SET title=?, description=?, content=?
                WHERE id=?
            ");

            $stmt->bind_param("bsssi", $title, $description, $content, $id);
        }

        $stmt->execute();

    } else {

        // INSERT
        $stmt = $conn->prepare("
            INSERT INTO blog_posts (title, image_data, image_type, description, content)
            VALUES (?, ?, ?, ?, ?)
        ");

        $null = NULL;

        $stmt->bind_param("sbsss", $title, $null, $image_type, $description, $content);
        $stmt->send_long_data(1, $image_data);

        $stmt->execute();
        $id = $conn->insert_id;
    }
}


// Handle delete product
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM blog_posts WHERE id=$id");
    header("Location: admin_add_blog.php");
    exit;
}

// Handle edit
$product = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $res = $conn->query("SELECT * FROM blog_posts WHERE id=$id");
    $post = $res->fetch_assoc();
}



$result = $conn->query("SELECT * FROM blog_posts ORDER BY created_at DESC");

?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - HoneyArt</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
            background: #f8f8f8;
        }
        h1 {
            color: #d38f12;
        }
        form {
            background: #fff;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        form input, form textarea {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px 0;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
        }
        table th, table td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }
        button {
            background: #d38f12;
            color: #fff;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 3px;
        }
        button:hover {
            background: #b3740d;
        }
        img.thumbnail {
            max-width: 100px;
            max-height: 100px;
        }
        a {
            color: #d38f12;
            text-decoration: none;
        }
    </style>    <script src="https://cdn.tiny.cloud/1/4o28q5yinxxj89omjagikuje2yqwox9z2cbv1gssc04eaa82/tinymce/8/tinymce.min.js" referrerpolicy="origin" crossorigin="anonymous"></script>
    <script>
      tinymce.init({
        selector: '#mytextarea',
        plugins: 'table lists link image code',
        toolbar: 'undo redo | bold italic | table tablecellprops tableprops | alignleft aligncenter alignright | code',
        menubar: 'file edit insert view format table tools help'
    });
    </script>
</head>
<body> 
    <h1>Admin Panel - HoneyArt</h1>
    <p><a href="logout.php">Logout</a> | <a href="admin_add_product.php">Add product</a></p>


    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo $product['id'] ?? ''; ?>">
        <p>Title:</p>
        <input name="title" type="text">
        <p>Main Image:</p>
        <input type="file" name="image_file" accept="image/*" />
        <p>Description: </p>
        <input type="text" name="description">
        <div>
            <textarea id="mytextarea" name="content"></textarea>
        </div>

        <button type="submit" name="save">Send</button>

    </form>

    <h2>Posts</h2>

    <?php if ($result->num_rows > 0): ?>
        <table>
            <tr>
                <th>Image</th>
                <th>Title</th>
                <th>Description</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php if (!empty($row["image_data"])): ?>
                            <img src="post_image.php?id=<?php echo $row["id"]; ?>" class="thumbnail" />
                        <?php else: ?>
                            No Image
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row["title"]); ?></td>
                    <td><?php echo htmlspecialchars($row["description"]); ?></td>
                    <td>
                        <a href="admin_add_blog.php?edit=<?php echo $row["id"]; ?>">Edit</a> |
                        <a href="admin_add_blog.php?delete=<?php echo $row["id"]; ?>" onclick="return confirm('Delete this post?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No posts found.</p>
    <?php endif; ?>
</body>
</html>