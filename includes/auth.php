<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user has admin role
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user has staff role
function isStaff() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ../dashboard.php');
        exit();
    }
}

// Login function
function login($username, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, username, password, full_name, email, role FROM users WHERE username = ? AND is_active = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
    }
    return false;
}

// Logout function
function logout() {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Get current user info
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}

// Log stock movement
function logStockMovement($product_id, $user_id, $movement_type, $quantity_change, $previous_quantity, $new_quantity, $reason = '', $notes = '') {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, user_id, movement_type, quantity_change, previous_quantity, new_quantity, reason, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisiisss", $product_id, $user_id, $movement_type, $quantity_change, $previous_quantity, $new_quantity, $reason, $notes);
    return $stmt->execute();
}
?>
