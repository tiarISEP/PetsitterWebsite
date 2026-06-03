<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

// Fetch FAQ data
$faq_categories = [];
$faq_items = [];

$faq_categories = $pdo->query(
    "SELECT * FROM faq_category WHERE 1 ORDER BY sort_order"
)->fetchAll();

$faq_items = $pdo->query(
    "SELECT f.*, c.label AS cat_label FROM faq f "
    . "JOIN faq_category c ON f.category_id = c.id "
    . "WHERE f.is_active = 1 "
    . "ORDER BY f.category_id, f.sort_order"
)->fetchAll();

function escapeAndTruncate($text, $max = 100) {
    $text = $text ?? '';
    $plain = strip_tags($text);
    if (mb_strlen($plain) <= $max) {
        return escapeOutput($plain);
    }
    return escapeOutput(mb_substr($plain, 0, $max - 1) . '…');
}

$pageTitle = "FAQ | PetSitter's Market";
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="css/faq.css">

<main id="main-content" class="container">
    <div class="faq-page-container">
        <h1 class="title-primary">Frequently Asked Questions</h1>
        <p class="subtitle">Find answers to common questions about our pet sitting service.</p>

        <?php foreach ($faq_categories as $cat):
            $cat_questions = array_filter(
                $faq_items,
                fn($q) => $q['category_id'] == $cat['id']
            );
            if (empty($cat_questions)) continue;
        ?>
        <div class="faq-category">
            <h2 class="faq-cat-title">
                <i class="fas <?php echo escapeOutput($cat['icon']); ?>"></i>
                <?php echo escapeOutput($cat['label']); ?>
            </h2>

            <div class="faq-questions">
                <?php foreach ($cat_questions as $q): ?>
                <details class="faq-item">
                    <summary class="faq-question">
                        <?php echo escapeAndTruncate($q['question'], 100); ?>
                    </summary>
                    <div class="faq-answer">
                        <?php echo escapeOutput($q['answer']); ?>
                    </div>
                </details>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($faq_categories)): ?>
        <div class="alert alert-info">
            No FAQ categories available at this time.
        </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
