<?php
$page_title = "Add Admin";
$breadcrumb = "Settings > Admin Management > Add New";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is super admin
if (!is_logged_in() || $_SESSION['role'] !== 'super_admin') {
    set_flash_message('danger', 'You must be logged in as super admin to access this page');
    redirect(BASE_URL);
}

// Initialize database connection
$db = new Database();
$auth = new Auth();
$institution_id = get_institution_id();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = clean_input($_POST['full_name']);
    $username = clean_input($_POST['username']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = clean_input($_POST['role']);
    
    // Validation
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($username)) {
        $errors[] = 'Username is required';
    } else {
        // Check if username already exists
        if ($auth->username_exists($username)) {
            $errors[] = 'Username already exists';
        }
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        } else {
            // Check if email already exists
            if ($auth->email_exists($email)) {
                $errors[] = 'Email already exists';
            }
        }
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } else {
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters long';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
    }
    
    if (empty($role) || !in_array($role, ['admin', 'super_admin'])) {
        $errors[] = 'Valid role is required';
    }
    
    // If no errors, create admin
    if (empty($errors)) {
        $user_data = [
            'institution_id' => $institution_id,
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'full_name' => $full_name,
            'role' => $role
        ];
        
        $user_id = $auth->register($user_data);
        
        if ($user_id) {
            set_flash_message('success', 'Admin created successfully.');
            redirect(ADMIN_URL . '/settings/admins.php');
        } else {
            $errors[] = 'Error creating admin. Please try again.';
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
                        <i class="bi bi-person-plus me-2"></i>Add New Admin
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
                                    <label for="full_name" class="form-label">
                                        Full Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="full_name" 
                                           name="full_name" required
                                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                                           placeholder="e.g., John Smith">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">
                                        Username <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="username" 
                                           name="username" required
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                                           placeholder="e.g., johnsmith">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control" id="email" 
                                   name="email" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   placeholder="e.g., john@example.com">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">
                                        Password <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" 
                                               name="password" required minlength="6"
                                               placeholder="Minimum 6 characters">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">
                                        Confirm Password <span class="text-danger">*</span>
                                    </label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" required minlength="6"
                                           placeholder="Repeat password">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>
                                    Admin - Can manage students, courses, and results
                                </option>
                                <option value="super_admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'super_admin') ? 'selected' : ''; ?>>
                                    Super Admin - Full system access including admin management
                                </option>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Role Permissions:</strong>
                            <ul class="mb-0 mt-2">
                                <li><strong>Admin:</strong> Can manage students, departments, courses, and results</li>
                                <li><strong>Super Admin:</strong> All admin permissions plus user management and system settings</li>
                            </ul>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="admins.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Admin List
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Create Admin
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
$('#togglePassword').on('click', function() {
    const passwordField = $('#password');
    const icon = $(this).find('i');
    
    if (passwordField.attr('type') === 'password') {
        passwordField.attr('type', 'text');
        icon.removeClass('bi-eye').addClass('bi-eye-slash');
    } else {
        passwordField.attr('type', 'password');
        icon.removeClass('bi-eye-slash').addClass('bi-eye');
    }
});

// Auto-generate username from full name
$('#full_name').on('input', function() {
    const fullName = $(this).val();
    const username = fullName.toLowerCase()
                            .replace(/[^a-z0-9\s]/g, '')
                            .replace(/\s+/g, '')
                            .substring(0, 20);
    $('#username').val(username);
});

// Password strength indicator
$('#password').on('input', function() {
    const password = $(this).val();
    let strength = 0;
    
    if (password.length >= 6) strength++;
    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.match(/[^a-zA-Z0-9]/)) strength++;
    
    let strengthText = '';
    let strengthClass = '';
    
    switch (strength) {
        case 0:
        case 1:
            strengthText = 'Very Weak';
            strengthClass = 'text-danger';
            break;
        case 2:
            strengthText = 'Weak';
            strengthClass = 'text-warning';
            break;
        case 3:
            strengthText = 'Fair';
            strengthClass = 'text-info';
            break;
        case 4:
            strengthText = 'Good';
            strengthClass = 'text-primary';
            break;
        case 5:
            strengthText = 'Strong';
            strengthClass = 'text-success';
            break;
    }
    
    // Remove existing strength indicator
    $('#password').next('.password-strength').remove();
    
    // Add strength indicator
    if (password.length > 0) {
        $('#password').after(`<small class="password-strength ${strengthClass}">Password Strength: ${strengthText}</small>`);
    }
});

// Confirm password validation
$('#confirm_password').on('input', function() {
    const password = $('#password').val();
    const confirmPassword = $(this).val();
    
    // Remove existing validation message
    $(this).next('.password-match').remove();
    
    if (confirmPassword.length > 0) {
        if (password === confirmPassword) {
            $(this).after('<small class="password-match text-success">Passwords match</small>');
        } else {
            $(this).after('<small class="password-match text-danger">Passwords do not match</small>');
        }
    }
});
</script>

<?php include_once '../includes/footer.php'; ?>