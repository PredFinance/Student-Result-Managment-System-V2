<?php
// Login test script - DELETE AFTER USE!
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>Login Test Tool</h2>";
echo "<p style='color: red;'><strong>WARNING: Delete this file after use!</strong></p>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        $db = new Database();
        
        // Get user from database
        $db->query("SELECT * FROM users WHERE username = :username AND is_active = 1");
        $db->bind(':username', $username);
        $user = $db->single();
        
        echo "<h3>Debug Information:</h3>";
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        
        if ($user) {
            echo "<p><strong>✅ User found in database</strong></p>";
            echo "<p><strong>Username:</strong> " . htmlspecialchars($user['username']) . "</p>";
            echo "<p><strong>Full Name:</strong> " . htmlspecialchars($user['full_name']) . "</p>";
            echo "<p><strong>Role:</strong> " . htmlspecialchars($user['role']) . "</p>";
            echo "<p><strong>Active:</strong> " . ($user['is_active'] ? 'Yes' : 'No') . "</p>";
            echo "<p><strong>Password Hash:</strong> " . substr($user['password'], 0, 20) . "...</p>";
            
            // Test password verification
            if (password_verify($password, $user['password'])) {
                echo "<p style='color: green;'><strong>✅ Password verification: SUCCESS</strong></p>";
                echo "<p style='color: green;'>Login should work! Try logging in now.</p>";
            } else {
                echo "<p style='color: red;'><strong>❌ Password verification: FAILED</strong></p>";
                echo "<p>The password you entered doesn't match the stored hash.</p>";
                
                // Show what the hash should be
                $correct_hash = password_hash($password, PASSWORD_DEFAULT);
                echo "<p><strong>New hash for '$password':</strong> " . substr($correct_hash, 0, 30) . "...</p>";
                
                // Update password in database
                echo "<form method='POST' style='margin-top: 15px;'>";
                echo "<input type='hidden' name='fix_password' value='1'>";
                echo "<input type='hidden' name='username' value='$username'>";
                echo "<input type='hidden' name='password' value='$password'>";
                echo "<button type='submit' style='background: #dc3545; color: white; padding: 8px 15px; border: none; border-radius: 3px;'>Fix Password Hash</button>";
                echo "</form>";
            }
        } else {
            echo "<p style='color: red;'><strong>❌ User not found in database</strong></p>";
            echo "<p>Username '$username' does not exist or is inactive.</p>";
        }
        
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
        echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

// Handle password fix
if (isset($_POST['fix_password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    try {
        $db = new Database();
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $db->query("UPDATE users SET password = :password WHERE username = :username");
        $db->bind(':password', $new_hash);
        $db->bind(':username', $username);
        
        if ($db->execute()) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<p><strong>✅ Password hash fixed!</strong> Try logging in now.</p>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
        echo "<p><strong>Error fixing password:</strong> " . $e->getMessage() . "</p>";
        echo "</div>";
    }
}

// Show all users
try {
    $db = new Database();
    $db->query("SELECT username, full_name, role, is_active FROM users WHERE role IN ('admin', 'super_admin')");
    $users = $db->resultSet();
    
    if ($users) {
        echo "<h3>Available Admin Users:</h3>";
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; margin: 20px 0;'>";
        echo "<tr style='background: #f8f9fa;'><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th></tr>";
        foreach ($users as $user) {
            $status = $user['is_active'] ? 'Active' : 'Inactive';
            $color = $user['is_active'] ? 'green' : 'red';
            echo "<tr>";
            echo "<td><strong>{$user['username']}</strong></td>";
            echo "<td>{$user['full_name']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td style='color: $color;'>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error loading users: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Test - LUFEM SRMS</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; 
        }
        button { 
            background: #007bff; color: white; padding: 12px 30px; border: none; 
            border-radius: 5px; cursor: pointer; font-size: 16px; 
        }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <form method="POST">
        <h3>Test Login Credentials</h3>
        
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required value="admin">
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required value="admin123">
        </div>
        
        <button type="submit">Test Login</button>
    </form>
    
    <p><a href="index.php">← Back to Login Page</a></p>
</body>
</html>