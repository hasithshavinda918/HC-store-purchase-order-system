<?php
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: staff/dashboard.php');
    }
    exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (login($username, $password)) {
            if ($_SESSION['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: staff/dashboard.php');
            }
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - HC Store</title>
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
    </script>
</head>
<body class="bg-gradient-to-br from-hc-blue to-hc-dark min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8">
        <div class="bg-white rounded-lg shadow-2xl p-8">
            <!-- Logo and Title -->
            <div class="text-center">
                <div class="mx-auto h-20 w-20 bg-hc-blue rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-store text-white text-3xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-gray-900 mb-2">HC Store</h2>
                <p class="text-gray-600 mb-8">Stock Management System</p>
                <p class="text-sm text-gray-500 mb-6">Colombo, Sri Lanka</p>
            </div>

            <!-- Error Message -->
            <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user mr-2"></i>Username
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           required 
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-hc-blue focus:border-transparent"
                           placeholder="Enter your username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock mr-2"></i>Password
                    </label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           class="w-full px-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-hc-blue focus:border-transparent"
                           placeholder="Enter your password">
                </div>

                <div>
                    <button type="submit" 
                            class="w-full bg-hc-blue hover:bg-hc-dark text-white font-semibold py-3 px-4 rounded-lg transition duration-200 ease-in-out transform hover:scale-105">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In
                    </button>
                </div>
            </form>

            <!-- Demo Credentials -->
            <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Demo Credentials:</h3>
                <div class="text-xs text-gray-600 space-y-1">
                    <div><strong>Admin:</strong> username: admin, password: admin123</div>
                    <div><strong>Staff:</strong> Create staff accounts via admin panel</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-white text-sm">
            <p>&copy; <?php echo date('Y'); ?> HC Store. All rights reserved.</p>
        </div>
    </div>

    <script>
        // Focus on username field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });

        // Handle Enter key press
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>
