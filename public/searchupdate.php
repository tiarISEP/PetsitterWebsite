<?php
// public/searchupdate.php
require_once 'includes/db.php';
require_once 'auth.php';

// 1. Secure session start
startSecureSession();

$is_sitter = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'pet-sitter');
$user_id = $_SESSION['user_id'] ?? null;
$error = '';
$success = '';

// Initialize CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --------------------------------------------------------
// HANDLE APPLY ACTION
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply') {
    if (!isUserLoggedIn()) {
        header("Location: login.php");
        exit();
    }
    
    if ($_SESSION['user_type'] !== 'pet-sitter') {
        $error = "Only pet sitters can apply to jobs.";
    } elseif (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Erreur de sécurité (CSRF). Veuillez rafraîchir la page et réessayer.";
    } else {
        $post_id = (int)$_POST['post_id'];
        
        // Verify post exists and is visible
        $stmtCheckPost = $pdo->prepare("SELECT postID FROM post WHERE postID = ? AND Visibility = 1");
        $stmtCheckPost->execute([$post_id]);
        $postExists = $stmtCheckPost->fetch();
        
        if (!$postExists) {
            $error = "Cette annonce n'est plus disponible.";
        } else {
            // Check if already applied
            $stmtCheckApp = $pdo->prepare("SELECT appID FROM application WHERE User_userID = ? AND Post_postID = ?");
            $stmtCheckApp->execute([$user_id, $post_id]);
            if ($stmtCheckApp->fetch()) {
                $error = "Vous avez déjà candidaté à cette offre.";
            } else {
                try {
                    $stmtInsert = $pdo->prepare("INSERT INTO application (CreationDate, Status, User_userID, Post_postID) VALUES (NOW(), 'Pending', ?, ?)");
                    $stmtInsert->execute([$user_id, $post_id]);
                    $success = "Votre candidature a été envoyée avec succès !";
                } catch (PDOException $e) {
                    $error = "Une erreur est survenue lors de l'envoi de votre candidature.";
                }
            }
        }
    }
}

// --------------------------------------------------------
// FETCH FILTER OPTIONS (Locations and Pet Species)
// --------------------------------------------------------
try {
    $stmtLocations = $pdo->query("SELECT DISTINCT location FROM post WHERE Visibility = 1 AND location IS NOT NULL AND location != '' ORDER BY location ASC");
    $locations = $stmtLocations->fetchAll(PDO::FETCH_COLUMN);
    
    $stmtSpecies = $pdo->query("SELECT DISTINCT species FROM animal WHERE species IS NOT NULL AND species != '' ORDER BY species ASC");
    $species_list = $stmtSpecies->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $locations = [];
    $species_list = [];
}

// --------------------------------------------------------
// SEARCH INPUTS & QUERY
// --------------------------------------------------------
$search = trim($_GET['search'] ?? '');
$location_filter = trim($_GET['location'] ?? '');
$pet_filter = trim($_GET['pet_type'] ?? '');

$sql = "
SELECT DISTINCT
    p.postID,
    p.Title,
    p.Description,
    p.Price,
    p.CreationDate,
    p.Visibility,
    p.location,
    p.start_date,
    p.end_date,
    p.service_type,
    p.payment_type,
    p.image_url,
    a.Name as animal_name,
    a.species as animal_species,
    a.breed as animal_breed,
    a.photo_url as animal_photo
FROM post p
LEFT JOIN post_has_animal pha ON p.postID = pha.Post_postID
LEFT JOIN animal a ON pha.Animal_animalID = a.animalID
WHERE p.Visibility = 1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (p.Title LIKE :search OR p.Description LIKE :search OR a.Name LIKE :search OR a.breed LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($location_filter)) {
    $sql .= " AND p.location = :location";
    $params[':location'] = $location_filter;
}

if (!empty($pet_filter)) {
    $sql .= " AND a.species = :pet_type";
    $params[':pet_type'] = $pet_filter;
}

$sql .= " ORDER BY p.CreationDate DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// --------------------------------------------------------
// QUERY SITTER APPLICATIONS & HISTORY
// --------------------------------------------------------
$sitter_apps = [];
$applied_post_ids = [];

