<?php
/**
 * Test file to debug path generation
 * Delete this file after testing
 */

require_once 'config/config.php';

echo "<h2>Path Configuration Test</h2>";
echo "<pre>";
echo "Current Script: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "BASE_URL: " . BASE_URL . "\n";
echo "ASSETS_URL: " . ASSETS_URL . "\n";
echo "CSS_URL: " . CSS_URL . "\n";
echo "JS_URL: " . JS_URL . "\n";
echo "</pre>";

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='" . BASE_URL . "/index.php'>Home</a></li>";
echo "<li><a href='" . BASE_URL . "/modules/financial.php'>Financial Management</a></li>";
echo "<li><a href='" . BASE_URL . "/modules/patients.php'>Patient Management</a></li>";
echo "</ul>";
?>