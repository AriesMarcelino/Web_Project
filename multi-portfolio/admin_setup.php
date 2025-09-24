<?php
require_once 'db.php'; // Include database connection

// Create database connection
$conn = new mysqli("localhost", "root", "", "portfolio_db");

$username = 'administrator'; // Set your desired username
$plainPassword = 'admin1234'; // Set your desired password

$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

$sql = "INSERT INTO admin (username, password) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $hashedPassword);

if ($stmt->execute()) {
    echo "✅ Admin account created successfully!";
} else {
    echo "❌ Error: " . $stmt->error;
}
?>
