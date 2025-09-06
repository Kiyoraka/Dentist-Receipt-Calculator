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
    
    <!-- Favicon - Tooth Icon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path fill='%232563eb' d='M443.4 92.7C443.4 41.5 401.9 0 350.7 0c-31.1 0-58.7 15.4-75.7 38.9C258 15.4 230.4 0 199.3 0 148.1 0 106.6 41.5 106.6 92.7c0 22.1 7.4 42.5 19.9 58.8 16.4 21.4 38.7 43.6 46.4 70.9 6.3 22.4 8.5 46.1 8.5 70.1 0 75.8-19.9 136.8-19.9 212.6 0 55.1 44.9 100 100 100s100-44.9 100-100c0-75.8-19.9-136.8-19.9-212.6 0-24 2.2-47.7 8.5-70.1 7.7-27.3 30-49.5 46.4-70.9 12.5-16.3 19.9-36.7 19.9-58.8z'/></svg>">
    <link rel="shortcut icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path fill='%232563eb' d='M443.4 92.7C443.4 41.5 401.9 0 350.7 0c-31.1 0-58.7 15.4-75.7 38.9C258 15.4 230.4 0 199.3 0 148.1 0 106.6 41.5 106.6 92.7c0 22.1 7.4 42.5 19.9 58.8 16.4 21.4 38.7 43.6 46.4 70.9 6.3 22.4 8.5 46.1 8.5 70.1 0 75.8-19.9 136.8-19.9 212.6 0 55.1 44.9 100 100 100s100-44.9 100-100c0-75.8-19.9-136.8-19.9-212.6 0-24 2.2-47.7 8.5-70.1 7.7-27.3 30-49.5 46.4-70.9 12.5-16.3 19.9-36.7 19.9-58.8z'/></svg>">
    
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