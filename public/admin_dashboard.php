<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();
redirectToLogin();

$user = getUserById($pdo, $_SESSION['user_id']);

if (!$user || $user['user_type'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$section = $_GET['section'] ?? 'overview';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $admin_action = $_POST['admin_action'] ?? '';
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $target_review_id = (int)($_POST['review_id'] ?? 0);

        // PROTECTION : L'admin ne peut pas se cibler lui-même
        if ($target_user_id && $target_user_id === $_SESSION['user_id']) {
            $error = "Security Error: You cannot ban or delete your own admin account.";
        } else {
            try {
                switch ($admin_action) {
                    case 'ban_user':
                        $pdo->prepare("UPDATE users SET is_banned = 1 WHERE id = ?")->execute([$target_user_id]);
                        $success = 'User banned successfully.';
                        break;

                    case 'unban_user':
                        $pdo->prepare("UPDATE users SET is_banned = 0 WHERE id = ?")->execute([$target_user_id]);
                        $success = 'User unbanned successfully.';
                        break;

                    case 'disable_review':
                        $pdo->prepare("UPDATE reviews SET is_disabled = 1 WHERE id = ?")->execute([$target_review_id]);
                        $success = 'Review disabled successfully.';
                        break;

                    case 'enable_review':
                        $pdo->prepare("UPDATE reviews SET is_disabled = 0 WHERE id = ?")->execute([$target_review_id]);
                        $success = 'Review enabled successfully.';
                        break;

                    case 'delete_user':
                        $pdo->beginTransaction();
                        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$target_user_id]);
                        $pdo->prepare("DELETE FROM application WHERE User_userID = ? OR Post_postID IN (SELECT postID FROM post WHERE User_userID = ?)")->execute([$target_user_id, $target_user_id]);
                        $pdo->prepare("DELETE FROM rating WHERE Rated_userID = ? OR Rater_userID = ?")->execute([$target_user_id, $target_user_id]);
                        $pdo->prepare("DELETE FROM post_has_animal WHERE Post_postID IN (SELECT postID FROM post WHERE User_userID = ?)")->execute([$target_user_id]);
                        $pdo->prepare("DELETE FROM post WHERE User_userID = ?")->execute([$target_user_id]);
                        $pdo->prepare("DELETE FROM animal WHERE User_userID = ?")->execute([$target_user_id]);
                        $pdo->prepare("DELETE FROM reviews WHERE rater_user_id = ? OR rated_user_id = ?")->execute([$target_user_id, $target_user_id]);
                        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_user_id]);
                        $pdo->commit();
                        $success = 'User and all related data deleted successfully.';
                        break;
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Admin Action Failed: " . $e->getMessage());
                $error = "Action failed due to a database constraint. Check logs.";
            }
        }
    }
}

// Fetch data based on section
$stats = [];
if ($section === 'overview') {
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_sitters'] = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'pet-sitter'")->fetchColumn();
    $stats['total_owners'] = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'pet-owner'")->fetchColumn();
    $stats['total_reviews'] = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
}

$users = [];
if ($section === 'users') {
    $users = $pdo->query("SELECT id, username, email, user_type, created_at, is_banned FROM users ORDER BY created_at DESC")->fetchAll();
}

$reviews = [];
if ($section === 'reviews') {
    $reviews = $pdo->query("SELECT r.id, r.rating, r.review_text, r.created_at, r.is_disabled, rater.username as rater_name, rated.username as rated_name FROM reviews r JOIN users rater ON r.rater_user_id = rater.id JOIN users rated ON r.rated_user_id = rated.id ORDER BY r.created_at DESC")->fetchAll();
}

$csrf_token = generateCsrfToken();
$error = $error ?? '';
$success = $success ?? '';

$pageTitle = "Admin Dashboard | PetSitter's Market";
require_once 'includes/header.php';
?>

