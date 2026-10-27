<?php
require_once __DIR__ . '/../config/database.php';

echo "--- FOREIGN KEYS REFERENCING TIMETABLE ---\n";
$res = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE REFERENCED_TABLE_SCHEMA = 'smart_faculty' 
      AND REFERENCED_TABLE_NAME = 'timetable'
");
$found = 0;
while ($r = $res->fetch_assoc()) {
    print_r($r);
    $found++;
}
if ($found === 0) {
    echo "No tables reference `timetable`!\n";
}

echo "\n--- SECTIONS ---\n";
$res = $conn->query("SELECT * FROM sections ORDER BY section_id");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['section_id']} | Name: {$r['section_name']} | Class ID: {$r['class_id']}\n";
}

echo "\n--- CLASSROOMS ---\n";
$res = $conn->query("SELECT * FROM classrooms ORDER BY classroom_id");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['classroom_id']} | Room: {$r['room_number']} | Type: {$r['room_type']} | Status: {$r['status']}\n";
}

echo "\n--- PERIODS ---\n";
$res = $conn->query("SELECT * FROM periods ORDER BY period_number");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['period_id']} | P{$r['period_number']} | {$r['start_time']} - {$r['end_time']}\n";
}
