<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

// URL: ?id=550e8400-e29b-41d4-a716-446655440000
if (!empty($_GET['id'])) {
    $public_id = trim($_GET['id']);

    // Validate UUID format before hitting the DB
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $public_id)) {
        header("Location: index.php");
        exit();
    }

    $stmt = $pdo->prepare(
        "SELECT id, public_id, username, first_name, last_name, bio, avatar_url, user_type, created_at
         FROM users WHERE public_id = ? AND user_type = 'pet-sitter' AND is_banned = 0"
    );
    $stmt->execute([$public_id]);
} else {
    header("Location: index.php");
    exit();
}

$petsitter = $stmt->fetch();
if (!$petsitter) {
    header("Location: index.php");
    exit();
}

$petsitter_id = $petsitter['id'];

// Reviews (visible only)
$stmt = $pdo->prepare(
    "SELECT r.id, r.rating, r.review_text, r.created_at,
            u.username, u.first_name
     FROM reviews r
     JOIN users u ON r.rater_user_id = u.id
     WHERE r.rated_user_id = ? AND r.is_disabled = 0
     ORDER BY r.created_at DESC"
);
$stmt->execute([$petsitter_id]);
$reviews = $stmt->fetchAll();

// Stats
$stmt = $pdo->prepare(
    "SELECT AVG(rating) AS avg_rating, COUNT(*) AS total_reviews
     FROM reviews WHERE rated_user_id = ? AND is_disabled = 0"
);
$stmt->execute([$petsitter_id]);
$rating_stats = $stmt->fetch();

$avg_rating    = round($rating_stats['avg_rating']    ?? 0, 1);
$total_reviews = (int)($rating_stats['total_reviews'] ?? 0);

// Star display
$full_stars  = (int)$avg_rating;
$has_partial = ($avg_rating - $full_stars) >= 0.5;
$empty_stars = 5 - $full_stars - ($has_partial ? 1 : 0);
$stars_html  = str_repeat('★', $full_stars)
             . ($has_partial ? '½' : '')
             . str_repeat('☆', $empty_stars);

// Check if logged-in user already left a review
$user_already_reviewed = false;
$can_review = false;
if (isUserLoggedIn()) {
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE rater_user_id = ? AND rated_user_id = ?");
    $stmt->execute([$_SESSION['user_id'], $petsitter_id]);
    $user_already_reviewed = (bool)$stmt->fetch();
    // Only owners can leave reviews, and not on their own profile
    $can_review = !empty($_SESSION['is_owner']) && $_SESSION['user_id'] !== $petsitter_id;
}

// Handle review submission
$review_error   = '';
$review_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isUserLoggedIn()) {
        $review_error = 'You must be logged in to leave a review.';
    } elseif (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $review_error = 'Invalid request. Please try again.';
    } elseif (!$can_review) {
        $review_error = 'Only pet owners can leave reviews.';
    } elseif ($user_already_reviewed) {
        $review_error = 'You have already reviewed this sitter.';
    } else {
        $rating      = (int)($_POST['rating']      ?? 0);
        $review_text = trim($_POST['review_text']  ?? '');
        if ($rating < 1 || $rating > 5) {
            $review_error = 'Please select a rating between 1 and 5.';
        } elseif (empty($review_text)) {
            $review_error = 'Please write a review.';
        } elseif (strlen($review_text) > 250) {
            $review_error = 'Review must be 250 characters or fewer.';
        } else {
            try {
                $pdo->prepare(
                    "INSERT INTO reviews (rater_user_id, rated_user_id, rating, review_text)
                     VALUES (?, ?, ?, ?)"
                )->execute([$_SESSION['user_id'], $petsitter_id, $rating, $review_text]);
                $review_success = 'Your review has been posted!';
                $user_already_reviewed = true;
                // Refresh stats and reviews
                header("Location: petsitter_profile.php?id=" . $petsitter_id . "&reviewed=1");
                exit();
            } catch (PDOException $e) {
                $review_error = 'Could not post review. You may have already reviewed this sitter.';
            }
        }
    }
}

if (isset($_GET['reviewed'])) {
    $review_success = 'Your review has been posted!';
}

