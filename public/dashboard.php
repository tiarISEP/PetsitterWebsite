<?php
// 1. Initialisation et sécurité
session_start();
require_once 'includes/db.php'; // On utilise ton connecteur PDO
require_once 'auth.php';

// 2. Protection de l'accès : redirection si non connecté
redirectToLogin();

// 3. Récupération des données utilisateur via PDO
// On passe $pdo (de db.php) au lieu de $conn (mysqli)
$user = getUserById($pdo, $_SESSION['user_id']);

// 4. Configuration de l'affichage
$pageTitle = "Dashboard | PetSitter's Market";
require_once 'includes/header.php'; // Utilise ton header propre 2026
?>

<main id="main-content" class="container">
    <div class="content">
        <h1 class="title-primary">Dashboard</h1>
        <p class="text-subtitle">Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</p>
        
        <div class="grid-layout">
            <div class="card">
                <h2>User Information</h2>
                <div style="margin-top: 1rem; line-height: 2;">
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Account Type:</strong> <?php echo ucwords(str_replace('-', ' ', htmlspecialchars($user['user_type']))); ?></p>
                    <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>

            <div class="card">
                <h2>Quick Links</h2>
                <ul style="list-style: none; margin-top: 1rem; display: flex; flex-direction: column; gap: 0.8rem;">
                    <li><a href="profile.php" style="color: var(--clr-brand); font-weight: 600;">→ Edit Profile</a></li>
                    <li><a href="bookings.php">My Bookings</a></li>
                    <li><a href="messages.php">Messages</a></li>
                </ul>
            </div>
        </div>
    </div>
</main>

<?php 
require_once 'includes/footer.php'; // Utilise ton footer propre 2026
?>
