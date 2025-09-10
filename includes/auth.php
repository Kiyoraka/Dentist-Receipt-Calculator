<?php
// Authentication middleware - Database-based authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
function isAuthenticated() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id']);
}

// Redirect to login if not authenticated
function requireAuth() {
    if (!isAuthenticated()) {
        // Store the current page to redirect back after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

// Authenticate user with database
function authenticateUser($username, $password) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get user from database
        $stmt = $conn->prepare("SELECT id, username, password, full_name, email, role, is_active FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify user exists and password is correct
        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Set session variables
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

// Get current user information
function getCurrentUser() {
    if (isAuthenticated()) {
        try {
            $db = new Database();
            $conn = $db->getConnection();
            
            // Get fresh user data from database
            $stmt = $conn->prepare("SELECT id, username, full_name, email, role, last_login FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                return [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'login_time' => $_SESSION['login_time'] ?? time(),
                    'last_login' => $user['last_login']
                ];
            }
        } catch (PDOException $e) {
            error_log("Get current user error: " . $e->getMessage());
        }
        
        // Fallback to session data
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? 'Unknown',
            'full_name' => $_SESSION['full_name'] ?? 'Unknown User',
            'email' => $_SESSION['email'] ?? null,
            'role' => $_SESSION['role'] ?? 'admin',
            'login_time' => $_SESSION['login_time'] ?? time()
        ];
    }
    return null;
}

// Change user password
function changeUserPassword($currentPassword, $newPassword) {
    if (!isAuthenticated()) {
        return ['success' => false, 'message' => 'User not authenticated'];
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get current user's password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Hash new password and update
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$hashedPassword, $_SESSION['user_id']]);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
        
    } catch (PDOException $e) {
        error_log("Change password error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

// Logout function
function logout() {
    session_destroy();
    // Check if we're on production or development
    $isProduction = (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'caninehubdentist.com') !== false);
    
    if ($isProduction) {
        header('Location: https://caninehubdentist.com/login.php');
    } else {
        header('Location: ' . BASE_URL . '/login.php');
    }
    exit();
}

// Check if logout is requested via GET parameter
if (isset($_GET['logout']) && $_GET['logout'] === '1') {
    logout();
}
?>