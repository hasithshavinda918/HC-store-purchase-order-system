<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db_connection.php';

$page_title = "Add New Supplier";
$current_page = 'suppliers';

$errors = [];
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'country' => trim($_POST['country'] ?? 'Sri Lanka'),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];

    // Validation
    if (empty($form_data['name'])) {
        $errors[] = "Supplier name is required.";
    }
    
    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // Check for duplicate supplier name
    $check_stmt = $conn->prepare("SELECT id FROM suppliers WHERE LOWER(name) = LOWER(?)");
    $check_stmt->bind_param("s", $form_data['name']);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $errors[] = "A supplier with this name already exists.";
    }
    
    // Check for duplicate email if provided
    if (!empty($form_data['email'])) {
        $check_stmt = $conn->prepare("SELECT id FROM suppliers WHERE email = ?");
        $check_stmt->bind_param("s", $form_data['email']);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "A supplier with this email already exists.";
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO suppliers (name, contact_person, email, phone, address, city, postal_code, country, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssi", 
            $form_data['name'], 
            $form_data['contact_person'], 
            $form_data['email'], 
            $form_data['phone'], 
            $form_data['address'], 
            $form_data['city'], 
            $form_data['postal_code'], 
            $form_data['country'], 
            $form_data['is_active']
        );
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Supplier '{$form_data['name']}' has been added successfully!";
            header('Location: index.php');
            exit;
        } else {
            $errors[] = "Error creating supplier. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - HC Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include '../includes/admin_navbar.php'; ?>
    
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center">
                <a href="index.php" class="text-gray-500 hover:text-gray-700 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        <i class="fas fa-plus-circle text-green-600 mr-3"></i><?= $page_title ?>
                    </h1>
                    <p class="text-gray-600 mt-2">Add a new supplier to your system</p>
                </div>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <div class="font-bold">Please fix the following errors:</div>
            <ul class="list-disc list-inside mt-2">
                <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Supplier Form -->
        <div class="bg-white rounded-lg shadow">
            <form method="POST" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="md:col-span-2">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b border-gray-200 pb-2">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>Basic Information
                        </h3>
                    </div>

                    <!-- Supplier Name -->
                    <div class="md:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Supplier Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="name" name="name" required
                               value="<?= htmlspecialchars($form_data['name'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Enter supplier/company name">
                    </div>

                    <!-- Contact Person -->
                    <div>
                        <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-2">
                            Contact Person
                        </label>
                        <input type="text" id="contact_person" name="contact_person"
                               value="<?= htmlspecialchars($form_data['contact_person'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Primary contact person name">
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <div class="flex items-center">
                            <input type="checkbox" id="is_active" name="is_active" 
                                   <?= ($form_data['is_active'] ?? 1) ? 'checked' : '' ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_active" class="ml-2 text-sm text-gray-700">
                                Active (supplier can receive purchase orders)
                            </label>
                        </div>
                    </div>

                    <!-- Contact Details Section -->
                    <div class="md:col-span-2">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b border-gray-200 pb-2 mt-6">
                            <i class="fas fa-address-book text-green-600 mr-2"></i>Contact Details
                        </h3>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                        </label>
                        <input type="email" id="email" name="email"
                               value="<?= htmlspecialchars($form_data['email'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="supplier@example.com">
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number
                        </label>
                        <input type="tel" id="phone" name="phone"
                               value="<?= htmlspecialchars($form_data['phone'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="077-123-4567">
                    </div>

                    <!-- Address Details Section -->
                    <div class="md:col-span-2">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b border-gray-200 pb-2 mt-6">
                            <i class="fas fa-map-marker-alt text-purple-600 mr-2"></i>Address Details
                        </h3>
                    </div>

                    <!-- Address -->
                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                            Street Address
                        </label>
                        <textarea id="address" name="address" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Enter street address"><?= htmlspecialchars($form_data['address'] ?? '') ?></textarea>
                    </div>

                    <!-- City -->
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 mb-2">
                            City
                        </label>
                        <input type="text" id="city" name="city"
                               value="<?= htmlspecialchars($form_data['city'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Colombo, Kandy, Galle, etc.">
                    </div>

                    <!-- Postal Code -->
                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-2">
                            Postal Code
                        </label>
                        <input type="text" id="postal_code" name="postal_code"
                               value="<?= htmlspecialchars($form_data['postal_code'] ?? '') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="00100, 10370, etc.">
                    </div>

                    <!-- Country -->
                    <div class="md:col-span-2">
                        <label for="country" class="block text-sm font-medium text-gray-700 mb-2">
                            Country
                        </label>
                        <select id="country" name="country"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="Sri Lanka" <?= ($form_data['country'] ?? 'Sri Lanka') === 'Sri Lanka' ? 'selected' : '' ?>>Sri Lanka</option>
                            <option value="India" <?= ($form_data['country'] ?? '') === 'India' ? 'selected' : '' ?>>India</option>
                            <option value="Bangladesh" <?= ($form_data['country'] ?? '') === 'Bangladesh' ? 'selected' : '' ?>>Bangladesh</option>
                            <option value="Pakistan" <?= ($form_data['country'] ?? '') === 'Pakistan' ? 'selected' : '' ?>>Pakistan</option>
                            <option value="Other" <?= ($form_data['country'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                    <a href="index.php" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 border border-transparent rounded-md text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i>Save Supplier
                    </button>
                </div>
            </form>
        </div>

        <!-- Help Section -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mt-6">
            <h3 class="text-lg font-medium text-blue-800 mb-3">
                <i class="fas fa-lightbulb mr-2"></i>Tips for Adding Suppliers
            </h3>
            <ul class="text-blue-700 space-y-2">
                <li><i class="fas fa-check-circle mr-2"></i>Use the full official company name for better record keeping</li>
                <li><i class="fas fa-check-circle mr-2"></i>Ensure contact information is accurate for smooth communication</li>
                <li><i class="fas fa-check-circle mr-2"></i>Keep supplier information updated to maintain good relationships</li>
                <li><i class="fas fa-check-circle mr-2"></i>Inactive suppliers won't appear in new purchase order forms</li>
            </ul>
        </div>
    </div>

    <script>
    // Auto-format phone number
    document.getElementById('phone').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 10) {
            value = value.substring(0, 10);
            e.target.value = value.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
        } else {
            e.target.value = value;
        }
    });
    
    // Auto-format postal code
    document.getElementById('postal_code').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 5) {
            value = value.substring(0, 5);
        }
        e.target.value = value;
    });
    </script>
</body>
</html>
