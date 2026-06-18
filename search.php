<?php
// search_ads.php

// -------------------------------
// DATABASE CONNECTION
// -------------------------------
$host = "localhost";
$dbname = "database";
$username = "root";
$password = "root"; // Use "" for XAMPP, "root" for MAMP
$port = 8889;       // Remove/change for XAMPP

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// -------------------------------
// SEARCH INPUT
// -------------------------------
$search = "";

$filters = [];

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Check selected filters
if (isset($_GET['Walking'])) {
    $filters[] = "walking";
}

if (isset($_GET['Sitting'])) {
    $filters[] = "sitting";
}


// -------------------------------
// SQL QUERY
// -------------------------------

$sql = "
SELECT 
    id,
    service_type,
    location,
    start_time,
    end_time,
    price_offered,
    status,
    description,
    created_at
FROM ads
WHERE (
    location LIKE ?
    OR service_type LIKE ?
    OR description LIKE ?
)
";

$params = [];
$types = "";

// Search keyword
$keyword = "%" . $search . "%";

$params[] = $keyword;
$params[] = $keyword;
$params[] = $keyword;

$types .= "sss";

// Add service type filters if selected
if (!empty($filters)) {

    $placeholders = implode(',', array_fill(0, count($filters), '?'));

    $sql .= " AND service_type IN ($placeholders)";

    foreach ($filters as $filter) {
        $params[] = $filter;
        $types .= "s";
    }
}

$sql .= " ORDER BY created_at DESC";

// Prepare statement
$stmt = $conn->prepare($sql);

// Bind parameters dynamically
$stmt->bind_param($types, ...$params);

$stmt->execute();

$result = $stmt->get_result();

require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Pet Service Ads</title>

<style>
.container {
    max-width: 1000px;
    margin: auto;
}


.search-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
}

.search-bar input {
    flex: 1;
    padding: 14px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 16px;
}

.search-bar button {
    padding: 14px 24px;
    border: none;
    background: #4CAF50;
    color: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
}

.search-bar button:hover {
    background: #3f9142;
}

.ads-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.ad-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    transition: transform 0.2s ease;
}

.ad-card:hover {
    transform: translateY(-3px);
}

.service-type {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 30px;
    color: white;
    font-size: 14px;
    margin-bottom: 15px;
}

.sitting {
    background: #2196F3;
}

.walking {
    background: #FF9800;
}

.location {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}

.description {
    color: #555;
    line-height: 1.5;
    margin-bottom: 15px;
}

.info {
    font-size: 14px;
    color: #777;
    margin-bottom: 8px;
}

.price {
    font-size: 22px;
    color: #4CAF50;
    font-weight: bold;
    margin-top: 15px;
}

.status {
    margin-top: 10px;
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 13px;
    text-transform: uppercase;
}

.no-results {
    text-align: center;
    color: #888;
    margin-top: 50px;
    font-size: 18px;
}

</style>
</head>

<body>

<div class="container">

    <h1>Pet Care Ads</h1>

    <form class="search-bar" method="GET">
        <input 
            type="text" 
            name="search"
            placeholder="Search by city, service or keyword..."
            value="<?php echo htmlspecialchars($search); ?>"
        >

        <button type="submit">Search</button> <br>
        <input type="checkbox" id="walking" name="Walking" value="Walk" 
        <?php if(isset($_GET['Walking'])) echo "checked"; ?>>
        <label for="walking"> Walking</label>
        <input type="checkbox" id="sitting" name="Sitting" value="Sit" 
        <?php if(isset($_GET['Sitting'])) echo "checked"; ?>>
        <label for="sitting"> Sitting</label><br>

    </form>

    <div class="ads-grid">

    <?php

    if ($result->num_rows > 0) {

        while ($row = $result->fetch_assoc()) {

            $serviceClass = $row['service_type'];

            echo "<div class='ad-card'>";

            echo "<div class='service-type {$serviceClass}'>";

            echo ucfirst(htmlspecialchars($row['service_type']));

            echo "</div>";

            echo "<div class='location'>";

            echo htmlspecialchars($row['location']);

            echo "</div>";

            echo "<div class='description'>";

            echo nl2br(htmlspecialchars($row['description']));

            echo "</div>";

            echo "<div class='info'>";

            echo "<strong>Start:</strong> " . date("d M Y H:i", strtotime($row['start_time']));

            echo "</div>";

            echo "<div class='info'>";

            echo "<strong>End:</strong> " . date("d M Y H:i", strtotime($row['end_time']));

            echo "</div>";

            echo "<div class='price'>";

            echo "$" . number_format($row['price_offered'], 2);

            echo "</div>";

            echo "<div class='status'>";

            echo htmlspecialchars($row['status']);

            echo "</div>";

            echo "</div>";
        }

    } else {

        echo "<div class='no-results'>No ads found.</div>";
    }

    $stmt->close();
    $conn->close();

    ?>

    </div>

</div>

</body>
<?php require_once 'includes/footer.php';
 ?>
</html>
