<?php
// Friendly "not found" page. Set $notFoundMessage before including this file.
http_response_code(404);
$pageTitle = 'Not found';
include __DIR__ . '/header.php';
?>
<main style="max-width: 640px; margin: 0 auto; padding: 72px 16px; text-align: center;">
    <h1 style="margin: 0 0 12px;"><?php echo htmlspecialchars($notFoundMessage ?? 'Page not found'); ?></h1>
    <p><a href="products.php" style="color: #b87d00; font-weight: 500;">Browse all products →</a></p>
</main>
<?php
include __DIR__ . '/footer.php';
exit;
