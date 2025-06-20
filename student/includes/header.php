<?php
// Start session if not already started
start_session();

// Check if user is logged in
if (!is_logged_in()) {
    redirect(BASE_URL);
}

// Get user info
$user_id = get_user_id();
$db = new Database();
$db->query("SELECT * FROM users WHERE user_id = :user_id");
$db->bind(':user_id', $user_id);
$user = $db->single();

// Get student info
$db->query("SELECT s.*, d.department_name, l.level_name
            FROM students s
            JOIN departments d ON s.department_id = d.department_id
            JOIN levels l ON s.level_id = l.level_id
            WHERE s.student_id = :student_id");
$db->bind(':student_id', $user_id);
$student = $db->single();

// Get institution info
$institution_id = get_institution_id();
$db->query("SELECT * FROM institutions WHERE institution_id = :institution_id");
$db->bind(':institution_id', $institution_id);
$institution = $db->single();

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to darken color
function darken_color($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, min(255, $r - ($r * $percent / 100)));
    $g = max(0, min(255, $g - ($g * $percent / 100)));
    $b = max(0, min(255, $b - ($b * $percent / 100)));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Helper function to lighten color
function lighten_color($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = min(255, $r + ((255 - $r) * $percent / 100));
    $g = min(255, $g + ((255 - $g) * $percent / 100));
    $b = min(255, $b + ((255 - $b) * $percent / 100));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: <?php echo $institution['primary_color'] ?? '#00A651'; ?>;
            --secondary-color: <?php echo $institution['secondary_color'] ?? '#FFFFFF'; ?>;
            --primary-dark: <?php echo darken_color($institution['primary_color'] ?? '#00A651', 15); ?>;
            --primary-light: <?php echo lighten_color($institution['primary_color'] ?? '#00A651', 30); ?>;
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --topbar-height: 60px;
            --transition-speed: 0.3s;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        #sidebar-wrapper {
            min-height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 999;
            transition: all var(--transition-speed) ease;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }
        
        #sidebar-wrapper.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar-heading {
            padding: 15px;
            text-align: center;
            font-weight: 700;
            font-size: 1.2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            white-space: nowrap;
            transition: all var(--transition-speed) ease;
        }
        
        .sidebar-heading img {
            max-height: 35px;
            margin-right: 10px;
            transition: all var(--transition-speed) ease;
        }
        
        #sidebar-wrapper.collapsed .sidebar-heading {
            padding: 15px 5px;
        }
        
        #sidebar-wrapper.collapsed .sidebar-heading img {
            margin-right: 0;
        }
        
        #sidebar-wrapper.collapsed .sidebar-heading .school-name {
            display: none;
        }
        
        .list-group {
            width: 100%;
            padding: 10px 0;
        }
        
        .list-group-item {
            background-color: transparent;
            color: rgba(255, 255, 255, 0.8);
            border: none;
            padding: 12px 20px;
            font-weight: 500;
            border-radius: 0;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            overflow: hidden;
            white-space: nowrap;
            text-decoration: none;
            position: relative;
        }
        
        .list-group-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
        }
        
        .list-group-item.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
        }
        
        .list-group-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: white;
        }
        
        .list-group-item i {
            margin-right: 15px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
            transition: all var(--transition-speed) ease;
        }
        
        #sidebar-wrapper.collapsed .list-group-item {
            padding: 12px 10px;
            justify-content: center;
        }
        
        #sidebar-wrapper.collapsed .list-group-item i {
            margin-right: 0;
        }
        
        #sidebar-wrapper.collapsed .list-group-item .menu-text {
            display: none;
        }
        
        .sidebar-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin: 10px 15px;
        }
        
        /* Content */
        #page-content-wrapper {
            width: calc(100% - var(--sidebar-width));
            margin-left: var(--sidebar-width);
            transition: all var(--transition-speed) ease;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        #page-content-wrapper.expanded {
            width: calc(100% - var(--sidebar-collapsed-width));
            margin-left: var(--sidebar-collapsed-width);
        }
        
        /* Topbar */
        .topbar {
            height: var(--topbar-height);
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .toggle-sidebar {
            background: none;
            border: none;
            color: #555;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 5px;
            transition: all 0.2s ease;
        }
        
        .toggle-sidebar:hover {
            background-color: #f0f0f0;
            color: var(--primary-color);
        }
        
        .breadcrumb-nav {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .breadcrumb-nav a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .breadcrumb-nav a:hover {
            text-decoration: underline;
        }
        
        .user-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .user-dropdown-toggle {
            background: none;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.2s ease;
            color: #333;
        }
        
        .user-dropdown-toggle:hover {
            background-color: #f0f0f0;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .user-dropdown-menu {
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            min-width: 200px;
            z-index: 1000;
            display: none;
            border: 1px solid #eee;
            margin-top: 5px;
        }
        
        .user-dropdown-menu.show {
            display: block;
            animation: fadeInDown 0.3s ease;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .user-dropdown-item {
            padding: 12px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #333;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .user-dropdown-item:last-child {
            border-bottom: none;
        }
        
        .user-dropdown-item:hover {
            background-color: #f8f9fa;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .user-dropdown-divider {
            border-top: 1px solid #eee;
            margin: 5px 0;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: all 0.2s ease;
        }
        
        .card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            border-bottom: 1px solid #eee;
            background-color: white;
            padding: 15px 20px;
            font-weight: 600;
            border-top-left-radius: 10px !important;
            border-top-right-radius: 10px !important;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Buttons */
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 166, 81, 0.3);
        }
        
        /* Tables */
        .table {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
        
        /* Forms */
        .form-control {
            border-radius: 6px;
            border: 1px solid #ddd;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 166, 81, 0.25);
        }
        
        /* Alerts */
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                transform: translateX(-100%);
                width: var(--sidebar-width);
            }
            
            #sidebar-wrapper.show {
                transform: translateX(0);
            }
            
            #sidebar-wrapper.collapsed {
                transform: translateX(-100%);
            }
            
            #page-content-wrapper {
                width: 100%;
                margin-left: 0;
            }
            
            #page-content-wrapper.expanded {
                width: 100%;
                margin-left: 0;
            }
            
            .topbar {
                padding: 0 15px;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .user-dropdown-toggle .user-name {
                display: none;
            }
        }
        
        /* Print Styles */
        @media print {
            body {
                background-color: white;
            }
            
            #sidebar-wrapper, .topbar, .no-print {
                display: none !important;
            }
            
            #page-content-wrapper {
                width: 100% !important;
                margin-left: 0 !important;
            }
            
            .main-content {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div id="sidebar-wrapper">
        <div class="sidebar-heading">
            <img src="<?php echo BASE_URL; ?>/assets/images/logo.png" alt="Logo" onerror="this.style.display='none'">
            <span class="school-name"><?php echo $institution['institution_name'] ?? 'LUFEM School'; ?></span>
        </div>
        
        <div class="list-group list-group-flush">
            <a href="<?php echo STUDENT_URL; ?>/dashboard.php" class="list-group-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i>
                <span class="menu-text">Dashboard</span>
            </a>
            
            <div class="sidebar-divider"></div>
            
            <!-- Course Registration -->
            <a href="<?php echo STUDENT_URL; ?>/register-courses.php" class="list-group-item <?php echo ($current_page == 'register-courses.php') ? 'active' : ''; ?>">
                <i class="bi bi-journal-plus"></i>
                <span class="menu-text">Register Courses</span>
            </a>
            
            <a href="<?php echo STUDENT_URL; ?>/my-courses.php" class="list-group-item <?php echo ($current_page == 'my-courses.php') ? 'active' : ''; ?>">
                <i class="bi bi-journal-bookmark"></i>
                <span class="menu-text">My Courses</span>
            </a>
            
            <div class="sidebar-divider"></div>
            
            <!-- Results -->
            <a href="<?php echo STUDENT_URL; ?>/results.php" class="list-group-item <?php echo ($current_page == 'results.php') ? 'active' : ''; ?>">
                <i class="bi bi-clipboard-data"></i>
                <span class="menu-text">My Results</span>
            </a>
            
            <a href="<?php echo STUDENT_URL; ?>/transcript.php" class="list-group-item <?php echo ($current_page == 'transcript.php') ? 'active' : ''; ?>">
                <i class="bi bi-file-earmark-pdf"></i>
                <span class="menu-text">Transcript</span>
            </a>
            
            <div class="sidebar-divider"></div>
            
            <!-- Profile -->
            <a href="<?php echo STUDENT_URL; ?>/profile.php" class="list-group-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="bi bi-person-gear"></i>
                <span class="menu-text">My Profile</span>
            </a>
            
            <a href="<?php echo STUDENT_URL; ?>/change-password.php" class="list-group-item <?php echo ($current_page == 'change-password.php') ? 'active' : ''; ?>">
                <i class="bi bi-shield-lock"></i>
                <span class="menu-text">Change Password</span>
            </a>
        </div>
    </div>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Page Content -->
    <div id="page-content-wrapper">
        <!-- Topbar -->
        <div class="topbar">
            <div class="d-flex align-items-center">
                <button class="toggle-sidebar" id="toggleSidebar">
                    <i class="bi bi-list"></i>
                </button>
                
                <div class="breadcrumb-nav ms-3">
                    <a href="<?php echo STUDENT_URL; ?>/dashboard.php">
                        <i class="bi bi-house"></i> Dashboard
                    </a>
                    <?php if (isset($breadcrumb)): ?>
                        <i class="bi bi-chevron-right"></i>
                        <span><?php echo $breadcrumb; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="user-dropdown">
                <button class="user-dropdown-toggle" id="userDropdownToggle">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                    </div>
                    <div class="user-info d-none d-md-block">
                        <div class="user-name"><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></div>
                        <div class="user-role text-muted" style="font-size: 0.8rem;"><?php echo $student['matric_number']; ?></div>
                    </div>
                    <i class="bi bi-chevron-down ms-2"></i>
                </button>
                
                <div class="user-dropdown-menu" id="userDropdownMenu">
                    <a href="<?php echo STUDENT_URL; ?>/profile.php" class="user-dropdown-item">
                        <i class="bi bi-person"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="<?php echo STUDENT_URL; ?>/change-password.php" class="user-dropdown-item">
                        <i class="bi bi-shield-lock"></i>
                        <span>Change Password</span>
                    </a>
                    <div class="user-dropdown-divider"></div>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="user-dropdown-item">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php display_flash_message(); ?>