$csrf_token = generateCsrfToken();
$display_name = trim(($petsitter['first_name'] ?? '') . ' ' . ($petsitter['last_name'] ?? ''))
              ?: $petsitter['username'];
$years_exp = max(1, date('Y') - date('Y', strtotime($petsitter['created_at'])));

$pageTitle = escapeOutput($display_name) . " | PetSitter's Market";
require_once 'includes/header.php';
?>

<!-- <main id="main-content" class="container"> -->
<main class="petsitter-profile">
<!-- <div class="petsitter-profile"> -->
<div class="middle">

    <!-- ── Profile header ──────────────────────────────────────── -->
    <!-- <div class="profile-header"> -->
    <div class="top">
        <!-- <div class="profile-image"> -->
        <div class="content">
            <?php if ($petsitter['avatar_url']): ?>
                <img src="<?php echo escapeOutput($petsitter['avatar_url']); ?>"
                     alt="<?php echo escapeOutput($display_name); ?>">
            <?php else: ?>
                <div class="placeholder-avatar"><i class="fas fa-user-circle"></i></div>
            <?php endif; ?>
        </div>

        <div class="content">
        <!-- <div class="profile-info"> -->
            <h1 class="sitter-name"><?php echo escapeOutput($display_name); ?></h1>
            <p class="sitter-subtitle">
                Professional Pet Sitter &bull;
                <?php echo $years_exp; ?> year<?php echo $years_exp !== 1 ? 's' : ''; ?> on the platform
            </p>

            <div class="rating-row">
                <span class="stars"><?php echo $stars_html; ?></span>
                <span class="score"><?php echo $avg_rating > 0 ? $avg_rating : '—'; ?></span>
                <span class="reviews-count">(<?php echo $total_reviews; ?> review<?php echo $total_reviews !== 1 ? 's' : ''; ?>)</span>
            </div>

            <div class="badges">
                <span class="badge">Available</span>
            </div>

            <?php if (isUserLoggedIn() && $_SESSION['user_id'] !== $petsitter_id): ?>
                <button class="btn btn-primary">Send Message</button>
            <?php elseif (!isUserLoggedIn()): ?>
                <a href="login.php" class="btn btn-primary">Log in to Contact</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Main + Sidebar ──────────────────────────────────────── -->
    <!-- <div class="profile-content"> -->
    <div class="bottom">
        <div class="left">
        <!-- <div class="profile-main"> -->

            <!-- About -->
            <!-- <section class="about-section"> -->
            <div class="content">
                <h2>About Me</h2>
                <p><?php echo escapeOutput($petsitter['bio'] ?? 'This sitter has not added a bio yet.'); ?></p>
            <!-- </section> -->
            </div>

            <!-- Reviews -->
            <div class="content">
            <!-- <section class="reviews-section" id="reviews"> -->
                <h2>Reviews (<?php echo $total_reviews; ?>)</h2>

                <?php if (!empty($review_error)): ?>
                    <div class="alert alert-error" style="margin-bottom:1rem;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo escapeOutput($review_error); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($review_success)): ?>
                    <div class="alert alert-success" style="margin-bottom:1rem;">
                        <i class="fas fa-check-circle"></i> <?php echo escapeOutput($review_success); ?>
                    </div>
                <?php endif; ?>

                <!-- Review form -->
                <?php if ($can_review && !$user_already_reviewed): ?>
                <div class="review-form-card">
                    <h3>Leave a Review</h3>
                    <form method="POST" action="petsitter_profile.php?id=<?php echo $petsitter_id; ?>#reviews">
                        <input type="hidden" name="csrf_token"    value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden" name="submit_review" value="1">

                        <div class="star-picker" id="star-picker">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>"
                                   <?php echo (isset($_POST['rating']) && (int)$_POST['rating'] === $i) ? 'checked' : ''; ?>>
                            <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>">★</label>
                            <?php endfor; ?>
                        </div>

                        <textarea name="review_text" placeholder="Share your experience with this sitter…"
                                  maxlength="250" rows="4" style="width:100%; box-sizing:border-box; padding:.75rem; border:1px solid rgba(112,80,48,.25); border-radius:8px; font-family:inherit; font-size:.9rem; resize:vertical;"><?php echo escapeOutput($_POST['review_text'] ?? ''); ?></textarea>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:.5rem;">
                            <small style="color:#aaa;" id="char-count">0 / 250</small>
                            <button type="submit" class="btn btn-primary">Post Review</button>
                        </div>
                    </form>
                </div>
                <?php elseif ($can_review && $user_already_reviewed): ?>
                    <p style="color:#888; font-style:italic; margin-bottom:1.5rem;">You have already reviewed this sitter.</p>
                <?php elseif (!isUserLoggedIn()): ?>
                    <p style="color:#888; margin-bottom:1.5rem;">
                        <a href="login.php" style="color:var(--clr-brand); font-weight:600;">Log in</a> to leave a review.
                    </p>
                <?php endif; ?>

                <!-- Review list -->
                <?php if (empty($reviews)): ?>
                    <p style="color:#888; font-style:italic;">No reviews yet — be the first!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <h4><?php echo escapeOutput($review['first_name'] ?: $review['username']); ?></h4>
                            <div class="review-rating">
                                <?php echo str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']); ?>
                            </div>
                        </div>
                        <p class="review-text">"<?php echo escapeOutput($review['review_text']); ?>"</p>
                        <p class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <!-- </section> -->
            </div>

        </div><!-- /profile-main -->

        <div class="right">
        <!-- <div class="profile-sidebar"> -->
            <div class="content">
            <!-- <section class="experience-section"> -->
                <h3>Experience</h3>
                <div class="experience-list">
                    <div class="experience-item"><i class="fas fa-dog"></i><div><h4>Dogs</h4><p>5+ years</p></div></div>
                    <div class="experience-item"><i class="fas fa-cat"></i><div><h4>Cats</h4><p>4+ years</p></div></div>
                    <div class="experience-item"><i class="fas fa-dove"></i><div><h4>Birds</h4><p>2+ years</p></div></div>
                    <div class="experience-item"><i class="fas fa-fish"></i><div><h4>Small Animals</h4><p>3+ years</p></div></div>
                </div>
            <!-- </section> -->
            </div>

            <div class="content">
            <!-- <section class="services-section"> -->
                <h3>Services Offered</h3>
                <ul class="services-list">
                    <li><i class="fas fa-check"></i> Pet Sitting</li>
                    <li><i class="fas fa-check"></i> Dog Walking</li>
                    <li><i class="fas fa-check"></i> Overnight Care</li>
                    <li><i class="fas fa-check"></i> Pet Transportation</li>
                    <li><i class="fas fa-check"></i> Basic Grooming</li>
                </ul>
            <!-- </section> -->
            </div>
        </div><!-- /profile-sidebar -->
    </div><!-- /profile-content -->

</div><!-- /petsitter-profile -->
</main>

<style>
.alert { padding:.85rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:.9rem; }
.alert-error   { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }
.alert-success { background:#dcfce7; color:#166534; border:1px solid #86efac; }

.review-form-card {
    background:rgba(240,160,96,.08);
    border:1px solid rgba(192,144,64,.25);
    border-radius:12px;
    padding:1.5rem;
    margin-bottom:1.5rem;
}
.review-form-card h3 { margin:0 0 1rem; color:var(--clr-text-title); }

/* CSS-only star picker */
.star-picker { display:flex; flex-direction:row-reverse; gap:.15rem; margin-bottom:.75rem; }
.star-picker input { display:none; }
.star-picker label {
    font-size:1.8rem; cursor:pointer; color:#ddd;
    transition:color .1s;
}
.star-picker input:checked ~ label,
.star-picker label:hover,
.star-picker label:hover ~ label { color:#d58337; }
</style>

<script>
// Character counter for review textarea
const ta = document.querySelector('textarea[name="review_text"]');
const cc = document.getElementById('char-count');
if (ta && cc) {
    cc.textContent = ta.value.length + ' / 250';
    ta.addEventListener('input', () => {
        cc.textContent = ta.value.length + ' / 250';
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>