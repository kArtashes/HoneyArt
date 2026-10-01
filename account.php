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
      break;
    }
  }
}

// 🔹 Redirect to login if still not logged in
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
  header("Location: logInReg.php");
  exit();
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid request.');
  }

  $user_id = (int) ($_SESSION['user_id'] ?? 0);

  try {
    $conn->begin_transaction();

    foreach (['cart', 'wishlist', 'recently_viewed'] as $table) {
      $stmt = $conn->prepare("DELETE FROM {$table} WHERE user_id = ?");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $stmt->close();
    }

    $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    session_unset();
    session_destroy();
    setcookie('remember_token', '', time() - 3600, '/');

    header('Location: logInReg.php?account_deleted=1');
    exit();
  } catch (mysqli_sql_exception $e) {
    $conn->rollback();
    $_SESSION['flash_message'] = 'Could not delete the account. Please try again.';
    $_SESSION['flash_type'] = 'error';
    header('Location: account.php');
    exit();
  }
}

// Process profile updates before any HTML is sent, so redirects can set headers.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
  $user_id = $_SESSION['user_id'] ?? 0;
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $address = trim($_POST['address'] ?? '');

  if ($username === '' || $email === '') {
    $_SESSION['flash_message'] = 'Username և Email պարտադիր են։';
    $_SESSION['flash_type'] = 'error';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_message'] = "Email-ի ֆորմատը սխալ է։";
    $_SESSION['flash_type'] = 'error';
  } elseif ($user_id) {
    $updateStmt = $conn->prepare(
      'UPDATE users SET username = ?, email = ?, phone = ?, address = ? WHERE id = ?'
    );
    $updateStmt->bind_param('ssssi', $username, $email, $phone, $address, $user_id);

    try {
      $updateStmt->execute();
      $_SESSION['email'] = $email;
      $_SESSION['flash_message'] = 'Profile updated successfully.';
      $_SESSION['flash_type'] = 'success';
    } catch (mysqli_sql_exception $e) {
      $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
      $_SESSION['flash_type'] = 'error';
    } finally {
      $updateStmt->close();
    }
  }

  header('Location: account.php');
  exit();
}

include('header.php');


