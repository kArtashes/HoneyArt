<?php
require __DIR__ . '/admin_auth.php';
requireAdmin();
requireAdminCsrf($_GET['token'] ?? null);

require_once __DIR__ . '/db.php';
$id = (int) ($_GET['id'] ?? 0);
$pid = (int) ($_GET['pid'] ?? 0);

$stmt = $conn->prepare("DELETE FROM product_images WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

header("Location: admin_add_product.php?edit=$pid");
