<?php
$page_title = "Result Analytics";
$breadcrumb = "Results > Analytics";

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
$session_id = isset($_GET['session_id']) ? clean_input($_GET['session_id']) : '';
$semester_id = isset($_GET['semester_id']) ? clean_input($_GET['semester_id']) : '';
$department_id = isset($_GET['department_id']) ? clean_input($_GET['department_id']) : '';

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

// Get sessions for filter
$sessions = get_all_sessions();

// Get semesters for filter
$semesters = get_all_semesters();

// Get departments for filter
$db->query("SELECT * FROM departments WHERE institution_id = :institution_id ORDER BY department_name");
$db->bind(':institution_id', $institution_id);
$departments = $db->resultSet();

// Build base query conditions
$base_conditions = "WHERE cr.institution_id = :institution_id";
$base_params = [':institution_id' => $institution_id];

if (!empty($session_id)) {
    $base_conditions .= " AND cr.session_id = :session_id";
    $base_params[':session_id'] = $session_id;
}

if (!empty($semester_id)) {
    $base_conditions .= " AND cr.semester_id = :semester_id";
    $base_params[':semester_id'] = $semester_id;
}

if (!empty($department_id)) {
    $base_conditions .= " AND s.department_id = :department_id";
    $base_params[':department_id'] = $department_id;
}

