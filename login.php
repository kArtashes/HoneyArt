<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // safer query
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
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
        } else {
            $error = urlencode("Wrong password. Please try again.");
            header("Location: logInReg.php?error=$error");
            exit();
        }
    } else {
        echo "<script>alert('No user found with this email');</script>";
        echo "<script>window.location.href = 'logInReg.php';</script>";
        exit();
    }
}
