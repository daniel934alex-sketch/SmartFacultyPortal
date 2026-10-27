<?php
require_once __DIR__ . '/../config/database.php';

echo "--- FOREIGN KEYS REFERENCING TIMETABLE ---\n";
$res = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE REFERENCED_TABLE_SCHEMA = 'smart_faculty' 
      AND REFERENCED_TABLE_NAME = 'timetable'
");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "\n--- SECTIONS ---\n";
$res = $conn->query("SELECT * FROM sections ORDER BY section_id");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "\n--- CLASSROOMS ---\n";
$res = $conn->query("SELECT * FROM classrooms ORDER BY classroom_id");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "\n--- PERIODS ---\n";
$res = $conn->query("SELECT * FROM periods ORDER BY period_number");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "\n--- SUBJECTS ---\n";
$res = $conn->query("SELECT * FROM subjects ORDER BY subject_id");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "\n--- FACULTY ---\n";
$res = $conn->query("SELECT faculty_id, user_id, faculty_code, faculty_name, designation, is_hod FROM faculty ORDER BY faculty_id");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "\n--- EE_PROJECTS ---\n";
$res = $conn->query("SELECT * FROM ee_projects ORDER BY project_id");
while ($r = $res->fetch_assoc()) {
    print_r($r);
}

echo "\n--- FACULTY_SUBJECTS MAPPING ---\n";
$res = $conn->query("
    SELECT fs.faculty_subject_id, f.faculty_id, f.faculty_code, f.faculty_name, s.subject_id, s.subject_code, s.subject_name, s.subject_type
    FROM faculty_subjects fs
    JOIN faculty f ON fs.faculty_id = f.faculty_id
    JOIN subjects s ON fs.subject_id = s.subject_id
    ORDER BY s.subject_code, f.faculty_code
");
while ($r = $res->fetch_assoc()) {
    echo "{$r['subject_code']} ({$r['subject_type']}) -> [Faculty ID {$r['faculty_id']}] {$r['faculty_code']} - {$r['faculty_name']}\n";
}
