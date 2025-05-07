<?php
session_start();

// 1. Include the database connection
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo

// --- Settings ---
$recipes_per_page = 20; // How many recipes to show per page

// --- 2. Get and Validate Category Name from URL ---
$category_name = null;
if (isset($_GET['name'])) {
    $category_name = trim(urldecode($_GET['name']));
    if (empty($category_name)) {
        exit('Category name cannot be empty.');
    }
} else {
    exit('No Category name provided.');
}

// --- 3. Get and Validate Current Page Number ---
$current_page = 1; // Default to page 1
if (isset($_GET['page'])) {
    if (filter_var($_GET['page'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $current_page = (int)$_GET['page'];
    } else {
        header("Location: category.php?name=" . urlencode($category_name)); // Redirect to page 1
        exit;
    }
}

// Set the page title based on the category
$pageTitle = "Category: " . htmlspecialchars($category_name) . " (Images Only) - Page " . $current_page;

// --- 4. Calculate OFFSET for SQL Query ---
$offset = ($current_page - 1) * $recipes_per_page;

// --- 5. Count Total Recipes WITH IMAGES in this Category ---
$total_recipes = 0;
$total_pages = 1;
try {
    // Add the image filters to the COUNT query as well
    $sqlCount = "SELECT COUNT(*) FROM Recipes
                 WHERE Category = :category_name
                   AND Recipe_Name IS NOT NULL AND Recipe_Name != ''
                   AND Image_URL IS NOT NULL           -- Added: Image filter
                   AND Image_URL != ''                 -- Added: Image filter
                   AND Image_URL != 'character(0)'     -- Added: Image filter
                   ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->bindParam(':category_name', $category_name, PDO::PARAM_STR);
    $stmtCount->execute();
    $total_recipes = (int)$stmtCount->fetchColumn();

    // Calculate total pages based on the filtered count
    if ($total_recipes > 0) {
        $total_pages = ceil($total_recipes / $recipes_per_page);
    } else {
        $total_pages = 1; // Ensure total_pages is at least 1 even if count is 0
    }


    // Redirect to last page if requested page is too high (and pages exist)
    if ($current_page > $total_pages && $total_pages > 0) {
         header("Location: category.php?name=" . urlencode($category_name) . "&page=" . $total_pages);
         exit;
    }

} catch (PDOException $e) {
    error_log("Database Count Error on Category Page: " . $e->getMessage());
    $queryError = "Could not determine total recipe count.";
}


// --- 6. Fetch Recipes WITH IMAGES for the Current Page ---
$recipesInCategory = [];
if (!isset($queryError)) {
    try {
        // Add the image filters to the main SELECT query
        $sql = "SELECT RecipeId, Recipe_Name, Average_Rating, Image_URL
                FROM Recipes
                WHERE Category = :category_name
                  AND Recipe_Name IS NOT NULL AND Recipe_Name != ''
                  AND Image_URL IS NOT NULL           -- Added: Image filter
                  AND Image_URL != ''                 -- Added: Image filter
                  AND Image_URL != 'character(0)'     -- Added: Image filter
                ORDER BY Recipe_Name ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':category_name', $category_name, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $recipes_per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $recipesInCategory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database Query Error on Category Page (Fetch): " . $e->getMessage());
        $queryError = "Sorry, an error occurred while fetching recipes for this category.";
        $recipesInCategory = [];
    }
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
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> <style> /* Pagination styles - can move to style.css */
        .pagination { margin: 2em 0 1em 0; text-align: center; }
        .pagination a, .pagination span { display: inline-block; padding: 0.5em 1em; margin: 0 0.2em; border: 1px solid #ddd; color: #0056b3; text-decoration: none; border-radius: 3px; }
        .pagination a:hover { background-color: #eee; }
        .pagination .current-page { background-color: #0056b3; color: white; border-color: #0056b3; font-weight: bold; }
        .pagination .disabled { color: #aaa; border-color: #eee; pointer-events: none; }
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
        <h1>Recipe Website</h1>
        <p><a href="index.php">&laquo; Back to Home</a></p>
    </header>

    <main class="container">
        <h2>Category: <?php echo htmlspecialchars($category_name); ?> (Images Only)</h2>
        <p>Showing page <?php echo $current_page; ?> of <?php echo $total_pages; ?> (<?php echo $total_recipes; ?> total recipes with images)</p>
        <hr>

        <?php
        if (isset($queryError)):
        ?>
            <p class="error-message"><?php echo $queryError; ?></p>
        <?php
        elseif (!empty($recipesInCategory)):
        ?>
            <ul class="recipe-list recipe-list-with-images">
                <?php foreach ($recipesInCategory as $recipe): ?>
                    <li>
                        <?php $imageUrl = extractFirstImageUrl($recipe['Image_URL']); ?>
                        <?php if ($imageUrl): // Should always have an image now ?>
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
                            <i class="far fa-star favorite-star <?php echo (isset($_SESSION['username']) && in_array($recipe['RecipeId'], $userFavorites)) ? 'favorited' : ''; ?>"
                               data-recipe-id="<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></i>
                            <span class="favorite-message" id="fav-msg-<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php
        else: // No recipes with images found for this category/page
        ?>
            <p>No recipes with images found in the category "<?php echo htmlspecialchars($category_name); ?>" on this page.</p>
        <?php
        endif;
        ?>

        <?php // --- Pagination Links --- ?>
        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php if ($current_page > 1): ?>
                    <a href="?name=<?php echo urlencode($category_name); ?>&page=<?php echo $current_page - 1; ?>">&laquo; Previous</a>
                <?php else: ?>
                    <span class="disabled">&laquo; Previous</span>
                <?php endif; ?>
                <?php
                $range = 2;
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == 1 || $i == $total_pages || ($i >= $current_page - $range && $i <= $current_page + $range)):
                ?>
                        <?php if ($i == $current_page): ?>
                            <span class="current-page"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?name=<?php echo urlencode($category_name); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php
                    elseif ($i == $current_page - $range - 1 || $i == $current_page + $range + 1):
                    ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($current_page < $total_pages): ?>
                    <a href="?name=<?php echo urlencode($category_name); ?>&page=<?php echo $current_page + 1; ?>">Next &raquo;</a>
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

    <script src="script.js"></script>
    <script>
        // Favorite star functionality
        document.addEventListener('DOMContentLoaded', function() {
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