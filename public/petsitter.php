<?php
require_once 'config.php';
require_once 'auth.php';

startSecureSession();

// Get petsitter ID from URL parameter
$petsitter_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$petsitter_id) {
    header("Location: index.html");
    exit();
}

// Fetch petsitter info
$stmt = $conn->prepare(
    "SELECT id, username, first_name, last_name, bio, avatar_url, user_type, created_at
     FROM users WHERE id = ? AND user_type = 'pet-sitter'"
);
$stmt->bind_param("i", $petsitter_id);
$stmt->execute();
$petsitter = $stmt->get_result()->fetch_assoc();

if (!$petsitter) {
    header("Location: index.html");
    exit();
}

// Fetch reviews for this petsitter with reviewer names
$stmt = $conn->prepare(
    "SELECT r.id, r.rating, r.review_text, r.created_at, u.username, u.first_name 
     FROM reviews r
     JOIN users u ON r.rater_user_id = u.id
     WHERE r.rated_user_id = ?
     ORDER BY r.created_at DESC"
);
$stmt->bind_param("i", $petsitter_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
$reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);

// Calculate average rating and stats
$stmt = $conn->prepare(
    "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
     FROM reviews WHERE rated_user_id = ?"
);
$stmt->bind_param("i", $petsitter_id);
$stmt->execute();
$rating_stats = $stmt->get_result()->fetch_assoc();

$avg_rating = round($rating_stats['avg_rating'] ?? 0, 1);
$total_reviews = $rating_stats['total_reviews'] ?? 0;

// Generate star display
$full_stars = (int)$avg_rating;
$partial_star = $avg_rating - $full_stars;
$empty_stars = 5 - $full_stars - ($partial_star > 0 ? 1 : 0);
$stars_html = str_repeat('★', $full_stars);
if ($partial_star > 0) {
    $stars_html .= '⭐'; // Half star representation
}
$stars_html .= str_repeat('☆', $empty_stars);

?>
<!DOCTYPE html>
<html lang="en"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- SEO de base obligatoire -->
    <title>Petsitter profile | PetSitter's Market</title>
    <meta name="description" content="Description courte et incisive de la page (environ 155 caractères), vitale pour le SEO.">

    <!-- CSS & Favicon -->
    <!-- Utilise des chemins relatifs absolus par rapport à la racine si tu as des sous-dossiers -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- <link rel="icon" href="/favicon.ico" type="image/x-icon"> -->
