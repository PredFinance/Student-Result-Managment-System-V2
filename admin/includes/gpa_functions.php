<?php
/**
 * GPA Calculation Functions
 * Smart GPA calculation with semester and cumulative GPA
 */

// Grade calculation function
function calculateGrade($score) {
    if ($score >= 70) {
        return ['grade' => 'A', 'point' => 5.0, 'remark' => 'Excellent'];
    } elseif ($score >= 60) {
        return ['grade' => 'B', 'point' => 4.0, 'remark' => 'Very Good'];
    } elseif ($score >= 50) {
        return ['grade' => 'C', 'point' => 3.0, 'remark' => 'Good'];
    } elseif ($score >= 45) {
        return ['grade' => 'D', 'point' => 2.0, 'remark' => 'Fair'];
    } else {
        return ['grade' => 'F', 'point' => 0.0, 'remark' => 'Fail'];
    }
}

// Calculate semester GPA for a student
function calculateSemesterGPA($student_id, $session_id, $semester_id) {
    $db = new Database();
    
    $db->query("SELECT r.grade_point, c.credit_units
                FROM results r
                JOIN course_registrations cr ON r.registration_id = cr.registration_id
                JOIN courses c ON cr.course_id = c.course_id
                WHERE cr.student_id = :student_id 
                AND cr.session_id = :session_id 
                AND cr.semester_id = :semester_id");
    
    $db->bind(':student_id', $student_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    
    $results = $db->resultSet();
    
    if (empty($results)) {
        return [
            'gpa' => 0.0,
            'total_credit_units' => 0,
            'total_grade_points' => 0.0,
            'courses_count' => 0
        ];
    }
    
    $total_grade_points = 0.0;
    $total_credit_units = 0;
    
    foreach ($results as $result) {
        $grade_points = $result['grade_point'] * $result['credit_units'];
        $total_grade_points += $grade_points;
        $total_credit_units += $result['credit_units'];
    }
    
    $gpa = $total_credit_units > 0 ? $total_grade_points / $total_credit_units : 0.0;
    
    return [
        'gpa' => round($gpa, 2),
        'total_credit_units' => $total_credit_units,
        'total_grade_points' => $total_grade_points,
        'courses_count' => count($results)
    ];
}

// Calculate cumulative GPA for a student
function calculateCumulativeGPA($student_id, $up_to_session_id = null, $up_to_semester_id = null) {
    $db = new Database();
    
    $query = "SELECT r.grade_point, c.credit_units, cr.session_id, cr.semester_id
              FROM results r
              JOIN course_registrations cr ON r.registration_id = cr.registration_id
              JOIN courses c ON cr.course_id = c.course_id
              WHERE cr.student_id = :student_id";
    
    $params = [':student_id' => $student_id];
    
    // Add session/semester filters if provided
    if ($up_to_session_id && $up_to_semester_id) {
        $query .= " AND (cr.session_id < :session_id OR 
                         (cr.session_id = :session_id AND cr.semester_id <= :semester_id))";
        $params[':session_id'] = $up_to_session_id;
        $params[':semester_id'] = $up_to_semester_id;
    }
    
    $query .= " ORDER BY cr.session_id, cr.semester_id";
    
    $db->query($query);
    foreach ($params as $param => $value) {
        $db->bind($param, $value);
    }
    
    $results = $db->resultSet();
    
    if (empty($results)) {
        return [
            'cgpa' => 0.0,
            'total_credit_units' => 0,
            'total_grade_points' => 0.0,
            'total_courses' => 0,
            'semesters_count' => 0
        ];
    }
    
    $total_grade_points = 0.0;
    $total_credit_units = 0;
    $semesters = [];
    
    foreach ($results as $result) {
        $grade_points = $result['grade_point'] * $result['credit_units'];
        $total_grade_points += $grade_points;
        $total_credit_units += $result['credit_units'];
        
        $semester_key = $result['session_id'] . '_' . $result['semester_id'];
        $semesters[$semester_key] = true;
    }
    
    $cgpa = $total_credit_units > 0 ? $total_grade_points / $total_credit_units : 0.0;
    
    return [
        'cgpa' => round($cgpa, 2),
        'total_credit_units' => $total_credit_units,
        'total_grade_points' => $total_grade_points,
        'total_courses' => count($results),
        'semesters_count' => count($semesters)
    ];
}

// Get student's academic performance summary
function getStudentPerformanceSummary($student_id) {
    $db = new Database();
    
    // Get all semester results
    $db->query("SELECT DISTINCT cr.session_id, cr.semester_id, 
                sess.session_name, sem.semester_name, sem.semester_order
                FROM course_registrations cr
                JOIN sessions sess ON cr.session_id = sess.session_id
                JOIN semesters sem ON cr.semester_id = sem.semester_id
                JOIN results r ON cr.registration_id = r.registration_id
                WHERE cr.student_id = :student_id
                ORDER BY sess.session_name, sem.semester_order");
    
    $db->bind(':student_id', $student_id);
    $semesters = $db->resultSet();
    
    $performance = [];
    $cumulative_gpa_data = calculateCumulativeGPA($student_id);
    
    foreach ($semesters as $semester) {
        $semester_gpa_data = calculateSemesterGPA(
            $student_id, 
            $semester['session_id'], 
            $semester['semester_id']
        );
        
        $performance[] = [
            'session_id' => $semester['session_id'],
            'semester_id' => $semester['semester_id'],
            'session_name' => $semester['session_name'],
            'semester_name' => $semester['semester_name'],
            'gpa' => $semester_gpa_data['gpa'],
            'credit_units' => $semester_gpa_data['total_credit_units'],
            'courses_count' => $semester_gpa_data['courses_count']
        ];
    }
    
    return [
        'semesters' => $performance,
        'cumulative' => $cumulative_gpa_data,
        'classification' => getClassification($cumulative_gpa_data['cgpa'])
    ];
}

// Get degree classification based on CGPA
function getClassification($cgpa) {
    if ($cgpa >= 4.50) {
        return ['class' => 'First Class', 'color' => 'success'];
    } elseif ($cgpa >= 3.50) {
        return ['class' => 'Second Class Upper', 'color' => 'primary'];
    } elseif ($cgpa >= 2.40) {
        return ['class' => 'Second Class Lower', 'color' => 'info'];
    } elseif ($cgpa >= 1.50) {
        return ['class' => 'Third Class', 'color' => 'warning'];
    } else {
        return ['class' => 'Pass', 'color' => 'secondary'];
    }
}

// Calculate class statistics for a course
function getCourseStatistics($course_id, $session_id, $semester_id) {
    $db = new Database();
    
    $db->query("SELECT r.total_score, r.grade, r.grade_point
                FROM results r
                JOIN course_registrations cr ON r.registration_id = cr.registration_id
                WHERE cr.course_id = :course_id 
                AND cr.session_id = :session_id 
                AND cr.semester_id = :semester_id");
    
    $db->bind(':course_id', $course_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    
    $results = $db->resultSet();
    
    if (empty($results)) {
        return null;
    }
    
    $scores = array_column($results, 'total_score');
    $grades = array_count_values(array_column($results, 'grade'));
    
    return [
        'total_students' => count($results),
        'highest_score' => max($scores),
        'lowest_score' => min($scores),
        'average_score' => round(array_sum($scores) / count($scores), 2),
        'pass_rate' => round((count($results) - ($grades['F'] ?? 0)) / count($results) * 100, 1),
        'grade_distribution' => $grades,
        'median_score' => getMedian($scores)
    ];
}

// Helper function to calculate median
function getMedian($numbers) {
    sort($numbers);
    $count = count($numbers);
    
    if ($count % 2 == 0) {
        return ($numbers[$count/2 - 1] + $numbers[$count/2]) / 2;
    } else {
        return $numbers[floor($count/2)];
    }
}

// Generate transcript data for a student
function generateTranscriptData($student_id) {
    $db = new Database();
    
    // Get student info
    $db->query("SELECT s.*, d.department_name, l.level_name
                FROM students s
                JOIN departments d ON s.department_id = d.department_id
                JOIN levels l ON s.level_id = l.level_id
                WHERE s.student_id = :student_id");
    $db->bind(':student_id', $student_id);
    $student = $db->single();
    
    if (!$student) {
        return null;
    }
    
    // Get all results grouped by session and semester
    $db->query("SELECT cr.session_id, cr.semester_id, 
                sess.session_name, sem.semester_name, sem.semester_order,
                c.course_code, c.course_title, c.credit_units,
                r.ca_score, r.exam_score, r.total_score, r.grade, r.grade_point
                FROM course_registrations cr
                JOIN sessions sess ON cr.session_id = sess.session_id
                JOIN semesters sem ON cr.semester_id = sem.semester_id
                JOIN courses c ON cr.course_id = c.course_id
                JOIN results r ON cr.registration_id = r.registration_id
                WHERE cr.student_id = :student_id
                ORDER BY sess.session_name, sem.semester_order, c.course_code");
    
    $db->bind(':student_id', $student_id);
    $results = $db->resultSet();
    
    // Group results by session and semester
    $grouped_results = [];
    foreach ($results as $result) {
        $key = $result['session_name'] . '_' . $result['semester_name'];
        $grouped_results[$key][] = $result;
    }
    
    // Calculate GPAs for each semester and cumulative
    $transcript_data = [
        'student' => $student,
        'semesters' => [],
        'summary' => getStudentPerformanceSummary($student_id)
    ];
    
    foreach ($grouped_results as $semester_key => $semester_results) {
        $session_id = $semester_results[0]['session_id'];
        $semester_id = $semester_results[0]['semester_id'];
        
        $semester_gpa = calculateSemesterGPA($student_id, $session_id, $semester_id);
        
        $transcript_data['semesters'][] = [
            'session_name' => $semester_results[0]['session_name'],
            'semester_name' => $semester_results[0]['semester_name'],
            'courses' => $semester_results,
            'gpa' => $semester_gpa['gpa'],
            'credit_units' => $semester_gpa['total_credit_units'],
            'courses_count' => $semester_gpa['courses_count']
        ];
    }
    
    return $transcript_data;
}

// Update or insert semester GPA record
function updateSemesterGPA($student_id, $session_id, $semester_id) {
    $db = new Database();
    $institution_id = get_institution_id();
    
    $gpa_data = calculateSemesterGPA($student_id, $session_id, $semester_id);
    
    // Check if record exists
    $db->query("SELECT gpa_id FROM semester_gpas 
                WHERE student_id = :student_id 
                AND session_id = :session_id 
                AND semester_id = :semester_id");
    $db->bind(':student_id', $student_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    
    $existing = $db->single();
    
    if ($existing) {
        // Update existing record
        $db->query("UPDATE semester_gpas SET 
                    gpa = :gpa,
                    credit_units = :credit_units,
                    grade_points = :grade_points,
                    courses_count = :courses_count,
                    updated_at = NOW()
                    WHERE gpa_id = :gpa_id");
        $db->bind(':gpa_id', $existing['gpa_id']);
    } else {
        // Insert new record
        $db->query("INSERT INTO semester_gpas 
                    (institution_id, student_id, session_id, semester_id, gpa, credit_units, grade_points, courses_count) 
                    VALUES (:institution_id, :student_id, :session_id, :semester_id, :gpa, :credit_units, :grade_points, :courses_count)");
        $db->bind(':institution_id', $institution_id);
        $db->bind(':student_id', $student_id);
        $db->bind(':session_id', $session_id);
        $db->bind(':semester_id', $semester_id);
    }
    
    $db->bind(':gpa', $gpa_data['gpa']);
    $db->bind(':credit_units', $gpa_data['total_credit_units']);
    $db->bind(':grade_points', $gpa_data['total_grade_points']);
    $db->bind(':courses_count', $gpa_data['courses_count']);
    
    return $db->execute();
}

// Update cumulative GPA for a student
function updateCumulativeGPA($student_id) {
    $db = new Database();
    $institution_id = get_institution_id();
    
    $cgpa_data = calculateCumulativeGPA($student_id);
    $classification = getClassification($cgpa_data['cgpa']);
    
    // Check if record exists
    $db->query("SELECT cgpa_id FROM cumulative_gpas WHERE student_id = :student_id");
    $db->bind(':student_id', $student_id);
    $existing = $db->single();
    
    if ($existing) {
        // Update existing record
        $db->query("UPDATE cumulative_gpas SET 
                    cgpa = :cgpa,
                    total_credit_units = :total_credit_units,
                    total_grade_points = :total_grade_points,
                    total_courses = :total_courses,
                    classification = :classification,
                    updated_at = NOW()
                    WHERE cgpa_id = :cgpa_id");
        $db->bind(':cgpa_id', $existing['cgpa_id']);
    } else {
        // Insert new record
        $db->query("INSERT INTO cumulative_gpas 
                    (institution_id, student_id, cgpa, total_credit_units, total_grade_points, total_courses, classification) 
                    VALUES (:institution_id, :student_id, :cgpa, :total_credit_units, :total_grade_points, :total_courses, :classification)");
        $db->bind(':institution_id', $institution_id);
        $db->bind(':student_id', $student_id);
    }
    
    $db->bind(':cgpa', $cgpa_data['cgpa']);
    $db->bind(':total_credit_units', $cgpa_data['total_credit_units']);
    $db->bind(':total_grade_points', $cgpa_data['total_grade_points']);
    $db->bind(':total_courses', $cgpa_data['total_courses']);
    $db->bind(':classification', $classification['class']);
    
    return $db->execute();
}
function get_all_sessions() {
    $db = new Database();
    $institution_id = get_institution_id();
    
    // First try academic_sessions
    $db->query("SELECT session_id, session_name FROM academic_sessions 
                WHERE institution_id = :institution_id 
                ORDER BY session_name DESC");
    $db->bind(':institution_id', $institution_id);
    $sessions = $db->resultSet();
    
    // If empty, try sessions table
    if (empty($sessions)) {
        $db->query("SELECT session_id, session_name FROM sessions 
                    WHERE institution_id = :institution_id 
                    ORDER BY session_name DESC");
        $db->bind(':institution_id', $institution_id);
        $sessions = $db->resultSet();
    }
    
    return $sessions;
}

/**
 * Get all semesters
 */
function get_all_semesters() {
    $db = new Database();
    $institution_id = get_institution_id();
    
    $db->query("SELECT semester_id, semester_name FROM semesters 
                WHERE institution_id = :institution_id 
                ORDER BY semester_name");
    $db->bind(':institution_id', $institution_id);
    
    return $db->resultSet();
}

// Batch update GPAs for all students in a session/semester
function batchUpdateGPAs($session_id, $semester_id) {
    $db = new Database();
    $institution_id = get_institution_id();
    
    // Get all students who have results in this session/semester
    $db->query("SELECT DISTINCT cr.student_id
                FROM course_registrations cr
                JOIN results r ON cr.registration_id = r.registration_id
                WHERE cr.session_id = :session_id 
                AND cr.semester_id = :semester_id
                AND cr.institution_id = :institution_id");
    
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    $db->bind(':institution_id', $institution_id);
    
    $students = $db->resultSet();
    
    $updated_count = 0;
    
    foreach ($students as $student) {
        // Update semester GPA
        if (updateSemesterGPA($student['student_id'], $session_id, $semester_id)) {
            $updated_count++;
        }
        
        // Update cumulative GPA
        updateCumulativeGPA($student['student_id']);
    }
    
    return $updated_count;
}
?>