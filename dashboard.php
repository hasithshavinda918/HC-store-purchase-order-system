<?php
require_once 'includes/auth.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Redirect based on user role
if (isAdmin()) {
    header('Location: admin/dashboard.php');
} else {
    header('Location: staff/dashboard.php');
}
exit();
?>
