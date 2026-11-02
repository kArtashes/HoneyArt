<?php
// One order of the logged-in customer: products, total, status and delivery address.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/orders_lib.php';

if (($_SESSION['loggedIn'] ?? false) !== true) {
    header('Location: logInReg.php');
    exit;
}

$orderId = (int) requestString($_GET, 'id');
$order = $orderId > 0 ? getOrderDetails($conn, $orderId) : null;

// Someone else's order looks exactly like one that doesn't exist
if (!$order || (int) $order['user_id'] !== (int) $_SESSION['user_id']) {
    $notFoundMessage = 'Order not found';
    include __DIR__ . '/not_found.php';
}

$deliveryLines = orderDeliveryLines($order);

$pageTitle = 'Order #' . (int) $order['id'];
$pageStyles = ['accountstyle.css'];
include 'header.php';
?>
<div class="container account-page">
    <div class="order-detail">
        <a class="order-back" href="account.php?section=orders">← All orders</a>

        <div class="order-detail-head">
            <div>
                <h1>Order #<?php echo (int) $order['id']; ?></h1>
                <p class="order-detail-date"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
            </div>
            <div class="order-status <?php echo orderStatusClass($order['status']); ?>"><?php echo htmlspecialchars($order['status']); ?></div>
        </div>

        <section class="card">
            <h2>Products</h2>
            <?php foreach ($order['items'] as $item): ?>
                <?php $lineTag = (int) $item['is_hidden'] === 1 ? 'div' : 'a'; ?>
                <<?php echo $lineTag; ?> class="order-line"<?php echo $lineTag === 'a' ? ' href="product.php?id=' . (int) $item['product_id'] . '"' : ''; ?>>
                    <img loading="lazy" src="<?php echo !empty($item['has_image']) ? 'image.php?id=' . (int) $item['product_id'] : 'images/default.webp'; ?>" alt="">
                    <div>
                        <div class="order-line-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="order-line-meta"><?php echo (int) $item['quantity']; ?> × ֏<?php echo number_format($item['unit_price'], 0); ?></div>
                    </div>
                    <div class="order-line-total">֏<?php echo number_format($item['unit_price'] * $item['quantity'], 0); ?></div>
                </<?php echo $lineTag; ?>>
            <?php endforeach; ?>
            <div class="order-total-row">
                <span>Total</span>
                <span>֏<?php echo number_format($order['total'], 0); ?></span>
            </div>
        </section>

        <section class="card order-delivery">
            <h2>Delivery address</h2>
            <?php if ($deliveryLines): ?>
                <?php foreach ($deliveryLines as $line): ?>
                    <p><?php echo htmlspecialchars($line); ?></p>
                <?php endforeach; ?>
                <?php if (!empty($order['delivery_notes'])): ?>
                    <p class="muted">Notes: <?php echo htmlspecialchars($order['delivery_notes']); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="muted">Older orders don't have a saved delivery address.</p>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php include 'footer.php'; ?>