<main id="main-content" class="container" style="max-width: 1400px; padding: 2rem 1rem;">
    <div style="display: flex; gap: 2rem; min-height: 70vh;">
        
        <aside style="width: 250px; background-color: var(--white); border-radius: 8px; border: var(--card-border); box-shadow: var(--card-shadow); padding: 1.5rem 0; align-self: flex-start; position: sticky; top: 2rem;">
            <h3 style="padding: 0 1.5rem; color: var(--clr-text-title); margin-bottom: 1rem;">Admin Menu</h3>
            <div style="display: flex; flex-direction: column;">
                <a href="?section=overview" style="padding: 1rem 1.5rem; text-decoration: none; font-weight: 600; color: <?php echo $section === 'overview' ? 'var(--clr-brand)' : 'var(--clr-text-main)'; ?>; border-left: 4px solid <?php echo $section === 'overview' ? 'var(--clr-brand)' : 'transparent'; ?>;">Overview</a>
                <a href="?section=users" style="padding: 1rem 1.5rem; text-decoration: none; font-weight: 600; color: <?php echo $section === 'users' ? 'var(--clr-brand)' : 'var(--clr-text-main)'; ?>; border-left: 4px solid <?php echo $section === 'users' ? 'var(--clr-brand)' : 'transparent'; ?>;">User Management</a>
                <a href="?section=reviews" style="padding: 1rem 1.5rem; text-decoration: none; font-weight: 600; color: <?php echo $section === 'reviews' ? 'var(--clr-brand)' : 'var(--clr-text-main)'; ?>; border-left: 4px solid <?php echo $section === 'reviews' ? 'var(--clr-brand)' : 'transparent'; ?>;">Reviews</a>
            </div>
        </aside>

        <div style="flex: 1;">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo escapeOutput($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo escapeOutput($success); ?></div>
            <?php endif; ?>

            <?php if ($section === 'overview'): ?>
                <h1 class="title-primary" style="text-align: left;">Dashboard Overview</h1>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
                    <div class="card" style="text-align: center; border-bottom: 4px solid var(--clr-brand);">
                        <h3 style="font-size: 2.5rem; color: var(--clr-text-title);"><?php echo $stats['total_users']; ?></h3>
                        <p>Total Users</p>
                    </div>
                    <div class="card" style="text-align: center; border-bottom: 4px solid var(--clr-cta);">
                        <h3 style="font-size: 2.5rem; color: var(--clr-text-title);"><?php echo $stats['total_sitters']; ?></h3>
                        <p>Pet Sitters</p>
                    </div>
                    <div class="card" style="text-align: center; border-bottom: 4px solid var(--clr-primary);">
                        <h3 style="font-size: 2.5rem; color: var(--clr-text-title);"><?php echo $stats['total_reviews']; ?></h3>
                        <p>Total Reviews</p>
                    </div>
                </div>

            <?php elseif ($section === 'users'): ?>
                <h1 class="title-primary" style="text-align: left;">User Management</h1>
                <div class="card" style="margin-top: 2rem; overflow-x: auto; padding: 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background-color: #f3f4f6;">
                            <tr>
                                <th style="padding: 1rem; text-align: left;">Username</th>
                                <th style="padding: 1rem; text-align: left;">Email</th>
                                <th style="padding: 1rem; text-align: left;">Type</th>
                                <th style="padding: 1rem; text-align: left;">Status</th>
                                <th style="padding: 1rem; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 1rem;"><strong><?php echo escapeOutput($u['username']); ?></strong></td>
                                    <td style="padding: 1rem;"><?php echo escapeOutput($u['email']); ?></td>
                                    <td style="padding: 1rem;"><?php echo escapeOutput($u['user_type']); ?></td>
                                    <td style="padding: 1rem;">
                                        <?php if ($u['is_banned']): ?>
                                            <span style="color: var(--clr-error-text); font-weight: bold;">Banned</span>
                                        <?php else: ?>
                                            <span style="color: var(--clr-success-text); font-weight: bold;">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <?php if ($u['is_banned']): ?>
                                                <input type="hidden" name="admin_action" value="unban_user">
                                                <button type="submit" style="padding: 0.5rem 1rem; background: var(--clr-success-bg); color: var(--clr-success-text); border: none; border-radius: 4px; cursor: pointer;">Unban</button>
                                            <?php else: ?>
                                                <input type="hidden" name="admin_action" value="ban_user">
                                                <button type="submit" style="padding: 0.5rem 1rem; background: #fff3cd; color: #856404; border: none; border-radius: 4px; cursor: pointer;">Ban</button>
                                            <?php endif; ?>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                            <input type="hidden" name="admin_action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" style="padding: 0.5rem 1rem; background: var(--clr-error-bg); color: var(--clr-error-text); border: none; border-radius: 4px; cursor: pointer; margin-left: 0.5rem;">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($section === 'reviews'): ?>
                <h1 class="title-primary" style="text-align: left;">Review Management</h1>
                <div class="card" style="margin-top: 2rem; overflow-x: auto; padding: 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background-color: #f3f4f6;">
                            <tr>
                                <th style="padding: 1rem; text-align: left;">From → To</th>
                                <th style="padding: 1rem; text-align: left;">Review</th>
                                <th style="padding: 1rem; text-align: left;">Status</th>
                                <th style="padding: 1rem; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $rev): ?>
                                <tr style="border-bottom: 1px solid #e5e7eb;">
                                    <td style="padding: 1rem;">
                                        <strong><?php echo escapeOutput($rev['rater_name']); ?></strong><br>
                                        → <?php echo escapeOutput($rev['rated_name']); ?>
                                    </td>
                                    <td style="padding: 1rem; max-width: 300px;">
                                        <span style="color: var(--clr-brand);"><?php echo str_repeat('★', $rev['rating']); ?></span><br>
                                        <?php echo escapeOutput(substr($rev['review_text'], 0, 80)); ?>...
                                    </td>
                                    <td style="padding: 1rem;">
                                        <?php echo $rev['is_disabled'] ? '<span style="color: var(--clr-error-text);">Hidden</span>' : '<span style="color: var(--clr-success-text);">Visible</span>'; ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                            <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                                            <?php if ($rev['is_disabled']): ?>
                                                <input type="hidden" name="admin_action" value="enable_review">
                                                <button type="submit" style="padding: 0.5rem 1rem; background: var(--clr-success-bg); color: var(--clr-success-text); border: none; border-radius: 4px; cursor: pointer;">Enable</button>
                                            <?php else: ?>
                                                <input type="hidden" name="admin_action" value="disable_review">
                                                <button type="submit" style="padding: 0.5rem 1rem; background: #fff3cd; color: #856404; border: none; border-radius: 4px; cursor: pointer;">Disable</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
