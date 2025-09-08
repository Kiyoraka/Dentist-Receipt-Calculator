-- Update admin password to 'admin123'
-- This will change the existing admin user's password

-- The hash below is for password 'admin123'
-- Generated using PHP: password_hash('admin123', PASSWORD_DEFAULT)
UPDATE users 
SET password = '$2y$10$Hgx3wz8TWHheT7pF8gl2YuXXhMwlh7c9KVxn5w/aKYnQHfLOHL1Xu' 
WHERE username = 'admin';

-- Verify the update
SELECT username, full_name, role FROM users WHERE username = 'admin';

-- Note: After running this, login with:
-- Username: admin
-- Password: admin123