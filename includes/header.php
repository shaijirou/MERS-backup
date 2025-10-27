<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>assets/css/styles.css" rel="stylesheet">
    
    <?php if (isset($additional_css)): ?>
        <?php foreach ($additional_css as $css): ?>
            <link href="<?php echo BASE_URL . $css; ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/img/logo.png">
    
    <!-- Meta tags -->
    <meta name="description" content="Agoncillo Disaster Alert System - Stay informed about emergencies and disasters in Agoncillo, Batangas">
    <meta name="keywords" content="disaster, alert, emergency, Agoncillo, Batangas, Philippines">
    <meta name="author" content="Municipality of Agoncillo">
    
    <!-- Open Graph tags -->
    <meta property="og:title" content="<?php echo isset($page_title) ? $page_title . ' - ' . APP_NAME : APP_NAME; ?>">
    <meta property="og:description" content="Stay informed about emergencies and disasters in Agoncillo, Batangas">
    <meta property="og:image" content="<?php echo BASE_URL; ?>assets/img/logo.png">
    <meta property="og:url" content="<?php echo BASE_URL; ?>">
    <meta property="og:type" content="website">
    
    <!-- EmailJS CDN -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/index.min.js"></script>
    
    <!-- Add EmailJS config script to initialize EmailJS functions -->
    <script type="text/javascript" src="<?php echo BASE_URL; ?>assets/js/emailjs-config.js"></script>
</head>
<body>
