<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is admin
if (
    !is_logged_in() ||
    !(has_role('admin') || has_role('super_admin'))
) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Initialize database connection
$db = new Database();

// Get institution info
$institution_id = get_institution_id();
$db->query("SELECT * FROM institutions WHERE institution_id = :institution_id");
$db->bind(':institution_id', $institution_id);
$institution = $db->single();

// Get current session and semester
$current_session = get_current_session();
$current_semester = get_current_semester();

// Get counts for dashboard
$db->query("SELECT COUNT(*) as count FROM students WHERE institution_id = :institution_id");
$db->bind(':institution_id', $institution_id);
$student_count = $db->single()['count'];

$db->query("SELECT COUNT(*) as count FROM departments WHERE institution_id = :institution_id");
$db->bind(':institution_id', $institution_id);
$department_count = $db->single()['count'];

$db->query("SELECT COUNT(*) as count FROM courses WHERE institution_id = :institution_id");
$db->bind(':institution_id', $institution_id);
$course_count = $db->single()['count'];

// Get recent students
$db->query("SELECT s.*, d.department_name, l.level_name 
            FROM students s
            JOIN departments d ON s.department_id = d.department_id
            JOIN levels l ON s.level_id = l.level_id
            WHERE s.institution_id = :institution_id
            ORDER BY s.created_at DESC
            LIMIT 5");
$db->bind(':institution_id', $institution_id);
$recent_students = $db->resultSet();

// Include header
include_once 'includes/header.php';
?>

<!-- Main Content -->
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title">Welcome, <?php echo $_SESSION['full_name']; ?>!</h2>
                    <p class="text-muted">
                        Current Session: <strong><?php echo $current_session ? $current_session['session_name'] : 'Not Set'; ?></strong> | 
                        Current Semester: <strong><?php echo $current_semester ? $current_semester['semester_name'] : 'Not Set'; ?></strong>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $student_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people-fill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Departments</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $department_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-building fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Courses</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $course_count; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Session</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $current_session ? $current_session['session_name'] : 'Not Set'; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Students -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">Recently Added Students</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Matric Number</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Level</th>
                                    <th>Date Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_students)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No students found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_students as $student): ?>
                                        <tr>
                                            <td><?php echo $student['matric_number']; ?></td>
                                            <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                            <td><?php echo $student['department_name']; ?></td>
                                            <td><?php echo $student['level_name']; ?></td>
                                            <td><?php echo format_date($student['created_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <a href="students/add.php" class="btn btn-success btn-block">
                                <i class="bi bi-person-plus-fill mr-2"></i> Add New Student
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="courses/add.php" class="btn btn-info btn-block">
                                <i class="bi bi-journal-plus mr-2"></i> Add New Course
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="results/entry.php" class="btn btn-warning btn-block">
                                <i class="bi bi-pencil-square mr-2"></i> Enter Results
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="reports/index.php" class="btn btn-primary btn-block">
                                <i class="bi bi-file-earmark-text mr-2"></i> View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">System Information</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Institution:</strong> <?php echo $institution['institution_name']; ?>
                    </div>
                    <div class="mb-3">
                        <strong>System Version:</strong> <?php echo APP_VERSION; ?>
                    </div>
                    <div class="mb-3">
                        <strong>Last Login:</strong> <?php echo format_date($_SESSION['last_login'] ?? date('Y-m-d H:i:s')); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Server Time:</strong> <?php echo date('d M, Y H:i:s'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once 'includes/footer.php';
?>