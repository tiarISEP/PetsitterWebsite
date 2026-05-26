<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

// Check if user is admin
redirectToLogin();
$user = getUserById($pdo, $_SESSION['user_id']);

if (!$user || !isset($user['is_admin']) || $user['is_admin'] != 1) {
    header("Location: dashboard.php");
    exit();
}

// Determine current section
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
                switch ($_POST['admin_action'] ?? '') {
                    // case 'ban_user':
                    //     $user_id = (int)($_POST['user_id'] ?? 0);
                    //     $stmt = $pdo->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
                    //     $stmt->execute([$user_id]);
                    //     $success = 'User banned successfully.';
                    //     break;
                    
                    // case 'unban_user':
                    //     $user_id = (int)($_POST['user_id'] ?? 0);
                    //     $stmt = $pdo->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
                    //     $stmt->execute([$user_id]);
                    //     $success = 'User unbanned successfully.';
                    //     break;
                    
                    // case 'delete_user':
                    //     $user_id = (int)($_POST['user_id'] ?? 0);
                    //     // Delete user's reviews first
                    //     $stmt = $pdo->prepare("DELETE FROM reviews WHERE rater_user_id = ? OR rated_user_id = ?");
                    //     $stmt->execute([$user_id, $user_id]);
                    //     // Delete user
                    //     $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    //     $stmt->execute([$user_id]);
                    //     $success = 'User deleted successfully.';
                    //     break;
                    
                    // case 'disable_review':
                    //     $review_id = (int)($_POST['review_id'] ?? 0);
                    //     $stmt = $pdo->prepare("UPDATE reviews SET is_disabled = 1 WHERE id = ?");
                    //     $stmt->execute([$review_id]);
                    //     $success = 'Review disabled successfully.';
                    //     break;
                    
                    // case 'enable_review':
                    //     $review_id = (int)($_POST['review_id'] ?? 0);
                    //     $stmt = $pdo->prepare("UPDATE reviews SET is_disabled = 0 WHERE id = ?");
                    //     $stmt->execute([$review_id]);
                    //     $success = 'Review enabled successfully.';
                    //     break;

                    // ── Users ──────────────────────────────────────────────
                    case 'ban_user':
                        $pdo->prepare("UPDATE users SET is_banned = 1 WHERE id = ?")->execute([$target_user_id]);
                        $success = 'User banned successfully.';
                        break;
                    case 'unban_user':
                        $pdo->prepare("UPDATE users SET is_banned = 0 WHERE id = ?")->execute([$target_user_id]);
                        $success = 'User unbanned successfully.';
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
                        $success = 'User and all related data deleted.';
                        break;
 
                    // ── Reviews ────────────────────────────────────────────
                    case 'disable_review':
                        $pdo->prepare("UPDATE reviews SET is_disabled = 1 WHERE id = ?")->execute([$target_review_id]);
                        $success = 'Review disabled.';
                        break;
                    case 'enable_review':
                        $pdo->prepare("UPDATE reviews SET is_disabled = 0 WHERE id = ?")->execute([$target_review_id]);
                        $success = 'Review enabled.';
                        break;
 
                    // ── FAQ ────────────────────────────────────────────────
                    case 'faq_add':
                        $cat_id   = (int)($_POST['category_id'] ?? 0);
                        $question = trim($_POST['question'] ?? '');
                        $answer   = trim($_POST['answer']   ?? '');
                        if ($cat_id && $question && $answer) {
                            $max = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM faq WHERE category_id = ?");
                            $max->execute([$cat_id]);
                            $sort = $max->fetchColumn();
                            $pdo->prepare("INSERT INTO faq (category_id, question, answer, sort_order) VALUES (?,?,?,?)")
                                ->execute([$cat_id, $question, $answer, $sort]);
                            $success = 'FAQ question added.';
                        } else { $error = 'All fields are required.'; }
                        break;
 
                    case 'faq_edit':
                        $faq_id   = (int)($_POST['faq_id']   ?? 0);
                        $question = trim($_POST['question']  ?? '');
                        $answer   = trim($_POST['answer']    ?? '');
                        if ($faq_id && $question && $answer) {
                            $pdo->prepare("UPDATE faq SET question = ?, answer = ? WHERE id = ?")
                                ->execute([$question, $answer, $faq_id]);
                            $success = 'FAQ question updated.';
                        } else { $error = 'All fields are required.'; }
                        break;
 
                    case 'faq_toggle':
                        $faq_id = (int)($_POST['faq_id'] ?? 0);
                        $pdo->prepare("UPDATE faq SET is_active = NOT is_active WHERE id = ?")->execute([$faq_id]);
                        $success = 'FAQ visibility toggled.';
                        break;
 
                    case 'faq_delete':
                        $faq_id = (int)($_POST['faq_id'] ?? 0);
                        $pdo->prepare("DELETE FROM faq WHERE id = ?")->execute([$faq_id]);
                        $success = 'FAQ question deleted.';
                        break;
 
                    case 'faq_cat_add':
                        $slug  = trim($_POST['slug']  ?? '');
                        $label = trim($_POST['label'] ?? '');
                        $icon  = trim($_POST['icon']  ?? '');
                        if ($slug && $label) {
                            $max = $pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM faq_category")->fetchColumn();
                            $pdo->prepare("INSERT INTO faq_category (slug, label, icon, sort_order) VALUES (?,?,?,?)")
                                ->execute([$slug, $label, $icon, $max]);
                            $success = 'FAQ category added.';
                        } else { $error = 'Slug and label are required.'; }
                        break;
 
                    case 'faq_cat_delete':
                        $cat_id = (int)($_POST['category_id'] ?? 0);
                        $pdo->prepare("DELETE FROM faq_category WHERE id = ?")->execute([$cat_id]);
                        $success = 'Category and its questions deleted.';
                        break;
 
                    // ── CGU ────────────────────────────────────────────────
                    case 'cgu_row_add':
                        $sec_id    = (int)($_POST['section_id'] ?? 0);
                        $content   = trim($_POST['content']     ?? '');
                        $sub_label = trim($_POST['sub_label']   ?? '') ?: null;
                        if ($sec_id && $content) {
                            $max = $pdo->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM cgu_row WHERE section_id = ?");
                            $max->execute([$sec_id]);
                            $sort = $max->fetchColumn();
                            $pdo->prepare("INSERT INTO cgu_row (section_id, sub_label, content, sort_order) VALUES (?,?,?,?)")
                                ->execute([$sec_id, $sub_label, $content, $sort]);
                            $success = 'CGU row added.';
                        } else { $error = 'Section and content are required.'; }
                        break;
 
                    case 'cgu_row_edit':
                        $row_id    = (int)($_POST['row_id']   ?? 0);
                        $content   = trim($_POST['content']   ?? '');
                        $sub_label = trim($_POST['sub_label'] ?? '') ?: null;
                        if ($row_id && $content) {
                            $pdo->prepare("UPDATE cgu_row SET content = ?, sub_label = ? WHERE id = ?")
                                ->execute([$content, $sub_label, $row_id]);
                            $success = 'CGU row updated.';
                        } else { $error = 'Content is required.'; }
                        break;
 
                    case 'cgu_row_toggle':
                        $row_id = (int)($_POST['row_id'] ?? 0);
                        $pdo->prepare("UPDATE cgu_row SET is_active = NOT is_active WHERE id = ?")->execute([$row_id]);
                        $success = 'CGU row visibility toggled.';
                        break;
 
                    case 'cgu_row_delete':
                        $row_id = (int)($_POST['row_id'] ?? 0);
                        $pdo->prepare("DELETE FROM cgu_row WHERE id = ?")->execute([$row_id]);
                        $success = 'CGU row deleted.';
                        break;
 
                    case 'cgu_section_add':
                        $title      = trim($_POST['title']      ?? '');
                        $intro_text = trim($_POST['intro_text'] ?? '') ?: null;
                        if ($title) {
                            $max = $pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM cgu_section")->fetchColumn();
                            $num = $pdo->query("SELECT COALESCE(MAX(number),0)+1 FROM cgu_section")->fetchColumn();
                            $pdo->prepare("INSERT INTO cgu_section (number, title, intro_text, sort_order) VALUES (?,?,?,?)")
                                ->execute([$num, $title, $intro_text, $max]);
                            $success = 'CGU section added.';
                        } else { $error = 'Title is required.'; }
                        break;
 
                    case 'cgu_section_edit':
                        $sec_id     = (int)($_POST['section_id'] ?? 0);
                        $title      = trim($_POST['title']       ?? '');
                        $intro_text = trim($_POST['intro_text']  ?? '') ?: null;
                        if ($sec_id && $title) {
                            $pdo->prepare("UPDATE cgu_section SET title = ?, intro_text = ? WHERE id = ?")
                                ->execute([$title, $intro_text, $sec_id]);
                            $success = 'CGU section updated.';
                        } else { $error = 'Title is required.'; }
                        break;
 
                    case 'cgu_section_toggle':
                        $sec_id = (int)($_POST['section_id'] ?? 0);
                        $pdo->prepare("UPDATE cgu_section SET is_active = NOT is_active WHERE id = ?")->execute([$sec_id]);
                        $success = 'CGU section visibility toggled.';
                        break;
 
                    case 'cgu_section_delete':
                        $sec_id = (int)($_POST['section_id'] ?? 0);
                        $pdo->prepare("DELETE FROM cgu_section WHERE id = ?")->execute([$sec_id]);
                        $success = 'CGU section and its rows deleted.';
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
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0");
    $stats['total_users'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_sitter = 1");
    $stats['total_sitters'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_owner = 1");
    $stats['total_owners'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reviews WHERE is_disabled = 0");
    $stats['total_reviews'] = $stmt->fetch()['total'];
}

// Fetch users for user management
$users = [];
if ($section === 'users') {
    $stmt = $pdo->query(
        "SELECT id, username, email, is_admin, is_sitter, is_owner, created_at, is_banned 
         FROM users ORDER BY created_at DESC"
    );
    $users = $stmt->fetchAll();
}

// Fetch reviews for review management
$reviews = [];
if ($section === 'reviews') {
    $stmt = $pdo->query(
        "SELECT r.id, r.rating, r.review_text, r.created_at, r.is_disabled,
                rater.username as rater_name, rater.first_name as rater_first_name,
                rated.username as rated_name, rated.first_name as rated_first_name
         FROM reviews r
         JOIN users rater ON r.rater_user_id = rater.id
         JOIN users rated ON r.rated_user_id = rated.id
         ORDER BY r.created_at DESC"
    );
    $reviews = $stmt->fetchAll();
}

$faq_categories = [];
$faq_items      = [];
if ($section === 'faq') {
    $faq_categories = $pdo->query("SELECT * FROM faq_category ORDER BY sort_order")->fetchAll();
    $faq_items      = $pdo->query("SELECT f.*, c.label AS cat_label FROM faq f JOIN faq_category c ON f.category_id = c.id ORDER BY f.category_id, f.sort_order")->fetchAll();
}

$cgu_sections = [];
$cgu_rows     = [];
if ($section === 'cgu') {
    $cgu_sections = $pdo->query("SELECT * FROM cgu_section ORDER BY sort_order")->fetchAll();
    $cgu_rows     = $pdo->query("SELECT * FROM cgu_row ORDER BY section_id, sort_order")->fetchAll();
}
 

$csrf_token = generateCsrfToken();
function escapeAndWrap($text, $max = 100) {
    $text = $text ?? '';
    
    // 1. Strip HTML tags if you want pure plain text
    $plain = strip_tags($text);
    
    // 2. Wrap using a plain newline character (\n) so character counts stay accurate
    $wrapped = wordwrap($plain, $max, "\n", true);
    
    // 3. Escape the text for security
    $escaped = escapeOutput($wrapped);
    
    // 4. Convert those plain newlines into HTML <br> tags
    return nl2br($escaped);
}

$error = $error ?? '';
$success = $success ?? '';

$pageTitle = "Admin Dashboard | PetSitter's Market";
require_once 'includes/header.php'; 
?>
<link rel="stylesheet" href="css/admin-dashboard.css">

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

        <!--Main Content-->
        <div class="admin-content">
            <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo escapeOutput($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo escapeOutput($success); ?></div>
            <?php endif; ?>

            <!--OVERVIEW SECTION-->
            <?php if ($section === 'overview'): ?>
                <h1 class="title-primary admin-title">Dashboard Overview</h1>
                <p class="admin-subtitle">Welcome to the admin panel. Here's an overview of your platform.</p>
                
                <div class="stats-grid">
                    <div class="card stat-card" style="border-bottom-color: var(--clr-brand);">
                        <h3><?php echo $stats['total_users']; ?></h3>
                        <p>Total Users</p>
                    </div>
                    <div class="card stat-card" style="border-bottom-color: var(--clr-cta);">
                        <h3><?php echo $stats['total_sitters']; ?></h3>
                        <p>Pet Sitters</p>
                    </div>
                    <div class="card stat-card" style="border-bottom-color: var(--clr-cta);">
                        <h3><?php echo $stats['total_owners']; ?></h3>
                        <p>Pet Owners</p>
                    </div>
                    <div class="card stat-card" style="border-bottom-color: var(--clr-primary);">
                        <h3><?php echo $stats['total_reviews']; ?></h3>
                        <p>Total Reviews</p>
                    </div>
                </div>

                <!-- <h2 style="margin-top: 2rem;">Quick Actions</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="?section=users" style="padding: 1rem; background: #eec170; text-decoration: none; border-radius: 8px; text-align: center; color: #333; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f2a65a'" onmouseout="this.style.backgroundColor='#eec170'">
                        <i class="fas fa-user-shield"></i><br> Manage Users
                    </a>
                    <a href="?section=reviews" style="padding: 1rem; background: #eec170; text-decoration: none; border-radius: 8px; text-align: center; color: #333; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f2a65a'" onmouseout="this.style.backgroundColor='#eec170'">
                        <i class="fas fa-comments"></i><br> Review Management
                    </a>
                    <a href="?section=posts" style="padding: 1rem; background: #eec170; text-decoration: none; border-radius: 8px; text-align: center; color: #333; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f2a65a'" onmouseout="this.style.backgroundColor='#eec170'">
                        <i class="fas fa-file-alt"></i><br> Moderate Posts
                    </a>
                </div> -->

            <!--USER MANAGEMENT SECTION-->
            <?php elseif ($section === 'users'): ?>
                <h1 class="title-primary admin-title">User Management</h1>
                <div class="card" style="margin-top: 2rem; overflow-x: auto; padding: 0;">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Admin</th>
                                <th>Sitter</th>
                                <th>Owner</th> 
                                <th>Joined</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo $u['id']; ?></td>
                                    <td><?php echo escapeOutput($u['username']); ?></td>
                                    <td><?php echo escapeOutput($u['email']); ?></td>
                                    <td>
                                        <?php if (isset($u['is_admin']) && $u['is_admin']): ?>
                                            <span class="badge badge-admin">Yes</span>
                                        <?php else: ?>
                                            <span class="text-disabled">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($u['is_sitter']) && $u['is_sitter']): ?>
                                            <span class="badge badge-sitter">Yes</span>
                                        <?php else: ?>
                                            <span class="text-disabled">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($u['is_owner']) && $u['is_owner']): ?>
                                            <span class="badge badge-owner">Yes</span>
                                        <?php else: ?>
                                            <span class="text-disabled">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <?php if ($u['is_banned']): ?>
                                            <span class="badge badge-banned">Banned</span>
                                        <?php else: ?>
                                            <span class="text-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                            <?php if ($u['is_banned']): ?>
                                                <input type="hidden" name="admin_action" value="unban_user">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="btn-small btn-success">Unban</button>
                                            <?php else: ?>
                                                <input type="hidden" name="admin_action" value="ban_user">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="btn-small btn-warning">Ban</button>
                                            <?php endif; ?>
                                        </form>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                            <input type="hidden" name="admin_action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn-small btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <!--REVIEWS SECTION-->
            <?php elseif ($section === 'reviews'): ?>
                <h1 class="title-primary admin-title">Review Management</h1>
                <p class="admin-subtitle">Monitor and manage platform reviews. Disable inappropriate content.</p>

                <div class="card" style="margin-top: 2rem; overflow-x: auto; padding: 0;">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>From</th>
                                <th>About</th>
                                <th>Rating</th>
                                <th>Review</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $rev): ?>
                                <tr>
                                    <td><?php echo $rev['id']; ?></td>
                                    <td><?php echo escapeOutput($rev['rater_first_name'] ?: $rev['rater_name']); ?></td>
                                    <td><?php echo escapeOutput($rev['rated_first_name'] ?: $rev['rated_name']); ?></td>
                                    <td>
                                        <span class="text-orange">
                                            <?php echo str_repeat('★', $rev['rating']); ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 200px; word-break: break-word;">
                                        <?php echo escapeAndWRAP($rev['review_text'] ?? '', 100); ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($rev['created_at'])); ?></td>
                                    <td>
                                        <?php if ($rev['is_disabled']): ?>
                                            <span class="badge badge-disabled">Disabled</span>
                                        <?php else: ?>
                                            <span class="text-success">Visible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                            <?php if ($rev['is_disabled']): ?>
                                                <input type="hidden" name="admin_action" value="enable_review">
                                                <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                                                <button type="submit" class="btn-small btn-success">Enable</button>
                                            <?php else: ?>
                                                <input type="hidden" name="admin_action" value="disable_review">
                                                <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                                                <button type="submit" class="btn-small btn-warning">Disable</button>
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
                <div class="add-panel">
                    <h3><i class="fas fa-folder-plus" style="color:var(--clr-brand);"></i> Add Category</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden" name="admin_action" value="faq_cat_add">
                        <div class="form-row">
                            <input class="f-grow" name="slug"  placeholder="slug (e.g. safety)" required>
                            <input class="f-grow" name="label" placeholder="Label (e.g. Safety & Security)" required>
                            <input style="width:150px;" name="icon" placeholder="FA icon (e.g. fa-shield-alt)">
                            <button class="btn-sm btn-primary-sm" type="submit">Add Category</button>
                        </div>
                    </form>
                </div>

                <!-- Add Question -->
                <div class="add-panel">
                    <h3><i class="fas fa-plus-circle" style="color:var(--clr-brand);"></i> Add Question</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden" name="admin_action" value="faq_add">
                        <div class="form-row">
                            <select name="category_id" required style="width:200px;">
                                <option value="">— Select category —</option>
                                <?php foreach ($faq_categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo escapeOutput($cat['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input class="f-grow" name="question" placeholder="Question" required>
                        </div>
                        <div class="form-row">
                            <textarea class="f-grow" name="answer" placeholder="Answer" required></textarea>
                            <button class="btn-sm btn-primary-sm" type="submit" style="align-self:flex-end;">Add Question</button>
                        </div>
                    </form>
                </div>

                <!-- Questions grouped by category -->
                <?php foreach ($faq_categories as $cat):
                    $cat_questions = array_filter($faq_items, fn($q) => $q['category_id'] == $cat['id']);
                ?>
                <div class="faq-cat-block">
                    <div class="faq-cat-label">
                        <span><i class="fas <?php echo escapeOutput($cat['icon']); ?>"></i> <?php echo escapeOutput($cat['label']); ?> <small>(<?php echo count($cat_questions); ?> questions)</small></span>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this category and ALL its questions?')">
                            <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                            <input type="hidden" name="admin_action" value="faq_cat_delete">
                            <input type="hidden" name="category_id"  value="<?php echo $cat['id']; ?>">
                            <button class="btn-sm btn-danger" type="submit">Delete Category</button>
                        </form>
                    </div>
                    <div class="adm-table-wrap" style="border-radius:0 0 8px 8px;">
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
                                <td style="text-align:right; white-space:nowrap;">
                                    <!-- Toggle -->
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                        <input type="hidden" name="admin_action" value="faq_toggle">
                                        <input type="hidden" name="faq_id"       value="<?php echo $q['id']; ?>">
                                        <button class="btn-sm <?php echo $q['is_active']?'btn-warn':'btn-success'; ?>"><?php echo $q['is_active']?'Hide':'Show'; ?></button>
                                    </form>
                                    <!-- Edit -->
                                    <button class="btn-sm btn-neutral"
                                        onclick="openFaqEdit(<?php echo $q['id']; ?>, <?php echo htmlspecialchars(json_encode($q['question'])); ?>, <?php echo htmlspecialchars(json_encode($q['answer'])); ?>)">Edit</button>
                                    <!-- Delete -->
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this question?')">
                                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                        <input type="hidden" name="admin_action" value="faq_delete">
                                        <input type="hidden" name="faq_id"       value="<?php echo $q['id']; ?>">
                                        <button class="btn-sm btn-danger">Delete</button>
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

                <!-- Add Section -->
                <div class="add-panel">
                    <h3><i class="fas fa-plus-circle" style="color:var(--clr-brand);"></i> Add Section</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden" name="admin_action" value="cgu_section_add">
                        <div class="form-row">
                            <input class="f-grow" name="title" placeholder="Section title" required>
                        </div>
                        <div class="form-row">
                            <textarea class="f-grow" name="intro_text" placeholder="Intro paragraph (optional)"></textarea>
                            <button class="btn-sm btn-primary-sm" type="submit" style="align-self:flex-end;">Add Section</button>
                        </div>
                    </form>
                </div>

                <!-- Sections -->
                <?php
                // Group rows by section
                $rows_by_section = [];
                foreach ($cgu_rows as $row) {
                    $rows_by_section[$row['section_id']][] = $row;
                }
                foreach ($cgu_sections as $sec):
                $sec_rows = $rows_by_section[$sec['id']] ?? [];
                ?>
                <div class="cgu-sec-block">
                    <div class="cgu-sec-header">
                        <span class="cgu-sec-num"><?php echo $sec['number']; ?></span>
                        <div>
                            <div class="cgu-sec-title"><?php echo escapeOutput($sec['title']); ?></div>
                            <?php if ($sec['intro_text']): ?>
                            <div class="cgu-sec-intro"><?php echo escapeAndWrap($sec['intro_text'], 100); ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex; gap:.4rem; flex-shrink:0;">
                            <button class="btn-sm btn-neutral"
                                onclick="openSecEdit(<?php echo $sec['id']; ?>, <?php echo htmlspecialchars(json_encode($sec['title'])); ?>, <?php echo htmlspecialchars(json_encode($sec['intro_text'] ?? '')); ?>)">Edit</button>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                <input type="hidden" name="admin_action" value="cgu_section_toggle">
                                <input type="hidden" name="section_id"   value="<?php echo $sec['id']; ?>">
                                <button class="btn-sm <?php echo $sec['is_active']?'btn-warn':'btn-success'; ?>"><?php echo $sec['is_active']?'Hide':'Show'; ?></button>
                            </form>
                            <form method="POST" class="inline-form" onsubmit="return confirm('Delete this section and ALL its rows?')">
                                <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                <input type="hidden" name="admin_action" value="cgu_section_delete">
                                <input type="hidden" name="section_id"   value="<?php echo $sec['id']; ?>">
                                <button class="btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>

                    <div class="adm-table-wrap" style="border-radius:0 0 8px 8px; border-top:none;">
                        <table class="adm-table">
                            <thead><tr>
                                <th>ID</th><th>Sub-label</th><th>Content</th><th>Status</th><th style="text-align:right;">Actions</th>
                            </tr></thead>
                            <tbody>
                            <?php if (empty($sec_rows)): ?>
                                <tr><td colspan="5" class="text-center-small">No rows yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($sec_rows as $row): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><span style="font-size:.8rem; color:var(--clr-brand); font-weight:600;"><?php echo $row['sub_label'] ? escapeOutput($row['sub_label']) : '—'; ?></span></td>
                                <td><span class="wrap"><?php echo escapeAndWrap($row['content'], 100); ?></span></td>
                                <td><?php echo $row['is_active'] ? '<span class="badge badge-active">Active</span>' : '<span class="badge badge-inactive">Hidden</span>'; ?></td>
                                <td style="text-align:right; white-space:nowrap;">
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                        <input type="hidden" name="admin_action" value="cgu_row_toggle">
                                        <input type="hidden" name="row_id"       value="<?php echo $row['id']; ?>">
                                        <button class="btn-sm <?php echo $row['is_active']?'btn-warn':'btn-success'; ?>"><?php echo $row['is_active']?'Hide':'Show'; ?></button>
                                    </form>
                                    <button class="btn-sm btn-neutral"
                                        onclick="openRowEdit(<?php echo $row['id']; ?>, <?php echo htmlspecialchars(json_encode($row['sub_label'] ?? '')); ?>, <?php echo htmlspecialchars(json_encode($row['content'])); ?>)">Edit</button>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this row?')">
                                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                        <input type="hidden" name="admin_action" value="cgu_row_delete">
                                        <input type="hidden" name="row_id"       value="<?php echo $row['id']; ?>">
                                        <button class="btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <!-- Add row inline -->
                            <tr style="background:rgba(240,160,96,.05);">
                                <td colspan="5" style="padding:.75rem 1.25rem;">
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                        <input type="hidden" name="admin_action" value="cgu_row_add">
                                        <input type="hidden" name="section_id"   value="<?php echo $sec['id']; ?>">
                                        <div class="form-row">
                                            <input style="width:160px;" name="sub_label" placeholder="Sub-label (optional)">
                                            <input class="f-grow" name="content" placeholder="Row content" required>
                                            <button class="btn-sm btn-primary-sm" type="submit">+ Add Row</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>

            <!-- POSTS SECTION -->
            <?php elseif ($section === 'posts'): ?>
                <h1>Post Moderation</h1>
                <p style="color: #666;">Post moderation section coming soon.</p>

            <!-- REPORTS SECTION -->
            <?php elseif ($section === 'reports'): ?>
                <h1>User Reports</h1>
                <p style="color: #666;">Reports and flagged content coming soon.</p>

            <!-- SETTINGS SECTION -->
            <?php elseif ($section === 'settings'): ?>
                <h1>Admin Settings</h1>
                <p style="color: #666;">Platform settings coming soon.</p>

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

    // CGU Section Edit Modal
    function openSecEdit(secId, title, introText) {
        const modal = `
            <div class="modal-overlay" id="secEditModal">
                <div class="modal-content">
                    <h2>Edit CGU Section</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $GLOBALS['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="admin_action" value="cgu_section_edit">
                        <input type="hidden" name="section_id" value="${secId}">
                        <div class="modal-form-group">
                            <label>Title:</label>
                            <input type="text" name="title" value="${title}" required>
                        </div>
                        <div class="modal-form-group">
                            <label>Intro Text:</label>
                            <textarea name="intro_text">${introText}</textarea>
                        </div>
                        <div class="modal-button-row">
                            <button type="button" class="btn-sm btn-neutral" onclick="document.getElementById('secEditModal').remove()">Cancel</button>
                            <button type="submit" class="btn-sm btn-primary-sm">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modal);
    }

    // CGU Row Edit Modal
    function openRowEdit(rowId, subLabel, content) {
        const modal = `
            <div class="modal-overlay" id="rowEditModal">
                <div class="modal-content">
                    <h2>Edit CGU Row</h2>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $GLOBALS['csrf_token'] ?? ''; ?>">
                        <input type="hidden" name="admin_action" value="cgu_row_edit">
                        <input type="hidden" name="row_id" value="${rowId}">
                        <div class="modal-form-group">
                            <label>Sub-label (optional):</label>
                            <input type="text" name="sub_label" value="${subLabel}">
                        </div>
                        <div class="modal-form-group">
                            <label>Content:</label>
                            <textarea name="content" required>${content}</textarea>
                        </div>
                        <div class="modal-button-row">
                            <button type="button" class="btn-sm btn-neutral" onclick="document.getElementById('rowEditModal').remove()">Cancel</button>
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
