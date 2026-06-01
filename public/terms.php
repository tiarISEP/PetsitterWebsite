<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/auth.php';

startSecureSession();

try {
    // Synchronisation avec la table 'cgu_versions', colonnes correctes et filtre sur la version active
$stmt = $pdo->query("SELECT section_title AS title, content FROM cgu_versions WHERE is_active = 1 ORDER BY id ASC");    $terms_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur Terms: " . $e->getMessage());
    $terms_list = [];
    $errorMessage = "Impossible de charger les conditions. L'équipe technique est notifiée.";
}

require_once __DIR__ . '/includes/header.php';
?>

<main class="terms-container">
    <h1>Conditions Générales</h1>
    
    <?php if (!empty($errorMessage)): ?>
        <p class="error"><?= escapeOutput($errorMessage) ?></p>
    <?php elseif (empty($terms_list)): ?>
        <p>Les conditions générales ne sont pas disponibles pour le moment.</p>
    <?php else: ?>
        <?php foreach ($terms_list as $term): ?>
            <section class="term-item">
                <h3><?= escapeOutput($term['title']) ?></h3>
                <p><?= nl2br(escapeOutput($term['content'])) ?></p>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>