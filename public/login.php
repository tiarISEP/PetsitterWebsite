<?php
session_start();
require_once 'includes/db.php';
require_once 'auth.php';

// Redirection si déjà connecté
if (isUserLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// 1. Génération du jeton CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

// 2. Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Vérification stricte du jeton CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Erreur de sécurité (CSRF). Veuillez rafraîchir la page et réessayer.";
    } else {
        $email = sanitizeInput($_POST['email'] ?? '');
        $password = sanitizeInput($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Utilisation stricte de PDO ($pdo)
            $user = getUserByEmail($pdo, $email);
            
            if ($user && verifyPassword($password, $user['password'])) {
                // Régénération de l'ID de session pour contrer le vol de session (Session Fixation)
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // On renouvelle le token CSRF après le login
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

$pageTitle = "Login | PetSitter's Market";
require_once 'includes/header.php';
?>

<main id="main-content" class="auth-layout">
    <div class="card auth-container">
        <h1 class="title-primary">Welcome Back</h1>
        <p class="text-subtitle">Sign in to your Petsitter's Market account</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" action="login.php" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email address" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper" style="position: relative;">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required style="width: 100%;">
                    <button class="password-toggle" type="button" aria-label="Toggle password visibility" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; font-size: 0.9rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="checkbox" name="remember-me" id="remember-me">
                    <label for="remember-me" style="font-weight: 500; color: var(--clr-text-title);">Remember me</label>
                </div>
                <a href="forgot-password.php" style="color: var(--clr-brand); font-weight: 600; text-decoration: none;">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Sign in</button>
            
            <p style="text-align: center; margin-top: 1.5rem;">
                Don't have an account? <a href="signup.php" style="color: var(--clr-brand); font-weight: bold;">Create one here</a>
            </p>
        </form>
    </div>
</main>

<script>
// Script de Romain nettoyé pour révéler le mot de passe
document.querySelector('.password-toggle').addEventListener('click', function() {
    const input = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
