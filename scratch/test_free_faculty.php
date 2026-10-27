<?php
require_once __DIR__ . '/../config/database.php';

// Check Monday Period 1 for Dr. Arun Kumar (faculty_id = 1) or Prof. Priya Sharma (faculty_id = 2)
$date = '2026-10-26'; // Monday
$day = 'MONDAY';
$period_id = 1;
$original_faculty_id = 2; // Priya Sharma
$department_id = 1;

// 1. Check if Priya has a class
$stmt = $conn->prepare("
    SELECT t.timetable_id, t.section_id, sec.section_name, t.subject_id, s.subject_code, s.subject_name, c.room_number
    FROM timetable t
    JOIN sections sec ON sec.section_id = t.section_id
    JOIN subjects s ON s.subject_id = t.subject_id
    JOIN classrooms c ON c.classroom_id = t.classroom_id
    WHERE t.faculty_id = ? AND t.day = ? AND t.period_id = ? AND t.status = 'PUBLISHED'
");
$stmt->bind_param("isi", $original_faculty_id, $day, $period_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
echo "Class for Faculty 2 on Monday Period 1: " . json_encode($class) . PHP_EOL;

// 2. Check eligible alternates
$stmt2 = $conn->prepare("
    SELECT f.faculty_id, f.faculty_name, u.user_id,
           CASE WHEN fs.subject_id IS NOT NULL THEN 1 ELSE 0 END as teaches_subject
    FROM faculty f
    INNER JOIN users u ON u.user_id = f.user_id
    LEFT JOIN faculty_availability fa ON fa.faculty_id = f.faculty_id AND fa.availability_date = ? AND fa.period_id = ?
    LEFT JOIN timetable t ON t.faculty_id = f.faculty_id AND t.day = ? AND t.period_id = ? AND t.status = 'PUBLISHED'
    LEFT JOIN faculty_subjects fs ON fs.faculty_id = f.faculty_id AND fs.subject_id = ?
    WHERE u.status = 'ACTIVE' AND f.department_id = ? AND f.faculty_id != ?
      AND (fa.availability_id IS NULL OR fa.status = 'AVAILABLE')
      AND t.timetable_id IS NULL
    ORDER BY teaches_subject DESC, f.faculty_name ASC
");
$subject_id = $class ? $class['subject_id'] : 0;
$stmt2->bind_param("sisiisi", $date, $period_id, $day, $period_id, $subject_id, $department_id, $original_faculty_id);
$stmt2->execute();
$res = $stmt2->get_result();
echo "Eligible faculty count: " . $res->num_rows . PHP_EOL;
while ($row = $res->fetch_assoc()) {
    echo " - " . json_encode($row) . PHP_EOL;
}
