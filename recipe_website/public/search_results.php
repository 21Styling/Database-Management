<?php
session_start();
require_once __DIR__ . '/../src/db_connect.php';

$searchTerm = $_GET['q'] ?? '';
$searchResults = [];
$page = $_GET['page'] ?? 1;
$resultsPerPage = 20;
$offset = ($page - 1) * $resultsPerPage;

if ($searchTerm) {
    try {
        $sql = "SELECT RecipeId, Recipe_Name, Average_Rating, Image_URL
                FROM Recipes
                WHERE Recipe_Name LIKE :searchTerm
                ORDER BY Recipe_Name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['searchTerm' => '%' . $searchTerm . '%']);
        $totalResults = $stmt->rowCount();

        $sqlPaged = "SELECT RecipeId, Recipe_Name, Average_Rating, Image_URL
                FROM Recipes
                WHERE Recipe_Name LIKE :searchTerm
                ORDER BY Recipe_Name ASC
                LIMIT :limit OFFSET :offset";
        $stmtPaged = $pdo->prepare($sqlPaged);
        $stmtPaged->bindValue(':searchTerm', '%' . $searchTerm . '%', PDO::PARAM_STR);
        $stmtPaged->bindValue(':limit', $resultsPerPage, PDO::PARAM_INT);
        $stmtPaged->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtPaged->execute();
        $searchResults = $stmtPaged->fetchAll(PDO::FETCH_ASSOC);

        $totalPages = ceil($totalResults / $resultsPerPage);
    } catch (PDOException $e) {
        error_log("Search Query Error: " . $e->getMessage());
        $error_message = "An error occurred while performing the search.";
        $searchResults = [];
        $totalResults = 0;
        $totalPages = 0;
    }
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

/**
 * Function to extract the first valid image URL from the stored string.
 */
function extractFirstImageUrl($imageUrlString) {
    if (empty($imageUrlString) || $imageUrlString === 'character(0)') {
        return null;
    }
    $trimmedUrl = trim($imageUrlString, ' "');
    if (filter_var($trimmedUrl, FILTER_VALIDATE_URL)) {
        return $trimmedUrl;
    }
    if (preg_match('/^c?\("([^"]+)"/', $imageUrlString, $matches)) {
        $potentialUrl = $matches[1];
        if (filter_var($potentialUrl, FILTER_VALIDATE_URL)) {
            return $potentialUrl;
        }
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <header class="site-header">
        <p><a href="index.php">&laquo; Back to Home</a></p>
    </header>
  <title>Results for <?php echo htmlspecialchars($_SESSION['searchTerm']); ?></title>
    <style>
        .favorite-star {
            cursor: pointer;
            color: #ccc;
        }
        .favorite-star.favorited {
            color: gold;
        }
        .favorite-message {
            font-size: 0.8em;
            color: #777;
            margin-top: 0.2em;
        }
        .pagination {
            margin: 2em 0 1em 0;
            text-align: center;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 0.5em 1em;
            margin: 0 0.2em;
            border: 1px solid #ddd;
            color: #0056b3;
            text-decoration: none;
            border-radius: 3px;
        }
        .pagination a:hover {
            background-color: #eee;
        }
        .pagination .current-page {
            background-color: #0056b3;
            color: white;
            border-color: #0056b3;
            font-weight: bold;
        }
        .pagination .disabled {
            color: #aaa;
            border-color: #eee;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <header>
        <h1>Search Results for "<?php echo htmlspecialchars($searchTerm); ?>"</h1>
    </header>
    <main>
        <?php if (isset($error_message)): ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php elseif (!empty($searchResults)): ?>
            <p>Found <?php echo $totalResults; ?> recipe(s).</p>
            <ul class="recipe-list recipe-list-with-images">
                <?php foreach ($searchResults as $recipe): ?>
                    <li>
                        <?php $imageUrl = extractFirstImageUrl($recipe['Image_URL']); ?>
                        <?php if ($imageUrl): ?>
                            <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                                <img src="<?php echo htmlspecialchars($imageUrl); ?>"
                                     alt="<?php echo htmlspecialchars($recipe['Recipe_Name']); ?>"
                                     class="recipe-list-image"
                                     loading="lazy"
                                     onerror="this.style.display='none'">
                            </a>
                        <?php else: ?>
                            <div class="recipe-list-image-placeholder">No Image</div>
                        <?php endif; ?>
                        <div class="recipe-list-info">
                            <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                                <?php echo htmlspecialchars($recipe['Recipe_Name']); ?>
                            </a>
                            <span class="rating">(Rating: <?php echo htmlspecialchars($recipe['Average_Rating'] ?? 'N/A'); ?>)</span>
                            <i class="far fa-star favorite-star <?php echo (isset($_SESSION['username']) && in_array($recipe['RecipeId'], $userFavorites)) ? 'favorited' : ''; ?>"
                               data-recipe-id="<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></i>
                            <span class="favorite-message" id="fav-msg-<?php echo htmlspecialchars($recipe['RecipeId']); ?>"></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?q=<?php echo urlencode($searchTerm); ?>&page=<?php echo $page - 1; ?>">&laquo; Previous</a>
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
                                <a href="?q=<?php echo urlencode($searchTerm); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php
                        elseif ($i == $page - $range - 1 || $i == $page + $range + 1):
                        ?>
                            <span>...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?q=<?php echo urlencode($searchTerm); ?>&page=<?php echo $page + 1; ?>">Next &raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Next &raquo;</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p id="no-results-message">No recipes found.</p>
        <?php endif; ?>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const noResultsMessage = document.getElementById('no-results-message');
            if (noResultsMessage) {
                setTimeout(function() {
                    noResultsMessage.style.display = 'none';
                }, 5000);
            }

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
                                setTimeout(() => messageEl.textContent = '', 2000);
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
        });
    </script>
</body>
</html>
