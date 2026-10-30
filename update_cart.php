<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Database connection
require_once __DIR__ . '/db.php';

$user_id = (int) ($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    echo json_encode(['quantity'=>0,'cart_count'=>0,'total_price'=>0]);
    exit;
}

$product_id = (int) ($_POST['product_id'] ?? 0);
$action = $_POST['action'] ?? '';

// Runs a prepared statement with integer parameters and returns its result (or null)
function cartQuery(mysqli $conn, string $sql, int ...$params): ?mysqli_result
{
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($params)), ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return $result ?: null;
}

if ($action === 'increase') {
    cartQuery($conn, "UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND product_id = ?", $user_id, $product_id);
} elseif ($action === 'decrease') {
    cartQuery($conn, "UPDATE cart SET quantity = quantity - 1 WHERE user_id = ? AND product_id = ?", $user_id, $product_id);
    cartQuery($conn, "DELETE FROM cart WHERE user_id = ? AND product_id = ? AND quantity <= 0", $user_id, $product_id);
} elseif ($action === 'remove') {
    cartQuery($conn, "DELETE FROM cart WHERE user_id = ? AND product_id = ?", $user_id, $product_id);
}

// Updated quantity
$row = cartQuery($conn, "SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?", $user_id, $product_id)->fetch_assoc();
$quantity = (int) ($row['quantity'] ?? 0);

// Total cart count
$row = cartQuery($conn, "SELECT SUM(quantity) AS total FROM cart WHERE user_id = ?", $user_id)->fetch_assoc();
$cart_count = (int) ($row['total'] ?? 0);

// Total cart price
$row = cartQuery($conn, "
    SELECT SUM(products.price * cart.quantity) AS total_price
    FROM cart
    INNER JOIN products ON cart.product_id = products.id
    WHERE cart.user_id = ?
", $user_id)->fetch_assoc();
$total_price = (int) ($row['total_price'] ?? 0);

// Return JSON
echo json_encode([
    'quantity'    => $quantity,
    'cart_count'  => $cart_count,
    'total_price' => $total_price
]);
exit;
