<?php
require_once __DIR__ . '/../config/database.php';

echo "Attendance row count: " . $conn->query("SELECT COUNT(*) FROM attendance")->fetch_row()[0] . "\n";
echo "Class handover row count: " . $conn->query("SELECT COUNT(*) FROM class_handover")->fetch_row()[0] . "\n";
echo "Handover requests row count: " . $conn->query("SELECT COUNT(*) FROM handover_requests")->fetch_row()[0] . "\n";
echo "Timetable row count: " . $conn->query("SELECT COUNT(*) FROM timetable")->fetch_row()[0] . "\n";

$res = $conn->query("SELECT section_id, COUNT(*) as cnt, status FROM timetable GROUP BY section_id, status");
echo "--- Timetable counts by section and status ---\n";
while ($r = $res->fetch_assoc()) {
    echo "Section {$r['section_id']} | Status: {$r['status']} | Count: {$r['cnt']}\n";
}
