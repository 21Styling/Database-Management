<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: signin.php');
    exit;
}
require_once __DIR__ . '/../src/db_connect.php';

// grab current JSON arrays
$stmt = $pdo->prepare("
  SELECT `Owned_Ingredients`, `User_Quantity`
    FROM `User`
   WHERE `Username` = ?
");
$stmt->execute([$_SESSION['username']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$names  = json_decode($user['Owned_Ingredients'], true) ?? [];
$quants = json_decode($user['User_Quantity'],    true) ?? [];

// read form inputs
$action   = $_POST['action']   ?? '';
$index    = isset($_POST['index']) ? intval($_POST['index']) : null;
$name     = trim($_POST['name']     ?? '');
$quantity = trim($_POST['quantity'] ?? '');
$unit     = trim($_POST['unit']     ?? '');

// build the full text
$full = $quantity && $unit && $name
      ? "{$quantity} {$unit} {$name}"
      : '';

if ($action === 'add') {
    // append
    $names[]  = $name;
    $quants[] = $full;

} elseif ($action === 'update' && $index !== null && isset($names[$index])) {
    // update in place
    $names[$index]  = $name;
    $quants[$index] = $full;

} elseif ($action === 'delete' && $index !== null && isset($names[$index])) {
    array_splice($names,  $index, 1);
    array_splice($quants, $index, 1);
}

// write back to the JSON columns
$upd = $pdo->prepare("
  UPDATE `User`
     SET `Owned_Ingredients` = ?, `User_Quantity` = ?
   WHERE `Username` = ?
");
$upd->execute([
    json_encode($names),
    json_encode($quants),
    $_SESSION['username']
]);

header('Location: ingredients.php');
exit;