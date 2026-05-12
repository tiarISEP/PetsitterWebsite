<?php
/**
 * Reviews Helper Functions
 * For fetching and displaying reviews in petsitter profiles
 */

function getReviewsForPetsitter($conn, $petsitter_id) {
    $stmt = $conn->prepare(
        "SELECT r.id, r.rating, r.review_text, r.created_at, u.username, u.first_name 
         FROM reviews r
         JOIN users u ON r.rater_user_id = u.id
         WHERE r.rated_user_id = ?
         ORDER BY r.created_at DESC"
    );
    $stmt->bind_param("i", $petsitter_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getReviewStatsForPetsitter($conn, $petsitter_id) {
    $stmt = $conn->prepare(
        "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
         FROM reviews WHERE rated_user_id = ?"
    );
    $stmt->bind_param("i", $petsitter_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return [
        'avg_rating' => round($result['avg_rating'] ?? 0, 1),
        'total_reviews' => $result['total_reviews'] ?? 0
    ];
}

function submitReview($conn, $rater_id, $rated_id, $rating, $review_text) {
    // Validate inputs
    if (!$rater_id || !$rated_id || $rating < 1 || $rating > 5) {
        return false;
    }
    
    if (strlen($review_text) > 250) {
        return false;
    }
    
    // Check for duplicate review (one per rater per sitter)
    $stmt = $conn->prepare(
        "SELECT id FROM reviews WHERE rater_user_id = ? AND rated_user_id = ?"
    );
    $stmt->bind_param("ii", $rater_id, $rated_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        // Update existing review
        $stmt = $conn->prepare(
            "UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW()
             WHERE rater_user_id = ? AND rated_user_id = ?"
        );
        $stmt->bind_param("isii", $rating, $review_text, $rater_id, $rated_id);
    } else {
        // Insert new review
        $stmt = $conn->prepare(
            "INSERT INTO reviews (rater_user_id, rated_user_id, rating, review_text, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("iiis", $rater_id, $rated_id, $rating, $review_text);
    }
    
    return $stmt->execute();
}

function generateStarsHTML($rating) {
    $full_stars = (int)$rating;
    $partial = $rating - $full_stars;
    $empty_stars = 5 - $full_stars - ($partial > 0 ? 1 : 0);
    
    $html = str_repeat('★', $full_stars);
    if ($partial > 0) $html .= '⭐';
    $html .= str_repeat('☆', $empty_stars);
    
    return $html;
}
?>
