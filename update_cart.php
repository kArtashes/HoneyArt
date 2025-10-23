<?php
session_start();
header('Content-Type: application/json');

// Database connection
$conn = new mysqli("localhost", "root", "root", "honey_art_db", 8889);
if ($conn->connect_error) {
    echo json_encode(['quantity'=>0, 'cart_count'=>0, 'total_price'=>0, 'error'=>'DB connection failed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['quantity'=>0,'cart_count'=>0,'total_price'=>0]);
    exit;
}

$product_id = intval($_POST['product_id']);
$action = $_POST['action'] ?? '';

if ($action === 'increase') {
    $conn->query("UPDATE cart SET quantity = quantity + 1 WHERE user_id = $user_id AND product_id = $product_id");
} elseif ($action === 'decrease') {
    $conn->query("UPDATE cart SET quantity = quantity - 1 WHERE user_id = $user_id AND product_id = $product_id");
    $conn->query("DELETE FROM cart WHERE user_id = $user_id AND product_id = $product_id AND quantity <= 0");
} elseif ($action === 'remove') {
    $conn->query("DELETE FROM cart WHERE user_id = $user_id AND product_id = $product_id");
}

// Updated quantity
$result = $conn->query("SELECT quantity FROM cart WHERE user_id = $user_id AND product_id = $product_id");
$row = $result->fetch_assoc();
$quantity = $row['quantity'] ?? 0;

// Total cart count
$result = $conn->query("SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id");
$row = $result->fetch_assoc();
$cart_count = $row['total'] ?? 0;

// Total cart price
$result = $conn->query("
    SELECT SUM(products.price * cart.quantity) AS total_price
    FROM cart
    INNER JOIN products ON cart.product_id = products.id
    WHERE cart.user_id = $user_id
");
$row = $result->fetch_assoc();
$total_price = $row['total_price'] !== null ? $row['total_price'] : 0;

// Return JSON
echo json_encode([
    'quantity'    => $quantity,
    'cart_count'  => $cart_count,
    'total_price' => $total_price
]);
exit;
?>
