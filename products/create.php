<?php
require_once '../includes/auth.php';
requireAdmin();

$page_title = 'Add New Product';
include '../includes/header.php';

$error = '';
$success = '';

// Get categories for dropdown
$categories_result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $sku = trim($_POST['sku']);
    $description = trim($_POST['description']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $quantity = intval($_POST['quantity']);
    $min_stock_level = intval($_POST['min_stock_level']);
    $price = floatval($_POST['price']);
    
    // Validation
    if (empty($name)) {
        $error = 'Product name is required.';
    } elseif (empty($sku)) {
        $error = 'SKU is required.';
    } elseif ($quantity < 0) {
        $error = 'Quantity cannot be negative.';
    } elseif ($min_stock_level < 0) {
        $error = 'Minimum stock level cannot be negative.';
    } elseif ($price < 0) {
        $error = 'Price cannot be negative.';
    } else {
        // Check if SKU already exists
        $check_stmt = $conn->prepare("SELECT id FROM products WHERE sku = ?");
        $check_stmt->bind_param("s", $sku);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'SKU already exists. Please use a different SKU.';
        } else {
            // Insert new product
            $stmt = $conn->prepare("INSERT INTO products (name, sku, description, category_id, quantity, min_stock_level, price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiidi", $name, $sku, $description, $category_id, $quantity, $min_stock_level, $price);
            
            if ($stmt->execute()) {
                $product_id = $conn->insert_id;
                
                // Log initial stock if quantity > 0
                if ($quantity > 0) {
                    logStockMovement($product_id, $_SESSION['user_id'], 'in', $quantity, 0, $quantity, 'Initial stock', 'Product created with initial inventory');
                }
                
                header('Location: index.php?success=' . urlencode('Product "' . $name . '" created successfully'));
                exit();
            } else {
                $error = 'Error creating product: ' . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}
?>

<div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center">
            <a href="index.php" class="text-gray-500 hover:text-gray-700 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Add New Product</h1>
                <p class="text-gray-600 mt-2">Create a new product in your inventory</p>
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
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
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
                               value="<?php echo isset($_POST['sku']) ? htmlspecialchars($_POST['sku']) : ''; ?>">
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
                              placeholder="Enter product description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>

                <!-- Category and Stock Information -->
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
                                            <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">
                            <a href="../categories/create.php" class="text-blue-600 hover:text-blue-800">Create new category</a>
                        </p>
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
                               value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>">
                    </div>
                </div>

                <!-- Stock Information -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Stock Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                                Initial Quantity
                            </label>
                            <input type="number" 
                                   id="quantity" 
                                   name="quantity" 
                                   min="0" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="0"
                                   value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '0'; ?>">
                            <p class="text-sm text-gray-500 mt-1">Current stock level</p>
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
                                   value="<?php echo isset($_POST['min_stock_level']) ? htmlspecialchars($_POST['min_stock_level']) : '10'; ?>">
                            <p class="text-sm text-gray-500 mt-1">Alert when stock goes below this level</p>
                        </div>
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
                    Create Product
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-generate SKU based on product name
    const nameInput = document.getElementById('name');
    const skuInput = document.getElementById('sku');
    
    nameInput.addEventListener('input', function() {
        if (skuInput.value === '') {
            const name = this.value.trim();
            if (name) {
                // Generate SKU: First 3 letters + random 3 digits
                const prefix = name.substring(0, 3).toUpperCase().replace(/[^A-Z]/g, '');
                const suffix = Math.floor(Math.random() * 900) + 100;
                skuInput.value = prefix + suffix.toString().padStart(3, '0');
            }
        }
    });
    
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
