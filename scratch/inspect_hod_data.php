<?php
require_once "config/database.php";

echo "--- PERIODS ---\n";
$res = $conn->query("SELECT * FROM periods ORDER BY period_number");
while ($r = $res->fetch_assoc()) {
    echo "P{$r['period_number']}: {$r['start_time']} - {$r['end_time']}\n";
}

echo "\n--- USERS with HOD or ADMIN role ---\n";
$res = $conn->query("SELECT u.user_id, u.username, u.role, f.faculty_id, f.faculty_name, f.department FROM users u LEFT JOIN faculty f ON f.user_id = u.user_id WHERE u.role IN ('HOD', 'ADMIN')");
while ($r = $res->fetch_assoc()) {
    echo "User: {$r['username']} (ID: {$r['user_id']}, Role: {$r['role']}) -> Faculty: {$r['faculty_name']} (ID: {$r['faculty_id']}, Dept: {$r['department']})\n";
}

echo "\n--- TIMETABLE ENTRIES for HOD/ADMIN faculty ---\n";
$res = $conn->query("
    SELECT t.timetable_id, t.faculty_id, f.faculty_name, t.day, p.period_number, s.subject_code, s.subject_name, sec.section_name, c.room_number, t.status 
    FROM timetable t 
    JOIN faculty f ON t.faculty_id = f.faculty_id 
    JOIN periods p ON t.period_id = p.period_id 
    JOIN subjects s ON t.subject_id = s.subject_id 
    JOIN sections sec ON t.section_id = sec.section_id 
    JOIN classrooms c ON t.classroom_id = c.classroom_id 
    WHERE f.user_id IN (SELECT user_id FROM users WHERE role IN ('HOD', 'ADMIN')) 
    ORDER BY t.day, p.period_number
");
while ($r = $res->fetch_assoc()) {
    echo "{$r['faculty_name']} | {$r['day']} P{$r['period_number']} | {$r['subject_code']} - {$r['subject_name']} | Sec: {$r['section_name']} | Room: {$r['room_number']} | Status: {$r['status']}\n";
}

echo "\n--- ALL TIMETABLE COUNT BY FACULTY ---\n";
$res = $conn->query("SELECT f.faculty_id, f.faculty_name, COUNT(t.timetable_id) as total_entries FROM faculty f LEFT JOIN timetable t ON f.faculty_id = t.faculty_id GROUP BY f.faculty_id, f.faculty_name");
while ($r = $res->fetch_assoc()) {
    echo "Faculty ID: {$r['faculty_id']} ({$r['faculty_name']}) -> {$r['total_entries']} entries\n";
}
