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
$is_admin = ($_SESSION['user_type'] === 'admin');
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if ($post_id === 0) {
    header("Location: my_ads.php");
    exit();
}

// Verify post ownership
$stmtCheck = $pdo->prepare("SELECT Title FROM post WHERE postID = ? " . ($is_admin ? "" : "AND User_userID = ?"));
if ($is_admin) {
    $stmtCheck->execute([$post_id]);
} else {
    $stmtCheck->execute([$post_id, $user_id]);
}
$post = $stmtCheck->fetch();

if (!$post) {
    header("Location: my_ads.php");
    exit();
}

// Handle Status Update Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['app_id'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Erreur de sécurité (CSRF).";
    } else {
        $app_id = (int)$_POST['app_id'];
        $action = $_POST['action'];
        $new_status = '';
        
        if ($action === 'accept') {
            $new_status = 'Accepted';
        } elseif ($action === 'reject') {
            $new_status = 'Rejected';
        }
        
        if ($new_status) {
            // Verify application belongs to this post
            $stmtAppCheck = $pdo->prepare("SELECT appID FROM application WHERE appID = ? AND Post_postID = ?");
            $stmtAppCheck->execute([$app_id, $post_id]);
            if ($stmtAppCheck->fetch()) {
                try {
                    $stmtUpdate = $pdo->prepare("UPDATE application SET Status = ? WHERE appID = ?");
                    $stmtUpdate->execute([$new_status, $app_id]);
                    $success = "Le statut de la candidature a été mis à jour avec succès.";
                } catch(PDOException $e) {
                    $error = "Erreur lors de la mise à jour du statut.";
                }
            } else {
                $error = "Candidature introuvable pour cette annonce.";
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch applicants with their details and average rating
$query = "
    SELECT a.appID, a.CreationDate, a.Status, a.User_userID,
           u.first_name, u.last_name, u.username, u.avatar_url, u.bio, u.public_id,
           (SELECT COALESCE(AVG(rating), 0) FROM reviews r WHERE r.rated_user_id = u.id AND r.is_disabled = 0) as avg_rating,
           (SELECT COUNT(id) FROM reviews r WHERE r.rated_user_id = u.id AND r.is_disabled = 0) as review_count
    FROM application a
    JOIN users u ON a.User_userID = u.id
    WHERE a.Post_postID = ?
    ORDER BY a.CreationDate DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute([$post_id]);
$applications = $stmt->fetchAll();

$pageTitle = "Candidatures - " . escapeOutput($post['Title']) . " | PetSitter's Market";
require_once 'includes/header.php';
?>

<main id="main-content" class="container" style="padding: 2rem 1rem; min-height: 70vh;">
    <div class="content" style="max-width: 1200px; margin: 0 auto;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <a href="my_ads.php" style="color: var(--clr-brand); text-decoration: none; font-size: 0.9rem; margin-bottom: 0.5rem; display: inline-block;">
                    <i class="fas fa-arrow-left"></i> Retour à mes annonces
                </a>
                <h1 class="title-primary" style="margin: 0;">Candidatures pour "<?php echo escapeOutput($post['Title']); ?>"</h1>
            </div>
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

        <?php if (empty($applications)): ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <i class="fas fa-users-slash" style="font-size: 3rem; color: var(--clr-primary); margin-bottom: 1rem;"></i>
                <h2>Aucune candidature pour le moment.</h2>
                <p style="margin-top: 1rem;">Les sitters intéressés par votre annonce apparaîtront ici.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
                <?php foreach ($applications as $app): 
                    $display_name = trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')) ?: $app['username'];
                    $rating = round($app['avg_rating'], 1);
                ?>
                    <div class="card" style="display: flex; flex-direction: column; height: 100%; position: relative;">
                        
                        <div style="position: absolute; top: 1rem; right: 1rem;">
                            <?php if ($app['Status'] === 'Pending'): ?>
                                <span class="badge" style="background: #f3f4f6; color: #4b5563; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">En attente</span>
                            <?php elseif ($app['Status'] === 'Accepted'): ?>
                                <span class="badge" style="background: #dcfce7; color: #15803d; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">Acceptée</span>
                            <?php elseif ($app['Status'] === 'Rejected'): ?>
                                <span class="badge" style="background: #fee2e2; color: #b91c1c; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">Refusée</span>
                            <?php else: ?>
                                <span class="badge" style="background: #e5e7eb; color: #374151; padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: bold;"><?php echo escapeOutput($app['Status']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <?php if (!empty($app['avatar_url'])): ?>
                                <img src="<?php echo escapeOutput($app['avatar_url']); ?>" alt="Avatar" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--clr-primary);">
                            <?php else: ?>
                                <div style="width: 60px; height: 60px; border-radius: 50%; background: #c09040; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: bold;">
                                    <?php echo strtoupper(substr($display_name, 0, 2)); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <h3 style="color: var(--clr-text-title); margin-bottom: 0.2rem; font-size: 1.2rem;">
                                    <?php echo escapeOutput($display_name); ?>
                                </h3>
                                <p style="color: #f5b041; margin: 0; font-size: 0.9rem;">
                                    <i class="fas fa-star"></i> <?php echo $rating > 0 ? $rating : 'N/A'; ?> 
                                    <span style="color: #888;">(<?php echo $app['review_count']; ?> avis)</span>
                                </p>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem; flex-grow: 1; font-size: 0.95rem;">
                            <p style="color: #555; margin-bottom: 0.5rem; font-style: italic;">"<?php echo escapeOutput($app['bio'] ?: 'Aucune biographie fournie.'); ?>"</p>
                            <p style="color: #888; font-size: 0.85rem; margin-top: 1rem;"><i class="far fa-clock"></i> Candidature envoyée le <?php echo date('d/m/Y', strtotime($app['CreationDate'])); ?></p>
                        </div>
                        
                        <div style="display: flex; flex-direction: column; gap: 0.5rem; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 1rem; margin-top: auto;">
                            <a href="petsitter.php?id=<?php echo $app['public_id']; ?>" class="btn btn-text" style="text-align: center; width: 100%; border: 1px solid var(--clr-brand); margin-bottom: 0.5rem;"><i class="fas fa-user"></i> Voir le profil</a>
                            
                            <?php if ($app['Status'] === 'Pending'): ?>
                                <div style="display: flex; gap: 0.5rem;">
                                    <form method="POST" action="ad_applications.php?id=<?php echo $post_id; ?>" style="flex: 1;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <input type="hidden" name="app_id" value="<?php echo $app['appID']; ?>">
                                        <button type="submit" class="btn btn-primary" style="width: 100%; background-color: #10b981; color: white;"><i class="fas fa-check"></i> Accepter</button>
                                    </form>
                                    
                                    <form method="POST" action="ad_applications.php?id=<?php echo $post_id; ?>" style="flex: 1;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="app_id" value="<?php echo $app['appID']; ?>">
                                        <button type="submit" class="btn btn-text" style="width: 100%; color: var(--clr-error-text); border: 1px solid var(--clr-error-border);"><i class="fas fa-times"></i> Refuser</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
