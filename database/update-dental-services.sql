-- Update dental services percentages
-- New percentages as specified

UPDATE dental_services SET percentage = 80.00 WHERE service_name = 'Consult';
UPDATE dental_services SET percentage = 40.00 WHERE service_name = 'Filling';
UPDATE dental_services SET percentage = 40.00 WHERE service_name = 'Scaling';
UPDATE dental_services SET percentage = 60.00 WHERE service_name = 'Implant';
UPDATE dental_services SET percentage = 40.00 WHERE service_name = 'Crown';
UPDATE dental_services SET percentage = 40.00 WHERE service_name = 'Extraction';
UPDATE dental_services SET percentage = 30.00 WHERE service_name = 'Package';
UPDATE dental_services SET percentage = 60.00 WHERE service_name = 'Braces';
UPDATE dental_services SET percentage = 40.00 WHERE service_name = 'Denture';
UPDATE dental_services SET percentage = 20.00 WHERE service_name = 'X-ray';
UPDATE dental_services SET percentage = 40.00 WHERE service_name = 'Trauma';

-- Add missing services that weren't in the original database
INSERT INTO dental_services (service_name, percentage) VALUES
('Scaling', 40.00),
('Crown', 40.00),
('Extraction', 40.00),
('Braces', 60.00)
ON DUPLICATE KEY UPDATE percentage = VALUES(percentage);

-- Remove or update services that may have different names
UPDATE dental_services SET service_name = 'X-ray', percentage = 20.00 WHERE service_name LIKE '%ray%' OR service_name LIKE '%Ray%';

-- Update any other similar services
UPDATE dental_services SET percentage = 40.00 WHERE service_name = 'Composite';
UPDATE dental_services SET percentage = 30.00 WHERE service_name = 'Bridgework';
UPDATE dental_services SET percentage = 40.00 WHERE service_name = 'Oral Surgery';