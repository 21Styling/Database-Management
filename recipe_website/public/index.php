<?php
// Attempt to include the database connection file
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo

// Define a title for the page
$pageTitle = "Recipe Website - Home";

// --- Fetch Newest Recipes WITH IMAGES ---
$newestRecipes = [];
$newestRecipesError = null; // Variable to hold potential errors
try {
    // Fetch latest 5 recipes that have an image URL
    $sqlNewest = "SELECT RecipeId, Recipe_Name, Average_Rating, Image_URL
                  FROM Recipes
                  WHERE Recipe_Name IS NOT NULL AND Recipe_Name != ''
                    AND Image_URL IS NOT NULL           -- Added: Must not be NULL
                    AND Image_URL != ''                 -- Added: Must not be empty string
                    AND Image_URL != 'character(0)'     -- Added: Must not be 'character(0)'
                  ORDER BY RecipeId DESC
                  LIMIT 5";
    $stmtNewest = $pdo->prepare($sqlNewest);
    $stmtNewest->execute();
    $newestRecipes = $stmtNewest->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log error and set a user-friendly message if query fails
    error_log("Database Query Error (Newest Recipes): " . $e->getMessage());
    $newestRecipesError = "Could not load newest recipes.";
}

$topCategories = [];
$categoriesError = null; // Variable to hold potential errors
try {
    $sqlCategories = "SELECT Category, COUNT(*) as recipe_count
                      FROM Recipes
                      WHERE Category IS NOT NULL
                        AND Category != ''
                        AND Category NOT LIKE '< %'
                        AND Category NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}T'
                        AND Category != '0'
                      GROUP BY Category
                      ORDER BY recipe_count DESC
                      LIMIT 20";
    $stmtCategories = $pdo->prepare($sqlCategories);
    $stmtCategories->execute();
    $topCategories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Query Error (Categories): " . $e->getMessage());
    $categoriesError = "Could not load recipe categories.";
}

/**
 * Function to extract the first valid image URL from the stored string.
 */
function extractFirstImageUrl($imageUrlString) {
    if (empty($imageUrlString) || $imageUrlString === 'character(0)') { return null; }
    $trimmedUrl = trim($imageUrlString, ' "');
    if (filter_var($trimmedUrl, FILTER_VALIDATE_URL)) { return $trimmedUrl; }
    if (preg_match('/^c?\("([^"]+)"/', $imageUrlString, $matches)) {
        $potentialUrl = $matches[1];
        if (filter_var($potentialUrl, FILTER_VALIDATE_URL)) { return $potentialUrl; }
    }
    return null;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

    <header class="site-header">
        <h1>Welcome to the Recipe Website!</h1>
        <p>Find delicious recipes for every occasion.</p>
    </header>

    <main class="container">
        <section class="home-section">
            <h2>Newest Recipes (with Images)</h2> <?php /* Updated heading slightly */ ?>
            <?php if ($newestRecipesError): ?>
                <p class="error-message"><?php echo $newestRecipesError; ?></p>
            <?php elseif (!empty($newestRecipes)): ?>
                <ul class="recipe-list recipe-list-with-images">
                    <?php foreach ($newestRecipes as $recipe): ?>
                        <li>
                            <?php $imageUrl = extractFirstImageUrl($recipe['Image_URL']); ?>
                            <?php if ($imageUrl): // Should always find one now based on query ?>
                                <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                                    <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                                         alt="<?php echo htmlspecialchars($recipe['Recipe_Name']); ?>"
                                         class="recipe-list-image"
                                         loading="lazy"
                                         onerror="this.style.display='none'">
                                </a>
                            <?php else: // Fallback just in case extraction fails ?>
                                <div class="recipe-list-image-placeholder">No Image</div>
                            <?php endif; ?>
                            <div class="recipe-list-info">
                                <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                                <?php echo htmlspecialchars(html_entity_decode($recipe['Recipe_Name'])); ?>
                                </a>
                                <span class="rating">(Rating: <?php echo htmlspecialchars($recipe['Average_Rating'] ?? 'N/A'); ?>)</span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No new recipes with images found.</p> <?php /* Updated message */ ?>
            <?php endif; ?>
        </section>

        <section class="home-section">
            <h2>Top Recipe Categories</h2>
            <?php if ($categoriesError): ?>
                <p class="error-message"><?php echo $categoriesError; ?></p>
            <?php elseif (!empty($topCategories)): ?>
                <ul class="category-list">
                    <?php foreach ($topCategories as $categoryData): ?>
                        <li>
                            <a href="category.php?name=<?php echo urlencode($categoryData['Category']); ?>">
                                <?php echo htmlspecialchars($categoryData['Category']); ?>
                            </a>
                            <span class="category-count">(<?php echo $categoryData['recipe_count']; ?> recipes)</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No categories found.</p>
            <?php endif; ?>
        </section>
        <a href="pantry_search.php">Search by Ingredients</a>
    </main>

    <footer class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> Recipe Website</p>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>
