<?php
require_once "config/database.php";

echo "--- FACULTY USERS ---\n";
$res = $conn->query("
    SELECT u.user_id, u.username, u.role, f.faculty_id, f.faculty_code, f.faculty_name, f.designation, d.department_name
    FROM users u
    JOIN faculty f ON f.user_id = u.user_id
    LEFT JOIN departments d ON d.department_id = f.department_id
    WHERE u.role = 'FACULTY'
    ORDER BY u.user_id
");
while ($r = $res->fetch_assoc()) {
    echo "User ID: {$r['user_id']} ({$r['username']}) -> Faculty ID: {$r['faculty_id']} ({$r['faculty_code']}) - {$r['faculty_name']} ({$r['designation']}, {$r['department_name']})\n";
}

echo "\n--- TIMETABLE ENTRIES BY FACULTY (PUBLISHED) ---\n";
$res = $conn->query("
    SELECT t.faculty_id, f.faculty_name, COUNT(*) as count 
    FROM timetable t 
    JOIN faculty f ON t.faculty_id = f.faculty_id 
    WHERE t.status = 'PUBLISHED' 
    GROUP BY t.faculty_id, f.faculty_name
");
while ($r = $res->fetch_assoc()) {
    echo "Faculty ID: {$r['faculty_id']} ({$r['faculty_name']}) -> {$r['count']} published entries\n";
}

echo "\n--- SAMPLE ENTRIES FOR FACULTY 2 (Prof. Priya Sharma) ---\n";
$res = $conn->query("
    SELECT t.day, p.period_number, p.start_time, p.end_time, s.subject_code, s.subject_name, s.subject_type, sec.section_name, c.room_number, t.status
    FROM timetable t
    JOIN periods p ON t.period_id = p.period_id
    JOIN subjects s ON t.subject_id = s.subject_id
    JOIN sections sec ON t.section_id = sec.section_id
    JOIN classrooms c ON t.classroom_id = c.classroom_id
    WHERE t.faculty_id = 2 AND t.status = 'PUBLISHED'
    ORDER BY FIELD(t.day, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'), p.period_number
");
while ($r = $res->fetch_assoc()) {
    echo "{$r['day']} P{$r['period_number']} ({$r['start_time']}-{$r['end_time']}) | {$r['subject_code']} - {$r['subject_name']} ({$r['subject_type']}) | {$r['room_number']} ({$r['section_name']})\n";
}
