<?php
require_once 'config.php';
require_once 'auth.php';

// Check if already logged in
if (isUserLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = sanitizeInput($_POST['password'] ?? '');
    $confirm_password = sanitizeInput($_POST['confirm_password'] ?? '');
    $user_type = sanitizeInput($_POST['user_type'] ?? '');
    $terms_accepted = isset($_POST['terms-conditions']) ? true : false;

    // Validate inputs
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($user_type)) {
        $error = 'All fields are required.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif (!validatePassword($password)) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!$terms_accepted) {
        $error = 'You must accept the Terms of Service and Privacy Policy.';
    } elseif ($user_type !== 'pet-owner' && $user_type !== 'pet-sitter') {
        $error = 'Invalid user type.';
    } else {
        // Check if email already exists
        $existingUser = getUserByEmail($conn, $email);
        if ($existingUser) {
            $error = 'Email address already registered.';
        } else {
            // Check if username already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = 'Username already taken.';
            } else {
                // Hash password and insert user
                $hashed_password = hashPassword($password);

                $stmt = $conn->prepare("INSERT INTO users (username, email, password, user_type, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssss", $username, $email, $hashed_password, $user_type);

                if ($stmt->execute()) {
                    $success = 'Account created successfully! Please <a href="login.php">log in</a>.';
                } else {
                    $error = 'Error creating account. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up | Petsitter's Market</title>
    <meta name="description" content="Create your Petsitter's Market account to connect with trusted sitters and pet owners.">
    
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
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
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" action="signup.php" method="post" novalidate>
                <p class="small-label">I am a:</p>
                <div class="user-type-row">
                    <input type="hidden" name="user_type" id="user_type_value" value="">
                    <button class="user-type-button" type="button" data-type="pet-owner" onclick="selectUserType('pet-owner', this)">
                        <span>❤</span>
                        <strong>Pet Owner</strong>
                        <small>Find trusted sitters</small>
                    </button>
                    <button class="user-type-button" type="button" data-type="pet-sitter" onclick="selectUserType('pet-sitter', this)">
                        <span>🤝</span>
                        <strong>Pet Sitter</strong>
                        <small>Earn caring for pets</small>
                    </button>
                </div>

                <loginBox>
                    <h2>Username</h2>
                    <input type="text" name="username" placeholder="Choose a username" size="49" required/>
                </loginBox>

                <loginBox>
                    <h2>Email Address</h2>
                    <input type="email" name="email" placeholder="Enter your email" size="49" required/>
                </loginBox>

                <loginBox>
                    <h2>Password</h2>
                    <input type="password" name="password" placeholder="Create a password (min 8 characters)" size="49" required/>
                </loginBox>

                <loginBox>
                    <h2>Confirm Password</h2>
                    <input type="password" name="confirm_password" placeholder="Confirm your password" size="49" required/>
                </loginBox>

                <div class="check-box">
                    <input type="checkbox" name="terms-conditions" id="terms-conditions" required/>
                    <label for="terms-conditions">I agree to the Terms of Service and Privacy Policy</label>
                </div>

                <input type="submit" value="Create Account" class="cta-button" />
            </form>

            <div class="divider">Sign-up from other platforms</div>

            <div class="social-row">
                <button type="button" class="social-button google-login" disabled>Google</button>
                <button type="button" class="social-button facebook-login" disabled>Facebook</button>
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
