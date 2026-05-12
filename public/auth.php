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
function sanitizeInput($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
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
    // Règle stricte : 12 caractères min, 1 majuscule, 1 minuscule, 1 chiffre.
    return strlen($password) >= 12
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password);
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
    return isset($_SESSION['user_id'], $_SESSION['username']);
}

function redirectToLogin() {
    if (!isUserLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function logoutUser() {
    startSecureSession();

    // Vidage de la mémoire serveur
    $_SESSION = [];

    // Destruction du cookie côté client
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
    header("Location: index.php");
    exit();
}

// --- 6. BASE DE DONNÉES (PDO STRICT) ---
function getUserById($pdo, $user_id) {
    // Alias role AS user_type pour compatibilité
    $stmt = $pdo->prepare("SELECT id, username, email, role AS user_type, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare("SELECT id, username, email, password, role AS user_type FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}
?>
