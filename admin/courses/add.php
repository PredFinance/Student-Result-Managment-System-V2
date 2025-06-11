<?php
$page_title = "Add Course";
$breadcrumb = "Courses > Add New";

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

// Get departments
$db->query("SELECT * FROM departments WHERE institution_id = :institution_id ORDER BY department_name");
$db->bind(':institution_id', $institution_id);
$departments = $db->resultSet();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_code = clean_input($_POST['course_code']);
    $course_title = clean_input($_POST['course_title']);
    $department_id = clean_input($_POST['department_id']);
    $credit_units = clean_input($_POST['credit_units']);
    $course_description = clean_input($_POST['course_description']);
    $prerequisites = clean_input($_POST['prerequisites']);
    
    // Validation
    $errors = [];
    
    if (empty($course_code)) {
        $errors[] = 'Course code is required';
    } else {
        // Check if course code already exists
        $db->query("SELECT COUNT(*) as count FROM courses 
                    WHERE course_code = :course_code AND institution_id = :institution_id");
        $db->bind(':course_code', $course_code);
        $db->bind(':institution_id', $institution_id);
        
        if ($db->single()['count'] > 0) {
            $errors[] = 'Course code already exists';
        }
    }
    
    if (empty($course_title)) {
        $errors[] = 'Course title is required';
    }
    
    if (empty($department_id)) {
        $errors[] = 'Department is required';
    }
    
    if (empty($credit_units) || !is_numeric($credit_units) || $credit_units < 1 || $credit_units > 10) {
        $errors[] = 'Credit units must be a number between 1 and 10';
    }
    
    // If no errors, insert course
    if (empty($errors)) {
        $db->query("INSERT INTO courses (institution_id, department_id, course_code, course_title, credit_units) 
                    VALUES (:institution_id, :department_id, :course_code, :course_title, :credit_units)");
        
        $db->bind(':institution_id', $institution_id);
        $db->bind(':department_id', $department_id);
        $db->bind(':course_code', strtoupper($course_code));
        $db->bind(':course_title', $course_title);
        $db->bind(':credit_units', $credit_units);
        
        if ($db->execute()) {
            set_flash_message('success', 'Course added successfully.');
            redirect(ADMIN_URL . '/courses/index.php');
        } else {
            $errors[] = 'Error adding course. Please try again.';
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-journal-plus me-2"></i>Add New Course
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
                    
                    <?php if (empty($departments)): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            No departments found. <a href="../departments/add.php">Create a department first</a> before adding courses.
                        </div>
                    <?php else: ?>
                    
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="course_code" class="form-label">
                                        Course Code <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="course_code" 
                                           name="course_code" required maxlength="20"
                                           value="<?php echo isset($_POST['course_code']) ? htmlspecialchars($_POST['course_code']) : ''; ?>"
                                           placeholder="e.g., CSC101" style="text-transform: uppercase;">
                                    <div class="form-text">Unique identifier for the course</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="credit_units" class="form-label">
                                        Credit Units <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="credit_units" name="credit_units" required>
                                        <option value="">Select Credit Units</option>
                                        <?php for ($i = 1; $i <= 6; $i++): ?>
                                            <option value="<?php echo $i; ?>" 
                                                    <?php echo (isset($_POST['credit_units']) && $_POST['credit_units'] == $i) ? 'selected' : ''; ?>>
                                                <?php echo $i; ?> Unit<?php echo $i > 1 ? 's' : ''; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="course_title" class="form-label">
                                Course Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="course_title" 
                                   name="course_title" required
                                   value="<?php echo isset($_POST['course_title']) ? htmlspecialchars($_POST['course_title']) : ''; ?>"
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
                                            <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?> (<?php echo htmlspecialchars($dept['department_code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="course_description" class="form-label">Course Description</label>
                            <textarea class="form-control" id="course_description" name="course_description" rows="4"
                                      placeholder="Brief description of the course content and objectives..."><?php echo isset($_POST['course_description']) ? htmlspecialchars($_POST['course_description']) : ''; ?></textarea>
                            <div class="form-text">Optional - Describe what students will learn in this course</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="prerequisites" class="form-label">Prerequisites</label>
                            <input type="text" class="form-control" id="prerequisites" 
                                   name="prerequisites"
                                   value="<?php echo isset($_POST['prerequisites']) ? htmlspecialchars($_POST['prerequisites']) : ''; ?>"
                                   placeholder="e.g., MTH101, CSC100">
                            <div class="form-text">Optional - List course codes that students must complete first</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Course Code Guidelines:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Use department abbreviation + level + sequence (e.g., CSC101, MTH201)</li>
                                <li>100-level: First year courses</li>
                                <li>200-level: Second year courses</li>
                                <li>300-level: Third year courses</li>
                                <li>400-level: Fourth year courses</li>
                            </ul>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Courses
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Add Course
                            </button>
                        </div>
                    </form>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-generate course code based on department and title
$('#department_id').on('change', function() {
    generateCourseCode();
});

$('#course_title').on('input', function() {
    generateCourseCode();
});

function generateCourseCode() {
    const deptSelect = $('#department_id');
    const titleInput = $('#course_title');
    const codeInput = $('#course_code');
    
    if (codeInput.val()) return; // Don't override if user has entered something
    
    const deptText = deptSelect.find('option:selected').text();
    const title = titleInput.val();
    
    if (deptText && deptText !== 'Select Department' && title) {
        // Extract department code from the option text
        const match = deptText.match(/$$([^)]+)$$$/);
        if (match) {
            const deptCode = match[1];
            
            // Generate a simple course number (you can make this more sophisticated)
            const randomNum = Math.floor(Math.random() * 900) + 100; // 100-999
            const courseCode = deptCode + randomNum;
            
            codeInput.val(courseCode);
        }
    }
}

// Force uppercase for course code
$('#course_code').on('input', function() {
    $(this).val($(this).val().toUpperCase());
});

// Course title formatting
$('#course_title').on('input', function() {
    const title = $(this).val();
    // Capitalize first letter of each word
    const formatted = title.replace(/\w\S*/g, function(txt) {
        return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
    });
    $(this).val(formatted);
});
</script>

<?php include_once '../includes/footer.php'; ?>