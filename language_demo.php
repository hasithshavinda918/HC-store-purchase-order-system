<?php
require_once 'includes/language.php';
require_once 'includes/auth.php';

// For demo purposes, simulate a logged-in admin
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['full_name'] = 'System Administrator';
    $_SESSION['role'] = 'admin';
}

$page_title = __('language_system_demo');
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - HC Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        // Global translations for JavaScript
        window.translations = <?php echo json_encode(LanguageManager::getInstance()->getAllTranslations()); ?>;
        window.currentLanguage = '<?php echo getCurrentLanguage(); ?>';
        
        // Translation function for JavaScript
        function __(key, replacements = {}) {
            let translation = window.translations[key] || key;
            
            // Handle replacements
            for (let placeholder in replacements) {
                translation = translation.replace(':' + placeholder, replacements[placeholder]);
            }
            
            return translation;
        }
    </script>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <?php include 'includes/admin_navbar.php'; ?>
    
    <div class="max-w-7xl mx-auto py-8 px-4">
        <!-- Language Demo Header -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <div class="text-center">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-language text-blue-600 mr-3"></i>
                    <?php _e('select_language'); ?> <?php _e('demo'); ?>
                </h1>
                <p class="text-lg text-gray-600 mb-6">
                    HC Store Purchase Order Management System - Multi-Language Demo
                </p>
                
                <!-- Current Language Display -->
                <div class="bg-blue-50 rounded-lg p-4 mb-6 inline-block">
                    <span class="font-semibold text-blue-900">
                        <?php _e('select_language'); ?>: 
                        <span class="text-blue-600"><?php echo LanguageManager::getInstance()->getLanguageName(); ?></span>
                        (<?php echo getCurrentLanguage(); ?>)
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Navigation Terms -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-bars text-indigo-600 mr-2"></i>
                    <?php _e('navigation'); ?>
                </h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <div class="flex items-center p-2 bg-gray-50 rounded">
                            <i class="fas fa-tachometer-alt text-blue-500 mr-2"></i>
                            <span><?php _e('dashboard'); ?></span>
                        </div>
                        <div class="flex items-center p-2 bg-gray-50 rounded">
                            <i class="fas fa-box text-green-500 mr-2"></i>
                            <span><?php _e('products'); ?></span>
                        </div>
                        <div class="flex items-center p-2 bg-gray-50 rounded">
                            <i class="fas fa-tags text-purple-500 mr-2"></i>
                            <span><?php _e('categories'); ?></span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center p-2 bg-gray-50 rounded">
                            <i class="fas fa-truck text-orange-500 mr-2"></i>
                            <span><?php _e('suppliers'); ?></span>
                        </div>
                        <div class="flex items-center p-2 bg-gray-50 rounded">
                            <i class="fas fa-file-invoice text-red-500 mr-2"></i>
                            <span><?php _e('purchase_orders'); ?></span>
                        </div>
                        <div class="flex items-center p-2 bg-gray-50 rounded">
                            <i class="fas fa-users text-indigo-500 mr-2"></i>
                            <span><?php _e('users'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Common Actions -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-cog text-green-600 mr-2"></i>
                    <?php _e('actions'); ?>
                </h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <button class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600 transition">
                            <i class="fas fa-plus mr-2"></i><?php _e('create'); ?>
                        </button>
                        <button class="w-full bg-green-500 text-white p-2 rounded hover:bg-green-600 transition">
                            <i class="fas fa-edit mr-2"></i><?php _e('edit'); ?>
                        </button>
                        <button class="w-full bg-purple-500 text-white p-2 rounded hover:bg-purple-600 transition">
                            <i class="fas fa-eye mr-2"></i><?php _e('view'); ?>
                        </button>
                    </div>
                    <div class="space-y-2">
                        <button class="w-full bg-orange-500 text-white p-2 rounded hover:bg-orange-600 transition">
                            <i class="fas fa-save mr-2"></i><?php _e('save'); ?>
                        </button>
                        <button class="w-full bg-gray-500 text-white p-2 rounded hover:bg-gray-600 transition">
                            <i class="fas fa-times mr-2"></i><?php _e('cancel'); ?>
                        </button>
                        <button class="w-full bg-red-500 text-white p-2 rounded hover:bg-red-600 transition">
                            <i class="fas fa-trash mr-2"></i><?php _e('delete'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Purchase Order Status -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-file-invoice text-purple-600 mr-2"></i>
                    <?php _e('purchase_orders'); ?> <?php _e('status'); ?>
                </h2>
                <div class="space-y-2">
                    <div class="flex items-center justify-between p-2 bg-gray-100 rounded">
                        <span><?php _e('draft'); ?></span>
                        <span class="bg-gray-500 text-white px-2 py-1 rounded text-sm"><?php _e('draft'); ?></span>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-blue-100 rounded">
                        <span><?php _e('sent'); ?></span>
                        <span class="bg-blue-500 text-white px-2 py-1 rounded text-sm"><?php _e('sent'); ?></span>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-yellow-100 rounded">
                        <span><?php _e('confirmed'); ?></span>
                        <span class="bg-yellow-500 text-white px-2 py-1 rounded text-sm"><?php _e('confirmed'); ?></span>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-orange-100 rounded">
                        <span><?php _e('partially_received'); ?></span>
                        <span class="bg-orange-500 text-white px-2 py-1 rounded text-sm"><?php _e('partially_received'); ?></span>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-green-100 rounded">
                        <span><?php _e('received'); ?></span>
                        <span class="bg-green-500 text-white px-2 py-1 rounded text-sm"><?php _e('received'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Form Elements -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-wpforms text-red-600 mr-2"></i>
                    <?php _e('form'); ?> <?php _e('elements'); ?>
                </h2>
                <form class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <?php _e('name'); ?> <span class="text-red-500">*</span>
                        </label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                               placeholder="<?php _e('name'); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <?php _e('email'); ?> <span class="text-red-500">*</span>
                        </label>
                        <input type="email" class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                               placeholder="<?php _e('email'); ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <?php _e('phone'); ?>
                        </label>
                        <input type="tel" class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                               placeholder="<?php _e('phone'); ?>">
                    </div>
                    <div class="flex space-x-2">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            <?php _e('submit'); ?>
                        </button>
                        <button type="button" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                            <?php _e('reset'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-8">
            <div class="bg-white rounded-lg shadow p-6 text-center">
                <div class="text-3xl font-bold text-blue-600 mb-2">150</div>
                <div class="text-sm text-gray-600"><?php _e('total_products'); ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 text-center">
                <div class="text-3xl font-bold text-green-600 mb-2">25</div>
                <div class="text-sm text-gray-600"><?php _e('active_suppliers'); ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 text-center">
                <div class="text-3xl font-bold text-purple-600 mb-2">8</div>
                <div class="text-sm text-gray-600"><?php _e('purchase_orders'); ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6 text-center">
                <div class="text-3xl font-bold text-red-600 mb-2">3</div>
                <div class="text-sm text-gray-600"><?php _e('low_stock_items'); ?></div>
            </div>
        </div>

        <!-- Messages Demo -->
        <div class="mt-8 space-y-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <i class="fas fa-check-circle mr-2"></i>
                <?php _e('data_saved'); ?>
            </div>
            <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                <i class="fas fa-info-circle mr-2"></i>
                <?php echo __('dashboard_welcome', ['name' => $_SESSION['full_name']]); ?>
            </div>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?php _e('field_required'); ?>
            </div>
        </div>

        <!-- JavaScript Demo -->
        <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
                <i class="fab fa-js-square text-yellow-500 mr-2"></i>
                JavaScript <?php _e('demo'); ?>
            </h2>
            <div class="space-x-2">
                <button onclick="showJSMessage('success')" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    <?php _e('success'); ?> <?php _e('message'); ?>
                </button>
                <button onclick="showJSMessage('error')" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    <?php _e('error'); ?> <?php _e('message'); ?>
                </button>
                <button onclick="showJSMessage('confirm')" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    <?php _e('confirm'); ?> <?php _e('message'); ?>
                </button>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="mt-8 text-center">
            <h3 class="text-xl font-semibold text-gray-900 mb-4"><?php _e('quick_actions'); ?></h3>
            <div class="space-x-4">
                <a href="admin/dashboard.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition duration-200">
                    <i class="fas fa-tachometer-alt mr-2"></i><?php _e('admin_dashboard'); ?>
                </a>
                <a href="suppliers/index.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition duration-200">
                    <i class="fas fa-truck mr-2"></i><?php _e('suppliers_management'); ?>
                </a>
                <a href="purchase_orders/index.php" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-medium transition duration-200">
                    <i class="fas fa-file-invoice mr-2"></i><?php _e('purchase_orders_management'); ?>
                </a>
            </div>
        </div>
    </div>

    <script>
        function showJSMessage(type) {
            let message;
            switch(type) {
                case 'success':
                    message = __('data_saved');
                    alert('✅ ' + message);
                    break;
                case 'error':
                    message = __('operation_failed');
                    alert('❌ ' + message);
                    break;
                case 'confirm':
                    message = __('confirm_delete');
                    if (confirm('⚠️ ' + message)) {
                        alert('✅ ' + __('data_deleted'));
                    }
                    break;
            }
        }

        // Test JavaScript translations
        console.log('JavaScript Translation Test:');
        console.log('Current Language:', window.currentLanguage);
        console.log('Welcome message:', __('welcome'));
        console.log('Dashboard:', __('dashboard'));
        console.log('Products:', __('products'));
    </script>
</body>
</html>
