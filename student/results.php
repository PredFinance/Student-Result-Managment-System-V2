<?php
$page_title = "My Results";
$breadcrumb = "My Results";

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/gpa_functions.php';

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

// Get results for selected session/semester
$results = [];
$semester_gpa = 0;
$total_credit_units = 0;

if ($selected_session_id && $selected_semester_id) {
    $db->query("SELECT c.course_code, c.course_title, c.credit_units,
                r.ca_score, r.exam_score, r.total_score, r.grade, r.grade_point, r.remark,
                sess.session_name, sem.semester_name, r.created_at
                FROM course_registrations cr
                JOIN courses c ON cr.course_id = c.course_id
                JOIN results r ON cr.registration_id = r.registration_id
                JOIN academic_sessions sess ON cr.session_id = sess.session_id
                JOIN semesters sem ON cr.semester_id = sem.semester_id
                WHERE cr.student_id = :student_id 
                AND cr.session_id = :session_id 
                AND cr.semester_id = :semester_id
                ORDER BY c.course_code");
    
    $db->bind(':student_id', $student_id);
    $db->bind(':session_id', $selected_session_id);
    $db->bind(':semester_id', $selected_semester_id);
    $results = $db->resultSet();
    
    // Calculate semester GPA
    if (!empty($results)) {
        $total_grade_points = 0;
        $total_credit_units = 0;
        
        foreach ($results as $result) {
            $total_grade_points += ($result['grade_point'] * $result['credit_units']);
            $total_credit_units += $result['credit_units'];
        }
        
        $semester_gpa = $total_credit_units > 0 ? round($total_grade_points / $total_credit_units, 2) : 0;
    }
}

// Get cumulative performance summary
$performance_summary = getStudentPerformanceSummary($student_id);

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clipboard-data me-2"></i>My Results
                    </h5>
                </div>
                
                <div class="card-body">
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

                    <!-- Filter Section -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label for="session_filter" class="form-label">Academic Session</label>
                            <select class="form-select" id="session_filter" onchange="filterResults()">
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
                            <select class="form-select" id="semester_filter" onchange="filterResults()">
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
                                <a href="transcript.php" class="btn btn-primary">
                                    <i class="bi bi-file-earmark-pdf me-2"></i>Download Transcript
                                </a>
                                <button class="btn btn-outline-secondary" onclick="printResults()">
                                    <i class="bi bi-printer me-2"></i>Print
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Results Display -->
                    <?php if (empty($results)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-clipboard-x display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No Results Found</h4>
                            <?php if ($selected_session_id && $selected_semester_id): ?>
                                <p class="text-muted">No results available for the selected session/semester.</p>
                            <?php else: ?>
                                <p class="text-muted">Please select a session and semester to view your results.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Semester Summary -->
                        <div class="card border-primary mb-4">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-bar-chart me-2"></i>Semester Summary
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h5 class="text-primary"><?php echo count($results); ?></h5>
                                        <small class="text-muted">Courses</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h5 class="text-info"><?php echo $total_credit_units; ?></h5>
                                        <small class="text-muted">Credit Units</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h5 class="text-success"><?php echo $semester_gpa; ?></h5>
                                        <small class="text-muted">Semester GPA</small>
                                    </div>
                                    <div class="col-md-3">
                                        <?php 
                                        $passed_courses = array_filter($results, function($r) { return $r['grade'] != 'F'; });
                                        $pass_rate = count($results) > 0 ? round((count($passed_courses) / count($results)) * 100, 1) : 0;
                                        ?>
                                        <h5 class="text-warning"><?php echo $pass_rate; ?>%</h5>
                                        <small class="text-muted">Pass Rate</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Results Table -->
                        <div class="table-responsive">
                            <table class="table table-hover" id="resultsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Title</th>
                                        <th>Credit Units</th>
                                        <th>CA Score</th>
                                        <th>Exam Score</th>
                                        <th>Total Score</th>
                                        <th>Grade</th>
                                        <th>Grade Point</th>
                                        <th>Remark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($result['course_code']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($result['course_title']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $result['credit_units']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $result['ca_score']; ?>/30</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $result['exam_score']; ?>/70</span>
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
                                            <td><?php echo $result['grade_point']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getRemarkColor($result['remark']); ?>">
                                                    <?php echo $result['remark']; ?>
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

<script>
function filterResults() {
    const sessionId = document.getElementById('session_filter').value;
    const semesterId = document.getElementById('semester_filter').value;
    
    if (sessionId && semesterId) {
        window.location.href = `results.php?session_id=${sessionId}&semester_id=${semesterId}`;
    }
}

function printResults() {
    window.print();
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

// Helper function for remark colors
function getRemarkColor($remark) {
    switch(strtolower($remark)) {
        case 'excellent': return 'success';
        case 'very good': return 'primary';
        case 'good': return 'info';
        case 'fair': return 'warning';
        case 'pass': return 'secondary';
        case 'fail': return 'danger';
        default: return 'secondary';
    }
}

include_once 'includes/footer.php'; 
?>
