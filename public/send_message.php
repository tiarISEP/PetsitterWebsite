<?php
require_once 'includes/db.php';
require_once 'includes/crypto.php';
require_once 'auth.php';
startSecureSession();

// ─── Auth guard ────────────────────────────────────────────────────────────
if (!isUserLoggedIn()) {
    header("Location: login.php");
    exit();
}

// ─── Only accept POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: messages.php");
    exit();
}

// ─── CSRF ──────────────────────────────────────────────────────────────────
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    // Bounce back to the conversation with an error flag
    $back = trim($_POST['conversation_public_id'] ?? '');
    $url  = $back ? "messages.php?convo=" . urlencode($back) . "&err=csrf" : "messages.php?err=csrf";
    header("Location: $url");
    exit();
}

$sender_id              = (int)$_SESSION['user_id'];
$recipient_public_id    = trim($_POST['recipient_public_id'] ?? '');
$conversation_public_id = trim($_POST['conversation_public_id'] ?? '');
$body                   = trim($_POST['body'] ?? '');

// ─── Input validation ──────────────────────────────────────────────────────
if (empty($recipient_public_id) || empty($body)) {
    $back = $conversation_public_id ? "?convo=" . urlencode($conversation_public_id) : "";
    $url  = $back ? "messages.php{$back}&err=invalid" : "messages.php?err=invalid";
    header("Location: $url");
    exit();
}

// Hard limit — matches textarea maxlength
if (strlen($body) > 1000) {
    $back = $conversation_public_id ? "?convo=" . urlencode($conversation_public_id) : "";
    $url = $back
         ? "messages.php{$back}&err=toolong"
         : "messages.php?err=toolong";
    header("Location: $url");
    exit();
}

// ─── Resolve recipient ID from public_id ──────────────────────────────────
$stmt = $pdo->prepare("SELECT id FROM users WHERE public_id = ? AND is_banned = 0");
$stmt->execute([$recipient_public_id]);
$recipient_row = $stmt->fetch();

if (!$recipient_row) {
    header("Location: messages.php?err=invalid");
    exit();
}

$recipient_id = (int)$recipient_row['id'];

// No self-messaging
if ($sender_id === $recipient_id) {
    header("Location: messages.php");
    exit();
}

// ─── Resolve / verify conversation ────────────────────────────────────────
if (!empty($conversation_public_id)) {
    // Existing conversation — make sure the sender actually belongs to it
    $stmt = $pdo->prepare(
        "SELECT id, reciever_id, sender_id
         FROM conversations
         WHERE public_id = ? AND (reciever_id = ? OR sender_id = ?)"
    );
    $stmt->execute([$conversation_public_id, $sender_id, $sender_id]);
    $conv = $stmt->fetch();

    if (!$conv) {
        // User tried to post into a conversation they don't own
        header("Location: messages.php");
        exit();
    }
    
    $conversation_id = (int)$conv['id'];
} else {
    // No existing conversation ID supplied — look one up or create it.
    // Determine which side is the owner and which is the sitter.
    $stmt = $pdo->prepare("SELECT id, user_type FROM users WHERE id = ?");
    $stmt->execute([$recipient_id]);
    $recipient = $stmt->fetch();

    if (!$recipient) {
        header("Location: messages.php");
        exit();
    }

    $session_type = $_SESSION['user_type'] ?? '';

    if ($session_type === 'pet-owner') {
        $reciever_id     = $sender_id;
        $sender_id = $recipient_id;
    } elseif ($session_type === 'pet-sitter') {
        $reciever_id     = $recipient_id;
        $sender_id = $sender_id;
    } else {
        // Fallback: derive from recipient type
        if ($recipient['user_type'] === 'pet-sitter') {
            $reciever_id     = $sender_id;
            $sender_id = $recipient_id;
        } else {
            $reciever_id     = $recipient_id;
            $sender_id = $sender_id;
        }
    }

    // Look for an existing conversation between these two
    $stmt = $pdo->prepare(
        "SELECT id FROM conversations
         WHERE reciever_id = ? AND sender_id = ?"
    );
    $stmt->execute([$reciever_id, $sender_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $conversation_id = (int)$existing['id'];
    } else {
        // Create a new conversation with public_id
        $public_id = bin2hex(random_bytes(18)); // or use UUID if available
        $pdo->prepare(
            "INSERT INTO conversations (reciever_id, sender_id, public_id) VALUES (?, ?, ?)"
        )->execute([$reciever_id, $sender_id, $public_id]);
        $conversation_id = (int)$pdo->lastInsertId();
    }
}

$encrypted_body = encryptMessage($body);

// ─── Insert message ────────────────────────────────────────────────────────
try {
    $pdo->prepare(
        "INSERT INTO messages (conversation_id, sender_id, body)
         VALUES (?, ?, ?)"
    )->execute([$conversation_id, $sender_id, $encrypted_body]);
} catch (PDOException $e) {
    error_log("send_message error: " . $e->getMessage());
    header("Location: messages.php?convo={$conversation_id}&err=db");
    exit();
}

// ─── Get the conversation's public_id for redirect ─────────────────────────
$stmt = $pdo->prepare("SELECT public_id FROM conversations WHERE id = ?");
$stmt->execute([$conversation_id]);
$conv_data = $stmt->fetch();
$conv_public_id = $conv_data['public_id'] ?? '';

// ─── Redirect back to the thread ──────────────────────────────────────────
header("Location: messages.php?convo=" . urlencode($conv_public_id));
exit();