<?php
/**
 * Cleanup Script for Expired Password Reset Codes
 * 
 * This script should be run periodically (e.g., via cron job) to clean up expired reset codes
 * 
 * Cron job example (runs every hour):
 * 0 * * * * php /path/to/scripts/cleanup-expired-reset-codes.php
 * 
 * Or run manually:
 * php scripts/cleanup-expired-reset-codes.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/PasswordResetHelper.php';

// Prevent direct web access
if (php_sapi_name() !== 'cli' && !isset($_GET['key'])) {
    http_response_code(403);
    die('Access denied');
}

// Optional: Add a security key for web access
// if (isset($_GET['key']) && $_GET['key'] !== 'your-secret-key') {
//     http_response_code(403);
//     die('Invalid key');
// }

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $resetHelper = new PasswordResetHelper($db);
    
    // Clear expired reset codes
    $cleared_count = $resetHelper->clearExpiredResetCodes();
    
    $message = "[" . date('Y-m-d H:i:s') . "] Cleanup completed. Cleared $cleared_count expired reset codes.";
    
    // Log to file
    $log_file = __DIR__ . '/../logs/cleanup.log';
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    file_put_contents($log_file, $message . "\n", FILE_APPEND);
    
    // Output message
    echo $message . "\n";
    
} catch (Exception $e) {
    $error_message = "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage();
    
    // Log error
    $log_file = __DIR__ . '/../logs/cleanup.log';
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    file_put_contents($log_file, $error_message . "\n", FILE_APPEND);
    
    // Output error
    echo $error_message . "\n";
    exit(1);
}
?>
