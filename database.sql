CREATE DATABASE IF NOT EXISTS recycle;
USE recycle;

CREATE TABLE recycling_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(10) NOT NULL,
    points_per_kg INT NOT NULL,
    price_per_kg DECIMAL(10,2) NOT NULL,
    color VARCHAR(7) DEFAULT '#28a745',
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    barangay VARCHAR(50),
    user_type ENUM('Household', 'Business') DEFAULT 'Household',
    points INT DEFAULT 0,
    total_recycled DECIMAL(10,2) DEFAULT 0,
    is_admin TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE recycling_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    category_id INT,
    weight DECIMAL(10,2) NOT NULL,
    points_earned INT NOT NULL,
    revenue_generated DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    recycled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES recycling_categories(id)
);

CREATE TABLE point_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    date_earned DATE NOT NULL,
    category_id INT,
    weight_kg DECIMAL(10,2) NOT NULL,
    points_earned INT NOT NULL,
    activity_type ENUM('recycling', 'bonus', 'reward') DEFAULT 'recycling',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES recycling_categories(id)
);

CREATE TABLE reward_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    reward_title VARCHAR(255) NOT NULL,
    points_used INT NOT NULL,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'processed', 'completed') DEFAULT 'pending',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE revenue_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    weight_kg DECIMAL(10,2) NOT NULL,
    price_per_kg DECIMAL(10,2) NOT NULL,
    total_revenue DECIMAL(10,2) NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES recycling_categories(id)
);

CREATE TABLE environmental_impact (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    category_id INT,
    weight_kg DECIMAL(10,2) NOT NULL,
    co2_saved DECIMAL(10,2) NOT NULL,
    water_saved DECIMAL(10,2) NOT NULL,
    energy_saved DECIMAL(10,2) NOT NULL,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES recycling_categories(id)
);

CREATE TABLE IF NOT EXISTS recycling_verification (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recycling_log_id INT,
    user_id INT,
    proof_image VARCHAR(255),
    weight_proof_image VARCHAR(255),
    submitted_at DATETIME,
    verified_at DATETIME,
    verified_by INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    FOREIGN KEY (recycling_log_id) REFERENCES recycling_logs(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- Add metal_subcategory column to recycling_logs table
ALTER TABLE recycling_logs 
ADD COLUMN metal_subcategory VARCHAR(50) DEFAULT NULL AFTER category_id;

-- Create metal_subcategories table if it doesn't exist
CREATE TABLE IF NOT EXISTS metal_subcategories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    points_per_kg INT NOT NULL,
    price_per_kg DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert metal subcategories with exact pricing
INSERT INTO metal_subcategories (name, display_name, points_per_kg, price_per_kg) VALUES
('aluminum', 'Aluminum Frame', 120, 12.00),
('metal_cans', 'Metal Cans', 40, 4.00),
('galvanized_iron', 'Galvanized Iron', 30, 3.00),
('steel_bar', 'Steel Bar', 100, 10.00)
ON DUPLICATE KEY UPDATE 
    display_name = VALUES(display_name),
    points_per_kg = VALUES(points_per_kg),
    price_per_kg = VALUES(price_per_kg);

-- Update existing categories with correct pricing
UPDATE recycling_categories SET 
    points_per_kg = 30,
    price_per_kg = 3.00
WHERE category_name = 'Plastic Bottles';

UPDATE recycling_categories SET 
    points_per_kg = 10,
    price_per_kg = 1.00
WHERE category_name = 'Paper & Cardboard';

UPDATE recycling_categories SET 
    points_per_kg = 10,  -- This will be per piece for glass
    price_per_kg = 1.00  -- This will be per piece for glass
WHERE category_name = 'Glass Containers';

-- Update Metal category description
UPDATE recycling_categories SET 
    description = 'Aluminum frame = ₱12/kg = 120 pts, metal cans = ₱4/kg = 40 pts, Galvanized iron = ₱3/kg = 30 pts, Steel bar = ₱10/kg = 100 pts',
    points_per_kg = 0,  -- Will be calculated based on subcategory
    price_per_kg = 0    -- Will be calculated based on subcategory
WHERE category_name = 'Metal';

ALTER TABLE recycling_logs 
ADD COLUMN proof_image VARCHAR(255),
ADD COLUMN weight_proof_image VARCHAR(255),
ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending';

INSERT INTO recycling_categories (category_name, description, icon, points_per_kg, price_per_kg, color) VALUES
('Plastic Bottles', 'PET bottles, water bottles, soda bottles', '🥤', 15, 18.00, '#28a745'),
('Metal Cans', 'Aluminum cans, tin cans, food containers', '🥫', 12, 22.00, '#17a2b8'),
('E-Waste', 'Old phones, batteries, electronics, cables', '📱', 20, 55.00, '#ffc107'),
('Paper & Cardboard', 'Newspapers, cardboard boxes, office paper', '📦', 8, 12.00, '#dc3545'),
('Glass Containers', 'Glass bottles, jars, containers', '🍶', 10, 15.00, '#6f42c1'),
('Textiles', 'Old clothes, fabrics, textiles', '👕', 6, 8.00, '#e83e8c'),
('Batteries', 'All types of batteries - AA, AAA, cellphone batteries', '🔋', 25, 65.00, '#fd7e14');

INSERT INTO users (name, email, password, barangay, user_type, points, is_admin) VALUES (
    'Recycling Administrator', 
    'admin@ecomina.ph', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'Mina Central', 
    'Business', 
    0, 
    1
);

INSERT INTO users (name, email, password, barangay, user_type, points, total_recycled) VALUES
('Juan Dela Cruz', 'juan.delacruz@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Aguinaldo', 'Household', 350, 25.5),
('Maria Santos', 'maria.santos@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rizal', 'Household', 620, 42.3);