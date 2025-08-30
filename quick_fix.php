<?php
// Quick admin password fix
$conn = new mysqli('localhost', 'root', '', 'hc_store_stock');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate proper password hash for 'admin123'
$new_password = password_hash('admin123', PASSWORD_DEFAULT);

// Update admin password
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$stmt->bind_param("s", $new_password);

if ($stmt->execute()) {
    echo "✅ Admin password fixed successfully!\n";
    echo "You can now login with:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
} else {
    echo "❌ Error: " . $conn->error . "\n";
}

$stmt->close();
$conn->close();
?>
