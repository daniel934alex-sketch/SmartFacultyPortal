<?php
require_once __DIR__ . '/../config/database.php';

$res = $conn->query("
    SELECT t.day, p.period_number, s.subject_code, f.faculty_name, c.room_number, t.status
    FROM timetable t
    JOIN periods p ON p.period_id = t.period_id
    JOIN subjects s ON s.subject_id = t.subject_id
    JOIN faculty f ON f.faculty_id = t.faculty_id
    JOIN classrooms c ON c.classroom_id = t.classroom_id
    WHERE t.section_id = 1
    ORDER BY FIELD(t.day, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'), p.period_number
");

echo "=== CSE-A CURRENT TIMETABLE ===\n";
while ($r = $res->fetch_assoc()) {
    echo sprintf("%-10s | P%d | %-10s | %-20s | Room %-5s | %s\n",
        $r['day'], $r['period_number'], $r['subject_code'], $r['faculty_name'], $r['room_number'], $r['status']
    );
}
