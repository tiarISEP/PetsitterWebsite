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
$success = '';

// 2. Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Vérification stricte du jeton CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Erreur de sécurité (CSRF). Veuillez rafraîchir la page et réessayer.";
    } else {
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
        } elseif (!validatePassword($password)) { // Utilise ta Regex sécurisée
            $error = 'Password must be at least 8 characters, with 1 uppercase, 1 lowercase, and 1 number.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (!$terms_accepted) {
            $error = 'You must accept the Terms of Service and Privacy Policy.';
        } elseif ($user_type_raw !== 'pet-owner' && $user_type_raw !== 'pet-sitter') {
            $error = 'Invalid user type.';
        } else {
            // Vérification doublon Email (PDO)
            $existingUser = getUserByEmail($pdo, $email);
            
            if ($existingUser) {
                $error = 'Email address already registered.';
            } else {
                // Vérification doublon Username (PDO)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username already taken.';
                } else {
                    // Mapping pour la base de données
                    $role = ($user_type_raw === 'pet-sitter') ? 'sitter' : 'owner';
                    $hashed_password = hashPassword($password);
                    
                    // Insertion sécurisée PDO
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                    
                    if ($stmt->execute([$username, $email, $hashed_password, $role])) {
                        // On renouvelle le token CSRF après un succès par sécurité
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        $success = 'Account created successfully! Please <a href="login.php" style="font-weight:bold; text-decoration:underline;">log in</a>.';
                    } else {
                        $error = 'Error creating account. Please try again.';
                    }
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

        <form class="auth-form" action="signup.php" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <p style="font-weight: 600; color: var(--clr-text-title); font-size: 0.95rem; margin-bottom: 0.5rem;">I am a:</p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <input type="hidden" name="user_type" id="user_type_value" value="pet-owner">
                
                <button type="button" class="btn card text-center" style="padding: 1rem; cursor: pointer; border: 2px solid var(--clr-brand);" onclick="selectUserType('pet-owner', this)">
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
                <input type="text" id="username" name="username" placeholder="Choose a username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-wrapper" style="position: relative;">
                    <input type="password" id="password" name="password" placeholder="Create a password" required style="width: 100%;">
                    <button class="password-toggle" type="button" aria-label="Toggle password visibility" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-wrapper" style="position: relative;">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required style="width: 100%;">
                    <button class="password-toggle" type="button" aria-label="Toggle password visibility" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
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
// Script pour la sélection du type d'utilisateur
function selectUserType(type, button) {
    document.getElementById('user_type_value').value = type;
    const buttons = button.parentElement.querySelectorAll('button');
    buttons.forEach(btn => btn.style.borderColor = 'transparent');
    button.style.borderColor = 'var(--clr-brand)';
}

// Script de Romain pour révéler les mots de passe
document.querySelectorAll('.password-toggle').forEach(btn => {
    btn.addEventListener('click', function() {
        const wrapper = this.closest('.password-wrapper');
        const input = wrapper.querySelector('input');
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
