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
                    case 'ban_user':
                        $user_id = (int)($_POST['user_id'] ?? 0);
                        $stmt = $pdo->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $success = 'User banned successfully.';
                        break;
                    
                    case 'unban_user':
                        $user_id = (int)($_POST['user_id'] ?? 0);
                        $stmt = $pdo->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $success = 'User unbanned successfully.';
                        break;
                    
                    case 'delete_user':
                        $user_id = (int)($_POST['user_id'] ?? 0);
                        // Delete user's reviews first
                        $stmt = $pdo->prepare("DELETE FROM reviews WHERE rater_user_id = ? OR rated_user_id = ?");
                        $stmt->execute([$user_id, $user_id]);
                        // Delete user
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $success = 'User deleted successfully.';
                        break;
                    
                    case 'disable_review':
                        $review_id = (int)($_POST['review_id'] ?? 0);
                        $stmt = $pdo->prepare("UPDATE reviews SET is_disabled = 1 WHERE id = ?");
                        $stmt->execute([$review_id]);
                        $success = 'Review disabled successfully.';
                        break;
                    
                    case 'enable_review':
                        $review_id = (int)($_POST['review_id'] ?? 0);
                        $stmt = $pdo->prepare("UPDATE reviews SET is_disabled = 0 WHERE id = ?");
                        $stmt->execute([$review_id]);
                        $success = 'Review enabled successfully.';
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

$csrf_token = generateCsrfToken();
$error = $error ?? '';
$success = $success ?? '';

