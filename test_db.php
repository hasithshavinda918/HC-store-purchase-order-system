<?php
// Database Connection Test for HC Store Stock Management
echo "<h2>HC Store Database Connection Test</h2>";
echo "<hr>";

// Test 1: Check if mysqli extension is loaded
echo "<h3>1. Checking MySQL Extension:</h3>";
if (extension_loaded('mysqli')) {
    echo "✅ MySQLi extension is loaded<br>";
} else {
    echo "❌ MySQLi extension is NOT loaded<br>";
    echo "Please install/enable MySQLi extension in PHP<br>";
}

// Test 2: Try to connect to MySQL server
echo "<h3>2. Testing MySQL Server Connection:</h3>";
$host = 'localhost';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    echo "❌ Connection to MySQL server failed: " . $conn->connect_error . "<br>";
    echo "<strong>Possible solutions:</strong><br>";
    echo "- Make sure XAMPP Apache and MySQL services are running<br>";
    echo "- Check if MySQL is running on port 3306<br>";
    echo "- Verify MySQL credentials (default: root with no password)<br>";
} else {
    echo "✅ Successfully connected to MySQL server<br>";
    echo "Server info: " . $conn->server_info . "<br>";
}

// Test 3: Check if database exists
echo "<h3>3. Checking HC Store Database:</h3>";
$database = 'hc_store_stock';

if ($conn->connect_error) {
    echo "❌ Cannot check database - no server connection<br>";
} else {
    $result = $conn->query("SHOW DATABASES LIKE '$database'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Database '$database' exists<br>";
        
        // Test connection to the specific database
        $conn->select_db($database);
        if ($conn->error) {
            echo "❌ Cannot select database: " . $conn->error . "<br>";
        } else {
            echo "✅ Successfully connected to '$database' database<br>";
            
            // Test 4: Check if tables exist
            echo "<h3>4. Checking Database Tables:</h3>";
            $tables = ['users', 'categories', 'products', 'stock_movements'];
            
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    echo "✅ Table '$table' exists<br>";
                    
                    // Count records
                    $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
                    if ($count_result) {
                        $count = $count_result->fetch_assoc()['count'];
                        echo "&nbsp;&nbsp;&nbsp;📊 Records: $count<br>";
                    }
                } else {
                    echo "❌ Table '$table' does NOT exist<br>";
                }
            }
        }
    } else {
        echo "❌ Database '$database' does NOT exist<br>";
        echo "<strong>Solution:</strong> You need to create the database and tables<br>";
        echo "1. Open phpMyAdmin (http://localhost/phpmyadmin)<br>";
        echo "2. Run the SQL code from 'database_setup.sql'<br>";
    }
}

// Test 5: Test the application's db_connection.php file
echo "<h3>5. Testing Application Database Connection:</h3>";
try {
    include 'includes/db_connection.php';
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        echo "✅ Application database connection file works correctly<br>";
        
        // Test a simple query
        $test_query = $conn->query("SELECT 1 as test");
        if ($test_query) {
            echo "✅ Database queries are working<br>";
        } else {
            echo "❌ Database queries failed: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ Application database connection failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Error loading database connection: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Summary:</h3>";
echo "If you see any ❌ marks above, please:<br>";
echo "1. Make sure XAMPP is running (Apache + MySQL)<br>";
echo "2. Open phpMyAdmin and run the database_setup.sql file<br>";
echo "3. Refresh this page to test again<br><br>";

echo "<strong>Quick Links:</strong><br>";
echo "<a href='http://localhost/phpmyadmin' target='_blank'>📊 Open phpMyAdmin</a><br>";
echo "<a href='index.php'>🔐 Go to Login Page</a><br>";
echo "<a href='database_setup.sql' target='_blank'>📄 View SQL Setup File</a><br>";

if ($conn && !$conn->connect_error) {
    $conn->close();
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #1e40af; }
h3 { color: #374151; margin-top: 20px; }
hr { margin: 20px 0; }
a { color: #1e40af; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
