<?php
// public/includes/db.php

$envPath = __DIR__ . '/../../.env'; 

if (!file_exists($envPath)) {
    error_log("CRITICAL ERROR: Fichier .env introuvable.");
    header("HTTP/1.1 500 Internal Server Error");
    exit("Une erreur interne critique est survenue."); 
}

// Remplacement du @ magique par une vraie gestion d'erreur
// Use custom parser that handles values with = signs
$env = [];
$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines !== false) {
    foreach ($lines as $line) {
        // Skip comments
        if (str_starts_with(trim($line), ';') || str_starts_with(trim($line), '#')) {
            continue;
        }
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove surrounding quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            $env[$key] = $value;
        }
    }
}

if (empty($env)) {
    error_log("CRITICAL ERROR: Impossible de parser le fichier .env.");
    header("HTTP/1.1 500 Internal Server Error");
    exit("Erreur de configuration serveur.");
}

// Load environment variables into $_ENV and global environment
foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
    putenv("{$key}={$value}");
}

try {
    $pdo = new PDO(
        "mysql:host=" . $env['DB_HOST'] . ";dbname=" . $env['DB_NAME'] . ";charset=utf8mb4", 
        $env['DB_USER'], 
        $env['DB_PASS']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    // On logge silencieusement la vraie erreur SQL (ex: mauvais mot de passe)
    error_log("Database Connection Error: " . $e->getMessage());
    // On affiche une erreur 500 propre à l'utilisateur
    header("HTTP/1.1 500 Internal Server Error");
    exit("Le service est temporairement indisponible. Veuillez réessayer plus tard.");
}
?>
