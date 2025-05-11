=<?php
// 1. Include the database connection
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo object

session_start(); // Start session (if not already started)

// --- 2. Get and Validate Recipe ID from URL ---
$recipe_id = null;
if (isset($_GET['id'])) {
    if (filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $recipe_id = (int)$_GET['id'];
    } else {
        // Consider a more user-friendly error page or message
        // For now, exiting as in your original file, but you might want to redirect or show a proper error template.
        exit('Invalid Recipe ID format provided.');
    }
} else {
    exit('No Recipe ID provided.');
}

// Function to extract the first valid image URL (from your original file)
function extractFirstImageUrl($imageUrlString) {
    if (empty($imageUrlString) || $imageUrlString === 'character(0)') {
        return null;
    }
    $trimmedUrl = trim($imageUrlString, ' "');
    if (filter_var($trimmedUrl, FILTER_VALIDATE_URL)) {
        return $trimmedUrl;
    }
    // Check for c("url1", ...) format
    if (preg_match('/^c?\("([^"]+)"/', $imageUrlString, $matches)) {
        $potentialUrl = $matches[1];
        if (filter_var($potentialUrl, FILTER_VALIDATE_URL)) {
            return $potentialUrl;
        }
    }
    return null;
}


// --- 3. Fetch Recipe Details from Database ---
$recipe = null; // Initialize recipe variable
$pageTitle = "Recipe Details"; // Default page title
$imageUrl = null; // Initialize image URL variable
$reviews = []; // Initialize reviews array
$reviewsError = null; // Initialize reviews error message
$queryError = null; // For general query errors
$recipeNotFoundError = null; // Specific for recipe not found

try {
    // Fetch main recipe details (and potentially meal details if you use a combined SP)
    // Using a direct query as per your original file, but you could swap this with a
    // stored procedure call like CALL GetRecipeAndMealDetailsById(?) if you've made that one.
    $sqlRecipe = "SELECT 
                    R.RecipeId, R.Recipe_Name, R.Category, R.Keywords, 
                    R.Rating_Count, R.Average_Rating, R.Ingredients, 
                    R.Ingredient_Quantity, R.Description, R.Instructions, 
                    R.Servings AS RecipeServings, R.Date AS RecipeDate, R.Image_URL, 
                    R.AuthorId, R.Author,
                    M.Meal_Name, M.Category AS MealCategorySpecific, M.Servings AS MealServingsNutrition, 
                    M.Total_Time, M.Prep_Time, M.Cook_Time, M.Yield AS MealYield, 
                    M.Calories, M.Fat, M.Saturated_Fat, M.Cholesterol, M.Sodium, 
                    M.Carbohydrate, M.Fiber, M.Sugar, M.Protein, M.Serving_Size AS MealServingSize
                  FROM Recipes R
                  LEFT JOIN Meal M ON R.RecipeId = M.MealId
                  WHERE R.RecipeId = :recipe_id";
    $stmtRecipe = $pdo->prepare($sqlRecipe);
    $stmtRecipe->bindParam(':recipe_id', $recipe_id, PDO::PARAM_INT);
    $stmtRecipe->execute();
    $recipe = $stmtRecipe->fetch(PDO::FETCH_ASSOC);
    $stmtRecipe->closeCursor();

    if ($recipe) {
        $pageTitle = htmlspecialchars(html_entity_decode($recipe['Recipe_Name'] ?? 'Recipe Details'));
        $imageUrl = extractFirstImageUrl($recipe['Image_URL']);

        // --- Fetch Reviews for this Recipe using Stored Procedure ---
        try {
            $stmtReviews = $pdo->prepare("CALL GetReviewsForRecipe(?)");
            $stmtReviews->bindParam(1, $recipe_id, PDO::PARAM_INT);
            $stmtReviews->execute();
            $reviews = $stmtReviews->fetchAll(PDO::FETCH_ASSOC);
            $stmtReviews->closeCursor();
        } catch (PDOException $e) {
            error_log("Database Query Error (Reviews SP for RecipeID $recipe_id): " . $e->getMessage());
            $reviewsError = "Could not load reviews for this recipe.";
        }

    } else {
        // Recipe not found
        $pageTitle = "Recipe Not Found";
        $recipeNotFoundError = "Sorry, we couldn't find a recipe with the ID " . htmlspecialchars($recipe_id) . ".";
    }

} catch (PDOException $e) {
    error_log("Database Query Error on Detail Page (Main Recipe for RecipeID $recipe_id): " . $e->getMessage());
    $queryError = "Sorry, an error occurred while fetching recipe details.";
    $pageTitle = "Error"; // Update page title for error state
}

