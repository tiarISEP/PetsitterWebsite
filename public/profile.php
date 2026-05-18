<?php
// public/profile.php
require_once 'includes/db.php';
require_once 'auth.php';

// 1. Security and session verification
startSecureSession();
redirectToLogin();

// 2. Fetch fresh user data
$user = getUserById($pdo, $_SESSION['user_id']);
$csrf_token = generateCsrfToken();

$pageTitle = "Profile Settings | PetSitter's Market";
require_once 'includes/header.php';
?>

<main class="middle" style="background-color: var(--clr-bg-page);">
    <div class="content" style="max-width: 600px; width: 100%; margin: 2rem auto;">
        
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="sitter-img" style="width: 120px; height: 120px; background: linear-gradient(135deg, var(--clr-brand), var(--clr-primary)); margin: 0 auto 1rem; border: 4px solid var(--white); box-shadow: var(--card-shadow);"></div>
            <h1 class="title-primary">Profile Settings</h1>
            <p class="text-subtitle">Update your personal information</p>
        </div>
        
        <?php if (isset($_GET['success']) && $_GET['success'] === 'profile_updated'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Your information has been successfully updated.
            </div>
        <?php elseif (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php 
                    switch($_GET['error']) {
                        case 'invalid_username_length': echo "Username must be between 3 and 50 characters."; break;
                        case 'name_too_long': echo "First name or last name is too long."; break;
                        case 'invalid_phone': echo "The phone number is invalid."; break;
                        case 'bio_too_long': echo "Your bio is too long (max 1000 characters)."; break;
                            case 'username_taken': echo "This username is already taken. Please choose another one."; break;
                        default: echo "An error occurred during the update.";
                    }
                ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" action="update_profile.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo escapeOutput($user['username'] ?? ''); ?>" required minlength="3" maxlength="50">
            </div>

            <div class="user-type-row" style="margin-bottom: 0;">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo escapeOutput($user['first_name'] ?? ''); ?>" maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo escapeOutput($user['last_name'] ?? ''); ?>" maxlength="50">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address (Read-only)</label>
                <input type="email" id="email" value="<?php echo escapeOutput($user['email'] ?? ''); ?>" disabled style="background-color: #f3f4f6; color: #9ca3af; cursor: not-allowed;">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" value="<?php echo escapeOutput($user['phone'] ?? ''); ?>" placeholder="+33 6 12 34 56 78">
            </div>

            <div class="form-group">
                <label for="bio">About Me (Bio)</label>
                <textarea id="bio" name="bio" rows="4" placeholder="Tell us a bit about yourself..." maxlength="1000"><?php echo escapeOutput($user['bio'] ?? ''); ?></textarea>
            </div>
            
            <div style="margin-top: 2rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Changes</button>
            </div>
        </form>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>