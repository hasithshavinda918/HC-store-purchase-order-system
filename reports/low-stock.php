<?php
require_once '../includes/auth.php';
requireAdmin();

$page_title = 'Low Stock Report';
include '../includes/header.php';

// Get low stock products
$query = "
    SELECT 
        p.*,
        c.name as category_name,
        (p.min_stock_level - p.quantity) as shortage
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.quantity <= p.min_stock_level
    ORDER BY p.quantity ASC, shortage DESC
";
$low_stock_products = $conn->query($query);

// Calculate totals
$total_affected = $low_stock_products->num_rows;
$total_shortage = 0;
$total_value = 0;

// Reset result pointer and calculate totals
$low_stock_products->data_seek(0);
while ($product = $low_stock_products->fetch_assoc()) {
    if ($product['quantity'] < $product['min_stock_level']) {
        $total_shortage += ($product['min_stock_level'] - $product['quantity']);
    }
    $total_value += ($product['price'] * $product['quantity']);
}

// Reset result pointer for display
$low_stock_products->data_seek(0);
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <a href="index.php" class="text-gray-500 hover:text-gray-700 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Low Stock Report</h1>
                    <p class="text-gray-600 mt-2">Products that need immediate restocking attention</p>
                </div>
            </div>
            <div class="flex space-x-2">
                <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-print mr-2"></i>Print Report
                </button>
                <a href="export.php?type=low_stock" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-download mr-2"></i>Export CSV
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Affected Products</div>
                        <div class="text-2xl font-bold text-red-600"><?php echo $total_affected; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-minus-circle text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Total Shortage</div>
                        <div class="text-2xl font-bold text-yellow-600"><?php echo $total_shortage; ?></div>
                        <div class="text-xs text-gray-500">units needed</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-rupee-sign text-white"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500">Current Value</div>
                        <div class="text-2xl font-bold text-green-600">LKR <?php echo number_format($total_value, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Products Table -->
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-list text-red-500 mr-2"></i>
                Low Stock Products
            </h2>
        </div>
        
        <?php if ($total_affected > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Min Level</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shortage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($product = $low_stock_products->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                SKU: <?php echo htmlspecialchars($product['sku']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-bold <?php echo $product['quantity'] == 0 ? 'text-red-600' : 'text-yellow-600'; ?>">
                                        <?php echo $product['quantity']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $product['min_stock_level']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($product['quantity'] < $product['min_stock_level']): ?>
                                        <span class="text-sm font-bold text-red-600">
                                            <?php echo $product['min_stock_level'] - $product['quantity']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-sm text-green-600">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    LKR <?php echo number_format($product['price'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($product['quantity'] == 0): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-times-circle mr-1"></i>Out of Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>Low Stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="../products/stock_adjust.php?id=<?php echo $product['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900 bg-blue-100 px-2 py-1 rounded">
                                        <i class="fas fa-plus mr-1"></i>Restock
                                    </a>
                                    <a href="../products/edit.php?id=<?php echo $product['id']; ?>" 
                                       class="text-gray-600 hover:text-gray-900">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-check-circle text-green-500 text-6xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">All Products Well Stocked!</h3>
                <p class="text-gray-500 mb-6">All products are currently above their minimum stock levels.</p>
                <a href="../products/index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                    <i class="fas fa-box mr-2"></i>
                    View All Products
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recommendations -->
    <?php if ($total_affected > 0): ?>
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-blue-900 mb-4">
                <i class="fas fa-lightbulb text-blue-600 mr-2"></i>
                Restocking Recommendations
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-800">
                <div>
                    <h4 class="font-medium mb-2">Immediate Action Required:</h4>
                    <ul class="list-disc list-inside space-y-1">
                        <li>Prioritize out-of-stock items for immediate reordering</li>
                        <li>Contact suppliers for fastest delivery options</li>
                        <li>Consider temporary substitutes if available</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium mb-2">Medium-term Planning:</h4>
                    <ul class="list-disc list-inside space-y-1">
                        <li>Review minimum stock levels for frequently low items</li>
                        <li>Analyze sales patterns to predict demand</li>
                        <li>Set up automated alerts for critical stock levels</li>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Generated timestamp -->
    <div class="mt-8 text-center text-sm text-gray-500">
        Report generated on <?php echo date('F j, Y \a\t g:i A'); ?>
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
