<?php
// public/auth.php

/**
 * Nettoie les entrées utilisateur pour éviter les failles XSS
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Vérifie si l'utilisateur possède une session active
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Redirige les utilisateurs non connectés vers la page de login
 */
function redirectToLogin() {
    if (!isUserLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Détruit la session et redirige vers l'accueil PHP
 */
function logoutUser() {
    session_destroy();
    header("Location: index.php"); // Corrigé : index.php au lieu de .html
    exit();
}

/**
 * Validations de format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    return strlen($password) >= 8;
}

/**
 * Sécurité des mots de passe
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * RÉCUPÉRATION DES DONNÉES (Version PDO avec mapping role -> user_type)
 */

function getUserById($pdo, $user_id) {
    // Utilisation de "role AS user_type" pour rester compatible avec le code de Romain
    $stmt = $pdo->prepare("SELECT id, username, email, role AS user_type, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(); // PDO renvoie directement un tableau associatif par défaut
}

function getUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare("SELECT id, username, email, password, role AS user_type FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}
?>
