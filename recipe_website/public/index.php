<?php
// Attempt to include the database connection file
// This connects to the DB and creates the $pdo variable
require_once __DIR__ . '/../src/db_connect.php';

// Define a title for the page
$pageTitle = "Recipe Website";

// --- Fetch recipes from the database ---
try {
    // 1. Prepare the SQL query
    //    (Make sure 'Recipes' table and columns like RecipeId, Recipe_Name,
    //     AuthorId, Average_Rating exist in your database)
    $sql = "SELECT RecipeId, Recipe_Name, AuthorId, Average_Rating
            FROM Recipes
            ORDER BY RecipeId DESC -- Changed this line!
            LIMIT 20";

    // 2. Prepare the statement using the $pdo connection object
    $stmt = $pdo->prepare($sql);

    // 3. Execute the statement
    $stmt->execute();

    // 4. Fetch all results into an array of associative arrays
    $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // If something goes wrong during the query:
    // Log the actual error for server admin/developer
    error_log("Database Query Error: " . $e->getMessage());
    // Set $recipes to empty to avoid errors in the HTML below
    $recipes = [];
    // We'll display a user-friendly message in the HTML part
    $queryError = "Sorry, an error occurred while fetching recipes.";
}
// --- End of fetching recipes ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); // Use htmlspecialchars for safety ?></title>
    <link rel="stylesheet" href="css/style.css"> </head>
<body>
    <h1>Welcome to the Recipe Website!</h1>

    <hr> <h2>Recipe List</h2>

    <?php
    // Check if there was a query error during fetching
    if (isset($queryError)):
    ?>
        <p style='color: red;'><?php echo $queryError; ?></p>
    <?php
    // Check if the $recipes array is not empty (meaning query ran successfully and found recipes)
    elseif (!empty($recipes)):
    ?>
        <ul>
            <?php foreach ($recipes as $recipe): ?>
                <li>
                    <?php /* Display Recipe Name */ ?>
                    <strong><?php echo htmlspecialchars($recipe['Recipe_Name']); ?></strong>

                    <?php /* Display other details - use null coalescing (??) for potentially NULL values */ ?>
                    (ID: <?php echo htmlspecialchars($recipe['RecipeId']); ?>,
                     Rating: <?php echo htmlspecialchars($recipe['Average_Rating'] ?? 'N/A'); ?>,
                     AuthorID: <?php echo htmlspecialchars($recipe['AuthorId'] ?? 'N/A'); ?>)

                    <?php /* Example: Add a link later */ ?>
                    <?php /* <a href="recipe_detail.php?id=<?php echo $recipe['RecipeId']; ?>">View Details</a> */ ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php
    // If $recipes is empty AND there was no query error, then no recipes were found
    else:
    ?>
        <p>No recipes found in the database.</p>
    <?php endif; ?>

    <script src="js/script.js"></script> </body>
</html>