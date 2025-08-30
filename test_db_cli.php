<?php
// Simple command line database test
echo "Testing MySQL connection...\n";

try {
    $conn = new mysqli('localhost', 'root', '');
    
    if ($conn->connect_error) {
        echo "❌ Connection failed: " . $conn->connect_error . "\n";
        exit(1);
    }
    
    echo "✅ MySQL connection successful!\n";
    echo "Server version: " . $conn->server_info . "\n";
    
    // Check if database exists
    $result = $conn->query("SHOW DATABASES LIKE 'hc_store_stock'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Database 'hc_store_stock' exists\n";
        
        // Select the database
        $conn->select_db('hc_store_stock');
        
        // Check tables
        $tables = ['users', 'categories', 'products', 'stock_movements'];
        echo "\nChecking tables:\n";
        
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                $count_result = $conn->query("SELECT COUNT(*) as count FROM $table");
                $count = $count_result->fetch_assoc()['count'];
                echo "✅ Table '$table' exists ($count records)\n";
            } else {
                echo "❌ Table '$table' missing\n";
            }
        }
    } else {
        echo "❌ Database 'hc_store_stock' does not exist\n";
        echo "Please run the database_setup.sql file in phpMyAdmin\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
