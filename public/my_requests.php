<?php
$pageTitle = "Mes Demandes | PetSitter's Market";
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();
redirectToLogin();

$user = getUserById($pdo, $_SESSION['user_id']);
if (!$user) {
    logoutUser($pdo);
}

$csrfToken = generateCsrfToken();
$errorMsg = '';
$successMsg = '';

if (isset($_GET['success'])) {
    $successMsg = "Votre réponse a bien été envoyée. Le fil de discussion a été mis à jour et l'administrateur en a été notifié.";
}

// Handle User Reply Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errorMsg = "Erreur de sécurité (CSRF). Veuillez rafraîchir la page et réessayer.";
    } else {
        $message_id = (int)($_POST['message_id'] ?? 0);
        $user_reply = trim($_POST['user_reply'] ?? '');

        if ($message_id <= 0 || empty($user_reply)) {
            $errorMsg = "Veuillez saisir une réponse valide.";
        } else {
            // Fetch message to verify ownership
            $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
            $stmt->execute([$message_id]);
            $msg = $stmt->fetch();

            if (!$msg || $msg['email'] !== $user['email']) {
                $errorMsg = "Demande introuvable ou accès non autorisé.";
            } elseif ($msg['status'] === 'archive') {
                $errorMsg = "Cette demande est archivée. Vous ne pouvez plus y répondre.";
            } elseif (empty($msg['reply_message'])) {
                $errorMsg = "Vous ne pouvez pas répondre tant que l'administrateur n'a pas répondu.";
            } else {
                // Construct new thread message
                $prev_admin_reply_header = "\n\n--- Réponse de l'admin le " . date('d/m/Y H:i', strtotime($msg['replied_at'])) . " ---\n";
                $prev_admin_reply_body = $msg['reply_message'];
                
                $user_reply_header = "\n\n--- Réponse de l'utilisateur le " . date('d/m/Y H:i') . " ---\n";
                $user_reply_body = $user_reply;
                
                $new_message = $msg['message'] . $prev_admin_reply_header . $prev_admin_reply_body . $user_reply_header . $user_reply_body;

                // Update database
                $update_stmt = $pdo->prepare("UPDATE contact_messages SET message = ?, status = 'non_traite', reply_message = NULL, replied_at = NULL WHERE id = ?");
                $update_stmt->execute([$new_message, $message_id]);

                // Redirect to avoid resubmission
                header("Location: my_requests.php?success=1");
                exit();
            }
        }
    }
}

