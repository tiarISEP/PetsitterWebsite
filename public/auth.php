<?php
// Authentication helper functions

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function redirectToLogin() {
    if (!isUserLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function logoutUser() {
    session_destroy();
    header("Location: index.html");
    exit();
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    // Password must be at least 8 characters
    return strlen($password) >= 8;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function getUserById($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id, username, email, user_type, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getUserByEmail($conn, $email) {
    $stmt = $conn->prepare("SELECT id, username, email, password, user_type FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
?>
