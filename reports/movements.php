<?php
require_once '../includes/auth.php';
require_once '../includes/language.php';
requireAdmin();

$page_title = __('stock_movements') . ' ' . __('report');
include '../includes/header.php';

// Get filters from URL parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$product_id = $_GET['product_id'] ?? '';
$movement_type = $_GET['movement_type'] ?? '';
$user_id = $_GET['user_id'] ?? '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = ["DATE(sm.created_at) BETWEEN ? AND ?"];
$params = [$start_date, $end_date];
$param_types = "ss";

if ($product_id) {
    $where_conditions[] = "sm.product_id = ?";
    $params[] = $product_id;
    $param_types .= "i";
}

if ($movement_type) {
    $where_conditions[] = "sm.movement_type = ?";
    $params[] = $movement_type;
    $param_types .= "s";
}

if ($user_id) {
    $where_conditions[] = "sm.user_id = ?";
    $params[] = $user_id;
    $param_types .= "i";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count
$count_query = "
    SELECT COUNT(*) as total
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    JOIN users u ON sm.user_id = u.id
    LEFT JOIN categories c ON p.category_id = c.id
    $where_clause
";

$stmt = $conn->prepare($count_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$total_pages = ceil($total_records / $limit);

// Get movements with pagination
$movements_query = "
    SELECT 
        sm.*,
        p.name as product_name,
        p.sku as product_sku,
        u.full_name as user_name,
        c.name as category_name
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    JOIN users u ON sm.user_id = u.id
    LEFT JOIN categories c ON p.category_id = c.id
    $where_clause
    ORDER BY sm.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($movements_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$movements = $stmt->get_result();
$stmt->close();

// Get products for filter dropdown
$products_query = "SELECT id, name, sku FROM products ORDER BY name";
$products = $conn->query($products_query);

// Get users for filter dropdown
$users_query = "SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name";
$users = $conn->query($users_query);
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    <i class="fas fa-exchange-alt text-blue-600 mr-3"></i>
                    <?php _e('stock_movements'); ?> <?php _e('report'); ?>
                </h1>
                <p class="text-gray-600 mt-2"><?php _e('detailed_stock_movement_history'); ?></p>
            </div>
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-arrow-left mr-2"></i><?php _e('back_to_reports'); ?>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4"><?php _e('filters'); ?></h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1"><?php _e('start_date'); ?></label>
                <input type="date" 
                       id="start_date" 
                       name="start_date" 
                       value="<?php echo htmlspecialchars($start_date); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1"><?php _e('end_date'); ?></label>
                <input type="date" 
                       id="end_date" 
                       name="end_date" 
                       value="<?php echo htmlspecialchars($end_date); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            
            <div>
                <label for="product_id" class="block text-sm font-medium text-gray-700 mb-1"><?php _e('product'); ?></label>
                <select id="product_id" name="product_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value=""><?php _e('all_products'); ?></option>
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <option value="<?php echo $product['id']; ?>" 
                                <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($product['name']); ?> 
                            (<?php echo htmlspecialchars($product['sku']); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label for="movement_type" class="block text-sm font-medium text-gray-700 mb-1"><?php _e('movement_type'); ?></label>
                <select id="movement_type" name="movement_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value=""><?php _e('all_types'); ?></option>
                    <option value="in" <?php echo $movement_type === 'in' ? 'selected' : ''; ?>><?php _e('stock_in'); ?></option>
                    <option value="out" <?php echo $movement_type === 'out' ? 'selected' : ''; ?>><?php _e('stock_out'); ?></option>
                    <option value="adjustment" <?php echo $movement_type === 'adjustment' ? 'selected' : ''; ?>><?php _e('adjustment'); ?></option>
                </select>
            </div>
            
            <div>
                <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1"><?php _e('user'); ?></label>
                <select id="user_id" name="user_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value=""><?php _e('all_users'); ?></option>
                    <?php while ($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" 
                                <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="col-span-full">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    <i class="fas fa-search mr-2"></i><?php _e('apply_filters'); ?>
                </button>
                <a href="movements.php" class="ml-2 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i><?php _e('clear_filters'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Results Summary -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex justify-between items-center">
            <div class="text-sm text-gray-600">
                <?php echo __('showing_results', [
                    'start' => number_format($offset + 1),
                    'end' => number_format(min($offset + $limit, $total_records)),
                    'total' => number_format($total_records)
                ]); ?>
            </div>
            <div class="flex space-x-2">
                <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-print mr-2"></i><?php _e('print'); ?>
                </button>
                <button onclick="exportToCSV()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-download mr-2"></i><?php _e('export_csv'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Movements Table -->
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <?php if ($movements->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('date_time'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('product'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('type'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('quantity_change'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('stock_levels'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('user'); ?></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"><?php _e('reason'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($movement = $movements->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M j, Y H:i', strtotime($movement['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($movement['product_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($movement['product_sku']); ?>
                                        <?php if ($movement['category_name']): ?>
                                            • <?php echo htmlspecialchars($movement['category_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                        <?php 
                                        switch($movement['movement_type']) {
                                            case 'in': echo 'bg-green-100 text-green-800'; break;
                                            case 'out': echo 'bg-red-100 text-red-800'; break;
                                            case 'adjustment': echo 'bg-blue-100 text-blue-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800'; break;
                                        }
                                        ?>">
                                        <i class="fas <?php 
                                            switch($movement['movement_type']) {
                                                case 'in': echo 'fa-arrow-up'; break;
                                                case 'out': echo 'fa-arrow-down'; break;
                                                case 'adjustment': echo 'fa-adjust'; break;
                                                default: echo 'fa-exchange-alt'; break;
                                            }
                                        ?> mr-1"></i>
                                        <?php _e($movement['movement_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold 
                                    <?php echo $movement['quantity_change'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    <?php echo $movement['quantity_change'] > 0 ? '+' : ''; ?><?php echo $movement['quantity_change']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $movement['previous_quantity']; ?> → <?php echo $movement['new_quantity']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($movement['user_name']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo htmlspecialchars($movement['reason'] ?: __('no_reason_provided')); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            <?php echo __('page_info', [
                                'current' => $page,
                                'total' => $total_pages
                            ]); ?>
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                   class="bg-white border border-gray-300 text-gray-500 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm">
                                    <i class="fas fa-chevron-left mr-1"></i><?php _e('previous'); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                   class="bg-white border border-gray-300 text-gray-500 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm">
                                    <?php _e('next'); ?><i class="fas fa-chevron-right ml-1"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="p-6 text-center text-gray-500">
                <i class="fas fa-search text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2"><?php _e('no_movements_found'); ?></h3>
                <p class="text-gray-500"><?php _e('try_adjusting_filters'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportToCSV() {
    const queryParams = new URLSearchParams(window.location.search);
    queryParams.set('export', 'csv');
    window.location.href = 'export.php?' + queryParams.toString();
}
</script>

<style media="print">
    .no-print { display: none !important; }
    body { background: white !important; }
</style>

<?php include '../includes/footer.php'; ?>
