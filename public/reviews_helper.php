<?php
/**
 * Reviews Helper Functions
 * For fetching and displaying reviews in petsitter profiles
 */

function getReviewsForPetsitter($pdo, $petsitter_id) {
    $stmt = $pdo->prepare(
        "SELECT r.id, r.rating, r.review_text, r.created_at, u.username, u.first_name 
         FROM reviews r
         JOIN users u ON r.rater_user_id = u.id
         WHERE r.rated_user_id = ?
         ORDER BY r.created_at DESC"
    );
    $stmt->execute([$petsitter_id]);
    return $stmt->fetchAll();
}

function getReviewStatsForPetsitter($pdo, $petsitter_id) {
    $stmt = $pdo->prepare(
        "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
         FROM reviews WHERE rated_user_id = ?"
    );
    $stmt->execute([$petsitter_id]);
    $result = $stmt->fetch();
    
    return [
        'avg_rating' => round($result['avg_rating'] ?? 0, 1),
        'total_reviews' => $result['total_reviews'] ?? 0
    ];
}

function submitReview($pdo, $rater_id, $rated_id, $rating, $review_text) {
    // Validate inputs
    if (!$rater_id || !$rated_id || $rating < 1 || $rating > 5) {
        return false;
    }
    
if (mb_strlen($review_text, 'UTF-8') > 250) {
    return false;
    }
    
    // Check for duplicate review (one per rater per sitter)
    $stmt = $pdo->prepare(
        "SELECT id FROM reviews WHERE rater_user_id = ? AND rated_user_id = ?"
    );
    $stmt->execute([$rater_id, $rated_id]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing review
        $stmt = $pdo->prepare(
            "UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW()
             WHERE rater_user_id = ? AND rated_user_id = ?"
        );
        return $stmt->execute([$rating, $review_text, $rater_id, $rated_id]);
    } else {
        // Insert new review
        $stmt = $pdo->prepare(
            "INSERT INTO reviews (rater_user_id, rated_user_id, rating, review_text, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        return $stmt->execute([$rater_id, $rated_id, $rating, $review_text]);
    }
}

function generateStarsHTML($rating) {
    $full_stars = (int)round($rating);
    $empty_stars = 5 - $full_stars;
    return str_repeat('★', $full_stars) . str_repeat('☆', $empty_stars);
}
?>
