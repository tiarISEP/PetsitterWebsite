<?php
// 1. Configuration des données de l'alerte
$destinataire = "eliott200.tom@gmail.com"; // Mets ton mail pour tester
$sujet = "ALERTE : Chien perdu - Secteur ISEP";

// 2. Le message en HTML (plus joli pour la présentation)
$message = "
<html>
<head>
  <title>Alerte Animale</title>
</head>
<body>
  <h1 style='color: #e74c3c;'>⚠️ Urgent : Chien égaré</h1>
  <p>Un chien de type <strong>Golden Retriever</strong> a été signalé perdu.</p>
  <p>Merci de contacter le propriétaire via l'application si vous avez des informations.</p>
  <hr>
  <p style='font-size: 0.8em;'>Ceci est une notification automatique de votre service de Pet Sitting.</p>
</body>
</html>
";

// 3. Les en-têtes (headers) pour le formatage et l'expéditeur [cite: 78, 94]
$headers = "From: eliott200.tom@gmail.com" . "\r\n";
$headers .= "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type: text/html; charset=utf-8";

// 4. L'envoi et la confirmation [cite: 79, 95]
if (mail($destinataire, $sujet, $message, $headers)) {
    echo "<h2>Succès ! L'alerte mail a été envoyée.</h2>";
} else {
    echo "<h2>Erreur : Le mail n'est pas parti.</h2>";
}
?>