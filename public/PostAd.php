<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

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

$pageTitle = "Post Ad | PetSitter's Market";
require_once 'includes/header.php';
?>

    <h1 style="text-align: center; margin-top: 2rem;">Post a Pet Care Ad</h1>
    <p style="text-align: center; color: #666;">Find the perfect sitter for your beloved pet</p>

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

                <button type="submit" class="cta-button" style="width: 100%; border: none; cursor: pointer; margin-top: 1rem;">Post My Ad</button>
            </form>
        </section>
    </main>

<?php
require_once 'includes/footer.php';
?>