</head>
<body class="petsitter-page">
    <!-- Accessibilité : Lien d'évitement -->
    <a href="#main-content" class="skip-link" style="position: absolute; left: -9999px;">Aller au contenu principal</a>

    <header>
        <div class="logo">
            <!-- Le logo doit TOUJOURS ramener à l'accueil -->
            <a href="index.html" style="text-decoration: none; color: inherit;">PetSitter's Market</a> 
        </div>
        <nav aria-label="Navigation principale">
            <ul>
                <!-- Utilise de vrais chemins pour le multi-pages -->
                <li><a href="index.html">Home</a></li>
                <li><a href="services.html">Services</a></li>
                <li><a href="contact.html">Contact</a></li>
                <li>
                    <a href="login.html" style="font-weight: 500; color: #772f1a; padding: 0.5rem 1rem;">Login</a>
                </li>
                <li>
                    <a href="signup.html" style="background-color: #585123; color: white; padding: 0.5rem 1.5rem; border-radius: 8px; font-weight: 500; text-decoration: none;">Sign Up</a>
                </li>
            </ul>
        </nav>
    </header>

    <!-- id="main-content" requis pour le lien d'évitement ci-dessus -->
    <main id="main-content">

        <div class="middle">
            <div class="top">
                <div class="content">
                    <div class="image"></div> <!-- utiliser balise <img> -->
                    <div class="info">
                        <p class="name"><?php echo escapeOutput($petsitter['first_name'] ? $petsitter['first_name'] . ' ' . ($petsitter['last_name'] ?? '') : $petsitter['username']); ?></p>
                        <p class="subtitle">Professional Pet Sitter • <?php echo date('Y') - date('Y', strtotime($petsitter['created_at'])); ?> years experience</p>
                        <div class="rating-row">
                            <span class="stars"><?php echo $stars_html; ?></span>
                            <span class="score"><?php echo $avg_rating; ?></span>
                            <span class="reviews">(<?php echo $total_reviews; ?> reviews)</span>
                        </div>
                        <div class="badges">
                            <span>$25/hour<!--{user.hourly_rate}--></span>
                            <span>Available<!--{user.available}--></span>
                            <span>2 miles away<!--{user.distance}--><!--calculate an estimate of distance in km--></span>
                        </div>
                        <button class="primary-btn">Send Message<!--redirect to the messagae page--></button>
                    </div>
                </div>
            </div>

            <div class="bottom">
                <div class="left">
                    <div class="content">
                        <h2>About Me</h2>
                        <p><?php echo escapeOutput($petsitter['bio'] ?? 'No bio provided'); ?></p>
                    </div>
                    <div class="content">
                        <h2>Reviews (127)</h2>
                        <div class="review">
                            <h4>Mike Chen</h4>
                            <p>“Sarah was amazing with our Golden Retriever, Max! She sent daily updates with photos and videos, and Max was so happy when we returned. Highly recommend!”</p>
                        </div>
                        <div class="review">
                            <h4>Emma Wilson</h4>
                            <p>“Perfect pet sitter! Sarah took excellent care of our two cats while we were on vacation. Very reliable and trustworthy. Will definitely book again!”</p>
                        </div>
                        <div class="review">
                            <h4>David Park</h4>
                            <p>“Sarah is wonderful! She took great care of our rescue dog who can be anxious with new people. Her patience and experience really showed. Thank you Sarah!”</p>
                        </div>
                        <a href="#reviews">View all reviews →</a>
                    </div>
                </div>

                <div class="right">
                    <div class="content">
                        <h2>Experience</h2>
                        <div class="experience-item"><i class="fas fa-dog"></i><div><h4>Dogs</h4><p>5+ years experience</p></div></div>
                        <div class="experience-item"><i class="fas fa-cat"></i><div><h4>Cats</h4><p>4+ years experience</p></div></div>
                        <div class="experience-item"><i class="fas fa-dove"></i><div><h4>Birds</h4><p>2+ years experience</p></div></div>
                        <div class="experience-item"><i class="fas fa-fish"></i><div><h4>Small Animals</h4><p>3+ years experience</p></div></div>
                    </div>
                    <div class="content">
                        <h2>Services Offered</h2>
                        <ul class="services-list">
                            <li>Pet Sitting</li>
                            <li>Dog Walking</li>
                            <li>Overnight Care</li>
                            <li>Pet Transportation</li>
                            <li>Basic Grooming</li>
                        </ul>
                    </div>
                    <div class="content">
                        <h2>Availability</h2>
                        <div class="calendar">
                            <h3>April 2026</h3>
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
                                        <td class="empty"></td>
                                        <td class="empty"></td>
                                        <td class="empty"></td>
                                        <td class="available">1</td>
                                        <td class="available">2</td>
                                        <td class="available">3</td>
                                    </tr>
                                    <tr>
                                        <td class="available">4</td>
                                        <td class="available">5</td>
                                        <td class="available">6</td>
                                        <td class="booked">7</td>
                                        <td class="available">8</td>
                                        <td class="available">9</td>
                                        <td class="available">10</td>
                                    </tr>
                                    <tr>
                                        <td class="available">11</td>
                                        <td class="available">12</td>
                                        <td class="available">13</td>
                                        <td class="booked">14</td>
                                        <td class="available">15</td>
                                        <td class="available">16</td>
                                        <td class="available">17</td>
                                    </tr>
                                    <tr>
                                        <td class="available">18</td>
                                        <td class="available">19</td>
                                        <td class="available">20</td>
                                        <td class="booked">21</td>
                                        <td class="available">22</td>
                                        <td class="available">23</td>
                                        <td class="available">24</td>
                                    </tr>
                                    <tr>
                                        <td class="available">25</td>
                                        <td class="available">26</td>
                                        <td class="available">27</td>
                                        <td class="booked">28</td>
                                        <td class="available">29</td>
                                        <td class="available">30</td>
                                        <td class="empty"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </main>

    <footer>
        <div class="footer-container">
            <div class="footer-col brand-col">
                <h2><i class="fas fa-paw"></i> Petsitter's Market</h2>
                <p>Connecting pet owners with<br>trusted caregivers since 2020.</p>
            </div>
            
            <div class="footer-col">
                <h3>Services</h3>
                <a href="#">Pet Sitting</a>
                <a href="#">Dog Walking</a>
                <a href="#">Pet Grooming</a>
                <a href="#">Vet Visits</a>
            </div>

            <div class="footer-col">
                <h3>Company</h3>
                <a href="#">About Us</a>
                <a href="#">Contact</a>
                <a href="#">Careers</a>
                <a href="#">Privacy Policy</a>
            </div>

            <div class="footer-col">
                <h3>Contact</h3>
                <div class="contact-item">
                    <i class="fas fa-phone-alt"></i> (555) 123-4567
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i> hello@petsittersmarket.com
                </div>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; 2026 Petsitter's Market. All rights reserved.
        </div>
    </footer>
</body>
</html>