$pageTitle = "Admin Dashboard | PetSitter's Market";
require_once 'includes/header.php'; 
?>

    <!-- <style>
        .admin-container {
            display: flex;
            min-height: calc(100vh - 200px);
            width: 100%;
        }
        .admin-sidebar {
            width: 250px;
            background-color: #585123;
            color: white;
            padding: 2rem 0;
            position: sticky;
            top: 0;
            height: fit-content;
        }
        .admin-sidebar a {
            display: block;
            padding: 1rem 1.5rem;
            color: #eec170;
            text-decoration: none;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        .admin-sidebar a:hover,
        .admin-sidebar a.active {
            background-color: #4c5122;
            border-left-color: #d58337;
            color: #fff;
        }
        .admin-content {
            flex: 1;
            padding: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #d58337, #bf702f);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
        }
        .stat-card p {
            margin: 0;
            font-size: 0.9rem;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table thead {
            background-color: #585123;
            color: white;
        }
        table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        table tbody tr:hover {
            background-color: #f9f9f9;
        }
        .btn-small {
            padding: 0.4rem 0.8rem;
            margin: 0.2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        .btn-danger {
            background-color: #c7254e;
            color: white;
        }
        .btn-danger:hover {
            background-color: #a01f3a;
        }
        .btn-warning {
            background-color: #ff9800;
            color: white;
        }
        .btn-warning:hover {
            background-color: #e68900;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-sitter {
            background-color: #d58337;
            color: white;
        }
        .badge-owner {
            background-color: #4c5122;
            color: white;
        }
        .badge-admin {
            background-color: #c7254e;
            color: white;
        }
        .badge-banned {
            background-color: #c7254e;
            color: white;
        }
        .badge-disabled {
            background-color: #ffc107;
            color: #333;
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 400px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .modal-content h2 {
            margin-top: 0;
        }
        .modal-footer {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }
    </style> -->

    <!-- <header>
        <div class="logo">
            <a href="index.html" style="text-decoration: none; color: inherit;">PetSitter's Market</a>
        </div>
        <nav aria-label="Navigation principale">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="admin_dashboard.php" style="font-weight: 500; color: #772f1a;">Admin Panel</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header> -->

<main id="main-content" class="container" style="max-width: 1400px; padding: 2rem 1rem;">
    <div style="display: flex; gap: 2rem; min-height: 70vh;">
        <!--Sidebar-->
        <aside style="width: 250px; background-color: var(--white); border-radius: 8px;
        border: var(--card-border); box-shadow: var(--card-shadow); padding: 1.5rem 0; 
        align-self: flex-start; position: sticky; top: 2rem;">
            <h3 style="padding: 0 1.5rem; color: var(--clr-text-title); margin-bottom: 1rem;">Admin Menu</h3>
            
            <div style="display: flex; flex-direction: column;">
                <a href="?section=overview" style="padding: 1rem 1.5rem; text-decoration: none; font-weight: 600; color: <?php echo $section === 'overview' ? 'var(--clr-brand)' : 'var(--clr-text-main)'; ?>; border-left: 4px solid <?php echo $section === 'overview' ? 'var(--clr-brand)' : 'transparent'; ?>;">Overview</a>
                <a href="?section=users" style="padding: 1rem 1.5rem; text-decoration: none; font-weight: 600; color: <?php echo $section === 'users' ? 'var(--clr-brand)' : 'var(--clr-text-main)'; ?>; border-left: 4px solid <?php echo $section === 'users' ? 'var(--clr-brand)' : 'transparent'; ?>;">User Management</a>
                <a href="?section=reviews" style="padding: 1rem 1.5rem; text-decoration: none; font-weight: 600; color: <?php echo $section === 'reviews' ? 'var(--clr-brand)' : 'var(--clr-text-main)'; ?>; border-left: 4px solid <?php echo $section === 'reviews' ? 'var(--clr-brand)' : 'transparent'; ?>;">Reviews</a>
                </a>
                <a href="?section=posts" class="<?php echo $section === 'posts' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i> Posts
                </a>
                <a href="?section=reports" class="<?php echo $section === 'reports' ? 'active' : ''; ?>">
                    <i class="fas fa-flag"></i> Reports
                </a>
                <a href="?section=settings" class="<?php echo $section === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </div>
        </aside>

        <!--Main Content-->
        <div style="flex: 1;">
            <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo escapeOutput($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo escapeOutput($success); ?></div>
            <?php endif; ?>

            OVERVIEW SECTION
            <?php if ($section === 'overview'): ?>
                <h1 class="title-primary" style="text-align: left;">Dashboard Overview</h1>
                <p style="color: #666; margin-bottom: 2rem;">Welcome to the admin panel. Here's an overview of your platform.</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
                    <div class="card" style="text-align: center; border-bottom: 4px solid var(--clr-brand);">
                        <h3 style="font-size: 2.5rem; color: var(--clr-text-title);"><?php echo $stats['total_users']; ?></h3>
                        <p>Total Users</p>
                    </div>
                    <div class="card" style="text-align: center; border-bottom: 4px solid var(--clr-cta);">
                        <h3 style="font-size: 2.5rem; color: var(--clr-text-title);"><?php echo $stats['total_sitters']; ?></h3>
                        <p>Pet Sitters</p>
                    </div>
                    <div class="card" style="text-align: center; border-bottom: 4px solid var(--clr-cta);">
                        <h3 style="font-size: 2.5rem; color: var(--clr-text-title);"><?php echo $stats['total_owners']; ?></h3>
                        <p>Pet Owners</p>
                    </div>
                    <div class="card" style="text-align: center; border-bottom: 4px solid var(--clr-primary);">
                        <h3 style="font-size: 2.5rem; color: var(--clr-text-title);"><?php echo $stats['total_reviews']; ?></h3>
                        <p>Total Reviews</p>
                    </div>
                </div>

                <h2 style="margin-top: 2rem;">Quick Actions</h2>
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
                </div>

            <!--USER MANAGEMENT SECTION-->
            <?php elseif ($section === 'users'): ?>
                <h1 class="title-primary" style="text-align: left;">User Management</h1>
                <div class="card" style="margin-top: 2rem; overflow-x: auto; padding: 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background-color: #f3f4f6;">
                            <tr>
                                <th style="padding: 1rem; text-align: left;">ID</th>
                                <th style="padding: 1rem; text-align: left;">Username</th>
                                <th style="padding: 1rem; text-align: left;">Email</th>
                                <th style="padding: 1rem; text-align: left;">Type</th>
                                <th style="padding: 1rem; text-align: left;">Joined</th>
                                <th style="padding: 1rem; text-align: left;">Status</th>
                                <th style="padding: 1rem; text-align: right;">Actions</th>
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
                                            <span class="badge badge-admin">Admin</span>
                                        <?php else: ?>
                                            <?php if (isset($u['is_sitter']) && $u['is_sitter']): ?>
                                                <span class="badge badge-sitter">Sitter</span>
                                            <?php endif; ?>
                                            <?php if (isset($u['is_owner']) && $u['is_owner']): ?>
                                                <span class="badge badge-owner">Owner</span>
                                            <?php endif; ?>
                                            <?php if (!isset($u['is_sitter']) || (!$u['is_sitter'] && !$u['is_owner'])): ?>
                                                <span style="color: #999;">—</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                                    <td>
                                        <?php if ($u['is_banned']): ?>
                                            <span class="badge badge-banned">Banned</span>
                                        <?php else: ?>
                                            <span style="color: #28a745;">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
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
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
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

            REVIEWS SECTION
            <?php elseif ($section === 'reviews'): ?>
                <h1 class="title-primary" style="text-align: left;">Review Management</h1>
                <p style="color: #666; margin-bottom: 2rem;">Monitor and manage platform reviews. Disable inappropriate content.</p>

                <div class="card" style="margin-top: 2rem; overflow-x: auto; padding: 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead style="background-color: #f3f4f6;">
                            <tr>
                                <th style="padding: 1rem; text-align: left;">ID</th>
                                <th style="padding: 1rem; text-align: left;">From</th>
                                <th style="padding: 1rem; text-align: left;">About</th>
                                <th style="padding: 1rem; text-align: left;">Rating</th>
                                <th style="padding: 1rem; text-align: left;">Review</th>
                                <th style="padding: 1rem; text-align: left;">Date</th>
                                <th style="padding: 1rem; text-align: left;">Status</th>
                                <th style="padding: 1rem; text-align: left;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $rev): ?>
                                <tr>
                                    <td><?php echo $rev['id']; ?></td>
                                    <td><?php echo escapeOutput($rev['rater_first_name'] ?: $rev['rater_name']); ?></td>
                                    <td><?php echo escapeOutput($rev['rated_first_name'] ?: $rev['rated_name']); ?></td>
                                    <td>
                                        <span style="color: #d58337; font-size: 1.1rem;">
                                            <?php echo str_repeat('★', $rev['rating']); ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 200px; word-break: break-word;">
                                        <?php echo escapeOutput(substr($rev['review_text'] ?? '', 0, 50) . (strlen($rev['review_text'] ?? '') > 50 ? '...' : '')); ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($rev['created_at'])); ?></td>
                                    <td>
                                        <?php if ($rev['is_disabled']): ?>
                                            <span class="badge badge-disabled">Disabled</span>
                                        <?php else: ?>
                                            <span style="color: #28a745;">Visible</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
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

            POSTS SECTION
            <?php elseif ($section === 'posts'): ?>
                <h1>Post Moderation</h1>
                <p style="color: #666;">Post moderation section coming soon.</p>

            REPORTS SECTION
            <?php elseif ($section === 'reports'): ?>
                <h1>User Reports</h1>
                <p style="color: #666;">Reports and flagged content coming soon.</p>

            SETTINGS SECTION
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
</script>

<?php require_once 'includes/footer.php'; ?>
