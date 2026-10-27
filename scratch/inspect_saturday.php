<?php
require_once __DIR__ . '/../config/database.php';

$res = $conn->query("
    SELECT sec.section_name, t.day, p.period_number, s.subject_code, f.faculty_name, c.room_number, t.status
    FROM timetable t
    JOIN sections sec ON sec.section_id = t.section_id
    JOIN periods p ON p.period_id = t.period_id
    JOIN subjects s ON s.subject_id = t.subject_id
    JOIN faculty f ON f.faculty_id = t.faculty_id
    JOIN classrooms c ON c.classroom_id = t.classroom_id
    WHERE t.day = 'SATURDAY'
    ORDER BY p.period_number, sec.section_name
");

echo "Saturday slots currently in DB:\n";
while ($r = $res->fetch_assoc()) {
    echo "P{$r['period_number']} | {$r['section_name']} | {$r['subject_code']} | {$r['faculty_name']} | Room {$r['room_number']}\n";
}
