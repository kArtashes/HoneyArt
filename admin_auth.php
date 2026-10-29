<?php
// Shared admin helpers: login check and CSRF tokens for admin actions.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Admin credentials live outside the web root (see config.php).
// Create them with:  php admin_set_password.php
define('ADMIN_CONFIG_PATH', PRIVATE_DIR . '/honeyart-admin.php');

function requireAdmin(): void
{
    if (($_SESSION['loggedin'] ?? false) !== true) {
        header('Location: adminlogin.php');
        exit;
    }
}

function adminCsrfToken(): string
{
    if (empty($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

// Stops the request unless it carries this session's admin token,
// so a link on another site can't make a logged-in admin delete things.
function requireAdminCsrf(?string $token): void
{
    if (!is_string($token) || !hash_equals(adminCsrfToken(), $token)) {
        http_response_code(403);
        exit('Invalid request. Please go back and try again.');
    }
}

function adminCheckCredentials(string $username, string $password): bool
{
    if (!is_readable(ADMIN_CONFIG_PATH)) {
        error_log('HoneyArt admin config is missing: ' . ADMIN_CONFIG_PATH);
        return false;
    }

    $config = require ADMIN_CONFIG_PATH;
    $validUsername = $config['username'] ?? '';
    $passwordHash = $config['password_hash'] ?? '';

    if ($validUsername === '' || $passwordHash === '') {
        return false;
    }

    // Verify the password even on a wrong username, so timing doesn't reveal which part was wrong
    $passwordOk = password_verify($password, $passwordHash);

    return hash_equals($validUsername, $username) && $passwordOk;
}
