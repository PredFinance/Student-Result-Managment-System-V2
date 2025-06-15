<?php
$page_title = "Academic Transcript";
$breadcrumb = "Results > Transcript";

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

// Get student ID
$student_id = isset($_GET['student_id']) ? clean_input($_GET['student_id']) : '';

if (empty($student_id)) {
    set_flash_message('danger', 'Student ID is required');
    redirect(ADMIN_URL . '/results/index.php');
}

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

// Get institution information
$db->query("SELECT * FROM institutions WHERE institution_id = :institution_id");
$db->bind(':institution_id', $institution_id);
$institution = $db->single();

// Get comprehensive transcript data
$db->query("SELECT cr.session_id, cr.semester_id,
            sess.session_name, sem.semester_name, sem.semester_order,
            c.course_code, c.course_title, c.credit_units,
            r.ca_score, r.exam_score, r.total_score, r.grade, r.grade_point, r.remark
            FROM course_registrations cr
            JOIN sessions sess ON cr.session_id = sess.session_id
            JOIN semesters sem ON cr.semester_id = sem.semester_id
            JOIN courses c ON cr.course_id = c.course_id
            LEFT JOIN results r ON cr.registration_id = r.registration_id
            WHERE cr.student_id = :student_id
            ORDER BY sess.session_name, sem.semester_order, c.course_code");

$db->bind(':student_id', $student_id);
$all_results = $db->resultSet();

// Group results by session and semester
$transcript_data = [];
$overall_stats = [
    'total_courses' => 0,
    'completed_courses' => 0,
    'total_credit_units' => 0,
    'total_grade_points' => 0,
    'sessions_count' => 0
];

foreach ($all_results as $result) {
    $session_key = $result['session_name'];
    $semester_key = $result['semester_name'];
    
    if (!isset($transcript_data[$session_key])) {
        $transcript_data[$session_key] = [];
        $overall_stats['sessions_count']++;
    }
    
    if (!isset($transcript_data[$session_key][$semester_key])) {
        $transcript_data[$session_key][$semester_key] = [
            'results' => [],
            'gpa' => null
        ];
    }
    
    $transcript_data[$session_key][$semester_key]['results'][] = $result;
    $overall_stats['total_courses']++;
    
    if ($result['total_score'] !== null) {
        $overall_stats['completed_courses']++;
        $overall_stats['total_credit_units'] += $result['credit_units'];
        $overall_stats['total_grade_points'] += ($result['grade_point'] * $result['credit_units']);
    }
}

// Calculate semester GPAs
foreach ($transcript_data as $session_name => &$session_data) {
    foreach ($session_data as $semester_name => &$semester_data) {
        $semester_credit_units = 0;
        $semester_grade_points = 0;
        
        foreach ($semester_data['results'] as $result) {
            if ($result['total_score'] !== null) {
                $semester_credit_units += $result['credit_units'];
                $semester_grade_points += ($result['grade_point'] * $result['credit_units']);
            }
        }
        
        if ($semester_credit_units > 0) {
            $semester_data['gpa'] = [
                'gpa' => $semester_grade_points / $semester_credit_units,
                'credit_units' => $semester_credit_units,
                'grade_points' => $semester_grade_points
            ];
        }
    }
}

// Get cumulative GPA
$cgpa_info = null;
if ($overall_stats['total_credit_units'] > 0) {
    $cgpa_info = [
        'cgpa' => $overall_stats['total_grade_points'] / $overall_stats['total_credit_units'],
        'total_credit_units' => $overall_stats['total_credit_units'],
        'total_grade_points' => $overall_stats['total_grade_points'],
        'total_courses' => $overall_stats['completed_courses']
    ];
}

include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Action Buttons -->
    <div class="row mb-4 no-print">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <a href="student_results.php?student_id=<?php echo $student_id; ?>" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Results
                </a>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer me-2"></i>Print Transcript
                    </button>
                    <button class="btn btn-success" onclick="downloadPDF()">
                        <i class="bi bi-file-earmark-pdf me-2"></i>Download PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Transcript Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="transcript-header text-center mb-4">
                <div class="row align-items-center">
                    <div class="col-md-2">
                        <img src="../../assets/images/logo.png" alt="Institution Logo" class="img-fluid" style="max-height: 80px;" onerror="this.style.display='none'">
                    </div>
                    <div class="col-md-8">
                        <h3 class="mb-1"><?php echo htmlspecialchars($institution['institution_name'] ?? 'LUFEM School'); ?></h3>
                        <p class="mb-1"><?php echo htmlspecialchars($institution['address'] ?? ''); ?></p>
                        <p class="mb-3"><strong>OFFICIAL ACADEMIC TRANSCRIPT</strong></p>
                    </div>
                    <div class="col-md-2">
                        <div class="text-end">
                            <small class="text-muted">Generated on:<br><?php echo date('d M, Y'); ?></small>
                        </div>
                    </div>
                </div>
                
                <!-- Student Information -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Student Name:</th>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Matric Number:</th>
                                <td><?php echo htmlspecialchars($student['matric_number']); ?></td>
                            </tr>
                            <tr>
                                <th>Department:</th>
                                <td><?php echo htmlspecialchars($student['department_name']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">Level:</th>
                                <td><?php echo htmlspecialchars($student['level_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Date of Birth:</th>
                                <td><?php echo $student['date_of_birth'] ? format_date($student['date_of_birth'], 'd M, Y') : 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <th>Gender:</th>
                                <td><?php echo ucfirst($student['gender'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Academic Record -->
    <div class="card border-0 shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-file-earmark-text me-2"></i>Academic Record
            </h5>
        </div>
        
        <div class="card-body">
            <?php if (empty($transcript_data)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No academic records found for this student.
                </div>
            <?php else: ?>
                <?php foreach ($transcript_data as $session_name => $session_data): ?>
                    <div class="transcript-session mb-4">
                        <h6 class="text-primary border-bottom pb-2 mb-3">
                            <i class="bi bi-calendar me-2"></i><?php echo htmlspecialchars($session_name); ?> Academic Session
                        </h6>
                        
                        <?php foreach ($session_data as $semester_name => $semester_data): ?>
                            <div class="transcript-semester mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0 text-secondary"><?php echo htmlspecialchars($semester_name); ?> Semester</h6>
                                    <?php if ($semester_data['gpa']): ?>
                                        <span class="badge bg-primary fs-6">
                                            Semester GPA: <?php echo number_format($semester_data['gpa']['gpa'], 2); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Course Code</th>
                                                <th>Course Title</th>
                                                <th class="text-center">Credit Units</th>
                                                <th class="text-center">CA Score</th>
                                                <th class="text-center">Exam Score</th>
                                                <th class="text-center">Total Score</th>
                                                <th class="text-center">Grade</th>
                                                <th class="text-center">Grade Point</th>
                                                <th class="text-center">Remark</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $semester_total_units = 0;
                                            $semester_total_points = 0;
                                            
                                            foreach ($semester_data['results'] as $result): 
                                                if ($result['total_score'] !== null) {
                                                    $semester_total_units += $result['credit_units'];
                                                    $semester_total_points += ($result['grade_point'] * $result['credit_units']);
                                                }
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($result['course_code']); ?></td>
                                                    <td><?php echo htmlspecialchars($result['course_title']); ?></td>
                                                    <td class="text-center"><?php echo $result['credit_units']; ?></td>
                                                    <td class="text-center">
                                                        <?php echo $result['ca_score'] !== null ? $result['ca_score'] : '-'; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $result['exam_score'] !== null ? $result['exam_score'] : '-'; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $result['total_score'] !== null ? $result['total_score'] : '-'; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $result['grade'] !== null ? $result['grade'] : '-'; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $result['grade_point'] !== null ? $result['grade_point'] : '-'; ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo $result['remark'] !== null ? $result['remark'] : 'Pending'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <?php if ($semester_data['gpa']): ?>
                                        <tfoot class="table-light">
                                            <tr>
                                                <th colspan="2">Semester Total</th>
                                                <th class="text-center"><?php echo $semester_total_units; ?></th>
                                                <th colspan="4"></th>
                                                <th class="text-center"><?php echo number_format($semester_total_points, 1); ?></th>
                                                <th class="text-center">GPA: <?php echo number_format($semester_data['gpa']['gpa'], 2); ?></th>
                                            </tr>
                                        </tfoot>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                
                <!-- Cumulative Summary -->
                <?php if ($cgpa_info): ?>
                    <div class="transcript-summary mt-5 pt-4 border-top">
                        <h6 class="mb-3">
                            <i class="bi bi-bar-chart me-2"></i>Cumulative Academic Summary
                        </h6>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="60%">Total Courses Attempted:</th>
                                        <td><?php echo $overall_stats['total_courses']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Courses Completed:</th>
                                        <td><?php echo $overall_stats['completed_courses']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Credit Units Earned:</th>
                                        <td><?php echo $cgpa_info['total_credit_units']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Grade Points Earned:</th>
                                        <td><?php echo number_format($cgpa_info['total_grade_points'], 1); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="60%">Cumulative Grade Point Average (CGPA):</th>
                                        <td><strong class="fs-5"><?php echo number_format($cgpa_info['cgpa'], 2); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Class of Degree:</th>
                                        <td>
                                            <?php 
                                            $cgpa = $cgpa_info['cgpa'];
                                            if ($cgpa >= 4.50) {
                                                echo '<span class="badge bg-success fs-6">First Class</span>';
                                            } elseif ($cgpa >= 3.50) {
                                                echo '<span class="badge bg-primary fs-6">Second Class Upper</span>';
                                            } elseif ($cgpa >= 2.40) {
                                                echo '<span class="badge bg-info fs-6">Second Class Lower</span>';
                                            } elseif ($cgpa >= 1.50) {
                                                echo '<span class="badge bg-warning fs-6">Third Class</span>';
                                            } elseif ($cgpa >= 1.00) {
                                                echo '<span class="badge bg-secondary fs-6">Pass</span>';
                                            } else {
                                                echo '<span class="badge bg-danger fs-6">Fail</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Academic Status:</th>
                                        <td>
                                            <?php 
                                            if ($cgpa >= 1.00) {
                                                echo '<span class="badge bg-success fs-6">Good Standing</span>';
                                            } else {
                                                echo '<span class="badge bg-danger fs-6">Academic Probation</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Completion Rate:</th>
                                        <td>
                                            <?php 
                                            $completion_rate = $overall_stats['total_courses'] > 0 
                                                ? ($overall_stats['completed_courses'] / $overall_stats['total_courses']) * 100 
                                                : 0;
                                            echo number_format($completion_rate, 1) . '%';
                                            ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Grading Scale -->
                <div class="mt-5 pt-4 border-top">
                    <h6 class="mb-3">
                        <i class="bi bi-info-circle me-2"></i>Grading Scale & Classification
                    </h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Grade</th>
                                        <th>Score Range</th>
                                        <th>Grade Point</th>
                                        <th>Remark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>A</td>
                                        <td>70-100</td>
                                        <td>5.0</td>
                                        <td>Excellent</td>
                                    </tr>
                                    <tr>
                                        <td>B</td>
                                        <td>60-69</td>
                                        <td>4.0</td>
                                        <td>Very Good</td>
                                    </tr>
                                    <tr>
                                        <td>C</td>
                                        <td>50-59</td>
                                        <td>3.0</td>
                                        <td>Good</td>
                                    </tr>
                                    <tr>
                                        <td>D</td>
                                        <td>45-49</td>
                                        <td>2.0</td>
                                        <td>Fair</td>
                                    </tr>
                                    <tr>
                                        <td>E</td>
                                        <td>40-44</td>
                                        <td>1.0</td>
                                        <td>Pass</td>
                                    </tr>
                                    <tr>
                                        <td>F</td>
                                        <td>0-39</td>
                                        <td>0.0</td>
                                        <td>Fail</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>CGPA Range</th>
                                        <th>Class of Degree</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>4.50 - 5.00</td>
                                        <td>First Class</td>
                                    </tr>
                                    <tr>
                                        <td>3.50 - 4.49</td>
                                        <td>Second Class Upper</td>
                                    </tr>
                                    <tr>
                                        <td>2.40 - 3.49</td>
                                        <td>Second Class Lower</td>
                                    </tr>
                                    <tr>
                                        <td>1.50 - 2.39</td>
                                        <td>Third Class</td>
                                    </tr>
                                    <tr>
                                        <td>1.00 - 1.49</td>
                                        <td>Pass</td>
                                    </tr>
                                    <tr>
                                        <td>0.00 - 0.99</td>
                                        <td>Fail</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Certification -->
                <div class="mt-5 pt-4 border-top">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Date of Issue:</strong> <?php echo date('d M, Y'); ?></p>
                            <p class="mb-1"><strong>Issued By:</strong> <?php echo htmlspecialchars($institution['institution_name'] ?? 'LUFEM School'); ?></p>
                            <p class="mb-1"><strong>Academic Sessions Covered:</strong> <?php echo $overall_stats['sessions_count']; ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="mt-4">
                                <div style="border-top: 1px solid #000; width: 200px; margin-left: auto;"></div>
                                <p class="mb-0 mt-2"><strong>Registrar's Signature & Official Seal</strong></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <p class="text-muted small">
                            This is an official transcript issued by <?php echo htmlspecialchars($institution['institution_name'] ?? 'LUFEM School'); ?>. 
                            Any alteration or forgery of this document is a criminal offense.
                            <br>
                            Transcript ID: TR-<?php echo strtoupper(substr(md5($student_id . date('Y-m-d')), 0, 8)); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .card-header {
        background-color: transparent !important;
        border-bottom: 1px solid #000 !important;
    }
    
    .transcript-header {
        page-break-inside: avoid;
    }
    
    .transcript-session {
        page-break-inside: avoid;
    }
    
    .transcript-semester {
        page-break-inside: avoid;
    }
    
    .transcript-summary {
        page-break-inside: avoid;
    }
    
    body {
        font-size: 12px;
    }
    
    .table {
        font-size: 11px;
    }
    
    .badge {
        color: #000 !important;
        background-color: transparent !important;
        border: 1px solid #000 !important;
    }
}
</style>

<script>
function downloadPDF() {
    // Create a new window for PDF generation
    const printWindow = window.open('', '_blank');
    const content = document.querySelector('.container-fluid').innerHTML;
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Academic Transcript - ${document.title}</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { font-size: 12px; }
                .table { font-size: 11px; }
                .no-print { display: none !important; }
                .card { border: none !important; box-shadow: none !important; }
                .badge { color: #000 !important; background-color: transparent !important; border: 1px solid #000 !important; }
            </style>
        </head>
        <body>
            ${content}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 1000);
}
</script>

<?php include_once '../includes/footer.php'; ?>
