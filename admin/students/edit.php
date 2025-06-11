<?php
$page_title = "Edit Student";
$breadcrumb = "Students > Edit Student";

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
$db->query("SELECT * FROM students WHERE student_id = :student_id AND institution_id = :institution_id");
$db->bind(':student_id', $student_id);
$db->bind(':institution_id', $institution_id);
$student = $db->single();

if (!$student) {
    set_flash_message('danger', 'Student not found');
    redirect(ADMIN_URL . '/students/index.php');
}

// Get departments
$db->query("SELECT * FROM departments WHERE institution_id = :institution_id ORDER BY department_name");
$db->bind(':institution_id', $institution_id);
$departments = $db->resultSet();

// Get levels
$db->query("SELECT * FROM levels WHERE institution_id = :institution_id ORDER BY level_name");
$db->bind(':institution_id', $institution_id);
$levels = $db->resultSet();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $matric_number = clean_input($_POST['matric_number']);
    $department_id = clean_input($_POST['department_id']);
    $level_id = clean_input($_POST['level_id']);
    $gender = clean_input($_POST['gender']);
    $date_of_birth = clean_input($_POST['date_of_birth']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $address = clean_input($_POST['address']);
    $admission_date = clean_input($_POST['admission_date']);
    
    $errors = [];
    
    // Validate required fields
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($matric_number)) $errors[] = 'Matriculation number is required';
    if (empty($department_id)) $errors[] = 'Department is required';
    if (empty($level_id)) $errors[] = 'Level is required';
    if (empty($gender)) $errors[] = 'Gender is required';
    
    // Check if matric number already exists (excluding current student)
    if (!empty($matric_number)) {
        $db->query("SELECT student_id FROM students WHERE matric_number = :matric_number AND institution_id = :institution_id AND student_id != :student_id");
        $db->bind(':matric_number', $matric_number);
        $db->bind(':institution_id', $institution_id);
        $db->bind(':student_id', $student_id);
        
        if ($db->single()) {
            $errors[] = 'Matriculation number already exists';
        }
    }
    
    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Check if email already exists (excluding current student)
    if (!empty($email)) {
        $db->query("SELECT student_id FROM students WHERE email = :email AND institution_id = :institution_id AND student_id != :student_id");
        $db->bind(':email', $email);
        $db->bind(':institution_id', $institution_id);
        $db->bind(':student_id', $student_id);
        
        if ($db->single()) {
            $errors[] = 'Email already exists';
        }
    }
    
    // If no errors, update student
    if (empty($errors)) {
        $db->query("UPDATE students SET 
                    first_name = :first_name,
                    last_name = :last_name,
                    matric_number = :matric_number,
                    department_id = :department_id,
                    level_id = :level_id,
                    gender = :gender,
                    date_of_birth = :date_of_birth,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    admission_date = :admission_date,
                    updated_at = NOW()
                    WHERE student_id = :student_id AND institution_id = :institution_id");
        
        $db->bind(':first_name', $first_name);
        $db->bind(':last_name', $last_name);
        $db->bind(':matric_number', $matric_number);
        $db->bind(':department_id', $department_id);
        $db->bind(':level_id', $level_id);
        $db->bind(':gender', $gender);
        $db->bind(':date_of_birth', !empty($date_of_birth) ? $date_of_birth : null);
        $db->bind(':email', !empty($email) ? $email : null);
        $db->bind(':phone', !empty($phone) ? $phone : null);
        $db->bind(':address', !empty($address) ? $address : null);
        $db->bind(':admission_date', !empty($admission_date) ? $admission_date : null);
        $db->bind(':student_id', $student_id);
        $db->bind(':institution_id', $institution_id);
        
        if ($db->execute()) {
            set_flash_message('success', 'Student updated successfully.');
            redirect(ADMIN_URL . '/students/view.php?id=' . $student_id);
        } else {
            $errors[] = 'Error updating student. Please try again.';
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
                        <i class="bi bi-pencil-square me-2"></i>Edit Student
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
                                    <label for="first_name" class="form-label">
                                        First Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="first_name" 
                                           name="first_name" required
                                           value="<?php echo htmlspecialchars($student['first_name']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">
                                        Last Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="last_name" 
                                           name="last_name" required
                                           value="<?php echo htmlspecialchars($student['last_name']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="matric_number" class="form-label">
                                        Matriculation Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="matric_number" 
                                           name="matric_number" required
                                           value="<?php echo htmlspecialchars($student['matric_number']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="gender" class="form-label">
                                        Gender <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo ($student['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo ($student['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo ($student['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department_id" class="form-label">
                                        Department <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="department_id" name="department_id" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['department_id']; ?>" 
                                                    <?php echo ($student['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="level_id" class="form-label">
                                        Level <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="level_id" name="level_id" required>
                                        <option value="">Select Level</option>
                                        <?php foreach ($levels as $level): ?>
                                            <option value="<?php echo $level['level_id']; ?>" 
                                                    <?php echo ($student['level_id'] == $level['level_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($level['level_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" 
                                           name="date_of_birth"
                                           value="<?php echo $student['date_of_birth']; ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="admission_date" class="form-label">Admission Date</label>
                                    <input type="date" class="form-control" id="admission_date" 
                                           name="admission_date"
                                           value="<?php echo $student['admission_date']; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" 
                                           name="email"
                                           value="<?php echo htmlspecialchars($student['email']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" 
                                           name="phone"
                                           value="<?php echo htmlspecialchars($student['phone']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($student['address']); ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Profile
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Update Student
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>