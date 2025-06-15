<?php
$page_title = "My Profile";
$breadcrumb = "My Profile";

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is student
if (!is_logged_in() || $_SESSION['role'] != 'student') {
    set_flash_message('danger', 'You must be logged in as a student to access this page');
    redirect(BASE_URL);
}

$db = new Database();
$student_id = $_SESSION['user_id'];
$institution_id = get_institution_id();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = clean_input($_POST['first_name']);
    $last_name = clean_input($_POST['last_name']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $address = clean_input($_POST['address']);
    $date_of_birth = clean_input($_POST['date_of_birth']);
    $gender = clean_input($_POST['gender']);
    
    // Validation
    $errors = [];
    
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!validate_email($email)) $errors[] = 'Invalid email format';
    if (!empty($phone) && !validate_phone($phone)) $errors[] = 'Invalid phone number format';
    
    // Check if email is already taken by another student
    $db->query("SELECT student_id FROM students WHERE email = :email AND student_id != :student_id AND institution_id = :institution_id");
    $db->bind(':email', $email);
    $db->bind(':student_id', $student_id);
    $db->bind(':institution_id', $institution_id);
    if ($db->single()) {
        $errors[] = 'Email address is already taken by another student';
    }
    
    if (empty($errors)) {
        $db->beginTransaction();
        
        try {
            // Update student record
            $db->query("UPDATE students SET 
                        first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        phone = :phone,
                        address = :address,
                        date_of_birth = :date_of_birth,
                        gender = :gender,
                        updated_at = NOW()
                        WHERE student_id = :student_id AND institution_id = :institution_id");
            
            $db->bind(':first_name', $first_name);
            $db->bind(':last_name', $last_name);
            $db->bind(':email', $email);
            $db->bind(':phone', $phone);
            $db->bind(':address', $address);
            $db->bind(':date_of_birth', $date_of_birth);
            $db->bind(':gender', $gender);
            $db->bind(':student_id', $student_id);
            $db->bind(':institution_id', $institution_id);
            $db->execute();
            
            // Update users table email
            $db->query("UPDATE users SET email = :email WHERE user_id = :user_id");
            $db->bind(':email', $email);
            $db->bind(':user_id', $student_id);
            $db->execute();
            
            $db->endTransaction();
            
            // Update session
            $_SESSION['full_name'] = $first_name . ' ' . $last_name;
            
            // Log activity
            log_activity($student_id, 'profile_update', 'Student updated profile information');
            
            set_flash_message('success', 'Profile updated successfully!');
            redirect(STUDENT_URL . '/profile.php');
            
        } catch (Exception $e) {
            $db->cancelTransaction();
            set_flash_message('danger', 'Error updating profile: ' . $e->getMessage());
        }
    } else {
        set_flash_message('danger', implode('<br>', $errors));
    }
}

// Get student information
$db->query("SELECT s.*, d.department_name, l.level_name
            FROM students s
            JOIN departments d ON s.department_id = d.department_id
            JOIN levels l ON s.level_id = l.level_id
            WHERE s.student_id = :student_id");
$db->bind(':student_id', $student_id);
$student_info = $db->single();

if (!$student_info) {
    set_flash_message('danger', 'Student record not found');
    redirect(STUDENT_URL . '/dashboard.php');
}

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person-circle me-2"></i>Profile Overview
                    </h5>
                </div>
                <div class="card-body text-center">
                    <div class="profile-avatar mb-3">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                             style="width: 100px; height: 100px; font-size: 2rem; font-weight: 600;">
                            <?php echo strtoupper(substr($student_info['first_name'], 0, 1) . substr($student_info['last_name'], 0, 1)); ?>
                        </div>
                    </div>
                    <h4><?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($student_info['matric_number']); ?></p>
                    
                    <div class="row text-center mt-4">
                        <div class="col-6">
                            <div class="border-end">
                                <h6 class="text-primary"><?php echo htmlspecialchars($student_info['department_name']); ?></h6>
                                <small class="text-muted">Department</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h6 class="text-success"><?php echo htmlspecialchars($student_info['level_name']); ?></h6>
                            <small class="text-muted">Level</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Account Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">Account Information</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Status:</strong></div>
                        <div class="col-sm-6">
                            <span class="badge bg-<?php echo $student_info['status'] == 'Active' ? 'success' : 'secondary'; ?>">
                                <?php echo $student_info['status']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Admission Date:</strong></div>
                        <div class="col-sm-6"><?php echo format_date($student_info['admission_date']); ?></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-6"><strong>Created:</strong></div>
                        <div class="col-sm-6"><?php echo format_date($student_info['created_at']); ?></div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6"><strong>Last Updated:</strong></div>
                        <div class="col-sm-6"><?php echo format_date($student_info['updated_at']); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Edit Profile Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil-square me-2"></i>Edit Profile
                    </h5>
                </div>
                
                <div class="card-body">
                    <form method="POST" id="profileForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($student_info['first_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($student_info['last_name']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($student_info['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($student_info['phone']); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo $student_info['date_of_birth']; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo ($student_info['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo ($student_info['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo ($student_info['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($student_info['address']); ?></textarea>
                        </div>
                        
                        <!-- Read-only fields -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Matric Number</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($student_info['matric_number']); ?>" readonly>
                                    <small class="text-muted">This field cannot be changed</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($student_info['department_name']); ?>" readonly>
                                    <small class="text-muted">Contact admin to change department</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <div>
                                <a href="change-password.php" class="btn btn-outline-warning me-2">
                                    <i class="bi bi-shield-lock me-2"></i>Change Password
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-2"></i>Update Profile
                                    <span class="spinner-border spinner-border-sm ms-2" id="submit_spinner" style="display: none;"></span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form submission with loading state
document.getElementById('profileForm').addEventListener('submit', function() {
    document.getElementById('submit_spinner').style.display = 'inline-block';
});

// Phone number formatting
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 0) {
        if (value.length <= 11) {
            value = value.replace(/(\d{4})(\d{3})(\d{4})/, '$1-$2-$3');
        }
    }
    e.target.value = value;
});

// Email validation
document.getElementById('email').addEventListener('blur', function(e) {
    const email = e.target.value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        e.target.classList.add('is-invalid');
        if (!document.querySelector('.email-error')) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback email-error';
            errorDiv.textContent = 'Please enter a valid email address';
            e.target.parentNode.appendChild(errorDiv);
        }
    } else {
        e.target.classList.remove('is-invalid');
        const errorDiv = document.querySelector('.email-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
});
</script>

<?php include_once 'includes/footer.php'; ?>
