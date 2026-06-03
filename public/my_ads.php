<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();
redirectToLogin();

if ($_SESSION['user_type'] !== 'pet-owner' && $_SESSION['user_type'] !== 'admin') {
    header("Location: profile.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Erreur de sécurité (CSRF).";
    } else {
        $post_id = (int)$_POST['post_id'];
        
        // Verify ownership
        $stmtCheck = $pdo->prepare("SELECT postID FROM post WHERE postID = ? AND User_userID = ?");
        $stmtCheck->execute([$post_id, $user_id]);
        if ($stmtCheck->fetch()) {
            // Check for applications
            $stmtApp = $pdo->prepare("SELECT COUNT(*) FROM application WHERE Post_postID = ?");
            $stmtApp->execute([$post_id]);
            $appCount = $stmtApp->fetchColumn();

            if ($appCount > 0) {
                $error = "Impossible de supprimer cette annonce car des sitters y ont déjà candidaté.";
            } else {
                try {
                    // Delete relation first
                    $pdo->prepare("DELETE FROM post_has_animal WHERE Post_postID = ?")->execute([$post_id]);
                    // Delete post
                    $pdo->prepare("DELETE FROM post WHERE postID = ?")->execute([$post_id]);
                    $success = "Annonce supprimée avec succès.";
                } catch(PDOException $e) {
                    $error = "Erreur lors de la suppression de l'annonce.";
                }
            }
        } else {
            $error = "Annonce introuvable ou non autorisée.";
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user's ads with animal data and applicant count
$query = "
    SELECT p.*, a.Name as animal_name, a.photo_url as animal_photo,
           (SELECT COUNT(*) FROM application app WHERE app.Post_postID = p.postID) as applicant_count
    FROM post p
    LEFT JOIN post_has_animal pha ON p.postID = pha.Post_postID
    LEFT JOIN animal a ON pha.Animal_animalID = a.animalID
    WHERE p.User_userID = ?
    ORDER BY p.CreationDate DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$ads = $stmt->fetchAll();

$pageTitle = "Mes Annonces | PetSitter's Market";
require_once 'includes/header.php';
?>

<main id="main-content" class="container" style="padding: 2rem 1rem; min-height: 70vh;">
    <div class="content" style="max-width: 1200px; margin: 0 auto;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 class="title-primary" style="margin: 0;">Mes Annonces</h1>
            <a href="PostAd.php" class="btn btn-primary"><i class="fas fa-plus"></i> Créer une annonce</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo escapeOutput($success); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($ads)): ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <i class="fas fa-bullhorn" style="font-size: 3rem; color: var(--clr-primary); margin-bottom: 1rem;"></i>
                <h2>Vous n'avez pas encore d'annonce en ligne.</h2>
                <p style="margin-top: 1rem;">Créez une annonce pour trouver le sitter parfait pour votre animal.</p>
                <a href="PostAd.php" class="btn btn-primary" style="margin-top: 1.5rem;">Créer ma première annonce</a>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
                <?php foreach ($ads as $ad): ?>
                    <div class="card" style="display: flex; flex-direction: column; height: 100%; position: relative;">
                        
                        <?php if ($ad['applicant_count'] > 0): ?>
                            <div style="position: absolute; top: -10px; right: -10px; background: #e74c3c; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                                <?php echo $ad['applicant_count']; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($ad['animal_photo'] || $ad['image_url']): ?>
                            <div style="width: 100%; height: 180px; overflow: hidden; border-radius: var(--border-radius-md) var(--border-radius-md) 0 0; margin: -1.5rem -1.5rem 1rem -1.5rem; width: calc(100% + 3rem);">
                                <img src="<?php echo escapeOutput($ad['image_url'] ?: $ad['animal_photo']); ?>" alt="Photo de l'annonce" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                        <?php endif; ?>
                        
                        <h3 style="color: var(--clr-text-title); margin-bottom: 0.5rem; font-size: 1.2rem;">
                            <?php echo escapeOutput($ad['Title']); ?>
                        </h3>
                        
                        <div style="margin-bottom: 1rem; flex-grow: 1; font-size: 0.95rem;">
                            <p style="color: #666; margin-bottom: 0.5rem;"><i class="fas fa-paw" style="width: 20px;"></i> <strong>Pour :</strong> <?php echo escapeOutput($ad['animal_name'] ?? 'Inconnu'); ?></p>
                            <p style="color: #666; margin-bottom: 0.5rem;"><i class="fas fa-briefcase" style="width: 20px;"></i> <strong>Service :</strong> <?php echo escapeOutput($ad['service_type'] ?: 'Garde classique'); ?></p>
                            <?php if ($ad['start_date'] && $ad['end_date']): ?>
                                <p style="color: #666; margin-bottom: 0.5rem;"><i class="fas fa-calendar-alt" style="width: 20px;"></i> <strong>Dates :</strong> <?php echo escapeOutput(date('d/m/Y', strtotime($ad['start_date']))) . ' au ' . escapeOutput(date('d/m/Y', strtotime($ad['end_date']))); ?></p>
                            <?php endif; ?>
                            <p style="color: #666; margin-bottom: 0.5rem;"><i class="fas fa-money-bill-wave" style="width: 20px;"></i> <strong>Prix :</strong> <?php echo escapeOutput($ad['Price']); ?> $ <?php echo escapeOutput($ad['payment_type'] ? '(' . $ad['payment_type'] . ')' : ''); ?></p>
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 1rem; margin-top: auto;">
                            <?php if ($ad['applicant_count'] > 0): ?>
                                <a href="ad_applications.php?id=<?php echo $ad['postID']; ?>" class="btn btn-cta" style="text-align: center; width: 100%;"><i class="fas fa-users"></i> Voir candidatures (<?php echo $ad['applicant_count']; ?>)</a>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="edit_ad.php?id=<?php echo $ad['postID']; ?>" class="btn btn-text" style="flex: 1; text-align: center; border: 1px solid var(--clr-brand);"><i class="fas fa-edit"></i> Modifier</a>
                                
                                <form method="POST" action="my_ads.php" style="flex: 1;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette annonce ?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="post_id" value="<?php echo $ad['postID']; ?>">
                                    <button type="submit" class="btn btn-text" style="width: 100%; color: var(--clr-error-text); border: 1px solid var(--clr-error-border);" <?php echo ($ad['applicant_count'] > 0) ? 'disabled title="Impossible de supprimer une annonce avec des candidatures"' : ''; ?>><i class="fas fa-trash-alt"></i> Supprimer</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
