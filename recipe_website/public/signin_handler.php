<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/db_connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// Simple validation
if (!$username || !$password) {
    echo json_encode(["message" => "All fields are required."]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM User WHERE Username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Fetched user: " . print_r($user, true));

    // ✅ Check user exists and has a password hash
    if (!$user || !isset($user['Password'])) {
        echo json_encode(["message" => "Invalid username or password."]);
        exit;
    }

    // ✅ Now safe to verify password
    if (!password_verify($password, $user['Password'])) {
        echo json_encode(["message" => "Invalid username or password."]);
        exit;
    }

    // ✅ Success
    $_SESSION['username'] = $user['Username'];
    echo json_encode(["message" => "Sign in successful!"]);

} catch (PDOException $e) {
    error_log("Sign in error: " . $e->getMessage());
    echo json_encode(["message" => "Server error. Please try again."]);
    exit;
}