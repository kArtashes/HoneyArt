<?php
// Slows down password guessing: after too many failed log-ins for one account
// or from one IP address, further attempts are refused for a while.
// Table: login_attempts.sql

const LOGIN_WINDOW_MINUTES = 15;
const LOGIN_MAX_FAILS_PER_ACCOUNT = 5;
const LOGIN_MAX_FAILS_PER_IP = 10;

function loginClientIp(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
}

function tooManyLoginAttempts(mysqli $conn, string $scope, string $identifier): bool
{
    $identifier = mb_strtolower(trim($identifier));
    $ip = loginClientIp();

    $stmt = $conn->prepare(
        'SELECT
             SUM(identifier = ?) AS account_fails,
             SUM(ip = ?) AS ip_fails
         FROM login_attempts
         WHERE scope = ? AND attempted_at > NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE
           AND (identifier = ? OR ip = ?)'
    );
    $stmt->bind_param('sssss', $identifier, $ip, $scope, $identifier, $ip);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) $counts['account_fails'] >= LOGIN_MAX_FAILS_PER_ACCOUNT
        || (int) $counts['ip_fails'] >= LOGIN_MAX_FAILS_PER_IP;
}

// Counts one attempt without slowing the visitor down (support form, registration)
function recordAttempt(mysqli $conn, string $scope, string $identifier): void
{
    $identifier = mb_strtolower(trim($identifier));
    $ip = loginClientIp();

    $stmt = $conn->prepare('INSERT INTO login_attempts (scope, identifier, ip) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $scope, $identifier, $ip);
    $stmt->execute();
    $stmt->close();

    // Old rows are no longer needed
    $conn->query('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
}

function recordFailedLogin(mysqli $conn, string $scope, string $identifier): void
{
    recordAttempt($conn, $scope, $identifier);
    sleep(1); // every wrong guess costs the guesser a second
}

function clearFailedLogins(mysqli $conn, string $scope, string $identifier): void
{
    $identifier = mb_strtolower(trim($identifier));

    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE scope = ? AND identifier = ?');
    $stmt->bind_param('ss', $scope, $identifier);
    $stmt->execute();
    $stmt->close();
}
