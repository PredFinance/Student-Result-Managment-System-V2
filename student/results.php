<?php
$page_title = "My Results";
$breadcrumb = "Results";

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/gpa_functions.php';

// Check if user is logged in and is student
if (!is_logged_in() || $_SESSION['role'] != 'student') {
    set_flash_message('danger', 'You must be logged in as a student to access this page');
    redirect(BASE_URL);
}

// Initialize database connection
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

// Get performance summary
$performance_summary = getStudentPerformanceSummary($student_id);

// Get filter parameters
$session_filter = isset($_GET['session']) ? clean_input($_GET['session']) : '';
$semester_filter = isset($_GET['semester']) ? clean_input($_GET['semester']) : '';

// Get sessions and semesters for filters
$db->query("SELECT DISTINCT sess.session_id, sess.session_name
            FROM course_registrations cr
            JOIN sessions sess ON cr.session_id = sess.session_id
            JOIN results r ON cr.registration_id = r.registration_id
            WHERE cr.student_id = :student_id
            ORDER BY sess.session_name DESC");
$db->bind(':student_id', $student_id);
$sessions = $db->resultSet();

$db->query("SELECT DISTINCT sem.semester_id, sem.semester_name
            FROM course_registrations cr
            JOIN semesters sem ON cr.semester_id = sem.semester_id
            JOIN results r ON cr.registration_id = r.registration_id
            WHERE cr.student_id = :student_id
            ORDER BY sem.semester_order");
$db->bind(':student_id', $student_id);
$semesters = $db->resultSet();

// Build results query with filters
$query = "SELECT cr.registration_id, c.course_code, c.course_title, c.credit_units,
          sess.session_name, sem.semester_name,
          r.ca_score, r.exam_score, r.total_score, r.grade, r.grade_point,
          r.created_at as result_date
          FROM course_registrations cr
          JOIN courses c ON cr.course_id = c.course_id
          JOIN sessions sess ON cr.session_id = sess.session_id
          JOIN semesters sem ON cr.semester_id = sem.semester_id
          JOIN results r ON cr.registration_id = r.registration_id
          WHERE cr.student_id = :student_id";

$params = [':student_id' => $student_id];

if (!empty($session_filter)) {
    $query .= " AND cr.session_id = :session_filter";
    $params[':session_filter'] = $session_filter;
}

if (!empty($semester_filter)) {
    $query .= " AND cr.semester_id = :semester_filter";
    $params[':semester_filter'] = $semester_filter;
}

$query .= " ORDER BY sess.session_name DESC, sem.semester_order, c.course_code";

$db->query($query);
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

$results = $db->resultSet();

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Student Info Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-person-badge me-2"></i>Academic Profile
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Name:</strong><br>
                            <span class="text-primary"><?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Matric Number:</strong><br>
                            <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($student_info['matric_number']); ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Department:</strong><br>
                            <?php echo htmlspecialchars($student_info['department_name']); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Level:</strong><br>
                            <span class="badge bg-info fs-6"><?php echo htmlspecialchars($student_info['level_name']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <i class="bi bi-trophy display-6"></i>
                    <h4 class="mt-2"><?php echo $performance_summary['cumulative']['cgpa']; ?></h4>
                    <small>Cumulative GPA</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <i class="bi bi-award display-6"></i>
                    <h4 class="mt-2"><?php echo $performance_summary['cumulative']['total_credit_units']; ?></h4>
                    <small>Total Credit Units</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <i class="bi bi-journal-bookmark display-6"></i>
                    <h4 class="mt-2"><?php echo $performance_summary['cumulative']['total_courses']; ?></h4>
                    <small>Courses Completed</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-<?php echo $performance_summary['classification']['color']; ?> text-white">
                <div class="card-body text-center">
                    <i class="bi bi-mortarboard display-6"></i>
                    <h6 class="mt-2"><?php echo $performance_summary['classification']['class']; ?></h6>
                    <small>Current Classification</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clipboard-data me-2"></i>My Results
                    </h5>
                    <div>
                        <a href="transcript.php" class="btn btn-success me-2">
                            <i class="bi bi-file-earmark-pdf me-2"></i>Download Transcript
                        </a>
                        <button class="btn btn-info" onclick="showGPAChart()">
                            <i class="bi bi-graph-up me-2"></i>View Progress Chart
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" action="" class="mb-4">
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
                                        <i class="bi bi-funnel"></i> Filter Results
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Results Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Title</th>
                                    <th>Session/Semester</th>
                                    <th>Credit Units</th>
                                    <th>CA Score</th>
                                    <th>Exam Score</th>
                                    <th>Total Score</th>
                                    <th>Grade</th>
                                    <th>Grade Point</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($results)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                                <?php if (!empty($session_filter) || !empty($semester_filter)): ?>
                                                    No results found for the selected filters
                                                <?php else: ?>
                                                    No results available yet
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $current_session = '';
                                    $semester_totals = [];
                                    ?>
                                    <?php foreach ($results as $result): ?>
                                        <?php 
                                        $session_semester = $result['session_name'] . ' - ' . $result['semester_name'];
                                        if ($current_session != $session_semester): 
                                            if ($current_session != ''):
                                                // Display semester summary
                                                $semester_gpa = calculateSemesterGPA($student_id, $prev_session_id, $prev_semester_id);
                                        ?>
                                            <tr class="table-info">
                                                <td colspan="6"><strong>Semester GPA:</strong></td>
                                                <td><strong><?php echo $semester_gpa['gpa']; ?></strong></td>
                                                <td><strong><?php echo $semester_gpa['total_credit_units']; ?> Units</strong></td>
                                                <td></td>
                                            </tr>
                                        <?php 
                                            endif;
                                            $current_session = $session_semester;
                                            $prev_session_id = $result['session_id'] ?? '';
                                            $prev_semester_id = $result['semester_id'] ?? '';
                                        ?>
                                            <tr class="table-secondary">
                                                <td colspan="9">
                                                    <strong><i class="bi bi-calendar3 me-2"></i><?php echo htmlspecialchars($session_semester); ?></strong>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($result['course_code']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($result['course_title']); ?></td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($result['session_name']); ?><br>
                                                    <?php echo htmlspecialchars($result['semester_name']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $result['credit_units']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $result['ca_score']; ?>/30</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $result['exam_score']; ?>/70</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getGradeColor($result['grade']); ?>">
                                                    <?php echo $result['total_score']; ?>/100
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo getGradeColor($result['grade']); ?> fs-6">
                                                    <?php echo $result['grade']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo $result['grade_point']; ?></strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (!empty($results)): ?>
                                        <!-- Final semester summary -->
                                        <?php $semester_gpa = calculateSemesterGPA($student_id, $prev_session_id, $prev_semester_id); ?>
                                        <tr class="table-info">
                                            <td colspan="6"><strong>Semester GPA:</strong></td>
                                            <td><strong><?php echo $semester_gpa['gpa']; ?></strong></td>
                                            <td><strong><?php echo $semester_gpa['total_credit_units']; ?> Units</strong></td>
                                            <td></td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- GPA Progress Chart Modal -->
<div class="modal fade" id="gpaChartModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">GPA Progress Chart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <canvas id="gpaChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function showGPAChart() {
    $('#gpaChartModal').modal('show');
    
    // Prepare data for chart
    const semesterData = <?php echo json_encode($performance_summary['semesters']); ?>;
    
    const labels = semesterData.map(sem => sem.session_name + ' ' + sem.semester_name);
    const gpaData = semesterData.map(sem => sem.gpa);
    
    const ctx = document.getElementById('gpaChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Semester GPA',
                data: gpaData,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5.0,
                    title: {
                        display: true,
                        text: 'GPA'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Semester'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Academic Progress Over Time'
                },
                legend: {
                    display: true
                }
            }
        }
    });
}

// Helper function for grade colors
function getGradeColor(grade) {
    switch(grade) {
        case 'A': return 'success';
        case 'B': return 'primary';
        case 'C': return 'info';
        case 'D': return 'warning';
        case 'F': return 'danger';
        default: return 'secondary';
    }
}
</script>

<?php 
// Helper function for grade colors (PHP version)
function getGradeColor($grade) {
    switch($grade) {
        case 'A': return 'success';
        case 'B': return 'primary';
        case 'C': return 'info';
        case 'D': return 'warning';
        case 'F': return 'danger';
        default: return 'secondary';
    }
}

include_once 'includes/footer.php'; 
?>