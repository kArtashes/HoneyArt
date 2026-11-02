<?php
// Shop e-mails (orders and support messages): queued first, sent later by send_order_notifications.php

require_once __DIR__ . '/config.php';

const ORDER_MAIL_MAX_ATTEMPTS = 3;

// Sends one plain-text e-mail, by default to the shop's inbox (notification_email in the mail config).
// Replies go to $replyEmail, so answering the e-mail answers the customer.
function sendSiteMail(string $fromName, string $subject, string $body, string $replyEmail, string $replyName, ?string $to = null): bool
{
    $mailConfigPath = PRIVATE_DIR . '/honeyart-mail.php';
    if (!is_readable($mailConfigPath)) {
        error_log('HoneyArt mail configuration file is unavailable.');
        return false;
    }

    $mailConfig = require $mailConfigPath;
    $smtpUsername = $mailConfig['smtp_username'] ?? '';
    $smtpPassword = $mailConfig['smtp_password'] ?? '';
    $notificationEmail = $mailConfig['notification_email'] ?? '';

    if ($smtpUsername === '' || $smtpPassword === '' || $notificationEmail === '') {
        error_log('HoneyArt mail configuration is incomplete.');
        return false;
    }

    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUsername;
        $mail->Password = $smtpPassword;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 15;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($smtpUsername, $fromName);
        $mail->addAddress($to ?? $notificationEmail);
        $mail->addReplyTo($replyEmail, $replyName);
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();

        return true;
    } catch (Throwable $e) {
        error_log('HoneyArt email failed: ' . $e->getMessage());
        return false;
    }
}

function sendOrderNotification(array $user, array $address, array $items, float $total, ?int $orderId = null): bool
{
    $lines = [];
    foreach ($items as $item) {
        $lines[] = $item['name'] . ' × ' . $item['quantity'] . ' = ֏' . number_format($item['price'] * $item['quantity'], 0);
    }

    $addressLines = [
        $address['address_line1'],
        $address['address_line2'] ?: null,
        $address['city'] . ($address['region'] ? ', ' . $address['region'] : ''),
        'Phone: ' . $address['phone'],
        $address['notes'] ? 'Notes: ' . $address['notes'] : null,
    ];

    $body = "Customer: {$user['username']}\nEmail: {$user['email']}\n\nDelivery address:\n"
        . implode("\n", array_filter($addressLines))
        . "\n\nItems:\n" . implode("\n", $lines)
        . "\n\nTotal: ֏" . number_format($total, 0);

    $subject = 'New HoneyArt order' . ($orderId ? ' #' . $orderId : '') . ' from ' . $user['username'];

    return sendSiteMail('HoneyArt Orders', $subject, $body, $user['email'], $user['username']);
}

function sendSupportMessage(string $name, string $email, string $message): bool
{
    $body = "From: $name <$email>\n\n$message";

    return sendSiteMail('HoneyArt Support', $name . ' needs support', $body, $email, $name);
}

function sendPasswordResetEmail(string $username, string $email, string $resetUrl): bool
{
    $body = "Hello $username,\n\n"
        . "Someone asked to reset the password of your HoneyArt account.\n"
        . "Open this link to choose a new password (it works for 1 hour):\n\n"
        . "$resetUrl\n\n"
        . "If this wasn't you, ignore this e-mail. Your password stays the same.";

    return sendSiteMail('HoneyArt', 'Reset your HoneyArt password', $body, $email, $username, $email);
}

