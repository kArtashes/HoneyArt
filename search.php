<?php
include('db.php');
if(isset($_POST['inputText'])){
    $input = $_POST['inputText'];

    $query = "SELECT * FROM products WHERE `name` LIKE '%{$input}%' LIMIT 3";
    $squery = "SELECT * FROM products WHERE `name` LIKE '%{$input}%' LIMIT 7";

    $result = mysqli_query($conn, $query);

    $sresult = mysqli_query($conn, $squery);

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
                            <img src="default.png" alt="No Image">
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