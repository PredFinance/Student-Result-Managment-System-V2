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
        // Attempt login with role validation
        if ($auth->login($username, $password, $role)) {
            start_session();
            $user_role = $_SESSION['role'];
            
            // Debug information
            error_log("User role from session: $user_role, Selected role: $role");
            
            // Role validation
            if ($role == 'student' && $user_role == 'student') {
                redirect(STUDENT_URL . '/dashboard.php');
            } else {
                redirect(ADMIN_URL . '/dashboard.php');
            }
        } else {
            $errors[] = 'Invalid username, password, or role';
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
            --gray-medium: #e9ecef;
            --gray-dark: #343a40;
            --shadow-light: rgba(0, 0, 0, 0.08);
            --shadow-medium: rgba(0, 0, 0, 0.12);
        }
        
        body {
            background-color: #ffffff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
            background-image: 
                radial-gradient(circle at 20% 80%, rgba(0, 166, 81, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(0, 166, 81, 0.03) 0%, transparent 50%);
        }
        
        .login-container {
            max-width: 480px;
            width: 100%;
            padding: 0 15px;
        }
        
        .card {
            border-radius: 20px;
            box-shadow: 0 10px 40px var(--shadow-light);
            overflow: hidden;
            border: 1px solid var(--gray-medium);
            background: #ffffff;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            text-align: center;
            padding: 40px 30px 30px;
            border-bottom: none;
            position: relative;
        }
        
        .card-header::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: 0 0 20px 20px;
        }
        
        .school-logo {
            max-width: 90px;
            height: 90px;
            margin-bottom: 20px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.2);
            object-fit: cover;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .nav-tabs {
            border-bottom: none;
            justify-content: center;
            margin-top: 25px;
            gap: 10px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 25px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .nav-tabs .nav-link:hover:not(.active) {
            color: white;
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }
        
        .card-body {
            padding: 40px 30px;
            background: #ffffff;
        }
        
        .form-control {
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            border: 2px solid var(--gray-medium);
            transition: all 0.3s ease;
            font-size: 16px;
            background: #ffffff;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 166, 81, 0.15);
            transform: translateY(-1px);
            background: #ffffff;
        }
        
        .form-control::placeholder {
            color: #6c757d;
            opacity: 0.7;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 12px;
            padding: 16px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(0, 166, 81, 0.2);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 166, 81, 0.3);
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .input-group-text {
            background: #ffffff;
            border-right: none;
            border: 2px solid var(--gray-medium);
            border-radius: 12px 0 0 12px;
            color: #6c757d;
            padding: 16px 15px;
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
            margin-bottom: 0;
        }
        
        .input-group .btn-outline-secondary {
            border: 2px solid var(--gray-medium);
            border-left: none;
            border-radius: 0 12px 12px 0;
            background: #ffffff;
            color: #6c757d;
            padding: 16px 15px;
        }
        
        .input-group .btn-outline-secondary:hover {
            background: var(--gray-light);
            border-color: var(--gray-medium);
            color: var(--primary-color);
        }
        
        .input-group {
            margin-bottom: 20px;
        }
        
        .input-group:focus-within .input-group-text,
        .input-group:focus-within .btn-outline-secondary {
            border-color: var(--primary-color);
        }
        
        .tab-content {
            padding-top: 20px;
        }
        
        .fade-in {
            animation: fadeIn 0.4s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(15px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        .system-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            letter-spacing: -0.5px;
        }
        
        .system-tagline {
            font-size: 1.1rem;
            opacity: 0.95;
            font-weight: 400;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 25px;
            padding: 16px 20px;
            box-shadow: 0 2px 10px var(--shadow-light);
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        
        .footer-text {
            text-align: center;
            margin-top: 30px;
            color: #6c757d;
            font-size: 0.9rem;
            padding: 20px;
            background: rgba(248, 249, 250, 0.8);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }
        
        /* Enhanced Mobile Responsiveness */
        @media (max-width: 576px) {
            body {
                padding: 10px 0;
            }
            
            .login-container {
                padding: 0 10px;
                max-width: 100%;
            }
            
            .card {
                border-radius: 16px;
                margin: 0;
            }
            
            .card-header {
                padding: 30px 20px 25px;
            }
            
            .card-body {
                padding: 30px 20px;
            }
            
            .system-name {
                font-size: 1.6rem;
            }
            
            .system-tagline {
                font-size: 1rem;
            }
            
            .nav-tabs .nav-link {
                padding: 10px 18px;
                font-size: 14px;
            }
            
            .form-control, .input-group-text, .btn-outline-secondary {
                padding: 14px 16px;
                font-size: 15px;
            }
            
            .btn-primary {
                padding: 14px 20px;
                font-size: 15px;
            }
            
            .school-logo {
                max-width: 70px;
                height: 70px;
                margin-bottom: 15px;
            }
        }
        
        @media (max-width: 400px) {
            .card-header {
                padding: 25px 15px 20px;
            }
            
            .card-body {
                padding: 25px 15px;
            }
            
            .system-name {
                font-size: 1.4rem;
            }
            
            .nav-tabs {
                flex-direction: column;
                gap: 8px;
            }
            
            .nav-tabs .nav-link {
                width: 100%;
                text-align: center;
            }
        }
        
        /* Tablet Responsiveness */
        @media (min-width: 577px) and (max-width: 768px) {
            .login-container {
                max-width: 520px;
            }
            
            .card-header {
                padding: 45px 35px 35px;
            }
            
            .card-body {
                padding: 45px 35px;
            }
        }
        
        /* Large Screen Optimization */
        @media (min-width: 1200px) {
            .login-container {
                max-width: 500px;
            }
            
            .card {
                box-shadow: 0 15px 50px var(--shadow-medium);
            }
        }
        
        /* High DPI Displays */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .school-logo {
                image-rendering: -webkit-optimize-contrast;
                image-rendering: crisp-edges;
            }
        }
        
        /* Focus and Accessibility Improvements */
        .nav-tabs .nav-link:focus,
        .btn:focus,
        .form-control:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
        
        /* Loading State */
        .btn-primary:disabled {
            opacity: 0.8;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Smooth Transitions */
        * {
            transition: border-color 0.3s ease, box-shadow 0.3s ease, transform 0.3s ease;
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
                                <input type="text" class="form-control" name="username" placeholder="Enter your username" required 
                                       value="<?php echo isset($_POST['username']) && $_POST['role'] == 'admin' ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                            
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
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
                                <input type="text" class="form-control" name="username" placeholder="Enter your matric number" required
                                       value="<?php echo isset($_POST['username']) && $_POST['role'] == 'student' ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                            
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" name="password" placeholder="Enter your password" required>
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
                    }, 400);
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
            
            // Enhanced form validation
            $('input[required]').on('blur', function() {
                if ($(this).val().trim() === '') {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                }
            });
            
            // Auto-hide alerts
            setTimeout(function() {
                $('.alert').fadeOut('slow');
            }, 8000);
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
        
        // Keyboard navigation enhancement
        $(document).keydown(function(e) {
            if (e.key === 'Tab') {
                $(':focus').addClass('keyboard-focus');
            }
        });
        
        $(document).mousedown(function() {
            $('.keyboard-focus').removeClass('keyboard-focus');
        });
    </script>
</body>
</html>