// Fetch user's favorite recipes if logged in (from your original file)
$userFavorites = [];
if (isset($_SESSION['username'])) {
    try {
        $stmtFav = $pdo->prepare("SELECT Favorites FROM User WHERE Username = ?");
        $stmtFav->execute([$_SESSION['username']]);
        $resultFav = $stmtFav->fetch(PDO::FETCH_ASSOC);
        // Ensure $userFavorites is always an array
        $userFavorites = ($resultFav && $resultFav['Favorites']) ? json_decode($resultFav['Favorites'], true) : [];
        if (!is_array($userFavorites)) { // Handle JSON decode errors or unexpected non-array
            $userFavorites = [];
        }
        $stmtFav->closeCursor();
    } catch (PDOException $e) {
        error_log("Database error fetching favorites for user {$_SESSION['username']}: " . $e->getMessage());
        // $userFavorites remains an empty array, no need to show error to user here
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="style.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Styles from your previous version - consider moving to style.css */
        .recipe-section { margin-bottom: 1.5em; }
        .recipe-section h2 { margin-bottom: 0.5em; color: #444; } /* Style from style.css */
        .recipe-ingredients ul, .recipe-instructions ol { margin-left: 2em; } /* Style from style.css */
        .recipe-ingredients p { margin: 0.5em 0; } /* Style from style.css */
        .recipe-main-image {
             max-width: 100%; /* Style from style.css */
             height: auto;
             display: block;
             margin-bottom: 1.5em;
             border-radius: 5px;
             box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Style from style.css */
        }
        .recipe-image-placeholder {
            width: 100%;
            max-width: 600px; /* Example max-width */
            height: 300px; /* Adjust as needed */
            background-color: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-style: italic;
            margin-bottom: 1.5em;
            border-radius: 5px;
            border: 1px dashed #ccc;
        }
        .favorite-star {
            cursor: pointer;
            color: #ccc;
            font-size: 1.5em; /* Made star a bit bigger */
            transition: color 0.2s ease-in-out;
        }
        .favorite-star.favorited {
            color: gold;
        }
        .favorite-message {
            font-size: 0.8em;
            color: #777;
            margin-left: 5px;
        }
        .review-item {
            border-bottom: 1px solid #eee;
            padding-bottom: 1em;
            margin-bottom: 1em;
        }
        .review-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .review-meta {
            font-size: 0.9em;
            color: #555;
            margin-bottom: 0.5em;
        }
        .review-meta strong {
            color: #333;
        }
        .review-text {
            line-height: 1.6;
            margin-top: 0.3em;
        }
        .star-rating .fa-star { /* Using Font Awesome stars now */
            color: #ccc; /* Default empty star color */
        }
        .star-rating .fa-star.filled {
            color: gold; /* Filled star color */
        }
        .error-message { color: #d9534f; font-weight: bold; } /* Style from style.css */

        /* Ensuring recipe-meta styles are applied if not fully covered by main style.css */
        .recipe-meta {
            background-color: #f9f9f9;
            padding: 0.8em 1em;
            border: 1px solid #eee;
            border-radius: 4px;
            margin-bottom: 1.5em;
            font-size: 0.9em;
            color: #555;
        }
        .recipe-meta p { margin: 0.3em 0; }
        .recipe-meta strong { color: #333; }

    </style>
</head>
<body>
    <header class="site-header">
        <h1><?php echo htmlspecialchars( ($recipe && !$recipeNotFoundError && !$queryError) ? ($recipe['Recipe_Name'] ?? 'Recipe Details') : $pageTitle ); ?></h1>
        <p><a href="index.php">&laquo; Back to Home / All Recipes</a></p>
    </header>

    <main class="container">
    <?php if ($queryError): ?>
        <p class="error-message"><?php echo htmlspecialchars($queryError); ?></p>
    <?php elseif ($recipeNotFoundError): ?>
        <h1>Recipe Not Found</h1>
        <p><?php echo htmlspecialchars($recipeNotFoundError); ?></p>
    <?php elseif ($recipe): ?>
        <div style="display: flex; align-items: center; margin-bottom: 1em; flex-wrap: wrap;">
            <h2 style="margin-right: 10px; margin-bottom: 0; font-size: 1.8em; color: #333;"><?php echo htmlspecialchars($recipe['Recipe_Name']); ?></h2>
            <?php if (isset($_SESSION['username'])): ?>
                <span title="<?php echo (is_array($userFavorites) && in_array($recipe['RecipeId'], $userFavorites)) ? 'Remove from favorites' : 'Add to favorites'; ?>">
                    <i class="favorite-star <?php echo (is_array($userFavorites) && in_array($recipe['RecipeId'], $userFavorites)) ? 'fas fa-star favorited' : 'far fa-star'; ?>"
                       data-recipe-id="<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></i>
                </span>
                <span class="favorite-message" id="fav-msg-<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></span>
            <?php endif; ?>
        </div>

        <?php if ($imageUrl): ?>
            <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                 alt="Image of <?php echo htmlspecialchars($recipe['Recipe_Name']); ?>"
                 class="recipe-main-image"
                 onerror="this.style.display='none'; this.outerHTML = '<div class=\'recipe-image-placeholder\'>Image for <?php echo htmlspecialchars(addslashes($recipe['Recipe_Name'])); ?> not available</div>';">
        <?php else: ?>
             <div class="recipe-image-placeholder">No Image Available for <?php echo htmlspecialchars($recipe['Recipe_Name']); ?></div>
        <?php endif; ?>

        <div class="recipe-meta">
            <p>
                <?php if(!empty($recipe['Category'])): ?>
                    <strong>Category:</strong> <?php echo htmlspecialchars($recipe['Category']); ?> |
                <?php endif; ?>
                <strong>Rating:</strong> <?php echo htmlspecialchars(number_format((float)($recipe['Average_Rating'] ?? 0), 1)); ?> / 5
                (<?php echo htmlspecialchars($recipe['Rating_Count'] ?? 0); ?> ratings)
            </p>
            <?php if (!empty($recipe['Author'])): // Using Author field from Recipes table as per your DESCRIBE output ?>
                <p><strong>Author:</strong> <?php echo htmlspecialchars($recipe['Author']); ?></p>
            <?php endif; ?>
            <?php if (!empty($recipe['RecipeDate'])): ?>
                <p><em>Submitted on: <?php echo date("F j, Y", strtotime($recipe['RecipeDate'])); ?></em></p>
            <?php endif; ?>
            <?php if (!empty($recipe['RecipeServings'])): ?>
                <p><strong>Recipe Yields:</strong> <?php echo htmlspecialchars($recipe['RecipeServings']); ?> servings</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($recipe['Description'])): ?>
        <div class="recipe-section recipe-description">
            <h2>Description</h2>
            <p><?php echo nl2br(htmlspecialchars($recipe['Description'])); ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($recipe['Total_Time']) || !empty($recipe['Calories'])): ?>
        <div class="recipe-section recipe-meal-info">
            <h4>Additional Details</h4>
            <?php if (!empty($recipe['Total_Time']) && $recipe['Total_Time'] !== '00:00:00'): ?>
                <p><strong>Total Time:</strong> <?php echo htmlspecialchars(ltrim($recipe['Total_Time'], '0:')); ?>
                    <?php if (!empty($recipe['Prep_Time']) && $recipe['Prep_Time'] !== '00:00:00'): ?>
                        (Prep: <?php echo htmlspecialchars(ltrim($recipe['Prep_Time'], '0:')); ?>)
                    <?php endif; ?>
                    <?php if (!empty($recipe['Cook_Time']) && $recipe['Cook_Time'] !== '00:00:00'): ?>
                        (Cook: <?php echo htmlspecialchars(ltrim($recipe['Cook_Time'], '0:')); ?>)
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <?php if (!empty($recipe['Calories'])): ?>
                <p><strong>Calories:</strong> <?php echo htmlspecialchars(round($recipe['Calories'])); ?>
                <?php if (!empty($recipe['MealServingSize'])): ?>
                    per serving (Serving size: <?php echo htmlspecialchars($recipe['MealServingSize']);
                    // The Meal.Yield field might be a string like "1 serving" or "100g"
                    echo !empty($recipe['MealYield']) ? ' ' . htmlspecialchars($recipe['MealYield']) : ''; ?>)
                <?php endif; ?>
                </p>
                <p style="font-size:0.9em; color:#666;">
                    Fat: <?php echo htmlspecialchars($recipe['Fat'] ?? 'N/A'); ?>g |
                    Carbs: <?php echo htmlspecialchars($recipe['Carbohydrate'] ?? 'N/A'); ?>g |
                    Protein: <?php echo htmlspecialchars($recipe['Protein'] ?? 'N/A'); ?>g
                </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <?php if (!empty($recipe['Ingredients']) || !empty($recipe['Ingredient_Quantity'])): ?>
        <div class="recipe-section recipe-ingredients">
            <h2>Ingredients</h2>
            <?php if (!empty($recipe['Ingredient_Quantity'])): // This is varchar(2500) ?>
                <div><?php echo nl2br(htmlspecialchars($recipe['Ingredient_Quantity'])); ?></div>
            <?php endif; ?>
            <?php if (!empty($recipe['Ingredients']) && empty($recipe['Ingredient_Quantity'])): // This is varchar(2000) ?>
                 <div><?php echo nl2br(htmlspecialchars($recipe['Ingredients'])); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <?php if (!empty($recipe['Instructions'])): ?>
        <div class="recipe-section recipe-instructions">
            <h2>Instructions</h2>
            <div><?php echo nl2br(htmlspecialchars($recipe['Instructions'])); ?></div>
        </div>
        <?php endif; ?>

        <div class="recipe-section recipe-reviews">
             <h2>Reviews</h2>
             <?php if ($reviewsError): ?>
                <p class="error-message"><?php echo htmlspecialchars($reviewsError); ?></p>
             <?php elseif (!empty($reviews)): ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <div class="review-meta">
                            <span class="star-rating" title="Rating: <?php echo htmlspecialchars($review['Rating'] ?? 0); ?> out of 5">
                                <?php
                                $rating = (int)($review['Rating'] ?? 0);
                                for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-star <?php echo ($i <= $rating) ? 'fas filled' : 'far'; ?>"></i>
                                <?php endfor; ?>
                            </span>
                            by
                            <strong><?php echo htmlspecialchars($review['ReviewerUsername'] ?? 'Anonymous'); ?></strong>
                            on <?php echo htmlspecialchars(date("M j, Y", strtotime($review['ReviewDate'] ?? 'now'))); ?>
                        </div>
                        <p class="review-text"><?php echo nl2br(htmlspecialchars($review['ReviewText'] ?? 'No comment provided.')); ?></p>
                    </div>
                <?php endforeach; ?>
             <?php else: ?>
                <p><em>No reviews yet for this recipe. Be the first to write one!</em></p>
                <?php if (isset($_SESSION['username'])): ?>
                    <?php else: ?>
                    <?php endif; ?>
             <?php endif; ?>
        </div>
        <?php
    // Removed the 'else:' for recipe not found here, as it's handled at the top of the main container.
    endif; // This was for if ($recipe)
    ?>

    <hr style="margin-top: 2em;">
    <p><a href="index.php">&laquo; Back to Home / All Recipes</a></p>
    </main>

    <footer class="site-footer"> <p>&copy; <?php echo date('Y'); ?> Recipe Website</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.favorite-star').forEach(star => {
                star.addEventListener('click', function() {
                    const recipeId = this.dataset.recipeId;
                    const messageEl = document.getElementById(`fav-msg-${recipeId}`);
                    const starIcon = this; // The <i> element

                    <?php if (isset($_SESSION['username'])): ?>
                        fetch('update_favorites.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ recipeId: recipeId, action: starIcon.classList.contains('favorited') ? 'remove' : 'add' })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                starIcon.classList.toggle('favorited'); // General state class
                                starIcon.classList.toggle('fas'); // Font Awesome solid star
                                starIcon.classList.toggle('far'); // Font Awesome regular star (outline)
                                if (messageEl) messageEl.textContent = starIcon.classList.contains('favorited') ? 'Favorited!' : 'Unfavorited.';
                                starIcon.parentElement.title = starIcon.classList.contains('favorited') ? 'Remove from favorites' : 'Add to favorites';
                                setTimeout(() => { if (messageEl) messageEl.textContent = ''; }, 2000);
                            } else {
                                if (messageEl) messageEl.textContent = data.message || 'Failed to update.';
                                setTimeout(() => { if (messageEl) messageEl.textContent = ''; }, 3000);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            if (messageEl) messageEl.textContent = 'Error updating favorites.';
                            setTimeout(() => { if (messageEl) messageEl.textContent = ''; }, 3000);
                        });
                    <?php else: ?>
                        if (messageEl) messageEl.textContent = 'Sign in to add to favorites.';
                        setTimeout(() => { if (messageEl) messageEl.textContent = ''; }, 3000);
                    <?php endif; ?>
                });
            });
        });
    </script>
</body>
</html>