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

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = sanitizeInput($_POST['password'] ?? '');
    $remember = isset($_POST['remember-me']) ? true : false;

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check user in database
        $user = getUserByEmail($conn, $email);

        if ($user && verifyPassword($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['login_time'] = time();

            // Set remember me cookie (30 days)
            if ($remember) {
                setcookie('remember_user', $user['id'], time() + (30 * 24 * 60 * 60), '/');
            }

            header("Location: dashboard.php");
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

// Check remember me cookie
if (!isUserLoggedIn() && isset($_COOKIE['remember_user'])) {
    $user = getUserById($conn, $_COOKIE['remember_user']);
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['login_time'] = time();
        header("Location: dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Petsitter's Market</title>
    <meta name="description" content="Sign in to your Petsitter's Market account to manage bookings and pet care services.">
    
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
            <h1>Welcome Back</h1>
            <p>Sign in to your Petsitter's Market account</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" action="login.php" method="post" novalidate>
                <loginBox>
                    <h2>Email address</h2>
                    <input type="email" name="email" placeholder="Enter your email address" size="49" required/>
                </loginBox>

                <loginBox>
                    <h2>Password</h2>
                    <input type="password" name="password" placeholder="Enter your password" size="49" required/>
                </loginBox>

                <div class="form-footer-row">
                    <div class="check-box">
                        <input type="checkbox" name="remember-me" id="remember-me" />
                        <label for="remember-me">Remember me</label>
                    </div>

                    <div class="forgot-row">
                        <a href="forgot-password.php">Forgot password?</a>
                    </div>
                </div>

                <input type="submit" value="Sign in" class="cta-button" />

                <div class="divider">or</div>

                <div class="social-row">
                    <button type="button" class="social-button google-login" disabled>Google</button>
                    <button type="button" class="social-button facebook-login" disabled>Facebook</button>
                </div>

                <p class="signin-copy">Don't have an account? <a href="signup.php">Create one here</a></p>
            </form>
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
</body>
</html>
