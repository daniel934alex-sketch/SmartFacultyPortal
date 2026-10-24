<?php
require_once "config/database.php";

echo "--- TIMETABLE FOR USER 1 (HOD: Dr. Arun Kumar) ---\n";
$res = $conn->query("
    SELECT t.timetable_id, t.day, p.period_number, p.start_time, p.end_time,
           s.subject_code, s.subject_name, sec.section_name, c.room_number, c.room_type, t.status
    FROM timetable t
    JOIN periods p ON t.period_id = p.period_id
    JOIN subjects s ON t.subject_id = s.subject_id
    JOIN sections sec ON t.section_id = sec.section_id
    JOIN classrooms c ON t.classroom_id = c.classroom_id
    WHERE t.faculty_id = 1
    ORDER BY FIELD(t.day, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'), p.period_number
");
while ($r = $res->fetch_assoc()) {
    echo "Day: {$r['day']} | P{$r['period_number']} ({$r['start_time']}-{$r['end_time']}) | {$r['subject_code']} - {$r['subject_name']} | Sec: {$r['section_name']} | Room: {$r['room_number']} ({$r['room_type']}) | Status: {$r['status']}\n";
}

echo "\n--- CLASSES AND DEPARTMENTS ---\n";
$res = $conn->query("DESCRIBE classes");
if ($res) {
    while ($r = $res->fetch_assoc()) echo "classes: {$r['Field']} ({$r['Type']})\n";
    $c = $conn->query("SELECT * FROM classes");
    while ($r = $c->fetch_assoc()) print_r($r);
}
$res = $conn->query("DESCRIBE departments");
if ($res) {
    while ($r = $res->fetch_assoc()) echo "departments: {$r['Field']} ({$r['Type']})\n";
    $c = $conn->query("SELECT * FROM departments");
    while ($r = $c->fetch_assoc()) print_r($r);
}
