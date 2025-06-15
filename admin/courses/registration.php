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
    redirect(ADMIN_URL . '/settings/academic.php');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'get_student_courses') {
        $student_id = clean_input($_POST['student_id']);

        // Get student information first
        $db->query("SELECT s.*, d.department_name, l.level_name 
                    FROM students s
                    JOIN departments d ON s.department_id = d.department_id
                    JOIN levels l ON s.level_id = l.level_id
                    WHERE s.student_id = :student_id AND s.institution_id = :institution_id");
        $db->bind(':student_id', $student_id);
        $db->bind(':institution_id', $institution_id);
        $student = $db->single();

        if (!$student) {
            echo json_encode([
                'success' => false,
                'message' => 'Student not found'
            ]);
            exit;
        }

        // Use the same query from fetch.php
        $db->query("SELECT c.*, d.department_name, d.department_code,
                    (SELECT COUNT(*) FROM course_registrations cr WHERE cr.course_id = c.course_id) as total_registrations,
                    (SELECT COUNT(*) FROM results r 
                     JOIN course_registrations cr ON r.registration_id = cr.registration_id 
                     WHERE cr.course_id = c.course_id) as total_results,
                    CASE WHEN existing_reg.registration_id IS NOT NULL THEN 1 ELSE 0 END as is_registered
                    FROM courses c
                    JOIN departments d ON c.department_id = d.department_id
                    LEFT JOIN course_registrations existing_reg ON c.course_id = existing_reg.course_id 
                        AND existing_reg.student_id = :student_id 
                        AND existing_reg.session_id = :session_id 
                        AND existing_reg.semester_id = :semester_id
                    WHERE c.institution_id = :institution_id
                    AND c.department_id = :department_id
                    AND c.is_active = 1
                    ORDER BY c.course_code");

        $db->bind(':institution_id', $institution_id);
        $db->bind(':department_id', $student['department_id']);
        $db->bind(':student_id', $student_id);
        $db->bind(':session_id', $current_session['session_id']);
        $db->bind(':semester_id', $current_semester['semester_id']);

        $courses = $db->resultSet();

        echo json_encode([
            'success' => true,
            'student' => $student,
            'courses' => $courses,
            'total_count' => count($courses)
        ]);
        exit;
    }

    if ($_POST['action'] === 'register_courses') {
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

    if ($_POST['action'] === 'unregister_course') {
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

    if ($_POST['action'] === 'fetch_courses_debug') {
        $student_id = clean_input($_POST['student_id']);
        
        // Get student info
        $db->query("SELECT department_id FROM students WHERE student_id = :student_id");
        $db->bind(':student_id', $student_id);
        $student = $db->single();
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }
        
        // Step 1: Check if courses exist in department
        $db->query("SELECT course_id, course_code, course_title, credit_units, is_active 
                    FROM courses 
                    WHERE department_id = :department_id 
                    AND institution_id = :institution_id");
        $db->bind(':department_id', $student['department_id']);
        $db->bind(':institution_id', $institution_id);
        $all_dept_courses = $db->resultSet();
        
        // Step 2: Check active courses only
        $db->query("SELECT course_id, course_code, course_title, credit_units 
                    FROM courses 
                    WHERE department_id = :department_id 
                    AND institution_id = :institution_id 
                    AND is_active = 1");
        $db->bind(':department_id', $student['department_id']);
        $db->bind(':institution_id', $institution_id);
        $active_courses = $db->resultSet();
        
        // Step 3: Check existing registrations
        $db->query("SELECT course_id FROM course_registrations 
                    WHERE student_id = :student_id 
                    AND session_id = :session_id 
                    AND semester_id = :semester_id");
        $db->bind(':student_id', $student_id);
        $db->bind(':session_id', $current_session['session_id']);
        $db->bind(':semester_id', $current_semester['semester_id']);
        $registered_courses = $db->resultSet();
        
        echo json_encode([
            'success' => true,
            'debug_info' => [
                'all_dept_courses' => $all_dept_courses,
                'active_courses' => $active_courses,
                'registered_courses' => $registered_courses,
                'department_id' => $student['department_id'],
                'session_id' => $current_session['session_id'],
                'semester_id' => $current_semester['semester_id']
            ]
        ]);
        exit;
    }

    if ($_POST['action'] === 'fetch_courses_for_student') {
        $student_id = clean_input($_POST['student_id']);
        
        // Get student info
        $db->query("SELECT department_id FROM students WHERE student_id = :student_id AND institution_id = :institution_id");
        $db->bind(':student_id', $student_id);
        $db->bind(':institution_id', $institution_id);
        $student = $db->single();
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            exit;
        }
        
        // Get all courses in department with detailed info
        $db->query("SELECT c.course_id, c.course_code, c.course_title, c.credit_units, c.is_active,
                    CASE WHEN cr.registration_id IS NOT NULL THEN 1 ELSE 0 END as is_registered,
                    (SELECT COUNT(*) FROM course_registrations cr2 WHERE cr2.course_id = c.course_id) as total_registrations,
                    (SELECT COUNT(*) FROM results r 
                     JOIN course_registrations cr3 ON r.registration_id = cr3.registration_id 
                     WHERE cr3.course_id = c.course_id) as total_results,
                    c.created_at
                    FROM courses c
                    LEFT JOIN course_registrations cr ON c.course_id = cr.course_id 
                        AND cr.student_id = :student_id 
                        AND cr.session_id = :session_id 
                        AND cr.semester_id = :semester_id
                    WHERE c.department_id = :department_id 
                    AND c.institution_id = :institution_id
                    ORDER BY c.is_active DESC, c.course_code");
        
        $db->bind(':student_id', $student_id);
        $db->bind(':session_id', $current_session['session_id']);
        $db->bind(':semester_id', $current_semester['semester_id']);
        $db->bind(':department_id', $student['department_id']);
        $db->bind(':institution_id', $institution_id);
        
        $all_courses = $db->resultSet();
        
        // Separate active and inactive courses
        $active_courses = array_filter($all_courses, function($course) { return $course['is_active'] == 1; });
        $inactive_courses = array_filter($all_courses, function($course) { return $course['is_active'] == 0; });
        
        echo json_encode([
            'success' => true,
            'all_courses' => $all_courses,
            'active_courses' => array_values($active_courses),
            'inactive_courses' => array_values($inactive_courses),
            'stats' => [
                'total' => count($all_courses),
                'active' => count($active_courses),
                'inactive' => count($inactive_courses),
                'registered' => count(array_filter($all_courses, function($c) { return $c['is_registered'] == 1; }))
            ]
        ]);
        exit;
    }
}

// Get all students with their registration counts
$db->query("SELECT s.student_id, s.matric_number, s.first_name, s.last_name, 
            d.department_name, l.level_name,
            (SELECT COUNT(*) FROM course_registrations cr 
             WHERE cr.student_id = s.student_id 
             AND cr.session_id = :session_id 
             AND cr.semester_id = :semester_id) as registered_courses,
            (SELECT COUNT(*) FROM courses c 
             WHERE c.department_id = s.department_id 
             AND c.is_active = 1) as available_courses
            FROM students s
            JOIN departments d ON s.department_id = d.department_id
            JOIN levels l ON s.level_id = l.level_id
            WHERE s.institution_id = :institution_id 
            AND s.status = 'Active'
            ORDER BY s.matric_number");
$db->bind(':institution_id', $institution_id);
$db->bind(':session_id', $current_session['session_id']);
$db->bind(':semester_id', $current_semester['semester_id']);
$all_students = $db->resultSet();

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
                    <!-- Students List -->
                    <?php if (empty($all_students)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-people display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No Students Found</h4>
                            <p class="text-muted">No active students found in the system.</p>
                            <a href="../students/add.php" class="btn btn-primary">
                                <i class="bi bi-person-plus me-2"></i>Add New Student
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Matric Number</th>
                                        <th>Student Name</th>
                                        <th>Department</th>
                                        <th>Level</th>
                                        <th>Courses Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_students as $student): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?php echo htmlspecialchars($student['matric_number']); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['department_name']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($student['level_name']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $student['registered_courses'] > 0 ? 'success' : 'warning'; ?>">
                                                    <?php echo $student['registered_courses']; ?>/<?php echo $student['available_courses']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" 
                                                        onclick="registerCourses(<?php echo $student['student_id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')">
                                                    <i class="bi bi-journal-plus me-2"></i>Register Courses
                                                </button>
                                                <a href="../students/view.php?id=<?php echo $student['student_id']; ?>" 
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Course Registration Modal -->
<div class="modal fade" id="courseRegistrationModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-journal-check me-2"></i>Course Registration
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Student Info -->
                <div class="card border-primary mb-4">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-person-badge me-2"></i>Student Information
                        </h6>
                    </div>
                    <div class="card-body" id="modal_student_info">
                        <!-- Student info will be loaded here -->
                    </div>
                </div>
                
                <!-- Courses -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-journal-bookmark me-2"></i>Available Courses
                            <span id="modal_course_counter" class="badge bg-secondary ms-2">0</span>
                        </h6>
                        <div>
                            <button class="btn btn-success btn-sm" id="modal_register_selected" style="display: none;">
                                <i class="bi bi-check-circle me-2"></i>Register Selected
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="selectAllModalCourses()">
                                <i class="bi bi-check-all me-2"></i>Select All
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="modal_courses_panel">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading courses...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading available courses...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentModalStudentId = null;

// Add console logging for debugging
console.log('Registration page loaded');

function registerCourses(studentId, studentName) {
    console.log('registerCourses called with:', studentId, studentName);
    currentModalStudentId = studentId;
    
    // Show modal
    $('#courseRegistrationModal').modal('show');
    
    // Load student courses
    loadModalStudentCourses(studentId);
}

function loadModalStudentCourses(studentId) {
    console.log('Loading courses for student:', studentId);
    
    // Show loading
    $('#modal_courses_panel').html(`
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading courses...</span>
            </div>
            <p class="mt-2 text-muted">Loading available courses...</p>
        </div>
    `);
    
    $.post('', {
        action: 'get_student_courses',
        student_id: studentId
    }, function(response) {
        console.log('Course loading response:', response);
        if (response.success) {
            displayModalStudentInfo(response.student);
            displayModalCourses(response.courses);
        } else {
            showModalAlert('danger', response.message);
            $('#modal_courses_panel').html(`
                <div class="text-center py-4">
                    <i class="bi bi-exclamation-triangle display-4 text-warning"></i>
                    <h5 class="text-muted mt-3">Error Loading Courses</h5>
                    <p class="text-muted">${response.message}</p>
                </div>
            `);
        }
    }, 'json').fail(function(xhr, status, error) {
        console.error('AJAX Error:', xhr, status, error);
        showModalAlert('danger', 'Error loading student information. Please try again.');
    });
}

function displayModalStudentInfo(student) {
    console.log('Displaying student info:', student);
    const html = `
        <div class="row">
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
        </div>
    `;
    $('#modal_student_info').html(html);
}

function displayModalCourses(courses) {
    console.log('Displaying courses:', courses);
    $('#modal_course_counter').text(courses.length);
    
    if (courses.length === 0) {
        $('#modal_courses_panel').html(`
            <div class="text-center py-4">
                <i class="bi bi-journal-x display-4 text-muted"></i>
                <h5 class="text-muted mt-3">No Courses Available</h5>
                <p class="text-muted">No courses found for this student's department.</p>
            </div>
        `);
        $('#modal_register_selected').hide();
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
                                     <button class="btn btn-sm btn-outline-danger ms-2" onclick="unregisterModalCourse(${course.course_id})">
                                         <i class="bi bi-x"></i>
                                     </button>` :
                                    `<input class="form-check-input modal-course-checkbox" type="checkbox" value="${course.course_id}" id="modal_course_${course.course_id}">
                                     <label class="form-check-label" for="modal_course_${course.course_id}"></label>`
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
            ${availableCount > 0 ? `<br><strong>${availableCount} courses available for registration.</strong>` : ''}
        </div>
    ` + html;

    $('#modal_courses_panel').html(html);

    // Show/hide register button based on available courses
    if (availableCount > 0) {
        $('#modal_register_selected').show();
        console.log('Register button shown - available courses:', availableCount);
        
        // Set up the click handler immediately after showing the button
        setupRegisterButtonHandler();
    } else {
        $('#modal_register_selected').hide();
        console.log('Register button hidden - no available courses');
    }

    // Update checkbox change handler
    $('.modal-course-checkbox').off('change').on('change', function() {
        updateRegisterButtonState();
    });
}

function setupRegisterButtonHandler() {
    // Remove any existing handlers to prevent duplicates
    $('#modal_register_selected').off('click.register');
    
    // Add the click handler with namespace
    $('#modal_register_selected').on('click.register', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Register button clicked');
        
        const selectedCourses = [];
        $('.modal-course-checkbox:checked').each(function() {
            selectedCourses.push($(this).val());
        });
        
        console.log('Selected courses:', selectedCourses);
        
        if (selectedCourses.length === 0) {
            showModalAlert('warning', 'Please select at least one course to register.');
            return false;
        }
        
        registerModalCourses(selectedCourses);
        return false;
    });
}

function updateRegisterButtonState() {
    const checkedCount = $('.modal-course-checkbox:checked').length;
    console.log('Checkbox changed, checked count:', checkedCount);
    
    if (checkedCount > 0) {
        $('#modal_register_selected').html(`<i class="bi bi-check-circle me-2"></i>Register ${checkedCount} Course${checkedCount > 1 ? 's' : ''}`);
        $('#modal_register_selected').removeClass('btn-success').addClass('btn-warning');
    } else {
        $('#modal_register_selected').html('<i class="bi bi-check-circle me-2"></i>Register Selected');
        $('#modal_register_selected').removeClass('btn-warning').addClass('btn-success');
    }
}

function selectAllModalCourses() {
    console.log('Selecting all courses');
    $('.modal-course-checkbox').prop('checked', true);
    updateRegisterButtonState();
}

function unregisterModalCourse(courseId) {
    console.log('Unregistering course:', courseId);
    
    if (!confirm('Are you sure you want to unregister this course?')) {
        return;
    }

    $.post('', {
        action: 'unregister_course',
        student_id: currentModalStudentId,
        course_id: courseId
    }, function(response) {
        console.log('Unregister response:', response);
        if (response.success) {
            showModalAlert('success', response.message);
            // Refresh the course list
            loadModalStudentCourses(currentModalStudentId);
            // Refresh the main table after a delay
            setTimeout(() => location.reload(), 2000);
        } else {
            showModalAlert('danger', response.message);
        }
    }, 'json').fail(function(xhr, status, error) {
        console.error('Unregister AJAX Error:', xhr, status, error);
        showModalAlert('danger', 'Error unregistering course. Please try again.');
    });
}

function registerModalCourses(courseIds) {
    console.log('Registering courses:', courseIds, 'for student:', currentModalStudentId);
    
    if (!currentModalStudentId) {
        showModalAlert('danger', 'No student selected.');
        return;
    }

    if (!courseIds || courseIds.length === 0) {
        showModalAlert('warning', 'No courses selected for registration.');
        return;
    }

    // Show loading state on button
    const originalButtonText = $('#modal_register_selected').html();
    $('#modal_register_selected').html('<i class="spinner-border spinner-border-sm me-2"></i>Registering...').prop('disabled', true);

    $.post('', {
        action: 'register_courses',
        student_id: currentModalStudentId,
        course_ids: courseIds
    }, function(response) {
        console.log('Registration response:', response);
        
        // Restore button
        $('#modal_register_selected').html(originalButtonText).prop('disabled', false);
        
        if (response.success) {
            showModalAlert('success', response.message);
            // Refresh the course list
            loadModalStudentCourses(currentModalStudentId);
            // Refresh the main table after a delay
            setTimeout(() => location.reload(), 2000);
        } else {
            showModalAlert('danger', response.message);
        }
    }, 'json').fail(function(xhr, status, error) {
        console.error('Registration AJAX Error:', xhr, status, error);
        
        // Restore button
        $('#modal_register_selected').html(originalButtonText).prop('disabled', false);
        
        showModalAlert('danger', 'Error registering courses. Please try again.');
    });
}

function showModalAlert(type, message) {
    console.log('Showing alert:', type, message);
    
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // Remove existing alerts in modal
    $('#courseRegistrationModal .alert').remove();

    // Add new alert at the top of the modal body
    $('#courseRegistrationModal .modal-body').prepend(alertHtml);

    // Auto-hide after 5 seconds for success messages
    if (type === 'success') {
        setTimeout(function() {
            $('#courseRegistrationModal .alert').fadeOut();
        }, 5000);
    }
}

// Document ready function
$(document).ready(function() {
    console.log('Document ready - setting up event handlers');
    
    // Modal event handlers
    $('#courseRegistrationModal').on('shown.bs.modal', function() {
        console.log('Modal shown');
    });
    
    $('#courseRegistrationModal').on('hidden.bs.modal', function() {
        console.log('Modal hidden');
        currentModalStudentId = null;
        // Clean up event handlers
        $('#modal_register_selected').off('click.register');
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>