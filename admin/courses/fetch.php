<?php
$page_title = "Fetch Courses";
$breadcrumb = "Courses > Fetch Courses";

require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    set_flash_message('danger', 'You must be logged in as admin to access this page');
    redirect(BASE_URL);
}

$db = new Database();
$institution_id = get_institution_id();

// Get filter parameters
$department_filter = isset($_GET['department']) ? clean_input($_GET['department']) : '';
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : '';
$search_term = isset($_GET['search']) ? clean_input($_GET['search']) : '';

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'fetch_courses') {
        $department_id = isset($_POST['department_id']) ? clean_input($_POST['department_id']) : '';
        $status = isset($_POST['status']) ? clean_input($_POST['status']) : '';
        $search = isset($_POST['search']) ? clean_input($_POST['search']) : '';

        // Build the query
        $query = "SELECT c.*, d.department_name, d.department_code,
                  (SELECT COUNT(*) FROM course_registrations cr WHERE cr.course_id = c.course_id) as total_registrations,
                  (SELECT COUNT(*) FROM results r 
                   JOIN course_registrations cr ON r.registration_id = cr.registration_id 
                   WHERE cr.course_id = c.course_id) as total_results
                  FROM courses c
                  JOIN departments d ON c.department_id = d.department_id
                  WHERE c.institution_id = :institution_id";

        $params = [':institution_id' => $institution_id];

        // Add filters
        if (!empty($department_id)) {
            $query .= " AND c.department_id = :department_id";
            $params[':department_id'] = $department_id;
        }

        if (!empty($status)) {
            if ($status === 'active') {
                $query .= " AND c.is_active = 1";
            } elseif ($status === 'inactive') {
                $query .= " AND c.is_active = 0";
            }
        }

        if (!empty($search)) {
            $query .= " AND (c.course_code LIKE :search OR c.course_title LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $query .= " ORDER BY d.department_name, c.course_code";

        $db->query($query);
        foreach ($params as $key => $value) {
            $db->bind($key, $value);
        }

        $courses = $db->resultSet();

        echo json_encode([
            'success' => true,
            'courses' => $courses,
            'total_count' => count($courses)
        ]);
        exit;
    }

    if ($_POST['action'] === 'toggle_course_status') {
        $course_id = clean_input($_POST['course_id']);
        $new_status = clean_input($_POST['new_status']);

        $db->query("UPDATE courses SET is_active = :status WHERE course_id = :course_id AND institution_id = :institution_id");
        $db->bind(':status', $new_status);
        $db->bind(':course_id', $course_id);
        $db->bind(':institution_id', $institution_id);

        if ($db->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Course status updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error updating course status'
            ]);
        }
        exit;
    }

    if ($_POST['action'] === 'delete_course') {
        $course_id = clean_input($_POST['course_id']);

        // Check if course has registrations
        $db->query("SELECT COUNT(*) as count FROM course_registrations WHERE course_id = :course_id");
        $db->bind(':course_id', $course_id);
        $registration_count = $db->single()['count'];

        if ($registration_count > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete course. It has student registrations.'
            ]);
            exit;
        }

        $db->query("DELETE FROM courses WHERE course_id = :course_id AND institution_id = :institution_id");
        $db->bind(':course_id', $course_id);
        $db->bind(':institution_id', $institution_id);

        if ($db->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Course deleted successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error deleting course'
            ]);
        }
        exit;
    }

    if ($_POST['action'] === 'get_course_details') {
        $course_id = clean_input($_POST['course_id']);

        $db->query("SELECT c.*, d.department_name, d.department_code,
                    (SELECT COUNT(*) FROM course_registrations cr WHERE cr.course_id = c.course_id) as total_registrations,
                    (SELECT COUNT(*) FROM results r 
                     JOIN course_registrations cr ON r.registration_id = cr.registration_id 
                     WHERE cr.course_id = c.course_id) as total_results,
                    (SELECT COUNT(*) FROM course_structures cs WHERE cs.course_id = c.course_id) as structure_count
                    FROM courses c
                    JOIN departments d ON c.department_id = d.department_id
                    WHERE c.course_id = :course_id AND c.institution_id = :institution_id");
        $db->bind(':course_id', $course_id);
        $db->bind(':institution_id', $institution_id);
        $course = $db->single();

        if ($course) {
            // Get recent registrations
            $db->query("SELECT s.first_name, s.last_name, s.matric_number, cr.registration_date
                        FROM course_registrations cr
                        JOIN students s ON cr.student_id = s.student_id
                        WHERE cr.course_id = :course_id
                        ORDER BY cr.registration_date DESC
                        LIMIT 5");
            $db->bind(':course_id', $course_id);
            $recent_registrations = $db->resultSet();

            echo json_encode([
                'success' => true,
                'course' => $course,
                'recent_registrations' => $recent_registrations
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Course not found'
            ]);
        }
        exit;
    }
}

// Get all departments for filter dropdown
$db->query("SELECT department_id, department_name FROM departments 
            WHERE institution_id = :institution_id AND is_active = 1 
            ORDER BY department_name");
$db->bind(':institution_id', $institution_id);
$departments = $db->resultSet();

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">
                                <i class="bi bi-search me-2"></i>Fetch Courses
                            </h5>
                            <small class="text-muted">Search and manage courses in the database</small>
                        </div>
                        <div>
                            <a href="add.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Add New Course
                            </a>
                            <button class="btn btn-success" onclick="exportCourses()">
                                <i class="bi bi-download me-2"></i>Export
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Search and Filter Section -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <label for="department_filter" class="form-label">Department</label>
                            <select class="form-select" id="department_filter">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" 
                                            <?php echo ($dept['department_id'] == $department_filter) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status_filter" class="form-label">Status</label>
                            <select class="form-select" id="status_filter">
                                <option value="">All Status</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="search_input" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search_input" 
                                   placeholder="Search by course code or title..." 
                                   value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button class="btn btn-primary" onclick="fetchCourses()">
                                    <i class="bi bi-search me-2"></i>Search
                                    <span class="spinner-border spinner-border-sm ms-2" id="search_spinner" style="display: none;"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Results Summary -->
                    <div id="results_summary" class="alert alert-info" style="display: none;">
                        <i class="bi bi-info-circle me-2"></i>
                        <span id="summary_text"></span>
                    </div>

                    <!-- Courses Display -->
                    <div id="courses_container">
                        <div class="text-center py-5">
                            <i class="bi bi-search display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">Search for Courses</h4>
                            <p class="text-muted">Use the filters above to search for courses in the database</p>
                            <button class="btn btn-primary" onclick="fetchAllCourses()">
                                <i class="bi bi-list me-2"></i>Show All Courses
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Course Details Modal -->
<div class="modal fade" id="courseDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-journal-bookmark me-2"></i>Course Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="course_details_content">
                <!-- Course details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="edit_course_btn">
                    <i class="bi bi-pencil me-2"></i>Edit Course
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentCourses = [];

$(document).ready(function() {
    // Auto-search on page load if filters are set
    <?php if (!empty($department_filter) || !empty($status_filter) || !empty($search_term)): ?>
        fetchCourses();
    <?php endif; ?>

    // Search on Enter key
    $('#search_input').on('keypress', function(e) {
        if (e.which === 13) {
            fetchCourses();
        }
    });

    // Auto-search when filters change
    $('#department_filter, #status_filter').on('change', function() {
        fetchCourses();
    });
});

function fetchCourses() {
    const departmentId = $('#department_filter').val();
    const status = $('#status_filter').val();
    const search = $('#search_input').val().trim();

    // Show loading
    $('#search_spinner').show();
    $('#courses_container').html(`
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted">Fetching courses...</p>
        </div>
    `);

    $.post('', {
        action: 'fetch_courses',
        department_id: departmentId,
        status: status,
        search: search
    }, function(response) {
        $('#search_spinner').hide();
        
        if (response.success) {
            currentCourses = response.courses;
            displayCourses(response.courses);
            showResultsSummary(response.total_count, departmentId, status, search);
        } else {
            showAlert('danger', 'Error fetching courses. Please try again.');
            $('#courses_container').html(`
                <div class="text-center py-5">
                    <i class="bi bi-exclamation-triangle display-4 text-warning"></i>
                    <h5 class="text-muted mt-3">Error Loading Courses</h5>
                    <p class="text-muted">Unable to fetch courses. Please try again.</p>
                </div>
            `);
        }
    }, 'json').fail(function() {
        $('#search_spinner').hide();
        showAlert('danger', 'Connection error. Please check your internet connection.');
    });
}

function fetchAllCourses() {
    $('#department_filter').val('');
    $('#status_filter').val('');
    $('#search_input').val('');
    fetchCourses();
}

function displayCourses(courses) {
    if (courses.length === 0) {
        $('#courses_container').html(`
            <div class="text-center py-5">
                <i class="bi bi-journal-x display-4 text-muted"></i>
                <h5 class="text-muted mt-3">No Courses Found</h5>
                <p class="text-muted">No courses match your search criteria.</p>
                <button class="btn btn-primary" onclick="fetchAllCourses()">
                    <i class="bi bi-arrow-clockwise me-2"></i>Show All Courses
                </button>
            </div>
        `);
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-hover"><thead class="table-light"><tr>';
    html += '<th>Course Code</th>';
    html += '<th>Course Title</th>';
    html += '<th>Department</th>';
    html += '<th>Credit Units</th>';
    html += '<th>Registrations</th>';
    html += '<th>Results</th>';
    html += '<th>Status</th>';
    html += '<th>Actions</th>';
    html += '</tr></thead><tbody>';

    courses.forEach(function(course) {
        const statusBadge = course.is_active == 1 ? 
            '<span class="badge bg-success">Active</span>' : 
            '<span class="badge bg-secondary">Inactive</span>';

        html += `
            <tr>
                <td>
                    <span class="badge bg-primary">${course.course_code}</span>
                </td>
                <td>
                    <strong>${course.course_title}</strong>
                    <br><small class="text-muted">ID: ${course.course_id}</small>
                </td>
                <td>
                    <span class="badge bg-info">${course.department_code}</span>
                    <br><small>${course.department_name}</small>
                </td>
                <td>
                    <span class="badge bg-secondary">${course.credit_units}</span>
                </td>
                <td>
                    <span class="badge bg-primary">${course.total_registrations}</span>
                </td>
                <td>
                    <span class="badge bg-success">${course.total_results}</span>
                </td>
                <td>${statusBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info" onclick="viewCourseDetails(${course.course_id})" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                        <a href="edit.php?id=${course.course_id}" class="btn btn-outline-primary" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button class="btn btn-outline-${course.is_active == 1 ? 'warning' : 'success'}" 
                                onclick="toggleCourseStatus(${course.course_id}, ${course.is_active == 1 ? 0 : 1})" 
                                title="${course.is_active == 1 ? 'Deactivate' : 'Activate'}">
                            <i class="bi bi-${course.is_active == 1 ? 'pause' : 'play'}"></i>
                        </button>
                        ${course.total_registrations == 0 ? 
                            `<button class="btn btn-outline-danger" onclick="deleteCourse(${course.course_id})" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>` : ''
                        }
                    </div>
                </td>
            </tr>
        `;
    });

    html += '</tbody></table></div>';
    $('#courses_container').html(html);
}

function showResultsSummary(count, departmentId, status, search) {
    let summaryText = `Found ${count} course${count !== 1 ? 's' : ''}`;
    
    const filters = [];
    if (departmentId) {
        const deptName = $('#department_filter option:selected').text();
        filters.push(`in ${deptName}`);
    }
    if (status) {
        filters.push(`with ${status} status`);
    }
    if (search) {
        filters.push(`matching "${search}"`);
    }
    
    if (filters.length > 0) {
        summaryText += ` ${filters.join(', ')}`;
    }
    
    $('#summary_text').text(summaryText);
    $('#results_summary').show();
}

function viewCourseDetails(courseId) {
    $.post('', {
        action: 'get_course_details',
        course_id: courseId
    }, function(response) {
        if (response.success) {
            displayCourseDetailsModal(response.course, response.recent_registrations);
        } else {
            showAlert('danger', response.message);
        }
    }, 'json');
}

function displayCourseDetailsModal(course, recentRegistrations) {
    const statusBadge = course.is_active == 1 ? 
        '<span class="badge bg-success">Active</span>' : 
        '<span class="badge bg-secondary">Inactive</span>';

    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6>Course Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Course Code:</strong></td><td><span class="badge bg-primary">${course.course_code}</span></td></tr>
                    <tr><td><strong>Course Title:</strong></td><td>${course.course_title}</td></tr>
                    <tr><td><strong>Department:</strong></td><td>${course.department_name}</td></tr>
                    <tr><td><strong>Credit Units:</strong></td><td><span class="badge bg-secondary">${course.credit_units}</span></td></tr>
                    <tr><td><strong>Status:</strong></td><td>${statusBadge}</td></tr>
                    <tr><td><strong>Created:</strong></td><td>${formatDate(course.created_at)}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Statistics</h6>
                <table class="table table-sm">
                    <tr><td><strong>Total Registrations:</strong></td><td><span class="badge bg-primary">${course.total_registrations}</span></td></tr>
                    <tr><td><strong>Total Results:</strong></td><td><span class="badge bg-success">${course.total_results}</span></td></tr>
                    <tr><td><strong>Course Structures:</strong></td><td><span class="badge bg-info">${course.structure_count}</span></td></tr>
                    <tr><td><strong>Pending Results:</strong></td><td><span class="badge bg-warning">${course.total_registrations - course.total_results}</span></td></tr>
                </table>
            </div>
        </div>
    `;

    if (course.course_description) {
        html += `
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Description</h6>
                    <p class="text-muted">${course.course_description}</p>
                </div>
            </div>
        `;
    }

    if (recentRegistrations.length > 0) {
        html += `
            <div class="row mt-3">
                <div class="col-12">
                    <h6>Recent Registrations</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Matric Number</th>
                                    <th>Registration Date</th>
                                </tr>
                            </thead>
                            <tbody>
        `;
        
        recentRegistrations.forEach(function(reg) {
            html += `
                <tr>
                    <td>${reg.first_name} ${reg.last_name}</td>
                    <td><span class="badge bg-primary">${reg.matric_number}</span></td>
                    <td>${formatDate(reg.registration_date)}</td>
                </tr>
            `;
        });
        
        html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    $('#course_details_content').html(html);
    $('#edit_course_btn').attr('onclick', `window.location.href='edit.php?id=${course.course_id}'`);
    $('#courseDetailsModal').modal('show');
}

function toggleCourseStatus(courseId, newStatus) {
    const action = newStatus == 1 ? 'activate' : 'deactivate';
    
    if (!confirm(`Are you sure you want to ${action} this course?`)) {
        return;
    }

    $.post('', {
        action: 'toggle_course_status',
        course_id: courseId,
        new_status: newStatus
    }, function(response) {
        if (response.success) {
            showAlert('success', response.message);
            fetchCourses(); // Refresh the list
        } else {
            showAlert('danger', response.message);
        }
    }, 'json');
}

function deleteCourse(courseId) {
    if (!confirm('Are you sure you want to delete this course? This action cannot be undone.')) {
        return;
    }

    $.post('', {
        action: 'delete_course',
        course_id: courseId
    }, function(response) {
        if (response.success) {
            showAlert('success', response.message);
            fetchCourses(); // Refresh the list
        } else {
            showAlert('danger', response.message);
        }
    }, 'json');
}

function exportCourses() {
    if (currentCourses.length === 0) {
        showAlert('warning', 'No courses to export. Please search for courses first.');
        return;
    }
    
    // Create CSV content
    let csvContent = "Course Code,Course Title,Department,Credit Units,Registrations,Results,Status\n";
    
    currentCourses.forEach(function(course) {
        const status = course.is_active == 1 ? 'Active' : 'Inactive';
        csvContent += `"${course.course_code}","${course.course_title}","${course.department_name}",${course.credit_units},${course.total_registrations},${course.total_results},"${status}"\n`;
    });
    
    // Download CSV
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'courses_export.csv';
    a.click();
    window.URL.revokeObjectURL(url);
    
    showAlert('success', 'Courses exported successfully!');
}

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // Remove existing alerts
    $('.alert:not(#results_summary)').remove();

    // Add new alert at the top of the card body
    $('.card-body').first().prepend(alertHtml);

    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('.alert:not(#results_summary)').fadeOut();
    }, 5000);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString();
}
</script>

<style>
.table th {
    border-top: none;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
}

.modal-lg {
    max-width: 900px;
}

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.125rem 0.25rem;
        font-size: 0.75rem;
    }
}
</style>

<?php include_once '../includes/footer.php'; ?>
