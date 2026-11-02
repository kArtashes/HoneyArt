<?php
// Where the site's secret settings live: a folder OUTSIDE the web root,
// so passwords can't be opened in a browser and never end up in git.
//
// The folder holds up to three files:
//   honeyart-db.php     database login (optional; without it db.php uses the MAMP defaults)
//   honeyart-mail.php   Gmail login for order e-mails
//   honeyart-admin.php  admin panel login (create it with: php admin_set_password.php)
//   honeyart-site.php   the site's address (required on hosting, see SITE_URL below)
//
// The first folder from this list that exists is used:
//   1. the HONEYART_PRIVATE_DIR environment variable (hosting panel, or "SetEnv" in .htaccess)
//   2. a "private" folder next to the web root, e.g. /home/<user>/private on most hosts
//   3. /Applications/MAMP/private on this computer

if (!defined('PRIVATE_DIR')) {
    $privateDirCandidates = array_filter([
        getenv('HONEYART_PRIVATE_DIR') ?: null,
        dirname(__DIR__) . '/private',
        dirname(__DIR__, 2) . '/private',
    ]);

    $privateDir = dirname(__DIR__, 2) . '/private';
    foreach ($privateDirCandidates as $candidate) {
        if (is_dir($candidate)) {
            $privateDir = $candidate;
            break;
        }
    }

    define('PRIVATE_DIR', rtrim($privateDir, '/'));
    unset($privateDirCandidates, $privateDir, $candidate);
}

// The site's public address, used for links inside e-mails. It comes from settings, never
// from the request: a faked Host header could otherwise point password-reset links to another site.
// On hosting, put this in honeyart-site.php:   <?php return ['url' => 'https://your-domain.am'];
if (!defined('SITE_URL')) {
    $siteConfig = is_readable(PRIVATE_DIR . '/honeyart-site.php') ? require PRIVATE_DIR . '/honeyart-site.php' : [];
    define('SITE_URL', rtrim($siteConfig['url'] ?? 'http://localhost:8888/HoneyArt', '/'));
    unset($siteConfig);
}

// Error messages: shown on the page while developing (localhost, 127.0.0.1, or the
// computer's address on the home network when testing from a phone), only written
// to the error log on a real server, so visitors never see file paths or code.
// Force it with the HONEYART_DEBUG environment variable: 1 = show, 0 = hide.
if (!defined('SHOW_ERRORS')) {
    $debugSetting = getenv('HONEYART_DEBUG');
    if ($debugSetting !== false) {
        $showErrors = $debugSetting === '1';
    } else {
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $isPrivateIp = filter_var($serverName, FILTER_VALIDATE_IP) !== false
            && filter_var($serverName, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        $showErrors = PHP_SAPI === 'cli' || $serverName === 'localhost' || $isPrivateIp;
    }

    define('SHOW_ERRORS', $showErrors);
    error_reporting(E_ALL);
    ini_set('display_errors', SHOW_ERRORS ? '1' : '0');
    ini_set('log_errors', '1');
    unset($debugSetting, $serverName, $isPrivateIp, $showErrors);
}

// Security headers for PHP pages (under FastCGI the .htaccess headers reach only static files)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header_remove('X-Powered-By');
}

// Anything unexpected (e.g. a database error): log the details, show the visitor a clean message
set_exception_handler(function (Throwable $e): void {
    error_log('HoneyArt uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo SHOW_ERRORS
        ? '<pre>' . htmlspecialchars((string) $e) . '</pre>'
        : 'Something went wrong. Please try again in a few minutes.';
});

// A text field from a form or link. Odd input such as ?q[]=x counts as empty instead of causing errors.
function requestString(array $source, string $key, bool $trim = true): string
{
    $value = $source[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }

    return $trim ? trim($value) : $value;
}
