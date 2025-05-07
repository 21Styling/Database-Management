<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/db_connect.php';

// Make sure we always return JSON
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$email    = trim($data['email']    ?? '');
$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? '');

if (!$email || !$username || !$password) {
    http_response_code(400);
    echo json_encode(["message" => "All fields are required."]);
    exit;
}

try {
    // Check username/email uniqueness
    $stmt = $pdo->prepare("SELECT 1 FROM User WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(["message" => "Username already exists."]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT 1 FROM User WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(["message" => "Email already in use."]);
        exit;
    }

    // Hash & insert
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
      "INSERT INTO User (email, username, password) VALUES (?, ?, ?)"
    );
    $stmt->execute([$email, $username, $hashed]);

    // ✅ Use the inserted username for the session
    $_SESSION['username'] = $username;

    // ✅ Return success
    http_response_code(201);
    echo json_encode(["message" => "Sign up successful!"]);
    exit;

} catch (PDOException $e) {
    error_log("Sign up error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Server error. Please try again."]);
    exit;
}