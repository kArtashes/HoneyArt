<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/image_utils.php';

serveStoredImage($conn, 'product_images', (int) ($_GET['id'] ?? 0));
