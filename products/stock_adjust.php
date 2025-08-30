<?php
require_once '../includes/auth.php';
requireLogin(); // Both admin and staff can adjust stock

$error = '';
$success = '';

// Get product ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?error=' . urlencode('Product not found'));
    exit();
}

$product_id = intval($_GET['id']);

// Get product details
$stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . (isAdmin() ? 'index.php' : 'view.php') . '?error=' . urlencode('Product not found'));
    exit();
}

$product = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adjustment_type = $_POST['adjustment_type']; // 'in', 'out', 'set'
    $quantity = intval($_POST['quantity']);
    $reason = trim($_POST['reason']);
    $notes = trim($_POST['notes']);
    
    // Validation
    if (empty($adjustment_type)) {
        $error = 'Adjustment type is required.';
    } elseif ($quantity <= 0 && $adjustment_type !== 'set') {
        $error = 'Quantity must be greater than 0.';
    } elseif ($quantity < 0 && $adjustment_type === 'set') {
        $error = 'Stock quantity cannot be negative.';
    } elseif ($adjustment_type === 'out' && $quantity > $product['quantity']) {
        $error = 'Cannot remove more stock than available (' . $product['quantity'] . ').';
    } elseif (empty($reason)) {
        $error = 'Reason is required.';
    } else {
        $current_quantity = $product['quantity'];
        $new_quantity = $current_quantity;
        $quantity_change = 0;
        $movement_type = $adjustment_type;
        
        // Calculate new quantity based on adjustment type
        switch ($adjustment_type) {
            case 'in':
                $new_quantity = $current_quantity + $quantity;
                $quantity_change = $quantity;
                break;
            case 'out':
                $new_quantity = $current_quantity - $quantity;
                $quantity_change = -$quantity;
                break;
            case 'set':
                $new_quantity = $quantity;
                $quantity_change = $quantity - $current_quantity;
                $movement_type = $quantity_change >= 0 ? 'in' : 'out';
                break;
        }
        
        // Update product quantity
        $update_stmt = $conn->prepare("UPDATE products SET quantity = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update_stmt->bind_param("ii", $new_quantity, $product_id);
        
        if ($update_stmt->execute()) {
            // Log stock movement
            logStockMovement($product_id, $_SESSION['user_id'], $movement_type, $quantity_change, $current_quantity, $new_quantity, $reason, $notes);
            
            $redirect_url = isAdmin() ? 'index.php' : 'view.php';
            header('Location: ' . $redirect_url . '?success=' . urlencode('Stock adjusted successfully for "' . $product['name'] . '"'));
            exit();
        } else {
            $error = 'Error updating stock: ' . $conn->error;
        }
        $update_stmt->close();
    }
}

// Get recent stock movements for this product
$movements_query = "
    SELECT sm.*, u.full_name as user_name 
    FROM stock_movements sm 
    JOIN users u ON sm.user_id = u.id 
    WHERE sm.product_id = ? 
    ORDER BY sm.created_at DESC 
    LIMIT 10
";
$movements_stmt = $conn->prepare($movements_query);
$movements_stmt->bind_param("i", $product_id);
$movements_stmt->execute();
$movements_result = $movements_stmt->get_result();
$movements_stmt->close();

// Set page title and include header after all redirects
$page_title = 'Adjust Stock';
include '../includes/header.php';
?>

