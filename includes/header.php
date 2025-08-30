<?php
// Include language system
require_once __DIR__ . '/language.php';
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - HC Store' : __('welcome') . ' - HC Store'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'hc-blue': '#1e40af',
                        'hc-light': '#3b82f6',
                        'hc-dark': '#1e3a8a'
                    }
                }
            }
        }
        
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
    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Navigation -->
    <nav class="bg-hc-blue shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-white text-xl font-bold">HC Store</h1>
                    </div>
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a href="<?php echo ($_SESSION['role'] === 'admin') ? '../admin/dashboard.php' : '../staff/dashboard.php'; ?>" 
                               class="text-gray-300 hover:bg-hc-light hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-tachometer-alt mr-2"></i><?php _e('dashboard'); ?>
                            </a>
                            
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="../products/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                    <i class="fas fa-box mr-2"></i><?php _e('products'); ?>
                                </a>
                                <a href="../categories/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                    <i class="fas fa-tags mr-2"></i><?php _e('categories'); ?>
                                </a>
                                <a href="../suppliers/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                    <i class="fas fa-truck mr-2"></i><?php _e('suppliers'); ?>
                                </a>
                                <a href="../purchase_orders/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                    <i class="fas fa-file-invoice mr-2"></i><?php _e('purchase_orders'); ?>
                                </a>
                                <a href="../users/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                    <i class="fas fa-users mr-2"></i><?php _e('users'); ?>
                                </a>
                                <a href="../reports/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                    <i class="fas fa-chart-bar mr-2"></i><?php _e('reports'); ?>
                                </a>
                            <?php else: ?>
                                <a href="../products/view.php" class="text-gray-300 hover:bg-hc-light hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                    <i class="fas fa-box mr-2"></i><?php _e('products'); ?>
                                </a>
                                <a href="../products/stock_update.php" class="text-gray-300 hover:bg-hc-light hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                    <i class="fas fa-edit mr-2"></i><?php echo __('update') . ' ' . __('stock_movements'); ?>
                                </a>
                                <a href="../reports/basic.php" class="text-gray-300 hover:bg-hc-light hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                    <i class="fas fa-list mr-2"></i><?php echo __('stock_movements') . ' ' . __('reports'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="hidden md:block">
                    <div class="ml-4 flex items-center md:ml-6 space-x-4">
                        <!-- Language Selector -->
                        <div class="language-selector-nav">
                            <?php echo generateLanguageSelector(); ?>
                        </div>
                        
                        <div class="text-gray-300 text-sm">
                            <?php echo __('welcome') . ', ' . htmlspecialchars($_SESSION['full_name']); ?>
                            <span class="bg-hc-light px-2 py-1 rounded text-xs ml-2">
                                <?php echo ucfirst(__($_SESSION['role'])); ?>
                            </span>
                        </div>
                        <a href="../logout.php" class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-sign-out-alt mr-2"></i><?php _e('logout'); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button class="mobile-menu-button text-gray-300 hover:text-white focus:outline-none focus:text-white">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div class="mobile-menu hidden md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="<?php echo ($_SESSION['role'] === 'admin') ? '../admin/dashboard.php' : '../staff/dashboard.php'; ?>" 
                   class="text-gray-300 hover:bg-hc-light hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    Dashboard
                </a>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="../products/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white block px-3 py-2 rounded-md text-base font-medium">Products</a>
                    <a href="../categories/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white block px-3 py-2 rounded-md text-base font-medium">Categories</a>
                    <a href="../suppliers/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white block px-3 py-2 rounded-md text-base font-medium">Suppliers</a>
                    <a href="../purchase_orders/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white block px-3 py-2 rounded-md text-base font-medium">Purchase Orders</a>
                    <a href="../users/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white block px-3 py-2 rounded-md text-base font-medium">Users</a>
                    <a href="../reports/index.php" class="text-gray-300 hover:bg-hc-light hover:text-white block px-3 py-2 rounded-md text-base font-medium">Reports</a>
                <?php else: ?>
                    <a href="../products/view.php" class="text-gray-300 hover:bg-hc-light hover:text-white block px-3 py-2 rounded-md text-base font-medium">View Products</a>
                    <a href="../products/stock_update.php" class="text-gray-300 hover:bg-hc-light hover:text-white block px-3 py-2 rounded-md text-base font-medium">Update Stock</a>
                    <a href="../reports/basic.php" class="text-gray-300 hover:bg-hc-light hover:text-white block px-3 py-2 rounded-md text-base font-medium">Stock Report</a>
                <?php endif; ?>
                <a href="../logout.php" class="text-gray-300 hover:bg-red-600 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Logout</a>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuButton = document.querySelector('.mobile-menu-button');
            const mobileMenu = document.querySelector('.mobile-menu');
            
            if (mobileMenuButton && mobileMenu) {
                mobileMenuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                });
            }
        });
    </script>
