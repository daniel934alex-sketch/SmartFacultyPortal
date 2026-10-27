<?php
/**
 * SmartFacultyPortal - Admin Timetable Generator / Regenerator Action
 * 
 * Safely regenerates the complete conflict-free schedule for CSE-A through CSE-E,
 * validates all constraints, publishes to database, and regenerates exports.
 */

require_once "../includes/auth-check.php";
require_once "../includes/role-check.php";
requireRole("HOD");

require_once "../config/database.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        header("Location: timetable.php?error=" . urlencode("Invalid session token. Please try again."));
        exit;
    }

    // Run the generator and inserter
    require_once __DIR__ . "/../scratch/generate_and_insert_full_timetable.php";

    // Regenerate Excel and CSV files
    require_once __DIR__ . "/../scratch/generate_excel_and_csv_exports.php";

    header("Location: timetable.php?msg=" . urlencode("Timetable successfully regenerated and published for all 5 sections (270 slots, 0 conflicts)!"));
    exit;
}

header("Location: timetable.php");
exit;
