<?php
session_start();
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo

// --- Settings ---
$recipes_per_page = 20;

// --- Search, Filter, and Sort Parameters ---
$searchTerm = $_GET['q'] ?? '';
$searchBy = $_GET['search_by'] ?? 'recipe_name'; // Default search type

// --- Pagination ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $recipes_per_page;

// --- Sort Parameters ---
$sort_by_options_all = [
    'recipe_name'    => ['column' => 'R.Recipe_Name',    'default_dir' => 'ASC',  'requires_join' => false],
    'date_added'     => ['column' => 'R.Date',           'default_dir' => 'DESC', 'requires_join' => false],
    'calories'       => ['column' => 'M.Calories',       'default_dir' => 'ASC',  'requires_join' => true],
    'review_count'   => ['column' => 'R.Rating_Count',   'default_dir' => 'DESC', 'requires_join' => false],
    'average_review' => ['column' => 'R.Average_Rating', 'default_dir' => 'DESC', 'requires_join' => false],
    'total_time'     => ['column' => 'M.Total_Time',     'default_dir' => 'ASC',  'requires_join' => true]
];
$sort_by_get = isset($_GET['sort_by']) && isset($sort_by_options_all[$_GET['sort_by']]) ? $_GET['sort_by'] : 'recipe_name'; // Default sort for all_recipes
$sort_column_info_all = $sort_by_options_all[$sort_by_get];
$current_sort_column_all = $sort_column_info_all['column'];
$current_sort_dir_all = isset($_GET['sort_dir']) && in_array(strtoupper($_GET['sort_dir']), ['ASC', 'DESC']) ? strtoupper($_GET['sort_dir']) : $sort_column_info_all['default_dir'];
$requires_meal_join_for_sort = $sort_column_info_all['requires_join'];


// --- Base SQL with JOIN (conditionally) ---
// Select all necessary fields for display, filtering, and sorting
$sqlBase = "SELECT R.RecipeId, R.Recipe_Name, R.AuthorId, R.Description, R.Ingredients,
            R.Average_Rating, R.Image_URL, R.Servings AS RecipeTableServings, R.Rating_Count, R.Date AS SubmissionDate,
            M.Calories, M.Fat, M.Saturated_Fat, M.Cholesterol, M.Sodium,
            M.Carbohydrate, M.Fiber, M.Sugar, M.Protein,
            M.Prep_Time, M.Cook_Time, M.Total_Time, M.Servings AS MealTableServings
            FROM Recipes R
            LEFT JOIN Meal M ON R.RecipeId = M.MealId"; // Always LEFT JOIN to allow filtering/sorting on Meal fields

$sqlWhere = [];
$sqlParams = [];

