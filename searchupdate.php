<?php
// search_ads.php

require_once 'auth.php';
require_once 'includes/db.php';

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
    postID,
    Title,
    Description,
    Price,
    CreationDate,
    Visibility,
    Applicability,
    User_userID
FROM post
WHERE (
    Title LIKE ?
    OR Description LIKE ?
    OR Applicability LIKE ?
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

// Add applicability filters if selected
if (!empty($filters)) {

    $placeholders = implode(',', array_fill(0, count($filters), '?'));

    $sql .= " AND Applicability IN ($placeholders)";

    foreach ($filters as $filter) {
        $params[] = $filter;
        $types .= "s";
    }
}

$sql .= " AND Visibility = 1";

$sql .= " ORDER BY CreationDate DESC";

// -------------------------------
// PREPARE & EXECUTE
// -------------------------------

$stmt = $conn->prepare($sql);

$stmt->bind_param($types, ...$params);

$stmt->execute();

$result = $stmt->get_result();

$pageTitle = "Post Search| PetSitter's Market";
require_once 'includes/header.php';
?>

</head>

<body>

<div class="container">

    <h1>Pet Care Ads</h1>

    <form class="search-bar" method="GET">

        <input
            type="text"
            name="search"
            placeholder="Search by title or keyword..."
            value="<?php echo escapeOutput($search); ?>"
        >

        <button type="submit">Search</button>

        <br>

        <input
            type="checkbox"
            id="walking"
            name="Walking"
            value="Walk"
            <?php if(isset($_GET['Walking'])) echo "checked"; ?>
        >

        <label for="walking">Walking</label>

        <input
            type="checkbox"
            id="sitting"
            name="Sitting"
            value="Sit"
            <?php if(isset($_GET['Sitting'])) echo "checked"; ?>
        >

        <label for="sitting">Sitting</label>

    </form>

    <div class="ads-grid">

    <?php

    if ($result->num_rows > 0) {

        while ($row = $result->fetch_assoc()) {

            $applicabilityClass = strtolower($row['Applicability']);

            echo "<div class='ad-card'>";

            // Applicability badge
            echo "<div class='service-type {$applicabilityClass}'>";
            echo ucfirst(escapeOutput($row['Applicability']));
            echo "</div>";

            // Title
            echo "<h2 class='title'>";
            echo escapeOutput($row['Title']);
            echo "</h2>";

            // Description
            echo "<div class='description'>";
            echo nl2br(escapeOutput($row['Description']));
            echo "</div>";

            // Price
            echo "<div class='price'>";
            echo "$" . number_format($row['Price'], 2);
            echo "</div>";

            // Creation date
            echo "<div class='info'>";
            echo "<strong>Posted:</strong> "
                . date("d M Y H:i", strtotime($row['CreationDate']));
            echo "</div>";

            // User ID
            echo "<div class='info'>";
            echo "<strong>User ID:</strong> "
                . escapeOutput($row['User_userID']);
            echo "</div>";

            echo "</div>";
        }

    } else {

        echo "<div class='no-results'>No posts found.</div>";
    }

    $stmt->close();
    $conn->close();

    ?>

    </div>

</div>

</body>

<?php require_once 'includes/footer.php'; ?>

</html>