<?php
session_start();
require_once 'includes/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? 0;

    $user_id = $_SESSION['user_id'] ?? 1;
    $creation_date = date('Y-m-d H:i:s');
    $visibility = 1;

    if (!empty($title) && !empty($price)) {
        $req = $pdo->prepare("INSERT INTO post (Title, Description, Price, CreationDate, Visibility, User_userID) VALUES (?, ?, ?, ?, ?, ?)");
        $req->execute([$title, $description, $price, $creation_date, $visibility, $user_id]);

        header("Location: dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Ad | PetSitter's Market</title>
    <meta name="description" content="Find the perfect sitter for your beloved pet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
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

    <h1 style="text-align: center;">Post a Pet Care Ad</h1>
    <br>
    <p style="text-align: center;">Find the perfect sitter for your beloved pet </p>

    <main id="main-content">
        <section>
            <form class="middle-content" method="post" action="PostAd.php">
                <div id="number-one">
                    <h2>1. Select your pet</h2>
                    <span>Choose from your registered pets</span> <br>
                    <select name="pets" id="pets">
                        <option value="1">Rufus</option>
                        <option value="2">Mistigri</option>
                        <option value="3">Pollux</option>
                        <option value="4">Martin</option>
                    </select>
                </div>

                <div id="number-two">
                    <h2>2. Service Details</h2>
                    <label>Title of your Ad</label>
                    <input type="text" name="title" placeholder="Ex: Need a dog walker for Rufus" style="width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ddd;" required>

                    <label>Start and End Dates</label>
                    <div>
                        <input type="date" id="startdate" style="width: 49%; display: inline-block;">
                        <input type="date" id="enddate" style="width: 49%; display: inline-block;">
                    </div>

                    <label>Address</label> <br>
                    <input type="text" id="address" value="Address">

                    <p class="small-label">Type of service:</p>
                    <div class="service-type-row">
                        <button class="user-type-button" type="button" data-type="dog-walking"><span>🚶</span> <strong>Dog Walking</strong></button>
                        <button class="user-type-button" type="button" data-type="pet-sitting"><span>🏠</span> <strong>Pet Sitting</strong></button>
                        <button class="user-type-button" type="button" data-type="pet-boarding"><span>🛏️</span> <strong>Pet Boarding</strong></button>
                    </div>
                </div>

                <textarea name="description" rows="4" id="addinfo" placeholder="Describe any additional requirements..."></textarea>

                <div id="number-three">
                    <h2>3. Set your budget</h2>
                    <label>Payment offer and type</label>
                    <div>
                        <input type="number" name="price" style="width: 49%;" placeholder="Price" required>
                        <select name="paytype" id="paytype" style="width: 49%;"><option value="1">Card</option> <option value="2">Cash</option></select>
                    </div>
                </div>

                <button type="submit" class="cta-button" style="width: 100%; border: none; cursor: pointer;">Post My Ad</button>
            </form>
        </section>
    </main>

    <footer>
        <div class="footer-container">
            <div class="footer-col brand-col">
                <h2><i class="fas fa-paw"></i> Petsitter's Market</h2>
                <p>Connecting pet owners with caregiver.</p>
            </div>
        </div>
    </footer>
</body>
</html>