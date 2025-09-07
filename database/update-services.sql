-- Update Dental Services to New Requirements
-- Keep Medication and update all other services

-- First, delete existing services except Medication (if it exists)
DELETE FROM dental_services WHERE service_name NOT IN ('Medication');

-- Check if Medication exists, if not add it
INSERT IGNORE INTO dental_services (service_name, percentage) VALUES ('Medication', 0.00);

-- Delete any duplicate services that might exist
DELETE FROM dental_services WHERE service_name IN ('Consult', 'Filling', 'Scaling', 'Implant', 'Crown', 'Extraction', 'Package', 'Braces', 'Denture', 'X-ray', 'Trauma');

-- Insert new services with updated percentages
INSERT INTO dental_services (service_name, percentage) VALUES
    ('Consult', 80.00),
    ('Filling', 40.00),
    ('Scaling', 40.00),
    ('Implant', 60.00),
    ('Crown', 40.00),
    ('Extraction', 40.00),
    ('Package', 30.00),
    ('Braces', 60.00),
    ('Denture', 40.00),
    ('X-ray', 20.00),
    ('Trauma', 40.00);

-- Display updated services
SELECT * FROM dental_services ORDER BY service_name;