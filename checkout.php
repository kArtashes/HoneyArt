<?php
session_start();
$host = 'localhost';
$username = 'root';
$password = "root";
$db_name = 'honey_art_db';
$conn = new mysqli($host, $username, $password, $db_name);

if ($conn->connect_error) {
    die('error: ' . $conn->connect_error);
}

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? 0;
if(!$user_id){
    die("User not logged in.");
}

// Fetch user info
$user_query = $conn->query("SELECT `username`, `email`, `phone`, `address` FROM users WHERE id = $user_id");
$user = $user_query->fetch_assoc();

// Fetch cart items
$cart_query = $conn->query("
    SELECT products.name, products.price, cart.quantity
    FROM cart
    INNER JOIN products ON cart.product_id = products.id
    WHERE cart.user_id = $user_id
");

$products_list = "";
$total_price = 0;
while($item = $cart_query->fetch_assoc()){
    $line_total = $item['price'] * $item['quantity'];
    $total_price += $line_total;
    $products_list .= $item['name'] . " x " . $item['quantity'] . " = $" . number_format($line_total,2) . "\n";
}

// Free shipping logic
$free_shipping_limit = 40000;
$shipping_status = $total_price >= $free_shipping_limit ? "Free shipping ✅" : "Shipping applies ❌";

// Prepare email body
$message = "New order received!\n\n";
$message .= "User info\n";
$message .= "Name: " . $user['username'] . "\n";
$message .= "Email: " . $user['email'] . "\n";
$message .= "Phone: " . $user['phone'] . "\n";
$message .= "Address: " . $user['address'] . "\n\n";
$message .= "Products:\n" . $products_list . "\n";
$message .= "Total: $" . number_format($total_price,2) . "\n";
$message .= "Shipping: " . $shipping_status . "\n";

// ✅ Use PHPMailer instead of mail()
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // Gmail SMTP
    $mail->SMTPAuth   = true;
    $mail->Username   = 'artashkhachatryan9@gmail.com'; // your Gmail
    $mail->Password   = 'amamdhbquoicjoyb';  // Gmail App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    //Recipients
    $mail->setFrom('yourgmail@gmail.com', 'HoneyArt Orders');
    $mail->addAddress('artashkhachatryan9@gmail.com'); // where you want to receive orders
    $mail->addReplyTo($user['email'], $user['username']);

    // Content
    $mail->isHTML(false); // plain text
    $mail->Subject = "New Order from " . $user['username'];
    $mail->Body    = $message;

    $mail->send();
    echo "Order email sent successfully!";
} catch (Exception $e) {
    echo "Email could not be sent. Error: {$mail->ErrorInfo}";
}

// Clear cart after checkout
$conn->query("DELETE FROM cart WHERE user_id = $user_id");


// Վերադարձ կայք
if (isset($_SERVER['HTTP_REFERER'])) {
    $redirect_url = $_SERVER['HTTP_REFERER'];
} else {
    $redirect_url = 'products.php'; // fallback
}

// Optional: add a message
$redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . 'message=Product added to cart';

header("Location: $redirect_url");
exit();
?>
