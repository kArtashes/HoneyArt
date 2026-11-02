<?php
// Handles the support form (support.php): checks the input, puts the message in the
// e-mail queue and goes back to the form with a success or error message.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: support.php');
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/order_mail.php';
require_once __DIR__ . '/login_limiter.php';

const SUPPORT_MIN_SECONDS_BETWEEN_MESSAGES = 60;
const SUPPORT_MAX_MESSAGE_LENGTH = 5000;

function backToSupport(string $type, string $text, array $old = []): void
{
    $_SESSION['support_flash'] = ['type' => $type, 'text' => $text];
    $_SESSION['support_old'] = $old;
    header('Location: support.php');
    exit;
}

// One line, no control characters (keeps the e-mail subject clean)
$name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', requestString($_POST, 'sp-name')));
$email = requestString($_POST, 'sp-email');
$message = requestString($_POST, 'sp-message');
$old = ['name' => $name, 'email' => $email, 'message' => $message];

// Bots fill in every field, including this one that people never see
if (($_POST['website'] ?? '') !== '') {
    backToSupport('success', 'Thank you! Your message has been sent.');
}

// A visitor without a token (no session, form never opened) must be rejected too:
// comparing two empty strings would otherwise pass
$expectedToken = $_SESSION['support_csrf_token'] ?? '';
if ($expectedToken === '' || !hash_equals($expectedToken, requestString($_POST, 'csrf_token'))) {
    backToSupport('error', 'The form has expired. Please send your message again.', $old);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    backToSupport('error', 'Please enter a valid e-mail address so we can reply to you.', $old);
}

if ($message === '') {
    backToSupport('error', 'Please write your message.', $old);
}

if (mb_strlen($message) > SUPPORT_MAX_MESSAGE_LENGTH || mb_strlen($name) > 100) {
    backToSupport('error', 'Your message is too long. Please keep it under ' . SUPPORT_MAX_MESSAGE_LENGTH . ' characters.', $old);
}

// A bot can start a new session for every message, so also count by e-mail and IP address
if (tooManyLoginAttempts($conn, 'support', $email)) {
    backToSupport('error', 'Too many messages were sent. Please try again in 15 minutes.', $old);
}

$secondsSinceLast = time() - (int) ($_SESSION['support_last_sent'] ?? 0);
if ($secondsSinceLast < SUPPORT_MIN_SECONDS_BETWEEN_MESSAGES) {
    backToSupport('error', 'You have just sent a message. Please wait a minute before sending another one.', $old);
}

try {
    queueSupportMessage($conn, $name !== '' ? $name : 'Website visitor', $email, $message);
} catch (Throwable $e) {
    error_log('HoneyArt support message could not be queued: ' . $e->getMessage());
    backToSupport('error', 'Sorry, your message could not be sent right now. Please try again in a few minutes.', $old);
}

recordAttempt($conn, 'support', $email);
$_SESSION['support_last_sent'] = time();
$_SESSION['mail_queued'] = true; // lets support.php start the background sending
backToSupport('success', 'Thank you! Your message has been sent. We will reply to ' . $email . ' soon.');
