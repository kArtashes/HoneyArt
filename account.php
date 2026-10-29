<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
require_once __DIR__ . '/db.php';

// 🔹 Restore session from remember me cookie if not logged in
if (!isset($_SESSION['loggedIn']) && isset($_COOKIE['remember_token'])) {
  $token = $_COOKIE['remember_token'];

  $stmt = $conn->prepare("SELECT * FROM users WHERE remember_expiry > NOW()");
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_assoc()) {
    if (password_verify($token, $row['remember_token'])) {
      // restore session
      session_regenerate_id(true);
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

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$activeSection = 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid request.');
  }

  try {
    $conn->begin_transaction();

    foreach (['cart', 'wishlist', 'recently_viewed', 'user_addresses'] as $table) {
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
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');

  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_message'] = 'Invalid request. Please try again.';
    $_SESSION['flash_type'] = 'error';
  } elseif ($username === '' || $email === '') {
    $_SESSION['flash_message'] = 'Username և Email պարտադիր են։';
    $_SESSION['flash_type'] = 'error';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_message'] = "Email-ի ֆորմատը սխալ է։";
    $_SESSION['flash_type'] = 'error';
  } elseif ($user_id) {
    $updateStmt = $conn->prepare(
      'UPDATE users SET username = ?, email = ? WHERE id = ?'
    );
    $updateStmt->bind_param('ssi', $username, $email, $user_id);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_address'])) {
  $addressId = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT) ?: 0;
  $phone = trim($_POST['phone'] ?? '');
  $city = trim($_POST['city'] ?? '');
  $region = trim($_POST['region'] ?? '');
  $addressLine1 = trim($_POST['address_line1'] ?? '');
  $addressLine2 = trim($_POST['address_line2'] ?? '');
  $notes = trim($_POST['notes'] ?? '');

  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_message'] = 'Invalid request. Please try again.';
    $_SESSION['flash_type'] = 'error';
  } elseif ($phone === '' || $city === '' || $addressLine1 === '') {
    $_SESSION['flash_message'] = 'Phone, city, and address line 1 are required.';
    $_SESSION['flash_type'] = 'error';
  } else {
    try {
      if ($addressId) {
        $stmt = $conn->prepare(
          'UPDATE user_addresses
           SET phone = ?, city = ?, region = ?, address_line1 = ?, address_line2 = ?, notes = ?
           WHERE id = ? AND user_id = ?'
        );
        $stmt->bind_param('ssssssii', $phone, $city, $region, $addressLine1, $addressLine2, $notes, $addressId, $user_id);
      } else {
        $stmt = $conn->prepare(
          'INSERT INTO user_addresses (user_id, phone, city, region, address_line1, address_line2, notes)
           VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issssss', $user_id, $phone, $city, $region, $addressLine1, $addressLine2, $notes);
      }
      $stmt->execute();
      $stmt->close();
      $_SESSION['flash_message'] = $addressId ? 'Address updated successfully.' : 'Address added successfully.';
      $_SESSION['flash_type'] = 'success';
    } catch (mysqli_sql_exception $e) {
      $_SESSION['flash_message'] = 'Could not save the address. Please try again.';
      $_SESSION['flash_type'] = 'error';
    }
  }

  header('Location: account.php');
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_address'])) {
  $addressId = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);

  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '') || !$addressId) {
    $_SESSION['flash_message'] = 'Invalid request. Please try again.';
    $_SESSION['flash_type'] = 'error';
  } else {
    $stmt = $conn->prepare('DELETE FROM user_addresses WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $addressId, $user_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['flash_message'] = 'Address deleted successfully.';
    $_SESSION['flash_type'] = 'success';
  }

  header('Location: account.php');
  exit();
}

$pageTitle = 'My Account';
$pageStyles = ['accountstyle.css'];
include('header.php');


// Fetch the profile and all saved addresses for the current user.
$stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$userinfo = [
  "id" => "",
  "username" => "",
  "email" => "",
];

if ($row = $result->fetch_assoc()) {
  $userinfo["id"] = $row['id'];
  $userinfo['username'] = $row['username'];
  $userinfo['email'] = $row['email'];
}

$addressStmt = $conn->prepare(
  'SELECT id, phone, city, region, address_line1, address_line2, notes
   FROM user_addresses WHERE user_id = ? ORDER BY updated_at DESC, id DESC'
);
$addressStmt->bind_param('i', $user_id);
$addressStmt->execute();
$addresses = $addressStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$addressStmt->close();
$dashboardAddresses = array_slice($addresses, 0, 2);