// fetch user info
$stmt = $conn->prepare("SELECT id, username, email, phone, `address` FROM users WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();

$userinfo = [
  "id" => "",
  "username" => "",
  "email" => "",
  "phone" => "",
  "address" => "",
];

if ($row = $result->fetch_assoc()) {
  $userinfo["id"] = $row['id'];
  $userinfo['username'] = $row['username'];
  $userinfo['email'] = $row['email'];
  $userinfo['phone'] = $row['phone'];
  $userinfo['address'] = $row['address'];
}
?>
<script>
  const sections = [
    '.dashboard',
    '.orders',
    '.wishlist',
    '.paymantMethods',
    '.accountSettings',
  ];

  function openSection(section) {
    sections.forEach(selector => {
      document.querySelector(selector).style.display = 'none';
    });

    document.querySelector(section).style.display = 'block';
  }
</script>

<div class="container">
  <aside class="sidebar">
    <div class="profile">
      <div class="avatar">👤</div>
      <h3><?php echo htmlspecialchars($userinfo['username']); ?></h3>
      <p><?php echo htmlspecialchars($userinfo['email']); ?></p>
    </div>
    <ul>
      <li onclick="openSection('.dashboard')">Dashboard</li>
      <li onclick="openSection('.orders')">Orders</li>
      <li onclick="openSection('.wishlist')">Wishlist</li>
      <li onclick="openSection('.paymantMethods')">Payment Methods</li>
      <li onclick="openSection('.accountSettings')">Account Settings</li>
      <hr>
      <li class="logout" onclick="window.location.href='logout.php'">Log Out</li>
    </ul>
  </aside>
  <!-- ============================dashboard=================================== -->
  <div class="dashboard">
    <h1>Welcome back, <?php echo $userinfo['username']; ?></h1>
    <span>Here's what's happening with your HoneyART account.</span>
    <div id="dash-viewed">
      <div id="dash-viewed-heading">
        <span>Recently viewed</span>
      </div>
      <div id="dash-viewed-cont">
        <?php
        $userId = $userinfo['id'];

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
            <div class="viewedProduct" onclick="window.location.href='product.php?id=<?php echo $row['id'];
            if ($_SESSION['viewed'][0] = !$row['id']) {
              array_unshift($_SESSION['viewed'], $row['id']);
            } ?>'">
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
              } ?>
              <h2><?php echo htmlspecialchars($row['name']) . " " . $formatted . " kg" ?></h2>
              <div class="price">֏<?php echo number_format($row['price'], 0); ?></div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p>No products found.</p>
        <?php endif; ?>
      </div>
    </div>
    <div id="dash-orders">
      <div id="dash-orders-heading">
        <span>Recent Orders</span>
        <button>View All Orders</button>
      </div>
      <div id="dash-orders-cont">
        <?php
        $sql = "SELECT 
                    orders.id,
                    orders.user_id,
                    orders.product_id,
                    orders.quantity,
                    orders.status,
                    orders.created_at,
                    products.name,
                    products.price,
                    products.`image_data`, 
                    products.`image_type`
                FROM orders
                JOIN products ON orders.product_id = products.id
                WHERE orders.user_id = ?
                ORDER BY orders.created_at DESC
                LIMIT 3";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($order = $result->fetch_assoc()): ?>

          <?php
          $status = strtolower($order['status']);

          switch ($status) {
            case 'delivered':
              $statusClass = 'status-delivered';
              break;

            case 'shipped':
              $statusClass = 'status-shipped';
              break;

            case 'processing':
              $statusClass = 'status-processing';
              break;

            case 'cancelled':
              $statusClass = 'status-cancelled';
              break;

            default:
              $statusClass = 'status-pending';
          }
          ?>

          <div class="order">
            <?php
            if (!empty($order["image_data"])): ?>
              <img src="image.php?id=<?php echo $order['product_id']; ?>"
                alt="<?php echo htmlspecialchars($order['name']); ?>">
            <?php else: ?>
              <img src="default.png" alt="No Image">
            <?php endif; ?>
            <div class="order-info">
              <div class="order-number">
                #<?= htmlspecialchars($order['id']) ?>
                - <?= htmlspecialchars($order['name']) ?>
              </div>

              <div class="order-date">
                <?= date("F j, Y", strtotime($order['created_at'])) ?>
              </div>
            </div>

            <div class="order-status <?= $statusClass ?>">
              <?= htmlspecialchars($order['status']) ?>
            </div>

            <div class="order-price">
              $<?= number_format($order['price'], 2) ?>
            </div>

            <div class="order-arrow">
              ›
            </div>

          </div>

        <?php endwhile; ?>
      </div>
    </div>


    <!-- ===============section 3============================ -->
    <div id="dash-section3">
      <div id="section3-details" class="dash-card">
        <div style="display: flex; justify-content:space-between; width: 100%; margin: 0;">
          <span>Account details</span>
          <?php if (!empty($userinfo)): ?>
            <button onclick="openSection('.accountSettings')">Edit</button>
          <?php endif; ?>
        </div>

        <?php if (!empty($userinfo)): ?>
          <div id="account-details-content">
            <div><span>Full name</span><span><?php echo htmlspecialchars($userinfo['username']); ?></span></div>
            <div><span>Email</span><span><?php echo htmlspecialchars($userinfo['email']); ?></span></div>
            <div><span>Phone</span><span><?php echo htmlspecialchars($userinfo['phone']); ?></span></div>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <img src="icons/empty-account.png" alt="" width="48" height="48">
            <p>No account details to show</p>
            <span>Add your information to keep your account up to date.</span>
            <button onclick="openSection('.accountSettings')">Edit Profile</button>
          </div>
        <?php endif; ?>
      </div>

      <div id="section3-addresses" class="dash-card">
        <div style="display: flex; justify-content:space-between; width: 100%; margin: 0;">
          <span>Saved addresses</span>
          <?php if (!empty($addresses)): ?>
            <button onclick="openSection('.accountSettings')">Manage</button>
          <?php endif; ?>
        </div>

        <?php if (!empty($addresses)): ?>
          <div id="addresses-content">
            <?php foreach ($addresses as $address): ?>
              <div class="address-item">
                <img src="icons/pin.png" alt="" width="20" height="20">
                <div>
                  <span><?php echo htmlspecialchars($address['label']); ?></span>
                  <?php if (!empty($address['isDefault'])): ?>
                    <span class="badge">Default</span>
                  <?php endif; ?>
                  <p>
                    <?php echo htmlspecialchars($address['street']); ?>,
                    <?php echo htmlspecialchars($address['city']); ?><br>
                    <?php echo htmlspecialchars($address['country']); ?><br>
                    <?php echo htmlspecialchars($address['phone']); ?>
                  </p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <img src="images/image.png" alt="" width="48" height="48">
            <p>No saved addresses</p>
            <span>Add an address for a faster checkout experience.</span>
            <button onclick="openSection('.accountSettings')">Add Address</button>
          </div>
        <?php endif; ?>
      </div>

      <div id="section3-paymants" class="dash-card">
        <div style="display: flex; justify-content:space-between; width: 100%; margin: 0;">
          <span>Payment methods</span>
          <?php if (!empty($paymentmethods)): ?>
            <button onclick="openSection('.paymantMethods')">Manage</button>
          <?php endif; ?>
        </div>

        <?php if (!empty($paymentmethods)): ?>
          <div id="payments-content">
            <?php foreach ($paymentmethods as $card): ?>
              <div class="payment-item">
                <img src="icons/<?php echo htmlspecialchars($card['brand']); ?>.png"
                  alt="<?php echo htmlspecialchars($card['brand']); ?>" width="34" height="22">
                <div>
                  <p><?php echo ucfirst(htmlspecialchars($card['brand'])); ?> ending in
                    <?php echo htmlspecialchars($card['lastFour']); ?></p>
                  <span>Expires <?php echo htmlspecialchars($card['expires']); ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <img src="images/image.png" alt="" width="48" height="48">
            <p>No payment methods</p>
            <span>Add a payment method for secure and quick payments.</span>
            <button onclick="openSection('.paymantMethods')">Add Payment Method</button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <!-- ============================orders=================================== -->
  <div class="orders">
    <h1>Orders</h1>
    <script>
      const orderSections = [
        '.all',
        '.processing',
        '.shipped',
        '.delivered',
        '.cancelled',
      ];

      function openOrderSection(section, clickedElement) {
        // Remove activeSec from all menu items
        document.querySelectorAll('.orderSectionsList li')
            .forEach(li => li.classList.remove('activeSec'));

        // Add activeSec to clicked menu item
        clickedElement.classList.add('activeSec');

        // Hide all sections
        orderSections.forEach(selector => {
            document.querySelector(selector).style.display = 'none';
        });

        // Show selected section
        document.querySelector(section).style.display = 'block';
      }
    </script>

    <ul class="orderSectionsList">
      <li class='activeSec' onclick="openOrderSection('.all', this)">All orders</li>
      <li onclick="openOrderSection('.processing', this)">Processing</li>
      <li onclick="openOrderSection('.shipped', this)">Shipped</li>
      <li onclick="openOrderSection('.delivered', this)">Delivered</li>
      <li onclick="openOrderSection('.cancelled', this)">Cancelled</li>
    </ul>
    <?php
      function showOrders($conn, $user_id, $status = null) {

        $sql = "SELECT 
                    orders.id,
                    orders.user_id,
                    orders.product_id,
                    orders.quantity,
                    orders.status,
                    orders.created_at,
                    products.name,
                    products.price,
                    products.image_data,
                    products.image_type
                FROM orders
                JOIN products ON orders.product_id = products.id
                WHERE orders.user_id = ?";
    
        if ($status !== null) {
            $sql .= " AND orders.status = ?";
        }
    
        $sql .= " ORDER BY orders.created_at DESC";
    
        $stmt = $conn->prepare($sql);
    
        if ($status === null) {
            $stmt->bind_param("i", $user_id);
        } else {
            $stmt->bind_param("is", $user_id, $status);
        }
    
        $stmt->execute();
    
        $result = $stmt->get_result();
    
        while ($order = $result->fetch_assoc()) {

          
          $status = strtolower($order['status']);

          switch ($status) {
            case 'delivered':
              $statusClass = 'status-delivered';
              break;

            case 'shipped':
              $statusClass = 'status-shipped';
              break;

            case 'processing':
              $statusClass = 'status-processing';
              break;

            case 'cancelled':
              $statusClass = 'status-cancelled';
              break;

            default:
              $statusClass = 'status-pending';
          }
          
          ?>
            <div class="order">
            <?php
            if (!empty($order["image_data"])): ?>
              <img src="image.php?id=<?php echo $order['product_id']; ?>"
                alt="<?php echo htmlspecialchars($order['name']); ?>">
            <?php else: ?>
              <img src="default.png" alt="No Image">
            <?php endif; ?>
            <div class="order-info">
              <div class="order-number">
                #<?= htmlspecialchars($order['id']) ?>
                - <?= htmlspecialchars($order['name']) ?>
              </div>

              <div class="order-date">
                <?= date("F j, Y", strtotime($order['created_at'])) ?>
              </div>
            </div>

            <div class="order-status <?= $statusClass ?>">
              <?= htmlspecialchars($order['status']) ?>
            </div>

            <div class="order-price">
              $<?= number_format($order['price'], 2) ?>
            </div>

            <div class="order-arrow">
              ›
            </div>

          </div>
          <?php
        }
      }
    ?>
    <div class="all">
    <?php showOrders($conn, $user_id); ?>
