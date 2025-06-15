<?php
$page_title = "Bulk Results Upload";
$breadcrumb = "Results > Bulk Upload";

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

if (!$current_session || !$current_semester) {
    set_flash_message('danger', 'No active academic session or semester found.');
    redirect(ADMIN_URL . '/results/entry.php');
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['results_file'])) {
    $upload_result = handleBulkUpload($_FILES['results_file'], $current_session['session_id'], $current_semester['semester_id']);
    
    if ($upload_result['success']) {
        set_flash_message('success', $upload_result['message']);
    } else {
        set_flash_message('danger', $upload_result['message']);
    }
    
    redirect($_SERVER['PHP_SELF']);
}

function handleBulkUpload($file, $session_id, $semester_id) {
    global $db, $institution_id;
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, ['csv', 'xlsx', 'xls'])) {
        return ['success' => false, 'message' => 'Invalid file format. Please upload CSV or Excel file'];
    }
    
    // Process file based on extension
    if ($file_ext === 'csv') {
        return processCsvFile($file['tmp_name'], $session_id, $semester_id);
    } else {
        return processExcelFile($file['tmp_name'], $session_id, $semester_id);
    }
}

function processCsvFile($file_path, $session_id, $semester_id) {
    global $db, $institution_id;
    
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        return ['success' => false, 'message' => 'Could not read file'];
    }
    
    $header = fgetcsv($handle);
    $expected_headers = ['matric_number', 'course_code', 'ca_score', 'exam_score'];
    
    // Validate headers
    if (array_diff($expected_headers, array_map('strtolower', $header))) {
        fclose($handle);
        return ['success' => false, 'message' => 'Invalid CSV format. Expected headers: ' . implode(', ', $expected_headers)];
    }
    
    $db->beginTransaction();
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    try {
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine(array_map('strtolower', $header), $row);
            
            $matric_number = trim($data['matric_number']);
            $course_code = trim($data['course_code']);
            $ca_score = floatval($data['ca_score']);
            $exam_score = floatval($data['exam_score']);
            
            // Validate scores
            if ($ca_score < 0 || $ca_score > 40 || $exam_score < 0 || $exam_score > 60) {
                $errors[] = "Invalid scores for {$matric_number} - {$course_code}";
                $error_count++;
                continue;
            }
            
            // Find registration
            $db->query("SELECT cr.registration_id, cr.student_id
                        FROM course_registrations cr
                        JOIN students s ON cr.student_id = s.student_id
                        JOIN courses c ON cr.course_id = c.course_id
                        WHERE s.matric_number = :matric_number
                        AND c.course_code = :course_code
                        AND cr.session_id = :session_id
                        AND cr.semester_id = :semester_id
                        AND s.institution_id = :institution_id");
            
            $db->bind(':matric_number', $matric_number);
            $db->bind(':course_code', $course_code);
            $db->bind(':session_id', $session_id);
            $db->bind(':semester_id', $semester_id);
            $db->bind(':institution_id', $institution_id);
            
            $registration = $db->single();
            
            if (!$registration) {
                $errors[] = "Registration not found for {$matric_number} - {$course_code}";
                $error_count++;
                continue;
            }
            
            $total_score = $ca_score + $exam_score;
            $grade_info = calculateGrade($total_score);
            
            // Check if result exists
            $db->query("SELECT result_id FROM results WHERE registration_id = :registration_id");
            $db->bind(':registration_id', $registration['registration_id']);
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
            
            $db->bind(':registration_id', $registration['registration_id']);
            $db->bind(':ca_score', $ca_score);
            $db->bind(':exam_score', $exam_score);
            $db->bind(':total_score', $total_score);
            $db->bind(':grade', $grade_info['grade']);
            $db->bind(':grade_point', $grade_info['point']);
            $db->bind(':remark', $grade_info['remark']);
            
            if ($db->execute()) {
                $success_count++;
                
                // Update GPA for this student
                updateSemesterGPA($registration['student_id'], $session_id, $semester_id);
                updateCumulativeGPA($registration['student_id']);
            } else {
                $error_count++;
            }
        }
        
        $db->endTransaction();
        fclose($handle);
        
        $message = "$success_count results uploaded successfully";
        if ($error_count > 0) {
            $message .= ". $error_count errors occurred.";
        }
        
        return ['success' => true, 'message' => $message, 'errors' => $errors];
        
    } catch (Exception $e) {
        $db->cancelTransaction();
        fclose($handle);
        return ['success' => false, 'message' => 'Error processing file: ' . $e->getMessage()];
    }
}

function processExcelFile($file_path, $session_id, $semester_id) {
    // For Excel files, you would need a library like PhpSpreadsheet
    // For now, return a message asking for CSV format
    return ['success' => false, 'message' => 'Excel files not supported yet. Please convert to CSV format.'];
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
                                <i class="bi bi-upload text-success me-2"></i>
                                Bulk Results Upload
                            </h4>
                            <p class="text-muted mb-0">
                                Upload multiple results at once for: <strong><?php echo htmlspecialchars($current_session['session_name'] . ' - ' . $current_semester['semester_name']); ?></strong>
                            </p>
                        </div>
                        <a href="entry.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Entry
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Upload Form -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-cloud-upload me-2"></i>Upload Results File
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="form-label">Select CSV File</label>
                            <input type="file" class="form-control" name="results_file" accept=".csv,.xlsx,.xls" required>
                            <div class="form-text">
                                Supported formats: CSV, Excel (.xlsx, .xls). Maximum file size: 5MB
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-upload me-2"></i>Upload Results
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>File Format Requirements
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-3">Your CSV file must contain the following columns:</p>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-check-circle text-success me-2"></i><strong>matric_number</strong> - Student's matriculation number</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i><strong>course_code</strong> - Course code (e.g., CSC101)</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i><strong>ca_score</strong> - CA score (0-40)</li>
                        <li><i class="bi bi-check-circle text-success me-2"></i><strong>exam_score</strong> - Exam score (0-60)</li>
                    </ul>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Make sure all students and courses are already registered for the current session/semester.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Sample Template -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="bi bi-download me-2"></i>Download Template
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-3">Download a sample CSV template to get started:</p>
                    <a href="download_template.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-download me-2"></i>Download CSV Template
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>
