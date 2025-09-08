<?php
// Debug script to check authentication issues
require_once 'config/database.php';

echo "<h2>Authentication Debug Check</h2>";
echo "<pre>";

// 1. Check database connection
echo "1. DATABASE CONNECTION CHECK:\n";
echo "=============================\n";
try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "✅ Database connection successful\n\n";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n\n";
    exit;
}

// 2. Check if users table exists
echo "2. USERS TABLE CHECK:\n";
echo "=====================\n";
try {
    $stmt = $conn->prepare("SHOW TABLES LIKE 'users'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "✅ Users table exists\n\n";
    } else {
        echo "❌ Users table does NOT exist!\n";
        echo "Please run the SQL file: database/users-table.sql\n\n";
        exit;
    }
} catch (PDOException $e) {
    echo "❌ Error checking table: " . $e->getMessage() . "\n\n";
}

// 3. Check users in database
echo "3. USERS IN DATABASE:\n";
echo "====================\n";
try {
    $stmt = $conn->prepare("SELECT id, username, full_name, email, role, is_active FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users) > 0) {
        echo "Found " . count($users) . " user(s):\n";
        foreach ($users as $user) {
            echo "- Username: " . $user['username'] . 
                 " | Name: " . $user['full_name'] . 
                 " | Role: " . $user['role'] . 
                 " | Active: " . ($user['is_active'] ? 'Yes' : 'No') . "\n";
        }
        echo "\n";
    } else {
        echo "❌ No users found in database!\n";
        echo "Please run: database/users-table.sql\n\n";
    }
} catch (PDOException $e) {
    echo "❌ Error fetching users: " . $e->getMessage() . "\n\n";
}

// 4. Test password verification
echo "4. PASSWORD VERIFICATION TEST:\n";
echo "==============================\n";
$test_password = 'admin123';
echo "Testing password: '$test_password'\n";

try {
    $stmt = $conn->prepare("SELECT username, password FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "Admin user found\n";
        echo "Password hash in DB: " . substr($admin['password'], 0, 20) . "...\n";
        
        // Test password verification
        if (password_verify($test_password, $admin['password'])) {
            echo "✅ Password 'admin123' is VALID\n\n";
        } else {
            echo "❌ Password 'admin123' is INVALID\n";
            echo "The password in database doesn't match\n\n";
            
            // Generate correct hash
            echo "5. GENERATING CORRECT HASH:\n";
            echo "===========================\n";
            $correct_hash = password_hash('admin123', PASSWORD_DEFAULT);
            echo "New hash for 'admin123': $correct_hash\n\n";
            echo "To fix, run this SQL:\n";
            echo "UPDATE users SET password = '$correct_hash' WHERE username = 'admin';\n\n";
        }
    } else {
        echo "❌ Admin user not found in database\n\n";
    }
} catch (PDOException $e) {
    echo "❌ Error testing password: " . $e->getMessage() . "\n\n";
}

// 5. Test the authentication function
echo "6. AUTHENTICATION FUNCTION TEST:\n";
echo "================================\n";
require_once 'includes/auth.php';

if (authenticateUser('admin', 'admin123')) {
    echo "✅ Authentication function works correctly!\n";
} else {
    echo "❌ Authentication function failed\n";
    echo "Check the includes/auth.php file\n";
}

echo "</pre>";
?>

<hr>
<h3>Quick Actions:</h3>
<ul>
    <li><a href="login.php">Go to Login Page</a></li>
    <li><a href="index.php">Go to Dashboard</a></li>
</ul>