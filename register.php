<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: logInReg.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$plainPassword = $_POST['password'] ?? '';

if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($plainPassword) < 8) {
    header('Location: logInReg.php?error=register_invalid');
    exit();
}

try {
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $username, $email, $passwordHash);
    $stmt->execute();
    $newUserId = $conn->insert_id;
    $stmt->close();

    session_regenerate_id(true);
    $_SESSION['loggedIn'] = true;
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['email'] = $email;
    header('Location: account.php');
    exit();
} catch (mysqli_sql_exception $e) {
    header('Location: logInReg.php?error=email_taken');
    exit();
}
