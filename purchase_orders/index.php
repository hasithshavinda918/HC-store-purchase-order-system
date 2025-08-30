<?php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db_connection.php';

$page_title = "Purchase Orders Management";
$current_page = 'purchase_orders';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $po_id = intval($_POST['po_id']);
                $new_status = $_POST['new_status'];
                
                $allowed_statuses = ['draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled'];
                if (in_array($new_status, $allowed_statuses)) {
                    $stmt = $conn->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_status, $po_id);
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Purchase order status updated successfully!";
                    } else {
                        $_SESSION['error'] = "Error updating purchase order status.";
                    }
                }
                break;
            
            case 'delete':
                $po_id = intval($_POST['po_id']);
                // Only allow deletion of draft or cancelled orders
                $check_stmt = $conn->prepare("SELECT status FROM purchase_orders WHERE id = ?");
                $check_stmt->bind_param("i", $po_id);
                $check_stmt->execute();
                $status_result = $check_stmt->get_result();
                
                if ($status_result->num_rows > 0) {
                    $status = $status_result->fetch_row()[0];
                    if (in_array($status, ['draft', 'cancelled'])) {
                        $stmt = $conn->prepare("DELETE FROM purchase_orders WHERE id = ?");
                        $stmt->bind_param("i", $po_id);
                        if ($stmt->execute()) {
                            $_SESSION['success'] = "Purchase order deleted successfully!";
                        } else {
                            $_SESSION['error'] = "Error deleting purchase order.";
                        }
                    } else {
                        $_SESSION['error'] = "Cannot delete purchase order. Only draft or cancelled orders can be deleted.";
                    }
                }
                break;
        }
    }
    header('Location: index.php');
    exit;
}

// Pagination and filtering
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$supplier_filter = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build where clause
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($status_filter)) {
    $where_conditions[] = "po.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($supplier_filter > 0) {
    $where_conditions[] = "po.supplier_id = ?";
    $params[] = $supplier_filter;
    $param_types .= 'i';
}

if (!empty($search)) {
    $where_conditions[] = "(po.po_number LIKE ? OR s.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'ss';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total count
$count_sql = "SELECT COUNT(*) FROM purchase_orders po 
              LEFT JOIN suppliers s ON po.supplier_id = s.id 
              $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_records / $limit);

// Get purchase orders
$sql = "SELECT po.id, po.po_number, po.status, po.order_date, po.expected_delivery, 
               po.total_amount, s.name as supplier_name, u.username as created_by_name,
               COUNT(poi.id) as total_items,
               SUM(poi.quantity) as total_quantity,
               SUM(poi.received_quantity) as total_received_quantity
        FROM purchase_orders po 
        LEFT JOIN suppliers s ON po.supplier_id = s.id 
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
        $where_clause 
        GROUP BY po.id
        ORDER BY po.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$final_params = $params;
$final_params[] = $limit;
$final_params[] = $offset;
$final_param_types = $param_types . 'ii';

