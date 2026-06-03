<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

// Get the petsitter identifier (can be public_id or numerical id)
$sitter_id = trim($_GET['id'] ?? '');

if (empty($sitter_id)) {
    header("Location: index.php");
    exit();
}

// Relaxed validation: check if it's a UUID or numeric ID
$is_uuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sitter_id);
$is_numeric = is_numeric($sitter_id);

if (!$is_uuid && !$is_numeric) {
    header("Location: index.php");
    exit();
}

// Fetch sitter info
$stmt = $pdo->prepare(
    "SELECT * FROM users WHERE (public_id = ? OR id = ?) AND user_type = 'pet-sitter' AND is_banned = 0"
);
$stmt->execute([$sitter_id, $sitter_id]);
$sitter = $stmt->fetch();

if (!$sitter) {
    header("Location: index.php");
    exit();
}

$petsitter_id = $sitter['id'];

// Get rating stats
$stmt = $pdo->prepare("
    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
    FROM reviews 
    WHERE rated_user_id = ? AND is_disabled = 0
");
$stmt->execute([$petsitter_id]);
$rating_stats = $stmt->fetch();

$avg_rating    = round($rating_stats['avg_rating']    ?? 0, 1);
$total_reviews = (int)($rating_stats['total_reviews'] ?? 0);

// Star display logic
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
        $review_error = 'You have already reviewed this pet sitter.';
    } else {
        $rating = (int)($_POST['rating'] ?? 0);
        $review_text = trim($_POST['review_text'] ?? '');
        
        if ($rating < 1 || $rating > 5) {
            $review_error = 'Please select a rating between 1 and 5.';
        } elseif (strlen($review_text) < 10) {
            $review_error = 'Review text must be at least 10 characters long.';
        } elseif (strlen($review_text) > 250) {
            $review_error = 'Review text cannot exceed 250 characters.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO reviews (rater_user_id, rated_user_id, rating, review_text) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$_SESSION['user_id'], $petsitter_id, $rating, $review_text]);
                $review_success = 'Thank you! Your review has been submitted successfully.';
                $user_already_reviewed = true;
                
                // Recalculate rating stats
                $stmt = $pdo->prepare("
                    SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                    FROM reviews 
                    WHERE rated_user_id = ? AND is_disabled = 0
                ");
                $stmt->execute([$petsitter_id]);
                $rating_stats = $stmt->fetch();
                $avg_rating    = round($rating_stats['avg_rating']    ?? 0, 1);
                $total_reviews = (int)($rating_stats['total_reviews'] ?? 0);
                
                // Recalculate stars
                $full_stars  = (int)$avg_rating;
                $has_partial = ($avg_rating - $full_stars) >= 0.5;
                $empty_stars = 5 - $full_stars - ($has_partial ? 1 : 0);
                $stars_html  = str_repeat('★', $full_stars)
                             . ($has_partial ? '½' : '')
                             . str_repeat('☆', $empty_stars);
            } catch (PDOException $e) {
                $review_error = 'Failed to submit review due to a database error.';
            }
        }
    }
}

