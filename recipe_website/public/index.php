<?php
session_start();
// Attempt to include the database connection file
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo

// Define a title for the page
$pageTitle = "Recipe Website - Home";

// --- Fetch Newest Recipes WITH IMAGES ---
$newestRecipes = [];
$newestRecipesError = null;

try {
    $sqlNewest = "SELECT RecipeId, Recipe_Name, Average_Rating, Image_URL
                  FROM Recipes
                  WHERE Recipe_Name IS NOT NULL AND Recipe_Name != ''
                    AND Image_URL IS NOT NULL AND Image_URL != '' AND Image_URL != 'character(0)'
                  ORDER BY RecipeId DESC LIMIT 5";
    $stmtNewest = $pdo->prepare($sqlNewest);
    $stmtNewest->execute();
    $newestRecipes = $stmtNewest->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Query Error (Newest Recipes): " . $e->getMessage());
    $newestRecipesError = "Could not load newest recipes.";
}

// --- Fetch Top Categories ---
$topCategories = [];
$categoriesError = null;
try {
    $sqlCategories = "SELECT Category, COUNT(*) as recipe_count
                      FROM Recipes
                      WHERE Category IS NOT NULL AND Category != '' AND Category NOT LIKE '< %'
                        AND Category NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}T' AND Category != '0'
                      GROUP BY Category ORDER BY recipe_count DESC LIMIT 20";
    $stmtCategories = $pdo->prepare($sqlCategories);
    $stmtCategories->execute();
    $topCategories = $stmtCategories->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Query Error (Categories): " . $e->getMessage());
    $categoriesError = "Could not load recipe categories.";
}

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

