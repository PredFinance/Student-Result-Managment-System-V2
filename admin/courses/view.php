<?php
$page_title = "View Course";
$breadcrumb = "Courses > View Course";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Get course ID
$course_id = isset($_GET['id']) ? clean_input($_GET['id']) : '';

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

// Get course registrations with results
$db->query("SELECT cr.*, s.first_name, s.last_name, s.matric_number,
            sess.session_name, sem.semester_name,
            r.ca_score, r.exam_score, r.total_score, r.grade, r.grade_point
            FROM course_registrations cr
            JOIN students s ON cr.student_id = s.student_id
            JOIN sessions sess ON cr.session_id = sess.session_id
            JOIN semesters sem ON cr.semester_id = sem.semester_id
            LEFT JOIN results r ON cr.registration_id = r.registration_id
            WHERE cr.course_id = :course_id
            ORDER BY sess.session_name DESC, sem.semester_order, s.first_name, s.last_name");
$db->bind(':course_id', $course_id);
$registrations = $db->resultSet();

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
        <div class="col-lg-4">
            <!-- Course Details Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-journal-bookmark me-2"></i>Course Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 1.5rem; font-weight: 600;">
                            <?php echo htmlspecialchars($course['course_code']); ?>
                        </div>
                        <h4 class="mt-3"><?php echo htmlspecialchars($course['course_title']); ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($course['department_name']); ?></p>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h5 class="text-primary"><?php echo $course['credit_units']; ?></h5>
                                <small class="text-muted">Credit Units</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h5 class="text-success"><?php echo count($registrations); ?></h5>
                            <small class="text-muted">Students</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Course Statistics -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Statistics</h6>
                </div>
                <div class="card-body">
                    <?php 
                    $completed_results = array_filter($registrations, function($r) { return $r['total_score'] !== null; });
                    $pending_results = array_filter($registrations, function($r) { return $r['total_score'] === null; });
                    ?>
                    
                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Total Registrations:</strong></div>
                        <div class="col-sm-6"><?php echo count($registrations); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Completed:</strong></div>
                        <div class="col-sm-6">
                            <span class="badge bg-success"><?php echo count($completed_results); ?></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Pending:</strong></div>
                        <div class="col-sm-6">
                            <span class="badge bg-warning"><?php echo count($pending_results); ?></span>
                        </div>
                    </div>
                    
                    <?php if (!empty($completed_results)): ?>
                        <?php 
                        $scores = array_column($completed_results, 'total_score');
                        $average = array_sum($scores) / count($scores);
                        ?>
                        <div class="row mb-3">
                            <div class="col-sm-6"><strong>Average Score:</strong></div>
                            <div class="col-sm-6"><?php echo round($average, 1); ?>%</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-6"><strong>Highest Score:</strong></div>
                            <div class="col-sm-6"><?php echo max($scores); ?>%</div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-sm-6"><strong>Lowest Score:</strong></div>
                            <div class="col-sm-6"><?php echo min($scores); ?>%</div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-sm-6"><strong>Created:</strong></div>
                        <div class="col-sm-6"><?php echo format_date($course['created_at']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Student Registrations -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2"></i>Student Registrations
                    </h5>
                    <div>
                        <a href="../results/entry.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-pencil-square me-2"></i>Enter Results
                        </a>
                        <a href="edit.php?id=<?php echo $course['course_id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil me-2"></i>Edit Course
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (empty($registrations)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-people-fill display-4 text-muted"></i>
                            <h5 class="text-muted mt-3">No Student Registrations</h5>
                            <p class="text-muted">No students have registered for this course yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Matric Number</th>
                                        <th>Student Name</th>
                                        <th>Session/Semester</th>
                                        <th>CA Score</th>
                                        <th>Exam Score</th>
                                        <th>Total Score</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $reg): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($reg['matric_number']); ?></span>
                                            </td>
                                            <td>
                                                <a href="../students/view.php?id=<?php echo $reg['student_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($reg['first_name'] . ' ' . $reg['last_name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars($reg['session_name']); ?><br>
                                                    <?php echo htmlspecialchars($reg['semester_name']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($reg['ca_score'] !== null): ?>
                                                    <span class="badge bg-secondary"><?php echo $reg['ca_score']; ?>/30</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($reg['exam_score'] !== null): ?>
                                                    <span class="badge bg-secondary"><?php echo $reg['exam_score']; ?>/70</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($reg['total_score'] !== null): ?>
                                                    <span class="badge bg-<?php echo get_grade_color($reg['grade']); ?>">
                                                        <?php echo $reg['total_score']; ?>/100
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($reg['grade']): ?>
                                                    <span class="badge bg-<?php echo get_grade_color($reg['grade']); ?> fs-6">
                                                        <?php echo $reg['grade']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($reg['total_score'] !== null): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="../students/view.php?id=<?php echo $reg['student_id']; ?>" 
                                                       class="btn btn-outline-info" title="View Student">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($reg['total_score'] === null): ?>
                                                        <a href="../results/entry.php?course_id=<?php echo $course['course_id']; ?>&student_id=<?php echo $reg['student_id']; ?>" 
                                                           class="btn btn-outline-primary" title="Enter Result">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
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
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Courses
                </a>
                <div>
                    <a href="edit.php?id=<?php echo $course['course_id']; ?>" class="btn btn-primary me-2">
                        <i class="bi bi-pencil me-2"></i>Edit Course
                    </a>
                    <a href="../results/entry.php?course_id=<?php echo $course['course_id']; ?>" class="btn btn-success">
                        <i class="bi bi-pencil-square me-2"></i>Enter Results
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
