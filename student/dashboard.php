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

// --- Start of Rewritten Data Fetching Logic ---

// 1. Initialize all variables to a default, safe state.
$stats = [
    'current_courses' => 0,
    'total_courses' => 0,
    'cgpa' => 0.00,
    'passed_courses' => 0,
    'failed_courses' => 0
];
$current_courses = [];
$recent_results = [];
$classification = 'N/A';

// 2. Defensively get session and institution IDs.
// If student_id is missing, the session is invalid. Force a logout to be safe.
if (!isset($_SESSION['student_id'])) {
    set_flash_message('danger', 'Your session is invalid. Please log in again.');
    redirect(BASE_URL . 'logout.php');
}
$student_id = $_SESSION['student_id'];
$institution_id = get_institution_id();

// 3. Get current academic period, but don't assume it exists.
$current_session = get_current_session($institution_id);
$current_semester = get_current_semester($institution_id);

// 4. Fetch general stats that DO NOT depend on the current academic period.
// This query is now simpler and runs independently.
try {
    $db_stats = new Database();
    $db_stats->query("SELECT
        (SELECT COUNT(*) FROM course_registrations cr WHERE cr.student_id = :student_id) as total_courses,
        (SELECT COALESCE(AVG(r.grade_point), 0) FROM results r JOIN course_registrations cr ON r.registration_id = cr.registration_id WHERE cr.student_id = :student_id AND r.grade_point > 0) as cgpa,
        (SELECT COUNT(*) FROM results r JOIN course_registrations cr ON r.registration_id = cr.registration_id WHERE cr.student_id = :student_id AND r.total_score >= 40) as passed_courses,
        (SELECT COUNT(*) FROM results r JOIN course_registrations cr ON r.registration_id = cr.registration_id WHERE cr.student_id = :student_id AND r.total_score < 40 AND r.total_score > 0) as failed_courses
    ");
    $db_stats->bind(':student_id', $student_id);
    $general_stats = $db_stats->single();

    if ($general_stats) {
        $stats['total_courses'] = $general_stats['total_courses'];
        $stats['cgpa'] = $general_stats['cgpa'];
        $stats['passed_courses'] = $general_stats['passed_courses'];
        $stats['failed_courses'] = $general_stats['failed_courses'];
    }
} catch (PDOException $e) {
    // Log error but don't crash the page
    error_log("Error fetching general stats for student_id: $student_id - " . $e->getMessage());
}


// 5. Fetch current semester data ONLY IF the academic period is valid.
// This is the check that prevents the fatal error.
if ($current_session && isset($current_session['session_id']) && $current_semester && isset($current_semester['semester_id'])) {
    try {
        // Query for the number of courses in the current semester
        $db_current_count = new Database();
        $db_current_count->query("SELECT COUNT(*) as count FROM course_registrations WHERE student_id = :student_id AND session_id = :session_id AND semester_id = :semester_id");
        $db_current_count->bind(':student_id', $student_id);
        $db_current_count->bind(':session_id', $current_session['session_id']);
        $db_current_count->bind(':semester_id', $current_semester['semester_id']);
        $current_course_count_result = $db_current_count->single();
        if ($current_course_count_result) {
            $stats['current_courses'] = $current_course_count_result['count'];
        }

        // Query for the list of courses in the current semester
        $db_current_list = new Database();
        $db_current_list->query("SELECT c.course_code, c.course_title, c.credit_units, r.ca_score, r.exam_score, r.total_score, r.grade
                                 FROM course_registrations cr
                                 JOIN courses c ON cr.course_id = c.course_id
                                 LEFT JOIN results r ON cr.registration_id = r.registration_id
                                 WHERE cr.student_id = :student_id AND cr.session_id = :session_id AND cr.semester_id = :semester_id
                                 ORDER BY c.course_code");
        $db_current_list->bind(':student_id', $student_id);
        $db_current_list->bind(':session_id', $current_session['session_id']);
        $db_current_list->bind(':semester_id', $current_semester['semester_id']);
        $current_courses = $db_current_list->resultSet();

    } catch (PDOException $e) {
        // Log error and fall back to empty array
        error_log("Error fetching current semester courses for student_id: $student_id - " . $e->getMessage());
        $current_courses = [];
    }
}

// 6. Fetch recent results. This query is also independent of the current academic period.
try {
    $db_recent = new Database();
    $db_recent->query("SELECT c.course_code, c.course_title, r.total_score, r.grade
                       FROM results r
                       JOIN course_registrations cr ON r.registration_id = cr.registration_id
                       JOIN courses c ON cr.course_id = c.course_id
                       WHERE cr.student_id = :student_id
                       ORDER BY r.created_at DESC
                       LIMIT 5");
    $db_recent->bind(':student_id', $student_id);
    $recent_results = $db_recent->resultSet();
} catch (PDOException $e) {
    // Log error and fall back to empty array
    error_log("Error fetching recent results for student_id: $student_id - " . $e->getMessage());
    $recent_results = [];
}


// 7. Calculate classification based on the fetched CGPA.
$cgpa = $stats['cgpa'] ?? 0.00;
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

// --- End of Rewritten Data Fetching Logic ---

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
                            <h4 class="mb-1">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h4>
                            <p class="mb-0">
                                <i class="bi bi-person-badge me-2"></i><?php echo htmlspecialchars($_SESSION['matric_number'] ?? 'N/A'); ?> |
                                <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($_SESSION['department_name'] ?? 'N/A'); ?> |
                                <i class="bi bi-mortarboard me-2"></i><?php echo htmlspecialchars($_SESSION['level_name'] ?? 'N/A'); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="text-white-50">
                                <?php if ($current_session && $current_semester): ?>
                                    <small>Current Session: <strong><?php echo htmlspecialchars($current_session['session_name']); ?></strong></small><br>
                                    <small>Current Semester: <strong><?php echo htmlspecialchars($current_semester['semester_name']); ?></strong></small>
                                <?php else: ?>
                                    <small>Academic period not set</small>
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
                    <small class="text-success"><?php echo htmlspecialchars($classification); ?></small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-6 text-info mb-2">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <h3 class="mb-1"><?php echo htmlspecialchars($stats['current_courses']); ?></h3>
                    <p class="text-muted mb-0">Current Courses</p>
                    <small class="text-muted"><?php echo htmlspecialchars($stats['total_courses']); ?> total registered</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-6 text-success mb-2">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <h3 class="mb-1"><?php echo htmlspecialchars($stats['passed_courses']); ?></h3>
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
                    <h3 class="mb-1"><?php echo htmlspecialchars($stats['failed_courses']); ?></h3>
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
                            <p class="text-muted">You haven't registered for any courses this semester, or the academic period is not set.</p>
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
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                                            <td><?php echo htmlspecialchars($course['credit_units']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($course['ca_score'] ?? '-'); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($course['exam_score'] ?? '-'); ?>
                                            </td>
                                            <td>
                                                <?php if (isset($course['total_score'])): ?>
                                                    <span class="badge bg-<?php echo $course['total_score'] >= 40 ? 'success' : 'danger'; ?>">
                                                        <?php echo htmlspecialchars($course['total_score']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($course['grade'])): ?>
                                                    <span class="badge bg-<?php echo $course['total_score'] >= 40 ? 'success' : 'danger'; ?>">
                                                        <?php echo htmlspecialchars($course['grade']); ?>
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
                                    <h6 class="mb-1"><?php echo htmlspecialchars($result['course_code']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($result['session_name'] ?? 'N/A'); ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo ($result['total_score'] ?? 0) >= 40 ? 'success' : 'danger'; ?>">
                                        <?php echo htmlspecialchars($result['grade'] ?? 'N/A'); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($result['total_score'] ?? '--'); ?>%</small>
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
