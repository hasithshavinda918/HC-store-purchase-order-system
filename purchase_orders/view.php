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

// Handle status updates via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $new_status = $_POST['status'] ?? '';
    $valid_statuses = ['sent', 'confirmed', 'cancelled'];
    
    header('Content-Type: application/json');
    
    if (!in_array($new_status, $valid_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    $update_fields = ['status = ?', 'updated_at = NOW()'];
    $params = [$new_status];
    $types = 's';
    
    if ($new_status == 'confirmed') {
        $update_fields[] = 'confirmed_date = NOW()';
        $update_fields[] = 'confirmed_by = ?';
        $params[] = $_SESSION['user_id'];
        $types .= 'i';
    }
    
    $sql = "UPDATE purchase_orders SET " . implode(', ', $update_fields) . " WHERE id = ? AND status NOT IN ('received', 'cancelled')";
    $params[] = $po_id;
    $types .= 'i';
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status or order cannot be modified']);
    }
    exit;
}

// Get purchase order details
$stmt = $conn->prepare("
    SELECT po.*, s.name as supplier_name, s.contact_person, s.email, s.phone, s.address, s.city,
           u1.username as created_by_name, u2.username as received_by_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN users u1 ON po.created_by = u1.id
    LEFT JOIN users u2 ON po.received_by = u2.id
    WHERE po.id = ?
");
$stmt->bind_param("i", $po_id);
$stmt->execute();
$po = $stmt->get_result()->fetch_assoc();

if (!$po) {
    $_SESSION['error'] = "Purchase order not found.";
    header('Location: index.php');
    exit;
}

// Get purchase order items
$items_stmt = $conn->prepare("
    SELECT poi.*, p.name as product_name, p.sku, c.name as category_name
    FROM purchase_order_items poi
    LEFT JOIN products p ON poi.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE poi.po_id = ?
    ORDER BY p.name
");
$items_stmt->bind_param("i", $po_id);
$items_stmt->execute();
$items = $items_stmt->get_result();

$page_title = "Purchase Order - " . $po['po_number'];
$current_page = 'purchase_orders';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_status':
            $new_status = $_POST['new_status'];
            $allowed_statuses = ['draft', 'sent', 'confirmed', 'cancelled'];
            
            if (in_array($new_status, $allowed_statuses)) {
                $update_stmt = $conn->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_status, $po_id);
                if ($update_stmt->execute()) {
                    $_SESSION['success'] = "Purchase order status updated to " . ucfirst($new_status);
                    $po['status'] = $new_status; // Update for current page display
                } else {
                    $_SESSION['error'] = "Error updating status.";
                }
            }
            break;
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
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <a href="index.php" class="text-gray-500 hover:text-gray-700 mr-4">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">
                            <i class="fas fa-file-invoice text-purple-600 mr-3"></i><?= htmlspecialchars($po['po_number']) ?>
                        </h1>
                        <p class="text-gray-600 mt-2">Purchase order details and management</p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <a href="print.php?id=<?= $po_id ?>" target="_blank" 
                       class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-print mr-2"></i>Print/PDF
                    </a>
                    <?php if (in_array($po['status'], ['draft', 'sent'])): ?>
                    <a href="edit.php?id=<?= $po_id ?>" 
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-edit mr-2"></i>Edit Order
                    </a>
                    <?php endif; ?>
                    <?php if ($po['status'] === 'confirmed' || $po['status'] === 'partially_received'): ?>
                    <a href="receive.php?id=<?= $po_id ?>" 
                       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-box-open mr-2"></i>Receive Items
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Purchase Order Info -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex justify-between items-start mb-6">
                        <h3 class="text-lg font-medium text-gray-900">
                            <i class="fas fa-info-circle text-blue-600 mr-2"></i>Order Information
                        </h3>
                        <?php if ($po['status'] !== 'received' && $po['status'] !== 'cancelled'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="update_status">
                            <select name="new_status" onchange="this.form.submit()" 
                                    class="px-3 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-blue-500">
                                <option value="draft" <?= $po['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="sent" <?= $po['status'] === 'sent' ? 'selected' : '' ?>>Sent</option>
                                <option value="confirmed" <?= $po['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="cancelled" <?= $po['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </form>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div>
                            <label class="text-sm font-medium text-gray-500">PO Number</label>
                            <p class="text-lg font-bold text-gray-900"><?= htmlspecialchars($po['po_number']) ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Status</label>
                            <div class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php
                                    switch($po['status']) {
                                        case 'draft': echo 'bg-gray-100 text-gray-800'; break;
                                        case 'sent': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'confirmed': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'partially_received': echo 'bg-orange-100 text-orange-800'; break;
                                        case 'received': echo 'bg-green-100 text-green-800'; break;
                                        case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <i class="fas fa-circle mr-1 text-xs"></i>
                                    <?= ucfirst(str_replace('_', ' ', $po['status'])) ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Order Date</label>
                            <p class="text-sm text-gray-900"><?= date('M d, Y', strtotime($po['order_date'])) ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Expected Delivery</label>
                            <p class="text-sm text-gray-900">
                                <?= $po['expected_delivery'] ? date('M d, Y', strtotime($po['expected_delivery'])) : 'Not specified' ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($po['notes']): ?>
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <label class="text-sm font-medium text-gray-500">Notes</label>
                        <p class="mt-1 text-gray-900"><?= nl2br(htmlspecialchars($po['notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Supplier Information -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 border-b border-gray-200 pb-2">
                        <i class="fas fa-truck text-green-600 mr-2"></i>Supplier Information
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-medium text-gray-900"><?= htmlspecialchars($po['supplier_name']) ?></h4>
                            <?php if ($po['contact_person']): ?>
                            <p class="text-sm text-gray-600">Contact: <?= htmlspecialchars($po['contact_person']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="space-y-1">
                            <?php if ($po['email']): ?>
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-envelope mr-2"></i>
                                <a href="mailto:<?= htmlspecialchars($po['email']) ?>" class="text-blue-600 hover:text-blue-800">
                                    <?= htmlspecialchars($po['email']) ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if ($po['phone']): ?>
                            <div class="flex items-center text-sm text-gray-600">
                                <i class="fas fa-phone mr-2"></i>
                                <a href="tel:<?= htmlspecialchars($po['phone']) ?>" class="text-blue-600 hover:text-blue-800">
                                    <?= htmlspecialchars($po['phone']) ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if ($po['address'] || $po['city']): ?>
                            <div class="flex items-start text-sm text-gray-600">
                                <i class="fas fa-map-marker-alt mr-2 mt-0.5"></i>
                                <div>
                                    <?php if ($po['address']): ?>
                                    <div><?= htmlspecialchars($po['address']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($po['city']): ?>
                                    <div><?= htmlspecialchars($po['city']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">
                            <i class="fas fa-box text-purple-600 mr-2"></i>Order Items
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Product
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Quantity
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Unit Cost
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total Cost
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Received
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $total_items = 0;
                                $total_quantity = 0;
                                $total_received = 0;
                                $grand_total = 0;
                                ?>
                                <?php while ($item = $items->fetch_assoc()): 
                                    $total_items++;
                                    $total_quantity += $item['quantity'];
                                    $total_received += $item['received_quantity'];
                                    $grand_total += $item['total_cost'];
                                ?>
                                <tr class="hover:bg-gray-50">
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                        <?= number_format($item['quantity']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        LKR <?= number_format($item['unit_cost'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        LKR <?= number_format($item['total_cost'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="text-center">
                                            <?= number_format($item['received_quantity']) ?>
                                            <?php if ($item['received_quantity'] > 0): ?>
                                            <div class="text-xs <?= $item['received_quantity'] >= $item['quantity'] ? 'text-green-600' : 'text-orange-600' ?>">
                                                <?= round(($item['received_quantity'] / $item['quantity']) * 100) ?>% complete
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-sm font-medium text-gray-900">
                                        Total (<?= $total_items ?> items)
                                    </th>
                                    <th class="px-6 py-3 text-center text-sm font-medium text-gray-900">
                                        <?= number_format($total_quantity) ?>
                                    </th>
                                    <th class="px-6 py-3 text-left text-sm font-medium text-gray-900">-</th>
                                    <th class="px-6 py-3 text-left text-sm font-medium text-blue-600">
                                        LKR <?= number_format($grand_total, 2) ?>
                                    </th>
                                    <th class="px-6 py-3 text-center text-sm font-medium text-gray-900">
                                        <?= number_format($total_received) ?>
                                        <?php if ($total_received > 0): ?>
                                        <div class="text-xs <?= $total_received >= $total_quantity ? 'text-green-600' : 'text-orange-600' ?>">
                                            <?= round(($total_received / $total_quantity) * 100) ?>% received
                                        </div>
                                        <?php endif; ?>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Order Summary -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <i class="fas fa-calculator text-blue-600 mr-2"></i>Order Summary
                    </h3>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total Items:</span>
                            <span class="font-medium"><?= $total_items ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Total Quantity:</span>
                            <span class="font-medium"><?= number_format($total_quantity) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Received:</span>
                            <span class="font-medium text-green-600"><?= number_format($total_received) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Pending:</span>
                            <span class="font-medium text-orange-600"><?= number_format($total_quantity - $total_received) ?></span>
                        </div>
                        <hr>
                        <div class="flex justify-between text-lg font-bold">
                            <span class="text-gray-900">Total Amount:</span>
                            <span class="text-blue-600">LKR <?= number_format($grand_total, 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Order History -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <i class="fas fa-history text-green-600 mr-2"></i>Order History
                    </h3>
                    
                    <div class="space-y-3 text-sm">
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-blue-500 rounded-full mr-3"></div>
                            <div class="flex-1">
                                <div class="font-medium">Created</div>
                                <div class="text-gray-500">
                                    <?= date('M d, Y g:i A', strtotime($po['created_at'])) ?>
                                    by <?= htmlspecialchars($po['created_by_name']) ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($po['status'] === 'received' && $po['received_by']): ?>
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                            <div class="flex-1">
                                <div class="font-medium">Received</div>
                                <div class="text-gray-500">
                                    <?= date('M d, Y g:i A', strtotime($po['received_date'])) ?>
                                    by <?= htmlspecialchars($po['received_by_name']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-gray-400 rounded-full mr-3"></div>
                            <div class="flex-1">
                                <div class="font-medium">Last Updated</div>
                                <div class="text-gray-500">
                                    <?= date('M d, Y g:i A', strtotime($po['updated_at'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        <i class="fas fa-bolt text-yellow-600 mr-2"></i>Quick Actions
                    </h3>
                    
                    <div class="space-y-2">
                        <a href="../suppliers/edit.php?id=<?= $po['supplier_id'] ?>" 
                           class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm flex items-center">
                            <i class="fas fa-truck mr-2"></i>Edit Supplier
                        </a>
                        
                        <?php if (in_array($po['status'], ['draft', 'sent', 'confirmed'])): ?>
                        <a href="create.php?copy=<?= $po_id ?>" 
                           class="w-full bg-blue-100 hover:bg-blue-200 text-blue-700 px-4 py-2 rounded text-sm flex items-center">
                            <i class="fas fa-copy mr-2"></i>Duplicate Order
                        </a>
                        <?php endif; ?>
                        
                        <a href="index.php?supplier=<?= $po['supplier_id'] ?>" 
                           class="w-full bg-green-100 hover:bg-green-200 text-green-700 px-4 py-2 rounded text-sm flex items-center">
                            <i class="fas fa-search mr-2"></i>Other Orders from Supplier
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Actions -->
            <div class="bg-white rounded-lg shadow p-6 mt-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">
                        <i class="fas fa-cog text-blue-600 mr-2"></i>Order Actions
                    </h3>
                    
                    <div class="flex space-x-2">
                        <?php if (in_array($po['status'], ['draft', 'sent'])): ?>
                            <a href="edit.php?id=<?= $po_id ?>" 
                               class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm">
                                <i class="fas fa-edit mr-2"></i>Edit Order
                            </a>
                        <?php endif; ?>
                        
                        <a href="print.php?id=<?= $po_id ?>" target="_blank" 
                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                            <i class="fas fa-print mr-2"></i>Print / PDF
                        </a>
                        
                        <?php if ($po['status'] == 'draft'): ?>
                            <button onclick="updateStatus('sent')" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                <i class="fas fa-paper-plane mr-2"></i>Mark as Sent
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($po['status'] == 'sent'): ?>
                            <button onclick="updateStatus('confirmed')" 
                                    class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 text-sm">
                                <i class="fas fa-check-circle mr-2"></i>Confirm Order
                            </button>
                        <?php endif; ?>
                        
                        <?php if (in_array($po['status'], ['sent', 'confirmed'])): ?>
                            <a href="receive.php?id=<?= $po_id ?>" 
                               class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm">
                                <i class="fas fa-truck mr-2"></i>Receive Stock
                            </a>
                        <?php endif; ?>
                        
                        <?php if (in_array($po['status'], ['draft', 'sent'])): ?>
                            <button onclick="cancelOrder()" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                                <i class="fas fa-times mr-2"></i>Cancel Order
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for status updates -->
    <script>
        function updateStatus(newStatus) {
            if (confirm(`Are you sure you want to mark this order as ${newStatus.replace('_', ' ')}?`)) {
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('status', newStatus);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error updating status: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error updating status. Please try again.');
                    console.error('Error:', error);
                });
            }
        }
        
        function cancelOrder() {
            if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                updateStatus('cancelled');
            }
        }
    </script>
</body>
</html>
