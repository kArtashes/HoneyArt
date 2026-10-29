<?php
// Social pages: paste the full address (https://...) and the icon becomes a link
$socialLinks = [
    'Facebook'  => ['icon' => 'facebook.png',   'style' => 'width: 20px; height: 35px;', 'url' => ''],
    'Instagram' => ['icon' => 'insta_icon.png', 'style' => 'width: 30px; height: 30px;', 'url' => ''],
    'YouTube'   => ['icon' => 'youtube_icon.png', 'style' => 'width: 35px; height: 25px;', 'url' => ''],
];
?>
<footer>
    <div class='footer-info'>
        <div class="footer-section1">
            <img class="footer-logo" src="./images/header_logo.png" alt="HoneyArt" />
            <div class="footer-social-media">
                <?php foreach ($socialLinks as $name => $social): ?>
                    <?php $icon = '<img src="./images/' . $social['icon'] . '" alt="' . $name . '" style="' . $social['style'] . '"/>'; ?>
                    <?php if ($social['url'] !== ''): ?>
                        <a class="footer-social-link" href="<?php echo htmlspecialchars($social['url']); ?>" target="_blank" rel="noopener" aria-label="<?php echo $name; ?>"><?php echo $icon; ?></a>
                    <?php else: ?>
                        <span class="footer-social-link"><?php echo $icon; ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <div id="textinfo">
            <div class="footer-section2">
                <h2>Quick links</h2>
                <span><a href="products.php">Products</a></span>
                <span><a href="blogs.php">Magazine</a></span>
                <span><a href="support.php">Contact us</a></span>
                <span><a href="aboutUs.php">About us</a></span>
            </div>
            <div class="footer-section3">
                <h2>Contact</h2>
                <div class='footer-contact-section'>
                    <img src="./images/phone.png" alt="" />
                    <a href="tel:+37495616112">+374 95 616 112</a>
                </div>
                <div class='footer-contact-section'>
                    <img src="./images/mail.png" alt="" />
                    <a href="mailto:artashkhachatryan9@gmail.com">artashkhachatryan9@gmail.com</a>
                </div>
                <div class='footer-contact-section'>
                    <img src="./images/address.png" alt="" />
                    <span>Yerevan, Armenia</span>
                </div>
            </div>
        </div>
    </div>
    <p class="footer-copy">© <?php echo date('Y'); ?> HoneyArt</p>
</footer>
