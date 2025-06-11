<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if already logged in
if (is_logged_in()) {
    if (has_role('student')) {
        redirect(STUDENT_URL . '/dashboard.php');
    } else {
        redirect(ADMIN_URL . '/dashboard.php');
    }
}

// Initialize Auth class
$auth = new Auth();
$errors = [];

// Process login form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    // Debug information (remove after fixing)
    error_log("Login attempt - Username: $username, Role: $role");
    
    // Validate input
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    if (empty($role)) {
        $errors[] = 'Role is required';
    }
    
    // If no errors, attempt login
    if (empty($errors)) {
        // Try login without role restriction first
        if ($auth->login($username, $password)) {
            // Check if the logged-in user's role matches the selected role
            start_session();
            $user_role = $_SESSION['role'];
            
            // Debug information
            error_log("User role from session: $user_role, Selected role: $role");
            
            // Role validation
            if ($role == 'student' && $user_role == 'student') {
                redirect(STUDENT_URL . '/dashboard.php');
            } elseif ($role == 'admin' && in_array($user_role, ['admin', 'super_admin'])) {
                redirect(ADMIN_URL . '/dashboard.php');
            } else {
                // Role mismatch
                $auth->logout();
                $errors[] = 'Invalid role selected for this account';
            }
        } else {
            $errors[] = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #00A651;
            --secondary-color: #FFFFFF;
            --primary-dark: #008a43;
            --primary-light: #4cd488;
            --gray-light: #f8f9fa;
            --gray-dark: #343a40;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 15px;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: none;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            text-align: center;
            padding: 30px 20px;
            border-bottom: none;
        }
        
        .school-logo {
            max-width: 80px;
            margin-bottom: 15px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .nav-tabs {
            border-bottom: none;
            justify-content: center;
            margin-top: 20px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            padding: 12px 25px;
            border-radius: 25px;
            position: relative;
            transition: all 0.3s ease;
            margin: 0 5px;
        }
        
        .nav-tabs .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .nav-tabs .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .card-body {
            padding: 40px 30px;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            font-size: 16px;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 166, 81, 0.25);
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 16px;
        }
        
        .btn-primary:hover, .btn-primary:focus {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 166, 81, 0.3);
        }
        
        .input-group-text {
            background: transparent;
            border-right: none;
            border: 2px solid #e9ecef;
            border-radius: 10px 0 0 10px;
            color: #6c757d;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
            margin-bottom: 0;
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .tab-content {
            padding-top: 20px;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .system-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .system-tagline {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 20px;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }
        
        @media (max-width: 576px) {
            .login-container {
                padding: 10px;
            }
            
            .card-body {
                padding: 30px 20px;
            }
            
            .system-name {
                font-size: 1.5rem;
            }
            
            .nav-tabs .nav-link {
                padding: 10px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <img src="assets/images/logo.png" alt="LUFEM School" class="school-logo" onerror="this.style.display='none'">
                <h1 class="system-name">LUFEM School</h1>
                <p class="system-tagline">Student Results Management System</p>
                
                <ul class="nav nav-tabs" id="loginTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin-login" type="button" role="tab" aria-controls="admin-login" aria-selected="true">
                            <i class="bi bi-shield-check me-2"></i>Admin Login
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="student-tab" data-bs-toggle="tab" data-bs-target="#student-login" type="button" role="tab" aria-controls="student-login" aria-selected="false">
                            <i class="bi bi-person-badge me-2"></i>Student Login
                        </button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?php foreach ($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="tab-content" id="loginTabsContent">
                    <!-- Admin Login Form -->
                    <div class="tab-pane fade show active fade-in" id="admin-login" role="tabpanel" aria-labelledby="admin-tab">
                        <form method="post" action="" id="adminLoginForm">
                            <input type="hidden" name="role" value="admin">
                            
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" name="username" placeholder="Username" required 
                                       value="<?php echo isset($_POST['username']) && $_POST['role'] == 'admin' ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                            
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" name="password" placeholder="Password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i> Login as Admin
                            </button>
                        </form>
                    </div>
                    
                    <!-- Student Login Form -->
                    <div class="tab-pane fade fade-in" id="student-login" role="tabpanel" aria-labelledby="student-tab">
                        <form method="post" action="" id="studentLoginForm">
                            <input type="hidden" name="role" value="student">
                            
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person-badge"></i></span>
                                <input type="text" class="form-control" name="username" placeholder="Matric Number" required
                                       value="<?php echo isset($_POST['username']) && $_POST['role'] == 'student' ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                            
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" name="password" placeholder="Password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword(this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i> Login as Student
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-text">
            <small>&copy; <?php echo date('Y'); ?> LUFEM School. All rights reserved.</small>
        </div>
    </div>
    
    <!-- Bootstrap 5.3.3 JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery 3.7.1 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Add animation when switching tabs
            $('#loginTabs button').on('click', function() {
                const targetId = $(this).data('bs-target');
                setTimeout(function() {
                    $(targetId).addClass('fade-in');
                    setTimeout(function() {
                        $(targetId).removeClass('fade-in');
                    }, 500);
                }, 150);
            });
            
            // Form submission with loading state
            $('form').on('submit', function() {
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                
                submitBtn.prop('disabled', true);
                submitBtn.html('<span class="spinner-border spinner-border-sm me-2" role="status"></span> Logging in...');
                
                // Re-enable after 10 seconds (fallback)
                setTimeout(function() {
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                }, 10000);
            });
        });
        
        function togglePassword(button) {
            const input = $(button).siblings('input[type="password"], input[type="text"]');
            const icon = $(button).find('i');
            
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                icon.removeClass('bi-eye').addClass('bi-eye-slash');
            } else {
                input.attr('type', 'password');
                icon.removeClass('bi-eye-slash').addClass('bi-eye');
            }
        }
    </script>
</body>
</html>