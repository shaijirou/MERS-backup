-- Database Updates for Multi-Role Emergency Response System
-- Version 2.0 - Adding Police, Emergency, Barangay, and Fire Fighter roles

USE agoncillo_disaster_system;

-- Update users table to support new user roles
ALTER TABLE users 
MODIFY COLUMN user_type ENUM('resident', 'admin', 'police', 'emergency', 'barangay', 'firefighter') DEFAULT 'resident';

-- Add department and badge_number columns for emergency responders
ALTER TABLE users 
ADD COLUMN department VARCHAR(100) NULL AFTER user_type,
ADD COLUMN badge_number VARCHAR(50) NULL AFTER department,
ADD COLUMN assigned_barangay VARCHAR(100) NULL AFTER badge_number;

-- Create notifications table for real-time alerts
CREATE TABLE IF NOT EXISTS user_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    incident_id INT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM('incident_approved', 'incident_assigned', 'status_update', 'general') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (incident_id) REFERENCES incident_reports(id) ON DELETE CASCADE
);

-- Update incident_reports table to support approval workflow and multiple responder types
ALTER TABLE incident_reports 
ADD COLUMN approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER status,
ADD COLUMN approved_by INT NULL AFTER approval_status,
ADD COLUMN approved_at TIMESTAMP NULL AFTER approved_by,
ADD COLUMN responder_type ENUM('police', 'emergency', 'barangay', 'firefighter') NULL AFTER assigned_to,
ADD COLUMN response_status ENUM('notified', 'responding', 'on_scene', 'resolved') DEFAULT 'notified' AFTER responder_type;

-- Add foreign key for approved_by
ALTER TABLE incident_reports 
ADD FOREIGN KEY (approved_by) REFERENCES users(id);

-- Create incident_assignments table for multiple responder assignments
CREATE TABLE IF NOT EXISTS incident_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    incident_id INT NOT NULL,
    assigned_to INT NOT NULL,
    responder_type ENUM('police', 'emergency', 'barangay', 'firefighter') NOT NULL,
    assignment_status ENUM('assigned', 'accepted', 'responding', 'on_scene', 'completed') DEFAULT 'assigned',
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT NULL,
    FOREIGN KEY (incident_id) REFERENCES incident_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

-- Create activity_logs table for tracking user actions
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    incident_id INT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (incident_id) REFERENCES incident_reports(id) ON DELETE SET NULL
);

-- Insert default emergency responder users
INSERT INTO users (first_name, last_name, email, phone, password, address, barangay, user_type, department, badge_number, verification_status, phone_verified) VALUES
('Police', 'Chief', 'police@agoncillo.gov.ph', '+63 912 111 1111', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Police Station, Poblacion, Agoncillo, Batangas', 'Poblacion', 'police', 'Agoncillo Police Station', 'P001', 'verified', TRUE),
('Emergency', 'Coordinator', 'emergency@agoncillo.gov.ph', '+63 912 222 2222', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'MDRRMO Office, Poblacion, Agoncillo, Batangas', 'Poblacion', 'emergency', 'MDRRMO', 'E001', 'verified', TRUE),
('Barangay', 'Captain', 'barangay@agoncillo.gov.ph', '+63 912 333 3333', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Barangay Hall, Poblacion, Agoncillo, Batangas', 'Poblacion', 'barangay', 'Barangay Emergency Response', 'B001', 'verified', TRUE),
('Fire', 'Chief', 'fire@agoncillo.gov.ph', '+63 912 444 4444', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Fire Station, Poblacion, Agoncillo, Batangas', 'Poblacion', 'firefighter', 'Agoncillo Fire Department', 'F001', 'verified', TRUE);

-- Create indexes for better performance
CREATE INDEX idx_user_notifications_user_id ON user_notifications(user_id);
CREATE INDEX idx_user_notifications_is_read ON user_notifications(is_read);
CREATE INDEX idx_incident_reports_approval_status ON incident_reports(approval_status);
CREATE INDEX idx_incident_reports_responder_type ON incident_reports(responder_type);
CREATE INDEX idx_incident_assignments_incident_id ON incident_assignments(incident_id);
CREATE INDEX idx_incident_assignments_assigned_to ON incident_assignments(assigned_to);
CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

-- Update settings for new system features
INSERT INTO settings (setting_key, setting_value, description, updated_by) VALUES
('enable_auto_assignment', '1', 'Enable automatic assignment of incidents to responders', 1),
('notification_sound', '1', 'Enable notification sounds for responders', 1),
('response_time_limit', '30', 'Maximum response time in minutes before escalation', 1),
('camera_selfie_required', '1', 'Require camera selfie during registration', 1);
