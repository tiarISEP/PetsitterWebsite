<?php 
require_once 'includes/db.php';
require_once 'auth.php';
startSecureSession();

// Fetch top 3 pet sitters dynamically
try {
    $stmtSitters = $pdo->query("
        SELECT u.id, u.username, u.first_name, u.last_name, u.public_id, u.avatar_url, u.bio,
               COALESCE(AVG(r.rating), 0) as avg_rating,
               COUNT(r.id) as review_count
        FROM users u
        LEFT JOIN reviews r ON u.id = r.rated_user_id AND r.is_disabled = 0
        WHERE u.user_type = 'pet-sitter' AND u.is_banned = 0
        GROUP BY u.id
        ORDER BY avg_rating DESC, review_count DESC
        LIMIT 3
    ");
    $topSitters = $stmtSitters->fetchAll();
} catch (PDOException $e) {
    error_log("Failed to fetch top sitters: " . $e->getMessage());
    $topSitters = [];
}

$pageTitle = "Home | PetSitter's Market";
require_once 'includes/header.php'; 
?>

<main id="main-content">
    <section class="hero">
        <div class="hero-content">
            <h1>Find the perfect pet sitter</h1>
            <p>Connect with trusted, loving pet sitters in your neighborhood.</p>
            
            <div class="search-bar card">
                <form action="searchupdate.php" method="GET" class="search-form">
                    <div class="input-group" style="flex: 2;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Rechercher par titre ou mot-clé...">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="services-section container">
        <h2 class="title-primary">Our Pet Care Services</h2>
        <p class="text-subtitle">Professional care tailored to your pet's needs</p>
        
        <div class="grid-layout">
            <div class="card text-center">
                <i class="fas fa-home fa-3x mb-1" style="color: var(--clr-primary);"></i>
                <h3>Pet Sitting</h3>
                <p>In-home care while you're away</p>
            </div>
            <div class="card text-center">
                <i class="fas fa-dog fa-3x mb-1" style="color: var(--clr-primary);"></i>
                <h3>Dog Walking</h3>
                <p>Daily exercise and outdoor fun</p>
            </div>
            <div class="card text-center">
                <i class="fas fa-cut fa-3x mb-1" style="color: var(--clr-primary);"></i>
                <h3>Pet Grooming</h3>
                <p>Professional grooming services</p>
            </div>
            <div class="card text-center">
                <i class="fas fa-stethoscope fa-3x mb-1" style="color: var(--clr-primary);"></i>
                <h3>Vet Visits</h3>
                <p>Transportation to appointments</p>
            </div>
        </div>
    </section>

    <section class="sitters-section container">
        <h2 class="title-primary">Top Rated Pet Sitters</h2>
        <div class="grid-layout">
            <?php if (empty($topSitters)): ?>
                <p style="text-align: center; color: #666; width: 100%; grid-column: 1 / -1;">No registered pet sitters found at the moment.</p>
            <?php else: ?>
                <?php foreach ($topSitters as $sitter): 
                    $rating = round($sitter['avg_rating'], 1);
                    $full_stars = (int)$rating;
                    $has_partial = ($rating - $full_stars) >= 0.5;
                    $empty_stars = 5 - $full_stars - ($has_partial ? 1 : 0);
                    $stars_html = str_repeat('★', $full_stars) . ($has_partial ? '½' : '') . str_repeat('☆', $empty_stars);
                ?>
                    <div class="card sitter-card" style="display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <?php if (!empty($sitter['avatar_url'])): ?>
                                <img src="<?php echo htmlspecialchars($sitter['avatar_url']); ?>" alt="Avatar" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin: 0 auto 1rem; display: block;">
                            <?php else: ?>
                                <div class="sitter-img" style="display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, var(--clr-bg-page), var(--clr-primary)); color:var(--clr-text-title); font-weight:bold; font-size:1.5rem;"><?php echo strtoupper(substr($sitter['first_name'] ?? $sitter['username'], 0, 2)); ?></div>
                            <?php endif; ?>
                            
                            <h3><?php echo htmlspecialchars($sitter['first_name'] . ' ' . $sitter['last_name']); ?></h3>
                            <p class="rating" style="margin-bottom: 0.5rem;"><?php echo $stars_html; ?> <?php echo $rating > 0 ? $rating : 'No ratings'; ?> (<?php echo $sitter['review_count']; ?> reviews)</p>
                            <p class="description" style="font-size: 0.9rem; color: #666; margin-bottom: 1rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; height: 4.2em; line-height: 1.4;">
                                <?php echo htmlspecialchars($sitter['bio'] ?: 'No biography provided yet.'); ?>
                            </p>
                        </div>
                        <a href="petsitter.php?id=<?php echo htmlspecialchars($sitter['public_id']); ?>" class="btn btn-primary" style="margin-top: 1rem;">View Profile</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>
