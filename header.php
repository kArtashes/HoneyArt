<?php
// Session and login must be ready before any HTML is sent (they fail on servers without output buffering)
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/asset.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <meta name="description" content="HoneyArt: pure, natural Armenian honey from mountain valleys and forests.">
    <link rel="icon" href="images/header_logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=TikTok+Sans:opsz,wght@12..36,300..900&family=Playfair+Display:wght@400;700&family=Cinzel:wght@700&family=Cormorant+Garamond:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('header.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('logInReg.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('footer.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('products.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('product.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('cart.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('support.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('blogs.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('blog.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('search_form.css'); ?>">
    <?php // Page-specific stylesheets: set $pageStyles = ['file.css'] before including header.php ?>
    <?php foreach ($pageStyles ?? [] as $pageStyle): ?>
    <link rel="stylesheet" href="<?php echo asset($pageStyle); ?>">
    <?php endforeach; ?>
    <?php // Each page can set $pageTitle before including header.php ?>
    <title><?php echo htmlspecialchars(!empty($pageTitle) ? $pageTitle . ' | HoneyArt' : 'HoneyArt — Pure Armenian Honey'); ?></title>
</head>
<body>
    <?php if (!empty($_SESSION['basket_flash'])): ?>
        <div id="basket-toast" role="status"><?php echo htmlspecialchars($_SESSION['basket_flash']); ?></div>
        <?php unset($_SESSION['basket_flash']); ?>
    <?php endif; ?>
    <header>
        <div id="header">
            
            <div id="header_menu">
                <div id="section-0">
                    <div id="hamburger" onclick="openMenu()" role="button" aria-label="Open menu"><img src="./images/burgerManu.png" alt=""></div>
                    <div id="mobile-search" role="button" aria-label="Search" onclick="searchShow()"><img src="./images/search-icon.png" alt=""></div>
                </div>

                <div id="section-1">
                    <a href="products.php">Products</a>
                    <a href="blogs.php">Blog</a>
                    <a href="support.php">Support</a>
                </div>
                <a href="home.php" style="text-decoration: none;">
                    <div class="logo">
                        <span class="honey">Honey</span><span class="art">Art</span>
                    </div>
                </a>

                <div id="section-2">
                    <button class="desktop-search" onclick="searchShow()" aria-label="Search"><img id="search-icon" src="./images/search-icon.png" alt=""></button>
                    <button class="basket-icon" onclick="openBasket()" aria-label="Open basket"><img id="basket-icon" src="./images/basket.png" alt=""></button>
                    <button onclick="window.location.href='account.php'" aria-label="My account"><img id="account-icon" src="./images/personalAccount.png" alt=""></button>
                </div>
            </div>
        </div>
    </header>


    <!-- Overlay -->
    <div class="basket-overlay" onclick="closeBasket()"></div>

    <!-- Basket Panel -->
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
                <span class="free-sheeping-default" style="display:none;">You are ֏<span><?php echo number_format($amount_left); ?></span> away from free shipping.</span>
            <?php else: ?>
                <span class="free-sheeping-success" style="display:none;">Congratulations! Your order qualifies for free shipping</span>
                <span class="free-sheeping-default">You are ֏<span><?php echo number_format($amount_left); ?></span> away from free shipping.</span>
            <?php endif; ?>

            <progress value="<?php echo $total_price; ?>" max="<?php echo $free_shipping_limit; ?>"></progress>
        </div>
        
        <!-- ==================================== -->
        
        <?php 
            include('cart.php');
        ?>
        <div class="basket-footer">
            <div class="cart-summary"><span>Subtotal</span> <span class="total-price">֏<?php echo number_format($total_price, 0); ?></span></div>            


            <form action="checkout.php" method="get" id="checkout-form">
                <button type="submit" class="checkout-btn">CHECK OUT</button>
            </form>
        </div>
    </div>




    <!-- Hamburger manu panel -->
    <div id="sidebar">
        <div class="close-btn" onclick="closeMenu()"><img src="./images/crossManu.png" alt=""></div>
        <a href="products.php">Products</a>
        <a href="blogs.php">Blog</a>
        <a href="support.php">Support</a>
        <a href="aboutUs.php">About us</a>
    </div>

    <!-- Dark Overlay -->
    <div id="overlay" onclick="closeMenu()"></div>


    <?php include('search_form.php');?>



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
        

        function showBasketToast(text) {
            const oldToast = document.getElementById('basket-toast');
            if (oldToast) oldToast.remove();
            const toast = document.createElement('div');
            toast.id = 'basket-toast';
            toast.setAttribute('role', 'status');
            toast.textContent = text;
            document.body.appendChild(toast);
        }

        // Subtotal, item count and the free-shipping line of the basket panel
        function updateBasketTotals(cart_count, total_price) {
            const totalPriceEl = document.querySelector('.total-price');
            if (totalPriceEl) totalPriceEl.textContent = '֏' + total_price.toLocaleString('en-US');

            const basketCountEl = document.querySelector('.basket-count');
            if (basketCountEl) basketCountEl.textContent = cart_count + ' items';

            const freeShippingLimit = 40000;
            const freeBlock = document.querySelector('.free-sheeping-block');
            if (!freeBlock) return;

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
                if (numInDefMs) numInDefMs.textContent = (freeShippingLimit - total_price).toLocaleString('en-US');
            }

            if (progressEl) progressEl.value = total_price;
        }

        // "Quick add" / "Add to basket" without leaving the page.
        // If this fails for any reason, the form is simply sent the normal way.
        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (form.getAttribute('action') !== 'add_to_cart.php') return;
            event.preventDefault();

            const button = form.querySelector('button');
            if (button) button.disabled = true;

            fetch('add_to_cart.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'fetch' },
                credentials: 'same-origin',
                body: new FormData(form)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.login_required) {
                        window.location.href = 'logInReg.php?error=login_required';
                        return;
                    }
                    showBasketToast(data.message);
                    if (data.basket_html) {
                        document.getElementById('basket-products').outerHTML = data.basket_html;
                        updateBasketTotals(data.cart_count, data.total_price);
                    }
                })
                .catch(() => form.submit())
                .finally(() => { if (button) button.disabled = false; });
        });

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

                if (data.stock_limit) {
                    showBasketToast('No more in stock');
                }

                const quantity = parseInt(data.quantity) || 0;

                updateBasketTotals(parseInt(data.cart_count) || 0, parseFloat(data.total_price) || 0);

                // Update item quantity or remove
                if (quantity === 0 || action === 'remove') {
                    itemDiv.closest('.basket-item').remove();
                } else {
                    const qtyEl = itemDiv.querySelector('.qty-value');
                    if (qtyEl) qtyEl.textContent = quantity;
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
    
