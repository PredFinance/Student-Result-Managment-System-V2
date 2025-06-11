<?php
$page_title = "Settings";
$breadcrumb = "Settings";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-gear me-2"></i>System Settings
                    </h5>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <!-- Academic Settings -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">
                                        <i class="bi bi-calendar3 me-2"></i>Academic Settings
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Manage academic sessions, semesters, and grading system.</p>
                                    <div class="d-grid gap-2">
                                        <a href="academic.php" class="btn btn-primary">
                                            <i class="bi bi-calendar-check me-2"></i>Academic Sessions
                                        </a>
                                        <a href="grading.php" class="btn btn-outline-primary">
                                            <i class="bi bi-award me-2"></i>Grading System
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Administration -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="bi bi-shield-check me-2"></i>System Administration
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Manage system administrators and user accounts.</p>
                                    <div class="d-grid gap-2">
                                        <a href="admins.php" class="btn btn-success">
                                            <i class="bi bi-people me-2"></i>Manage Admins
                                        </a>
                                        <a href="accounts.php" class="btn btn-outline-success">
                                            <i class="bi bi-person-gear me-2"></i>User Accounts
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Institution Settings -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="bi bi-building me-2"></i>Institution Settings
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Configure institution details and system preferences.</p>
                                    <div class="d-grid gap-2">
                                        <a href="institution.php" class="btn btn-info">
                                            <i class="bi bi-building-gear me-2"></i>Institution Details
                                        </a>
                                        <a href="system.php" class="btn btn-outline-info">
                                            <i class="bi bi-gear-wide me-2"></i>System Configuration
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Backup & Security -->
                        <div class="col-md-6 mb-4">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="bi bi-shield-lock me-2"></i>Backup & Security
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Database backup and security configurations.</p>
                                    <div class="d-grid gap-2">
                                        <a href="backup.php" class="btn btn-warning">
                                            <i class="bi bi-download me-2"></i>Database Backup
                                        </a>
                                        <a href="security.php" class="btn btn-outline-warning">
                                            <i class="bi bi-shield-exclamation me-2"></i>Security Settings
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>