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