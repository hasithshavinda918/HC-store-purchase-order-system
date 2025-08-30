<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'hc_store_stock';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8");
?>
