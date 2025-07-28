<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'User logged out');
    
    // Destroy session
    session_destroy();
}

redirect('index.php');
?>
