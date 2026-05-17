<?php
require_once 'includes/db.php';
require_once 'auth.php';

// 1. Secure session start 
startSecureSession();

// Redirect if already logged in
if (isUserLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// 2. Centralize CSRF token
$csrf_token = generateCsrfToken();
$error = '';
$success = '';

// 3. Form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Verification
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Security error (CSRF). Please refresh the page and try again.";
    } else {
        $username   = trimInput($_POST['username'] ?? '');
        $first_name = trimInput($_POST['first_name'] ?? '');
        $last_name  = trimInput($_POST['last_name'] ?? '');
        $email      = trimInput($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? ''; 
        $user_type  = $_POST['user_type'] ?? 'pet-owner';

        // Role validation
        $allowed_roles = ['pet-owner', 'pet-sitter'];
        if (!in_array($user_type, $allowed_roles)) {
            $error = "Invalid role selected.";
        }
        elseif (empty($username) || empty($email) || empty($password)) {
            $error = "Username, email, and password are required.";
        } 
        elseif (!validateEmail($email)) {
            $error = "Invalid email address format.";
        } 
        elseif (!validatePassword($password)) {
            $error = "Password must be between 12 and 64 characters, including at least one uppercase letter, one lowercase letter, and one number.";
        } 
        else {
            try {
                // Check if email or username already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$email, $username]);
                
                if ($stmt->fetch()) {
                    $error = "This email or username is already taken.";
                } else {
                    // Hashing and insertion
                    $hashed_password = hashPassword($password);
                    $stmt = $pdo->prepare("INSERT INTO users (username, first_name, last_name, email, password, user_type) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $first_name, $last_name, $email, $hashed_password, $user_type]);
                    
                    // Session Fixation Defense
                    $user_id = $pdo->lastInsertId();
                    session_regenerate_id(true); 
                    
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_type'] = $user_type;
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT']; 
                    
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    header("Location: dashboard.php?welcome=1");
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Signup Error: " . $e->getMessage());
                $error = "A server error occurred. Please try again later.";
            }
        }
    }
}

$pageTitle = "Sign Up | PetSitter's Market";
require_once 'includes/header.php'; 
?>

<main id="main-content" class="auth-layout">
    <div class="card auth-container">
        <h1 class="title-primary">Create an Account</h1>
        <p class="text-subtitle">Join the PetSitter's Market community</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" action="signup.php" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="user-type-row">
                <label class="user-type-button" style="display: flex; flex-direction: row; gap: 0.5rem; justify-content: center;">
                    <input type="radio" name="user_type" value="pet-owner" checked>
                    <strong>Pet Owner</strong>
                </label>
                <label class="user-type-button" style="display: flex; flex-direction: row; gap: 0.5rem; justify-content: center;">
                    <input type="radio" name="user_type" value="pet-sitter">
                    <strong>Pet Sitter</strong>
                </label>
            </div>

            <div class="form-group">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" placeholder="Choose a username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" placeholder="John" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" placeholder="Doe" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" placeholder="john@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <div class="password-wrapper" style="position: relative;">
                    <input type="password" id="password" name="password" placeholder="At least 12 characters (1 uppercase, 1 number)" required style="width: 100%;">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Create Account</button>
            
            <p style="text-align: center; margin-top: 1.5rem;">
                Already have an account? <a href="login.php" style="color: var(--clr-brand); font-weight: bold;">Sign in here</a>
            </p>
        </form>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>