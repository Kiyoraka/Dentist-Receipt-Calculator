<?php
// Authentication protection
require_once '../includes/auth.php';
requireAuth();

header('Content-Type: application/json');

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$current_password = $input['current_password'] ?? '';
$new_password = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';

// Validate inputs
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
    exit();
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
    exit();
}

// Get current user
$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

// For now, use simple validation since we're using hardcoded credentials
// In production, this should validate against database
if ($user['username'] === 'admin' && $current_password === 'dental2025') {
    // In a real system, you would update the database here
    // For now, we'll just simulate success
    echo json_encode([
        'success' => true, 
        'message' => 'Password change simulated successfully. In production, this would update the database.',
        'note' => 'Current system uses hardcoded authentication. Implement database users table for real password changes.'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
}
?>