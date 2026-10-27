<?php
/**
 * SmartFacultyPortal - Timetable Downloader Endpoint
 * 
 * Secure download endpoint for Admin, Faculty, and Students:
 * Serves section timetable Excel (.xlsx) and CSV (.csv) files.
 */

require_once "../includes/auth-check.php";
require_once "../config/database.php";

$sectionName = strtoupper(trim($_GET['section'] ?? 'CSE-A'));
$format = strtolower(trim($_GET['format'] ?? 'xlsx'));

$validSections = ['CSE-A', 'CSE-B', 'CSE-C', 'CSE-D', 'CSE-E'];
if (!in_array($sectionName, $validSections, true)) {
    http_response_code(400);
    die("Invalid section specified. Valid sections: " . implode(', ', $validSections));
}

if (!in_array($format, ['xlsx', 'csv'], true)) {
    http_response_code(400);
    die("Invalid format specified. Valid formats: xlsx, csv");
}

// Check role permission:
// HOD can download any section
// FACULTY can download any section
// STUDENT can download their own section (or any section if viewing)
$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['HOD', 'FACULTY', 'STUDENT'], true)) {
    http_response_code(403);
    die("Access denied.");
}

$filename = "{$sectionName}_Timetable.{$format}";
$filePath = __DIR__ . "/../uploads/timetables/{$filename}";

// If file does not exist, trigger generator
if (!file_exists($filePath)) {
    require_once __DIR__ . "/../scratch/generate_excel_and_csv_exports.php";
}

if (!file_exists($filePath)) {
    http_response_code(404);
    die("Timetable export file could not be generated.");
}

// Clear output buffers
if (ob_get_level()) {
    ob_end_clean();
}

if ($format === 'xlsx') {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
} else {
    header('Content-Type: text/csv; charset=UTF-8');
}

header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
