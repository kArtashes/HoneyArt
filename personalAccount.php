<?php
session_start();

$host = 'localhost';
$username = 'root';
$password = "root";
$db_name = 'honey_art_db';
$conn = new mysqli($host, $username, $password, $db_name);

if ($conn->connect_error) {
    die('error: ' . $conn->connect_error);
}

// 🔹 Restore session from remember me cookie if not logged in
if (!isset($_SESSION['loggedIn']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_expiry > NOW()");
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        if (password_verify($token, $row['remember_token'])) {
            // restore session
            $_SESSION['loggedIn'] = true;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];
            break;
        }
    }
}

// 🔹 Redirect to login if still not logged in
if (!isset($_SESSION['loggedIn']) || $_SESSION['loggedIn'] !== true) {
    header("Location: logInReg.php");
    exit();
}

include("header.php");

// fetch user info
$stmt = $conn->prepare("SELECT username, email, phone FROM users WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();

$userinfo = [
    "username" => "",
    "email" => "",
    "phone" => "",
];

if ($row = $result->fetch_assoc()) {
    $userinfo['username'] = $row['username'];
    $userinfo['email'] = $row['email'];
    $userinfo['phone'] = $row['phone'];
}

?>
<div id="personal-account-body">
    <div id='personal-account'> 
        <div class="personal-account-control"> 
            <h1>Personal Account</h1> 
        </div> 
        <div class="person-data"> 
            <div id='personal-data-control'> 
                <div class='person-username'> 
                    <span><?php echo htmlspecialchars($userinfo['username']); ?></span> 
                </div> 
                <div class='person-control-buttons'> 
                    <form action="edit.php" method="POST">
                        <button type="submit" name="edit">Edit</button>
                    </form>
                    <form action="logout.php" method="POST">
                        <button type="submit" name="logout">Log Out</button>
                    </form>
                </div> 
            </div> 
            <div class='person-personal-data'> 
                <span><?php echo htmlspecialchars($userinfo['email']); ?></span> 
                <span><?php echo htmlspecialchars($userinfo['phone']); ?></span> 
            </div> 
        </div> 
    </div>
</div>

<?php include('footer.php'); ?>
