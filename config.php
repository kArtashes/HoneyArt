<?php
// Where the site's secret settings live: a folder OUTSIDE the web root,
// so passwords can't be opened in a browser and never end up in git.
//
// The folder holds up to three files:
//   honeyart-db.php     database login (optional; without it db.php uses the MAMP defaults)
//   honeyart-mail.php   Gmail login for order e-mails
//   honeyart-admin.php  admin panel login (create it with: php admin_set_password.php)
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
