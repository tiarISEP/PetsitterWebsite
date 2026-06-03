<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

$postID = $_GET['id'] ?? 0;

$req = $pdo->prepare("
    SELECT p.*, u.username, a.Name as animal_name
    FROM post p
    JOIN users u ON p.User_userID = u.id
    LEFT JOIN post_has_animal pha ON p.postID = pha.Post_postID
    LEFT JOIN animal a ON pha.Animal_animalID = a.animalID
    WHERE p.postID = ?
");
$req->execute([$postID]);
$annonce = $req->fetch();

if (!$annonce) {
    die("<h2 style='text-align:center; margin-top:50px;'>Ad not found.</h2>");
}

$is_sitter = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'pet-sitter');
$user_id = $_SESSION['user_id'] ?? null;
$error = '';
$success = '';

// Handle Apply Submission on AdDetail.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply') {
    if (!isUserLoggedIn()) {
        header("Location: login.php");
        exit();
    }
    
    if ($_SESSION['user_type'] !== 'pet-sitter') {
        $error = "Seuls les pet sitters peuvent postuler à des offres.";
    } elseif (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Erreur de sécurité (CSRF). Veuillez rafraîchir la page et réessayer.";
    } else {
        // Check if already applied
        $stmtCheck = $pdo->prepare("SELECT appID FROM application WHERE User_userID = ? AND Post_postID = ?");
        $stmtCheck->execute([$user_id, $postID]);
        if ($stmtCheck->fetch()) {
            $error = "Vous avez déjà candidaté à cette offre.";
        } else {
            try {
                $stmtInsert = $pdo->prepare("INSERT INTO application (CreationDate, Status, User_userID, Post_postID) VALUES (NOW(), 'Pending', ?, ?)");
                $stmtInsert->execute([$user_id, $postID]);
                $success = "Votre candidature a été envoyée avec succès !";
            } catch (PDOException $e) {
                $error = "Une erreur est survenue lors de l'envoi de votre candidature.";
            }
        }
    }
}

$has_applied = false;
if ($is_sitter && $user_id) {
    $stmtCheck = $pdo->prepare("SELECT appID FROM application WHERE User_userID = ? AND Post_postID = ?");
    $stmtCheck->execute([$user_id, $postID]);
    $has_applied = (bool)$stmtCheck->fetch();
}

$pageTitle = "Ad Details | PetSitter's Market";
require_once 'includes/header.php';
?>
<style>
    .whitebox { border-radius: 20px; background-color: white; margin: 20px; padding: 20px; box-shadow: 5px 10px 15px rgba(0, 0, 0, 0.3); }
    .orangebox { border-radius: 20px; color: white; background-color: rgb(255, 179, 0); margin: 20px; padding: 20px; box-shadow: 5px 10px 15px rgba(0, 0, 0, 0.3); }
    .orangebox:hover { position: relative; top: 7px; border-radius: 20px; color: rgb(180, 180, 180); background-color: rgb(139, 98, 0); margin: 20px; padding: 20px; box-shadow: 5px 10px 15px rgba(0, 0, 0, 0.3); }
</style>

    <main id="main-content">
        <div id="left side" style="width:10%;display:inline-block"></div>

        <div id="middle" style="width:80%;display:inline-block">
            <div id="topper part">
                <h1><?php echo htmlspecialchars($annonce['Title']); ?></h1>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" style="margin: 10px 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin: 10px 20px;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <div id="top part">
                <div id="pictures" class="whitebox" style="width:45%;display:inline-block; vertical-align:top; text-align:center;">
                    <strong>Pictures</strong> <br><br>
                    <?php if (!empty($annonce['image_url'])): ?>
                        <img style="max-width:100%; max-height:300px; border-radius:10px;" src="<?php echo htmlspecialchars($annonce['image_url']); ?>" alt="Pet Ad Image">
                    <?php else: ?>
                        <img width="400" height="300" src="https://upload.wikimedia.org/wikipedia/commons/9/99/Brooks_Chase_Ranger_of_Jolly_Dogs_Jack_Russell.jpg" alt="dog placeholder" style="border-radius:10px;">
                    <?php endif; ?>
                </div>

                <div id="main information" style="width:45%;display:inline-block; vertical-align:top;">
                    <div id="animal" class="whitebox">
                        <strong>Pet Information</strong><br>
                        Name: <?php echo htmlspecialchars($annonce['animal_name'] ?? 'Not specified'); ?>
                    </div>

                    <div id="mission details" class="whitebox">
                        <strong>Mission Details:</strong><br>
                        <?php echo htmlspecialchars($annonce['Description']); ?><br><br>
                        <strong>Budget Offered:</strong> <?php echo htmlspecialchars($annonce['Price']); ?> $
                    </div>

                    <div id="pet owner" class="whitebox">
                        <strong>Posted by:</strong> <?php echo htmlspecialchars($annonce['username']); ?>
                    </div>

                    <?php if ($has_applied): ?>
                        <div class="orangebox" style="background-color: #a3a3a3; cursor: not-allowed; text-align: center; border-radius: 20px; color: white; padding: 20px; box-shadow: 5px 10px 15px rgba(0, 0, 0, 0.3);">
                            <b>Already Applied</b>
                        </div>
                    <?php elseif (!$is_sitter): ?>
                        <a href="login.php" style="text-decoration: none;">
                            <div class="orangebox" style="cursor: pointer; text-align: center;"> <b>Log in as Sitter to Apply</b></div>
                        </a>
                    <?php else: ?>
                        <form method="POST" action="AdDetail.php?id=<?php echo $postID; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="action" value="apply">
                            <button type="submit" style="background: none; border: none; padding: 0; width: 100%; font-family: inherit; font-size: inherit; text-align: inherit; color: inherit;">
                                <div class="orangebox" style="cursor: pointer; text-align: center; margin: 20px 0;"> <b>Apply now ==></b></div>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div id="bottom part">
                <div id="about" style="width:20%;display:inline-block" class="whitebox">About <?php echo htmlspecialchars($annonce['username']); ?></div>
                <div id="requirements" style="width:20%;display:inline-block" class="whitebox">Requirements</div>
                <div id="included" style="width:20%;display:inline-block" class="whitebox">Included</div>
            </div>
        </div>

        <div id="right side" style="width:10%;display:inline-block"></div>
    </main>

<?php
require_once 'includes/footer.php';
?>