-- Dental Practice Management System Database
-- Created for XAMPP MySQL

CREATE DATABASE IF NOT EXISTS dental_system;
USE dental_system;

-- Patients table
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Dental services reference table
CREATE TABLE dental_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Receipts/Invoices table
CREATE TABLE receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    invoice_number VARCHAR(50) NOT NULL UNIQUE,
    invoice_date DATE NOT NULL,
    base_cost DECIMAL(10,2) NOT NULL,
    services_total DECIMAL(10,2) NOT NULL,
    other_charges DECIMAL(10,2) DEFAULT 0.00,
    payment_method VARCHAR(50) NOT NULL,
    payment_fee_percentage DECIMAL(5,2) DEFAULT 0.00,
    payment_fee_amount DECIMAL(10,2) DEFAULT 0.00,
    terminal_charge_percentage DECIMAL(5,2) DEFAULT 0.00,
    terminal_charge_amount DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
);

-- Receipt services (selected services for each receipt)
CREATE TABLE receipt_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    service_name VARCHAR(100) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_id) REFERENCES receipts(id) ON DELETE CASCADE
);

-- Receipt additional charges
CREATE TABLE receipt_charges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (receipt_id) REFERENCES receipts(id) ON DELETE CASCADE
);

-- Insert default dental services
INSERT INTO dental_services (service_name, percentage) VALUES
('Consult', 30.00),
('Filling', 30.00),
('Composite', 50.00),
('Implant', 60.00),
('Denture', 60.00),
('Bridgework', 30.00),
('Package', 30.00),
('Oral Surgery', 30.00),
('X-ray', 30.00),
('Trauma', 40.00);

-- Insert sample patients for testing
INSERT INTO patients (name, phone, email, address) VALUES
('Ahmad Rahman', '012-3456789', 'ahmad@email.com', '123 Jalan Utama, KL'),
('Siti Nurhaliza', '013-2345678', 'siti@email.com', '456 Jalan Damai, Selangor'),
('Muhammad Ali', '014-1234567', 'ali@email.com', '789 Jalan Merdeka, Johor'),
('Fatimah Ibrahim', '015-9876543', 'fatimah@email.com', '321 Jalan Harmoni, Penang'),
('Omar Hassan', '016-8765432', 'omar@email.com', '654 Jalan Sejahtera, Sabah');