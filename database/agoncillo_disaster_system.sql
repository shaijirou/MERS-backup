-- Agoncillo Disaster Alert System Database
-- MySQL Database Schema

CREATE DATABASE IF NOT EXISTS agoncillo_disaster_system;
USE agoncillo_disaster_system;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    id_document VARCHAR(255), -- Path to uploaded ID
    selfie_photo VARCHAR(255), -- Path to uploaded selfie
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    phone_verified BOOLEAN DEFAULT FALSE,
    user_type ENUM('resident', 'admin') DEFAULT 'resident',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Barangays table
CREATE TABLE barangays (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    population INT DEFAULT 0,
    area_sqkm DECIMAL(10,2) DEFAULT 0,
    risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Disaster types table
CREATE TABLE disaster_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    severity_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Alerts table
CREATE TABLE alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    alert_type ENUM('emergency', 'warning', 'advisory', 'information') NOT NULL,
    disaster_type_id INT,
    affected_barangays JSON, -- Store array of barangay IDs
    notification_methods JSON, -- Store array of methods (sms, email, app)
    evacuation_centers JSON, -- Store array of evacuation center IDs
    attachments JSON, -- Store array of file paths
    status ENUM('draft', 'sent', 'expired') DEFAULT 'draft',
    sent_by INT NOT NULL,
    sent_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (disaster_type_id) REFERENCES disaster_types(id),
    FOREIGN KEY (sent_by) REFERENCES users(id)
);

-- Incident reports table
CREATE TABLE incident_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    incident_type VARCHAR(100) NOT NULL,
    urgency_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    location TEXT NOT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    description TEXT NOT NULL,
    people_affected ENUM('none', 'few', 'several', 'many', 'unknown') DEFAULT 'none',
    injuries ENUM('yes', 'no', 'unknown') DEFAULT 'no',
    photos JSON, -- Store array of photo paths
    contact_number VARCHAR(20) NOT NULL,
    status ENUM('pending', 'in_progress', 'resolved', 'closed') DEFAULT 'pending',
    assigned_to INT NULL,
    response_time INT NULL, -- Response time in minutes
    resolution_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Evacuation centers table
CREATE TABLE evacuation_centers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    barangay_id INT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    capacity INT NOT NULL,
    current_occupancy INT DEFAULT 0,
    facilities JSON, -- Store array of available facilities
    contact_person VARCHAR(255),
    contact_number VARCHAR(20),
    status ENUM('active', 'inactive', 'full', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id)
);

