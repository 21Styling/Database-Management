<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: signin.php');
    exit;
}
?>
<!DOCTYPE html>
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
