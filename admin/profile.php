<?php
$page_title = "My Profile";
$breadcrumb = "Profile";

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Initialize database connection
$db = new Database();
$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = clean_input($_POST['full_name']);
    $email = clean_input($_POST['email']);
    $current_password = clean_input($_POST['current_password']);
    $new_password = clean_input($_POST['new_password']);
    $confirm_password = clean_input($_POST['confirm_password']);
    
    $errors = [];
    
    // Validate required fields
    if (empty($full_name)) $errors[] = 'Full name is required';
    if (empty($email)) $errors[] = 'Email is required';
    
    // Validate email format
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Check if email already exists (excluding current user)
    if (!empty($email)) {
        $db->query("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id");
        $db->bind(':email', $email);
        $db->bind(':user_id', $user_id);
        
        if ($db->single()) {
            $errors[] = 'Email already exists';
        }
    }
    
    // Password validation if changing password
    if (!empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = 'Current password is required to change password';
        } else {
            // Verify current password
            $db->query("SELECT password FROM users WHERE user_id = :user_id");
            $db->bind(':user_id', $user_id);
            $user = $db->single();
            
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = 'Current password is incorrect';
            }
        }
        
        if (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'Password confirmation does not match';
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        if (!empty($new_password)) {
            // Update with new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $db->query("UPDATE users SET 
                        full_name = :full_name, 
                        email = :email, 
                        password = :password, 
                        updated_at = NOW() 
                        WHERE user_id = :user_id");
            $db->bind(':password', $hashed_password);
        } else {
            // Update without password change
            $db->query("UPDATE users SET 
                        full_name = :full_name, 
                        email = :email, 
                        updated_at = NOW() 
                        WHERE user_id = :user_id");
        }
        
        $db->bind(':full_name', $full_name);
        $db->bind(':email', $email);
        $db->bind(':user_id', $user_id);
        
        if ($db->execute()) {
            // Update session data
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            
            set_flash_message('success', 'Profile updated successfully.');
            redirect($_SERVER['PHP_SELF']);
        } else {
            $errors[] = 'Error updating profile. Please try again.';
        }
    }
}

// Get current user data
$db->query("SELECT * FROM users WHERE user_id = :user_id");
$db->bind(':user_id', $user_id);
$user = $db->single();

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-person-gear me-2"></i>My Profile
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
                            <label for="full_name" class="form-label">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="full_name" 
                                   name="full_name" required
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" class="form-control" id="email" 
                                   name="email" required
                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" readonly
                                   value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>">
                        </div>
                        
                        <hr>
                        
                        <h6 class="mb-3">Change Password (Optional)</h6>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" 
                                   name="current_password">
                            <div class="form-text">Required only if changing password</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" 
                                           name="new_password" minlength="6">
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           name="confirm_password" minlength="6">
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>Account Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Account Created:</strong><br>
                            <?php echo date('F j, Y g:i A', strtotime($user['created_at'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Last Updated:</strong><br>
                            <?php echo $user['updated_at'] ? date('F j, Y g:i A', strtotime($user['updated_at'])) : 'Never'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
$('#confirm_password').on('input', function() {
    const newPassword = $('#new_password').val();
    const confirmPassword = $(this).val();
    
    if (confirmPassword && newPassword !== confirmPassword) {
        $(this).addClass('is-invalid');
        if (!$(this).next('.invalid-feedback').length) {
            $(this).after('<div class="invalid-feedback">Passwords do not match</div>');
        }
    } else {
        $(this).removeClass('is-invalid');
        $(this).next('.invalid-feedback').remove();
    }
});

// New password validation
$('#new_password').on('input', function() {
    const password = $(this).val();
    
    if (password && password.length < 6) {
        $(this).addClass('is-invalid');
        if (!$(this).next('.invalid-feedback').length) {
            $(this).after('<div class="invalid-feedback">Password must be at least 6 characters</div>');
        }
    } else {
        $(this).removeClass('is-invalid');
        $(this).next('.invalid-feedback').remove();
    }
    
    // Also validate confirm password
    $('#confirm_password').trigger('input');
});
</script>

<?php include_once 'includes/footer.php'; ?>