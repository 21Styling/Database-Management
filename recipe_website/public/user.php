<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: signin.php');
    exit;
}
?>
<!DOCTYPE html>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css">
<html>
<head><title>Your Account</title></head>
<body>
  <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
  <ul>
    <li><a href="ingredients.php">Enter Owned Ingredients</a></li>
    <li><a href="favorites.php">View Favorite Recipes</a></li>
    <li><a href="logout.php">Sign Out</a></li>
  </ul>
</body>
</html>
