<?php
$page_title = "Result Entry";
$breadcrumb = "Results > Enter Results";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../includes/gpa_functions.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Initialize database connection
$db = new Database();
$institution_id = get_institution_id();

// Get filter parameters
$course_id = isset($_GET['course_id']) ? clean_input($_GET['course_id']) : '';
$student_id = isset($_GET['student_id']) ? clean_input($_GET['student_id']) : '';
$session_id = isset($_GET['session_id']) ? clean_input($_GET['session_id']) : '';
$semester_id = isset($_GET['semester_id']) ? clean_input($_GET['semester_id']) : '';

// Get current session and semester if not specified
if (empty($session_id) || empty($semester_id)) {
    $current_session = get_current_session();
    $current_semester = get_current_semester();
    
    if ($current_session && empty($session_id)) {
        $session_id = $current_session['session_id'];
    }
    
    if ($current_semester && empty($semester_id)) {
        $semester_id = $current_semester['semester_id'];
    }
}

// Get sessions for dropdown
$db->query("SELECT * FROM sessions WHERE institution_id = :institution_id ORDER BY session_name DESC");
$db->bind(':institution_id', $institution_id);
$sessions = $db->resultSet();

// Get semesters for dropdown
$db->query("SELECT * FROM semesters WHERE institution_id = :institution_id ORDER BY semester_id");
$db->bind(':institution_id', $institution_id);
$semesters = $db->resultSet();

