<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: adminlogin.php");
    exit;
}

// Connect to DB
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/orders_lib.php';

$statuses = [
    'all' => 'All orders',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
];

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$selectedStatus = $_GET['status'] ?? 'all';
if (!array_key_exists($selectedStatus, $statuses)) {
    $selectedStatus = 'all';
}

$selectedUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if ($selectedUserId === false || $selectedUserId === null || $selectedUserId < 1) {
    $selectedUserId = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $newStatus = $_POST['order_status'] ?? '';
    $returnStatus = $_POST['return_status'] ?? 'all';
    $returnUserId = filter_input(INPUT_POST, 'return_user_id', FILTER_VALIDATE_INT) ?: 0;
    $returnView = filter_input(INPUT_POST, 'return_view', FILTER_VALIDATE_INT) ?: 0;

    $redirectQuery = http_build_query($returnView ? ['view' => $returnView] : [
        'status' => array_key_exists($returnStatus, $statuses) ? $returnStatus : 'all',
        'user_id' => $returnUserId ?: null,
    ]);

    if (!hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['order_flash'] = ['type' => 'error', 'text' => 'Invalid request. Please try again.'];
    } elseif (!$orderId || !isset($statuses[$newStatus]) || $newStatus === 'all') {
        $_SESSION['order_flash'] = ['type' => 'error', 'text' => 'Please select a valid order status.'];
    } else {
        try {
            $conn->begin_transaction();

            $orderStmt = $conn->prepare('SELECT status, stock_deducted FROM orders WHERE id = ? FOR UPDATE');
            $orderStmt->bind_param('i', $orderId);
            $orderStmt->execute();
            $order = $orderStmt->get_result()->fetch_assoc();
            $orderStmt->close();

            if (!$order) {
                throw new RuntimeException('Order not found.');
            }

            $itemsStmt = $conn->prepare(
                'SELECT i.product_id, i.quantity, p.name
                 FROM order_items i
                 JOIN products p ON p.id = i.product_id
                 WHERE i.order_id = ?
                 ORDER BY i.product_id'
            );
            $itemsStmt->bind_param('i', $orderId);
            $itemsStmt->execute();
            $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $itemsStmt->close();

            $stockDeducted = (int) $order['stock_deducted'];

            if ($newStatus === 'cancelled' && $stockDeducted === 1) {
                // Cancelled: every product of the order goes back to stock
                $stockStmt = $conn->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?');
                foreach ($items as $item) {
                    $quantity = (int) $item['quantity'];
                    $productId = (int) $item['product_id'];
                    $stockStmt->bind_param('ii', $quantity, $productId);
                    $stockStmt->execute();
                }
                $stockStmt->close();
                $stockDeducted = 0;
            } elseif ($newStatus !== 'cancelled' && $order['status'] === 'cancelled' && $stockDeducted === 0) {
                // A cancelled order is active again: take the products back out, if they are all still there
                $stockStmt = $conn->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?');
                foreach ($items as $item) {
                    $quantity = (int) $item['quantity'];
                    $productId = (int) $item['product_id'];
                    $stockStmt->bind_param('iii', $quantity, $productId, $quantity);
                    $stockStmt->execute();
                    if ($stockStmt->affected_rows !== 1) {
                        throw new RuntimeException('Not enough “' . $item['name'] . '” in stock to reactivate this order.');
                    }
                }
                $stockStmt->close();
                $stockDeducted = 1;
            }

            $updateStmt = $conn->prepare('UPDATE orders SET status = ?, stock_deducted = ? WHERE id = ?');
            $updateStmt->bind_param('sii', $newStatus, $stockDeducted, $orderId);
            $updateStmt->execute();
            $updateStmt->close();

            $conn->commit();
            $_SESSION['order_flash'] = ['type' => 'success', 'text' => 'Order status updated successfully.'];
        } catch (RuntimeException $e) {
            // Includes database errors (mysqli_sql_exception is a RuntimeException)
            $conn->rollback();
            $_SESSION['order_flash'] = ['type' => 'error', 'text' => $e instanceof mysqli_sql_exception
                ? 'Order could not be updated.'
                : $e->getMessage()];
        }
    }

    header('Location: admin_orders.php?' . $redirectQuery);
    exit;
}

