<?php
require_once __DIR__ . '/../config/database.php';

$res = $conn->query("
    SELECT sec.section_name, s.subject_code, s.subject_type, COUNT(*) as cnt
    FROM timetable t
    JOIN sections sec ON sec.section_id = t.section_id
    JOIN subjects s ON s.subject_id = t.subject_id
    GROUP BY sec.section_name, s.subject_code, s.subject_type
    ORDER BY sec.section_name, s.subject_type, s.subject_code
");

$data = [];
while ($r = $res->fetch_assoc()) {
    $data[$r['section_name']][] = $r;
}

foreach ($data as $sec => $rows) {
    echo "\n=== $sec Subject Counts ===\n";
    $total = 0;
    foreach ($rows as $r) {
        echo sprintf("  %-12s (%-7s): %d\n", $r['subject_code'], $r['subject_type'], $r['cnt']);
        $total += $r['cnt'];
    }
    echo "  TOTAL: $total\n";
}
