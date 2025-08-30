<?php
require_once '../includes/auth.php';
require_once '../includes/language.php';
requireAdmin();

// Determine export type and data
$export_type = $_GET['export'] ?? 'csv';
$report_type = $_GET['report'] ?? 'inventory';

// Set headers for download
$filename = '';
$content_type = 'text/csv';

switch($report_type) {
    case 'movements':
        $filename = 'stock-movements-' . date('Y-m-d');
        break;
    case 'inventory':
        $filename = 'inventory-report-' . date('Y-m-d');
        break;
    case 'low-stock':
        $filename = 'low-stock-report-' . date('Y-m-d');
        break;
    case 'out-of-stock':
        $filename = 'out-of-stock-report-' . date('Y-m-d');
        break;
    case 'user-activity':
        $filename = 'user-activity-report-' . date('Y-m-d');
        break;
    case 'inventory-value':
        $filename = 'inventory-value-report-' . date('Y-m-d');
        break;
    default:
        $filename = 'report-' . date('Y-m-d');
}

$filename .= '.' . $export_type;

// Set download headers
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Export based on report type
switch($report_type) {
    case 'movements':
        exportStockMovements();
        break;
    case 'inventory':
        exportInventory();
        break;
    case 'low-stock':
        exportLowStock();
        break;
    case 'out-of-stock':
        exportOutOfStock();
        break;
    case 'user-activity':
        exportUserActivity();
        break;
    case 'inventory-value':
        exportInventoryValue();
        break;
    default:
        echo "Invalid report type";
        exit;
}

