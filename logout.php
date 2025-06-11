<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Initialize Auth class
$auth = new Auth();

// Logout user
$auth->logout();

// Set flash message
set_flash_message('success', 'You have been logged out successfully.');

// Redirect to login page
redirect(BASE_URL);
?>