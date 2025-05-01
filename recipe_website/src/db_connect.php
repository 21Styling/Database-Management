<?php
// Include database configuration
require_once __DIR__ . '/../config/db_config.php';

// Set up the DSN (Data Source Name) string
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// Set PDO options for error handling and fetching
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements for security
];

try {
    // Attempt to create a new PDO connection object
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // If connection fails, catch the exception
    error_log("Database Connection Error: " . $e->getMessage());
    // Display a generic error message to the user and stop script execution
    exit('Sorry, a database connection error occurred. Please try again later.');
}

// If the script reaches here without exiting, the $pdo variable holds the active database connection object
?>