<?php
$page_title = "Smart Results Entry";
$breadcrumb = "Results > Enter Results";

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

// Get current session and semester (the system knows this automatically)
$current_session = get_current_session($institution_id);
$current_semester = get_current_semester($institution_id);

if (!$current_session || !$current_semester) {
    set_flash_message('danger', 'No active academic session or semester found. Please set up the current academic period first.');
    redirect(ADMIN_URL . '/settings/academic.php');
}

$session_id = $current_session['session_id'];
$semester_id = $current_semester['semester_id'];

// Handle AJAX requests for saving results
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'save_student_results') {
        $student_id = clean_input($_POST['student_id']);
        $results_data = $_POST['results'] ?? [];
        
        $db->beginTransaction();
        
        try {
            $success_count = 0;
            
            foreach ($results_data as $result) {
                $registration_id = clean_input($result['registration_id']);
                $ca_score = floatval($result['ca_score']);
                $exam_score = floatval($result['exam_score']);
                $total_score = $ca_score + $exam_score;
                
                // Validate scores
                if ($ca_score < 0 || $ca_score > 40 || $exam_score < 0 || $exam_score > 60) {
                    continue;
                }
                
                // Calculate grade
                $grade_info = calculateGrade($total_score);
                
                // Check if result exists
                $db->query("SELECT result_id FROM results WHERE registration_id = :registration_id");
                $db->bind(':registration_id', $registration_id);
                $existing = $db->single();
                
                if ($existing) {
                    // Update existing result
                    $db->query("UPDATE results SET 
                                ca_score = :ca_score,
                                exam_score = :exam_score,
                                total_score = :total_score,
                                grade = :grade,
                                grade_point = :grade_point,
                                remark = :remark,
                                updated_at = NOW()
                                WHERE registration_id = :registration_id");
                } else {
                    // Insert new result
                    $db->query("INSERT INTO results 
                                (registration_id, ca_score, exam_score, total_score, grade, grade_point, remark) 
                                VALUES 
                                (:registration_id, :ca_score, :exam_score, :total_score, :grade, :grade_point, :remark)");
                }
                
                $db->bind(':registration_id', $registration_id);
                $db->bind(':ca_score', $ca_score);
                $db->bind(':exam_score', $exam_score);
                $db->bind(':total_score', $total_score);
                $db->bind(':grade', $grade_info['grade']);
                $db->bind(':grade_point', $grade_info['point']);
                $db->bind(':remark', $grade_info['remark']);
                
                if ($db->execute()) {
                    $success_count++;
                }
            }
            
            // Update GPAs for this student
            updateSemesterGPA($student_id, $session_id, $semester_id);
            updateCumulativeGPA($student_id);
            
            $db->endTransaction();
            
            echo json_encode([
                'success' => true,
                'message' => "$success_count result(s) saved successfully"
            ]);
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            echo json_encode([
                'success' => false,
                'message' => 'Error saving results: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}

// Get all students who have course registrations in current session/semester
$db->query("SELECT DISTINCT s.student_id, s.matric_number, s.first_name, s.last_name,
            d.department_name, l.level_name,
            COUNT(cr.registration_id) as total_courses,
            COUNT(r.result_id) as completed_results
            FROM students s
            JOIN course_registrations cr ON s.student_id = cr.student_id
            JOIN departments d ON s.department_id = d.department_id
            JOIN levels l ON s.level_id = l.level_id
            LEFT JOIN results r ON cr.registration_id = r.registration_id
            WHERE cr.session_id = :session_id 
            AND cr.semester_id = :semester_id
            AND s.institution_id = :institution_id
            GROUP BY s.student_id
            ORDER BY s.matric_number");

$db->bind(':session_id', $session_id);
$db->bind(':semester_id', $semester_id);
$db->bind(':institution_id', $institution_id);

$students = $db->resultSet();

// Calculate overall statistics
$total_students = count($students);
$total_registrations = array_sum(array_column($students, 'total_courses'));
$total_completed = array_sum(array_column($students, 'completed_results'));
$completion_rate = $total_registrations > 0 ? round(($total_completed / $total_registrations) * 100, 1) : 0;

include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">
                                <i class="bi bi-lightning-charge me-2"></i>
                                Smart Results Entry
                            </h4>
                            <p class="mb-0 opacity-75">
                                Current Period: <strong><?php echo htmlspecialchars($current_session['session_name'] . ' - ' . $current_semester['semester_name']); ?></strong>
                            </p>
                        </div>
                        <div class="text-end">
                            <div class="row g-3 text-center">
                                <div class="col">
                                    <div class="h5 mb-0"><?php echo $total_students; ?></div>
                                    <small class="opacity-75">Students</small>
                                </div>
                                <div class="col">
                                    <div class="h5 mb-0"><?php echo $total_registrations; ?></div>
                                    <small class="opacity-75">Registrations</small>
                                </div>
                                <div class="col">
                                    <div class="h5 mb-0"><?php echo $completion_rate; ?>%</div>
                                    <small class="opacity-75">Completed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Students List -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-people me-2"></i>Students Requiring Results Entry
                        </h6>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                            <a href="bulk-upload.php" class="btn btn-sm btn-success">
                                <i class="bi bi-upload me-1"></i>Bulk Upload
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($students)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-people-fill display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No Students Found</h4>
                            <p class="text-muted">No students have registered for courses in the current academic period.</p>
                            <a href="../courses/registration.php" class="btn btn-primary">
                                <i class="bi bi-journal-check me-2"></i>Manage Course Registration
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Department</th>
                                        <th>Level</th>
                                        <th class="text-center">Courses</th>
                                        <th class="text-center">Results Status</th>
                                        <th class="text-center">Progress</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <?php
                                        $completion_percent = $student['total_courses'] > 0 
                                            ? round(($student['completed_results'] / $student['total_courses']) * 100) 
                                            : 0;
                                        
                                        $status_color = ($completion_percent === 100) ? 'success' : 
                                                        (($completion_percent > 0) ? 'warning' : 'danger');

                                        $status_text = ($completion_percent === 100) ? 'Complete' : 
                                                       (($completion_percent > 0) ? 'In Progress' : 'Not Started');
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle bg-primary text-white me-3">
                                                        <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($student['matric_number']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($student['department_name']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($student['level_name']); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?php echo $student['total_courses']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $status_color; ?>">
                                                    <?php echo $student['completed_results']; ?>/<?php echo $student['total_courses']; ?>
                                                </span>
                                                <br>
                                                <small class="text-<?php echo $status_color; ?>"><?php echo $status_text; ?></small>
                                            </td>
                                            <td class="text-center">
                                                <div class="progress" style="height: 20px; width: 100px;">
                                                    <div class="progress-bar bg-<?php echo $status_color; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $completion_percent; ?>%" 
                                                         aria-valuenow="<?php echo $completion_percent; ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="100">
                                                        <?php echo $completion_percent; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-primary" 
                                                        onclick="enterStudentResults(<?php echo $student['student_id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>', '<?php echo htmlspecialchars($student['matric_number']); ?>')">
                                                    <i class="bi bi-pencil-square me-1"></i>
                                                    <?php echo $completion_percent === 0 ? 'Enter Results' : 'Edit Results'; ?>
                                                </button>
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

<!-- Results Entry Modal -->
<div class="modal fade" id="resultsModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Enter Results for <span id="studentName"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Student Info Card -->
                <div class="card border-primary mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bi bi-person me-2"></i>Student Information
                        </h6>
                    </div>
                    <div class="card-body" id="studentInfo">
                        <!-- Student info will be loaded here -->
                    </div>
                </div>

                <!-- Results Entry Form -->
                <div class="card">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="bi bi-table me-2"></i>Course Results
                            </h6>
                            <button class="btn btn-sm btn-outline-primary" onclick="calculateAllTotals()">
                                <i class="bi bi-calculator me-1"></i>Calculate All
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 400px;">
                            <table class="table table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Course</th>
                                        <th class="text-center">Credit Units</th>
                                        <th class="text-center">CA Score (40)</th>
                                        <th class="text-center">Exam Score (60)</th>
                                        <th class="text-center">Total (100)</th>
                                        <th class="text-center">Grade</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="coursesTableBody">
                                    <!-- Courses will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveResultsBtn">
                    <i class="bi bi-save me-2"></i>Save All Results
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.progress {
    margin: 0 auto;
}

.card.border-primary {
    border-width: 2px !important;
}

.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}
</style>

<script>
let currentStudentId = null;

function enterStudentResults(studentId, studentName, matricNumber) {
    currentStudentId = studentId;
    
    // Update modal title
    $('#studentName').text(studentName);
    
    // Load student courses and results
    loadStudentCourses(studentId, studentName, matricNumber);
    
    // Show modal
    $('#resultsModal').modal('show');
}

function loadStudentCourses(studentId, studentName, matricNumber) {
    // Show loading
    $('#studentInfo').html('<div class="text-center"><div class="spinner-border text-primary"></div></div>');
    $('#coursesTableBody').html('<tr><td colspan="7" class="text-center"><div class="spinner-border text-primary"></div></td></tr>');
    
    // Get student courses with current results
    $.get('get_student_courses.php', {
        student_id: studentId,
        session_id: <?php echo $session_id; ?>,
        semester_id: <?php echo $semester_id; ?>
    }, function(response) {
        if (response.success) {
            displayStudentCourses(response.data, studentName, matricNumber);
        } else {
            showAlert('danger', 'Error loading student courses');
        }
    }, 'json').fail(function() {
        showAlert('danger', 'Error loading student courses');
    });
}

function displayStudentCourses(data, studentName, matricNumber) {
    const student = data.student;
    const courses = data.courses;
    
    // Update student info
    $('#studentInfo').html(`
        <div class="row">
            <div class="col-md-3">
                <strong>Name:</strong><br>
                ${studentName}
            </div>
            <div class="col-md-3">
                <strong>Matric Number:</strong><br>
                <span class="badge bg-primary">${matricNumber}</span>
            </div>
            <div class="col-md-3">
                <strong>Department:</strong><br>
                ${student.department_name}
            </div>
            <div class="col-md-3">
                <strong>Level:</strong><br>
                ${student.level_name}
            </div>
        </div>
    `);
    
    // Update courses table
    let tableBody = '';
    courses.forEach(function(course, index) {
        const hasResult = course.result_id !== null;
        const statusBadge = hasResult 
            ? '<span class="badge bg-success">Completed</span>' 
            : '<span class="badge bg-warning">Pending</span>';
        
        tableBody += `
            <tr data-registration-id="${course.registration_id}">
                <td>
                    <div>
                        <strong>${course.course_code}</strong>
                        <br>
                        <small class="text-muted">${course.course_title}</small>
                    </div>
                </td>
                <td class="text-center">
                    <span class="badge bg-info">${course.credit_units}</span>
                </td>
                <td class="text-center">
                    <input type="number" class="form-control form-control-sm ca-score" 
                           min="0" max="40" step="0.1" 
                           value="${course.ca_score || ''}" 
                           data-index="${index}"
                           placeholder="0-40">
                </td>
                <td class="text-center">
                    <input type="number" class="form-control form-control-sm exam-score" 
                           min="0" max="60" step="0.1" 
                           value="${course.exam_score || ''}" 
                           data-index="${index}"
                           placeholder="0-60">
                </td>
                <td class="text-center">
                    <input type="number" class="form-control form-control-sm total-score" 
                           min="0" max="100" step="0.1" 
                           value="${course.total_score || ''}" 
                           data-index="${index}" readonly>
                </td>
                <td class="text-center">
                    <input type="text" class="form-control form-control-sm grade-display" 
                           value="${course.grade || ''}" readonly>
                </td>
                <td class="text-center">
                    ${statusBadge}
                </td>
            </tr>
        `;
    });
    
    $('#coursesTableBody').html(tableBody);
    
    // Add event listeners for score calculation
    $('.ca-score, .exam-score').on('input', function() {
        const index = $(this).data('index');
        calculateTotal(index);
    });
    
    // Calculate initial totals
    courses.forEach((course, index) => {
        if (course.ca_score || course.exam_score) {
            calculateTotal(index);
        }
    });
}

function calculateTotal(index) {
    const caScore = parseFloat($(`.ca-score[data-index="${index}"]`).val()) || 0;
    const examScore = parseFloat($(`.exam-score[data-index="${index}"]`).val()) || 0;
    const total = caScore + examScore;
    
    $(`.total-score[data-index="${index}"]`).val(total.toFixed(1));
    
    // Calculate grade
    let grade = '';
    if (total >= 70) grade = 'A';
    else if (total >= 60) grade = 'B';
    else if (total >= 50) grade = 'C';
    else if (total >= 45) grade = 'D';
    else if (total >= 40) grade = 'E';
    else grade = 'F';
    
    $(`.grade-display[data-index="${index}"]`).val(grade);
}

function calculateAllTotals() {
    $('.ca-score').each(function() {
        const index = $(this).data('index');
        calculateTotal(index);
    });
}

// Save results
$('#saveResultsBtn').on('click', function() {
    const resultsData = [];
    
    $('#coursesTableBody tr').each(function() {
        const registrationId = $(this).data('registration-id');
        const caScore = $(this).find('.ca-score').val();
        const examScore = $(this).find('.exam-score').val();
        
        if (caScore !== '' && examScore !== '') {
            resultsData.push({
                registration_id: registrationId,
                ca_score: caScore,
                exam_score: examScore
            });
        }
    });
    
    if (resultsData.length === 0) {
        showAlert('warning', 'No results to save');
        return;
    }
    
    // Show loading
    $('#saveResultsBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');
    
    $.post('', {
        action: 'save_student_results',
        student_id: currentStudentId,
        results: resultsData
    }, function(response) {
        if (response.success) {
            showAlert('success', response.message);
            $('#resultsModal').modal('hide');
            // Refresh the page to update the status
            location.reload();
        } else {
            showAlert('danger', response.message);
        }
    }, 'json').fail(function() {
        showAlert('danger', 'Error saving results');
    }).always(function() {
        $('#saveResultsBtn').prop('disabled', false).html('<i class="bi bi-save me-2"></i>Save All Results');
    });
});

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('.alert').remove();
    $('.container-fluid').prepend(alertHtml);
    
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}
</script>

<?php include_once '../includes/footer.php'; ?>
