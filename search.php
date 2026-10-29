<?php
require_once __DIR__ . '/db.php';
if(isset($_POST['inputText'])){
    // Escape LIKE wildcards so "%" or "_" are searched literally
    $like = '%' . addcslashes((string) $_POST['inputText'], '%_\\') . '%';

    $stmt = $conn->prepare("SELECT * FROM products WHERE `name` LIKE ? LIMIT 3");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $sstmt = $conn->prepare("SELECT * FROM products WHERE `name` LIKE ? LIMIT 7");
    $sstmt->bind_param("s", $like);
    $sstmt->execute();
    $sresult = $sstmt->get_result();

    if(mysqli_num_rows($result) > 0){ ?>
        <style>
            #search-overlay{
                display: block;
            }
        </style>
        
        <div id="search-products">
            <!-- <div id="suggestions">
                <h4>Suggestions</h4>
                <?php //while ($row = $sresult->fetch_assoc()): ?>
                        <p><?php //echo htmlspecialchars($row['name']); ?></p>
                <?php //endwhile; ?>
            </div> -->
            
            <div id="search-t-products">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="search-product" onclick="window.location.href='product.php?id=<?php echo $row['id']; ?>'">
                        <?php if (!empty($row["image_data"])): ?>
                            <img src="image.php?id=<?php echo $row['id']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                        <?php else: ?>
                            <img src="images/default.webp" alt="No Image">
                        <?php endif; ?>
                        <h2><?php echo htmlspecialchars($row['name']); ?></h2>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>


    <?php
    }
    else{
        echo "<h6 class='text-danger'>NO data found</h6>";
    }
}