// Fetch all reviews for this sitter
$stmt = $pdo->prepare("
    SELECT r.*, u.username, u.first_name, u.last_name, u.avatar_url 
    FROM reviews r 
    JOIN users u ON r.rater_user_id = u.id 
    WHERE r.rated_user_id = ? AND r.is_disabled = 0 
    ORDER BY r.created_at DESC
");
$stmt->execute([$petsitter_id]);
$sitter_reviews = $stmt->fetchAll();

$pageTitle = htmlspecialchars($sitter['first_name'] . ' ' . $sitter['last_name']) . "'s Profile | PetSitter's Market";
require_once 'includes/header.php';
$csrf_token = generateCsrfToken();
?>
<script>
    // Dynamically apply petsitter-page class to body
    document.body.classList.add('petsitter-page');
</script>

<main id="main-content" class="petsitter-profile">
    <div class="middle">
        <div class="top">
            <div class="content">
                <?php if (!empty($sitter['avatar_url'])): ?>
                    <img src="<?php echo htmlspecialchars($sitter['avatar_url']); ?>" alt="Avatar" class="image" style="object-fit: cover;">
                <?php else: ?>
                    <div class="image" style="display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, var(--clr-bg-page), var(--clr-primary)); color:var(--clr-text-title); font-weight:bold; font-size:2rem;"><?php echo strtoupper(substr($sitter['first_name'] ?? $sitter['username'], 0, 2)); ?></div>
                <?php endif; ?>
                
                <h1 class="name" style="font-size: 1.8rem; color: var(--clr-text-title); font-weight: 700; margin: 0;"><?php echo htmlspecialchars($sitter['first_name'] . ' ' . $sitter['last_name']); ?></h1>
                <p class="subtitle" style="margin: 0; font-size: 0.9rem; color: #666;">Professional Pet Sitter • Member since <?php echo date('Y', strtotime($sitter['created_at'])); ?></p>
                
                <div class="rating-row">
                    <span class="stars" style="color: var(--clr-brand); font-size: 1.2rem;"><?php echo $stars_html; ?></span>
                    <span class="score" style="font-size: 1.1rem; font-weight: 600;"><?php echo $avg_rating > 0 ? $avg_rating : 'No ratings'; ?></span>
                    <span class="reviews" style="font-size: 0.9rem; color: #666;">(<?php echo $total_reviews; ?> review<?php echo $total_reviews !== 1 ? 's' : ''; ?>)</span>
                </div>
                
                <div class="badges">
                    <span><i class="fas fa-user-check"></i> Verified Sitter</span>
                    <?php if(!empty($sitter['phone'])): ?>
                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($sitter['phone']); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if (isUserLoggedIn() && $_SESSION['user_id'] !== $petsitter_id): ?>
                    <a href="messages.php?convo=<?php echo htmlspecialchars($sitter['public_id']); ?>" class="primary-btn" style="text-decoration:none; text-align: center; width: 100%;">Send Message</a>
                <?php elseif (!isUserLoggedIn()): ?>
                    <a href="login.php" class="primary-btn" style="text-decoration:none; text-align: center; width: 100%;">Log in to Contact</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="bottom">
            <div class="left">
                <div class="content">
                    <h2>About Me</h2>
                    <p><?php echo nl2br(htmlspecialchars($sitter['bio'] ?: "This pet sitter hasn't written a biography yet.")); ?></p>
                </div>
                
                <div class="content">
                    <h2>Reviews (<?php echo $total_reviews; ?>)</h2>
                    
                    <?php if (empty($sitter_reviews)): ?>
                        <p style="color: #666; font-style: italic;">No reviews yet. Be the first to leave a review!</p>
                    <?php else: ?>
                        <div class="reviews-list">
                            <?php foreach ($sitter_reviews as $rev): ?>
                                <div class="review-card">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <strong style="color: var(--clr-text-title);"><?php echo htmlspecialchars(($rev['first_name'] ?? '') . ' ' . ($rev['last_name'] ?? '')) ?: htmlspecialchars($rev['username']); ?></strong>
                                        <span style="color: var(--clr-brand);"><?php echo str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']); ?></span>
                                    </div>
                                    <p>“<?php echo htmlspecialchars($rev['review_text']); ?>”</p>
                                    <small style="color: #888; font-size: 0.8rem;"><?php echo date('d M Y', strtotime($rev['created_at'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Leave a Review Section -->
                <?php if (isUserLoggedIn()): ?>
                    <?php if ($can_review && !$user_already_reviewed): ?>
                        <div class="content" style="margin-top: 1.5rem;">
                            <h2>Leave a Review</h2>
                            <?php if (!empty($review_error)): ?>
                                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($review_error); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($review_success)): ?>
                                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($review_success); ?></div>
                            <?php endif; ?>
                            
                            <form method="POST" action="petsitter.php?id=<?php echo htmlspecialchars($sitter_id); ?>" class="auth-form" style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                
                                <div class="star-picker" id="star-picker">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>"
                                           <?php echo (isset($_POST['rating']) && (int)$_POST['rating'] === $i) ? 'checked' : ''; ?>>
                                    <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>">★</label>
                                    <?php endfor; ?>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="review_text">Review Description</label>
                                    <textarea name="review_text" id="review_text" rows="4" placeholder="Share your experience with this pet sitter (min 10 characters)..." required style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid #ddd; font-family: inherit; resize: vertical;"></textarea>
                                </div>
                                
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:.5rem;">
                                    <small style="color:#aaa;" id="char-count">0 / 250</small>
                                    <button type="submit" name="submit_review" class="btn btn-primary" style="align-self: flex-start; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer;">Submit Review</button>
                                </div>
                            </form>
                        </div>
                    <?php elseif ($user_already_reviewed): ?>
                        <div class="content" style="margin-top: 1.5rem; background: #faf8f5; border: 1px solid rgba(192, 144, 64, 0.1); border-radius: 12px; padding: 1.5rem; text-align: center;">
                            <p style="color: #666; font-weight: 500; margin: 0;"><i class="fas fa-check-circle" style="color: var(--clr-success-text);"></i> You have already reviewed this pet sitter.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="content" style="margin-top: 1.5rem; text-align: center;">
                        <p><a href="login.php" style="color: var(--clr-primary); font-weight: 600; text-decoration: none;">Log in</a> to leave a review.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="right">
                <div class="content">
                    <h2>Experience</h2>
                    <div class="experience-item"><i class="fas fa-dog"></i><div><h4>Dogs</h4><p>Experienced care provider</p></div></div>
                    <div class="experience-item"><i class="fas fa-cat"></i><div><h4>Cats</h4><p>Experienced care provider</p></div></div>
                    <div class="experience-item"><i class="fas fa-dove"></i><div><h4>Birds</h4><p>Experienced care provider</p></div></div>
                    <div class="experience-item"><i class="fas fa-fish"></i><div><h4>Small Animals</h4><p>Experienced care provider</p></div></div>
                </div>
                
                <div class="content">
                    <h2>Services Offered</h2>
                    <ul class="services-list">
                        <li><i class="fas fa-check"></i> Pet Sitting</li>
                        <li><i class="fas fa-check"></i> Dog Walking</li>
                        <li><i class="fas fa-check"></i> Overnight Care</li>
                        <li><i class="fas fa-check"></i> Pet Transportation</li>
                        <li><i class="fas fa-check"></i> Basic Grooming</li>
                    </ul>
                </div>
                
                <div class="content">
                    <h2>Availability</h2>
                    <div class="calendar">
                        <h3>June 2026</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Sun</th>
                                    <th>Mon</th>
                                    <th>Tue</th>
                                    <th>Wed</th>
                                    <th>Thu</th>
                                    <th>Fri</th>
                                    <th>Sat</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="empty"></td>
                                    <td class="available">1</td>
                                    <td class="available">2</td>
                                    <td class="available">3</td>
                                    <td class="available">4</td>
                                    <td class="available">5</td>
                                    <td class="available">6</td>
                                </tr>
                                <tr>
                                    <td class="available">7</td>
                                    <td class="available">8</td>
                                    <td class="available">9</td>
                                    <td class="booked">10</td>
                                    <td class="available">11</td>
                                    <td class="available">12</td>
                                    <td class="available">13</td>
                                </tr>
                                <tr>
                                    <td class="available">14</td>
                                    <td class="available">15</td>
                                    <td class="available">16</td>
                                    <td class="booked">17</td>
                                    <td class="available">18</td>
                                    <td class="available">19</td>
                                    <td class="available">20</td>
                                </tr>
                                <tr>
                                    <td class="available">21</td>
                                    <td class="available">22</td>
                                    <td class="available">23</td>
                                    <td class="booked">24</td>
                                    <td class="available">25</td>
                                    <td class="available">26</td>
                                    <td class="available">27</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.alert { padding:.85rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:.9rem; }
.alert-error   { background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; }
.alert-success { background:#dcfce7; color:#166534; border:1px solid #86efac; }

/* CSS-only star picker */
.star-picker { display:flex; flex-direction:row-reverse; gap:.15rem; margin-bottom:.75rem; justify-content: flex-end; }
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
