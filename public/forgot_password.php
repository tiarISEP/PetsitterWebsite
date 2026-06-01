<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'vendor/autoload.php';
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

$message = "";
$message_class = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!empty($email)) {
        try {
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

                $mail = new PHPMailer(true);

                // Configuration du serveur d'envoi (Exemple avec Mailtrap ou Gmail)
                $mail->isSMTP();
                $mail->Host       = 'sandbox.smtp.mailtrap.io'; // Remplace par ton hôte SMTP
                $mail->SMTPAuth   = true;
                $mail->Username   = 'TON_IDENTIFIANT';          // Remplace par ton identifiant SMTP
                $mail->Password   = 'TON_MOT_DE_PASSE';         // Remplace par ton mot de passe SMTP
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('no-reply@petsittersmarket.com', "PetSitter's Market");
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = "Reinitialisation de votre mot de passe";
                $mail->Body    = "Bonjour,<br><br>Pour réinitialiser votre mot de passe, cliquez sur le lien ci-dessous :<br><a href='" . $reset_link . "'>" . $reset_link . "</a><br><br>Ce lien expirera dans 1 heure.<br><br>L'équipe PetSitter's Market";
                $mail->AltBody = "Bonjour,\n\nPour réinitialiser votre mot de passe, cliquez sur le lien ci-dessous :\n" . $reset_link;

                $mail->send();
                $message = "Un e-mail de récupération a été envoyé si l'adresse existe.";
                $message_class = "success";
            } else {
                $message = "Un e-mail de récupération a été envoyé si l'adresse existe.";
                $message_class = "success";
            }
        } catch (Exception $e) {
            $message = "Erreur lors de l'envoi de l'e-mail : " . $mail->ErrorInfo;
            $message_class = "error";
        } catch (PDOException $e) {
            $message = "Erreur de base de données : " . $e->getMessage();
            $message_class = "error";
        }
    } else {
        $message = "Veuillez entrer une adresse e-mail valide.";
        $message_class = "error";
    }
}

$pageTitle = "Mot de passe oublié | PetSitter's Market";
require_once 'includes/header.php';
?>

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

<?php
require_once 'includes/footer.php';
?>