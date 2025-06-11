<?php
$page_title = "Results Management";
$breadcrumb = "Results > View Results";

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
$department_id = isset($_GET['department_id']) ? clean_input($_GET['department_id']) : '';
$level_id = isset($_GET['level_id']) ? clean_input($_GET['level_id']) : '';
$session_id = isset($_GET['session_id']) ? clean_input($_GET['session_id']) : '';
$semester_id = isset($_GET['semester_id']) ? clean_input($_GET['semester_id']) : '';
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

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

// Get departments for filter
$db->query("SELECT * FROM departments WHERE institution_id = :institution_id ORDER BY department_name");
$db->bind(':institution_id', $institution_id);
$departments = $db->resultSet();

// Get levels for filter
$db->query("SELECT * FROM levels WHERE institution_id = :institution_id ORDER BY level_name");
$db->bind(':institution_id', $institution_id);
$levels = $db->resultSet();

// Get sessions for filter (try both tables)
$sessions = get_all_sessions();

// Get semesters for filter
$db->query("SELECT * FROM semesters WHERE institution_id = :institution_id ORDER BY semester_name");
$db->bind(':institution_id', $institution_id);
$semesters = $db->resultSet();

// Build query with filters
$query = "SELECT DISTINCT s.student_id, s.first_name, s.last_name, s.matric_number, 
          d.department_name, l.level_name
          FROM students s
          JOIN departments d ON s.department_id = d.department_id
          JOIN levels l ON s.level_id = l.level_id
          WHERE s.institution_id = :institution_id";

$params = [':institution_id' => $institution_id];

if (!empty($department_id)) {
    $query .= " AND s.department_id = :department_id";
    $params[':department_id'] = $department_id;
}

if (!empty($level_id)) {
    $query .= " AND s.level_id = :level_id";
    $params[':level_id'] = $level_id;
}

