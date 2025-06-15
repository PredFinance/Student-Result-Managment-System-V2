<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user is logged in and is admin
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    redirect(BASE_URL);
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="results_template.csv"');

// Create CSV content
$output = fopen('php://output', 'w');

// Write headers
fputcsv($output, ['matric_number', 'course_code', 'ca_score', 'exam_score']);

// Write sample data
fputcsv($output, ['STU/2023/001', 'CSC101', '35', '55']);
fputcsv($output, ['STU/2023/001', 'MTH101', '38', '52']);
fputcsv($output, ['STU/2023/002', 'CSC101', '32', '48']);
fputcsv($output, ['STU/2023/002', 'MTH101', '40', '58']);

fclose($output);
exit;
?>
