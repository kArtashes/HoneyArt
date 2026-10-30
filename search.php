<?php
// Live search results for the search bar (search_form.php).
// GET search.php?q=acacia returns a small HTML list of matching products.

require_once __DIR__ . '/db.php';

const SEARCH_LIVE_LIMIT = 6;

$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '') {
    exit;
}

// Escape LIKE wildcards so "%" or "_" are searched literally
$like = '%' . addcslashes($query, '%_\\') . '%';
$startsWith = addcslashes($query, '%_\\') . '%';

// Name matches first (names that start with the text first of all), then category matches
$stmt = $conn->prepare(
    "SELECT id, name, price, weight_kg, image_data IS NOT NULL AS has_image
     FROM products
     WHERE name LIKE ? OR category LIKE ?
     ORDER BY name LIKE ? DESC, name LIKE ? DESC, name
     LIMIT " . SEARCH_LIVE_LIMIT
);
$stmt->bind_param('ssss', $like, $like, $startsWith, $like);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$countStmt = $conn->prepare('SELECT COUNT(*) FROM products WHERE name LIKE ? OR category LIKE ?');
$countStmt->bind_param('ss', $like, $like);
$countStmt->execute();
$total = (int) $countStmt->get_result()->fetch_row()[0];

// Product name with the searched text wrapped in <mark>, safely escaped
function highlightMatch(string $text, string $query): string
{
    $position = mb_stripos($text, $query);
    if ($position === false) {
        return htmlspecialchars($text);
    }
    $length = mb_strlen($query);

    return htmlspecialchars(mb_substr($text, 0, $position))
        . '<mark>' . htmlspecialchars(mb_substr($text, $position, $length)) . '</mark>'
        . htmlspecialchars(mb_substr($text, $position + $length));
}

function formatWeight($weight): string
{
    $weight = (float) $weight;
    return ($weight == floor($weight) ? number_format($weight, 0) : number_format($weight, 1)) . ' kg';
}

$allResultsUrl = 'products.php?q=' . urlencode($query);
?>
<?php if (!$products): ?>
    <div class="search-empty">
        <p>No products match “<?php echo htmlspecialchars($query); ?>”.</p>
        <a href="products.php">Browse all products →</a>
    </div>
<?php else: ?>
    <ul class="search-hits">
        <?php foreach ($products as $product): ?>
            <li>
                <a class="search-hit" href="product.php?id=<?php echo (int) $product['id']; ?>">
                    <img src="<?php echo $product['has_image'] ? 'image.php?id=' . (int) $product['id'] : 'images/default.webp'; ?>" alt="" loading="lazy">
                    <span class="search-hit-text">
                        <span class="search-hit-name"><?php echo highlightMatch($product['name'], $query); ?></span>
                        <span class="search-hit-meta"><?php echo formatWeight($product['weight_kg']); ?> · <strong>֏<?php echo number_format($product['price'], 0); ?></strong></span>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <a class="search-all" href="<?php echo htmlspecialchars($allResultsUrl); ?>">
        See all <?php echo $total; ?> result<?php echo $total === 1 ? '' : 's'; ?> for “<?php echo htmlspecialchars($query); ?>” →
    </a>
<?php endif; ?>
