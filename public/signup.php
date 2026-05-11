<?php
require_once 'config.php';
require_once 'auth.php';
 
startSecureSession();
 
// Redirect if already logged in
if (isUserLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}
 
$error   = '';
$success = '';

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 
    // 1. CSRF check
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } else {
        // 2. Collect inputs — trim only, no escapeOutput before DB
        $username         = trim($_POST['username']         ?? '');
        $email            = trim($_POST['email']            ?? '');
        $password         =      $_POST['password']         ?? '';
        $confirm_password =      $_POST['confirm_password'] ?? '';
        $user_type        = trim($_POST['user_type']        ?? '');
        $terms_accepted   = isset($_POST['terms-conditions']);
 
        // 3. Validate
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($user_type)) {
            $error = 'All fields are required.';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $error = 'Username must be between 3 and 50 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $error = 'Username may only contain letters, numbers, and underscores.';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (!validatePassword($password)) {
            $error = 'Password must be at least 12 characters and include an uppercase letter, a lowercase letter, and a number.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (!$terms_accepted) {
            $error = 'You must accept the Terms of Service and Privacy Policy.';
        } elseif ($user_type !== 'pet-owner' && $user_type !== 'pet-sitter') {
            $error = 'Invalid user type selected.';
        } else {
            // 4. Check for duplicate email
            if (getUserByEmail($conn, $email)) {
                $error = 'An account with this email already exists.';
            } else {
                // 5. Check for duplicate username
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $stmt->get_result()->num_rows > 0
                    ? $error = 'Username already taken.'
                    : null;
 
                if (empty($error)) {
                    // 6. Insert user
                    $hashed_password = hashPassword($password);
                    $stmt = $conn->prepare(
                        "INSERT INTO users (username, email, password, user_type, created_at)
                         VALUES (?, ?, ?, ?, NOW())"
                    );
                    $stmt->bind_param("ssss", $username, $email, $hashed_password, $user_type);
 
                    if ($stmt->execute()) {
                        // Rotate CSRF token after successful action
                        unset($_SESSION['csrf_token']);
                        $success = 'Account created successfully! Please <a href="login.php">log in</a>.';
                    } else {
                        $error = 'Error creating account. Please try again.';
                    }
                }
            }
        }
    }
}
 
// Generate CSRF token for the form
$csrfToken = generateCsrfToken();

?>

<!DOCTYPE html>
<html lang="en"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up | Petsitter's Market</title>
    <meta name="description" content="Create your Petsitter's Market account to connect with trusted sitters and pet owners.">
    
    <link rel="stylesheet" href="css/style.css?v=1.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.password-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const wrapper = btn.closest('.password-wrapper');
                const input   = wrapper ? wrapper.querySelector('input') : null;
                const icon    = btn.querySelector('i');

                if (!input || !icon) return;

                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';

                icon.classList.toggle('fa-eye',       !isHidden);
                icon.classList.toggle('fa-eye-slash',  isHidden);

                btn.setAttribute('aria-label',
                    isHidden ? 'Hide password' : 'Show password'
                );
            });
        });
    });
</script>

