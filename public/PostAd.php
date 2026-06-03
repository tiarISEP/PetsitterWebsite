<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? 0;
    
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $location = trim($_POST['location'] ?? '');
    $service_type = $_POST['service_type'] ?? '';
    $payment_type = $_POST['payment_type'] ?? '';

    $user_id = $_SESSION['user_id'] ?? 1;
    $creation_date = date('Y-m-d H:i:s');
    $visibility = 1;
    $animal_id = $_POST['pets'] ?? 0;

    if (!empty($price) && !empty($animal_id)) {
        // Fetch the selected animal's photo URL to use as the ad's image
        $stmtPhoto = $pdo->prepare("SELECT photo_url FROM animal WHERE animalID = ?");
        $stmtPhoto->execute([$animal_id]);
        $animal = $stmtPhoto->fetch();
        $image_url = $animal ? $animal['photo_url'] : null;

        // If title is empty but we have a service type and animal name, we can auto-generate it or use a default.
        if (empty($title)) {
            $title = "Besoin de " . ($service_type ?: 'garde') . " pour mon animal";
        }

        $req = $pdo->prepare("INSERT INTO post (Title, Description, Price, start_date, end_date, location, service_type, payment_type, CreationDate, Visibility, User_userID, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $req->execute([$title, $description, $price, $start_date, $end_date, $location, $service_type, $payment_type, $creation_date, $visibility, $user_id, $image_url]);

        $postID = $pdo->lastInsertId();

        $reqAnimal = $pdo->prepare("INSERT INTO post_has_animal (Post_postID, Animal_animalID) VALUES (?, ?)");
        $reqAnimal->execute([$postID, $animal_id]);

        header("Location: profile.php");
        exit();
    }
}

// Fetch user's pets
$user_id = $_SESSION['user_id'] ?? 1;
$stmtPets = $pdo->prepare("SELECT animalID, Name, species, breed, photo_url FROM animal WHERE User_userID = ?");
$stmtPets->execute([$user_id]);
$userPets = $stmtPets->fetchAll();

// Prepare pets data for JS
$petsJson = [];
foreach ($userPets as $pet) {
    $petsJson[$pet['animalID']] = $pet['photo_url'];
}

$pageTitle = "Post Ad | PetSitter's Market";
require_once 'includes/header.php';
?>

