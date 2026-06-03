<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();
redirectToLogin();

$user = getUserById($pdo, $_SESSION['user_id']);

$pageTitle = "Dashboard | PetSitter's Market";
require_once 'includes/header.php'; 
?>

<main id="main-content" class="container" style="padding: 2rem 1rem; min-height: 70vh;">
    <div class="content">
        <h1 style="color: var(--clr-text-title); margin-bottom: 0.5rem;">Dashboard</h1>
        <p style="color: var(--clr-text-title); margin-bottom: 2rem;">Welcome back, <?php echo escapeOutput($user['username']); ?>!</p>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <div class="card">
                <h2 style="color: var(--clr-text-title); margin-bottom: 1rem;">User Information</h2>
                <div style="line-height: 2;">
                    <p><strong>Username:</strong> <?php echo escapeOutput($user['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo escapeOutput($user['email']); ?></p>
                    <p><strong>Account Type:</strong> <?php echo escapeOutput(ucwords(str_replace('-', ' ', $user['user_type']))); ?></p>
                    <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                </div>
            </div>

            <div class="card">
                <h2 style="color: var(--clr-text-title); margin-bottom: 1rem;">Quick Links</h2>
                <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.8rem;">
                    <li><a href="profile.php" style="color: var(--clr-primary); font-weight: 600; text-decoration: none;">→ Edit Profile</a></li>
                    
                    <?php if (($user['user_type'] ?? '') === 'admin'): ?>
                        <li><a href="admin_dashboard.php" style="color: var(--clr-brand); font-weight: 600; text-decoration: none;">→ Admin Panel</a></li>
                    <?php endif; ?>
                    <?php if (($user['user_type'] ?? '') === 'super-admin'): ?>
                        <li><a href="super_admin_dashboard.php" style="color: var(--clr-brand); font-weight: 600; text-decoration: none;">→ Super Admin Panel</a></li>
                    <?php endif; ?>

                    
                    <li><a href="logout.php" style="color: var(--clr-error-text); text-decoration: none; font-weight: 600;">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
