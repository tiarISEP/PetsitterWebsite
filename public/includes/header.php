    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? "PetSitter's Market"; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <div class="logo">
            <a href="index.php" class="logo-link">PetSitter's Market</a>
        </div>
        <nav aria-label="Main navigation">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="#">Find Sitters</a></li> <li><a href="#">About</a></li> <li><a href="ContactUs.php">Contact</a></li>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li><a href="messages.php" class="btn-text">Messages</a></li>
                        <li><a href="profile.php" class="btn-text">My Profile</a></li>
                        <li><a href="logout.php" class="btn btn-cta">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="btn btn-text">Login</a></li>
                        <li><a href="signup.php" class="btn btn-cta">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
    </header>
