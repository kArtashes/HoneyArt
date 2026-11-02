<?php

require_once __DIR__ . '/auth.php';
header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "message" => "You must be logged in."
    ]);
    exit;
}


$user_id = (int) $_SESSION['user_id'];
$product_id = (int) requestString($_POST, 'product_id');

$stmt = $conn->prepare('SELECT 1 FROM products WHERE id = ? AND is_hidden = 0');
$stmt->bind_param('i', $product_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid product."
    ]);
    exit;
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