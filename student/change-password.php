<?php
$page_title = "Change Password";
$breadcrumb = "Change Password";

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = clean_input($_POST['current_password']);
    $new_password = clean_input($_POST['new_password']);
    $confirm_password = clean_input($_POST['confirm_password']);
    
    // Validation
    $errors = [];
    
    if (empty($current_password)) $errors[] = 'Current password is required';
    if (empty($new_password)) $errors[] = 'New password is required';
    if (empty($confirm_password)) $errors[] = 'Password confirmation is required';
    
    if (strlen($new_password) < 6) $errors[] = 'New password must be at least 6 characters long';
    if ($new_password !== $confirm_password) $errors[] = 'New password and confirmation do not match';
    
    if (empty($errors)) {
        // Verify current password
        $db->query("SELECT password_hash FROM users WHERE user_id = :user_id");
        $db->bind(':user_id', $student_id);
        $user = $db->single();
        
        if ($user && verify_password($current_password, $user['password_hash'])) {
            // Update password
            $new_password_hash = hash_password($new_password);
            
            $db->query("UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE user_id = :user_id");
            $db->bind(':password_hash', $new_password_hash);
            $db->bind(':user_id', $student_id);
            
            if ($db->execute()) {
                // Log activity
                log_activity($student_id, 'password_change', 'Student changed password');
                
                set_flash_message('success', 'Password changed successfully!');
                redirect(STUDENT_URL . '/profile.php');
            } else {
                set_flash_message('danger', 'Error updating password. Please try again.');
            }
        } else {
            set_flash_message('danger', 'Current password is incorrect');
        }
    } else {
        set_flash_message('danger', implode('<br>', $errors));
    }
}

// Include header
include_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-shield-lock me-2"></i>Change Password
                    </h5>
                </div>
                
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Password Requirements:</strong>
                        <ul class="mb-0 mt-2">
                            <li>At least 6 characters long</li>
                            <li>Use a combination of letters, numbers, and symbols</li>
                            <li>Avoid using personal information</li>
                        </ul>
                    </div>
                    
                    <form method="POST" id="passwordForm">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password *</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" 
                                        onclick="togglePassword('current_password')" style="border: none; background: none;">
                                    <i class="bi bi-eye" id="current_password_toggle"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password *</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" 
                                        onclick="togglePassword('new_password')" style="border: none; background: none;">
                                    <i class="bi bi-eye" id="new_password_toggle"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                <div id="password_strength" class="mt-2"></div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y" 
                                        onclick="togglePassword('confirm_password')" style="border: none; background: none;">
                                    <i class="bi bi-eye" id="confirm_password_toggle"></i>
                                </button>
                            </div>
                            <div id="password_match" class="form-text"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="profile.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Profile
                            </a>
                            <button type="submit" class="btn btn-primary" id="submit_btn">
                                <i class="bi bi-shield-check me-2"></i>Change Password
                                <span class="spinner-border spinner-border-sm ms-2" id="submit_spinner" style="display: none;"></span>
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
function togglePassword(fieldId) {
    const passwordInput = document.getElementById(fieldId);
    const toggleIcon = document.getElementById(fieldId + '_toggle');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'bi bi-eye-slash';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'bi bi-eye';
    }
}

// Password strength checker
document.getElementById('new_password').addEventListener('input', function(e) {
    const password = e.target.value;
    const strengthDiv = document.getElementById('password_strength');
    
    if (password.length === 0) {
        strengthDiv.innerHTML = '';
        return;
    }
    
    let strength = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 6) strength++;
    else feedback.push('At least 6 characters');
    
    // Uppercase check
    if (/[A-Z]/.test(password)) strength++;
    else feedback.push('One uppercase letter');
    
    // Lowercase check
    if (/[a-z]/.test(password)) strength++;
    else feedback.push('One lowercase letter');
    
    // Number check
    if (/\d/.test(password)) strength++;
    else feedback.push('One number');
    
    // Special character check
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
    else feedback.push('One special character');
    
    let strengthText = '';
    let strengthClass = '';
    
    if (strength <= 2) {
        strengthText = 'Weak';
        strengthClass = 'text-danger';
    } else if (strength <= 3) {
        strengthText = 'Fair';
        strengthClass = 'text-warning';
    } else if (strength <= 4) {
        strengthText = 'Good';
        strengthClass = 'text-info';
    } else {
        strengthText = 'Strong';
        strengthClass = 'text-success';
    }
    
    strengthDiv.innerHTML = `
        <small class="${strengthClass}">
            <strong>Password Strength: ${strengthText}</strong>
            ${feedback.length > 0 ? '<br>Missing: ' + feedback.join(', ') : ''}
        </small>
    `;
});

// Password match checker
document.getElementById('confirm_password').addEventListener('input', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = e.target.value;
    const matchDiv = document.getElementById('password_match');
    
    if (confirmPassword.length === 0) {
        matchDiv.innerHTML = '';
        return;
    }
    
    if (newPassword === confirmPassword) {
        matchDiv.innerHTML = '<small class="text-success"><i class="bi bi-check-circle me-1"></i>Passwords match</small>';
        e.target.classList.remove('is-invalid');
        e.target.classList.add('is-valid');
    } else {
        matchDiv.innerHTML = '<small class="text-danger"><i class="bi bi-x-circle me-1"></i>Passwords do not match</small>';
        e.target.classList.remove('is-valid');
        e.target.classList.add('is-invalid');
    }
});

// Form submission with loading state
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return;
    }
    
    document.getElementById('submit_spinner').style.display = 'inline-block';
    document.getElementById('submit_btn').disabled = true;
});
</script>

<?php include_once 'includes/footer.php'; ?>
