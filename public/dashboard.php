<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

// Protection: redirect if not logged in
redirectToLogin();

// Get user data
$user = getUserById($pdo, $_SESSION['user_id']);

// Page title
$pageTitle = "Dashboard | PetSitter's Market";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeOutput($pageTitle); ?></title>
    <link rel="stylesheet" href="css/style.css?v=1.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <header>
        <div class="logo">
            <a href="index.php" style="text-decoration: none; color: inherit;">PetSitter's Market</a> 
        </div>
        <nav aria-label="Navigation principale">
            <ul>
                <li><a href="dashboard.php" style="font-weight: 500; color: #772f1a;">Dashboard</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main id="main-content" class="container" style="padding: 2rem 1rem;">
        <div class="content">
            <h1 style="color: #585123; margin-bottom: 0.5rem;">Dashboard</h1>
            <p style="color: #772f1a; margin-bottom: 2rem;">Welcome back, <?php echo escapeOutput($user['username']); ?>!</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                <div style="border: 1px solid #ddd; padding: 1.5rem; border-radius: 8px; background: #fff;">
                    <h2 style="color: #585123; margin-bottom: 1rem;">User Information</h2>
                    <div style="line-height: 2;">
                        <p><strong>Username:</strong> <?php echo escapeOutput($user['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo escapeOutput($user['email']); ?></p>
                        <p><strong>Account Type:</strong> <?php echo escapeOutput(ucwords(str_replace('-', ' ', $user['user_type']))); ?></p>
                        <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                    </div>
                </div>

                <div style="border: 1px solid #ddd; padding: 1.5rem; border-radius: 8px; background: #fff;">
                    <h2 style="color: #585123; margin-bottom: 1rem;">Quick Links</h2>
                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.8rem;">
                        <li><a href="profile.php" style="color: #d58337; font-weight: 600; text-decoration: none;">→ Edit Profile</a></li>
                        <li><a href="admin_dashboard.php" style="color: #d58337; font-weight: 600; text-decoration: none;">→ Admin Panel</a></li>
                        <li><a href="logout.php" style="color: #772f1a; text-decoration: none;">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <footer style="background-color: #585123; color: #fff; padding: 2rem; text-align: center; margin-top: 3rem;">
        <p>&copy; 2026 Petsitter's Market. All rights reserved.</p>
    </footer>
</body>
</html>
