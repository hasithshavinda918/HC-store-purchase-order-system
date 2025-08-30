<?php
require_once '../includes/auth.php';
requireLogin(); // Both admin and staff can view products

$page_title = 'View Products';
include '../includes/header.php';

// Get all products with category names
$products_query = "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY p.name ASC
";
$products_result = $conn->query($products_query);

// Get categories for filter
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");

// Get statistics
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= min_stock_level")->fetch_assoc()['count'];
$out_of_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity = 0")->fetch_assoc()['count'];
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Product Inventory</h1>
        <p class="text-gray-600 mt-2">View and search all products in inventory</p>
    </div>

    <!-- Quick Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
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
    </div>

    <!-- Search and Filter -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <div class="flex-1 max-w-lg">
                    <div class="relative">
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Search products by name, SKU, or description..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <select id="categoryFilter" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Categories</option>
                        <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                            <?php while ($category = $categories_result->fetch_assoc()): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                    <select id="stockFilter" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Stock Levels</option>
                        <option value="in-stock">In Stock</option>
                        <option value="low-stock">Low Stock</option>
                        <option value="out-of-stock">Out of Stock</option>
                    </select>
                    <?php if (isAdmin()): ?>
                    <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Grid/Table Toggle -->
    <div class="mb-6 flex justify-between items-center">
        <div class="flex space-x-2">
            <button id="gridView" class="bg-blue-600 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-th mr-2"></i>Grid View
            </button>
            <button id="tableView" class="bg-gray-500 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-list mr-2"></i>Table View
            </button>
        </div>
        <div class="text-sm text-gray-500">
            <span id="productCount"><?php echo $total_products; ?></span> products found
        </div>
    </div>

    <!-- Products Grid View -->
    <div id="gridContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if ($products_result && $products_result->num_rows > 0): ?>
            <?php 
            $products_result->data_seek(0); // Reset pointer
            while ($product = $products_result->fetch_assoc()): 
            ?>
                <div class="product-card bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300"
                     data-category="<?php echo $product['category_id'] ?? ''; ?>"
                     data-stock-status="<?php 
                        if ($product['quantity'] == 0) echo 'out-of-stock';
                        elseif ($product['quantity'] <= $product['min_stock_level']) echo 'low-stock';
                        else echo 'in-stock';
                     ?>">
                    <div class="p-6">
                        <!-- Product Header -->
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 mb-1 line-clamp-2">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </h3>
                                <p class="text-sm text-gray-500 mb-2"><?php echo htmlspecialchars($product['sku']); ?></p>
                                <span class="inline-block bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?>
                                </span>
                            </div>
                            <div class="ml-4 text-right">
                                <?php if ($product['quantity'] == 0): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        Out of Stock
                                    </span>
                                <?php elseif ($product['quantity'] <= $product['min_stock_level']): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Low Stock
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        In Stock
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Stock Information -->
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-medium text-gray-500">Current Stock:</span>
                                <span class="text-2xl font-bold <?php 
                                    if ($product['quantity'] == 0) echo 'text-red-600';
                                    elseif ($product['quantity'] <= $product['min_stock_level']) echo 'text-yellow-600';
                                    else echo 'text-green-600';
                                ?>"><?php echo $product['quantity']; ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500">Min Level:</span>
                                <span class="text-gray-900"><?php echo $product['min_stock_level']; ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500">Price:</span>
                                <span class="font-semibold text-gray-900">LKR <?php echo number_format($product['price'], 2); ?></span>
                            </div>
                        </div>

                        <!-- Description -->
                        <?php if (!empty($product['description'])): ?>
                            <div class="mt-4">
                                <p class="text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars($product['description']); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="mt-6 flex space-x-2">
                            <a href="stock_adjust.php?id=<?php echo $product['id']; ?>" 
                               class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-center px-3 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-edit mr-1"></i>Update Stock
                            </a>
                            <?php if (isAdmin()): ?>
                                <a href="edit.php?id=<?php echo $product['id']; ?>" 
                                   class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-sm" title="Edit Product">
                                    <i class="fas fa-edit"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full text-center py-12">
                <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No products found</h3>
                <p class="text-gray-500">Try adjusting your search or filter criteria.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Products Table View (Hidden by default) -->
    <div id="tableContainer" class="hidden bg-white shadow overflow-hidden sm:rounded-lg">
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
                    <?php 
                    $products_result->data_seek(0); // Reset pointer
                    if ($products_result && $products_result->num_rows > 0): 
                    ?>
                        <?php while ($product = $products_result->fetch_assoc()): ?>
                            <tr class="product-row" 
                                data-category="<?php echo $product['category_id'] ?? ''; ?>"
                                data-stock-status="<?php 
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
                                        <a href="stock_adjust.php?id=<?php echo $product['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="Update Stock">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (isAdmin()): ?>
                                            <a href="edit.php?id=<?php echo $product['id']; ?>" 
                                               class="text-green-600 hover:text-green-900" title="Edit Product">
                                                <i class="fas fa-cog"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                <i class="fas fa-box-open text-4xl mb-4 text-gray-300"></i>
                                <p>No products found</p>
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
    const gridView = document.getElementById('gridView');
    const tableView = document.getElementById('tableView');
    const gridContainer = document.getElementById('gridContainer');
    const tableContainer = document.getElementById('tableContainer');
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const stockFilter = document.getElementById('stockFilter');
    const productCount = document.getElementById('productCount');
    
    let currentView = 'grid';
    
    // View toggle
    gridView.addEventListener('click', function() {
        currentView = 'grid';
        gridContainer.classList.remove('hidden');
        tableContainer.classList.add('hidden');
        gridView.classList.add('bg-blue-600');
        gridView.classList.remove('bg-gray-500');
        tableView.classList.add('bg-gray-500');
        tableView.classList.remove('bg-blue-600');
    });
    
    tableView.addEventListener('click', function() {
        currentView = 'table';
        gridContainer.classList.add('hidden');
        tableContainer.classList.remove('hidden');
        tableView.classList.add('bg-blue-600');
        tableView.classList.remove('bg-gray-500');
        gridView.classList.add('bg-gray-500');
        gridView.classList.remove('bg-blue-600');
    });
    
    // Filter function
    function filterProducts() {
        const searchTerm = searchInput.value.toLowerCase();
        const categoryValue = categoryFilter.value;
        const stockValue = stockFilter.value;
        
        let visibleCount = 0;
        
        // Filter grid cards
        const cards = document.querySelectorAll('.product-card');
        cards.forEach(card => {
            const productName = card.querySelector('h3').textContent.toLowerCase();
            const productSku = card.querySelector('.text-gray-500').textContent.toLowerCase();
            const categoryId = card.getAttribute('data-category');
            const stockStatus = card.getAttribute('data-stock-status');
            
            let visible = true;
            
            // Search filter
            if (searchTerm && !productName.includes(searchTerm) && !productSku.includes(searchTerm)) {
                visible = false;
            }
            
            // Category filter
            if (categoryValue !== 'all' && categoryId !== categoryValue) {
                visible = false;
            }
            
            // Stock filter
            if (stockValue !== 'all' && stockStatus !== stockValue) {
                visible = false;
            }
            
            card.style.display = visible ? '' : 'none';
            if (visible) visibleCount++;
        });
        
        // Filter table rows
        const rows = document.querySelectorAll('.product-row');
        rows.forEach(row => {
            const cells = row.getElementsByTagName('td');
            if (cells.length === 0) return;
            
            const productName = cells[0].textContent.toLowerCase();
            const productSku = cells[1].textContent.toLowerCase();
            const categoryId = row.getAttribute('data-category');
            const stockStatus = row.getAttribute('data-stock-status');
            
            let visible = true;
            
            // Search filter
            if (searchTerm && !productName.includes(searchTerm) && !productSku.includes(searchTerm)) {
                visible = false;
            }
            
            // Category filter
            if (categoryValue !== 'all' && categoryId !== categoryValue) {
                visible = false;
            }
            
            // Stock filter
            if (stockValue !== 'all' && stockStatus !== stockValue) {
                visible = false;
            }
            
            row.style.display = visible ? '' : 'none';
        });
        
        // Update count
        productCount.textContent = visibleCount;
    }
    
    // Add event listeners
    searchInput.addEventListener('keyup', filterProducts);
    categoryFilter.addEventListener('change', filterProducts);
    stockFilter.addEventListener('change', filterProducts);
});
</script>

<style>
.line-clamp-2 {
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

@media print {
    .no-print { display: none !important; }
    .grid { display: block !important; }
    .product-card { 
        page-break-inside: avoid; 
        margin-bottom: 1rem;
        border: 1px solid #ddd;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
