<?php
// public/auth.php

// 1. Démarrage de session sécurisé
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            // 'secure'   => true, // Dé-commenter en production (HTTPS)
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

// 2. Gestion de l'état utilisateur (Anti-Hijacking léger)
function isUserLoggedIn() {
    // Vérifie les identifiants ET l'empreinte du navigateur pour contrer le vol de cookie
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_agent'])) {
        if ($_SESSION['user_agent'] === $_SERVER['HTTP_USER_AGENT']) {
            return true;
        }
    }
    return false;
}

function redirectToLogin() {
    if (!isUserLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// 3. Déconnexion propre
function logoutUser() {
    startSecureSession();
    
    $_SESSION = array();

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

// 4. Validations (Regex Bcrypt Safe)
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    // Exige : 1 min, 1 maj, 1 chiffre. Longueur : 12 à 64 max (Bcrypt safe)
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{12,64}$/', $password);
}

// 5. Utilitaire de nettoyage de base (Trim uniquement pour l'entrée)
// Le htmlspecialchars() sera utilisé UNIQUEMENT dans les vues (HTML)
function trimInput($input) {
    return trim($input);
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 6. Fonctions BDD (inchangées)
function getUserById($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, phone, bio, avatar_url, role as user_type, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare("SELECT id, username, email, password, role as user_type FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}
?>