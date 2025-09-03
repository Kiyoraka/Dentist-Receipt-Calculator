<?php 
// Include configuration for dynamic paths
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : SITE_NAME; ?></title>
    <meta name="description" content="Professional dental practice management system with financial tracking">
    
    <!-- Enhanced CSS for Dashboard with Dynamic Paths -->
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/dashboard.css">
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo CSS_URL; ?>/mobile.css">
    
    <!-- Additional page-specific CSS -->
    <?php if(isset($additional_css) && is_array($additional_css)): ?>
        <?php foreach($additional_css as $css_file): ?>
            <link rel="stylesheet" href="<?php echo $css_file; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Loading overlay -->
        <div id="loading-overlay" class="loading-overlay hidden">
            <div class="loading-spinner">
                <i class="fas fa-tooth fa-spin"></i>
                <p>Loading...</p>
            </div>
        </div>