<?php
$pageTitle = 'Contact Us';
include('header.php');

if (empty($_SESSION['support_csrf_token'])) {
    $_SESSION['support_csrf_token'] = bin2hex(random_bytes(32));
}

// Result of the last send (set by supportMsgSend.php), shown once
$flash = $_SESSION['support_flash'] ?? null;
$old = $_SESSION['support_old'] ?? [];
unset($_SESSION['support_flash'], $_SESSION['support_old']);
?>
<section id="support-section1"><h2>Contact us</h2></section>
<form id="support-section2" method="POST" action="supportMsgSend.php">
        <?php if ($flash): ?>
            <p class="support-message <?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($flash['text']); ?></p>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['support_csrf_token']); ?>">
        <!-- Left empty by people; bots that fill it in are ignored -->
        <div class="support-hp" aria-hidden="true">
            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <input type="text" name="sp-name" placeholder="Full name" maxlength="100" value="<?php echo htmlspecialchars($old['name'] ?? ''); ?>">
        <input type="email" name="sp-email" placeholder="Email*" required value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>">
        <textarea name="sp-message" placeholder="Message*" required maxlength="5000"><?php echo htmlspecialchars($old['message'] ?? ''); ?></textarea>
        <button id="support-msg-button">SEND</button>
</form>

<?php if ($flash && $flash['type'] === 'success'): ?>
<script>
    // Send the e-mail in the background; the visitor doesn't wait for Gmail
    fetch('send_order_notifications.php', { method: 'POST', keepalive: true, credentials: 'same-origin' })
        .catch(() => {});
</script>
<?php endif; ?>

<?php
include('footer.php');
