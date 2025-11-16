<?php
include('header.php');
?>
<section id="support-section1"><h2>Contact us</h2></section>
<form id="support-section2"method="POST" action="supportMsgSend.php">
        <input type="text" name="sp-name" placeholder="Full name">
        <input type="email" name="sp-email" placeholder="Email*">
        <textarea name="sp-message" placeholder="Message*"></textarea>
        <button id="support-msg-button">SEND</button>
</form>



<?php
include('footer.php');