<?php
$page_title = "My Courses";
$breadcrumb = "My Courses";

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is student
if (!is_logged_in() || $_SESSION['role'] != 'student') {
    set_flash_message('danger', 'You must be logged in as a student to access this page');
    redirect(BASE_URL);
}

$db = new Database();
$student_id = $_SESSION['user_id'];
$institution_id = get_institution_id();

// Get student information
$db->query("SELECT s.*, d.department_name, l.level_name
            FROM students s
            JOIN departments d ON s.department_id = d.department_id
            JOIN levels l ON s.level_id = l.level_id
            WHERE s.student_id = :student_id");
$db->bind(':student_id', $student_id);
$student_info = $db->single();

// Get current session and semester
$current_session = get_current_session($institution_id);
$current_semester = get_current_semester($institution_id);

// Get selected session and semester from URL parameters
$selected_session_id = isset($_GET['session_id']) ? clean_input($_GET['session_id']) : ($current_session ? $current_session['session_id'] : '');
$selected_semester_id = isset($_GET['semester_id']) ? clean_input($_GET['semester_id']) : ($current_semester ? $current_semester['semester_id'] : '');

// Get all sessions and semesters for dropdown
$all_sessions = get_all_sessions($institution_id);
$all_semesters = get_all_semesters($institution_id);

// Get registered courses for selected session/semester
$registered_courses = [];
if ($selected_session_id && $selected_semester_id) {
    $registered_courses = get_student_registered_courses($student_id, $selected_session_id, $selected_semester_id);
}

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-journal-bookmark me-2"></i>My Courses
                    </h5>
                </div>
                
                <div class="card-body">
                    <!-- Filter Section -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label for="session_filter" class="form-label">Academic Session</label>
                            <select class="form-select" id="session_filter" onchange="filterCourses()">
                                <option value="">Select Session</option>
                                <?php foreach ($all_sessions as $session): ?>
                                    <option value="<?php echo $session['session_id']; ?>" 
                                            <?php echo ($session['session_id'] == $selected_session_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($session['session_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="semester_filter" class="form-label">Semester</label>
                            <select class="form-select" id="semester_filter" onchange="filterCourses()">
                                <option value="">Select Semester</option>
                                <?php foreach ($all_semesters as $semester): ?>
                                    <option value="<?php echo $semester['semester_id']; ?>" 
                                            <?php echo ($semester['semester_id'] == $selected_semester_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($semester['semester_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quick Actions</label>
                            <div class="d-flex gap-2">
                                <a href="register-courses.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Register Courses
                                </a>
                                <button class="btn btn-outline-secondary" onclick="printCourses()">
                                    <i class="bi bi-printer me-2"></i>Print
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Courses Display -->
                    <?php if (empty($registered_courses)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-journal-x display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No Courses Found</h4>
                            <?php if ($selected_session_id && $selected_semester_id): ?>
                                <p class="text-muted">You haven't registered for any courses in the selected session/semester.</p>
                            <?php else: ?>
                                <p class="text-muted">Please select a session and semester to view your courses.</p>
                            <?php endif; ?>
                            <div class="mt-4">
                                <a href="register-courses.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Register for Courses
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Summary Cards -->
                        <?php 
                        $total_units = array_sum(array_column($registered_courses, 'credit_units'));
                        $completed_courses = array_filter($registered_courses, function($course) { 
                            return $course['total_score'] !== null; 
                        });
                        $pending_courses = array_filter($registered_courses, function($course) { 
                            return $course['total_score'] === null; 
                        });
                        ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h4><?php echo count($registered_courses); ?></h4>
                                        <small>Total Courses</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h4><?php echo $total_units; ?></h4>
                                        <small>Credit Units</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h4><?php echo count($completed_courses); ?></h4>
                                        <small>Completed</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h4><?php echo count($pending_courses); ?></h4>
                                        <small>Pending Results</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Courses Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="coursesTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Title</th>
                                        <th>Credit Units</th>
                                        <th>CA Score</th>
                                        <th>Exam Score</th>
                                        <th>Total Score</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                        <th>Registration Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registered_courses as $course): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $course['credit_units']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($course['ca_score'] !== null): ?>
                                                    <span class="badge bg-info"><?php echo $course['ca_score']; ?>/30</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($course['exam_score'] !== null): ?>
                                                    <span class="badge bg-info"><?php echo $course['exam_score']; ?>/70</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($course['total_score'] !== null): ?>
                                                    <span class="badge bg-<?php echo getGradeColor($course['grade']); ?>">
                                                        <?php echo $course['total_score']; ?>/100
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($course['grade']): ?>
                                                    <span class="badge bg-<?php echo getGradeColor($course['grade']); ?> fs-6">
                                                        <?php echo $course['grade']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($course['total_score'] !== null): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">In Progress</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo format_date($course['registration_date']); ?></small>
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
    </div>
</div>

<script>
function filterCourses() {
    const sessionId = document.getElementById('session_filter').value;
    const semesterId = document.getElementById('semester_filter').value;
    
    if (sessionId && semesterId) {
        window.location.href = `my-courses.php?session_id=${sessionId}&semester_id=${semesterId}`;
    }
}

function printCourses() {
    window.print();
}

// Helper function for grade colors
function getGradeColor(grade) {
    switch(grade) {
        case 'A': return 'success';
        case 'B': return 'primary';
        case 'C': return 'info';
        case 'D': return 'warning';
        case 'E': return 'secondary';
        case 'F': return 'danger';
        default: return 'secondary';
    }
}
</script>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
}
</style>

<?php 
// Helper function for grade colors
function getGradeColor($grade) {
    switch($grade) {
        case 'A': return 'success';
        case 'B': return 'primary';
        case 'C': return 'info';
        case 'D': return 'warning';
        case 'E': return 'secondary';
        case 'F': return 'danger';
        default: return 'secondary';
    }
}

include_once 'includes/footer.php'; 
?>
