<?php
session_start();

// Ստուգում ենք՝ մուտք գործած է, թե ոչ
if (!isset($_SESSION['user_id'])) {
    // Եթե չէ՝ հետ ենք ուղարկում login էջ
    header("Location: logInReg.php?message=Please login to add products to cart.");
    exit();
}

// Ստուգում ենք՝ տվյալները ուղարկվե՞լ են
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['product_id'])) {
        $product_id = intval($_POST['product_id']);
        $user_id = $_SESSION['user_id'];

        // Կապ ենք հաստատում MySQL-ի հետ
        $conn = new mysqli("localhost", "root", "root", "honey_art_db");

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Ստուգում ենք՝ արդյոք արդեն կա այդ ապրանքը զամբյուղում
        $sql_check = "SELECT * FROM cart WHERE user_id = ? AND product_id = ?";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $quantity = 1;
        if ($result->num_rows > 0) {
            // Արդեն կա, ավելացնենք քանակը
            $sql_update = "UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("iii", $quantity, $user_id, $product_id);
            $stmt->execute();
        } else {
            // Չկա, նոր ավելացնենք
            $sql_insert = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param("iii", $user_id, $product_id, $quantity);
            $stmt->execute();
        }

        $stmt->close();
        $conn->close();

        // Վերադարձ կայք
        if (isset($_SERVER['HTTP_REFERER'])) {
            $redirect_url = $_SERVER['HTTP_REFERER'];
        } else {
            $redirect_url = 'products.php'; // fallback
        }
        
        // Optional: add a message
        $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . 'message=Product added to cart';
        
        header("Location: $redirect_url");
        exit();
    } else {
        die("Invalid data.");
    }
} else {
    die("Invalid request method.");
}
?>
