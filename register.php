<?php
session_start();

$host = 'localhost';
$username = 'root';
$password = "root";
$db_name = 'honey_art_db';
$conn = new mysqli($host, $username, $password, $db_name);

if ($conn -> connect_error) {
    die('error: ' . $conn->connect_error);
}



if($_SERVER['REQUEST_METHOD'] = "POST"){
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];

    $_SESSION['loggedIn'] = true;
    $_SESSION['email'] = $email;
    header("Location: logInReg.php");
    
}

$sql = "INSERT INTO users (`username`, `email`, `password`, `phone`) 
VALUES ('$username', '$email', '$password', '$phone')";
$result = $conn->query($sql);

exit();
?>