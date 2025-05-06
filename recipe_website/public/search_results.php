<?php
require_once __DIR__ . '/../src/db_connect.php';

$searchTerm = $_GET['q'] ?? '';
$searchResults = [];

if ($searchTerm) {
    try {
        $sql = "SELECT RecipeId, Recipe_Name, Average_Rating, Image_URL
                FROM Recipes
                WHERE Recipe_Name LIKE :searchTerm
                ORDER BY Recipe_Name ASC
                LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['searchTerm' => '%' . $searchTerm . '%']);
        $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Search Query Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header><h1>Search Results for "<?php echo htmlspecialchars($searchTerm); ?>"</h1></header>
    <main>
        <?php if (!empty($searchResults)): ?>
            <ul class="recipe-list">
                <?php foreach ($searchResults as $recipe): ?>
                    <li>
                        <a href="recipe_detail.php?id=<?php echo htmlspecialchars($recipe['RecipeId']); ?>">
                            <?php echo htmlspecialchars($recipe['Recipe_Name']); ?>
                        </a>
                        <span>(Rating: <?php echo htmlspecialchars($recipe['Average_Rating'] ?? 'N/A'); ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No recipes found.</p>
        <?php endif; ?>
    </main>
</body>
</html>

