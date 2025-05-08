<?php
// 1. Include the database connection
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo

// --- Define a Curated List of Common Ingredients ---
$common_ingredients = [
    'all-purpose flour', 'baking powder', 'baking soda', 'basil', 'bay leaf',
    'beef', 'bell pepper', 'black pepper', 'bread crumbs', 'broccoli',
    'brown sugar', 'butter', 'carrot', 'celery', 'cheddar cheese', 'chicken',
    'chicken broth', 'chili powder', 'cinnamon', 'corn', 'cumin', 'egg',
    'garlic', 'garlic powder', 'green beans', 'ground beef', 'honey', 'ketchup',
    'lemon juice', 'mayonnaise', 'milk', 'mustard', 'olive oil', 'onion',
    'onion powder', 'oregano', 'paprika', 'parsley', 'pasta', 'pork',
    'potato', 'rice', 'salt', 'soy sauce', 'sugar', 'thyme', 'tomato',
    'tomato paste', 'tomato sauce', 'vanilla extract', 'vegetable oil', 'vinegar',
    'water', 'worcestershire sauce', 'yeast'
];
sort($common_ingredients); // Sort alphabetically for display

// --- Handle Form Submission ---
$selected_ingredients = [];
$matching_recipes = [];
$search_error = null;
$search_performed = false; // Flag to know if a search was done

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ingredients'])) {
    $search_performed = true;
    // Ensure ingredients is an array and sanitize values
    if (is_array($_GET['ingredients'])) {
        foreach ($_GET['ingredients'] as $ing) {
            // Basic sanitization - allow letters, numbers, spaces, hyphens
            if (preg_match('/^[a-zA-Z0-9\s\-]+$/', $ing)) {
                // Only add if it's in our curated list (optional safety check)
                if (in_array(strtolower($ing), $common_ingredients)) {
                     $selected_ingredients[] = trim($ing);
                }
            }
        }
    }

    if (!empty($selected_ingredients)) {
        try {
            // --- Build the SQL Query ---
            $sql = "SELECT RecipeId, Recipe_Name, Average_Rating, Image_URL, Ingredients
                    FROM Recipes
                    WHERE Recipe_Name IS NOT NULL AND Recipe_Name != ''
                      AND Image_URL IS NOT NULL AND Image_URL != '' AND Image_URL != 'character(0)'";

            $like_clauses = [];
            $bind_params = [];
            $i = 0;
            foreach ($selected_ingredients as $ingredient) {
                $placeholder = ":ingredient" . $i;
                // Try to match the ingredient within quotes or as a whole word
                $like_clauses[] = "(Ingredients LIKE " . $placeholder . "_1 OR Ingredients LIKE " . $placeholder . "_2)";
                $bind_params[$placeholder . "_1"] = "%'" . $ingredient . "'%"; // Match like 'ingredient'
                $bind_params[$placeholder . "_2"] = '% ' . $ingredient . ' %'; // Match like space ingredient space
                $i++;
            }

            if (!empty($like_clauses)) {
                $sql .= " AND " . implode(' AND ', $like_clauses);
            }

            $sql .= " ORDER BY Average_Rating DESC, Rating_Count DESC LIMIT 50";

            $stmt = $pdo->prepare($sql);

            foreach ($bind_params as $key => $value) {
                 $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }

            $stmt->execute();
            $matching_recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Database Query Error (Pantry Search): " . $e->getMessage());
            $search_error = "An error occurred during the search.";
        }
    } else {
        if (isset($_GET['ingredients']) && empty($selected_ingredients)) {
             $search_error = "Invalid ingredients selected.";
        }
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

$pageTitle = "Search Recipes by Ingredients";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .ingredient-list {
            columns: 4; /* Adjust column count */
            -webkit-columns: 4;
            -moz-columns: 4;
            list-style: none;
            padding: 0;
            margin-bottom: 1em; /* Add space below list */
        }
        .ingredient-list li {
            margin-bottom: 0.5em;
            display: block;
            break-inside: avoid-column;
        }
        .ingredient-list label {
            display: block;
            cursor: pointer;
            font-size: 0.9em; /* Slightly smaller font for list */
        }
         .search-results { margin-top: 2em; }
         .search-results h3 { border-bottom: 1px solid #eee; padding-bottom: 0.5em; }
         .recipe-list-with-images { margin-top: 1em; }
         button[type="submit"] { /* Style the button */
            padding: 0.6em 1.2em;
            background-color: #0056b3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
         }
         button[type="submit"]:hover {
            background-color: #004085;
         }
    </style>
</head>
<body>

    <header class="site-header">
        <h1>Recipe Website</h1>
         <p><a href="index.php">&laquo; Back to Home</a></p>
    </header>

    <main class="container">
        <h2><?php echo htmlspecialchars($pageTitle); ?></h2>
        <p>Select the ingredients you have on hand:</p>

        <form action="pantry_search.php" method="GET">
            <ul class="ingredient-list">
                <?php foreach ($common_ingredients as $ingredient): ?>
                    <li>
                        <label>
                            <input type="checkbox"
                                   name="ingredients[]"
                                   value="<?php echo htmlspecialchars($ingredient); ?>"
                                   <?php // Keep checkboxes checked after search ?>
                                   <?php echo (in_array($ingredient, $selected_ingredients) ? 'checked' : ''); ?>
                            >
                            <?php echo htmlspecialchars(ucfirst($ingredient)); // Capitalize first letter ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p>
                <button type="submit">Find Recipes</button>
            </p>
        </form>

        <?php // --- Display Search Results --- ?>
        <?php if ($search_performed): ?>
            <section class="search-results">
                <h3>Search Results</h3>
                <?php if ($search_error): ?>
                    <p class="error-message"><?php echo htmlspecialchars($search_error); ?></p>
                <?php elseif (empty($selected_ingredients)): ?>
                     <p>Please select at least one ingredient.</p>
                <?php elseif (!empty($matching_recipes)): ?>
                    <p>Found <?php echo count($matching_recipes); ?> recipe(s) containing all selected ingredients:</p>
                    <ul class="recipe-list recipe-list-with-images">
                        <?php foreach ($matching_recipes as $recipe): ?>
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
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: // No recipes found ?>
                    <p>No recipes found containing all the selected ingredients: <?php echo htmlspecialchars(implode(', ', $selected_ingredients)); ?>.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <?php // --- End Search Results --- ?>

    </main>

    <footer class="site-footer">
        <p>&copy; <?php echo date('Y'); ?> Recipe Website</p>
    </footer>

    <script src="script.js"></script>
</body>
</html>