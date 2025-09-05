-- Database Update Script for Clinic Fee Feature
-- Run this SQL to update your existing dental_system database
-- Execute these commands in phpMyAdmin or MySQL command line

USE dental_system;

-- 1. Add clinic_fee column to receipts table
ALTER TABLE receipts 
ADD COLUMN clinic_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 
AFTER invoice_date;

-- 2. Rename base_cost column to doctor_fee (if it exists)
-- Check if base_cost column exists and rename it
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'dental_system' 
    AND TABLE_NAME = 'receipts' 
    AND COLUMN_NAME = 'base_cost'
);

-- If base_cost exists, rename it to doctor_fee
SET @sql = CASE 
    WHEN @column_exists > 0 THEN 'ALTER TABLE receipts CHANGE base_cost doctor_fee DECIMAL(10,2) NOT NULL;'
    ELSE 'SELECT "base_cost column does not exist, skipping rename" as message;'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Update receipt_services table to remove calculation columns
-- Check if percentage column exists and remove it
SET @percentage_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'dental_system' 
    AND TABLE_NAME = 'receipt_services' 
    AND COLUMN_NAME = 'percentage'
);

SET @sql = CASE 
    WHEN @percentage_exists > 0 THEN 'ALTER TABLE receipt_services DROP COLUMN percentage;'
    ELSE 'SELECT "percentage column does not exist, skipping drop" as message;'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check if amount column exists and remove it
SET @amount_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'dental_system' 
    AND TABLE_NAME = 'receipt_services' 
    AND COLUMN_NAME = 'amount'
);

SET @sql = CASE 
    WHEN @amount_exists > 0 THEN 'ALTER TABLE receipt_services DROP COLUMN amount;'
    ELSE 'SELECT "amount column does not exist, skipping drop" as message;'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Verify the final table structure
DESCRIBE receipts;
DESCRIBE receipt_services;

-- 5. Show success message
SELECT 'Database schema updated successfully! Clinic fee feature is now ready.' as status;