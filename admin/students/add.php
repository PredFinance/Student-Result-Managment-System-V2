<?php
$page_title = "Add Student";
$breadcrumb = "Students > Add New";

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

// Get levels
$db->query("SELECT * FROM levels WHERE institution_id = :institution_id ORDER BY level_name");
$db->bind(':institution_id', $institution_id);
$levels = $db->resultSet();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $matric_number = clean_input($_POST['matric_number']);
    $gender = clean_input($_POST['gender']);
    $department_id = clean_input($_POST['department_id']);
    $level_id = clean_input($_POST['level_id']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $address = clean_input($_POST['address']);
    $date_of_birth = clean_input($_POST['date_of_birth']);
    $admission_date = clean_input($_POST['admission_date']);
    $create_login = isset($_POST['create_login']) ? true : false;
    
    // Validation
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = 'First name is required';
    }
    
    if (empty($last_name)) {
        $errors[] = 'Last name is required';
    }
    
    if (empty($matric_number)) {
        $errors[] = 'Matriculation number is required';
    } else {
        // Check if matric number already exists
        $db->query("SELECT COUNT(*) as count FROM students 
                    WHERE matric_number = :matric_number AND institution_id = :institution_id");
        $db->bind(':matric_number', $matric_number);
        $db->bind(':institution_id', $institution_id);
        
        if ($db->single()['count'] > 0) {
            $errors[] = 'Matriculation number already exists';
        }
    }
    
    if (empty($gender)) {
        $errors[] = 'Gender is required';
    }
    
    if (empty($department_id)) {
        $errors[] = 'Department is required';
    }
    
    if (empty($level_id)) {
        $errors[] = 'Level is required';
    }
    
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        } else {
            // Check if email already exists
            $db->query("SELECT COUNT(*) as count FROM students 
                        WHERE email = :email AND institution_id = :institution_id");
            $db->bind(':email', $email);
            $db->bind(':institution_id', $institution_id);
            
            if ($db->single()['count'] > 0) {
                $errors[] = 'Email already exists';
            }
        }
    }
    
    // If no errors, insert student
    if (empty($errors)) {
        $db->beginTransaction();
        
        try {
            $user_id = null;
            
            // Create user account if requested
            if ($create_login && !empty($email)) {
                $auth = new Auth();
                $default_password = strtolower($first_name) . '123'; // Default password
                
                $user_data = [
                    'institution_id' => $institution_id,
                    'username' => $matric_number,
                    'password' => $default_password,
                    'email' => $email,
                    'full_name' => $first_name . ' ' . $last_name,
                    'role' => 'student'
                ];
                
                $user_id = $auth->register($user_data);
                
                if (!$user_id) {
                    throw new Exception('Failed to create user account');
                }
            }
            
            // Insert student
            $db->query("INSERT INTO students (institution_id, department_id, level_id, user_id, matric_number, 
                        first_name, last_name, gender, date_of_birth, email, phone, address, admission_date) 
                        VALUES (:institution_id, :department_id, :level_id, :user_id, :matric_number, 
                        :first_name, :last_name, :gender, :date_of_birth, :email, :phone, :address, :admission_date)");
            
            $db->bind(':institution_id', $institution_id);
            $db->bind(':department_id', $department_id);
            $db->bind(':level_id', $level_id);
            $db->bind(':user_id', $user_id);
            $db->bind(':matric_number', strtoupper($matric_number));
            $db->bind(':first_name', $first_name);
            $db->bind(':last_name', $last_name);
            $db->bind(':gender', $gender);
            $db->bind(':date_of_birth', !empty($date_of_birth) ? $date_of_birth : null);
            $db->bind(':email', !empty($email) ? $email : null);
            $db->bind(':phone', !empty($phone) ? $phone : null);
            $db->bind(':address', !empty($address) ? $address : null);
            $db->bind(':admission_date', !empty($admission_date) ? $admission_date : null);
            
            if ($db->execute()) {
                $db->endTransaction();
                
                $success_message = 'Student added successfully.';
                if ($create_login && !empty($email)) {
                    $success_message .= ' Login credentials: Username: ' . $matric_number . ', Password: ' . $default_password;
                }
                
                set_flash_message('success', $success_message);
                redirect(ADMIN_URL . '/students/index.php');
            } else {
                throw new Exception('Failed to insert student');
            }
        } catch (Exception $e) {
            $db->cancelTransaction();
            $errors[] = 'Error adding student: ' . $e->getMessage();
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person-plus me-2"></i>Add New Student
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
                            <!-- Personal Information -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">Personal Information</h6>
                                
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">
                                        First Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="first_name" 
                                           name="first_name" required
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">
                                        Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="last_name" 
                                           name="last_name" required
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="gender" class="form-label">
                                        Gender <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" 
                                           name="date_of_birth"
                                           value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" 
                                           name="email"
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" 
                                           name="phone"
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                                </div>
                            </div>
                            
                            <!-- Academic Information -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">Academic Information</h6>
                                
                                <div class="mb-3">
                                    <label for="matric_number" class="form-label">
                                        Matriculation Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="matric_number" 
                                           name="matric_number" required style="text-transform: uppercase;"
                                           value="<?php echo isset($_POST['matric_number']) ? htmlspecialchars($_POST['matric_number']) : ''; ?>">
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
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="level_id" class="form-label">
                                        Level <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="level_id" name="level_id" required>
                                        <option value="">Select Level</option>
                                        <?php foreach ($levels as $level): ?>
                                            <option value="<?php echo $level['level_id']; ?>" 
                                                    <?php echo (isset($_POST['level_id']) && $_POST['level_id'] == $level['level_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($level['level_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="admission_date" class="form-label">Admission Date</label>
                                    <input type="date" class="form-control" id="admission_date" 
                                           name="admission_date"
                                           value="<?php echo isset($_POST['admission_date']) ? htmlspecialchars($_POST['admission_date']) : date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="create_login" 
                                               name="create_login" value="1"
                                               <?php echo (isset($_POST['create_login'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="create_login">
                                            Create student login account
                                        </label>
                                        <div class="form-text">
                                            If checked, a login account will be created using the matriculation number as username.
                                            Email is required for this option.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Students
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Add Student
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-generate matric number based on department and year
$('#department_id').on('change', function() {
    const deptSelect = $(this);
    const deptText = deptSelect.find('option:selected').text();
    const year = new Date().getFullYear().toString().substr(-2);
    
    if (deptText && deptText !== 'Select Department') {
        // Get department code (first 3 letters)
        const deptCode = deptText.split(' ').map(word => word.charAt(0)).join('').toUpperCase().substring(0, 3);
        const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
        const matricNumber = year + deptCode + randomNum;
        
        $('#matric_number').val(matricNumber);
    }
});

// Force uppercase for matric number
$('#matric_number').on('input', function() {
    $(this).val($(this).val().toUpperCase());
});

// Toggle create login checkbox based on email
$('#email').on('input', function() {
    const email = $(this).val();
    const createLoginCheckbox = $('#create_login');
    
    if (email) {
        createLoginCheckbox.prop('disabled', false);
    } else {
        createLoginCheckbox.prop('disabled', true).prop('checked', false);
    }
});

// Initial check for email field
$(document).ready(function() {
    $('#email').trigger('input');
});
</script>

<?php include_once '../includes/footer.php'; ?>