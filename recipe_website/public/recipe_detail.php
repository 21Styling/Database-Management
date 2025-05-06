<?php
// 1. Include the database connection
require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo object

session_start(); // Start session (if not already started)

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

// Fetch user's favorite recipes if logged in
$userFavorites = [];
if (isset($_SESSION['username'])) {
    try {
        $stmt = $pdo->prepare("SELECT Favorites FROM User WHERE Username = ?");
        $stmt->execute([$_SESSION['username']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $userFavorites = $result ? json_decode($result['Favorites'], true) : [];
    } catch (PDOException $e) {
        error_log("Database error fetching favorites: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> <style>
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
        .favorite-star {
            cursor: pointer;
            color: #ccc; /* Default color (outline) */
        }
        .favorite-star.favorited {
            color: gold; /* Color when favorited */
        }
        .favorite-message {
            font-size: 0.8em;
            color: #777;
            margin-top: 0.2em;
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
        <h1>
            <?php echo htmlspecialchars($recipe['Recipe_Name'] ?? 'Recipe Name Not Found'); ?>
            <i class="far fa-star favorite-star <?php echo (isset($_SESSION['username']) && in_array($recipe['RecipeId'], $userFavorites)) ? 'favorited' : ''; ?>"
               data-recipe-id="<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></i>
            <span class="favorite-message" id="fav-msg-<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></span>
        </h1>

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
    <script>
        // Favorite star functionality (now also on detail page)
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
    </script>
</body>
</html>
3.  public/update_favorites.php (Unchanged)

PHP

<?php
session_start();
require_once __DIR__ . '/../src/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Please sign in to add recipes to favorites.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$recipeId = $data['recipeId'] ?? null;
$action = $data['action'] ?? null;

if (!$recipeId || !$action || !in_array($action, ['add', 'remove'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT Favorites FROM User WHERE Username = ?");
    $stmt->execute([$_SESSION['username']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $favorites = $result ? json_decode($result['Favorites'], true) : [];

    if ($action === 'add') {
        if (!in_array($recipeId, $favorites)) {
            $favorites[] = $recipeId;
        }
    } else {
        $favorites = array_filter($favorites, function($favId) use ($recipeId) {
            return $favId != $recipeId;
        });
    }

    $stmt = $pdo->prepare("UPDATE User SET Favorites = ? WHERE Username = ?");
    $stmt->execute([json_encode(array_values($favorites)), $_SESSION['username']]); // Use array_values to re-index

    echo json_encode(['success' => true, 'message' => 'Favorites updated.']);

} catch (PDOException $e) {
    error_log("Error updating favorites: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update favorites.']);
}
?>
