<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>


<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: adminlogin.php");
    exit;
}

// Connect to DB
$conn = new mysqli("localhost", "root", "root", "honey_art_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Admin Panel - HoneyArt</title>
    <style>
        .processing, .shipped, .delivered, .cancelled{
            display: none;
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


    </style>
</head>

<body>

    <h1>Admin Panel - HoneyArt</h1>
    <p><a href="logout.php">Logout</a> | <a href="admin_add_blog.php">Add blog</a> | <a
            href="admin_orders.php">Orders</a></p>
    <div class="orders">
        <h1>Orders</h1>
        <script>
            const orderSections = [
                '.all',
                '.processing',
                '.shipped',
                '.delivered',
                '.cancelled',
            ];

            function openOrderSection(section, clickedElement) {
                // Remove activeSec from all menu items
                document.querySelectorAll('.orderSectionsList li')
                    .forEach(li => li.classList.remove('activeSec'));

                // Add activeSec to clicked menu item
                clickedElement.classList.add('activeSec');

                // Hide all sections
                orderSections.forEach(selector => {
                    document.querySelector(selector).style.display = 'none';
                });

                // Show selected section
                document.querySelector(section).style.display = 'block';
            }
        </script>

        <ul class="orderSectionsList">
            <li class='activeSec' onclick="openOrderSection('.all', this)">All orders</li>
            <li onclick="openOrderSection('.processing', this)">Processing</li>
            <li onclick="openOrderSection('.shipped', this)">Shipped</li>
            <li onclick="openOrderSection('.delivered', this)">Delivered</li>
            <li onclick="openOrderSection('.cancelled', this)">Cancelled</li>
        </ul>
        <?php
        function showOrders($conn, $status = null)
        {

            $sql = "SELECT 
                    orders.id,
                    orders.user_id,
                    orders.product_id,
                    orders.quantity,
                    orders.status,
                    orders.created_at,
                    products.name,
                    products.price,
                    products.image_data,
                    products.image_type,
                    users.username
                FROM orders
                JOIN products ON orders.product_id = products.id
                JOIN users ON orders.user_id = users.id";

            if ($status !== null) {
                $sql .= " WHERE orders.status = ?";
            }

            $sql .= " ORDER BY orders.created_at DESC";

            $stmt = $conn->prepare($sql);

            if ($status !== null) {
                $stmt->bind_param("s", $status);
            }

            $stmt->execute();

            $result = $stmt->get_result();

            while ($order = $result->fetch_assoc()) {


                $status = strtolower($order['status']);

                switch ($status) {
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
                        <img src="default.png" alt="No Image">
                    <?php endif; ?>
                    <div class="order-info">
                        <div class="order-number">
                            #<?= htmlspecialchars($order['id']) ?>
                            - <?= htmlspecialchars($order['name']) ?>
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

                    <div class="order-price">
                        $<?= number_format($order['price'], 2) ?>
                    </div>

                    <div class="order-arrow">
                        ›
                    </div>

                </div>
                <?php
            }
        }
        ?>
        <div class="all">
            <?php showOrders($conn); ?>
        </div>

        <div class="processing">
            <?php showOrders($conn, 'processing'); ?>
        </div>

        <div class="shipped">
            <?php showOrders($conn, 'shipped'); ?>
        </div>

        <div class="delivered">
            <?php showOrders($conn, 'delivered'); ?>
        </div>

        <div class="cancelled">
            <?php showOrders($conn, 'cancelled'); ?>
        </div>
    </div>

</body>

</html>