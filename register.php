<?php
require_once __DIR__ . '/auth.php';

require_once __DIR__ . '/login_limiter.php';



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: logInReg.php');
    exit();
}

$username = requestString($_POST, 'username');
$email = requestString($_POST, 'email');
$plainPassword = requestString($_POST, 'password', false);

// Limits match the users table: username 60, email 255 characters
if ($username === '' || mb_strlen($username) > 60 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || mb_strlen($email) > 255 || !passwordLengthOk($plainPassword)) {
    header('Location: logInReg.php?error=register_invalid');
    exit();
}

// Stops bots from creating accounts in bulk (10 per IP address in 15 minutes)
if (tooManyLoginAttempts($conn, 'register', $email)) {
    header('Location: logInReg.php?error=too_many');
    exit();
}
recordAttempt($conn, 'register', $email);

$checkStmt = $conn->prepare('SELECT 1 FROM users WHERE email = ?');
$checkStmt->bind_param('s', $email);
$checkStmt->execute();
$emailTaken = $checkStmt->get_result()->num_rows > 0;
$checkStmt->close();

if ($emailTaken) {
    header('Location: logInReg.php?error=email_taken');
    exit();
}

try {
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $username, $email, $passwordHash);
    $stmt->execute();
    $newUserId = $conn->insert_id;
    $stmt->close();

    logInCustomer(['id' => $newUserId, 'email' => $email, 'password' => $passwordHash]);
    header('Location: account.php');
    exit();
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        // Two sign-ups with one e-mail at the same moment
        header('Location: logInReg.php?error=email_taken');
        exit();
    }
    error_log('HoneyArt registration failed: ' . $e->getMessage());
    header('Location: logInReg.php?error=register_failed');
    exit();
}
