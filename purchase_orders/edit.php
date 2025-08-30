<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db_connection.php';

$po_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$po_id) {
    $_SESSION['error'] = "Purchase order ID is required.";
    header('Location: index.php');
    exit;
}

// Get purchase order details
$stmt = $conn->prepare("SELECT * FROM purchase_orders WHERE id = ? AND status IN ('draft', 'sent')");
$stmt->bind_param("i", $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    $_SESSION['error'] = "Purchase order not found or cannot be edited.";
    header('Location: index.php');
    exit;
}

$page_title = "Edit Purchase Order - " . $po['po_number'];
$current_page = 'purchase_orders';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Basic validation and update
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $order_date = $_POST['order_date'] ?? '';
    $expected_delivery = $_POST['expected_delivery'] ?? null;
    $notes = trim($_POST['notes'] ?? '');

    if ($supplier_id <= 0) {
        $errors[] = "Please select a supplier.";
    }
    
    if (empty($order_date)) {
        $errors[] = "Order date is required.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE purchase_orders SET supplier_id = ?, order_date = ?, expected_delivery = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("isssi", $supplier_id, $order_date, $expected_delivery, $notes, $po_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Purchase order updated successfully!";
            header("Location: view.php?id=$po_id");
            exit;
        } else {
            $errors[] = "Error updating purchase order.";
        }
    }
} else {
    // Pre-populate form
    $_POST = $po;
}

// Get suppliers
$suppliers = $conn->query("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
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
                <a href="view.php?id=<?= $po_id ?>" class="text-gray-500 hover:text-gray-700 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        <i class="fas fa-edit text-blue-600 mr-3"></i>Edit Purchase Order
                    </h1>
                    <p class="text-gray-600 mt-2"><?= htmlspecialchars($po['po_number']) ?></p>
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

        <!-- Warning -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-yellow-600 mt-1 mr-3"></i>
                <div>
                    <h3 class="font-medium text-yellow-800">Limited Editing</h3>
                    <p class="mt-1 text-yellow-700 text-sm">
                        You can only edit basic order information. To modify items, cancel this order and create a new one.
                        Orders that have been confirmed or received cannot be edited.
                    </p>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="bg-white rounded-lg shadow p-6">
            <form method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 border-b border-gray-200 pb-2">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>Order Information
                        </h3>
                    </div>

                    <!-- Supplier -->
                    <div class="md:col-span-2">
                        <label for="supplier_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Supplier <span class="text-red-500">*</span>
                        </label>
                        <select id="supplier_id" name="supplier_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select a supplier</option>
                            <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $supplier['id'] ?>" <?= $supplier['id'] == $po['supplier_id'] ? 'selected' : '' ?>>
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
                               value="<?= htmlspecialchars($po['order_date']) ?>"
                               max="<?= date('Y-m-d') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <!-- Expected Delivery -->
                    <div>
                        <label for="expected_delivery" class="block text-sm font-medium text-gray-700 mb-2">
                            Expected Delivery
                        </label>
                        <input type="date" id="expected_delivery" name="expected_delivery"
                               value="<?= htmlspecialchars($po['expected_delivery']) ?>"
                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <!-- Notes -->
                    <div class="md:col-span-2">
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                            Notes
                        </label>
                        <textarea id="notes" name="notes" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Additional notes for this purchase order..."><?= htmlspecialchars($po['notes']) ?></textarea>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                    <a href="view.php?id=<?= $po_id ?>" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 border border-transparent rounded-md text-white hover:bg-blue-700">
                        <i class="fas fa-save mr-2"></i>Update Purchase Order
                    </button>
                </div>
            </form>
        </div>

        <!-- Current Order Items (Read-only) -->
        <?php
        $items_stmt = $conn->prepare("
            SELECT poi.*, p.name as product_name, p.sku 
            FROM purchase_order_items poi
            LEFT JOIN products p ON poi.product_id = p.id
            WHERE poi.po_id = ?
            ORDER BY p.name
        ");
        $items_stmt->bind_param("i", $po_id);
        $items_stmt->execute();
        $items = $items_stmt->get_result();
        ?>
        
        <?php if ($items->num_rows > 0): ?>
        <div class="bg-white rounded-lg shadow mt-6">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                    <i class="fas fa-box text-purple-600 mr-2"></i>Current Order Items
                    <span class="text-sm text-gray-500 ml-2">(Read-only)</span>
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Unit Cost</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php $total_amount = 0; ?>
                        <?php while ($item = $items->fetch_assoc()): 
                            $total_amount += $item['total_cost'];
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($item['product_name']) ?>
                                </div>
                                <?php if ($item['sku']): ?>
                                <div class="text-sm text-gray-500">SKU: <?= htmlspecialchars($item['sku']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                <?= number_format($item['quantity']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                LKR <?= number_format($item['unit_cost'], 2) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                LKR <?= number_format($item['total_cost'], 2) ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <th colspan="3" class="px-6 py-3 text-left text-sm font-medium text-gray-900">
                                Total Amount:
                            </th>
                            <th class="px-6 py-3 text-left text-sm font-medium text-blue-600">
                                LKR <?= number_format($total_amount, 2) ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
