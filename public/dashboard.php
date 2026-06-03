<?php
require_once 'auth.php';
startSecureSession();
redirectToLogin();
header("Location: profile.php");
exit();
?>
