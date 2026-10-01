<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "You must be logged in."
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'] ?? null;

if (!$product_id) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid product."
    ]);
    exit;
}


$conn = new mysqli("localhost", "root", "root", "honey_art_db", 8889);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare(
    "SELECT id FROM wishlist 
     WHERE user_id = ? AND product_id = ?"
);

$stmt->bind_param("ii", $user_id, $product_id);
$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows > 0) {

    $stmt = $conn->prepare(
        "DELETE FROM wishlist
         WHERE user_id = ? AND product_id = ?"
    );
    
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    
    echo json_encode([
        "success" => true,
        "action" => "removed"
    ]);
} else {

    $stmt = $conn->prepare(
        "INSERT INTO wishlist (user_id, product_id)
         VALUES (?, ?)"
    );
    
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    
    echo json_encode([
        "success" => true,
        "action" => "added"
    ]);

}