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
$stmt = $conn->prepare("
    SELECT po.*, s.name as supplier_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.id = ? AND po.status IN ('confirmed', 'partially_received')
");
$stmt->bind_param("i", $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    $_SESSION['error'] = "Purchase order not found or not ready for receiving.";
    header('Location: index.php');
    exit;
}

// Get purchase order items that still need to be received
$items_stmt = $conn->prepare("
    SELECT poi.*, p.name as product_name, p.sku, c.name as category_name,
           (poi.quantity - poi.received_quantity) as pending_quantity
    FROM purchase_order_items poi
    LEFT JOIN products p ON poi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE poi.po_id = ? AND (poi.quantity > poi.received_quantity)
    ORDER BY p.name
");
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items = $items_stmt->get_result();

$page_title = "Receive Items - " . $po['po_number'];
$current_page = 'purchase_orders';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $received_items = $_POST['received_items'] ?? [];
    $valid_receipts = [];
    
    // Validate received items
    foreach ($received_items as $item_id => $received_qty) {
        $item_id = intval($item_id);
        $received_qty = intval($received_qty);
        
        if ($received_qty <= 0) continue;
        
        // Get current item details
        $item_check = $conn->prepare("SELECT poi.*, p.name as product_name, (poi.quantity - poi.received_quantity) as pending_quantity FROM purchase_order_items poi LEFT JOIN products p ON poi.product_id = p.id WHERE poi.id = ? AND poi.po_id = ?");
        $item_check->bind_param("ii", $item_id, $po_id);
        $item_check->execute();
        $item_data = $item_check->get_result()->fetch_assoc();
        
        if (!$item_data) {
            $errors[] = "Invalid item ID: $item_id";
            continue;
        }
        
        if ($received_qty > $item_data['pending_quantity']) {
            $errors[] = "Cannot receive {$received_qty} of {$item_data['product_name']}. Only {$item_data['pending_quantity']} pending.";
            continue;
        }
        
        $valid_receipts[] = [
            'item_id' => $item_id,
            'product_id' => $item_data['product_id'],
            'received_qty' => $received_qty,
            'product_name' => $item_data['product_name']
        ];
    }
    
    if (empty($valid_receipts)) {
        $errors[] = "Please specify quantities to receive for at least one item.";
    }

    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update purchase order items
            foreach ($valid_receipts as $receipt) {
                // Update received quantity
                $update_stmt = $conn->prepare("UPDATE purchase_order_items SET received_quantity = received_quantity + ? WHERE id = ?");
                $update_stmt->bind_param("ii", $receipt['received_qty'], $receipt['item_id']);
                $update_stmt->execute();
                
                // Update product stock
                $stock_stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                $stock_stmt->bind_param("ii", $receipt['received_qty'], $receipt['product_id']);
                $stock_stmt->execute();
                
                // Add stock movement record
                $movement_stmt = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, reference, notes, created_by, po_id) VALUES (?, 'in', ?, ?, ?, ?, ?)");
                $reference = "PO Received: " . $po['po_number'];
                $notes = "Received from supplier: " . $po['supplier_name'];
                $movement_stmt->bind_param("iissii", $receipt['product_id'], $receipt['received_qty'], $reference, $notes, $_SESSION['user_id'], $po_id);
                $movement_stmt->execute();
            }
            
            // Check if all items are fully received
            $check_stmt = $conn->prepare("SELECT COUNT(*) FROM purchase_order_items WHERE po_id = ? AND quantity > received_quantity");
            $check_stmt->bind_param("i", $po_id);
            $check_stmt->execute();
            $pending_items = $check_stmt->get_result()->fetch_row()[0];
            
            // Update PO status
            if ($pending_items == 0) {
                // All items received
                $status_stmt = $conn->prepare("UPDATE purchase_orders SET status = 'received', received_by = ?, received_date = NOW() WHERE id = ?");
                $status_stmt->bind_param("ii", $_SESSION['user_id'], $po_id);
                $new_status = 'received';
            } else {
                // Partially received
                $status_stmt = $conn->prepare("UPDATE purchase_orders SET status = 'partially_received' WHERE id = ?");
                $status_stmt->bind_param("i", $po_id);
                $new_status = 'partially_received';
            }
            $status_stmt->execute();
            
            $conn->commit();
            
            $total_received = array_sum(array_column($valid_receipts, 'received_qty'));
            $_SESSION['success'] = "Successfully received $total_received items. Purchase order status updated to " . ucfirst(str_replace('_', ' ', $new_status));
            header("Location: view.php?id=$po_id");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error processing receipt: " . $e->getMessage();
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
    
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center">
                <a href="view.php?id=<?= $po_id ?>" class="text-gray-500 hover:text-gray-700 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        <i class="fas fa-box-open text-green-600 mr-3"></i>Receive Items
                    </h1>
                    <p class="text-gray-600 mt-2">
                        Purchase Order: <span class="font-medium"><?= htmlspecialchars($po['po_number']) ?></span> - 
                        Supplier: <span class="font-medium"><?= htmlspecialchars($po['supplier_name']) ?></span>
                    </p>
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

        <?php if ($items->num_rows == 0): ?>
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-900 mb-2">All Items Received</h3>
            <p class="text-gray-600 mb-6">All items in this purchase order have been fully received.</p>
            <a href="view.php?id=<?= $po_id ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg">
                <i class="fas fa-eye mr-2"></i>View Purchase Order
            </a>
        </div>
        <?php else: ?>

        <!-- Instructions -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-600 mt-1 mr-3"></i>
                <div>
                    <h3 class="font-medium text-blue-800">Receiving Instructions</h3>
                    <ul class="mt-2 text-blue-700 text-sm space-y-1">
                        <li>• Enter the quantity received for each item</li>
                        <li>• You can receive partial quantities - remaining items will stay pending</li>
                        <li>• Stock levels will be automatically updated upon receipt</li>
                        <li>• Leave quantity blank or 0 for items not received in this shipment</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Receive Items Form -->
        <form method="POST" id="receiveForm">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900">
                            <i class="fas fa-clipboard-list text-purple-600 mr-2"></i>Items to Receive
                        </h3>
                        <div class="text-sm text-gray-600">
                            Total Pending Items: <span class="font-medium"><?= $items->num_rows ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Product
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Ordered
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Already Received
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Pending
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Receive Now
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($item = $items->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50" data-item-id="<?= $item['id'] ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($item['product_name']) ?>
                                        </div>
                                        <?php if ($item['sku']): ?>
                                        <div class="text-sm text-gray-500">SKU: <?= htmlspecialchars($item['sku']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($item['category_name']): ?>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($item['category_name']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center font-medium">
                                    <?= number_format($item['quantity']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-center font-medium">
                                    <?= number_format($item['received_quantity']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-orange-600 text-center font-medium">
                                    <?= number_format($item['pending_quantity']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" 
                                           name="received_items[<?= $item['id'] ?>]" 
                                           class="receive-input w-24 px-3 py-2 border border-gray-300 rounded text-center focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500" 
                                           min="0" 
                                           max="<?= $item['pending_quantity'] ?>" 
                                           placeholder="0"
                                           data-max="<?= $item['pending_quantity'] ?>">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button type="button" class="fill-max-btn text-blue-600 hover:text-blue-900 mr-3" 
                                            data-target="received_items[<?= $item['id'] ?>]" 
                                            data-max="<?= $item['pending_quantity'] ?>">
                                        <i class="fas fa-arrow-up mr-1"></i>Max
                                    </button>
                                    <button type="button" class="clear-btn text-gray-600 hover:text-gray-900" 
                                            data-target="received_items[<?= $item['id'] ?>]">
                                        <i class="fas fa-times mr-1"></i>Clear
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary and Actions -->
                <div class="p-6 bg-gray-50 border-t border-gray-200">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <span class="mr-6">Items to receive: <span id="totalItemsToReceive" class="font-medium">0</span></span>
                            <span>Total quantity: <span id="totalQuantityToReceive" class="font-medium">0</span></span>
                        </div>
                        <div class="flex space-x-3">
                            <a href="view.php?id=<?= $po_id ?>" 
                               class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <i class="fas fa-times mr-2"></i>Cancel
                            </a>
                            <button type="button" id="fillAllMax" 
                                    class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md">
                                <i class="fas fa-fill mr-2"></i>Receive All Pending
                            </button>
                            <button type="submit" 
                                    class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md">
                                <i class="fas fa-box-open mr-2"></i>Process Receipt
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const receiveInputs = document.querySelectorAll('.receive-input');
        const fillMaxButtons = document.querySelectorAll('.fill-max-btn');
        const clearButtons = document.querySelectorAll('.clear-btn');
        const fillAllMaxButton = document.getElementById('fillAllMax');
        
        // Update summary when inputs change
        receiveInputs.forEach(input => {
            input.addEventListener('input', function() {
                // Validate max quantity
                const max = parseInt(this.getAttribute('data-max'));
                if (parseInt(this.value) > max) {
                    this.value = max;
                }
                updateSummary();
            });
        });
        
        // Fill max buttons
        fillMaxButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetName = this.getAttribute('data-target');
                const maxValue = this.getAttribute('data-max');
                const input = document.querySelector(`input[name="${targetName}"]`);
                input.value = maxValue;
                updateSummary();
            });
        });
        
        // Clear buttons
        clearButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetName = this.getAttribute('data-target');
                const input = document.querySelector(`input[name="${targetName}"]`);
                input.value = '';
                updateSummary();
            });
        });
        
        // Fill all max button
        fillAllMaxButton.addEventListener('click', function() {
            receiveInputs.forEach(input => {
                const maxValue = input.getAttribute('data-max');
                input.value = maxValue;
            });
            updateSummary();
        });
        
        function updateSummary() {
            let totalItems = 0;
            let totalQuantity = 0;
            
            receiveInputs.forEach(input => {
                const value = parseInt(input.value) || 0;
                if (value > 0) {
                    totalItems++;
                    totalQuantity += value;
                }
            });
            
            document.getElementById('totalItemsToReceive').textContent = totalItems;
            document.getElementById('totalQuantityToReceive').textContent = totalQuantity;
        }
        
        // Form validation
        document.getElementById('receiveForm').addEventListener('submit', function(e) {
            let hasItems = false;
            
            receiveInputs.forEach(input => {
                const value = parseInt(input.value) || 0;
                if (value > 0) {
                    hasItems = true;
                }
            });
            
            if (!hasItems) {
                e.preventDefault();
                alert('Please specify quantities to receive for at least one item.');
            } else if (!confirm('Are you sure you want to process this receipt? This will update stock levels and cannot be easily undone.')) {
                e.preventDefault();
            }
        });
        
        // Initial summary update
        updateSummary();
    });
    </script>
</body>
</html>
