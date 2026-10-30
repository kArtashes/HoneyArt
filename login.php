<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

require_once __DIR__ . '/login_limiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: logInReg.php');
    exit();
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if (tooManyLoginAttempts($conn, 'customer', $email)) {
    header('Location: logInReg.php?error=too_many');
    exit();
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
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

// set normal session (new id, so a pre-set session id can't be hijacked)
session_regenerate_id(true);
$_SESSION['loggedIn'] = true;
$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $user['email'];

// 🔽 Remember Me
if (isset($_POST['remember'])) {
    $token = bin2hex(random_bytes(32)); // raw token
    $hashedToken = password_hash($token, PASSWORD_DEFAULT);
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));

    // save hashed token + expiry in DB
    $stmt2 = $conn->prepare("UPDATE users SET remember_token=?, remember_expiry=? WHERE id=?");
    $stmt2->bind_param("ssi", $hashedToken, $expiry, $user['id']);
    $stmt2->execute();

    // set cookie with raw token (secure & httponly)
    setcookie("remember_token", $token, time() + (86400 * 30), "/", "", false, true);
}

header("Location: account.php");
exit();
