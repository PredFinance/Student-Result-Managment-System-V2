<?php
$page_title = "Results Management";
$breadcrumb = "Results > Manage Results";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../includes/gpa_functions.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

$db = new Database();
$institution_id = get_institution_id();

// Get current session and semester
$current_session = get_current_session($institution_id);
$current_semester = get_current_semester($institution_id);

// Get all sessions and semesters for filters
$sessions = get_sessions_for_gpa();
$semesters = get_semesters_for_gpa();

// Handle filters
$selected_session = isset($_GET['session_id']) ? clean_input($_GET['session_id']) : ($current_session['session_id'] ?? '');
$selected_semester = isset($_GET['semester_id']) ? clean_input($_GET['semester_id']) : ($current_semester['semester_id'] ?? '');
$selected_department = isset($_GET['department_id']) ? clean_input($_GET['department_id']) : '';
$selected_level = isset($_GET['level_id']) ? clean_input($_GET['level_id']) : '';

// Get departments and levels for filters
$db->query("SELECT * FROM departments WHERE institution_id = :institution_id ORDER BY department_name");
$db->bind(':institution_id', $institution_id);
$departments = $db->resultSet();

$db->query("SELECT * FROM levels WHERE institution_id = :institution_id ORDER BY level_name");
$db->bind(':institution_id', $institution_id);
$levels = $db->resultSet();

// Build query for results
$where_conditions = ["cr.institution_id = :institution_id"];
$params = [':institution_id' => $institution_id];

if ($selected_session) {
    $where_conditions[] = "cr.session_id = :session_id";
    $params[':session_id'] = $selected_session;
}

if ($selected_semester) {
    $where_conditions[] = "cr.semester_id = :semester_id";
    $params[':semester_id'] = $selected_semester;
}

if ($selected_department) {
    $where_conditions[] = "s.department_id = :department_id";
    $params[':department_id'] = $selected_department;
}

if ($selected_level) {
    $where_conditions[] = "s.level_id = :level_id";
    $params[':level_id'] = $selected_level;
}

$where_clause = implode(' AND ', $where_conditions);

// Get results with student and course information
$db->query("SELECT r.*, cr.registration_id, 
            s.student_id, s.matric_number, s.first_name, s.last_name,
            c.course_code, c.course_title, c.credit_units,
            sess.session_name, sem.semester_name,
            d.department_name, l.level_name
            FROM results r
            JOIN course_registrations cr ON r.registration_id = cr.registration_id
            JOIN students s ON cr.student_id = s.student_id
            JOIN courses c ON cr.course_id = c.course_id
            JOIN sessions sess ON cr.session_id = sess.session_id
            JOIN semesters sem ON cr.semester_id = sem.semester_id
            JOIN departments d ON s.department_id = d.department_id
            JOIN levels l ON s.level_id = l.level_id
            WHERE $where_clause
            ORDER BY s.matric_number, c.course_code");

foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

$results = $db->resultSet();

// Calculate statistics
$total_results = count($results);
$total_students = count(array_unique(array_column($results, 'student_id')));
$total_courses = count(array_unique(array_column($results, 'course_code')));

// Grade distribution
$grade_distribution = [];
foreach ($results as $result) {
    $grade = $result['grade'];
    $grade_distribution[$grade] = ($grade_distribution[$grade] ?? 0) + 1;
}

include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">
                                <i class="bi bi-clipboard-data text-primary me-2"></i>
                                Results Management
                            </h4>
                            <p class="text-muted mb-0">Manage and view student results</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="add.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Add Results
                            </a>
                            <a href="bulk-upload.php" class="btn btn-success">
                                <i class="bi bi-upload me-2"></i>Bulk Upload
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-6 text-primary mb-2">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <h5 class="mb-1"><?php echo number_format($total_results); ?></h5>
                    <small class="text-muted">Total Results</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-6 text-success mb-2">
                        <i class="bi bi-people"></i>
                    </div>
                    <h5 class="mb-1"><?php echo number_format($total_students); ?></h5>
                    <small class="text-muted">Students</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-6 text-info mb-2">
                        <i class="bi bi-journal-bookmark"></i>
                    </div>
                    <h5 class="mb-1"><?php echo number_format($total_courses); ?></h5>
                    <small class="text-muted">Courses</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-6 text-warning mb-2">
                        <i class="bi bi-graph-up"></i>
                    </div>
                    <h5 class="mb-1">
                        <?php 
                        $pass_count = 0;
                        foreach ($grade_distribution as $grade => $count) {
                            if ($grade != 'F') $pass_count += $count;
                        }
                        echo $total_results > 0 ? round(($pass_count / $total_results) * 100, 1) : 0;
                        ?>%
                    </h5>
                    <small class="text-muted">Pass Rate</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Session</label>
                            <select name="session_id" class="form-select">
                                <option value="">All Sessions</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?php echo $session['session_id']; ?>" 
                                            <?php echo $selected_session == $session['session_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($session['session_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Semester</label>
                            <select name="semester_id" class="form-select">
                                <option value="">All Semesters</option>
                                <?php foreach ($semesters as $semester): ?>
                                    <option value="<?php echo $semester['semester_id']; ?>" 
                                            <?php echo $selected_semester == $semester['semester_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($semester['semester_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-select">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['department_id']; ?>" 
                                            <?php echo $selected_department == $department['department_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($department['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Level</label>
                            <select name="level_id" class="form-select">
                                <option value="">All Levels</option>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo $level['level_id']; ?>" 
                                            <?php echo $selected_level == $level['level_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($level['level_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-2"></i>Apply Filters
                            </button>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-2"></i>Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-table me-2"></i>Results List
                            <span class="badge bg-primary ms-2"><?php echo number_format($total_results); ?></span>
                        </h6>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-success" onclick="exportResults()">
                                <i class="bi bi-download me-1"></i>Export
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($results)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-clipboard-x display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No Results Found</h4>
                            <p class="text-muted">No results match your current filters.</p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Add First Result
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Session/Semester</th>
                                        <th>CA Score</th>
                                        <th>Exam Score</th>
                                        <th>Total</th>
                                        <th>Grade</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($result['matric_number']); ?> | 
                                                        <?php echo htmlspecialchars($result['department_name']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($result['course_code']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($result['course_title']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars($result['session_name']); ?><br>
                                                    <?php echo htmlspecialchars($result['semester_name']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $result['ca_score']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning"><?php echo $result['exam_score']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $result['total_score']; ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $grade_colors = [
                                                    'A' => 'success',
                                                    'B' => 'primary',
                                                    'C' => 'info',
                                                    'D' => 'warning',
                                                    'F' => 'danger'
                                                ];
                                                $color = $grade_colors[$result['grade']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>">
                                                    <?php echo $result['grade']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?php echo $result['result_id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="view.php?id=<?php echo $result['result_id']; ?>" 
                                                       class="btn btn-outline-info" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="deleteResult(<?php echo $result['result_id']; ?>)" 
                                                            title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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
</div>

<script>
function deleteResult(resultId) {
    if (confirm('Are you sure you want to delete this result? This action cannot be undone.')) {
        // Implement delete functionality
        window.location.href = 'delete.php?id=' + resultId;
    }
}

function exportResults() {
    // Implement export functionality
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'export.php?' + params.toString();
}
</script>

<?php include_once '../includes/footer.php'; ?>
