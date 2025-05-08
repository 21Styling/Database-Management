<?php
session_start();
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo

// --- Search Term & Type ---
$searchTerm = $_GET['q'] ?? '';
$searchBy = $_GET['search_by'] ?? 'recipe_name';

// --- Pagination ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$resultsPerPage = 20;
$offset = ($page - 1) * $resultsPerPage;

// --- Base SQL with JOIN ---
$joinCondition = "R.RecipeId = M.MealId"; // Verify this join condition

$sqlBase = "SELECT R.RecipeId, R.Recipe_Name, R.Author, R.Description, R.Ingredients,
            R.Average_Rating, R.Image_URL, R.Servings AS RecipeTableServings,
            M.Calories, M.Fat, M.Saturated_Fat, M.Cholesterol, M.Sodium,
            M.Carbohydrate, M.Fiber, M.Sugar, M.Protein,
            M.Prep_Time, M.Cook_Time, M.Total_Time, M.Servings AS MealTableServings
            FROM Recipes R
            LEFT JOIN Meal M ON " . $joinCondition; // Use LEFT JOIN

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
            $sqlParams[':searchTermAuthor'] = $searchTerm; // Consider validation
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

// --- Process Time Filters (Hours/Minutes) ---
foreach ($time_form_keys_srp as $form_key_filter_srp => $label_srp) {
    $db_column = $time_db_columns_map_srp[$form_key_filter_srp];
    $min_hr_input = $_GET['min_' . $form_key_filter_srp . '_hr'] ?? ''; $min_min_input = $_GET['min_' . $form_key_filter_srp . '_min'] ?? '';
    $apply_min_time_filter = false; $min_total_minutes_value = 0;
    if (($min_hr_input !== '' && is_numeric($min_hr_input)) || ($min_min_input !== '' && is_numeric($min_min_input))) {
        $apply_min_time_filter = true;
        $hours = (is_numeric($min_hr_input) && $min_hr_input >= 0) ? (int)$min_hr_input : 0;
        $minutes = (is_numeric($min_min_input) && $min_min_input >= 0 && $min_min_input < 60) ? (int)$min_min_input : 0;
        $min_total_minutes_value = ($hours * 60) + $minutes;
    }
    if ($apply_min_time_filter) {
        $sqlWhere[] = "(TIME_TO_SEC($db_column) / 60) >= :min_total_minutes_$form_key_filter_srp";
        $sqlParams[":min_total_minutes_$form_key_filter_srp"] = $min_total_minutes_value;
    }

    $max_hr_input = $_GET['max_' . $form_key_filter_srp . '_hr'] ?? ''; $max_min_input = $_GET['max_' . $form_key_filter_srp . '_min'] ?? '';
    $apply_max_time_filter = false; $max_total_minutes_value = 0;
    if (($max_hr_input !== '' && is_numeric($max_hr_input)) || ($max_min_input !== '' && is_numeric($max_min_input))) {
        $apply_max_time_filter = true;
        $hours = (is_numeric($max_hr_input) && $max_hr_input >= 0) ? (int)$max_hr_input : 0;
        $minutes = (is_numeric($max_min_input) && $max_min_input >= 0 && $max_min_input < 60) ? (int)$max_min_input : 0;
        $max_total_minutes_value = ($hours * 60) + $minutes;
    }
    if ($apply_max_time_filter) {
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

// --- Count Total Results & Fetch Paged Data ---
$totalResults = 0;
$totalPages = 0;
$searchResults = [];
$error_message = null;
$debug_sql_tried = "";

$hasSearchCriteria = !empty($searchTerm);
if (!$hasSearchCriteria) {
    foreach ($_GET as $key => $value) {
        if ($value !== '' && !in_array($key, ['q', 'search_by', 'page'])) {
            $hasSearchCriteria = true;
            break;
        }
    }
    if (!$hasSearchCriteria && $searchBy !== 'recipe_name') {
        $hasSearchCriteria = true;
    }
}

if ($hasSearchCriteria) {
    try {
        $countSql = "SELECT COUNT(DISTINCT R.RecipeId) as total FROM Recipes R LEFT JOIN Meal M ON " . $joinCondition;
        if (!empty($sqlWhere)) {
            $countSql .= " WHERE " . implode(" AND ", $sqlWhere);
        }
        $debug_sql_tried = $countSql;

        $stmtCount = $pdo->prepare($countSql);
        if (!empty($sqlParams)) {
            foreach ($sqlParams as $key => $value) {
                 $paramType = PDO::PARAM_STR;
                if ($key === ':searchTermAuthor' && is_numeric($value)) {
                    $paramType = PDO::PARAM_INT;
                } elseif (is_int($value)) {
                    $paramType = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $paramType = PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $paramType = PDO::PARAM_NULL;
                }
                $stmtCount->bindValue($key, $value, $paramType);
            }
        }
        $stmtCount->execute();
        $totalResults = (int)$stmtCount->fetchColumn();

        if ($totalResults > 0) {
            $totalPages = ceil($totalResults / $resultsPerPage);
             if ($page > $totalPages && $totalPages > 0) { $page = $totalPages; $offset = ($page - 1) * $resultsPerPage; }
             elseif ($page < 1) { $page = 1; $offset = 0;}

            $dataQuerySql = $finalSql . " GROUP BY R.RecipeId ORDER BY R.Recipe_Name ASC LIMIT :limit OFFSET :offset";
            $debug_sql_tried = $dataQuerySql;

            $stmtPaged = $pdo->prepare($dataQuerySql);
            if (!empty($sqlParams)) {
                foreach ($sqlParams as $key => $value) {
                    $paramType = PDO::PARAM_STR;
                    if ($key === ':searchTermAuthor' && is_numeric($value)) {
                        $paramType = PDO::PARAM_INT;
                    } elseif (is_int($value)) {
                        $paramType = PDO::PARAM_INT;
                    } elseif (is_bool($value)) {
                        $paramType = PDO::PARAM_BOOL;
                    } elseif (is_null($value)) {
                        $paramType = PDO::PARAM_NULL;
                    }
                    $stmtPaged->bindValue($key, $value, $paramType);
                }
            }
            $stmtPaged->bindValue(':limit', $resultsPerPage, PDO::PARAM_INT);
            $stmtPaged->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmtPaged->execute();
            $searchResults = $stmtPaged->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $searchResults = [];
        }
    } catch (PDOException $e) {
        error_log("Advanced Search PDOException: " . $e->getMessage() . " --- SQL tried: " . $debug_sql_tried . " --- Params: " . print_r($sqlParams, true));
        $error_message = "An error occurred while performing the search. Please check your input or the PHP error log for more details.";
        $searchResults = [];
    }
}

// --- Fetch user's favorite recipes if logged in ---
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
        .recipe-search-form-on-results input[type="text"]{ padding: 5px 10px; font-size: 0.9em; margin-right: 5px; vertical-align: middle; box-sizing: border-box; height: 31px; }
        .recipe-search-form-on-results > button { padding: 5px 10px; font-size: 0.9em; margin-left: 5px; vertical-align: middle; cursor: pointer; box-sizing: border-box; height: 31px; }
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
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p><a href="index.php">&laquo; Back to Home / New Search</a></p>
    </header>

    <main class="container">
        <div class="search-form-container">
            <section class="home-section">
                <h2>Refine Search or Start New</h2>
                <form action="search_results.php" method="get" class="recipe-search-form-on-results" id="recipeSearchFormResults">
                    <input type="text" name="q" placeholder="Enter search term..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                    <button type="button" id="advancedSearchBtnResults">Advanced Search</button>
                    <button type="submit">Search</button>
                    <button type="button" id="resetSearchBtnResults">Reset</button>

                    <div id="advancedSearchOptionsResults" style="display:none; border: 1px solid #ccc; padding: 15px; margin-top: 10px;">
                        {/* Form content remains the same - it's pre-filled by PHP */}
                        <h4>Advanced Options</h4>
                        <label for="search_by_results">Search By:</label>
                        <select name="search_by" id="search_by_results">
                            <option value="recipe_name" <?php if (($_GET['search_by'] ?? 'recipe_name') === 'recipe_name') echo 'selected'; ?>>Recipe Name</option>
                            <option value="keywords" <?php if (($_GET['search_by'] ?? '') === 'keywords') echo 'selected'; ?>>Keywords (Name, Desc, Ingred.)</option>
                            <option value="author" <?php if (($_GET['search_by'] ?? '') === 'author') echo 'selected'; ?>>Author</option>
                        </select>
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
                        <br><br>
                        <h5>Time:</h5>
                        <?php
                        foreach ($time_form_keys_srp as $field_key_srp => $field_label_srp) { // Use the renamed variable
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
                        echo " (in " . htmlspecialchars(ucfirst(str_replace('_', ' ', $_displaySearchBy_srp))) . ")";
                    } elseif (count(array_filter(array_keys($_GET), function($k){ return !in_array($k, ['q', 'search_by', 'page']); })) > 0 ) {
                        echo "Displaying results based on advanced filters.";
                    }
                    ?>
                </p>
                <?php
                    $activeFiltersList_srp = [];
                    foreach ($nutrition_form_fields_display_srp as $form_key => $displayName) {
                        if (isset($_GET['min_' . $form_key]) && $_GET['min_' . $form_key] !== '') $activeFiltersList_srp[] = $displayName . " Min: " . htmlspecialchars($_GET['min_' . $form_key]);
                        if (isset($_GET['max_' . $form_key]) && $_GET['max_' . $form_key] !== '') $activeFiltersList_srp[] = $displayName . " Max: " . htmlspecialchars($_GET['max_' . $form_key]);
                    }
                    if (isset($_GET['min_RecipeServings']) && $_GET['min_RecipeServings'] !== '') $activeFiltersList_srp[] = "Recipe Servings Min: " . htmlspecialchars($_GET['min_RecipeServings']);
                    if (isset($_GET['max_RecipeServings']) && $_GET['max_RecipeServings'] !== '') $activeFiltersList_srp[] = "Recipe Servings Max: " . htmlspecialchars($_GET['max_RecipeServings']);

                    foreach ($time_form_keys_srp as $form_key_srp => $displayName_srp) {
                        $min_hr_input_val_srp = $_GET['min_' . $form_key_srp . '_hr'] ?? ''; $min_min_input_val_srp = $_GET['min_' . $form_key_srp . '_min'] ?? '';
                        $max_hr_input_val_srp = $_GET['max_' . $form_key_srp . '_hr'] ?? ''; $max_min_input_val_srp = $_GET['max_' . $form_key_srp . '_min'] ?? '';
                        if ($min_hr_input_val_srp !== '' || $min_min_input_val_srp !== '') {
                            $hr_val_srp = (is_numeric($min_hr_input_val_srp) && $min_hr_input_val_srp >=0) ? (int)$min_hr_input_val_srp : 0;
                            $min_val_srp = (is_numeric($min_min_input_val_srp) && $min_min_input_val_srp >=0 && $min_min_input_val_srp < 60) ? (int)$min_min_input_val_srp : 0;
                            if ($hr_val_srp > 0 || $min_val_srp > 0 || $min_hr_input_val_srp !== '' || $min_min_input_val_srp !== '') $activeFiltersList_srp[] = $displayName_srp . " Min: " . $hr_val_srp . "hr " . $min_val_srp . "min";
                        }
                        if ($max_hr_input_val_srp !== '' || $max_min_input_val_srp !== '') {
                            $hr_val_srp = (is_numeric($max_hr_input_val_srp) && $max_hr_input_val_srp >=0) ? (int)$max_hr_input_val_srp : 0;
                            $min_val_srp = (is_numeric($max_min_input_val_srp) && $max_min_input_val_srp >=0 && $max_min_input_val_srp < 60) ? (int)$max_min_input_val_srp : 0;
                            if ($hr_val_srp > 0 || $min_val_srp > 0 || $max_hr_input_val_srp !== '' || $max_min_input_val_srp !== '') $activeFiltersList_srp[] = $displayName_srp . " Max: " . $hr_val_srp . "hr " . $min_val_srp . "min";
                        }
                    }
                    if (!empty($activeFiltersList_srp)) {
                        echo "<p>Active Filters: " . implode('; ', $activeFiltersList_srp) . "</p>";
                    }
                ?>
                <p>Found <?php echo $totalResults; ?> recipe(s).
                   <?php if ($totalPages > 1) echo "Displaying page $page of $totalPages." ?>
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
                                <span class="favorite-message" id="fav-msg-srp-<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $queryParams = $_GET; unset($queryParams['page']); $queryString = http_build_query($queryParams);
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo $queryString; ?>">&laquo; Previous</a>
                    <?php else: ?>
                        <span class="disabled">&laquo; Previous</span>
                    <?php endif; ?>
                    <?php
                    $range = 2;
                    for ($i = 1; $i <= $totalPages; $i++):
                        if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)):
                    ?>
                            <?php if ($i == $page): ?>
                                <span class="current-page"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&<?php echo $queryString; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php
                        elseif (($i == $page - $range - 1 && $page - $range -1 > 1) || ($i == $page + $range + 1 && $page + $range +1 < $totalPages) ):
                        ?>
                            <span>...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo $queryString; ?>">Next &raquo;</a>
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
            const advancedSearchBtn = document.getElementById('advancedSearchBtnResults');
            const advancedSearchOptions = document.getElementById('advancedSearchOptionsResults');
            const resetSearchBtn = document.getElementById('resetSearchBtnResults');
            const recipeSearchForm = document.getElementById('recipeSearchFormResults');

            if(advancedSearchBtn && advancedSearchOptions) {
                // Click listener for the toggle button remains the same
                advancedSearchBtn.addEventListener('click', function() {
                    if (advancedSearchOptions.style.display === 'none' || advancedSearchOptions.style.display === '') {
                        advancedSearchOptions.style.display = 'block';
                        this.textContent = 'Hide Advanced';
                    } else {
                        advancedSearchOptions.style.display = 'none';
                        this.textContent = 'Advanced Search';
                    }
                });

                // REMOVED: The logic that automatically showed the dropdown based on URL params.
                // The dropdown (`advancedSearchOptionsResults`) starts hidden due to inline style="display:none;"
                // and will now only be shown if the user explicitly clicks the "Advanced Search" button.
                // We also ensure the button text defaults correctly.
                advancedSearchBtn.textContent = 'Advanced Search';

            } // End of if(advancedSearchBtn && advancedSearchOptions)

            if (recipeSearchForm) { // Logic for hiding advanced options ON SUBMIT (remains the same)
                recipeSearchForm.addEventListener('submit', function() {
                    // Hide the options and reset button text when the form is submitted
                    if (advancedSearchOptions) {
                        advancedSearchOptions.style.display = 'none';
                    }
                    if (advancedSearchBtn) {
                        advancedSearchBtn.textContent = 'Advanced Search';
                    }
                    // Allow the form submission to proceed naturally
                });
            }

            if (resetSearchBtn) { // Logic for reset button (remains the same)
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

            // Favorite star functionality (remains the same)
            document.querySelectorAll('.favorite-star').forEach(star => {
                star.addEventListener('click', function() {
                    const recipeId = this.dataset.recipeId;
                    const messageEl = document.getElementById(`fav-msg-srp-${recipeId}`);
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