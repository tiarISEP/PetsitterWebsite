<?php
// public/my_applications.php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();
redirectToLogin();

if ($_SESSION['user_type'] !== 'pet-sitter') {
    header("Location: profile.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle cancel application action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Erreur de sécurité (CSRF).";
    } else {
        $app_id = (int)$_POST['app_id'];
        
        // Verify application belongs to user and is still pending
        $stmtCheck = $pdo->prepare("SELECT Status FROM application WHERE appID = ? AND User_userID = ?");
        $stmtCheck->execute([$app_id, $user_id]);
        $app = $stmtCheck->fetch();
        
        if ($app) {
            if ($app['Status'] === 'Pending' || $app['Status'] === 'En attente') {
                try {
                    $pdo->prepare("DELETE FROM application WHERE appID = ?")->execute([$app_id]);
                    $success = "Candidature annulée avec succès.";
                } catch(PDOException $e) {
                    $error = "Erreur lors de l'annulation de la candidature.";
                }
            } else {
                $error = "Impossible d'annuler une candidature qui a déjà été traitée.";
            }
        } else {
            $error = "Candidature introuvable ou non autorisée.";
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch all applications submitted by the sitter
$query = "
    SELECT a.appID, a.Status, a.CreationDate,
           p.postID, p.Title, p.Price, p.location, p.start_date, p.end_date, p.payment_type, p.image_url
    FROM application a
    JOIN post p ON a.Post_postID = p.postID
    WHERE a.User_userID = ?
    ORDER BY a.CreationDate DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$applications = $stmt->fetchAll();

$pageTitle = "My Applications | PetSitter's Market";
require_once 'includes/header.php';
?>

<!-- Link Sitter Dashboard styles -->
<link rel="stylesheet" href="css/sitter-dashboard.css?v=<?php echo time(); ?>">

<main class="sitter-dashboard-page">
    <div class="sitter-dashboard-container">
        
        <div class="sitter-dashboard-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1>My Applications</h1>
                <p>Track and manage the status of jobs you have applied to</p>
            </div>
            <a href="searchupdate.php" class="btn btn-primary" style="background-color: #6d4b29; color: white;">
                <i class="fas fa-search" style="margin-right: 0.5rem;"></i> Find More Jobs
            </a>
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

        <div class="sitter-jobs-card" style="width: 100%;">
            <div class="sitter-jobs-header-row">
                <h2>Applications History</h2>
                <span class="sitter-jobs-count"><?php echo count($applications); ?> applications submitted</span>
            </div>

            <?php if (empty($applications)): ?>
                <div style="text-align: center; padding: 4rem 2rem;">
                    <i class="fas fa-briefcase" style="font-size: 3.5rem; color: #c09040; margin-bottom: 1.5rem; opacity: 0.6;"></i>
                    <h3 style="color: #705030; margin-bottom: 0.5rem;">No Applications Found</h3>
                    <p style="color: #666; max-width: 500px; margin: 0 auto 1.5rem;">
                        You haven't applied to any pet sitting jobs yet. Browse available listings and start applying today!
                    </p>
                    <a href="searchupdate.php" class="btn btn-primary">Browse Available Jobs</a>
                </div>
            <?php else: ?>
                <div class="sitter-jobs-list">
                    <?php foreach ($applications as $app): 
                        $statusClass = strtolower($app['Status']);
                        // Translate display status nicely
                        $displayStatus = htmlspecialchars($app['Status']);
                        
                        $duration = '';
                        if ($app['start_date'] && $app['end_date']) {
                            $diff = strtotime($app['end_date']) - strtotime($app['start_date']);
                            $days = round($diff / (60 * 60 * 24)) + 1;
                            $duration = $days . " day" . ($days > 1 ? "s" : "");
                        }
                    ?>
                        <div class="sitter-job-item" style="grid-template-columns: 80px 1fr 180px;">
                            
                            <?php if ($app['image_url']): ?>
                                <img src="<?php echo escapeOutput($app['image_url']); ?>" alt="Pet Photo" class="sitter-job-photo">
                            <?php else: ?>
                                <div class="sitter-job-photo" style="display: flex; align-items: center; justify-content: center; background: #faf7f2; border: 1px solid #f2e2cb; color: #c09040;">
                                    <i class="fas fa-paw fa-2x"></i>
                                </div>
                            <?php endif; ?>

                            <div class="sitter-job-details">
                                <a href="AdDetail.php?id=<?php echo $app['postID']; ?>" class="sitter-job-title">
                                    <?php echo escapeOutput($app['Title']); ?>
                                </a>
                                <div class="sitter-job-meta">
                                    <div class="sitter-job-meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo escapeOutput($app['location']); ?></span>
                                    </div>
                                    <?php if ($app['start_date']): ?>
                                        <div class="sitter-job-meta-item">
                                            <i class="far fa-calendar-alt"></i>
                                            <span><?php echo date('M d', strtotime($app['start_date'])) . ($app['end_date'] ? ' - ' . date('M d', strtotime($app['end_date'])) : ''); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($duration): ?>
                                        <div class="sitter-job-meta-item">
                                            <i class="far fa-clock"></i>
                                            <span><?php echo $duration; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #888; margin-top: auto;">
                                    Applied on <?php echo date('M d, Y at H:i', strtotime($app['CreationDate'])); ?>
                                </div>
                            </div>

                            <div class="sitter-job-right">
                                <div class="sitter-job-price-box">
                                    <span class="sitter-job-price">$<?php echo number_format($app['Price'], 2); ?></span>
                                    <span class="sitter-job-price-unit"><?php echo escapeOutput($app['payment_type'] ?: 'per day'); ?></span>
                                </div>
                                
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem; width: 100%;">
                                    <span class="app-badge <?php echo $statusClass; ?>" style="padding: 0.35rem 1rem; font-size: 0.8rem; border-radius: 8px; text-align: center; width: 100%; display: inline-block;">
                                        <?php echo $displayStatus; ?>
                                    </span>
                                    
                                    <?php if ($app['Status'] === 'Pending' || $app['Status'] === 'En attente' || $app['Status'] === 'pending'): ?>
                                        <form method="POST" action="my_applications.php" style="width: 100%;" onsubmit="return confirm('Are you sure you want to cancel this application?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="app_id" value="<?php echo $app['appID']; ?>">
                                            <button type="submit" class="btn btn-text" style="width: 100%; color: var(--clr-error-text); border: 1px solid var(--clr-error-border); padding: 0.35rem; font-size: 0.8rem; border-radius: 6px;">
                                                <i class="fas fa-times-circle"></i> Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
