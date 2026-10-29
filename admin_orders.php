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

    $redirectQuery = http_build_query([
        'status' => array_key_exists($returnStatus, $statuses) ? $returnStatus : 'all',
        'user_id' => $returnUserId ?: null,
    ]);

    if (!hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['order_flash'] = ['type' => 'error', 'text' => 'Invalid request. Please try again.'];
    } elseif (!$orderId || !isset($statuses[$newStatus]) || $newStatus === 'all') {
        $_SESSION['order_flash'] = ['type' => 'error', 'text' => 'Please select a valid order status.'];
    } else {
        $updateStmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
        $updateStmt->bind_param('si', $newStatus, $orderId);
        $updateStmt->execute();

        $_SESSION['order_flash'] = $updateStmt->affected_rows >= 0
            ? ['type' => 'success', 'text' => 'Order status updated successfully.']
            : ['type' => 'error', 'text' => 'Order could not be updated.'];
        $updateStmt->close();
    }

    header('Location: admin_orders.php?' . $redirectQuery);
    exit;
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
        <?php
        function showOrders($conn, $status, $userId)
        {
            $activeStatus = $status;

            $sql = "SELECT 
                    orders.id,
                    orders.user_id,
                    orders.product_id,
                    orders.quantity,
                    orders.status,
                    orders.created_at,
                    products.name,
                    orders.unit_price AS price,
                    products.image_data,
                    products.image_type,
                    users.username
                FROM orders
                JOIN products ON orders.product_id = products.id
                JOIN users ON orders.user_id = users.id
                WHERE (? = 'all' OR orders.status = ?)
                  AND (? = 0 OR orders.user_id = ?)
                ORDER BY orders.created_at DESC";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param("ssii", $status, $status, $userId, $userId);

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                echo '<p class="empty-orders">No orders found for the selected filters.</p>';
                return;
            }

            while ($order = $result->fetch_assoc()) {


                $orderStatus = strtolower($order['status']);

                switch ($orderStatus) {
                    case 'delivered':
                        $statusClass = 'status-delivered';
                        break;

                    case 'shipped':
                        $statusClass = 'status-shipped';
                        break;

                    case 'processing':
                        $statusClass = 'status-processing';
                        break;

                    case 'cancelled':
                        $statusClass = 'status-cancelled';
                        break;

                    default:
                        $statusClass = 'status-pending';
                }

                ?>
                <div class="order">
                    <?php
                    if (!empty($order["image_data"])): ?>
                        <img src="image.php?id=<?php echo $order['product_id']; ?>"
                            alt="<?php echo htmlspecialchars($order['name']); ?>">
                    <?php else: ?>
                        <img src="images/default.webp" alt="No Image">
                    <?php endif; ?>
                    <div class="order-info">
                        <div class="order-number">
                            #<?= htmlspecialchars($order['id']) ?>
                            - <?= htmlspecialchars($order['name']) ?><?= (int) $order['quantity'] > 1 ? ' × ' . (int) $order['quantity'] : '' ?>
                        </div>

                        <div class="order-date">
                            <?= date("F j, Y", strtotime($order['created_at'])) ?>
                        </div>

                        <div class="order-date">
                            user id: <?= htmlspecialchars($order['user_id']) ?>
                            <br>
                            username: <?= htmlspecialchars($order['username']) ?>
                        </div>
                    </div>

                    <div class="order-status <?= $statusClass ?>">
                        <?= htmlspecialchars($order['status']) ?>
                    </div>

                    <form method="POST" class="status-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_csrf_token']) ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                        <input type="hidden" name="return_status" value="<?= htmlspecialchars($activeStatus) ?>">
                        <input type="hidden" name="return_user_id" value="<?= (int) $userId ?>">
                        <select name="order_status" aria-label="Order status for order #<?= (int) $order['id'] ?>">
                            <?php foreach (['processing', 'shipped', 'delivered', 'cancelled'] as $statusOption): ?>
                                <option value="<?= $statusOption ?>" <?= $orderStatus === $statusOption ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($statusOption)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="update_order_status">Update</button>
                    </form>

                    <div class="order-price">
                        ֏<?= number_format($order['price'] * $order['quantity'], 0) ?>
                    </div>

                    <div class="order-arrow">
                        ›
                    </div>

                </div>
                <?php
            }
        }
        ?>
        <?php showOrders($conn, $selectedStatus, $selectedUserId); ?>
    </div>

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

</body>

</html>