// Get overall statistics
$db->query("SELECT 
            COUNT(DISTINCT s.student_id) as total_students,
            COUNT(DISTINCT c.course_id) as total_courses,
            COUNT(cr.registration_id) as total_registrations,
            COUNT(r.result_id) as completed_results
            FROM course_registrations cr
            JOIN students s ON cr.student_id = s.student_id
            JOIN courses c ON cr.course_id = c.course_id
            LEFT JOIN results r ON cr.registration_id = r.registration_id
            $base_conditions");

foreach ($base_params as $param => $value) {
    $db->bind($param, $value);
}
$overall_stats = $db->single();

// Get grade distribution
$db->query("SELECT r.grade, COUNT(*) as count
            FROM course_registrations cr
            JOIN students s ON cr.student_id = s.student_id
            JOIN results r ON cr.registration_id = r.registration_id
            $base_conditions
            GROUP BY r.grade
            ORDER BY r.grade");

foreach ($base_params as $param => $value) {
    $db->bind($param, $value);
}
$grade_distribution = $db->resultSet();

// Get department performance - FIXED: Remove reference to non-existent columns
$db->query("SELECT d.department_name,
            COUNT(DISTINCT s.student_id) as student_count,
            COUNT(r.result_id) as result_count,
            ROUND(AVG(r.score), 2) as avg_score,
            ROUND(AVG(sg.gpa), 2) as avg_gpa
            FROM departments d
            JOIN students s ON d.department_id = s.department_id
            JOIN course_registrations cr ON s.student_id = cr.student_id
            LEFT JOIN results r ON cr.registration_id = r.registration_id
            LEFT JOIN semester_gpas sg ON (s.student_id = sg.student_id AND cr.session_id = sg.session_id AND cr.semester_id = sg.semester_id)
            WHERE d.institution_id = :institution_id" . 
            (empty($department_id) ? "" : " AND d.department_id = :department_id") .
            (!empty($session_id) ? " AND cr.session_id = :session_id" : "") .
            (!empty($semester_id) ? " AND cr.semester_id = :semester_id" : "") . "
            GROUP BY d.department_id
            ORDER BY avg_gpa DESC");

$dept_params = [':institution_id' => $institution_id];
if (!empty($department_id)) $dept_params[':department_id'] = $department_id;
if (!empty($session_id)) $dept_params[':session_id'] = $session_id;
if (!empty($semester_id)) $dept_params[':semester_id'] = $semester_id;

foreach ($dept_params as $param => $value) {
    $db->bind($param, $value);
}
$department_performance = $db->resultSet();

// Get top performing students - FIXED: Remove reference to non-existent columns
$db->query("SELECT s.first_name, s.last_name, s.matric_number, d.department_name,
            sg.gpa, sg.credit_units
            FROM students s
            JOIN departments d ON s.department_id = d.department_id
            JOIN semester_gpas sg ON s.student_id = sg.student_id
            JOIN course_registrations cr ON s.student_id = cr.student_id
            $base_conditions
            AND sg.gpa IS NOT NULL
            ORDER BY sg.gpa DESC
            LIMIT 10");

foreach ($base_params as $param => $value) {
    $db->bind($param, $value);
}
$top_students = $db->resultSet();

// Get course performance - FIXED: Remove reference to non-existent columns
$db->query("SELECT c.course_code, c.course_title, c.credit_units,
            COUNT(r.result_id) as student_count,
            ROUND(AVG(r.score), 2) as avg_score,
            ROUND(AVG(r.grade_point), 2) as avg_grade_point,
            COUNT(CASE WHEN r.grade IN ('A', 'B') THEN 1 END) as excellent_count,
            COUNT(CASE WHEN r.grade = 'F' THEN 1 END) as fail_count
            FROM courses c
            JOIN course_registrations cr ON c.course_id = cr.course_id
            JOIN students s ON cr.student_id = s.student_id
            JOIN results r ON cr.registration_id = r.registration_id
            $base_conditions
            GROUP BY c.course_id
            HAVING student_count > 0
            ORDER BY avg_score DESC");

foreach ($base_params as $param => $value) {
    $db->bind($param, $value);
}
$course_performance = $db->resultSet();

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-funnel me-2"></i>Analytics Filters
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Academic Session</label>
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
                    <div class="col-md-3">
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
                    
                    <div class="col-md-3">
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
                    
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-bar-chart me-2"></i>Generate Analytics
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Overall Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-left-primary h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Students
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($overall_stats['total_students']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card border-left-success h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Courses
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($overall_stats['total_courses']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-journal-bookmark fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card border-left-info h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Registrations
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($overall_stats['total_registrations']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-journal-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card border-left-warning h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Completion Rate
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                $completion_rate = $overall_stats['total_registrations'] > 0 
                                    ? ($overall_stats['completed_results'] / $overall_stats['total_registrations']) * 100 
                                    : 0;
                                echo number_format($completion_rate, 1) . '%';
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- Grade Distribution Chart -->
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-pie-chart me-2"></i>Grade Distribution
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($grade_distribution)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            No grade data available for the selected filters.
                        </div>
                    <?php else: ?>
                        <canvas id="gradeChart" width="400" height="200"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Department Performance Chart -->
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-bar-chart me-2"></i>Department Performance (Average GPA)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($department_performance)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            No department performance data available for the selected filters.
                        </div>
                    <?php else: ?>
                        <canvas id="departmentChart" width="400" height="200"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tables Row -->
    <div class="row">
        <!-- Top Performing Students -->
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-trophy me-2"></i>Top Performing Students
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($top_students)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            No student data available for the selected filters.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Student</th>
                                        <th>Department</th>
                                        <th class="text-center">GPA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_students as $index => $student): ?>
                                        <tr>
                                            <td>
                                                <?php if ($index == 0): ?>
                                                    <i class="bi bi-trophy text-warning"></i>
                                                <?php elseif ($index == 1): ?>
                                                    <i class="bi bi-award text-secondary"></i>
                                                <?php elseif ($index == 2): ?>
                                                    <i class="bi bi-award text-warning"></i>
                                                <?php else: ?>
                                                    <?php echo $index + 1; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($student['matric_number']); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($student['department_name']); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo get_gpa_color($student['gpa']); ?>">
                                                    <?php echo number_format($student['gpa'], 2); ?>
                                                </span>
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
        
        <!-- Course Performance -->
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-journal-bookmark me-2"></i>Course Performance Summary
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($course_performance)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            No course performance data available for the selected filters.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Course</th>
                                        <th class="text-center">Students</th>
                                        <th class="text-center">Avg Score</th>
                                        <th class="text-center">Pass Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($course_performance as $course): ?>
                                        <?php 
                                        $pass_rate = $course['student_count'] > 0 
                                            ? (($course['student_count'] - $course['fail_count']) / $course['student_count']) * 100 
                                            : 0;
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($course['course_title'], 0, 30)) . (strlen($course['course_title']) > 30 ? '...' : ''); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?php echo $course['student_count']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo get_score_color($course['avg_score']); ?>">
                                                    <?php echo $course['avg_score']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $pass_rate >= 80 ? 'success' : ($pass_rate >= 60 ? 'warning' : 'danger'); ?>">
                                                    <?php echo number_format($pass_rate, 1); ?>%
                                                </span>
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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
<?php if (!empty($grade_distribution)): ?>
// Grade Distribution Chart
const gradeData = <?php echo json_encode($grade_distribution); ?>;
const gradeLabels = gradeData.map(item => item.grade);
const gradeCounts = gradeData.map(item => parseInt(item.count));

const gradeCtx = document.getElementById('gradeChart').getContext('2d');
new Chart(gradeCtx, {
    type: 'doughnut',
    data: {
        labels: gradeLabels,
        datasets: [{
            data: gradeCounts,
            backgroundColor: [
                '#28a745', // A - Green
                '#17a2b8', // B - Blue
                '#ffc107', // C - Yellow
                '#fd7e14', // D - Orange
                '#6f42c1', // E - Purple
                '#dc3545'  // F - Red
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($department_performance)): ?>
// Department Performance Chart
const deptData = <?php echo json_encode($department_performance); ?>;
const deptLabels = deptData.map(item => item.department_name.length > 15 ? item.department_name.substring(0, 15) + '...' : item.department_name);
const deptGPAs = deptData.map(item => parseFloat(item.avg_gpa) || 0);

const deptCtx = document.getElementById('departmentChart').getContext('2d');
new Chart(deptCtx, {
    type: 'bar',
    data: {
        labels: deptLabels,
        datasets: [{
            label: 'Average GPA',
            data: deptGPAs,
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 5.0,
                ticks: {
                    stepSize: 0.5
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
<?php endif; ?>
</script>

<?php include_once '../includes/footer.php'; ?>