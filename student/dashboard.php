<?php
$page_title = "Student Dashboard";
$breadcrumb = "Dashboard";

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is student
if (!is_logged_in() || !has_role('student')) {
    set_flash_message('danger', 'You must be logged in as a student to access this page');
    redirect(BASE_URL);
}

$db = new Database();
$student_id = $_SESSION['student_id'];
$institution_id = get_institution_id();

// Get current session and semester
$current_session = get_current_session($institution_id);
$current_semester = get_current_semester($institution_id);

// Get student statistics
$db->query("SELECT 
    (SELECT COUNT(*) FROM course_registrations cr 
     WHERE cr.student_id = :student_id AND cr.session_id = :session_id AND cr.semester_id = :semester_id) as current_courses,
    (SELECT COUNT(*) FROM course_registrations cr 
     WHERE cr.student_id = :student_id) as total_courses,
    (SELECT COALESCE(AVG(r.grade_point), 0) FROM results r 
     JOIN course_registrations cr ON r.registration_id = cr.registration_id 
     WHERE cr.student_id = :student_id AND r.grade_point > 0) as cgpa,
    (SELECT COUNT(*) FROM results r 
     JOIN course_registrations cr ON r.registration_id = cr.registration_id 
     WHERE cr.student_id = :student_id AND r.total_score >= 40) as passed_courses,
    (SELECT COUNT(*) FROM results r 
     JOIN course_registrations cr ON r.registration_id = cr.registration_id 
     WHERE cr.student_id = :student_id AND r.total_score < 40 AND r.total_score > 0) as failed_courses");

$db->bind(':student_id', $student_id);
$db->bind(':session_id', $current_session['session_id'] ?? 0);
$db->bind(':semester_id', $current_semester['semester_id'] ?? 0);
$stats = $db->single();

// Get current semester courses
$db->query("SELECT c.course_code, c.course_title, c.credit_units,
            r.ca_score, r.exam_score, r.total_score, r.grade, r.grade_point
            FROM course_registrations cr
            JOIN courses c ON cr.course_id = c.course_id
            LEFT JOIN results r ON cr.registration_id = r.registration_id
            WHERE cr.student_id = :student_id 
            AND cr.session_id = :session_id 
            AND cr.semester_id = :semester_id
            ORDER BY c.course_code");

$db->bind(':student_id', $student_id);
$db->bind(':session_id', $current_session['session_id'] ?? 0);
$db->bind(':semester_id', $current_semester['semester_id'] ?? 0);
$current_courses = $db->resultSet();

// Get recent results (last 5)
$db->query("SELECT c.course_code, c.course_title, r.total_score, r.grade, r.grade_point,
            s.session_name, sem.semester_name
            FROM results r
            JOIN course_registrations cr ON r.registration_id = cr.registration_id
            JOIN courses c ON cr.course_id = c.course_id
            LEFT JOIN sessions s ON cr.session_id = s.session_id
            LEFT JOIN semesters sem ON cr.semester_id = sem.semester_id
            WHERE cr.student_id = :student_id
            ORDER BY r.created_at DESC
            LIMIT 5");

$db->bind(':student_id', $student_id);
$recent_results = $db->resultSet();

// Calculate classification
$cgpa = $stats['cgpa'];
$classification = 'N/A';
if ($cgpa >= 4.5) {
    $classification = 'First Class';
} elseif ($cgpa >= 3.5) {
    $classification = 'Second Class Upper';
} elseif ($cgpa >= 2.5) {
    $classification = 'Second Class Lower';
} elseif ($cgpa >= 1.5) {
    $classification = 'Third Class';
} elseif ($cgpa > 0) {
    $classification = 'Pass';
}

include_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-1">Welcome back, <?php echo $_SESSION['full_name']; ?>!</h4>
                            <p class="mb-0">
                                <i class="bi bi-person-badge me-2"></i><?php echo $_SESSION['matric_number']; ?> |
                                <i class="bi bi-building me-2"></i><?php echo $_SESSION['department_name']; ?> |
                                <i class="bi bi-mortarboard me-2"></i><?php echo $_SESSION['level_name']; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="text-white-50">
                                <?php if ($current_session && $current_semester): ?>
                                    <small>Current Session: <strong><?php echo $current_session['session_name']; ?></strong></small><br>
                                    <small>Current Semester: <strong><?php echo $current_semester['semester_name']; ?></strong></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-6 text-primary mb-2">
                        <i class="bi bi-trophy"></i>
                    </div>
                    <h3 class="mb-1"><?php echo number_format($cgpa, 2); ?></h3>
                    <p class="text-muted mb-0">CGPA</p>
                    <small class="text-success"><?php echo $classification; ?></small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-6 text-info mb-2">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['current_courses']; ?></h3>
                    <p class="text-muted mb-0">Current Courses</p>
                    <small class="text-muted"><?php echo $stats['total_courses']; ?> total registered</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-6 text-success mb-2">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['passed_courses']; ?></h3>
                    <p class="text-muted mb-0">Passed Courses</p>
                    <small class="text-success">Grade ≥ 40</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-6 text-danger mb-2">
                        <i class="bi bi-x-circle"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $stats['failed_courses']; ?></h3>
                    <p class="text-muted mb-0">Failed Courses</p>
                    <small class="text-danger">Grade < 40</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Current Semester Courses -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-journal-text me-2"></i>Current Semester Courses
                    </h5>
                    <a href="register-courses.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-2"></i>Register Courses
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($current_courses)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-journal-x display-4 text-muted"></i>
                            <h5 class="text-muted mt-3">No Courses Registered</h5>
                            <p class="text-muted">You haven't registered for any courses this semester.</p>
                            <a href="register-courses.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Register Now
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Title</th>
                                        <th>Credit Units</th>
                                        <th>CA Score</th>
                                        <th>Exam Score</th>
                                        <th>Total</th>
                                        <th>Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($current_courses as $course): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $course['course_code']; ?></span>
                                            </td>
                                            <td><?php echo $course['course_title']; ?></td>
                                            <td><?php echo $course['credit_units']; ?></td>
                                            <td>
                                                <?php echo $course['ca_score'] !== null ? $course['ca_score'] : '-'; ?>
                                            </td>
                                            <td>
                                                <?php echo $course['exam_score'] !== null ? $course['exam_score'] : '-'; ?>
                                            </td>
                                            <td>
                                                <?php if ($course['total_score'] !== null): ?>
                                                    <span class="badge bg-<?php echo $course['total_score'] >= 40 ? 'success' : 'danger'; ?>">
                                                        <?php echo $course['total_score']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($course['grade']): ?>
                                                    <span class="badge bg-<?php echo $course['total_score'] >= 40 ? 'success' : 'danger'; ?>">
                                                        <?php echo $course['grade']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Recent Results -->
        <div class="col-md-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="register-courses.php" class="btn btn-outline-primary">
                            <i class="bi bi-journal-plus me-2"></i>Register Courses
                        </a>
                        <a href="my-courses.php" class="btn btn-outline-info">
                            <i class="bi bi-journal-bookmark me-2"></i>View My Courses
                        </a>
                        <a href="results.php" class="btn btn-outline-success">
                            <i class="bi bi-graph-up me-2"></i>View Results
                        </a>
                        <a href="transcript.php" class="btn btn-outline-warning">
                            <i class="bi bi-file-earmark-text me-2"></i>Download Transcript
                        </a>
                        <a href="profile.php" class="btn btn-outline-secondary">
                            <i class="bi bi-person-gear me-2"></i>Edit Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Results -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>Recent Results
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_results)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-graph-up display-6 text-muted"></i>
                            <p class="text-muted mt-2 mb-0">No results available yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_results as $result): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo $result['course_code']; ?></h6>
                                    <small class="text-muted"><?php echo $result['session_name'] . ' - ' . $result['semester_name']; ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $result['total_score'] >= 40 ? 'success' : 'danger'; ?>">
                                        <?php echo $result['grade']; ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?php echo $result['total_score']; ?>%</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="results.php" class="btn btn-sm btn-outline-primary">View All Results</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>

<?php include_once 'includes/footer.php'; ?>
