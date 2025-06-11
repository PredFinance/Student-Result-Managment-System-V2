<?php
$page_title = "Edit Course";
$breadcrumb = "Courses > Edit Course";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Get course ID
$course_id = isset($_GET['id']) ? clean_input($_GET['id']) : '';

if (empty($course_id)) {
    set_flash_message('danger', 'Course ID is required');
    redirect(ADMIN_URL . '/courses/index.php');
}

// Initialize database connection
$db = new Database();
$institution_id = get_institution_id();

// Get course information
$db->query("SELECT * FROM courses WHERE course_id = :course_id AND institution_id = :institution_id");
$db->bind(':course_id', $course_id);
$db->bind(':institution_id', $institution_id);
$course = $db->single();

if (!$course) {
    set_flash_message('danger', 'Course not found');
    redirect(ADMIN_URL . '/courses/index.php');
}

// Get departments
$db->query("SELECT * FROM departments WHERE institution_id = :institution_id ORDER BY department_name");
$db->bind(':institution_id', $institution_id);
$departments = $db->resultSet();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_code = clean_input($_POST['course_code']);
    $course_title = clean_input($_POST['course_title']);
    $department_id = clean_input($_POST['department_id']);
    $credit_units = clean_input($_POST['credit_units']);
    
    $errors = [];
    
    // Validate required fields
    if (empty($course_code)) $errors[] = 'Course code is required';
    if (empty($course_title)) $errors[] = 'Course title is required';
    if (empty($department_id)) $errors[] = 'Department is required';
    if (empty($credit_units) || !is_numeric($credit_units) || $credit_units < 1) {
        $errors[] = 'Valid credit units (minimum 1) is required';
    }
    
    // Check if course code already exists (excluding current course)
    if (!empty($course_code)) {
        $db->query("SELECT course_id FROM courses WHERE course_code = :course_code AND institution_id = :institution_id AND course_id != :course_id");
        $db->bind(':course_code', $course_code);
        $db->bind(':institution_id', $institution_id);
        $db->bind(':course_id', $course_id);
        
        if ($db->single()) {
            $errors[] = 'Course code already exists';
        }
    }
    
    // If no errors, update course
    if (empty($errors)) {
        $db->query("UPDATE courses SET 
                    course_code = :course_code,
                    course_title = :course_title,
                    department_id = :department_id,
                    credit_units = :credit_units,
                    updated_at = NOW()
                    WHERE course_id = :course_id AND institution_id = :institution_id");
        
        $db->bind(':course_code', $course_code);
        $db->bind(':course_title', $course_title);
        $db->bind(':department_id', $department_id);
        $db->bind(':credit_units', $credit_units);
        $db->bind(':course_id', $course_id);
        $db->bind(':institution_id', $institution_id);
        
        if ($db->execute()) {
            set_flash_message('success', 'Course updated successfully.');
            redirect(ADMIN_URL . '/courses/view.php?id=' . $course_id);
        } else {
            $errors[] = 'Error updating course. Please try again.';
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil-square me-2"></i>Edit Course
                    </h5>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="course_code" class="form-label">
                                Course Code <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="course_code" 
                                   name="course_code" required
                                   value="<?php echo htmlspecialchars($course['course_code']); ?>"
                                   placeholder="e.g., CSC101">
                        </div>
                        
                        <div class="mb-3">
                            <label for="course_title" class="form-label">
                                Course Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="course_title" 
                                   name="course_title" required
                                   value="<?php echo htmlspecialchars($course['course_title']); ?>"
                                   placeholder="e.g., Introduction to Computer Science">
                        </div>
                        
                        <div class="mb-3">
                            <label for="department_id" class="form-label">
                                Department <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" 
                                            <?php echo ($course['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="credit_units" class="form-label">
                                Credit Units <span class="text-danger">*</span>
                            </label>
                            <input type="number" class="form-control" id="credit_units" 
                                   name="credit_units" required min="1" max="10"
                                   value="<?php echo $course['credit_units']; ?>">
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="view.php?id=<?php echo $course_id; ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Course
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Update Course
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>