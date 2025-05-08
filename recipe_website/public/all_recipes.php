<?php
session_start();

// 1. Include the database connection
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo

// --- Settings ---
$recipes_per_page = 20;

// --- Get and Validate Current Page Number for pagination ---
$current_page = 1;
if (isset($_GET['page'])) {
    if (filter_var($_GET['page'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $current_page = (int)$_GET['page'];
    } else {
        $preserved_search_params = [];
        foreach($_GET as $key => $value) {
            if ($key !== 'page') {
                 $preserved_search_params[$key] = $value;
            }
        }
        $redirect_url = "all_recipes.php";
        if (!empty($preserved_search_params)) {
            $redirect_url .= "?" . http_build_query($preserved_search_params);
        }
        if ($current_page != 1 && empty($preserved_search_params)) {
            header("Location: all_recipes.php");
            exit;
        } elseif ($current_page !=1) {
             // If invalid page but other params exist, they are preserved by redirect_url construction
        }
    }
}


$pageTitle = "All Recipes";
if ($current_page > 1) {
    $pageTitle .= " - Page " . $current_page;
}

$form_q = htmlspecialchars($_GET['q'] ?? '');
$form_search_by = $_GET['search_by'] ?? 'recipe_name';


$offset = ($current_page - 1) * $recipes_per_page;

$total_recipes = 0;
$total_pages = 1;
$queryError = null;

try {
    // MODIFIED: Removed Image_URL specific conditions to count ALL recipes
    $sqlCount = "SELECT COUNT(*) FROM Recipes
                 WHERE Recipe_Name IS NOT NULL AND Recipe_Name != ''";

    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute();
    $total_recipes = (int)$stmtCount->fetchColumn();

    if ($total_recipes > 0) {
        $total_pages = ceil($total_recipes / $recipes_per_page);
    } else {
        $total_pages = 1;
    }

    if ($current_page > $total_pages && $total_pages > 0) {
        $preserved_search_params_redir = [];
        foreach($_GET as $key => $value) {
            if ($key !== 'page') {
                 $preserved_search_params_redir[$key] = $value;
            }
        }
        $redirect_url_page = "all_recipes.php?page=" . $total_pages;
        if (!empty($preserved_search_params_redir)) {
             $redirect_url_page .= "&" . http_build_query($preserved_search_params_redir);
        }
        header("Location: " . $redirect_url_page);
        exit;
    }
     if ($current_page < 1 && $total_pages > 0) {
        $preserved_get_without_page = array_diff_key($_GET, ['page'=>1]);
        $redirect_query_string = !empty($preserved_get_without_page) ? '&'.http_build_query($preserved_get_without_page) : '';
        header("Location: all_recipes.php?page=1" . $redirect_query_string );
        exit;
    }

} catch (PDOException $e) {
    error_log("Database Count Error on All Recipes Page: " . $e->getMessage());
    $queryError = "Could not determine total recipe count.";
}

$pageTitle = "All Recipes";
if ($current_page > 1) {
    $pageTitle .= " - Page " . $current_page;
}

$recipes = [];
if (!isset($queryError)) {
    try {
        // MODIFIED: Removed Image_URL specific conditions to fetch ALL recipes
        $sql = "SELECT RecipeId, Recipe_Name, Average_Rating, Image_URL
                FROM Recipes
                WHERE Recipe_Name IS NOT NULL AND Recipe_Name != ''
                ORDER BY Recipe_Name ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':limit', $recipes_per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database Query Error on All Recipes Page (Fetch): " . $e->getMessage());
        $queryError = "Sorry, an error occurred while fetching recipes.";
        $recipes = [];
    }
}

$userFavorites = [];
if (isset($_SESSION['username'])) {
    try {
        $stmtUser = $pdo->prepare("SELECT Favorites FROM User WHERE Username = ?");
        $stmtUser->execute([$_SESSION['username']]);
        $resultUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $userFavorites = $resultUser ? json_decode($resultUser['Favorites'], true) : [];
        if ($userFavorites === null) {
            $userFavorites = [];
        }
    } catch (PDOException $e) {
        error_log("Database error fetching favorites for all_recipes.php: " . $e->getMessage());
        $userFavorites = [];
    }
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
        #advancedSearchOptionsAll label { display: inline-block; margin-right: 5px; margin-left:10px; font-size:0.9em; }
        #advancedSearchOptionsAll input[type="number"] { width: 60px; margin-right: 3px; padding: 4px; font-size:0.9em; }
        #advancedSearchOptionsAll .time-input-group span { margin-left: 2px; margin-right: 10px; font-size:0.9em; }
        #advancedSearchOptionsAll select { padding: 4px; font-size:0.9em;}
        #advancedSearchOptionsAll br { margin-bottom: 8px; line-height:1.5; }
        #advancedSearchOptionsAll h5 { margin-top: 12px; margin-bottom: 6px; font-size: 1em; color: #333;}
        #advancedSearchOptionsAll .time-input-set { margin-bottom: 5px; }

        .recipe-search-form-on-all input[type="text"]{
            padding: 5px 10px; font-size: 0.9em; margin-right: 5px; vertical-align: middle; box-sizing: border-box; height: 31px;
        }
        .recipe-search-form-on-all > button {
            padding: 5px 10px; font-size: 0.9em; margin-left: 5px; vertical-align: middle; cursor: pointer; box-sizing: border-box; height: 31px;
        }

        .pagination { margin: 2em 0 1em 0; text-align: center; }
        .pagination a, .pagination span { display: inline-block; padding: 0.5em 1em; margin: 0 0.2em; border: 1px solid #ddd; color: #0056b3; text-decoration: none; border-radius: 3px; }
        .pagination a:hover { background-color: #eee; }
        .pagination .current-page { background-color: #0056b3; color: white; border-color: #0056b3; font-weight: bold; }
        .pagination .disabled { color: #aaa; border-color: #eee; pointer-events: none; }

        .favorite-star { cursor: pointer; color: #ccc; }
        .favorite-star.favorited { color: gold; }
        .favorite-message { font-size: 0.8em; color: #777; margin-top: 0.2em; display: inline-block; margin-left: 5px; }
        .search-form-container { margin-bottom: 20px; }
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
    </style>
</head>
<body>

    <header class="site-header">
        <h1>Recipe Website</h1>
        <p><a href="index.php">&laquo; Back to Home</a></p>
    </header>

    <main class="container">
        <div class="search-form-container">
             <section class="home-section">
                <h2>Search All Recipes</h2>
                <form action="search_results.php" method="get" class="recipe-search-form-on-all" id="recipeSearchFormAll">
                    <input type="text" name="q" placeholder="Enter search term..." value="<?php echo $form_q; ?>">
                    <button type="button" id="advancedSearchBtnAll">Advanced Search</button>
                    <button type="submit">Search</button>
                    <button type="button" id="resetSearchBtnAll">Reset</button>

                    <div id="advancedSearchOptionsAll" style="display:none; border: 1px solid #ccc; padding: 15px; margin-top: 10px;">
                        <h4>Advanced Options</h4>
                        <label for="search_by_all">Search By:</label>
                        <select name="search_by" id="search_by_all">
                            <option value="recipe_name" <?php if ($form_search_by === 'recipe_name') echo 'selected'; ?>>Recipe Name</option>
                            <option value="keywords" <?php if ($form_search_by === 'keywords') echo 'selected'; ?>>Keywords (Name, Desc, Ingred.)</option>
                            <option value="author" <?php if ($form_search_by === 'author') echo 'selected'; ?>>Author ID</option>
                        </select>
                        <br><br>

                        <h5>Nutrition Facts (per serving):</h5>
                        <?php
                        $nutrition_form_fields_all_display = [
                            'Calories' => 'Calories', 'Fat' => 'Fat (g)', 'Saturated_Fat' => 'Saturated Fat (g)',
                            'Cholesterol' => 'Cholesterol (mg)', 'Sodium' => 'Sodium (mg)',
                            'Carbohydrate' => 'Carbohydrates (g)', 'Fiber' => 'Fiber (g)',
                            'Sugar' => 'Sugar (g)', 'Protein' => 'Protein (g)'
                        ];
                        foreach ($nutrition_form_fields_all_display as $field_key => $field_label) {
                            echo '<label for="min_' . $field_key . '_all">Min ' . $field_label . ':</label>';
                            echo '<input type="number" name="min_' . $field_key . '" id="min_' . $field_key . '_all" step="any" value="' . htmlspecialchars($_GET['min_' . $field_key] ?? '') . '" min="0">';
                            echo '<label for="max_' . $field_key . '_all">Max ' . $field_label . ':</label>';
                            echo '<input type="number" name="max_' . $field_key . '" id="max_' . $field_key . '_all" step="any" value="' . htmlspecialchars($_GET['max_' . $field_key] ?? '') . '" min="0">';
                            echo '<br>';
                        }
                        ?>

                        <h5>Recipe Yield:</h5>
                        <label for="min_RecipeServings_all">Min Servings (from Recipe):</label>
                        <input type="number" name="min_RecipeServings" id="min_RecipeServings_all" value="<?php echo htmlspecialchars($_GET['min_RecipeServings'] ?? ''); ?>" min="0">
                        <label for="max_RecipeServings_all">Max Servings (from Recipe):</label>
                        <input type="number" name="max_RecipeServings" id="max_RecipeServings_all" value="<?php echo htmlspecialchars($_GET['max_RecipeServings'] ?? ''); ?>" min="0">
                        <br><br>

                        <h5>Time:</h5>
                        <?php
                        $time_form_keys_all_display = ['PrepTime' => 'Prep Time', 'CookTime' => 'Cook Time', 'TotalTime' => 'Total Time'];
                        foreach ($time_form_keys_all_display as $field_key => $field_label) {
                            echo '<div class="time-input-set">';
                            echo '<strong>' . $field_label . ':</strong><br>';
                            echo '<label for="min_' . $field_key . '_hr_all">Min:</label>';
                            echo '<span class="time-input-group">';
                            echo '<input type="number" name="min_' . $field_key . '_hr" id="min_' . $field_key . '_hr_all" placeholder="hr" value="' . htmlspecialchars($_GET['min_' . $field_key . '_hr'] ?? '') . '" min="0" max="838">';
                            echo '<span>hrs</span>';
                            echo '<input type="number" name="min_' . $field_key . '_min" id="min_' . $field_key . '_min_all" placeholder="min" value="' . htmlspecialchars($_GET['min_' . $field_key . '_min'] ?? '') . '" min="0" max="59">';
                            echo '<span>min</span>';
                            echo '</span>';
                            echo '<label for="max_' . $field_key . '_hr_all" style="margin-left:20px;">Max:</label>';
                            echo '<span class="time-input-group">';
                            echo '<input type="number" name="max_' . $field_key . '_hr" id="max_' . $field_key . '_hr_all" placeholder="hr" value="' . htmlspecialchars($_GET['max_' . $field_key . '_hr'] ?? '') . '" min="0" max="838">';
                            echo '<span>hrs</span>';
                            echo '<input type="number" name="max_' . $field_key . '_min" id="max_' . $field_key . '_min_all" placeholder="min" value="' . htmlspecialchars($_GET['max_' . $field_key . '_min'] ?? '') . '" min="0" max="59">';
                            echo '<span>min</span>';
                            echo '</span>';
                            echo '<br>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                </form>
            </section>
        </div>

        <h2><?php echo htmlspecialchars($pageTitle); ?></h2>
        <p>Showing page <?php echo $current_page; ?> of <?php echo $total_pages; ?> (<?php echo $total_recipes; ?> total recipes found)</p> 
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
                            <?php echo htmlspecialchars(html_entity_decode($recipe['Recipe_Name'])); ?>
                            </a>
                            <span class="rating">(Rating: <?php echo htmlspecialchars($recipe['Average_Rating'] ?? 'N/A'); ?>)</span>
                            <?php if (isset($_SESSION['username'])): ?>
                                <i class="far fa-star favorite-star <?php echo (is_array($userFavorites) && in_array($recipe['RecipeId'], $userFavorites)) ? 'fas favorited' : 'far'; ?>"
                                   data-recipe-id="<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></i>
                                <span class="favorite-message" id="fav-msg-allr-<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No recipes found.</p> {/* MODIFIED TEXT */}
        <?php endif; ?>

        <div class="pagination">
            <?php if ($total_pages > 1): ?>
                <?php
                $page_query_params_all_pg = $_GET;
                unset($page_query_params_all_pg['page']);
                $base_query_string_all_pg = http_build_query($page_query_params_all_pg);
                $base_pagination_url_all_pg = "all_recipes.php?" . ($base_query_string_all_pg ? $base_query_string_all_pg . '&' : '');
                ?>
                <?php if ($current_page > 1): ?>
                    <a href="<?php echo $base_pagination_url_all_pg; ?>page=<?php echo $current_page - 1; ?>">&laquo; Previous</a>
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
                            <a href="<?php echo $base_pagination_url_all_pg; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php
                     elseif (($i == $current_page - $range - 1 && $current_page - $range - 1 > 1) || ($i == $current_page + $range + 1 && $current_page + $range + 1 < $totalPages)):
                    ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo $base_pagination_url_all_pg; ?>page=<?php echo $current_page + 1; ?>">Next &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &raquo;</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </main>

    <footer class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> Recipe Website</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const advancedSearchBtn = document.getElementById('advancedSearchBtnAll');
            const advancedSearchOptions = document.getElementById('advancedSearchOptionsAll');
            const resetSearchBtn = document.getElementById('resetSearchBtnAll');
            const recipeSearchForm = document.getElementById('recipeSearchFormAll');

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

            if (recipeSearchForm) {
                recipeSearchForm.addEventListener('submit', function() { // This form submits to search_results.php
                    if (advancedSearchOptions) {
                        advancedSearchOptions.style.display = 'none';
                    }
                    if (advancedSearchBtn) {
                        advancedSearchBtn.textContent = 'Advanced Search';
                    }
                });
            }

            if (resetSearchBtn) {
                resetSearchBtn.addEventListener('click', function() {
                    if (recipeSearchForm) {
                        const inputs = recipeSearchForm.querySelectorAll('input[type="text"], input[type="number"]');
                        inputs.forEach(input => input.value = '');
                        const selects = recipeSearchForm.querySelectorAll('select');
                        selects.forEach(select => select.selectedIndex = 0);
                    }
                    window.location.href = 'all_recipes.php'; // Reset always goes to default all_recipes.php
                });
            }

            document.querySelectorAll('.favorite-star').forEach(star => {
                star.addEventListener('click', function() {
                    const recipeId = this.dataset.recipeId;
                    const messageEl = document.getElementById(`fav-msg-allr-${recipeId}`);
                    <?php if (isset($_SESSION['username'])): ?>
                        fetch('update_favorites.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ recipeId: recipeId, action: this.classList.contains('favorited') ? 'remove' : 'add' }) })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.classList.toggle('favorited'); this.classList.toggle('fas'); this.classList.toggle('far');
                                if(messageEl) messageEl.textContent = this.classList.contains('favorited') ? 'Added!' : 'Removed!';
                                setTimeout(() => {if(messageEl) messageEl.textContent = '';}, 2000);
                            } else { 
                                if(messageEl) messageEl.textContent = data.message; 
                                setTimeout(() => {if(messageEl) messageEl.textContent = '';}, 3000); 
                            }
                        })
                        .catch(error => { 
                            console.error('Error:', error); 
                            if(messageEl) messageEl.textContent = 'Error.'; 
                            setTimeout(() => {if(messageEl) messageEl.textContent = '';}, 3000); 
                        });
                    <?php else: ?>
                        if(messageEl) messageEl.textContent = 'Sign in to favorite.'; 
                        setTimeout(() => {if(messageEl) messageEl.textContent = '';}, 3000);
                    <?php endif; ?>
                });
            });
        });
    </script>
</body>
</html>