// --- Fetch user's favorite recipes if logged in ---
$userFavorites = [];
if (isset($_SESSION['username'])) {
    try {
        $stmt = $pdo->prepare("SELECT Favorites FROM User WHERE Username = ?");
        $stmt->execute([$_SESSION['username']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $userFavorites = $result ? json_decode($result['Favorites'], true) : [];
        if ($userFavorites === null) $userFavorites = [];
    } catch (PDOException $e) {
        error_log("Database error fetching favorites: " . $e->getMessage());
        $userFavorites = [];
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
        /* --- Styles specific to index.php --- */
        .favorite-star { cursor: pointer; color: #ccc; }
        .favorite-star.favorited { color: gold; }
        .favorite-message { font-size: 0.8em; color: #777; margin-top: 0.2em; display: inline-block; margin-left: 5px; }
        #advancedSearchOptions label { display: inline-block; margin-right: 5px; margin-left:10px; font-size:0.9em; }
        #advancedSearchOptions input[type="number"] { width: 60px; margin-right: 3px; padding: 4px; font-size:0.9em; }
        #advancedSearchOptions .time-input-group span { margin-left: 2px; margin-right: 10px; font-size:0.9em; }
        #advancedSearchOptions select { padding: 4px; font-size:0.9em;}
        #advancedSearchOptions br { margin-bottom: 8px; line-height:1.5; }
        #advancedSearchOptions h5 { margin-top: 12px; margin-bottom: 6px; font-size: 1em; color: #333;}
        .time-input-set { margin-bottom: 5px; }
        .recipe-list-image-placeholder { /* Ensure this style is effective */
            width: 80px; /* Match .recipe-list-image width */
            height: 60px; /* Match .recipe-list-image height */
            background-color: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-style: italic;
            margin-right: 1em; /* Match .recipe-list-image margin-right */
            border-radius: 3px; /* Match .recipe-list-image border-radius */
            flex-shrink: 0; /* Match .recipe-list-image flex-shrink */
            text-align: center;
            font-size:0.8em;
        }
        .recipe-search-form input[type="text"]{
            padding: 5px 10px; font-size: 0.9em; margin-right: 5px; vertical-align: middle; box-sizing: border-box; height: 31px;
        }
        .recipe-search-form > button {
            padding: 5px 10px; font-size: 0.9em; margin-left: 5px; vertical-align: middle; cursor: pointer; box-sizing: border-box; height: 31px;
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

    <main class="container">
        <section class="home-section">
            <h2>Search Recipes</h2>
            <form action="search_results.php" method="get" class="recipe-search-form" id="recipeSearchFormIndex">
                <input type="text" name="q" placeholder="Enter search term..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                <button type="button" id="advancedSearchBtn">Advanced Search</button>
                <button type="submit">Search</button>
                <button type="button" id="resetSearchBtnIndex">Reset</button>

                <div id="advancedSearchOptions" style="display:none; border: 1px solid #ccc; padding: 15px; margin-top: 10px;">
                    <h4>Advanced Options</h4>
                    <label for="search_by">Search By:</label>
                    <select name="search_by" id="search_by">
                        <option value="recipe_name" <?php if (($_GET['search_by'] ?? 'recipe_name') === 'recipe_name') echo 'selected'; ?>>Recipe Name</option>
                        <option value="keywords" <?php if (($_GET['search_by'] ?? '') === 'keywords') echo 'selected'; ?>>Keywords (Name, Desc, Ingred.)</option>
                        <option value="author" <?php if (($_GET['search_by'] ?? '') === 'author') echo 'selected'; ?>>Author ID</option>
                    </select>
                    <br><br>
                    <h5>Nutrition Facts (per serving):</h5>
                    <?php
                    $nutrition_form_fields = [
                        'Calories' => 'Calories', 'Fat' => 'Fat (g)', 'Saturated_Fat' => 'Saturated Fat (g)',
                        'Cholesterol' => 'Cholesterol (mg)', 'Sodium' => 'Sodium (mg)',
                        'Carbohydrate' => 'Carbohydrates (g)', 'Fiber' => 'Fiber (g)',
                        'Sugar' => 'Sugar (g)', 'Protein' => 'Protein (g)'
                    ];
                    foreach ($nutrition_form_fields as $field_key => $field_label) {
                        echo '<label for="min_' . $field_key . '">Min ' . $field_label . ':</label>';
                        echo '<input type="number" name="min_' . $field_key . '" id="min_' . $field_key . '" step="any" value="' . htmlspecialchars($_GET['min_' . $field_key] ?? '') . '" min="0">';
                        echo '<label for="max_' . $field_key . '">Max ' . $field_label . ':</label>';
                        echo '<input type="number" name="max_' . $field_key . '" id="max_' . $field_key . '" step="any" value="' . htmlspecialchars($_GET['max_' . $field_key] ?? '') . '" min="0">';
                        echo '<br>';
                    }
                    ?>
                    <h5>Recipe Yield:</h5>
                    <label for="min_RecipeServings">Min Servings (from Recipe):</label>
                    <input type="number" name="min_RecipeServings" id="min_RecipeServings" value="<?php echo htmlspecialchars($_GET['min_RecipeServings'] ?? ''); ?>" min="0">
                    <label for="max_RecipeServings">Max Servings (from Recipe):</label>
                    <input type="number" name="max_RecipeServings" id="max_RecipeServings" value="<?php echo htmlspecialchars($_GET['max_RecipeServings'] ?? ''); ?>" min="0">
                    <br><br>
                    <h5>Time:</h5>
                    <?php
                    $time_form_keys = ['PrepTime' => 'Prep Time', 'CookTime' => 'Cook Time', 'TotalTime' => 'Total Time'];
                    foreach ($time_form_keys as $field_key => $field_label) {
                        echo '<div class="time-input-set">';
                        echo '<strong>' . $field_label . ':</strong><br>';
                        echo '<label for="min_' . $field_key . '_hr">Min:</label>';
                        echo '<span class="time-input-group">';
                        echo '<input type="number" name="min_' . $field_key . '_hr" id="min_' . $field_key . '_hr" placeholder="hr" value="' . htmlspecialchars($_GET['min_' . $field_key . '_hr'] ?? '') . '" min="0" max="838">';
                        echo '<span>hrs</span>';
                        echo '<input type="number" name="min_' . $field_key . '_min" id="min_' . $field_key . '_min" placeholder="min" value="' . htmlspecialchars($_GET['min_' . $field_key . '_min'] ?? '') . '" min="0" max="59">';
                        echo '<span>min</span>';
                        echo '</span>';
                        echo '<label for="max_' . $field_key . '_hr" style="margin-left:20px;">Max:</label>';
                        echo '<span class="time-input-group">';
                        echo '<input type="number" name="max_' . $field_key . '_hr" id="max_' . $field_key . '_hr" placeholder="hr" value="' . htmlspecialchars($_GET['max_' . $field_key . '_hr'] ?? '') . '" min="0" max="838">';
                        echo '<span>hrs</span>';
                        echo '<input type="number" name="max_' . $field_key . '_min" id="max_' . $field_key . '_min" placeholder="min" value="' . htmlspecialchars($_GET['max_' . $field_key . '_min'] ?? '') . '" min="0" max="59">';
                        echo '<span>min</span>';
                        echo '</span>';
                        echo '<br>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </form>
        </section>

        <section class="home-section">
            <h2>Newest Recipes (with Images)</h2>
            <?php if ($newestRecipesError): ?>
                <p class="error-message"><?php echo $newestRecipesError; ?></p>
            <?php elseif (!empty($newestRecipes)): ?>
                <ul class="recipe-list recipe-list-with-images">
                    <?php foreach ($newestRecipes as $recipe): ?>
                        <li>
                            <?php $imageUrl = extractFirstImageUrl($recipe['Image_URL']); ?>
                            <?php if ($imageUrl): ?>
                                <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                                    <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                                         alt="<?php echo htmlspecialchars($recipe['Recipe_Name']); ?>"
                                         class="recipe-list-image" loading="lazy" onerror="this.style.display='none'">
                                </a>
                            <?php else: ?>
                                <div class="recipe-list-image-placeholder">No Image</div>
                            <?php endif; ?>
                            <div class="recipe-list-info">
                                <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                                    <?php echo htmlspecialchars(html_entity_decode($recipe['Recipe_Name'])); ?>
                                </a>
                                <span class="rating">(Rating: <?php echo htmlspecialchars($recipe['Average_Rating'] ?? 'N/A'); ?>)</span>
                                <?php if (isset($_SESSION['username'])): ?>
                                    <i class="far fa-star favorite-star <?php echo (is_array($userFavorites) && in_array($recipe['RecipeId'], $userFavorites)) ? 'fas favorited' : 'far'; ?>"
                                       data-recipe-id="<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></i>
                                    <span class="favorite-message" id="fav-msg-<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No new recipes with images found.</p>
            <?php endif; ?>
            <a href="all_recipes.php">View All Recipes</a>
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
        <a href="pantry_search.php">Search by Ingredients You Have</a>

    </main>

    <footer class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> Recipe Website</p>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const advancedSearchBtn = document.getElementById('advancedSearchBtn');
        const advancedSearchOptions = document.getElementById('advancedSearchOptions');
        const resetSearchBtn = document.getElementById('resetSearchBtnIndex');
        const recipeSearchForm = document.getElementById('recipeSearchFormIndex');

        if(advancedSearchBtn && advancedSearchOptions) {
            advancedSearchBtn.addEventListener('click', function() {
                if (advancedSearchOptions.style.display === 'none' || advancedSearchOptions.style.display === '') {
                    advancedSearchOptions.style.display = 'block';
                    this.textContent = 'Hide Advanced';
                } else {
                    advancedSearchOptions.style.display = 'none';
                    this.textContent = 'Advanced Search';
                }
            });

            const urlParams = new URLSearchParams(window.location.search);
            let advancedActive = false;
            const advancedParamKeys = [
                'search_by', 'min_Calories', 'max_Calories', 'min_Fat', 'max_Fat',
                'min_Saturated_Fat', 'max_Saturated_Fat', 'min_Cholesterol', 'max_Cholesterol',
                'min_Sodium', 'max_Sodium', 'min_Carbohydrate', 'max_Carbohydrate',
                'min_Fiber', 'max_Fiber', 'min_Sugar', 'max_Sugar', 'min_Protein', 'max_Protein',
                'min_RecipeServings', 'max_RecipeServings',
                'min_PrepTime_hr', 'min_PrepTime_min', 'max_PrepTime_hr', 'max_PrepTime_min',
                'min_CookTime_hr', 'min_CookTime_min', 'max_CookTime_hr', 'max_CookTime_min',
                'min_TotalTime_hr', 'min_TotalTime_min', 'max_TotalTime_hr', 'max_TotalTime_min'
            ];
            for (const key of advancedParamKeys) {
                if (urlParams.has(key) && urlParams.get(key) !== '') {
                     if (key === 'search_by' && urlParams.get(key) === 'recipe_name' && !urlParams.has('q')) {
                        let otherAdvancedFilterPresent = false;
                        for (const otherKey of advancedParamKeys) {
                            if (otherKey !== 'search_by' && urlParams.has(otherKey) && urlParams.get(otherKey) !== '') {
                                otherAdvancedFilterPresent = true; break;
                            }
                        }
                        if (otherAdvancedFilterPresent) advancedActive = true;
                    } else if (key === 'search_by' && urlParams.get(key) !== 'recipe_name') {
                         advancedActive = true;
                    } else if (key !== 'search_by') {
                         advancedActive = true;
                    }
                    if (advancedActive) break;
                }
            }
            if ((!urlParams.has('q') || urlParams.get('q') === '') && !advancedActive) {
                for (const key of advancedParamKeys) {
                    if (key !== 'search_by' && urlParams.has(key) && urlParams.get(key) !== '') {
                        advancedActive = true;
                        break;
                    }
                }
            }

            if (advancedActive) {
                advancedSearchOptions.style.display = 'block';
                if(advancedSearchBtn) advancedSearchBtn.textContent = 'Hide Advanced';
            }
        }

        if (resetSearchBtn) {
            resetSearchBtn.addEventListener('click', function() {
                if (recipeSearchForm) {
                    const inputs = recipeSearchForm.querySelectorAll('input[type="text"], input[type="number"]');
                    inputs.forEach(input => input.value = '');
                    const selects = recipeSearchForm.querySelectorAll('select');
                    selects.forEach(select => select.selectedIndex = 0);
                }
                window.location.href = 'all_recipes.php';
            });
        }

        document.querySelectorAll('.favorite-star').forEach(star => {
            star.addEventListener('click', function() {
                const recipeId = this.dataset.recipeId;
                const messageEl = document.getElementById(`fav-msg-${recipeId}`);
                <?php if (isset($_SESSION['username'])): ?>
                    fetch('update_favorites.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ recipeId: recipeId, action: this.classList.contains('favorited') ? 'remove' : 'add' }) })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.toggle('favorited'); this.classList.toggle('fas'); this.classList.toggle('far');
                            messageEl.textContent = this.classList.contains('favorited') ? 'Added!' : 'Removed!';
                            setTimeout(() => messageEl.textContent = '', 2000);
                        } else { messageEl.textContent = data.message; setTimeout(() => messageEl.textContent = '', 3000); }
                    })
                    .catch(error => { console.error('Error:', error); messageEl.textContent = 'Error.'; setTimeout(() => messageEl.textContent = '', 3000); });
                <?php else: ?>
                    messageEl.textContent = 'Sign in to favorite.'; setTimeout(() => messageEl.textContent = '', 3000);
                <?php endif; ?>
            });
        });
    });
    </script>
</body>
</html>