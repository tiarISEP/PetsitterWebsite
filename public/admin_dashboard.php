<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();
redirectToLogin();

$user = getUserById($pdo, $_SESSION['user_id']);

if (!$user || !isset($user['is_admin']) || $user['is_admin'] != 1) {
    header("Location: dashboard.php");
    exit();
}

function escapeAndWrap($text, $max = 100) {
    $text = $text ?? '';
    $plain = strip_tags($text);
    if (mb_strlen($plain) <= $max) {
        return escapeOutput($plain);
    }
    return escapeOutput(mb_substr($plain, 0, $max - 1)) . '…';
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

                    // ── Contact Messages ───────────────────────────────────
                    case 'message_change_status':
                        $message_id = (int)($_POST['message_id'] ?? 0);
                        $new_status = $_POST['status'] ?? '';
                        if (in_array($new_status, ['non_traite', 'en_cours', 'archive'])) {
                            $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?")->execute([$new_status, $message_id]);
                            $success = 'Statut du message mis à jour.';
                        } else {
                            $error = 'Statut invalide.';
                        }
                        break;

                    case 'message_reply':
                        $message_id = (int)($_POST['message_id'] ?? 0);
                        $reply_message = trim($_POST['reply_message'] ?? '');
                        $new_status = $_POST['status'] ?? '';
                        
                        if ($message_id && !empty($reply_message)) {
                            if (in_array($new_status, ['non_traite', 'en_cours', 'archive'])) {
                                $pdo->prepare("UPDATE contact_messages SET reply_message = ?, replied_at = NOW(), status = ? WHERE id = ?")
                                    ->execute([$reply_message, $new_status, $message_id]);
                            } else {
                                $pdo->prepare("UPDATE contact_messages SET reply_message = ?, replied_at = NOW() WHERE id = ?")
                                    ->execute([$reply_message, $message_id]);
                            }
                            
                            // Simulate sending email
                            $msg_stmt = $pdo->prepare("SELECT email, subject, name FROM contact_messages WHERE id = ?");
                            $msg_stmt->execute([$message_id]);
                            $msg_info = $msg_stmt->fetch();
                            if ($msg_info) {
                                $to = $msg_info['email'];
                                $subj = "Re: " . $msg_info['subject'];
                                $headers = "From: admin@petsitter.local\r\nReply-To: support@petsitter.local";
                                @mail($to, $subj, $reply_message, $headers);
                            }
                            
                            $success = 'Réponse enregistrée et envoyée par email.';
                        } else {
                            $error = 'Veuillez rédiger une réponse non vide.';
                        }
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

$faq_categories = [];
$faq_items      = [];
if ($section === 'faq') {
    $faq_categories = $pdo->query("SELECT * FROM faq_category ORDER BY sort_order")->fetchAll();
    $faq_items      = $pdo->query("SELECT f.*, c.label AS cat_label FROM faq f JOIN faq_category c ON f.category_id = c.id ORDER BY f.category_id, f.sort_order")->fetchAll();
}

$cgu_versions = [];
if ($section === 'cgu') {
    $cgu_versions = $pdo->query("SELECT * FROM cgu_versions ORDER BY version_number DESC, id")->fetchAll();
}

// Fetch posts for post management
$posts = [];
$post_search = '';
$post_view = null;
if ($section === 'posts') {
    $post_search = trim($_GET['search'] ?? '');
    $post_view_id = (int)($_GET['view'] ?? 0);

    // View individual post
    if ($post_view_id > 0) {
        $stmt = $pdo->prepare(
            "SELECT p.postID, p.Title, p.Description, p.Price, p.CreationDate, p.Visibility, 
                    p.Applicability, u.username, u.first_name, u.last_name, u.email,
                    COUNT(DISTINCT a.appID) as app_count
             FROM post p
             JOIN users u ON p.User_userID = u.id
             LEFT JOIN application a ON p.postID = a.Post_postID
             WHERE p.postID = ?
             GROUP BY p.postID"
        );
        $stmt->execute([$post_view_id]);
        $post_view = $stmt->fetch();
    } else {
        // List posts with search
        $query = "SELECT p.postID, p.Title, p.Description, p.Price, p.CreationDate, p.Visibility, 
                         u.username, u.first_name, COUNT(DISTINCT a.appID) as app_count
                  FROM post p
                  JOIN users u ON p.User_userID = u.id
                  LEFT JOIN application a ON p.postID = a.Post_postID";
        
        if (!empty($post_search)) {
            $query .= " WHERE p.Title LIKE ? OR p.Description LIKE ? OR u.username LIKE ?";
            $search_term = '%' . $post_search . '%';
            $stmt = $pdo->prepare($query . " GROUP BY p.postID ORDER BY p.CreationDate DESC");
            $stmt->execute([$search_term, $search_term, $search_term]);
        } else {
            $stmt = $pdo->prepare($query . " GROUP BY p.postID ORDER BY p.CreationDate DESC");
            $stmt->execute();
        }
        $posts = $stmt->fetchAll();
    }
}

// Fetch reports for report management
$reports = [];
$report_search = '';
$report_view = null;
if ($section === 'reports') {
    $report_search = trim($_GET['search'] ?? '');
    $report_view_id = (int)($_GET['view'] ?? 0);

    // View individual report
    if ($report_view_id > 0) {
        $stmt = $pdo->prepare(
            "SELECT r.id, r.report_type, r.reported_user_id, r.post_id, r.reason, 
                    r.description, r.status, r.created_at, r.resolved_at,
                    reporter.username as reporter_username, reporter.first_name as reporter_first_name,
                    reported.username as reported_username, reported.first_name as reported_first_name
             FROM reports r
             LEFT JOIN users reporter ON r.reporter_user_id = reporter.id
             LEFT JOIN users reported ON r.reported_user_id = reported.id
             WHERE r.id = ?"
        );
        $stmt->execute([$report_view_id]);
        $report_view = $stmt->fetch();
    } else {
        // List reports with search
        $query = "SELECT r.id, r.report_type, r.reported_user_id, r.post_id, r.reason, 
                         r.description, r.status, r.created_at,
                         reporter.username as reporter_username, reporter.first_name as reporter_first_name,
                         reported.username as reported_username
                  FROM reports r
                  LEFT JOIN users reporter ON r.reporter_user_id = reporter.id
                  LEFT JOIN users reported ON r.reported_user_id = reported.id";
        
        if (!empty($report_search)) {
            $query .= " WHERE r.reason LIKE ? OR r.description LIKE ? OR r.status LIKE ? OR reported.username LIKE ?";
            $search_term = '%' . $report_search . '%';
            $stmt = $pdo->prepare($query . " ORDER BY r.created_at DESC");
            $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
        } else {
            $stmt = $pdo->prepare($query . " ORDER BY r.created_at DESC");
            $stmt->execute();
        }
        $reports = $stmt->fetchAll();
    }
}

 

$csrf_token = generateCsrfToken();
$error = $error ?? '';
$success = $success ?? '';

$pageTitle = "Admin Dashboard | PetSitter's Market";
require_once 'includes/header.php';
?>

<main id="main-content" class="container">
    
    <div class="admin-container">
        <!--Sidebar-->
        <aside class="admin-sidebar">
            <h3>Admin Menu</h3>
            
            <div class="admin-menu">
                <a href="?section=overview" class="admin-menu-link <?php echo $section === 'overview' ? 'active' : ''; ?>">Overview</a>
                <a href="?section=users" class="admin-menu-link <?php echo $section === 'users' ? 'active' : ''; ?>">User Management</a>
                <a href="?section=reviews" class="admin-menu-link <?php echo $section === 'reviews' ? 'active' : ''; ?>">Reviews</a>
                
                <a href="?section=faq" class="admin-menu-link <?php echo $section === 'faq' ? 'active' : ''; ?>">FAQ</a>
                <a href="?section=cgu" class="admin-menu-link <?php echo $section === 'cgu' ? 'active' : ''; ?>">CGU</a>

                <a href="?section=posts" class="admin-menu-link <?php echo $section === 'posts' ? 'active' : ''; ?>">Posts</a>
                <a href="?section=reports" class="admin-menu-link <?php echo $section === 'reports' ? 'active' : ''; ?>">Reports</a>
                <a href="?section=settings" class="admin-menu-link <?php echo $section === 'settings' ? 'active' : ''; ?>">Settings</a>
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

            <!-- FAQ MANAGEMENT SECTION -->
            <?php elseif ($section === 'faq'): ?>

                <h1 class="title-primary admin-title">FAQ Management</h1>

                <!-- Add Category -->
                <div class="card" style="margin-bottom: 2rem;">
                    <h3 style="margin-bottom: 1rem;"><i class="fas fa-folder-plus" style="color:var(--clr-brand);"></i> Add Category</h3>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden" name="admin_action" value="faq_cat_add">
                        
                        <div class="form-group">
                            <label>Slug (e.g. safety)</label>
                            <input type="text" name="slug" placeholder="slug" required>
                        </div>
                        <div class="form-group">
                            <label>Label (e.g. Safety & Security)</label>
                            <input type="text" name="label" placeholder="Label" required>
                        </div>
                        <div class="form-group">
                            <label>FA icon (e.g. fa-shield-alt)</label>
                            <input type="text" name="icon" placeholder="FA icon">
                        </div>
                        <button class="btn btn-cta" type="submit" style="width: 100%;">Add Category</button>
                    </form>
                </div>

                <!-- Add Question -->
                <div class="card" style="margin-bottom: 2rem;">
                    <h3 style="margin-bottom: 1rem;"><i class="fas fa-plus-circle" style="color:var(--clr-brand);"></i> Add Question</h3>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden" name="admin_action" value="faq_add">
                        
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" required style="width: 100%; padding: 1.1rem 1.2rem; border: 2px solid #e2d9cd; border-radius: 12px; background: linear-gradient(135deg, #fbf8f4 0%, #faf5f0 100%); font-family: inherit; font-size: 0.95rem;">
                                <option value="">— Select category —</option>
                                <?php foreach ($faq_categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo escapeOutput($cat['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Question</label>
                            <input type="text" name="question" placeholder="Question" required>
                        </div>
                        <div class="form-group">
                            <label>Answer</label>
                            <textarea name="answer" placeholder="Answer" required style="min-height: 100px; resize: vertical;"></textarea>
                        </div>
                        <button class="btn btn-cta" type="submit" style="width: 100%;">Add Question</button>
                    </form>
                </div>

                <!-- Questions grouped by category -->
                <?php foreach ($faq_categories as $cat):
                    $cat_questions = array_filter($faq_items, fn($q) => $q['category_id'] == $cat['id']);
                ?>
                <div class="faq-cat-block">
                    <div class="faq-cat-label" style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #fbf8f4; border-radius: 12px 12px 0 0; border: var(--card-border); border-bottom: none;">
                        <span style="font-weight: 600; font-size: 1.1rem;"><i class="fas <?php echo escapeOutput($cat['icon']); ?>" style="color: var(--clr-primary);"></i> <?php echo escapeOutput($cat['label']); ?> <small style="font-weight: normal; color: #777;">(<?php echo count($cat_questions); ?> questions)</small></span>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category and ALL its questions?')">
                            <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                            <input type="hidden" name="admin_action" value="faq_cat_delete">
                            <input type="hidden" name="category_id"  value="<?php echo $cat['id']; ?>">
                            <button class="btn btn-text" type="submit" style="color: var(--clr-error-text); padding: 0.5rem;"><i class="fas fa-trash-alt"></i> Delete Category</button>
                        </form>
                    </div>
                    <div class="adm-table-wrap" style="border-radius:0 0 8px 8px; background: #ffffff;">
                        <table class="adm-table">
                            <thead><tr>
                                <th>ID</th><th>Question</th><th>Answer</th><th>Status</th><th style="text-align:right;">Actions</th>
                            </tr></thead>
                            <tbody>
                            <?php if (empty($cat_questions)): ?>
                                <tr><td colspan="5" class="text-center-small">No questions yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($cat_questions as $q): ?>
                            <tr>
                                <td><?php echo $q['id']; ?></td>
                                <td><span class="Wrap"><?php echo escapeAndWrap($q['question'], 100); ?></span></td>
                                <td><span class="Wrap"><?php echo escapeAndWrap($q['answer'], 100); ?></span></td>
                                <td><?php echo $q['is_active'] ? '<span class="badge badge-active">Active</span>' : '<span class="badge badge-inactive">Hidden</span>'; ?></td>
                                <td style="display: flex; justify-content: flex-end; gap: 0.5rem; align-items: center; white-space: nowrap;">
                                    <!-- Toggle -->
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                        <input type="hidden" name="admin_action" value="faq_toggle">
                                        <input type="hidden" name="faq_id"       value="<?php echo $q['id']; ?>">
                                        <button style="padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; color: white; background: <?php echo $q['is_active'] ? 'var(--clr-brand)' : 'var(--clr-cta)'; ?>;"><?php echo $q['is_active']?'Hide':'Show'; ?></button>
                                    </form>
                                    <!-- Edit -->
                                    <button style="padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; color: white; background: var(--clr-primary);"
                                        onclick="openFaqEdit(<?php echo $q['id']; ?>, <?php echo htmlspecialchars(json_encode($q['question'])); ?>, <?php echo htmlspecialchars(json_encode($q['answer'])); ?>)">Edit</button>
                                    <!-- Delete -->
                                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete this question?')">
                                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                        <input type="hidden" name="admin_action" value="faq_delete">
                                        <input type="hidden" name="faq_id"       value="<?php echo $q['id']; ?>">
                                        <button style="padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; color: white; background: var(--clr-error-text);">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>

            <!-- CGU MANAGEMENT SECTION -->
            <?php elseif ($section === 'cgu'): ?>

                <h1 class="title-primary admin-title">CGU Management</h1>

                <!-- Add CGU Version -->
                <div class="card" style="margin-bottom: 2rem;">
                    <h3 style="margin-bottom: 1rem;"><i class="fas fa-plus-circle" style="color:var(--clr-brand);"></i> Add CGU Version</h3>
                    <form method="POST" class="auth-form">
                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden" name="admin_action" value="cgu_version_add">
                        
                        <div class="form-group">
                            <label>Version (e.g. 1.0, 1.1)</label>
                            <input type="text" name="version_number" placeholder="Version" required>
                        </div>
                        <div class="form-group">
                            <label>Effective Date</label>
                            <input type="date" name="effective_from" required>
                        </div>
                        <div class="form-group">
                            <label>Section Title</label>
                            <input type="text" name="section_title" placeholder="Title" required>
                        </div>
                        <div class="form-group">
                            <label>Section Content</label>
                            <textarea name="content" placeholder="Content" required style="min-height:150px; resize: vertical;"></textarea>
                        </div>
                        <button class="btn btn-cta" type="submit" style="width: 100%;">Add Version</button>
                    </form>
                </div>

                <!-- CGU Versions -->
                <div class="card" style="margin-top: 2rem; overflow-x: auto; padding: 0;">
                    <table class="adm-table">
                        <thead><tr>
                            <th>ID</th><th>Version</th><th>Section Title</th><th>Effective Date</th><th>Status</th><th style="text-align:right;">Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if (empty($cgu_versions)): ?>
                            <tr><td colspan="6" class="text-center-small">No CGU versions yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($cgu_versions as $cgu): ?>
                        <tr>
                            <td><?php echo $cgu['id']; ?></td>
                            <td><strong><?php echo escapeOutput($cgu['version_number']); ?></strong></td>
                            <td><?php echo escapeOutput($cgu['section_title']); ?></td>
                            <td><?php echo escapeOutput($cgu['effective_from']); ?></td>
                            <td><?php echo $cgu['is_active'] ? '<span class="badge badge-active">Active</span>' : '<span class="badge badge-inactive">Inactive</span>'; ?></td>
                            <td style="display: flex; justify-content: flex-end; gap: 0.5rem; align-items: center; white-space: nowrap;">
                                <button style="padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; color: white; background: var(--clr-primary);"
                                    onclick="openCguEdit(<?php echo $cgu['id']; ?>, <?php echo htmlspecialchars(json_encode($cgu['section_title'])); ?>, <?php echo htmlspecialchars(json_encode($cgu['content'])); ?>, <?php echo htmlspecialchars(json_encode($cgu['effective_from'])); ?>)">Edit</button>
                                <?php if (!$cgu['is_active']): ?>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                    <input type="hidden" name="admin_action" value="cgu_version_activate">
                                    <input type="hidden" name="cgu_id"       value="<?php echo $cgu['id']; ?>">
                                    <button style="padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; color: white; background: var(--clr-cta);">Activate</button>
                                </form>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete this CGU version?')">
                                    <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                    <input type="hidden" name="admin_action" value="cgu_version_delete">
                                    <input type="hidden" name="cgu_id"       value="<?php echo $cgu['id']; ?>">
                                    <button style="padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; color: white; background: var(--clr-error-text);">Delete</button>
                                </form>
                                <?php else: ?>
                                <span class="badge badge-info">Active Version</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <!-- POSTS SECTION -->
            <?php elseif ($section === 'posts'): ?>
                <h1 class="title-primary admin-title">Post Management</h1>
                <p class="admin-subtitle">Monitor and manage platform posts. Search by title, description, or creator.</p>

                <?php if ($post_view): ?>
                    <!-- Individual Post View -->
                    <div class="card" style="margin-top: 2rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h2><?php echo escapeOutput($post_view['Title']); ?></h2>
                            <a href="?section=posts" class="btn-small btn-neutral">← Back to List</a>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div>
                                <p><strong>Post ID:</strong> <?php echo $post_view['postID']; ?></p>
                                <p><strong>Creator:</strong> <?php echo escapeOutput($post_view['first_name'] ?: $post_view['username']); ?></p>
                                <p><strong>Email:</strong> <?php echo escapeOutput($post_view['email']); ?></p>
                                <p><strong>Price:</strong> $<?php echo number_format($post_view['Price'], 2); ?></p>
                            </div>
                            <div>
                                <p><strong>Created:</strong> <?php echo date('M j, Y H:i', strtotime($post_view['CreationDate'])); ?></p>
                                <p><strong>Status:</strong> <?php echo $post_view['Visibility'] ? '<span class="badge badge-active">Visible</span>' : '<span class="badge badge-inactive">Hidden</span>'; ?></p>
                                <p><strong>Applications:</strong> <?php echo $post_view['app_count']; ?></p>
                                <p><strong>Type:</strong> <?php echo escapeOutput($post_view['Applicability']); ?></p>
                            </div>
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <h3>Description</h3>
                            <div style="background: #f9f9f9; padding: 1rem; border-radius: 6px; border-left: 4px solid var(--clr-brand);">
                                <?php echo escapeAndWrap($post_view['Description'] ?? 'No description provided', 100); ?>
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.5rem;">
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                <input type="hidden" name="admin_action" value="toggle_post_visibility">
                                <input type="hidden" name="post_id" value="<?php echo $post_view['postID']; ?>">
                                <button type="submit" class="btn-small <?php echo $post_view['Visibility'] ? 'btn-warning' : 'btn-success'; ?>">
                                    <?php echo $post_view['Visibility'] ? 'Hide Post' : 'Show Post'; ?>
                                </button>
                            </form>
                            <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this post permanently?');">
                                <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                <input type="hidden" name="admin_action" value="delete_post">
                                <input type="hidden" name="post_id" value="<?php echo $post_view['postID']; ?>">
                                <button type="submit" class="btn-small btn-danger">Delete Post</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Post Search -->
                    <form method="GET" class="search-form" style="margin-top: 1.5rem; max-width: none;">
                        <input type="hidden" name="section" value="posts">
                        <div class="input-group">
                            <i class="fas fa-search" style="color: #888;"></i>
                            <input type="text" name="search" placeholder="Search by title, description, or creator..." value="<?php echo escapeOutput($post_search); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.5rem;">Search</button>
                        <?php if (!empty($post_search)): ?>
                            <a href="?section=posts" class="btn btn-text" style="align-self: center;">Clear</a>
                        <?php endif; ?>
                    </form>

                    <!-- Posts Table -->
                    <div class="card" style="margin-top: 2rem; overflow-x: auto; padding: 0;">
                        <?php if (empty($posts)): ?>
                            <div style="padding: 2rem; text-align: center;">
                                <p style="color: #666;">No posts found.</p>
                            </div>
                        <?php else: ?>
                            <table class="adm-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Creator</th>
                                        <th>Price</th>
                                        <th>Applications</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($posts as $post): ?>
                                        <tr>
                                            <td><?php echo $post['postID']; ?></td>
                                            <td><?php echo escapeOutput($post['Title']); ?></td>
                                            <td><?php echo escapeOutput($post['first_name'] ?: $post['username']); ?></td>
                                            <td>$<?php echo number_format($post['Price'], 2); ?></td>
                                            <td><span class="badge"><?php echo $post['app_count']; ?></span></td>
                                            <td>
                                                <?php echo $post['Visibility'] ? '<span class="badge badge-active">Visible</span>' : '<span class="badge badge-inactive">Hidden</span>'; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($post['CreationDate'])); ?></td>
                                            <td style="display: flex; justify-content: flex-end; gap: 0.5rem; align-items: center;">
                                                <a href="?section=posts&view=<?php echo $post['postID']; ?>" style="padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; color: white; background: var(--clr-brand); text-decoration: none;">View</a>
                                                <form method="POST" style="margin: 0;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                                    <input type="hidden" name="admin_action" value="toggle_post_visibility">
                                                    <input type="hidden" name="post_id" value="<?php echo $post['postID']; ?>">
                                                    <button type="submit" style="padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; color: white; background: <?php echo $post['Visibility'] ? 'var(--clr-brand)' : 'var(--clr-cta)'; ?>;">
                                                        <?php echo $post['Visibility'] ? 'Hide' : 'Show'; ?>
                                                    </button>
                                                </form>
                                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete this post?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                                    <input type="hidden" name="admin_action" value="delete_post">
                                                    <input type="hidden" name="post_id" value="<?php echo $post['postID']; ?>">
                                                    <button type="submit" style="padding: 0.4rem 0.8rem; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; color: white; background: var(--clr-error-text);">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>


            <!-- REPORTS SECTION -->
            <?php elseif ($section === 'reports'): ?>
                <h1 class="title-primary admin-title">Report Management</h1>
                <p class="admin-subtitle">Review and respond to user reports. Search by reason, status, or reported user.</p>

                <?php if ($report_view): ?>
                    <!-- Individual Report View -->
                    <div class="card" style="margin-top: 2rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <h2>Report #<?php echo $report_view['id']; ?></h2>
                            <a href="?section=reports" class="btn-small btn-neutral">← Back to List</a>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                            <div>
                                <p><strong>Report Type:</strong> <?php echo ucfirst(escapeOutput($report_view['report_type'])); ?></p>
                                <p><strong>Reason:</strong> <?php echo escapeOutput($report_view['reason']); ?></p>
                                <p><strong>Reporter:</strong> <?php echo escapeOutput($report_view['reporter_first_name'] ?: $report_view['reporter_username'] ?: 'Anonymous'); ?></p>
                                <p><strong>Reported User:</strong> <?php echo escapeOutput($report_view['reported_first_name'] ?: $report_view['reported_username'] ?: 'N/A'); ?></p>
                            </div>
                            <div>
                                <p><strong>Status:</strong> 
                                    <span class="badge <?php 
                                        echo $report_view['status'] === 'open' ? 'badge-warn' : 
                                             ($report_view['status'] === 'resolved' ? 'badge-active' : 'badge-inactive'); 
                                    ?>">
                                        <?php echo ucfirst($report_view['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Created:</strong> <?php echo date('M j, Y H:i', strtotime($report_view['created_at'])); ?></p>
                                <?php if ($report_view['resolved_at']): ?>
                                    <p><strong>Resolved:</strong> <?php echo date('M j, Y H:i', strtotime($report_view['resolved_at'])); ?></p>
                                <?php endif; ?>
                                <p><strong>Post ID:</strong> <?php echo $report_view['post_id'] ?? 'N/A'; ?></p>
                            </div>
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <h3>Description</h3>
                            <div style="background: #f9f9f9; padding: 1rem; border-radius: 6px; border-left: 4px solid var(--clr-brand);">
                                <?php echo escapeAndWrap($report_view['description'] ?? 'No description provided', 100); ?>
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.5rem;">
                            <?php if ($report_view['status'] !== 'resolved'): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                    <input type="hidden" name="admin_action" value="resolve_report">
                                    <input type="hidden" name="report_id" value="<?php echo $report_view['id']; ?>">
                                    <button type="submit" class="btn-small btn-success">Mark as Resolved</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($report_view['status'] !== 'dismissed'): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                    <input type="hidden" name="admin_action" value="dismiss_report">
                                    <input type="hidden" name="report_id" value="<?php echo $report_view['id']; ?>">
                                    <button type="submit" class="btn-small btn-warning">Dismiss</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($report_view['status'] !== 'open'): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                    <input type="hidden" name="admin_action" value="reopen_report">
                                    <input type="hidden" name="report_id" value="<?php echo $report_view['id']; ?>">
                                    <button type="submit" class="btn-small btn-neutral">Reopen</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Report Search -->
                    <form method="GET" style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
                        <input type="hidden" name="section" value="reports">
                        <input type="text" name="search" placeholder="Search by reason, description, status, or user..." 
                               value="<?php echo escapeOutput($report_search); ?>" style="flex: 1; padding: 0.75rem;">
                        <button type="submit" class="btn-small btn-primary-sm">Search</button>
                        <?php if (!empty($report_search)): ?>
                            <a href="?section=reports" class="btn-small btn-neutral">Clear</a>
                        <?php endif; ?>
                    </form>

                    <!-- Reports Table -->
                    <div class="card" style="margin-top: 2rem; overflow-x: auto; padding: 0;">
                        <?php if (empty($reports)): ?>
                            <div style="padding: 2rem; text-align: center;">
                                <p style="color: #666;">No reports found.</p>
                            </div>
                        <?php else: ?>
                            <table class="adm-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Reason</th>
                                        <th>Reported User</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reports as $report): ?>
                                        <tr>
                                            <td><?php echo $report['id']; ?></td>
                                            <td><?php echo ucfirst(escapeOutput($report['report_type'])); ?></td>
                                            <td><?php echo escapeAndWrap($report['reason'] ?? '', 50); ?></td>
                                            <td><?php echo escapeOutput($report['reported_first_name'] ?: $report['reported_username'] ?: 'N/A'); ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $report['status'] === 'open' ? 'badge-warn' : 
                                                         ($report['status'] === 'resolved' ? 'badge-active' : 'badge-inactive'); 
                                                ?>">
                                                    <?php echo ucfirst($report['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($report['created_at'])); ?></td>
                                            <td style="text-align:right;">
                                                <a href="?section=reports&view=<?php echo $report['id']; ?>" class="btn-small btn-neutral">View</a>
                                                <?php if ($report['status'] === 'open'): ?>
                                                    <form method="POST" class="inline-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                                        <input type="hidden" name="admin_action" value="resolve_report">
                                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                        <button type="submit" class="btn-small btn-success" style="font-size: 0.8rem;">Resolve</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>


            <!-- SETTINGS SECTION -->
            <?php elseif ($section === 'settings'): ?>
                <h1 class="title-primary admin-title">Admin Settings</h1>
                <p class="admin-subtitle">Configure platform-wide settings and manage system preferences.</p>

                <div style="margin-top: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <!-- Platform Info -->
                    <div class="card">
                        <h3 style="margin-top: 0; color: var(--clr-brand);">Platform Information</h3>
                        <div style="font-size: 0.95rem;">
                            <p><strong>Platform Name:</strong> PetSitter's Market</p>
                            <p><strong>Version:</strong> 1.0.0</p>
                            <p><strong>Database:</strong> petsitter_db</p>
                            <p><strong>Last Updated:</strong> June 1, 2026</p>
                        </div>
                    </div>

                    <!-- System Stats -->
                    <div class="card">
                        <h3 style="margin-top: 0; color: var(--clr-primary);">System Overview</h3>
                        <div style="font-size: 0.95rem;">
                            <?php 
                                $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                                $total_posts = $pdo->query("SELECT COUNT(*) FROM post")->fetchColumn();
                                $total_applications = $pdo->query("SELECT COUNT(*) FROM application")->fetchColumn();
                                $total_reports = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn() ?? 0;
                            ?>
                            <p><strong>Total Users:</strong> <?php echo $total_users; ?></p>
                            <p><strong>Active Posts:</strong> <?php echo $total_posts; ?></p>
                            <p><strong>Applications:</strong> <?php echo $total_applications; ?></p>
                            <p><strong>Reports:</strong> <?php echo $total_reports; ?></p>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="card">
                        <h3 style="margin-top: 0; color: var(--clr-cta);">Security Settings</h3>
                        <div style="font-size: 0.95rem;">
                            <p><strong>Session Timeout:</strong> 30 minutes</p>
                            <p><strong>Password Policy:</strong> Bcrypt hashing</p>
                            <p><strong>CSRF Protection:</strong> Enabled</p>
                            <p><strong>HTTPS:</strong> Recommended</p>
                        </div>
                    </div>

                    <!-- Email Settings -->
                    <div class="card">
                        <h3 style="margin-top: 0; color: var(--clr-secondary);">Email Configuration</h3>
                        <div style="font-size: 0.95rem;">
                            <p><strong>SMTP Server:</strong> Not configured</p>
                            <p><strong>From Address:</strong> admin@petsitter.local</p>
                            <p><strong>Reply-To:</strong> support@petsitter.local</p>
                            <p style="color: #666;"><small>Configure in config files</small></p>
                        </div>
                    </div>

                    <!-- Moderation Rules -->
                    <div class="card">
                        <h3 style="margin-top: 0; color: #e74c3c;">Moderation Rules</h3>
                        <div style="font-size: 0.95rem;">
                            <p><strong>Auto-ban on Reports:</strong> After 3 reports</p>
                            <p><strong>Post Review:</strong> Disabled</p>
                            <p><strong>Content Filter:</strong> Basic</p>
                            <p style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                                <a href="#" style="color: var(--clr-brand); text-decoration: none; font-weight: 500;">Configure Rules →</a>
                            </p>
                        </div>
                    </div>

                    <!-- Backup & Maintenance -->
                    <div class="card">
                        <h3 style="margin-top: 0; color: #27ae60;">Maintenance</h3>
                        <div style="font-size: 0.95rem;">
                            <p><strong>Last Backup:</strong> N/A</p>
                            <p><strong>Maintenance Mode:</strong> Off</p>
                            <p><strong>Error Logging:</strong> Enabled</p>
                            <p style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                                <a href="#" style="color: var(--clr-brand); text-decoration: none; font-weight: 500;">View Logs →</a>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Advanced Settings -->
                <div class="card" style="margin-top: 2rem;">
                    <h3 style="margin-top: 0;">Advanced Settings</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; font-size: 0.95rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Cache Management</label>
                            <button class="btn-small btn-neutral" onclick="alert('Cache cleared!')">Clear Cache</button>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Session Management</label>
                            <button class="btn-small btn-neutral" onclick="alert('Sessions cleaned!')">Clear Old Sessions</button>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Database Optimization</label>
                            <button class="btn-small btn-neutral" onclick="alert('Database optimized!')">Optimize Tables</button>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Export Data</label>
                            <button class="btn-small btn-neutral" onclick="alert('Export initiated!')">Export Database</button>
                        </div>
                    </div>
                </div>


            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    // Confirm before sensitive actions
    function confirmAction(message) {
        return confirm(message);
    }

    // FAQ Edit Modal
    function openFaqEdit(faqId, question, answer) {
        const modal = `
            <div class="modal-overlay" id="faqEditModal">
                <div class="modal-content">
                    <h2>Edit FAQ Question</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $GLOBALS['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="admin_action" value="faq_edit">
                        <input type="hidden" name="faq_id" value="${faqId}">
                        <div class="modal-form-group">
                            <label>Question:</label>
                            <input type="text" name="question" value="${question}" required>
                        </div>
                        <div class="modal-form-group">
                            <label>Answer:</label>
                            <textarea name="answer" required>${answer}</textarea>
                        </div>
                        <div class="modal-button-row">
                            <button type="button" class="btn-sm btn-neutral" onclick="document.getElementById('faqEditModal').remove()">Cancel</button>
                            <button type="submit" class="btn-sm btn-primary-sm">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modal);
    }

    // CGU Version Edit Modal
    function openCguEdit(cguId, sectionTitle, content, effectiveFrom) {
        const modal = `
            <div class="modal-overlay" id="cguEditModal">
                <div class="modal-content">
                    <h2>Edit CGU Version</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden" name="admin_action" value="cgu_version_edit">
                        <input type="hidden" name="cgu_id" value="${cguId}">
                        <div class="modal-form-group">
                            <label>Section Title:</label>
                            <input type="text" name="section_title" value="${sectionTitle}" required>
                        </div>
                        <div class="modal-form-group">
                            <label>Effective From:</label>
                            <input type="date" name="effective_from" value="${effectiveFrom}" required>
                        </div>
                        <div class="modal-form-group">
                            <label>Content:</label>
                            <textarea name="content" required style="min-height:200px;">${content}</textarea>
                        </div>
                        <div class="modal-button-row">
                            <button type="button" class="btn-sm btn-neutral" onclick="document.getElementById('cguEditModal').remove()">Cancel</button>
                            <button type="submit" class="btn-sm btn-primary-sm">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modal);
    }
</script>

<?php require_once 'includes/footer.php'; ?>
