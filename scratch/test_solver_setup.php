<?php
require_once __DIR__ . '/../config/database.php';

// First, check existing faculty_subjects
$facSubs = [];
$res = $conn->query("SELECT faculty_id, subject_id FROM faculty_subjects");
while ($r = $res->fetch_assoc()) {
    $facSubs[(int)$r['subject_id']][] = (int)$r['faculty_id'];
}

// Add the 2 administrator approved faculty for EE-PROJECT (subject 12):
// Faculty 15 (Prof. Manoj S) and Faculty 2 (Prof. Priya Sharma)
if (!in_array(15, $facSubs[12] ?? [])) {
    $facSubs[12][] = 15;
}
if (!in_array(2, $facSubs[12] ?? [])) {
    $facSubs[12][] = 2;
}

echo "EE-PROJECT eligible faculty IDs: " . implode(', ', $facSubs[12]) . "\n";

$days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY'];
$sections = [
    1 => 'CSE-A',
    2 => 'CSE-B',
    3 => 'CSE-C',
    4 => 'CSE-D',
    5 => 'CSE-E'
];

$sectionClassrooms = [
    1 => 1, // Room 36
    2 => 2, // Room 37
    3 => 3, // Room 38
    4 => 4, // Room 39
    5 => 5  // Room 40
];

echo "Solver test initial setup ready.\n";
