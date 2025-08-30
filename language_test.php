<?php
require_once 'includes/language.php';

// Handle language change
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'si', 'ta'])) {
    setLanguage($_GET['lang']);
    $_SESSION['success'] = __('language_changed');
    header('Location: ' . ($_GET['redirect'] ?? 'language_test.php'));
    exit;
}

$page_title = __('select_language');
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - HC Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .language-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .language-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <div class="container mx-auto px-4 py-16">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">
                <i class="fas fa-language text-blue-600 mr-3"></i>
                <?php _e('select_language'); ?>
            </h1>
            <p class="text-lg text-gray-600">
                Choose your preferred language for the HC Store Purchase Order Management System
            </p>
        </div>

        <!-- Language Cards -->
        <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-8">
            
            <!-- English -->
            <div class="language-card bg-white rounded-xl shadow-lg p-8 text-center border-2 border-gray-200 hover:border-blue-500">
                <div class="text-6xl mb-4">🇬🇧</div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">English</h3>
                <p class="text-gray-600 mb-6">Full system support in English language</p>
                <a href="?lang=en&redirect=admin/dashboard.php" 
                   class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-lg transition duration-200 transform hover:scale-105">
                    <i class="fas fa-check mr-2"></i>Select English
                </a>
                <div class="mt-4 text-sm text-gray-500">
                    Current: <?php echo getCurrentLanguage() === 'en' ? '✓ Active' : 'Available'; ?>
                </div>
            </div>

            <!-- Sinhala -->
            <div class="language-card bg-white rounded-xl shadow-lg p-8 text-center border-2 border-gray-200 hover:border-green-500">
                <div class="text-6xl mb-4">🇱🇰</div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">සිංහල</h3>
                <p class="text-gray-600 mb-6">සිංහල භාෂාවෙන් සම්පූර්ණ පද්ධති සහාය</p>
                <a href="?lang=si&redirect=admin/dashboard.php" 
                   class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-8 rounded-lg transition duration-200 transform hover:scale-105">
                    <i class="fas fa-check mr-2"></i>සිංහල තෝරන්න
                </a>
                <div class="mt-4 text-sm text-gray-500">
                    Current: <?php echo getCurrentLanguage() === 'si' ? '✓ සක්‍රීය' : 'ලබා ගත හැක'; ?>
                </div>
            </div>

            <!-- Tamil -->
            <div class="language-card bg-white rounded-xl shadow-lg p-8 text-center border-2 border-gray-200 hover:border-red-500">
                <div class="text-6xl mb-4">🇱🇰</div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">தமிழ்</h3>
                <p class="text-gray-600 mb-6">தமிழ் மொழியில் முழு கணினி ஆதரவு</p>
                <a href="?lang=ta&redirect=admin/dashboard.php" 
                   class="inline-block bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-8 rounded-lg transition duration-200 transform hover:scale-105">
                    <i class="fas fa-check mr-2"></i>தமிழ் தேர்ந்தெடுக்கவும்
                </a>
                <div class="mt-4 text-sm text-gray-500">
                    Current: <?php echo getCurrentLanguage() === 'ta' ? '✓ செயலில்' : 'கிடைக்கிறது'; ?>
                </div>
            </div>
        </div>

        <!-- Features -->
        <div class="max-w-6xl mx-auto mt-16">
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h2 class="text-2xl font-bold text-center text-gray-900 mb-8">
                    <i class="fas fa-star text-yellow-500 mr-2"></i>
                    Multi-Language System Features
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="text-center p-4">
                        <div class="bg-blue-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-globe text-2xl text-blue-600"></i>
                        </div>
                        <h3 class="font-semibold text-gray-900">Multi-Language</h3>
                        <p class="text-sm text-gray-600 mt-2">Support for English, Sinhala, and Tamil</p>
                    </div>
                    
                    <div class="text-center p-4">
                        <div class="bg-green-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-sync-alt text-2xl text-green-600"></i>
                        </div>
                        <h3 class="font-semibold text-gray-900">Dynamic Switching</h3>
                        <p class="text-sm text-gray-600 mt-2">Change language anytime without logout</p>
                    </div>
                    
                    <div class="text-center p-4">
                        <div class="bg-purple-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-user text-2xl text-purple-600"></i>
                        </div>
                        <h3 class="font-semibold text-gray-900">User Preference</h3>
                        <p class="text-sm text-gray-600 mt-2">Remembers your language choice</p>
                    </div>
                    
                    <div class="text-center p-4">
                        <div class="bg-red-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-mobile-alt text-2xl text-red-600"></i>
                        </div>
                        <h3 class="font-semibold text-gray-900">Responsive</h3>
                        <p class="text-sm text-gray-600 mt-2">Works perfectly on all devices</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Language Info -->
        <div class="max-w-2xl mx-auto mt-12 bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                Current System Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <strong>Active Language:</strong> 
                    <span class="text-blue-600"><?php echo LanguageManager::getInstance()->getLanguageName(); ?></span>
                </div>
                <div>
                    <strong>Language Code:</strong> 
                    <span class="text-green-600"><?php echo getCurrentLanguage(); ?></span>
                </div>
                <div>
                    <strong>Supported Languages:</strong> 
                    <span class="text-purple-600"><?php echo count(getSupportedLanguages()); ?> languages</span>
                </div>
                <div>
                    <strong>Auto-Detection:</strong> 
                    <span class="text-orange-600">Enabled</span>
                </div>
            </div>
        </div>

        <!-- Test Links -->
        <div class="text-center mt-12">
            <h3 class="text-xl font-semibold text-gray-900 mb-4">Test Language System</h3>
            <div class="space-x-4">
                <a href="admin/dashboard.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium transition duration-200">
                    <i class="fas fa-tachometer-alt mr-2"></i>Admin Dashboard
                </a>
                <a href="suppliers/index.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition duration-200">
                    <i class="fas fa-truck mr-2"></i>Suppliers
                </a>
                <a href="purchase_orders/index.php" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg font-medium transition duration-200">
                    <i class="fas fa-file-invoice mr-2"></i>Purchase Orders
                </a>
            </div>
        </div>
    </div>

    <script>
        // Add some animation effects
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.language-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('animate-fadeIn');
            });
        });
    </script>

    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn {
            animation: fadeIn 0.5s ease-out forwards;
        }
    </style>
</body>
</html>
