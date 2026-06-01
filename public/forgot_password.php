<?php
$message = "";
$message_class = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!empty($email)) {
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;dbname=petsitter_db;charset=utf8mb4', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $selector = bin2hex(random_bytes(8));
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);

                $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));

                $ins = $pdo->prepare("INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)");
                $ins->execute([$user['id'], $selector, $token_hash, $expires_at]);

                $reset_link = "http://localhost/PetsitterWebsite/public/ResetPassword.php?selector=" . $selector . "&token=" . $token;

                $to = $email;
                $subject = "Reinitialisation de votre mot de passe - PetSitter's Market";
                $email_content = "Bonjour,\n\nPour réinitialiser votre mot de passe, cliquez sur le lien ci-dessous :\n" . $reset_link . "\n\nCe lien expirera dans 1 heure.\n\nL'équipe PetSitter's Market";
                $headers = "From: no-reply@petsittersmarket.com\r\nReply-To: no-reply@petsittersmarket.com\r\nX-Mailer: PHP/" . phpversion();

                if (mail($to, $subject, $email_content, $headers)) {
                    $message = "Un e-mail de récupération a été envoyé si l'adresse existe.";
                    $message_class = "success";
                } else {
                    $message = "Erreur lors de l'envoi de l'e-mail. Vérifiez la configuration SMTP de votre serveur.";
                    $message_class = "error";
                }
            } else {
                $message = "Un e-mail de récupération a été envoyé si l'adresse existe.";
                $message_class = "success";
            }
        } catch (PDOException $e) {
            $message = "Erreur de base de données : " . $e->getMessage();
            $message_class = "error";
        }
    } else {
        $message = "Veuillez entrer une adresse e-mail valide.";
        $message_class = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié | PetSitter's Market</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo"><a href="index.html" style="text-decoration: none; color: inherit;">PetSitter's Market</a></div>
        <nav aria-label="Navigation principale">
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="services.html">Services</a></li>
                <li><a href="contact.html">Contact</a></li>
                <li><a href="login.html" style="font-weight: 500; color: #772f1a; padding: 0.5rem 1rem;">Login</a></li>
                <li><a href="signup.html" style="background-color: #585123; color: white; padding: 0.5rem 1.5rem; border-radius: 8px; font-weight: 500; text-decoration: none;">Sign Up</a></li>
            </ul>
        </nav>
    </header>

    <main id="main-content" style="padding: 3rem 1rem; text-align: center;">
        <h1 style="margin-bottom: 1rem; color: #585123;">Mot de passe oublié</h1>
        <p style="color: #666; margin-bottom: 2rem;">Entrez votre adresse e-mail pour recevoir un lien de réinitialisation.</p>

        <?php if (!empty($message)): ?>
            <div style="max-width: 380px; margin: 0 auto 1.5rem auto; padding: 0.8rem; border-radius: 8px; font-weight: 500; <?php echo $message_class === 'success' ? 'background-color: #e2f0d9; color: #385723;' : 'background-color: #fce4d6; color: #c65911;'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST" style="max-width: 380px; margin: 0 auto; display: flex; flex-direction: column; gap: 1rem;">
            <input type="email" name="email" required placeholder="Rentrez votre adresse e-mail"
                   style="width: 100%; padding: 0.8rem 1rem; border: 1px solid #ccc; border-radius: 8px; font-size: 1rem; box-sizing: border-box;">

            <button type="submit" style="background-color: #585123; color: white; padding: 0.8rem; border: none; border-radius: 8px; font-weight: 500; font-size: 1rem; cursor: pointer; transition: background 0.3s;">
                Envoyer le lien de récupération
            </button>
        </form>
    </main>

    <footer style="margin-top: 4rem;">
        <div class="footer-bottom" style="text-align: center; padding: 1rem;">&copy; 2026 Petsitter's Market. All rights reserved.</div>
    </footer>
</body>
</html>