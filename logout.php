<?php
require_once __DIR__ . '/auth.php';


// Remove the "Remember me" token from the database and the browser
if (isset($_SESSION['user_id'])) {
    forgetCustomer($conn, (int) $_SESSION['user_id']);
} else {
    clearRememberCookie();
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header("Location: logInReg.php");
exit();
?>
