<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Intel+One+Mono:ital,wght@0,300..700;1,300..700&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=TikTok+Sans:opsz,wght@12..36,300..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Cinzel:wght@700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=TikTok+Sans:opsz,wght@12..36,300..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="logInReg.css">
    <link rel="stylesheet" href="footer.css">
    <link rel="stylesheet" href="personalAccount.css">
    <link rel="stylesheet" href="products.css">
    <link rel="stylesheet" href="product.css">
    <link rel="stylesheet" href="cart.css">
    <link rel="stylesheet" href="support.css">
    <link rel="stylesheet" href="blogs.css">
    <link rel="stylesheet" href="blog.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>Document</title>
</head>
<body>
    <header>
        <div id="header">
            
            <div id="header_menu">
                <div id="section-0">
                    <div id="hamburger" onclick="openMenu()"><img src="./images/burgerManu.png" alt=""></div>
                    <div id="mobile-search"><img src="./images/search-icon.png" alt=""></div>
                </div>

                <div id="section-1">
                    <a href="products.php">Products</a>
                    <a href="blogs.php">Blog</a>
                    <a href="support.php">Support</a>
                </div>

                <div class="logo">
                    <span class="honey">Honey</span><span class="art">Art</span>
                </div>

                <div id="section-2">
                    <button><img id="search-icon" src="./images/search-icon.png" alt=""></button>
                    <button class="basket-icon" onclick="openBasket()"><img id="basket-icon" src="./images/basket.png" alt=""></button>
                    <button onclick="window.location.href='personalAccount.php'"><img id="account-icon" src="./images/personalAccount.png" alt=""></button>
                </div>
            </div>
        </div>
    </header>

    <!-- Overlay -->
    <div class="basket-overlay" onclick="closeBasket()"></div>

    <!-- Basket Panel -->
     <?php 
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
     ?>
    <div class="basket-panel">
        <div id="basket-heading">
            <span>Your Basket</span>
            <button onclick="closeBasket()">x</button>
        </div>
