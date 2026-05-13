<?php
// public/includes/db.php

$envPath = __DIR__ . '/../../.env'; 

if (!file_exists($envPath)) {
    // En production, on log l'erreur et on affiche un message générique.
    error_log("Fichier critique .env manquant à l'emplacement : " . $envPath);
    die("Erreur interne du serveur."); 
}

// On cache les erreurs du parseur (avec @) et on gère l'échec
$env = @parse_ini_file($envPath);

if ($env === false) {
    error_log("Échec de l'analyse du fichier .env. Le fichier est-il mal formaté ?");
    die("Erreur interne de configuration.");
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
    error_log("Échec de connexion PDO : " . $e->getMessage());
    die("Erreur de connexion à la base de données."); // Ne jamais afficher $e->getMessage() en production !
}
?>
