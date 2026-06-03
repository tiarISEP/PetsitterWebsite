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
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Check if post exists and belongs to user
$stmtCheck = $pdo->prepare("SELECT * FROM post WHERE postID = ? AND User_userID = ?");
$stmtCheck->execute([$post_id, $user_id]);
$postData = $stmtCheck->fetch();

if (!$postData) {
    header("Location: my_ads.php");
    exit();
}

// Fetch current animal associated with this post
$stmtCurrentAnimal = $pdo->prepare("SELECT Animal_animalID FROM post_has_animal WHERE Post_postID = ?");
$stmtCurrentAnimal->execute([$post_id]);
$currentAnimalId = $stmtCurrentAnimal->fetchColumn();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? 0;
    
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $service_type = $_POST['service_type'] ?? '';
    $payment_type = $_POST['payment_type'] ?? '';
    
    $animal_id = $_POST['pets'] ?? 0;

    if (!empty($price) && !empty($animal_id)) {
        // If animal changed, update the photo URL
        if ($animal_id != $currentAnimalId) {
            $stmtPhoto = $pdo->prepare("SELECT photo_url FROM animal WHERE animalID = ?");
            $stmtPhoto->execute([$animal_id]);
            $animal = $stmtPhoto->fetch();
            $image_url = $animal ? $animal['photo_url'] : null;
            
            // Update relation
            $stmtUpdateAnimal = $pdo->prepare("UPDATE post_has_animal SET Animal_animalID = ? WHERE Post_postID = ?");
            $stmtUpdateAnimal->execute([$animal_id, $post_id]);
        } else {
            $image_url = $postData['image_url']; // keep existing
        }

        if (empty($title)) {
            $title = "Besoin de " . ($service_type ?: 'garde') . " pour mon animal";
        }

        $reqUpdate = $pdo->prepare("UPDATE post SET Title = ?, Description = ?, Price = ?, start_date = ?, end_date = ?, location = ?, service_type = ?, payment_type = ?, image_url = ? WHERE postID = ?");
        $reqUpdate->execute([$title, $description, $price, $start_date, $end_date, $location, $service_type, $payment_type, $image_url, $post_id]);

        header("Location: my_ads.php?success=1");
        exit();
    } else {
        $error = "Veuillez remplir les champs obligatoires.";
    }
}

// Fetch user's pets for the dropdown
$stmtPets = $pdo->prepare("SELECT animalID, Name, species, breed, photo_url FROM animal WHERE User_userID = ?");
$stmtPets->execute([$user_id]);
$userPets = $stmtPets->fetchAll();

// Prepare pets data for JS
$petsJson = [];
foreach ($userPets as $pet) {
    $petsJson[$pet['animalID']] = $pet['photo_url'];
}

$pageTitle = "Modifier l'annonce | PetSitter's Market";
require_once 'includes/header.php';
?>

