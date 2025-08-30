<?php
require_once '../includes/auth.php';
require_once '../includes/language.php';
requireAdmin();

$page_title = __('reports_analytics');
include '../includes/header.php';

// Get date range from URL parameters or set defaults
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today

// Validate dates
if (!DateTime::createFromFormat('Y-m-d', $start_date)) {
    $start_date = date('Y-m-01');
}
if (!DateTime::createFromFormat('Y-m-d', $end_date)) {
    $end_date = date('Y-m-d');
}

// Get report statistics
$stats = [];

// Stock Movements Summary
$movements_query = "
    SELECT 
        movement_type,
        COUNT(*) as count,
        SUM(ABS(quantity_change)) as total_quantity
    FROM stock_movements 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY movement_type
";
$stmt = $conn->prepare($movements_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$movements_result = $stmt->get_result();

$movements_summary = [];
while ($row = $movements_result->fetch_assoc()) {
    $movements_summary[$row['movement_type']] = $row;
}
$stmt->close();

// Low Stock Items
$low_stock_query = "SELECT COUNT(*) as count FROM products WHERE quantity <= min_stock_level";
$low_stock_count = $conn->query($low_stock_query)->fetch_assoc()['count'];

// Out of Stock Items
$out_of_stock_query = "SELECT COUNT(*) as count FROM products WHERE quantity = 0";
$out_of_stock_count = $conn->query($out_of_stock_query)->fetch_assoc()['count'];

// Most Active Users
$active_users_query = "
    SELECT 
        u.full_name,
        u.role,
        COUNT(sm.id) as movement_count
    FROM users u
    LEFT JOIN stock_movements sm ON u.id = sm.user_id 
        AND DATE(sm.created_at) BETWEEN ? AND ?
    WHERE u.is_active = 1
    GROUP BY u.id, u.full_name, u.role
    ORDER BY movement_count DESC
    LIMIT 5
";
$stmt = $conn->prepare($active_users_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$active_users = $stmt->get_result();
$stmt->close();

// Category Distribution
$category_query = "
    SELECT 
        c.name as category_name,
        COUNT(p.id) as product_count,
        SUM(p.quantity) as total_stock,
        SUM(p.price * p.quantity) as total_value
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id, c.name
    ORDER BY product_count DESC
";
$category_distribution = $conn->query($category_query);

// Recent Stock Movements
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
    ORDER BY sm.created_at DESC
    LIMIT 20
";
$stmt = $conn->prepare($recent_movements_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$recent_movements = $stmt->get_result();
$stmt->close();
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900"><?php _e('reports_analytics'); ?></h1>
                <p class="text-gray-600 mt-2"><?php _e('comprehensive_inventory_valuation'); ?></p>
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
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Quick Report Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Stock In -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-up text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Stock In</div>
                        <div class="text-2xl font-bold text-green-600">
                            <?php echo $movements_summary['in']['total_quantity'] ?? 0; ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?php echo $movements_summary['in']['count'] ?? 0; ?> movements
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
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-down text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Stock Out</div>
                        <div class="text-2xl font-bold text-red-600">
                            <?php echo $movements_summary['out']['total_quantity'] ?? 0; ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?php echo $movements_summary['out']['count'] ?? 0; ?> movements
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Low Stock</div>
                        <div class="text-2xl font-bold text-yellow-600"><?php echo $low_stock_count; ?></div>
                        <div class="text-xs text-gray-500">items need restocking</div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3">
                <a href="low-stock.php" class="text-sm text-yellow-600 hover:text-yellow-800">View details →</a>
            </div>
        </div>

        <!-- Out of Stock -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-times-circle text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Out of Stock</div>
                        <div class="text-2xl font-bold text-red-700"><?php echo $out_of_stock_count; ?></div>
                        <div class="text-xs text-gray-500">items unavailable</div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3">
                <a href="out-of-stock.php" class="text-sm text-red-600 hover:text-red-800">View details →</a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Most Active Users -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-users text-blue-500 mr-2"></i>
                    Most Active Users (<?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j', strtotime($end_date)); ?>)
                </h2>
            </div>
            <div class="p-6">
                <?php if ($active_users->num_rows > 0): ?>
                    <div class="space-y-4">
                        <?php while ($user = $active_users->fetch_assoc()): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-gray-600 text-sm"></i>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo ucfirst($user['role']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-sm font-semibold text-blue-600">
                                    <?php echo $user['movement_count']; ?> movements
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-user-slash text-gray-400 text-3xl mb-3"></i>
                        <p>No user activity in selected period</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Category Distribution -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-chart-pie text-green-500 mr-2"></i>
                    Category Distribution
                </h2>
            </div>
            <div class="overflow-x-auto">
                <?php if ($category_distribution->num_rows > 0): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Products</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($category = $category_distribution->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $category['product_count']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $category['total_stock'] ?? 0; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        LKR <?php echo number_format($category['total_value'] ?? 0, 2); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-tags text-gray-400 text-3xl mb-3"></i>
                        <p>No categories found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Stock Movements -->
    <div class="bg-white shadow-lg rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-history text-purple-500 mr-2"></i>
                    Recent Stock Movements (<?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j', strtotime($end_date)); ?>)
                </h2>
                <a href="movements.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                   class="text-blue-600 hover:text-blue-800 text-sm">
                    View all movements →
                </a>
            </div>
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($movement['user_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($movement['reason']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="p-6 text-center text-gray-500">
                    <i class="fas fa-history text-gray-400 text-3xl mb-3"></i>
                    <p>No stock movements found in selected period</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Report Links -->
    <div class="mt-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Detailed Reports</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="movements.php" class="bg-blue-500 hover:bg-blue-600 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-exchange-alt mr-3"></i>
                <span>Stock Movements</span>
            </a>
            <a href="low-stock.php" class="bg-yellow-500 hover:bg-yellow-600 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-exclamation-triangle mr-3"></i>
                <span>Low Stock Report</span>
            </a>
            <a href="out-of-stock.php" class="bg-red-500 hover:bg-red-600 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-times-circle mr-3"></i>
                <span>Out of Stock</span>
            </a>
            <a href="inventory-value.php" class="bg-green-500 hover:bg-green-600 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-calculator mr-3"></i>
                <span>Inventory Value</span>
            </a>
            <a href="user-activity.php" class="bg-purple-500 hover:bg-purple-600 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-user-chart mr-3"></i>
                <span>User Activity</span>
            </a>
            <a href="export.php" class="bg-gray-600 hover:bg-gray-700 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-download mr-3"></i>
                <span>Export Data</span>
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
