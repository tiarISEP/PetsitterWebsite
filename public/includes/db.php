<?php
// public/includes/db.php

$host = 'localhost';
$dbname = 'petsitter_db'; 
$username = 'root'; 
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Configuration stricte pour faire crasher le script si une requête SQL est mal écrite
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur fatale de connexion à la base de données : " . $e->getMessage());
}
?>