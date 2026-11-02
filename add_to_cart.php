<?php
require_once __DIR__ . '/auth.php';

// The basket script asks with this header and gets JSON; a plain form post gets a redirect (still works without JS)
$wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

// Ստուգում ենք՝ մուտք գործած է, թե ոչ
if (!isset($_SESSION['user_id'])) {
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['login_required' => true]);
        exit();
    }
    // Եթե չէ՝ հետ ենք ուղարկում login էջ
    header('Location: logInReg.php?error=login_required');
    exit();
}

$product_id = (int) requestString($_POST, 'product_id');
$user_id = (int) $_SESSION['user_id'];

// Back to the page the customer came from, only if it is on this site
function backToShop(): string
{
    $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '');
    $thisHost = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    if (($referer['host'] ?? '') !== $thisHost || empty($referer['path'])) {
        return 'products.php';
    }

    return $referer['path'] . (isset($referer['query']) ? '?' . $referer['query'] : '');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $product_id < 1) {
    header('Location: products.php');
    exit();
}

// Ստուգում ենք՝ այդպիսի ապրանք գոյություն ունի՞, և քանի՞ հատ կա պահեստում
$stmt = $conn->prepare(
    'SELECT p.stock_quantity, COALESCE(c.quantity, 0) AS in_cart
     FROM products p
     LEFT JOIN cart c ON c.product_id = p.id AND c.user_id = ?
     WHERE p.id = ? AND p.is_hidden = 0'
);
$stmt->bind_param('ii', $user_id, $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    if ($wantsJson) {
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Sorry, this product is not available.']);
        exit();
    }
    header('Location: products.php');
    exit();
}

$stock = (int) $product['stock_quantity'];

if ((int) $product['in_cart'] + 1 > $stock) {
    $message = $stock > 0
        ? 'Sorry, only ' . $stock . ' left in stock.'
        : 'Sorry, this product is out of stock.';
} else {
    // Չկա՝ ավելացնում ենք, արդեն կա՝ մեծացնում ենք քանակը
    $stmt = $conn->prepare(
        'INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE quantity = quantity + 1'
    );
    $stmt->bind_param('ii', $user_id, $product_id);
    $stmt->execute();
    $stmt->close();

    $message = 'Added to your basket ✓';
}

if ($wantsJson) {
    $stmt = $conn->prepare(
        'SELECT COALESCE(SUM(cart.quantity), 0) AS cart_count,
                COALESCE(SUM(cart.quantity * products.price), 0) AS total_price
         FROM cart
         INNER JOIN products ON cart.product_id = products.id
         WHERE cart.user_id = ?'
    );
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // The same list the basket panel shows on a normal page load
    ob_start();
    include __DIR__ . '/cart.php';
    $basketHtml = ob_get_clean();

    header('Content-Type: application/json');
    echo json_encode([
        'message' => $message,
        'cart_count' => (int) $totals['cart_count'],
        'total_price' => (int) $totals['total_price'],
        'basket_html' => $basketHtml,
    ]);
    exit();
}

// Վերադարձ կայք, header.php-ն մեկ անգամ ցույց կտա հաղորդագրությունը
$_SESSION['basket_flash'] = $message;
header('Location: ' . backToShop());
exit();