if (!empty($params)) {
    $stmt->bind_param($final_param_types, ...$final_params);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$purchase_orders = $stmt->get_result();

// Get suppliers for filter dropdown
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
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        <i class="fas fa-file-invoice text-purple-600 mr-3"></i>Purchase Orders Management
                    </h1>
                    <p class="text-gray-600 mt-2">Create and manage purchase orders for inventory procurement</p>
                </div>
                <div class="flex space-x-3">
                    <a href="../suppliers/index.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-truck mr-2"></i>Manage Suppliers
                    </a>
                    <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                        <i class="fas fa-plus mr-2"></i>New Purchase Order
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <?php
        $total_pos = $conn->query("SELECT COUNT(*) FROM purchase_orders")->fetch_row()[0];
        $draft_pos = $conn->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'draft'")->fetch_row()[0];
        $pending_pos = $conn->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('sent', 'confirmed')")->fetch_row()[0];
        $received_pos = $conn->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'received'")->fetch_row()[0];
        ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-file-invoice text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $total_pos ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-gray-100">
                        <i class="fas fa-edit text-gray-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Draft Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $draft_pos ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-orange-100">
                        <i class="fas fa-clock text-orange-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Pending Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $pending_pos ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Received Orders</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $received_pos ?></p>
                    </div>
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

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Search PO number or supplier..."
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Statuses</option>
                            <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="sent" <?= $status_filter === 'sent' ? 'selected' : '' ?>>Sent</option>
                            <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="partially_received" <?= $status_filter === 'partially_received' ? 'selected' : '' ?>>Partially Received</option>
                            <option value="received" <?= $status_filter === 'received' ? 'selected' : '' ?>>Received</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <!-- Supplier Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Supplier</label>
                        <select name="supplier" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Suppliers</option>
                            <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                            <option value="<?= $supplier['id'] ?>" <?= $supplier_filter == $supplier['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($supplier['name']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <!-- Filter Buttons -->
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-filter mr-2"></i>Filter
                        </button>
                        <a href="index.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Purchase Orders Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                PO Details
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Supplier
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Items
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($purchase_orders->num_rows > 0): ?>
                            <?php while ($po = $purchase_orders->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($po['po_number']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Order Date: <?= date('M d, Y', strtotime($po['order_date'])) ?>
                                        </div>
                                        <?php if ($po['expected_delivery']): ?>
                                        <div class="text-xs text-gray-400">
                                            Expected: <?= date('M d, Y', strtotime($po['expected_delivery'])) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div>
                                        <div class="font-medium"><?= htmlspecialchars($po['supplier_name']) ?></div>
                                        <div class="text-xs text-gray-500">by <?= htmlspecialchars($po['created_by_name']) ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <div class="text-center">
                                        <div class="font-medium"><?= $po['total_items'] ?: 0 ?></div>
                                        <div class="text-xs">items</div>
                                        <?php if ($po['total_received_quantity'] > 0): ?>
                                        <div class="text-xs text-green-600">
                                            <?= $po['total_received_quantity'] ?>/<?= $po['total_quantity'] ?> received
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
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
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    LKR <?= number_format($po['total_amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="view.php?id=<?= $po['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-900" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (in_array($po['status'], ['draft', 'sent'])): ?>
                                        <a href="edit.php?id=<?= $po['id'] ?>" 
                                           class="text-green-600 hover:text-green-900" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="print.php?id=<?= $po['id'] ?>" target="_blank" 
                                           class="text-purple-600 hover:text-purple-900" title="Print/PDF">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <?php if ($po['status'] === 'confirmed' || ($po['status'] === 'partially_received' && $po['total_received_quantity'] < $po['total_quantity'])): ?>
                                        <a href="receive.php?id=<?= $po['id'] ?>" 
                                           class="text-orange-600 hover:text-orange-900" title="Receive Items">
                                            <i class="fas fa-box-open"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (in_array($po['status'], ['draft', 'cancelled'])): ?>
                                        <form method="POST" class="inline" onsubmit="return confirmDelete('<?= htmlspecialchars($po['po_number']) ?>')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-file-invoice text-4xl text-gray-300 mb-4 block"></i>
                                    <?php if (!empty($search) || !empty($status_filter) || $supplier_filter > 0): ?>
                                        No purchase orders found matching your filters.
                                    <?php else: ?>
                                        No purchase orders found. <a href="create.php" class="text-blue-600 hover:text-blue-800">Create your first purchase order</a>.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6 mt-6 rounded-lg">
            <div class="flex-1 flex justify-between sm:hidden">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= http_build_query(['status' => $status_filter, 'supplier' => $supplier_filter, 'search' => $search], '', '&', PHP_QUERY_RFC3986) ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= http_build_query(['status' => $status_filter, 'supplier' => $supplier_filter, 'search' => $search], '', '&', PHP_QUERY_RFC3986) ?>" 
                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Next
                </a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?= $offset + 1 ?></span> to 
                        <span class="font-medium"><?= min($offset + $limit, $total_records) ?></span> of 
                        <span class="font-medium"><?= $total_records ?></span> purchase orders
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                        <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                        <a href="?page=<?= $i ?><?= http_build_query(['status' => $status_filter, 'supplier' => $supplier_filter, 'search' => $search], '', '&', PHP_QUERY_RFC3986) ?>" 
                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?= $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> <?= $i === 1 ? 'rounded-l-md' : '' ?> <?= $i === min($total_pages, 10) ? 'rounded-r-md' : '' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                    </nav>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function confirmDelete(poNumber) {
        return confirm(`Are you sure you want to delete purchase order "${poNumber}"? This action cannot be undone.`);
    }
    </script>
</body>
</html>
