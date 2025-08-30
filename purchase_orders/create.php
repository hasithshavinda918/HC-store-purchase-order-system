<?php
require_once '../includes/auth.php';
require_once '../includes/language.php';
requireAdmin();
require_once '../includes/db_connection.php';

$page_title = "Create Purchase Order";
$current_page = 'purchase_orders';

$errors = [];
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate form data
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $order_date = $_POST['order_date'] ?? '';
    $expected_delivery = $_POST['expected_delivery'] ?? null;
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    // Basic validation
    if ($supplier_id <= 0) {
        $errors[] = "Please select a supplier.";
    }
    
    if (empty($order_date)) {
        $errors[] = "Order date is required.";
    } elseif (strtotime($order_date) > time()) {
        $errors[] = "Order date cannot be in the future.";
    }
    
    if (!empty($expected_delivery) && strtotime($expected_delivery) <= strtotime($order_date)) {
        $errors[] = "Expected delivery date must be after order date.";
    }
    
    if (empty($items) || count($items) === 0) {
        $errors[] = "Please add at least one item to the purchase order.";
    }
    
    // Validate items
    $valid_items = [];
    $total_amount = 0;
    
    foreach ($items as $item) {
        if (empty($item['product_id']) || empty($item['quantity']) || empty($item['unit_cost'])) {
            continue;
        }
        
        $product_id = intval($item['product_id']);
        $quantity = intval($item['quantity']);
        $unit_cost = floatval($item['unit_cost']);
        
        if ($product_id > 0 && $quantity > 0 && $unit_cost > 0) {
            $total_cost = $quantity * $unit_cost;
            $valid_items[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_cost' => $unit_cost,
                'total_cost' => $total_cost
            ];
            $total_amount += $total_cost;
        }
    }
    
    if (empty($valid_items)) {
        $errors[] = "Please add valid items with proper quantities and costs.";
    }

    if (empty($errors)) {
        // Generate PO number
        $po_number = 'PO-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Check for duplicate PO number
        while (true) {
            $check_stmt = $conn->prepare("SELECT id FROM purchase_orders WHERE po_number = ?");
            $check_stmt->bind_param("s", $po_number);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows === 0) {
                break;
            }
            $po_number = 'PO-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Insert purchase order
            $stmt = $conn->prepare("INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery, notes, total_amount, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisssd", $po_number, $supplier_id, $order_date, $expected_delivery, $notes, $total_amount, $_SESSION['user_id']);
            $stmt->execute();
            $po_id = $conn->insert_id;
            
            // Insert purchase order items
            $item_stmt = $conn->prepare("INSERT INTO purchase_order_items (po_id, product_id, quantity, unit_cost, total_cost) VALUES (?, ?, ?, ?, ?)");
            foreach ($valid_items as $item) {
                $item_stmt->bind_param("iiidd", $po_id, $item['product_id'], $item['quantity'], $item['unit_cost'], $item['total_cost']);
                $item_stmt->execute();
            }
            
            $conn->commit();
            $_SESSION['success'] = "Purchase order {$po_number} has been created successfully!";
            header("Location: view.php?id={$po_id}");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error creating purchase order: " . $e->getMessage();
        }
    }
}

// Get suppliers for dropdown
$suppliers = $conn->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");

