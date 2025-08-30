<?php
require_once '../includes/auth.php';
require_once '../includes/language.php';
requireAdmin();

$page_title = __('admin_dashboard');
include '../includes/header.php';

// Get dashboard statistics
$stats = [];

// Total products
$result = $conn->query("SELECT COUNT(*) as count FROM products");
$stats['total_products'] = $result->fetch_assoc()['count'];

// Low stock items
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= 5");
$stats['low_stock'] = $result->fetch_assoc()['count'];

// Total categories
$result = $conn->query("SELECT COUNT(*) as count FROM categories");
$stats['total_categories'] = $result->fetch_assoc()['count'];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Total suppliers
$result = $conn->query("SELECT COUNT(*) as count FROM suppliers WHERE is_active = 1");
$stats['total_suppliers'] = $result->fetch_assoc()['count'];

// Purchase order statistics
$result = $conn->query("SELECT COUNT(*) as count FROM purchase_orders");
$stats['total_pos'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status IN ('sent', 'confirmed')");
$stats['pending_pos'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM purchase_orders WHERE status = 'draft'");
$stats['draft_pos'] = $result->fetch_assoc()['count'];

// Recent stock movements
$recent_movements = $conn->query("
    SELECT sm.*, p.name as product_name, u.username as user_name 
    FROM stock_movements sm 
    JOIN products p ON sm.product_id = p.id 
    JOIN users u ON sm.user_id = u.id 
    ORDER BY sm.created_at DESC 
    LIMIT 10
");

// Low stock products
$low_stock_products = $conn->query("
    SELECT * FROM products 
    WHERE quantity <= 5
    ORDER BY quantity ASC 
    LIMIT 10
");

// Recent purchase orders
$recent_pos = $conn->query("
    SELECT po.po_number, po.status, po.total_amount, s.name as supplier_name, po.created_at
    FROM purchase_orders po 
    LEFT JOIN suppliers s ON po.supplier_id = s.id 
    ORDER BY po.created_at DESC 
    LIMIT 8
");
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900"><?php _e('admin_dashboard'); ?></h1>
        <p class="text-gray-600 mt-2"><?php echo __('dashboard_welcome', ['name' => htmlspecialchars($_SESSION['full_name'])]); ?></p>
    </div>

    <!-- Statistics Cards -->
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
            <div class="bg-gray-50 px-6 py-3">
                <a href="../products/index.php" class="text-sm text-blue-600 hover:text-blue-800">View all products →</a>
            </div>
        </div>

        <!-- Total Suppliers -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-truck text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Active Suppliers</div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_suppliers']; ?></div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3">
                <a href="../suppliers/index.php" class="text-sm text-green-600 hover:text-green-800">Manage suppliers →</a>
            </div>
        </div>

        <!-- Purchase Orders -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-file-invoice text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Purchase Orders</div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo $stats['total_pos']; ?></div>
                        <div class="text-xs text-gray-500">
                            <?php echo $stats['pending_pos']; ?> pending, <?php echo $stats['draft_pos']; ?> drafts
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3">
                <a href="../purchase_orders/index.php" class="text-sm text-purple-600 hover:text-purple-800">View all orders →</a>
            </div>
        </div>

        <!-- Low Stock Items -->
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Low Stock Items</div>
                        <div class="text-2xl font-bold text-red-600"><?php echo $stats['low_stock']; ?></div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3">
                <a href="#low-stock" class="text-sm text-red-600 hover:text-red-800">View low stock →</a>
            </div>
        </div>
    </div>

    <!-- Secondary Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Categories -->
        <div class="bg-white p-4 rounded-lg shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Categories</p>
                    <p class="text-xl font-bold text-gray-900"><?php echo $stats['total_categories']; ?></p>
                </div>
                <i class="fas fa-tags text-2xl text-gray-400"></i>
            </div>
        </div>

        <!-- Users -->
        <div class="bg-white p-4 rounded-lg shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Active Users</p>
                    <p class="text-xl font-bold text-gray-900"><?php echo $stats['total_users']; ?></p>
                </div>
                <i class="fas fa-users text-2xl text-gray-400"></i>
            </div>
        </div>

        <!-- Recent PO Value -->
        <div class="bg-white p-4 rounded-lg shadow">
            <?php 
            $recent_po_value = $conn->query("SELECT SUM(total_amount) as total FROM purchase_orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['total'] ?? 0;
            ?>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">PO Value (30 days)</p>
                    <p class="text-xl font-bold text-gray-900">LKR <?php echo number_format($recent_po_value, 0); ?></p>
                </div>
                <i class="fas fa-calculator text-2xl text-gray-400"></i>
            </div>
        </div>

        <!-- Stock Value -->
        <div class="bg-white p-4 rounded-lg shadow">
            <?php 
            $stock_value = $conn->query("SELECT SUM(quantity * price) as total FROM products")->fetch_assoc()['total'] ?? 0;
            ?>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">Total Stock Value</p>
                    <p class="text-xl font-bold text-gray-900">LKR <?php echo number_format($stock_value, 0); ?></p>
                </div>
                <i class="fas fa-warehouse text-2xl text-gray-400"></i>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Recent Purchase Orders -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-file-invoice text-purple-500 mr-2"></i>
                    Recent Purchase Orders
                </h2>
            </div>
            <div class="overflow-x-auto">
                <?php if ($recent_pos->num_rows > 0): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($po = $recent_pos->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-blue-600">
                                        <a href="../purchase_orders/view.php?id=<?php echo $po['po_number']; ?>" class="hover:text-blue-800">
                                            <?php echo htmlspecialchars($po['po_number']); ?>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo htmlspecialchars(substr($po['supplier_name'], 0, 15)); ?>
                                        <?php echo strlen($po['supplier_name']) > 15 ? '...' : ''; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch($po['status']) {
                                                case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                                                case 'sent': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'confirmed': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'received': echo 'bg-green-100 text-green-800'; break;
                                                case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($po['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        LKR <?php echo number_format($po['total_amount'], 0); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <div class="px-4 py-3 bg-gray-50 text-center">
                        <a href="../purchase_orders/index.php" class="text-sm text-purple-600 hover:text-purple-800">
                            View all purchase orders →
                        </a>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-file-invoice text-gray-400 text-3xl mb-3"></i>
                        <p>No purchase orders yet.</p>
                        <a href="../purchase_orders/create.php" class="mt-2 inline-block text-purple-600 hover:text-purple-800">
                            Create your first PO
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Low Stock Products -->
        <div class="bg-white shadow-lg rounded-lg" id="low-stock">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    Low Stock Alert
                </h2>
            </div>
            <div class="overflow-x-auto">
                <?php if ($low_stock_products->num_rows > 0): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($product = $low_stock_products->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo htmlspecialchars(substr($product['name'], 0, 20)); ?>
                                        <?php echo strlen($product['name']) > 20 ? '...' : ''; ?>
                                        <?php if ($product['sku']): ?>
                                        <br><small class="text-gray-500"><?php echo htmlspecialchars($product['sku']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-semibold <?php echo $product['quantity'] <= 0 ? 'text-red-600' : 'text-orange-600'; ?>">
                                        <?php echo $product['quantity']; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <a href="../purchase_orders/create.php?product=<?php echo $product['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-xs">
                                            Order Now
                                        </a>
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

        <!-- Recent Stock Movements -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-history text-blue-500 mr-2"></i>
                    Recent Activity
                </h2>
            </div>
            <div class="overflow-x-auto">
                <?php if ($recent_movements->num_rows > 0): ?>
                    <div class="divide-y divide-gray-200">
                        <?php while ($movement = $recent_movements->fetch_assoc()): ?>
                            <div class="px-4 py-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars(substr($movement['product_name'], 0, 20)); ?>
                                            <?php echo strlen($movement['product_name']) > 20 ? '...' : ''; ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($movement['user_name']); ?> • 
                                            <?php echo date('M j, H:i', strtotime($movement['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm font-medium <?php echo $movement['movement_type'] === 'in' ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $movement['movement_type'] === 'in' ? '+' : '-'; ?><?php echo abs($movement['quantity_change']); ?>
                                        </span>
                                        <p class="text-xs text-gray-500">
                                            <?php echo ucfirst($movement['movement_type']); ?>
                                        </p>
                                    </div>
                                </div>
                                <?php if ($movement['reason']): ?>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?php echo htmlspecialchars($movement['reason']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-history text-gray-400 text-3xl mb-3"></i>
                        <p>No recent activity found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mt-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <a href="../purchase_orders/create.php" class="bg-purple-500 hover:bg-purple-600 text-white p-4 rounded-lg transition duration-200 flex flex-col items-center text-center">
                <i class="fas fa-plus-circle text-2xl mb-2"></i>
                <span class="text-sm">New Purchase Order</span>
            </a>
            <a href="../suppliers/create.php" class="bg-green-500 hover:bg-green-600 text-white p-4 rounded-lg transition duration-200 flex flex-col items-center text-center">
                <i class="fas fa-truck text-2xl mb-2"></i>
                <span class="text-sm">Add Supplier</span>
            </a>
            <a href="../products/create.php" class="bg-blue-500 hover:bg-blue-600 text-white p-4 rounded-lg transition duration-200 flex flex-col items-center text-center">
                <i class="fas fa-box text-2xl mb-2"></i>
                <span class="text-sm">Add Product</span>
            </a>
            <a href="../categories/create.php" class="bg-indigo-500 hover:bg-indigo-600 text-white p-4 rounded-lg transition duration-200 flex flex-col items-center text-center">
                <i class="fas fa-tag text-2xl mb-2"></i>
                <span class="text-sm">Add Category</span>
            </a>
            <a href="../users/create.php" class="bg-orange-500 hover:bg-orange-600 text-white p-4 rounded-lg transition duration-200 flex flex-col items-center text-center">
                <i class="fas fa-user-plus text-2xl mb-2"></i>
                <span class="text-sm">Add User</span>
            </a>
            <a href="../reports/index.php" class="bg-red-500 hover:bg-red-600 text-white p-4 rounded-lg transition duration-200 flex flex-col items-center text-center">
                <i class="fas fa-chart-bar text-2xl mb-2"></i>
                <span class="text-sm">View Reports</span>
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
