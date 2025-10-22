<?php
// Semaphore SMS API Configuration
// Get API key from environment or define here
define('SEMAPHORE_API_KEY', getenv('SEMAPHORE_API_KEY') ?: '9f28ebe8d41d44502679ef59f782beb8');
define('SEMAPHORE_API_URL', 'https://api.semaphore.co/api/v4/messages');
define('SEMAPHORE_SENDER_NAME', 'AgonMDRRMO'); // Sender name (max 11 characters)

// SMS notification settings
define('ENABLE_SMS_NOTIFICATIONS', true);
define('SMS_ALERT_RECIPIENTS', 'all'); // 'all' = all users, 'barangay' = affected barangay only
?>