// Get courses for dropdown (if student is selected)
$courses = [];
if (!empty($student_id) && !empty($session_id) && !empty($semester_id)) {
    $db->query("SELECT cr.*, c.course_code, c.course_title, c.credit_units
                FROM course_registrations cr
                JOIN courses c ON cr.course_id = c.course_id
                WHERE cr.student_id = :student_id 
                AND cr.session_id = :session_id 
                AND cr.semester_id = :semester_id
                AND cr.institution_id = :institution_id
                ORDER BY c.course_code");
    $db->bind(':student_id', $student_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    $db->bind(':institution_id', $institution_id);
    $courses = $db->resultSet();
}

// Get students for dropdown (if course is selected)
$students = [];
if (!empty($course_id) && !empty($session_id) && !empty($semester_id)) {
    $db->query("SELECT cr.*, s.first_name, s.last_name, s.matric_number
                FROM course_registrations cr
                JOIN students s ON cr.student_id = s.student_id
                WHERE cr.course_id = :course_id 
                AND cr.session_id = :session_id 
                AND cr.semester_id = :semester_id
                AND cr.institution_id = :institution_id
                ORDER BY s.first_name, s.last_name");
    $db->bind(':course_id', $course_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    $db->bind(':institution_id', $institution_id);
    $students = $db->resultSet();
}

// Get course info if selected
$course = null;
if (!empty($course_id)) {
    $db->query("SELECT c.*, d.department_name 
                FROM courses c
                JOIN departments d ON c.department_id = d.department_id
                WHERE c.course_id = :course_id AND c.institution_id = :institution_id");
    $db->bind(':course_id', $course_id);
    $db->bind(':institution_id', $institution_id);
    $course = $db->single();
}

// Get student info if selected
$student = null;
if (!empty($student_id)) {
    $db->query("SELECT s.*, d.department_name, l.level_name 
                FROM students s
                JOIN departments d ON s.department_id = d.department_id
                JOIN levels l ON s.level_id = l.level_id
                WHERE s.student_id = :student_id AND s.institution_id = :institution_id");
    $db->bind(':student_id', $student_id);
    $db->bind(':institution_id', $institution_id);
    $student = $db->single();
}

// Get session and semester info
$session = null;
$semester = null;
if (!empty($session_id)) {
    $db->query("SELECT * FROM sessions WHERE session_id = :session_id");
    $db->bind(':session_id', $session_id);
    $session = $db->single();
}
if (!empty($semester_id)) {
    $db->query("SELECT * FROM semesters WHERE semester_id = :semester_id");
    $db->bind(':semester_id', $semester_id);
    $semester = $db->single();
}

// Handle form submission for result entry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_results'])) {
    $registration_ids = $_POST['registration_id'] ?? [];
    $ca_scores = $_POST['ca_score'] ?? [];
    $exam_scores = $_POST['exam_score'] ?? [];
    $total_scores = $_POST['total_score'] ?? [];
    
    $success_count = 0;
    $error_count = 0;
    $updated_students = [];
    
    foreach ($registration_ids as $index => $registration_id) {
        if (isset($ca_scores[$index]) && isset($exam_scores[$index]) && isset($total_scores[$index])) {
            $ca_score = clean_input($ca_scores[$index]);
            $exam_score = clean_input($exam_scores[$index]);
            $total_score = clean_input($total_scores[$index]);
            
            // Validate scores
            if ($ca_score < 0 || $ca_score > 40 || $exam_score < 0 || $exam_score > 60 || $total_score < 0 || $total_score > 100) {
                $error_count++;
                continue;
            }
            
            // Calculate grade
            $grade_info = calculateGrade($total_score);
            
            // Check if result already exists
            $db->query("SELECT result_id FROM results WHERE registration_id = :registration_id");
            $db->bind(':registration_id', $registration_id);
            $existing_result = $db->single();
            
            if ($existing_result) {
                // Update existing result
                $db->query("UPDATE results SET 
                            ca_score = :ca_score,
                            exam_score = :exam_score,
                            total_score = :total_score,
                            grade = :grade,
                            grade_point = :grade_point,
                            remark = :remark,
                            updated_at = NOW()
                            WHERE registration_id = :registration_id");
            } else {
                // Insert new result
                $db->query("INSERT INTO results 
                            (registration_id, ca_score, exam_score, total_score, grade, grade_point, remark, created_at) 
                            VALUES 
                            (:registration_id, :ca_score, :exam_score, :total_score, :grade, :grade_point, :remark, NOW())");
            }
            
            $db->bind(':registration_id', $registration_id);
            $db->bind(':ca_score', $ca_score);
            $db->bind(':exam_score', $exam_score);
            $db->bind(':total_score', $total_score);
            $db->bind(':grade', $grade_info['grade']);
            $db->bind(':grade_point', $grade_info['grade_point']);
            $db->bind(':remark', $grade_info['remark']);
            
            if ($db->execute()) {
                $success_count++;
                
                // Get student ID for this registration
                $db->query("SELECT student_id FROM course_registrations WHERE registration_id = :registration_id");
                $db->bind(':registration_id', $registration_id);
                $reg = $db->single();
                
                if ($reg) {
                    $updated_students[$reg['student_id']] = true;
                }
            } else {
                $error_count++;
            }
        }
    }
    
    // Update GPAs for affected students
    foreach (array_keys($updated_students) as $updated_student_id) {
        updateSemesterGPA($updated_student_id, $session_id, $semester_id);
        updateCumulativeGPA($updated_student_id);
    }
    
    if ($success_count > 0) {
        set_flash_message('success', $success_count . ' result(s) saved successfully.');
    }
    
    if ($error_count > 0) {
        set_flash_message('danger', $error_count . ' result(s) failed to save. Please check the values and try again.');
    }
    
    // Redirect to maintain clean URL
    $redirect_url = 'entry.php?';
    if (!empty($course_id)) $redirect_url .= 'course_id=' . $course_id . '&';
    if (!empty($student_id)) $redirect_url .= 'student_id=' . $student_id . '&';
    if (!empty($session_id)) $redirect_url .= 'session_id=' . $session_id . '&';
    if (!empty($semester_id)) $redirect_url .= 'semester_id=' . $semester_id;
    
    redirect($redirect_url);
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-funnel me-2"></i>Select Parameters
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Academic Session</label>
                        <select class="form-select" name="session_id" id="sessionSelect" required>
                            <option value="">Select Session</option>
                            <?php foreach ($sessions as $sess): ?>
                                <option value="<?php echo $sess['session_id']; ?>" 
                                        <?php echo ($session_id == $sess['session_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sess['session_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Semester</label>
                        <select class="form-select" name="semester_id" id="semesterSelect" required>
                            <option value="">Select Semester</option>
                            <?php foreach ($semesters as $sem): ?>
                                <option value="<?php echo $sem['semester_id']; ?>" 
                                        <?php echo ($semester_id == $sem['semester_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sem['semester_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id" id="courseSelect" <?php echo empty($student_id) ? '' : 'required'; ?>>
                            <option value="">Select Course</option>
                            <?php if (!empty($student_id) && !empty($courses)): ?>
                                <?php foreach ($courses as $c): ?>
                                    <option value="<?php echo $c['course_id']; ?>" 
                                            <?php echo ($course_id == $c['course_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['course_code'] . ' - ' . $c['course_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php elseif (!empty($course_id) && $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" selected>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Student</label>
                        <select class="form-select" name="student_id" id="studentSelect" <?php echo empty($course_id) ? '' : 'required'; ?>>
                            <option value="">Select Student</option>
                            <?php if (!empty($course_id) && !empty($students)): ?>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['student_id']; ?>" 
                                            <?php echo ($student_id == $s['student_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['matric_number'] . ' - ' . $s['first_name'] . ' ' . $s['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php elseif (!empty($student_id) && $student): ?>
                                <option value="<?php echo $student['student_id']; ?>" selected>
                                    <?php echo htmlspecialchars($student['matric_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?>
                                </option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                        <i class="bi bi-x-circle me-2"></i>Clear Filters
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-2"></i>Load Results
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ((!empty($course_id) && empty($student_id)) || (!empty($student_id) && empty($course_id))): ?>
        <!-- Results Entry Form -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <?php if (!empty($course_id) && $course): ?>
                        <i class="bi bi-pencil-square me-2"></i>Enter Results for <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?>
                    <?php elseif (!empty($student_id) && $student): ?>
                        <i class="bi bi-pencil-square me-2"></i>Enter Results for <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['matric_number'] . ')'); ?>
                    <?php endif; ?>
                </h5>
                
                <?php if (!empty($session) && !empty($semester)): ?>
                    <span class="badge bg-info fs-6">
                        <?php echo htmlspecialchars($session['session_name'] . ' - ' . $semester['semester_name']); ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="card-body">
                <?php if ((!empty($course_id) && empty($students)) || (!empty($student_id) && empty($courses))): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php if (!empty($course_id)): ?>
                            No students registered for this course in the selected session and semester.
                        <?php else: ?>
                            No courses registered for this student in the selected session and semester.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" class="result-form">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <?php if (!empty($course_id)): ?>
                                            <th>Matric Number</th>
                                            <th>Student Name</th>
                                        <?php else: ?>
                                            <th>Course Code</th>
                                            <th>Course Title</th>
                                            <th>Credit Units</th>
                                        <?php endif; ?>
                                        <th class="text-center">CA Score (40)</th>
                                        <th class="text-center">Exam Score (60)</th>
                                        <th class="text-center">Total Score (100)</th>
                                        <th class="text-center">Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($course_id) && !empty($students)): ?>
                                        <?php foreach ($students as $index => $s): ?>
                                            <?php
                                            // Get existing result if any
                                            $db->query("SELECT * FROM results WHERE registration_id = :registration_id");
                                            $db->bind(':registration_id', $s['registration_id']);
                                            $result = $db->single();
                                            
                                            $ca_score = $result ? $result['ca_score'] : '';
                                            $exam_score = $result ? $result['exam_score'] : '';
                                            $total_score = $result ? $result['total_score'] : '';
                                            $grade = $result ? $result['grade'] : '';
                                            ?>
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="registration_id[<?php echo $index; ?>]" value="<?php echo $s['registration_id']; ?>">
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($s['matric_number']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                                <td>
                                                    <input type="number" class="form-control score-input ca-score" 
                                                           name="ca_score[<?php echo $index; ?>]" 
                                                           min="0" max="40" step="0.1"
                                                           value="<?php echo $ca_score; ?>"
                                                           data-index="<?php echo $index; ?>">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control score-input exam-score" 
                                                           name="exam_score[<?php echo $index; ?>]" 
                                                           min="0" max="60" step="0.1"
                                                           value="<?php echo $exam_score; ?>"
                                                           data-index="<?php echo $index; ?>">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control score-input total-score" 
                                                           name="total_score[<?php echo $index; ?>]" 
                                                           min="0" max="100" step="0.1"
                                                           value="<?php echo $total_score; ?>"
                                                           data-index="<?php echo $index; ?>" readonly>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control grade-display" 
                                                           value="<?php echo $grade; ?>" readonly>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php elseif (!empty($student_id) && !empty($courses)): ?>
                                        <?php foreach ($courses as $index => $c): ?>
                                            <?php
                                            // Get existing result if any
                                            $db->query("SELECT * FROM results WHERE registration_id = :registration_id");
                                            $db->bind(':registration_id', $c['registration_id']);
                                            $result = $db->single();
                                            
                                            $ca_score = $result ? $result['ca_score'] : '';
                                            $exam_score = $result ? $result['exam_score'] : '';
                                            $total_score = $result ? $result['total_score'] : '';
                                            $grade = $result ? $result['grade'] : '';
                                            ?>
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="registration_id[<?php echo $index; ?>]" value="<?php echo $c['registration_id']; ?>">
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($c['course_code']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($c['course_title']); ?></td>
                                                <td><span class="badge bg-info"><?php echo $c['credit_units']; ?></span></td>
                                                <td>
                                                    <input type="number" class="form-control score-input ca-score" 
                                                           name="ca_score[<?php echo $index; ?>]" 
                                                           min="0" max="40" step="0.1"
                                                           value="<?php echo $ca_score; ?>"
                                                           data-index="<?php echo $index; ?>">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control score-input exam-score" 
                                                           name="exam_score[<?php echo $index; ?>]" 
                                                           min="0" max="60" step="0.1"
                                                           value="<?php echo $exam_score; ?>"
                                                           data-index="<?php echo $index; ?>">
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control score-input total-score" 
                                                           name="total_score[<?php echo $index; ?>]" 
                                                           min="0" max="100" step="0.1"
                                                           value="<?php echo $total_score; ?>"
                                                           data-index="<?php echo $index; ?>" readonly>
                                                </td>
                                                <td>
                                                    <input type="text" class="form-control grade-display" 
                                                           value="<?php echo $grade; ?>" readonly>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" name="save_results" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Results
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif (!empty($course_id) && !empty($student_id)): ?>
        <!-- Single Student-Course Result Entry -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-pencil-square me-2"></i>Enter Result
                </h5>
            </div>
            
            <div class="card-body">
                <?php
                // Get registration record
                $db->query("SELECT cr.* FROM course_registrations cr
                            WHERE cr.student_id = :student_id 
                            AND cr.course_id = :course_id
                            AND cr.session_id = :session_id
                            AND cr.semester_id = :semester_id
                            AND cr.institution_id = :institution_id");
                $db->bind(':student_id', $student_id);
                $db->bind(':course_id', $course_id);
                $db->bind(':session_id', $session_id);
                $db->bind(':semester_id', $semester_id);
                $db->bind(':institution_id', $institution_id);
                $registration = $db->single();
                
                if (!$registration) {
                    echo '<div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            No registration found for this student and course in the selected session and semester.
                          </div>';
                } else {
                    // Get existing result if any
                    $db->query("SELECT * FROM results WHERE registration_id = :registration_id");
                    $db->bind(':registration_id', $registration['registration_id']);
                    $result = $db->single();
                    
                    $ca_score = $result ? $result['ca_score'] : '';
                    $exam_score = $result ? $result['exam_score'] : '';
                    $total_score = $result ? $result['total_score'] : '';
                    $grade = $result ? $result['grade'] : '';
                ?>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Student Information</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                <p><strong>Matric Number:</strong> <?php echo htmlspecialchars($student['matric_number']); ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department_name']); ?></p>
                                <p><strong>Level:</strong> <?php echo htmlspecialchars($student['level_name']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Course Information</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Course Code:</strong> <?php echo htmlspecialchars($course['course_code']); ?></p>
                                <p><strong>Course Title:</strong> <?php echo htmlspecialchars($course['course_title']); ?></p>
                                <p><strong>Credit Units:</strong> <?php echo $course['credit_units']; ?></p>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($course['department_name']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" action="" class="result-form">
                    <input type="hidden" name="registration_id[0]" value="<?php echo $registration['registration_id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">CA Score (40)</label>
                                <input type="number" class="form-control ca-score" 
                                       name="ca_score[0]" 
                                       min="0" max="40" step="0.1"
                                       value="<?php echo $ca_score; ?>"
                                       data-index="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Exam Score (60)</label>
                                <input type="number" class="form-control exam-score" 
                                       name="exam_score[0]" 
                                       min="0" max="60" step="0.1"
                                       value="<?php echo $exam_score; ?>"
                                       data-index="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Total Score (100)</label>
                                <input type="number" class="form-control total-score" 
                                       name="total_score[0]" 
                                       min="0" max="100" step="0.1"
                                       value="<?php echo $total_score; ?>"
                                       data-index="0" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Grade</label>
                                <input type="text" class="form-control grade-display" 
                                       value="<?php echo $grade; ?>" readonly>
                            </div>
                        </div>
                        
                        <?php if ($result): ?>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Last Updated</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo format_date($result['updated_at'] ?? $result['created_at'], 'd M, Y H:i'); ?>" readonly>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-3">
                        <a href="entry.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Result Entry
                        </a>
                        <button type="submit" name="save_results" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Save Result
                        </button>
                    </div>
                </form>
                <?php } ?>
            </div>
        </div>
    <?php elseif (!empty($session_id) && !empty($semester_id)): ?>
        <!-- Course Selection -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-journal-bookmark me-2"></i>Select Course
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get courses with registrations in this session/semester
                                               // Get courses with registrations in this session/semester
                        $db->query("SELECT DISTINCT c.course_id, c.course_code, c.course_title, c.credit_units,
                                    d.department_name, COUNT(cr.registration_id) as student_count,
                                    (SELECT COUNT(*) FROM course_registrations cr2 
                                     JOIN results r ON cr2.registration_id = r.registration_id 
                                     WHERE cr2.course_id = c.course_id 
                                     AND cr2.session_id = :session_id 
                                     AND cr2.semester_id = :semester_id) as result_count
                                    FROM courses c
                                    JOIN course_registrations cr ON c.course_id = cr.course_id
                                    JOIN departments d ON c.department_id = d.department_id
                                    WHERE cr.session_id = :session_id 
                                    AND cr.semester_id = :semester_id
                                    AND c.institution_id = :institution_id
                                    GROUP BY c.course_id
                                    ORDER BY c.course_code");
                        $db->bind(':session_id', $session_id);
                        $db->bind(':semester_id', $semester_id);
                        $db->bind(':institution_id', $institution_id);
                        $available_courses = $db->resultSet();
                        
                        if (empty($available_courses)) {
                            echo '<div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    No courses with registrations found for the selected session and semester.
                                  </div>';
                        } else {
                        ?>
                        <div class="list-group">
                            <?php foreach ($available_courses as $ac): ?>
                                <a href="entry.php?course_id=<?php echo $ac['course_id']; ?>&session_id=<?php echo $session_id; ?>&semester_id=<?php echo $semester_id; ?>" 
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($ac['course_code']); ?></div>
                                        <div><?php echo htmlspecialchars($ac['course_title']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($ac['department_name']); ?> • <?php echo $ac['credit_units']; ?> Units</small>
                                    </div>
                                    <div class="text-end">
                                        <div class="badge bg-primary rounded-pill"><?php echo $ac['student_count']; ?> Students</div>
                                        <div class="mt-1">
                                            <?php if ($ac['result_count'] == 0): ?>
                                                <span class="badge bg-danger">No Results</span>
                                            <?php elseif ($ac['result_count'] < $ac['student_count']): ?>
                                                <span class="badge bg-warning"><?php echo $ac['result_count']; ?>/<?php echo $ac['student_count']; ?> Results</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">All Results Entered</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-people me-2"></i>Select Student
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get students with registrations in this session/semester
                        $db->query("SELECT DISTINCT s.student_id, s.first_name, s.last_name, s.matric_number,
                                    d.department_name, l.level_name, COUNT(cr.registration_id) as course_count,
                                    (SELECT COUNT(*) FROM course_registrations cr2 
                                     JOIN results r ON cr2.registration_id = r.registration_id 
                                     WHERE cr2.student_id = s.student_id 
                                     AND cr2.session_id = :session_id 
                                     AND cr2.semester_id = :semester_id) as result_count
                                    FROM students s
                                    JOIN course_registrations cr ON s.student_id = cr.student_id
                                    JOIN departments d ON s.department_id = d.department_id
                                    JOIN levels l ON s.level_id = l.level_id
                                    WHERE cr.session_id = :session_id 
                                    AND cr.semester_id = :semester_id
                                    AND s.institution_id = :institution_id
                                    GROUP BY s.student_id
                                    ORDER BY s.first_name, s.last_name");
                        $db->bind(':session_id', $session_id);
                        $db->bind(':semester_id', $semester_id);
                        $db->bind(':institution_id', $institution_id);
                        $available_students = $db->resultSet();
                        
                        if (empty($available_students)) {
                            echo '<div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    No students with course registrations found for the selected session and semester.
                                  </div>';
                        } else {
                        ?>
                        <div class="list-group">
                            <?php foreach ($available_students as $as): ?>
                                <a href="entry.php?student_id=<?php echo $as['student_id']; ?>&session_id=<?php echo $session_id; ?>&semester_id=<?php echo $semester_id; ?>" 
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($as['first_name'] . ' ' . $as['last_name']); ?></div>
                                        <div><?php echo htmlspecialchars($as['matric_number']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($as['department_name']); ?> • <?php echo htmlspecialchars($as['level_name']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="badge bg-primary rounded-pill"><?php echo $as['course_count']; ?> Courses</div>
                                        <div class="mt-1">
                                            <?php if ($as['result_count'] == 0): ?>
                                                <span class="badge bg-danger">No Results</span>
                                            <?php elseif ($as['result_count'] < $as['course_count']): ?>
                                                <span class="badge bg-warning"><?php echo $as['result_count']; ?>/<?php echo $as['course_count']; ?> Results</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">All Results Entered</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// JavaScript for result entry form
document.addEventListener('DOMContentLoaded', function() {
    // Function to calculate total score
    function calculateTotal(index) {
        const caInput = document.querySelector(`.ca-score[data-index="${index}"]`);
        const examInput = document.querySelector(`.exam-score[data-index="${index}"]`);
        const totalInput = document.querySelector(`.total-score[data-index="${index}"]`);
        const gradeDisplay = document.querySelectorAll('.grade-display')[index];
        
        if (caInput && examInput && totalInput) {
            const ca = parseFloat(caInput.value) || 0;
            const exam = parseFloat(examInput.value) || 0;
            const total = ca + exam;
            
            totalInput.value = total.toFixed(1);
            
            // Update grade display
            if (total >= 0) {
                let grade = '';
                
                if (total >= 70) grade = 'A';
                else if (total >= 60) grade = 'B';
                else if (total >= 50) grade = 'C';
                else if (total >= 45) grade = 'D';
                else if (total >= 40) grade = 'E';
                else grade = 'F';
                
                if (gradeDisplay) gradeDisplay.value = grade;
            } else {
                if (gradeDisplay) gradeDisplay.value = '';
            }
        }
    }
    
    // Add event listeners to CA and Exam score inputs
    document.querySelectorAll('.ca-score, .exam-score').forEach(input => {
        input.addEventListener('input', function() {
            const index = this.getAttribute('data-index');
            calculateTotal(index);
        });
    });
    
    // Calculate initial totals
    document.querySelectorAll('.ca-score').forEach(input => {
        const index = input.getAttribute('data-index');
        calculateTotal(index);
    });
});

// Function to clear filters
function clearFilters() {
    window.location.href = 'entry.php';
}
</script>

<?php include_once '../includes/footer.php'; ?>