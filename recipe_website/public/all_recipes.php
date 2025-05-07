<?php
session_start();

// 1. Include the database connection
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo

// --- Settings ---
$recipes_per_page = 20; // How many recipes to show per page

// --- 2. Get and Validate Current Page Number ---
// Category name is no longer needed for an "All Recipes" view
$current_page = 1; // Default to page 1
if (isset($_GET['page'])) {
    if (filter_var($_GET['page'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $current_page = (int)$_GET['page'];
    } else {
        header("Location: all_recipes_view.php"); // Redirect to page 1 of all recipes
        exit;
    }
}

// Set the page title
$pageTitle = "All Recipes - Page " . $current_page;

// --- 3. Calculate OFFSET for SQL Query ---
$offset = ($current_page - 1) * $recipes_per_page;

// --- 4. Count Total Recipes (with filters similar to homepage's "Newest Recipes") ---
$total_recipes = 0;
$total_pages = 1;
$queryError = null; // Initialize queryError

try {
    // Filters to ensure recipes have a name and a valid image URL, like on the homepage
    $sqlCount = "SELECT COUNT(*) FROM Recipes";

    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute();
    $total_recipes = (int)$stmtCount->fetchColumn();

    if ($total_recipes > 0) {
        $total_pages = ceil($total_recipes / $recipes_per_page);
    } else {
        $total_pages = 1; // Ensure total_pages is at least 1
    }

    // Redirect to last page if requested page is too high
    if ($current_page > $total_pages && $total_pages > 0) {
         header("Location: all_recipes_view.php?page=" . $total_pages);
         exit;
    }

} catch (PDOException $e) {
    error_log("Database Count Error on All Recipes Page: " . $e->getMessage());
    $queryError = "Could not determine total recipe count.";
}


// --- 5. Fetch Recipes for the Current Page (with filters) ---
$recipes = []; // Changed from $recipesInCategory
if (!isset($queryError)) { // Proceed only if count query was successful
    try {
        $sql = "SELECT RecipeId, Recipe_Name, Average_Rating, Image_URL
                FROM Recipes
                WHERE Recipe_Name IS NOT NULL AND Recipe_Name != ''
                  AND Image_URL IS NOT NULL
                  AND Image_URL != ''
                  AND Image_URL != 'character(0)'
                ORDER BY RecipeId DESC  -- Or RecipeId DESC for newest first
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':limit', $recipes_per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database Query Error on All Recipes Page (Fetch): " . $e->getMessage());
        $queryError = "Sorry, an error occurred while fetching recipes.";
        $recipes = []; // Ensure $recipes is an array on error
    }
}

// Fetch user's favorite recipes if logged in
$userFavorites = [];
if (isset($_SESSION['username'])) {
    try {
        $stmtUser = $pdo->prepare("SELECT Favorites FROM User WHERE Username = ?");
        $stmtUser->execute([$_SESSION['username']]);
        $resultUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $userFavorites = $resultUser ? json_decode($resultUser['Favorites'], true) : [];
        if ($userFavorites === null) { // Handle JSON decode error or empty string
            $userFavorites = [];
        }
    } catch (PDOException $e) {
        error_log("Database error fetching favorites for all_recipes_view.php: " . $e->getMessage());
        $userFavorites = []; // Default to empty array on error
    }
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
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Pagination styles - can move to style.css if not already there */
        .pagination { margin: 2em 0 1em 0; text-align: center; }
        .pagination a, .pagination span { display: inline-block; padding: 0.5em 1em; margin: 0 0.2em; border: 1px solid #ddd; color: #0056b3; text-decoration: none; border-radius: 3px; }
        .pagination a:hover { background-color: #eee; }
        .pagination .current-page { background-color: #0056b3; color: white; border-color: #0056b3; font-weight: bold; }
        .pagination .disabled { color: #aaa; border-color: #eee; pointer-events: none; }

        /* Favorite star styles - can move to style.css if not already there */
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
            display: inline-block; /* Keep it on the same line or manage layout */
            margin-left: 5px;
        }
        /* Ensure recipe list structure matches homepage from style.css */
        /* .recipe-list, .recipe-list-with-images are expected to be in style.css */
    </style>
</head>
<body>

    <header class="site-header">
        <h1>Recipe Website</h1>
        <p><a href="index.php">&laquo; Back to Home</a></p>
    </header>

    <main class="container">
        <h2>All Recipes</h2>
        <p>Showing page <?php echo $current_page; ?> of <?php echo $total_pages; ?> (<?php echo $total_recipes; ?> total recipes with images found)</p>
        <hr>

        <?php if (isset($queryError)): ?>
            <p class="error-message"><?php echo htmlspecialchars($queryError); ?></p>
        <?php elseif (!empty($recipes)): ?>
            <ul class="recipe-list recipe-list-with-images">
                <?php foreach ($recipes as $recipe): ?>
                    <li>
                        <?php $imageUrl = extractFirstImageUrl($recipe['Image_URL']); ?>
                        <?php if ($imageUrl): ?>
                            <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($recipe['Recipe_Name']); ?>" class="recipe-list-image" loading="lazy" onerror="this.style.display='none'">
                            </a>
                        <?php else: ?>
                            <div class="recipe-list-image-placeholder">No Image</div>
                        <?php endif; ?>
                        <div class="recipe-list-info">
                            <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                                <?php echo htmlspecialchars($recipe['Recipe_Name']); ?>
                            </a>
                            <span class="rating">(Rating: <?php echo htmlspecialchars($recipe['Average_Rating'] ?? 'N/A'); ?>)</span>
                            <i class="far fa-star favorite-star <?php echo (isset($_SESSION['username']) && is_array($userFavorites) && in_array($recipe['RecipeId'], $userFavorites)) ? 'fas favorited' : 'far'; ?>"
                               data-recipe-id="<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></i>
                            <span class="favorite-message" id="fav-msg-<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No recipes with images found.</p>
        <?php endif; ?>

        <?php // --- Pagination Links --- ?>
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?>">&laquo; Previous</a>
                <?php else: ?>
                    <span class="disabled">&laquo; Previous</span>
                <?php endif; ?>

                <?php
                $range = 2; // Number of pages to show around the current page
                for ($i = 1; $i <= $total_pages; $i++):
                    // Always show first and last page, and pages within the range
                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)):
                ?>
                        <?php if ($i == $current_page): ?>
                            <span class="current-page"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php
                    // Show '...' if there's a gap
                    elseif ($i == $current_page - $range - 1 || $i == $current_page + $range + 1):
                    ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>">Next &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &raquo;</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php // --- End Pagination Links --- ?>

    </main>

    <footer class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> Recipe Website</p>
    </footer>

    <script>
        // Favorite star functionality (should be similar to or same as index.php and other pages)
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.favorite-star').forEach(star => {
                star.addEventListener('click', function() {
                    const recipeId = this.dataset.recipeId;
                    const messageEl = document.getElementById(`fav-msg-${recipeId}`);
                    const isFavorited = this.classList.contains('favorited');

                    <?php if (isset($_SESSION['username'])): ?>
                        fetch('update_favorites.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ recipeId: recipeId, action: isFavorited ? 'remove' : 'add' })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.classList.toggle('favorited');
                                this.classList.toggle('fas'); // If using Font Awesome solid for favorited
                                this.classList.toggle('far'); // And regular for not
                                messageEl.textContent = this.classList.contains('favorited') ? 'Added to favorites' : 'Removed from favorites';
                                setTimeout(() => messageEl.textContent = '', 2000);
                            } else {
                                messageEl.textContent = data.message || 'Failed to update.';
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            messageEl.textContent = 'Request error. Failed to update favorites.';
                        });
                    <?php else: ?>
                        messageEl.textContent = 'Please sign in to add recipes to favorites.';
                        setTimeout(() => messageEl.textContent = '', 3000);
                    <?php endif; ?>
                });
            });
        });
    </script>
</body>
</html>