// Fetch all messages matching the user's email
$stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE email = ? ORDER BY created_at DESC");
$stmt->execute([$user['email']]);
$requests = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<main id="main-content" style="max-width: 900px; margin: 3rem auto; padding: 0 1rem; width: 100%;">
    <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 class="title-primary" style="text-align: left; margin-bottom: 0.25rem;">Mes Demandes de Contact</h1>
            <p style="color: var(--clr-text-title); opacity: 0.8; font-size: 0.95rem;">
                Retrouvez l'historique de vos demandes envoyées au support et échangez avec les administrateurs.
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-primary" style="padding: 0.75rem 1.25rem; font-size: 0.9rem;">
            <i class="fas fa-arrow-left" style="margin-right: 0.5rem;"></i> Retour Dashboard
        </a>
    </div>

    <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($errorMsg); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMsg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($successMsg); ?></span>
        </div>
    <?php endif; ?>

    <?php if (empty($requests)): ?>
        <div class="card" style="text-align: center; padding: 3rem 2rem;">
            <div style="font-size: 3rem; color: var(--clr-brand); margin-bottom: 1rem; opacity: 0.6;">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <h2 style="color: var(--clr-text-title); margin-bottom: 0.5rem;">Aucune demande trouvée</h2>
            <p style="color: var(--clr-text-main); margin-bottom: 1.5rem; max-width: 500px; margin-inline: auto;">
                Vous n'avez pas encore envoyé de message via notre formulaire de contact. 
                Si vous rencontrez un problème ou avez une question, n'hésitez pas à nous écrire.
            </p>
            <a href="ContactUs.php" class="btn btn-cta" style="display: inline-flex; width: auto;">
                Nous contacter
            </a>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 2rem; width: 100%;">
            <?php foreach ($requests as $req): ?>
                <div class="card" style="padding: 2rem; position: relative; border-left: 6px solid <?php 
                    echo $req['status'] === 'archive' ? 'var(--clr-cta)' : ($req['status'] === 'en_cours' ? 'var(--clr-primary)' : 'var(--clr-brand)'); 
                ?>;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px dashed rgba(112, 80, 48, 0.1); padding-bottom: 0.8rem;">
                        <div>
                            <h2 style="color: var(--clr-text-title); font-size: 1.25rem; margin-bottom: 0.25rem;">
                                <?php echo escapeOutput($req['subject']); ?>
                            </h2>
                            <span style="font-size: 0.85rem; color: #888;">
                                <i class="far fa-calendar-alt"></i> Envoyé le <?php echo date('d/m/Y à H:i', strtotime($req['created_at'])); ?>
                            </span>
                        </div>
                        
                        <div>
                            <?php if ($req['status'] === 'non_traite'): ?>
                                <span class="badge-status status-waiting">En attente</span>
                            <?php elseif ($req['status'] === 'en_cours'): ?>
                                <span class="badge-status status-process">En cours</span>
                            <?php else: ?>
                                <span class="badge-status status-resolved">Traité</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="color: var(--clr-text-title); font-size: 0.9rem; margin-bottom: 0.5rem; font-weight: 600;">Fil de discussion :</h4>
                        <div style="background: #faf7f2; padding: 1rem; border-radius: 8px; color: #555; white-space: pre-wrap; font-size: 0.95rem; border: 1px solid rgba(112, 80, 48, 0.05); line-height: 1.5;">
                            <?php echo escapeOutput($req['message']); ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($req['reply_message'])): ?>
                        <!-- Admin Reply Box -->
                        <div style="background: #f4faf6; border: 1px solid var(--clr-success-border); border-radius: 12px; padding: 1.25rem; margin-top: 1rem; margin-bottom: 1.5rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <span style="font-weight: 700; color: var(--clr-success-text); display: flex; align-items: center; gap: 0.4rem; font-size: 0.95rem;">
                                    <i class="fas fa-reply-all"></i> Réponse de l'administrateur :
                                </span>
                                <span style="font-size: 0.8rem; color: #666;">
                                    Répondu le <?php echo date('d/m/Y à H:i', strtotime($req['replied_at'])); ?>
                                </span>
                            </div>
                            <div style="color: #2b5c3d; white-space: pre-wrap; line-height: 1.6; font-size: 0.95rem;">
                                <?php echo escapeOutput($req['reply_message']); ?>
                            </div>
                        </div>

                        <!-- User Reply Form (Allowed since status is not archive and there is an admin response) -->
                        <?php if ($req['status'] !== 'archive'): ?>
                            <div style="border-top: 1px solid #eee; padding-top: 1.5rem; margin-top: 1.5rem;">
                                <h4 style="color: var(--clr-text-title); font-size: 0.95rem; margin-bottom: 0.75rem; font-weight: 600;">Répondre à l'administrateur :</h4>
                                <form method="POST" action="my_requests.php" style="display: flex; flex-direction: column; gap: 0.75rem;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="message_id" value="<?php echo $req['id']; ?>">
                                    <textarea name="user_reply" rows="4" placeholder="Saisissez votre réponse ici..." required style="width: 100%; border: 2px solid #e2d9cd; border-radius: 12px; padding: 1rem; font-family: inherit; font-size: 0.95rem; line-height: 1.5; resize: vertical; outline: none; transition: border-color 0.3s;" onfocus="this.style.borderColor='var(--clr-primary)'" onblur="this.style.borderColor='#e2d9cd'"></textarea>
                                    <button type="submit" class="btn btn-cta" style="width: auto; align-self: flex-start; padding: 0.75rem 1.5rem; font-size: 0.9rem; font-weight: 700; display: inline-flex; gap: 0.5rem; align-items: center;">
                                        <i class="fas fa-paper-plane"></i> Envoyer ma réponse
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <?php if ($req['status'] === 'archive'): ?>
                            <div style="background: #fafafa; border: 1px solid #eee; border-radius: 12px; padding: 1rem; text-align: center; color: #777; font-size: 0.9rem; margin-top: 1rem;">
                                <i class="fas fa-archive" style="margin-right: 0.5rem;"></i> Cette demande a été archivée et est close.
                            </div>
                        <?php else: ?>
                            <div style="background: #fafafa; border: 1px dashed #ddd; border-radius: 12px; padding: 1rem; text-align: center; color: #777; font-size: 0.9rem; margin-top: 1rem;">
                                <i class="fas fa-hourglass-half" style="margin-right: 0.5rem;"></i> Pas encore de réponse de l'équipe d'administration.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<style>
.badge-status {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.status-waiting {
    background-color: #fdf6b2;
    color: #723b10;
    border: 1px solid #fce8e6;
}
.status-process {
    background-color: #e1effe;
    color: #1e429f;
    border: 1px solid #c3ddfd;
}
.status-resolved {
    background-color: var(--clr-success-bg);
    color: var(--clr-success-text);
    border: 1px solid var(--clr-success-border);
}
</style>

<?php
require_once 'includes/footer.php';
?>
