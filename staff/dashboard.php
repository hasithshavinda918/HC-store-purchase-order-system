<?php
require_once '../includes/auth.php';
requireLogin();

// Ensure only staff can access this page (admin should use admin dashboard)
if (isAdmin()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$page_title = 'Staff Dashboard';
include '../includes/header.php';

// Get basic statistics for staff
$stats = [];

// Total products
$result = $conn->query("SELECT COUNT(*) as count FROM products");
$stats['total_products'] = $result->fetch_assoc()['count'];

// Low stock items
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= min_stock_level");
$stats['low_stock'] = $result->fetch_assoc()['count'];

// Out of stock items
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity = 0");
$stats['out_of_stock'] = $result->fetch_assoc()['count'];

// Low stock products for staff view
$low_stock_products = $conn->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.quantity <= p.min_stock_level 
    ORDER BY p.quantity ASC 
    LIMIT 15
");

// Recent stock movements by this user
$user_id = $_SESSION['user_id'];
$my_recent_movements = $conn->query("
    SELECT sm.*, p.name as product_name 
    FROM stock_movements sm 
    JOIN products p ON sm.product_id = p.id 
    WHERE sm.user_id = $user_id 
    ORDER BY sm.created_at DESC 
    LIMIT 10
");
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Staff Dashboard</h1>
        <p class="text-gray-600 mt-2">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Manage your daily stock operations here.</p>
    </div>

    <!-- Quick Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
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
            <div class="bg-gray-50 px-6 py-3">
                <a href="../products/view.php" class="text-sm text-blue-600 hover:text-blue-800">View all products →</a>
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
                <a href="#low-stock" class="text-sm text-yellow-600 hover:text-yellow-800">View details →</a>
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
                <a href="../products/stock_update.php" class="text-sm text-red-600 hover:text-red-800">Update stock →</a>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="../products/view.php" class="bg-blue-500 hover:bg-blue-600 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-list mr-3"></i>
                <span>View All Products</span>
            </a>
            <a href="../products/stock_update.php" class="bg-green-500 hover:bg-green-600 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-edit mr-3"></i>
                <span>Update Stock</span>
            </a>
            <a href="../reports/basic.php" class="bg-purple-500 hover:bg-purple-600 text-white p-4 rounded-lg transition duration-200 flex items-center">
                <i class="fas fa-chart-line mr-3"></i>
                <span>Stock Report</span>
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Low Stock Alert -->
        <div class="bg-white shadow-lg rounded-lg" id="low-stock">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                    Low Stock Alert
                </h2>
                <p class="text-sm text-gray-600 mt-1">Items that need immediate attention</p>
            </div>
            <div class="overflow-x-auto">
                <?php if ($low_stock_products->num_rows > 0): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Min Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($product = $low_stock_products->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($product['sku']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php echo $product['quantity'] <= 0 ? 'text-red-600' : 'text-yellow-600'; ?>">
                                        <?php echo $product['quantity']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $product['min_stock_level']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="../products/stock_update.php?id=<?php echo $product['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800">Update</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-check-circle text-green-500 text-3xl mb-3"></i>
                        <p>All products are well stocked!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Recent Activity -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-user-clock text-blue-500 mr-2"></i>
                    My Recent Activity
                </h2>
                <p class="text-sm text-gray-600 mt-1">Your recent stock updates</p>
            </div>
            <div class="overflow-x-auto">
                <?php if ($my_recent_movements->num_rows > 0): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Change</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">When</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($movement = $my_recent_movements->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($movement['product_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="<?php echo $movement['quantity_change'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $movement['quantity_change'] > 0 ? '+' : ''; ?><?php echo $movement['quantity_change']; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            <?php 
                                            switch($movement['movement_type']) {
                                                case 'in': echo 'bg-green-100 text-green-800'; break;
                                                case 'out': echo 'bg-red-100 text-red-800'; break;
                                                case 'adjustment': echo 'bg-blue-100 text-blue-800'; break;
                                            }
                                            ?>">
                                            <?php echo ucfirst($movement['movement_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, H:i', strtotime($movement['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-history text-gray-400 text-3xl mb-3"></i>
                        <p>No recent activity found.</p>
                        <p class="text-sm mt-2">Start updating stock to see your activity here!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
