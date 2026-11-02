<?php
// Customer login in one place: starts the session, restores "Remember me", password rules.
// Pages include it before any HTML:  require_once __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

const REMEMBER_COOKIE = 'remember_token';
const REMEMBER_DAYS = 30;
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 72; // the hashing method ignores anything longer

function passwordLengthOk(string $password): bool
{
    $length = strlen($password);

    return $length >= PASSWORD_MIN_LENGTH && $length <= PASSWORD_MAX_LENGTH;
}

// Short fingerprint of the password hash. A session remembers it, so changing the
// password logs out every other device that is still logged in.
function passwordFingerprint(string $passwordHash): string
{
    return substr(hash('sha256', $passwordHash), 0, 16);
}

// $user needs: id, email, password (the hash)
function logInCustomer(array $user): void
{
    // New session id after login, so a pre-set session id can't be hijacked
    session_regenerate_id(true);
    $_SESSION['loggedIn'] = true;
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['password_fingerprint'] = passwordFingerprint($user['password']);
}

function logOutCustomerSession(): void
{
    unset($_SESSION['loggedIn'], $_SESSION['user_id'], $_SESSION['email'], $_SESSION['password_fingerprint']);
}

function setRememberCookie(string $value, int $expires): void
{
    setcookie(REMEMBER_COOKIE, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']), // HTTPS-only once the site has a certificate
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearRememberCookie(): void
{
    setRememberCookie('', time() - 3600);
}

// The cookie holds "selector:validator". The selector finds the user directly;
// the database keeps only a hash of the validator, so a leaked database can't log anyone in.
function rememberCustomer(mysqli $conn, int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $validatorHash = hash('sha256', $validator);

    $stmt = $conn->prepare(
        'UPDATE users
         SET remember_selector = ?, remember_token = ?, remember_expiry = NOW() + INTERVAL ' . REMEMBER_DAYS . ' DAY
         WHERE id = ?'
    );
    $stmt->bind_param('ssi', $selector, $validatorHash, $userId);
    $stmt->execute();
    $stmt->close();

    setRememberCookie($selector . ':' . $validator, time() + REMEMBER_DAYS * 86400);
}

function forgetCustomer(mysqli $conn, int $userId): void
{
    $stmt = $conn->prepare(
        'UPDATE users SET remember_selector = NULL, remember_token = NULL, remember_expiry = NULL WHERE id = ?'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    clearRememberCookie();
}

function restoreRememberedCustomer(mysqli $conn): void
{
    if (($_SESSION['loggedIn'] ?? false) === true || !isset($_COOKIE[REMEMBER_COOKIE])) {
        return;
    }

    $cookie = $_COOKIE[REMEMBER_COOKIE];
    $parts = is_string($cookie) ? explode(':', $cookie, 2) : [];

    if (count($parts) === 2) {
        [$selector, $validator] = $parts;

        $stmt = $conn->prepare(
            'SELECT id, email, password, remember_token FROM users
             WHERE remember_selector = ? AND remember_expiry > NOW()'
        );
        $stmt->bind_param('s', $selector);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && hash_equals((string) $user['remember_token'], hash('sha256', $validator))) {
            logInCustomer($user);
            return;
        }
    }

    // Expired or fake: remove it, so it isn't checked again on every page
    clearRememberCookie();
}

// Ends the session if the account was deleted or its password changed elsewhere
function checkCustomerSession(mysqli $conn): void
{
    if (($_SESSION['loggedIn'] ?? false) !== true) {
        return;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        logOutCustomerSession();
        return;
    }

    $fingerprint = passwordFingerprint($user['password']);
    $sessionFingerprint = $_SESSION['password_fingerprint'] ?? null;

    if ($sessionFingerprint === null) {
        // Session from before this check existed
        $_SESSION['password_fingerprint'] = $fingerprint;
    } elseif (!hash_equals($sessionFingerprint, $fingerprint)) {
        logOutCustomerSession();
    }
}

checkCustomerSession($conn);
restoreRememberedCustomer($conn);
