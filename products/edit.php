<?php
require_once '../includes/auth.php';
requireAdmin();

$error = '';
$success = '';

// Get product ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?error=' . urlencode('Product not found'));
    exit();
}

$product_id = intval($_GET['id']);

// Get product details
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php?error=' . urlencode('Product not found'));
    exit();
}

$product = $result->fetch_assoc();
$stmt->close();

// Get categories for dropdown
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $sku = trim($_POST['sku']);
    $description = trim($_POST['description']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $min_stock_level = intval($_POST['min_stock_level']);
    $price = floatval($_POST['price']);
    
    // Validation
    if (empty($name)) {
        $error = 'Product name is required.';
    } elseif (empty($sku)) {
        $error = 'SKU is required.';
    } elseif ($min_stock_level < 0) {
        $error = 'Minimum stock level cannot be negative.';
    } elseif ($price < 0) {
        $error = 'Price cannot be negative.';
    } else {
        // Check if SKU already exists for other products
        $check_stmt = $conn->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $check_stmt->bind_param("si", $sku, $product_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'SKU already exists. Please use a different SKU.';
        } else {
            // Update product
            $update_stmt = $conn->prepare("UPDATE products SET name = ?, sku = ?, description = ?, category_id = ?, min_stock_level = ?, price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update_stmt->bind_param("sssiidi", $name, $sku, $description, $category_id, $min_stock_level, $price, $product_id);
            
            if ($update_stmt->execute()) {
                header('Location: index.php?success=' . urlencode('Product "' . $name . '" updated successfully'));
                exit();
            } else {
                $error = 'Error updating product: ' . $conn->error;
            }
            $update_stmt->close();
        }
        $check_stmt->close();
    }
} else {
    // Pre-fill form with existing data
    $_POST = $product;
}

// Set page title and include header after all redirects
$page_title = 'Edit Product';
include '../includes/header.php';
?>

<div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center">
            <a href="index.php" class="text-gray-500 hover:text-gray-700 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Edit Product</h1>
                <p class="text-gray-600 mt-2">Update product information</p>
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

    <!-- Product Form -->
    <div class="bg-white shadow-lg rounded-lg">
        <form method="POST" action="" id="productForm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Product Information</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Created: <?php echo date('M j, Y g:i A', strtotime($product['created_at'])); ?>
                    <?php if ($product['updated_at'] != $product['created_at']): ?>
                        | Last updated: <?php echo date('M j, Y g:i A', strtotime($product['updated_at'])); ?>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Basic Information Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Product Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter product name"
                               value="<?php echo htmlspecialchars($_POST['name']); ?>">
                    </div>

                    <div>
                        <label for="sku" class="block text-sm font-medium text-gray-700 mb-2">
                            SKU (Stock Keeping Unit) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="sku" 
                               name="sku" 
                               required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="e.g., ABC001"
                               value="<?php echo htmlspecialchars($_POST['sku']); ?>">
                        <p class="text-sm text-gray-500 mt-1">Must be unique for each product</p>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea id="description" 
                              name="description" 
                              rows="3" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Enter product description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <!-- Category and Price -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Category
                        </label>
                        <select id="category_id" 
                                name="category_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select a category</option>
                            <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                                <?php while ($category = $categories_result->fetch_assoc()): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                            Price (LKR)
                        </label>
                        <input type="number" 
                               id="price" 
                               name="price" 
                               step="0.01" 
                               min="0" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="0.00"
                               value="<?php echo htmlspecialchars($_POST['price']); ?>">
                    </div>
                </div>

                <!-- Stock Information -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Stock Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Current Quantity
                            </label>
                            <div class="flex items-center">
                                <input type="text" 
                                       value="<?php echo $product['quantity']; ?>" 
                                       readonly 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                                <a href="stock_adjust.php?id=<?php echo $product_id; ?>" 
                                   class="ml-3 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                                    Adjust Stock
                                </a>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">Use "Adjust Stock" to change quantity</p>
                        </div>

                        <div>
                            <label for="min_stock_level" class="block text-sm font-medium text-gray-700 mb-2">
                                Minimum Stock Level
                            </label>
                            <input type="number" 
                                   id="min_stock_level" 
                                   name="min_stock_level" 
                                   min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="10"
                                   value="<?php echo htmlspecialchars($_POST['min_stock_level']); ?>">
                            <p class="text-sm text-gray-500 mt-1">Alert when stock goes below this level</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Quick Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <a href="stock_adjust.php?id=<?php echo $product_id; ?>" 
                           class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-center">
                            <i class="fas fa-boxes mr-2"></i>Adjust Stock
                        </a>
                        <a href="../reports/product_history.php?id=<?php echo $product_id; ?>" 
                           class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-center">
                            <i class="fas fa-history mr-2"></i>View History
                        </a>
                        <a href="?delete=<?php echo $product_id; ?>" 
                           onclick="return confirmDelete('Are you sure you want to delete this product?')"
                           class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-center">
                            <i class="fas fa-trash mr-2"></i>Delete Product
                        </a>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium">
                    Cancel
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                    <i class="fas fa-save mr-2"></i>
                    Update Product
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    document.getElementById('productForm').addEventListener('submit', function(e) {
        if (!validateForm('productForm')) {
            e.preventDefault();
            showAlert('Please fill in all required fields', 'error');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
