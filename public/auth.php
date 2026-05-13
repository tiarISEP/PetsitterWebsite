<?php
// public/auth.php

// --- 1. GESTION SÉCURISÉE DES SESSIONS ---
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            // 'secure'   => true, // DÉSACTIVÉ POUR XAMPP (HTTP). À réactiver en production (HTTPS).
            'httponly' => true,    // Bloque l'accès au cookie via JavaScript (Anti-XSS)
            'samesite' => 'Strict' // Anti-CSRF global
        ]);
        session_start();
    }
}

// --- 2. UTILITAIRES & SÉCURITÉ ---
// Utilitaire de nettoyage de base (Trim uniquement pour l'entrée)
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

// --- 3. VALIDATIONS ---
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    // Exige : 1 min, 1 maj, 1 chiffre. Longueur : 12 à 64 max (Bcrypt safe)
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{12,64}$/', $password);
}

// --- 4. MOTS DE PASSE ---
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// --- 5. GESTION D'ÉTAT DE L'UTILISATEUR ---
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

// --- 6. DÉCONNEXION ---
function logoutUser() {
    startSecureSession();
    
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params["path"],
            'domain' => $params["domain"],
            'secure' => $params["secure"],
            'httponly' => $params["httponly"],
            'samesite' => 'Strict'
        ]);
    }
    
    session_destroy();
    header("Location: index.php");
    exit();
}

// --- 7. BASE DE DONNÉES (PDO STRICT) ---
function getUserById($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, phone, bio, avatar_url, role as user_type, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare("SELECT id, username, email, password, role AS user_type FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}
?>