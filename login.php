<?php
require_once __DIR__ . '/auth.php';


require_once __DIR__ . '/login_limiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: logInReg.php');
    exit();
}

$email = requestString($_POST, 'email');
$password = requestString($_POST, 'password', false);

if (tooManyLoginAttempts($conn, 'customer', $email)) {
    header('Location: logInReg.php?error=too_many');
    exit();
}

$stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Check a password even when the e-mail is unknown, so the answer takes the same
// time either way and doesn't reveal which e-mails have an account
$passwordHash = $user['password'] ?? '$2y$10$jS11IVBEIEWkIDMmt4QzpeTYspRHGlyivLblJcMTZqLRS5tMTZXr2';
$passwordOk = password_verify($password, $passwordHash);

if (!$user || !$passwordOk) {
    recordFailedLogin($conn, 'customer', $email);
    // Same message for an unknown e-mail and a wrong password
    header('Location: logInReg.php?error=invalid');
    exit();
}

clearFailedLogins($conn, 'customer', $email);

logInCustomer($user);

// 🔽 Remember Me
if (isset($_POST['remember'])) {
    rememberCustomer($conn, (int) $user['id']);
}

header("Location: account.php");
exit();
