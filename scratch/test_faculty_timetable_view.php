<?php
require_once __DIR__ . '/../config/database.php';

$res = $conn->query("
    SELECT f.faculty_id, f.faculty_name, f.faculty_code, COUNT(t.timetable_id) as total_periods
    FROM faculty f
    LEFT JOIN timetable t ON t.faculty_id = f.faculty_id AND t.status = 'PUBLISHED'
    GROUP BY f.faculty_id, f.faculty_name, f.faculty_code
    ORDER BY f.faculty_id
");

echo "=== FACULTY TIMETABLE WORKLOAD SUMMARY ===\n";
while ($r = $res->fetch_assoc()) {
    echo sprintf("[ID %2d] %-10s | %-22s : %2d periods scheduled\n",
        $r['faculty_id'], $r['faculty_code'], $r['faculty_name'], $r['total_periods']
    );
}

// Check Saturday project supervision breakdown
$res2 = $conn->query("
    SELECT f.faculty_name, sec.section_name, COUNT(*) as p_count
    FROM timetable t
    JOIN faculty f ON f.faculty_id = t.faculty_id
    JOIN sections sec ON sec.section_id = t.section_id
    WHERE t.day = 'SATURDAY' AND t.status = 'PUBLISHED'
    GROUP BY f.faculty_name, sec.section_name
    ORDER BY f.faculty_name, sec.section_name
");
echo "\n=== SATURDAY EE-PROJECT SUPERVISION BREAKDOWN ===\n";
while ($r = $res2->fetch_assoc()) {
    echo sprintf("%-22s supervising %-6s : %d periods\n", $r['faculty_name'], $r['section_name'], $r['p_count']);
}
