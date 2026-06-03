<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();
redirectToLogin();

if ($_SESSION['user_type'] !== 'pet-owner' && $_SESSION['user_type'] !== 'admin') {
    header("Location: profile.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Erreur de sécurité (CSRF).";
    } else {
        $animal_id = (int)$_POST['animal_id'];
        
        // Check if pet belongs to user
        $stmt = $pdo->prepare("SELECT * FROM animal WHERE animalID = ? AND User_userID = ?");
        $stmt->execute([$animal_id, $user_id]);
        $pet = $stmt->fetch();
        
        if ($pet) {
            // Check if pet is linked to an ad
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_has_animal WHERE Animal_animalID = ?");
            $stmt->execute([$animal_id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $error = "Impossible de supprimer cet animal car il est lié à une ou plusieurs annonces.";
            } else {
                // Delete photo if exists
                if ($pet['photo_url'] && file_exists(__DIR__ . '/' . $pet['photo_url'])) {
                    unlink(__DIR__ . '/' . $pet['photo_url']);
                }
                
                // Delete pet
                $stmt = $pdo->prepare("DELETE FROM animal WHERE animalID = ?");
                $stmt->execute([$animal_id]);
                $success = "Animal supprimé avec succès.";
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user's pets
$stmt = $pdo->prepare("SELECT * FROM animal WHERE User_userID = ? ORDER BY CreationDate DESC");
$stmt->execute([$user_id]);
$pets = $stmt->fetchAll();

$pageTitle = "Mes Animaux | PetSitter's Market";
require_once 'includes/header.php';
?>

<main id="main-content" class="container" style="padding: 2rem 1rem; min-height: 70vh;">
    <div class="content" style="max-width: 1000px; margin: 0 auto;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 class="title-primary" style="margin: 0;">Mes Animaux</h1>
            <a href="add_pet.php" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter un animal</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo escapeOutput($success); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($pets)): ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <i class="fas fa-paw" style="font-size: 3rem; color: var(--clr-primary); margin-bottom: 1rem;"></i>
                <h2>Vous n'avez pas encore ajouté d'animal.</h2>
                <p style="margin-top: 1rem;">Ajoutez votre premier compagnon pour pouvoir créer des annonces de garde.</p>
                <a href="add_pet.php" class="btn btn-primary" style="margin-top: 1.5rem;">Ajouter mon premier animal</a>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
                <?php foreach ($pets as $pet): ?>
                    <div class="card" style="display: flex; flex-direction: column; height: 100%;">
                        <?php if ($pet['photo_url']): ?>
                            <div style="width: 100%; height: 200px; overflow: hidden; border-radius: var(--border-radius-md) var(--border-radius-md) 0 0; margin: -1.5rem -1.5rem 1rem -1.5rem; width: calc(100% + 3rem);">
                                <img src="<?php echo escapeOutput($pet['photo_url']); ?>" alt="<?php echo escapeOutput($pet['Name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                        <?php endif; ?>
                        
                        <h2 style="color: var(--clr-text-title); margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between;">
                            <?php echo escapeOutput($pet['Name']); ?>
                            <span style="font-size: 0.9rem; background: var(--clr-primary); color: white; padding: 0.2rem 0.6rem; border-radius: 12px; font-weight: normal;">
                                <?php echo escapeOutput($pet['species']); ?>
                            </span>
                        </h2>
                        
                        <div style="margin-bottom: 1rem; flex-grow: 1;">
                            <p><strong>Race :</strong> <?php echo escapeOutput($pet['breed']); ?></p>
                            <p><strong>Âge :</strong> <?php echo escapeOutput($pet['age']); ?></p>
                            <?php if (!empty($pet['medical_notes'])): ?>
                                <p style="margin-top: 0.5rem; font-size: 0.9rem; color: #666;">
                                    <strong>Notes :</strong><br>
                                    <?php echo nl2br(escapeOutput($pet['medical_notes'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 1rem; margin-top: auto;">
                            <!-- <a href="edit_pet.php?id=<?php echo $pet['animalID']; ?>" class="btn btn-text" style="flex: 1; text-align: center; border: 1px solid var(--clr-brand);"><i class="fas fa-edit"></i> Modifier</a> -->
                            <form method="POST" action="my_pets.php" style="flex: 1;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet animal ?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="animal_id" value="<?php echo $pet['animalID']; ?>">
                                <button type="submit" class="btn btn-text" style="width: 100%; color: var(--clr-error-text); border: 1px solid var(--clr-error-border);"><i class="fas fa-trash-alt"></i> Supprimer</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
