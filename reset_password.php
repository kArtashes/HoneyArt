<?php
// Opened from the e-mail sent by forgot_password.php: lets the customer choose a new password.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/login_limiter.php';

// The address holds a secret token: don't send it to other sites or list it in search engines
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');

$token = requestString($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET, 'token');
$user = null;

if ($token !== '') {
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare(
        'SELECT id, email FROM users WHERE password_reset_hash = ? AND password_reset_expires > NOW()'
    );
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$error = '';

if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = requestString($_POST, 'password', false);
    $confirm = requestString($_POST, 'password_confirm', false);

    if (!passwordLengthOk($password)) {
        $error = 'The password must be ' . PASSWORD_MIN_LENGTH . ' to ' . PASSWORD_MAX_LENGTH . ' characters long.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $userId = (int) $user['id'];

        // The link works once. "Remember me" on other devices stops too, and their
        // sessions end by themselves because the password changed (auth.php).
        $stmt = $conn->prepare(
            'UPDATE users
             SET password = ?, password_reset_hash = NULL, password_reset_expires = NULL,
                 remember_selector = NULL, remember_token = NULL, remember_expiry = NULL
             WHERE id = ?'
        );
        $stmt->bind_param('si', $passwordHash, $userId);
        $stmt->execute();
        $stmt->close();

        clearFailedLogins($conn, 'customer', $user['email']);
        clearRememberCookie();
        logOutCustomerSession();
        session_regenerate_id(true);

        header('Location: logInReg.php?notice=password_reset');
        exit;
    }
}

$pageTitle = 'Choose a new password';
include 'header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <?php if (!$user): ?>
            <h1>This link doesn't work</h1>
            <p>It has expired or was already used. Reset links work once, for 1 hour.</p>
            <a class="auth-link" href="forgot_password.php">Send a new link</a>
        <?php else: ?>
            <h1>Choose a new password</h1>
            <?php if ($error !== ''): ?>
                <p class="login-message error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST" class="auth-form">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <label for="new-password">New password (<?php echo PASSWORD_MIN_LENGTH; ?> to <?php echo PASSWORD_MAX_LENGTH; ?> characters)</label>
                <input type="password" id="new-password" name="password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo PASSWORD_MAX_LENGTH; ?>" autocomplete="new-password" required autofocus>
                <label for="new-password-confirm">Repeat the new password</label>
                <input type="password" id="new-password-confirm" name="password_confirm" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo PASSWORD_MAX_LENGTH; ?>" autocomplete="new-password" required>
                <button type="submit">Save new password</button>
            </form>
        <?php endif; ?>
    </div>
</main>
<?php include 'footer.php'; ?>
