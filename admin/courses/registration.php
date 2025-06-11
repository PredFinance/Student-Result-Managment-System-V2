<?php
$page_title = "Course Registration";
$breadcrumb = "Courses > Student Course Registration";

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

// Get current session and semester
$current_session = get_current_session($institution_id);
$current_semester = get_current_semester($institution_id);
if (!$current_session || !$current_semester) {
    set_flash_message('warning', 'No active session or semester found. Please set them up first.');
    redirect(ADMIN_URL . '/settings/sessions.php');
}

$selected_student_id = isset($_GET['student_id']) ? clean_input($_GET['student_id']) : '';

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] == 'search_students') {
        $search_term = clean_input($_POST['search_term']);
        
        $db->query("SELECT s.student_id, s.matric_number, s.first_name, s.last_name, 
                    d.department_name, l.level_name
                    FROM students s
                    JOIN departments d ON s.department_id = d.department_id
                    JOIN levels l ON s.level_id = l.level_id
                    WHERE s.institution_id = :institution_id 
                    AND (s.matric_number LIKE :search OR 
                         CONCAT(s.first_name, ' ', s.last_name) LIKE :search OR
                         s.first_name LIKE :search OR 
                         s.last_name LIKE :search)
                    ORDER BY s.matric_number
                    LIMIT 10");
        
        $search_param = '%' . $search_term . '%';
        $db->bind(':institution_id', $institution_id);
        $db->bind(':search', $search_param);
        
        $students = $db->resultSet();
        echo json_encode($students);
        exit;
    }

    if ($_POST['action'] == 'get_student_info') {
        $student_id = clean_input($_POST['student_id']);
        
        $db->query("SELECT s.*, d.department_name, l.level_name 
                    FROM students s
                    JOIN departments d ON s.department_id = d.department_id
                    JOIN levels l ON s.level_id = l.level_id
                    WHERE s.student_id = :student_id AND s.institution_id = :institution_id");
        $db->bind(':student_id', $student_id);
        $db->bind(':institution_id', $institution_id);
        
        $student = $db->single();
        
        if ($student) {
            // Get available courses for this student's department
            $db->query("SELECT c.*, 
                        CASE WHEN cr.registration_id IS NOT NULL THEN 1 ELSE 0 END as is_registered
                        FROM courses c
                        LEFT JOIN course_registrations cr ON c.course_id = cr.course_id 
                            AND cr.student_id = :student_id 
                            AND cr.session_id = :session_id 
                            AND cr.semester_id = :semester_id
                        WHERE c.department_id = :department_id AND c.institution_id = :institution_id
                        ORDER BY c.course_code");
            $db->bind(':student_id', $student['student_id']);
            $db->bind(':session_id', $current_session['session_id']);
            $db->bind(':semester_id', $current_semester['semester_id']);
            $db->bind(':department_id', $student['department_id']);
            $db->bind(':institution_id', $institution_id);
            
            $courses = $db->resultSet();
            
            echo json_encode([
                'success' => true,
                'student' => $student,
                'courses' => $courses
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Student not found'
            ]);
        }
        exit;
    }

    if ($_POST['action'] == 'register_courses') {
        $student_id = clean_input($_POST['student_id']);
        $course_ids = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];
        
        if (empty($course_ids)) {
            echo json_encode([
                'success' => false,
                'message' => 'No courses selected'
            ]);
            exit;
        }
        
        $db->beginTransaction();
        
        try {
            $registered_count = 0;
            $skipped_count = 0;
            
            foreach ($course_ids as $course_id) {
                // Check if already registered
                $db->query("SELECT COUNT(*) as count FROM course_registrations 
                            WHERE student_id = :student_id AND course_id = :course_id 
                            AND session_id = :session_id AND semester_id = :semester_id");
                $db->bind(':student_id', $student_id);
                $db->bind(':course_id', $course_id);
                $db->bind(':session_id', $current_session['session_id']);
                $db->bind(':semester_id', $current_semester['semester_id']);
                
                if ($db->single()['count'] == 0) {
                    // Register the course
                    $db->query("INSERT INTO course_registrations 
                                (institution_id, student_id, course_id, session_id, semester_id) 
                                VALUES (:institution_id, :student_id, :course_id, :session_id, :semester_id)");
                    $db->bind(':institution_id', $institution_id);
                    $db->bind(':student_id', $student_id);
                    $db->bind(':course_id', $course_id);
                    $db->bind(':session_id', $current_session['session_id']);
                    $db->bind(':semester_id', $current_semester['semester_id']);
                    $db->execute();
                    
                    $registered_count++;
                } else {
                    $skipped_count++;
                }
            }
            
            $db->endTransaction();
            
            $message = "Registration completed! $registered_count course(s) registered.";
            if ($skipped_count > 0) {
                $message .= " $skipped_count course(s) were already registered.";
            }
            
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
        } catch (Exception $e) {
            $db->cancelTransaction();
            echo json_encode([
                'success' => false,
                'message' => 'Error registering courses: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    if ($_POST['action'] == 'unregister_course') {
        $student_id = clean_input($_POST['student_id']);
        $course_id = clean_input($_POST['course_id']);
        
        // Check if student has results for this course
        $db->query("SELECT COUNT(*) as count FROM results r
                    JOIN course_registrations cr ON r.registration_id = cr.registration_id
                    WHERE cr.student_id = :student_id AND cr.course_id = :course_id
                    AND cr.session_id = :session_id AND cr.semester_id = :semester_id");
        $db->bind(':student_id', $student_id);
        $db->bind(':course_id', $course_id);
        $db->bind(':session_id', $current_session['session_id']);
        $db->bind(':semester_id', $current_semester['semester_id']);
        
        if ($db->single()['count'] > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot unregister course. Student has results for this course.'
            ]);
            exit;
        }
        
        // Unregister the course
        $db->query("DELETE FROM course_registrations 
                    WHERE student_id = :student_id AND course_id = :course_id 
                    AND session_id = :session_id AND semester_id = :semester_id");
        $db->bind(':student_id', $student_id);
        $db->bind(':course_id', $course_id);
        $db->bind(':session_id', $current_session['session_id']);
        $db->bind(':semester_id', $current_semester['semester_id']);
        
        if ($db->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Course unregistered successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error unregistering course'
            ]);
        }
        exit;
    }
}

// If student_id is provided, get student info
$student_info = null;
if (!empty($selected_student_id)) {
    $db->query("SELECT s.*, d.department_name, l.level_name 
                FROM students s
                JOIN departments d ON s.department_id = d.department_id
                JOIN levels l ON s.level_id = l.level_id
                WHERE s.student_id = :student_id AND s.institution_id = :institution_id");
    $db->bind(':student_id', $selected_student_id);
    $db->bind(':institution_id', $institution_id);
    $student_info = $db->single();
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
                        <i class="bi bi-journal-check me-2"></i>Course Registration
                    </h5>
                    <small class="text-muted">
                        Current Session: <strong><?php echo $current_session['session_name']; ?></strong> | 
                        Current Semester: <strong><?php echo $current_semester['semester_name']; ?></strong>
                    </small>
                </div>
                
                <div class="card-body">
                    <!-- Student Search with Autocomplete -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <label for="student_search" class="form-label">
                                <i class="bi bi-search me-2"></i>Search Student
                            </label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="student_search" 
                                       placeholder="Type student name or matriculation number..." 
                                       value="<?php echo $student_info ? $student_info['first_name'] . ' ' . $student_info['last_name'] . ' (' . $student_info['matric_number'] . ')' : ''; ?>">
                                <div id="search_results" class="position-absolute w-100 bg-white border rounded shadow-sm" style="display: none; z-index: 1000; max-height: 300px; overflow-y: auto;"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Quick Actions</label>
                            <div class="d-flex gap-2">
                                <a href="../students/index.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-people me-2"></i>Browse Students
                                </a>
                                <button class="btn btn-outline-info" onclick="showRegistrationStats()">
                                    <i class="bi bi-graph-up me-2"></i>Statistics
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Information Panel -->
                    <div id="student_panel" style="display: <?php echo $student_info ? 'block' : 'none'; ?>;">
                        <div class="card border-primary mb-4">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="bi bi-person-badge me-2"></i>Student Information
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row" id="student_info">
                                    <?php if ($student_info): ?>
                                        <div class="col-md-3">
                                            <strong>Name:</strong><br>
                                            <span class="text-primary"><?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Matric Number:</strong><br>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($student_info['matric_number']); ?></span>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Department:</strong><br>
                                            <?php echo htmlspecialchars($student_info['department_name']); ?>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Level:</strong><br>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($student_info['level_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Course Registration Panel -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="bi bi-journal-bookmark me-2"></i>Available Courses
                                </h6>
                                <div>
                                    <button class="btn btn-success btn-sm" id="register_selected" style="display: none;">
                                        <i class="bi bi-check-circle me-2"></i>Register Selected
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="selectAllCourses()">
                                        <i class="bi bi-check-all me-2"></i>Select All
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="courses_panel">
                                    <!-- Courses will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- No Student Selected -->
                    <div id="no_student_panel" style="display: <?php echo $student_info ? 'none' : 'block'; ?>;">
                        <div class="text-center py-5">
                            <i class="bi bi-person-plus display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No Student Selected</h4>
                            <p class="text-muted">Search for a student above to begin course registration</p>
                            <div class="mt-4">
                                <a href="../students/index.php" class="btn btn-primary">
                                    <i class="bi bi-people me-2"></i>Browse All Students
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentStudentId = null;
let searchTimeout = null;

$(document).ready(function() {
    // Auto-load if student info is pre-loaded
    <?php if ($student_info): ?>
        currentStudentId = <?php echo $student_info['student_id']; ?>;
        loadStudentCourses(currentStudentId);
    <?php endif; ?>

    // Student search with autocomplete
    $('#student_search').on('input', function() {
        const searchTerm = $(this).val().trim();
        
        clearTimeout(searchTimeout);
        
        if (searchTerm.length >= 2) {
            searchTimeout = setTimeout(function() {
                searchStudents(searchTerm);
            }, 300);
        } else {
            $('#search_results').hide();
        }
    });

    // Hide search results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#student_search, #search_results').length) {
            $('#search_results').hide();
        }
    });

    // Register selected courses
    $('#register_selected').on('click', function() {
        const selectedCourses = [];
        $('.course-checkbox:checked').each(function() {
            selectedCourses.push($(this).val());
        });
        
        if (selectedCourses.length === 0) {
            showAlert('warning', 'Please select at least one course to register.');
            return;
        }
        
        registerCourses(selectedCourses);
    });
});

function searchStudents(searchTerm) {
    $.post('', {
        action: 'search_students',
        search_term: searchTerm
    }, function(students) {
        let html = '';
        
        if (students.length === 0) {
            html = '<div class="p-3 text-muted">No students found</div>';
        } else {
            students.forEach(function(student) {
                html += `
                    <div class="p-2 border-bottom student-result" style="cursor: pointer;" 
                         onclick="selectStudent(${student.student_id}, '${student.first_name} ${student.last_name} (${student.matric_number})')">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${student.first_name} ${student.last_name}</strong>
                                <br><small class="text-muted">${student.matric_number}</small>
                            </div>
                            <div class="text-end">
                                <small>${student.department_name}</small>
                                <br><small class="text-muted">${student.level_name}</small>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        $('#search_results').html(html).show();
    }, 'json');
}

function selectStudent(studentId, displayText) {
    currentStudentId = studentId;
    $('#student_search').val(displayText);
    $('#search_results').hide();
    
    loadStudentInfo(studentId);
}

function loadStudentInfo(studentId) {
    showLoading();
    
    $.post('', {
        action: 'get_student_info',
        student_id: studentId
    }, function(response) {
        hideLoading();
        
        if (response.success) {
            loadStudentInfoDisplay(response.student);
            loadCourses(response.courses);
            $('#student_panel').show();
            $('#no_student_panel').hide();
        } else {
            showAlert('danger', response.message);
            $('#student_panel').hide();
            $('#no_student_panel').show();
        }
    }, 'json').fail(function() {
        hideLoading();
        showAlert('danger', 'Error loading student information. Please try again.');
    });
}

function loadStudentInfoDisplay(student) {
    const html = `
        <div class="col-md-3">
            <strong>Name:</strong><br>
            <span class="text-primary">${student.first_name} ${student.last_name}</span>
        </div>
        <div class="col-md-3">
            <strong>Matric Number:</strong><br>
            <span class="badge bg-primary">${student.matric_number}</span>
        </div>
        <div class="col-md-3">
            <strong>Department:</strong><br>
            ${student.department_name}
        </div>
        <div class="col-md-3">
            <strong>Level:</strong><br>
            <span class="badge bg-info">${student.level_name}</span>
        </div>
    `;
    $('#student_info').html(html);
}

function loadStudentCourses(studentId) {
    $.post('', {
        action: 'get_student_info',
        student_id: studentId
    }, function(response) {
        if (response.success) {
            loadCourses(response.courses);
        }
    }, 'json');
}

function loadCourses(courses) {
    if (courses.length === 0) {
        $('#courses_panel').html(`
            <div class="text-center py-4">
                <i class="bi bi-journal-x display-4 text-muted"></i>
                <h5 class="text-muted mt-3">No Courses Available</h5>
                <p class="text-muted">No courses found for this student's department.</p>
            </div>
        `);
        return;
    }

    let html = '<div class="row">';
    let registeredCount = 0;

    courses.forEach(function(course) {
        const isRegistered = course.is_registered == 1;
        if (isRegistered) registeredCount++;
        
        html += `
            <div class="col-md-6 mb-3">
                <div class="card ${isRegistered ? 'border-success' : 'border-light'}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1">
                                    <span class="badge bg-primary me-2">${course.course_code}</span>
                                    ${course.course_title}
                                </h6>
                                <p class="card-text text-muted mb-2">
                                    <i class="bi bi-award me-1"></i>${course.credit_units} Credit Units
                                </p>
                            </div>
                            <div class="form-check">
                                ${isRegistered ? 
                                    `<span class="badge bg-success">Registered</span>
                                     <button class="btn btn-sm btn-outline-danger ms-2" onclick="unregisterCourse(${course.course_id})">
                                         <i class="bi bi-x"></i>
                                     </button>` :
                                    `<input class="form-check-input course-checkbox" type="checkbox" value="${course.course_id}" id="course_${course.course_id}">
                                     <label class="form-check-label" for="course_${course.course_id}"></label>`
                                }
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    html += '</div>';

    // Add summary
    html = `
        <div class="alert alert-info mb-3">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Registration Summary:</strong> ${registeredCount} of ${courses.length} courses registered for current semester.
        </div>
    ` + html;

    $('#courses_panel').html(html);

    // Show/hide register button based on available courses
    const availableCourses = courses.filter(c => c.is_registered == 0);
    if (availableCourses.length > 0) {
        $('#register_selected').show();
    } else {
        $('#register_selected').hide();
    }

    // Update checkbox change handler
    $('.course-checkbox').on('change', function() {
        const checkedCount = $('.course-checkbox:checked').length;
        if (checkedCount > 0) {
            $('#register_selected').removeClass('btn-success').addClass('btn-primary');
            $('#register_selected').html(`<i class="bi bi-check-circle me-2"></i>Register ${checkedCount} Course${checkedCount > 1 ? 's' : ''}`);
        } else {
            $('#register_selected').removeClass('btn-primary').addClass('btn-success');
            $('#register_selected').html('<i class="bi bi-check-circle me-2"></i>Register Selected');
        }
    });
}

function registerCourses(courseIds) {
    if (!currentStudentId) {
        showAlert('danger', 'No student selected.');
        return;
    }

    showLoading();

    $.post('', {
        action: 'register_courses',
        student_id: currentStudentId,
        course_ids: courseIds
    }, function(response) {
        hideLoading();
        
        if (response.success) {
            showAlert('success', response.message);
            // Refresh the course list
            loadStudentInfo(currentStudentId);
        } else {
            showAlert('danger', response.message);
        }
    }, 'json').fail(function() {
        hideLoading();
        showAlert('danger', 'Error registering courses. Please try again.');
    });
}

function unregisterCourse(courseId) {
    if (!confirm('Are you sure you want to unregister this course?')) {
        return;
    }

    $.post('', {
        action: 'unregister_course',
        student_id: currentStudentId,
        course_id: courseId
    }, function(response) {
        if (response.success) {
            showAlert('success', response.message);
            // Refresh the course list
            loadStudentInfo(currentStudentId);
        } else {
            showAlert('danger', response.message);
        }
    }, 'json');
}

function selectAllCourses() {
    $('.course-checkbox').prop('checked', true).trigger('change');
}

function showRegistrationStats() {
    // Implementation for showing statistics
    showAlert('info', 'Registration statistics feature coming soon!');
}

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // Remove existing alerts
    $('.alert').remove();

    // Add new alert at the top of the card body
    $('.card-body').first().prepend(alertHtml);

    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

function showLoading() {
    // Add loading indicator
    if (!$('#loading-indicator').length) {
        $('body').append('<div id="loading-indicator" class="position-fixed top-50 start-50 translate-middle" style="z-index: 9999;"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>');
    }
}

function hideLoading() {
    $('#loading-indicator').remove();
}

// Add hover effects for search results
$(document).on('mouseenter', '.student-result', function() {
    $(this).addClass('bg-light');
}).on('mouseleave', '.student-result', function() {
    $(this).removeClass('bg-light');
});
</script>

<style>
.student-result:hover {
    background-color: #f8f9fa !important;
}

#search_results {
    border-top: none !important;
    margin-top: -1px;
}

.position-relative {
    position: relative !important;
}
</style>

<?php include_once '../includes/footer.php'; ?>