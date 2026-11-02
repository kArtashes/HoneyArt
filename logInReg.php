<?php
require_once __DIR__ . '/auth.php';
// Already logged in: nothing to do here
if (($_SESSION['loggedIn'] ?? false) === true) {
    header('Location: account.php');
    exit();
}

$pageTitle = 'Log In / Register';
include("header.php");

// Only these fixed texts can appear; the link carries a short code, not the text itself
$messages = [
    'invalid'          => ['error',   'login',    'Wrong email or password. Please try again.'],
    'too_many'         => ['error',   'login',    'Too many attempts. Please wait 15 minutes and try again.'],
    'login_required'   => ['error',   'login',    'Please log in to add products to your basket.'],
    'register_invalid' => ['error',   'register', 'Please enter a name, a valid email and a password of 8 to 72 characters.'],
    'register_failed'  => ['error',   'register', 'Sorry, we could not create your account. Please try again.'],
    'email_taken'      => ['error',   'register', 'This email is already registered. Try logging in instead.'],
    'account_deleted'  => ['success', 'login',    'Your account has been deleted.'],
    'password_reset'   => ['success', 'login',    'Your password has been changed. You can log in now.'],
];
$messageCode = requestString($_GET, 'error') ?: requestString($_GET, 'notice');
[$messageType, $activeForm, $messageText] = $messages[$messageCode] ?? [null, 'login', null];
?>
<div id="login-reg-body">
    <div id="login-slider-div">
        <?php include("slider.php");?>
    </div>
    <div id="login-div">
        <?php if ($messageText): ?>
            <p class="login-message <?php echo $messageType; ?>"><?php echo htmlspecialchars($messageText); ?></p>
        <?php endif; ?>
        <div id="log-reg-buttons">
            <button id="login-btn" onclick="showForm('login')" class="active"><h2>Log in</h2></button>
            <button id="register-btn" onclick="showForm('register')"><h2>Register</h2></button>
        </div>


        <div id="login" class="form-container active">
            <form method="POST" action="login.php">
                <input type="email" id="login-email" name="email" placeholder="Email" required><br>
                    
                <input type="password" id="login-password" name="password" placeholder="Password" required><br>
                
                <label for="">
                    <input type="checkbox" name="remember" id="remember"><span>Remember me</span>
                </label>
                
                <button type="submit">Log In</button>
                <a class="login-forgot" href="forgot_password.php">Forgot your password?</a>
            </form>
        </div>


        <div id="register" class="form-container">
            <form method="POST" action="register.php">
                <input type="text"  id="register-username" name="username" placeholder="Username" maxlength="60" required><br>

                <input type="email" id="register-email" name="email" placeholder="Email" maxlength="255" required><br>
                    
                <input type="password" id="register-password" name="password" placeholder="Password" minlength="8" maxlength="72" required><br>
                    
                <button type="submit">Register</button>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>

<script>
    // Open the form the message belongs to (e.g. Register after a registration error)
    document.addEventListener('DOMContentLoaded', () => showForm('<?php echo $activeForm; ?>'));

    function showForm(formId) {
        document.querySelectorAll('.form-container').forEach(form => {
            form.classList.remove('active');
        });
        document.getElementById(formId).classList.add('active');

        document.getElementById('login-btn').classList.remove('active');
        document.getElementById('register-btn').classList.remove('active');
        
        if (formId === 'login') {
            document.getElementById('login-btn').classList.add('active');
        } else if (formId === 'register') {
            document.getElementById('register-btn').classList.add('active');
        }
    }
</script>
