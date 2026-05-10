<?php
// public/auth.php

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

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    // Exige : 1 minuscule, 1 majuscule, 1 chiffre, et 8 caractères minimum
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,}$/', $password);
}

function logoutUser() {
    session_start(); // Nécessaire si la session n'a pas encore été démarrée dans le script
    
    // On détruit toutes les variables de session
    $_SESSION = array();

    // On tue le cookie de session sur le navigateur de l'utilisateur
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, 
            $params["path"], $params["domain"], 
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    header("Location: index.php");
    exit();
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// --- FONCTIONS BASE DE DONNÉES CONVERTIES EN PDO ---

function getUserById($pdo, $user_id) {
    // Note: on utilise "role as user_type" pour que le reste du code de Romain fonctionne
    $stmt = $pdo->prepare("SELECT id, username, email, role as user_type, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare("SELECT id, username, email, password, role as user_type FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}
?>