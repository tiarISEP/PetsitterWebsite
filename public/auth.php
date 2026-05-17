<?php
// public/auth.php

// --- 1. GESTION SÉCURISÉE DES SESSIONS ---
// Replace your existing startSecureSession function:
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Dynamic detection of HTTPS for the secure flag
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure, 
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}


// --- 2. UTILITAIRES & SÉCURITÉ ---
// Utilitaire de nettoyage de base (Trim uniquement pour l'entrée)
function trimInput($input) {
    if (is_string($input)) {
        return trim($input);
    }
    return $input;
}

// Fonction globale pour sécuriser les affichages
function escapeOutput($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
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
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{12,64}$/', $password);
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
    // Vérification stricte et suffisante de la présence des identifiants de session
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

function redirectToLogin() {
    if (!isUserLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// --- 6. DÉCONNEXION ---
function logoutUser($pdo = null) {
    startSecureSession();
    
    // 1. Invalidation du token 'Remember Me' dans la base de données (si PDO est fourni)
    if ($pdo && isset($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me']);
        if (count($parts) === 2) {
            $selector = $parts[0];
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            $stmt->execute([$selector]);
        }
    }

    // 2. Suppression du cookie 'Remember Me' du navigateur
    if (isset($_COOKIE['remember_me'])) {
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        setcookie('remember_me', '', time() - 3600, '/', '', $isSecure, true);
    }
    
    // 3. Destruction de la session PHP classique
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
    // Pure PDO implementation
    $stmt = $pdo->prepare("SELECT id, username, first_name, last_name, email, phone, bio, avatar_url, user_type, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getUserByEmail($pdo, $email) {
    // Pure PDO implementation
    $stmt = $pdo->prepare("SELECT id, username, email, password, user_type FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

// Add these two new functions at the bottom of auth.php:

//Generates a secure long-lived token, stores the hash in the DB, and sets the cookie.
function setRememberMeCookie($pdo, $user_id) {
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $validator);
    $expires_at = date('Y-m-d H:i:s', time() + 86400 * 30); // 30 days validity
    
    $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $selector, $token_hash, $expires_at]);
    
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    // Format: selector:validator
    setcookie('remember_me', $selector . ':' . $validator, time() + 86400 * 30, '/', '', $isSecure, true);
}

//Validates the remember me cookie against the database.

function verifyRememberMeCookie($pdo) {
    if (empty($_COOKIE['remember_me'])) return false;
    
    $parts = explode(':', $_COOKIE['remember_me']);
    if (count($parts) !== 2) return false;
    
    list($selector, $validator) = $parts;
    
    $stmt = $pdo->prepare("SELECT user_id, token_hash, expires_at FROM remember_tokens WHERE selector = ?");
    $stmt->execute([$selector]);
    $token = $stmt->fetch();
    
    if ($token) {
        // Check if token expired
        if (strtotime($token['expires_at']) < time()) {
            return false;
        }
        // Verify the hash
        if (hash_equals($token['token_hash'], hash('sha256', $validator))) {
            return $token['user_id'];
        }
    }
    return false;
}