<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

// Check if user is admin
redirectToLogin();
$user = getUserById($pdo, $_SESSION['user_id']);

if (!$user || !isset($user['is_super_admin']) || $user['is_super_admin'] != 1) {
    header("Location: dashboard.php");
    exit();
}

// Determine current section and search terms
$section = $_GET['section'] ?? 'overview';
$search  = trim($_GET['search'] ?? '');
$search_wildcard = "%{$search}%";

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf_token)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $admin_action = $_POST['admin_action'] ?? '';
        $target_user_id = (int)($_POST['user_id'] ?? 0);
        $target_review_id = (int)($_POST['review_id'] ?? 0);

        // PROTECTION: Admin cannot target themselves for destructive actions
        if ($target_user_id && $target_user_id === $_SESSION['user_id']) {
            $error = "Security Error: You cannot ban, delete, or modify your own admin account.";
        } else {
            try {
                switch ($admin_action) {

                    // ── User / Admin Creation ──────────────────────────────
                    case 'create_user':
                    case 'create_admin':
                        $username   = trim($_POST['username'] ?? '');
                        $first_name = trim($_POST['first_name'] ?? '');
                        $last_name  = trim($_POST['last_name'] ?? '');
                        $email      = trim($_POST['email'] ?? '');
                        $password   = $_POST['password'] ?? '';
                        $user_type  = $_POST['user_type'] ?? 'owner'; 

                        if ($username && $first_name && $last_name && $email && $password) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                            $stmt->execute([$username, $email]);
                            if ($stmt->fetchColumn() > 0) {
                                $error = 'Username or email already exists.';
                                break;
                            }

                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            
                            $is_admin  = ($admin_action === 'create_admin') ? 1 : 0;
                            $is_sitter = ($user_type === 'sitter' || $user_type === 'pet-sitter') ? 1 : 0;
                            $is_owner  = ($user_type === 'owner' || $user_type === 'pet-owner') ? 1 : 0;
                            
                            $stmt = $pdo->prepare(
                                "INSERT INTO users (username, first_name, last_name, email, password, user_type, is_admin, is_sitter, is_owner) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                            );
                            $stmt->execute([$username, $first_name, $last_name, $email, $hashed_password, $user_type, $is_admin, $is_sitter, $is_owner]);
                            
                            $account_label = $is_admin ? 'Admin' : 'User';
                            $success = "{$account_label} account created successfully for '" . escapeOutput($username) . "' with visible password: <strong>" . escapeOutput($password) . "</strong>";
                        } else {
                            $error = 'All fields are required to create an account.';
                        }
                        break;

                    // ── Users ──────────────────────────────────────────────
                    case 'ban_user':
                        $pdo->prepare("UPDATE users SET is_banned = 1 WHERE id = ?")->execute([$target_user_id]);
                        $success = 'User banned successfully.';
                        break;
                        
                    case 'unban_user':
                        $pdo->prepare("UPDATE users SET is_banned = 0 WHERE id = ?")->execute([$target_user_id]);
                        $success = 'User unbanned successfully.';
                        break;

                    case 'retract_deletion_vote':
                        $stmt_check = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                        $stmt_check->execute([$target_user_id]);
                        $target_username = $stmt_check->fetchColumn();

                        if ($target_username) {
                            $stmt = $pdo->prepare("DELETE FROM admin_deletion_votes WHERE target_user_id = ? AND super_admin_id = ?");
                            $stmt->execute([$target_user_id, $_SESSION['user_id']]);
                            
                            if ($stmt->rowCount() > 0) {
                                $success = "Your deletion vote for admin '" . escapeOutput($target_username) . "' has been successfully retracted.";
                            } else {
                                $error = "You have not submitted a deletion vote for this account.";
                            }
                        } else {
                            $error = "Admin not found.";
                        }
                        break;

                    case 'delete_user':
                        $stmt_check = $pdo->prepare("SELECT is_admin, is_super_admin, username FROM users WHERE id = ?");
                        $stmt_check->execute([$target_user_id]);
                        $target_profile = $stmt_check->fetch();

                        if (!$target_profile) {
                            $error = "User not found.";
                            break;
                        }

                        if ($target_profile['is_admin'] == 1 || $target_profile['is_super_admin'] == 1) {
                            $pdo->beginTransaction();
                            
                            $pdo->prepare("INSERT IGNORE INTO admin_deletion_votes (target_user_id, super_admin_id) VALUES (?, ?)")
                                ->execute([$target_user_id, $_SESSION['user_id']]);
                            
                            $stmt_votes = $pdo->prepare("SELECT COUNT(*) FROM admin_deletion_votes WHERE target_user_id = ?");
                            $stmt_votes->execute([$target_user_id]);
                            $total_votes = $stmt_votes->fetchColumn();
                            
                            if ($total_votes >= 2) {
                                $pdo->prepare("DELETE FROM admin_deletion_votes WHERE target_user_id = ?")->execute([$target_user_id]);
                                $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$target_user_id]);
                                $pdo->prepare("DELETE FROM application WHERE User_userID = ? OR Post_postID IN (SELECT postID FROM post WHERE User_userID = ?)")->execute([$target_user_id, $target_user_id]);
                                $pdo->prepare("DELETE FROM rating WHERE Rated_userID = ? OR Rater_userID = ?")->execute([$target_user_id, $target_user_id]);
                                $pdo->prepare("DELETE FROM post_has_animal WHERE Post_postID IN (SELECT postID FROM post WHERE User_userID = ?)")->execute([$target_user_id]);
                                $pdo->prepare("DELETE FROM post WHERE User_userID = ?")->execute([$target_user_id]);
                                $pdo->prepare("DELETE FROM animal WHERE User_userID = ?")->execute([$target_user_id]);
                                $pdo->prepare("DELETE FROM reviews WHERE rater_user_id = ? OR rated_user_id = ?")->execute([$target_user_id, $target_user_id]);
                                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_user_id]);
                                
                                $pdo->commit();
                                $success = "Admin account '" . escapeOutput($target_profile['username']) . "' has been permanently deleted after receiving 2 super admin approvals.";
                            } else {
                                $pdo->commit();
                                $success = "Deletion request registered for admin '" . escapeOutput($target_profile['username']) . "'. Currently at 1/2 required Super Admin votes.";
                            }
                        } else {
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
                        }
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
                    case 'cgu_version_add':
                        $version_number = trim($_POST['version_number'] ?? '');
                        $section_title  = trim($_POST['section_title']  ?? '');
                        $content        = trim($_POST['content']        ?? '');
                        $effective_from = trim($_POST['effective_from'] ?? '');
                        if ($version_number && $section_title && $content && $effective_from) {
                            $stmt = $pdo->prepare("INSERT INTO cgu_versions (version_number, section_title, content, effective_from, is_active) VALUES (?, ?, ?, ?, 0)");
                            $stmt->execute([$version_number, $section_title, $content, $effective_from]);
                            $success = 'CGU version added.';
                        } else { 
                            $error = 'Version number, section title, content, and effective date are required.'; 
                        }
                        break;
 
                    case 'cgu_version_edit':
                        $cgu_id         = (int)($_POST['cgu_id']        ?? 0);
                        $section_title  = trim($_POST['section_title']  ?? '');
                        $content        = trim($_POST['content']        ?? '');
                        $effective_from = trim($_POST['effective_from'] ?? '');
                        if ($cgu_id && $section_title && $content && $effective_from) {
                            $stmt = $pdo->prepare("UPDATE cgu_versions SET section_title = ?, content = ?, effective_from = ? WHERE id = ?");
                            $stmt->execute([$section_title, $content, $effective_from, $cgu_id]);
                            $success = 'CGU version updated.';
                        } else { 
                            $error = 'All fields are required.'; 
                        }
                        break;
 
                    case 'cgu_version_activate':
                        $cgu_id = (int)($_POST['cgu_id'] ?? 0);
                        if ($cgu_id) {
                            $pdo->beginTransaction();
                            $pdo->prepare("UPDATE cgu_versions SET is_active = 0")->execute();
                            $pdo->prepare("UPDATE cgu_versions SET is_active = 1 WHERE id = ?")->execute([$cgu_id]);
                            $pdo->commit();
                            $success = 'CGU version activated.';
                        }
                        break;
 
                    case 'cgu_version_delete':
                        $cgu_id = (int)($_POST['cgu_id'] ?? 0);
                        $stmt = $pdo->prepare("SELECT is_active FROM cgu_versions WHERE id = ?");
                        $stmt->execute([$cgu_id]);
                        $cgu = $stmt->fetch();
                        if ($cgu && $cgu['is_active']) {
                            $error = 'Cannot delete the active CGU version. Activate another version first.';
                        } else {
                            $pdo->prepare("DELETE FROM cgu_versions WHERE id = ?")->execute([$cgu_id]);
                            $success = 'CGU version deleted.';
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
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0");
    $stats['total_users'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_sitter = 1");
    $stats['total_sitters'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_owner = 1");
    $stats['total_owners'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM reviews WHERE is_disabled = 0");
    $stats['total_reviews'] = $stmt->fetch()['total'];
}

// Fetch users for user management (Filtered via Search Bar input structural definition)
$users = [];
if ($section === 'user_management') {
    $sql = "SELECT u.id, u.username, u.email, u.is_admin, u.is_sitter, u.is_owner, u.created_at, u.is_banned,
                   (SELECT COUNT(*) FROM admin_deletion_votes WHERE target_user_id = u.id) as pending_votes
            FROM users u WHERE u.is_admin = 0";
    if ($search !== '') {
        $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $stmt = $pdo->prepare($sql . " ORDER BY u.created_at DESC");
        $stmt->execute([$search_wildcard, $search_wildcard, $search_wildcard, $search_wildcard]);
    } else {
        $stmt = $pdo->prepare($sql . " ORDER BY u.created_at DESC");
        $stmt->execute();
    }
    $users = $stmt->fetchAll();
}

// Fetch admins (Tracks sub-select votes and checks if current super admin logged in has personally voted)
$admins = [];
if ($section === 'admin_management') {
    $sql = "SELECT u.id, u.username, u.email, u.is_admin, u.is_sitter, u.is_owner, u.created_at, u.is_banned,
                   (SELECT COUNT(*) FROM admin_deletion_votes WHERE target_user_id = u.id) as pending_votes,
                   (SELECT COUNT(*) FROM admin_deletion_votes WHERE target_user_id = u.id AND super_admin_id = ?) as caller_has_voted
            FROM users u WHERE u.is_admin = 1";
    if ($search !== '') {
        $sql .= " AND (u.username LIKE ? OR u.email LIKE ?)";
        $stmt = $pdo->prepare($sql . " ORDER BY u.created_at DESC");
        $stmt->execute([$_SESSION['user_id'], $search_wildcard, $search_wildcard]);
    } else {
        $stmt = $pdo->prepare($sql . " ORDER BY u.created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $admins = $stmt->fetchAll();
}

// Fetch reviews
$reviews = [];
if ($section === 'reviews') {
    $sql = "SELECT r.id, r.rating, r.review_text, r.created_at, r.is_disabled,
                   rater.username as rater_name, rater.first_name as rater_first_name,
                   rated.username as rated_name, rated.first_name as rated_first_name
            FROM reviews r
            JOIN users rater ON r.rater_user_id = rater.id
            JOIN users rated ON r.rated_user_id = rated.id";
    if ($search !== '') {
        $sql .= " WHERE rater.username LIKE ? OR rater.first_name LIKE ? OR rated.username LIKE ? OR rated.first_name LIKE ? OR r.review_text LIKE ?";
        $stmt = $pdo->prepare($sql . " ORDER BY r.created_at DESC");
        $stmt->execute([$search_wildcard, $search_wildcard, $search_wildcard, $search_wildcard, $search_wildcard]);
    } else {
        $stmt = $pdo->query($sql . " ORDER BY r.created_at DESC");
    }
    $reviews = $stmt->fetchAll();
}

// Fetch FAQs
$faq_categories = [];
$faq_items      = [];
if ($section === 'faq') {
    $faq_categories = $pdo->query("SELECT * FROM faq_category ORDER BY sort_order")->fetchAll();
    $faq_items      = $pdo->query("SELECT f.*, c.label AS cat_label FROM faq f JOIN faq_category c ON f.category_id = c.id ORDER BY f.category_id, f.sort_order")->fetchAll();
}

// Fetch CGUs
$cgu_versions = [];
if ($section === 'cgu') {
    $cgu_versions = $pdo->query("SELECT * FROM cgu_versions ORDER BY version_number DESC, id")->fetchAll();
}

$csrf_token = generateCsrfToken();
function escapeAndWrap($text, $max = 100) {
    $text = $text ?? '';
    $plain = strip_tags($text);
    $wrapped = wordwrap($plain, $max, "\n", true);
    $escaped = escapeOutput($wrapped);
    return nl2br($escaped);
}

$error = $error ?? '';
$success = $success ?? '';

$pageTitle = "Super Admin Dashboard | PetSitter's Market";
require_once 'includes/header.php'; 
?>
<link rel="stylesheet" href="css/admin-dashboard.css">

<main id="main-content" class="container">
    <div class="admin-container">
        <aside class="admin-sidebar">
            <h3>Super Admin Menu</h3>
            <div class="admin-menu">
                <a href="?section=overview" class="admin-menu-link <?php echo $section === 'overview' ? 'active' : ''; ?>">Overview</a>
                <a href="?section=user_management" class="admin-menu-link <?php echo $section === 'user_management' ? 'active' : ''; ?>">User Management</a>
                <a href="?section=admin_management" class="admin-menu-link <?php echo $section === 'admin_management' ? 'active' : ''; ?>">Admin Management</a>
                <a href="?section=reviews" class="admin-menu-link <?php echo $section === 'reviews' ? 'active' : ''; ?>">Reviews</a>
                <a href="?section=faq" class="admin-menu-link <?php echo $section === 'faq' ? 'active' : ''; ?>">FAQ</a>
                <a href="?section=cgu" class="admin-menu-link <?php echo $section === 'cgu' ? 'active' : ''; ?>">CGU</a>
                <a href="?section=posts" class="admin-menu-link <?php echo $section === 'posts' ? 'active' : ''; ?>">Posts</a>
                <a href="?section=reports" class="admin-menu-link <?php echo $section === 'reports' ? 'active' : ''; ?>">Reports</a>
                <a href="?section=settings" class="admin-menu-link <?php echo $section === 'settings' ? 'active' : ''; ?>">Settings</a>
            </div>
        </aside>

        <div class="admin-content">
            <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo escapeOutput($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo escapeOutput($success); ?></div>
            <?php endif; ?>

            <?php if ($section === 'overview'): ?>
                <h1 class="title-primary admin-title">Dashboard Overview</h1>
                <p class="admin-subtitle">Welcome to the super admin panel. Here's an overview of your platform.</p>
                
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

            <?php elseif ($section === 'user_management'): ?>
                <h1 class="title-primary admin-title">User Management</h1>

                <div class="add-panel">
                    <h3><i class="fas fa-user-plus" style="color:var(--clr-brand);"></i> Create New User</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden" name="admin_action" value="create_user">
                        <div class="form-row">
                            <input class="f-grow" name="username" placeholder="Username" required>
                            <input class="f-grow" name="email" type="email" placeholder="Email Address" required>
                            <input class="f-grow" name="password" type="text" placeholder="Password" required>
                        </div>
                        <div class="form-row">
                            <input class="f-grow" name="first_name" placeholder="First Name" required>
                            <input class="f-grow" name="last_name" placeholder="Last Name" required>
                            <select class="f-grow" name="user_type" required>
                                <option value="owner">Pet Owner</option>
                                <option value="sitter">Pet Sitter</option>
                            </select>
                            <button class="btn-sm btn-primary-sm" type="submit" style="align-self:flex-end;">Create User</button>
                        </div>
                    </form>
                </div>

                <!-- Functional Search Bar component -->
                <div class="search-panel" style="margin-top:2rem;">
                    <form method="GET" style="display:flex; gap:0.5rem; max-width:500px;">
                        <input type="hidden" name="section" value="user_management">
                        <input type="text" name="search" value="<?php echo escapeOutput($search); ?>" placeholder="Search username, email, or name..." style="flex-grow:1; padding:0.4rem 0.8rem; border:1px solid #ccc; border-radius:4px;">
                        <button type="submit" class="btn-sm btn-primary-sm">Search</button>
                        <?php if ($search !== ''): ?>
                            <a href="?section=user_management" class="btn-sm" style="background:#ccc; color:#333; text-decoration:none; display:flex; align-items:center; border-radius:4px; padding:0 0.8rem;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card" style="margin-top: 1rem; overflow-x: auto; padding: 0;">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Sitter</th>
                                <th>Owner</th> 
                                <th>Joined</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="8" style="text-align:center; padding:2rem; color:#888;">No users found matching requirements.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo $u['id']; ?></td>
                                    <td><?php echo escapeOutput($u['username']); ?></td>
                                    <td><?php echo escapeOutput($u['email']); ?></td>
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

            <?php elseif ($section === 'admin_management'): ?>
                <h1 class="title-primary admin-title">Admin Management</h1>

                <div class="add-panel">
                    <h3><i class="fas fa-user-shield" style="color:var(--clr-brand);"></i> Create New Admin</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden" name="admin_action" value="create_admin">
                        <div class="form-row">
                            <input class="f-grow" name="username" placeholder="Username" required>
                            <input class="f-grow" name="email" type="email" placeholder="Email Address" required>
                            <input class="f-grow" name="password" type="text" placeholder="Password" required>
                        </div>
                        <div class="form-row">
                            <input class="f-grow" name="first_name" placeholder="First Name" required>
                            <input class="f-grow" name="last_name" placeholder="Last Name" required>
                            <input type="hidden" name="user_type" value="admin">
                            <button class="btn-sm btn-primary-sm" type="submit" style="align-self:flex-end;">Create Admin</button>
                        </div>
                    </form>
                </div>

                <!-- Functional Search Bar component -->
                <div class="search-panel" style="margin-top:2rem;">
                    <form method="GET" style="display:flex; gap:0.5rem; max-width:500px;">
                        <input type="hidden" name="section" value="admin_management">
                        <input type="text" name="search" value="<?php echo escapeOutput($search); ?>" placeholder="Search administrative accounts..." style="flex-grow:1; padding:0.4rem 0.8rem; border:1px solid #ccc; border-radius:4px;">
                        <button type="submit" class="btn-sm btn-primary-sm">Search</button>
                        <?php if ($search !== ''): ?>
                            <a href="?section=admin_management" class="btn-sm" style="background:#ccc; color:#333; text-decoration:none; display:flex; align-items:center; border-radius:4px; padding:0 0.8rem;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card" style="margin-top: 1rem; overflow-x: auto; padding: 0;">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Deletion State</th>
                                <th>Joined</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($admins)): ?>
                                <tr><td colspan="7" style="text-align:center; padding:2rem; color:#888;">No admin records found.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($admins as $u): ?>
                                <tr>
                                    <td><?php echo $u['id']; ?></td>
                                    <td><?php echo escapeOutput($u['username']); ?></td>
                                    <td><?php echo escapeOutput($u['email']); ?></td>
                                    <td>
                                        <!-- Displays detailed state of deletion votes -->
                                        <?php if (!empty($u['pending_votes']) && $u['pending_votes'] > 0): ?>
                                            <span class="badge badge-banned" style="background-color:#e67e22;">
                                                Pending Deletion (<?php echo (int)$u['pending_votes']; ?>/2 Votes)
                                            </span>
                                            <?php if (!empty($u['caller_has_voted']) && $u['caller_has_voted'] > 0): ?>
                                                <br><small style="color:var(--clr-brand); font-weight:bold;">(You have voted)</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color:#7f8c8d; font-size:0.9rem;">Stable (0/2 Votes)</span>
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
                                        <div style="display:inline-flex; gap:0.35rem; justify-content:flex-end;">
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
                                            
                                            <!-- Retract Deletion Vote structural form button -->
                                            <?php if (!empty($u['caller_has_voted']) && $u['caller_has_voted'] > 0): ?>
                                                <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure you want to retract your deletion vote for this admin account?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                                    <input type="hidden" name="admin_action" value="retract_deletion_vote">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" class="btn-small" style="background:#7f8c8d; color:#fff;">Retract Vote</button>
                                                </form>
                                            <?php endif; ?>

                                            <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure? Administrative deletions require 2 Super Admin confirmations.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrf_token); ?>">
                                                <input type="hidden" name="admin_action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="btn-small btn-danger">
                                                    <?php echo (!empty($u['pending_votes']) && $u['pending_votes'] > 0) ? "Confirm Vote ({$u['pending_votes']}/2)" : "Vote Delete"; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($section === 'reviews'): ?>
                <h1 class="title-primary admin-title">Review Management</h1>
                <p class="admin-subtitle">Monitor and manage platform reviews. Disable inappropriate content.</p>

                <!-- Functional Search Bar component -->
                <div class="search-panel" style="margin-top:1.5rem; margin-bottom:1rem;">
                    <form method="GET" style="display:flex; gap:0.5rem; max-width:500px;">
                        <input type="hidden" name="section" value="reviews">
                        <input type="text" name="search" value="<?php echo escapeOutput($search); ?>" placeholder="Search review text, rater, or target..." style="flex-grow:1; padding:0.4rem 0.8rem; border:1px solid #ccc; border-radius:4px;">
                        <button type="submit" class="btn-sm btn-primary-sm">Search</button>
                        <?php if ($search !== ''): ?>
                            <a href="?section=reviews" class="btn-sm" style="background:#ccc; color:#333; text-decoration:none; display:flex; align-items:center; border-radius:4px; padding:0 0.8rem;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="card" style="margin-top: 1rem; overflow-x: auto; padding: 0;">
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
                            <?php if (empty($reviews)): ?>
                                <tr><td colspan="8" style="text-align:center; padding:2rem; color:#888;">No reviews found.</td></tr>
                            <?php endif; ?>
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
                                        <?php echo escapeAndWrap($rev['review_text'] ?? '', 100); ?>
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

            <?php elseif ($section === 'faq'): ?>

                <h1 class="title-primary admin-title">FAQ Management</h1>

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
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                        <input type="hidden" name="admin_action" value="faq_toggle">
                                        <input type="hidden" name="faq_id"       value="<?php echo $q['id']; ?>">
                                        <button class="btn-sm <?php echo $q['is_active']?'btn-warn':'btn-success'; ?>"><?php echo $q['is_active']?'Hide':'Show'; ?></button>
                                    </form>
                                    <button class="btn-sm btn-neutral"
                                        onclick="openFaqEdit(<?php echo $q['id']; ?>, <?php echo htmlspecialchars(json_encode($q['question'])); ?>, <?php echo htmlspecialchars(json_encode($q['answer'])); ?>)">Edit</button>
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

            <?php elseif ($section === 'cgu'): ?>

                <h1 class="title-primary admin-title">CGU Management</h1>

                <div class="add-panel">
                    <h3><i class="fas fa-plus-circle" style="color:var(--clr-brand);"></i> Add CGU Version</h3>
                    <form method="POST">
                        <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                        <input type="hidden" name="admin_action" value="cgu_version_add">
                        <div class="form-row">
                            <input class="f-grow" name="version_number" placeholder="Version (e.g. 1.0, 1.1)" required>
                            <input class="f-grow" name="effective_from" type="date" required>
                        </div>
                        <div class="form-row">
                            <input class="f-grow" name="section_title" placeholder="Section title" required>
                        </div>
                        <div class="form-row">
                            <textarea class="f-grow" name="content" placeholder="Section content" required style="min-height:120px;"></textarea>
                            <button class="btn-sm btn-primary-sm" type="submit" style="align-self:flex-end;">Add Version</button>
                        </div>
                    </form>
                </div>

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
                            <td style="text-align:right; white-space:nowrap;">
                                <button class="btn-sm btn-neutral"
                                    onclick="openCguEdit(<?php echo $cgu['id']; ?>, <?php echo htmlspecialchars(json_encode($cgu['section_title'])); ?>, <?php echo htmlspecialchars(json_encode($cgu['content'])); ?>, <?php echo htmlspecialchars(json_encode($cgu['effective_from'])); ?>)">Edit</button>
                                <?php if (!$cgu['is_active']): ?>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                    <input type="hidden" name="admin_action" value="cgu_version_activate">
                                    <input type="hidden" name="cgu_id"       value="<?php echo $cgu['id']; ?>">
                                    <button class="btn-sm btn-success">Activate</button>
                                </form>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Delete this CGU version?')">
                                    <input type="hidden" name="csrf_token"   value="<?php echo escapeOutput($csrf_token); ?>">
                                    <input type="hidden" name="admin_action" value="cgu_version_delete">
                                    <input type="hidden" name="cgu_id"       value="<?php echo $cgu['id']; ?>">
                                    <button class="btn-sm btn-danger">Delete</button>
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

            <?php elseif ($section === 'posts'): ?>
                <h1>Post Moderation</h1>
                <p style="color: #666;">Post moderation section coming soon.</p>

            <?php elseif ($section === 'reports'): ?>
                <h1>User Reports</h1>
                <p style="color: #666;">Reports and flagged content coming soon.</p>

            <?php elseif ($section === 'settings'): ?>
                <h1>Admin Settings</h1>
                <p style="color: #666;">Platform settings coming soon.</p>

            <?php endif; ?>
        </div>
    </div>
</main>

<script>
    function confirmAction(message) {
        return confirm(message);
    }

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