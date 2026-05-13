<?php 
require_once 'includes/auth.php';
startSecureSession();

$pageTitle = "Home | PetSitter's Market";
require_once 'includes/header.php'; 
?>

<main id="main-content">
    <section class="hero">
        <div class="hero-content">
            <h1>Find the perfect pet sitter</h1>
            <p>Connect with trusted, loving pet sitters in your neighborhood.</p>
            
            <div class="search-bar card">
                <form action="#" method="GET" class="search-form">
                    <div class="input-group">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" name="location" placeholder="Where are you looking?">
                    </div>
                    <div class="input-group">
                        <i class="fas fa-calendar-alt"></i>
                        <input type="date" name="date">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="services-section container">
        <h2 class="title-primary">Our Pet Care Services</h2>
        <p class="text-subtitle">Professional care tailored to your pet's needs</p>
        
        <div class="grid-layout">
            <div class="card text-center">
                <i class="fas fa-home fa-3x mb-1"></i>
                <h3>Pet Sitting</h3>
                <p>In-home care while you're away</p>
            </div>
            <div class="card text-center">
                <i class="fas fa-dog fa-3x mb-1"></i>
                <h3>Dog Walking</h3>
                <p>Daily exercise and outdoor fun</p>
            </div>
            <div class="card text-center">
                <i class="fas fa-cut fa-3x mb-1"></i>
                <h3>Pet Grooming</h3>
                <p>Professional grooming services</p>
            </div>
            <div class="card text-center">
                <i class="fas fa-stethoscope fa-3x mb-1"></i>
                <h3>Vet Visits</h3>
                <p>Transportation to appointments</p>
            </div>
        </div>
    </section>

    <section class="sitters-section container">
        <h2 class="title-primary">Top Rated Pet Sitters</h2>
        <div class="grid-layout">
            <div class="card sitter-card">
                <div class="sitter-img"></div>
                <h3>Sarah Johnson</h3>
                <p class="rating">★★★★★ 5.0 (127 reviews)</p>
                <p class="price">$25/day</p>
                <a href="#" class="btn btn-primary">Book Now</a>
            </div>
            <div class="card sitter-card">
                <div class="sitter-img"></div>
                <h3>Mike Chen</h3>
                <p class="rating">★★★★★ 4.9 (89 reviews)</p>
                <p class="price">$30/day</p>
                <a href="#" class="btn btn-primary">Book Now</a>
            </div>
            <div class="card sitter-card">
                <div class="sitter-img"></div>
                <h3>Emma Davis</h3>
                <p class="rating">★★★★★ 4.8 (156 reviews)</p>
                <p class="price">$20/day</p>
                <a href="#" class="btn btn-primary">Book Now</a>
            </div>
        </div>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>
