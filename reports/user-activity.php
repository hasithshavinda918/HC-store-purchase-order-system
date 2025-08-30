<?php
require_once '../includes/auth.php';
require_once '../includes/language.php';
requireAdmin();

$page_title = __('user_activity') . ' ' . __('report');
include '../includes/header.php';

// Get date range from URL parameters or set defaults
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$user_id = $_GET['user_id'] ?? '';

// User activity summary
$activity_query = "
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.role,
        u.created_at as user_created,
        u.is_active,
        COUNT(sm.id) as total_movements,
        COUNT(CASE WHEN sm.movement_type = 'in' THEN 1 END) as stock_in_count,
        COUNT(CASE WHEN sm.movement_type = 'out' THEN 1 END) as stock_out_count,
        COUNT(CASE WHEN sm.movement_type = 'adjustment' THEN 1 END) as adjustment_count,
        SUM(CASE WHEN sm.movement_type = 'in' THEN ABS(sm.quantity_change) ELSE 0 END) as total_stock_in,
        SUM(CASE WHEN sm.movement_type = 'out' THEN ABS(sm.quantity_change) ELSE 0 END) as total_stock_out,
        MAX(sm.created_at) as last_activity
    FROM users u
    LEFT JOIN stock_movements sm ON u.id = sm.user_id 
        AND DATE(sm.created_at) BETWEEN ? AND ?
    " . ($user_id ? "WHERE u.id = ?" : "") . "
    GROUP BY u.id, u.full_name, u.username, u.role, u.created_at, u.is_active
    ORDER BY total_movements DESC, u.full_name ASC
";

$params = [$start_date, $end_date];
$param_types = "ss";

if ($user_id) {
    $params[] = $user_id;
    $param_types .= "i";
}

