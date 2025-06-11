<?php
$page_title = "Add Department";
$breadcrumb = "Departments > Add New";

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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $department_name = clean_input($_POST['department_name']);
    $department_code = clean_input($_POST['department_code']);
    $hod_name = clean_input($_POST['hod_name']);
    
    // Validation
    $errors = [];
    
    if (empty($department_name)) {
        $errors[] = 'Department name is required';
    }
    
    if (empty($department_code)) {
        $errors[] = 'Department code is required';
    } else {
        // Check if department code already exists
        $db->query("SELECT COUNT(*) as count FROM departments 
                    WHERE department_code = :department_code AND institution_id = :institution_id");
        $db->bind(':department_code', $department_code);
        $db->bind(':institution_id', $institution_id);
        
        if ($db->single()['count'] > 0) {
            $errors[] = 'Department code already exists';
        }
    }
    
    // If no errors, insert department
    if (empty($errors)) {
        $db->query("INSERT INTO departments (institution_id, department_name, department_code, hod_name) 
                    VALUES (:institution_id, :department_name, :department_code, :hod_name)");
        
        $db->bind(':institution_id', $institution_id);
        $db->bind(':department_name', $department_name);
        $db->bind(':department_code', strtoupper($department_code));
        $db->bind(':hod_name', $hod_name);
        
        if ($db->execute()) {
            set_flash_message('success', 'Department added successfully.');
            redirect(ADMIN_URL . '/departments/index.php');
        } else {
            $errors[] = 'Error adding department. Please try again.';
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
                        <i class="bi bi-building-add me-2"></i>Add New Department
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
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department_name" class="form-label">
                                        Department Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="department_name" 
                                           name="department_name" required
                                           value="<?php echo isset($_POST['department_name']) ? htmlspecialchars($_POST['department_name']) : ''; ?>"
                                           placeholder="e.g., Computer Science">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department_code" class="form-label">
                                        Department Code <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="department_code" 
                                           name="department_code" required maxlength="20"
                                           value="<?php echo isset($_POST['department_code']) ? htmlspecialchars($_POST['department_code']) : ''; ?>"
                                           placeholder="e.g., CSC" style="text-transform: uppercase;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="hod_name" class="form-label">Head of Department (HOD)</label>
                            <input type="text" class="form-control" id="hod_name" 
                                   name="hod_name"
                                   value="<?php echo isset($_POST['hod_name']) ? htmlspecialchars($_POST['hod_name']) : ''; ?>"
                                   placeholder="e.g., Dr. John Smith">
                            <div class="form-text">Optional - You can assign this later</div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Departments
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Add Department
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-generate department code from name
    $('#department_name').on('input', function() {
        const name = $(this).val();
        const code = name.split(' ').map(word => word.charAt(0)).join('').toUpperCase().substring(0, 5);
        $('#department_code').val(code);
    });
    
    // Force uppercase for department code
    $('#department_code').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });
</script>

<?php include_once '../includes/footer.php'; ?>