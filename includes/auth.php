<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    // Login user
    public function login($username, $password, $role = null) {
        // Clean input
        $username = clean_input($username);
        
        // Check if username exists and is active
        $this->db->query("SELECT * FROM users WHERE username = :username AND is_active = 1");
        $this->db->bind(':username', $username);
        
        $user = $this->db->single();
        
        // If user exists, verify password
        if ($user && password_verify($password, $user['password'])) {
            // If role is specified, check if user has that role
            if ($role) {
                if ($role === 'student' && $user['role'] !== 'student') {
                    return false; // Role mismatch
                }
                
                if ($role === 'admin' && !in_array($user['role'], ['admin', 'super_admin'])) {
                    return false; // Role mismatch
                }
            }
            
            // Start session
            start_session();
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['institution_id'] = $user['institution_id'];
            
            // If the user is a student, get their full details
            if ($user['role'] === 'student') {
                $this->db->query("SELECT s.student_id, s.matric_number, d.department_name, l.level_name
                                  FROM students s
                                  JOIN departments d ON s.department_id = d.department_id
                                  JOIN levels l ON s.level_id = l.level_id
                                  WHERE s.user_id = :user_id");
                $this->db->bind(':user_id', $user['user_id']);
                $student_data = $this->db->single();

                if ($student_data) {
                    // Populate session with all student-specific data
                    $_SESSION['student_id'] = $student_data['student_id'];
                    $_SESSION['matric_number'] = $student_data['matric_number'];
                    $_SESSION['department_name'] = $student_data['department_name'];
                    $_SESSION['level_name'] = $student_data['level_name'];
                } else {
                    // Data inconsistency: a user with 'student' role has no student record.
                    // Log this error and prevent login.
                    error_log("Data inconsistency for user_id: " . $user['user_id'] . ". Student record not found.");
                    return false;
                }
            }

            // Update last login
            $this->db->query("UPDATE users SET last_login = NOW() WHERE user_id = :user_id");
            $this->db->bind(':user_id', $user['user_id']);
            $this->db->execute();
            
            return true;
        }
        
        return false;
    }
    
    // Register user
    public function register($data) {
        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT, ['cost' => HASH_COST]);
        
        // Insert user
        $this->db->query("INSERT INTO users (institution_id, username, password, email, full_name, role) 
                          VALUES (:institution_id, :username, :password, :email, :full_name, :role)");
        
        // Bind values
        $this->db->bind(':institution_id', $data['institution_id']);
        $this->db->bind(':username', $data['username']);
        $this->db->bind(':password', $data['password']);
        $this->db->bind(':email', $data['email']);
        $this->db->bind(':full_name', $data['full_name']);
        $this->db->bind(':role', $data['role']);
        
        // Execute
        if ($this->db->execute()) {
            return $this->db->lastInsertId();
        } else {
            return false;
        }
    }
    
    // Logout user
    public function logout() {
        start_session();
        
        // Unset all session variables
        $_SESSION = array();
        
        // Destroy the session
        session_destroy();
        
        return true;
    }
    
    // Check if username exists
    public function username_exists($username) {
        $this->db->query("SELECT COUNT(*) as count FROM users WHERE username = :username");
        $this->db->bind(':username', $username);
        
        $row = $this->db->single();
        
        return $row['count'] > 0;
    }
    
    // Check if email exists
    public function email_exists($email) {
        $this->db->query("SELECT COUNT(*) as count FROM users WHERE email = :email");
        $this->db->bind(':email', $email);
        
        $row = $this->db->single();
        
        return $row['count'] > 0;
    }
    
    // Get user by ID
    public function get_user($user_id) {
        $this->db->query("SELECT * FROM users WHERE user_id = :user_id");
        $this->db->bind(':user_id', $user_id);
        
        return $this->db->single();
    }
    
    // Update user
    public function update_user($user_id, $data) {
        // Whitelist of allowed fields to update
        $allowed_fields = ['username', 'password', 'email', 'full_name', 'role', 'is_active'];

        // Start query
        $query = "UPDATE users SET ";
        $params = [':user_id' => $user_id];
        $set_parts = [];
        
        // Add fields to update
        foreach ($data as $key => $value) {
            // Check if the field is allowed
            if (in_array($key, $allowed_fields)) {
                // Hash password if it's being updated
                if ($key == 'password') {
                    $value = password_hash($value, PASSWORD_DEFAULT, ['cost' => HASH_COST]);
                }

                $set_parts[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }

        // If no valid fields to update, return false
        if (empty($set_parts)) {
            return false;
        }
        
        // Add updated_at
        $query .= implode(', ', $set_parts) . ", updated_at = NOW() WHERE user_id = :user_id";
        
        // Execute query
        $this->db->query($query);
        
        // Bind all parameters
        foreach ($params as $param => $value) {
            $this->db->bind($param, $value);
        }
        
        return $this->db->execute();
    }
    
    // Delete user
    public function delete_user($user_id) {
        $this->db->query("DELETE FROM users WHERE user_id = :user_id");
        $this->db->bind(':user_id', $user_id);
        
        return $this->db->execute();
    }
}
?>