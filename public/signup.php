<?php
session_start();
require_once 'includes/db.php';
require_once 'auth.php';

if (isUserLoggedIn()) {
    header("Location: profile.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = sanitizeInput($_POST['password'] ?? '');
    $confirm_password = sanitizeInput($_POST['confirm_password'] ?? '');
    $user_type_raw = sanitizeInput($_POST['user_type'] ?? '');
    $terms_accepted = isset($_POST['terms-conditions']);

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($user_type_raw)) {
        $error = 'All fields are required.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (!validatePassword($password)) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!$terms_accepted) {
        $error = 'You must accept the Terms of Service and Privacy Policy.';
    } elseif ($user_type_raw !== 'pet-owner' && $user_type_raw !== 'pet-sitter') {
        $error = 'Invalid user type.';
    } else {
        // Vérification email (avec PDO)
        $existingUser = getUserByEmail($pdo, $email);
        
        if ($existingUser) {
            $error = 'Email address already registered.';
        } else {
            // Vérification pseudo (avec PDO)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username already taken.';
            } else {
                // TRADUCTION POUR LA BASE DE DONNÉES (Le pont vital)
                $role = ($user_type_raw === 'pet-sitter') ? 'sitter' : 'owner';
                
                $hashed_password = hashPassword($password);
                
                // Insertion sécurisée PDO
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                
                if ($stmt->execute([$username, $email, $hashed_password, $role])) {
                    $success = 'Account created successfully! Please <a href="login.php" style="font-weight:bold; text-decoration:underline;">log in</a>.';
                } else {
                    $error = 'Error creating account. Please try again.';
                }
            }
        }
    }
}

$pageTitle = "Sign up | PetSitter's Market";
require_once 'includes/header.php';
?>

<main id="main-content" class="auth-layout">
    <div class="card auth-container">
        <h1 class="title-primary">Create Your Account</h1>
        <p class="text-subtitle">Join our community of pet lovers</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" action="signup.php" method="post">
            <p style="font-weight: 600; color: var(--clr-text-title); font-size: 0.95rem; margin-bottom: 0.5rem;">I am a:</p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <input type="hidden" name="user_type" id="user_type_value" value="">
                
                <button type="button" class="btn card text-center" style="padding: 1rem; cursor: pointer; border: 2px solid transparent;" onclick="selectUserType('pet-owner', this)">
                    <span style="font-size: 1.5rem;">❤</span>
                    <strong style="display: block; margin: 0.5rem 0;">Pet Owner</strong>
                    <small style="opacity: 0.8;">Find trusted sitters</small>
                </button>
                
                <button type="button" class="btn card text-center" style="padding: 1rem; cursor: pointer; border: 2px solid transparent;" onclick="selectUserType('pet-sitter', this)">
                    <span style="font-size: 1.5rem;">🤝</span>
                    <strong style="display: block; margin: 0.5rem 0;">Pet Sitter</strong>
                    <small style="opacity: 0.8;">Earn caring for pets</small>
                </button>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Choose a username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Create a password (min 8 chars)" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
            </div>

            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                <input type="checkbox" name="terms-conditions" id="terms-conditions" required>
                <label for="terms-conditions" style="font-size: 0.85rem;">I agree to the Terms of Service and Privacy Policy</label>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">Create Account</button>
            
            <p style="text-align: center; margin-top: 1.5rem;">
                Already have an account? <a href="login.php" style="color: var(--clr-brand); font-weight: bold;">Sign in</a>
            </p>
        </form>
    </div>
</main>

<script>
// Le script de Romain, adapté pour ajouter une bordure colorée au bouton sélectionné
function selectUserType(type, button) {
    document.getElementById('user_type_value').value = type;
    const buttons = button.parentElement.querySelectorAll('button');
    buttons.forEach(btn => btn.style.borderColor = 'transparent');
    button.style.borderColor = 'var(--clr-brand)';
}
</script>

<?php require_once 'includes/footer.php'; ?>
