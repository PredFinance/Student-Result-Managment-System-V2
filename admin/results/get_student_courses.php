<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$institution_id = get_institution_id();

$student_id = clean_input($_GET['student_id']);
$session_id = clean_input($_GET['session_id']);
$semester_id = clean_input($_GET['semester_id']);

// Get student information
$db->query("SELECT s.*, d.department_name, l.level_name 
            FROM students s
            JOIN departments d ON s.department_id = d.department_id
            JOIN levels l ON s.level_id = l.level_id
            WHERE s.student_id = :student_id AND s.institution_id = :institution_id");
$db->bind(':student_id', $student_id);
$db->bind(':institution_id', $institution_id);
$student = $db->single();

if (!$student) {
    echo json_encode(['success' => false, 'message' => 'Student not found']);
    exit;
}

// Get student's registered courses with current results
$db->query("SELECT cr.registration_id, cr.course_id,
            c.course_code, c.course_title, c.credit_units,
            r.result_id, r.ca_score, r.exam_score, r.total_score, r.grade, r.grade_point, r.remark,
            r.updated_at
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

$courses = $db->resultSet();

echo json_encode([
    'success' => true,
    'data' => [
        'student' => $student,
        'courses' => $courses
    ]
]);
?>