// Get products for dropdown
$products = $conn->query("SELECT id, name, price FROM products ORDER BY name");
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
    
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
                    <p class="text-gray-600 mt-2">Create a new purchase order for inventory procurement</p>
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

        <!-- Purchase Order Form -->
        <form method="POST" id="poForm">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Form -->
                <div class="lg:col-span-2">
                    <!-- Order Information -->
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b border-gray-200 pb-2">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>Order Information
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Supplier -->
                            <div class="md:col-span-2">
                                <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Supplier <span class="text-red-500">*</span>
                                </label>
                                <select id="supplier_id" name="supplier_id" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select a supplier</option>
                                    <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                                    <option value="<?= $supplier['id'] ?>" <?= (isset($_POST['supplier_id']) && $_POST['supplier_id'] == $supplier['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['name']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <!-- Order Date -->
                            <div>
                                <label for="order_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Order Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="order_date" name="order_date" required
                                       value="<?= $_POST['order_date'] ?? date('Y-m-d') ?>"
                                       max="<?= date('Y-m-d') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <!-- Expected Delivery -->
                            <div>
                                <label for="expected_delivery" class="block text-sm font-medium text-gray-700 mb-2">
                                    Expected Delivery
                                </label>
                                <input type="date" id="expected_delivery" name="expected_delivery"
                                       value="<?= $_POST['expected_delivery'] ?? '' ?>"
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <!-- Notes -->
                            <div class="md:col-span-2">
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                    Notes
                                </label>
                                <textarea id="notes" name="notes" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Additional notes for this purchase order..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex justify-between items-center mb-4 border-b border-gray-200 pb-2">
                            <h3 class="text-lg font-medium text-gray-900">
                                <i class="fas fa-box text-green-600 mr-2"></i>Order Items
                            </h3>
                            <button type="button" id="addItemBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm">
                                <i class="fas fa-plus mr-2"></i>Add Item
                            </button>
                        </div>
                        
                        <div id="itemsContainer">
                            <!-- Items will be added here dynamically -->
                        </div>
                        
                        <div id="noItems" class="text-center text-gray-500 py-8">
                            <i class="fas fa-box-open text-4xl text-gray-300 mb-4 block"></i>
                            No items added yet. Click "Add Item" to start building your purchase order.
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow p-6 sticky top-8">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b border-gray-200 pb-2">
                            <i class="fas fa-calculator text-purple-600 mr-2"></i>Order Summary
                        </h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Total Items:</span>
                                <span id="totalItems" class="font-medium">0</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Total Quantity:</span>
                                <span id="totalQuantity" class="font-medium">0</span>
                            </div>
                            <hr>
                            <div class="flex justify-between text-lg font-bold">
                                <span class="text-gray-900">Total Amount:</span>
                                <span id="totalAmount" class="text-blue-600">LKR 0.00</span>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mt-8 space-y-3">
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                                <i class="fas fa-save mr-2"></i>Create Purchase Order
                            </button>
                            <a href="index.php" class="w-full bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-3 rounded-lg font-medium text-center block">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                        </div>

                        <!-- Help -->
                        <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                            <h4 class="text-sm font-medium text-blue-800 mb-2">
                                <i class="fas fa-info-circle mr-1"></i>Tips
                            </h4>
                            <ul class="text-xs text-blue-700 space-y-1">
                                <li>• Add all items before creating the order</li>
                                <li>• Double-check quantities and prices</li>
                                <li>• Use notes for special instructions</li>
                                <li>• Orders can be edited until sent</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Item Row Template -->
    <template id="itemTemplate">
        <div class="item-row border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Product</label>
                    <select class="product-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select product</option>
                        <?php
                        // Reset products query for template
                        $products = $conn->query("SELECT id, name, price FROM products ORDER BY name");
                        while ($product = $products->fetch_assoc()):
                        ?>
                        <option value="<?= $product['id'] ?>" data-price="<?= $product['price'] ?>">
                            <?= htmlspecialchars($product['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                    <input type="number" class="quantity-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                           min="1" required placeholder="0">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Unit Cost (LKR)</label>
                    <input type="number" class="unit-cost-input w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                           min="0.01" step="0.01" required placeholder="0.00">
                </div>
                <div class="flex items-end">
                    <div class="w-full">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Total</label>
                        <div class="total-cost text-lg font-bold text-blue-600 px-3 py-2 bg-white border border-gray-300 rounded-md">
                            LKR 0.00
                        </div>
                    </div>
                    <button type="button" class="remove-item ml-3 text-red-600 hover:text-red-800 p-2">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <script>
    let itemCounter = 0;

    document.addEventListener('DOMContentLoaded', function() {
        const addItemBtn = document.getElementById('addItemBtn');
        const itemsContainer = document.getElementById('itemsContainer');
        const noItems = document.getElementById('noItems');
        
        addItemBtn.addEventListener('click', addItem);
        
        // Add initial item
        addItem();
    });

    function addItem() {
        const template = document.getElementById('itemTemplate');
        const clone = template.content.cloneNode(true);
        const itemsContainer = document.getElementById('itemsContainer');
        const noItems = document.getElementById('noItems');
        
        // Set unique names for form submission
        const productSelect = clone.querySelector('.product-select');
        const quantityInput = clone.querySelector('.quantity-input');
        const unitCostInput = clone.querySelector('.unit-cost-input');
        
        productSelect.name = `items[${itemCounter}][product_id]`;
        quantityInput.name = `items[${itemCounter}][quantity]`;
        unitCostInput.name = `items[${itemCounter}][unit_cost]`;
        
        // Add event listeners
        productSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.dataset.price) {
                unitCostInput.value = selectedOption.dataset.price;
                calculateRowTotal(this.closest('.item-row'));
            }
        });
        
        quantityInput.addEventListener('input', function() {
            calculateRowTotal(this.closest('.item-row'));
        });
        
        unitCostInput.addEventListener('input', function() {
            calculateRowTotal(this.closest('.item-row'));
        });
        
        clone.querySelector('.remove-item').addEventListener('click', function() {
            removeItem(this.closest('.item-row'));
        });
        
        itemsContainer.appendChild(clone);
        noItems.style.display = 'none';
        itemCounter++;
        
        updateSummary();
    }

    function removeItem(itemRow) {
        const itemsContainer = document.getElementById('itemsContainer');
        const noItems = document.getElementById('noItems');
        
        itemRow.remove();
        
        if (itemsContainer.children.length === 0) {
            noItems.style.display = 'block';
        }
        
        updateSummary();
    }

    function calculateRowTotal(itemRow) {
        const quantityInput = itemRow.querySelector('.quantity-input');
        const unitCostInput = itemRow.querySelector('.unit-cost-input');
        const totalCostDiv = itemRow.querySelector('.total-cost');
        
        const quantity = parseFloat(quantityInput.value) || 0;
        const unitCost = parseFloat(unitCostInput.value) || 0;
        const total = quantity * unitCost;
        
        totalCostDiv.textContent = `LKR ${total.toFixed(2)}`;
        
        updateSummary();
    }

    function updateSummary() {
        const itemRows = document.querySelectorAll('.item-row');
        let totalItems = 0;
        let totalQuantity = 0;
        let totalAmount = 0;
        
        itemRows.forEach(row => {
            const productSelect = row.querySelector('.product-select');
            const quantityInput = row.querySelector('.quantity-input');
            const unitCostInput = row.querySelector('.unit-cost-input');
            
            if (productSelect.value && quantityInput.value && unitCostInput.value) {
                totalItems++;
                totalQuantity += parseInt(quantityInput.value) || 0;
                totalAmount += (parseInt(quantityInput.value) || 0) * (parseFloat(unitCostInput.value) || 0);
            }
        });
        
        document.getElementById('totalItems').textContent = totalItems;
        document.getElementById('totalQuantity').textContent = totalQuantity;
        document.getElementById('totalAmount').textContent = `LKR ${totalAmount.toFixed(2)}`;
    }

    // Form validation
    document.getElementById('poForm').addEventListener('submit', function(e) {
        const itemRows = document.querySelectorAll('.item-row');
        let hasValidItems = false;
        
        itemRows.forEach(row => {
            const productSelect = row.querySelector('.product-select');
            const quantityInput = row.querySelector('.quantity-input');
            const unitCostInput = row.querySelector('.unit-cost-input');
            
            if (productSelect.value && quantityInput.value && unitCostInput.value) {
                hasValidItems = true;
            }
        });
        
        if (!hasValidItems) {
            e.preventDefault();
            alert('Please add at least one item with valid product, quantity, and unit cost.');
        }
    });
    </script>
</body>
</html>
