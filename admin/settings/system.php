<?php
$page_title = "System Settings";
$breadcrumb = "Settings > System";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Initialize database connection
$db = new Database();
$institution_id = get_institution_id();

// Get institution details
$db->query("SELECT * FROM institutions WHERE institution_id = :institution_id");
$db->bind(':institution_id', $institution_id);
$institution = $db->single();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $institution_name = clean_input($_POST['institution_name']);
    $institution_code = clean_input($_POST['institution_code']);
    $email = clean_input($_POST['email']);
    $phone = clean_input($_POST['phone']);
    $website = clean_input($_POST['website']);
    $address = clean_input($_POST['address']);
    $primary_color = clean_input($_POST['primary_color']);
    $secondary_color = clean_input($_POST['secondary_color']);
    
    // Validation
    $errors = [];
    
    if (empty($institution_name)) {
        $errors[] = 'Institution name is required';
    }
    
    if (empty($institution_code)) {
        $errors[] = 'Institution code is required';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = 'Invalid website URL';
    }
    
    // If no errors, update institution
    if (empty($errors)) {
        $db->query("UPDATE institutions SET 
                    institution_name = :institution_name,
                    institution_code = :institution_code,
                    email = :email,
                    phone = :phone,
                    website = :website,
                    address = :address,
                    primary_color = :primary_color,
                    secondary_color = :secondary_color,
                    updated_at = NOW()
                    WHERE institution_id = :institution_id");
        
        $db->bind(':institution_name', $institution_name);
        $db->bind(':institution_code', strtoupper($institution_code));
        $db->bind(':email', $email);
        $db->bind(':phone', $phone);
        $db->bind(':website', $website);
        $db->bind(':address', $address);
        $db->bind(':primary_color', $primary_color);
        $db->bind(':secondary_color', $secondary_color);
        $db->bind(':institution_id', $institution_id);
        
        if ($db->execute()) {
            set_flash_message('success', 'System settings updated successfully. Please refresh the page to see changes.');
            redirect(ADMIN_URL . '/settings/system.php');
        } else {
            $errors[] = 'Error updating settings. Please try again.';
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-gear me-2"></i>System Settings
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
                            <!-- Institution Information -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">Institution Information</h6>
                                
                                <div class="mb-3">
                                    <label for="institution_name" class="form-label">
                                        Institution Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="institution_name" 
                                           name="institution_name" required
                                           value="<?php echo isset($_POST['institution_name']) ? htmlspecialchars($_POST['institution_name']) : htmlspecialchars($institution['institution_name']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="institution_code" class="form-label">
                                        Institution Code <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="institution_code" 
                                           name="institution_code" required maxlength="20"
                                           value="<?php echo isset($_POST['institution_code']) ? htmlspecialchars($_POST['institution_code']) : htmlspecialchars($institution['institution_code']); ?>"
                                           style="text-transform: uppercase;">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" 
                                           name="email"
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($institution['email']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" 
                                           name="phone"
                                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : htmlspecialchars($institution['phone']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="website" class="form-label">Website</label>
                                    <input type="url" class="form-control" id="website" 
                                           name="website"
                                           value="<?php echo isset($_POST['website']) ? htmlspecialchars($_POST['website']) : htmlspecialchars($institution['website']); ?>"
                                           placeholder="https://example.com">
                                </div>
                            </div>
                            
                            <!-- Appearance & Branding -->
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3">Appearance & Branding</h6>
                                
                                <div class="mb-3">
                                    <label for="primary_color" class="form-label">Primary Color</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" id="primary_color" 
                                               name="primary_color"
                                               value="<?php echo isset($_POST['primary_color']) ? htmlspecialchars($_POST['primary_color']) : htmlspecialchars($institution['primary_color']); ?>">
                                        <input type="text" class="form-control" id="primary_color_text"
                                               value="<?php echo isset($_POST['primary_color']) ? htmlspecialchars($_POST['primary_color']) : htmlspecialchars($institution['primary_color']); ?>"
                                               readonly>
                                    </div>
                                    <div class="form-text">This color is used for buttons, links, and accents</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="secondary_color" class="form-label">Secondary Color</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" id="secondary_color" 
                                               name="secondary_color"
                                               value="<?php echo isset($_POST['secondary_color']) ? htmlspecialchars($_POST['secondary_color']) : htmlspecialchars($institution['secondary_color']); ?>">
                                        <input type="text" class="form-control" id="secondary_color_text"
                                               value="<?php echo isset($_POST['secondary_color']) ? htmlspecialchars($_POST['secondary_color']) : htmlspecialchars($institution['secondary_color']); ?>"
                                               readonly>
                                    </div>
                                    <div class="form-text">This color is used for backgrounds and secondary elements</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Color Preview</label>
                                    <div class="d-flex gap-2">
                                        <div class="color-preview" id="primary_preview" 
                                             style="background-color: <?php echo $institution['primary_color']; ?>; width: 50px; height: 50px; border-radius: 5px; border: 1px solid #ddd;"></div>
                                        <div class="color-preview" id="secondary_preview" 
                                             style="background-color: <?php echo $institution['secondary_color']; ?>; width: 50px; height: 50px; border-radius: 5px; border: 1px solid #ddd;"></div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Quick Color Themes</label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-secondary theme-btn" 
                                                data-primary="#00A651" data-secondary="#FFFFFF">Green & White</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary theme-btn" 
                                                data-primary="#007bff" data-secondary="#FFFFFF">Blue & White</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary theme-btn" 
                                                data-primary="#dc3545" data-secondary="#FFFFFF">Red & White</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary theme-btn" 
                                                data-primary="#6f42c1" data-secondary="#FFFFFF">Purple & White</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : htmlspecialchars($institution['address']); ?></textarea>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <a href="../dashboard.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Color picker synchronization
$('#primary_color').on('input', function() {
    const color = $(this).val();
    $('#primary_color_text').val(color);
    $('#primary_preview').css('background-color', color);
});

$('#secondary_color').on('input', function() {
    const color = $(this).val();
    $('#secondary_color_text').val(color);
    $('#secondary_preview').css('background-color', color);
});

// Theme buttons
$('.theme-btn').on('click', function() {
    const primary = $(this).data('primary');
    const secondary = $(this).data('secondary');
    
    $('#primary_color').val(primary);
    $('#primary_color_text').val(primary);
    $('#primary_preview').css('background-color', primary);
    
    $('#secondary_color').val(secondary);
    $('#secondary_color_text').val(secondary);
    $('#secondary_preview').css('background-color', secondary);
});

// Force uppercase for institution code
$('#institution_code').on('input', function() {
    $(this).val($(this).val().toUpperCase());
});
</script>

<?php include_once '../includes/footer.php'; ?>