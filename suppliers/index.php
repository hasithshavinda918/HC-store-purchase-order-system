<?php
require_once '../includes/auth.php';
require_once '../includes/language.php';
requireAdmin();
require_once '../includes/db_connection.php';

$page_title = __("suppliers_management");
$current_page = 'suppliers';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_status':
                $supplier_id = intval($_POST['supplier_id']);
                $new_status = $_POST['new_status'] == '1' ? 1 : 0;
                $stmt = $conn->prepare("UPDATE suppliers SET is_active = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_status, $supplier_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Supplier status updated successfully!";
                } else {
                    $_SESSION['error'] = "Error updating supplier status.";
                }
                break;
            
            case 'delete':
                $supplier_id = intval($_POST['supplier_id']);
                // Check if supplier has active purchase orders
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = ? AND status NOT IN ('cancelled', 'received')");
                $check_stmt->bind_param("i", $supplier_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $active_orders = $check_result->fetch_row()[0];
                
                if ($active_orders > 0) {
                    $_SESSION['error'] = "Cannot delete supplier with active purchase orders. Complete or cancel orders first.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
                    $stmt->bind_param("i", $supplier_id);
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Supplier deleted successfully!";
                    } else {
                        $_SESSION['error'] = "Error deleting supplier.";
                    }
                }
                break;
        }
    }
    header('Location: index.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = "";
$search_params = [];

if (!empty($search)) {
    $where_clause = "WHERE (name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $search_params = [$search_term, $search_term, $search_term, $search_term];
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM suppliers $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($search_params)) {
    $count_stmt->bind_param("ssss", ...$search_params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_records / $limit);

// Get suppliers
$sql = "SELECT id, name, contact_person, email, phone, address, city, is_active, created_at 
        FROM suppliers $where_clause 
        ORDER BY name ASC 
        LIMIT ? OFFSET ?";
        
$stmt = $conn->prepare($sql);
if (!empty($search_params)) {
    $search_params[] = $limit;
    $search_params[] = $offset;
    $stmt->bind_param("ssssii", ...$search_params);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$suppliers = $stmt->get_result();
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
                        <i class="fas fa-truck text-blue-600 mr-3"></i>Suppliers Management
                    </h1>
                    <p class="text-gray-600 mt-2">Manage your supplier information and contacts</p>
                </div>
                <a href="create.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                    <i class="fas fa-plus mr-2"></i>Add New Supplier
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <?php
        $active_suppliers = $conn->query("SELECT COUNT(*) FROM suppliers WHERE is_active = 1")->fetch_row()[0];
        $inactive_suppliers = $conn->query("SELECT COUNT(*) FROM suppliers WHERE is_active = 0")->fetch_row()[0];
        $total_suppliers = $active_suppliers + $inactive_suppliers;
        ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-truck text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Suppliers</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $total_suppliers ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Suppliers</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $active_suppliers ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100">
                        <i class="fas fa-times-circle text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Inactive Suppliers</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $inactive_suppliers ?></p>
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

        <!-- Search and Filters -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6 border-b border-gray-200">
                <form method="GET" class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Search suppliers by name, contact person, email, or phone..."
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                            <i class="fas fa-search mr-2"></i>Search
                        </button>
                        <?php if (!empty($search)): ?>
                        <a href="index.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-6 py-2 rounded-lg">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Suppliers Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Supplier Info
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Contact Details
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Location
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($suppliers->num_rows > 0): ?>
                            <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($supplier['name']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Contact: <?= htmlspecialchars($supplier['contact_person'] ?: 'Not specified') ?>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            Added: <?= date('M d, Y', strtotime($supplier['created_at'])) ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div>
                                        <?php if ($supplier['email']): ?>
                                        <div class="flex items-center mb-1">
                                            <i class="fas fa-envelope text-gray-400 mr-2"></i>
                                            <a href="mailto:<?= htmlspecialchars($supplier['email']) ?>" 
                                               class="text-blue-600 hover:text-blue-800">
                                                <?= htmlspecialchars($supplier['email']) ?>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($supplier['phone']): ?>
                                        <div class="flex items-center">
                                            <i class="fas fa-phone text-gray-400 mr-2"></i>
                                            <a href="tel:<?= htmlspecialchars($supplier['phone']) ?>" 
                                               class="text-blue-600 hover:text-blue-800">
                                                <?= htmlspecialchars($supplier['phone']) ?>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?php if ($supplier['address'] || $supplier['city']): ?>
                                        <?= htmlspecialchars($supplier['city'] ?: $supplier['address']) ?>
                                    <?php else: ?>
                                        <span class="text-gray-400">Not specified</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form method="POST" class="inline" onsubmit="return confirmToggle(<?= $supplier['id'] ?>, <?= $supplier['is_active'] ?>)">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="supplier_id" value="<?= $supplier['id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $supplier['is_active'] ? '0' : '1' ?>">
                                        <button type="submit" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $supplier['is_active'] ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200' ?>">
                                            <i class="fas <?= $supplier['is_active'] ? 'fa-check-circle' : 'fa-times-circle' ?> mr-1"></i>
                                            <?= $supplier['is_active'] ? 'Active' : 'Inactive' ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="edit.php?id=<?= $supplier['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" class="inline" onsubmit="return confirmDelete('<?= htmlspecialchars($supplier['name']) ?>')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="supplier_id" value="<?= $supplier['id'] ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-truck text-4xl text-gray-300 mb-4 block"></i>
                                    <?php if (!empty($search)): ?>
                                        No suppliers found matching "<?= htmlspecialchars($search) ?>".
                                    <?php else: ?>
                                        No suppliers found. <a href="create.php" class="text-blue-600 hover:text-blue-800">Add your first supplier</a>.
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
                <a href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    Previous
                </a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
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
                        <span class="font-medium"><?= $total_records ?></span> suppliers
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>" 
                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?= $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> <?= $i === 1 ? 'rounded-l-md' : '' ?> <?= $i === $total_pages ? 'rounded-r-md' : '' ?>">
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
    function confirmToggle(supplierId, currentStatus) {
        const action = currentStatus ? 'deactivate' : 'activate';
        return confirm(`Are you sure you want to ${action} this supplier?`);
    }

    function confirmDelete(supplierName) {
        return confirm(`Are you sure you want to delete supplier "${supplierName}"? This action cannot be undone.`);
    }
    </script>
</body>
</html>
