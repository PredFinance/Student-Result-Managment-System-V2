<?php
$page_title = "Academic Settings";
$breadcrumb = "Settings > Academic";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../includes/gpa_functions.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

// Initialize database connection
$db = new Database();
$institution_id = get_institution_id();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add_session') {
            $session_name = clean_input($_POST['session_name']);
            $start_date = clean_input($_POST['start_date']);
            $end_date = clean_input($_POST['end_date']);
            $is_current = isset($_POST['is_current']) ? 1 : 0;
            
            // If this is set as current, unset others
            if ($is_current) {
                $db->query("UPDATE sessions SET is_current = 0 WHERE institution_id = :institution_id");
                $db->bind(':institution_id', $institution_id);
                $db->execute();
            }
            
            $db->query("INSERT INTO sessions (institution_id, session_name, start_date, end_date, is_current)
                        VALUES (:institution_id, :session_name, :start_date, :end_date, :is_current)");
            $db->bind(':institution_id', $institution_id);
            $db->bind(':session_name', $session_name);
            $db->bind(':start_date', $start_date);
            $db->bind(':end_date', $end_date);
            $db->bind(':is_current', $is_current);
            
            if ($db->execute()) {
                set_flash_message('success', 'Academic session added successfully.');
            } else {
                set_flash_message('danger', 'Error adding academic session.');
            }
        }
        
        if ($_POST['action'] == 'add_semester') {
            $session_id = clean_input($_POST['session_id']);
            $semester_name = clean_input($_POST['semester_name']);
            $is_current = isset($_POST['is_current_semester']) ? 1 : 0;
            
            // If this is set as current, unset others
            if ($is_current) {
                $db->query("UPDATE semesters SET is_current = 0 WHERE institution_id = :institution_id");
                $db->bind(':institution_id', $institution_id);
                $db->execute();
            }
            
            $db->query("INSERT INTO semesters (institution_id, session_id, semester_name, is_current) 
                        VALUES (:institution_id, :session_id, :semester_name, :is_current)");
            $db->bind(':institution_id', $institution_id);
            $db->bind(':session_id', $session_id);
            $db->bind(':semester_name', $semester_name);
            $db->bind(':is_current', $is_current);
            
            if ($db->execute()) {
                set_flash_message('success', 'Semester added successfully.');
            } else {
                set_flash_message('danger', 'Error adding semester.');
            }
        }
        
        if ($_POST['action'] == 'set_current_session') {
            $session_id = clean_input($_POST['session_id']);
            
            $db->query("UPDATE sessions SET is_current = 0 WHERE institution_id = :institution_id");
            $db->bind(':institution_id', $institution_id);
            $db->execute();
            
            $db->query("UPDATE sessions SET is_current = 1 WHERE session_id = :session_id");
            $db->bind(':session_id', $session_id);
            
            if ($db->execute()) {
                set_flash_message('success', 'Current session updated successfully.');
            }
        }
        
        if ($_POST['action'] == 'set_current_semester') {
            $semester_id = clean_input($_POST['semester_id']);
            
            $db->query("UPDATE semesters SET is_current = 0 WHERE institution_id = :institution_id");
            $db->bind(':institution_id', $institution_id);
            $db->execute();
            
            $db->query("UPDATE semesters SET is_current = 1 WHERE semester_id = :semester_id");
            $db->bind(':semester_id', $semester_id);
            
            if ($db->execute()) {
                set_flash_message('success', 'Current semester updated successfully.');
            }
        }
    }
    
    redirect($_SERVER['PHP_SELF']);
}

// Get sessions
$sessions = get_all_sessions();

// Get semesters - FIXED: Remove semester_order reference
$db->query("SELECT * FROM semesters WHERE institution_id = :institution_id ORDER BY semester_id");
$db->bind(':institution_id', $institution_id);
$semesters = $db->resultSet();

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sessions Management -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar3 me-2"></i>Academic Sessions
                    </h5>
                </div>
                
                <div class="card-body">
                    <!-- Add Session Form -->
                    <form method="POST" action="" class="mb-4">
                        <input type="hidden" name="action" value="add_session">
                        
                        <div class="mb-3">
                            <label class="form-label">Session Name</label>
                            <input type="text" class="form-control" name="session_name" 
                                   placeholder="e.g., 2024/2025" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_current" id="is_current">
                                <label class="form-check-label" for="is_current">
                                    Set as current session
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Add Session
                        </button>
                    </form>
                    
                    <!-- Sessions List -->
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Session</th>
                                    <th>Period</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $session): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($session['session_name']); ?></td>
                                        <td>
                                            <small>
                                                <?php if (isset($session['start_date']) && isset($session['end_date'])): ?>
                                                    <?php echo date('M Y', strtotime($session['start_date'])); ?> - 
                                                    <?php echo date('M Y', strtotime($session['end_date'])); ?>
                                                <?php else: ?>
                                                    Not set
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($session['is_current']): ?>
                                                <span class="badge bg-success">Current</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$session['is_current']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="set_current_session">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                        Set Current
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Semesters Management -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-week me-2"></i>Semesters
                    </h5>
                </div>
                
                <div class="card-body">
                    <!-- Add Semester Form -->
                    <form method="POST" action="" class="mb-4">
                        <input type="hidden" name="action" value="add_semester">
                        
                        <div class="mb-3">
                            <label class="form-label">Academic Session</label>
                            <select class="form-select" name="session_id" required>
                                <option value="">Select Session</option>
                                <?php foreach ($sessions as $session): ?>
                                    <option value="<?php echo $session['session_id']; ?>">
                                        <?php echo htmlspecialchars($session['session_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Semester Name</label>
                            <input type="text" class="form-control" name="semester_name" 
                                   placeholder="e.g., First Semester" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_current_semester" id="is_current_semester">
                                <label class="form-check-label" for="is_current_semester">
                                    Set as current semester
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Add Semester
                        </button>
                    </form>
                    
                    <!-- Semesters List -->
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Semester</th>
                                    <th>Session</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($semesters as $semester): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($semester['semester_name']); ?></td>
                                        <td>
                                            <?php
                                            // Find session name
                                            $session_name = '';
                                            foreach ($sessions as $session) {
                                                if ($session['session_id'] == $semester['session_id']) {
                                                    $session_name = $session['session_name'];
                                                    break;
                                                }
                                            }
                                            echo htmlspecialchars($session_name);
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($semester['is_current']): ?>
                                                <span class="badge bg-success">Current</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$semester['is_current']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="set_current_semester">
                                                    <input type="hidden" name="semester_id" value="<?php echo $semester['semester_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                        Set Current
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>