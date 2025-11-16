<?php
session_start();

// Prepare email body
$message = $_POST['sp-message'];


// ✅ Use PHPMailer instead of mail()
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // Gmail SMTP
    $mail->SMTPAuth   = true;
    $mail->Username   = 'artashkhachatryan9@gmail.com'; // your Gmail
    $mail->Password   = 'amamdhbquoicjoyb';  // Gmail App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    //Recipients
    $mail->setFrom('yourgmail@gmail.com', 'HoneyArt Support');
    $mail->addAddress('artashkhachatryan9@gmail.com'); // where you want to receive orders
    $mail->addReplyTo($_POST['sp-email'], $_POST['sp-name']);

    // Content
    $mail->isHTML(false); // plain text
    $mail->Subject = $_POST['sp-name'] . " needs support";
    $mail->Body    = $message;

    $mail->send();
    echo "Order email sent successfully!";
} catch (Exception $e) {
    echo "Email could not be sent. Error: {$mail->ErrorInfo}";
}

// Վերադարձ կայք
$redirect_url = 'support.php'; // fallback
header("Location: $redirect_url");

exit();