function exportStockMovements() {
    global $conn;
    
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $product_id = $_GET['product_id'] ?? '';
    $movement_type = $_GET['movement_type'] ?? '';
    $user_id = $_GET['user_id'] ?? '';

    // Build query
    $where_conditions = ["DATE(sm.created_at) BETWEEN ? AND ?"];
    $params = [$start_date, $end_date];
    $param_types = "ss";

    if ($product_id) {
        $where_conditions[] = "sm.product_id = ?";
        $params[] = $product_id;
        $param_types .= "i";
    }

    if ($movement_type) {
        $where_conditions[] = "sm.movement_type = ?";
        $params[] = $movement_type;
        $param_types .= "s";
    }

    if ($user_id) {
        $where_conditions[] = "sm.user_id = ?";
        $params[] = $user_id;
        $param_types .= "i";
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    $query = "
        SELECT 
            sm.created_at as date_time,
            p.name as product_name,
            p.sku,
            c.name as category,
            sm.movement_type,
            sm.quantity_change,
            sm.previous_quantity,
            sm.new_quantity,
            u.full_name as user_name,
            sm.reason
        FROM stock_movements sm
        JOIN products p ON sm.product_id = p.id
        JOIN users u ON sm.user_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        $where_clause
        ORDER BY sm.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Output CSV headers
    echo "Date Time,Product,SKU,Category,Movement Type,Quantity Change,Previous Quantity,New Quantity,User,Reason\n";

    while ($row = $result->fetch_assoc()) {
        echo '"' . $row['date_time'] . '",';
        echo '"' . str_replace('"', '""', $row['product_name']) . '",';
        echo '"' . $row['sku'] . '",';
        echo '"' . str_replace('"', '""', $row['category'] ?: 'Uncategorized') . '",';
        echo '"' . ucfirst($row['movement_type']) . '",';
        echo '"' . $row['quantity_change'] . '",';
        echo '"' . $row['previous_quantity'] . '",';
        echo '"' . $row['new_quantity'] . '",';
        echo '"' . str_replace('"', '""', $row['user_name']) . '",';
        echo '"' . str_replace('"', '""', $row['reason'] ?: '') . '"';
        echo "\n";
    }
    
    $stmt->close();
}

function exportInventory() {
    global $conn;
    
    $query = "
        SELECT 
            p.name,
            p.sku,
            p.description,
            c.name as category,
            p.quantity,
            p.min_stock_level,
            p.price,
            (p.price * p.quantity) as total_value,
            CASE 
                WHEN p.quantity = 0 THEN 'Out of Stock'
                WHEN p.quantity <= p.min_stock_level THEN 'Low Stock'
                ELSE 'In Stock'
            END as status
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.name
    ";

    $result = $conn->query($query);

    // Output CSV headers
    echo "Product Name,SKU,Description,Category,Quantity,Min Stock Level,Unit Price,Total Value,Status\n";

    while ($row = $result->fetch_assoc()) {
        echo '"' . str_replace('"', '""', $row['name']) . '",';
        echo '"' . $row['sku'] . '",';
        echo '"' . str_replace('"', '""', $row['description'] ?: '') . '",';
        echo '"' . str_replace('"', '""', $row['category'] ?: 'Uncategorized') . '",';
        echo '"' . $row['quantity'] . '",';
        echo '"' . $row['min_stock_level'] . '",';
        echo '"' . $row['price'] . '",';
        echo '"' . $row['total_value'] . '",';
        echo '"' . $row['status'] . '"';
        echo "\n";
    }
}

function exportLowStock() {
    global $conn;
    
    $query = "
        SELECT 
            p.name,
            p.sku,
            c.name as category,
            p.quantity,
            p.min_stock_level,
            p.price,
            (p.min_stock_level - p.quantity) as needed_quantity
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.quantity <= p.min_stock_level AND p.quantity > 0
        ORDER BY (p.min_stock_level - p.quantity) DESC
    ";

    $result = $conn->query($query);

    // Output CSV headers
    echo "Product Name,SKU,Category,Current Quantity,Min Stock Level,Unit Price,Needed Quantity\n";

    while ($row = $result->fetch_assoc()) {
        echo '"' . str_replace('"', '""', $row['name']) . '",';
        echo '"' . $row['sku'] . '",';
        echo '"' . str_replace('"', '""', $row['category'] ?: 'Uncategorized') . '",';
        echo '"' . $row['quantity'] . '",';
        echo '"' . $row['min_stock_level'] . '",';
        echo '"' . $row['price'] . '",';
        echo '"' . $row['needed_quantity'] . '"';
        echo "\n";
    }
}

function exportOutOfStock() {
    global $conn;
    
    $query = "
        SELECT 
            p.name,
            p.sku,
            c.name as category,
            p.min_stock_level,
            p.price,
            COALESCE(
                (SELECT created_at 
                 FROM stock_movements sm 
                 WHERE sm.product_id = p.id 
                 ORDER BY created_at DESC 
                 LIMIT 1
                ), p.created_at
            ) as last_movement
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.quantity = 0
        ORDER BY last_movement DESC
    ";

    $result = $conn->query($query);

    // Output CSV headers
    echo "Product Name,SKU,Category,Min Stock Level,Unit Price,Last Movement\n";

    while ($row = $result->fetch_assoc()) {
        echo '"' . str_replace('"', '""', $row['name']) . '",';
        echo '"' . $row['sku'] . '",';
        echo '"' . str_replace('"', '""', $row['category'] ?: 'Uncategorized') . '",';
        echo '"' . $row['min_stock_level'] . '",';
        echo '"' . $row['price'] . '",';
        echo '"' . $row['last_movement'] . '"';
        echo "\n";
    }
}

function exportUserActivity() {
    global $conn;
    
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $user_id = $_GET['user_id'] ?? '';

    $query = "
        SELECT 
            u.full_name,
            u.username,
            u.role,
            COUNT(sm.id) as total_movements,
            COUNT(CASE WHEN sm.movement_type = 'in' THEN 1 END) as stock_in_count,
            COUNT(CASE WHEN sm.movement_type = 'out' THEN 1 END) as stock_out_count,
            COUNT(CASE WHEN sm.movement_type = 'adjustment' THEN 1 END) as adjustment_count,
            SUM(CASE WHEN sm.movement_type = 'in' THEN ABS(sm.quantity_change) ELSE 0 END) as total_stock_in,
            SUM(CASE WHEN sm.movement_type = 'out' THEN ABS(sm.quantity_change) ELSE 0 END) as total_stock_out,
            MAX(sm.created_at) as last_activity
        FROM users u
        LEFT JOIN stock_movements sm ON u.id = sm.user_id 
            AND DATE(sm.created_at) BETWEEN ? AND ?
        " . ($user_id ? "WHERE u.id = ?" : "") . "
        GROUP BY u.id, u.full_name, u.username, u.role
        ORDER BY total_movements DESC, u.full_name ASC
    ";

    $params = [$start_date, $end_date];
    $param_types = "ss";

    if ($user_id) {
        $params[] = $user_id;
        $param_types .= "i";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Output CSV headers
    echo "Full Name,Username,Role,Total Movements,Stock In Count,Stock Out Count,Adjustment Count,Total Stock In,Total Stock Out,Last Activity\n";

    while ($row = $result->fetch_assoc()) {
        echo '"' . str_replace('"', '""', $row['full_name']) . '",';
        echo '"' . $row['username'] . '",';
        echo '"' . ucfirst($row['role']) . '",';
        echo '"' . $row['total_movements'] . '",';
        echo '"' . $row['stock_in_count'] . '",';
        echo '"' . $row['stock_out_count'] . '",';
        echo '"' . $row['adjustment_count'] . '",';
        echo '"' . $row['total_stock_in'] . '",';
        echo '"' . $row['total_stock_out'] . '",';
        echo '"' . ($row['last_activity'] ?: '') . '"';
        echo "\n";
    }
    
    $stmt->close();
}

function exportInventoryValue() {
    global $conn;
    
    $query = "
        SELECT 
            p.name,
            p.sku,
            c.name as category,
            p.quantity,
            p.price,
            (p.price * p.quantity) as total_value,
            CASE 
                WHEN p.quantity = 0 THEN 'Out of Stock'
                WHEN p.quantity <= p.min_stock_level THEN 'Low Stock'
                ELSE 'In Stock'
            END as stock_status
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY total_value DESC
    ";

    $result = $conn->query($query);

    // Output CSV headers
    echo "Product Name,SKU,Category,Quantity,Unit Price,Total Value,Stock Status\n";

    while ($row = $result->fetch_assoc()) {
        echo '"' . str_replace('"', '""', $row['name']) . '",';
        echo '"' . $row['sku'] . '",';
        echo '"' . str_replace('"', '""', $row['category'] ?: 'Uncategorized') . '",';
        echo '"' . $row['quantity'] . '",';
        echo '"' . $row['price'] . '",';
        echo '"' . $row['total_value'] . '",';
        echo '"' . $row['stock_status'] . '"';
        echo "\n";
    }
}

exit;
?>
