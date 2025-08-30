<?php
require_once '../includes/auth.php';
require_once '../includes/language.php';
requireAdmin();

$page_title = __('out_of_stock') . ' ' . __('report');
include '../includes/header.php';

// Get out-of-stock products
$out_of_stock_query = "
    SELECT 
        p.*,
        c.name as category_name,
        COALESCE(
            (SELECT SUM(ABS(quantity_change)) 
             FROM stock_movements sm 
             WHERE sm.product_id = p.id 
             AND sm.movement_type = 'out' 
             AND DATE(sm.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ), 0
        ) as usage_last_30_days,
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
    ORDER BY last_movement DESC, p.name ASC
";

$out_of_stock_products = $conn->query($out_of_stock_query);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_products,
        COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock_count,
        COUNT(CASE WHEN quantity <= min_stock_level AND quantity > 0 THEN 1 END) as low_stock_count,
        COUNT(CASE WHEN quantity > min_stock_level THEN 1 END) as in_stock_count
    FROM products
";
$stats = $conn->query($stats_query)->fetch_assoc();

// Calculate percentage
$out_of_stock_percentage = $stats['total_products'] > 0 ? 
    round(($stats['out_of_stock_count'] / $stats['total_products']) * 100, 1) : 0;
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    <i class="fas fa-times-circle text-red-600 mr-3"></i>
                    <?php _e('out_of_stock'); ?> <?php _e('report'); ?>
                </h1>
                <p class="text-gray-600 mt-2"><?php _e('products_with_zero_stock'); ?></p>
            </div>
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-arrow-left mr-2"></i><?php _e('back_to_reports'); ?>
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-boxes text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('total_products'); ?></div>
                        <div class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_products']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-600 rounded-full flex items-center justify-center">
                            <i class="fas fa-times-circle text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('out_of_stock'); ?></div>
                        <div class="text-2xl font-bold text-red-700"><?php echo number_format($stats['out_of_stock_count']); ?></div>
                        <div class="text-xs text-gray-500"><?php echo $out_of_stock_percentage; ?>% <?php _e('of_total'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('low_stock'); ?></div>
                        <div class="text-2xl font-bold text-yellow-600"><?php echo number_format($stats['low_stock_count']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('in_stock'); ?></div>
                        <div class="text-2xl font-bold text-green-600"><?php echo number_format($stats['in_stock_count']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex justify-between items-center">
            <div class="text-sm text-gray-600">
                <?php echo number_format($stats['out_of_stock_count']); ?> <?php _e('products_found'); ?>
            </div>
            <div class="flex space-x-2">
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-print mr-2"></i><?php _e('print'); ?>
                </button>
                <button onclick="exportToCSV()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-download mr-2"></i><?php _e('export_csv'); ?>
                </button>
                <a href="../purchase_orders/create.php" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-plus mr-2"></i><?php _e('create_purchase_order'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Out of Stock Products Table -->
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <?php if ($out_of_stock_products->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('product'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('category'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('sku'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('min_stock_level'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('usage_30_days'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('last_movement'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($product = $out_of_stock_products->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-2 h-2 bg-red-500 rounded-full mr-3"></div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                LKR <?php echo number_format($product['price'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($product['category_name'] ?: __('uncategorized')); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                    <?php echo htmlspecialchars($product['sku']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($product['min_stock_level']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($product['usage_last_30_days'] > 0): ?>
                                        <span class="text-red-600 font-medium">
                                            <?php echo number_format($product['usage_last_30_days']); ?> <?php _e('units'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400"><?php _e('no_usage'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($product['last_movement']): ?>
                                        <?php echo date('M j, Y', strtotime($product['last_movement'])); ?>
                                    <?php else: ?>
                                        <span class="text-gray-400"><?php _e('no_movements'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <a href="../products/stock_update.php?id=<?php echo $product['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-plus mr-1"></i><?php _e('add_stock'); ?>
                                    </a>
                                    <a href="../products/edit.php?id=<?php echo $product['id']; ?>" 
                                       class="text-indigo-600 hover:text-indigo-900 ml-3">
                                        <i class="fas fa-edit mr-1"></i><?php _e('edit'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-6 text-center text-gray-500">
                <i class="fas fa-check-circle text-green-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2"><?php _e('great_news'); ?>!</h3>
                <p class="text-gray-500"><?php _e('no_products_out_of_stock'); ?></p>
                <div class="mt-4">
                    <a href="low-stock.php" class="text-blue-600 hover:text-blue-800">
                        <?php _e('check_low_stock_items'); ?> →
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($out_of_stock_products->num_rows > 0): ?>
        <!-- Recommendations -->
        <div class="mt-8 bg-yellow-50 border-l-4 border-yellow-400 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-lightbulb text-yellow-400"></i>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800"><?php _e('recommendations'); ?></h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li><?php _e('review_minimum_stock_levels'); ?></li>
                            <li><?php _e('consider_automatic_reorder_points'); ?></li>
                            <li><?php _e('analyze_usage_patterns'); ?></li>
                            <li><?php _e('setup_supplier_relationships'); ?></li>
                        </ul>
                    </div>
                    <div class="mt-4">
                        <div class="flex space-x-4">
                            <a href="../purchase_orders/create.php" 
                               class="bg-yellow-800 hover:bg-yellow-900 text-white px-3 py-2 rounded text-sm">
                                <?php _e('create_purchase_order'); ?>
                            </a>
                            <a href="../suppliers/index.php" 
                               class="bg-white hover:bg-gray-50 text-yellow-800 border border-yellow-300 px-3 py-2 rounded text-sm">
                                <?php _e('manage_suppliers'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function exportToCSV() {
    const data = [];
    const table = document.querySelector('table');
    
    if (table) {
        // Get headers
        const headers = [];
        table.querySelectorAll('thead th').forEach(th => {
            headers.push(th.textContent.trim());
        });
        data.push(headers);
        
        // Get rows
        table.querySelectorAll('tbody tr').forEach(tr => {
            const row = [];
            tr.querySelectorAll('td').forEach((td, index) => {
                // Skip actions column
                if (index < headers.length - 1) {
                    row.push(td.textContent.trim());
                }
            });
            data.push(row);
        });
        
        // Create CSV
        const csv = data.map(row => row.map(field => `"${field}"`).join(',')).join('\n');
        
        // Download
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'out-of-stock-report.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    }
}
</script>

<style media="print">
    .no-print { display: none !important; }
    body { background: white !important; }
</style>

<?php include '../includes/footer.php'; ?>
