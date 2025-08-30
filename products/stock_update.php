<?php
require_once '../includes/auth.php';
requireLogin(); // Both admin and staff can update stock

$error = '';
$success = '';

// Handle bulk stock update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update'])) {
    if (isset($_POST['updates']) && is_array($_POST['updates'])) {
        $updates = $_POST['updates'];
        $errors = [];
        $success_updates = 0;
        
        foreach ($updates as $product_id => $update_data) {
            if (empty($update_data['quantity']) || empty($update_data['reason'])) {
                continue; // Skip empty updates
            }
            
            $product_id = intval($product_id);
            $new_quantity = intval($update_data['quantity']);
            $reason = trim($update_data['reason']);
            $notes = trim($update_data['notes'] ?? '');
            
            if ($new_quantity < 0) {
                $errors[] = "Invalid quantity for product ID $product_id";
                continue;
            }
            
            // Get current product info
            $stmt = $conn->prepare("SELECT name, quantity FROM products WHERE id = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $errors[] = "Product ID $product_id not found";
                $stmt->close();
                continue;
            }
            
            $product = $result->fetch_assoc();
            $current_quantity = $product['quantity'];
            $product_name = $product['name'];
            $stmt->close();
            
            // Calculate the change
            $quantity_change = $new_quantity - $current_quantity;
            
            if ($quantity_change != 0) {
                // Update the product quantity
                $update_stmt = $conn->prepare("UPDATE products SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $update_stmt->bind_param("ii", $new_quantity, $product_id);
                
                if ($update_stmt->execute()) {
                    // Log the stock movement
                    $movement_type = $quantity_change > 0 ? 'in' : 'out';
                    logStockMovement($product_id, $_SESSION['user_id'], $movement_type, $quantity_change, $current_quantity, $new_quantity, $reason, $notes);
                    $success_updates++;
                } else {
                    $errors[] = "Failed to update $product_name: " . $conn->error;
                }
                $update_stmt->close();
            }
        }
        
        if ($success_updates > 0) {
            $success = "$success_updates product(s) updated successfully.";
        }
        if (!empty($errors)) {
            $error = implode('; ', $errors);
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$stock_filter = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';

// Build query for products
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if (!empty($category_filter) && $category_filter !== 'all') {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
    $param_types .= 'i';
}

if (!empty($stock_filter) && $stock_filter !== 'all') {
    switch ($stock_filter) {
        case 'out_of_stock':
            $where_conditions[] = "p.quantity = 0";
            break;
        case 'low_stock':
            $where_conditions[] = "p.quantity <= p.min_stock_level AND p.quantity > 0";
            break;
        case 'in_stock':
            $where_conditions[] = "p.quantity > p.min_stock_level";
            break;
    }
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get products for stock update
$products_query = "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $where_clause
    ORDER BY p.name ASC
";

if (!empty($params)) {
    $stmt = $conn->prepare($products_query);
    if (!empty($param_types)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $products_result = $stmt->get_result();
} else {
    $products_result = $conn->query($products_query);
}

// Get categories for filter
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");

// Set page title and include header after all redirects
$page_title = 'Stock Update';
include '../includes/header.php';
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Stock Update</h1>
                <p class="text-gray-600 mt-2">Update inventory levels for multiple products</p>
            </div>
            <div class="flex space-x-2">
                <a href="view.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-list mr-2"></i>View All Products
                </a>
                <?php if (isAdmin()): ?>
                <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-cog mr-2"></i>Manage Products
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (!empty($success)): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="bg-white shadow-lg rounded-lg mb-6">
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Products</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>"
                           placeholder="Name or SKU..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select id="category" name="category" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="all">All Categories</option>
                        <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                            <?php while ($category = $categories_result->fetch_assoc()): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div>
                    <label for="stock_status" class="block text-sm font-medium text-gray-700 mb-2">Stock Status</label>
                    <select id="stock_status" name="stock_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="all">All Status</option>
                        <option value="out_of_stock" <?php echo $stock_filter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        <option value="low_stock" <?php echo $stock_filter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="in_stock" <?php echo $stock_filter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                    </select>
                </div>
                
                <div class="flex items-end space-x-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                    <a href="stock_update.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-refresh mr-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Update Form -->
    <form method="POST" id="bulkUpdateForm">
        <input type="hidden" name="bulk_update" value="1">
        
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-edit text-blue-500 mr-2"></i>
                        Bulk Stock Update
                    </h2>
                    <div class="text-sm text-gray-500">
                        Only products with changes will be updated
                    </div>
                </div>
            </div>
            
            <?php if ($products_result && $products_result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Stock</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">New Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($product = $products_result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    SKU: <?php echo htmlspecialchars($product['sku']); ?>
                                                    <?php if ($product['category_name']): ?>
                                                        • <?php echo htmlspecialchars($product['category_name']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col">
                                            <span class="text-2xl font-bold <?php 
                                                if ($product['quantity'] == 0) echo 'text-red-600';
                                                elseif ($product['quantity'] <= $product['min_stock_level']) echo 'text-yellow-600';
                                                else echo 'text-green-600';
                                            ?>"><?php echo $product['quantity']; ?></span>
                                            <span class="text-xs text-gray-500">Min: <?php echo $product['min_stock_level']; ?></span>
                                            <?php if ($product['quantity'] <= $product['min_stock_level']): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $product['quantity'] == 0 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'; ?> mt-1">
                                                    <?php echo $product['quantity'] == 0 ? 'Out of Stock' : 'Low Stock'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="number" 
                                               name="updates[<?php echo $product['id']; ?>][quantity]" 
                                               min="0"
                                               placeholder="<?php echo $product['quantity']; ?>"
                                               class="w-24 px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <select name="updates[<?php echo $product['id']; ?>][reason]" 
                                                class="w-40 px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                            <option value="">Select reason</option>
                                            <option value="Stock Count">Stock Count</option>
                                            <option value="Purchase">New Purchase</option>
                                            <option value="Sale">Sale/Dispatch</option>
                                            <option value="Return">Customer Return</option>
                                            <option value="Damage">Damage/Loss</option>
                                            <option value="Transfer">Transfer</option>
                                            <option value="Correction">Correction</option>
                                        </select>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="text" 
                                               name="updates[<?php echo $product['id']; ?>][notes]" 
                                               placeholder="Optional notes..."
                                               maxlength="255"
                                               class="w-32 px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Form Actions -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-1"></i>
                            Enter new quantities and reasons for products you want to update. Leave blank to skip.
                        </div>
                        <div class="flex space-x-3">
                            <button type="button" onclick="clearForm()" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium">
                                <i class="fas fa-eraser mr-2"></i>Clear All
                            </button>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                                <i class="fas fa-save mr-2"></i>Update Stock
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-box-open text-gray-400 text-6xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No products found</h3>
                    <p class="text-gray-500 mb-6">Try adjusting your search or filter criteria.</p>
                    <a href="view.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                        <i class="fas fa-list mr-2"></i>
                        View All Products
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <!-- Quick Actions -->
    <?php if ($products_result && $products_result->num_rows > 0): ?>
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-900 mb-4">
            <i class="fas fa-zap text-blue-600 mr-2"></i>
            Quick Actions
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <button onclick="fillLowStockToMin()" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-level-up-alt mr-2"></i>
                Fill Low Stock to Min Level
            </button>
            <button onclick="markAllAsStockCount()" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-clipboard-list mr-2"></i>
                Mark All as "Stock Count"
            </button>
            <button onclick="focusOutOfStock()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm">
                <i class="fas fa-focus mr-2"></i>
                Focus Out of Stock Items
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('bulkUpdateForm');
    
    // Form submission validation
    form.addEventListener('submit', function(e) {
        const updates = document.querySelectorAll('input[name*="[quantity]"]');
        let hasUpdates = false;
        let hasErrors = false;
        
        updates.forEach(function(input) {
            if (input.value.trim() !== '') {
                hasUpdates = true;
                const productRow = input.closest('tr');
                const reasonSelect = productRow.querySelector('select[name*="[reason]"]');
                
                if (!reasonSelect.value) {
                    reasonSelect.classList.add('border-red-500');
                    hasErrors = true;
                } else {
                    reasonSelect.classList.remove('border-red-500');
                }
                
                if (parseInt(input.value) < 0) {
                    input.classList.add('border-red-500');
                    hasErrors = true;
                } else {
                    input.classList.remove('border-red-500');
                }
            }
        });
        
        if (!hasUpdates) {
            e.preventDefault();
            showAlert('Please enter at least one quantity update', 'error');
            return;
        }
        
        if (hasErrors) {
            e.preventDefault();
            showAlert('Please fix the errors: quantities must be >= 0 and reasons are required for all updates', 'error');
            return;
        }
        
        if (!confirm('Are you sure you want to update the stock for the selected products? This action will be logged.')) {
            e.preventDefault();
        }
    });
});

function clearForm() {
    document.querySelectorAll('input[name*="[quantity]"]').forEach(input => input.value = '');
    document.querySelectorAll('select[name*="[reason]"]').forEach(select => select.selectedIndex = 0);
    document.querySelectorAll('input[name*="[notes]"]').forEach(input => input.value = '');
}

function fillLowStockToMin() {
    const rows = document.querySelectorAll('tbody tr');
    
    rows.forEach(function(row) {
        const currentStockCell = row.querySelector('td:nth-child(2)');
        const quantityInput = row.querySelector('input[name*="[quantity]"]');
        const reasonSelect = row.querySelector('select[name*="[reason]"]');
        
        // Check if this is a low stock item
        const statusSpan = currentStockCell.querySelector('.bg-yellow-100, .bg-red-100');
        if (statusSpan) {
            const minLevelText = currentStockCell.querySelector('.text-xs').textContent;
            const minLevel = minLevelText.match(/\d+/)[0];
            
            quantityInput.value = minLevel;
            reasonSelect.value = 'Purchase';
        }
    });
    
    showAlert('Low stock items filled to minimum levels', 'success');
}

function markAllAsStockCount() {
    document.querySelectorAll('select[name*="[reason]"]').forEach(select => {
        select.value = 'Stock Count';
    });
    
    showAlert('All reasons set to "Stock Count"', 'success');
}

function focusOutOfStock() {
    const rows = document.querySelectorAll('tbody tr');
    let outOfStockFound = false;
    
    rows.forEach(function(row) {
        const currentStockCell = row.querySelector('td:nth-child(2)');
        const hasOutOfStock = currentStockCell.querySelector('.bg-red-100');
        
        if (hasOutOfStock) {
            row.style.backgroundColor = '#FEF2F2';
            row.style.border = '2px solid #DC2626';
            outOfStockFound = true;
            
            // Focus on the first out of stock item
            if (!document.querySelector('.focused-item')) {
                row.classList.add('focused-item');
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } else {
            row.style.backgroundColor = '';
            row.style.border = '';
        }
    });
    
    if (outOfStockFound) {
        showAlert('Out of stock items highlighted in red', 'info');
    } else {
        showAlert('No out of stock items found', 'success');
    }
}
</script>

<?php include '../includes/footer.php'; ?>
