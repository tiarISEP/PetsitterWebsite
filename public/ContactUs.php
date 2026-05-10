<?php 
// 1. Démarrage de la session (TOUJOURS en première ligne)
session_start(); 

// 2. Initialisation des variables de message
$errorMsg = '';
$successMsg = '';

// 3. Traitement du formulaire si la méthode est POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Nettoyage des entrées pour la sécurité (anti-XSS)
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars(trim($_POST['subject'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));

    // Validation des données
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $errorMsg = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = "Le format de l'adresse email est invalide.";
    } else {
        // C'est ici que tu ajouteras plus tard ta requête SQL (PDO) pour insérer dans la base de données
        // Exemple : $stmt = $pdo->prepare("INSERT INTO messages ...");
        
        $successMsg = "Merci $name ! Votre message a bien été envoyé. Nous vous répondrons rapidement.";
    }
}

// 4. Configuration de la page et inclusion de l'en-tête
$pageTitle = "Contact Us | PetSitter's Market";
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
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" placeholder="John Doe" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="john@example.com" required>
            </div>

            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" placeholder="How can we help?" required>
            </div>

            <div class="form-group">
                <label for="message">Message</label>
                <textarea id="message" name="message" rows="5" placeholder="Write your message here..." required></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Send Message</button>
        </form>
    </div>

</main>

<?php 
// 6. Inclusion du pied de page
require_once 'includes/footer.php'; 
?>