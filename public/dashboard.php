<?php
require_once 'config.php';
require_once 'auth.php';

// Check if user is logged in
redirectToLogin();

// Get current user info
$user = getUserById($conn, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Petsitter's Market</title>
    <meta name="description" content="Your Petsitter's Market dashboard.">
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <a href="index.html" style="text-decoration: none; color: inherit;">PetSitter's Market</a> 
        </div>
        <nav aria-label="Navigation principale">
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="services.html">Services</a></li>
                <li><a href="contact.html">Contact</a></li>
                <li style="display: flex; gap: 1rem; align-items: center;">
                    <span>Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
                    <a href="logout.php" style="background-color: #772f1a; color: white; padding: 0.5rem 1.5rem; border-radius: 8px; font-weight: 500; text-decoration: none;">Logout</a>
                </li>
            </ul>
        </nav>
    </header>

    <main id="main-content">
        <div class="content">
            <h1>Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</p>

            <div class="dashboard-info">
                <div class="info-card">
                    <h2>User Information</h2>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Account Type:</strong> <?php echo ucwords(str_replace('-', ' ', htmlspecialchars($user['user_type']))); ?></p>
                    <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>

                <div class="info-card">
                    <h2>Quick Links</h2>
                    <ul>
                        <li><a href="edit-profile.php">Edit Profile</a></li>
                        <li><a href="change-password.php">Change Password</a></li>
                        <li><a href="bookings.php">My Bookings</a></li>
                        <li><a href="messages.php">Messages</a></li>
                    </ul>
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
            </div>
        </div>
        <p class="footer-bottom">&copy; 2024 Petsitter's Market. All rights reserved.</p>
    </footer>
</body>
</html>
