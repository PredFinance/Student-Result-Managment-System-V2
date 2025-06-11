<?php
$page_title = "Manage Students";
$breadcrumb = "Students";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Initialize database connection
$db = new Database();
$institution_id = get_institution_id();

// Handle delete request
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $student_id = clean_input($_GET['delete']);
    
    // Check if student has course registrations
    $db->query("SELECT COUNT(*) as count FROM course_registrations WHERE student_id = :student_id");
    $db->bind(':student_id', $student_id);
    $registration_count = $db->single()['count'];
    
    if ($registration_count > 0) {
        set_flash_message('danger', 'Cannot delete student. Student has course registrations.');
    } else {
        // Safe to delete
        $db->beginTransaction();
        
        try {
            // Delete from users table if exists
            $db->query("DELETE FROM users WHERE user_id = (SELECT user_id FROM students WHERE student_id = :student_id)");
            $db->bind(':student_id', $student_id);
            $db->execute();
            
            // Delete student
            $db->query("DELETE FROM students WHERE student_id = :student_id AND institution_id = :institution_id");
            $db->bind(':student_id', $student_id);
            $db->bind(':institution_id', $institution_id);
            $db->execute();
            
            $db->endTransaction();
            set_flash_message('success', 'Student deleted successfully.');
        } catch (Exception $e) {
            $db->cancelTransaction();
            set_flash_message('danger', 'Error deleting student.');
        }
    }
    
    redirect(ADMIN_URL . '/students/index.php');
}

// Get filter parameters
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? clean_input($_GET['department']) : '';
$level_filter = isset($_GET['level']) ? clean_input($_GET['level']) : '';
$gender_filter = isset($_GET['gender']) ? clean_input($_GET['gender']) : '';

// Get departments for filter
$db->query("SELECT * FROM departments WHERE institution_id = :institution_id ORDER BY department_name");
$db->bind(':institution_id', $institution_id);
$departments = $db->resultSet();

// Get levels for filter
$db->query("SELECT * FROM levels WHERE institution_id = :institution_id ORDER BY level_name");
$db->bind(':institution_id', $institution_id);
$levels = $db->resultSet();

// Build query with filters
$query = "SELECT s.*, d.department_name, l.level_name,
          (SELECT COUNT(*) FROM course_registrations cr WHERE cr.student_id = s.student_id) as course_count
          FROM students s
          JOIN departments d ON s.department_id = d.department_id
          JOIN levels l ON s.level_id = l.level_id
          WHERE s.institution_id = :institution_id";

$params = [':institution_id' => $institution_id];

if (!empty($search)) {
    $query .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.matric_number LIKE :search OR s.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($department_filter)) {
    $query .= " AND s.department_id = :department_filter";
    $params[':department_filter'] = $department_filter;
}

if (!empty($level_filter)) {
    $query .= " AND s.level_id = :level_filter";
    $params[':level_filter'] = $level_filter;
}

if (!empty($gender_filter)) {
    $query .= " AND s.gender = :gender_filter";
    $params[':gender_filter'] = $gender_filter;
}

$query .= " ORDER BY s.first_name ASC, s.last_name ASC";

$db->query($query);
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

$students = $db->resultSet();

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2"></i>Student Management
                    </h5>
                    <div>
                        <a href="bulk-import.php" class="btn btn-success me-2">
                            <i class="bi bi-upload me-2"></i>Bulk Import
                        </a>
                        <a href="add.php" class="btn btn-primary">
                            <i class="bi bi-person-plus me-2"></i>Add New Student
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Search and Filters -->
                    <form method="GET" action="" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search students..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="department">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_id']; ?>" 
                                                <?php echo ($department_filter == $dept['department_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="level">
                                    <option value="">All Levels</option>
                                    <?php foreach ($levels as $level): ?>
                                        <option value="<?php echo $level['level_id']; ?>" 
                                                <?php echo ($level_filter == $level['level_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($level['level_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="gender">
                                    <option value="">All Genders</option>
                                    <option value="Male" <?php echo ($gender_filter == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($gender_filter == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo ($gender_filter == 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-search"></i> Filter
                                    </button>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Clear
                                    </a>
                                    <button type="button" class="btn btn-outline-success" onclick="exportStudents()">
                                        <i class="bi bi-download"></i> Export
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo count($students); ?></h4>
                                    <small>Total Students</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo count(array_filter($students, function($s) { return $s['gender'] == 'Male'; })); ?></h4>
                                    <small>Male Students</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo count(array_filter($students, function($s) { return $s['gender'] == 'Female'; })); ?></h4>
                                    <small>Female Students</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo count($departments); ?></h4>
                                    <small>Departments</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Students Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Matric Number</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Level</th>
                                    <th>Gender</th>
                                    <th>Courses</th>
                                    <th>Admission Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                                <?php if (!empty($search) || !empty($department_filter) || !empty($level_filter) || !empty($gender_filter)): ?>
                                                    No students found matching the selected filters
                                                <?php else: ?>
                                                    No students found. <a href="add.php">Add the first student</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($student['matric_number']); ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar me-2">
                                                        <div class="avatar-initial bg-secondary rounded-circle">
                                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                                        <?php if (!empty($student['email'])): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['department_name']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($student['level_name']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo ($student['gender'] == 'Male') ? 'primary' : (($student['gender'] == 'Female') ? 'danger' : 'secondary'); ?>">
                                                    <?php echo htmlspecialchars($student['gender']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $student['course_count']; ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo $student['admission_date'] ? format_date($student['admission_date']) : 'Not Set'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="view.php?id=<?php echo $student['student_id']; ?>" 
                                                       class="btn btn-outline-info" title="View Profile">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $student['student_id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="../courses/registration.php?student_id=<?php echo $student['student_id']; ?>" 
                                                       class="btn btn-outline-success" title="Course Registration">
                                                        <i class="bi bi-journal-check"></i>
                                                    </a>
                                                    <?php if ($student['course_count'] == 0): ?>
                                                        <a href="?delete=<?php echo $student['student_id']; ?>" 
                                                           class="btn btn-outline-danger btn-delete" 
                                                           title="Delete"
                                                           data-item="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" disabled title="Cannot delete - has course registrations">
                                                            <i class="bi bi-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
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
</div>

<style>
.avatar {
    width: 40px;
    height: 40px;
}

.avatar-initial {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}
</style>

<script>
function exportStudents() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    // Create download link
    const link = document.createElement('a');
    link.href = 'export.php?' + params.toString();
    link.download = 'students_export.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include_once '../includes/footer.php'; ?>