<!-- ================================ -->
        <?php
            $basket_user_id = $_SESSION['user_id'] ?? 0;
            $total_price=0;
            if ($basket_user_id) {
                $hi = $conn->query("
                SELECT SUM(products.price * cart.quantity) AS total_price
                FROM cart
                INNER JOIN products ON cart.product_id = products.id
                WHERE cart.user_id = $basket_user_id
                ");
                $ola = $hi->fetch_assoc();
                $total_price = $ola['total_price'] ?? 0;
            }

            $free_shipping_limit = 40000;
            $amount_left = max($free_shipping_limit - $total_price, 0);
        ?>


        <div class="free-sheeping-block">
            <?php if($total_price >= $free_shipping_limit): ?>
                <span class="free-sheeping-success">Congratulations! Your order qualifies for free shipping</span>
                <span class="free-sheeping-default" style="display:none;">You are <span><?php echo number_format($amount_left,2); ?>$</span> away from free shipping.</span>
            <?php else: ?>
                <span class="free-sheeping-success" style="display:none;">Congratulations! Your order qualifies for free shipping</span>
                <span class="free-sheeping-default">You are <span><?php echo number_format($amount_left); ?></span>$ away from free shipping.</span>
            <?php endif; ?>

            <progress value="<?php echo $total_price; ?>" max="<?php echo $free_shipping_limit; ?>"></progress>
        </div>
        
        <!-- ==================================== -->
        
        <?php 
            include('cart.php');
            ?>
        <div class="basket-footer">
            <?php
                $basket_user_id = $_SESSION['user_id'] ?? 0;
                if ($basket_user_id) {
                    $hi = $conn->query("
                    SELECT SUM(products.price * cart.quantity) AS total_price
                    FROM cart
                    INNER JOIN products ON cart.product_id = products.id
                    WHERE cart.user_id = $basket_user_id
                    ");
                    $ola = $hi->fetch_assoc();
                    $total_price = $ola['total_price'] ?? 0;
                }
            ?>
            <div class="cart-summary"><span>Subtotal</span> <span class="total-price">$<?php echo number_format($total_price, 0); ?></span></div>            


            <?php
                $free_shipping_limit = 40000;
                $amount_left = max($free_shipping_limit - $total_price, 0);
            ?>
            <form action="checkout.php" method="post" id="checkout-form">
                <button type="submit" class="checkout-btn">CHECK OUT</button>
            </form>
        </div>
    </div>




    <!-- Hamburger manu panel -->
    <div id="sidebar">
        <div class="close-btn" onclick="closeMenu()"><img src="./images/crossManu.png" alt=""></div>
        <a href="products.php">Products</a>
        <a href="">Blog</a>
        <a href="">Support</a>
    </div>

    <!-- Dark Overlay -->
    <div id="overlay" onclick="closeMenu()"></div>

    <script>
        function openMenu() {
            document.getElementById('sidebar').classList.add('active');
            document.getElementById('overlay').classList.add('active');
        }

        function closeMenu() {
            document.getElementById('sidebar').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        }

        function openBasket() {
            document.querySelector('.basket-panel').classList.add('open');
            document.querySelector('.basket-overlay').style.display = 'block';
        }

        function closeBasket() {
            document.querySelector('.basket-panel').classList.remove('open');
            document.querySelector('.basket-overlay').style.display = 'none';
        }
        

        // Quantity and Remove buttons
        function updateQuantity(productId, action, itemDiv) {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'update_cart.php', true);
            xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                let data;
                try {
                    data = JSON.parse(xhr.responseText);
                } catch(e) {
                    console.error('AJAX response is not valid JSON:', xhr.responseText);
                    return;
                }

                const quantity = parseInt(data.quantity) || 0;
                const cart_count = parseInt(data.cart_count) || 0;
                const total_price = parseFloat(data.total_price) || 0;

                // Update total price
                const totalPriceEl = document.querySelector('.total-price');
                if (totalPriceEl) totalPriceEl.textContent = '$' + total_price.toLocaleString();

                // Update item quantity or remove
                if (quantity === 0 || action === 'remove') {
                    itemDiv.remove();
                } else {
                    const qtyEl = itemDiv.querySelector('.qty-value');
                    if (qtyEl) qtyEl.textContent = quantity;
                }

                // Update basket count display
                const basketCountEl = document.querySelector('.basket-count');
                if (basketCountEl) basketCountEl.textContent = cart_count + ' items';

                const freeShippingLimit = 40000;

                // Update free shipping text
                const freeBlock = document.querySelector('.free-sheeping-block');
                if (freeBlock) {
                    const successMsg = freeBlock.querySelector('.free-sheeping-success');
                    const defaultMsg = freeBlock.querySelector('.free-sheeping-default');
                    const numInDefMs = freeBlock.querySelector('.free-sheeping-default span');
                    const progressEl = freeBlock.querySelector('progress');

                    if (total_price >= freeShippingLimit) {
                        if (successMsg) successMsg.style.display = 'inline';
                        if (defaultMsg) defaultMsg.style.display = 'none';
                    } else {
                        if (successMsg) successMsg.style.display = 'none';
                        if (defaultMsg) defaultMsg.style.display = 'inline';
                        if (numInDefMs) numInDefMs.textContent = (freeShippingLimit - total_price);
                    }

                    if (progressEl) progressEl.value = total_price;
                }
            };
            xhr.send('product_id=' + productId + '&action=' + action);
        }

        // Event delegation for dynamic items
        document.querySelector('.basket-panel').addEventListener('click', function(e) {
            const btn = e.target.closest('.increase-btn, .decrease-btn, .remove-btn');
            if (!btn) return;

            const itemDiv = btn.closest('.item-details');
            const productId = itemDiv.dataset.id;

            if (btn.classList.contains('increase-btn')) {
                updateQuantity(productId, 'increase', itemDiv);
            } else if (btn.classList.contains('decrease-btn')) {
                updateQuantity(productId, 'decrease', itemDiv);
            } else if (btn.classList.contains('remove-btn')) {
                updateQuantity(productId, 'remove', itemDiv);
            }

        });

        

    </script>
    
