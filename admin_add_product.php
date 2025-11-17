<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>


<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: adminlogin.php");
    exit;
}

// Connect to DB
$conn = new mysqli("localhost", "root", "root", "honey_art_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle save (insert/update)
if (isset($_POST['save'])) {
    $id = $_POST['id'];
    $name = $conn->real_escape_string($_POST['name']);
    $description = $conn->real_escape_string($_POST['description']);
    $price = $_POST['price'];
    $stock_quantity = $_POST['stock_quantity'];
    $category = $conn->real_escape_string($_POST['category']);
    $weight_kg = $_POST['weight_kg'];

    $image_data = null;
    $image_type = null;

    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $image_data = file_get_contents($_FILES['image_file']['tmp_name']);
        $image_type = $_FILES['image_file']['type'];
    }

    if ($id) {
        // UPDATE
        if ($image_data !== null) {
            $stmt = $conn->prepare("
                UPDATE products
                SET name=?, description=?, price=?, stock_quantity=?, category=?, weight_kg=?, image_data=?, image_type=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "ssdisdbsi",
                $name,
                $description,
                $price,
                $stock_quantity,
                $category,
                $weight_kg,
                $image_data,
                $image_type,
                $id
            );
            $stmt->send_long_data(6, $image_data);
        } else {
            $stmt = $conn->prepare("
                UPDATE products
                SET name=?, description=?, price=?, stock_quantity=?, category=?, weight_kg=?
                WHERE id=?
            ");
            $stmt->bind_param(
                "ssdisdi",
                $name,
                $description,
                $price,
                $stock_quantity,
                $category,
                $weight_kg,
                $id
            );
        }
        $stmt->execute();
    } else {
        // INSERT
        $stmt = $conn->prepare("
            INSERT INTO products (name, description, price, stock_quantity, category, weight_kg, image_data, image_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssdisdbs",
            $name,
            $description,
            $price,
            $stock_quantity,
            $category,
            $weight_kg,
            $image_data,
            $image_type
        );
        $stmt->send_long_data(6, $image_data);
        $stmt->execute();
        $id = $conn->insert_id; // New product ID
    }

    // Save extra images
    if (isset($_FILES['additional_images'])) {
        $count = count($_FILES['additional_images']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['additional_images']['error'][$i] === 0) {
                $imgData = file_get_contents($_FILES['additional_images']['tmp_name'][$i]);
                $imgType = $_FILES['additional_images']['type'][$i];

                $stmt = $conn->prepare("
                    INSERT INTO product_images (product_id, image_data, image_type)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("iss", $id, $imgData, $imgType);
                $stmt->send_long_data(1, $imgData);
                $stmt->execute();
            }
        }
    }

    header("Location: admin_add_product.php");
    exit;
}

// Handle delete product
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM products WHERE id=$id");
    header("Location: admin_add_product.php");
    exit;
}

// Handle edit
$product = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $res = $conn->query("SELECT * FROM products WHERE id=$id");
    $product = $res->fetch_assoc();
}

// Fetch all products
$result = $conn->query("SELECT * FROM products ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
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
    </style>
</head>
<body>

<h1>Admin Panel - HoneyArt</h1>
<p><a href="logout.php">Logout</a> | <a href="admin_add_blog.php">Add blog</a></p>

<h2><?php echo $product ? "Edit Product" : "Add New Product"; ?></h2>

<form action="admin_add_product.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?php echo $product['id'] ?? ''; ?>">
    <input type="text" name="name" placeholder="Product Name" required value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>">
    <textarea name="description" placeholder="Description"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
    <input type="number" step="0.01" name="price" placeholder="Price" required value="<?php echo $product['price'] ?? ''; ?>">
    <input type="number" name="stock_quantity" placeholder="Stock Quantity" value="<?php echo $product['stock_quantity'] ?? ''; ?>">
    <input type="number" step="0.01" name="weight_kg" placeholder="Weight (kg)" value="<?php echo $product['weight_kg'] ?? ''; ?>">
    <input type="text" name="category" placeholder="Category" value="<?php echo htmlspecialchars($product['category'] ?? ''); ?>">

    <p>Main Image:</p>
    <input type="file" name="image_file" accept="image/*" />

    <p>Additional Images:</p>
    <input type="file" name="additional_images[]" accept="image/*" multiple />

    <?php if (!empty($product["id"])): ?>
        <?php if (!empty($product["image_data"])): ?>
            <p>Current Main Image:</p>
            <img src="image.php?id=<?php echo $product["id"]; ?>" class="thumbnail" />
        <?php endif; ?>

        <p>Other Images:</p>
        <?php
        $pid = $product["id"];
        $imgs = $conn->query("SELECT id FROM product_images WHERE product_id = $pid");
        while ($img = $imgs->fetch_assoc()):
        ?>
            <div style="display:inline-block;">
                <img src="extra_image.php?id=<?php echo $img["id"]; ?>" class="thumbnail" />
                <br />
                <a href="delete_image.php?id=<?php echo $img["id"]; ?>&pid=<?php echo $pid; ?>" onclick="return confirm('Delete this image?')">Delete</a>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>

    <br><br>
    <button type="submit" name="save"><?php echo $product ? "Update Product" : "Add Product"; ?></button>
</form>

<h2>Products</h2>

<?php if ($result->num_rows > 0): ?>
    <table>
        <tr>
            <th>Image</th>
            <th>Name</th>
            <th>Weight (kg)</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Category</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td>
                    <?php if (!empty($row["image_data"])): ?>
                        <img src="image.php?id=<?php echo $row["id"]; ?>" class="thumbnail" />
                    <?php else: ?>
                        No Image
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($row["name"]); ?></td>
                <td><?php echo htmlspecialchars($row["weight_kg"]); ?></td>
                <td><?php echo htmlspecialchars($row["price"]); ?></td>
                <td><?php echo htmlspecialchars($row["stock_quantity"]); ?></td>
                <td><?php echo htmlspecialchars($row["category"]); ?></td>
                <td>
                    <a href="admin_add_product.php?edit=<?php echo $row["id"]; ?>">Edit</a> |
                    <a href="admin_add_product.php?delete=<?php echo $row["id"]; ?>" onclick="return confirm('Delete this product?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
<?php else: ?>
    <p>No products found.</p>
<?php endif; ?>

</body>
</html>
