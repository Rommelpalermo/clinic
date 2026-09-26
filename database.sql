CREATE DATABASE IF NOT EXISTS clinic
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE clinic;

CREATE TABLE IF NOT EXISTS patients (
  id VARCHAR(32) PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  course VARCHAR(120) NOT NULL,
  year_level VARCHAR(30) NOT NULL,
  blood_type VARCHAR(8) NOT NULL DEFAULT 'Unknown',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  supplier VARCHAR(160) NOT NULL DEFAULT '',
  category VARCHAR(40) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  unit VARCHAR(40) NOT NULL DEFAULT 'pieces',
  minimum_stock INT UNSIGNED NOT NULL DEFAULT 0,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  expiry_date DATE NULL,
  location VARCHAR(120) NOT NULL DEFAULT '',
  description TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  seed_key VARCHAR(32) NULL UNIQUE,
  patient_id VARCHAR(32) NOT NULL,
  complaint TEXT NOT NULL,
  severity ENUM('mild', 'moderate', 'severe') NOT NULL DEFAULT 'mild',
  visit_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diagnosis TEXT NOT NULL,
  status ENUM('inprogress', 'completed', 'followup') NOT NULL DEFAULT 'inprogress',
  attending_staff VARCHAR(160) NOT NULL DEFAULT '',
  temperature DECIMAL(4,1) NULL,
  blood_pressure VARCHAR(20) NOT NULL DEFAULT '',
  pulse SMALLINT UNSIGNED NULL,
  respiratory_rate SMALLINT UNSIGNED NULL,
  weight DECIMAL(5,1) NULL,
  treatment TEXT NOT NULL,
  medications TEXT NOT NULL,
  notes TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_visits_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO patients (id, name, course, year_level, blood_type) VALUES
  ('20-0190', 'Gladezel Aubrey Anulat', 'Btte-FSM', '1st Year', 'Unknown'),
  ('TCI-2024-0003', 'Angela Reyes', 'BS Computer Science', '1st Year', 'B+'),
  ('TCI-2023-0015', 'Carlos Garcia', 'BS Accountancy', '4th Year', 'AB+'),
  ('TCI-2023-0020', 'Patricia Mendoza', 'BS Nursing', '3rd Year', 'O-'),
  ('TCI-2024-0002', 'Juan Dela Cruz', 'BS Business Administration', '3rd Year', 'A+'),
  ('TCI-2024-0001', 'Maria Santos', 'BS Information Technology', '2nd Year', 'O+');

INSERT INTO inventory (name, supplier, category, quantity, unit, minimum_stock, unit_cost, expiry_date)
SELECT seed.name, seed.supplier, seed.category, seed.quantity, seed.unit, seed.minimum_stock, seed.unit_cost, seed.expiry_date
FROM (
  SELECT 'Isopropyl Alcohol 70%' AS name, 'Metro Drug Inc.' AS supplier, 'Consumable' AS category, 3 AS quantity, 'bottles' AS unit, 5 AS minimum_stock, 220.00 AS unit_cost, '2027-04-01' AS expiry_date
  UNION ALL SELECT 'Digital Thermometer', 'TechMed Corp.', 'Equipment', 5, 'pieces', 0, 1097.00, NULL
  UNION ALL SELECT 'Face Masks (Surgical)', 'SafeGuard Med', 'Ppe', 45, 'boxes', 0, 80.00, '2027-08-01'
  UNION ALL SELECT 'Oral Rehydration Salts', 'Metro Drug Inc.', 'Medication', 60, 'packs', 0, 12.00, '2026-12-01'
  UNION ALL SELECT 'Gauze Rolls', 'MedSupply PH', 'First Aid', 30, 'rolls', 0, 45.00, '2027-12-01'
  UNION ALL SELECT 'Disposable Gloves (Medium)', 'SafeGuard Med', 'Ppe', 8, 'boxes', 10, 350.00, '2027-09-01'
  UNION ALL SELECT 'Adhesive Bandages', 'MedSupply PH', 'First Aid', 15, 'boxes', 20, 80.00, '2028-01-01'
  UNION ALL SELECT 'Blood Pressure Monitor', 'TechMed Corp.', 'Equipment', 3, 'pieces', 0, 1600.00, NULL
  UNION ALL SELECT 'Paracetamol 500mg', 'Metro Drug Inc.', 'Medication', 250, 'pieces', 0, 2.00, '2027-06-15'
  UNION ALL SELECT 'Ibuprofen 200mg', 'Metro Drug Inc.', 'Medication', 120, 'pieces', 0, 3.00, '2027-03-20'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM inventory existing WHERE existing.name = seed.name);

INSERT IGNORE INTO visits (seed_key, patient_id, complaint, severity, visit_date, diagnosis, status)
VALUES
  ('seed-visit-1', 'TCI-2024-0001', 'head acne', 'mild', '2026-05-04 09:00:00', 'tension and stress', 'followup'),
  ('seed-visit-2', 'TCI-2024-0001', 'Headache and dizziness', 'mild', '2026-05-04 09:30:00', 'Tension headache', 'completed'),
  ('seed-visit-3', 'TCI-2024-0002', 'Fever and body malaise', 'moderate', '2026-05-04 10:00:00', 'Viral infection', 'completed'),
  ('seed-visit-4', 'TCI-2023-0020', 'Minor cut on hand', 'mild', '2026-05-04 10:30:00', 'Superficial laceration', 'completed'),
  ('seed-visit-5', 'TCI-2024-0001', 'Asthma attack during PE class', 'severe', '2026-05-04 11:00:00', 'Acute asthma exacerbation', 'completed'),
  ('seed-visit-6', 'TCI-2023-0015', 'High blood pressure episode', 'severe', '2026-05-04 11:30:00', 'Hypertension exacerbation', 'followup'),
  ('seed-visit-7', 'TCI-2024-0003', 'Cough and cold symptoms', 'mild', '2026-05-04 12:00:00', 'Upper respiratory tract infection', 'completed'),
  ('seed-visit-8', '20-0190', 'Sprained ankle', 'moderate', '2026-05-04 12:30:00', 'Ankle sprain', 'completed');