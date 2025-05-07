<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: signin.php');
    exit;
}

require_once __DIR__ . '/../src/db_connect.php';

// Fetch user's favorite recipe IDs
try {
    $stmt = $pdo->prepare("SELECT Favorites FROM User WHERE Username = ?");
    $stmt->execute([$_SESSION['username']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $favoriteIds = $result ? json_decode($result['Favorites'], true) : [];
} catch (PDOException $e) {
    error_log("Database error fetching favorite IDs: " . $e->getMessage());
    $favoriteIds = []; // Ensure $favoriteIds is always initialized
    $error_message = "Failed to retrieve your favorite recipes.";
}

// Fetch recipe details for the favorite IDs
$favoriteRecipes = [];
if (!empty($favoriteIds)) {
    try {
        // Construct the SQL query with a dynamic IN clause
        $placeholders = implode(',', array_fill(0, count($favoriteIds), '?'));
        $sql = "SELECT RecipeId, Recipe_Name, Average_Rating, Image_URL
                FROM Recipes
                WHERE RecipeId IN ($placeholders)
                ORDER BY Recipe_Name ASC";
        $stmt = $pdo->prepare($sql);

        // Bind the parameters
        foreach ($favoriteIds as $index => $recipeId) {
            $stmt->bindValue($index + 1, $recipeId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $favoriteRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error fetching favorite recipes: " . $e->getMessage());
        $error_message = "Failed to retrieve your favorite recipes.";
        $favoriteRecipes = [];
    }
}

/**
 * Function to extract the first valid image URL from the stored string.
 */
function extractFirstImageUrl($imageUrlString) {
    if (empty($imageUrlString) || $imageUrlString === 'character(0)') {
        return null;
    }
    $trimmedUrl = trim($imageUrlString, ' "');
    if (filter_var($trimmedUrl, FILTER_VALIDATE_URL)) {
        return $trimmedUrl;
    }
    if (preg_match('/^c?\("([^"]+)"/', $imageUrlString, $matches)) {
        $potentialUrl = $matches[1];
        if (filter_var($potentialUrl, FILTER_VALIDATE_URL)) {
            return $potentialUrl;
        }
    }
    return null;
}

$pageTitle = "Your Favorite Recipes";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .favorite-star {
            cursor: pointer;
            color: gold; /* Always gold on this page */
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
        <h1>Your Favorite Recipes</h1>
        <p><a href="user.php">&laquo; Back to Account</a></p>
    </header>

    <main class="container">
        <?php if (isset($error_message)): ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php elseif (empty($favoriteRecipes)): ?>
            <p>You have not added any recipes to your favorites yet.</p>
        <?php else: ?>
            <ul class="recipe-list recipe-list-with-images">
                <?php foreach ($favoriteRecipes as $recipe): ?>
                    <li>
                        <?php $imageUrl = extractFirstImageUrl($recipe['Image_URL']); ?>
                        <?php if ($imageUrl): ?>
                            <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                                     alt="<?php echo htmlspecialchars($recipe['Recipe_Name']); ?>"
                                     class="recipe-list-image"
                                     loading="lazy"
                                     onerror="this.style.display='none'">
                            </a>
                        <?php else: ?>
                            <div class="recipe-list-image-placeholder">No Image</div>
                        <?php endif; ?>
                        <div class="recipe-list-info">
                            <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                            <?php echo htmlspecialchars(html_entity_decode($recipe['Recipe_Name'])); ?>
                            </a>
                            <span class="rating">(Rating: <?php echo htmlspecialchars($recipe['Average_Rating'] ?? 'N/A'); ?>)</span>
                            <i class="fas fa-star favorite-star" data-recipe-id="<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></i>
                            <span class="favorite-message" id="fav-msg-<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> Recipe Website</p>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
