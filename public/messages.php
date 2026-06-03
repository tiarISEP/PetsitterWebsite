<?php
require_once 'includes/db.php';
require_once 'includes/crypto.php';
require_once 'auth.php';
startSecureSession();

if (!isUserLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// ─── Active conversation ───────────────────────────────────────────────────
$active_public_id = trim($_GET['convo'] ?? '');
$conversation = null;
$messages     = [];
$other_user   = null;
$sitter_data  = null;

if (!empty($active_public_id)) {
    // Verify the logged-in user actually belongs to this conversation
    $stmt = $pdo->prepare(
        "SELECT * FROM conversations
         WHERE public_id = ? AND (reciever_id = ? OR sender_id = ?)"
    );
    $stmt->execute([$active_public_id, $user_id, $user_id]);
    $conversation = $stmt->fetch();

    if (!$conversation) {
        // Silently drop the bad param — show inbox only
        $active_public_id = '';
    } else {
        // Mark incoming messages as read
        $pdo->prepare(
            "UPDATE messages SET is_read = 1
             WHERE conversation_id = ? AND sender_id != ?"
        )->execute([$conversation['id'], $user_id]);

        // Fetch messages
        $stmt = $pdo->prepare(
            "SELECT m.id, m.sender_id, m.body, m.created_at,
                    u.first_name, u.last_name, u.username, u.avatar_url
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = ?
             ORDER BY m.created_at ASC"
        );
        $stmt->execute([$conversation['id']]);
        $messages = $stmt->fetchAll();

        // 1. Get the ID of the person you are conversing with
        // Note: Changed === to == to prevent type mismatch bugs (e.g., string "5" vs integer 5 from DB)
        $other_id = ($conversation['reciever_id'] == $user_id)
            ? $conversation['sender_id']
            : $conversation['reciever_id'];

        // 2. Fetch the other user's complete profile summary
        $stmt = $pdo->prepare(
            "SELECT id, first_name, last_name, username, avatar_url,
                    bio, user_type, created_at, public_id, is_sitter, is_owner
            FROM users WHERE id = ?"
        );
        $stmt->execute([$other_id]);
        $other_user_data = $stmt->fetch();

        // 3. Initialize default rating tracking
        $other_user_rating = ['avg_rating' => 0, 'total_reviews' => 0];

        // 4. Fetch ratings for the other user (if they exist)
        if ($other_user_data) {
            // Optional: If ONLY pet-sitters can receive reviews in your app, 
            // you can uncomment this wrapper line to save a database query:
            // if ($other_user_data['user_type'] === 'pet-sitter' || $other_user_data['is_sitter'] == 1) {

            $stmt = $pdo->prepare(
                "SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total_reviews
                FROM reviews WHERE rated_user_id = ? AND is_disabled = 0"
            );
            $stmt->execute([$other_id]);
            $rating_data = $stmt->fetch();
            
            if ($rating_data) {
                $other_user_rating = $rating_data;
            }
        }
    }
}

// Conversation list (left panel)
$stmt = $pdo->prepare("
    SELECT
        c.id                    AS conversation_id,
        c.public_id             AS conversation_public_id,
        CASE WHEN c.reciever_id = :uid THEN c.sender_id
             ELSE c.reciever_id END AS other_user_id,
        u.first_name, u.last_name, u.username, u.avatar_url,
        m.body                  AS last_message,
        m.created_at            AS last_message_at,
        (SELECT COUNT(*) FROM messages
         WHERE conversation_id = c.id
           AND sender_id != :uid2
           AND is_read = 0)     AS unread_count
    FROM conversations c
    JOIN users u ON u.id = CASE WHEN c.reciever_id = :uid3
                                THEN c.sender_id ELSE c.reciever_id END
    JOIN messages m ON m.id = (
        SELECT id FROM messages
        WHERE conversation_id = c.id
        ORDER BY created_at DESC LIMIT 1
    )
    WHERE c.reciever_id = :uid4 OR c.sender_id = :uid5
    ORDER BY last_message_at DESC
");
$stmt->execute([
    ':uid'  => $user_id,
    ':uid2' => $user_id,
    ':uid3' => $user_id,
    ':uid4' => $user_id,
    ':uid5' => $user_id,
]);
$conversations = $stmt->fetchAll();

// CSRF + helpers
$csrf_token = generateCsrfToken();

function display_name_from(array $u): string {
    $n = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    return $n ?: $u['username'];
}

function time_label(string $dt): string {
    $ts   = strtotime($dt);
    $now  = time();
    $diff = $now - $ts;
    if ($diff < 86400 && date('d') === date('d', $ts)) return date('H:i', $ts);
    if ($diff < 172800)                                 return 'Yesterday';
    if ($diff < 604800)                                 return date('D', $ts);
    return date('d M', $ts);
}

function stars_html(float $avg): string {
    $full  = (int)$avg;
    $half  = ($avg - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;
    return str_repeat('★', $full) . ($half ? '½' : '') . str_repeat('☆', $empty);
}

$pageTitle = "Messages | PetSitter's Market";
require_once 'includes/header.php';
?>

<?php
$err_map = [
    'csrf'    => 'Security error — please try again.',
    'invalid' => 'Your message could not be sent.',
    'toolong' => 'Message must be 1000 characters or fewer.',
    'db'      => 'A server error occurred. Please try again.',
];
$err_key = $_GET['err'] ?? '';
if ($err_key && isset($err_map[$err_key])): ?>
    <div class="alert alert-error" style="grid-column:1/-1;margin:.75rem 1rem 0;">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo escapeOutput($err_map[$err_key]); ?>
    </div>
<?php endif; ?>

<link rel="stylesheet" href="css/messages.css">

<main id="main-content" class="messages-page">

<aside class="msg-left"> <!-- maybe use container (rounder) -->
    <div class="msg-left__header">
        <h1>Messages</h1>
    </div>

    <div class="msg-left__filters">
        <a href="messages.php"
           class="filter-btn <?php echo !isset($_GET['unread']) ? 'active' : ''; ?>">
            All
        </a>
        <a href="messages.php?unread=1"
           class="filter-btn <?php echo isset($_GET['unread']) ? 'active' : ''; ?>">
            Unread
        </a>
    </div>

    <div class="msg-left__list">
        <?php if (empty($conversations)): ?>
            <p class="empty-list">No conversations yet.</p>
        <?php else: ?>
            <?php foreach ($conversations as $conv):
                // If "unread" filter is active, skip read conversations
                if (isset($_GET['unread']) && $conv['unread_count'] < 1) continue;
                $is_active   = ($active_public_id === $conv['conversation_public_id']);
                $other_name  = display_name_from($conv);
                $time_lbl    = time_label($conv['last_message_at']);
                $conv['last_message'] = decryptMessage($conv['last_message']);
                $preview     = mb_strimwidth($conv['last_message'], 0, 55, '…');
            ?>
            <a href="messages.php?convo=<?php echo urlencode($conv['conversation_public_id']); ?>"
               class="convo-item <?php echo $is_active ? 'active' : ''; ?>">

                <div class="convo-avatar">
                    <?php if ($conv['avatar_url']): ?>
                        <img src="<?php echo escapeOutput($conv['avatar_url']); ?>"
                             alt="<?php echo escapeOutput($other_name); ?>">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>

                <div class="convo-meta">
                    <span class="convo-name"><?php echo escapeOutput($other_name); ?></span>
                    <span class="convo-preview"><?php echo escapeOutput($preview); ?></span>
                </div>

                <div class="convo-aside">
                    <span class="convo-time"><?php echo escapeOutput($time_lbl); ?></span>
                    <?php if ($conv['unread_count'] > 0): ?>
                        <span class="unread-badge"><?php echo (int)$conv['unread_count']; ?></span>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</aside>


<section class="msg-middle">
    <?php if (!$conversation): ?>
        <div class="thread-empty">
            <i class="fas fa-comments"></i>
            <p>Select a conversation to start chatting.</p>
        </div>
        <?php else:
            $other_name = $other_user_data ? display_name_from($other_user_data) : 'Unknown';
            
            $is_sitter = $other_user_data && (!empty($other_user_data['is_sitter']) || ($other_user_data['user_type'] ?? '') === 'pet-sitter');
            $profile_page = $is_sitter ? 'petsitter.php' : 'petowner_profile.php';

            $profile_href = $other_user_data
                ? $profile_page . '?public_id=' . urlencode($other_user_data['public_id'])
                : '#';
        ?>

        <!-- Thread header -->
        <div class="thread-header">
            <div class="thread-header__avatar">
                <?php if ($other_user && $other_user['avatar_url']): ?>
                    <img src="<?php echo escapeOutput($other_user['avatar_url']); ?>"
                         alt="<?php echo escapeOutput($other_name); ?>">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="thread-header__info">
                <a href="<?php echo escapeOutput($profile_href); ?>" class="thread-header__name">
                    <?php echo escapeOutput($other_name); ?>
                </a>
                <?php if ($other_user): ?>
                <span class="thread-header__type">
                    <?php echo $other_user['user_type'] === 'pet-sitter' ? 'Pet Sitter' : 'Pet Owner'; ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Messages -->
        <div class="thread-body" id="thread-body">
            <?php if (empty($messages)): ?>
                <p class="thread-no-messages">No messages yet — say hello!</p>
            <?php else:
                $prev_date = '';
                foreach ($messages as $msg):
                    $msg['body'] = decryptMessage($msg['body']);
                    $is_mine  = ((int)$msg['sender_id'] === $user_id);
                    $msg_date = date('d M Y', strtotime($msg['created_at']));
                    $msg_time = date('H:i',   strtotime($msg['created_at']));
            ?>
                <?php if ($msg_date !== $prev_date): $prev_date = $msg_date; ?>
                    <div class="date-divider"><span><?php echo escapeOutput($msg_date); ?></span></div>
                <?php endif; ?>

                <div class="bubble-wrap <?php echo $is_mine ? 'me' : 'them'; ?>">
                    <div class="bubble"><?php echo escapeOutput($msg['body']); ?></div>
                    <span class="bubble-time"><?php echo $msg_time; ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Compose -->
        <div class="compose-area">
            <form method="POST" action="send_message.php" class="compose-form">
                <input type="hidden" name="csrf_token"              value="<?php echo escapeOutput($csrf_token); ?>">
                <input type="hidden" name="conversation_public_id"  value="<?php echo escapeOutput($active_public_id); ?>">
                <input type="hidden" name="recipient_public_id"
                       value="<?php echo $other_user ? escapeOutput($other_user['public_id']) : ''; ?>">

                <textarea name="body"
                          id="compose-input"
                          placeholder="Write a message…"
                          maxlength="1000"
                          rows="1"
                          required></textarea>

                <button type="submit" class="send-btn" aria-label="Send">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>

    <?php endif; ?>
</section>


<!-- RIGHT — other person summary -->
<aside class="msg-right">
    <?php if ($conversation && isset($other_user_data) && $other_user_data): ?>
        <?php
        // Generic data setup for whoever you are conversing with
        $other_name   = display_name_from($other_user_data);
        $avg          = (float)($other_user_rating['avg_rating']    ?? 0);
        $total        = (int)  ($other_user_rating['total_reviews'] ?? 0);
        $years        = max(1, date('Y') - date('Y', strtotime($other_user_data['created_at'])));
        
        // Determine role flags
        $is_sitter    = !empty($other_user_data['is_sitter']) || ($other_user_data['user_type'] ?? '') === 'pet-sitter';
        ?>

        <div class="msg-right__header">
            <h2><?php echo $is_sitter ? 'Sitter profile' : 'Owner profile'; ?></h2>
        </div>

        <div class="msg-right__body">

            <!-- Avatar + name -->
            <div class="sitter-card">
                <div class="sitter-card__avatar">
                    <?php if (!empty($other_user_data['avatar_url'])): ?>
                        <img src="<?php echo escapeOutput($other_user_data['avatar_url']); ?>"
                             alt="<?php echo escapeOutput($other_name); ?>">
                    <?php else: ?>
                        <i class="fas fa-user-circle"></i>
                    <?php endif; ?>
                </div>

                <div class="sitter-card__info">
                    <strong><?php echo escapeOutput($other_name); ?></strong>
                    <span>Member for <?php echo $years; ?> yr<?php echo $years > 1 ? 's' : ''; ?></span>
                </div>
            </div>

            <!-- Rating (Displays cleanly if they have any reviews) -->
            <?php if ($total > 0): ?>
            <div class="sitter-rating">
                <span class="stars"><?php echo stars_html($avg); ?></span>
                <span class="score"><?php echo $avg; ?></span>
                <span class="count">(<?php echo $total; ?> review<?php echo $total > 1 ? 's' : ''; ?>)</span>
            </div>
            <?php endif; ?>

            <hr class="sitter-divider">

            <!-- Bio -->
            <?php if (!empty($other_user_data['bio'])): ?>
            <div class="sitter-bio">
                <h3>About</h3>
                <p><?php echo escapeOutput(mb_strimwidth($other_user_data['bio'], 0, 180, '…')); ?></p>
            </div>
            <hr class="sitter-divider">
            <?php endif; ?>

            <!-- Services Block (Only displayed if they are actually a sitter) -->
            <?php if ($is_sitter): ?>
            <div class="sitter-services">
                <h3>Services</h3>
                <ul>
                    <li><i class="fas fa-check"></i> Pet Sitting</li>
                    <li><i class="fas fa-check"></i> Dog Walking</li>
                    <li><i class="fas fa-check"></i> Overnight Care</li>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Dynamic Profile Redirect -->
            <?php 
            $profile_page = $is_sitter ? 'petsitter.php' : 'petowner_profile.php'; 
            ?>
            <a href="<?php echo $profile_page; ?>?public_id=<?php echo urlencode($other_user_data['public_id']); ?>"
               class="btn btn-outline btn-block" style="margin-top:1.25rem;">
                View full profile
            </a>
        </div>

    <?php else: ?>
        <!-- Empty state when no message thread is active -->
        <div class="msg-right__empty">
            <i class="fas fa-paw"></i>
            <p>Select a conversation to see their profile summary.</p>
        </div>
    <?php endif; ?>
</aside>

</main>

<!--Auto-scroll & textarea auto-resize -->
<script>
(function () {
    // Scroll thread to bottom on load
    const thread = document.getElementById('thread-body');
    if (thread) thread.scrollTop = thread.scrollHeight;

    // Auto-resize textarea
    const ta = document.getElementById('compose-input');
    if (ta) {
        ta.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 140) + 'px';
        });
        // Submit on Enter, newline on Shift+Enter
        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.closest('form').requestSubmit();
            }
        });
    }
})();
</script>

<?php require_once 'includes/footer.php'; ?>