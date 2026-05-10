<?php
session_start();
require_once 'includes/db.php'; // Changé : db.php au lieu de config.php
require_once 'auth.php';
logoutUser();
?>
