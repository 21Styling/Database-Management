<?php
session_start();             
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

// Fetch user's favorite recipes if logged in
$userFavorites = [];
if (isset($_SESSION['username'])) {
    try {
        $stmt = $pdo->prepare("SELECT Favorites FROM User WHERE Username = ?");
        $stmt->execute([$_SESSION['username']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $userFavorites = $result ? json_decode($result['Favorites'], true) : [];
    } catch (PDOException $e) {
        error_log("Database error fetching favorites: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .favorite-star {
            cursor: pointer;
            color: #ccc; /* Default color (outline) */
        }
        .favorite-star.favorited {
            color: gold; /* Color when favorited */
        }
        .favorite-message {
            font-size: 0.8em;
            color: #777;
            margin-top: 0.2em;
        }
    </style>
</head>
<body>

    <header class="site-header">
        <h1>Welcome to the Recipe Website!</h1>
        <p>Find delicious recipes for every occasion.</p>
    </header>
    <div class="top-right-buttons">
<?php if (isset($_SESSION['username'])): ?>
    <button onclick="window.location.href='user.php'">Account</button>
<?php else: ?>
    <button onclick="window.location.href='signup.php'">Sign Up</button>
    <button onclick="window.location.href='signin.php'">Sign In</button>
<?php endif; ?>
</div>
     <main>
         <section class="home-section">
             <h2>Search Recipes by Name</h2>
             <form action="search_results.php" method="get" class="recipe-search-form">
                 <input type="text" name="q" placeholder="Enter recipe name..." required>
                 <button type="submit">Search</button>
             </form>
         </section>
 
         </main>
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
                                    <?php echo htmlspecialchars($recipe['Recipe_Name']); ?>
                                </a>
                                <span class="rating">(Rating: <?php echo htmlspecialchars($recipe['Average_Rating'] ?? 'N/A'); ?>)</span>
                                <i class="far fa-star favorite-star <?php echo (isset($_SESSION['username']) && in_array($recipe['RecipeId'], $userFavorites)) ? 'favorited' : ''; ?>"
                                   data-recipe-id="<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></i>
                                <span class="favorite-message" id="fav-msg-<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></span>
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

    <script src="script.js"></script>
    <script>
        // JavaScript to hide the "No recipes found" message after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const noResultsMessage = document.getElementById('no-results-message');
            if (noResultsMessage) {
                setTimeout(function() {
                    noResultsMessage.style.display = 'none';
                }, 5000); // 5000 milliseconds = 5 seconds
            }

            // Favorite star functionality
            document.querySelectorAll('.favorite-star').forEach(star => {
                star.addEventListener('click', function() {
                    const recipeId = this.dataset.recipeId;
                    const messageEl = document.getElementById(`fav-msg-${recipeId}`);

                    // Check if user is logged in (PHP variable from session)
                    <?php if (isset($_SESSION['username'])): ?>
                        // User is logged in, call update_favorites.php
                        fetch('update_favorites.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ recipeId: recipeId, action: this.classList.contains('favorited') ? 'remove' : 'add' })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.classList.toggle('favorited');
                                messageEl.textContent = this.classList.contains('favorited') ? 'Added to favorites' : 'Removed from favorites';
                                setTimeout(() => messageEl.textContent = '', 2000); // Clear message after 2 seconds
                            } else {
                                messageEl.textContent = data.message;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            messageEl.textContent = 'Failed to update favorites.';
                        });
                    <?php else: ?>
                        // User is not logged in
                        messageEl.textContent = 'Please sign in to add recipes to favorites.';
                    <?php endif; ?>
                });
            });
        });
    </script>
</body>
</html>
