<?php
// public/includes/db.php

$envPath = __DIR__ . '/../../.env'; 

if (!file_exists($envPath)) {
    error_log("CRITICAL ERROR: Fichier .env introuvable.");
    header("HTTP/1.1 500 Internal Server Error");
    exit("Une erreur interne critique est survenue."); 
}

$env = parse_ini_file($envPath);

if ($env === false) {
    error_log("CRITICAL ERROR: Impossible de parser le fichier .env.");
    header("HTTP/1.1 500 Internal Server Error");
    exit("Erreur de configuration serveur.");
}

try {
    $pdo = new PDO(
        "mysql:host=" . $env['DB_HOST'] . ";dbname=" . $env['DB_NAME'] . ";charset=utf8mb4", 
        $env['DB_USER'], 
        $env['DB_PASS']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // On logge silencieusement la vraie erreur SQL
    error_log("Database Connection Error: " . $e->getMessage());
    // On affiche une erreur 500 propre et on quitte sans utiliser die()
    header("HTTP/1.1 500 Internal Server Error");
    exit("Le service est temporairement indisponible. Veuillez réessayer plus tard.");
}
?>
