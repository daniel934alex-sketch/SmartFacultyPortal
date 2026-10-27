<?php
require_once __DIR__ . '/../config/database.php';

// Check what CSE-A, CSE-B, CSE-C in current database actually have on weekdays
$res = $conn->query("
    SELECT sec.section_name, s.subject_code, COUNT(*) as cnt
    FROM timetable t
    JOIN sections sec ON sec.section_id = t.section_id
    JOIN subjects s ON s.subject_id = t.subject_id
    WHERE t.day != 'SATURDAY'
    GROUP BY sec.section_name, s.subject_code
    ORDER BY sec.section_name, s.subject_code
");

while ($r = $res->fetch_assoc()) {
    echo "{$r['section_name']} | {$r['subject_code']} : {$r['cnt']}\n";
}
