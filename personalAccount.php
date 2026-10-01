<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$host = 'localhost';
$username = 'root';
$password = "root";
$db_name = 'honey_art_db';
$conn = new mysqli($host, $username, $password, $db_name);

if ($conn->connect_error) {
    die('error: ' . $conn->connect_error);
}

// 🔹 Restore session from remember me cookie if not logged in
if (!isset($_SESSION['loggedIn']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_expiry > NOW()");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if (password_verify($token, $row['remember_token'])) {
            // restore session
            $_SESSION['loggedIn'] = true;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];

            var_dump($_SESSION);

            break;
        }
    }
}

// 🔹 Redirect to login if still not logged in
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
    header("Location: logInReg.php");
    exit();
}

include("header.php");

// fetch user info
$stmt = $conn->prepare("SELECT username, email, phone, `address` FROM users WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();

$userinfo = [
    "username" => "",
    "email" => "",
    "phone" => "",
    "address" => "",
];

if ($row = $result->fetch_assoc()) {
    $userinfo['username'] = $row['username'];
    $userinfo['email'] = $row['email'];
    $userinfo['phone'] = $row['phone'];
    $userinfo['address'] = $row['address'];
} 

?>
<div class="layout">
    <aside class="sidebar">
        <div class="profile">
        <div class="avatar">A</div>
        <div>
            <div class="profile-name"><?php echo htmlspecialchars($userinfo['username']); ?></div>
            <div class="profile-email"><?php echo htmlspecialchars($userinfo['email']); ?></div>
        </div>
        </div>
        <ul>
        <li class="active"><span class="ic" onclick="openSection('.dashboard')">&#8962;</span> Dashboard</li>
        <li><span class="ic" onclick="openSection('.wishlist')">&#128230;</span> Orders</li>
        <li><span class="ic" onclick="openSection('.addresses')">&#9825;</span> Wishlist</li>
        <li><span class="ic" onclick="openSection('.addresses')">&#128205;</span> Addresses</li>
        <li><span class="ic" onclick="openSection('.paymantMethods')">&#128179;</span> Payment Methods</li>
        <li><span class="ic" onclick="openSection('.accountSettings')">&#9881;</span> Account Settings</li>
        <li onclick="openSection('.notifications')">Notifications</li>
        <li class="logout" onclick="window.location.href='logout.php'"><span class="ic">&#8594;</span> Log Out</li>
        </ul>
    </aside>

    <script>
        const sections = [
            '.dashboard',
            '.orders',
            '.wishlist',
            '.addresses',
            '.paymantMethods',
            '.accountSettings',
            '.notifications'
        ];

        function openSection(section) {
            sections.forEach(selector => {
                document.querySelector(selector).style.display = 'none';
            });

            document.querySelector(section).style.display = 'block';
        }
    </script>


    <main class="main">
        <!-- ============================dashboard=================================== -->
         <div class="dashboard">
        <section class="hero">
            <h1>Welcome back, Anahit!</h1>
            <p>Here's what's happening with your HoneyART account.</p>
            </section>

            <section class="row-2col">
            <div class="card">
                <div class="card-head">
                <h2>Recently Viewed</h2>
                <a href="#">See All</a>
                </div>
                <div class="rv-grid">
                <?php 
                            $userId = $_SESSION['user_id'];

                            $sql = "
                            SELECT p.*
                            FROM products p
                            JOIN recently_viewed rv ON p.id = rv.product_id
                            WHERE rv.user_id = ?
                            ORDER BY rv.viewed_at DESC
                            LIMIT 5
                            ";
                            
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $userId);
                            $stmt->execute();
                            
                            $result = $stmt->get_result();
                    ?>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <div class="rv-item" onclick="window.location.href='product.php?id=<?php echo $row['id']; if($_SESSION['viewed'][0]=!$row['id']){array_unshift($_SESSION['viewed'], $row['id']);} ?>'">
                                <?php if (!empty($row["image_data"])): ?>
                                    <img src="image.php?id=<?php echo $row['id']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                                <?php else: ?>
                                    <img src="default.png" alt="No Image">
                                <?php endif; ?>
                                <?php
                                $weight = $row['weight_kg'];

                                if (floor($weight) == $weight) {
                                    // Integer → no decimals
                                    $formatted = number_format($weight, 0);
                                } else {
                                    // Float → 1 decimal
                                    $formatted = number_format($weight, 1);
                                }?>
                                <h2><?php echo htmlspecialchars($row['name']) ." ". $formatted . " kg" ?></h2>
                                <div class="price">֏<?php echo number_format($row['price'], 0); ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No products found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                <h2>Recent Orders</h2>
                <a href="#">View All Orders</a>
                </div>

                <div class="order-row">
                <div>
                    <div class="order-id">#HA-2024-1025</div>
                    <div class="order-date">May 20, 2024</div>
                </div>
                <div class="order-right">
                    <span class="badge delivered">Delivered</span>
                    <span class="order-total">$28.50</span>
                    <span class="chev">&#8250;</span>
                </div>
                </div>

                <div class="order-row">
                <div>
                    <div class="order-id">#HA-2024-0987</div>
                    <div class="order-date">May 12, 2024</div>
                </div>
                <div class="order-right">
                    <span class="badge shipped">Shipped</span>
                    <span class="order-total">$15.00</span>
                    <span class="chev">&#8250;</span>
                </div>
                </div>

                <div class="order-row">
                <div>
                    <div class="order-id">#HA-2024-0954</div>
                    <div class="order-date">May 5, 2024</div>
                </div>
                <div class="order-right">
                    <span class="badge processing">Processing</span>
                    <span class="order-total">$22.00</span>
                    <span class="chev">&#8250;</span>
                </div>
                </div>

                <div class="order-row">
                <div>
                    <div class="order-id">#HA-2024-0911</div>
                    <div class="order-date">Apr 28, 2024</div>
                </div>
                <div class="order-right">
                    <span class="badge cancelled">Cancelled</span>
                    <span class="order-total">$9.50</span>
                    <span class="chev">&#8250;</span>
                </div>
                </div>
            </div>
            </section>

            <section class="card">
            <div class="track-top">
                <div>
                <div class="order-id">#HA-2024-0987 <span class="badge shipped">Shipped</span></div>
                <div class="track-meta">Order Date: <b>May 12, 2024</b> &nbsp;&nbsp; Total: <b>$15.00</b></div>
                </div>
                <a href="#" style="font-size:13px;color:var(--gold-dark);font-weight:600;">Track Another Order</a>
            </div>

            <div class="steps">
                <div class="step done">
                <div class="dot">&#10003;</div>
                <div class="step-label">Order Placed</div>
                <div class="step-date">May 12</div>
                </div>
                <div class="step-line done"></div>
                <div class="step current">
                <div class="dot">&#128666;</div>
                <div class="step-label">Shipped</div>
                <div class="step-date">May 14</div>
                </div>
                <div class="step-line"></div>
                <div class="step">
                <div class="dot">&#128666;</div>
                <div class="step-label">Out for Delivery</div>
                <div class="step-date">&nbsp;</div>
                </div>
                <div class="step-line"></div>
                <div class="step">
                <div class="dot">&#8962;</div>
                <div class="step-label">Delivered</div>
                <div class="step-date">&nbsp;</div>
                </div>
            </div>
            </section>

            <section class="row-3col">
            <div class="card">
                <div class="card-head">
                <h2>Account Details</h2>
                <a href="#">Edit</a>
                </div>
                <div class="info-line"><span class="label">Full Name</span><span class="value">Anahit Martirosyan</span></div>
                <div class="info-line"><span class="label">Email</span><span class="value">anahit.m@mail.com</span></div>
                <div class="info-line"><span class="label">Phone</span><span class="value">+374 55 123456</span></div>
                <div class="info-line"><span class="label">Member Since</span><span class="value">April 15, 2024</span></div>
            </div>

            <div class="card">
                <div class="card-head">
                <h2>Saved Addresses</h2>
                <a href="#">Manage</a>
                </div>
                <div class="addr-head">
                <div>
                    <div class="addr-name">&#128205; Home</div>
                </div>
                <span class="default-tag">Default</span>
                </div>
                <div class="addr-text">
                Sayat-Nova 12, Yerevan 0001<br>
                Armenia<br>
                +374 55 123456
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                <h2>Payment Methods</h2>
                <a href="#">Manage</a>
                </div>
                <div class="card-item">
                <div class="pm-details">
                    <span class="pm-brand pm-visa">VISA</span>
                    <div>
                    <div class="pm-name">Visa ending in 4242</div>
                    <div class="pm-exp">Expires 12/26</div>
                    </div>
                </div>
                </div>
                <div class="card-item">
                <div class="pm-details">
                    <span class="pm-brand pm-mc">MC</span>
                    <div>
                    <div class="pm-name">Mastercard ending in 8888</div>
                    <div class="pm-exp">Expires 09/25</div>
                    </div>
                </div>
                </div>
            </div>
            </section>

            <section class="perks">
            <div class="perk-group">
                <div class="perk">
                <div class="ic">&#128666;</div>
                <div>
                    <div class="perk-title">Free Shipping</div>
                    <div class="perk-sub">On orders over $50</div>
                </div>
                </div>
                <div class="perk">
                <div class="ic">&#127807;</div>
                <div>
                    <div class="perk-title">100% Natural</div>
                    <div class="perk-sub">Pure &amp; Raw Honey</div>
                </div>
                </div>
                <div class="perk">
                <div class="ic">&#128274;</div>
                <div>
                    <div class="perk-title">Secure Payment</div>
                    <div class="perk-sub">100% Protected</div>
                </div>
                </div>
            </div>
            <div class="help-box">
                <div>
                <div class="perk-title">Need Help?</div>
                <div class="perk-sub">Our support team is here for you.</div>
                </div>
                <button class="btn">Contact Support</button>
            </div>
            </section>
        <div class="dashboard">
                <h1>Welcome back, <?php echo $userinfo['username']; ?></h1>
                <span>Here's what's happening with your HoneyART account.</span>
                <div id="dash1">
                    <div id="dash-viewed">
                    <?php 
                            $userId = $_SESSION['user_id'];

                            $sql = "
                            SELECT p.*
                            FROM products p
                            JOIN recently_viewed rv ON p.id = rv.product_id
                            WHERE rv.user_id = ?
                            ORDER BY rv.viewed_at DESC
                            LIMIT 5
                            ";
                            
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("i", $userId);
                            $stmt->execute();
                            
                            $result = $stmt->get_result();
                    ?>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <div class="product" onclick="window.location.href='product.php?id=<?php echo $row['id']; if($_SESSION['viewed'][0]=!$row['id']){array_unshift($_SESSION['viewed'], $row['id']);} ?>'">
                                <?php if (!empty($row["image_data"])): ?>
                                    <img src="image.php?id=<?php echo $row['id']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                                <?php else: ?>
                                    <img src="default.png" alt="No Image">
                                <?php endif; ?>
                                <?php
                                $weight = $row['weight_kg'];

                                if (floor($weight) == $weight) {
                                    // Integer → no decimals
                                    $formatted = number_format($weight, 0);
                                } else {
                                    // Float → 1 decimal
                                    $formatted = number_format($weight, 1);
                                }?>
                                <h2><?php echo htmlspecialchars($row['name']) ." ". $formatted . " kg" ?></h2>
                                <div class="price">֏<?php echo number_format($row['price'], 0); ?></div>
                                <form method="POST" action="add_to_cart.php">
                                    <input type="hidden" name="product_id" value="<?php echo $row['id'] ?>">
                                    <button type="submit">Quick add</button>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No products found.</p>
                    <?php endif; ?>
                    </div>
                    <div id="dash-orders">
                        <span>Recent Orders</span>
                        <button>View All Orders</button>
                    </div>
                </div>
            </div>
            </div>
        <!-- ============================orders=================================== -->
            <div class="orders">
                <h1>Orders</h1>
            </div>
        <!-- ============================wishlist=================================== -->
            <div class="wishlist">
                <h1>Wishlist</h1>
            </div>
        <!-- ============================addresses=================================== -->
            <div class="addresses">
                <h1>Addresses</h1>
            </div>
        <!-- ============================paymantMethods=================================== -->
            <div class="paymantMethods">
                <h1>Payment Methods</h1>
            </div>
        <!-- ============================accountSettngs=================================== -->
            <div class="accountSettings">
                <h1>Account Settings</h1>
                <div class="tabs"><span>Profile</span><span>Password</span><span>Email</span><span>Privacy</span>
                </div>
                <div class="grid">
                    <section class="card">
                        <h2>Profile Information</h2>
                        <label>Full Name<input value="Artash Khachatryan"></label>
                        <label>Email<input value="artash@email.com"></label>
                        <label>Phone<input value="+374"></label>
                        <label>Birthday<input type="date"></label>
                        <button>Save Changes</button>
                    </section>
                    <section class="card">
                        <h2>Profile Picture</h2>
                        <div class="bigavatar">👤</div>
                        <button>Upload Photo</button>
                        <h3>Account Summary</h3>
                        <p>Member Since: 2026</p>
                        <p>Total Orders: 12</p>
                        <p>Total Spent: $350</p>
                    </section>
                </div>
                <section class="card danger">
                    <h2>Delete Account</h2>
                    <p>This action cannot be undone.</p>
                    <button class="dangerbtn">Delete My Account</button>
                </section>
            </div>
        <!-- =======================notifications======================================== -->
            <div class="notifications">
                <h1>Notifications</h1>
            </div>
        </main>
    </div>
