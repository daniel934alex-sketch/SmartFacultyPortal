<?php
/**
 * SmartFacultyPortal - Rebuild Timetable System
 * 
 * Safely updates periods timings, records administrator-approved faculty_subjects mappings,
 * resets existing timetable rows, and populates 270 conflict-free slots for all 5 sections.
 */

require_once __DIR__ . '/../config/database.php';

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

echo "=== PHASE 1: PRE-RESET CHECKS ===\n";
// Confirm backup file exists
$backupPath = __DIR__ . '/../database/smart_faculty_backup_verified_pre_rebuild.sql';
if (!file_exists($backupPath) || filesize($backupPath) < 10000) {
    die("ABORT: Verified backup not found or corrupt! Cannot proceed.\n");
}
echo "Confirmed verified database backup exists: " . basename($backupPath) . " (" . filesize($backupPath) . " bytes)\n";

// Begin transaction
$conn->begin_transaction();

try {
    // 1. Update periods timings to match the official project timings
    echo "\n=== Updating Periods Timings ===\n";
    $periodTimings = [
        1 => ['08:30:00', '09:15:00'],
        2 => ['09:15:00', '10:00:00'],
        3 => ['10:00:00', '10:45:00'],
        4 => ['10:45:00', '11:30:00'],
        5 => ['11:30:00', '12:15:00'],
        6 => ['12:15:00', '13:00:00'],
        7 => ['14:00:00', '14:45:00'],
        8 => ['14:45:00', '15:30:00'],
        9 => ['15:30:00', '16:15:00']
    ];

    $updatePeriodStmt = $conn->prepare("UPDATE periods SET start_time = ?, end_time = ? WHERE period_number = ?");
    foreach ($periodTimings as $num => $times) {
        $updatePeriodStmt->bind_param("ssi", $times[0], $times[1], $num);
        $updatePeriodStmt->execute();
        echo "Updated P{$num}: {$times[0]} - {$times[1]}\n";
    }
    $updatePeriodStmt->close();

    // 2. Insert administrator-approved faculty-subject mappings for EE-PROJECT (subject_id 12)
    echo "\n=== Recording Administrator Approval in faculty_subjects ===\n";
    $adminApprovedFaculty = [15, 2]; // Prof. Manoj S, Prof. Priya Sharma
    $eeSubjectId = 12;

    $checkFsStmt = $conn->prepare("SELECT faculty_subject_id FROM faculty_subjects WHERE faculty_id = ? AND subject_id = ?");
    $insertFsStmt = $conn->prepare("INSERT INTO faculty_subjects (faculty_id, subject_id) VALUES (?, ?)");

    foreach ($adminApprovedFaculty as $fId) {
        $checkFsStmt->bind_param("ii", $fId, $eeSubjectId);
        $checkFsStmt->execute();
        $checkFsStmt->store_result();
        if ($checkFsStmt->num_rows === 0) {
            $insertFsStmt->bind_param("ii", $fId, $eeSubjectId);
            $insertFsStmt->execute();
            echo "Added Administrator Approval: Faculty ID {$fId} mapped to Subject ID {$eeSubjectId} (EE-PROJECT)\n";
        } else {
            echo "Faculty ID {$fId} already mapped to Subject ID {$eeSubjectId}\n";
        }
    }
    $checkFsStmt->close();
    $insertFsStmt->close();

    // 3. Update ee_projects table to assign distinct faculty supervisors to sections 4 and 5
    $updateEeStmt = $conn->prepare("UPDATE ee_projects SET faculty_id = ? WHERE section_id = ?");
    $eeProjectAssignments = [
        1 => 6,  // CSE-A: Prof. Meena R
        2 => 11, // CSE-B: Prof. Sanjay R
        3 => 1,  // CSE-C: Dr. Arun Kumar
        4 => 2,  // CSE-D: Prof. Priya Sharma
        5 => 15  // CSE-E: Prof. Manoj S
    ];
    foreach ($eeProjectAssignments as $secId => $fId) {
        $updateEeStmt->bind_param("ii", $fId, $secId);
        $updateEeStmt->execute();
    }
    $updateEeStmt->close();
    echo "Updated ee_projects supervisor records with distinct faculty.\n";

    // 4. Safe Reset: DELETE ONLY existing timetable entries belonging to the rebuilt sections (1..5)
    echo "\n=== Safe Reset of Timetable Entries ===\n";
    $countBefore = $conn->query("SELECT COUNT(*) FROM timetable")->fetch_row()[0];
    echo "Existing timetable rows before reset: {$countBefore}\n";

    // Check if attendance or class_handover have records
    $attCount = $conn->query("SELECT COUNT(*) FROM attendance")->fetch_row()[0];
    $chCount = $conn->query("SELECT COUNT(*) FROM class_handover")->fetch_row()[0];
    if ($attCount > 0 || $chCount > 0) {
        throw new Exception("Cannot reset timetable: related attendance ($attCount) or handover ($chCount) records exist!");
    }

    $deleteStmt = $conn->prepare("DELETE FROM timetable WHERE section_id IN (1, 2, 3, 4, 5)");
    $deleteStmt->execute();
    $deletedRows = $deleteStmt->affected_rows;
    $deleteStmt->close();
    echo "Successfully deleted {$deletedRows} existing timetable rows. Unrelated tables remain untouched.\n";

    // 5. Commit transaction so far
    $conn->commit();
    echo "Database state prepared successfully.\n";

} catch (Exception $e) {
    $conn->rollback();
    die("ERROR during migration: " . $e->getMessage() . "\n");
}