-- Hazard zones table
CREATE TABLE hazard_zones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    zone_type ENUM('flood_prone', 'landslide_prone', 'fault_line', 'volcanic_risk') NOT NULL,
    coordinates JSON NOT NULL, -- Store polygon coordinates
    risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    affected_barangays JSON, -- Store array of barangay IDs
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alert_id INT NOT NULL,
    user_id INT NOT NULL,
    method ENUM('sms', 'email', 'app') NOT NULL,
    status ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alert_id) REFERENCES alerts(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- System logs table
CREATE TABLE system_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(255) NOT NULL,
    table_name VARCHAR(100) NULL,
    record_id INT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Settings table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_by INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Insert default barangays
INSERT INTO barangays (name, population, area_sqkm, risk_level) VALUES
('Adia', 1200, 5.2, 'medium'),
('Banyaga', 980, 4.8, 'high'),
('Bilibinwang', 1450, 6.1, 'medium'),
('Bugaan East', 1320, 5.5, 'high'),
('Bugaan West', 1100, 4.9, 'medium'),
('Pansipit', 1560, 7.2, 'high'),
('Poblacion', 1870, 3.8, 'medium'),
('San Jacinto', 1030, 5.8, 'low'),
('Subic Ibaba', 1120, 6.3, 'high'),
('Subic Ilaya', 870, 4.2, 'medium');

-- Insert default disaster types
INSERT INTO disaster_types (name, description, severity_level) VALUES
('Typhoon', 'Tropical cyclone with strong winds and heavy rainfall', 'critical'),
('Flood', 'Overflow of water onto normally dry land', 'high'),
('Volcanic Eruption', 'Eruption of molten rock, ash, and gases from Taal Volcano', 'critical'),
('Earthquake', 'Sudden shaking of the ground caused by tectonic movements', 'high'),
('Landslide', 'Movement of rock, earth, or debris down a slope', 'high'),
('Fire', 'Uncontrolled burning that destroys property and threatens lives', 'high'),
('Road Accident', 'Traffic collision involving vehicles', 'medium'),
('Power Outage', 'Loss of electrical power supply', 'low'),
('Water Supply Issue', 'Disruption in water distribution system', 'medium');

-- Insert default evacuation centers
INSERT INTO evacuation_centers (name, address, barangay_id, latitude, longitude, capacity, facilities, contact_person, contact_number) VALUES
('Agoncillo Elementary School', 'Poblacion, Agoncillo, Batangas', 7, 13.9094, 120.9200, 500, '["classrooms", "restrooms", "kitchen", "water_supply"]', 'Principal Maria Santos', '+63 912 345 6789'),
('Municipal Gymnasium', 'Poblacion, Agoncillo, Batangas', 7, 13.9100, 120.9210, 800, '["gymnasium", "restrooms", "shower_facilities", "kitchen"]', 'Gym Manager Pedro Cruz', '+63 912 345 6790'),
('Subic National High School', 'Subic Ibaba, Agoncillo, Batangas', 9, 13.9150, 120.9180, 600, '["classrooms", "restrooms", "library", "canteen"]', 'Principal Ana Garcia', '+63 912 345 6791'),
('Pansipit Barangay Hall', 'Pansipit, Agoncillo, Batangas', 6, 13.9080, 120.9250, 200, '["hall", "restrooms", "kitchen"]', 'Barangay Captain Jose Reyes', '+63 912 345 6792'),
('Banyaga Community Center', 'Banyaga, Agoncillo, Batangas', 2, 13.9120, 120.9160, 300, '["hall", "restrooms", "stage"]', 'Center Manager Lisa Tan', '+63 912 345 6793'),
('San Jacinto Covered Court', 'San Jacinto, Agoncillo, Batangas', 8, 13.9200, 120.9300, 400, '["covered_court", "restrooms", "storage"]', 'Court Supervisor Mark Lopez', '+63 912 345 6794'),
('Bugaan East Barangay Hall', 'Bugaan East, Agoncillo, Batangas', 4, 13.9180, 120.9220, 250, '["hall", "restrooms", "office"]', 'Barangay Secretary Rosa Mendoza', '+63 912 345 6795'),
('Bilibinwang Multi-Purpose Center', 'Bilibinwang, Agoncillo, Batangas', 3, 13.9160, 120.9280, 350, '["hall", "restrooms", "kitchen", "stage"]', 'Center Coordinator Juan Dela Cruz', '+63 912 345 6796');

-- Insert default admin user
INSERT INTO users (first_name, last_name, email, phone, password, address, barangay, user_type, verification_status, phone_verified) VALUES
('Admin', 'User', 'admin@agoncillo.gov.ph', '+63 912 000 0000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Municipal Hall, Poblacion, Agoncillo, Batangas', 'Poblacion', 'admin', 'verified', TRUE);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description, updated_by) VALUES
('system_name', 'Agoncillo Disaster Alert System', 'Name of the disaster alert system', 1),
('municipality_name', 'Municipality of Agoncillo, Batangas', 'Full name of the municipality', 1),
('contact_email', 'mdrrmo@agoncillo.gov.ph', 'Main contact email for MDRRMO', 1),
('contact_phone', '+63 912 345 6789', 'Main contact phone for MDRRMO', 1),
('sms_api_key', '', 'API key for SMS service provider', 1),
('email_smtp_host', 'smtp.gmail.com', 'SMTP host for email notifications', 1),
('email_smtp_port', '587', 'SMTP port for email notifications', 1),
('email_username', '', 'Email username for notifications', 1),
('email_password', '', 'Email password for notifications', 1),
('max_file_size', '5242880', 'Maximum file upload size in bytes (5MB)', 1),
('allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx', 'Allowed file types for uploads', 1);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_phone ON users(phone);
CREATE INDEX idx_users_barangay ON users(barangay);
CREATE INDEX idx_incident_reports_status ON incident_reports(status);
CREATE INDEX idx_incident_reports_created_at ON incident_reports(created_at);
CREATE INDEX idx_alerts_status ON alerts(status);
CREATE INDEX idx_alerts_created_at ON alerts(created_at);
CREATE INDEX idx_notifications_status ON notifications(status);
CREATE INDEX idx_system_logs_created_at ON system_logs(created_at);
