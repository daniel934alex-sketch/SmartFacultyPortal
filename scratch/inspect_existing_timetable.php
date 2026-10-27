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
    ORDER BY sec.section_name, FIELD(t.day, 'MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'), p.period_number
");

$bySec = [];
while ($r = $res->fetch_assoc()) {
    $bySec[$r['section_name']][] = $r;
}

foreach ($bySec as $sec => $rows) {
    echo "Section $sec: " . count($rows) . " rows\n";
    $days = [];
    foreach ($rows as $r) {
        $days[$r['day']] = ($days[$r['day']] ?? 0) + 1;
    }
    foreach ($days as $d => $c) {
        echo "  $d: $c periods\n";
    }
}
