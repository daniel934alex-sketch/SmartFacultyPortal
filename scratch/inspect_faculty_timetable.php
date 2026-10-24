<?php
require_once __DIR__ . '/../config/database.php';

$res = $conn->query("
    SELECT t.timetable_id, t.day, p.period_number, p.start_time, p.end_time, s.subject_code, s.subject_name, sec.section_name, c.room_number, c.room_type, t.status
    FROM timetable t
    JOIN periods p ON t.period_id=p.period_id
    JOIN subjects s ON t.subject_id=s.subject_id
    JOIN sections sec ON t.section_id=sec.section_id
    JOIN classrooms c ON t.classroom_id=c.classroom_id
    WHERE t.faculty_id=2
    ORDER BY FIELD(t.day, 'MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY'), p.period_number
");

echo "Faculty 2 Timetable:\n";
while ($r = $res->fetch_assoc()) {
    echo "{$r['day']} P{$r['period_number']} ({$r['start_time']}-{$r['end_time']}): {$r['subject_code']} - {$r['subject_name']} | Sec: {$r['section_name']} | Room: {$r['room_number']} ({$r['room_type']}) [{$r['status']}]\n";
}
