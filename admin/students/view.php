<?php
$page_title = "View Student";
$breadcrumb = "Students > View Student";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Get student ID
$student_id = isset($_GET['id']) ? clean_input($_GET['id']) : '';

if (empty($student_id)) {
    set_flash_message('danger', 'Student ID is required');
    redirect(ADMIN_URL . '/students/index.php');
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
    redirect(ADMIN_URL . '/students/index.php');
}

// Get student's course registrations
$db->query("SELECT cr.*, c.course_code, c.course_title, c.credit_units,
            sess.session_name, sem.semester_name,
            r.ca_score, r.exam_score, r.total_score, r.grade, r.grade_point
            FROM course_registrations cr
            JOIN courses c ON cr.course_id = c.course_id
            JOIN sessions sess ON cr.session_id = sess.session_id
            JOIN semesters sem ON cr.semester_id = sem.semester_id
            LEFT JOIN results r ON cr.registration_id = r.registration_id
            WHERE cr.student_id = :student_id
            ORDER BY sess.session_name DESC, sem.semester_id, c.course_code");
$db->bind(':student_id', $student_id);
$registrations = $db->resultSet();

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-4">
            <!-- Student Profile Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person-badge me-2"></i>Student Profile
                    </h5>
                </div>
                <div class="card-body text-center">
                    <div class="avatar mx-auto mb-3" style="width: 80px; height: 80px;">
                        <div class="avatar-initial bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 100%; height: 100%; font-size: 2rem; font-weight: 600;">
                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                        </div>
                    </div>
                    
                    <h4><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($student['matric_number']); ?></p>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-end">
                                <h5 class="text-primary"><?php echo count($registrations); ?></h5>
                                <small class="text-muted">Courses</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h5 class="text-success"><?php echo array_sum(array_column($registrations, 'credit_units')); ?></h5>
                            <small class="text-muted">Credit Units</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Student Details Card -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Personal Information</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Department:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($student['department_name']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Level:</strong></div>
                        <div class="col-sm-8">
                            <span class="badge bg-info"><?php echo htmlspecialchars($student['level_name']); ?></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Gender:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($student['gender']); ?></div>
                    </div>
                    <?php if ($student['date_of_birth']): ?>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Date of Birth:</strong></div>
                        <div class="col-sm-8"><?php echo format_date($student['date_of_birth']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($student['email']): ?>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Email:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($student['email']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($student['phone']): ?>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Phone:</strong></div>
                        <div class="col-sm-8"><?php echo htmlspecialchars($student['phone']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="row mb-3">
                        <div class="col-sm-4"><strong>Admission Date:</strong></div>
                        <div class="col-sm-8"><?php echo $student['admission_date'] ? format_date($student['admission_date']) : 'Not Set'; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Course Registrations -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-journal-bookmark me-2"></i>Course Registrations
                    </h5>
                    <div>
                        <a href="../courses/registration.php?student_id=<?php echo $student['student_id']; ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-2"></i>Register Courses
                        </a>
                        <a href="edit.php?id=<?php echo $student['student_id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil me-2"></i>Edit Student
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <?php if (empty($registrations)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-journal-x display-4 text-muted"></i>
                            <h5 class="text-muted mt-3">No Course Registrations</h5>
                            <p class="text-muted">This student has not registered for any courses yet.</p>
                            <a href="../courses/registration.php?student_id=<?php echo $student['student_id']; ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Register First Course
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Title</th>
                                        <th>Session/Semester</th>
                                        <th>Credit Units</th>
                                        <th>Score</th>
                                        <th>Grade</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $reg): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($reg['course_code']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($reg['course_title']); ?></td>
                                            <td>
                                                <small>
                                                    <?php echo htmlspecialchars($reg['session_name']); ?><br>
                                                    <?php echo htmlspecialchars($reg['semester_name']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $reg['credit_units']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($reg['total_score'] !== null): ?>
                                                    <span class="badge bg-secondary"><?php echo $reg['total_score']; ?>/100</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($reg['grade']): ?>
                                                    <span class="badge bg-<?php echo get_grade_color($reg['grade']); ?>">
                                                        <?php echo $reg['grade']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($reg['total_score'] !== null): ?>
                                                    <span class="badge bg-success">Completed</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php endif; ?>
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
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Students
                </a>
                <div>
                    <a href="edit.php?id=<?php echo $student['student_id']; ?>" class="btn btn-primary me-2">
                        <i class="bi bi-pencil me-2"></i>Edit Student
                    </a>
                    <a href="../courses/registration.php?student_id=<?php echo $student['student_id']; ?>" class="btn btn-success">
                        <i class="bi bi-journal-check me-2"></i>Manage Courses
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>