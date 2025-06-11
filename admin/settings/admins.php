<?php
$page_title = "Admin Management";
$breadcrumb = "Settings > Admin Management";

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
$institution_id = get_institution_id();

// Handle delete request
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $user_id = clean_input($_GET['delete']);
    
    // Prevent deleting self
    if ($user_id == get_user_id()) {
        set_flash_message('danger', 'You cannot delete your own account');
    } else {
        $db->query("DELETE FROM users WHERE user_id = :user_id AND institution_id = :institution_id AND role IN ('admin', 'super_admin')");
        $db->bind(':user_id', $user_id);
        $db->bind(':institution_id', $institution_id);
        
        if ($db->execute()) {
            set_flash_message('success', 'Admin deleted successfully.');
        } else {
            set_flash_message('danger', 'Error deleting admin.');
        }
    }
    
    redirect(ADMIN_URL . '/settings/admins.php');
}

// Handle status toggle
if (isset($_GET['toggle']) && !empty($_GET['toggle'])) {
    $user_id = clean_input($_GET['toggle']);
    
    // Prevent disabling self
    if ($user_id == get_user_id()) {
        set_flash_message('danger', 'You cannot disable your own account');
    } else {
        $db->query("UPDATE users SET is_active = NOT is_active WHERE user_id = :user_id AND institution_id = :institution_id");
        $db->bind(':user_id', $user_id);
        $db->bind(':institution_id', $institution_id);
        
        if ($db->execute()) {
            set_flash_message('success', 'Admin status updated successfully.');
        } else {
            set_flash_message('danger', 'Error updating admin status.');
        }
    }
    
    redirect(ADMIN_URL . '/settings/admins.php');
}

// Get all admins
$db->query("SELECT * FROM users WHERE institution_id = :institution_id AND role IN ('admin', 'super_admin') ORDER BY created_at DESC");
$db->bind(':institution_id', $institution_id);
$admins = $db->resultSet();

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-shield-check me-2"></i>Admin Management
                    </h5>
                    <a href="add-admin.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>Add New Admin
                    </a>
                </div>
                
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($admins)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                                No admins found
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($admins as $admin): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar me-2">
                                                        <div class="avatar-initial bg-primary rounded-circle">
                                                            <?php echo strtoupper(substr($admin['full_name'], 0, 1)); ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($admin['full_name']); ?></strong>
                                                        <?php if ($admin['user_id'] == get_user_id()): ?>
                                                            <span class="badge bg-info ms-2">You</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($admin['username']); ?></code>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($admin['email'] ?: 'Not Set'); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo ($admin['role'] == 'super_admin') ? 'danger' : 'warning'; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $admin['role'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $admin['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo $admin['last_login'] ? format_date($admin['last_login'], 'd M, Y H:i') : 'Never'; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo format_date($admin['created_at']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="edit-admin.php?id=<?php echo $admin['user_id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    
                                                    <?php if ($admin['user_id'] != get_user_id()): ?>
                                                        <a href="?toggle=<?php echo $admin['user_id']; ?>" 
                                                           class="btn btn-outline-<?php echo $admin['is_active'] ? 'warning' : 'success'; ?>" 
                                                           title="<?php echo $admin['is_active'] ? 'Disable' : 'Enable'; ?>">
                                                            <i class="bi bi-<?php echo $admin['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                        </a>
                                                        
                                                        <a href="?delete=<?php echo $admin['user_id']; ?>" 
                                                           class="btn btn-outline-danger btn-delete" 
                                                           title="Delete"
                                                           data-item="<?php echo htmlspecialchars($admin['full_name']); ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-secondary" disabled title="Cannot modify your own account">
                                                            <i class="bi bi-lock"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.avatar {
    width: 40px;
    height: 40px;
}

.avatar-initial {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}
</style>

<?php include_once '../includes/footer.php'; ?>