<?php
$conn = new mysqli("localhost", "root", "root", "honey_art_db");
$id = intval($_GET['id']);
$res = $conn->query("SELECT image_data, image_type FROM product_images WHERE id=$id");
if ($row = $res->fetch_assoc()) {
    header("Content-Type: " . $row["image_type"]);
    echo $row["image_data"];
}
?>