$stmt = $conn->prepare($activity_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$users = $stmt->get_result();
$stmt->close();

// Get all users for filter dropdown
$all_users_query = "SELECT id, full_name FROM users ORDER BY full_name";
$all_users = $conn->query($all_users_query);

// Recent activity for selected user or all users
$recent_activity_query = "
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
    WHERE DATE(sm.created_at) BETWEEN ? AND ?
    " . ($user_id ? "AND sm.user_id = ?" : "") . "
    ORDER BY sm.created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($recent_activity_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$recent_activities = $stmt->get_result();
$stmt->close();

// Summary stats
$summary_query = "
    SELECT 
        COUNT(DISTINCT sm.user_id) as active_users,
        COUNT(sm.id) as total_movements,
        SUM(CASE WHEN sm.movement_type = 'in' THEN ABS(sm.quantity_change) ELSE 0 END) as total_in,
        SUM(CASE WHEN sm.movement_type = 'out' THEN ABS(sm.quantity_change) ELSE 0 END) as total_out
    FROM stock_movements sm
    WHERE DATE(sm.created_at) BETWEEN ? AND ?
    " . ($user_id ? "AND sm.user_id = ?" : "");

$stmt = $conn->prepare($summary_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    <i class="fas fa-users text-purple-600 mr-3"></i>
                    <?php _e('user_activity'); ?> <?php _e('report'); ?>
                </h1>
                <p class="text-gray-600 mt-2"><?php _e('track_user_stock_movements'); ?></p>
            </div>
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                <i class="fas fa-arrow-left mr-2"></i><?php _e('back_to_reports'); ?>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4"><?php _e('filters'); ?></h2>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1"><?php _e('start_date'); ?></label>
                <input type="date" 
                       id="start_date" 
                       name="start_date" 
                       value="<?php echo htmlspecialchars($start_date); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1"><?php _e('end_date'); ?></label>
                <input type="date" 
                       id="end_date" 
                       name="end_date" 
                       value="<?php echo htmlspecialchars($end_date); ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            </div>
            
            <div>
                <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1"><?php _e('user'); ?></label>
                <select id="user_id" name="user_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                    <option value=""><?php _e('all_users'); ?></option>
                    <?php while ($user = $all_users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" 
                                <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg mr-2">
                    <i class="fas fa-search mr-2"></i><?php _e('apply_filters'); ?>
                </button>
                <a href="user-activity.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-times mr-2"></i><?php _e('clear'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Summary Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-users text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('active_users'); ?></div>
                        <div class="text-2xl font-bold text-purple-600"><?php echo number_format($summary['active_users']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-exchange-alt text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('total_movements'); ?></div>
                        <div class="text-2xl font-bold text-blue-600"><?php echo number_format($summary['total_movements']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-up text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('total_stock_in'); ?></div>
                        <div class="text-2xl font-bold text-green-600"><?php echo number_format($summary['total_in']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow-lg rounded-lg">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-down text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <div class="text-sm font-medium text-gray-500"><?php _e('total_stock_out'); ?></div>
                        <div class="text-2xl font-bold text-red-600"><?php echo number_format($summary['total_out']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- User Activity Summary -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-chart-bar text-purple-500 mr-2"></i>
                    <?php _e('user_activity_summary'); ?> (<?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j', strtotime($end_date)); ?>)
                </h2>
            </div>
            <div class="overflow-y-auto max-h-96">
                <?php if ($users->num_rows > 0): ?>
                    <div class="divide-y divide-gray-200">
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-user text-gray-600"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                <?php if (!$user['is_active']): ?>
                                                    <span class="text-red-500 text-xs ml-1">(<?php _e('inactive'); ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                @<?php echo htmlspecialchars($user['username']); ?> • <?php echo ucfirst(__($user['role'])); ?>
                                            </div>
                                            <?php if ($user['last_activity']): ?>
                                                <div class="text-xs text-gray-400">
                                                    <?php _e('last_activity'); ?>: <?php echo date('M j, H:i', strtotime($user['last_activity'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-semibold text-purple-600">
                                            <?php echo number_format($user['total_movements']); ?> <?php _e('movements'); ?>
                                        </div>
                                        <?php if ($user['total_movements'] > 0): ?>
                                            <div class="text-xs text-gray-500">
                                                <span class="text-green-600">+<?php echo number_format($user['total_stock_in']); ?></span>
                                                <span class="text-red-600 ml-1">-<?php echo number_format($user['total_stock_out']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($user['total_movements'] > 0): ?>
                                    <div class="mt-3">
                                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                                            <span><?php _e('activity_breakdown'); ?></span>
                                        </div>
                                        <div class="flex space-x-2 text-xs">
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded">
                                                <?php echo $user['stock_in_count']; ?> <?php _e('in'); ?>
                                            </span>
                                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded">
                                                <?php echo $user['stock_out_count']; ?> <?php _e('out'); ?>
                                            </span>
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                                <?php echo $user['adjustment_count']; ?> <?php _e('adj'); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-user-slash text-gray-400 text-3xl mb-3"></i>
                        <p><?php _e('no_user_activity_found'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">
                    <i class="fas fa-history text-green-500 mr-2"></i>
                    <?php _e('recent_activity'); ?> (<?php _e('last_50'); ?>)
                </h2>
            </div>
            <div class="overflow-y-auto max-h-96">
                <?php if ($recent_activities->num_rows > 0): ?>
                    <div class="divide-y divide-gray-200">
                        <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 mr-3">
                                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs
                                            <?php 
                                            switch($activity['movement_type']) {
                                                case 'in': echo 'bg-green-100 text-green-600'; break;
                                                case 'out': echo 'bg-red-100 text-red-600'; break;
                                                default: echo 'bg-blue-100 text-blue-600'; break;
                                            }
                                            ?>">
                                            <i class="fas <?php 
                                                switch($activity['movement_type']) {
                                                    case 'in': echo 'fa-plus'; break;
                                                    case 'out': echo 'fa-minus'; break;
                                                    default: echo 'fa-adjust'; break;
                                                }
                                            ?>"></i>
                                        </span>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-sm">
                                            <span class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($activity['user_name']); ?>
                                            </span>
                                            <?php _e('performed'); ?>
                                            <span class="font-medium <?php echo $activity['quantity_change'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                <?php echo $activity['quantity_change'] > 0 ? '+' : ''; ?><?php echo $activity['quantity_change']; ?>
                                            </span>
                                            <?php _e('movement_for'); ?>
                                            <span class="font-medium text-gray-900">
                                                <?php echo htmlspecialchars($activity['product_name']); ?>
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?>
                                            <?php if ($activity['reason']): ?>
                                                • <?php echo htmlspecialchars($activity['reason']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-history text-gray-400 text-3xl mb-3"></i>
                        <p><?php _e('no_recent_activity_found'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Export Actions -->
    <div class="bg-white rounded-lg shadow p-4">
        <div class="flex justify-between items-center">
            <div class="text-sm text-gray-600">
                <?php _e('activity_period'); ?>: <?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>
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
</div>

<script>
function exportToCSV() {
    // Create CSV data for user activity
    const data = [
        ['User', 'Role', 'Total Movements', 'Stock In', 'Stock Out', 'Adjustments', 'Last Activity']
    ];
    
    <?php $users->data_seek(0); ?>
    <?php while ($user = $users->fetch_assoc()): ?>
        data.push([
            '<?php echo addslashes($user['full_name']); ?>',
            '<?php echo addslashes(ucfirst(__($user['role']))); ?>',
            '<?php echo $user['total_movements']; ?>',
            '<?php echo $user['stock_in_count']; ?>',
            '<?php echo $user['stock_out_count']; ?>',
            '<?php echo $user['adjustment_count']; ?>',
            '<?php echo $user['last_activity'] ? date('Y-m-d H:i:s', strtotime($user['last_activity'])) : ''; ?>'
        ]);
    <?php endwhile; ?>
    
    // Create CSV
    const csv = data.map(row => row.map(field => `"${field}"`).join(',')).join('\n');
    
    // Download
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'user-activity-report.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
</script>

<style media="print">
    .no-print { display: none !important; }
    body { background: white !important; }
</style>

<?php include '../includes/footer.php'; ?>
