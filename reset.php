<?php
// This is a one-time script to reset admin password
require_once 'config/config.php';
require_once 'config/database.php';

$db = new Database();

// Reset admin password to 'admin123'
$new_password = password_hash('admin123', PASSWORD_DEFAULT);

$db->query("UPDATE users SET password = :password WHERE username = 'admin'");
$db->bind(':password', $new_password);

if ($db->execute()) {
    echo "<h2>Admin Password Reset Successfully!</h2>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>New Password:</strong> admin123</p>";
    echo "<p><a href='index.php'>Go to Login Page</a></p>";
    echo "<p style='color: red;'><strong>Important:</strong> Delete this file after use for security!</p>";
} else {
    echo "<h2>Error resetting password!</h2>";
}
?>