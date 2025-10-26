<?php
// Get these values from your EmailJS Dashboard:
// 1. Service ID: Account > Services > Copy Service ID
// 2. Template ID: Email Templates > Copy Template ID  
// 3. Private Key: Account > API Keys > Copy Private Key (NOT Public Key)

define('EMAILJS_SERVICE_ID', 'service_k9i98tr');
define('EMAILJS_TEMPLATE_ID', 'template_f5ceyhw');
define('EMAILJS_PRIVATE_KEY', '0bBR4rThRpzmSDOeZHY16'); // Private Key from EmailJS Dashboard
define('EMAILJS_API_URL', 'https://api.emailjs.com/api/v1.0/email/send');

function validateEmailJSConfig() {
    $errors = [];
    
    if (empty(EMAILJS_SERVICE_ID)) {
        $errors[] = 'EMAILJS_SERVICE_ID is not configured';
    }
    if (empty(EMAILJS_TEMPLATE_ID)) {
        $errors[] = 'EMAILJS_TEMPLATE_ID is not configured';
    }
    if (empty(EMAILJS_PRIVATE_KEY)) {
        $errors[] = 'EMAILJS_PRIVATE_KEY is not configured';
    }
    
    if (!empty($errors)) {
        error_log("[EmailJS Config] Validation Errors: " . implode(', ', $errors));
        return false;
    }
    
    return true;
}
?>
