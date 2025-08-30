<?php
require_once '../includes/auth.php';
requireAdmin();

$error = '';
$success = '';

// Get user ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php?error=' . urlencode('User not found'));
    exit();
}

$user_id = intval($_GET['id']);

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php?error=' . urlencode('User not found'));
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($full_name)) {
        $error = 'Full name is required.';
    } elseif (empty($username)) {
        $error = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores.';
    } elseif (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (empty($role) || !in_array($role, ['admin', 'staff'])) {
        $error = 'Valid role is required.';
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif ($user_id == $_SESSION['user_id'] && !$is_active) {
        $error = 'You cannot deactivate your own account.';
    } else {
        // Check if username already exists (excluding current user)
        $check_username = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_username->bind_param("si", $username, $user_id);
        $check_username->execute();
        if ($check_username->get_result()->num_rows > 0) {
            $error = 'Username already exists.';
        }
        $check_username->close();
        
        // Check if email already exists (excluding current user)
        if (empty($error)) {
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->bind_param("si", $email, $user_id);
            $check_email->execute();
            if ($check_email->get_result()->num_rows > 0) {
                $error = 'Email already exists.';
            }
            $check_email->close();
        }
        
        // Update user if no errors
        if (empty($error)) {
            if (!empty($new_password)) {
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, role = ?, password = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param("ssssssii", $full_name, $username, $email, $phone, $role, $hashed_password, $is_active, $user_id);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, role = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param("sssssii", $full_name, $username, $email, $phone, $role, $is_active, $user_id);
            }
            
            if ($stmt->execute()) {
                header('Location: index.php?success=' . urlencode('User "' . $full_name . '" updated successfully'));
                exit();
            } else {
                $error = 'Error updating user: ' . $conn->error;
            }
            $stmt->close();
        }
    }
} else {
    // Pre-fill form with existing data
    $_POST = $user;
}

// Set page title and include header after all redirects
$page_title = 'Edit User';
include '../includes/header.php';
?>

<div class="max-w-2xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center">
            <a href="index.php" class="text-gray-500 hover:text-gray-700 mr-4">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Edit User</h1>
                <p class="text-gray-600 mt-2">Update user information for <?php echo htmlspecialchars($user['full_name']); ?></p>
            </div>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- User Info & Edit Form -->
    <div class="space-y-6">
        <!-- User Information -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Account Information</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <label class="block text-gray-500">User ID</label>
                        <p class="font-semibold text-gray-900"><?php echo $user['id']; ?></p>
                    </div>
                    <div>
                        <label class="block text-gray-500">Account Created</label>
                        <p class="font-semibold text-gray-900"><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></p>
                    </div>
                    <?php if ($user['updated_at'] != $user['created_at']): ?>
                    <div>
                        <label class="block text-gray-500">Last Updated</label>
                        <p class="font-semibold text-gray-900"><?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                    <div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            <i class="fas fa-user mr-1"></i>
                            Your Account
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="bg-white shadow-lg rounded-lg">
            <form method="POST" action="" id="userForm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Edit User Details</h2>
                </div>
                
                <div class="p-6 space-y-6">
                    <!-- Full Name -->
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Full Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               value="<?php echo htmlspecialchars($_POST['full_name']); ?>"
                               required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Username -->
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Username <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               value="<?php echo htmlspecialchars($_POST['username']); ?>"
                               required 
                               minlength="3"
                               pattern="[a-zA-Z0-9_]+"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($_POST['email']); ?>"
                               required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Phone Number
                        </label>
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                            Role <span class="text-red-500">*</span>
                        </label>
                        <select id="role" 
                                name="role" 
                                required 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="admin" <?php echo ($_POST['role'] === 'admin') ? 'selected' : ''; ?>>
                                Admin - Full system access
                            </option>
                            <option value="staff" <?php echo ($_POST['role'] === 'staff') ? 'selected' : ''; ?>>
                                Staff - Limited access
                            </option>
                        </select>
                    </div>

                    <!-- Password Section -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Change Password (Optional)</h3>
                        
                        <!-- New Password -->
                        <div class="mb-4">
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                New Password
                            </label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   minlength="6"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Leave blank to keep current password">
                            <p class="text-sm text-gray-500 mt-1">Leave blank to keep the current password unchanged.</p>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm New Password
                            </label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   minlength="6"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Confirm new password">
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="border-t border-gray-200 pt-6">
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   name="is_active" 
                                   value="1"
                                   <?php echo $_POST['is_active'] ? 'checked' : ''; ?>
                                   <?php echo ($user_id == $_SESSION['user_id']) ? 'disabled' : ''; ?>
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Account is active</span>
                        </label>
                        <?php if ($user_id == $_SESSION['user_id']): ?>
                            <p class="text-sm text-gray-500 mt-1">You cannot deactivate your own account.</p>
                            <input type="hidden" name="is_active" value="1">
                        <?php else: ?>
                            <p class="text-sm text-gray-500 mt-1">Inactive users cannot log in to the system.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                    <a href="index.php" 
                       class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium">
                        Cancel
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                        <i class="fas fa-save mr-2"></i>
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('userForm');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    // Password confirmation validation
    function validatePasswords() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('change', validatePasswords);
    confirmPassword.addEventListener('keyup', validatePasswords);
    
    // Form submission validation
    form.addEventListener('submit', function(e) {
        // Only validate passwords if new password is provided
        if (newPassword.value && newPassword.value !== confirmPassword.value) {
            e.preventDefault();
            showAlert('Passwords do not match', 'error');
            return;
        }
        
        if (!validateForm('userForm')) {
            e.preventDefault();
            showAlert('Please fill in all required fields', 'error');
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
