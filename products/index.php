<?php
require_once '../includes/auth.php';
requireAdmin();

$page_title = 'Product Management';
include '../includes/header.php';

// Handle delete action
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $product_id = intval($_GET['delete']);
    
    // Check if product exists and get current quantity for logging
    $check_stmt = $conn->prepare("SELECT name, quantity FROM products WHERE id = ?");
    $check_stmt->bind_param("i", $product_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Log the deletion as stock movement
        if ($product['quantity'] > 0) {
            logStockMovement($product_id, $_SESSION['user_id'], 'out', -$product['quantity'], $product['quantity'], 0, 'Product deleted', 'Product removed from inventory');
        }
        
        // Delete the product
        $delete_stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $delete_stmt->bind_param("i", $product_id);
        
        if ($delete_stmt->execute()) {
            header('Location: index.php?success=' . urlencode('Product "' . $product['name'] . '" deleted successfully'));
            exit();
        } else {
            $error = 'Error deleting product: ' . $conn->error;
        }
        $delete_stmt->close();
    }
    $check_stmt->close();
}

// Get all products with category names
$products_query = "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.name ASC
";
$products_result = $conn->query($products_query);

// Get statistics
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= min_stock_level")->fetch_assoc()['count'];
$out_of_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity = 0")->fetch_assoc()['count'];
$total_value = $conn->query("SELECT SUM(quantity * price) as total FROM products")->fetch_assoc()['total'] ?? 0;
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Product Management</h1>
            <p class="text-gray-600 mt-2">Manage your inventory products</p>
        </div>
        <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold flex items-center">
            <i class="fas fa-plus mr-2"></i>
            Add New Product
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-box text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Products</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $total_products; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Low Stock</dt>
                            <dd class="text-lg font-medium text-yellow-600"><?php echo $low_stock_count; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-times-circle text-red-500 text-2xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Out of Stock</dt>
                            <dd class="text-lg font-medium text-red-600"><?php echo $out_of_stock_count; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-rupee-sign text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Value</dt>
                            <dd class="text-lg font-medium text-green-600">LKR <?php echo number_format($total_value, 2); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <div class="flex-1 max-w-lg">
                    <div class="relative">
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Search products..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <select id="stockFilter" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Products</option>
                        <option value="in-stock">In Stock</option>
                        <option value="low-stock">Low Stock</option>
                        <option value="out-of-stock">Out of Stock</option>
                    </select>
                    <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="overflow-x-auto">
            <table id="dataTable" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Min Level</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($products_result && $products_result->num_rows > 0): ?>
                        <?php while ($product = $products_result->fetch_assoc()): ?>
                            <tr data-stock-status="<?php 
                                if ($product['quantity'] == 0) echo 'out-of-stock';
                                elseif ($product['quantity'] <= $product['min_stock_level']) echo 'low-stock';
                                else echo 'in-stock';
                            ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($product['description'] ?? ''); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($product['sku']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php 
                                    if ($product['quantity'] == 0) echo 'text-red-600';
                                    elseif ($product['quantity'] <= $product['min_stock_level']) echo 'text-yellow-600';
                                    else echo 'text-green-600';
                                ?>">
                                    <?php echo $product['quantity']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $product['min_stock_level']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    LKR <?php echo number_format($product['price'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($product['quantity'] == 0): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Out of Stock
                                        </span>
                                    <?php elseif ($product['quantity'] <= $product['min_stock_level']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Low Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            In Stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="edit.php?id=<?php echo $product['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="stock_adjust.php?id=<?php echo $product['id']; ?>" 
                                           class="text-green-600 hover:text-green-900" title="Adjust Stock">
                                            <i class="fas fa-boxes"></i>
                                        </a>
                                        <a href="?delete=<?php echo $product['id']; ?>" 
                                           onclick="return confirmDelete('Are you sure you want to delete this product?')"
                                           class="text-red-600 hover:text-red-900" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                <i class="fas fa-box-open text-4xl mb-4 text-gray-300"></i>
                                <p>No products found. <a href="create.php" class="text-blue-600 hover:text-blue-800">Add your first product</a></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Stock filter functionality
    const stockFilter = document.getElementById('stockFilter');
    const rows = document.querySelectorAll('#dataTable tbody tr[data-stock-status]');
    
    stockFilter.addEventListener('change', function() {
        const filterValue = this.value;
        
        rows.forEach(row => {
            const stockStatus = row.getAttribute('data-stock-status');
            
            if (filterValue === 'all' || stockStatus === filterValue) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // Enhanced search functionality
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        
        rows.forEach(row => {
            if (row.style.display === 'none' && stockFilter.value !== 'all') {
                return; // Skip if hidden by stock filter
            }
            
            const cells = row.getElementsByTagName('td');
            let found = false;
            
            // Search in product name, SKU, and category
            for (let i = 0; i < 3; i++) {
                if (cells[i] && cells[i].textContent.toLowerCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
            
            row.style.display = found ? '' : 'none';
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
