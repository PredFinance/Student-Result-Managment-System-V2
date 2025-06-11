<?php
// Simple admin creation script - DELETE AFTER USE!
require_once 'config/config.php';

try {
    // Direct database connection
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "<h2>Admin Creation Tool</h2>";
    echo "<p style='color: red;'><strong>WARNING: Delete this file after use!</strong></p>";
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Delete existing admin if exists
        $stmt = $pdo->prepare("DELETE FROM users WHERE username = ?");
        $stmt->execute([$username]);
        
        // Insert new admin
        $stmt = $pdo->prepare("
            INSERT INTO users (institution_id, username, password, email, full_name, role, is_active) 
            VALUES (1, ?, ?, ?, ?, 'super_admin', 1)
        ");
        
        if ($stmt->execute([$username, $hashed_password, $email, $full_name])) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>✅ Admin Created Successfully!</h3>";
            echo "<p><strong>Username:</strong> $username</p>";
            echo "<p><strong>Password:</strong> $password</p>";
            echo "<p><strong>Email:</strong> $email</p>";
            echo "<p><strong>Role:</strong> Super Admin</p>";
            echo "<p><a href='index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>❌ Error creating admin!</h3>";
            echo "</div>";
        }
    }
    
    // Show current admins
    $stmt = $pdo->query("SELECT username, email, full_name, role, is_active, created_at FROM users WHERE role IN ('admin', 'super_admin')");
    $admins = $stmt->fetchAll();
    
    if ($admins) {
        echo "<h3>Current Admins:</h3>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #f8f9fa;'><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>";
        foreach ($admins as $admin) {
            $status = $admin['is_active'] ? 'Active' : 'Inactive';
            $statusColor = $admin['is_active'] ? 'green' : 'red';
            echo "<tr>";
            echo "<td><strong>{$admin['username']}</strong></td>";
            echo "<td>{$admin['full_name']}</td>";
            echo "<td>{$admin['email']}</td>";
            echo "<td>{$admin['role']}</td>";
            echo "<td style='color: $statusColor;'>$status</td>";
            echo "<td>{$admin['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<h3>Database Connection Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config/config.php</p>";
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Admin - LUFEM SRMS</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="email"], input[type="password"] { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; 
        }
        button { 
            background: #28a745; color: white; padding: 12px 30px; border: none; 
            border-radius: 5px; cursor: pointer; font-size: 16px; 
        }
        button:hover { background: #218838; }
        .warning { 
            background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; 
            border: 1px solid #ffeaa7; margin-bottom: 20px; 
        }
    </style>
</head>
<body>
    <div class="warning">
        <strong>⚠️ SECURITY WARNING:</strong> This is a temporary admin creation tool. 
        Delete this file immediately after creating your admin account!
    </div>
    
    <form method="POST">
        <h3>Create New Admin Account</h3>
        
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required value="admin">
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required value="admin123">
        </div>
        
        <div class="form-group">
            <label for="full_name">Full Name:</label>
            <input type="text" id="full_name" name="full_name" required value="System Administrator">
        </div>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required value="admin@lufem.edu">
        </div>
        
        <button type="submit">Create Admin Account</button>
    </form>
    
    <hr style="margin: 40px 0;">
    
    <h3>Test Database Connection</h3>
    <p>Database Host: <?php echo DB_HOST; ?></p>
    <p>Database Name: <?php echo DB_NAME; ?></p>
    <p>Database User: <?php echo DB_USER; ?></p>
    <p>Connection Status: 
        <?php 
        try {
            $test_pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS);
            echo "<span style='color: green;'>✅ Connected</span>";
        } catch (Exception $e) {
            echo "<span style='color: red;'>❌ Failed - " . $e->getMessage() . "</span>";
        }
        ?>
    </p>
</body>
</html>