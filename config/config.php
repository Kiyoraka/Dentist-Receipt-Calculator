<?php
/**
 * Configuration file for Dental Practice Management System
 * Dynamic path configuration for flexibility across different folder names
 */

// Dynamic base URL detection
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the current script path
    $scriptName = $_SERVER['SCRIPT_NAME'];
    
    // Find the project root (where index.php is located)
    // Remove everything after the project folder name
    if (strpos($scriptName, '/modules/') !== false) {
        // We're in a module, get the base path before /modules/
        $basePath = substr($scriptName, 0, strpos($scriptName, '/modules/'));
    } else {
        // We're in the root, just get the directory
        $basePath = dirname($scriptName);
    }
    
    return $protocol . $host . $basePath;
}

// Define constants only if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', getBaseUrl());
}
if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', BASE_URL . '/assets');
}
if (!defined('CSS_URL')) {
    define('CSS_URL', ASSETS_URL . '/css');
}
if (!defined('JS_URL')) {
    define('JS_URL', ASSETS_URL . '/js');
}
if (!defined('IMAGES_URL')) {
    define('IMAGES_URL', ASSETS_URL . '/images');
}

// Database configuration (if needed in future)
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'dental_system');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

// System configuration
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Dental Practice Management System');
}
if (!defined('SITE_VERSION')) {
    define('SITE_VERSION', '1.0.0');
}
if (!defined('TIMEZONE')) {
    define('TIMEZONE', 'Asia/Kuala_Lumpur');
}

// Set timezone
date_default_timezone_set(TIMEZONE);