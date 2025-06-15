<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lufem_school');

// Application configuration
define('APP_NAME', 'LUFEM Student Results Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/srms');
define('ADMIN_URL', BASE_URL . '/admin');
define('STUDENT_URL', BASE_URL . '/student');

// Session configuration
define('SESSION_NAME', 'lufem_srms_session');
define('SESSION_LIFETIME', 86400); // 24 hours

// Security configuration
define('HASH_COST', 10); // For password hashing

// Default institution ID (for multi-tenancy)
define('DEFAULT_INSTITUTION_ID', 1);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>