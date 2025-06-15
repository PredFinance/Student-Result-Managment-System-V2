<?php
$page_title = "Course Registration";
$breadcrumb = "Course Registration";

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user is logged in and is student
if (!is_logged_in() || !has_role('student')) {
    set_flash_message('danger', 'You must be logged in as a student to access this page');
    redirect(BASE_URL);
}

$db = new Database();
$student_id = $_SESSION['student_id'];
$institution_id = get_institution_id();

// Get current session and semester
$current_session = get_current_session($institution_id);
$current_semester = get_current_semester($institution_id);

if (!$current_session || !$current_semester) {
    set_flash_message('warning', 'No active session or semester found. Please contact admin.');
    redirect(BASE_URL . '/student/dashboard.php');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'load_courses') {
        // Get student's department
        $db->query("SELECT department_id FROM students WHERE student_id = :student_id");
        $db->bind(':student_id', $student_id);
        $student = $db->single();

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }

        // Get available courses for this student's department
        $db->query("SELECT c.course_id, c.course_code, c.course_title, c.credit_units,
                    CASE WHEN cr.registration_id IS NOT NULL THEN 1 ELSE 0 END as is_registered,
                    (SELECT COUNT(*) FROM course_registrations cr2 WHERE cr2.course_id = c.course_id) as total_registrations
                    FROM courses c
                    LEFT JOIN course_registrations cr ON c.course_id = cr.course_id 
                        AND cr.student_id = :student_id 
                        AND cr.session_id = :session_id 
                        AND cr.semester_id = :semester_id
                    WHERE c.department_id = :department_id 
                    AND c.institution_id = :institution_id
                    AND c.is_active = 1
                    ORDER BY c.course_code");

        $db->bind(':student_id', $student_id);
        $db->bind(':session_id', $current_session['session_id']);
        $db->bind(':semester_id', $current_semester['semester_id']);
        $db->bind(':department_id', $student['department_id']);
        $db->bind(':institution_id', $institution_id);

        $courses = $db->resultSet();

        echo json_encode([
            'success' => true,
            'courses' => $courses,
            'session' => $current_session,
            'semester' => $current_semester
        ]);
        exit;
    }

    if ($_POST['action'] === 'register_courses') {
        $course_ids = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];

        if (empty($course_ids)) {
            echo json_encode(['success' => false, 'message' => 'No courses selected']);
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

            echo json_encode(['success' => true, 'message' => $message]);
        } catch (Exception $e) {
            $db->cancelTransaction();
            echo json_encode(['success' => false, 'message' => 'Error registering courses: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($_POST['action'] === 'unregister_course') {
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
            echo json_encode(['success' => false, 'message' => 'Cannot unregister course. You have results for this course.']);
            exit;
        }

        $db->query("DELETE FROM course_registrations 
                    WHERE student_id = :student_id AND course_id = :course_id 
                    AND session_id = :session_id AND semester_id = :semester_id");
        $db->bind(':student_id', $student_id);
        $db->bind(':course_id', $course_id);
        $db->bind(':session_id', $current_session['session_id']);
        $db->bind(':semester_id', $current_semester['semester_id']);

        if ($db->execute()) {
            echo json_encode(['success' => true, 'message' => 'Course unregistered successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error unregistering course']);
        }
        exit;
    }
}

include_once 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-journal-plus me-2"></i>Course Registration
                    </h5>
                    <small class="text-muted" id="session_info">Loading session information...</small>
                </div>
                
                <div class="card-body">
                    <!-- Registration Summary -->
                    <div class="alert alert-info" id="registration_summary" style="display: none;">
                        <i class="bi bi-info-circle me-2"></i>
                        <span id="summary_text"></span>
                    </div>
                    
                    <!-- Course Selection -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">
                                    <i class="bi bi-journal-bookmark me-2"></i>Available Courses
                                    <span id="course_counter" class="badge bg-secondary ms-2">0</span>
                                </h6>
                                <div>
                                    <button class="btn btn-success btn-sm" id="register_selected" style="display: none;">
                                        <i class="bi bi-check-circle me-2"></i>Register Selected
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="selectAllCourses()">
                                        <i class="bi bi-check-all me-2"></i>Select All Available
                                    </button>
                                </div>
                            </div>
                            
                            <div id="courses_panel">
                                <div class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading courses...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Loading available courses...</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Registration Guide -->
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="bi bi-info-circle me-2"></i>Registration Guide
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle text-success me-2"></i>
                                            Select courses you want to register for
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-check-circle text-success me-2"></i>
                                            Click "Register Selected" to confirm
                                        </li>
                                        <li class="mb-2">
                                            <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                                            You can unregister courses if no results exist
                                        </li>
                                        <li class="mb-0">
                                            <i class="bi bi-info-circle text-info me-2"></i>
                                            Contact admin for course-related issues
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
console.log('Course registration page loaded');

// Load courses on page load
$(document).ready(function() {
    console.log('Document ready - loading courses');
    loadCourses();
    
    // Register selected courses button handler with event delegation
    $(document).on('click', '#register_selected', function(e) {
        e.preventDefault();
        console.log('Register button clicked');
        
        const selectedCourses = [];
        $('.course-checkbox:checked').each(function() {
            selectedCourses.push($(this).val());
        });
        
        console.log('Selected courses:', selectedCourses);
        
        if (selectedCourses.length === 0) {
            showAlert('warning', 'Please select at least one course to register.');
            return;
        }
        
        registerCourses(selectedCourses);
    });
});

function loadCourses() {
    console.log('Loading courses...');
    
    $.post('', {
        action: 'load_courses'
    }, function(response) {
        console.log('Load courses response:', response);
        
        if (response.success) {
            displaySessionInfo(response.session, response.semester);
            displayCourses(response.courses);
        } else {
            showAlert('danger', response.message);
            $('#courses_panel').html(`
                <div class="text-center py-4">
                    <i class="bi bi-exclamation-triangle display-4 text-warning"></i>
                    <h5 class="text-muted mt-3">Error Loading Courses</h5>
                    <p class="text-muted">${response.message}</p>
                </div>
            `);
        }
    }, 'json').fail(function(xhr, status, error) {
        console.error('AJAX Error:', xhr, status, error);
        showAlert('danger', 'Error loading courses. Please refresh the page.');
    });
}

function displaySessionInfo(session, semester) {
    $('#session_info').html(`
        Current Session: <strong>${session.session_name}</strong> | 
        Current Semester: <strong>${semester.semester_name}</strong>
    `);
}

function displayCourses(courses) {
    console.log('Displaying courses:', courses);
    $('#course_counter').text(courses.length);
    
    if (courses.length === 0) {
        $('#courses_panel').html(`
            <div class="text-center py-4">
                <i class="bi bi-journal-x display-4 text-muted"></i>
                <h5 class="text-muted mt-3">No Courses Available</h5>
                <p class="text-muted">No courses found for your department.</p>
            </div>
        `);
        return;
    }

    let html = '<div class="row">';
    let registeredCount = 0;
    let availableCount = 0;

    courses.forEach(function(course) {
        const isRegistered = course.is_registered == 1;
        if (isRegistered) {
            registeredCount++;
        } else {
            availableCount++;
        }
        
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
                                    <br><small><i class="bi bi-people me-1"></i>${course.total_registrations || 0} students registered</small>
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
    $('#registration_summary').show();
    $('#summary_text').html(`
        <strong>Registration Summary:</strong> ${registeredCount} of ${courses.length} courses registered for current semester.
        ${availableCount > 0 ? `<br><strong>${availableCount} courses available for registration.</strong>` : ''}
    `);

    $('#courses_panel').html(html);

    // Show/hide register button
    if (availableCount > 0) {
        $('#register_selected').show();
        console.log('Register button shown - available courses:', availableCount);
    } else {
        $('#register_selected').hide();
        console.log('Register button hidden - no available courses');
    }

    // Update checkbox change handler with event delegation
    $(document).off('change', '.course-checkbox').on('change', '.course-checkbox', function() {
        const checkedCount = $('.course-checkbox:checked').length;
        console.log('Checkbox changed, checked count:', checkedCount);
        
        if (checkedCount > 0) {
            $('#register_selected').html(`<i class="bi bi-check-circle me-2"></i>Register ${checkedCount} Course${checkedCount > 1 ? 's' : ''}`);
            $('#register_selected').removeClass('btn-success').addClass('btn-warning');
        } else {
            $('#register_selected').html('<i class="bi bi-check-circle me-2"></i>Register Selected');
            $('#register_selected').removeClass('btn-warning').addClass('btn-success');
        }
    });
}

function selectAllCourses() {
    console.log('Selecting all available courses');
    $('.course-checkbox').prop('checked', true).trigger('change');
}

function registerCourses(courseIds) {
    console.log('Registering courses:', courseIds);
    
    // Show loading state on button
    const originalButtonText = $('#register_selected').html();
    $('#register_selected').html('<i class="spinner-border spinner-border-sm me-2"></i>Registering...').prop('disabled', true);

    $.post('', {
        action: 'register_courses',
        course_ids: courseIds
    }, function(response) {
        console.log('Registration response:', response);
        
        // Restore button
        $('#register_selected').html(originalButtonText).prop('disabled', false);
        
        if (response.success) {
            showAlert('success', response.message);
            // Refresh the course list
            setTimeout(() => loadCourses(), 1000);
        } else {
            showAlert('danger', response.message);
        }
    }, 'json').fail(function(xhr, status, error) {
        console.error('Registration AJAX Error:', xhr, status, error);
        
        // Restore button
        $('#register_selected').html(originalButtonText).prop('disabled', false);
        
        showAlert('danger', 'Error registering courses. Please try again.');
    });
}

function unregisterCourse(courseId) {
    console.log('Unregistering course:', courseId);
    
    if (!confirm('Are you sure you want to unregister this course?')) {
        return;
    }

    $.post('', {
        action: 'unregister_course',
        course_id: courseId
    }, function(response) {
        console.log('Unregister response:', response);
        
        if (response.success) {
            showAlert('success', response.message);
            // Refresh the course list
            setTimeout(() => loadCourses(), 1000);
        } else {
            showAlert('danger', response.message);
        }
    }, 'json').fail(function(xhr, status, error) {
        console.error('Unregister AJAX Error:', xhr, status, error);
        showAlert('danger', 'Error unregistering course. Please try again.');
    });
}

function showAlert(type, message) {
    console.log('Showing alert:', type, message);
    
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // Remove existing alerts
    $('.alert').remove();

    // Add new alert at the top of the card body
    $('.card-body').first().prepend(alertHtml);

    // Auto-hide success messages after 5 seconds
    if (type === 'success') {
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    }
}
</script>

<?php include_once 'includes/footer.php'; ?>
