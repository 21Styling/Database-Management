<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../src/db_connect.php'; // Provides $pdo

// Make sure we always return JSON
header('Content-Type: application/json');

// This is the beginning of the new section
$data = json_decode(file_get_contents("php://input"), true);
$email    = trim($data['email']    ?? '');
$username = trim($data['username'] ?? '');
$password = trim($data['password'] ?? ''); // Raw password

if (!$email || !$username || !$password) {
    http_response_code(400);
    echo json_encode(["message" => "All fields are required."]);
    exit;
}

// Hash the password before sending to SP
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    // Prepare the stored procedure call
    // Note: We're binding the OUT parameter as well to retrieve it.
    $stmt = $pdo->prepare("CALL RegisterUser(?, ?, ?, @signupStatus)");
    
    // Bind IN parameters
    $stmt->bindParam(1, $username, PDO::PARAM_STR);
    $stmt->bindParam(2, $email, PDO::PARAM_STR);
    $stmt->bindParam(3, $hashedPassword, PDO::PARAM_STR);
    
    // Execute the stored procedure
    $stmt->execute();
    $stmt->closeCursor(); // Close cursor before fetching OUT parameter

    // Fetch the OUT parameter
    $statusRow = $pdo->query("SELECT @signupStatus AS signupStatus")->fetch(PDO::FETCH_ASSOC);
    $signupStatus = $statusRow['signupStatus'];

    if ($signupStatus == 'Success') {
        $_SESSION['username'] = $username; // Set session
        http_response_code(201);
        echo json_encode(["message" => "Sign up successful!"]);
    } elseif ($signupStatus == 'UsernameExists') {
        http_response_code(409);
        echo json_encode(["message" => "Username already exists."]);
    } elseif ($signupStatus == 'EmailExists') {
        http_response_code(409);
        echo json_encode(["message" => "Email already in use."]);
    } else {
        // This case should ideally not be reached if the SP logic is comprehensive for known errors
        http_response_code(500);
        echo json_encode(["message" => "An unexpected error occurred during signup: " . ($signupStatus ?? 'Unknown SP status')]);
    }
    exit;

} catch (PDOException $e) {
    error_log("Sign up error (SP): " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "Server error. Please try again."]);
    exit;
}
// This is the end of the new section
?>