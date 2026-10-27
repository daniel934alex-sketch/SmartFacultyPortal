<?php
require_once __DIR__ . '/../config/database.php';
$r = $conn->query('DESCRIBE faculty_subjects');
while ($row = $r->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
}
echo "Sample data:\n";
$r2 = $conn->query('SELECT fs.*, f.faculty_name, s.subject_name FROM faculty_subjects fs JOIN faculty f ON f.faculty_id = fs.faculty_id JOIN subjects s ON s.subject_id = fs.subject_id LIMIT 10');
while ($row = $r2->fetch_assoc()) {
    echo json_encode($row) . PHP_EOL;
}
