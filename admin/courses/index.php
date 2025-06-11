<?php
$page_title = "Manage Courses";
$breadcrumb = "Courses";

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
    $course_id = clean_input($_GET['delete']);
    
    // Check if course has registrations
    $db->query("SELECT COUNT(*) as count FROM course_registrations WHERE course_id = :course_id");
    $db->bind(':course_id', $course_id);
    $registration_count = $db->single()['count'];
    
    if ($registration_count > 0) {
        set_flash_message('danger', 'Cannot delete course. It has ' . $registration_count . ' student registrations.');
    } else {
        // Safe to delete
        $db->query("DELETE FROM courses WHERE course_id = :course_id AND institution_id = :institution_id");
        $db->bind(':course_id', $course_id);
        $db->bind(':institution_id', $institution_id);
        
        if ($db->execute()) {
            set_flash_message('success', 'Course deleted successfully.');
        } else {
            set_flash_message('danger', 'Error deleting course.');
        }
    }
    
    redirect(ADMIN_URL . '/courses/index.php');
}

// Get filter parameters
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? clean_input($_GET['department']) : '';

// Get departments for filter
$db->query("SELECT * FROM departments WHERE institution_id = :institution_id ORDER BY department_name");
$db->bind(':institution_id', $institution_id);
$departments = $db->resultSet();

// Build query with filters
$query = "SELECT c.*, d.department_name,
          (SELECT COUNT(*) FROM course_registrations cr WHERE cr.course_id = c.course_id) as registration_count
          FROM courses c
          JOIN departments d ON c.department_id = d.department_id
          WHERE c.institution_id = :institution_id";

$params = [':institution_id' => $institution_id];

if (!empty($search)) {
    $query .= " AND (c.course_code LIKE :search OR c.course_title LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($department_filter)) {
    $query .= " AND c.department_id = :department_filter";
    $params[':department_filter'] = $department_filter;
}

$query .= " ORDER BY d.department_name ASC, c.course_code ASC";

$db->query($query);
foreach ($params as $param => $value) {
    $db->bind($param, $value);
}

$courses = $db->resultSet();

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-journal-bookmark me-2"></i>Course Management
                    </h5>
                    <div>
                        <a href="bulk-import.php" class="btn btn-success me-2">
                            <i class="bi bi-upload me-2"></i>Bulk Import
                        </a>
                        <a href="add.php" class="btn btn-primary">
                            <i class="bi bi-journal-plus me-2"></i>Add New Course
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Search and Filters -->
                    <form method="GET" action="" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
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
                            <div class="col-md-5">
                                <div class="btn-group w-100" role="group">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-search"></i> Filter
                                    </button>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle"></i> Clear
                                    </a>
                                    <button type="button" class="btn btn-outline-success" onclick="exportCourses()">
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
                                    <h4><?php echo count($courses); ?></h4>
                                    <small>Total Courses</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo count($departments); ?></h4>
                                    <small>Departments</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo array_sum(array_column($courses, 'registration_count')); ?></h4>
                                    <small>Total Registrations</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo array_sum(array_column($courses, 'credit_units')); ?></h4>
                                    <small>Total Credit Units</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Courses Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Title</th>
                                    <th>Department</th>
                                    <th>Credit Units</th>
                                    <th>Registrations</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($courses)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                                <?php if (!empty($search) || !empty($department_filter)): ?>
                                                    No courses found matching the selected filters
                                                <?php else: ?>
                                                    No courses found. <a href="add.php">Add the first course</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($course['course_title']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?php echo htmlspecialchars($course['department_name']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $course['credit_units']; ?> Units</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $course['registration_count']; ?> Students</span>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo format_date($course['created_at']); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="view.php?id=<?php echo $course['course_id']; ?>" 
                                                       class="btn btn-outline-info" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="edit.php?id=<?php echo $course['course_id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="students.php?course_id=<?php echo $course['course_id']; ?>" 
                                                       class="btn btn-outline-success" title="View Students">
                                                        <i class="bi bi-people"></i>
                                                    </a>
                                                    <?php if ($course['registration_count'] == 0): ?>
                                                        <a href="?delete=<?php echo $course['course_id']; ?>" 
                                                           class="btn btn-outline-danger btn-delete" 
                                                           title="Delete"
                                                           data-item="<?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_title']); ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" disabled title="Cannot delete - has registrations">
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

<script>
function exportCourses() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    
    // Create download link
    const link = document.createElement('a');
    link.href = 'export.php?' + params.toString();
    link.download = 'courses_export.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include_once '../includes/footer.php'; ?>