// --- Build WHERE clause for search term (if any) ---
if (!empty($searchTerm)) {
    switch ($searchBy) {
        case 'keywords':
            $sqlWhere[] = "(R.Recipe_Name LIKE :searchTerm OR R.Description LIKE :searchTerm OR R.Ingredients LIKE :searchTerm)";
            $sqlParams[':searchTerm'] = '%' . $searchTerm . '%';
            break;
        case 'author':
            $sqlWhere[] = "R.AuthorId = :searchTermAuthor";
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
$nutrition_db_map_all = [
    'Calories' => 'M.Calories', 'Fat' => 'M.Fat', 'Saturated_Fat' => 'M.Saturated_Fat',
    'Cholesterol' => 'M.Cholesterol', 'Sodium' => 'M.Sodium', 'Carbohydrate' => 'M.Carbohydrate',
    'Fiber' => 'M.Fiber', 'Sugar' => 'M.Sugar', 'Protein' => 'M.Protein'
];
$servings_db_map_all = ['RecipeServings' => 'R.Servings'];
$time_form_keys_all_page = ['PrepTime' => 'Prep Time', 'CookTime' => 'Cook Time', 'TotalTime' => 'Total Time'];
$time_db_columns_map_all_page = ['PrepTime' => 'M.Prep_Time', 'CookTime' => 'M.Cook_Time', 'TotalTime' => 'M.Total_Time'];

// --- Process Nutrition and Servings Filters ---
foreach ($nutrition_db_map_all as $form_key => $db_column) {
    if (isset($_GET['min_' . $form_key]) && is_numeric($_GET['min_' . $form_key]) && $_GET['min_' . $form_key] !== '') {
        $sqlWhere[] = "$db_column >= :min_$form_key";
        $sqlParams[":min_$form_key"] = $_GET['min_' . $form_key];
    }
    if (isset($_GET['max_' . $form_key]) && is_numeric($_GET['max_' . $form_key]) && $_GET['max_' . $form_key] !== '') {
        $sqlWhere[] = "$db_column <= :max_$form_key";
        $sqlParams[":max_$form_key"] = $_GET['max_' . $form_key];
    }
}
foreach ($servings_db_map_all as $form_key => $db_column) {
    if (isset($_GET['min_' . $form_key]) && is_numeric($_GET['min_' . $form_key]) && $_GET['min_' . $form_key] !== '') {
        $sqlWhere[] = "$db_column >= :min_$form_key";
        $sqlParams[":min_$form_key"] = $_GET['min_' . $form_key];
    }
    if (isset($_GET['max_' . $form_key]) && is_numeric($_GET['max_' . $form_key]) && $_GET['max_' . $form_key] !== '') {
        $sqlWhere[] = "$db_column <= :max_$form_key";
        $sqlParams[":max_$form_key"] = $_GET['max_' . $form_key];
    }
}

// --- Process Time Filters ---
foreach ($time_form_keys_all_page as $form_key_filter_all => $label_all) {
    $db_column = $time_db_columns_map_all_page[$form_key_filter_all];
    // Min time
    $min_hr_input = $_GET['min_' . $form_key_filter_all . '_hr'] ?? ''; $min_min_input = $_GET['min_' . $form_key_filter_all . '_min'] ?? '';
    if (($min_hr_input !== '' && is_numeric($min_hr_input)) || ($min_min_input !== '' && is_numeric($min_min_input))) {
        $hours = (is_numeric($min_hr_input) && $min_hr_input >= 0) ? (int)$min_hr_input : 0;
        $minutes = (is_numeric($min_min_input) && $min_min_input >= 0 && $min_min_input < 60) ? (int)$min_min_input : 0;
        $min_total_minutes_value = ($hours * 60) + $minutes;
        $sqlWhere[] = "(TIME_TO_SEC($db_column) / 60) >= :min_total_minutes_$form_key_filter_all";
        $sqlParams[":min_total_minutes_$form_key_filter_all"] = $min_total_minutes_value;
    }
    // Max time
    $max_hr_input = $_GET['max_' . $form_key_filter_all . '_hr'] ?? ''; $max_min_input = $_GET['max_' . $form_key_filter_all . '_min'] ?? '';
    if (($max_hr_input !== '' && is_numeric($max_hr_input)) || ($max_min_input !== '' && is_numeric($max_min_input))) {
        $hours = (is_numeric($max_hr_input) && $max_hr_input >= 0) ? (int)$max_hr_input : 0;
        $minutes = (is_numeric($max_min_input) && $max_min_input >= 0 && $max_min_input < 60) ? (int)$max_min_input : 0;
        $max_total_minutes_value = ($hours * 60) + $minutes;
        if ($max_total_minutes_value > 0 || ($max_hr_input !== '' || $max_min_input !== '')) {
            $sqlWhere[] = "(TIME_TO_SEC($db_column) / 60) <= :max_total_minutes_$form_key_filter_all";
            $sqlParams[":max_total_minutes_$form_key_filter_all"] = $max_total_minutes_value;
        }
    }
}

// Base condition for recipes on this page (always apply)
$sqlWhere[] = "R.Recipe_Name IS NOT NULL AND R.Recipe_Name != ''";

$finalSql = $sqlBase;
if (!empty($sqlWhere)) {
    $finalSql .= " WHERE " . implode(" AND ", $sqlWhere);
}

// Determine if any search criteria or filters were applied for display purposes
$hasActiveFilters = !empty($searchTerm);
if (!$hasActiveFilters) {
    foreach ($_GET as $key => $value) {
        if ($value !== '' && !in_array($key, ['q', 'search_by', 'page', 'sort_by', 'sort_dir'])) {
            $hasActiveFilters = true;
            break;
        }
    }
    if (!$hasActiveFilters && ($searchBy !== 'recipe_name' && $searchBy !== '')) { $hasActiveFilters = true;}
}


// --- Count Total Results & Fetch Paged Data ---
$totalResults = 0;
$totalPages = 0;
$recipes = []; // Renamed from $searchResults to $recipes for clarity on all_recipes page
$error_message = null;
$debug_sql_tried = "";

try {
    $countSql = "SELECT COUNT(DISTINCT R.RecipeId) as total FROM Recipes R LEFT JOIN Meal M ON R.RecipeId = M.MealId";
    if (!empty($sqlWhere)) {
        $countSql .= " WHERE " . implode(" AND ", $sqlWhere);
    }
    $stmtCount = $pdo->prepare($countSql);
    if (!empty($sqlParams)) {
         foreach ($sqlParams as $key => $value) {
            $paramType = PDO::PARAM_STR;
            if (strpos($key, 'Author') !== false && is_numeric($value)) $paramType = PDO::PARAM_INT;
            elseif (is_int($value)) $paramType = PDO::PARAM_INT;
            $stmtCount->bindValue($key, $value, $paramType);
        }
    }
    $stmtCount->execute();
    $totalResults = (int)$stmtCount->fetchColumn();

    if ($totalResults > 0) {
        $totalPages = ceil($totalResults / $recipes_per_page);
        if ($page > $totalPages && $totalPages > 0) { $page = $totalPages; $offset = ($page - 1) * $recipes_per_page; }
        elseif ($page < 1) { $page = 1; $offset = 0;}

        $orderByClause = " ORDER BY $current_sort_column_all $current_sort_dir_all";
        if ($sort_by_get !== 'recipe_name' && $current_sort_column_all !== 'R.Recipe_Name') $orderByClause .= ", R.Recipe_Name ASC";
        if ($sort_by_get !== 'date_added' && $current_sort_column_all !== 'R.Date') $orderByClause .= ", R.Date DESC";


        $dataQuerySql = $finalSql . " GROUP BY R.RecipeId " . $orderByClause . " LIMIT :limit OFFSET :offset";
        $debug_sql_tried = $dataQuerySql;

        $stmtPaged = $pdo->prepare($dataQuerySql);
        if (!empty($sqlParams)) {
            foreach ($sqlParams as $key => $value) {
                $paramType = PDO::PARAM_STR;
                if (strpos($key, 'Author') !== false && is_numeric($value)) $paramType = PDO::PARAM_INT;
                elseif (is_int($value)) $paramType = PDO::PARAM_INT;
                $stmtPaged->bindValue($key, $value, $paramType);
            }
        }
        $stmtPaged->bindValue(':limit', $recipes_per_page, PDO::PARAM_INT);
        $stmtPaged->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmtPaged->execute();
        $recipes = $stmtPaged->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $recipes = [];
    }
} catch (PDOException $e) {
    error_log("All Recipes Page PDOException: " . $e->getMessage() . " --- SQL tried: " . $debug_sql_tried . " --- Params: " . print_r($sqlParams, true));
    $error_message = "An error occurred. Details: " . $e->getMessage(); // Provide more detailed error for admin/dev
    $recipes = [];
}

// --- User Favorites ---
$userFavorites = [];
// ... (user favorites fetching logic remains the same as before) ...
if (isset($_SESSION['username'])) {
    try {
        $stmtUser = $pdo->prepare("SELECT Favorites FROM User WHERE Username = ?");
        $stmtUser->execute([$_SESSION['username']]);
        $resultUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        $userFavorites = $resultUser ? json_decode($resultUser['Favorites'], true) : [];
        if ($userFavorites === null) { $userFavorites = []; }
    } catch (PDOException $e) {
        error_log("Database error fetching favorites for all_recipes.php: " . $e->getMessage());
        $userFavorites = [];
    }
}

// --- Helper Function ---
function extractFirstImageUrl($imageUrlString) {
    // ... (extractFirstImageUrl function remains the same) ...
    if (empty($imageUrlString) || $imageUrlString === 'character(0)') { return null; }
    $trimmedUrl = trim($imageUrlString, ' "');
    if (filter_var($trimmedUrl, FILTER_VALIDATE_URL)) { return $trimmedUrl; }
    if (preg_match('/^c?\("([^"]+)"/', $imageUrlString, $matches)) {
        $potentialUrl = $matches[1];
        if (filter_var($potentialUrl, FILTER_VALIDATE_URL)) { return $potentialUrl; }
    }
    return null;
}

// --- Page Title ---
$pageTitle = "All Recipes";
if ($hasActiveFilters && !empty($searchTerm)) { $pageTitle = "Search Results for \"" . htmlspecialchars($searchTerm) . "\""; }
elseif ($hasActiveFilters) { $pageTitle = "Filtered Recipes"; }
$pageTitle .= " (Page $page)";
if ($current_sort_column_all) { // Add sort info if a column is being sorted by
    $pageTitle .= " - Sorted by " . ucwords(str_replace('_', ' ', $sort_by_get)) . " " . $current_sort_dir_all;
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
        /* Minor specific styles if needed, most should be in style.css */
        .pagination { margin: 2em 0 1em 0; text-align: center; }
        .pagination a, .pagination span { display: inline-block; padding: 0.5em 1em; margin: 0 0.2em; border: 1px solid #ddd; color: #0056b3; text-decoration: none; border-radius: 3px; }
        .pagination a:hover { background-color: #eee; }
        .pagination .current-page { background-color: #0056b3; color: white; border-color: #0056b3; font-weight: bold; }
        .pagination .disabled { color: #aaa; border-color: #eee; pointer-events: none; }
        .favorite-star { cursor: pointer; color: #ccc; }
        .favorite-star.favorited { color: gold; }
        .favorite-message { font-size: 0.8em; color: #777; margin-top: 0.2em; display: inline-block; margin-left: 5px; }
         .recipe-list-image-placeholder { 
            width: 80px; height: 60px; background-color: #eee; display: flex; align-items: center;
            justify-content: center; color: #aaa; font-style: italic; margin-right: 1em; 
            border-radius: 3px; flex-shrink: 0; text-align: center; font-size:0.8em;
        }
        .search-summary { margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #eee; font-size: 0.9em; color: #555;}
    </style>
</head>
<body>
    <header class="site-header">
        <h1><?php echo htmlspecialchars(explode(" (Page", $pageTitle)[0]); // Cleaner title for header ?></h1>
        <p><a href="index.php">&laquo; Back to Home</a></p>
    </header>

    <main class="container">
        <div class="search-form-container">
            <section class="home-section">
                <h2>Find Recipes</h2>
                <form action="all_recipes.php" method="get" class="recipe-search-form-on-all-page" id="allRecipesUnifiedForm">
                    <div style="display:flex; align-items:center; width:100%; flex-wrap: wrap; margin-bottom:10px;">
                        <input type="text" name="q" placeholder="Enter search term..." value="<?php echo htmlspecialchars($searchTerm); ?>" style="flex-grow:1; margin-right:5px; margin-bottom: 5px;" class="recipe-search-form-on-results input[type='text']">
                        <button type="button" id="advancedSearchBtnAllPage" style="margin-bottom: 5px;">Advanced Filters</button>
                        <button type="submit" style="margin-bottom: 5px;">Search / Apply</button>
                        <button type="button" id="resetSearchBtnAllPage" style="margin-bottom: 5px;">Reset</button>
                    </div>

                    <div id="advancedSearchOptionsAllPage" style="display:none; border: 1px solid #ccc; padding: 15px; margin-top: 10px; margin-bottom:10px;">
                        <h4>Advanced Filters</h4>
                        <label for="search_by_all_page_form">Search By:</label>
                        <select name="search_by" id="search_by_all_page_form">
                            <option value="recipe_name" <?php if ($searchBy === 'recipe_name') echo 'selected'; ?>>Recipe Name</option>
                            <option value="keywords" <?php if ($searchBy === 'keywords') echo 'selected'; ?>>Keywords (Name, Desc, Ingred.)</option>
                            <option value="author" <?php if ($searchBy === 'author') echo 'selected'; ?>>Author ID</option>
                        </select>
                        <br><br>
                        <h5>Nutrition Facts (per serving):</h5>
                        <?php
                        foreach ($nutrition_db_map_all as $field_key => $db_col) {
                            echo '<label for="min_' . $field_key . '_all_page">Min ' . ucwords(str_replace("_"," ",$field_key)) . ':</label>';
                            echo '<input type="number" name="min_' . $field_key . '" id="min_' . $field_key . '_all_page" step="any" value="' . htmlspecialchars($_GET['min_' . $field_key] ?? '') . '" min="0">';
                            echo '<label for="max_' . $field_key . '_all_page">Max ' . ucwords(str_replace("_"," ",$field_key)) . ':</label>';
                            echo '<input type="number" name="max_' . $field_key . '" id="max_' . $field_key . '_all_page" step="any" value="' . htmlspecialchars($_GET['max_' . $field_key] ?? '') . '" min="0">';
                            echo '<br>';
                        }
                        ?>
                        <h5>Recipe Yield:</h5>
                        <label for="min_RecipeServings_all_page">Min Servings:</label>
                        <input type="number" name="min_RecipeServings" id="min_RecipeServings_all_page" value="<?php echo htmlspecialchars($_GET['min_RecipeServings'] ?? ''); ?>" min="0">
                        <label for="max_RecipeServings_all_page">Max Servings:</label>
                        <input type="number" name="max_RecipeServings" id="max_RecipeServings_all_page" value="<?php echo htmlspecialchars($_GET['max_RecipeServings'] ?? ''); ?>" min="0">
                        <h5>Time:</h5>
                        <?php
                        foreach ($time_form_keys_all_page as $field_key_all => $field_label_all) {
                            echo '<div class="time-input-set">';
                            echo '<strong>' . $field_label_all . ':</strong><br>';
                            echo '<label for="min_' . $field_key_all . '_hr_all_page">Min:</label>';
                            echo '<span class="time-input-group">';
                            echo '<input type="number" name="min_' . $field_key_all . '_hr" id="min_' . $field_key_all . '_hr_all_page" placeholder="hr" value="' . htmlspecialchars($_GET['min_' . $field_key_all . '_hr'] ?? '') . '" min="0" max="838">';
                            echo '<span>hrs</span>';
                            echo '<input type="number" name="min_' . $field_key_all . '_min" id="min_' . $field_key_all . '_min_all_page" placeholder="min" value="' . htmlspecialchars($_GET['min_' . $field_key_all . '_min'] ?? '') . '" min="0" max="59">';
                            echo '<span>min</span>';
                            echo '</span>';
                            echo '<label for="max_' . $field_key_all . '_hr_all_page" style="margin-left:20px;">Max:</label>';
                            echo '<span class="time-input-group">';
                            echo '<input type="number" name="max_' . $field_key_all . '_hr" id="max_' . $field_key_all . '_hr_all_page" placeholder="hr" value="' . htmlspecialchars($_GET['max_' . $field_key_all . '_hr'] ?? '') . '" min="0" max="838">';
                            echo '<span>hrs</span>';
                            echo '<input type="number" name="max_' . $field_key_all . '_min" id="max_' . $field_key_all . '_min_all_page" placeholder="min" value="' . htmlspecialchars($_GET['max_' . $field_key_all . '_min'] ?? '') . '" min="0" max="59">';
                            echo '<span>min</span>';
                            echo '</span>';
                            echo '<br>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <div class="sort-controls-container" style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #eee; justify-content: flex-end;">
                        <label for="sort_by_all_page_form">Sort By:</label>
                        <select name="sort_by" id="sort_by_all_page_form">
                            <option value="recipe_name" <?php if ($sort_by_get === 'recipe_name') echo 'selected'; ?>>Alphabetical</option>
                            <option value="date_added" <?php if ($sort_by_get === 'date_added') echo 'selected'; ?>>Date Added</option>
                            <option value="calories" <?php if ($sort_by_get === 'calories') echo 'selected'; ?>>Calories</option>
                            <option value="review_count" <?php if ($sort_by_get === 'review_count') echo 'selected'; ?>>Review Count</option>
                            <option value="average_review" <?php if ($sort_by_get === 'average_review') echo 'selected'; ?>>Average Review</option>
                            <option value="total_time" <?php if ($sort_by_get === 'total_time') echo 'selected'; ?>>Total Cook Time</option>
                        </select>
                        <input type="hidden" name="sort_dir" id="sort_dir_all_page_form" value="<?php echo htmlspecialchars($current_sort_dir_all); ?>">
                        <button type="button" id="sort_dir_toggle_all_page_form" class="sort-direction-button"><?php echo htmlspecialchars($current_sort_dir_all); ?></button>
                    </div>
                    <input type="hidden" name="page" value="<?php echo $page; ?>" id="all_recipes_page_input_form">
                </form>
            </section>
        </div>

        <div class="search-summary">
            <p>Found <?php echo $totalResults; ?> recipe(s).
               <?php if ($totalPages > 1) echo " Displaying page $page of $totalPages." ?>
               <?php if ($current_sort_column_all) echo " Sorted by " . ucwords(str_replace('_', ' ', $sort_by_get)) . " " . $current_sort_dir_all . "."; ?>
            </p>
             <?php
                // Display active filters summary (optional, can be extensive)
                $activeFiltersSummary = [];
                if(!empty($searchTerm)) $activeFiltersSummary[] = "Search Term: \"".htmlspecialchars($searchTerm)."\"";
                // Add more based on $_GET parameters for advanced filters if desired
                if(!empty($activeFiltersSummary)) echo "<p><i>Active Filters: " . implode("; ", $activeFiltersSummary) . "</i></p>";
            ?>
        </div>
        <hr>

        <?php if (isset($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php elseif (!empty($recipes)): ?>
            <ul class="recipe-list recipe-list-with-images">
                <?php foreach ($recipes as $recipe_item): // Changed variable name to avoid conflict ?>
                    <li>
                        <?php $imageUrl = extractFirstImageUrl($recipe_item['Image_URL']); ?>
                        <?php if ($imageUrl): ?>
                            <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe_item['RecipeId']); ?>">
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars(html_entity_decode($recipe_item['Recipe_Name'])); ?>" class="recipe-list-image" loading="lazy" onerror="this.style.display='none'">
                            </a>
                        <?php else: ?>
                            <div class="recipe-list-image-placeholder">No Image</div>
                        <?php endif; ?>
                        <div class="recipe-list-info">
                            <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe_item['RecipeId']); ?>">
                                <?php echo htmlspecialchars(html_entity_decode($recipe_item['Recipe_Name'])); ?>
                            </a>
                            <span class="rating">(Rating: <?php echo htmlspecialchars($recipe_item['Average_Rating'] ?? 'N/A'); ?>)</span>
                            <?php if (isset($_SESSION['username'])): ?>
                                <?php
                                $isFavorited = (is_array($userFavorites) && in_array($recipe_item['RecipeId'], $userFavorites));
                                $iconClass = $isFavorited ? 'fas favorited' : 'far';
                                ?>
                                <i class="<?php echo $iconClass; ?> fa-star favorite-star"
                                   data-recipe-id="<?php echo htmlspecialchars($recipe_item['RecipeId']); ?>"></i>
                                <span class="favorite-message" id="fav-msg-allr-<?php echo htmlspecialchars($recipe_item['RecipeId']); ?>"></span>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
             <?php if ($hasActiveFilters): ?>
                <p>No recipes found matching your current filters. Try adjusting your search or filters.</p>
            <?php else: ?>
                <p>No recipes found.</p>
            <?php endif; ?>
        <?php endif; ?>

        <div class="pagination">
            <?php if ($totalPages > 1): ?>
                <?php
                $page_query_params_all_pg = $_GET; 
                unset($page_query_params_all_pg['page']); 
                $base_query_string_all_pg = http_build_query($page_query_params_all_pg);
                $base_pagination_url_all_pg = "all_recipes.php?" . ($base_query_string_all_pg ? $base_query_string_all_pg . '&' : '');
                ?>
                <?php if ($page > 1): ?>
                    <a href="<?php echo $base_pagination_url_all_pg; ?>page=<?php echo $page - 1; ?>">&laquo; Previous</a>
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
                            <a href="<?php echo $base_pagination_url_all_pg; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php
                     elseif (($i == $page - $range - 1 && $page - $range - 1 > 1) || ($i == $page + $range + 1 && $page + $range + 1 < $totalPages)):
                    ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo $base_pagination_url_all_pg; ?>page=<?php echo $page + 1; ?>">Next &raquo;</a>
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
            const advancedSearchBtnAll = document.getElementById('advancedSearchBtnAllPage');
            const advancedSearchOptionsAll = document.getElementById('advancedSearchOptionsAllPage');
            const resetSearchBtnAll = document.getElementById('resetSearchBtnAllPage');
            const allRecipesForm = document.getElementById('allRecipesUnifiedForm');
            const pageInputAllForm = document.getElementById('all_recipes_page_input_form');


            if(advancedSearchBtnAll && advancedSearchOptionsAll) {
                advancedSearchBtnAll.addEventListener('click', function() {
                    if (advancedSearchOptionsAll.style.display === 'none' || advancedSearchOptionsAll.style.display === '') {
                        advancedSearchOptionsAll.style.display = 'block';
                        this.textContent = 'Hide Filters';
                    } else {
                        advancedSearchOptionsAll.style.display = 'none';
                        this.textContent = 'Advanced Filters';
                    }
                });
                const urlParamsAll = new URLSearchParams(window.location.search);
                let advancedActiveAll = false;
                const nonDefaultFiltersAll = ['min_Calories', 'max_Calories', /* add all your filter keys here */ 'min_RecipeServings', 'max_RecipeServings'];
                nonDefaultFiltersAll.forEach(key => { if (urlParamsAll.has(key) && urlParamsAll.get(key) !== '') advancedActiveAll = true; });
                if (urlParamsAll.has('search_by') && urlParamsAll.get('search_by') !== 'recipe_name' && urlParamsAll.get('search_by') !== '') advancedActiveAll = true;
                if (urlParamsAll.has('q') && urlParamsAll.get('q') !== '') advancedActiveAll = true;


                if (advancedActiveAll) {
                    advancedSearchOptionsAll.style.display = 'block';
                    if(advancedSearchBtnAll) advancedSearchBtnAll.textContent = 'Hide Filters';
                } else {
                     if(advancedSearchBtnAll) advancedSearchBtnAll.textContent = 'Advanced Filters';
                }
            }

            if (allRecipesForm) {
                 allRecipesForm.addEventListener('submit', function() {
                    if (advancedSearchOptionsAll) advancedSearchOptionsAll.style.display = 'none';
                    if (advancedSearchBtnAll) advancedSearchBtnAll.textContent = 'Advanced Filters';
                });
            }

            if (resetSearchBtnAll) {
                resetSearchBtnAll.addEventListener('click', function() {
                    // Clear specific fields if form.reset() is not enough or if you want to control defaults
                    if (allRecipesForm) {
                        allRecipesForm.reset(); // Resets to default HTML values for controlled inputs
                        // Manually clear text inputs and ensure selects are at default
                        allRecipesForm.querySelectorAll('input[type="text"], input[type="number"]').forEach(input => input.value = '');
                        allRecipesForm.querySelectorAll('select').forEach(select => {
                            if (select.id === 'sort_by_all_page_form') select.value = 'recipe_name'; // Default sort
                            else select.selectedIndex = 0;
                        });
                        // Reset sort direction button and hidden input
                        const sortDirInput = document.getElementById('sort_dir_all_page_form');
                        const sortDirButton = document.getElementById('sort_dir_toggle_all_page_form');
                        if(sortDirInput) sortDirInput.value = 'ASC'; // Default for recipe_name
                        if(sortDirButton) sortDirButton.textContent = 'ASC';
                        if(pageInputAllForm) pageInputAllForm.value = '1';
                    }
                    window.location.href = 'all_recipes.php'; // Redirect to clean all_recipes page
                });
            }
            
            // --- Sort Controls for All Recipes Page (Auto-submit) ---
            const sortBySelectAllForm = document.getElementById('sort_by_all_page_form');
            const sortDirInputAllForm = document.getElementById('sort_dir_all_page_form');
            const sortDirToggleButtonAllForm = document.getElementById('sort_dir_toggle_all_page_form');

            const sortOptionsDefaultsAllPage = {
                'recipe_name': 'ASC', 'date_added': 'DESC', 'calories': 'ASC',
                'review_count': 'DESC', 'average_review': 'DESC', 'total_time': 'ASC'
            };

            if (sortBySelectAllForm && allRecipesForm) {
                sortBySelectAllForm.addEventListener('change', function() {
                    const selectedSortBy = this.value;
                    const defaultDir = sortOptionsDefaultsAllPage[selectedSortBy] || 'ASC';
                    if (sortDirInputAllForm) sortDirInputAllForm.value = defaultDir;
                    if (sortDirToggleButtonAllForm) sortDirToggleButtonAllForm.textContent = defaultDir;
                    if (pageInputAllForm) pageInputAllForm.value = '1'; 
                    allRecipesForm.submit();
                });
            }

            if (sortDirToggleButtonAllForm && allRecipesForm) {
                sortDirToggleButtonAllForm.addEventListener('click', function() {
                    const newDir = sortDirInputAllForm.value === 'ASC' ? 'DESC' : 'ASC';
                    sortDirInputAllForm.value = newDir;
                    this.textContent = newDir;
                    if (pageInputAllForm) pageInputAllForm.value = '1'; 
                    allRecipesForm.submit();
                });
            }

            // Favorite star functionality
            document.querySelectorAll('.favorite-star').forEach(star => {
                star.addEventListener('click', function() {
                    const recipeId = this.dataset.recipeId;
                    const messageEl = document.getElementById(`fav-msg-allr-${recipeId}`);
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