$editingAddress = null;
$editingAddressId = filter_input(INPUT_GET, 'edit_address', FILTER_VALIDATE_INT);
if ($editingAddressId) {
  $editStmt = $conn->prepare(
    'SELECT id, phone, city, region, address_line1, address_line2, notes
     FROM user_addresses WHERE id = ? AND user_id = ?'
  );
  $editStmt->bind_param('ii', $editingAddressId, $user_id);
  $editStmt->execute();
  $editingAddress = $editStmt->get_result()->fetch_assoc();
  $editStmt->close();
  if ($editingAddress) {
    $activeSection = 'addresses';
  }
}
?>
<script>
  const sections = [
    '.dashboard',
    '.orders',
    '.wishlist',
    '.addresses',
    '.paymantMethods',
    '.accountSettings',
  ];

  function openSection(section) {
    sections.forEach(selector => {
      document.querySelector(selector).style.display = 'none';
    });

    document.querySelector(section).style.display = 'block';

    // Highlight the matching sidebar item
    document.querySelectorAll('.sidebar li[data-section]').forEach(li => {
      li.classList.toggle('active', li.dataset.section === section);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    openSection('.<?php echo htmlspecialchars($activeSection); ?>');
  });
</script>

<div class="container account-page">
  <aside class="sidebar">
    <div class="profile">
      <div class="avatar">👤</div>
      <h3><?php echo htmlspecialchars($userinfo['username']); ?></h3>
      <p><?php echo htmlspecialchars($userinfo['email']); ?></p>
    </div>
    <ul>
      <li data-section=".dashboard" onclick="openSection('.dashboard')">Dashboard</li>
      <li data-section=".orders" onclick="openSection('.orders')">Orders</li>
      <li data-section=".wishlist" onclick="openSection('.wishlist')">Wishlist</li>
      <li data-section=".addresses" onclick="openSection('.addresses')">Addresses</li>
      <li data-section=".paymantMethods" onclick="openSection('.paymantMethods')">Payment Methods</li>
      <li data-section=".accountSettings" onclick="openSection('.accountSettings')">Account Settings</li>
      <hr>
      <li class="logout" onclick="window.location.href='logout.php'">Log Out</li>
    </ul>
  </aside>
  <!-- ============================dashboard=================================== -->
  <div class="dashboard">
    <h1>Welcome back, <?php echo htmlspecialchars($userinfo['username']); ?></h1>
    <?php if ($activeSection === 'dashboard' && isset($_SESSION['flash_message'])): ?>
      <?php
      $dashboardMessage = $_SESSION['flash_message'];
      $dashboardMessageType = $_SESSION['flash_type'] ?? 'success';
      unset($_SESSION['flash_message'], $_SESSION['flash_type']);
      ?>
      <p class="account-flash <?php echo htmlspecialchars($dashboardMessageType); ?>"><?php echo htmlspecialchars($dashboardMessage); ?></p>
    <?php endif; ?>
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
            <div class="viewedProduct" onclick="window.location.href='product.php?id=<?php echo (int) $row['id']; ?>'">
              <?php if (!empty($row["image_data"])): ?>
                <img src="image.php?id=<?php echo $row['id']; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
              <?php else: ?>
                <img src="images/default.webp" alt="No Image">
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
        <button onclick="openSection('.orders')">View All Orders</button>
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
                    orders.unit_price AS price,
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
              <img src="images/default.webp" alt="No Image">
            <?php endif; ?>
            <div class="order-info">
              <div class="order-number">
                #<?= htmlspecialchars($order['id']) ?>
                - <?= htmlspecialchars($order['name']) ?><?= (int) $order['quantity'] > 1 ? ' × ' . (int) $order['quantity'] : '' ?>
              </div>

              <div class="order-date">
                <?= date("F j, Y", strtotime($order['created_at'])) ?>
              </div>
            </div>

            <div class="order-status <?= $statusClass ?>">
              <?= htmlspecialchars($order['status']) ?>
            </div>

            <div class="order-price">
              ֏<?= number_format($order['price'] * $order['quantity'], 0) ?>
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
          <button onclick="openSection('.addresses')">Manage</button>
        </div>

        <?php if (!empty($dashboardAddresses)): ?>
          <div id="addresses-content">
            <?php foreach ($dashboardAddresses as $address): ?>
              <div class="address-item">
                <img src="./images/address.png" alt="" width="20" height="30">
                <div>
                  <p>
                    <?php echo htmlspecialchars($address['address_line1']); ?><br>
                    <?php if (!empty($address['address_line2'])): ?>
                      <?php echo htmlspecialchars($address['address_line2']); ?><br>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($address['city']); ?><?php echo !empty($address['region']) ? ', ' . htmlspecialchars($address['region']) : ''; ?><br>
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
            <button onclick="openSection('.addresses')">Add Address</button>
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
                    orders.unit_price AS price,
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
              <img src="images/default.webp" alt="No Image">
            <?php endif; ?>
            <div class="order-info">
              <div class="order-number">
                #<?= htmlspecialchars($order['id']) ?>
                - <?= htmlspecialchars($order['name']) ?><?= (int) $order['quantity'] > 1 ? ' × ' . (int) $order['quantity'] : '' ?>
              </div>

              <div class="order-date">
                <?= date("F j, Y", strtotime($order['created_at'])) ?>
              </div>
            </div>

            <div class="order-status <?= $statusClass ?>">
              <?= htmlspecialchars($order['status']) ?>
            </div>

            <div class="order-price">
              ֏<?= number_format($order['price'] * $order['quantity'], 0) ?>
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
                            <img src="images/default.webp" alt="No Image">
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
  <!-- ============================addresses=================================== -->
  <div class="addresses">
    <h1>Addresses</h1>
    <?php
    $message = '';
    $messageType = '';
    if ($activeSection === 'addresses' && isset($_SESSION['flash_message'])) {
      $message = $_SESSION['flash_message'];
      $messageType = $_SESSION['flash_type'] ?? 'success';
      unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }
    ?>

    <?php if ($message !== ''): ?>
      <p class="account-flash <?php echo htmlspecialchars($messageType); ?>"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <div class="address-manager">
      <section class="card address-form-card">
        <h2><?php echo $editingAddress ? 'Edit address' : 'Add a new address'; ?></h2>
        <form method="POST" class="address-form">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="address_id" value="<?php echo (int) ($editingAddress['id'] ?? 0); ?>">
          <div class="address-form-grid">
            <label>Phone *
              <input name="phone" required value="<?php echo htmlspecialchars($editingAddress['phone'] ?? ''); ?>">
            </label>
            <label>City *
              <input name="city" required value="<?php echo htmlspecialchars($editingAddress['city'] ?? ''); ?>">
            </label>
            <label>Region
              <input name="region" value="<?php echo htmlspecialchars($editingAddress['region'] ?? ''); ?>">
            </label>
            <label>Address line 1 *
              <input name="address_line1" required value="<?php echo htmlspecialchars($editingAddress['address_line1'] ?? ''); ?>">
            </label>
            <label>Address line 2
              <input name="address_line2" value="<?php echo htmlspecialchars($editingAddress['address_line2'] ?? ''); ?>">
            </label>
            <label class="address-notes">Delivery notes
              <textarea name="notes" rows="3" placeholder="Entrance, floor, delivery instructions..."><?php echo htmlspecialchars($editingAddress['notes'] ?? ''); ?></textarea>
            </label>
          </div>
          <button type="submit" name="save_address"><?php echo $editingAddress ? 'Save address' : 'Add address'; ?></button>
          <?php if ($editingAddress): ?>
            <a class="cancel-edit" href="account.php">Cancel</a>
          <?php endif; ?>
        </form>
      </section>

      <section class="card saved-addresses-card">
        <h2>Saved addresses</h2>
        <?php if ($addresses): ?>
          <?php foreach ($addresses as $address): ?>
            <article class="saved-address-card">
              <div>
                <strong><?php echo htmlspecialchars($address['address_line1']); ?></strong>
                <?php if (!empty($address['address_line2'])): ?>
                  <span><?php echo htmlspecialchars($address['address_line2']); ?></span>
                <?php endif; ?>
                <span><?php echo htmlspecialchars($address['city']); ?><?php echo !empty($address['region']) ? ', ' . htmlspecialchars($address['region']) : ''; ?></span>
                <span><?php echo htmlspecialchars($address['phone']); ?></span>
                <?php if (!empty($address['notes'])): ?>
                  <small><?php echo htmlspecialchars($address['notes']); ?></small>
                <?php endif; ?>
              </div>
              <div class="address-actions">
                <a href="account.php?edit_address=<?php echo (int) $address['id']; ?>">Edit</a>
                <form method="POST" onsubmit="return confirm('Delete this address?');">
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                  <input type="hidden" name="address_id" value="<?php echo (int) $address['id']; ?>">
                  <button type="submit" name="delete_address" class="address-delete">Delete</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="empty-addresses">You have not saved any addresses yet.</p>
        <?php endif; ?>
      </section>
    </div>
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
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc(); // ասոցիատիվ array՝ մեկ տող
    $stmt->close();

    // Account Summary-ի համար
    $stmt2 = $conn->prepare("
        SELECT COUNT(*) AS total_orders, SUM(CASE WHEN status <> 'cancelled' THEN unit_price * quantity ELSE 0 END) AS total_spent FROM orders WHERE user_id = ?
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
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <label>Full Name
            <input name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
          </label>
          <label>Email
            <input name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
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
        <p>Total Spent: ֏<?php echo number_format($summary['total_spent'] ?? 0, 0); ?></p>
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

<?php include('footer.php'); ?>
