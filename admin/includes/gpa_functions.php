<?php

/**
 * GPA Functions for the Admin Panel
 */

/**
 * Calculate GPA for a student in a specific session and semester.
 *
 * @param int $student_id The ID of the student.
 * @param int $session_id The ID of the session.
 * @param int $semester_id The ID of the semester.
 * @return float|null The calculated GPA, or null if no grades are found.
 */
function calculate_gpa($student_id, $session_id, $semester_id) {
    $db = new Database();

    // Query to fetch grades for the student in the specified session and semester
    $db->query("SELECT course_grades.grade_value, courses.credit_hours 
                FROM course_grades
                INNER JOIN courses ON course_grades.course_id = courses.course_id
                WHERE course_grades.student_id = :student_id
                AND course_grades.session_id = :session_id
                AND course_grades.semester_id = :semester_id");

    $db->bind(':student_id', $student_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);

    $grades = $db->resultSet();

    if (!$grades) {
        return null; // No grades found for the student in this session and semester
    }

    $total_weighted_grade_points = 0;
    $total_credit_hours = 0;

    foreach ($grades as $grade) {
        $total_weighted_grade_points += ($grade->grade_value * $grade->credit_hours);
        $total_credit_hours += $grade->credit_hours;
    }

    // Calculate GPA
    $gpa = $total_weighted_grade_points / $total_credit_hours;

    return round($gpa, 2); // Round to 2 decimal places
}


/**
 * Get all students with their basic information.
 *
 * @return array An array of student objects.
 */
function get_all_students() {
    $db = new Database();

    $db->query("SELECT student_id, first_name, last_name, email FROM students");
    $students = $db->resultSet();

    return $students;
}

/**
 * Get a student by their ID.
 *
 * @param int $student_id The ID of the student.
 * @return object|null The student object, or null if not found.
 */
function get_student_by_id($student_id) {
    $db = new Database();

    $db->query("SELECT student_id, first_name, last_name, email FROM students WHERE student_id = :student_id");
    $db->bind(':student_id', $student_id);

    $student = $db->single();

    return $student;
}

/**
 * Get all courses.
 *
 * @return array An array of course objects.
 */
function get_all_courses() {
    $db = new Database();

    $db->query("SELECT course_id, course_name, course_code, credit_hours FROM courses");
    $courses = $db->resultSet();

    return $courses;
}

/**
 * Get a course by its ID.
 *
 * @param int $course_id The ID of the course.
 * @return object|null The course object, or null if not found.
 */
function get_course_by_id($course_id) {
    $db = new Database();

    $db->query("SELECT course_id, course_name, course_code, credit_hours FROM courses WHERE course_id = :course_id");
    $db->bind(':course_id', $course_id);

    $course = $db->single();

    return $course;
}

/**
 * Get all grade values.
 *
 * @return array An array of grade value objects.
 */
function get_all_grade_values() {
    $db = new Database();

    $db->query("SELECT grade_value_id, grade_letter, grade_value FROM grade_values ORDER BY grade_value DESC");
    $grade_values = $db->resultSet();

    return $grade_values;
}

/**
 * Get a grade value by its ID.
 *
 * @param int $grade_value_id The ID of the grade value.
 * @return object|null The grade value object, or null if not found.
 */
function get_grade_value_by_id($grade_value_id) {
    $db = new Database();

    $db->query("SELECT grade_value_id, grade_letter, grade_value FROM grade_values WHERE grade_value_id = :grade_value_id");
    $db->bind(':grade_value_id', $grade_value_id);

    $grade_value = $db->single();

    return $grade_value;
}

/**
 * Get all course grades.
 *
 * @return array An array of course grade objects.
 */
function get_all_course_grades() {
    $db = new Database();

    $db->query("SELECT course_grade_id, student_id, course_id, session_id, semester_id, grade_value_id FROM course_grades");
    $course_grades = $db->resultSet();

    return $course_grades;
}

/**
 * Get a course grade by its ID.
 *
 * @param int $course_grade_id The ID of the course grade.
 * @return object|null The course grade object, or null if not found.
 */
function get_course_grade_by_id($course_grade_id) {
    $db = new Database();

    $db->query("SELECT course_grade_id, student_id, course_id, session_id, semester_id, grade_value_id FROM course_grades WHERE course_grade_id = :course_grade_id");
    $db->bind(':course_grade_id', $course_grade_id);

    $course_grade = $db->single();

    return $course_grade;
}

/**
 * Get course grades for a specific student.
 *
 * @param int $student_id The ID of the student.
 * @return array An array of course grade objects.
 */
function get_course_grades_by_student($student_id) {
    $db = new Database();

    $db->query("SELECT course_grade_id, student_id, course_id, session_id, semester_id, grade_value_id FROM course_grades WHERE student_id = :student_id");
    $db->bind(':student_id', $student_id);

    $course_grades = $db->resultSet();

    return $course_grades;
}

/**
 * Get course grades for a specific course.
 *
 * @param int $course_id The ID of the course.
 * @return array An array of course grade objects.
 */
function get_course_grades_by_course($course_id) {
    $db = new Database();

    $db->query("SELECT course_grade_id, student_id, course_id, session_id, semester_id, grade_value_id FROM course_grades WHERE course_id = :course_id");
    $db->bind(':course_id', $course_id);

    $course_grades = $db->resultSet();

    return $course_grades;
}

/**
 * Get course grades for a specific session.
 *
 * @param int $session_id The ID of the session.
 * @return array An array of course grade objects.
 */
function get_course_grades_by_session($session_id) {
    $db = new Database();

    $db->query("SELECT course_grade_id, student_id, course_id, session_id, semester_id, grade_value_id FROM course_grades WHERE session_id = :session_id");
    $db->bind(':session_id', $session_id);

    $course_grades = $db->resultSet();

    return $course_grades;
}

/**
 * Get course grades for a specific semester.
 *
 * @param int $semester_id The ID of the semester.
 * @return array An array of course grade objects.
 */
function get_course_grades_by_semester($semester_id) {
    $db = new Database();

    $db->query("SELECT course_grade_id, student_id, course_id, session_id, semester_id, grade_value_id FROM course_grades WHERE semester_id = :semester_id");
    $db->bind(':semester_id', $semester_id);

    $course_grades = $db->resultSet();

    return $course_grades;
}

/**
 * Get course grades for a specific student, session, and semester.
 *
 * @param int $student_id The ID of the student.
 * @param int $session_id The ID of the session.
 * @param int $semester_id The ID of the semester.
 * @return array An array of course grade objects.
 */
function get_course_grades_by_student_session_semester($student_id, $session_id, $semester_id) {
    $db = new Database();

    $db->query("SELECT course_grade_id, student_id, course_id, session_id, semester_id, grade_value_id FROM course_grades WHERE student_id = :student_id AND session_id = :session_id AND semester_id = :semester_id");
    $db->bind(':student_id', $student_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);

    $course_grades = $db->resultSet();

    return $course_grades;
}

/**
 * Get sessions for GPA calculations (local version)
 */
function get_sessions_for_gpa() {
    $db = new Database();
    $institution_id = get_institution_id();
    
    $db->query("SELECT session_id, session_name FROM sessions 
                WHERE institution_id = :institution_id 
                ORDER BY session_name DESC");
    $db->bind(':institution_id', $institution_id);
    
    return $db->resultSet();
}

/**
 * Get semesters for GPA calculations (local version)
 */
function get_semesters_for_gpa() {
    $db = new Database();
    $institution_id = get_institution_id();
    
    $db->query("SELECT semester_id, semester_name FROM semesters 
                WHERE institution_id = :institution_id 
                ORDER BY semester_name");
    $db->bind(':institution_id', $institution_id);
    
    return $db->resultSet();
}