// Saves the e-mail to the queue. Call it inside the order transaction,
// so the order and its e-mail are saved together or not at all.
function queueOrderNotification(mysqli $conn, array $user, array $address, array $items, float $total, int $orderId): void
{
    $payload = json_encode([
        'user' => $user,
        'address' => $address,
        'items' => $items,
        'total' => $total,
        'order_id' => $orderId,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $stmt = $conn->prepare('INSERT INTO order_notifications (payload) VALUES (?)');
    $stmt->bind_param('s', $payload);
    $stmt->execute();
    $stmt->close();
}

// Support form messages use the same queue, so a Gmail hiccup is retried too.
function queueSupportMessage(mysqli $conn, string $name, string $email, string $message): void
{
    $payload = json_encode([
        'type' => 'support',
        'name' => $name,
        'email' => $email,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $stmt = $conn->prepare('INSERT INTO order_notifications (payload) VALUES (?)');
    $stmt->bind_param('s', $payload);
    $stmt->execute();
    $stmt->close();
}

// Queued as well, so the page answers just as fast for an unknown e-mail
// and the response time doesn't reveal who has an account.
function queuePasswordResetEmail(mysqli $conn, string $username, string $email, string $resetUrl): void
{
    $payload = json_encode([
        'type' => 'password_reset',
        'username' => $username,
        'email' => $email,
        'url' => $resetUrl,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $stmt = $conn->prepare('INSERT INTO order_notifications (payload) VALUES (?)');
    $stmt->bind_param('s', $payload);
    $stmt->execute();
    $stmt->close();
}

// Sends up to $limit queued e-mails. Returns how many were sent.
function processOrderNotifications(mysqli $conn, int $limit = 5): int
{
    // Sent e-mails hold customers' addresses; no reason to keep them for long
    $conn->query("DELETE FROM order_notifications WHERE status = 'sent' AND sent_at < NOW() - INTERVAL 90 DAY");
    // A reset link is useless after 1 hour, whether it could be sent or not
    $conn->query("DELETE FROM order_notifications
                  WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) = 'password_reset'
                    AND created_at < NOW() - INTERVAL 1 HOUR");

    // 'sending' rows older than 10 minutes belong to a request that died mid-send
    $stmt = $conn->prepare(
        "SELECT id FROM order_notifications
         WHERE status = 'pending'
            OR (status = 'sending' AND locked_at < NOW() - INTERVAL 10 MINUTE)
         ORDER BY id
         LIMIT ?"
    );
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $stmt->close();

    $sent = 0;
    foreach ($ids as $id) {
        $id = (int) $id;

        // Claim the row so two parallel requests never send the same e-mail
        $claim = $conn->prepare(
            "UPDATE order_notifications
             SET status = 'sending', attempts = attempts + 1, locked_at = NOW()
             WHERE id = ?
               AND (status = 'pending'
                    OR (status = 'sending' AND locked_at < NOW() - INTERVAL 10 MINUTE))"
        );
        $claim->bind_param('i', $id);
        $claim->execute();
        $claimed = $claim->affected_rows === 1;
        $claim->close();

        if (!$claimed) {
            continue;
        }

        $row = $conn->query("SELECT payload, attempts FROM order_notifications WHERE id = $id")->fetch_assoc();
        $data = json_decode($row['payload'], true);

        $type = $data['type'] ?? 'order';
        if ($type === 'support') {
            $sentOk = sendSupportMessage($data['name'], $data['email'], $data['message']);
        } elseif ($type === 'password_reset') {
            $sentOk = sendPasswordResetEmail($data['username'], $data['email'], $data['url']);
        } else {
            $sentOk = sendOrderNotification($data['user'], $data['address'], $data['items'], (float) $data['total'], $data['order_id'] ?? null);
        }

        if ($sentOk && $type === 'password_reset') {
            // The link inside is a key to the account: don't keep it once it has been sent
            $conn->query("DELETE FROM order_notifications WHERE id = $id");
            $sent++;
        } elseif ($sentOk) {
            $conn->query("UPDATE order_notifications SET status = 'sent', sent_at = NOW() WHERE id = $id");
            $sent++;
        } else {
            $nextStatus = (int) $row['attempts'] >= ORDER_MAIL_MAX_ATTEMPTS ? 'failed' : 'pending';
            $conn->query("UPDATE order_notifications SET status = '$nextStatus' WHERE id = $id");
        }
    }

    return $sent;
}
