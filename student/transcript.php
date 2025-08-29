<?php
$page_title = "Academic Transcript";
$breadcrumb = "Academic Transcript";

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
$db->query("SELECT s.*, d.department_name, l.level_name, i.institution_name
            FROM students s
            JOIN departments d ON s.department_id = d.department_id
            JOIN levels l ON s.level_id = l.level_id
            JOIN institutions i ON s.institution_id = i.institution_id
            WHERE s.student_id = :student_id");
$db->bind(':student_id', $student_id);
$student_info = $db->single();

// Get all results grouped by session and semester
$db->query("SELECT c.course_code, c.course_title, c.credit_units,
            r.ca_score, r.exam_score, r.total_score, r.grade, r.grade_point, r.remark,
            sess.session_name, sem.semester_name, sem.semester_order,
            sess.session_id, sem.semester_id
            FROM course_registrations cr
            JOIN courses c ON cr.course_id = c.course_id
            JOIN results r ON cr.registration_id = r.registration_id
            JOIN sessions sess ON cr.session_id = sess.session_id
            JOIN semesters sem ON cr.semester_id = sem.semester_id
            WHERE cr.student_id = :student_id
            ORDER BY sess.session_name, sem.semester_order, c.course_code");

$db->bind(':student_id', $student_id);
$all_results = $db->resultSet();

// Group results by session and semester
$grouped_results = [];
$session_gpas = [];
$cumulative_stats = [
    'total_courses' => 0,
    'total_credit_units' => 0,
    'total_grade_points' => 0,
    'cgpa' => 0
];

foreach ($all_results as $result) {
    $session_key = $result['session_name'];
    $semester_key = $result['semester_name'];
    
    if (!isset($grouped_results[$session_key])) {
        $grouped_results[$session_key] = [];
    }
    
    if (!isset($grouped_results[$session_key][$semester_key])) {
        $grouped_results[$session_key][$semester_key] = [
            'courses' => [],
            'semester_credit_units' => 0,
            'semester_grade_points' => 0,
            'semester_gpa' => 0
        ];
    }
    
    $grouped_results[$session_key][$semester_key]['courses'][] = $result;
    $grouped_results[$session_key][$semester_key]['semester_credit_units'] += $result['credit_units'];
    $grouped_results[$session_key][$semester_key]['semester_grade_points'] += ($result['grade_point'] * $result['credit_units']);
    
    // Update cumulative stats
    $cumulative_stats['total_courses']++;
    $cumulative_stats['total_credit_units'] += $result['credit_units'];
    $cumulative_stats['total_grade_points'] += ($result['grade_point'] * $result['credit_units']);
}

// Calculate semester GPAs
foreach ($grouped_results as $session => &$semesters) {
    foreach ($semesters as $semester => &$data) {
        if ($data['semester_credit_units'] > 0) {
            $data['semester_gpa'] = round($data['semester_grade_points'] / $data['semester_credit_units'], 2);
        }
    }
}

// Calculate CGPA
if ($cumulative_stats['total_credit_units'] > 0) {
    $cumulative_stats['cgpa'] = round($cumulative_stats['total_grade_points'] / $cumulative_stats['total_credit_units'], 2);
}

// Get classification
$classification = getClassification($cumulative_stats['cgpa']);

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center no-print">
                    <h5 class="mb-0">
                        <i class="bi bi-file-earmark-pdf me-2"></i>Academic Transcript
                    </h5>
                    <div>
                        <button class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Print Transcript
                        </button>
                        <button class="btn btn-primary" onclick="downloadPDF()">
                            <i class="bi bi-download me-2"></i>Download PDF
                        </button>
                    </div>
                </div>
                
                <div class="card-body" id="transcript-content">
                    <!-- Institution Header -->
                    <div class="text-center mb-4">
                        <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="Logo" style="max-height: 80px;" onerror="this.style.display='none'">
                        <h3 class="mt-2"><?php echo htmlspecialchars($student_info['institution_name']); ?></h3>
                        <h5 class="text-muted">OFFICIAL ACADEMIC TRANSCRIPT</h5>
                        <hr>
                    </div>
                    
                    <!-- Student Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Student Name:</strong></td>
                                    <td><?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Matric Number:</strong></td>
                                    <td><?php echo htmlspecialchars($student_info['matric_number']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Department:</strong></td>
                                    <td><?php echo htmlspecialchars($student_info['department_name']); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Level:</strong></td>
                                    <td><?php echo htmlspecialchars($student_info['level_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Admission Date:</strong></td>
                                    <td><?php echo format_date($student_info['admission_date']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td><?php echo htmlspecialchars($student_info['status']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Academic Records -->
                    <?php if (empty($grouped_results)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-clipboard-x display-4 text-muted"></i>
                            <h5 class="text-muted mt-3">No Academic Records Found</h5>
                            <p class="text-muted">No completed courses found for this student.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($grouped_results as $session => $semesters): ?>
                            <div class="mb-4">
                                <h5 class="bg-light p-2 border-start border-primary border-4">
                                    <?php echo htmlspecialchars($session); ?> Academic Session
                                </h5>
                                
                                <?php foreach ($semesters as $semester => $data): ?>
                                    <div class="mb-3">
                                        <h6 class="text-primary"><?php echo htmlspecialchars($semester); ?></h6>
                                        
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Course Code</th>
                                                        <th>Course Title</th>
                                                        <th>Units</th>
                                                        <th>Score</th>
                                                        <th>Grade</th>
                                                        <th>Points</th>
                                                        <th>Remark</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($data['courses'] as $course): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                                            <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                                                            <td><?php echo $course['credit_units']; ?></td>
                                                            <td><?php echo $course['total_score']; ?></td>
                                                            <td><?php echo $course['grade']; ?></td>
                                                            <td><?php echo $course['grade_point']; ?></td>
                                                            <td><?php echo htmlspecialchars($course['remark']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="table-secondary">
                                                    <tr>
                                                        <td colspan="2"><strong>Semester Summary</strong></td>
                                                        <td><strong><?php echo $data['semester_credit_units']; ?></strong></td>
                                                        <td colspan="2"><strong>GPA: <?php echo $data['semester_gpa']; ?></strong></td>
                                                        <td colspan="2"></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Cumulative Summary -->
                        <div class="border-top pt-4">
                            <h5>Cumulative Academic Summary</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Total Courses Completed:</strong></td>
                                            <td><?php echo $cumulative_stats['total_courses']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Total Credit Units:</strong></td>
                                            <td><?php echo $cumulative_stats['total_credit_units']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Cumulative GPA (CGPA):</strong></td>
                                            <td><strong class="text-primary"><?php echo $cumulative_stats['cgpa']; ?></strong></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Classification:</strong></td>
                                            <td><strong class="text-success"><?php echo $classification; ?></strong></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <!-- Grade Scale -->
                                    <h6>Grading Scale</h6>
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Grade</th>
                                                <th>Points</th>
                                                <th>Range</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td>A</td><td>5.0</td><td>70-100</td></tr>
                                            <tr><td>B</td><td>4.0</td><td>60-69</td></tr>
                                            <tr><td>C</td><td>3.0</td><td>50-59</td></tr>
                                            <tr><td>D</td><td>2.0</td><td>45-49</td></tr>
                                            <tr><td>E</td><td>1.0</td><td>40-44</td></tr>
                                            <tr><td>F</td><td>0.0</td><td>0-39</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Footer -->
                    <div class="text-center mt-5 pt-4 border-top">
                        <p class="text-muted">
                            <small>
                                This is an official transcript generated on <?php echo date('F j, Y'); ?><br>
                                <?php echo htmlspecialchars($student_info['institution_name']); ?><br>
                                Student Results Management System
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF() {
    const element = document.getElementById('transcript-content');
    const opt = {
        margin: 1,
        filename: 'academic_transcript_<?php echo $student_info['matric_number']; ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    
    html2pdf().set(opt).from(element).save();
}
</script>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    body {
        background: white !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .table {
        font-size: 12px;
    }
    
    .page-break {
        page-break-before: always;
    }
}
</style>

<?php 
// Helper function for classification
function getClassification($cgpa) {
    if ($cgpa >= 4.5) return 'First Class';
    if ($cgpa >= 3.5) return 'Second Class Upper';
    if ($cgpa >= 2.5) return 'Second Class Lower';
    if ($cgpa >= 1.5) return 'Third Class';
    if ($cgpa >= 1.0) return 'Pass';
    return 'Fail';
}

include_once 'includes/footer.php'; 
?>
