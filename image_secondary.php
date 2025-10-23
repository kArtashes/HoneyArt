<?php
$conn = new mysqli( "localhost", "root", "root", "honey_art_db");
$id = intval($_GET["id"]);

$res = $conn->query("SELECT image_data, image_type FROM product_images WHERE id = $id");
$row = $res->fetch_assoc();

if ($row && !empty($row["image_data"])) {
    header("Content-Type: ".$row["image_type"]);
    echo $row["image_data"];
} else {
    // fallback image if none stored
    header("Content-Type: image/png");
    readfile("default.png");
}
