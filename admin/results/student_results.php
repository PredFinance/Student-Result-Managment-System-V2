<?php
$page_title = "Student Results";
$breadcrumb = "Results > Student Results";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../includes/gpa_functions.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Get student ID
$student_id = isset($_GET['student_id']) ? clean_input($_GET['student_id']) : '';

if (empty($student_id)) {
    set_flash_message('danger', 'Student ID is required');
    redirect(ADMIN_URL . '/results/index.php');
}

// Initialize database connection
$db = new Database();
$institution_id = get_institution_id();

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
    set_flash_message('danger', 'Student not found');
    redirect(ADMIN_URL . '/results/index.php');
}

// Get filter parameters
$session_id = isset($_GET['session_id']) ? clean_input($_GET['session_id']) : '';
$semester_id = isset($_GET['semester_id']) ? clean_input($_GET['semester_id']) : '';

// Get sessions for filter
$db->query("SELECT DISTINCT sess.session_id, sess.session_name
            FROM course_registrations cr
            JOIN sessions sess ON cr.session_id = sess.session_id
            WHERE cr.student_id = :student_id
            ORDER BY sess.session_name DESC");
$db->bind(':student_id', $student_id);
$sessions = $db->resultSet();

// Get semesters for filter
$db->query("SELECT DISTINCT sem.semester_id, sem.semester_name
            FROM course_registrations cr
            JOIN semesters sem ON cr.semester_id = sem.semester_id
            WHERE cr.student_id = :student_id
            ORDER BY sem.semester_id");
$db->bind(':student_id', $student_id);
$semesters = $db->resultSet();

// Build query with filters
$query = "SELECT cr.registration_id, c.course_code, c.course_title, c.credit_units,
          sess.session_name, sem.semester_name,
          r.ca_score, r.exam_score, r.total_score, r.grade, r.grade_point, r.remark
          FROM course_registrations cr
          JOIN courses c ON cr.course_id = c.course_id
          JOIN sessions sess ON cr.session_id = sess.session_id
          JOIN semesters sem ON cr.semester_id = sem.semester_id
          LEFT JOIN results r ON cr.registration_id = r.registration_id
          WHERE cr.student_id = :student_id";

$params = [':student_id' => $student_id];

if (!empty($session_id)) {
    $query .= " AND cr.session_id = :session_id";
    $params[':session_id'] = $session_id;
}

if (!empty($semester_id)) {
    $query .= " AND cr.semester_id = :semester_id";
    $params[':semester_id'] = $semester_id;
}

$query .= " ORDER BY sess.session_name DESC, sem.semester_id, c.course_code";

$db->query($query);
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

$results = $db->resultSet();

// Get GPA information
$gpa_info = [];
if (!empty($session_id) && !empty($semester_id)) {
    $db->query("SELECT * FROM semester_gpas 
                WHERE student_id = :student_id 
                AND session_id = :session_id 
                AND semester_id = :semester_id");
    $db->bind(':student_id', $student_id);
    $db->bind(':session_id', $session_id);
    $db->bind(':semester_id', $semester_id);
    $gpa_info = $db->single();
}

// Get cumulative GPA
$db->query("SELECT * FROM cumulative_gpas WHERE student_id = :student_id");
$db->bind(':student_id', $student_id);
$cgpa_info = $db->single();

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Student Info Header -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-1"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                    <p class="text-muted mb-0">
                        <span class="badge bg-primary me-2"><?php echo htmlspecialchars($student['matric_number']); ?></span>
                        <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($student['department_name']); ?>
                        <span class="ms-3"><i class="bi bi-bar-chart me-1"></i><?php echo htmlspecialchars($student['level_name']); ?></span>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="../students/view.php?id=<?php echo $student['student_id']; ?>" class="btn btn-outline-primary me-2">
                        <i class="bi bi-person me-2"></i>Student Profile
                    </a>
                    <a href="transcript.php?student_id=<?php echo $student['student_id']; ?>" class="btn btn-primary">
                        <i class="bi bi-file-earmark-pdf me-2"></i>Generate Transcript
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-funnel me-2"></i>Filter Results
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="filterForm">
                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Session</label>
                        <select class="form-select" name="session_id">
                            <option value="">All Sessions</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?php echo $session['session_id']; ?>" 
                                        <?php echo ($session_id == $session['session_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($session['session_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-5">
                        <label class="form-label">Semester</label>
                        <select class="form-select" name="semester_id">
                            <option value="">All Semesters</option>
                            <?php foreach ($semesters as $semester): ?>
                                <option value="<?php echo $semester['semester_id']; ?>" 
                                        <?php echo ($semester_id == $semester['semester_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($semester['semester_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-filter me-2"></i>Filter
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Results Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bi bi-file-earmark-text me-2"></i>Course Results
            </h5>
            
            <?php if (!empty($session_id) && !empty($semester_id)): ?>
                <?php
                // Get session and semester names
                $db->query("SELECT session_name FROM sessions WHERE session_id = :session_id");
                $db->bind(':session_id', $session_id);
                $session_name = $db->single()['session_name'] ?? '';
                
                $db->query("SELECT semester_name FROM semesters WHERE semester_id = :semester_id");
                $db->bind(':semester_id', $semester_id);
                $semester_name = $db->single()['semester_name'] ?? '';
                ?>
                <span class="badge bg-info fs-6">
                    <?php echo htmlspecialchars($session_name . ' - ' . $semester_name); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <div class="card-body">
            <?php if (empty($results)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    No results found for the selected filters.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th class="text-center">Credit Units</th>
                                <th class="text-center">CA Score (40)</th>
                                <th class="text-center">Exam Score (60)</th>
                                <th class="text-center">Total Score (100)</th>
                                <th class="text-center">Grade</th>
                                <th class="text-center">Grade Point</th>
                                <th>Remark</th>
                                <th>Session/Semester</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_credit_units = 0;
                            $total_weighted_points = 0;
                            $completed_courses = 0;
                            
                            foreach ($results as $result): 
                                $has_result = isset($result['total_score']);
                                
                                if ($has_result) {
                                    $total_credit_units += $result['credit_units'];
                                    $total_weighted_points += ($result['grade_point'] * $result['credit_units']);
                                    $completed_courses++;
                                }
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($result['course_code']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['course_title']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-info"><?php echo $result['credit_units']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($has_result): ?>
                                            <?php echo $result['ca_score']; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($has_result): ?>
                                            <?php echo $result['exam_score']; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($has_result): ?>
                                            <span class="badge bg-<?php echo get_score_color($result['total_score']); ?>">
                                                <?php echo $result['total_score']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($has_result): ?>
                                            <span class="badge bg-<?php echo get_grade_color($result['grade']); ?>">
                                                <?php echo $result['grade']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($has_result): ?>
                                            <?php echo $result['grade_point']; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($has_result): ?>
                                            <span class="badge bg-<?php echo get_remark_color($result['remark']); ?>">
                                                <?php echo $result['remark']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo htmlspecialchars($result['session_name']); ?><br>
                                            <?php echo htmlspecialchars($result['semester_name']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($has_result): ?>
                                                <a href="entry.php?student_id=<?php echo $student_id; ?>&session_id=<?php echo $session_id; ?>&semester_id=<?php echo $semester_id; ?>" 
                                                   class="btn btn-outline-success" title="Edit Result">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="entry.php?student_id=<?php echo $student_id; ?>&session_id=<?php echo $session_id; ?>&semester_id=<?php echo $semester_id; ?>" 
                                                   class="btn btn-outline-primary" title="Enter Result">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2">Total</th>
                                <th class="text-center"><?php echo $total_credit_units; ?></th>
                                <th colspan="4"></th>
                                <th class="text-center"><?php echo $total_weighted_points; ?></th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- GPA Summary -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <?php if (!empty($gpa_info)): ?>
                            <div class="card border-left-primary">
                                <div class="card-body">
                                    <h6 class="card-title">Semester GPA Summary</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Total Credit Units:</th>
                                                <td><?php echo $gpa_info['total_credit_units']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Total Grade Points:</th>
                                                <td><?php echo $gpa_info['total_grade_points']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>GPA:</th>
                                                <td>
                                                    <span class="badge bg-<?php echo get_gpa_color($gpa_info['gpa']); ?> fs-6">
                                                        <?php echo number_format($gpa_info['gpa'], 2); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if (!empty($cgpa_info)): ?>
                            <div class="card border-left-success">
                                <div class="card-body">
                                    <h6 class="card-title">Cumulative GPA Summary</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Total Credit Units:</th>
                                                <td><?php echo $cgpa_info['total_credit_units']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Total Grade Points:</th>
                                                <td><?php echo $cgpa_info['total_grade_points']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>CGPA:</th>
                                                <td>
                                                    <span class="badge bg-<?php echo get_gpa_color($cgpa_info['cgpa']); ?> fs-6">
                                                        <?php echo number_format($cgpa_info['cgpa'], 2); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Results
                </a>
                <div>
                    <a href="entry.php?student_id=<?php echo $student_id; ?>&session_id=<?php echo $session_id; ?>&semester_id=<?php echo $semester_id; ?>" class="btn btn-success me-2">
                        <i class="bi bi-pencil-square me-2"></i>Enter/Edit Results
                    </a>
                    <a href="transcript.php?student_id=<?php echo $student_id; ?>" class="btn btn-primary">
                        <i class="bi bi-file-earmark-pdf me-2"></i>Generate Transcript
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>