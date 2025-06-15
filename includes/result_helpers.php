<?php
/**
 * Helper functions for results management
 */

/**
 * Calculate grade based on total score
 */
function calculateGrade($score) {
    if ($score >= 70) {
        return ['grade' => 'A', 'point' => 5.0, 'remark' => 'Excellent'];
    } elseif ($score >= 60) {
        return ['grade' => 'B', 'point' => 4.0, 'remark' => 'Very Good'];
    } elseif ($score >= 50) {
        return ['grade' => 'C', 'point' => 3.0, 'remark' => 'Good'];
    } elseif ($score >= 45) {
        return ['grade' => 'D', 'point' => 2.0, 'remark' => 'Fair'];
    } elseif ($score >= 40) {
        return ['grade' => 'E', 'point' => 1.0, 'remark' => 'Pass'];
    } else {
        return ['grade' => 'F', 'point' => 0.0, 'remark' => 'Fail'];
    }
}

/**
 * Get color class for grade
 */
function get_grade_color($grade) {
    $colors = [
        'A' => 'success',
        'B' => 'primary',
        'C' => 'info',
        'D' => 'warning',
        'E' => 'secondary',
        'F' => 'danger'
    ];
    return $colors[$grade] ?? 'secondary';
}

/**
 * Get color class for score
 */
function get_score_color($score) {
    if ($score >= 70) return 'success';
    if ($score >= 60) return 'primary';
    if ($score >= 50) return 'info';
    if ($score >= 45) return 'warning';
    if ($score >= 40) return 'secondary';
    return 'danger';
}

/**
 * Get color class for GPA
 */
function get_gpa_color($gpa) {
    if ($gpa >= 4.50) return 'success';
    if ($gpa >= 3.50) return 'primary';
    if ($gpa >= 2.40) return 'info';
    if ($gpa >= 1.50) return 'warning';
    if ($gpa >= 1.00) return 'secondary';
    return 'danger';
}

/**
 * Get color class for remark
 */
function get_remark_color($remark) {
    $colors = [
        'Excellent' => 'success',
        'Very Good' => 'primary',
        'Good' => 'info',
        'Fair' => 'warning',
        'Pass' => 'secondary',
        'Fail' => 'danger'
    ];
    return $colors[$remark] ?? 'secondary';
}

/**
 * Format date for display
 */
function format_date($date, $format = 'd M, Y') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * Get results completion status for a course
 */
function get_course_completion_status($course_id, $session_id, $semester_id) {
    $db = new Database();
    
    $db->query("SELECT 
                COUNT(cr.registration_id) as total_students,
                COUNT(r.result_id) as completed_results
                FROM course_registrations cr
                LEFT JOIN results r ON cr.registration_id = r.registration_id
                WHERE cr.course_id = :course_id 
                AND cr.session_id = :session_id 
                AND cr.semester_id = :semester_id");
    
    $db->bind(':course_id', $course_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    
    $result = $db->single();
    
    return [
        'total_students' => $result['total_students'],
        'completed_results' => $result['completed_results'],
        'completion_rate' => $result['total_students'] > 0 
            ? ($result['completed_results'] / $result['total_students']) * 100 
            : 0,
        'status' => $result['completed_results'] == 0 ? 'not_started' : 
                   ($result['completed_results'] < $result['total_students'] ? 'in_progress' : 'completed')
    ];
}

/**
 * Get student results completion status
 */
function get_student_completion_status($student_id, $session_id, $semester_id) {
    $db = new Database();
    
    $db->query("SELECT 
                COUNT(cr.registration_id) as total_courses,
                COUNT(r.result_id) as completed_results
                FROM course_registrations cr
                LEFT JOIN results r ON cr.registration_id = r.registration_id
                WHERE cr.student_id = :student_id 
                AND cr.session_id = :session_id 
                AND cr.semester_id = :semester_id");
    
    $db->bind(':student_id', $student_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    
    $result = $db->single();
    
    return [
        'total_courses' => $result['total_courses'],
        'completed_results' => $result['completed_results'],
        'completion_rate' => $result['total_courses'] > 0 
            ? ($result['completed_results'] / $result['total_courses']) * 100 
            : 0,
        'status' => $result['completed_results'] == 0 ? 'not_started' : 
                   ($result['completed_results'] < $result['total_courses'] ? 'in_progress' : 'completed')
    ];
}

/**
 * Validate result scores
 */
function validate_result_scores($ca_score, $exam_score) {
    $errors = [];
    
    if ($ca_score < 0 || $ca_score > 40) {
        $errors[] = 'CA score must be between 0 and 40';
    }
    
    if ($exam_score < 0 || $exam_score > 60) {
        $errors[] = 'Exam score must be between 0 and 60';
    }
    
    return $errors;
}

/**
 * Get academic performance summary
 */
function get_academic_performance_summary($session_id, $semester_id, $institution_id) {
    $db = new Database();
    
    // Overall statistics
    $db->query("SELECT 
                COUNT(DISTINCT cr.student_id) as total_students,
                COUNT(DISTINCT cr.course_id) as total_courses,
                COUNT(cr.registration_id) as total_registrations,
                COUNT(r.result_id) as completed_results,
                ROUND(AVG(r.total_score), 2) as avg_score
                FROM course_registrations cr
                LEFT JOIN results r ON cr.registration_id = r.registration_id
                WHERE cr.session_id = :session_id 
                AND cr.semester_id = :semester_id
                AND cr.institution_id = :institution_id");
    
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    $db->bind(':institution_id', $institution_id);
    
    $overall = $db->single();
    
    // Grade distribution
    $db->query("SELECT r.grade, COUNT(*) as count
                FROM course_registrations cr
                JOIN results r ON cr.registration_id = r.registration_id
                WHERE cr.session_id = :session_id 
                AND cr.semester_id = :semester_id
                AND cr.institution_id = :institution_id
                GROUP BY r.grade
                ORDER BY r.grade");
    
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    $db->bind(':institution_id', $institution_id);
    
    $grade_distribution = $db->resultSet();
    
    return [
        'overall' => $overall,
        'grade_distribution' => $grade_distribution,
        'completion_rate' => $overall['total_registrations'] > 0 
            ? ($overall['completed_results'] / $overall['total_registrations']) * 100 
            : 0
    ];
}
?>
