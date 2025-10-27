<?php
// Application configuration
session_start();

// Database configuration
require_once 'database.php';

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$script_dir = dirname($_SERVER['SCRIPT_NAME']);
$base_path = ($script_dir !== '/') ? $script_dir : '';
define('APP_URL', $protocol . '://' . $host . $base_path);

// // Ensure $pdo is defined (should be set in database.php)
// if (!isset($pdo)) {
//     throw new Exception('Database connection not established.');
// }

// Application settings
define('APP_NAME', 'Mobile Emergency Response System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '');

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
    // Clean the URL to prevent double slashes
    $url = ltrim($url, '/');
    
    // If BASE_URL is defined and not empty, use it
    if (defined('BASE_URL') && BASE_URL !== '') {
        $base_url = rtrim(BASE_URL, '/');
        header("Location: $base_url/$url");
    } else {
        // For relative URLs, use absolute path from document root
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script_dir = dirname($_SERVER['SCRIPT_NAME']);
        
        // Remove the current subdirectory from the script path to get the base path
        $base_path = '';
        if ($script_dir !== '/') {
            // If we're in a subdirectory like /admin, /barangay, etc., go to root
            $path_parts = explode('/', trim($script_dir, '/'));
            if (count($path_parts) > 0 && in_array($path_parts[count($path_parts) - 1], ['super_admin','admin', 'barangay', 'police', 'emergency', 'firefighter', 'user'])) {
                // We're in a module directory, so base path should go up one level
                $base_path = '/' . implode('/', array_slice($path_parts, 0, -1));
                if ($base_path === '/') $base_path = '';
            } else {
                $base_path = $script_dir;
            }
        }
        
        $full_url = $protocol . '://' . $host . $base_path . '/' . $url;
        header("Location: $full_url");
    }
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}
function isSuperAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'super_admin';
}

function isPolice() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'police';
}

function isEmergency() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'emergency';
}

function isBarangay() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'barangay';
}

function isFirefighter() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'firefighter';
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('index.php');
    }
}

function requirePolice() {
    requireLogin();
    if (!isPolice()) {
        redirect('index.php');
    }
}

function requireEmergency() {
    requireLogin();
    if (!isEmergency()) {
        redirect('index.php');
    }
}

function requireBarangay() {
    requireLogin();
    if (!isBarangay()) {
        redirect('index.php');
    }
}

function requireFirefighter() {
    requireLogin();
    if (!isFirefighter()) {
        redirect('index.php');
    }
}

function requireAdmin() {
    requireLogin();
    if (isAdmin()) {
        // Super admin stays
        return;
    } elseif (isSuperAdmin()) {
        // Super admin stays
        return;
    }
    elseif (isPolice()) {
        redirect('police/dashboard.php');
    } elseif (isBarangay()) {
        redirect('barangay/dashboard.php');
    } elseif (isEmergency()) {
        redirect('emergency/dashboard.php');
    } elseif (isFirefighter()) {
        redirect('firefighter/dashboard.php');
    } else {
        redirect('user/dashboard.php');
    }
}
function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        if (isAdmin()) {
            redirect('admin/dashboard.php');
        } elseif (isPolice()) {
            redirect('police/dashboard.php');
        } elseif (isBarangay()) {
            redirect('barangay/dashboard.php');
        } elseif (isEmergency()) {
            redirect('emergency/dashboard.php');
        } elseif (isFirefighter()) {
            redirect('firefighter/dashboard.php');
        } else {
            redirect('user/dashboard.php');
        }
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
