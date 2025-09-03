<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Dental Practice Management System'; ?></title>
    <meta name="description" content="Professional dental practice management system with financial tracking">
    
    <!-- Enhanced CSS for Dashboard -->
    <link rel="stylesheet" href="/Dentist%20Receipt%20Calculator/assets/css/dashboard.css">
    <link rel="stylesheet" href="/Dentist%20Receipt%20Calculator/assets/css/style.css">
    <link rel="stylesheet" href="/Dentist%20Receipt%20Calculator/assets/css/mobile.css">
    
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