<style>
    /* reusing styles from PostAd.php */
    .post-ad-page { background-color: #f2cb78; padding: 2rem 0; min-height: calc(100vh - 80px); }
    .ad-form-card { background: #ffffff; max-width: 800px; margin: 0 auto; border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    .form-section { margin-bottom: 2.5rem; }
    .section-title { display: flex; align-items: center; gap: 1rem; color: #6d4b29; font-size: 1.2rem; font-weight: 600; margin-bottom: 1.5rem; }
    .section-num { background-color: #5b5535; color: white; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 1rem; }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: #6d4b29; font-weight: 500; font-size: 0.95rem; }
    .form-control { width: 100%; padding: 0.8rem 1rem; border: 1px solid #e0d8c8; border-radius: 8px; font-family: inherit; font-size: 1rem; transition: border-color 0.2s; }
    .form-control:focus { outline: none; border-color: #c09040; }
    .input-row { display: flex; gap: 1.5rem; }
    .input-row > div { flex: 1; }
    
    .service-types { display: flex; gap: 1rem; flex-wrap: wrap; }
    .service-btn { flex: 1; background: white; border: 1px solid #e0d8c8; padding: 1rem; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; color: #6d4b29; font-weight: 500; transition: all 0.2s; }
    .service-btn i { color: #d18d4f; }
    .service-btn.active { border-color: #d18d4f; background-color: rgba(209, 141, 79, 0.05); box-shadow: 0 0 0 1px #d18d4f; }
    
    .pricing-tips { background-color: #fff8eb; border: 1px solid #f2e2cb; padding: 1.2rem; border-radius: 8px; margin-top: 1rem; color: #8c7359; font-size: 0.9rem; display: flex; gap: 0.8rem; }
    .pricing-tips i { color: #e59c53; font-size: 1.1rem; }
    
    .submit-btn { background-color: #5b5535; color: white; border: none; width: 100%; padding: 1.2rem; font-size: 1.1rem; font-weight: 600; border-radius: 8px; cursor: pointer; transition: background-color 0.2s; margin-top: 1rem; }
    .submit-btn:hover { background-color: #4a452a; }
    
    .pet-preview-box { margin-top: 1rem; text-align: center; }
    .pet-preview-box img { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #f2cb78; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
</style>

<div class="post-ad-page">
    <div style="max-width: 800px; margin: 0 auto; margin-bottom: 1rem;">
        <a href="my_ads.php" class="btn btn-text"><i class="fas fa-arrow-left"></i> Retour à mes annonces</a>
    </div>
    
    <main class="container">
        <form class="ad-form-card" method="post" action="edit_ad.php?id=<?php echo $post_id; ?>">
            
            <h1 style="color: #6d4b29; margin-bottom: 2rem; text-align: center;">Modifier l'annonce</h1>

            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 1.5rem;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo escapeOutput($error); ?>
                </div>
            <?php endif; ?>

            <!-- SECTION 1 -->
            <div class="form-section">
                <div class="section-title">
                    <div class="section-num">1</div>
                    Select Your Pet
                </div>
                
                <div class="form-group">
                    <select name="pets" id="pets" class="form-control" required>
                        <?php foreach ($userPets as $pet): ?>
                            <option value="<?php echo $pet['animalID']; ?>" <?php echo ($pet['animalID'] == $currentAnimalId) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pet['Name']) . ' - ' . htmlspecialchars($pet['breed']) . ' (' . htmlspecialchars($pet['species']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div class="pet-preview-box" id="petPreviewBox">
                        <img src="" id="petPreviewImg" alt="Pet preview">
                        <p style="margin-top: 0.5rem; color: #6d4b29; font-weight: 500;" id="petPreviewName"></p>
                    </div>
                </div>
            </div>

            <!-- SECTION 2 -->
            <div class="form-section">
                <div class="section-title">
                    <div class="section-num">2</div>
                    Service Details
                </div>
                
                <div class="form-group">
                    <label>Title of your Ad</label>
                    <input type="text" name="title" class="form-control" value="<?php echo escapeOutput($postData['Title']); ?>" required>
                </div>
                
                <div class="input-row form-group">
                    <div>
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo escapeOutput($postData['start_date']); ?>" required>
                    </div>
                    <div>
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo escapeOutput($postData['end_date']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control" value="<?php echo escapeOutput($postData['location']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Type of Service</label>
                    <input type="hidden" name="service_type" id="service_type_input" value="<?php echo escapeOutput($postData['service_type']); ?>" required>
                    <div class="service-types">
                        <button type="button" class="service-btn <?php echo ($postData['service_type'] == 'Dog Walking') ? 'active' : ''; ?>" data-value="Dog Walking">
                            <i class="fas fa-walking"></i> Dog Walking
                        </button>
                        <button type="button" class="service-btn <?php echo ($postData['service_type'] == 'Pet Sitting') ? 'active' : ''; ?>" data-value="Pet Sitting">
                            <i class="fas fa-home"></i> Pet Sitting
                        </button>
                        <button type="button" class="service-btn <?php echo ($postData['service_type'] == 'Pet Boarding') ? 'active' : ''; ?>" data-value="Pet Boarding">
                            <i class="fas fa-bed"></i> Pet Boarding
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Additional Details</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo escapeOutput($postData['Description']); ?></textarea>
                </div>
            </div>

            <!-- SECTION 3 -->
            <div class="form-section" style="margin-bottom: 1.5rem;">
                <div class="section-title">
                    <div class="section-num">3</div>
                    Set Your Budget
                </div>
                
                <div class="input-row form-group">
                    <div>
                        <label>Offered Amount</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #888;">$</span>
                            <input type="number" name="price" class="form-control" value="<?php echo escapeOutput($postData['Price']); ?>" style="padding-left: 2rem;" required>
                        </div>
                    </div>
                    <div>
                        <label>Payment Type</label>
                        <select name="payment_type" class="form-control">
                            <option value="Per Day" <?php echo ($postData['payment_type'] == 'Per Day') ? 'selected' : ''; ?>>Per Day</option>
                            <option value="Per Hour" <?php echo ($postData['payment_type'] == 'Per Hour') ? 'selected' : ''; ?>>Per Hour</option>
                            <option value="Total" <?php echo ($postData['payment_type'] == 'Total') ? 'selected' : ''; ?>>Total</option>
                        </select>
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn"><i class="fas fa-save" style="margin-right: 0.5rem;"></i> Sauvegarder les modifications</button>
        </form>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Pet Photo Preview Logic
        const petsData = <?php echo json_encode($petsJson); ?>;
        const petSelect = document.getElementById('pets');
        const previewImg = document.getElementById('petPreviewImg');
        const previewName = document.getElementById('petPreviewName');

        function updatePreview() {
            const petId = petSelect.value;
            if (petId && petsData[petId]) {
                previewImg.src = petsData[petId];
                previewName.textContent = petSelect.options[petSelect.selectedIndex].text.split(' - ')[0];
            }
        }
        
        // Initial load
        updatePreview();
        
        petSelect.addEventListener('change', updatePreview);

        // Service Type Selection Logic
        const serviceBtns = document.querySelectorAll('.service-btn');
        const serviceInput = document.getElementById('service_type_input');

        serviceBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                serviceBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                serviceInput.value = this.getAttribute('data-value');
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>
