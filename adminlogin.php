<?php
require __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/login_limiter.php';

// Check if already logged in
if (($_SESSION["loggedin"] ?? false) === true) {
    header("Location: admin_add_product.php");
    exit;
}

// Check submitted login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = (string) ($_POST["username"] ?? '');
    $password = (string) ($_POST["password"] ?? '');

    if (tooManyLoginAttempts($conn, 'admin', $username)) {
        $error = "Too many attempts. Please wait 15 minutes and try again.";
    } elseif (adminCheckCredentials($username, $password)) {
        clearFailedLogins($conn, 'admin', $username);
        // New session id after login, so a pre-set session id can't be hijacked
        session_regenerate_id(true);
        $_SESSION["loggedin"] = true;
        header("Location: admin_add_product.php");
        exit;
    } else {
        recordFailedLogin($conn, 'admin', $username); // also waits 1 second
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Login</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f8f8f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        form {
            background: #fff;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        input {
            display: block;
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 3px;
            border: 1px solid #ccc;
        }
        button {
            background: #d38f12;
            color: #fff;
            border: none;
            padding: 10px;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            background: #b3740d;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <form method="post">
        <h2>Admin Login</h2>
        <?php if (!empty($error)) echo "<div class='error'>" . htmlspecialchars($error) . "</div>"; ?>
        <input type="text" name="username" placeholder="Username" required />
        <input type="password" name="password" placeholder="Password" required />
        <button type="submit">Login</button>
    </form>
</body>
</html>
