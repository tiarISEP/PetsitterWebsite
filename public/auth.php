<?php
// Authentication helper functions

// Session
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => true,   // HTTPS only
            'httponly' => true,   // No JS access
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

// Input / Output helpers
//sanitizeInput changed to escapeOutput
function escapeOutput($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// Validation
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    // Minimum 12 characters; at least one uppercase, one lowercase, one digit
    return strlen($password) >= 12
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

// Password hashing
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// CSRF protection
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Brute-force / rate limiting
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_SECONDS', 900); // 15 minutes

function isAccountLocked($conn, $email) {
    $stmt = $conn->prepare(
        "SELECT attempts, last_attempt FROM login_attempts
         WHERE email = ? LIMIT 1"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) return false;

    $elapsed = time() - strtotime($row['last_attempt']);

    if ($elapsed > LOCKOUT_SECONDS) {
        // Reset stale lock
        resetLoginAttempts($conn, $email);
        return false;
    }

    return $row['attempts'] >= MAX_LOGIN_ATTEMPTS;
}

function recordFailedAttempt($conn, $email) {
    $stmt = $conn->prepare(
        "INSERT INTO login_attempts (email, attempts, last_attempt)
         VALUES (?, 1, NOW())
         ON DUPLICATE KEY UPDATE
           attempts     = attempts + 1,
           last_attempt = NOW()"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
}

function resetLoginAttempts($conn, $email) {
    $stmt = $conn->prepare(
        "DELETE FROM login_attempts WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
}

// Authentication flow
/**
 * Full login flow: rate-limit → verify → session fixation fix.
 * Returns true on success, or a string error message on failure.
 */
function loginUser($conn, $email, $password, $csrfToken) {
    // 1. CSRF
    if (!validateCsrfToken($csrfToken)) {
        return "Invalid request. Please try again.";
    }

    // 2. Sanitise input (trim only; do NOT htmlspecialchars before DB)
    $email = trim($email);

    // 3. Rate limiting
    if (isAccountLocked($conn, $email)) {
        return "Too many failed attempts. Please wait 15 minutes.";
    }

    // 4. Fetch user
    $user = getUserByEmail($conn, $email);
    if (!$user || !verifyPassword($password, $user['password'])) {
        recordFailedAttempt($conn, $email);
        return "Invalid email or password."; // Same message to prevent enumeration
    }

    // 5. Session fixation fix: regenerate ID before writing session data
    session_regenerate_id(true);

    // 6. Populate session
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['user_type'] = $user['user_type'];

    // 7. Reset failed-attempt counter on success
    resetLoginAttempts($conn, $email);

    // 8. Rotate CSRF token after login
    unset($_SESSION['csrf_token']);
    generateCsrfToken();

    return true;
}

function isUserLoggedIn() {
    return isset($_SESSION['user_id'], $_SESSION['username']);
}

function redirectToLogin() {
    if (!isUserLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function logoutUser() {
    global $conn;
    
    // 1. Clear remember token from database if user is logged in
    if (isset($_SESSION['user_id']) && $conn) {
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
        }
    }
    
    // 2. Clear remember token cookie
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);

    // 3. Clear session data
    $_SESSION = [];

    // 4. Expire the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params["path"],   $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // 5. Destroy session on server
    session_destroy();

    header("Location: index.html");
    exit();
}

// Database helpers
function getUserById($conn, $user_id) {
    $stmt = $conn->prepare(
        "SELECT id, username, email, user_type, created_at
         FROM users WHERE id = ?"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getUserByEmail($conn, $email) {
    $stmt = $conn->prepare(
        "SELECT id, username, email, password, user_type
         FROM users WHERE email = ?"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
