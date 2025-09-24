<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "portfolio_db";

try {
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    // Test the connection
    if (!$conn->ping()) {
        throw new Exception("Database connection lost");
    }
} catch (Exception $e) {
    // Log error with more details
    error_log("Database connection error: " . $e->getMessage() . " | Host: $host | DB: $db");
    die("Database connection failed. Please check your database configuration.");
}
?>
