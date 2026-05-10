<?php
session_start(); // Toujours en haut !
require_once 'includes/db.php';
require_once 'auth.php';

// Check if already logged in
if (isUserLoggedIn()) {
    header("Location: profile.php"); // On le renvoie sur profile plutôt que dashboard qui n'existe pas proprement
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = sanitizeInput($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Changement ici : on passe $pdo au lieu de $conn
        $user = getUserByEmail($pdo, $email);
        
        if ($user && verifyPassword($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = $user['user_type'];
            
            header("Location: profile.php");
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

// --- AFFICHAGE DE LA PAGE ---
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

        <form class="auth-form" action="login.php" method="post">
            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email address" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Sign in</button>
            
            <p style="text-align: center; margin-top: 1.5rem;">
                Don't have an account? <a href="signup.php" style="color: var(--clr-brand); font-weight: bold;">Create one here</a>
            </p>
        </form>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
