-- ============================================
-- Dentist Receipt Calculator Database (Modified)
-- Optimized for Hostinger Production Deployment
-- Version: 1.0-MOD
-- Date: 2025-09-06
-- ============================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- ============================================
-- Database Structure
-- ============================================


-- ============================================
-- Table: dental_services
-- Core dental services with doctor fee percentages
-- ============================================
CREATE TABLE IF NOT EXISTS `dental_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(100) NOT NULL,
  `percentage` decimal(5,2) NOT NULL COMMENT 'Doctor fee percentage',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_service_name` (`service_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default dental services data
INSERT INTO `dental_services` (`service_name`, `percentage`) VALUES
('Consult', 80.00),
('Filling', 40.00),
('Composite', 50.00),
('Implant', 60.00),
('Denture', 60.00),
('Bridgework', 30.00),
('Package', 30.00),
('Oral Surgery', 30.00),
('X-ray', 30.00),
('Trauma', 40.00),
('Extraction', 40.00),
('Medication', 0.00);

-- ============================================
-- Table: patients
-- Patient information management
-- ============================================
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_patient_name` (`name`),
  KEY `idx_patient_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: receipts
-- Main receipts/invoices table
-- ============================================
CREATE TABLE IF NOT EXISTS `receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_date` date NOT NULL,
  `clinic_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Material fee for clinic',
  `doctor_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Doctor professional fee',
  `other_charges` decimal(10,2) DEFAULT 0.00 COMMENT 'Additional charges total',
  `payment_method` varchar(50) NOT NULL,
  `payment_fee_percentage` decimal(5,2) DEFAULT 0.00,
  `payment_fee_amount` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_invoice` (`invoice_number`),
  KEY `idx_patient` (`patient_id`),
  KEY `idx_invoice_date` (`invoice_date`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_receipt_patient` FOREIGN KEY (`patient_id`) 
    REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: receipt_services
-- Services included in each receipt
-- ============================================
CREATE TABLE IF NOT EXISTS `receipt_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_receipt` (`receipt_id`),
  CONSTRAINT `fk_service_receipt` FOREIGN KEY (`receipt_id`) 
    REFERENCES `receipts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: receipt_charges
-- Additional charges for each receipt
-- ============================================
CREATE TABLE IF NOT EXISTS `receipt_charges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_receipt_charge` (`receipt_id`),
  CONSTRAINT `fk_charge_receipt` FOREIGN KEY (`receipt_id`) 
    REFERENCES `receipts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Cleanup and Reset AUTO_INCREMENT
-- ============================================
ALTER TABLE `dental_services` AUTO_INCREMENT = 1;
ALTER TABLE `patients` AUTO_INCREMENT = 1;
ALTER TABLE `receipts` AUTO_INCREMENT = 1;
ALTER TABLE `receipt_services` AUTO_INCREMENT = 1;
ALTER TABLE `receipt_charges` AUTO_INCREMENT = 1;

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

-- ============================================
-- End of Database Structure
-- Ready for Hostinger Production Deployment
-- ============================================