<body class="login-page">
    <a href="#main-content" class="skip-link" style="position: absolute; left: -9999px;">Aller au contenu principal</a>

    <header>
        <div class="logo">
            <a href="index.html" style="text-decoration: none; color: inherit;">PetSitter's Market</a> 
        </div>
        <nav aria-label="Navigation principale">
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="services.html">Services</a></li>
                <li><a href="contact.html">Contact</a></li>
                <li><a href="login.php" style="font-weight: 500; color: #772f1a; padding: 0.5rem 1rem;">Login</a></li>
                <li><a href="signup.php" style="background-color: #585123; color: white; padding: 0.5rem 1.5rem; border-radius: 8px; font-weight: 500; text-decoration: none;">Sign Up</a></li>
            </ul>
        </nav>
    </header>

    <main id="main-content">
        <div class="content">
            <h1>Create Your Account</h1>
            <p>Join our community of pet lovers</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" action="signup.php" method="post" novalidate>
            
                <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrfToken); ?>" />
            
                <p class="small-label">I am a:</p>
                <div class="user-type-row" role="group" aria-label="Account type">
                    <input type="hidden" name="user_type" id="user_type_value" 
                        value="<?php echo escapeOutput($_POST['user_type'] ?? ''); ?>">
                    <button 
                            class="user-type-button <?php echo (($_POST['user_type'] ?? '') === 'pet-owner') ? 'selected' : ''; ?>"
                            type="button" data-type="pet-owner" 
                            onclick="selectUserType('pet-owner', this)"
                        >
                        <span aria-hidden="true">❤</span>
                        <strong>Pet Owner</strong>
                        <small>Find trusted sitters</small>
                    </button>
                    <button 
                            class="user-type-button <?php echo (($_POST['user_type'] ?? '') === 'pet-sitter') ? 'selected' : ''; ?>" 
                            type="button" data-type="pet-sitter" 
                            onclick="selectUserType('pet-sitter', this)"
                        >
                        <span aria-hidden="true">🤝</span>
                        <strong>Pet Sitter</strong>
                        <small>Earn caring for pets</small>
                    </button>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        name="username" 
                        id="username" 
                        placeholder="Choose a username" 
                        value="<?php echo escapeOutput($_POST['username'] ?? ''); ?>"
                        autocomplete="username"
                        maxlength="50"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        placeholder="Enter your email address" 
                        value="<?php echo escapeOutput($_POST['email'] ?? ''); ?>"
                        autocomplete="email"
                        required
                    />
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            placeholder="min 12 ch, upper, lower, number" 
                            autocomplete="new-password"
                            required
                        />
                        <button class="password-toggle" type="button" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            name="confirm_password" 
                            id="confirm_password" 
                            placeholder="Confirm your password" 
                            autocomplete="new-password"
                            required
                        />
                        <button class="password-toggle" type="button" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="check-box">
                    <input type="checkbox" name="terms-conditions" id="terms-conditions" 
                        <?php echo isset($_POST['terms-conditions']) ? 'checked' : ''; ?> 
                        required
                    />
                    <label for="terms-conditions">I agree to the <a href="terms.html">Terms of Service</a> 
                        </br> and 
                        <a href="privacy.html">Privacy Policy</a>
                        .
                    </label>
                </div>

                <button type="submit" class="cta-button">Create Account</button>
            </form>

            <div class="divider">or sign up with</div>

            <div class="social-row">
                <button type="button" class="social-button google-login" disabled>Google</button>
                <button type="button" class="social-button facebook-login" disabled>Facebook</button> <!-- maybe remove -->
            </div>

            <p class="signin-copy">Already have an account? <a href="login.php">Sign in</a></p>
        </div>
    </main>

    <footer>
        <div class="footer-container">
            <div class="footer-col brand-col">
                <h2><i class="fas fa-paw"></i> Petsitter's Market</h2>
                <p>Connecting pet owners with<br>trusted caregivers since 2020.</p>
            </div>
            <div class="footer-col">
                <h3>Services</h3>
                <a href="#">Pet Sitting</a>
                <a href="#">Dog Walking</a>
                <a href="#">Pet Grooming</a>
                <a href="#">Vet Visits</a>
            </div>
            <div class="footer-col">
                <h3>Company</h3>
                <a href="#">About Us</a>
                <a href="#">Contact</a>
                <a href="#">Careers</a>
                <a href="#">Privacy Policy</a>
            </div>
            <div class="footer-col">
                <h3>Contact</h3>
                <div class="contact-item">
                    <i class="fas fa-phone-alt"></i> (555) 123-4567
                </div>
            </div>
        </div>
        <p class="footer-bottom">&copy; 2024 Petsitter's Market. All rights reserved.</p>
    </footer>

    <script>
        function selectUserType(type, button) {
            // Set the hidden input value
            document.getElementById('user_type_value').value = type;
            
            // Update button styles
            document.querySelectorAll('.user-type-button').forEach(btn => {
                btn.classList.remove('selected');
            });
            button.classList.add('selected');
        }
    </script>
</body>
</html>