<style>
    .post-ad-page {
        background-color: #f2cb78; /* Matching the mockup background */
        padding: 2rem 0;
        min-height: calc(100vh - 80px);
    }
    .post-ad-header {
        text-align: center;
        margin-bottom: 2rem;
        color: #6d4b29;
    }
    .post-ad-header h1 {
        font-size: 2.2rem;
        margin-bottom: 0.5rem;
    }
    .post-ad-header p {
        font-size: 1.1rem;
        opacity: 0.8;
    }
    .ad-form-card {
        background: #ffffff;
        max-width: 800px;
        margin: 0 auto;
        border-radius: 12px;
        padding: 2.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .form-section {
        margin-bottom: 2.5rem;
    }
    .section-title {
        display: flex;
        align-items: center;
        gap: 1rem;
        color: #6d4b29;
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
    }
    .section-num {
        background-color: #5b5535;
        color: white;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-size: 1rem;
    }
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: #6d4b29;
        font-weight: 500;
        font-size: 0.95rem;
    }
    .form-control {
        width: 100%;
        padding: 0.8rem 1rem;
        border: 1px solid #e0d8c8;
        border-radius: 8px;
        font-family: inherit;
        font-size: 1rem;
        transition: border-color 0.2s;
    }
    .form-control:focus {
        outline: none;
        border-color: #c09040;
    }
    .input-row {
        display: flex;
        gap: 1.5rem;
    }
    .input-row > div {
        flex: 1;
    }
    
    /* Service Type Buttons */
    .service-types {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .service-btn {
        flex: 1;
        background: white;
        border: 1px solid #e0d8c8;
        padding: 1rem;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        color: #6d4b29;
        font-weight: 500;
        transition: all 0.2s;
    }
    .service-btn i {
        color: #d18d4f;
    }
    .service-btn.active {
        border-color: #d18d4f;
        background-color: rgba(209, 141, 79, 0.05);
        box-shadow: 0 0 0 1px #d18d4f;
    }
    
    .pricing-tips {
        background-color: #fff8eb;
        border: 1px solid #f2e2cb;
        padding: 1.2rem;
        border-radius: 8px;
        margin-top: 1rem;
        color: #8c7359;
        font-size: 0.9rem;
        display: flex;
        gap: 0.8rem;
    }
    .pricing-tips i {
        color: #e59c53;
        font-size: 1.1rem;
    }
    
    .submit-btn {
        background-color: #5b5535;
        color: white;
        border: none;
        width: 100%;
        padding: 1.2rem;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.2s;
        margin-top: 1rem;
    }
    .submit-btn:hover {
        background-color: #4a452a;
    }
    
    .legal-text {
        text-align: center;
        font-size: 0.8rem;
        color: #999;
        margin-top: 1rem;
    }
    
    .pet-preview-box {
        margin-top: 1rem;
        text-align: center;
        display: none;
    }
    .pet-preview-box img {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #f2cb78;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
</style>

<div class="post-ad-page">
    <div class="post-ad-header">
        <h1>Post a Pet Care Ad</h1>
        <p>Find the perfect sitter for your beloved pet</p>
    </div>

    <main class="container">
        <form class="ad-form-card" method="post" action="PostAd.php">
            
            <!-- SECTION 1 -->
            <div class="form-section">
                <div class="section-title">
                    <div class="section-num">1</div>
                    Select Your Pet
                </div>
                
                <div class="form-group">
                    <label>Choose from your registered pets</label>
                    <?php if (count($userPets) > 0): ?>
                        <select name="pets" id="pets" class="form-control" required>
                            <option value="">Select a pet...</option>
                            <?php foreach ($userPets as $pet): ?>
                                <option value="<?php echo $pet['animalID']; ?>">
                                    <?php echo htmlspecialchars($pet['Name']) . ' - ' . htmlspecialchars($pet['breed']) . ' (' . htmlspecialchars($pet['species']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="pet-preview-box" id="petPreviewBox">
                            <img src="" id="petPreviewImg" alt="Pet preview">
                            <p style="margin-top: 0.5rem; color: #6d4b29; font-weight: 500;" id="petPreviewName"></p>
                        </div>
                    <?php else: ?>
                        <select class="form-control" disabled>
                            <option>No pets available</option>
                        </select>
                    <?php endif; ?>
                    
                    <div style="margin-top: 0.8rem; font-size: 0.9rem; color: #888;">
                        Don't see your pet? <a href="add_pet.php" style="color: #6d4b29; text-decoration: underline;">Add a new pet</a> to your profile first.
                    </div>
                </div>
            </div>

            <!-- SECTION 2 -->
            <div class="form-section">
                <div class="section-title">
                    <div class="section-num">2</div>
                    Service Details
                </div>
                
                <div class="input-row form-group">
                    <div>
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div>
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control" placeholder="Enter your address or neighborhood" required>
                </div>
                
                <div class="form-group">
                    <label>Type of Service</label>
                    <input type="hidden" name="service_type" id="service_type_input" value="" required>
                    <div class="service-types">
                        <button type="button" class="service-btn" data-value="Dog Walking">
                            <i class="fas fa-walking"></i> Dog Walking
                        </button>
                        <button type="button" class="service-btn" data-value="Pet Sitting">
                            <i class="fas fa-home"></i> Pet Sitting
                        </button>
                        <button type="button" class="service-btn" data-value="Pet Boarding">
                            <i class="fas fa-bed"></i> Pet Boarding
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Additional Details</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Describe any special requirements, instructions, or information about your pet..."></textarea>
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
                            <input type="number" name="price" class="form-control" placeholder="0.00" style="padding-left: 2rem;" required>
                        </div>
                    </div>
                    <div>
                        <label>Payment Type</label>
                        <select name="payment_type" class="form-control">
                            <option value="Per Day">Per Day</option>
                            <option value="Per Hour">Per Hour</option>
                            <option value="Total">Total</option>
                        </select>
                    </div>
                </div>
                
                <div class="pricing-tips">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Pricing Tips</strong><br>
                        Consider the complexity of care, duration of service, and local market rates. Most dog walking services range from $15-30 per day.
                    </div>
                </div>
            </div>

            <button type="submit" class="submit-btn"><i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i> Post My Ad</button>
            <p class="legal-text">By posting, you agree to our <a href="CGU.php" style="color:inherit; text-decoration:underline;">Terms of Service</a> and <a href="CGU.php" style="color:inherit; text-decoration:underline;">Privacy Policy</a></p>
        </form>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Pet Photo Preview Logic
        const petsData = <?php echo json_encode($petsJson); ?>;
        const petSelect = document.getElementById('pets');
        const previewBox = document.getElementById('petPreviewBox');
        const previewImg = document.getElementById('petPreviewImg');
        const previewName = document.getElementById('petPreviewName');

        petSelect.addEventListener('change', function() {
            const petId = this.value;
            if (petId && petsData[petId]) {
                previewImg.src = petsData[petId];
                previewName.textContent = this.options[this.selectedIndex].text.split(' - ')[0]; // Extract just the name
                previewBox.style.display = 'block';
            } else {
                previewBox.style.display = 'none';
            }
        });

        // Service Type Selection Logic
        const serviceBtns = document.querySelectorAll('.service-btn');
        const serviceInput = document.getElementById('service_type_input');

        serviceBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all
                serviceBtns.forEach(b => b.classList.remove('active'));
                // Add active class to clicked
                this.classList.add('active');
                // Update hidden input
                serviceInput.value = this.getAttribute('data-value');
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>