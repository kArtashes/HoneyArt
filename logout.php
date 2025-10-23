<?php
session_start();

$host = 'localhost';
$username = 'root';
$password = 'root';
$db_name = 'honey_art_db';
$conn = new mysqli($host, $username, $password, $db_name);

if ($conn->connect_error) {
    die('error: ' . $conn->connect_error);
}

// If user is logged in, remove token from DB
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("UPDATE users SET remember_token=NULL, remember_expiry=NULL WHERE id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
}

// Destroy session
session_unset();
session_destroy();

// Delete remember me cookie
setcookie("remember_token", "", time() - 3600, "/");

// Redirect to login page
header("Location: logInReg.php");
exit();
?>
