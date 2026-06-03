<?php
$is_logged_in = false;
$display_name = '';
$avatar_url = '';
$header_user_type = '';

if (isset($_SESSION['user_id'])) {
    $is_logged_in = true;
    $header_user_type = $_SESSION['user_type'] ?? '';
    if (isset($pdo)) {
        $stmtUser = $pdo->prepare("SELECT first_name, last_name, username, avatar_url FROM users WHERE id = ?");
        $stmtUser->execute([$_SESSION['user_id']]);
        $uData = $stmtUser->fetch();
        if ($uData) {
            $display_name = trim(($uData['first_name'] ?? '') . ' ' . ($uData['last_name'] ?? '')) ?: $uData['username'];
            $avatar_url = $uData['avatar_url'];
        }
    }
    if (empty($display_name)) {
        $display_name = $_SESSION['username'] ?? 'User';
    }
}
$safeTitle = htmlspecialchars($pageTitle ?? "PetSitter's Market", ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $safeTitle; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <?php if ($is_logged_in): ?>
        <link rel="stylesheet" href="css/sitter-dashboard.css?v=<?php echo time(); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <?php if ($is_logged_in): ?>
        <!-- Unified Dashboard Header for logged-in users -->
        <header class="mock-header">
            <div class="mock-header-left" style="display: flex; align-items: center; gap: 1rem;">
                <!-- Hamburger Menu Button placed before links -->
                <button id="sidebarToggle" class="btn btn-text" style="background:none; border:none; color:#555; font-size:1.4rem; cursor:pointer; padding:0; margin-right: 0.5rem;" title="Menu">
                    <i class="fas fa-bars"></i>
                </button>
                
                <ul class="mock-header-nav">
                    <?php if ($header_user_type === 'pet-sitter'): ?>
                        <li><a href="searchupdate.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'searchupdate.php') ? 'active' : ''; ?>">Find Jobs</a></li>
                        <li><a href="my_applications.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'my_applications.php') ? 'active' : ''; ?>">My Applications</a></li>
                        <li><a href="messages.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'messages.php') ? 'active' : ''; ?>">Messages</a></li>
                        <li><a href="profile.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">Profile</a></li>
                    <?php elseif ($header_user_type === 'pet-owner'): ?>
                        <li><a href="my_pets.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'my_pets.php') ? 'active' : ''; ?>">Mes animaux</a></li>
                        <li><a href="my_ads.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'my_ads.php') ? 'active' : ''; ?>">Mes annonces</a></li>
                        <li><a href="messages.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'messages.php') ? 'active' : ''; ?>">Messages</a></li>
                        <li><a href="profile.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">Profil</a></li>
                    <?php elseif ($header_user_type === 'admin'): ?>
                        <li><a href="admin_dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' && empty($_GET['section'])) ? 'active' : ''; ?>">Vue d'ensemble</a></li>
                        <li><a href="admin_dashboard.php?section=users" class="<?php echo (isset($_GET['section']) && $_GET['section'] == 'users') ? 'active' : ''; ?>">Utilisateurs</a></li>
                        <li><a href="admin_dashboard.php?section=ads" class="<?php echo (isset($_GET['section']) && $_GET['section'] == 'ads') ? 'active' : ''; ?>">Annonces</a></li>
                        <li><a href="admin_dashboard.php?section=messages" class="<?php echo (isset($_GET['section']) && $_GET['section'] == 'messages') ? 'active' : ''; ?>">Support</a></li>
                    <?php elseif ($header_user_type === 'super-admin'): ?>
                        <li><a href="super_admin_dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'super_admin_dashboard.php') ? 'active' : ''; ?>">Super Admin</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="logo" style="margin: 0 auto;">
                <a href="index.php" class="mock-header-logo" style="justify-content: center; font-size: 1.5rem;">
                    <i class="fas fa-paw"></i> PetSitter's Market
                </a>
            </div>
            
            <div class="mock-header-right">
                <a href="profile.php" class="mock-user-profile">
                    <?php if (!empty($avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Avatar" class="mock-user-avatar">
                    <?php else: ?>
                        <div class="mock-user-avatar" style="display:flex; align-items:center; justify-content:center; background:#c09040; color:white; font-weight:bold; font-size:0.9rem;"><?php echo strtoupper(substr($display_name, 0, 2)); ?></div>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($display_name); ?></span>
                </a>
            </div>
        </header>
    <?php else: ?>
        <!-- Standard Global Header for Guests -->
        <header style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 2rem;">
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <div class="logo">
                    <a href="index.php" class="logo-link" style="margin: 0;">PetSitter's Market</a>
                </div>
            </div>
            
            <nav aria-label="Main navigation">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="searchupdate.php">Find Sitters</a></li>
                    <li><a href="FAQ.php">FAQ</a></li>
                    <li><a href="CGU.php">CGU</a></li>
                    <li><a href="ContactUs.php">Contact</a></li>
                    <li><a href="login.php" class="btn btn-text">Login</a></li>
                    <li><a href="signup.php" class="btn btn-cta">Sign Up</a></li>
                </ul>
            </nav>
        </header>
    <?php endif; ?>

<?php if($is_logged_in): ?>
<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>Dashboard</h2>
        <button class="sidebar-close" id="sidebarClose" title="Fermer"><i class="fas fa-times"></i></button>
    </div>
    <nav class="sidebar-nav">
        <!-- Common -->
        <a href="profile.php"><i class="fas fa-user"></i> Mon Profil</a>

        <!-- Admin -->
        <?php if ($header_user_type === 'admin'): ?>
            <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Vue d'ensemble</a>
            <a href="admin_dashboard.php?section=users"><i class="fas fa-users"></i> Utilisateurs</a>
            <a href="admin_dashboard.php?section=ads"><i class="fas fa-list"></i> Annonces</a>
            <a href="admin_dashboard.php?section=messages"><i class="fas fa-envelope"></i> Messages Support</a>
        <?php endif; ?>

        <!-- Super Admin -->
        <?php if ($header_user_type === 'super-admin'): ?>
            <a href="super_admin_dashboard.php"><i class="fas fa-shield-alt"></i> Super Admin Panel</a>
        <?php endif; ?>

        <!-- Pet Owner -->
        <?php if ($header_user_type === 'pet-owner'): ?>
            <a href="my_pets.php"><i class="fas fa-paw"></i> Mes animaux</a>
            <a href="my_ads.php"><i class="fas fa-bullhorn"></i> Mes annonces</a>
            <a href="PostAd.php"><i class="fas fa-plus-circle"></i> Créer une annonce</a>
            <a href="my_requests.php"><i class="fas fa-envelope-open-text"></i> Mes demandes de contact</a>
            <a href="messages.php"><i class="fas fa-comment-dots"></i> Messagerie interne</a>
            <a href="FAQ.php"><i class="fas fa-question-circle"></i> FAQ</a>
        <?php endif; ?>

        <!-- Pet Sitter -->
        <?php if ($header_user_type === 'pet-sitter'): ?>
            <a href="searchupdate.php"><i class="fas fa-search"></i> Trouver des annonces</a>
            <a href="my_applications.php"><i class="fas fa-briefcase"></i> Mes candidatures</a>
            <a href="my_requests.php"><i class="fas fa-envelope-open-text"></i> Mes demandes</a>
            <a href="messages.php"><i class="fas fa-comment-dots"></i> Messagerie interne</a>
            <a href="FAQ.php"><i class="fas fa-question-circle"></i> FAQ</a>
        <?php endif; ?>

        <!-- Common Logout -->
        <a href="logout.php" style="color: var(--clr-error-text); margin-top: 1rem; border-top: 1px solid rgba(112, 80, 48, 0.1); padding-top: 1rem;"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </nav>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('sidebarToggle');
        const closeBtn = document.getElementById('sidebarClose');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function openSidebar() {
            if(sidebar) sidebar.classList.add('active');
            if(overlay) overlay.classList.add('active');
        }

        function closeSidebar() {
            if(sidebar) sidebar.classList.remove('active');
            if(overlay) overlay.classList.remove('active');
        }

        if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);
    });
</script>
<?php endif; ?>
