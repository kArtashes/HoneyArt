<?php
// Orders: one order (orders table) holds one or more products (order_items table).

function orderStatusClass(string $status): string
{
    $known = ['delivered', 'shipped', 'processing', 'cancelled'];
    $status = strtolower($status);

    return in_array($status, $known, true) ? 'status-' . $status : 'status-pending';
}

// Orders for a list, newest first, with their first product for the picture and the title.
// $userId and $status narrow the list; null means all.
function getOrderList(mysqli $conn, ?int $userId = null, ?string $status = null): array
{
    $sql = "SELECT o.id, o.user_id, o.status, o.total, o.created_at, u.username,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count,
                   first_item.product_id AS first_product_id,
                   first_item.quantity AS first_quantity,
                   p.name AS first_name,
                   (p.image_data IS NOT NULL) AS has_image
            FROM orders o
            JOIN users u ON u.id = o.user_id
            JOIN order_items first_item ON first_item.id = (SELECT MIN(id) FROM order_items WHERE order_id = o.id)
            JOIN products p ON p.id = first_item.product_id
            WHERE (? IS NULL OR o.user_id = ?)
              AND (? IS NULL OR o.status = ?)
            ORDER BY o.created_at DESC, o.id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iiss', $userId, $userId, $status, $status);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $orders;
}

// "#30 - Forest Honey × 3" or, with several products, "#30 - Forest Honey + 2 more"
function orderTitle(array $order): string
{
    $title = '#' . (int) $order['id'] . ' - ' . $order['first_name'];
    $others = (int) $order['item_count'] - 1;

    if ($others > 0) {
        return $title . ' + ' . $others . ' more';
    }

    return (int) $order['first_quantity'] > 1 ? $title . ' × ' . (int) $order['first_quantity'] : $title;
}

// One order with its customer and products, or null if it doesn't exist
function getOrderDetails(mysqli $conn, int $orderId): ?array
{
    $stmt = $conn->prepare(
        'SELECT o.*, u.username, u.email
         FROM orders o
         JOIN users u ON u.id = o.user_id
         WHERE o.id = ?'
    );
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        return null;
    }

    $stmt = $conn->prepare(
        'SELECT i.product_id, i.quantity, i.unit_price, p.name, p.weight_kg, p.is_hidden, (p.image_data IS NOT NULL) AS has_image
         FROM order_items i
         JOIN products p ON p.id = i.product_id
         WHERE i.order_id = ?
         ORDER BY i.id'
    );
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $order;
}

// Delivery address lines of an order; empty for older orders that never stored it
function orderDeliveryLines(array $order): array
{
    if (empty($order['delivery_address_line1'])) {
        return [];
    }

    $city = $order['delivery_city'] . (!empty($order['delivery_region']) ? ', ' . $order['delivery_region'] : '');

    return array_values(array_filter([
        $order['delivery_address_line1'],
        $order['delivery_address_line2'],
        $city,
        $order['delivery_phone'],
    ]));
}

// One row of the customer's order list (account page); the whole row opens the order
function renderCustomerOrderRow(array $order): void
{
    ?>
    <a class="order" href="order.php?id=<?php echo (int) $order['id']; ?>">
        <?php if (!empty($order['has_image'])): ?>
            <img loading="lazy" src="image.php?id=<?php echo (int) $order['first_product_id']; ?>" alt="">
        <?php else: ?>
            <img src="images/default.webp" alt="">
        <?php endif; ?>
        <div class="order-info">
            <div class="order-number"><?php echo htmlspecialchars(orderTitle($order)); ?></div>
            <div class="order-date"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></div>
        </div>
        <div class="order-status <?php echo orderStatusClass($order['status']); ?>"><?php echo htmlspecialchars($order['status']); ?></div>
        <div class="order-price">֏<?php echo number_format($order['total'], 0); ?></div>
        <div class="order-arrow">›</div>
    </a>
    <?php
}
