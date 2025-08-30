<?php
require_once '../includes/auth.php';
require_once '../includes/language.php';
requireAdmin();

$page_title = __('inventory_value') . ' ' . __('report');
include '../includes/header.php';

// Get inventory value data
$inventory_query = "
    SELECT 
        p.*,
        c.name as category_name,
        (p.price * p.quantity) as total_value,
        CASE 
            WHEN p.quantity = 0 THEN 'out_of_stock'
            WHEN p.quantity <= p.min_stock_level THEN 'low_stock'
            ELSE 'in_stock'
        END as stock_status
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY total_value DESC
";

$products = $conn->query($inventory_query);

// Calculate totals
$totals_query = "
    SELECT 
        COUNT(*) as total_products,
        SUM(quantity) as total_quantity,
        SUM(price * quantity) as total_value,
        COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock_count,
        COUNT(CASE WHEN quantity <= min_stock_level AND quantity > 0 THEN 1 END) as low_stock_count,
        COUNT(CASE WHEN quantity > min_stock_level THEN 1 END) as in_stock_count,
        AVG(price) as avg_price
    FROM products
";
$totals = $conn->query($totals_query)->fetch_assoc();

// Category breakdown
$category_query = "
    SELECT 
        COALESCE(c.name, 'Uncategorized') as category_name,
        COUNT(p.id) as product_count,
        SUM(p.quantity) as total_quantity,
        SUM(p.price * p.quantity) as total_value,
        AVG(p.price) as avg_price
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    GROUP BY c.id, c.name
    ORDER BY total_value DESC
";
$categories = $conn->query($category_query);

// Top value products
$top_products_query = "
    SELECT 
        p.*,
        c.name as category_name,
        (p.price * p.quantity) as total_value
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.quantity > 0
    ORDER BY total_value DESC
    LIMIT 10
";
$top_products = $conn->query($top_products_query);
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    <i class="fas fa-calculator text-green-600 mr-3"></i>
                    <?php _e('inventory_value'); ?> <?php _e('report'); ?>
                </h1>
                <p class="text-gray-600 mt-2"><?php _e('comprehensive_inventory_valuation'); ?></p>
                <p class="text-sm text-gray-500 mt-1"><?php _e('generated_on'); ?>: <?php echo date('F j, Y \a\t H:i'); ?></p>
            </div>
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-arrow-left mr-2"></i><?php _e('back_to_reports'); ?>
            </a>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('total_inventory_value'); ?></div>
                        <div class="text-2xl font-bold text-green-600">
                            LKR <?php echo number_format($totals['total_value'], 2); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-boxes text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('total_products'); ?></div>
                        <div class="text-2xl font-bold text-blue-600"><?php echo number_format($totals['total_products']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-cubes text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('total_quantity'); ?></div>
                        <div class="text-2xl font-bold text-purple-600"><?php echo number_format($totals['total_quantity']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-line text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('average_price'); ?></div>
                        <div class="text-2xl font-bold text-indigo-600">
                            LKR <?php echo number_format($totals['avg_price'], 2); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Category Breakdown -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-chart-pie text-green-500 mr-2"></i>
                    <?php _e('value_by_category'); ?>
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('category'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('products'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('quantity'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('value'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $categories->data_seek(0); while ($category = $categories->fetch_assoc()): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($category['product_count']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($category['total_quantity']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600">
                                    LKR <?php echo number_format($category['total_value'], 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Value Products -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-trophy text-yellow-500 mr-2"></i>
                    <?php _e('top_10_valuable_products'); ?>
                </h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <?php $rank = 1; while ($product = $top_products->fetch_assoc()): ?>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-sm font-bold text-gray-600"><?php echo $rank; ?></span>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($product['sku']); ?>
                                        <?php if ($product['category_name']): ?>
                                            • <?php echo htmlspecialchars($product['category_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-semibold text-green-600">
                                    LKR <?php echo number_format($product['total_value'], 2); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo number_format($product['quantity']); ?> × LKR <?php echo number_format($product['price'], 2); ?>
                                </div>
                            </div>
                        </div>
                        <?php $rank++; ?>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex justify-between items-center">
            <div class="text-sm text-gray-600">
                <?php echo number_format($totals['total_products']); ?> <?php _e('products_total'); ?>
            </div>
            <div class="flex space-x-2">
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-print mr-2"></i><?php _e('print'); ?>
                </button>
                <button onclick="exportToCSV()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-download mr-2"></i><?php _e('export_csv'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Detailed Inventory Table -->
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-list text-blue-500 mr-2"></i>
                <?php _e('detailed_inventory_listing'); ?>
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="inventoryTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('product'); ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('category'); ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('sku'); ?></th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"><?php _e('quantity'); ?></th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"><?php _e('unit_price'); ?></th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase"><?php _e('total_value'); ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('status'); ?></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php $products->data_seek(0); while ($product = $products->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </div>
                                <?php if ($product['description']): ?>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>
                                        <?php echo strlen($product['description']) > 50 ? '...' : ''; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($product['category_name'] ?: __('uncategorized')); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                                <?php echo htmlspecialchars($product['sku']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?php echo number_format($product['quantity']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                LKR <?php echo number_format($product['price'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-600 text-right">
                                LKR <?php echo number_format($product['total_value'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    <?php 
                                    switch($product['stock_status']) {
                                        case 'out_of_stock': echo 'bg-red-100 text-red-800'; break;
                                        case 'low_stock': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'in_stock': echo 'bg-green-100 text-green-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800'; break;
                                    }
                                    ?>">
                                    <?php _e($product['stock_status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Summary Card -->
    <div class="mt-8 bg-green-50 border-l-4 border-green-400 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-green-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-green-800"><?php _e('inventory_summary'); ?></h3>
                <div class="mt-2 text-sm text-green-700">
                    <p>
                        <?php echo __('inventory_summary_text', [
                            'total_value' => 'LKR ' . number_format($totals['total_value'], 2),
                            'total_products' => number_format($totals['total_products']),
                            'total_quantity' => number_format($totals['total_quantity'])
                        ]); ?>
                    </p>
                    <div class="mt-3 grid grid-cols-3 gap-4">
                        <div class="text-center">
                            <div class="text-lg font-bold text-green-800"><?php echo $totals['in_stock_count']; ?></div>
                            <div class="text-xs text-green-600"><?php _e('in_stock'); ?></div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-yellow-600"><?php echo $totals['low_stock_count']; ?></div>
                            <div class="text-xs text-yellow-600"><?php _e('low_stock'); ?></div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-red-600"><?php echo $totals['out_of_stock_count']; ?></div>
                            <div class="text-xs text-red-600"><?php _e('out_of_stock'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportToCSV() {
    const table = document.getElementById('inventoryTable');
    const data = [];
    
    // Get headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    data.push(headers);
    
    // Get rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            row.push(td.textContent.trim().replace(/\s+/g, ' '));
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
    a.download = 'inventory-value-report.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<style media="print">
    .no-print { display: none !important; }
    body { background: white !important; }
</style>

<?php include '../includes/footer.php'; ?>