</div>

<?php include('footer.php'); ?>


<style>
    :root{
    --ink:#1c1a16;
    --header:#181511;
    --gold:#c8871e;
    --gold-dark:#a8700f;
    --gold-soft:#f5e2bd;
    --cream:#faf6ee;
    --card:#ffffff;
    --line:#eee6d6;
    --muted:#7a7368;
    --green-bg:#e4f3e6; --green-fg:#2c7a3d;
    --amber-bg:#fbe9cf; --amber-fg:#a06612;
    --blue-bg:#e6eefb; --blue-fg:#2f5fb5;
    --gray-bg:#eeece7; --gray-fg:#847c6c;
    --radius:14px;
    font-family:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  body{background:var(--cream); color:var(--ink);}
  a{text-decoration:none; color:inherit;}
  ul{list-style:none;}

  /* Header */
  .topbar{
    background:var(--header);
    display:flex; align-items:center; justify-content:space-between;
    padding:18px 40px;
  }
  .topbar nav{display:flex; gap:28px;}
  .topbar nav a{color:#efe9db; font-size:15px; opacity:.85;}
  .topbar nav a:hover{opacity:1;}
  .brand{
    font-family:Georgia,'Times New Roman',serif;
    font-size:26px; letter-spacing:.5px; color:var(--gold-soft);
  }
  .brand b{color:var(--gold);}
  .topbar .icons{display:flex; gap:22px; color:#efe9db;}

  /* Layout */
  .layout{
    display:grid;
    grid-template-columns:270px 1fr;
    gap:24px;
    max-width:1500px;
    margin:24px auto;
    padding:0 24px;
    align-items:start;
  }

  /* Sidebar */
  .sidebar{
    background:var(--card);
    border-radius:var(--radius);
    padding:22px;
    border:1px solid var(--line);
  }
  .profile{display:flex; gap:12px; align-items:center; padding-bottom:16px; border-bottom:1px solid var(--line); margin-bottom:12px;}
  .avatar{
    width:44px; height:44px; border-radius:50%;
    background:var(--header); color:#fff;
    display:flex; align-items:center; justify-content:center;
    font-size:18px; flex-shrink:0;
  }
  .profile-name{font-weight:600; font-size:15px;}
  .profile-email{font-size:12.5px; color:var(--muted);}
  .profile-edit{font-size:12.5px; color:var(--gold-dark); font-weight:600;}

  .sidebar li{
    display:flex; align-items:center; gap:12px;
    padding:11px 12px; border-radius:10px;
    font-size:14.5px; color:#4b463c; cursor:pointer;
    margin-bottom:2px;
  }
  .sidebar li:hover{background:#faf3e4;}
  .sidebar li.active{background:var(--gold-soft); color:var(--gold-dark); font-weight:600;}
  .sidebar li .ic{width:18px; text-align:center; opacity:.85;}
  .sidebar .logout{color:#c0392b; margin-top:8px; border-top:1px solid var(--line); padding-top:14px;}

  /* Main column */
  .main{display:flex; flex-direction:column; gap:20px;}

  .hero{
    background:linear-gradient(120deg,#3a2c14,#5c4520 55%, #82601f);
    border-radius:var(--radius);
    padding:34px 40px;
    color:#fff5e2;
    position:relative;
    overflow:hidden;
  }
  .hero h1{font-family:Georgia,serif; font-size:30px; margin-bottom:8px;}
  .hero p{color:#e9d8b3; font-size:14.5px;}

  .card{
    background:var(--card);
    border:1px solid var(--line);
    border-radius:var(--radius);
    padding:22px 24px;
  }
  .card-head{
    display:flex; justify-content:space-between; align-items:center;
    margin-bottom:18px;
  }
  .card-head h2{font-size:17px; font-weight:700;}
  .card-head a{font-size:13px; color:var(--gold-dark); font-weight:600;}

  .row-2col{
    display:grid; grid-template-columns:1.15fr 1fr; gap:20px;
  }

  /* Recently viewed */
  .rv-grid{display:grid; grid-template-columns:repeat(2,1fr); gap:16px;}
  .rv-item img{width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:10px; margin-bottom:8px; background:#f1ead9;}
  .rv-item .name{font-size:13.5px; font-weight:600;}
  .rv-item .price{font-size:13px; color:var(--muted); margin-top:2px;}

  /* Orders */
  .order-row{
    display:flex; justify-content:space-between; align-items:center;
    padding:13px 0; border-bottom:1px solid var(--line);
  }
  .order-row:last-child{border-bottom:none;}
  .order-id{font-size:14px; font-weight:700;}
  .order-date{font-size:12.5px; color:var(--muted); margin-top:2px;}
  .badge{padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600;}
  .badge.delivered{background:var(--green-bg); color:var(--green-fg);}
  .badge.shipped{background:var(--amber-bg); color:var(--amber-fg);}
  .badge.processing{background:var(--blue-bg); color:var(--blue-fg);}
  .badge.cancelled{background:var(--gray-bg); color:var(--gray-fg);}
  .order-right{display:flex; align-items:center; gap:16px;}
  .order-total{font-weight:700; font-size:14px;}
  .chev{color:var(--muted);}

  /* Tracking */
  .track-top{display:flex; justify-content:space-between; align-items:center; margin-bottom:26px; flex-wrap:wrap; gap:10px;}
  .track-meta{font-size:13px; color:var(--muted);}
  .track-meta b{color:var(--ink);}
  .steps{display:flex; align-items:center;}
  .step{display:flex; flex-direction:column; align-items:center; gap:8px; flex:1; text-align:center;}
  .step .dot{
    width:44px; height:44px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    background:var(--gray-bg); color:#b6ae9c; font-size:18px;
  }
  .step.done .dot, .step.current .dot{background:var(--gold); color:#fff;}
  .step-label{font-size:13px; font-weight:600;}
  .step-date{font-size:12px; color:var(--muted);}
  .step-line{flex:1; height:2px; background:var(--gray-bg); margin-bottom:34px;}
  .step-line.done{background:var(--gold);}

  /* Info cards */
  .row-3col{display:grid; grid-template-columns:repeat(3,1fr); gap:20px;}
  .info-line{display:flex; justify-content:space-between; padding:8px 0; font-size:13.5px;}
  .info-line .label{color:var(--muted);}
  .info-line .value{font-weight:600; text-align:right;}
  .addr-name{font-weight:700; font-size:14px; margin-bottom:4px;}
  .addr-text{font-size:13px; color:var(--muted); line-height:1.5;}
  .default-tag{background:var(--gray-bg); color:var(--gray-fg); font-size:11px; padding:3px 9px; border-radius:20px; font-weight:600;}
  .addr-head{display:flex; justify-content:space-between; align-items:flex-start;}
  .card-item{padding:12px 0; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center;}
  .card-item:last-child{border:none;}
  .pm-brand{font-weight:800; font-size:12px; padding:6px 8px; border-radius:5px; color:#fff;}
  .pm-visa{background:#1a1f71;}
  .pm-mc{background:#d0021b;}
  .pm-details{display:flex; gap:10px; align-items:center;}
  .pm-name{font-size:13.5px; font-weight:600;}
  .pm-exp{font-size:12px; color:var(--muted);}

  /* Perks strip */
  .perks{
    display:flex; align-items:center; justify-content:space-between;
    background:var(--card); border:1px solid var(--line); border-radius:var(--radius);
    padding:20px 28px; flex-wrap:wrap; gap:20px;
  }
  .perk-group{display:flex; gap:26px; flex-wrap:wrap;}
  .perk{display:flex; gap:12px; align-items:center;}
  .perk .ic{
    width:38px; height:38px; border-radius:10px; background:var(--gold-soft); color:var(--gold-dark);
    display:flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0;
  }
  .perk-title{font-size:13.5px; font-weight:700;}
  .perk-sub{font-size:12px; color:var(--muted);}
  .help-box{
    display:flex; align-items:center; gap:16px;
    background:var(--gold-soft); padding:12px 18px; border-radius:12px;
  }
  .btn{
    background:transparent; border:1.5px solid var(--gold-dark); color:var(--gold-dark);
    font-weight:700; font-size:13px; padding:9px 18px; border-radius:9px; cursor:pointer;
  }
  .btn:hover{background:var(--gold-dark); color:#fff;}







  .orders, .wishlist, .addresses, .paymantMethods, .accountSettings, .notifications{
    display: none;
}

  @media(max-width:960px){
    .layout{grid-template-columns:1fr;}
    .row-2col, .row-3col{grid-template-columns:1fr;}
    .rv-grid{grid-template-columns:repeat(2,1fr);}
    .steps{flex-wrap:wrap;}
  }

</style>
