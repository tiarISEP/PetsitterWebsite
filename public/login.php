<?php
require_once 'config.php'; //scripts to be determined
require_once 'auth.php';   //scripts to be determined

startSecureSession();

// Check if already logged in
if (isUserLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

//remember me: auto-login via secure token
if (!isUserLoggedIn() && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    //token format: user_id:selector:validator
    $parts = explode(':', $token);
    if (count($parts) === 3) {
        [$user_id, $selector, $validator] = $parts;
        
        $stmt = $conn->prepare(
            "SELECT user_id, token_hash, expires_at FROM remember_tokens
             WHERE user_id = ? AND selector = ? LIMIT 1"
        );
        $stmt->bind_param("is", $user_id, $selector);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row && strtotime($row['expires_at']) > time()
            && hash_equals($row['token_hash'], hash('sha256', $validator))
        ) {
            $user = getUserById($conn, $user_id);
            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['login_time'] = time();
                header("Location: dashboard.php");
                exit();
            }
        }
        else {
            // Invalid/expired token - clear cookie
            setcookie('remember_token', '', time() - 3600, '/','',true,true);
        }
    }
}


$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember-me']) ? true : false;
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        // loginUser() handles: CSRF, rate limiting, 
        // verify, session_regenerate_id, session population
        $result = loginUser($conn, $email, $password, $csrfToken);

        if ($result === true) {
            $_SESSION['login_time'] = time();

            //remember me: secure token
            if ($remember) {
                $selector  = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $validator);
                $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); //30 days
                $userId    = $_SESSION['user_id'];


                // Store token in database
                $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();

                $stmt = $conn->prepare(
                    "INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param("isss", $userId, $selector, $tokenHash, $expiresAt);
                $stmt->execute();

                // Set cookie: value format user_id:selector:validator
                setcookie(
                    'remember_token',
                    "$userId:$selector:$validator",
                    time() + (30 * 24 * 60 * 60),
                    '/', '', true, true //path domain, secure, httponly
                );
            }

            header("Location: dashboard.php");
            exit();
        } else {
            //$error = $result; //error message from loginUser()
            $error = 'Invalid email or password.';
        }
    }
}

//generate CSRF token for the from
$csrfToken = generateCsrfToken();

?>

<!DOCTYPE html>
<html lang="en"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Petsitter's Market</title>
    <meta name="description" content="Sign in to your Petsitter's Market account to manage bookings and pet care services.">
    
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
            <h1>Welcome Back</h1>
            <p>Sign in to your Petsitter's Market account</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo escapeOutput($error); ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" action="login.php" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrfToken); ?>" />

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
                            placeholder="Enter your password" 
                            autocomplete="current-password"
                            required
                        />
                        <button class="password-toggle" type="button" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-footer-row">
                    <div class="check-box">
                        <input type="checkbox" name="remember-me" id="remember-me" 
                        <?php echo isset($_POST['remember-me']) ? 'checked' : ''; ?> />
                        <label for="remember-me">Remember me</label>
                    </div>
                    <div class="forgot-row">
                        <a href="forgot-password.php">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" class="cta-button">Sign in</button>

                <div class="divider">or</div>

                <div class="social-row">
                    <button type="button" class="social-button google-login" disabled>Google</button>
                    <button type="button" class="social-button facebook-login" disabled>Facebook</button> <!-- maybe not the facebook implementation -->
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
