<?php
// public/update_profile.php

require_once 'includes/db.php';
require_once 'auth.php';

// 1. Initialisation et vérifications d'état
startSecureSession();
redirectToLogin();

// 2. Protocole HTTP strict : On dégage tout ce qui n'est pas du POST proprement
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed");
}

// 3. Bouclier CSRF avec log silencieux
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    error_log("Tentative CSRF échouée pour l'utilisateur ID: " . ($_SESSION['user_id'] ?? 'inconnu'));
    header("Location: profile.php?error=invalid_request");
    exit();
}

$userId = $_SESSION['user_id'];

// 4. Nettoyage des données entrantes
$username   = trimInput($_POST['username'] ?? '');
$first_name = trimInput($_POST['first_name'] ?? '');
$last_name  = trimInput($_POST['last_name'] ?? '');
$phone      = trimInput($_POST['phone'] ?? '');
$bio        = trimInput($_POST['bio'] ?? '');

// 5. Validation stricte (Ingénierie défensive)
if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
    header("Location: profile.php?error=invalid_username_length");
    exit();
}
if (mb_strlen($first_name) > 50 || mb_strlen($last_name) > 50) {
    header("Location: profile.php?error=name_too_long");
    exit();
}
if (mb_strlen($bio) > 1000) {
    header("Location: profile.php?error=bio_too_long");
    exit();
}
if (!empty($phone) && !preg_match('/^[+0-9]{10,15}$/', $phone)) {
    header("Location: profile.php?error=invalid_phone");
    exit();
}

// 6. Exécution blindée avec journalisation
try {
    $stmt = $pdo->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, phone = ?, bio = ? WHERE id = ?");
    $stmt->execute([$username, $first_name, $last_name, $phone, $bio, $userId]);

    // Synchronisation de la session pour l'affichage en temps réel
    $_SESSION['username'] = $username;

    header("Location: profile.php?success=profile_updated");
    exit();

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        header("Location: profile.php?error=username_taken");
        exit();
    }
    error_log("Erreur DB update_profile (User: $userId): " . $e->getMessage());
    header("Location: profile.php?error=update_failed");
    exit();
}
?>
