<?php
// Image helpers: every uploaded picture is stored as a resized WebP,
// so pages load kilobytes instead of megabytes.

const PRODUCT_IMAGE_MAX_WIDTH = 800;
const BLOG_IMAGE_MAX_WIDTH = 1200;
const WEBP_QUALITY = 82;

// Converts image bytes (PNG, JPEG, GIF, WebP) to WebP no wider than $maxWidth.
// Returns null when the bytes are not an image GD can read.
function toWebp(string $imageData, int $maxWidth): ?string
{
    $image = @imagecreatefromstring($imageData);
    if ($image === false) {
        return null;
    }

    // Keep transparency (PNG logos, cut-out jars)
    imagepalettetotruecolor($image);
    imagealphablending($image, false);
    imagesavealpha($image, true);

    if (imagesx($image) > $maxWidth) {
        $scaled = imagescale($image, $maxWidth, -1, IMG_BICUBIC);
        imagedestroy($image);
        $image = $scaled;
        imagealphablending($image, false);
        imagesavealpha($image, true);
    }

    ob_start();
    imagewebp($image, null, WEBP_QUALITY);
    $webp = ob_get_clean();
    imagedestroy($image);

    return $webp !== '' ? $webp : null;
}

// Converts the pictures pasted into editor content (data:image/...;base64,...) to WebP.
function convertInlineImagesToWebp(string $html, int $maxWidth): string
{
    return preg_replace_callback(
        '#data:image/(?:png|jpe?g|gif);base64,([A-Za-z0-9+/=]+)#',
        function (array $match) use ($maxWidth): string {
            $webp = toWebp(base64_decode($match[1]), $maxWidth);
            return $webp === null ? $match[0] : 'data:image/webp;base64,' . base64_encode($webp);
        },
        $html
    );
}

// Sends an image stored in the database with browser caching.
// The ETag is the image's fingerprint: an unchanged image is answered with
// "304 Not Modified" (no download), a replaced one shows up right away.
function serveStoredImage(mysqli $conn, string $table, int $id): void
{
    $allowedTables = ['products', 'product_images', 'blog_posts'];
    if (!in_array($table, $allowedTables, true)) {
        http_response_code(404);
        exit;
    }

    $stmt = $conn->prepare("SELECT image_data, image_type FROM $table WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || empty($row['image_data'])) {
        // Placeholder when the product or post has no picture
        header('Content-Type: image/webp');
        header('Cache-Control: public, max-age=86400');
        readfile(__DIR__ . '/images/default.webp');
        exit;
    }

    $etag = '"' . md5($row['image_data']) . '"';
    // no-cache = "ask before reusing": the answer is a tiny 304 unless the image changed
    header('Cache-Control: no-cache');
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');

    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }

    header('Content-Type: ' . $row['image_type']);
    header('Content-Length: ' . strlen($row['image_data']));
    echo $row['image_data'];
}