<div class="max-w-6xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center">
            <a href="<?php echo isAdmin() ? 'index.php' : 'view.php'; ?>" class="text-gray-500 hover:text-gray-700 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Adjust Stock</h1>
                <p class="text-gray-600 mt-2">Update inventory levels for <?php echo htmlspecialchars($product['name']); ?></p>
            </div>
        </div>
    </div>

    <!-- Error/Success Messages -->
    <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Product Info & Stock Adjustment Form -->
        <div class="space-y-6">
            <!-- Product Information -->
            <div class="bg-white shadow-lg rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Product Information</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Product Name</label>
                            <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">SKU</label>
                            <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($product['sku']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Category</label>
                            <p class="text-lg text-gray-900"><?php echo htmlspecialchars($product['category_name'] ?? 'No Category'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Current Stock</label>
                            <p class="text-2xl font-bold <?php 
                                if ($product['quantity'] == 0) echo 'text-red-600';
                                elseif ($product['quantity'] <= $product['min_stock_level']) echo 'text-yellow-600';
                                else echo 'text-green-600';
                            ?>"><?php echo $product['quantity']; ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Minimum Level</label>
                            <p class="text-lg text-gray-900"><?php echo $product['min_stock_level']; ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Price</label>
                            <p class="text-lg text-gray-900">LKR <?php echo number_format($product['price'], 2); ?></p>
                        </div>
                    </div>
                    
                    <!-- Stock Status -->
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-500 mb-2">Stock Status</label>
                        <?php if ($product['quantity'] == 0): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                <i class="fas fa-times-circle mr-2"></i>Out of Stock
                            </span>
                        <?php elseif ($product['quantity'] <= $product['min_stock_level']): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                <i class="fas fa-exclamation-triangle mr-2"></i>Low Stock
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                <i class="fas fa-check-circle mr-2"></i>In Stock
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Stock Adjustment Form -->
            <div class="bg-white shadow-lg rounded-lg">
                <form method="POST" action="" id="stockForm">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Stock Adjustment</h2>
                    </div>
                    
                    <div class="p-6 space-y-6">
                        <!-- Adjustment Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                Adjustment Type <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <label class="relative">
                                    <input type="radio" name="adjustment_type" value="in" class="sr-only" required>
                                    <div class="adjustment-option border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-green-500 transition-colors">
                                        <div class="text-center">
                                            <i class="fas fa-plus-circle text-green-500 text-2xl mb-2"></i>
                                            <div class="font-medium text-gray-900">Stock In</div>
                                            <div class="text-sm text-gray-500">Add inventory</div>
                                        </div>
                                    </div>
                                </label>
                                
                                <label class="relative">
                                    <input type="radio" name="adjustment_type" value="out" class="sr-only" required>
                                    <div class="adjustment-option border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-red-500 transition-colors">
                                        <div class="text-center">
                                            <i class="fas fa-minus-circle text-red-500 text-2xl mb-2"></i>
                                            <div class="font-medium text-gray-900">Stock Out</div>
                                            <div class="text-sm text-gray-500">Remove inventory</div>
                                        </div>
                                    </div>
                                </label>
                                
                                <label class="relative">
                                    <input type="radio" name="adjustment_type" value="set" class="sr-only" required>
                                    <div class="adjustment-option border-2 border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-500 transition-colors">
                                        <div class="text-center">
                                            <i class="fas fa-cog text-blue-500 text-2xl mb-2"></i>
                                            <div class="font-medium text-gray-900">Set Stock</div>
                                            <div class="text-sm text-gray-500">Set exact amount</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Quantity -->
                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                                Quantity <span class="text-red-500">*</span>
                            </label>
                            <input type="number" 
                                   id="quantity" 
                                   name="quantity" 
                                   min="0" 
                                   required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg"
                                   placeholder="Enter quantity">
                            <div id="quantityHelp" class="text-sm text-gray-500 mt-1"></div>
                        </div>

                        <!-- Reason -->
                        <div>
                            <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">
                                Reason <span class="text-red-500">*</span>
                            </label>
                            <select id="reason" 
                                    name="reason" 
                                    required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Select a reason</option>
                                <option value="Sale">Sale</option>
                                <option value="Purchase">New Purchase</option>
                                <option value="Return">Customer Return</option>
                                <option value="Damage">Damage/Loss</option>
                                <option value="Theft">Theft</option>
                                <option value="Expired">Expired Products</option>
                                <option value="Transfer">Transfer</option>
                                <option value="Inventory Count">Inventory Count Adjustment</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                Additional Notes
                            </label>
                            <textarea id="notes" 
                                      name="notes" 
                                      rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Optional additional details..."></textarea>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                        <a href="<?php echo isAdmin() ? 'index.php' : 'view.php'; ?>" 
                           class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium">
                            Cancel
                        </a>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                            <i class="fas fa-save mr-2"></i>
                            Apply Adjustment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Stock Movements -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Recent Stock Movements</h2>
            </div>
            <div class="overflow-x-auto">
                <?php if ($movements_result && $movements_result->num_rows > 0): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Change</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($movement = $movements_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M j, H:i', strtotime($movement['created_at'])); ?>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php echo $movement['quantity_change'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo $movement['quantity_change'] > 0 ? '+' : ''; ?><?php echo $movement['quantity_change']; ?>
                                        <span class="text-gray-500 font-normal">
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
                        <p>No stock movements found for this product.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const adjustmentOptions = document.querySelectorAll('input[name="adjustment_type"]');
    const quantityInput = document.getElementById('quantity');
    const quantityHelp = document.getElementById('quantityHelp');
    const currentStock = <?php echo $product['quantity']; ?>;
    
    // Handle adjustment type selection
    adjustmentOptions.forEach(option => {
        option.addEventListener('change', function() {
            // Update visual selection
            adjustmentOptions.forEach(opt => {
                opt.nextElementSibling.classList.remove('border-green-500', 'border-red-500', 'border-blue-500', 'bg-green-50', 'bg-red-50', 'bg-blue-50');
                opt.nextElementSibling.classList.add('border-gray-200');
            });
            
            let borderColor, bgColor;
            switch(this.value) {
                case 'in':
                    borderColor = 'border-green-500';
                    bgColor = 'bg-green-50';
                    break;
                case 'out':
                    borderColor = 'border-red-500';
                    bgColor = 'bg-red-50';
                    break;
                case 'set':
                    borderColor = 'border-blue-500';
                    bgColor = 'bg-blue-50';
                    break;
            }
            
            this.nextElementSibling.classList.add(borderColor, bgColor);
            this.nextElementSibling.classList.remove('border-gray-200');
            
            updateQuantityHelp();
        });
    });
    
    // Update quantity help text
    function updateQuantityHelp() {
        const selectedType = document.querySelector('input[name="adjustment_type"]:checked');
        const quantity = parseInt(quantityInput.value) || 0;
        
        if (!selectedType) {
            quantityHelp.textContent = '';
            return;
        }
        
        let newStock;
        switch(selectedType.value) {
            case 'in':
                newStock = currentStock + quantity;
                quantityHelp.textContent = `New stock will be: ${newStock}`;
                quantityHelp.className = 'text-sm text-green-600 mt-1';
                break;
            case 'out':
                newStock = currentStock - quantity;
                if (quantity > currentStock) {
                    quantityHelp.textContent = `Cannot remove ${quantity} items (only ${currentStock} available)`;
                    quantityHelp.className = 'text-sm text-red-600 mt-1';
                } else {
                    quantityHelp.textContent = `New stock will be: ${newStock}`;
                    quantityHelp.className = 'text-sm text-red-600 mt-1';
                }
                break;
            case 'set':
                quantityHelp.textContent = `Stock will be set to: ${quantity}`;
                quantityHelp.className = 'text-sm text-blue-600 mt-1';
                break;
        }
    }
    
    quantityInput.addEventListener('input', updateQuantityHelp);
    
    // Form validation
    document.getElementById('stockForm').addEventListener('submit', function(e) {
        const selectedType = document.querySelector('input[name="adjustment_type"]:checked');
        const quantity = parseInt(quantityInput.value) || 0;
        
        if (!selectedType) {
            e.preventDefault();
            showAlert('Please select an adjustment type', 'error');
            return;
        }
        
        if (selectedType.value === 'out' && quantity > currentStock) {
            e.preventDefault();
            showAlert(`Cannot remove ${quantity} items (only ${currentStock} available)`, 'error');
            return;
        }
        
        if (!validateForm('stockForm')) {
            e.preventDefault();
            showAlert('Please fill in all required fields', 'error');
        }
    });
});
</script>

<style>
.adjustment-option input:checked + div {
    border-width: 2px;
}
</style>

<?php include '../includes/footer.php'; ?>
