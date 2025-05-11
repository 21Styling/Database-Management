<?php
session_start();
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo

// --- CONSTANT FOR PHP FILTERING LIMIT ---
define('PHP_FILTER_LIMIT', 300); // Process up to 300 recipes in PHP for ingredient matching

// --- Search Term & Type ---
$searchTerm = $_GET['q'] ?? '';
$searchBy = $_GET['search_by'] ?? 'recipe_name';
$matchOwnedIngredientsValResults = isset($_GET['match_owned_ingredients']) ? 'checked' : '';

// --- Pagination ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$resultsPerPage = 20;
// $offset will be calculated after we know the totalResults from PHP filtering if applicable

// ... (Sort parameters, SQL Base, SQL Where, Filter mappings - remain mostly the same as your updated version) ...
// --- Get Sort Parameters ---
$sort_by_options_sr = [
    'relevance'      => ['column' => null,               'default_dir' => 'ASC'],
    'recipe_name'    => ['column' => 'R.Recipe_Name',    'default_dir' => 'ASC'],
    'date_added'     => ['column' => 'R.Date',           'default_dir' => 'DESC'],
    'calories'       => ['column' => 'M.Calories',       'default_dir' => 'ASC'],
    'review_count'   => ['column' => 'R.Rating_Count',   'default_dir' => 'DESC'],
    'average_review' => ['column' => 'R.Average_Rating', 'default_dir' => 'DESC'],
    'total_time'     => ['column' => 'M.Total_Time',     'default_dir' => 'ASC']
];
$sort_by_sr = isset($_GET['sort_by']) && isset($sort_by_options_sr[$_GET['sort_by']]) ? $_GET['sort_by'] : 'relevance';
$sort_column_info_sr = $sort_by_options_sr[$sort_by_sr];
$current_sort_column_sr = $sort_column_info_sr['column'];
$sort_dir_sr = isset($_GET['sort_dir']) && in_array(strtoupper($_GET['sort_dir']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_dir']) : $sort_column_info_sr['default_dir'];


// --- Base SQL with JOIN ---
$joinCondition = "R.RecipeId = M.MealId";
$sqlBase = "SELECT R.RecipeId, R.Recipe_Name, R.Author, R.Description, R.Ingredients, R.Ingredient_Quantity,
            R.Average_Rating, R.Image_URL, R.Servings AS RecipeTableServings, R.Rating_Count, R.Date AS SubmissionDate,
            M.Calories, M.Fat, M.Saturated_Fat, M.Cholesterol, M.Sodium,
            M.Carbohydrate, M.Fiber, M.Sugar, M.Protein,
            M.Prep_Time, M.Cook_Time, M.Total_Time, M.Servings AS MealTableServings
            FROM Recipes R
            LEFT JOIN Meal M ON " . $joinCondition;

$sqlWhere = [];
$sqlParams = [];

// --- Build WHERE clause for search term ---
if (!empty($searchTerm)) {
    switch ($searchBy) {
        case 'keywords':
            $sqlWhere[] = "(R.Recipe_Name LIKE :searchTerm OR R.Description LIKE :searchTerm OR R.Ingredients LIKE :searchTerm)";
            $sqlParams[':searchTerm'] = '%' . $searchTerm . '%';
            break;
        case 'author':
            $sqlWhere[] = "R.Author = :searchTermAuthor"; 
            $sqlParams[':searchTermAuthor'] = $searchTerm; 
            break;
        case 'recipe_name':
        default:
            $sqlWhere[] = "R.Recipe_Name LIKE :searchTerm";
            $sqlParams[':searchTerm'] = '%' . $searchTerm . '%';
            break;
    }
}

// --- Define mappings for filter fields ---
$nutrition_db_map = [
    'Calories' => 'M.Calories', 'Fat' => 'M.Fat', 'Saturated_Fat' => 'M.Saturated_Fat',
    'Cholesterol' => 'M.Cholesterol', 'Sodium' => 'M.Sodium', 'Carbohydrate' => 'M.Carbohydrate',
    'Fiber' => 'M.Fiber', 'Sugar' => 'M.Sugar', 'Protein' => 'M.Protein'
];
$servings_db_map = ['RecipeServings' => 'R.Servings']; 
$time_form_keys_srp = ['PrepTime' => 'Prep Time', 'CookTime' => 'Cook Time', 'TotalTime' => 'Total Time'];
$time_db_columns_map_srp = ['PrepTime' => 'M.Prep_Time', 'CookTime' => 'M.Cook_Time', 'TotalTime' => 'M.Total_Time'];


// --- Process Nutrition and Servings Filters ---
foreach ($nutrition_db_map as $form_key => $db_column) {
    if (isset($_GET['min_' . $form_key]) && is_numeric($_GET['min_' . $form_key]) && $_GET['min_' . $form_key] !== '') {
        $sqlWhere[] = "$db_column >= :min_$form_key";
        $sqlParams[":min_$form_key"] = $_GET['min_' . $form_key];
    }
    if (isset($_GET['max_' . $form_key]) && is_numeric($_GET['max_' . $form_key]) && $_GET['max_' . $form_key] !== '') {
        $sqlWhere[] = "$db_column <= :max_$form_key";
        $sqlParams[":max_$form_key"] = $_GET['max_' . $form_key];
    }
}
// ... (similar for servings and time filters, ensure these are correct from your previous update) ...
foreach ($servings_db_map as $form_key => $db_column) {
    if (isset($_GET['min_' . $form_key]) && is_numeric($_GET['min_' . $form_key]) && $_GET['min_' . $form_key] !== '') {
        $sqlWhere[] = "$db_column >= :min_$form_key";
        $sqlParams[":min_$form_key"] = $_GET['min_' . $form_key];
    }
    if (isset($_GET['max_' . $form_key]) && is_numeric($_GET['max_' . $form_key]) && $_GET['max_' . $form_key] !== '') {
        $sqlWhere[] = "$db_column <= :max_$form_key";
        $sqlParams[":max_$form_key"] = $_GET['max_' . $form_key];
    }
}

foreach ($time_form_keys_srp as $form_key_filter_srp => $label_srp) {
    $db_column = $time_db_columns_map_srp[$form_key_filter_srp];
    $min_hr_input = $_GET['min_' . $form_key_filter_srp . '_hr'] ?? ''; $min_min_input = $_GET['min_' . $form_key_filter_srp . '_min'] ?? '';
    if (($min_hr_input !== '' && is_numeric($min_hr_input)) || ($min_min_input !== '' && is_numeric($min_min_input))) {
        $hours = (is_numeric($min_hr_input) && $min_hr_input >= 0) ? (int)$min_hr_input : 0;
        $minutes = (is_numeric($min_min_input) && $min_min_input >= 0 && $min_min_input < 60) ? (int)$min_min_input : 0;
        $min_total_minutes_value = ($hours * 60) + $minutes;
        $sqlWhere[] = "(TIME_TO_SEC($db_column) / 60) >= :min_total_minutes_$form_key_filter_srp";
        $sqlParams[":min_total_minutes_$form_key_filter_srp"] = $min_total_minutes_value;
    }

    $max_hr_input = $_GET['max_' . $form_key_filter_srp . '_hr'] ?? ''; $max_min_input = $_GET['max_' . $form_key_filter_srp . '_min'] ?? '';
    if (($max_hr_input !== '' && is_numeric($max_hr_input)) || ($max_min_input !== '' && is_numeric($max_min_input))) {
        $hours = (is_numeric($max_hr_input) && $max_hr_input >= 0) ? (int)$max_hr_input : 0;
        $minutes = (is_numeric($max_min_input) && $max_min_input >= 0 && $max_min_input < 60) ? (int)$max_min_input : 0;
        $max_total_minutes_value = ($hours * 60) + $minutes;
        if ($max_total_minutes_value > 0 || ($max_hr_input !== '' || $max_min_input !== '')) { 
            $sqlWhere[] = "(TIME_TO_SEC($db_column) / 60) <= :max_total_minutes_$form_key_filter_srp";
            $sqlParams[":max_total_minutes_$form_key_filter_srp"] = $max_total_minutes_value;
        }
    }
}


$sqlWhere[] = "R.Recipe_Name IS NOT NULL AND R.Recipe_Name != ''"; 

$finalSql = $sqlBase;
if (!empty($sqlWhere)) {
    $finalSql .= " WHERE " . implode(" AND ", $sqlWhere);
}

$totalResults = 0;
$totalPages = 0;
$searchResults = [];
$error_message = null;
$debug_sql_tried = "";

$hasSearchCriteria = !empty($searchTerm) || !empty(array_filter($_GET, function($v, $k){
    return $v !== '' && !in_array($k, ['q', 'page', 'sort_by', 'sort_dir']);
}, ARRAY_FILTER_USE_BOTH));
if (isset($_GET['match_owned_ingredients'])) { $hasSearchCriteria = true; }


if ($hasSearchCriteria) {
    try {
        $orderByClause = "";
        if ($current_sort_column_sr) { 
            $orderByClause = " ORDER BY $current_sort_column_sr $sort_dir_sr";
            if ($sort_by_sr !== 'recipe_name' && $current_sort_column_sr !== 'R.Recipe_Name') $orderByClause .= ", R.Recipe_Name ASC";
            if ($sort_by_sr !== 'date_added' && $current_sort_column_sr !== 'R.Date') $orderByClause .= ", R.Date DESC";
        } else { // Default sort if no specific sort or relevance is chosen
            $orderByClause = " ORDER BY R.Recipe_Name ASC";
        }

        $dataQuerySql = $finalSql . " GROUP BY R.RecipeId " . $orderByClause;
        
        // Apply a limit if 'match_owned_ingredients' is ON to reduce PHP processing load
        if (!empty($_GET['match_owned_ingredients']) && isset($_SESSION['username'])) {
            $dataQuerySql .= " LIMIT " . PHP_FILTER_LIMIT; // No OFFSET here, pagination applied after PHP filter
        }
        // $debug_sql_tried = $dataQuerySql; // For debugging

        $stmtPaged = $pdo->prepare($dataQuerySql);
        if (!empty($sqlParams)) {
            foreach ($sqlParams as $key => $value) {
                // Determine param type (ensure this is robust)
                $paramType = is_int($value) ? PDO::PARAM_INT : (is_bool($value) ? PDO::PARAM_BOOL : (is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR));
                $stmtPaged->bindValue($key, $value, $paramType);
            }
        }
        $stmtPaged->execute();
        $allMatchingRecipes = $stmtPaged->fetchAll(PDO::FETCH_ASSOC);
        
        $phpFilteredRecipes = [];

        if (!empty($_GET['match_owned_ingredients']) && isset($_SESSION['username'])) {
            $username = $_SESSION['username'];
            $stmtUserIng = $pdo->prepare("SELECT Owned_Ingredients, User_Quantity FROM User WHERE Username = ?");
            $stmtUserIng->execute([$username]);
            $userIngData = $stmtUserIng->fetch(PDO::FETCH_ASSOC);
            $ownedIngredientNames = json_decode($userIngData['Owned_Ingredients'] ?? '[]', true);
            $ownedIngredientQuantitiesFull = json_decode($userIngData['User_Quantity'] ?? '[]', true);
            
            $parsedUserIngredients = []; // Key: ingredient name, Value: ['quantity' => X, 'unit' => Y]
            // Basic parsing for user's owned ingredients (NEEDS TO BE VERY ROBUST)
            if (is_array($ownedIngredientNames) && is_array($ownedIngredientQuantitiesFull)) {
                foreach ($ownedIngredientNames as $idx => $name) {
                    if (!isset($ownedIngredientQuantitiesFull[$idx])) continue;
                    $fullName = strtolower(trim($name));
                    $quantityStr = $ownedIngredientQuantitiesFull[$idx];
                    if (preg_match('/^([\d\.\s\/]+)\s*([a-zA-Z]+)/', $quantityStr, $matches)) { // Improved regex for quantity
                        $quantityValue = $matches[1];
                        // Basic fraction handling (e.g., "1/2", "1 1/2") - needs more work for robustness
                        if (strpos($quantityValue, '/') !== false) {
                            if (strpos($quantityValue, ' ') !== false) { // "1 1/2"
                                list($whole, $fraction) = explode(' ', $quantityValue);
                                list($num, $den) = explode('/', $fraction);
                                if ($den != 0) $quantityValue = (float)$whole + ((float)$num / (float)$den);
                                else $quantityValue = (float)$whole;
                            } else { // "1/2"
                                list($num, $den) = explode('/', $quantityValue);
                                if ($den != 0) $quantityValue = (float)$num / (float)$den;
                                else $quantityValue = 0;
                            }
                        } else {
                            $quantityValue = floatval($quantityValue);
                        }
                        $parsedUserIngredients[$fullName] = ['quantity' => $quantityValue, 'unit' => strtolower(trim($matches[2]))];
                    } elseif (!empty($fullName)) { // If no quantity/unit but name exists, assume available (e.g., "salt")
                        $parsedUserIngredients[$fullName] = ['quantity' => PHP_INT_MAX, 'unit' => 'unit']; // Effectively infinite quantity
                    }
                }
            }
            
            foreach ($allMatchingRecipes as $recipe) {
                $canMakeRecipe = true; 
                // Placeholder for the actual complex ingredient parsing and comparison logic
                // This logic needs to be very robust, handle various string formats for ingredients,
                // perform unit conversions, and compare quantities.
                // Example: $recipeIngredients = parseRecipeIngredients($recipe['Ingredients'], $recipe['Ingredient_Quantity']);
                // foreach ($recipeIngredient as $reqIng) { if (!userHasEnough($reqIng, $parsedUserIngredients)) { $canMakeRecipe = false; break; }}
                
                // --- Rudimentary Check (must be replaced with robust parsing & comparison) ---
                $recipeIngredientsString = strtolower(is_array($recipe['Ingredients']) ? implode(",", $recipe['Ingredients']) : (string)$recipe['Ingredients']);
                if (empty($recipeIngredientsString) || $recipeIngredientsString === "[]" || $recipeIngredientsString === "''") {
                    // No ingredients listed in recipe, assume can be made
                } else {
                    // Attempt to parse recipe ingredients (this is highly simplified)
                    // A better approach would parse Recipe.Ingredients and Recipe.Ingredient_Quantity
                    $tempRecipeIngredients = array_map('trim', preg_split("/(,'|\s*,\s*|'\s*,\s*')/", trim($recipeIngredientsString, "[]'\"")));
                    $tempRecipeIngredients = array_filter($tempRecipeIngredients); // Remove empty items

                    if (empty($tempRecipeIngredients)) {
                         // Still no specific ingredients after parsing
                    } else {
                        foreach($tempRecipeIngredients as $r_ing_name_only) {
                            if (empty($r_ing_name_only)) continue;
                            $foundThisRecipeIng = false;
                            foreach($parsedUserIngredients as $owned_name => $owned_details) {
                                // Simple name check (e.g., "flour" in "all-purpose flour")
                                if (strpos($owned_name, $r_ing_name_only) !== false || strpos($r_ing_name_only, $owned_name) !== false) {
                                    // Here, you would also parse Recipe.Ingredient_Quantity for $r_ing_name_only
                                    // and compare its quantity and unit against $owned_details['quantity'] and $owned_details['unit']
                                    // after appropriate unit conversion.
                                    $foundThisRecipeIng = true;
                                    break;
                                }
                            }
                            if (!$foundThisRecipeIng) {
                                $canMakeRecipe = false;
                                break;
                            }
                        }
                    }
                }
                // --- End Rudimentary Check ---

                if ($canMakeRecipe) {
                    $phpFilteredRecipes[] = $recipe;
                }
            }
            $searchResults = $phpFilteredRecipes;
            $totalResults = count($searchResults);
        } else { // Toggle is OFF, or user not logged in but toggle ON (no PHP filter applied beyond SQL)
            $searchResults = $allMatchingRecipes; // These are already limited if toggle was on but no session
                                                // Or all if toggle was off (SQL will get count later)
            // If toggle was OFF, we need to get the true total count from the DB without the PHP_FILTER_LIMIT
            if (empty($_GET['match_owned_ingredients'])) {
                $countSql = "SELECT COUNT(DISTINCT R.RecipeId) as total FROM Recipes R LEFT JOIN Meal M ON " . $joinCondition;
                if (!empty($sqlWhere)) {
                    $countSql .= " WHERE " . implode(" AND ", $sqlWhere);
                }
                $stmtTotalCount = $pdo->prepare($countSql);
                if (!empty($sqlParams)) {
                    foreach ($sqlParams as $key => $value) {
                        $paramType = is_int($value) ? PDO::PARAM_INT : (is_bool($value) ? PDO::PARAM_BOOL : (is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR));
                        $stmtTotalCount->bindValue($key, $value, $paramType);
                    }
                }
                $stmtTotalCount->execute();
                $totalResults = (int)$stmtTotalCount->fetchColumn();
                
                // And now, apply SQL pagination to the $searchResults if toggle was off
                // This re-fetches the paged data.
                $offsetForSqlPage = ($page - 1) * $resultsPerPage;
                $dataQuerySqlWithPage = $finalSql . " GROUP BY R.RecipeId " . $orderByClause . " LIMIT :limit OFFSET :offset";
                $stmtSqlPaged = $pdo->prepare($dataQuerySqlWithPage);
                 if (!empty($sqlParams)) {
                    foreach ($sqlParams as $key => $value) {
                        $paramType = is_int($value) ? PDO::PARAM_INT : (is_bool($value) ? PDO::PARAM_BOOL : (is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR));
                        $stmtSqlPaged->bindValue($key, $value, $paramType);
                    }
                }
                $stmtSqlPaged->bindValue(':limit', $resultsPerPage, PDO::PARAM_INT);
                $stmtSqlPaged->bindValue(':offset', $offsetForSqlPage, PDO::PARAM_INT);
                $stmtSqlPaged->execute();
                $searchResults = $stmtSqlPaged->fetchAll(PDO::FETCH_ASSOC);

            } else { // Toggle was ON but no session, $allMatchingRecipes is already limited by PHP_FILTER_LIMIT
                 $totalResults = count($searchResults); // Total is just what we fetched and "can make" (all of them in this case)
            }
        }

        // Apply PHP-side pagination to the $searchResults array
        $totalPages = ceil($totalResults / $resultsPerPage);
        if ($page > $totalPages && $totalPages > 0) { $page = $totalPages; }
        if ($page < 1) { $page = 1; }
        $offset = ($page - 1) * $resultsPerPage;
        $searchResults = array_slice($searchResults, $offset, $resultsPerPage);

    } catch (PDOException $e) {
        error_log("Search Results PDOException: " . $e->getMessage() . " --- SQL: " . ($debug_sql_tried ?: "Not available"));
        $error_message = "An error occurred during the search. Please try again later.";
        $searchResults = [];
        $totalResults = 0;
        $totalPages = 0;
    }
}

// ... (Rest of the file: $userFavorites, extractFirstImageUrl, HTML structure)
// Make sure the HTML part is identical to your updated version from the previous step,
// including the new toggle checkbox inside #advancedSearchOptionsResults and the JavaScript.
$userFavorites = [];
if (isset($_SESSION['username'])) {
    try {
        $stmtFav = $pdo->prepare("SELECT Favorites FROM User WHERE Username = ?");
        $stmtFav->execute([$_SESSION['username']]);
        $resultFav = $stmtFav->fetch(PDO::FETCH_ASSOC);
        $userFavorites = $resultFav ? json_decode($resultFav['Favorites'], true) : [];
        if ($userFavorites === null) $userFavorites = []; 
    } catch (PDOException $e) {
        error_log("Database error fetching favorites: " . $e->getMessage());
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

$pageTitle = "Search Results";
if (!empty($searchTerm)) { $pageTitle .= " for \"" . htmlspecialchars($searchTerm) . "\""; }
elseif ($hasSearchCriteria) { $pageTitle = "Advanced Search Results"; }
if ($sort_by_sr !== 'relevance') {
    $pageTitle .= " (Sorted by " . ucwords(str_replace('_', ' ', $sort_by_sr)) . " " . $sort_dir_sr . ")";
}
if (!empty($_GET['match_owned_ingredients']) && isset($_SESSION['username'])) {
    $pageTitle .= " [My Ingredients]";
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
        #advancedSearchOptionsResults label { display: inline-block; margin-right: 5px; margin-left:10px; font-size:0.9em; }
        #advancedSearchOptionsResults input[type="number"] { width: 60px; margin-right: 3px; padding: 4px; font-size:0.9em; }
        #advancedSearchOptionsResults .time-input-group span { margin-left: 2px; margin-right: 10px; font-size:0.9em; }
        #advancedSearchOptionsResults select { padding: 4px; font-size:0.9em;}
        #advancedSearchOptionsResults br { margin-bottom: 8px; line-height:1.5; }
        #advancedSearchOptionsResults h5 { margin-top: 12px; margin-bottom: 6px; font-size: 1em; color: #333;}
        #advancedSearchOptionsResults .time-input-set { margin-bottom: 5px; }
        .pagination { margin: 2em 0 1em 0; text-align: center; }
        .pagination a, .pagination span { display: inline-block; padding: 0.5em 1em; margin: 0 0.2em; border: 1px solid #ddd; color: #0056b3; text-decoration: none; border-radius: 3px; }
        .pagination a:hover { background-color: #eee; }
        .pagination .current-page { background-color: #0056b3; color: white; border-color: #0056b3; font-weight: bold; }
        .pagination .disabled { color: #aaa; border-color: #eee; pointer-events: none; }
        .favorite-star { cursor: pointer; color: #ccc; }
        .favorite-star.favorited { color: gold; }
        .favorite-message { font-size: 0.8em; color: #777; margin-top: 0.2em; display: inline-block; margin-left: 5px;}
        .search-summary { margin-top: 1em; margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #eee; font-size: 0.9em; color: #555; }
        .search-summary p { margin: 0.3em 0; }
        .search-form-container { margin-bottom: 20px; }
         .recipe-list-image-placeholder { 
            width: 80px; height: 60px; background-color: #eee; display: flex; align-items: center;
            justify-content: center; color: #aaa; font-style: italic; margin-right: 1em; 
            border-radius: 3px; flex-shrink: 0; text-align: center; font-size:0.8em;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <h1><?php echo htmlspecialchars(explode(" (Sorted", $pageTitle)[0]); ?></h1>
        <p><a href="index.php">&laquo; Back to Home / New Search</a></p>
    </header>

    <main class="container">
        <div class="search-form-container">
            <section class="home-section">
                <h2>Refine Search or Start New</h2>
                <form action="search_results.php" method="get" class="recipe-search-form-on-results" id="recipeSearchFormResults">
                    <div style="display:flex; align-items:center; width:100%; flex-wrap: wrap; margin-bottom:10px;">
                        <input type="text" name="q" placeholder="Enter search term..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" style="flex-grow:1; margin-right:5px; margin-bottom: 5px;">
                        <button type="button" id="advancedSearchBtnResults" style="margin-bottom: 5px;">Advanced Search</button>
                        <button type="submit" style="margin-bottom: 5px;">Search</button>
                        <button type="button" id="resetSearchBtnResults" style="margin-bottom: 5px;">Reset</button>
                    </div>

                    <div id="advancedSearchOptionsResults" style="display:none; border: 1px solid #ccc; padding: 15px; margin-top: 10px; margin-bottom:10px;">
                        <h4>Advanced Options</h4>
                        <label for="search_by_results">Search By:</label>
                        <select name="search_by" id="search_by_results">
                            <option value="recipe_name" <?php if (($_GET['search_by'] ?? 'recipe_name') === 'recipe_name') echo 'selected'; ?>>Recipe Name</option>
                            <option value="keywords" <?php if (($_GET['search_by'] ?? '') === 'keywords') echo 'selected'; ?>>Keywords (Name, Desc, Ingred.)</option>
                            <option value="author" <?php if (($_GET['search_by'] ?? '') === 'author') echo 'selected'; ?>>Author</option>
                        </select>
                        <br><br>

                        <h5>Ingredient Matching:</h5>
                        <label for="match_owned_ingredients_results">
                            <input type="checkbox" name="match_owned_ingredients" id="match_owned_ingredients_results" value="1" <?php echo $matchOwnedIngredientsValResults; ?>>
                            Only show recipes I can make with my ingredients
                        </label>
                        <?php if (isset($_GET['match_owned_ingredients']) && !isset($_SESSION['username'])): ?>
                            <small style="color:red; display:block;"> (Sign in to use this feature effectively)</small>
                        <?php endif; ?>
                        <br><br>

                        <h5>Nutrition Facts (per serving):</h5>
                        <?php
                        $nutrition_form_fields_display_srp = [
                            'Calories' => 'Calories', 'Fat' => 'Fat (g)', 'Saturated_Fat' => 'Saturated Fat (g)',
                            'Cholesterol' => 'Cholesterol (mg)', 'Sodium' => 'Sodium (mg)',
                            'Carbohydrate' => 'Carbohydrates (g)', 'Fiber' => 'Fiber (g)',
                            'Sugar' => 'Sugar (g)', 'Protein' => 'Protein (g)'
                        ];
                        foreach ($nutrition_form_fields_display_srp as $field_key => $field_label) {
                            echo '<label for="min_' . $field_key . '_results">Min ' . $field_label . ':</label>';
                            echo '<input type="number" name="min_' . $field_key . '" id="min_' . $field_key . '_results" step="any" value="' . htmlspecialchars($_GET['min_' . $field_key] ?? '') . '" min="0">';
                            echo '<label for="max_' . $field_key . '_results">Max ' . $field_label . ':</label>';
                            echo '<input type="number" name="max_' . $field_key . '" id="max_' . $field_key . '_results" step="any" value="' . htmlspecialchars($_GET['max_' . $field_key] ?? '') . '" min="0">';
                            echo '<br>';
                        }
                        ?>
                        <h5>Recipe Yield:</h5>
                        <label for="min_RecipeServings_results">Min Servings (from Recipe):</label>
                        <input type="number" name="min_RecipeServings" id="min_RecipeServings_results" value="<?php echo htmlspecialchars($_GET['min_RecipeServings'] ?? ''); ?>" min="0">
                        <label for="max_RecipeServings_results">Max Servings (from Recipe):</label>
                        <input type="number" name="max_RecipeServings" id="max_RecipeServings_results" value="<?php echo htmlspecialchars($_GET['max_RecipeServings'] ?? ''); ?>" min="0">
                        <h5>Time:</h5>
                        <?php
                        foreach ($time_form_keys_srp as $field_key_srp => $field_label_srp) {
                            echo '<div class="time-input-set">';
                            echo '<strong>' . $field_label_srp . ':</strong><br>';
                            echo '<label for="min_' . $field_key_srp . '_hr_results">Min:</label>';
                            echo '<span class="time-input-group">';
                            echo '<input type="number" name="min_' . $field_key_srp . '_hr" id="min_' . $field_key_srp . '_hr_results" placeholder="hr" value="' . htmlspecialchars($_GET['min_' . $field_key_srp . '_hr'] ?? '') . '" min="0" max="838">';
                            echo '<span>hrs</span>';
                            echo '<input type="number" name="min_' . $field_key_srp . '_min" id="min_' . $field_key_srp . '_min_results" placeholder="min" value="' . htmlspecialchars($_GET['min_' . $field_key_srp . '_min'] ?? '') . '" min="0" max="59">';
                            echo '<span>min</span>';
                            echo '</span>';
                            echo '<label for="max_' . $field_key_srp . '_hr_results" style="margin-left:20px;">Max:</label>';
                            echo '<span class="time-input-group">';
                            echo '<input type="number" name="max_' . $field_key_srp . '_hr" id="max_' . $field_key_srp . '_hr_results" placeholder="hr" value="' . htmlspecialchars($_GET['max_' . $field_key_srp . '_hr'] ?? '') . '" min="0" max="838">';
                            echo '<span>hrs</span>';
                            echo '<input type="number" name="max_' . $field_key_srp . '_min" id="max_' . $field_key_srp . '_min_results" placeholder="min" value="' . htmlspecialchars($_GET['max_' . $field_key_srp . '_min'] ?? '') . '" min="0" max="59">';
                            echo '<span>min</span>';
                            echo '</span>';
                            echo '<br>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <div class="sort-controls-container" style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee; justify-content: flex-end;">
                        <label for="sort_by_results_page">Sort By:</label>
                        <select name="sort_by" id="sort_by_results_page">
                            <option value="relevance" <?php if ($sort_by_sr === 'relevance') echo 'selected'; ?>>Relevance</option>
                            <option value="recipe_name" <?php if ($sort_by_sr === 'recipe_name') echo 'selected'; ?>>Alphabetical</option>
                            <option value="date_added" <?php if ($sort_by_sr === 'date_added') echo 'selected'; ?>>Date Added</option>
                            <option value="calories" <?php if ($sort_by_sr === 'calories') echo 'selected'; ?>>Calories</option>
                            <option value="review_count" <?php if ($sort_by_sr === 'review_count') echo 'selected'; ?>>Review Count</option>
                            <option value="average_review" <?php if ($sort_by_sr === 'average_review') echo 'selected'; ?>>Average Review</option>
                            <option value="total_time" <?php if ($sort_by_sr === 'total_time') echo 'selected'; ?>>Total Cook Time</option>
                        </select>
                        <input type="hidden" name="sort_dir" id="sort_dir_results_page" value="<?php echo htmlspecialchars($sort_dir_sr); ?>">
                        <button type="button" id="sort_dir_toggle_results_page" class="sort-direction-button"><?php echo htmlspecialchars($sort_dir_sr); ?></button>
                    </div>
                    <input type="hidden" name="page" id="current_page_hidden_input" value="<?php echo $page; ?>">
                </form>
            </section>
        </div>

        <div class="search-summary">
           <?php if ($hasSearchCriteria): ?>
                <p>
                    <?php
                    if (!empty($searchTerm)) {
                        echo "Showing results for: <strong>\"" . htmlspecialchars($searchTerm) . "\"</strong>";
                        $_displaySearchBy_srp = $searchBy; 
                        if ($_displaySearchBy_srp === 'recipe_name') $_displaySearchBy_srp = 'Recipe Name';
                        if ($_displaySearchBy_srp === 'keywords') $_displaySearchBy_srp = 'Keywords (Name, Desc, Ingred.)';
                        if ($_displaySearchBy_srp === 'author') $_displaySearchBy_srp = 'Author';
                        echo " (in " . htmlspecialchars(ucwords(str_replace('_', ' ', $_displaySearchBy_srp))) . ")";
                    } elseif (count(array_filter(array_keys($_GET), function($k){ return !in_array($k, ['q', 'search_by', 'page', 'sort_by', 'sort_dir']); })) > 0 ) {
                        echo "Displaying results based on advanced filters.";
                    }
                    if (!empty($_GET['match_owned_ingredients']) && isset($_SESSION['username'])) {
                        echo " <span style='color:green;'>(Showing recipes you can make";
                        if (count($allMatchingRecipes) >= PHP_FILTER_LIMIT && count($phpFilteredRecipes) < PHP_FILTER_LIMIT) {
                             echo " from a sample of " . PHP_FILTER_LIMIT;
                        }
                        echo ")</span>";
                    }
                    if ($sort_by_sr !== 'relevance') {
                        echo " Sorted by " . ucwords(str_replace('_', ' ', $sort_by_sr)) . " " . $sort_dir_sr . ".";
                    }
                    ?>
                </p>
                <?php
                    $activeFiltersList_srp = [];
                    if (!empty($_GET['match_owned_ingredients']) && isset($_SESSION['username'])) {
                        $activeFiltersList_srp[] = "Matching Owned Ingredients";
                    }
                    // You can add more active filter displays here if needed
                    if (!empty($activeFiltersList_srp)) {
                        echo "<p><i>Active Filters: " . implode('; ', $activeFiltersList_srp) . "</i></p>";
                    }
                ?>
                <p>Found <?php echo $totalResults; ?> recipe(s).
                   <?php if ($totalPages > 1) echo " Displaying page $page of $totalPages." ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if (isset($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php elseif ($hasSearchCriteria && !empty($searchResults)): ?>
            <ul class="recipe-list recipe-list-with-images">
                <?php foreach ($searchResults as $recipe): ?>
                     <li>
                        <?php $imageUrl = extractFirstImageUrl($recipe['Image_URL']); ?>
                        <?php if ($imageUrl): ?>
                            <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                                     alt="<?php echo htmlspecialchars(html_entity_decode($recipe['Recipe_Name'])); ?>"
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
                                <?php
                                $isFavoritedSrpItem = (is_array($userFavorites) && in_array($recipe['RecipeId'], $userFavorites));
                                $iconTypeSrpItem = $isFavoritedSrpItem ? 'fas' : 'far';
                                ?>
                                <i class="<?php echo $iconTypeSrpItem; ?> fa-star favorite-star <?php if ($isFavoritedSrpItem) echo 'favorited'; ?>"
                                   data-recipe-id="<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></i>
                                <span class="favorite-message" id="fav-msg-srp-<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $queryParamsPagination = $_GET; 
                    ?>
                    <?php if ($page > 1): 
                        $queryParamsPagination['page'] = $page - 1;
                    ?>
                        <a href="?<?php echo http_build_query($queryParamsPagination); ?>">&laquo; Previous</a>
                    <?php else: ?>
                        <span class="disabled">&laquo; Previous</span>
                    <?php endif; ?>
                    <?php
                     $range = 2; 
                    for ($i = 1; $i <= $totalPages; $i++):
                        if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)):
                            $queryParamsPagination['page'] = $i;
                    ?>
                            <?php if ($i == $page): ?>
                                <span class="current-page"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query($queryParamsPagination); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php
                        elseif (($i == $page - $range - 1 && $page - $range -1 > 1) || ($i == $page + $range + 1 && $page + $range +1 < $totalPages) ):
                        ?>
                            <span>...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): 
                        $queryParamsPagination['page'] = $page + 1;
                    ?>
                        <a href="?<?php echo http_build_query($queryParamsPagination); ?>">Next &raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Next &raquo;</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php elseif (!$hasSearchCriteria && empty($searchResults)) : ?>
             <p>Please enter a search term or use the advanced filters to find recipes.</p>
        <?php elseif ($hasSearchCriteria && empty($searchResults)) : ?>
            <p id="no-results-message">No recipes found matching your criteria. Try broadening your search.</p>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> Recipe Website</p>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const advancedSearchBtnResults = document.getElementById('advancedSearchBtnResults');
            const advancedSearchOptionsResults = document.getElementById('advancedSearchOptionsResults');
            const resetSearchBtnResults = document.getElementById('resetSearchBtnResults');
            const recipeSearchFormResults = document.getElementById('recipeSearchFormResults');
            // const pageInputResults = recipeSearchFormResults.querySelector('input[name="page"]'); // Already defined earlier
            const currentPageHiddenInput = document.getElementById('current_page_hidden_input');


            if(advancedSearchBtnResults && advancedSearchOptionsResults) {
                advancedSearchBtnResults.addEventListener('click', function() {
                    advancedSearchOptionsResults.style.display = (advancedSearchOptionsResults.style.display === 'none' || advancedSearchOptionsResults.style.display === '') ? 'block' : 'none';
                    this.textContent = (advancedSearchOptionsResults.style.display === 'block') ? 'Hide Advanced' : 'Advanced Search';
                });
                const urlParams = new URLSearchParams(window.location.search);
                let advancedActiveSR = false;
                const nonDefaultFilters = [ /* ... your list of filters ... */ 'match_owned_ingredients'];
                nonDefaultFilters.forEach(key => { if (urlParams.has(key) && urlParams.get(key) !== '') advancedActiveSR = true; });
                if (urlParams.has('search_by') && urlParams.get('search_by') !== 'recipe_name') advancedActiveSR = true;

                if (advancedActiveSR) {
                    advancedSearchOptionsResults.style.display = 'block';
                    if(advancedSearchBtnResults) advancedSearchBtnResults.textContent = 'Hide Advanced';
                }
            }

            if (recipeSearchFormResults) {
                recipeSearchFormResults.addEventListener('submit', function() {
                    // When submitting the main form, always reset to page 1 for the new result set
                    if (currentPageHiddenInput) currentPageHiddenInput.value = '1';
                });
            }

            if (resetSearchBtnResults) {
                resetSearchBtnResults.addEventListener('click', function() {
                    if (recipeSearchFormResults) {
                        recipeSearchFormResults.reset(); 
                        recipeSearchFormResults.querySelectorAll('input[type="text"], input[type="number"], input[type="checkbox"]').forEach(input => {
                           if(input.type === 'checkbox') input.checked = false;
                           else input.value = '';
                        });
                        recipeSearchFormResults.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
                        
                        const sortBySelect = document.getElementById('sort_by_results_page');
                        const sortDirInput = document.getElementById('sort_dir_results_page');
                        const sortDirButton = document.getElementById('sort_dir_toggle_results_page');
                        if(sortBySelect) sortBySelect.value = 'relevance';
                        if(sortDirInput) sortDirInput.value = 'ASC';
                        if(sortDirButton) sortDirButton.textContent = 'ASC';
                        if(currentPageHiddenInput) currentPageHiddenInput.value = '1'; 
                    }
                    window.location.href = 'search_results.php'; 
                });
            }
            
            const sortBySelectResults = document.getElementById('sort_by_results_page');
            const sortDirInputResults = document.getElementById('sort_dir_results_page');
            const sortDirToggleButtonResults = document.getElementById('sort_dir_toggle_results_page');

            const sortOptionsDefaultsResults = { /* ... your sort defaults ... */ };

            function submitSortOrFilterChange() {
                if (currentPageHiddenInput) currentPageHiddenInput.value = '1'; // Reset page for sort/filter changes
                recipeSearchFormResults.submit();
            }

            if (sortBySelectResults && recipeSearchFormResults) { 
                sortBySelectResults.addEventListener('change', function() {
                    const selectedSortBy = this.value;
                    const defaultDir = sortOptionsDefaultsResults[selectedSortBy] || 'ASC';
                    if (sortDirInputResults) sortDirInputResults.value = defaultDir;
                    if (sortDirToggleButtonResults) sortDirToggleButtonResults.textContent = defaultDir;
                    submitSortOrFilterChange();
                });
            }

            if (sortDirToggleButtonResults && recipeSearchFormResults) { 
                sortDirToggleButtonResults.addEventListener('click', function() {
                    const currentDir = sortDirInputResults.value;
                    const newDir = currentDir === 'ASC' ? 'DESC' : 'ASC';
                    sortDirInputResults.value = newDir;
                    this.textContent = newDir;
                    submitSortOrFilterChange();
                });
            }

            // Favorite star functionality (ensure this is present and correct)
            document.querySelectorAll('.favorite-star').forEach(star => {
                star.addEventListener('click', function() {
                    const recipeId = this.dataset.recipeId;
                    const messageEl = document.getElementById(`fav-msg-srp-${recipeId}`);
                    <?php if (isset($_SESSION['username'])): ?>
                        fetch('update_favorites.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ recipeId: recipeId, action: this.classList.contains('favorited') ? 'remove' : 'add' }) })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                this.classList.toggle('favorited'); 
                                this.classList.toggle('fas'); 
                                this.classList.toggle('far');
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
