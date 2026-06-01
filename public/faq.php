<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/auth.php';

startSecureSession();

try {
    // Synchronisation avec la table 'faq' et filtrage sur les éléments publiés
    $stmt = $pdo->query("SELECT id, question, answer FROM faq WHERE is_published = 1 ORDER BY display_order ASC");
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur FAQ: " . $e->getMessage());
    $faqs = [];
    $errorMessage = "Impossible de charger la Foire Aux Questions. L'équipe technique est sur le coup.";
}

require_once __DIR__ . '/includes/header.php';
?>

<main class="faq-container">
    <h1>Foire Aux Questions</h1>
    
    <?php if (!empty($errorMessage)): ?>
        <p class="error"><?= escapeOutput($errorMessage) ?></p>
    <?php elseif (empty($faqs)): ?>
        <p>Aucune question n'a été ajoutée pour le moment.</p>
    <?php else: ?>
        <?php foreach ($faqs as $faq): ?>
            <article class="faq-item">
                <h3><?= escapeOutput($faq['question']) ?></h3>
                <p><?= nl2br(escapeOutput($faq['answer'])) ?></p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>