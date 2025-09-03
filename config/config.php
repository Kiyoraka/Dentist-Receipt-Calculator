<?php
/**
 * Configuration file for Dental Practice Management System
 * Dynamic path configuration for flexibility across different folder names
 */

// Dynamic base URL detection
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the folder path dynamically
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    
    // If we're in a subdirectory (modules), go up one level
    if (strpos($scriptPath, '/modules') !== false) {
        $scriptPath = dirname(dirname($_SERVER['SCRIPT_NAME']));
    }
    
    return $protocol . $host . $scriptPath;
}

// Define constants
define('BASE_URL', getBaseUrl());
define('ASSETS_URL', BASE_URL . '/assets');
define('CSS_URL', ASSETS_URL . '/css');
define('JS_URL', ASSETS_URL . '/js');
define('IMAGES_URL', ASSETS_URL . '/images');

// Database configuration (if needed in future)
define('DB_HOST', 'localhost');
define('DB_NAME', 'dental_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// System configuration
define('SITE_NAME', 'Dental Practice Management System');
define('SITE_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Kuala_Lumpur');

// Set timezone
date_default_timezone_set(TIMEZONE);