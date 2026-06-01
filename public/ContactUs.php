<?php
$pageTitle = "Contact Us | PetSitter's Market";
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

$csrfToken = generateCsrfToken();

$errorMsg = '';
$successMsg = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMsg = "Erreur de sécurité (CSRF). Veuillez rafraîchir la page et réessayer.";
    } else {
        $name = trimInput($_POST['name'] ?? '');
        $email = trimInput($_POST['email'] ?? '');
        $subject = trimInput($_POST['subject'] ?? '');
        $message = trimInput($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $errorMsg = "Veuillez remplir tous les champs obligatoires.";
        } elseif (!validateEmail($email)) {
            $errorMsg = "Le format de l'adresse email est invalide.";
        } else {
            $req = $pdo->prepare("INSERT INTO contact_messages (nom, email, message) VALUES (?, ?, ?)");
            $req->execute([$name, $email, $message]);

            $successMsg = "Merci " . htmlspecialchars($name) . " ! Votre message a bien été envoyé. Nous vous répondrons rapidement.";

            $name = $email = $subject = $message = '';
        }
    }
}

require_once 'includes/header.php';
?>

<main id="main-content" style="max-width: 800px; margin: 3rem auto; padding: 0 1rem;">
    <div class="card">
        <h1 class="title-primary">Contact Us</h1>
        <p class="text-subtitle">We'd love to hear from you! Please fill out the form below.</p>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $errorMsg; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $successMsg; ?></span>
            </div>
        <?php endif; ?>

        <form action="ContactUs.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" placeholder="John Doe" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="john@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" placeholder="How can we help?" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" rows="5" placeholder="Write your message here..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Send Message</button>
        </form>
    </div>
</main>

<?php
require_once 'includes/footer.php';
?>