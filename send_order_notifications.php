<?php
// Sends queued e-mails. Called in the background by checkout.php and support.php
// after the visitor already sees the "order placed" / "message sent" page.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Logged-in customers, or a visitor who has just sent the support form
$mayTriggerSending = !empty($_SESSION['user_id']) || !empty($_SESSION['mail_queued']);
unset($_SESSION['mail_queued']);
// Release the session lock so the customer's next page isn't blocked while SMTP runs
session_write_close();

if (!$mayTriggerSending) {
    http_response_code(403);
    exit;
}

// Keep sending even if the customer closes the tab
ignore_user_abort(true);
set_time_limit(120);

require __DIR__ . '/order_mail.php';

try {
    require_once __DIR__ . '/db.php';
    $sent = processOrderNotifications($conn);
} catch (Throwable $e) {
    error_log('HoneyArt notification queue failed: ' . $e->getMessage());
    http_response_code(500);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['sent' => $sent]);
