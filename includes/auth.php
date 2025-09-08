<?php
// Authentication middleware - Include this at the top of protected pages
session_start();

// Check if user is logged in
function isAuthenticated() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Redirect to login if not authenticated
function requireAuth() {
    if (!isAuthenticated()) {
        // Store the current page to redirect back after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
}

// Get current user information
function getCurrentUser() {
    if (isAuthenticated()) {
        return [
            'username' => $_SESSION['username'] ?? 'Unknown',
            'login_time' => $_SESSION['login_time'] ?? time()
        ];
    }
    return null;
}

// Logout function
function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Check if logout is requested via GET parameter
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    logout();
}
?>