// admin_orders.php?view=30 shows one order instead of the list
$viewOrderId = filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT) ?: 0;
$viewOrder = $viewOrderId > 0 ? getOrderDetails($conn, $viewOrderId) : null;
if ($viewOrderId > 0 && !$viewOrder) {
    $_SESSION['order_flash'] = ['type' => 'error', 'text' => 'Order #' . $viewOrderId . ' was not found.'];
}

function renderOrderStatusForm(array $order, string $returnStatus, int $returnUserId, int $returnView): void
{
    $orderStatus = strtolower((string) $order['status']);
    ?>
    <form method="POST" class="status-form" action="admin_orders.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>">
        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
        <input type="hidden" name="return_status" value="<?= htmlspecialchars($returnStatus) ?>">
        <input type="hidden" name="return_user_id" value="<?= $returnUserId ?>">
        <input type="hidden" name="return_view" value="<?= $returnView ?>">
        <select name="order_status" aria-label="Status of order #<?= (int) $order['id'] ?>">
            <?php foreach (['processing', 'shipped', 'delivered', 'cancelled'] as $statusOption): ?>
                <option value="<?= $statusOption ?>" <?= $orderStatus === $statusOption ? 'selected' : '' ?>>
                    <?= ucfirst($statusOption) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="update_order_status">Update</button>
    </form>
    <?php
}

// The customer list is limited by the currently selected status.
$customerSql = "SELECT DISTINCT users.id, users.username, users.email
                FROM users
                INNER JOIN orders ON orders.user_id = users.id";
if ($selectedStatus !== 'all') {
    $customerSql .= " WHERE orders.status = ?";
}
$customerSql .= " ORDER BY users.username, users.email";
$customerStmt = $conn->prepare($customerSql);
if ($selectedStatus !== 'all') {
    $customerStmt->bind_param('s', $selectedStatus);
}
$customerStmt->execute();
$customers = $customerStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$customerStmt->close();

$selectedCustomer = null;
foreach ($customers as $customer) {
    if ((int) $customer['id'] === $selectedUserId) {
        $selectedCustomer = $customer;
        break;
    }
}

