<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Log out of the admin panel only; a customer login in the same browser stays
unset($_SESSION['loggedin'], $_SESSION['admin_csrf_token']);
session_regenerate_id(true);
header("Location: adminlogin.php");
exit;
