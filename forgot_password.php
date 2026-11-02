<?php
// "Forgot your password?": e-mails a one-hour link to reset_password.php.
// The answer is the same for known and unknown e-mails, so it doesn't reveal who has an account.

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/order_mail.php';
require_once __DIR__ . '/login_limiter.php';

const RESET_LINK_MINUTES = 60;

if (empty($_SESSION['reset_csrf_token'])) {
    $_SESSION['reset_csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = requestString($_POST, 'email');

    if (!hash_equals($_SESSION['reset_csrf_token'], requestString($_POST, 'csrf_token'))) {
        $error = 'The form has expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (tooManyLoginAttempts($conn, 'password_reset', $email)) {
        $error = 'Too many requests. Please try again in 15 minutes.';
    } else {
        recordAttempt($conn, 'password_reset', $email);

        $stmt = $conn->prepare('SELECT id, username, email FROM users WHERE email = ?');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            // The link carries the token; the database keeps only its hash
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $userId = (int) $user['id'];

            $stmt = $conn->prepare(
                'UPDATE users
                 SET password_reset_hash = ?, password_reset_expires = NOW() + INTERVAL ' . RESET_LINK_MINUTES . ' MINUTE
                 WHERE id = ?'
            );
            $stmt->bind_param('si', $tokenHash, $userId);
            $stmt->execute();
            $stmt->close();

            queuePasswordResetEmail($conn, $user['username'], $user['email'], SITE_URL . '/reset_password.php?token=' . $token);
        }

        $_SESSION['mail_queued'] = true; // lets this page start the background sending
        $_SESSION['reset_requested'] = true;
        header('Location: forgot_password.php');
        exit;
    }
}

$requested = !empty($_SESSION['reset_requested']);
unset($_SESSION['reset_requested']);

$pageTitle = 'Forgot password';
include 'header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <h1>Forgot your password?</h1>

        <?php if ($requested): ?>
            <p class="login-message success">If an account exists for this email, we have sent a link to choose a new password. It works for 1 hour. Please also check your spam folder.</p>
            <a class="auth-link" href="logInReg.php">Back to log in</a>
            <script>
                // Send the e-mail in the background, so the visitor doesn't wait for Gmail
                fetch('send_order_notifications.php', { method: 'POST', keepalive: true, credentials: 'same-origin' })
                    .catch(() => {});
            </script>
        <?php else: ?>
            <p>Enter your email and we will send you a link to choose a new password.</p>
            <?php if ($error !== ''): ?>
                <p class="login-message error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['reset_csrf_token']); ?>">
                <label for="reset-email">Email</label>
                <input type="email" id="reset-email" name="email" maxlength="255" required autofocus value="<?php echo htmlspecialchars($email); ?>">
                <button type="submit">Send link</button>
            </form>
            <a class="auth-link" href="logInReg.php">Back to log in</a>
        <?php endif; ?>
    </div>
</main>
<?php include 'footer.php'; ?>
