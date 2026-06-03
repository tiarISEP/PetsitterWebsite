<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

// Fetch active CGU versions
$cgu_versions = [];
try {
    $cgu_versions = $pdo->query(
        "SELECT * FROM cgu_versions WHERE is_active = 1 ORDER BY version_number ASC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur CGU: " . $e->getMessage());
    $cgu_versions = [];
}

$pageTitle = "Terms & Conditions | PetSitter's Market";
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="css/faq.css">

<main id="main-content" class="container">
    <div class="faq-page-container">
        <h1 class="title-primary">Terms & Conditions</h1>
        <p class="subtitle">Please read our terms and conditions carefully before using our service.</p>

        <?php if (empty($cgu_versions)): ?>
        <div class="alert alert-info">
            No Terms & Conditions available at this time.
        </div>
        <?php else: ?>
            <?php foreach ($cgu_versions as $section): ?>
            <div class="faq-category">
                <h2 class="faq-cat-title">
                    <?php echo escapeOutput($section['section_title']); ?>
                </h2>
                <div class="cgu-content">
                    <?php echo nl2br(escapeOutput($section['content'])); ?>
                </div>
                <div class="cgu-meta">
                    <strong>Version:</strong> <?php echo escapeOutput($section['version_number']); ?> 
                    | <strong>Effective from:</strong> <?php echo escapeOutput($section['effective_from']); ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="cgu-footer">
            <p>Last updated: <?php echo date('F d, Y', strtotime($cgu_versions[0]['created_at'] ?? 'now')); ?></p>
            <p>If you have any questions about these terms, please <a href="ContactUs.php" style="color: var(--clr-primary); font-weight:600; text-decoration:none;">contact us</a>.</p>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>