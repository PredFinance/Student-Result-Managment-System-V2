<?php
$page_title = "Manage Departments";
$breadcrumb = "Departments";

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
    $department_id = clean_input($_GET['delete']);
    
    // Check if department has students
    $db->query("SELECT COUNT(*) as count FROM students WHERE department_id = :department_id");
    $db->bind(':department_id', $department_id);
    $student_count = $db->single()['count'];
    
    if ($student_count > 0) {
        set_flash_message('danger', 'Cannot delete department. It has ' . $student_count . ' students assigned to it.');
    } else {
        // Check if department has courses
        $db->query("SELECT COUNT(*) as count FROM courses WHERE department_id = :department_id");
        $db->bind(':department_id', $department_id);
        $course_count = $db->single()['count'];
        
        if ($course_count > 0) {
            set_flash_message('danger', 'Cannot delete department. It has ' . $course_count . ' courses assigned to it.');
        } else {
            // Safe to delete
            $db->query("DELETE FROM departments WHERE department_id = :department_id AND institution_id = :institution_id");
            $db->bind(':department_id', $department_id);
            $db->bind(':institution_id', $institution_id);
            
            if ($db->execute()) {
                set_flash_message('success', 'Department deleted successfully.');
            } else {
                set_flash_message('danger', 'Error deleting department.');
            }
        }
    }
    
    redirect(ADMIN_URL . '/departments/index.php');
}

// Get search parameters
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Build query with search
$query = "SELECT d.*, 
          (SELECT COUNT(*) FROM students s WHERE s.department_id = d.department_id) as student_count,
          (SELECT COUNT(*) FROM courses c WHERE c.department_id = d.department_id) as course_count
          FROM departments d 
          WHERE d.institution_id = :institution_id";

$params = [':institution_id' => $institution_id];

if (!empty($search)) {
    $query .= " AND (d.department_name LIKE :search OR d.department_code LIKE :search OR d.hod_name LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY d.department_name ASC";

$db->query($query);
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

$departments = $db->resultSet();

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-building me-2"></i>Department Management
                    </h5>
                    <a href="add.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Add New Department
                    </a>
                </div>
                
                <div class="card-body">
                    <!-- Search and Filter -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="GET" action="">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Search departments..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-outline-primary" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="index.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-x-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6 text-end">
                            <span class="text-muted">
                                Total Departments: <strong><?php echo count($departments); ?></strong>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Departments Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Department Code</th>
                                    <th>Department Name</th>
                                    <th>HOD</th>
                                    <th>Students</th>
                                    <th>Courses</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($departments)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                                <?php if (!empty($search)): ?>
                                                    No departments found matching "<?php echo htmlspecialchars($search); ?>"
                                                <?php else: ?>
                                                    No departments found. <a href="add.php">Add the first department</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($departments as $department): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($department['department_code']); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($department['department_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($department['hod_name'] ?: 'Not Assigned'); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $department['student_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $department['course_count']; ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo format_date($department['created_at']); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="edit.php?id=<?php echo $department['department_id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="view.php?id=<?php echo $department['department_id']; ?>" 
                                                       class="btn btn-outline-info" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($department['student_count'] == 0 && $department['course_count'] == 0): ?>
                                                        <a href="?delete=<?php echo $department['department_id']; ?>" 
                                                           class="btn btn-outline-danger btn-delete" 
                                                           title="Delete"
                                                           data-item="<?php echo htmlspecialchars($department['department_name']); ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" disabled title="Cannot delete - has students/courses">
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

<?php include_once '../includes/footer.php'; ?>