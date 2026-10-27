<?php
require_once __DIR__ . '/../config/database.php';

$testStudents = [
    'student01' => 'CSE-A',
    'student02' => 'CSE-B',
    'student03' => 'CSE-C',
    'student04' => 'CSE-D',
    'student05' => 'CSE-E'
];

echo "=== TESTING STUDENT TIMETABLE DATA LOADING ===\n";

foreach ($testStudents as $username => $expectedSec) {
    // 1. Get student
    $stmt = $conn->prepare("
        SELECT s.student_id, s.student_name, s.section_id, sec.section_name
        FROM students s
        JOIN users u ON u.user_id = s.user_id
        JOIN sections sec ON sec.section_id = s.section_id
        WHERE u.username = ?
    ");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $secId = (int)$student['section_id'];

    // 2. Query timetable
    $ttStmt = $conn->prepare("
        SELECT COUNT(*) as slot_count
        FROM timetable t
        WHERE t.section_id = ? AND t.status = 'PUBLISHED'
    ");
    $ttStmt->bind_param("i", $secId);
    $ttStmt->execute();
    $slotCount = $ttStmt->get_result()->fetch_assoc()['slot_count'];
    $ttStmt->close();

    // 3. Saturday project count
    $satStmt = $conn->prepare("
        SELECT COUNT(*) as sat_count, f.faculty_name
        FROM timetable t
        JOIN faculty f ON f.faculty_id = t.faculty_id
        WHERE t.section_id = ? AND t.day = 'SATURDAY' AND t.status = 'PUBLISHED'
        GROUP BY f.faculty_name
    ");
    $satStmt->bind_param("i", $secId);
    $satStmt->execute();
    $satRows = $satStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $satStmt->close();

    echo "Student: $username -> Section: {$student['section_name']} | Published Slots: $slotCount / 54 | Saturday Supervised: " . count($satRows) . " faculty assignments\n";
}
