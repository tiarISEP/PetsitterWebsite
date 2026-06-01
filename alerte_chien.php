<?php
// 1. Configuration des données de l'alerte
$destinataire = "eliott200.tom@gmail.com";
$sujet = "Sorry I lost your dog..."; // Nouveau titre demandé

// 2. Le message en HTML avec l'image et ton texte
$message = "
<html>
<body style='font-family: Arial, sans-serif;'>
  <h2>Alerte Pet Sitting</h2>

  <img src='https://i.ibb.co/vzR0jXN/dog-balloons.jpg'
       alt='Dog flying'
       style='width: 300px; display: block; margin-bottom: 20px;'>

  <p style='font-size: 1.1em;'>
    I was playing with your dog and wanted to make him fly with baloons to make him happy but he flew away and died sorry.
  </p>
</body>
</html>
";

// 3. Les en-têtes (headers)
$headers = "From: eliott200.tom@gmail.com" . "\r\n";
$headers .= "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type: text/html; charset=utf-8";

// 4. L'envoi
if (mail($destinataire, $sujet, $message, $headers)) {
    echo "<h2>Succès ! Le mail d'excuses a été envoyé.</h2>";
} else {
    echo "<h2>Erreur : L'envoi a échoué.</h2>";
}
?>