<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: adminlogin.php");
    exit;
}
require __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/image_utils.php';


require_once __DIR__ . '/db.php';

$id = isset($_POST['id']) ? (int) $_POST['id'] : null;
$error = '';

if (isset($_POST['save'])) {
    requireAdminCsrf($_POST['csrf_token'] ?? null);

    // Prepared statements handle escaping; escaping here too stored "\" in the posts
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    // Pictures pasted into the editor are converted too
    $content = convertInlineImagesToWebp((string) ($_POST['content'] ?? ''), BLOG_IMAGE_MAX_WIDTH);

    $image_data = null;
    $image_type = null;

    if (!empty($_FILES['image_file']['tmp_name'])) {
        // Stored as a resized WebP; a file that isn't an image is ignored
        $image_data = toWebp(file_get_contents($_FILES['image_file']['tmp_name']), BLOG_IMAGE_MAX_WIDTH);
        $image_type = $image_data === null ? null : 'image/webp';
    }

    if ($id) {

        // UPDATE
        if ($image_data !== null) {

            $stmt = $conn->prepare("
                UPDATE blog_posts
                SET title=?, image_data=?, image_type=?, description=?, content=?
                WHERE id=?
            ");

            $null = NULL;
            $stmt->bind_param("sbsssi", $title, $null, $image_type, $description, $content, $id);
            $stmt->send_long_data(1, $image_data);

        } else {

            $stmt = $conn->prepare("
                UPDATE blog_posts
                SET title=?, description=?, content=?
                WHERE id=?
            ");

            $stmt->bind_param("sssi", $title, $description, $content, $id);
        }

        $stmt->execute();

    } elseif ($image_data === null) {

        $error = 'Please choose a main image for the new post.';

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

    if ($error === '') {
        header("Location: admin_add_blog.php?edit=$id&saved=1");
        exit;
    }
}


// Handle delete product
if (isset($_GET['delete'])) {
    requireAdminCsrf($_GET['token'] ?? null);
    $id = (int) $_GET['delete'];
    $conn->query("DELETE FROM blog_posts WHERE id=$id");
    header("Location: admin_add_blog.php");
    exit;
}

// Handle edit
$product = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
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
        .message {
            padding: 12px 14px;
            border-radius: 5px;
        }
        .message.success { background: #e6f4ea; color: #217a3c; }
        .message.error { background: #fde7e7; color: #b42318; }

        /* Inputs include their padding, so they don't stick out of the form */
        form input, form textarea {
            box-sizing: border-box;
        }

        /* ---------- Phones & small tablets ---------- */
        @media (max-width: 767px) {
            body {
                margin: 12px;
            }
            h1 {
                font-size: 26px;
            }
            form {
                padding: 14px;
            }
            /* The list scrolls sideways inside its box instead of widening the page */
            table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            table th, table td {
                padding: 8px;
                min-width: 90px;
            }
            img.thumbnail {
                max-width: 64px;
                max-height: 64px;
            }
        }
    </style>    <script src="https://cdn.jsdelivr.net/npm/tinymce@7.9.1/tinymce.min.js"></script>
    <script>
      tinymce.init({
        selector: '#mytextarea',
        license_key: 'gpl',
        promotion: false,
        plugins: 'table lists link image code',
        toolbar: 'undo redo | bold italic | table tablecellprops tableprops | alignleft aligncenter alignright | code | image',
        menubar: 'file edit insert view format table tools help',
        image_dimensions: true,
        image_caption: true,
        object_resizing: true,
        automatic_uploads: false
    });
    </script>
</head>
<body> 
    <h1>Admin Panel - HoneyArt</h1>
    <p><a href="adminlogout.php">Logout</a> | <a href="admin_add_product.php">Add product</a> | <a href="admin_orders.php">Orders</a></p>


    <?php if ($error !== ''): ?>
        <p class="message error"><?php echo htmlspecialchars($error); ?></p>
    <?php elseif (isset($_GET['saved'])): ?>
        <p class="message success">Post saved.</p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken()); ?>">
    <input type="hidden" name="id" value="<?php echo (int) ($post['id'] ?? 0) ?: ''; ?>">

    <p>Title:</p>
    <input name="title" type="text" required value="<?php echo htmlspecialchars($post['title'] ?? $_POST['title'] ?? ''); ?>">

    <p>Main Image:</p>
    <?php if (!empty($post['image_data'])): ?>
        <img src="post_image.php?id=<?php echo $post['id']; ?>" class="thumbnail" />
        <br><br>
    <?php endif; ?>
    <input type="file" name="image_file" accept="image/*" />

    <p>Description:</p>
    <input type="text" name="description" value="<?php echo htmlspecialchars($post['description'] ?? $_POST['description'] ?? ''); ?>">

    <p>Content:</p>
    <textarea id="mytextarea" name="content"><?php echo htmlspecialchars($post['content'] ?? $_POST['content'] ?? ''); ?></textarea>

    <button type="submit" name="save">
        <?php echo isset($post) ? "Update" : "Create"; ?>
    </button>
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
                        <a href="admin_add_blog.php?delete=<?php echo (int) $row["id"]; ?>&token=<?php echo urlencode(adminCsrfToken()); ?>" onclick="return confirm('Delete this post?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No posts found.</p>
    <?php endif; ?>
</body>
</html>