<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();
redirectToLogin();

if ($_SESSION['user_type'] !== 'pet-owner' && $_SESSION['user_type'] !== 'admin') {
    header("Location: profile.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Erreur de sécurité (CSRF).";
    } else {
        $name = trimInput($_POST['name'] ?? '');
        $species = trimInput($_POST['species'] ?? '');
        $breed = trimInput($_POST['breed'] ?? '');
        $age = trimInput($_POST['age'] ?? '');
        $medical_notes = trimInput($_POST['medical_notes'] ?? '');
        $user_id = $_SESSION['user_id'];
        
        // Validation basique
        if (empty($name) || empty($species) || empty($breed) || empty($age)) {
            $error = "Veuillez remplir tous les champs obligatoires.";
        } else if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $error = "La photo de l'animal est obligatoire.";
        } else {
            // Traitement de l'upload d'image
            $file = $_FILES['photo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = "Format d'image non supporté (JPEG, PNG, WEBP uniquement).";
            } elseif ($file['size'] > $max_size) {
                $error = "L'image ne doit pas dépasser 5 Mo.";
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = uniqid('pet_') . '.' . $ext;
                $upload_dir = 'images/pets/';
                $destination = __DIR__ . '/' . $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $photo_url = $upload_dir . $new_filename;
                    
                    try {
                        $stmt = $pdo->prepare("INSERT INTO animal (Name, species, breed, age, photo_url, medical_notes, User_userID) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $species, $breed, $age, $photo_url, $medical_notes, $user_id]);
                        
                        header("Location: my_pets.php?success=1");
                        exit();
                    } catch (PDOException $e) {
                        $error = "Erreur lors de l'enregistrement dans la base de données.";
                        // Supprimer l'image en cas d'erreur BDD
                        if (file_exists($destination)) {
                            unlink($destination);
                        }
                    }
                } else {
                    $error = "Erreur lors du téléchargement de l'image.";
                }
            }
        }
    }
}

$pageTitle = "Ajouter un animal | PetSitter's Market";
require_once 'includes/header.php';
?>

<main id="main-content" class="container" style="padding: 2rem 1rem; min-height: 70vh;">
    <div class="card" style="max-width: 600px; margin: 0 auto;">
        
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem;">
            <a href="my_pets.php" class="btn btn-text" style="padding: 0.5rem;"><i class="fas fa-arrow-left"></i></a>
            <h1 class="title-primary" style="margin: 0;">Ajouter un animal</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
            </div>
        <?php endif; ?>

        <form action="add_pet.php" method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="photo">Photo de l'animal <span style="color:red;">*</span></label>
                <input type="file" id="photo" name="photo" accept="image/jpeg, image/png, image/webp" required style="width: 100%; padding: 0.8rem; border: 1px solid rgba(0,0,0,0.1); border-radius: var(--border-radius-md);">
                <small style="color: #666; display: block; margin-top: 0.3rem;">Obligatoire. Max 5Mo (JPG, PNG, WEBP)</small>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="name">Nom de l'animal <span style="color:red;">*</span></label>
                <input type="text" id="name" name="name" value="<?php echo escapeOutput($_POST['name'] ?? ''); ?>" required placeholder="Ex: Rex, Luna...">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="species">Espèce <span style="color:red;">*</span></label>
                    <select id="species" name="species" required style="width: 100%; padding: 0.8rem; border: 1px solid rgba(0,0,0,0.1); border-radius: var(--border-radius-md); font-family: inherit;">
                        <option value="">Sélectionner...</option>
                        <option value="Chien" <?php echo (($_POST['species'] ?? '') === 'Chien') ? 'selected' : ''; ?>>Chien</option>
                        <option value="Chat" <?php echo (($_POST['species'] ?? '') === 'Chat') ? 'selected' : ''; ?>>Chat</option>
                        <option value="Oiseau" <?php echo (($_POST['species'] ?? '') === 'Oiseau') ? 'selected' : ''; ?>>Oiseau</option>
                        <option value="Rongeur" <?php echo (($_POST['species'] ?? '') === 'Rongeur') ? 'selected' : ''; ?>>Rongeur</option>
                        <option value="Reptile" <?php echo (($_POST['species'] ?? '') === 'Reptile') ? 'selected' : ''; ?>>Reptile</option>
                        <option value="Autre" <?php echo (($_POST['species'] ?? '') === 'Autre') ? 'selected' : ''; ?>>Autre</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="breed">Race <span style="color:red;">*</span></label>
                    <input type="text" id="breed" name="breed" value="<?php echo escapeOutput($_POST['breed'] ?? ''); ?>" required placeholder="Ex: Golden Retriever, Siamois...">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="age">Âge <span style="color:red;">*</span></label>
                <input type="text" id="age" name="age" value="<?php echo escapeOutput($_POST['age'] ?? ''); ?>" required placeholder="Ex: 3 ans, 6 mois...">
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="medical_notes">Notes médicales ou caractère (facultatif)</label>
                <textarea id="medical_notes" name="medical_notes" rows="3" placeholder="Allergies, peurs, habitudes spécifiques, médicaments..." style="width: 100%; padding: 0.8rem; border: 1px solid rgba(0,0,0,0.1); border-radius: var(--border-radius-md); font-family: inherit; resize: vertical;"><?php echo escapeOutput($_POST['medical_notes'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save"></i> Enregistrer l'animal</button>
        </form>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
