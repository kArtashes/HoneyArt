<?php 

include("header.php");

if (isset($_GET['error'])) {
    echo "<p style='color: red;'>" . htmlspecialchars($_GET['error']) . "</p>";
}
?>
<div id="login-reg-body">
    <div id="login-slider-div">
        <?php include("slider.php");?>
    </div>
    <div id="login-div">
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
            </form>
        </div>


        <div id="register" class="form-container">
            <form method="POST" action="register.php">
                <input type="text"  id="register-username" name="username" placeholder="Username" required><br>

                <input type="email" id="register-email" name="email" placeholder="Email" required><br>
                    
                <input type="password" id="register-password" name="password" placeholder="Password" required><br>
                    
                <input type="tel" id="register-phone" name="phone" placeholder="Phone" required><br>
                                        
                <button type="submit">Register</button>
            </form>
        </div>
    </div>
</div>

<?php include("footer.php"); ?>

<script>
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