if (!empty($search)) {
    $query .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.matric_number LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY s.first_name, s.last_name";

$db->query($query);
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

$students = $db->resultSet();

// For each student, get their course and result statistics
foreach ($students as &$student) {
    // Get total courses for this student in the selected session/semester
    $course_query = "SELECT COUNT(*) as total_courses FROM course_registrations cr 
                     WHERE cr.student_id = :student_id";
    $course_params = [':student_id' => $student['student_id']];
    
    if (!empty($session_id)) {
        $course_query .= " AND cr.session_id = :session_id";
        $course_params[':session_id'] = $session_id;
    }
    
    if (!empty($semester_id)) {
        $course_query .= " AND cr.semester_id = :semester_id";
        $course_params[':semester_id'] = $semester_id;
    }
    
    $db->query($course_query);
    foreach ($course_params as $param => $value) {
        $db->bind($param, $value);
    }
    $course_stats = $db->single();
    $student['total_courses'] = $course_stats['total_courses'] ?? 0;
    
    // Get completed results count
    $result_query = "SELECT COUNT(*) as completed_courses, AVG(r.score) as avg_score 
                     FROM course_registrations cr 
                     LEFT JOIN results r ON cr.registration_id = r.registration_id
                     WHERE cr.student_id = :student_id AND r.result_id IS NOT NULL";
    $result_params = [':student_id' => $student['student_id']];
    
    if (!empty($session_id)) {
        $result_query .= " AND cr.session_id = :session_id";
        $result_params[':session_id'] = $session_id;
    }
    
    if (!empty($semester_id)) {
        $result_query .= " AND cr.semester_id = :semester_id";
        $result_params[':semester_id'] = $semester_id;
    }
    
    $db->query($result_query);
    foreach ($result_params as $param => $value) {
        $db->bind($param, $value);
    }
    $result_stats = $db->single();
    $student['completed_courses'] = $result_stats['completed_courses'] ?? 0;
    $student['avg_score'] = $result_stats['avg_score'] ? round($result_stats['avg_score'], 1) : null;
    
    // Get GPA if available
    if (!empty($session_id) && !empty($semester_id)) {
        $db->query("SELECT gpa FROM semester_gpas 
                    WHERE student_id = :student_id 
                    AND session_id = :session_id 
                    AND semester_id = :semester_id");
        $db->bind(':student_id', $student['student_id']);
        $db->bind(':session_id', $session_id);
        $db->bind(':semester_id', $semester_id);
        $gpa_record = $db->single();
        $student['gpa'] = $gpa_record['gpa'] ?? null;
    } else {
        $student['gpa'] = null;
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-funnel me-2"></i>Filter Results
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department_id">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" 
                                        <?php echo ($department_id == $dept['department_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label">Level</label>
                        <select class="form-select" name="level_id">
                            <option value="">All Levels</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?php echo $level['level_id']; ?>" 
                                        <?php echo ($level_id == $level['level_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($level['level_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 col-lg-3">
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
                    
                    <div class="col-md-6 col-lg-3">
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
                    
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Name or Matric Number" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-filter me-2"></i>Apply Filters
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                                <i class="bi bi-x-circle me-2"></i>Clear Filters
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <a href="entry.php" class="btn btn-success">
                                <i class="bi bi-pencil-square me-2"></i>Enter Results
                            </a>
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
                <i class="bi bi-file-earmark-text me-2"></i>Student Results
            </h5>
            
            <?php if (!empty($session_id) && !empty($semester_id)): ?>
                <?php
                // Get session and semester names
                $session_name = '';
                $semester_name = '';
                
                foreach ($sessions as $session) {
                    if ($session['session_id'] == $session_id) {
                        $session_name = $session['session_name'];
                        break;
                    }
                }
                
                foreach ($semesters as $semester) {
                    if ($semester['semester_id'] == $semester_id) {
                        $semester_name = $semester['semester_name'];
                        break;
                    }
                }
                ?>
                <span class="badge bg-info fs-6">
                    <?php echo htmlspecialchars($session_name . ' - ' . $semester_name); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <div class="card-body">
            <?php if (empty($students)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    No students found for the selected filters.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Matric Number</th>
                                <th>Student Name</th>
                                <th>Department</th>
                                <th>Level</th>
                                <th class="text-center">Courses</th>
                                <th class="text-center">Avg. Score</th>
                                <th class="text-center">GPA</th>
                                <th class="text-center">Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
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
                                    <td><?php echo htmlspecialchars($student['department_name']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($student['level_name']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">
                                            <?php echo $student['completed_courses']; ?>/<?php echo $student['total_courses']; ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($student['avg_score']): ?>
                                            <span class="badge bg-<?php echo get_score_color($student['avg_score']); ?>">
                                                <?php echo $student['avg_score']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($student['gpa']): ?>
                                            <span class="badge bg-<?php echo get_gpa_color($student['gpa']); ?>">
                                                <?php echo number_format($student['gpa'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($student['completed_courses'] == 0): ?>
                                            <span class="badge bg-danger">No Results</span>
                                        <?php elseif ($student['completed_courses'] < $student['total_courses']): ?>
                                            <span class="badge bg-warning">Incomplete</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Complete</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="student_results.php?student_id=<?php echo $student['student_id']; ?>&session_id=<?php echo $session_id; ?>&semester_id=<?php echo $semester_id; ?>" 
                                               class="btn btn-outline-primary" title="View Results">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="entry.php?student_id=<?php echo $student['student_id']; ?>&session_id=<?php echo $session_id; ?>&semester_id=<?php echo $semester_id; ?>" 
                                               class="btn btn-outline-success" title="Enter/Edit Results">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="transcript.php?student_id=<?php echo $student['student_id']; ?>" 
                                               class="btn btn-outline-info" title="Generate Transcript">
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            </a>
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

<script>
// Function to clear filters
function clearFilters() {
    window.location.href = 'index.php';
}
</script>

<?php include_once '../includes/footer.php'; ?>