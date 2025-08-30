<?php
// Check and fix admin password
echo "<h2>Admin Password Debug Tool</h2>";
echo "<hr>";

require_once 'includes/db_connection.php';

// Check current admin user
echo "<h3>1. Current Admin User in Database:</h3>";
$result = $conn->query("SELECT id, username, password, full_name, role FROM users WHERE username = 'admin'");

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "✅ Admin user found:<br>";
    echo "ID: " . $admin['id'] . "<br>";
    echo "Username: " . $admin['username'] . "<br>";
    echo "Full Name: " . $admin['full_name'] . "<br>";
    echo "Role: " . $admin['role'] . "<br>";
    echo "Password Hash: " . substr($admin['password'], 0, 20) . "...<br>";
    
    // Test password verification
    echo "<h3>2. Testing Password Verification:</h3>";
    $test_password = 'admin123';
    
    if (password_verify($test_password, $admin['password'])) {
        echo "✅ Password 'admin123' works correctly<br>";
    } else {
        echo "❌ Password 'admin123' does NOT work<br>";
        echo "Current hash: " . $admin['password'] . "<br>";
        
        // Generate correct hash
        $correct_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "Correct hash should be: " . $correct_hash . "<br>";
        
        echo "<h3>3. Fixing Password:</h3>";
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $stmt->bind_param("s", $correct_hash);
        
        if ($stmt->execute()) {
            echo "✅ Password updated successfully!<br>";
            echo "You can now login with:<br>";
            echo "<strong>Username:</strong> admin<br>";
            echo "<strong>Password:</strong> admin123<br>";
        } else {
            echo "❌ Failed to update password: " . $conn->error . "<br>";
        }
        $stmt->close();
    }
    
} else {
    echo "❌ Admin user not found in database<br>";
    
    echo "<h3>2. Creating Admin User:</h3>";
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $full_name = 'System Administrator';
    $email = 'admin@hcstore.com';
    $role = 'admin';
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $password, $full_name, $email, $role);
    
    if ($stmt->execute()) {
        echo "✅ Admin user created successfully!<br>";
        echo "Login credentials:<br>";
        echo "<strong>Username:</strong> admin<br>";
        echo "<strong>Password:</strong> admin123<br>";
    } else {
        echo "❌ Failed to create admin user: " . $conn->error . "<br>";
    }
    $stmt->close();
}

echo "<h3>4. All Users in Database:</h3>";
$all_users = $conn->query("SELECT id, username, full_name, role, is_active FROM users");
if ($all_users && $all_users->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Active</th></tr>";
    while ($user = $all_users->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['username'] . "</td>";
        echo "<td>" . $user['full_name'] . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td>" . ($user['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No users found in database<br>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "1. Try logging in again with username: <strong>admin</strong> and password: <strong>admin123</strong><br>";
echo "2. <a href='index.php'>Go to Login Page</a><br>";
echo "3. If still having issues, clear your browser cache and cookies<br>";

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #1e40af; }
h3 { color: #374151; margin-top: 20px; }
hr { margin: 20px 0; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f3f4f6; }
a { color: #1e40af; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
