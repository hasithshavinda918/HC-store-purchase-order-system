<?php
// Include language system
require_once __DIR__ . '/language.php';

// Determine the current page for navigation highlighting
$current_page = $current_page ?? '';
?>
<nav class="bg-blue-800 shadow-lg">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <a href="../dashboard.php" class="text-white text-xl font-bold">HC Store</a>
                </div>
                <div class="hidden md:block">
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="../dashboard.php" 
                           class="<?= $current_page === 'dashboard' ? 'bg-blue-900 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-tachometer-alt mr-2"></i><?php _e('dashboard'); ?>
                        </a>
                        
                        <a href="../products/index.php" 
                           class="<?= $current_page === 'products' ? 'bg-blue-900 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-box mr-2"></i><?php _e('products'); ?>
                        </a>
                        
                        <a href="../categories/index.php" 
                           class="<?= $current_page === 'categories' ? 'bg-blue-900 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-tags mr-2"></i><?php _e('categories'); ?>
                        </a>
                        
                        <a href="../suppliers/index.php" 
                           class="<?= $current_page === 'suppliers' ? 'bg-blue-900 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-truck mr-2"></i><?php _e('suppliers'); ?>
                        </a>
                        
                        <a href="../purchase_orders/index.php" 
                           class="<?= $current_page === 'purchase_orders' ? 'bg-blue-900 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-file-invoice mr-2"></i><?php _e('purchase_orders'); ?>
                        </a>
                        
                        <a href="../users/index.php" 
                           class="<?= $current_page === 'users' ? 'bg-blue-900 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-users mr-2"></i><?php _e('users'); ?>
                        </a>
                        
                        <a href="../reports/index.php" 
                           class="<?= $current_page === 'reports' ? 'bg-blue-900 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-chart-bar mr-2"></i>Reports
                        </a>
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
                        <?php echo __('welcome') . ', ' . htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                        <span class="bg-blue-600 px-2 py-1 rounded text-xs ml-2">
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
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 bg-blue-900">
            <a href="../dashboard.php" 
               class="<?= $current_page === 'dashboard' ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
            </a>
            
            <a href="../products/index.php" 
               class="<?= $current_page === 'products' ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                <i class="fas fa-box mr-2"></i>Products
            </a>
            
            <a href="../categories/index.php" 
               class="<?= $current_page === 'categories' ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                <i class="fas fa-tags mr-2"></i>Categories
            </a>
            
            <a href="../suppliers/index.php" 
               class="<?= $current_page === 'suppliers' ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                <i class="fas fa-truck mr-2"></i>Suppliers
            </a>
            
            <a href="../purchase_orders/index.php" 
               class="<?= $current_page === 'purchase_orders' ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                <i class="fas fa-file-invoice mr-2"></i>Purchase Orders
            </a>
            
            <a href="../users/index.php" 
               class="<?= $current_page === 'users' ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                <i class="fas fa-users mr-2"></i>Users
            </a>
            
            <a href="../reports/index.php" 
               class="<?= $current_page === 'reports' ? 'bg-blue-800 text-white' : 'text-gray-300 hover:bg-blue-700 hover:text-white' ?> block px-3 py-2 rounded-md text-base font-medium">
                <i class="fas fa-chart-bar mr-2"></i>Reports
            </a>
            
            <div class="border-t border-blue-700 pt-3 mt-3">
                <div class="px-3 py-2 text-gray-300 text-sm">
                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
                    <span class="bg-blue-600 px-2 py-1 rounded text-xs ml-2">
                        <?php echo ucfirst($_SESSION['role']); ?>
                    </span>
                </div>
                <a href="../logout.php" class="text-gray-300 hover:bg-red-600 hover:text-white block px-3 py-2 rounded-md text-base font-medium">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

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
