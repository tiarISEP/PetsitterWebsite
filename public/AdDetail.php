<?php
require_once 'includes/db.php';
require_once 'auth.php';

startSecureSession();

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

<?php
require_once 'includes/footer.php';
?>