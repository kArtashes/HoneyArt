<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/order_mail.php';

require_once __DIR__ . '/db.php';
$userId = (int) ($_SESSION['user_id'] ?? 0);
if (!$userId) {
    header('Location: logInReg.php');
    exit;
}

if (empty($_SESSION['checkout_csrf_token'])) {
    $_SESSION['checkout_csrf_token'] = bin2hex(random_bytes(32));
}

function getCartItems(mysqli $conn, int $userId): array
{
    $stmt = $conn->prepare(
        'SELECT products.id, products.name, products.price, cart.quantity
         FROM cart
         INNER JOIN products ON cart.product_id = products.id
         WHERE cart.user_id = ?
         ORDER BY cart.created_at'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $items;
}

function getSavedAddresses(mysqli $conn, int $userId): array
{
    $stmt = $conn->prepare(
        'SELECT id, phone, city, region, address_line1, address_line2, notes
         FROM user_addresses
         WHERE user_id = ?
         ORDER BY updated_at DESC, id DESC'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $addresses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $addresses;
}

$userStmt = $conn->prepare('SELECT username, email FROM users WHERE id = ?');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: logInReg.php');
    exit;
}

$cartItems = getCartItems($conn, $userId);
$addresses = getSavedAddresses($conn, $userId);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $selectedAddressId = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);

    if (!hash_equals($_SESSION['checkout_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif (!$cartItems) {
        $error = 'Your basket is empty.';
    } elseif (!$selectedAddressId) {
        $error = 'Please select a delivery address.';
    } else {
        $selectedAddress = null;
        foreach ($addresses as $address) {
            if ((int) $address['id'] === $selectedAddressId) {
                $selectedAddress = $address;
                break;
            }
        }

        if (!$selectedAddress) {
            $error = 'Please select one of your saved addresses.';
        } else {
            try {
                $conn->begin_transaction();
                $orderStmt = $conn->prepare(
                    "INSERT INTO orders (user_id, product_id, quantity, unit_price, status) VALUES (?, ?, ?, ?, 'processing')"
                );

                foreach ($cartItems as $item) {
                    $productId = (int) $item['id'];
                    $quantity = (int) $item['quantity'];
                    $unitPrice = (int) $item['price']; // price at the moment of purchase
                    $orderStmt->bind_param('iiii', $userId, $productId, $quantity, $unitPrice);
                    $orderStmt->execute();
                }
                $orderStmt->close();

                $clearCartStmt = $conn->prepare('DELETE FROM cart WHERE user_id = ?');
                $clearCartStmt->bind_param('i', $userId);
                $clearCartStmt->execute();
                $clearCartStmt->close();

                // The e-mail is sent in the background after the redirect (see below)
                $total = array_sum(array_map(
                    fn(array $item): float => (float) $item['price'] * (int) $item['quantity'],
                    $cartItems
                ));
                queueOrderNotification($conn, $user, $selectedAddress, $cartItems, $total);
                $conn->commit();

                $_SESSION['checkout_flash'] = 'Your order has been placed successfully.';
                header('Location: checkout.php');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                $error = 'Could not place your order. Please try again.';
            }
        }
    }
}

$total = array_sum(array_map(
    fn(array $item): float => (float) $item['price'] * (int) $item['quantity'],
    $cartItems
));
$successMessage = $_SESSION['checkout_flash'] ?? '';
unset($_SESSION['checkout_flash']);

$pageTitle = 'Checkout';
include 'header.php';
?>

<main class="checkout-page">
    <div class="checkout-heading">
        <h1>Checkout</h1>
        <a href="products.php">Continue shopping</a>
    </div>

    <?php if ($successMessage): ?>
        <p class="checkout-message success"><?= htmlspecialchars($successMessage) ?></p>
        <script>
            // Send the order e-mail without making the customer wait for it
            fetch('send_order_notifications.php', { method: 'POST', keepalive: true, credentials: 'same-origin' })
                .catch(() => {});
        </script>
    <?php elseif ($error): ?>
        <p class="checkout-message error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (!$cartItems && !$successMessage): ?>
        <section class="checkout-card empty-checkout">
            <h2>Your basket is empty</h2>
            <a class="checkout-button" href="products.php">Browse products</a>
        </section>
    <?php elseif ($cartItems): ?>
        <form method="POST" class="checkout-layout">
            <section class="checkout-card">
                <h2>Delivery address</h2>
                <?php if ($addresses): ?>
                    <div class="address-choice-list">
                        <?php foreach ($addresses as $index => $address): ?>
                            <label class="address-choice">
                                <input type="radio" name="address_id" value="<?= (int) $address['id'] ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                                <span>
                                    <strong><?= htmlspecialchars($address['address_line1']) ?></strong>
                                    <?php if (!empty($address['address_line2'])): ?>
                                        <span><?= htmlspecialchars($address['address_line2']) ?></span>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($address['city']) ?><?= !empty($address['region']) ? ', ' . htmlspecialchars($address['region']) : '' ?></span>
                                    <span><?= htmlspecialchars($address['phone']) ?></span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>You need to save a delivery address before placing an order.</p>
                    <a class="checkout-button" href="account.php">Go to account</a>
                <?php endif; ?>
            </section>

            <aside class="checkout-card order-summary">
                <h2>Order summary</h2>
                <?php foreach ($cartItems as $item): ?>
                    <div class="checkout-item">
                        <span><?= htmlspecialchars($item['name']) ?> × <?= (int) $item['quantity'] ?></span>
                        <strong>֏<?= number_format($item['price'] * $item['quantity'], 0) ?></strong>
                    </div>
                <?php endforeach; ?>
                <div class="checkout-total">
                    <span>Total</span><strong>֏<?= number_format($total, 0) ?></strong>
                </div>
                <?php if ($addresses): ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['checkout_csrf_token']) ?>">
                    <button type="submit" name="place_order" class="checkout-button">Place order</button>
                <?php endif; ?>
            </aside>
        </form>
    <?php endif; ?>
