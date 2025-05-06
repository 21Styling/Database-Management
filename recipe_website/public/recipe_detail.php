
<?php
// 1. Include the database connection
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo object

// --- 2. Get and Validate Recipe ID from URL ---
$recipe_id = null;
if (isset($_GET['id'])) {
    if (filter_var($_GET['id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $recipe_id = (int)$_GET['id'];
    } else {
        exit('Invalid Recipe ID format provided.');
    }
} else {
    exit('No Recipe ID provided.');
}

/**
 * Function to extract the first valid image URL from the stored string.
 * Handles c("url1",...), "url1", and ignores character(0).
 * (Could be moved to a shared functions file later)
 */
function extractFirstImageUrl($imageUrlString) {
    if (empty($imageUrlString) || $imageUrlString === 'character(0)') {
        return null;
    }

    // 1. Check for c("url1", ...) format
    if (preg_match('/^c?\("([^"]+)"/', $imageUrlString, $matches)) {
        $potentialUrl = $matches[1];
        if (filter_var($potentialUrl, FILTER_VALIDATE_URL)) {
            return $potentialUrl;
        }
    }

    // 2. Check if the string itself (potentially quote-wrapped) is a URL
    $trimmedUrl = trim($imageUrlString, ' "'); // Remove surrounding quotes/spaces
    if (filter_var($trimmedUrl, FILTER_VALIDATE_URL)) {
        return $trimmedUrl;
    }

    // 3. If neither format matches or contains a valid URL, return null
    return null;
}


// --- 3. Fetch Recipe Details from Database ---
$recipe = null; // Initialize recipe variable
$pageTitle = "Recipe Details"; // Default page title
$imageUrl = null; // Initialize image URL variable

try {
    // Prepare SQL query to get all details for the specific recipe
    $sql = "SELECT * FROM Recipes WHERE RecipeId = :recipe_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':recipe_id', $recipe_id, PDO::PARAM_INT);
    $stmt->execute();
    $recipe = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($recipe) {
        $pageTitle = htmlspecialchars($recipe['Recipe_Name'] ?? 'Recipe Details');
        // Use the helper function to extract the image URL
        $imageUrl = extractFirstImageUrl($recipe['Image_URL']);
    }

} catch (PDOException $e) {
    error_log("Database Query Error on Detail Page: " . $e->getMessage());
    $queryError = "Sorry, an error occurred while fetching recipe details.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Basic styling specific to detail page (can move to style.css) */
        .recipe-section { margin-bottom: 1.5em; }
        .recipe-section h2 { margin-bottom: 0.5em; }
        .recipe-ingredients ul, .recipe-instructions ol { margin-left: 2em; }
        .recipe-main-image {
             max-width: 100%;
             height: auto;
             display: block;
             margin-bottom: 1.5em;
             border-radius: 5px;
        }
        .recipe-image-placeholder { /* Style for placeholder */
            width: 100%;
            height: 200px; /* Adjust as needed */
            background-color: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            font-style: italic;
            margin-bottom: 1.5em;
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <p><a href="index.php">&laquo; Back to Recipe List</a></p>
    <hr>

    <?php
    // --- 4. Display Recipe Details (or errors) ---
    if (isset($queryError)):
    ?>
        <p style='color: red;'><?php echo $queryError; ?></p>
    <?php
    elseif ($recipe):
    ?>
        <h1><?php echo htmlspecialchars($recipe['Recipe_Name'] ?? 'Recipe Name Not Found'); ?></h1>

        <?php // Display the main image if available, otherwise maybe a placeholder ?>
        <?php if ($imageUrl): ?>
            <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                 alt="<?php echo htmlspecialchars($recipe['Recipe_Name'] ?? 'Recipe Image'); ?>"
                 class="recipe-main-image"
                 onerror="this.style.display='none'"> <?php /* Hide if image fails */ ?>
        <?php else: ?>
             <div class="recipe-image-placeholder">No Image Available</div>
        <?php endif; ?>

        <div class="recipe-meta">
            <p>
                <strong>Category:</strong> <?php echo htmlspecialchars($recipe['Category'] ?? 'N/A'); ?> |
                <strong>Rating:</strong> <?php echo htmlspecialchars($recipe['Average_Rating'] ?? 'N/A'); ?> / 5
                (<?php echo htmlspecialchars($recipe['Rating_Count'] ?? 0); ?> ratings) |
                <strong>Recipe ID:</strong> <?php echo htmlspecialchars($recipe['RecipeId'] ?? 'N/A'); ?> |
                <strong>Author ID:</strong> <?php echo htmlspecialchars($recipe['AuthorId'] ?? 'N/A'); ?>
            </p>
            <?php if (!empty($recipe['Date'])): ?>
                <p><em>Submitted on: <?php echo date("F j, Y", strtotime($recipe['Date'])); ?></em></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($recipe['Description'])): ?>
        <div class="recipe-section recipe-description">
            <h2>Description</h2>
            <p><?php echo nl2br(htmlspecialchars($recipe['Description'])); ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($recipe['Ingredients']) || !empty($recipe['Ingredient_Quantity'])): ?>
        <div class="recipe-section recipe-ingredients">
            <h2>Ingredients</h2>
            <?php if (!empty($recipe['Ingredient_Quantity'])): ?>
                <p><strong>Quantities:</strong> <?php echo nl2br(htmlspecialchars($recipe['Ingredient_Quantity'])); ?></p>
            <?php endif; ?>
            <?php if (!empty($recipe['Ingredients'])): ?>
                 <p><strong>Items:</strong> <?php echo htmlspecialchars($recipe['Ingredients']); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <?php if (!empty($recipe['Instructions'])): ?>
        <div class="recipe-section recipe-instructions">
            <h2>Instructions</h2>
            <?php echo nl2br(htmlspecialchars($recipe['Instructions'])); ?>
        </div>
        <?php endif; ?>

        <div class="recipe-section recipe-reviews">
             <h2>Reviews</h2>
             <p><em>(Review display functionality will be added later)</em></p>
        </div>

    <?php
    else: // Recipe not found
    ?>
        <h1>Recipe Not Found</h1>
        <p>Sorry, we couldn't find a recipe with the ID <?php echo htmlspecialchars($recipe_id); ?>.</p>
    <?php
    endif;
    ?>

    <hr>
    <p><a href="index.php">&laquo; Back to Recipe List</a></p>

    <script src="script.js"></script>
</body>
</html>
