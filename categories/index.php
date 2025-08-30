<?php
require_once '../includes/auth.php';
requireAdmin();

$page_title = 'Category Management';
include '../includes/header.php';

// Handle delete action
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    
    // Check if category has products
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
    $check_stmt->bind_param("i", $category_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $product_count = $result->fetch_assoc()['count'];
    
    if ($product_count > 0) {
        $error = "Cannot delete category. It has $product_count products assigned to it.";
    } else {
        // Get category name for confirmation
        $name_stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
        $name_stmt->bind_param("i", $category_id);
        $name_stmt->execute();
        $name_result = $name_stmt->get_result();
        
        if ($name_result->num_rows > 0) {
            $category_name = $name_result->fetch_assoc()['name'];
            
            // Delete the category
            $delete_stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $delete_stmt->bind_param("i", $category_id);
            
            if ($delete_stmt->execute()) {
                header('Location: index.php?success=' . urlencode('Category "' . $category_name . '" deleted successfully'));
                exit();
            } else {
                $error = 'Error deleting category: ' . $conn->error;
            }
            $delete_stmt->close();
        }
        $name_stmt->close();
    }
    $check_stmt->close();
}

// Get all categories with product counts
$categories_query = "
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id 
    GROUP BY c.id 
    ORDER BY c.name ASC
";
$categories_result = $conn->query($categories_query);

// Get statistics
$total_categories = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
$categories_with_products = $conn->query("SELECT COUNT(DISTINCT category_id) as count FROM products WHERE category_id IS NOT NULL")->fetch_assoc()['count'];
$empty_categories = $total_categories - $categories_with_products;
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Category Management</h1>
            <p class="text-gray-600 mt-2">Organize your products into categories</p>
        </div>
        <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold flex items-center">
            <i class="fas fa-plus mr-2"></i>
            Add New Category
        </a>
    </div>

    <!-- Error/Success Messages -->
    <?php if (isset($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tags text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Categories</dt>
                            <dd class="text-lg font-medium text-gray-900"><?php echo $total_categories; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-box text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">With Products</dt>
                            <dd class="text-lg font-medium text-green-600"><?php echo $categories_with_products; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-folder-open text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Empty Categories</dt>
                            <dd class="text-lg font-medium text-yellow-600"><?php echo $empty_categories; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                <div class="flex-1 max-w-lg">
                    <div class="relative">
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Search categories..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <select id="statusFilter" class="border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Categories</option>
                        <option value="with-products">With Products</option>
                        <option value="empty">Empty Categories</option>
                    </select>
                    <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="categoriesGrid">
        <?php if ($categories_result && $categories_result->num_rows > 0): ?>
            <?php while ($category = $categories_result->fetch_assoc()): ?>
                <div class="category-card bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300"
                     data-status="<?php echo $category['product_count'] > 0 ? 'with-products' : 'empty'; ?>">
                    <div class="p-6">
                        <!-- Category Header -->
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h3 class="text-xl font-semibold text-gray-900 mb-2">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </h3>
                                <?php if (!empty($category['description'])): ?>
                                    <p class="text-gray-600 text-sm">
                                        <?php echo htmlspecialchars($category['description']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="ml-4">
                                <?php if ($category['product_count'] > 0): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <?php echo $category['product_count']; ?> Products
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Empty
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Category Stats -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <div class="grid grid-cols-2 gap-4 text-center">
                                <div>
                                    <div class="text-2xl font-bold text-blue-600"><?php echo $category['product_count']; ?></div>
                                    <div class="text-xs text-gray-500">Products</div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Created</div>
                                    <div class="text-xs text-gray-600"><?php echo date('M j, Y', strtotime($category['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex space-x-2">
                            <a href="../products/index.php?category=<?php echo $category['id']; ?>" 
                               class="flex-1 bg-blue-100 hover:bg-blue-200 text-blue-700 text-center px-3 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-eye mr-1"></i>View Products
                            </a>
                            <a href="edit.php?id=<?php echo $category['id']; ?>" 
                               class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-sm" title="Edit Category">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($category['product_count'] == 0): ?>
                                <a href="?delete=<?php echo $category['id']; ?>" 
                                   onclick="return confirmDelete('Are you sure you want to delete this category?')"
                                   class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-sm" title="Delete Category">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php else: ?>
                                <button disabled 
                                        class="bg-gray-300 text-gray-500 px-3 py-2 rounded-lg text-sm cursor-not-allowed" 
                                        title="Cannot delete - has products">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-span-full text-center py-12">
                <i class="fas fa-tags text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No categories found</h3>
                <p class="text-gray-500 mb-6">Create your first category to organize your products.</p>
                <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                    <i class="fas fa-plus mr-2"></i>Create Category
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Categories Table (Alternative View) -->
    <div class="mt-8 bg-white shadow overflow-hidden sm:rounded-lg hidden" id="categoriesTable">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Products</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php 
                    $categories_result->data_seek(0); // Reset pointer
                    if ($categories_result && $categories_result->num_rows > 0): 
                    ?>
                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                            <tr class="category-row" data-status="<?php echo $category['product_count'] > 0 ? 'with-products' : 'empty'; ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-500 max-w-xs truncate">
                                        <?php echo htmlspecialchars($category['description'] ?? 'No description'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($category['product_count'] > 0): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo $category['product_count']; ?> products
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Empty
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($category['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="../products/index.php?category=<?php echo $category['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="View Products">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $category['id']; ?>" 
                                           class="text-green-600 hover:text-green-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($category['product_count'] == 0): ?>
                                            <a href="?delete=<?php echo $category['id']; ?>" 
                                               onclick="return confirmDelete('Are you sure you want to delete this category?')"
                                               class="text-red-600 hover:text-red-900" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    
    // Filter function
    function filterCategories() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value;
        
        // Filter grid cards
        const cards = document.querySelectorAll('.category-card');
        cards.forEach(card => {
            const categoryName = card.querySelector('h3').textContent.toLowerCase();
            const categoryDesc = card.querySelector('p') ? card.querySelector('p').textContent.toLowerCase() : '';
            const status = card.getAttribute('data-status');
            
            let visible = true;
            
            // Search filter
            if (searchTerm && !categoryName.includes(searchTerm) && !categoryDesc.includes(searchTerm)) {
                visible = false;
            }
            
            // Status filter
            if (statusValue !== 'all' && status !== statusValue) {
                visible = false;
            }
            
            card.style.display = visible ? '' : 'none';
        });
        
        // Filter table rows
        const rows = document.querySelectorAll('.category-row');
        rows.forEach(row => {
            const cells = row.getElementsByTagName('td');
            if (cells.length === 0) return;
            
            const categoryName = cells[0].textContent.toLowerCase();
            const categoryDesc = cells[1].textContent.toLowerCase();
            const status = row.getAttribute('data-status');
            
            let visible = true;
            
            // Search filter
            if (searchTerm && !categoryName.includes(searchTerm) && !categoryDesc.includes(searchTerm)) {
                visible = false;
            }
            
            // Status filter
            if (statusValue !== 'all' && status !== statusValue) {
                visible = false;
            }
            
            row.style.display = visible ? '' : 'none';
        });
    }
    
    // Add event listeners
    searchInput.addEventListener('keyup', filterCategories);
    statusFilter.addEventListener('change', filterCategories);
});
</script>

<?php include '../includes/footer.php'; ?>