</main>

<style>
    .checkout-page { max-width: 1080px; margin: 42px auto; padding: 0 20px 50px; color: #2d261c; }
    .checkout-heading { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; }
    .checkout-heading a { color: #9a6816; text-decoration: none; }
    .checkout-layout { display: grid; grid-template-columns: 1.35fr .85fr; gap: 22px; align-items: start; }
    .checkout-card { background: #fff; padding: 24px; border: 1px solid #eee4d3; border-radius: 14px; box-shadow: 0 2px 10px rgba(52, 39, 18, .06); }
    .checkout-card h2 { margin: 0 0 18px; font-size: 20px; }
    .address-choice-list { display: grid; gap: 12px; }
    .address-choice { display: flex; gap: 12px; padding: 15px; border: 1px solid #e5d8bf; border-radius: 10px; cursor: pointer; }
    .address-choice:has(input:checked) { border-color: #bd861f; background: #fff9ed; }
    .address-choice input { width: auto; margin-top: 4px; }
    .address-choice span span, .address-choice strong { display: block; line-height: 1.55; }
    .address-choice span span { color: #6b6256; font-size: 14px; }
    .checkout-item, .checkout-total { display: flex; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid #eee4d3; }
    .checkout-total { margin-top: 8px; border-bottom: 0; font-size: 18px; }
    .checkout-button { display: inline-block; margin-top: 20px; padding: 12px 18px; border: 0; border-radius: 8px; background: #b87d00; color: #fff; cursor: pointer; text-decoration: none; font: inherit; }
    .order-summary .checkout-button { width: 100%; }
    .checkout-message { padding: 13px 16px; border-radius: 8px; margin-bottom: 20px; }
    .checkout-message.success { background: #e6f4ea; color: #217a3c; }
    .checkout-message.error { background: #fde7e7; color: #b42318; }
    .empty-checkout { text-align: center; }
    @media (max-width: 760px) { .checkout-layout { grid-template-columns: 1fr; } }
</style>

<?php include 'footer.php'; ?>
