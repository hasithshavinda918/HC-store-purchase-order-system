<?php
require_once '../includes/auth.php';
requireLogin(); // Both admin and staff can view basic reports

$page_title = 'Stock Reports';
include '../includes/header.php';

// Get date range from URL parameters or set defaults
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')); // Last 30 days
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Validate dates
if (!DateTime::createFromFormat('Y-m-d', $start_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
}
if (!DateTime::createFromFormat('Y-m-d', $end_date)) {
    $end_date = date('Y-m-d');
}

// Get current user's role to show appropriate data
$user_id = $_SESSION['user_id'];
$is_admin = isAdmin();

// Basic statistics
$stats = [];

// Total products
$stats['total_products'] = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];

// Low stock items
$stats['low_stock'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= min_stock_level")->fetch_assoc()['count'];

// Out of stock items
$stats['out_of_stock'] = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity = 0")->fetch_assoc()['count'];

// Stock movements in date range
$movements_query = "SELECT COUNT(*) as count FROM stock_movements WHERE DATE(created_at) BETWEEN ? AND ?";
if (!$is_admin) {
    $movements_query = "SELECT COUNT(*) as count FROM stock_movements WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?";
}

$stmt = $conn->prepare($movements_query);
if ($is_admin) {
    $stmt->bind_param("ss", $start_date, $end_date);
} else {
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
}
$stmt->execute();
$stats['movements_period'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Stock movements by type in date range
$movements_by_type_query = "
    SELECT 
        movement_type,
        COUNT(*) as count,
        SUM(ABS(quantity_change)) as total_quantity
    FROM stock_movements 
    WHERE DATE(created_at) BETWEEN ? AND ?
";

if (!$is_admin) {
    $movements_by_type_query .= " AND user_id = ?";
}

$movements_by_type_query .= " GROUP BY movement_type";

$stmt = $conn->prepare($movements_by_type_query);
if ($is_admin) {
    $stmt->bind_param("ss", $start_date, $end_date);
} else {
    $stmt->bind_param("ssi", $start_date, $end_date, $user_id);
}
$stmt->execute();
$movements_by_type = $stmt->get_result();
$movement_summary = [];
while ($row = $movements_by_type->fetch_assoc()) {
    $movement_summary[$row['movement_type']] = $row;
}
$stmt->close();

// Recent stock movements
$recent_movements_query = "
    SELECT 
        sm.*,
        p.name as product_name,
        p.sku as product_sku,
        u.full_name as user_name,
        c.name as category_name
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    JOIN users u ON sm.user_id = u.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE DATE(sm.created_at) BETWEEN ? AND ?
";

if (!$is_admin) {
    $recent_movements_query .= " AND sm.user_id = ?";
}

$recent_movements_query .= " ORDER BY sm.created_at DESC LIMIT 20";

$stmt = $conn->prepare($recent_movements_query);
if ($is_admin) {
    $stmt->bind_param("ss", $start_date, $end_date);
} else {
    $stmt->bind_param("ssi", $start_date, $end_date, $user_id);
}
$stmt->execute();
$recent_movements = $stmt->get_result();
$stmt->close();

// Low stock products
$low_stock_products = $conn->query("
    SELECT p.*, c.name as category_name,
           (p.min_stock_level - p.quantity) as shortage
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.quantity <= p.min_stock_level 
    ORDER BY p.quantity ASC 
    LIMIT 15
");

// Most updated products (if staff, only their updates)
$most_updated_query = "
    SELECT 
        p.name as product_name,
        p.sku as product_sku,
        COUNT(sm.id) as update_count,
        MAX(sm.created_at) as last_update
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    WHERE DATE(sm.created_at) BETWEEN ? AND ?
";

if (!$is_admin) {
    $most_updated_query .= " AND sm.user_id = ?";
}

$most_updated_query .= " GROUP BY sm.product_id ORDER BY update_count DESC LIMIT 10";

$stmt = $conn->prepare($most_updated_query);
if ($is_admin) {
    $stmt->bind_param("ss", $start_date, $end_date);
} else {
    $stmt->bind_param("ssi", $start_date, $end_date, $user_id);
}
$stmt->execute();
$most_updated_products = $stmt->get_result();
$stmt->close();
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Stock Reports</h1>
                <p class="text-gray-600 mt-2">
                    <?php echo $is_admin ? 'System-wide' : 'Your personal'; ?> inventory reports and statistics
                </p>
            </div>
            
            <!-- Date Range Filter -->
            <div class="bg-white rounded-lg shadow p-4">
                <form method="GET" class="flex items-center space-x-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700">From</label>
                        <input type="date" 
                               id="start_date" 
                               name="start_date" 
                               value="<?php echo $start_date; ?>"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700">To</label>
                        <input type="date" 
                               id="end_date" 
                               name="end_date" 
                               value="<?php echo $end_date; ?>"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-filter mr-2"></i>Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Products -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-box text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Total Products</div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_products']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Movements This Period -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-exchange-alt text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">
                            <?php echo $is_admin ? 'Total' : 'Your'; ?> Movements
                        </div>
                        <div class="text-2xl font-bold text-green-600"><?php echo $stats['movements_period']; ?></div>
                        <div class="text-xs text-gray-500"><?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j', strtotime($end_date)); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Items -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Low Stock Items</div>
                        <div class="text-2xl font-bold text-yellow-600"><?php echo $stats['low_stock']; ?></div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3">
                <a href="#low-stock-section" class="text-sm text-yellow-600 hover:text-yellow-800">View details →</a>
            </div>
        </div>

        <!-- Out of Stock -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-times-circle text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Out of Stock</div>
                        <div class="text-2xl font-bold text-red-600"><?php echo $stats['out_of_stock']; ?></div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3">
                <a href="../products/stock_update.php?stock_status=out_of_stock" class="text-sm text-red-600 hover:text-red-800">Update stock →</a>
            </div>
        </div>
    </div>

    <!-- Movement Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Stock In -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-up text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Stock Added</div>
                        <div class="text-2xl font-bold text-green-700">
                            <?php echo $movement_summary['in']['total_quantity'] ?? 0; ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?php echo $movement_summary['in']['count'] ?? 0; ?> movements
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Out -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-down text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Stock Removed</div>
                        <div class="text-2xl font-bold text-red-700">
                            <?php echo $movement_summary['out']['total_quantity'] ?? 0; ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?php echo $movement_summary['out']['count'] ?? 0; ?> movements
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Change -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <?php 
                        $net_change = ($movement_summary['in']['total_quantity'] ?? 0) - ($movement_summary['out']['total_quantity'] ?? 0);
                        $net_color = $net_change >= 0 ? 'bg-blue-600' : 'bg-orange-600';
                        $net_icon = $net_change >= 0 ? 'fa-plus' : 'fa-minus';
                        ?>
                        <div class="w-8 h-8 <?php echo $net_color; ?> rounded-full flex items-center justify-center">
                            <i class="fas <?php echo $net_icon; ?> text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Net Change</div>
                        <div class="text-2xl font-bold <?php echo $net_change >= 0 ? 'text-blue-700' : 'text-orange-700'; ?>">
                            <?php echo $net_change >= 0 ? '+' : ''; ?><?php echo $net_change; ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            Total movements: <?php echo $stats['movements_period']; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Low Stock Products -->
        <div class="bg-white shadow-lg rounded-lg" id="low-stock-section">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                    Low Stock Alert
                </h2>
            </div>
            <div class="overflow-x-auto">
                <?php if ($low_stock_products->num_rows > 0): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Min</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Need</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($product = $low_stock_products->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($product['sku']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold <?php echo $product['quantity'] == 0 ? 'text-red-600' : 'text-yellow-600'; ?>">
                                        <?php echo $product['quantity']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $product['min_stock_level']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600">
                                        <?php echo max(0, $product['shortage']); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <div class="px-6 py-3 bg-gray-50 border-t">
                        <a href="../products/stock_update.php?stock_status=low_stock" class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-edit mr-1"></i>Update Low Stock Items →
                        </a>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-check-circle text-green-500 text-3xl mb-3"></i>
                        <p>All products are well stocked!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Most Updated Products -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-chart-line text-purple-500 mr-2"></i>
                    Most Updated Products
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    <?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j', strtotime($end_date)); ?>
                    <?php if (!$is_admin): ?>
                        (Your updates only)
                    <?php endif; ?>
                </p>
            </div>
            <div class="p-6">
                <?php if ($most_updated_products->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while ($product = $most_updated_products->fetch_assoc()): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($product['product_sku']); ?> • 
                                        Last: <?php echo date('M j, H:i', strtotime($product['last_update'])); ?>
                                    </div>
                                </div>
                                <div class="text-sm font-semibold text-purple-600">
                                    <?php echo $product['update_count']; ?> updates
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-chart-line text-gray-400 text-3xl mb-3"></i>
                        <p>No updates in selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white shadow-lg rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-history text-blue-500 mr-2"></i>
                Recent Stock Movements
            </h2>
            <p class="text-sm text-gray-600 mt-1">
                <?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j', strtotime($end_date)); ?>
                <?php if (!$is_admin): ?>
                    (Your activity only)
                <?php endif; ?>
            </p>
        </div>
        <div class="overflow-x-auto">
            <?php if ($recent_movements->num_rows > 0): ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Change</th>
                            <?php if ($is_admin): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <?php endif; ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($movement = $recent_movements->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, H:i', strtotime($movement['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($movement['product_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($movement['product_sku']); ?>
                                        <?php if ($movement['category_name']): ?>
                                            • <?php echo htmlspecialchars($movement['category_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        <?php 
                                        switch($movement['movement_type']) {
                                            case 'in': echo 'bg-green-100 text-green-800'; break;
                                            case 'out': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-blue-100 text-blue-800'; break;
                                        }
                                        ?>">
                                        <i class="fas <?php echo $movement['movement_type'] === 'in' ? 'fa-arrow-up' : 'fa-arrow-down'; ?> mr-1"></i>
                                        <?php echo ucfirst($movement['movement_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php echo $movement['quantity_change'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $movement['quantity_change'] > 0 ? '+' : ''; ?><?php echo $movement['quantity_change']; ?>
                                    <span class="text-gray-500 font-normal text-xs">
                                        (<?php echo $movement['previous_quantity']; ?> → <?php echo $movement['new_quantity']; ?>)
                                    </span>
                                </td>
                                <?php if ($is_admin): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($movement['user_name']); ?>
                                </td>
                                <?php endif; ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($movement['reason']); ?>
                                    <?php if (!empty($movement['notes'])): ?>
                                        <div class="text-xs text-gray-400 mt-1">
                                            <?php echo htmlspecialchars($movement['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="p-6 text-center text-gray-500">
                    <i class="fas fa-history text-gray-400 text-3xl mb-3"></i>
                    <p>No stock movements found in selected period</p>
                    <p class="text-sm mt-2">Try adjusting your date range or start updating stock!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mt-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="../products/view.php" class="bg-blue-500 hover:bg-blue-600 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-list mr-3"></i>
                <span>View All Products</span>
            </a>
            <a href="../products/stock_update.php" class="bg-green-500 hover:bg-green-600 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-edit mr-3"></i>
                <span>Update Stock</span>
            </a>
            <a href="../products/stock_update.php?stock_status=low_stock" class="bg-yellow-500 hover:bg-yellow-600 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-exclamation-triangle mr-3"></i>
                <span>Fix Low Stock</span>
            </a>
            <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-print mr-3"></i>
                <span>Print Report</span>
            </button>
        </div>
    </div>

    <!-- Generated timestamp -->
    <div class="mt-8 text-center text-sm text-gray-500">
        Report generated on <?php echo date('F j, Y \a\t g:i A'); ?>
        <?php if (!$is_admin): ?>
            • Showing your activity only
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    body {
        font-size: 12px;
    }
    
    .shadow-lg {
        box-shadow: none !important;
        border: 1px solid #e5e7eb;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
