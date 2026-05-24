<?php
require_once 'includes/db.php';

$postID = $_GET['id'] ?? 0;

$req = $pdo->prepare("
    SELECT p.*, u.username
    FROM post p
    JOIN users u ON p.User_userID = u.id
    WHERE p.postID = ?
");
$req->execute([$postID]);
$annonce = $req->fetch();

if (!$annonce) {
    die("<h2 style='text-align:center; margin-top:50px;'>Ad not found.</h2>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Details | PetSitter's Market</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<style>
    .whitebox { border-radius: 20px; background-color: white; margin: 20px; padding: 20px; box-shadow: 5px 10px 15px rgba(0, 0, 0, 0.3); }
    .orangebox { border-radius: 20px; color: white; background-color: rgb(255, 179, 0); margin: 20px; padding: 20px; box-shadow: 5px 10px 15px rgba(0, 0, 0, 0.3); }
    .orangebox:hover { position: relative; top: 7px; border-radius: 20px; color: rgb(180, 180, 180); background-color: rgb(139, 98, 0); margin: 20px; padding: 20px; box-shadow: 5px 10px 15px rgba(0, 0, 0, 0.3); }
</style>
<body>
    <a href="#main-content" class="skip-link" style="position: absolute; left: -9999px;">Aller au contenu principal</a>

    <header>
        <div class="logo">
            <a href="index.html" style="text-decoration: none; color: inherit;">PetSitter's Market</a>
        </div>
        <nav aria-label="Navigation principale">
            <ul>
                <li><a href="index.html">Home</a></li>
                <li><a href="services.html">Services</a></li>
                <li><a href="contact.html">Contact</a></li>
                <li><a href="login.html" style="font-weight: 500; color: #772f1a; padding: 0.5rem 1rem;">Login</a></li>
                <li><a href="signup.html" style="background-color: #585123; color: white; padding: 0.5rem 1.5rem; border-radius: 8px; font-weight: 500; text-decoration: none;">Sign Up</a></li>
            </ul>
        </nav>
    </header>

    <main id="main-content">
        <div id="left side" style="width:10%;display:inline-block"></div>

        <div id="middle" style="width:80%;display:inline-block">
            <div id="topper part">
                <h1><?php echo htmlspecialchars($annonce['Title']); ?></h1>
            </div>

            <div id="top part">
                <div id="pictures" class="whitebox" style="width:45%;display:inline-block">
                    Pictures <br>
                    <img width="400" height="300" src="https://upload.wikimedia.org/wikipedia/commons/9/99/Brooks_Chase_Ranger_of_Jolly_Dogs_Jack_Russell.jpg" alt="dog">
                </div>

                <div id="main information" style="width:45%;display:inline-block">
                    <div id="animal" class="whitebox">
                        <strong>Pet Information</strong><br>
                        Registered pet tied to the task
                    </div>

                    <div id="mission details" class="whitebox">
                        <strong>Mission Details:</strong><br>
                        <?php echo htmlspecialchars($annonce['Description']); ?><br><br>
                        <strong>Budget Offered:</strong> <?php echo htmlspecialchars($annonce['Price']); ?> $
                    </div>

                    <div id="pet owner" class="whitebox">
                        <strong>Posted by:</strong> <?php echo htmlspecialchars($annonce['username']); ?>
                    </div>

                    <div id="apply now" class="orangebox" style="cursor: pointer;"> <b>Apply now ==></b></div>
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

    <footer>
        <div class="footer-container">
            <div class="footer-col brand-col">
                <h2><i class="fas fa-paw"></i> Petsitter's Market</h2>
                <p>&copy; 2026 Petsitter's Market. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>