if ($is_sitter) {
    // Get recent applications (limit 4 for dashboard preview)
    $stmtApps = $pdo->prepare("
        SELECT a.appID, a.Status, a.CreationDate,
               p.postID, p.Title, p.Price, p.location, p.start_date, p.end_date, p.payment_type
        FROM application a
        JOIN post p ON a.Post_postID = p.postID
        WHERE a.User_userID = ?
        ORDER BY a.CreationDate DESC
        LIMIT 4
    ");
    $stmtApps->execute([$user_id]);
    $sitter_apps = $stmtApps->fetchAll();

    // Fetch applied postIDs to disable the Apply button
    $stmtApplied = $pdo->prepare("SELECT Post_postID FROM application WHERE User_userID = ?");
    $stmtApplied->execute([$user_id]);
    $applied_post_ids = $stmtApplied->fetchAll(PDO::FETCH_COLUMN);
}

$pageTitle = "Sitter Dashboard | PetSitter's Market";
require_once 'includes/header.php';
?>

<!-- Link Sitter Dashboard styles -->
<link rel="stylesheet" href="css/sitter-dashboard.css?v=<?php echo time(); ?>">

<main class="sitter-dashboard-page">
    <div class="sitter-dashboard-container">
        
        <!-- Header Section -->
        <div class="sitter-dashboard-header">
            <h1>Sitter dashboard</h1>
            <p>Find pet sitting opportunities and manage your applications</p>
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

        <!-- Search Bar Section -->
        <div class="sitter-search-card">
            <form action="searchupdate.php" method="GET" class="sitter-search-form">
                
                <div class="sitter-search-input-group">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search pet sitting jobs..." value="<?php echo escapeOutput($search); ?>">
                </div>

                <div class="sitter-search-select-group">
                    <i class="fas fa-map-marker-alt"></i>
                    <select name="location">
                        <option value="">Locations</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo escapeOutput($loc); ?>" <?php echo ($location_filter === $loc) ? 'selected' : ''; ?>>
                                <?php echo escapeOutput($loc); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sitter-search-select-group">
                    <i class="fas fa-paw"></i>
                    <select name="pet_type">
                        <option value="">Pets</option>
                        <?php foreach ($species_list as $spec): ?>
                            <option value="<?php echo escapeOutput($spec); ?>" <?php echo ($pet_filter === $spec) ? 'selected' : ''; ?>>
                                <?php echo escapeOutput($spec); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="sitter-search-btn">Search</button>
            </form>
        </div>

        <!-- Dashboard Grid Layout -->
        <div class="sitter-dashboard-grid">
            
            <!-- Left Column: My Applications -->
            <div class="sitter-applications-card">
                <h2>My Applications</h2>
                
                <?php if (!$is_sitter): ?>
                    <!-- Sitter Promo Card for owners / guests -->
                    <div style="text-align: center; padding: 1rem 0;">
                        <i class="fas fa-user-md fa-3x" style="color: #c09040; margin-bottom: 1rem; opacity: 0.7;"></i>
                        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'pet-owner'): ?>
                            <h3 style="color: #705030; font-size: 1.05rem; margin-bottom: 0.5rem;">Hiring Pet Care?</h3>
                            <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">Post ads to find a qualified sitter for your pet.</p>
                            <a href="PostAd.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; width: 100%;">Create an Ad</a>
                        <?php else: ?>
                            <h3 style="color: #705030; font-size: 1.05rem; margin-bottom: 0.5rem;">Join as a Sitter</h3>
                            <p style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">Register to apply for sitting jobs and manage bookings.</p>
                            <a href="signup.php" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; width: 100%; margin-bottom: 0.5rem;">Register Now</a>
                            <a href="login.php" class="sitter-view-all-link" style="margin-top: 0.5rem; font-size: 0.85rem;">Login to your account</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Sitter application list -->
                    <?php if (empty($sitter_apps)): ?>
                        <p style="font-style: italic; color: #888; text-align: center; padding: 1.5rem 0;">Aucune candidature envoyée.</p>
                    <?php else: ?>
                        <div class="sitter-app-list">
                            <?php foreach ($sitter_apps as $app): 
                                $statusClass = strtolower($app['Status']);
                                $duration = '';
                                if ($app['start_date'] && $app['end_date']) {
                                    $diff = strtotime($app['end_date']) - strtotime($app['start_date']);
                                    $days = round($diff / (60 * 60 * 24)) + 1;
                                    $duration = $days . ' day' . ($days > 1 ? 's' : '');
                                }
                            ?>
                                <div class="sitter-app-item">
                                    <div class="sitter-app-header">
                                        <a href="AdDetail.php?id=<?php echo $app['postID']; ?>" class="sitter-app-title">
                                            <?php echo escapeOutput($app['Title']); ?>
                                        </a>
                                        <span class="app-badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($app['Status']); ?>
                                        </span>
                                    </div>
                                    <div class="sitter-app-details">
                                        <?php echo escapeOutput($app['location']); ?>
                                        <?php if ($duration): ?> &bull; <?php echo $duration; ?><?php endif; ?>
                                    </div>
                                    <div class="sitter-app-price">
                                        $<?php echo escapeOutput($app['Price']); ?>/day
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="my_applications.php" class="sitter-view-all-link">View All Applications</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Right Column: Available Jobs -->
            <div class="sitter-jobs-card">
                <div class="sitter-jobs-header-row">
                    <h2>Available Jobs</h2>
                    <span class="sitter-jobs-count"><?php echo count($posts); ?> jobs found</span>
                </div>

                <?php if (empty($posts)): ?>
                    <div style="text-align: center; padding: 4rem 1rem; color: #888;">
                        <i class="fas fa-search fa-3x" style="margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No pet sitting jobs match your criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="sitter-jobs-list">
                        <?php foreach ($posts as $post): 
                            $duration = '';
                            if ($post['start_date'] && $post['end_date']) {
                                $diff = strtotime($post['end_date']) - strtotime($post['start_date']);
                                $days = round($diff / (86400)) + 1;
                                $duration = $days . ' day' . ($days > 1 ? 's' : '');
                            }
                            
                            $formatted_dates = '';
                            if ($post['start_date']) {
                                $formatted_dates = date('M d', strtotime($post['start_date']));
                                if ($post['end_date']) {
                                    $formatted_dates .= ' - ' . date('d', strtotime($post['end_date']));
                                }
                            }
                            
                            $has_applied = in_array($post['postID'], $applied_post_ids);
                        ?>
                            <div class="sitter-job-item">
                                <!-- Pet Photo -->
                                <?php if ($post['image_url'] || $post['animal_photo']): ?>
                                    <img src="<?php echo escapeOutput($post['image_url'] ?: $post['animal_photo']); ?>" alt="Pet Photo" class="sitter-job-photo">
                                <?php else: ?>
                                    <div class="sitter-job-photo" style="display: flex; align-items: center; justify-content: center; background: #faf7f2; color: #c09040;">
                                        <i class="fas fa-paw fa-2x"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- Job details -->
                                <div class="sitter-job-details">
                                    <a href="AdDetail.php?id=<?php echo $post['postID']; ?>" class="sitter-job-title">
                                        <?php echo escapeOutput($post['Title']); ?>
                                    </a>
                                    
                                    <div class="sitter-job-meta">
                                        <div class="sitter-job-meta-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo escapeOutput($post['location']); ?></span>
                                        </div>
                                        <?php if ($formatted_dates): ?>
                                            <div class="sitter-job-meta-item">
                                                <i class="far fa-calendar-alt"></i>
                                                <span><?php echo $formatted_dates; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($duration): ?>
                                            <div class="sitter-job-meta-item">
                                                <i class="far fa-clock"></i>
                                                <span><?php echo $duration; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="sitter-job-description">
                                        <?php echo escapeOutput($post['Description'] ?: 'Aucune description fournie.'); ?>
                                    </div>

                                    <!-- Tags -->
                                    <div class="sitter-job-tags">
                                        <?php if ($post['animal_species']): ?>
                                            <span class="sitter-job-tag"><?php echo escapeOutput($post['animal_species']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($post['animal_breed']): ?>
                                            <span class="sitter-job-tag experience"><?php echo escapeOutput($post['animal_breed']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Price & Action -->
                                <div class="sitter-job-right">
                                    <div class="sitter-job-price-box">
                                        <span class="sitter-job-price">$<?php echo number_format($post['Price'], 0); ?></span>
                                        <span class="sitter-job-price-unit"><?php echo escapeOutput($post['payment_type'] ? strtolower($post['payment_type']) : 'per day'); ?></span>
                                    </div>
                                    
                                    <?php if ($has_applied): ?>
                                        <button class="sitter-job-apply-btn applied" disabled>
                                            <i class="fas fa-check"></i> Applied
                                        </button>
                                    <?php elseif (!$is_sitter): ?>
                                        <a href="login.php" class="sitter-job-apply-btn">Apply</a>
                                    <?php else: ?>
                                        <form method="POST" action="searchupdate.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="apply">
                                            <input type="hidden" name="post_id" value="<?php echo $post['postID']; ?>">
                                            <button type="submit" class="sitter-job-apply-btn">Apply</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <span class="sitter-load-more">Load More Jobs</span>
                <?php endif; ?>
            </div>

        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>