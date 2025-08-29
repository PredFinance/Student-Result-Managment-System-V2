<?php
/**
 * Core Functions for LUFEM Student Results Management System
 */

// Session management functions
function start_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in() {
    start_session();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_user_id() {
    start_session();
    return $_SESSION['user_id'] ?? null;
}

function get_institution_id() {
    start_session();
    return $_SESSION['institution_id'] ?? 1; // Default to institution 1
}

function has_role($role) {
    start_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

// Flash message functions
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
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

function display_flash_message() {
    $message = get_flash_message();
    if ($message) {
        $type = $message['type'];
        $text = $message['message'];
        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$text}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";
    }
}

// Input sanitization
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Date formatting
function format_date($date, $format = 'd M, Y') {
    if (empty($date)) return 'N/A';
    return date($format, strtotime($date));
}

function format_datetime($datetime, $format = 'd M, Y H:i') {
    if (empty($datetime)) return 'N/A';
    return date($format, strtotime($datetime));
}

// Academic functions
function get_current_session($institution_id = null) {
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db = new Database();
    
    // Get the current session from the single sessions table
    $db->query("SELECT * FROM sessions
                WHERE institution_id = :institution_id AND is_current = 1 
                ORDER BY session_id DESC LIMIT 1");
    $db->bind(':institution_id', $institution_id);
    $session = $db->single();
    
    // If no current session is explicitly set, get the most recent one
    if (!$session) {
        $db->query("SELECT * FROM sessions 
                    WHERE institution_id = :institution_id 
                    ORDER BY session_id DESC LIMIT 1");
        $db->bind(':institution_id', $institution_id);
        $session = $db->single();
    }
    
    return $session;
}

function get_current_semester($institution_id = null) {
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db = new Database();
    
    // Get current semester
    $db->query("SELECT * FROM semesters 
                WHERE institution_id = :institution_id AND is_current = 1 
                ORDER BY semester_id DESC LIMIT 1");
    $db->bind(':institution_id', $institution_id);
    $semester = $db->single();
    
    // If no current semester, get the first one
    if (!$semester) {
        $db->query("SELECT * FROM semesters 
                    WHERE institution_id = :institution_id 
                    ORDER BY semester_order ASC LIMIT 1");
        $db->bind(':institution_id', $institution_id);
        $semester = $db->single();
    }
    
    return $semester;
}

function get_all_sessions($institution_id = null) {
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db = new Database();
    
    // Get all sessions from the single sessions table
    $db->query("SELECT session_id, session_name FROM sessions
                WHERE institution_id = :institution_id 
                ORDER BY session_name DESC");
    $db->bind(':institution_id', $institution_id);
    $sessions = $db->resultSet();
    
    return $sessions;
}

function get_all_semesters($institution_id = null) {
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db = new Database();
    
    $db->query("SELECT semester_id, semester_name FROM semesters 
                WHERE institution_id = :institution_id 
                ORDER BY semester_order");
    $db->bind(':institution_id', $institution_id);
    
    return $db->resultSet();
}

function get_all_departments($institution_id = null) {
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db = new Database();
    
    $db->query("SELECT department_id, department_name FROM departments 
                WHERE institution_id = :institution_id AND is_active = 1
                ORDER BY department_name");
    $db->bind(':institution_id', $institution_id);
    
    return $db->resultSet();
}

function get_all_levels($institution_id = null) {
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db = new Database();
    
    $db->query("SELECT level_id, level_name FROM levels 
                WHERE institution_id = :institution_id AND is_active = 1
                ORDER BY level_order");
    $db->bind(':institution_id', $institution_id);
    
    return $db->resultSet();
}

// Course registration functions
function get_available_courses_for_student($student_id, $session_id, $semester_id) {
    $db = new Database();
    
    // Get student's department and level
    $db->query("SELECT department_id, level_id FROM students WHERE student_id = :student_id");
    $db->bind(':student_id', $student_id);
    $student = $db->single();
    
    if (!$student) {
        return [];
    }
    
    // Get available courses for this student's department and level
    $db->query("SELECT c.course_id, c.course_code, c.course_title, c.credit_units,
                CASE WHEN cr.registration_id IS NOT NULL THEN 1 ELSE 0 END as is_registered
                FROM courses c
                LEFT JOIN course_structures cs ON c.course_id = cs.course_id 
                    AND cs.department_id = :department_id 
                    AND cs.level_id = :level_id 
                    AND cs.session_id = :session_id 
                    AND cs.semester_id = :semester_id
                LEFT JOIN course_registrations cr ON c.course_id = cr.course_id 
                    AND cr.student_id = :student_id 
                    AND cr.session_id = :session_id 
                    AND cr.semester_id = :semester_id
                WHERE c.department_id = :department_id 
                AND c.is_active = 1
                AND (cs.structure_id IS NOT NULL OR c.course_id IN (
                    SELECT DISTINCT course_id FROM course_registrations 
                    WHERE session_id = :session_id AND semester_id = :semester_id
                ))
                ORDER BY c.course_code");
    
    $db->bind(':student_id', $student_id);
    $db->bind(':department_id', $student['department_id']);
    $db->bind(':level_id', $student['level_id']);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    
    return $db->resultSet();
}

function get_student_registered_courses($student_id, $session_id, $semester_id) {
    $db = new Database();
    
    $db->query("SELECT c.course_id, c.course_code, c.course_title, c.credit_units,
                cr.registration_date, cr.status,
                r.ca_score, r.exam_score, r.total_score, r.grade, r.grade_point
                FROM course_registrations cr
                JOIN courses c ON cr.course_id = c.course_id
                LEFT JOIN results r ON cr.registration_id = r.registration_id
                WHERE cr.student_id = :student_id 
                AND cr.session_id = :session_id 
                AND cr.semester_id = :semester_id
                ORDER BY c.course_code");
    
    $db->bind(':student_id', $student_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    
    return $db->resultSet();
}

// Grade calculation functions
function calculate_grade($total_score, $institution_id = null) {
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db = new Database();
    
    $db->query("SELECT grade, grade_point, remark FROM grade_systems 
                WHERE institution_id = :institution_id 
                AND :score BETWEEN min_score AND max_score 
                AND is_active = 1
                ORDER BY min_score DESC LIMIT 1");
    $db->bind(':institution_id', $institution_id);
    $db->bind(':score', $total_score);
    
    $grade_info = $db->single();
    
    // Default grade system if none found
    if (!$grade_info) {
        if ($total_score >= 70) {
            return ['grade' => 'A', 'grade_point' => 5.0, 'remark' => 'Excellent'];
        } elseif ($total_score >= 60) {
            return ['grade' => 'B', 'grade_point' => 4.0, 'remark' => 'Very Good'];
        } elseif ($total_score >= 50) {
            return ['grade' => 'C', 'grade_point' => 3.0, 'remark' => 'Good'];
        } elseif ($total_score >= 45) {
            return ['grade' => 'D', 'grade_point' => 2.0, 'remark' => 'Fair'];
        } elseif ($total_score >= 40) {
            return ['grade' => 'E', 'grade_point' => 1.0, 'remark' => 'Pass'];
        } else {
            return ['grade' => 'F', 'grade_point' => 0.0, 'remark' => 'Fail'];
        }
    }
    
    return [
        'grade' => $grade_info['grade'],
        'grade_point' => $grade_info['grade_point'],
        'remark' => $grade_info['remark']
    ];
}

// Validation functions
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid length (10-15 digits)
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

function validate_matric_number($matric_number, $institution_id = null) {
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db = new Database();
    
    $db->query("SELECT COUNT(*) as count FROM students 
                WHERE matric_number = :matric_number 
                AND institution_id = :institution_id");
    $db->bind(':matric_number', $matric_number);
    $db->bind(':institution_id', $institution_id);
    
    $result = $db->single();
    
    return $result['count'] == 0; // Return true if matric number is unique
}

// File upload functions
function upload_file($file, $upload_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf']) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded'];
    }
    
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    
    if ($file_error !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    // Check file size (5MB max)
    if ($file_size > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File too large (max 5MB)'];
    }
    
    // Create upload directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '.' . $file_ext;
    $upload_path = $upload_dir . '/' . $new_filename;
    
    if (move_uploaded_file($file_tmp, $upload_path)) {
        return ['success' => true, 'filename' => $new_filename, 'path' => $upload_path];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

// Pagination functions
function paginate($total_records, $records_per_page = 20, $current_page = 1) {
    $total_pages = ceil($total_records / $records_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $records_per_page;
    
    return [
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'records_per_page' => $records_per_page,
        'offset' => $offset,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages,
        'previous_page' => $current_page - 1,
        'next_page' => $current_page + 1
    ];
}

// Utility functions
function generate_password($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $password;
}

function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

function format_currency($amount, $currency = 'NGN') {
    return $currency . ' ' . number_format($amount, 2);
}

function format_number($number, $decimals = 0) {
    return number_format($number, $decimals);
}

// System settings functions
function get_system_setting($key, $default = null, $institution_id = null) {
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db = new Database();
    
    $db->query("SELECT setting_value FROM system_settings 
                WHERE institution_id = :institution_id 
                AND setting_key = :key");
    $db->bind(':institution_id', $institution_id);
    $db->bind(':key', $key);
    
    $result = $db->single();
    
    return $result ? $result['setting_value'] : $default;
}

function set_system_setting($key, $value, $institution_id = null) {
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db = new Database();
    
    // Check if setting exists
    $db->query("SELECT setting_id FROM system_settings 
                WHERE institution_id = :institution_id 
                AND setting_key = :key");
    $db->bind(':institution_id', $institution_id);
    $db->bind(':key', $key);
    
    $existing = $db->single();
    
    if ($existing) {
        // Update existing setting
        $db->query("UPDATE system_settings 
                    SET setting_value = :value, updated_at = NOW() 
                    WHERE setting_id = :setting_id");
        $db->bind(':value', $value);
        $db->bind(':setting_id', $existing['setting_id']);
    } else {
        // Insert new setting
        $db->query("INSERT INTO system_settings 
                    (institution_id, setting_key, setting_value) 
                    VALUES (:institution_id, :key, :value)");
        $db->bind(':institution_id', $institution_id);
        $db->bind(':key', $key);
        $db->bind(':value', $value);
    }
    
    return $db->execute();
}

// Logging functions
function log_activity($user_id, $action, $details = '', $institution_id = null) {
    if (!$institution_id) {
        $institution_id = get_institution_id();
    }
    
    $db = new Database();
    
    $db->query("INSERT INTO activity_logs 
                (institution_id, user_id, action, details, ip_address, user_agent) 
                VALUES (:institution_id, :user_id, :action, :details, :ip_address, :user_agent)");
    
    $db->bind(':institution_id', $institution_id);
    $db->bind(':user_id', $user_id);
    $db->bind(':action', $action);
    $db->bind(':details', $details);
    $db->bind(':ip_address', $_SERVER['REMOTE_ADDR'] ?? '');
    $db->bind(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
    
    return $db->execute();
}

// Export functions
function export_to_csv($data, $filename, $headers = []) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers if provided
    if (!empty($headers)) {
        fputcsv($output, $headers);
    } elseif (!empty($data)) {
        // Use array keys as headers
        fputcsv($output, array_keys($data[0]));
    }
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

// Security functions
function generate_csrf_token() {
    start_session();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    start_session();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function rate_limit($key, $max_attempts = 5, $time_window = 300) {
    start_session();
    
    $attempts_key = 'rate_limit_' . $key;
    $time_key = 'rate_limit_time_' . $key;
    
    $current_time = time();
    $attempts = $_SESSION[$attempts_key] ?? 0;
    $first_attempt_time = $_SESSION[$time_key] ?? $current_time;
    
    // Reset if time window has passed
    if ($current_time - $first_attempt_time > $time_window) {
        $attempts = 0;
        $first_attempt_time = $current_time;
    }
    
    $attempts++;
    
    $_SESSION[$attempts_key] = $attempts;
    $_SESSION[$time_key] = $first_attempt_time;
    
    return $attempts <= $max_attempts;
}



function updateSemesterGPA($student_id, $session_id, $semester_id) {
    $db = new Database();
    $institution_id = get_institution_id();

    // Calculate GPA for this student/session/semester
    $db->query("SELECT SUM(r.grade_point * c.credit_units) AS total_points, SUM(c.credit_units) AS total_units, COUNT(*) AS courses_count
                FROM course_registrations cr
                JOIN results r ON cr.registration_id = r.registration_id
                JOIN courses c ON cr.course_id = c.course_id
                WHERE cr.student_id = :student_id
                  AND cr.session_id = :session_id
                  AND cr.semester_id = :semester_id
                  AND cr.institution_id = :institution_id");
    $db->bind(':student_id', $student_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    $db->bind(':institution_id', $institution_id);
    $row = $db->single();

    $gpa = ($row && $row['total_units'] > 0) ? round($row['total_points'] / $row['total_units'], 2) : 0.00;

    // Upsert into semester_gpas table
    $db->query("INSERT INTO semester_gpas (institution_id, student_id, session_id, semester_id, gpa, credit_units, grade_points, courses_count, created_at, updated_at)
                VALUES (:institution_id, :student_id, :session_id, :semester_id, :gpa, :credit_units, :grade_points, :courses_count, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    gpa = VALUES(gpa),
                    credit_units = VALUES(credit_units),
                    grade_points = VALUES(grade_points),
                    courses_count = VALUES(courses_count),
                    updated_at = NOW()");
    $db->bind(':institution_id', $institution_id);
    $db->bind(':student_id', $student_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    $db->bind(':gpa', $gpa);
    $db->bind(':credit_units', $row['total_units'] ?? 0);
    $db->bind(':grade_points', $row['total_points'] ?? 0);
    $db->bind(':courses_count', $row['courses_count'] ?? 0);
    $db->execute();
}

function updateCumulativeGPA($student_id) {
    $db = new Database();
    $institution_id = get_institution_id();

    // Calculate cumulative GPA
    $db->query("SELECT SUM(r.grade_point * c.credit_units) AS total_points, SUM(c.credit_units) AS total_units, COUNT(*) AS total_courses
                FROM course_registrations cr
                JOIN results r ON cr.registration_id = r.registration_id
                JOIN courses c ON cr.course_id = c.course_id
                WHERE cr.student_id = :student_id
                  AND cr.institution_id = :institution_id");
    $db->bind(':student_id', $student_id);
    $db->bind(':institution_id', $institution_id);
    $row = $db->single();

    $cgpa = ($row && $row['total_units'] > 0) ? round($row['total_points'] / $row['total_units'], 2) : 0.00;

    // Upsert into cumulative_gpas table
    $db->query("INSERT INTO cumulative_gpas (institution_id, student_id, cgpa, total_credit_units, total_grade_points, total_courses, created_at, updated_at)
                VALUES (:institution_id, :student_id, :cgpa, :total_units, :total_points, :total_courses, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    cgpa = VALUES(cgpa),
                    total_credit_units = VALUES(total_credit_units),
                    total_grade_points = VALUES(total_grade_points),
                    total_courses = VALUES(total_courses),
                    updated_at = NOW()");
    $db->bind(':institution_id', $institution_id);
    $db->bind(':student_id', $student_id);
    $db->bind(':cgpa', $cgpa);
    $db->bind(':total_units', $row['total_units'] ?? 0);
    $db->bind(':total_points', $row['total_points'] ?? 0);
    $db->bind(':total_courses', $row['total_courses'] ?? 0);
    $db->execute();
}

?>