// Status counts are limited by the selected customer, so unavailable tabs can be disabled.
$statusCounts = array_fill_keys(array_keys($statuses), 0);
$statusCountSql = "SELECT status, COUNT(*) AS total FROM orders";
if ($selectedUserId) {
    $statusCountSql .= " WHERE user_id = ?";
}
$statusCountSql .= " GROUP BY status";
$statusCountStmt = $conn->prepare($statusCountSql);
if ($selectedUserId) {
    $statusCountStmt->bind_param('i', $selectedUserId);
}
$statusCountStmt->execute();
$statusCountResult = $statusCountStmt->get_result();
while ($row = $statusCountResult->fetch_assoc()) {
    if (isset($statusCounts[$row['status']])) {
        $statusCounts[$row['status']] = (int) $row['total'];
        $statusCounts['all'] += (int) $row['total'];
    }
}
$statusCountStmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Admin Panel - HoneyArt</title>
    <style>
        /* Same base look as the other admin pages */
        body {
            font-family: sans-serif;
            margin: 20px;
            background: #f8f8f8;
            color: #222;
        }
        h1 {
            color: #d38f12;
        }
        a {
            color: #d38f12;
            text-decoration: none;
        }

        .orderSectionsList {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;

            border-bottom: 1px solid #e5e5e5;
        }

        .orderSectionsList li {
            position: relative;
            padding: 12px 20px;
            font-size: 13px;
            color: #444;
            cursor: pointer;
        }

        .orderSectionsList li.activeSec {
            color: #111;
            background: #f2e7d2;
            font-weight: bold;
        }

        .orderSectionsList a {
            color: inherit;
            text-decoration: none;
        }

        .orderSectionsList li.disabled {
            color: #aaa;
            cursor: not-allowed;
        }

        .orderSectionsList li.disabled a {
            pointer-events: none;
        }

        .filters {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            margin: 20px 0;
        }

        .customer-filter {
            position: relative;
            width: min(390px, 100%);
        }

        .customer-filter label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 600;
        }

        .customer-filter input {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .customer-options {
            display: none;
            position: absolute;
            z-index: 10;
            top: 100%;
            width: 100%;
            max-height: 230px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .12);
        }

        .customer-options.open { display: block; }

        .customer-option {
            width: 100%;
            padding: 10px 12px;
            border: 0;
            border-bottom: 1px solid #eee;
            background: #fff;
            text-align: left;
            cursor: pointer;
        }

        .customer-option:hover { background: #f8f3e9; }
        .customer-option small { display: block; color: #777; margin-top: 3px; }
        .clear-filters { padding: 10px 14px; color: #555; }
        .empty-orders { padding: 28px 0; color: #777; text-align: center; }

        .orderSectionsList li.active::after {
            content: "";
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #111;
        }
        .order {
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 82px;
            padding: 12px 0px;
            border-bottom: 1px solid #eeeeee;
            box-sizing: border-box;
        }
        .order img {
            width: 15%;
            margin-right: 5%;
        }

        .order-info {
            flex: 1;
        }

        .order-number {
            font-size: 17px;
            font-weight: 500;
            color: #222;
            margin-bottom: 7px;
        }

        .order-date {
            font-size: 14px;
            color: #888;
        }

        /* STATUS */

        .order-status {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            min-width: 66px;
            text-align: center;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 16px;
        }

        .status-form select, .status-form button {
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: #fff;
        }

        .status-form button {
            cursor: pointer;
            background: #f2e7d2;
            border-color: #e1cfad;
        }

        .flash-message {
            padding: 12px 14px;
            margin: 16px 0;
            border-radius: 6px;
        }

        .flash-message.success { background: #e6f4ea; color: #217a3c; }
        .flash-message.error { background: #fde7e7; color: #b42318; }

        /* Green */

        .status-delivered {
            background: #e6f4ea;
            color: #29904a;
        }

        /* Orange */

        .status-shipped {
            background: #fff2d6;
            color: #d88a00;
        }

        /* Blue */

        .status-processing {
            background: #e5f0ff;
            color: #3578d4;
        }

        /* Red */

        .status-cancelled {
            background: #fde7e7;
            color: #d64545;
        }

        /* Gray */

        .status-pending {
            background: #eeeeee;
            color: #666;
        }


        /* PRICE */

        .order-price {
            width: 90px;
            text-align: right;
            font-size: 16px;
            font-weight: 500;
            color: #222;
        }


        /* ARROW */

        .order-arrow {
            width: 35px;
            text-align: center;
            font-size: 30px;
            font-weight: 300;
            color: #222;
            line-height: 1;
        }



        /* ---------- One order ---------- */
        .order-number a { color: inherit; }
        .order-arrow { text-decoration: none; }
        .order-view { max-width: 820px; }
        .order-back { display: inline-block; margin-bottom: 12px; }
        .order-view-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px 16px;
            margin-bottom: 16px;
        }
        .order-view-head h2 { margin: 0; }
        .order-view-head .status-form { margin-left: 0; }
        .order-card {
            padding: 16px 18px;
            margin-bottom: 14px;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
        }
        .order-card h3 { margin: 0 0 10px; }
        .order-card p { margin: 0 0 4px; line-height: 1.5; }
        .order-note { color: #777; }
        .order-line {
            display: grid;
            grid-template-columns: 56px minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .order-line img { width: 56px; height: 56px; object-fit: cover; border-radius: 6px; }
        .order-total-row {
            display: flex;
            justify-content: space-between;
            padding-top: 12px;
            font-size: 18px;
            font-weight: 600;
        }

        /* ---------- Phones & small tablets ---------- */
        @media (max-width: 767px) {
            body {
                margin: 12px;
            }
            h1 {
                font-size: 26px;
            }

            /* Status tabs scroll sideways */
            .orderSectionsList {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .orderSectionsList li {
                flex-shrink: 0;
                white-space: nowrap;
                padding: 12px 14px;
            }

            .filters {
                flex-wrap: wrap;
            }

            /* Order row: image + info on top, status / change / price below */
            .order {
                flex-wrap: wrap;
                gap: 10px 12px;
            }
            .order img {
                width: 56px;
                height: 56px;
                object-fit: cover;
                margin-right: 0;
                border-radius: 6px;
            }
            .order-info {
                flex: 1 1 calc(100% - 68px);
                min-width: 0;
            }
            .order-number {
                font-size: 15px;
            }
            .status-form {
                margin-left: 0;
            }
            .order-price {
                width: auto;
                margin-left: auto;
            }
            .order-arrow {
                display: none;
            }
        }
    </style>
</head>

<body>

    <h1>Admin Panel - HoneyArt</h1>
    <p><a href="adminlogout.php">Logout</a> | <a href="admin_add_product.php">Add product</a> | <a href="admin_add_blog.php">Add blog</a> | <a
            href="admin_orders.php">Orders</a></p>
    <div class="orders">
        <h1>Orders</h1>
        <?php if (!empty($_SESSION['order_flash'])): ?>
            <?php $flash = $_SESSION['order_flash']; unset($_SESSION['order_flash']); ?>
            <div class="flash-message <?= htmlspecialchars($flash['type']) ?>">
                <?= htmlspecialchars($flash['text']) ?>
            </div>
        <?php endif; ?>
        <?php if ($viewOrder): ?>
            <?php $deliveryLines = orderDeliveryLines($viewOrder); ?>
            <div class="order-view">
                <a class="order-back" href="admin_orders.php">← All orders</a>
                <div class="order-view-head">
                    <div>
                        <h2>Order #<?= (int) $viewOrder['id'] ?></h2>
                        <div class="order-date"><?= date('F j, Y, H:i', strtotime($viewOrder['created_at'])) ?></div>
                    </div>
                    <div class="order-status <?= orderStatusClass((string) $viewOrder['status']) ?>"><?= htmlspecialchars((string) $viewOrder['status']) ?></div>
                    <?php renderOrderStatusForm($viewOrder, 'all', 0, (int) $viewOrder['id']); ?>
                </div>

                <section class="order-card">
                    <h3>Customer</h3>
                    <p><?= htmlspecialchars($viewOrder['username']) ?> (user id: <?= (int) $viewOrder['user_id'] ?>)</p>
                    <p><a href="mailto:<?= htmlspecialchars($viewOrder['email']) ?>"><?= htmlspecialchars($viewOrder['email']) ?></a></p>
                </section>

                <section class="order-card">
                    <h3>Delivery address</h3>
                    <?php if ($deliveryLines): ?>
                        <?php foreach ($deliveryLines as $line): ?>
                            <p><?= htmlspecialchars($line) ?></p>
                        <?php endforeach; ?>
                        <?php if (!empty($viewOrder['delivery_notes'])): ?>
                            <p class="order-note">Notes: <?= htmlspecialchars($viewOrder['delivery_notes']) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="order-note">This older order has no saved delivery address.</p>
                    <?php endif; ?>
                </section>

                <section class="order-card">
                    <h3>Products</h3>
                    <?php foreach ($viewOrder['items'] as $item): ?>
                        <div class="order-line">
                            <img loading="lazy" src="<?= !empty($item['has_image']) ? 'image.php?id=' . (int) $item['product_id'] : 'images/default.webp' ?>" alt="">
                            <div>
                                <div><?= htmlspecialchars($item['name']) ?></div>
                                <div class="order-date"><?= (int) $item['quantity'] ?> × ֏<?= number_format($item['unit_price'], 0) ?></div>
                            </div>
                            <strong>֏<?= number_format($item['unit_price'] * $item['quantity'], 0) ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <div class="order-total-row"><span>Total</span><span>֏<?= number_format($viewOrder['total'], 0) ?></span></div>
                </section>
            </div>
        <?php else: ?>
        <ul class="orderSectionsList">
            <?php foreach ($statuses as $status => $label): ?>
                <?php
                $isDisabled = $status !== 'all' && $selectedUserId && $statusCounts[$status] === 0;
                $url = 'admin_orders.php?' . http_build_query([
                    'status' => $status,
                    'user_id' => $selectedUserId ?: null,
                ]);
                ?>
                <li class="<?= $selectedStatus === $status ? 'activeSec' : '' ?> <?= $isDisabled ? 'disabled' : '' ?>">
                    <a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($label) ?> (<?= $statusCounts[$status] ?>)</a>
                </li>
            <?php endforeach; ?>
        </ul>

        <form class="filters" method="GET" id="customer-filter-form">
            <input type="hidden" name="status" value="<?= htmlspecialchars($selectedStatus) ?>">
            <input type="hidden" name="user_id" id="selected-user-id" value="<?= $selectedUserId ?: '' ?>">
            <div class="customer-filter">
                <label for="customer-search">Customer</label>
                <input id="customer-search" type="search" autocomplete="off"
                       placeholder="Search by name, email, or ID"
                       value="<?= htmlspecialchars($selectedCustomer ? $selectedCustomer['username'] . ' — ' . $selectedCustomer['email'] : '') ?>">
                <div class="customer-options" id="customer-options">
                    <?php foreach ($customers as $customer): ?>
                        <button type="button" class="customer-option"
                                data-id="<?= (int) $customer['id'] ?>"
                                data-search="<?= htmlspecialchars(strtolower($customer['id'] . ' ' . $customer['username'] . ' ' . $customer['email'])) ?>">
                            <?= htmlspecialchars($customer['username']) ?>
                            <small>#<?= (int) $customer['id'] ?> · <?= htmlspecialchars($customer['email']) ?></small>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($selectedUserId || $selectedStatus !== 'all'): ?>
                <a class="clear-filters" href="admin_orders.php">Clear filters</a>
            <?php endif; ?>
        </form>
        <?php $listedOrders = getOrderList($conn, $selectedUserId ?: null, $selectedStatus === 'all' ? null : $selectedStatus); ?>
        <?php if (!$listedOrders): ?>
            <p class="empty-orders">No orders found for the selected filters.</p>
        <?php endif; ?>
        <?php foreach ($listedOrders as $order): ?>
            <?php $orderUrl = 'admin_orders.php?view=' . (int) $order['id']; ?>
            <div class="order">
                <?php if (!empty($order['has_image'])): ?>
                    <img loading="lazy" src="image.php?id=<?= (int) $order['first_product_id'] ?>" alt="">
                <?php else: ?>
                    <img src="images/default.webp" alt="">
                <?php endif; ?>
                <div class="order-info">
                    <div class="order-number">
                        <a href="<?= $orderUrl ?>"><?= htmlspecialchars(orderTitle($order)) ?></a>
                    </div>
                    <div class="order-date"><?= date('F j, Y', strtotime($order['created_at'])) ?></div>
                    <div class="order-date">
                        user id: <?= (int) $order['user_id'] ?>
                        <br>
                        username: <?= htmlspecialchars($order['username']) ?>
                    </div>
                </div>

                <div class="order-status <?= orderStatusClass((string) $order['status']) ?>">
                    <?= htmlspecialchars((string) $order['status']) ?>
                </div>

                <?php renderOrderStatusForm($order, $selectedStatus, $selectedUserId, 0); ?>

                <div class="order-price">֏<?= number_format($order['total'], 0) ?></div>

                <a class="order-arrow" href="<?= $orderUrl ?>" aria-label="Open order #<?= (int) $order['id'] ?>">›</a>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!$viewOrder): ?>
    <script>
        const search = document.getElementById('customer-search');
        const options = document.getElementById('customer-options');
        const userId = document.getElementById('selected-user-id');
        const form = document.getElementById('customer-filter-form');

        function filterCustomers() {
            const query = search.value.trim().toLowerCase();
            options.querySelectorAll('.customer-option').forEach(option => {
                option.hidden = query !== '' && !option.dataset.search.includes(query);
            });
        }

        search.addEventListener('focus', () => {
            options.classList.add('open');
            filterCustomers();
        });
        search.addEventListener('input', () => {
            userId.value = '';
            options.classList.add('open');
            filterCustomers();
        });
        options.querySelectorAll('.customer-option').forEach(option => {
            option.addEventListener('click', () => {
                userId.value = option.dataset.id;
                search.value = option.childNodes[0].textContent.trim();
                form.submit();
            });
        });
        document.addEventListener('click', event => {
            if (!event.target.closest('.customer-filter')) options.classList.remove('open');
        });
    </script>
    <?php endif; ?>

</body>

</html>