</div>

<div class="processing">
    <?php showOrders($conn, $user_id, 'processing'); ?>
</div>

<div class="shipped">
    <?php showOrders($conn, $user_id, 'shipped'); ?>
</div>

<div class="delivered">
    <?php showOrders($conn, $user_id, 'delivered'); ?>
</div>

<div class="cancelled">
    <?php showOrders($conn, $user_id, 'cancelled'); ?>
</div>
  </div>
  <!-- ============================wishlist=================================== -->
  <div class="wishlist">
    <h1>Wishlist</h1>
    <?php
    $conn = new mysqli("localhost", "root", "root", "honey_art_db", 8889);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $user_id = $_SESSION['user_id'] ?? 0;

    $stmt = $conn->prepare("
        SELECT p.*
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC; 
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();?>

    <div id="products">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="product" onclick="window.location.href='product.php?id=<?php echo $row['id']; ?>'">
                    <button 
                        type="button" 
                        class="wishlist-btn active"
                        data-product-id="<?= $row['id'] ?>"
                    >
                        ♥
                    </button>
                        <?php
                        if (!empty($row["image_data"])): ?>
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

    <script>
        document.querySelectorAll(".wishlist-btn").forEach(button => {

        button.addEventListener("click", function(event) {

            event.preventDefault();
            event.stopPropagation();

            const productId = this.dataset.productId;

            fetch("wishlist.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "product_id=" + encodeURIComponent(productId)
            })
            .then(response => response.json())
            .then(data => {

                if (data.success) {

                    if (data.action === "added") {
                        this.textContent = "♥";
                        this.classList.add("active");
                    } 
                    else if (data.action === "removed") {
                        this.closest(".product").remove();
                    }

                } else {
                    alert(data.message);
                }

            })
            .catch(error => {
                console.error(error);
            });

        });

    });
    </script>
  </div>
  <!-- ============================paymantMethods=================================== -->
  <div class="paymantMethods">
    <h1>Payment Methods</h1>
  </div>
  <!-- ============================accountSettngs=================================== -->
  <div class="accountSettings">
    <h1>Account Settings</h1>
    <?php
    $message = '';
    $messageType = '';

    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $messageType = $_SESSION['flash_type'] ?? 'success';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }

    // 1) Profile-ի տվյալները users աղյուսակից
    $stmt = $conn->prepare("SELECT username, email, phone, `address` FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc(); // ասոցիատիվ array՝ մեկ տող
    $stmt->close();

    // Account Summary-ի համար
    $stmt2 = $conn->prepare("
        SELECT COUNT(*) AS total_orders, SUM(products.price) AS total_spent FROM orders INNER JOIN products ON orders.product_id=products.id WHERE user_id = ?
    ");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $summary = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();
    ?>

    <div class="tabs">
      <span>Profile</span><span>Password</span>
    </div>
    <div class="grid">
      <section class="card">
        <h2>Profile Information</h2>
        <form method="POST" action="">
          <label>Full Name
            <input name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
          </label>
          <label>Email
            <input name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
          </label>
          <label>Phone
            <input name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
          </label>
          <label>Address
            <input name="address" type="text" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
          </label>
          <button type="submit" name="save_profile">Save Changes</button>
        </form>
      </section>
      <section class="card">
        <h2>Profile Picture</h2>
        <div class="bigavatar">👤</div>
        <button>Upload Photo</button>
        <h3>Account Summary</h3>
        <p>Member Since: <?php echo htmlspecialchars($user['member_since'] ?? '2026'); ?></p>
        <p>Total Orders: <?php echo (int)($summary['total_orders'] ?? 0); ?></p>
        <p>Total Spent: $<?php echo number_format($summary['total_spent'] ?? 0, 2); ?></p>
      </section>
    </div>
    <section class="card danger">
      <h2>Delete Account</h2>
      <p>This action cannot be undone.</p>
      <form method="POST" onsubmit="return confirm('Are you sure? This action cannot be undone.');">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <button type="submit" name="delete_account" class="dangerbtn">Delete My Account</button>
      </form>
    </section>
  </div>
</div>
