<?php
require_once __DIR__ . '/../config/database.php';

// 1. Load subjects and faculty eligibility
$subjects = [];
$res = $conn->query("SELECT * FROM subjects ORDER BY subject_id");
while ($r = $res->fetch_assoc()) {
    $subjects[(int)$r['subject_id']] = $r;
}

$facEligibility = [];
$res = $conn->query("SELECT faculty_id, subject_id FROM faculty_subjects");
while ($r = $res->fetch_assoc()) {
    $facEligibility[(int)$r['subject_id']][] = (int)$r['faculty_id'];
}

// Add administrator approved supervisors for EE-PROJECT (subject 12):
// Faculty 15 (Prof. Manoj S) and Faculty 2 (Prof. Priya Sharma)
if (!in_array(15, $facEligibility[12] ?? [])) {
    $facEligibility[12][] = 15;
}
if (!in_array(2, $facEligibility[12] ?? [])) {
    $facEligibility[12][] = 2;
}

$faculty = [];
$res = $conn->query("SELECT * FROM faculty ORDER BY faculty_id");
while ($r = $res->fetch_assoc()) {
    $faculty[(int)$r['faculty_id']] = $r;
}

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

$labRooms = [
    6, // LAB-1
    7, // LAB-2
    8  // LAB-3
];

// Weekday subject requirements per section (35 theory + 10 lab = 45 periods)
// Theory: DBMS(1):5, ADSA(2):5, KVD(3):5, OS(4):5, MATHS(5):5, ENG(6):5, QUANTS(7):5
// Labs:
// CSE-A: DBMS-LAB(11):5, ADSA-LAB(8):5
// CSE-B: KVD-LAB(9):5,   OS-LAB(10):5
// CSE-C: DBMS-LAB(11):5, ADSA-LAB(8):5
// CSE-D: KVD-LAB(9):5,   OS-LAB(10):5
// CSE-E: DBMS-LAB(11):5, KVD-LAB(9):5

$sectionRequirements = [
    1 => [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 5, 7 => 5, 11 => 5, 8 => 5],
    2 => [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 5, 7 => 5, 9 => 5,  10 => 5],
    3 => [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 5, 7 => 5, 11 => 5, 8 => 5],
    4 => [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 5, 7 => 5, 9 => 5,  10 => 5],
    5 => [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 5, 7 => 5, 11 => 5, 9 => 5],
];

echo "Setting up timetable generation...\n";
