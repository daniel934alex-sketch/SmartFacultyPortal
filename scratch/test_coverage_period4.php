<?php
require_once __DIR__ . '/../config/database.php';

$date = '2026-10-26'; // Monday
$day = 'MONDAY';
$period_id = 4;
$original_faculty_id = 2; // Priya Sharma
$department_id = 1;

$stmt = $conn->prepare("
    SELECT t.timetable_id, t.section_id, sec.section_name, t.subject_id, s.subject_code, s.subject_name, c.room_number, f.faculty_name
    FROM timetable t
    JOIN sections sec ON sec.section_id = t.section_id
    JOIN subjects s ON s.subject_id = t.subject_id
    JOIN classrooms c ON c.classroom_id = t.classroom_id
    JOIN faculty f ON f.faculty_id = t.faculty_id
    WHERE t.faculty_id = ? AND t.day = ? AND t.period_id = ? AND t.status = 'PUBLISHED'
");
$stmt->bind_param("isi", $original_faculty_id, $day, $period_id);
$stmt->execute();
$class = $stmt->get_result()->fetch_assoc();
echo "Class for Faculty 2 on Monday Period 4:\n" . json_encode($class, JSON_PRETTY_PRINT) . PHP_EOL;

$subject_id = $class['subject_id'];

// Check faculty teaching this subject who are free
$stmt_subj = $conn->prepare("
    SELECT f.faculty_id, f.faculty_name, u.user_id, 1 as is_subject_match
    FROM faculty f
    INNER JOIN users u ON u.user_id = f.user_id
    INNER JOIN faculty_subjects fs ON fs.faculty_id = f.faculty_id AND fs.subject_id = ?
    LEFT JOIN faculty_availability fa ON fa.faculty_id = f.faculty_id AND fa.availability_date = ? AND fa.period_id = ?
    LEFT JOIN timetable t ON t.faculty_id = f.faculty_id AND t.day = ? AND t.period_id = ? AND t.status = 'PUBLISHED'
    WHERE u.status = 'ACTIVE' AND f.department_id = ? AND f.faculty_id != ?
      AND (fa.availability_id IS NULL OR fa.status = 'AVAILABLE')
      AND t.timetable_id IS NULL
    ORDER BY f.faculty_name ASC
");
$stmt_subj->bind_param("isisiii", $subject_id, $date, $period_id, $day, $period_id, $department_id, $original_faculty_id);
$stmt_subj->execute();
$subj_matches = $stmt_subj->get_result()->fetch_all(MYSQLI_ASSOC);
echo "Free faculty teaching this subject: " . count($subj_matches) . PHP_EOL;
print_r($subj_matches);

// Check all free faculty in department
$stmt_all = $conn->prepare("
    SELECT f.faculty_id, f.faculty_name, u.user_id
    FROM faculty f
    INNER JOIN users u ON u.user_id = f.user_id
    LEFT JOIN faculty_availability fa ON fa.faculty_id = f.faculty_id AND fa.availability_date = ? AND fa.period_id = ?
    LEFT JOIN timetable t ON t.faculty_id = f.faculty_id AND t.day = ? AND t.period_id = ? AND t.status = 'PUBLISHED'
    WHERE u.status = 'ACTIVE' AND f.department_id = ? AND f.faculty_id != ?
      AND (fa.availability_id IS NULL OR fa.status = 'AVAILABLE')
      AND t.timetable_id IS NULL
    ORDER BY f.faculty_name ASC
");
$stmt_all->bind_param("sisiii", $date, $period_id, $day, $period_id, $department_id, $original_faculty_id);
$stmt_all->execute();
$all_free = $stmt_all->get_result()->fetch_all(MYSQLI_ASSOC);
echo "Total free faculty in department: " . count($all_free) . PHP_EOL;
print_r($all_free);
