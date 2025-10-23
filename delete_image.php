<?php
$conn = new mysqli("localhost", "root", "root", "honey_art_db");
$id = intval($_GET['id']);
$pid = intval($_GET['pid']);
$conn->query("DELETE FROM product_images WHERE id=$id");
header("Location: admin.php?edit=$pid");
?>
