<?php
require_once '../includes/auth.php';
requireAdmin();

$page_title = 'Add New Category';
include '../includes/header.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    
    // Validation
    if (empty($name)) {
        $error = 'Category name is required.';
    } else {
        // Check if category name already exists
        $check_stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $check_stmt->bind_param("s", $name);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Category name already exists. Please use a different name.';
        } else {
            // Insert new category
            $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $description);
            
            if ($stmt->execute()) {
                header('Location: index.php?success=' . urlencode('Category "' . $name . '" created successfully'));
                exit();
            } else {
                $error = 'Error creating category: ' . $conn->error;
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
                <h1 class="text-3xl font-bold text-gray-900">Add New Category</h1>
                <p class="text-gray-600 mt-2">Create a new category to organize your products</p>
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

    <!-- Category Form -->
    <div class="bg-white shadow-lg rounded-lg">
        <form method="POST" action="" id="categoryForm">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Category Information</h2>
            </div>
            
            <div class="p-6 space-y-6">
                <!-- Category Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        Category Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           required 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter category name"
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    <p class="text-sm text-gray-500 mt-1">Choose a unique name for this category</p>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea id="description" 
                              name="description" 
                              rows="4" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Enter category description (optional)"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <p class="text-sm text-gray-500 mt-1">Briefly describe what products belong to this category</p>
                </div>

                <!-- Category Examples -->
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-blue-900 mb-2">Category Examples:</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm text-blue-700">
                        <div>• Electronics</div>
                        <div>• Clothing</div>
                        <div>• Home & Garden</div>
                        <div>• Sports & Outdoor</div>
                        <div>• Books & Media</div>
                        <div>• Food & Beverages</div>
                        <div>• Health & Beauty</div>
                        <div>• Automotive</div>
                        <div>• Toys & Games</div>
                    </div>
                </div>

                <!-- Preview -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-sm font-medium text-gray-900 mb-3">Preview:</h3>
                    <div class="bg-white border rounded-lg p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 id="previewName" class="text-lg font-semibold text-gray-900">Category Name</h4>
                                <p id="previewDescription" class="text-gray-600 text-sm mt-1">Category description will appear here</p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                0 Products
                            </span>
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
                    Create Category
                </button>
            </div>
        </form>
    </div>

    <!-- Tips -->
    <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-yellow-900 mb-3">
            <i class="fas fa-lightbulb mr-2"></i>Tips for Creating Categories
        </h3>
        <ul class="text-yellow-800 space-y-2">
            <li class="flex items-start">
                <i class="fas fa-check text-yellow-600 mr-2 mt-1 text-xs"></i>
                <span>Use clear, descriptive names that make sense to your team</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-yellow-600 mr-2 mt-1 text-xs"></i>
                <span>Keep categories broad enough to include multiple products</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-yellow-600 mr-2 mt-1 text-xs"></i>
                <span>Avoid creating too many categories - aim for 5-15 main categories</span>
            </li>
            <li class="flex items-start">
                <i class="fas fa-check text-yellow-600 mr-2 mt-1 text-xs"></i>
                <span>You can always edit or reorganize categories later</span>
            </li>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('name');
    const descriptionInput = document.getElementById('description');
    const previewName = document.getElementById('previewName');
    const previewDescription = document.getElementById('previewDescription');
    
    // Update preview as user types
    function updatePreview() {
        const name = nameInput.value.trim() || 'Category Name';
        const description = descriptionInput.value.trim() || 'Category description will appear here';
        
        previewName.textContent = name;
        previewDescription.textContent = description;
    }
    
    nameInput.addEventListener('input', updatePreview);
    descriptionInput.addEventListener('input', updatePreview);
    
    // Form validation
    document.getElementById('categoryForm').addEventListener('submit', function(e) {
        if (!validateForm('categoryForm')) {
            e.preventDefault();
            showAlert('Please fill in all required fields', 'error');
        }
    });
    
    // Focus on name field when page loads
    nameInput.focus();
});
</script>

<?php include '../includes/footer.php'; ?>
