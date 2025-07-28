<?php
// Application configuration
session_start();

// Database configuration
require_once 'database.php';

// // Ensure $pdo is defined (should be set in database.php)
// if (!isset($pdo)) {
//     throw new Exception('Database connection not established.');
// }

// Application settings
define('APP_NAME', 'Mobile Emergency Response System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/MERS/');

// File upload settings
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Pagination settings
define('RECORDS_PER_PAGE', 10);

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Helper functions
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
          redirect('login.php');
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
         redirect('user/dashboard.php');
    }
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateReportNumber() {
    return 'RPT-' . date('Y') . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

function formatDateTime($datetime) {
    return date('M j, Y g:i A', strtotime($datetime));
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

function logActivity($user_id, $action, $table_name = null, $record_id = null, $old_values = null, $new_values = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO system_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
              VALUES (:user_id, :action, :table_name, :record_id, :old_values, :new_values, :ip_address, :user_agent)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':action', $action);
    $stmt->bindParam(':table_name', $table_name);
    $stmt->bindParam(':record_id', $record_id);
    $old_values_json = json_encode($old_values);
    $new_values_json = json_encode($new_values);
    $stmt->bindParam(':old_values', $old_values_json);
    $stmt->bindParam(':new_values', $new_values_json);
    $stmt->bindParam(':ip_address', $_SERVER['REMOTE_ADDR']);
    $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT']);
    
    $stmt->execute();
}
?>
