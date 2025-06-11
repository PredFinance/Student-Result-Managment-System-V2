<?php
$page_title = "Course Students";
$breadcrumb = "Courses > Course Students";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../includes/gpa_functions.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Get course ID
$course_id = isset($_GET['course_id']) ? clean_input($_GET['course_id']) : '';

if (empty($course_id)) {
    set_flash_message('danger', 'Course ID is required');
    redirect(ADMIN_URL . '/courses/index.php');
}

// Initialize database connection
$db = new Database();
$institution_id = get_institution_id();

// Get course information
$db->query("SELECT c.*, d.department_name 
            FROM courses c
            JOIN departments d ON c.department_id = d.department_id
            WHERE c.course_id = :course_id AND c.institution_id = :institution_id");
$db->bind(':course_id', $course_id);
$db->bind(':institution_id', $institution_id);
$course = $db->single();

if (!$course) {
    set_flash_message('danger', 'Course not found');
    redirect(ADMIN_URL . '/courses/index.php');
}

// Get filter parameters
$session_filter = isset($_GET['session']) ? clean_input($_GET['session']) : '';
$semester_filter = isset($_GET['semester']) ? clean_input($_GET['semester']) : '';

// Get sessions and semesters for filters - FIXED: Use both academic_sessions and sessions tables
$sessions = [];
// Try academic_sessions first
$db->query("SELECT DISTINCT sess.session_id, sess.session_name
            FROM course_registrations cr
            JOIN academic_sessions sess ON cr.session_id = sess.session_id
            WHERE cr.course_id = :course_id
            ORDER BY sess.session_name DESC");
$db->bind(':course_id', $course_id);
$sessions = $db->resultSet();

// If no results, try sessions table
if (empty($sessions)) {
    $db->query("SELECT DISTINCT sess.session_id, sess.session_name
                FROM course_registrations cr
                JOIN sessions sess ON cr.session_id = sess.session_id
                WHERE cr.course_id = :course_id
                ORDER BY sess.session_name DESC");
    $db->bind(':course_id', $course_id);
    $sessions = $db->resultSet();
}

$db->query("SELECT DISTINCT sem.semester_id, sem.semester_name
            FROM course_registrations cr
            JOIN semesters sem ON cr.semester_id = sem.semester_id
            WHERE cr.course_id = :course_id
            ORDER BY sem.semester_id");
$db->bind(':course_id', $course_id);
$semesters = $db->resultSet();

// Build query with filters - FIXED: Use proper table joins
$query = "SELECT cr.*, s.first_name, s.last_name, s.matric_number,
          sess.session_name, sem.semester_name, l.level_name,
          r.score, r.grade, r.grade_point
          FROM course_registrations cr
          JOIN students s ON cr.student_id = s.student_id
          LEFT JOIN academic_sessions sess ON cr.session_id = sess.session_id
          LEFT JOIN sessions sess2 ON (cr.session_id = sess2.session_id AND sess.session_id IS NULL)
          JOIN semesters sem ON cr.semester_id = sem.semester_id
          JOIN levels l ON s.level_id = l.level_id
          LEFT JOIN results r ON cr.registration_id = r.registration_id
          WHERE cr.course_id = :course_id";

$params = [':course_id' => $course_id];

if (!empty($session_filter)) {
    $query .= " AND cr.session_id = :session_filter";
    $params[':session_filter'] = $session_filter;
}

if (!empty($semester_filter)) {
    $query .= " AND cr.semester_id = :semester_filter";
    $params[':semester_filter'] = $semester_filter;
}

$query .= " ORDER BY COALESCE(sess.session_name, sess2.session_name) DESC, sem.semester_id, s.first_name, s.last_name";

$db->query($query);
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

$students = $db->resultSet();

// Helper function for grade colors
function get_grade_color($grade) {
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

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <!-- Course Info Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-1"><?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_title']); ?></h4>
                            <p class="text-muted mb-0">
                                <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($course['department_name']); ?>
                                <span class="ms-3"><i class="bi bi-award me-2"></i><?php echo $course['credit_units']; ?> Credit Units</span>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="view.php?id=<?php echo $course['course_id']; ?>" class="btn btn-outline-primary me-2">
                                <i class="bi bi-eye me-2"></i>View Course
                            </a>
                            <a href="../results/entry.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-primary">
                                <i class="bi bi-pencil-square me-2"></i>Enter Results
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Students Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2"></i>Registered Students
                    </h5>
                    <span class="badge bg-primary fs-6"><?php echo count($students); ?> Students</span>
                </div>
                
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" action="" class="mb-4">
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Session</label>
                                <select class="form-select" name="session">
                                    <option value="">All Sessions</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['session_id']; ?>" 
                                                <?php echo ($session_filter == $session['session_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($session['session_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Semester</label>
                                <select class="form-select" name="semester">
                                    <option value="">All Semesters</option>
                                    <?php foreach ($semesters as $semester): ?>
                                        <option value="<?php echo $semester['semester_id']; ?>" 
                                                <?php echo ($semester_filter == $semester['semester_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($semester['semester_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-funnel"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Students Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Matric Number</th>
                                    <th>Student Name</th>
                                    <th>Level</th>
                                    <th>Session/Semester</th>
                                    <th>Score</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                                <?php if (!empty($session_filter) || !empty($semester_filter)): ?>
                                                    No students found for the selected filters
                                                <?php else: ?>
                                                    No students registered for this course yet
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($student['matric_number']); ?></span>
                                            </td>
                                            <td>
                                                <a href="../students/view.php?id=<?php echo $student['student_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($student['level_name']); ?></span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars($student['session_name']); ?><br>
                                                    <?php echo htmlspecialchars($student['semester_name']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($student['score'] !== null): ?>
                                                    <span class="badge bg-secondary"><?php echo $student['score']; ?>/100</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($student['grade']): ?>
                                                    <span class="badge bg-<?php echo get_grade_color($student['grade']); ?>">
                                                        <?php echo $student['grade']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($student['score'] !== null): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="../students/view.php?id=<?php echo $student['student_id']; ?>" 
                                                       class="btn btn-outline-info" title="View Student">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($student['score'] === null): ?>
                                                        <a href="../results/entry.php?course_id=<?php echo $course['course_id']; ?>&student_id=<?php echo $student['student_id']; ?>" 
                                                           class="btn btn-outline-primary" title="Enter Result">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="../results/entry.php?course_id=<?php echo $course['course_id']; ?>&student_id=<?php echo $student['student_id']; ?>" 
                                                           class="btn btn-outline-success" title="Edit Result">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Courses
                </a>
                <div>
                    <a href="view.php?id=<?php echo $course['course_id']; ?>" class="btn btn-outline-primary me-2">
                        <i class="bi bi-eye me-2"></i>Course Details
                    </a>
                    <a href="../results/entry.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil-square me-2"></i>Enter Results
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>