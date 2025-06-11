<?php
// Start session if not already started
function start_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

// Clean input data
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Redirect to a specific URL
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Check if user is logged in
function is_logged_in() {
    start_session();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user has specific role
function has_role($role) {
    start_session();
    return isset($_SESSION['role']) && $_SESSION['role'] == $role;
}

// Get current user ID
function get_user_id() {
    start_session();
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Get current institution ID
function get_institution_id() {
    start_session();
    return isset($_SESSION['institution_id']) ? $_SESSION['institution_id'] : DEFAULT_INSTITUTION_ID;
}

// Flash messages
function set_flash_message($type, $message) {
    start_session();
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_message() {
    start_session();
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// Display flash message
function display_flash_message() {
    $flash = get_flash_message();
    if ($flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
}

// Generate random string
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Format date
function format_date($date, $format = 'd M, Y') {
    return date($format, strtotime($date));
}

// Calculate GPA
function calculate_gpa($grade_points, $credit_units) {
    $total_points = 0;
    $total_units = 0;
    
    foreach ($grade_points as $index => $point) {
        $total_points += $point * $credit_units[$index];
        $total_units += $credit_units[$index];
    }
    
    if ($total_units == 0) return 0;
    
    return round($total_points / $total_units, 2);
}

// Get grade from score
function get_grade_from_score($score, $institution_id = null) {
    global $db;
    
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db->query("SELECT grade, grade_point FROM grade_systems 
                WHERE institution_id = :institution_id 
                AND :score BETWEEN min_score AND max_score");
    $db->bind(':institution_id', $institution_id);
    $db->bind(':score', $score);
    
    $result = $db->single();
    
    if ($result) {
        return [
            'grade' => $result['grade'],
            'grade_point' => $result['grade_point']
        ];
    }
    
    return [
        'grade' => 'F',
        'grade_point' => 0
    ];
}

// Get system setting
function get_setting($key, $institution_id = null) {
    global $db;
    
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db->query("SELECT setting_value FROM system_settings 
                WHERE institution_id = :institution_id 
                AND setting_key = :key");
    $db->bind(':institution_id', $institution_id);
    $db->bind(':key', $key);
    
    $result = $db->single();
    
    return $result ? $result['setting_value'] : null;
}

// Update system setting
function update_setting($key, $value, $institution_id = null) {
    global $db;
    
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db->query("INSERT INTO system_settings (institution_id, setting_key, setting_value) 
                VALUES (:institution_id, :key, :value)
                ON DUPLICATE KEY UPDATE setting_value = :value");
    $db->bind(':institution_id', $institution_id);
    $db->bind(':key', $key);
    $db->bind(':value', $value);
    
    return $db->execute();
}

// Get current academic session
function get_current_session($institution_id = null) {
    global $db;
    
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db->query("SELECT * FROM academic_sessions 
                WHERE institution_id = :institution_id 
                AND is_current = 1");
    $db->bind(':institution_id', $institution_id);
    
    return $db->single();
}

// Get current semester
function get_current_semester($institution_id = null) {
    global $db;
    
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db->query("SELECT s.* FROM semesters s
                JOIN academic_sessions a ON s.session_id = a.session_id
                WHERE s.institution_id = :institution_id 
                AND s.is_current = 1
                AND a.is_current = 1");
    $db->bind(':institution_id', $institution_id);
    
    return $db->single();
}
?>